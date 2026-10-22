<?php
session_start();
require_once 'base.php';
include 'award_points.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle Mark as Paid action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid') {
    $order_id = mysqli_real_escape_string($connect, $_POST['order_id'] ?? '');
    if ($order_id) {
        mysqli_begin_transaction($connect);
        try {
            $order_query = mysqli_query($connect, "SELECT userid, total_price, status, payment_method FROM orders WHERE orderid = '$order_id'");
            if (!$order_query) throw new Exception("Failed to fetch order: " . mysqli_error($connect));
            $order = mysqli_fetch_assoc($order_query);
            if (!$order) throw new Exception("Order not found.");
            if ($order['status'] !== 'pending') throw new Exception("Order is already " . $order['status'] . ". Only pending orders can be marked as paid.");
            if ($order['payment_method'] !== 'cash') throw new Exception("This order is not a cash order. Payment method: " . $order['payment_method']);

            $user_id      = $order['userid'];
            $total_amount = (float)$order['total_price'];

            $update_order = mysqli_query($connect, "UPDATE orders SET status = 'paid', updated_at = NOW() WHERE orderid = '$order_id' AND status = 'pending'");
            if (!$update_order) throw new Exception("Failed to update order: " . mysqli_error($connect));
            if (mysqli_affected_rows($connect) == 0) throw new Exception("Order status may have been changed by another process.");

            $update_payment = mysqli_query($connect, "UPDATE payments SET status = 'paid' WHERE orderid = '$order_id' AND status = 'pending'");
            if (!$update_payment) throw new Exception("Failed to update payment: " . mysqli_error($connect));

            if (mysqli_affected_rows($connect) == 0) {
                $pay_res = mysqli_query($connect, "SELECT paymentid FROM payments WHERE orderid = '$order_id' LIMIT 1");
                if (!$pay_res || mysqli_num_rows($pay_res) === 0) {
                    $pay_id_res = mysqli_query($connect, "SELECT paymentid FROM payments WHERE paymentid REGEXP '^PAY[0-9]{5}$' ORDER BY paymentid DESC LIMIT 1");
                    $pay_num = 1;
                    if ($pay_id_res && mysqli_num_rows($pay_id_res) > 0) {
                        $pay_id_row = mysqli_fetch_assoc($pay_id_res);
                        $pay_num = intval(substr($pay_id_row['paymentid'], 3)) + 1;
                    }
                    $new_pay_id = 'PAY' . str_pad($pay_num, 5, '0', STR_PAD_LEFT);
                    $uid_esc = mysqli_real_escape_string($connect, $user_id);
                    $now_str = date('Y-m-d H:i:s');
                    mysqli_query($connect, "INSERT INTO payments (paymentid, orderid, user_id, method, status, amount, created_at)
                        VALUES ('$new_pay_id', '$order_id', '$uid_esc', 'cash', 'paid', $total_amount, '$now_str')");
                }
            }

            $points_awarded = awardPoints($connect, $user_id, $order_id, $total_amount);
            if ($points_awarded === false) throw new Exception("Failed to award points.");

            $uid_esc2 = mysqli_real_escape_string($connect, $user_id);
            $col_check = mysqli_query($connect, "SHOW COLUMNS FROM user_vouchers LIKE 'used_order_id'");
            if ($col_check && mysqli_num_rows($col_check) > 0) {
                mysqli_query($connect, "UPDATE user_vouchers SET is_used=1, used_at=NOW(), used_order_id='$order_id'
                    WHERE userid='$uid_esc2' AND is_used=0 AND used_order_id='$order_id'");
            }

            mysqli_commit($connect);
            $full_ringgit    = floor($total_amount);
            $cents_remainder = round(($total_amount - $full_ringgit) * 100);
            $success_message = "Order $order_id marked as paid! " . number_format($points_awarded) . " loyalty points awarded.";
            if ($cents_remainder > 0)
                $success_message .= " (RM " . number_format($cents_remainder/100, 2) . " not counted until next order)";
        } catch (Exception $e) {
            mysqli_rollback($connect);
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = "Invalid order ID.";
    }
}
$user_id      = $_SESSION['user_id'] ?? '';
$admin_id     = $user_id;  //
$admin_name   = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$current_page = 'orders';

$valid_statuses = ['pending','paid','preparing','completed','cancelled'];
$status_filter  = in_array($_GET['status'] ?? '', $valid_statuses) ? $_GET['status'] : '';
$search         = trim($_GET['q'] ?? '');
$per_page       = 10;
$page           = max(1, intval($_GET['page'] ?? 1));

$status_counts = [];
$sc_res = mysqli_query($connect, "SELECT status, COUNT(*) as c FROM orders GROUP BY status");
if ($sc_res) while ($sc = mysqli_fetch_assoc($sc_res)) $status_counts[$sc['status']] = (int)$sc['c'];
$total_all = array_sum($status_counts);

$types = ''; $params = []; $wheres = [];
if ($status_filter !== '') { $wheres[] = "o.status = ?"; $types .= 's'; $params[] = $status_filter; }
if ($search !== '') {
    $wheres[] = "(o.orderid LIKE ? OR o.customer_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%"; $types .= 'ssss'; $params = array_merge($params, [$like,$like,$like,$like]);
}
$where_sql = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

$count_sql = "SELECT COUNT(*) AS c FROM orders o LEFT JOIN users u ON o.userid=u.userid $where_sql";
$stmt = mysqli_prepare($connect, $count_sql);
if ($stmt) {
    if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $total_orders = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);
} else { $total_orders = 0; }

$total_pages = max(1,(int)ceil($total_orders/$per_page));
$page        = min($page,$total_pages);
$offset      = ($page-1)*$per_page;

$data_sql = "SELECT o.*, u.username, u.email AS user_email
             FROM orders o LEFT JOIN users u ON o.userid=u.userid
             $where_sql ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = mysqli_prepare($connect, $data_sql);
if ($stmt) {
    if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $orders = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else { $orders = []; }

$showing_from = $total_orders===0 ? 0 : $offset+1;
$showing_to   = min($offset+$per_page, $total_orders);

/* ── Detect customisation column ── */
$opts_col = null;
foreach (['selected_options','custom_options'] as $candidate) {
    $chk = mysqli_query($connect, "SHOW COLUMNS FROM order_items LIKE '$candidate'");
    if ($chk && mysqli_num_rows($chk) > 0) { $opts_col = $candidate; break; }
}

function build_link($overrides=[]) {
    $defaults=['status'=>$_GET['status']??'','q'=>$_GET['q']??'','page'=>'1'];
    $p=array_merge($defaults,$overrides); $parts=[];
    foreach ($p as $k=>$v) if ($v!=='') $parts[]=urlencode($k).'='.urlencode($v);
    return 'Order.php'.($parts?'?'.implode('&',$parts):'');
}

function parseVoucher($notes) {
    if (!empty($notes) && preg_match('/Voucher:\s*([A-Z0-9]+)\s*\(RM\s*([\d.]+)\s*off\)/i',$notes,$m))
        return ['code'=>strtoupper($m[1]),'discount'=>floatval($m[2])];
    return null;
}

/*
 * ─────────────────────────────────────────────────────────────────
 * HOW customizeModal.php SAVES DATA
 * confirmAddToCart() writes to localStorage this object:
 *
 *   {
 *     id, name, price, basePrice, quantity, category,
 *     spice_level : "original" | "mild" | "spicy" | null,
 *     serve_time  : "before_meal" | "after_meal" | null,
 *     bev_temp    : "ice" | "less_ice" | "no_ice" | "hot" | null,
 *     removes : { "no_onion": false, "no_chili": true, "no_sauce": false },  ← ALL keys, bool
 *     addons  : { "add_sauce": true,  "add_rice": false, "add_meat": false }, ← ALL keys, bool
 *   }
 *
 * Your backend (cart submit / checkout) should save that JSON string
 * into order_items.selected_options (or custom_options).
 *
 * We also handle legacy/alternative shapes:
 *   "remove"  (singular) instead of "removes"
 *   indexed arrays: [{"key":"no_onion","label":"..."}]  (only selected rows)
 *   flat root keys: {"no_onion":true, "add_sauce":true}
 * ─────────────────────────────────────────────────────────────────
 */
function parseCustomOptions(?string $json): ?array {
    if (!$json || in_array(trim($json),['','null','{}','[]'])) return null;
    $d = @json_decode($json,true);
    if (!is_array($d)||empty($d)) return null;
    return $d;
}

function friendlyRemoveLabel(string $key): string {
    static $map=[
        'no_onion'=>'🧅 No Onion','no_chili'=>'🌶 No Chili','no_garlic'=>'🧄 No Garlic',
        'no_ginger'=>'🫚 No Ginger','no_sauce'=>'🥫 No Sauce','no_egg'=>'🥚 No Egg',
        'no_coriander'=>'🌿 No Coriander','no_peanut'=>'🥜 No Peanut','no_mushroom'=>'🍄 No Mushroom',
        'no_tomato'=>'🍅 No Tomato','no_pepper'=>'🫑 No Pepper','no_lemon'=>'🍋 No Lemon',
        'no_cream'=>'🥛 No Cream','no_butter'=>'🧈 No Butter','no_cheese'=>'🧀 No Cheese',
        'no_prawn'=>'🦐 No Prawn','no_meat'=>'🥩 No Meat','no_lettuce'=>'🥬 No Lettuce',
        'no_pickle'=>'🥒 No Pickle','no_sugar'=>'🍬 Less Sugar','no_ice'=>'🧊 Less Ice',
        'no_milk'=>'🥛 Less Milk','no_capsicum'=>'🫑 No Capsicum','no_corn'=>'🌽 No Corn',
        'no_carrot'=>'🥕 No Carrot','no_cabbage'=>'🥬 No Cabbage','no_nuts'=>'🌰 No Nuts',
        'no_basil'=>'🌿 No Basil','no_pineapple'=>'🍍 No Pineapple','no_vinegar'=>'🍶 No Vinegar',
        'no_sausage'=>'🌭 No Sausage','no_lemongrass'=>'🌾 No Lemongrass',
        'no_parmesan'=>'🧀 No Parmesan','no_pancake'=>'🫓 No Pancake','no_curry_leaf'=>'🌿 No Curry Leaf',
    ];
    return $map[$key]??ucwords(str_replace(['no_','_'],['No ',' '],$key));
}

function friendlyAddonLabel(string $key): string {
    static $map=[
        'add_rice'=>'🍚 Add Rice','add_sauce'=>'🥫 Extra Sauce','add_meat'=>'🥩 Extra Meat',
        'add_egg'=>'🥚 Extra Egg','add_prawn'=>'🦐 Extra Prawn','add_cheese'=>'🧀 Extra Cheese',
        'add_tofu'=>'🟨 Extra Tofu','add_mushroom'=>'🍄 Extra Mushroom','add_bread'=>'🍞 Bread/Bun',
        'add_cream'=>'🍦 Whipped Cream','add_peanut'=>'🥜 Extra Peanut','add_extra'=>'➕ Extra Portion',
        'add_salad'=>'🥗 Side Salad','add_sugar'=>'🍬 Extra Sugar','add_milk'=>'🥛 Extra Milk',
        'add_shot'=>'☕ Extra Shot','add_pancake'=>'🫓 Extra Pancake','add_onion'=>'🧅 Extra Onion',
        'add_nuts'=>'🌰 Extra Nuts','add_coriander'=>'🌿 Extra Coriander',
    ];
    return $map[$key]??ucwords(str_replace(['add_','_'],['',  ' '],$key));
}

function renderCustomisation(?array $opts): string {
    if (!$opts) return '';
    $pills = [];

    /* ── Spice ── */
    $spice = $opts['spice_level'] ?? ($opts['spice'] ?? null);
    if ($spice !== null && $spice !== false && (string)$spice !== '' && (string)$spice !== 'null') {
        $sm=['original'=>['🌿','Original','#16a34a'],'mild'=>['🌶','Mild','#d97706'],
             'spicy'=>['🌶🌶','Spicy','#dc2626'],'extra_spicy'=>['🔥','Extra Spicy','#7c3aed']];
        $k=strtolower(trim((string)$spice));
        if (in_array($k,['normal','regular','none','no spice'])) $k='original';
        if (in_array($k,['less spicy','less']))                  $k='mild';
        if (in_array($k,['extra spicy','very spicy']))           $k='extra_spicy';
        [$ico,$lbl,$col]=$sm[$k]??['🌶',ucfirst($k),'#dc2626'];
        $pills[]='<span class="co-pill" style="background:'.$col.'20;color:'.$col.';border-color:'.$col.'40;">'.$ico.' '.htmlspecialchars($lbl).'</span>';
    }

    /* ── Temperature ── */
    $temp = $opts['bev_temp'] ?? ($opts['temp'] ?? null);
    if ($temp && (string)$temp !== '' && (string)$temp !== 'null') {
        $tm=['ice'=>['❄️','Ice','#0ea5e9'],'less_ice'=>['🧊','Less Ice','#38bdf8'],
             'no_ice'=>['💧','No Ice','#6366f1'],'hot'=>['🔥','Hot','#f97316']];
        [$ti,$tl,$tc]=$tm[strtolower((string)$temp)]??['🧋',ucfirst(str_replace('_',' ',(string)$temp)),'#64748b'];
        $pills[]='<span class="co-pill" style="background:'.$tc.'20;color:'.$tc.';border-color:'.$tc.'40;">'.$ti.' '.htmlspecialchars($tl).'</span>';
    }

    /* ── Serve time ── */
    $serve = $opts['serve_time'] ?? null;
    if ($serve && (string)$serve !== '' && (string)$serve !== 'null') {
        $sv=['before_meal'=>'🍽 Before Meal','after_meal'=>'🍮 After Meal'];
        $pills[]='<span class="co-pill" style="background:#16a34a20;color:#16a34a;border-color:#16a34a40;">'.htmlspecialchars($sv[$serve]??ucfirst(str_replace('_',' ',$serve))).'</span>';
    }

    /* ── Removes ──
       customizeModal saves: "removes": {"no_onion":false,"no_chili":true}  ← KEY IS "removes" (plural)
       Legacy admin shape:   "remove":  [{"key":"no_onion","label":"..."}]  ← indexed array
       Flat root:            {"no_onion":true}
    */
    $remove_labels = [];
    $rm_src = $opts['removes'] ?? ($opts['remove'] ?? null); // "removes" first!

    if ($rm_src !== null && is_array($rm_src)) {
        if (array_values($rm_src) === $rm_src) {
            // Indexed — only selected items (admin product form shape)
            foreach ($rm_src as $r) {
                if (is_string($r)) $remove_labels[] = friendlyRemoveLabel($r);
                elseif (is_array($r)) {
                    $lbl = !empty($r['label']) ? $r['label'] : friendlyRemoveLabel($r['key']??'');
                    if ($lbl) $remove_labels[] = $lbl;
                }
            }
        } else {
            // Associative key => bool (customizeModal shape)
            foreach ($rm_src as $k => $v) {
                if ($v === true || $v === 1 || $v === '1' || $v === 'true') {
                    $remove_labels[] = friendlyRemoveLabel((string)$k);
                }
            }
        }
    } else {
        // Flat root keys
        foreach ($opts as $k => $v) {
            if (is_string($k) && str_starts_with($k,'no_') && ($v===true||$v===1||$v==='1'||$v==='true'))
                $remove_labels[] = friendlyRemoveLabel($k);
        }
    }

    if (!empty($remove_labels)) {
        $row = '<div class="co-row"><span class="co-lbl">🚫 Remove:</span>';
        foreach ($remove_labels as $l) $row .= '<span class="co-pill co-pill-red">'.htmlspecialchars($l).'</span>';
        $pills[] = $row.'</div>';
    }

    /* ── Addons ──
       customizeModal saves: "addons": {"add_sauce":true,"add_rice":false}  ← KEY IS "addons" (plural)
       Legacy admin shape:   "addons": [{"key":"add_sauce","label":"...","price":1}]
       Flat root:            {"add_sauce":true}
    */
    $addon_items = [];
    $ad_src = $opts['addons'] ?? ($opts['addon'] ?? null); // "addons" first!

    if ($ad_src !== null && is_array($ad_src)) {
        if (array_values($ad_src) === $ad_src) {
            // Indexed — only selected items
            foreach ($ad_src as $a) {
                if (is_string($a)) $addon_items[]=['label'=>friendlyAddonLabel($a),'price'=>0];
                elseif (is_array($a)) {
                    $lbl=!empty($a['label'])?$a['label']:friendlyAddonLabel($a['key']??'');
                    if ($lbl) $addon_items[]=['label'=>$lbl,'price'=>(float)($a['price']??0)];
                }
            }
        } else {
            // Associative key => bool (customizeModal shape)
            foreach ($ad_src as $k => $v) {
                if ($v===true||$v===1||$v==='1'||$v==='true')
                    $addon_items[]=['label'=>friendlyAddonLabel((string)$k),'price'=>0];
            }
        }
    } else {
        foreach ($opts as $k => $v) {
            if (is_string($k) && str_starts_with($k,'add_') && ($v===true||$v===1||$v==='1'||$v==='true'))
                $addon_items[]=['label'=>friendlyAddonLabel($k),'price'=>0];
        }
    }

    if (!empty($addon_items)) {
        $row = '<div class="co-row"><span class="co-lbl">➕ Add-ons:</span>';
        foreach ($addon_items as $a) {
            $extra = $a['price']>0 ? ' <small>(+RM '.number_format($a['price'],2).')</small>' : '';
            $row .= '<span class="co-pill co-pill-green">'.htmlspecialchars($a['label']).$extra.'</span>';
        }
        $pills[] = $row.'</div>';
    }

    if (empty($pills)) return '';
    return '<div class="custom-opts-block">'.implode('',$pills).'</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .btn-mark-paid{background:linear-gradient(135deg,#27ae60,#2ecc71);color:white;border:none;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;transition:all .2s;}
        .btn-mark-paid:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(46,204,113,.3);}
        .alert-success{background:#d4edda;color:#155724;padding:12px 20px;border-radius:8px;margin-bottom:20px;border-left:4px solid #28a745;animation:slideDown .3s ease;}
        .alert-error{background:#f8d7da;color:#721c24;padding:12px 20px;border-radius:8px;margin-bottom:20px;border-left:4px solid #dc3545;animation:slideDown .3s ease;}
        .action-buttons{display:flex;gap:8px;justify-content:center;}
        @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .points-badge{display:inline-block;background:#f39c12;color:white;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:5px;}
        .points-info{font-size:11px;color:#f39c12;margin-top:4px;}
        .points-breakdown{font-size:10px;color:#7f8c8d;margin-top:2px;}
        .voucher-col-discount{color:#16a34a;font-weight:700;font-size:12px;}
        .voucher-col-code{display:inline-flex;align-items:center;gap:4px;background:rgba(22,163,74,.1);color:#16a34a;border:1px solid rgba(22,163,74,.3);padding:2px 7px;border-radius:10px;font-size:11px;font-weight:700;margin-top:3px;}
        .items-summary-totals{margin-top:12px;padding-top:10px;border-top:1px solid rgba(212,101,15,.2);}
        .items-summary-row{display:flex;justify-content:space-between;font-size:12.5px;padding:3px 0;color:var(--text-2);}
        .items-summary-row.discount{color:#16a34a;font-weight:700;}
        .items-summary-row.total{font-weight:700;color:var(--accent);font-size:14px;border-top:1px solid var(--border);padding-top:6px;margin-top:4px;}

        /* ── Customisation display ── */
        .custom-opts-block{display:flex;flex-direction:column;gap:5px;margin-top:6px;}
        .co-row{display:flex;align-items:center;flex-wrap:wrap;gap:5px;}
        .co-lbl{font-size:10px;font-weight:800;color:var(--muted,#888);text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;flex-shrink:0;}
        .co-pill{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;border:1px solid;white-space:nowrap;}
        .co-pill-red{background:rgba(220,38,38,.08);color:#dc2626;border-color:rgba(220,38,38,.3);}
        .co-pill-green{background:rgba(34,197,94,.08);color:#16a34a;border-color:rgba(34,197,94,.3);}
        .co-pill small{font-size:10px;opacity:.75;font-weight:600;}
        .product-name{font-weight:700;color:var(--text);font-size:13px;}
        .item-customised{margin-top:5px;padding:7px 10px;border-left:3px solid rgba(212,101,15,.4);border-radius:0 6px 6px 0;background:rgba(212,101,15,.04);}
    </style>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">Order <span>Management</span></div>
        <div class="topbar-right">
            <span class="text-muted" style="font-size:12.5px;"><?= $total_orders ?> order<?= $total_orders!==1?'s':'' ?> found</span>
        </div>
    </div>

    <div class="content">

        <?php if (isset($success_message)): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            <button onclick="this.parentElement.style.display='none'" style="float:right;background:none;border:none;cursor:pointer;font-size:18px;">&times;</button>
        </div>
        <?php endif; ?>

        <!-- Status filter bar -->
        <div class="filter-bar">
            <a href="<?= build_link(['status'=>'','page'=>'1']) ?>" class="fpill <?= $status_filter===''?'active':'' ?>">All <span class="fcount"><?= $total_all ?></span></a>
            <?php foreach ($valid_statuses as $s): ?>
            <a href="<?= build_link(['status'=>$s,'page'=>'1']) ?>" class="fpill <?= $status_filter===$s?'active':'' ?>"><?= ucfirst($s) ?> <span class="fcount"><?= $status_counts[$s]??0 ?></span></a>
            <?php endforeach; ?>
        </div>

        <!-- Search -->
        <div class="toolbar">
            <form method="GET" id="searchForm" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;">
                <?php if ($status_filter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                <input type="hidden" name="page" value="1">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search order ID, customer name…" value="<?= htmlspecialchars($search) ?>" oninput="debounceSearch()">
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-receipt"></i> Orders</div>
            </div>

            <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No orders found<?= $status_filter?" with status: {$status_filter}":'' ?>.</p>
            </div>
            <?php else: ?>

            <div class="table-wrap">
                <table class="dt" id="ordersTable">
                    <thead>
                        <tr>
                            <th></th><th>Order ID</th><th>Customer</th><th>Type</th><th>Table</th>
                            <th>Payment</th><th>Subtotal</th><th>Discount</th><th>Tax</th>
                            <th>Total</th><th>Status</th><th>Date</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $idx => $order):
                        $row_id      = 'items-'.$offset.'-'.$idx;
                        $order_id_db = mysqli_real_escape_string($connect, $order['orderid']);

                        if ($opts_col) {
                            $items_q = mysqli_query($connect,
                                "SELECT orderitemid, product_name, quantity, price, subtotal,
                                        `{$opts_col}` AS opts_json
                                 FROM order_items WHERE orderid='$order_id_db' ORDER BY orderitemid ASC");
                        } else {
                            $items_q = mysqli_query($connect,
                                "SELECT orderitemid, product_name, quantity, price, subtotal
                                 FROM order_items WHERE orderid='$order_id_db' ORDER BY orderitemid ASC");
                        }

                        $items_count     = $items_q ? mysqli_num_rows($items_q) : 0;
                        $total_price     = (float)$order['total_price'];
                        $full_ringgit    = floor($total_price);
                        $points_to_earn  = $full_ringgit * 5;
                        $cents_remainder = round(($total_price - $full_ringgit) * 100);
                        $voucher_info    = parseVoucher($order['special_notes'] ?? '');
                    ?>
                    <tr class="order-row">
                        <td style="width:40px;">
                            <?php if ($items_count > 0): ?>
                            <button class="expand-btn" onclick="toggleItems('<?= $row_id ?>',this)" title="View items">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                        <td class="order-id-cell"><?= htmlspecialchars($order['orderid']) ?></td>
                        <td>
                            <div class="fw-600" style="font-size:13px;"><?= htmlspecialchars($order['customer_name']??'—') ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($order['customer_email']??$order['user_email']??'') ?></div>
                        </td>
                        <td style="text-transform:capitalize;"><?= str_replace('_',' ',$order['order_type']??'') ?></td>
                        <td><?= $order['tableid'] ? htmlspecialchars($order['tableid']) : '—' ?></td>
                        <td style="text-transform:capitalize;"><?= str_replace('_',' ',$order['payment_method']??'—') ?></td>
                        <td>RM <?= number_format((float)$order['subtotal'],2) ?></td>
                        <td>
                            <?php if ($voucher_info): ?>
                                <div class="voucher-col-discount">- RM <?= number_format($voucher_info['discount'],2) ?></div>
                                <div><span class="voucher-col-code"><i class="fas fa-tag"></i> <?= htmlspecialchars($voucher_info['code']) ?></span></div>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="text-muted">RM <?= number_format((float)$order['tax'],2) ?></td>
                        <td class="text-accent fw-700">RM <?= number_format($total_price,2) ?></td>
                        <td>
                            <span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                            <?php if (!empty($order['points_earned'])&&$order['points_earned']>0): ?>
                            <span class="points-badge">+<?= $order['points_earned'] ?> pts</span>
                            <?php endif; ?>
                            <?php if ($order['status']==='pending'&&$order['payment_method']==='cash'): ?>
                            <div class="points-info">Will earn: <?= $points_to_earn ?> pts
                                <?php if ($cents_remainder>0): ?>
                                <div class="points-breakdown">(RM <?= $full_ringgit ?> × 5, RM <?= number_format($cents_remainder/100,2) ?> not counted)</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="font-size:12px;white-space:nowrap;">
                            <?= date('d M Y',strtotime($order['created_at'])) ?><br>
                            <?= date('H:i',strtotime($order['created_at'])) ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($order['status']==='pending'&&($order['payment_method']??'')==='cash'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Mark order <?= htmlspecialchars($order['orderid']) ?> as paid?\n\nAmount: RM <?= number_format($total_price,2) ?>\nPoints to award: <?= $points_to_earn ?>');">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['orderid']) ?>">
                                    <button type="submit" class="btn-mark-paid"><i class="fas fa-money-bill-wave"></i> Mark Paid</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php if ($items_count > 0):
                        $items_arr=[];
                        while ($item=mysqli_fetch_assoc($items_q)) $items_arr[]=$item;
                    ?>
                    <tr class="items-row" id="<?= $row_id ?>" style="display:none;">
                        <td colspan="13">
                            <div class="items-inner">
                                <table style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Item ID</th>
                                            <th>Product &amp; Customisation</th>
                                            <th>Qty</th><th>Unit Price</th><th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($items_arr as $item):
                                        $custom      = parseCustomOptions($item['opts_json']??null);
                                        $custom_html = renderCustomisation($custom);
                                    ?>
                                    <tr>
                                        <td class="text-muted mono" style="font-size:11px;vertical-align:top;padding-top:10px;"><?= htmlspecialchars($item['orderitemid']) ?></td>
                                        <td>
                                            <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <?php if ($custom_html): ?>
                                            <div class="item-customised"><?= $custom_html ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="vertical-align:top;padding-top:10px;"><?= intval($item['quantity']) ?></td>
                                        <td style="vertical-align:top;padding-top:10px;">RM <?= number_format((float)$item['price'],2) ?></td>
                                        <td class="fw-600" style="vertical-align:top;padding-top:10px;">RM <?= number_format((float)$item['subtotal'],2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="items-summary-totals">
                                    <div class="items-summary-row"><span>Items Subtotal</span><span>RM <?= number_format((float)$order['subtotal'],2) ?></span></div>
                                    <?php if ($voucher_info): ?>
                                    <div class="items-summary-row discount"><span><i class="fas fa-tag"></i> Voucher: <?= htmlspecialchars($voucher_info['code']) ?></span><span>- RM <?= number_format($voucher_info['discount'],2) ?></span></div>
                                    <?php endif; ?>
                                    <div class="items-summary-row"><span>Service Tax (6%)</span><span>RM <?= number_format((float)$order['tax'],2) ?></span></div>
                                    <div class="items-summary-row total"><span>Total Charged</span><span>RM <?= number_format($total_price,2) ?></span></div>
                                </div>

                                <?php if (!empty($order['special_notes'])): ?>
                                <div style="margin-top:10px;font-size:12.5px;color:var(--text-2);">
                                    📝 <strong>Notes:</strong> <?= htmlspecialchars($order['special_notes']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pg-info">Showing <?= $showing_from ?>–<?= $showing_to ?> of <?= $total_orders ?> orders</div>
                <div class="pg-nav">
                    <?php
                    $prev=build_link(['page'=>max(1,$page-1)]);
                    echo '<a href="'.$prev.'" class="pg-btn'.($page<=1?' disabled" style="opacity:.3;pointer-events:none;"':'"').'><i class="fas fa-chevron-left"></i></a>';
                    $start=max(1,$page-2);$end=min($total_pages,$page+2);
                    if($start>1){echo '<a href="'.build_link(['page'=>1]).'" class="pg-btn">1</a>';if($start>2)echo '<span class="pg-btn pg-ellipsis">…</span>';}
                    for($i=$start;$i<=$end;$i++) echo '<a href="'.build_link(['page'=>$i]).'" class="pg-btn'.($i===$page?' active':'').'">'.$i.'</a>';
                    if($end<$total_pages){if($end<$total_pages-1)echo '<span class="pg-btn pg-ellipsis">…</span>';echo '<a href="'.build_link(['page'=>$total_pages]).'" class="pg-btn">'.$total_pages.'</a>';}
                    $next=build_link(['page'=>min($total_pages,$page+1)]);
                    echo '<a href="'.$next.'" class="pg-btn'.($page>=$total_pages?' disabled" style="opacity:.3;pointer-events:none;"':'"').'><i class="fas fa-chevron-right"></i></a>';
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function toggleItems(id,btn){
    const row=document.getElementById(id),icon=btn.querySelector('i');
    if(!row)return;
    const showing=row.style.display!=='none';
    row.style.display=showing?'none':'table-row';
    icon.className=showing?'fas fa-chevron-right':'fas fa-chevron-down';
    btn.style.background=showing?'':'rgba(255,107,53,.22)';
}
let searchTimer;
function debounceSearch(){clearTimeout(searchTimer);searchTimer=setTimeout(()=>document.getElementById('searchForm').submit(),400);}
setTimeout(()=>{
    document.querySelectorAll('.alert-success,.alert-error').forEach(el=>{
        setTimeout(()=>{el.style.opacity='0';setTimeout(()=>el.remove(),300);},5000);
    });
},100);
</script>
</body>
</html>