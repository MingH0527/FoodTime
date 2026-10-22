<?php
session_start();
require_once 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}
$user_id      = $_SESSION['user_id'] ?? '';
$admin_id     = $user_id;
$admin_name   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin');
$current_page = 'products';

$flash = null;

/* ── Image base URL helper ───────────────────────────────────── */
function uploads_url(): string {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $parent     = rtrim(dirname(rtrim($script_dir, '/')), '/');
    return $parent . '/uploads/';
}
$UPLOADS_URL = uploads_url();

/* ── Stock badge helper ──────────────────────────────────────── */
function stockBadge($stock) {
    $stock = (int)$stock;
    if ($stock <= 0)  return '<span class="stock-badge stock-out"><i class="fas fa-ban"></i> Out of Stock</span>';
    if ($stock <= 10) return '<span class="stock-badge stock-low"><i class="fas fa-exclamation-triangle"></i> Low: ' . $stock . '</span>';
    return '<span class="stock-badge stock-ok"><i class="fas fa-check"></i> ' . $stock . ' left</span>';
}

const MAX_STOCK = 1000;

/* ── Flash from redirect ─────────────────────────────────────── */
if (isset($_GET['flash'])) {
    $fname = htmlspecialchars($_GET['name'] ?? '');
    if ($_GET['flash'] === 'added')   $flash = ['type'=>'success','msg'=>'"'.$fname.'" added to menu.'];
    if ($_GET['flash'] === 'updated') $flash = ['type'=>'success','msg'=>'"'.$fname.'" updated successfully.'];
}

/* ── POST: toggle / delete / restock ────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $pid  = trim($_POST['productid'] ?? '');
        $stmt = mysqli_prepare($connect, "UPDATE products SET is_available=IF(is_available=1,0,1) WHERE productid=?");
        mysqli_stmt_bind_param($stmt, "s", $pid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: Product.php" . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit();
    }

    if ($action === 'delete') {
        $pid = trim($_POST['productid'] ?? '');
        $row = mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT name FROM products WHERE productid='" . mysqli_real_escape_string($connect, $pid) . "'"));
        $stmt = mysqli_prepare($connect, "DELETE FROM products WHERE productid=?");
        mysqli_stmt_bind_param($stmt, "s", $pid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $flash = ['type' => 'success', 'msg' => '"' . htmlspecialchars($row['name'] ?? $pid) . '" removed.'];
    }

    if ($action === 'restock') {
        header('Content-Type: application/json');
        $pid  = trim($_POST['productid'] ?? '');
        $val  = max(0, intval($_POST['stock'] ?? 0));
        $mode = $_POST['mode'] ?? 'set';

        if ($mode === 'add') {
            $stmt = mysqli_prepare($connect, "UPDATE products SET stock = LEAST(?, GREATEST(0, stock + ?)) WHERE productid=?");
            $max  = MAX_STOCK;
            mysqli_stmt_bind_param($stmt, "iis", $max, $val, $pid);
        } elseif ($mode === 'sub') {
            $stmt = mysqli_prepare($connect, "UPDATE products SET stock = GREATEST(0, stock - ?) WHERE productid=?");
            mysqli_stmt_bind_param($stmt, "is", $val, $pid);
        } else {
            $val  = min(MAX_STOCK, $val);
            $stmt = mysqli_prepare($connect, "UPDATE products SET stock=? WHERE productid=?");
            mysqli_stmt_bind_param($stmt, "is", $val, $pid);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $row = mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT stock FROM products WHERE productid='" . mysqli_real_escape_string($connect, $pid) . "'"));
        echo json_encode(['success' => true, 'stock' => (int)$row['stock']]);
        exit();
    }
}

/* ── Categories ──────────────────────────────────────────────── */
$cats_result = mysqli_query($connect, "SELECT * FROM categories ORDER BY categoryid");
$cats        = mysqli_fetch_all($cats_result, MYSQLI_ASSOC);

/* ── Filters + Pagination ────────────────────────────────────── */
$catf     = $_GET['cat']  ?? 'all';
$search   = trim($_GET['q'] ?? '');
$per_page = 12;
$page     = max(1, intval($_GET['page'] ?? 1));

$types2 = ''; $params2 = [];
$where_parts = ['1=1'];
if ($catf !== 'all') { $where_parts[] = "p.categoryid = ?"; $types2 .= 's'; $params2[] = $catf; }
if ($search !== '')  { $where_parts[] = "p.name LIKE ?";    $types2 .= 's'; $params2[] = "%{$search}%"; }
$where2 = implode(' AND ', $where_parts);

$count_sql = "SELECT COUNT(*) AS c FROM products p LEFT JOIN categories c ON p.categoryid=c.categoryid WHERE $where2";
$stmt      = mysqli_prepare($connect, $count_sql);
if ($types2) mysqli_stmt_bind_param($stmt, $types2, ...$params2);
mysqli_stmt_execute($stmt);
$total_products = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
mysqli_stmt_close($stmt);

$total_pages  = max(1, (int)ceil($total_products / $per_page));
$page         = min($page, $total_pages);
$offset       = ($page - 1) * $per_page;

$data_sql = "SELECT p.*, c.category_name FROM products p
             LEFT JOIN categories c ON p.categoryid=c.categoryid
             WHERE $where2 ORDER BY p.categoryid, p.productid
             LIMIT $per_page OFFSET $offset";
$stmt = mysqli_prepare($connect, $data_sql);
if ($types2) mysqli_stmt_bind_param($stmt, $types2, ...$params2);
mysqli_stmt_execute($stmt);
$products = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$showing_from = $total_products === 0 ? 0 : $offset + 1;
$showing_to   = min($offset + $per_page, $total_products);

function prod_link($overrides = []) {
    $d = ['cat' => $_GET['cat'] ?? 'all', 'q' => $_GET['q'] ?? '', 'page' => '1'];
    $p = array_merge($d, $overrides);
    $parts = [];
    foreach ($p as $k => $v) {
        if ($v !== '' && $v !== 'all') $parts[] = urlencode($k) . '=' . urlencode($v);
    }
    return 'Product.php' . ($parts ? '?' . implode('&', $parts) : '');
}

$stats = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) as total,
            SUM(is_available=1) as active,
            SUM(stock=0) as out_of_stock,
            SUM(stock>0 AND stock<=10) as low_stock
     FROM products"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Items — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        .stat-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px 20px; display:flex; align-items:center; gap:14px; transition:transform .15s,box-shadow .15s; }
        .stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 24px rgba(0,0,0,.08); }
        .stat-icon { width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0; }
        .stat-icon.orange { background:rgba(212,101,15,.12);color:#d4650f; }
        .stat-icon.green  { background:rgba(22,163,74,.12);color:#16a34a; }
        .stat-icon.yellow { background:rgba(234,179,8,.12);color:#b45309; }
        .stat-icon.red    { background:rgba(220,38,38,.12);color:#dc2626; }
        .stat-val { font-size:26px;font-weight:800;color:var(--text);line-height:1; }
        .stat-lbl { font-size:12px;color:var(--muted);font-weight:600;margin-top:3px; }

        .stock-badge { display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800; }
        .stock-ok  { background:#dcfce7;color:#166534;border:1px solid #bbf7d0; }
        .stock-low { background:#fef9c3;color:#854d0e;border:1px solid #fde68a; }
        .stock-out { background:#fee2e2;color:#991b1b;border:1px solid #fecaca; }

        .prod-cell { display:flex; align-items:center; gap:12px; }
        .prod-thumb {
            width:52px; height:52px; border-radius:10px; overflow:hidden; flex-shrink:0;
            background:var(--surface-2,#f3f4f6); border:1.5px solid var(--border,#e5e7eb);
            display:flex; align-items:center; justify-content:center; position:relative;
        }
        .prod-thumb img { width:100%; height:100%; object-fit:cover; display:block; min-width:52px; min-height:52px; }

        /* ═══════════════════════════════════════════════════════════
           RESTOCK MODAL — fully hardcoded colors, immune to dark mode
           ═══════════════════════════════════════════════════════════ */
        .restock-modal-bg {
            display:none;position:fixed;inset:0;
            background:rgba(0,0,0,.65);
            backdrop-filter:blur(3px);
            z-index:9999;align-items:center;justify-content:center;
        }
        .restock-modal-bg.show { display:flex; }

        /* Modal card — always light */
        .restock-modal {
            background:#ffffff !important;
            color:#1c1e21 !important;
            border-radius:18px;
            padding:28px 28px 22px;
            width:380px;
            box-shadow:0 16px 60px rgba(0,0,0,.35);
            animation:rmIn .18s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes rmIn { from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1} }

        .rm-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px; }
        .rm-header h3 {
            font-size:16px;font-weight:800;
            color:#1c1e21 !important;
            margin:0;line-height:1.3;
        }
        .rm-close {
            background:none;border:none;font-size:18px;
            color:#9ca3af !important;
            cursor:pointer;padding:0;line-height:1;
        }
        .rm-close:hover { color:#374151 !important; }

        .rm-current {
            display:flex;align-items:center;gap:10px;
            background:#f9fafb !important;
            border:1px solid #e5e7eb;
            border-radius:10px;padding:10px 14px;margin-bottom:16px;
            font-size:13px;color:#6b7280 !important;font-weight:600;
        }
        .rm-current strong {
            font-size:20px;font-weight:900;
            color:#d4650f !important;
        }
        .rm-max-label {
            font-size:11px;color:#9ca3af !important;margin-left:auto;
        }

        .rm-tabs { display:flex;gap:6px;margin-bottom:14px; }
        .rm-tab {
            flex:1;padding:8px 4px;border-radius:8px;
            border:1.5px solid #e4e6eb;
            background:#ffffff !important;
            cursor:pointer;font-size:12px;font-weight:700;
            color:#6b7280 !important;
            transition:all .15s;text-align:center;
            display:flex;align-items:center;justify-content:center;gap:5px;
        }
        .rm-tab:hover { border-color:#d4650f;color:#d4650f !important;background:#fff7f2 !important; }
        .rm-tab.active {
            border-color:#d4650f;
            background:#fff3e8 !important;
            color:#d4650f !important;
        }
        .rm-tab.active.tab-add {
            border-color:#16a34a;
            background:#f0fdf4 !important;
            color:#16a34a !important;
        }
        .rm-tab.active.tab-sub {
            border-color:#dc2626;
            background:#fef2f2 !important;
            color:#dc2626 !important;
        }

        .rm-input {
            width:100%;padding:11px 14px;
            border:1.5px solid #e4e6eb;
            border-radius:10px;font-size:22px;font-weight:900;
            text-align:center;box-sizing:border-box;outline:none;
            color:#1c1e21 !important;
            background:#ffffff !important;
            transition:border-color .15s;
        }
        .rm-input:focus { border-color:#d4650f; }
        .rm-input.input-add:focus { border-color:#16a34a; }
        .rm-input.input-sub:focus { border-color:#dc2626; }

        .rm-preview {
            text-align:center;font-size:13px;font-weight:700;
            margin:10px 0 16px;padding:8px 14px;border-radius:8px;min-height:34px;
        }
        .rm-preview.preview-ok  { background:#dcfce7 !important;color:#166534 !important; }
        .rm-preview.preview-low { background:#fef9c3 !important;color:#854d0e !important; }
        .rm-preview.preview-out { background:#fee2e2 !important;color:#991b1b !important; }
        .rm-preview.preview-max { background:#fff0e6 !important;color:#d4650f !important; }

        .rm-chips { display:flex;gap:5px;flex-wrap:wrap;margin-bottom:14px; }
        .rm-chip {
            padding:4px 10px;border-radius:6px;
            border:1.5px solid #e4e6eb;
            background:#ffffff !important;
            cursor:pointer;font-size:11px;font-weight:700;
            color:#6b7280 !important;
            transition:all .15s;
        }
        .rm-chip:hover {
            border-color:#d4650f;
            color:#d4650f !important;
            background:#fff0e6 !important;
        }

        .rm-btns { display:flex;gap:8px; }
        .rm-btn-cancel {
            flex:1;padding:10px;border-radius:8px;
            border:1.5px solid #e4e6eb;
            background:#ffffff !important;
            cursor:pointer;font-size:13px;font-weight:700;
            color:#6b7280 !important;
            transition:background .15s;
        }
        .rm-btn-cancel:hover { background:#f3f4f6 !important; }

        .rm-btn-save {
            flex:2;padding:10px;border-radius:8px;border:none;
            background:linear-gradient(135deg,#d4650f,#f0874a) !important;
            cursor:pointer;font-size:13px;font-weight:800;
            color:#ffffff !important;
            display:flex;align-items:center;justify-content:center;gap:6px;
            transition:opacity .15s;
        }
        .rm-btn-save:hover { opacity:.88; }
        .rm-btn-save:disabled {
            background:#c0c4cc !important;
            color:#ffffff !important;
            cursor:not-allowed;opacity:1;
        }

        /* ═══════════════════════════════════════════════════════════ */

        .stock-cell { display:flex;flex-direction:column;gap:5px;align-items:flex-start; }
        .btn-restock {
            font-size:10px;font-weight:700;padding:3px 9px;border-radius:6px;
            border:1.5px solid var(--border,#e4e6eb);
            background:var(--card,#fff);
            color:#374151;cursor:pointer;transition:all .15s;
            display:inline-flex;align-items:center;gap:4px;
        }
        .btn-restock:hover { border-color:#d4650f;background:rgba(212,101,15,.08);color:#d4650f; }

        .opts-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#fff3e8;color:#d4650f;border:1px solid #fde8d8; }
        .opts-badge.none { background:#f3f4f6;color:#9ca3af;border-color:#e5e7eb; }

        .btn-add-product { display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#d4650f,#f0874a);color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;transition:opacity .15s,transform .15s; }
        .btn-add-product:hover { opacity:.9;transform:translateY(-1px); }

        .cat-tabs-wrap { padding:12px 22px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap; }
        .filter-bar { display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex:1;justify-content:flex-end; }

        @media(max-width:900px) { .stats-row { grid-template-columns:repeat(2,1fr); } }
        @media(max-width:600px) { .stats-row { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>

<?php include 'admin_sidebar.php'; ?>

<!-- Restock Modal -->
<div class="restock-modal-bg" id="restockModalBg">
    <div class="restock-modal">
        <div class="rm-header">
            <h3 id="rmTitle">Manage Stock</h3>
            <button class="rm-close" onclick="closeRestockModal()">✕</button>
        </div>
        <div class="rm-current">
            <i class="fas fa-boxes" style="color:#d4650f;font-size:16px;"></i>
            <span>Current stock: <strong id="rmCurrentStock">—</strong></span>
            <span class="rm-max-label">Max: 1000</span>
        </div>
        <div class="rm-tabs">
            <button class="rm-tab active" onclick="setRmMode('set',this)"><i class="fas fa-equals"></i> Set to</button>
            <button class="rm-tab tab-add" onclick="setRmMode('add',this)"><i class="fas fa-plus"></i> Add</button>
            <button class="rm-tab tab-sub" onclick="setRmMode('sub',this)"><i class="fas fa-minus"></i> Deduct</button>
        </div>
        <input type="number" class="rm-input" id="rmInput" min="0" max="1000" placeholder="0"
               oninput="onRmInput()" onkeydown="if(event.key==='Enter') saveRestock()">
        <div class="rm-chips" id="rmChips">
            <span class="rm-chip" onclick="setRmChip(10)">10</span>
            <span class="rm-chip" onclick="setRmChip(50)">50</span>
            <span class="rm-chip" onclick="setRmChip(100)">100</span>
            <span class="rm-chip" onclick="setRmChip(200)">200</span>
            <span class="rm-chip" onclick="setRmChip(500)">500</span>
            <span class="rm-chip" onclick="setRmChip(1000)">1000</span>
        </div>
        <div class="rm-preview preview-ok" id="rmPreview">New stock: —</div>
        <div class="rm-btns">
            <button class="rm-btn-cancel" onclick="closeRestockModal()">Cancel</button>
            <button class="rm-btn-save" id="rmSaveBtn" onclick="saveRestock()">
                <i class="fas fa-save"></i> Save Stock
            </button>
        </div>
    </div>
</div>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">Menu <span>Items</span></div>
        <div class="topbar-right" style="display:flex;align-items:center;gap:12px;">
            <span class="text-muted" style="font-size:12.5px;"><?= $total_products ?> items total</span>
            <a href="Product_form.php" class="btn-add-product">
                <i class="fas fa-plus"></i> Add New Item
            </a>
        </div>
    </div>

    <div class="content">

        <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] ?>">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-utensils"></i></div>
                <div><div class="stat-val"><?= $stats['total'] ?></div><div class="stat-lbl">Total Items</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-val"><?= $stats['active'] ?></div><div class="stat-lbl">Active Items</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="stat-val"><?= $stats['low_stock'] ?></div><div class="stat-lbl">Low Stock</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-ban"></i></div>
                <div><div class="stat-val"><?= $stats['out_of_stock'] ?></div><div class="stat-lbl">Out of Stock</div></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header" style="flex-wrap:wrap;gap:10px;">
                <div class="panel-title"><i class="fas fa-utensils"></i> Menu Items</div>
                <form method="GET" class="filter-bar">
                    <?php if ($catf !== 'all'): ?>
                        <input type="hidden" name="cat" value="<?= htmlspecialchars($catf) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="page" value="1">
                    <div class="search-box" style="max-width:260px;min-width:180px;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" placeholder="Search items…"
                               value="<?= htmlspecialchars($search) ?>" oninput="debounceSearch()">
                    </div>
                </form>
            </div>

            <!-- Category Tabs -->
            <div class="cat-tabs-wrap">
                <a href="<?= prod_link(['cat'=>'all']) ?>" class="cat-tab <?= $catf==='all'?'active':'' ?>">
                    <i class="fas fa-th" style="font-size:10px;"></i> All
                </a>
                <?php foreach ($cats as $c): ?>
                    <a href="<?= prod_link(['cat'=>$c['categoryid']]) ?>"
                       class="cat-tab <?= $catf===$c['categoryid']?'active':'' ?>">
                        <?= htmlspecialchars($c['category_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="table-wrap">
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Options</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-utensils"></i>
                                <p>No items found.</p>
                                <a href="Product_form.php" style="color:#d4650f;font-weight:700;font-size:13px;">+ Add your first item</a>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p):
                            $p_opts = !empty($p['custom_options']) ? json_decode($p['custom_options'], true) : null;
                            $remove_count = count($p_opts['remove'] ?? []);
                            $addon_count  = count($p_opts['addons'] ?? []);
                            $has_opts = $p_opts && ($remove_count > 0 || $addon_count > 0 || !empty($p_opts['spice']));
                            $prod_type = 'Food'; $prod_type_icon = '🍽';
                            if ($p_opts) {
                                if (!empty($p_opts['beverage'])) { $prod_type = 'Beverage'; $prod_type_icon = '🧋'; }
                                elseif (!empty($p_opts['dessert'])) { $prod_type = 'Dessert'; $prod_type_icon = '🍰'; }
                            }
                            $img_file  = trim($p['image'] ?? '');
                            $thumb_src = ($img_file !== '') ? htmlspecialchars($UPLOADS_URL . $img_file) : '';
                            $p_stock   = (int)($p['stock'] ?? 0);
                            $js_name   = addslashes(htmlspecialchars($p['name']));
                        ?>
                        <tr id="row_<?= $p['productid'] ?>">
                            <td>
                                <div class="prod-cell">
                                    <div class="prod-thumb">
                                        <?php if ($thumb_src !== ''): ?>
                                            <img src="<?= $thumb_src ?>"
                                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            <div style="display:none;position:absolute;inset:0;background:var(--surface-2,#2a2a2a);align-items:center;justify-content:center;flex-direction:column;gap:2px;">
                                                <i class="fas fa-image" style="font-size:16px;color:var(--muted,#9ca3af);"></i>
                                                <span style="font-size:9px;color:var(--muted,#9ca3af);font-weight:700;">No image</span>
                                            </div>
                                        <?php else: ?>
                                            <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:20px;">
                                                <?php
                                                    if ($prod_type === 'Beverage') echo '🧋';
                                                    elseif ($prod_type === 'Dessert') echo '🍰';
                                                    else echo '<i class="fas fa-utensils" style="color:var(--muted,#9ca3af);font-size:18px;"></i>';
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-600" style="font-size:13.5px;"><?= htmlspecialchars($p['name']) ?></div>
                                        <div class="text-muted" style="font-size:11px;"><?= $p['productid'] ?> · <?= $prod_type_icon ?> <?= $prod_type ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-customer" style="text-transform:none;letter-spacing:0;">
                                    <?= htmlspecialchars($p['category_name'] ?? $p['categoryid']) ?>
                                </span>
                            </td>
                            <td class="text-accent fw-600">RM <?= number_format($p['price'], 2) ?></td>
                            <td>
                                <div class="stock-cell">
                                    <span id="stockBadge_<?= $p['productid'] ?>">
                                        <?= stockBadge($p_stock) ?>
                                    </span>
                                    <button class="btn-restock"
                                            data-pid="<?= $p['productid'] ?>"
                                            data-name="<?= $js_name ?>"
                                            data-stock="<?= $p_stock ?>"
                                            onclick="openRestockModal(this.dataset.pid, this.dataset.name, parseInt(this.dataset.stock))">
                                        <i class="fas fa-boxes"></i> Manage
                                    </button>
                                </div>
                            </td>
                            <td>
                                <?php if ($has_opts): ?>
                                    <span class="opts-badge" title="<?= $remove_count ?> remove, <?= $addon_count ?> add-ons<?= !empty($p_opts['spice']) ? ', spice' : '' ?>">
                                        <i class="fas fa-sliders-h"></i>
                                        <?= $remove_count ?>R / <?= $addon_count ?>A
                                        <?= !empty($p_opts['spice']) ? '🌶' : '' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="opts-badge none">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="avail-dot <?= $p['is_available'] ? 'avail-yes' : 'avail-no' ?>"></span>
                                <?= $p['is_available'] ? 'Active' : 'Hidden' ?>
                            </td>
                            <td>
                                <div class="action-btns" style="justify-content:flex-end;">
                                    <a href="Product_form.php?edit=<?= $p['productid'] ?>&cat=<?= urlencode($catf) ?>&q=<?= urlencode($search) ?>&page=<?= $page ?>"
                                       class="btn btn-info" style="font-size:11.5px;padding:5px 10px;">
                                        <i class="fas fa-pen"></i> Edit
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="productid" value="<?= $p['productid'] ?>">
                                        <button type="submit"
                                                class="btn <?= $p['is_available'] ? 'btn-warning' : 'btn-success' ?>"
                                                style="font-size:11.5px;padding:5px 10px;">
                                            <?= $p['is_available']
                                                ? '<i class="fas fa-eye-slash"></i> Hide'
                                                : '<i class="fas fa-eye"></i> Show' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete \'<?= addslashes($p['name']) ?>\' from menu?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="productid" value="<?= $p['productid'] ?>">
                                        <button type="submit" class="btn btn-danger"
                                                style="font-size:11.5px;padding:5px 10px;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pg-info">Showing <?= $showing_from ?>–<?= $showing_to ?> of <?= $total_products ?> items</div>
                <div class="pg-nav">
                    <?php
                    echo '<a href="'.prod_link(['page'=>max(1,$page-1)]).'" class="pg-btn'.($page<=1?'" style="opacity:.3;pointer-events:none;"':'"').'><i class="fas fa-chevron-left"></i></a>';
                    $st=max(1,$page-2);$en=min($total_pages,$page+2);
                    if($st>1){echo '<a href="'.prod_link(['page'=>1]).'" class="pg-btn">1</a>';if($st>2)echo '<span class="pg-btn pg-ellipsis">…</span>';}
                    for($i=$st;$i<=$en;$i++)echo '<a href="'.prod_link(['page'=>$i]).'" class="pg-btn'.($i===$page?' active':'').'">'.$i.'</a>';
                    if($en<$total_pages){if($en<$total_pages-1)echo '<span class="pg-btn pg-ellipsis">…</span>';echo '<a href="'.prod_link(['page'=>$total_pages]).'" class="pg-btn">'.$total_pages.'</a>';}
                    echo '<a href="'.prod_link(['page'=>min($total_pages,$page+1)]).'" class="pg-btn'.($page>=$total_pages?'" style="opacity:.3;pointer-events:none;"':'"').'><i class="fas fa-chevron-right"></i></a>';
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
const MAX_STOCK = 1000;
let rmPid = '', rmMode = 'set', rmCurrent = 0;

function openRestockModal(pid, name, current) {
    rmPid     = pid;
    rmCurrent = current;
    rmMode    = 'set';

    document.getElementById('rmTitle').textContent        = 'Stock — ' + name;
    document.getElementById('rmCurrentStock').textContent = current;
    document.getElementById('rmInput').value              = current;
    document.getElementById('rmInput').className          = 'rm-input';
    document.querySelectorAll('.rm-tab').forEach((t, i) => t.classList.toggle('active', i === 0));
    document.getElementById('rmChips').style.display = 'flex';
    updateRmPreview();
    document.getElementById('restockModalBg').classList.add('show');
    setTimeout(() => document.getElementById('rmInput').focus(), 80);
}

function closeRestockModal() {
    document.getElementById('restockModalBg').classList.remove('show');
}

function setRmMode(mode, btn) {
    rmMode = mode;
    document.querySelectorAll('.rm-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    const inp = document.getElementById('rmInput');
    inp.className = 'rm-input' + (mode === 'add' ? ' input-add' : mode === 'sub' ? ' input-sub' : '');
    inp.value = mode === 'set' ? rmCurrent : 0;
    document.getElementById('rmChips').style.display = mode === 'sub' ? 'none' : 'flex';
    updateRmPreview();
    inp.focus();
}

function setRmChip(val) {
    document.getElementById('rmInput').value = val;
    updateRmPreview();
}

function onRmInput() {
    const inp = document.getElementById('rmInput');
    let v = parseInt(inp.value) || 0;
    if (v < 0) { v = 0; inp.value = 0; }
    if (rmMode === 'sub' && v > rmCurrent) { v = rmCurrent; inp.value = rmCurrent; }
    if (rmMode !== 'sub' && v > MAX_STOCK) { v = MAX_STOCK; inp.value = MAX_STOCK; }
    updateRmPreview();
}

function updateRmPreview() {
    const val     = parseInt(document.getElementById('rmInput').value) || 0;
    const preview = document.getElementById('rmPreview');
    const saveBtn = document.getElementById('rmSaveBtn');
    let newStock;
    if      (rmMode === 'add') newStock = Math.min(MAX_STOCK, rmCurrent + val);
    else if (rmMode === 'sub') newStock = Math.max(0, rmCurrent - val);
    else                       newStock = Math.min(MAX_STOCK, Math.max(0, val));

    let icon = '', cls = '';
    if      (newStock <= 0)          { icon = '🚫'; cls = 'preview-out'; }
    else if (newStock <= 10)         { icon = '⚠️'; cls = 'preview-low'; }
    else if (newStock === MAX_STOCK) { icon = '✅'; cls = 'preview-max'; }
    else                             { icon = '✅'; cls = 'preview-ok';  }

    const changeText = rmMode === 'add' ? ' (+' + val + ')' : rmMode === 'sub' ? ' (−' + val + ')' : '';
    preview.className = 'rm-preview ' + cls;
    preview.innerHTML = icon + ' New stock: <strong>' + newStock + '</strong>' + changeText;
    saveBtn.disabled  = (rmMode === 'set' && newStock === rmCurrent);
}

function saveRestock() {
    const val     = parseInt(document.getElementById('rmInput').value) || 0;
    const saveBtn = document.getElementById('rmSaveBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    const fd = new FormData();
    fd.append('action', 'restock');
    fd.append('productid', rmPid);
    fd.append('stock', val);
    fd.append('mode', rmMode);

    fetch('Product.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const newStock = data.stock;
                rmCurrent = newStock;

                const badge = document.getElementById('stockBadge_' + rmPid);
                if (badge) {
                    if      (newStock <= 0)  badge.innerHTML = '<span class="stock-badge stock-out"><i class="fas fa-ban"></i> Out of Stock</span>';
                    else if (newStock <= 10) badge.innerHTML = '<span class="stock-badge stock-low"><i class="fas fa-exclamation-triangle"></i> Low: ' + newStock + '</span>';
                    else                     badge.innerHTML = '<span class="stock-badge stock-ok"><i class="fas fa-check"></i> ' + newStock + ' left</span>';
                }

                const manageBtn = document.querySelector('.btn-restock[data-pid="' + rmPid + '"]');
                if (manageBtn) manageBtn.dataset.stock = newStock;

                closeRestockModal();
            }
        })
        .catch(() => alert('Network error. Please try again.'))
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Stock';
        });
}

document.getElementById('restockModalBg').addEventListener('click', e => {
    if (e.target === document.getElementById('restockModalBg')) closeRestockModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRestockModal(); });

let searchTimer;
function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => document.querySelector('form[method="GET"]').submit(), 400);
}
</script>
</body>
</html>