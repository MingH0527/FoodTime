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
$current_page = 'top-products';

/* ── Filters ─────────────────────────────────────────────────── */
$range      = $_GET['range']  ?? 'all';
$cat_filter = $_GET['cat']    ?? 'all';

$valid_ranges = ['today', '7days', '30days', 'all'];
if (!in_array($range, $valid_ranges)) $range = 'all';

// Date condition for orders
$date_sql = '';
switch ($range) {
    case 'today':   $date_sql = "AND DATE(o.created_at) = CURDATE()"; break;
    case '7days':   $date_sql = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
    case '30days':  $date_sql = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
}

// Category condition
$cat_sql = '';
if ($cat_filter !== 'all') {
    $cat_safe = mysqli_real_escape_string($connect, $cat_filter);
    $cat_sql  = "AND p.categoryid = '$cat_safe'";
}

/*
 * ══════════════════════════════════════════════════════════════
 * REVENUE STRATEGY — SINGLE SOURCE OF TRUTH (matches dashboard)
 * ══════════════════════════════════════════════════════════════
 *
 * Dashboard uses:
 *   SELECT COALESCE(SUM(total_price),0) FROM orders WHERE status != 'cancelled'
 *   (includes pending orders)
 *
 * So ALL revenue queries here MUST use the same filter:
 *   status != 'cancelled'   ← only exclusion, same as dashboard
 *
 * Discount comes from orders.discount_rm (already stored on the order row).
 * Do NOT join user_vouchers — that table has rows with used_order_id = NULL
 * which can cause double-counting or missing amounts.
 *
 * Tax comes from orders.tax, same filter.
 * ══════════════════════════════════════════════════════════════
 */
$non_cancelled = "o.status != 'cancelled'";

/* ── All categories for filter dropdown ─────────────────────── */
$cats_result = mysqli_query($connect, "SELECT * FROM categories ORDER BY categoryid");
$all_cats    = mysqli_fetch_all($cats_result, MYSQLI_ASSOC);

/* ── Top products query ──────────────────────────────────────── */
$result = mysqli_query($connect, "
    SELECT
        p.productid, p.name, p.price, p.categoryid, p.stock,
        c.category_name,
        COUNT(oi.productid)  AS total_orders,
        SUM(oi.quantity)     AS total_quantity,
        SUM(oi.subtotal)     AS item_subtotal,
        AVG(oi.price)        AS avg_price
    FROM order_items oi
    JOIN products p  ON oi.productid = p.productid
    JOIN orders   o  ON oi.orderid   = o.orderid
    LEFT JOIN categories c ON p.categoryid = c.categoryid
    WHERE $non_cancelled $date_sql $cat_sql
    GROUP BY oi.productid, p.productid, p.name, p.price, p.categoryid, p.stock, c.category_name
    ORDER BY total_quantity DESC
");

$top_products = [];
if ($result) { while ($row = mysqli_fetch_assoc($result)) $top_products[] = $row; }

/* ── TOTAL REVENUE — matches dashboard exactly ───────────────── */
// Dashboard: SUM(total_price) WHERE status != 'cancelled'  (pending included)
$revenue_result = mysqli_query($connect, "
    SELECT COALESCE(SUM(o.total_price), 0) AS total_revenue
    FROM orders o
    WHERE $non_cancelled $date_sql
");
$total_rev = $revenue_result ? (float)mysqli_fetch_assoc($revenue_result)['total_revenue'] : 0;

/* ── TOTAL DISCOUNT — read directly from orders.discount_rm ──── */
// orders.discount_rm is already the applied voucher deduction per order.
// Using this avoids mismatches from user_vouchers rows with NULL used_order_id.
$discount_result = mysqli_query($connect, "
    SELECT COALESCE(SUM(o.discount_rm), 0) AS total_discount
    FROM orders o
    WHERE $non_cancelled $date_sql
");
$total_discount = $discount_result ? (float)mysqli_fetch_assoc($discount_result)['total_discount'] : 0;

/* ── TOTAL TAX — same filter as revenue ──────────────────────── */
$tax_result = mysqli_query($connect, "
    SELECT COALESCE(SUM(o.tax), 0) AS total_tax
    FROM orders o
    WHERE $non_cancelled $date_sql
");
$total_tax = $tax_result ? (float)mysqli_fetch_assoc($tax_result)['total_tax'] : 0;

/* ── COMPLETED ORDERS count (excludes cancelled only, same as revenue) ── */
$orders_result = mysqli_query($connect, "
    SELECT COUNT(DISTINCT o.orderid) AS total_orders
    FROM orders o
    WHERE $non_cancelled $date_sql
");
$total_unique_orders = $orders_result ? (int)mysqli_fetch_assoc($orders_result)['total_orders'] : 0;

/* ── Items subtotal (pre-tax, pre-voucher) ───────────────────── */
$subtotal_result = mysqli_query($connect, "
    SELECT COALESCE(SUM(oi.subtotal), 0) AS subtotal
    FROM order_items oi
    JOIN orders o ON oi.orderid = o.orderid
    WHERE $non_cancelled $date_sql
");
$subtotal_amount = $subtotal_result ? (float)mysqli_fetch_assoc($subtotal_result)['subtotal'] : 0;

/* ── Payment Method Breakdown ───────────────────────────────── */
$payment_result  = mysqli_query($connect, "
    SELECT
        o.payment_method,
        COUNT(*)                      AS count,
        COALESCE(SUM(o.total_price), 0) AS revenue
    FROM orders o
    WHERE o.status != 'cancelled'
      AND o.payment_method IS NOT NULL
      AND o.payment_method != ''
      $date_sql
    GROUP BY o.payment_method
");
if ($payment_result) {
    while ($row = mysqli_fetch_assoc($payment_result)) $payment_methods[] = $row;
}

/* ── Top 5 Customers ────────────────────────────────────────── */
$top_customers = [];
$top_result    = mysqli_query($connect, "
    SELECT
        u.username,
        u.userid,
        COUNT(DISTINCT o.orderid)            AS order_count,
        COALESCE(SUM(o.total_price), 0)      AS total_spent
    FROM orders o
    JOIN users u ON o.userid = u.userid
    WHERE $non_cancelled $date_sql
    GROUP BY o.userid, u.username, u.userid
    ORDER BY total_spent DESC
    LIMIT 5
");
if ($top_result) {
    while ($row = mysqli_fetch_assoc($top_result)) $top_customers[] = $row;
}

/* ── Hourly Distribution (Peak Hours) ───────────────────────── */
$hourly_counts  = array_fill(0, 24, 0);
$hourly_revenue = array_fill(0, 24, 0);
$hourly_result  = mysqli_query($connect, "
    SELECT
        HOUR(o.created_at)              AS hour,
        COUNT(*)                        AS orders,
        COALESCE(SUM(o.total_price), 0) AS revenue
    FROM orders o
    WHERE $non_cancelled $date_sql
    GROUP BY HOUR(o.created_at)
    ORDER BY hour ASC
");
if ($hourly_result) {
    while ($row = mysqli_fetch_assoc($hourly_result)) {
        $h = (int)$row['hour'];
        $hourly_counts[$h]  = (int)$row['orders'];
        $hourly_revenue[$h] = (float)$row['revenue'];
    }
}

/* ── CSV Export ──────────────────────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="top_products_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Rank','Product ID','Product Name','Category','Unit Price (RM)','Qty Sold','Order Lines','Item Subtotal (RM)','Stock Remaining','Subtotal Share %']);
    $rank = 1;
    foreach ($top_products as $p) {
        $share = $subtotal_amount > 0 ? round(((float)$p['item_subtotal'] / $subtotal_amount) * 100, 1) : 0;
        fputcsv($out, [
            $rank++,
            $p['productid'],
            $p['name'],
            $p['category_name'] ?? 'Unknown',
            number_format((float)$p['price'], 2),
            (int)$p['total_quantity'],
            (int)$p['total_orders'],
            number_format((float)$p['item_subtotal'], 2),
            (int)$p['stock'],
            $share . '%'
        ]);
    }
    fclose($out);
    exit();
}

/* ── Summary totals ──────────────────────────────────────────── */
$total_qty     = array_sum(array_column($top_products, 'total_quantity'));
$total_ord     = array_sum(array_column($top_products, 'total_orders'));
$avg_order_val = $total_unique_orders > 0 ? $total_rev / $total_unique_orders : 0;
$max_qty       = $top_products ? max(array_column($top_products, 'total_quantity')) : 1;

/* ── Top 8 for charts ────────────────────────────────────────── */
$chart_products = array_slice($top_products, 0, 8);

/* ── Low stock top sellers (stock ≤ 10) ─────────────────────── */
$low_stock_stars = array_filter($top_products, fn($p) => (int)$p['stock'] <= 10);

/* ── Category breakdown for donut ───────────────────────────── */
$cat_rev = [];
foreach ($top_products as $p) {
    $cn = $p['category_name'] ?? 'Unknown';
    if (!isset($cat_rev[$cn])) $cat_rev[$cn] = ['qty' => 0, 'rev' => 0];
    $cat_rev[$cn]['qty'] += (int)$p['total_quantity'];
    $cat_rev[$cn]['rev'] += (float)$p['item_subtotal'];
}
arsort($cat_rev);

/* ── 7-day trend ─────────────────────────────────────────────── */
$trend_rows   = [];
$trend_result = mysqli_query($connect, "
    SELECT DATE(o.created_at) AS day, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS rev
    FROM order_items oi
    JOIN orders o   ON oi.orderid   = o.orderid
    JOIN products p ON oi.productid = p.productid
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND o.status != 'cancelled'
      " . ($cat_filter !== 'all' ? "AND p.categoryid='" . mysqli_real_escape_string($connect, $cat_filter) . "'" : "") . "
    GROUP BY DATE(o.created_at)
    ORDER BY day ASC
");
if ($trend_result) {
    while ($r = mysqli_fetch_assoc($trend_result)) $trend_rows[] = $r;
}

$trend_map = [];
foreach ($trend_rows as $r) $trend_map[$r['day']] = $r;
$trend_labels = $trend_qty = $trend_rev = [];
for ($i = 6; $i >= 0; $i--) {
    $day            = date('Y-m-d', strtotime("-{$i} days"));
    $trend_labels[] = date('d M', strtotime($day));
    $trend_qty[]    = isset($trend_map[$day]) ? (int)$trend_map[$day]['qty'] : 0;
    $trend_rev[]    = isset($trend_map[$day]) ? round((float)$trend_map[$day]['rev'], 2) : 0;
}

/* ── Chart colours ───────────────────────────────────────────── */
$colors = ['#d4650f','#f0874a','#22c55e','#3b82f6','#a855f7','#ec4899','#f59e0b','#14b8a6'];

/* ── Range label helper ──────────────────────────────────────── */
$range_labels = ['today' => 'Today', '7days' => 'Last 7 Days', '30days' => 'Last 30 Days', 'all' => 'All Time'];
$range_label  = $range_labels[$range] ?? 'All Time';

function export_url($extra = []) {
    $p = array_merge($_GET, $extra);
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Products — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        .filter-bar-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 18px;
        }
        .filter-label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
        .range-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
        .range-tab {
            padding: 7px 14px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            background: var(--surface-2);
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all .15s;
            white-space: nowrap;
        }
        .range-tab:hover { border-color: var(--accent); color: var(--accent); }
        .range-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
        .filter-divider { width: 1px; height: 28px; background: var(--border); flex-shrink: 0; }
        .cat-select {
            padding: 7px 12px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: var(--surface-2);
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
            outline: none;
            cursor: pointer;
            transition: border-color .15s;
        }
        .cat-select:focus { border-color: var(--accent); }
        .btn-export {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 16px;
            border-radius: 8px;
            border: 1.5px solid #22c55e;
            background: rgba(34,197,94,.1);
            color: #22c55e;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all .15s;
            white-space: nowrap;
        }
        .btn-export:hover { background: #22c55e; color: #fff; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        @media (max-width: 900px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .stats-row { grid-template-columns: 1fr 1fr; } }

        .low-stock-banner {
            background: rgba(239,68,68,.08);
            border: 1.5px solid rgba(239,68,68,.3);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .lsb-icon { font-size: 20px; color: #ef4444; flex-shrink: 0; margin-top: 1px; }
        .lsb-title { font-size: 13px; font-weight: 800; color: #ef4444; margin-bottom: 6px; }
        .lsb-items { display: flex; flex-wrap: wrap; gap: 6px; }
        .lsb-badge {
            padding: 3px 10px;
            border-radius: 6px;
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.25);
            font-size: 11px;
            font-weight: 700;
            color: #ef4444;
        }
        .lsb-badge .stock-num { color: #fca5a5; }

        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .charts-row-3 {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .charts-row-4 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 900px) {
            .charts-row, .charts-row-3, .charts-row-4 { grid-template-columns: 1fr; }
        }

        .chart-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.18);
        }
        .chart-panel-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-panel-title i { color: var(--accent); font-size: 13px; }
        .chart-panel-subtitle { font-size: 11px; color: var(--muted); font-weight: 600; margin-left: auto; }

        .donut-wrap {
            position: relative;
            width: 220px;
            height: 220px;
            margin: 0 auto 16px;
        }
        .donut-center {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            pointer-events: none;
        }
        .donut-center-val { font-size: 22px; font-weight: 900; color: #d4650f; line-height: 1; }
        .donut-center-label { font-size: 10px; color: var(--muted); font-weight: 700; margin-top: 4px; text-transform: uppercase; letter-spacing: .4px; }

        .chart-legend { display: flex; flex-direction: column; gap: 7px; margin-top: 4px; }
        .legend-item { display: flex; align-items: center; gap: 10px; font-size: 12px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
        .legend-name { flex: 1; color: var(--text); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }
        .legend-val { font-weight: 800; color: #d4650f; font-size: 12px; }
        .legend-pct { font-size: 10px; color: var(--muted); min-width: 36px; text-align: right; }

        .trend-canvas-wrap { height: 200px; position: relative; }

        .hbar-list { display: flex; flex-direction: column; gap: 12px; }
        .hbar-top { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px; }
        .hbar-name { font-weight: 700; color: var(--text); }
        .hbar-meta { color: var(--muted); font-size: 11px; }
        .hbar-track { height: 9px; background: var(--surface-3); border-radius: 10px; overflow: hidden; }
        .hbar-fill { height: 100%; border-radius: 10px; transition: width .8s ease; }

        .stock-ok  { color: #22c55e; font-weight: 800; }
        .stock-low { color: #f59e0b; font-weight: 800; }
        .stock-out { color: #ef4444; font-weight: 800; }

        .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; font-size: 16px; }
        .rank-1 { background: rgba(245,200,66,.15); }
        .rank-2 { background: var(--surface-3); }
        .rank-3 { background: rgba(212,101,15,.12); }
        .rank-n { background: var(--surface-3); font-size: 11px; font-weight: 800; color: var(--muted); }

        .mini-bar-wrap { display: flex; align-items: center; gap: 6px; }
        .mini-bar-track { flex: 1; height: 5px; background: var(--surface-3); border-radius: 5px; overflow: hidden; min-width: 50px; }
        .mini-bar-fill { height: 100%; border-radius: 5px; background: linear-gradient(90deg, #d4650f, #f0874a); }
        .mini-bar-pct { font-size: 10px; color: var(--muted); font-weight: 700; min-width: 30px; text-align: right; }

        .empty-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 60px 20px; text-align: center; }

        .hour-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .hour-item {
            flex: 1;
            min-width: 70px;
            text-align: center;
            padding: 10px 6px;
            background: var(--surface-2);
            border-radius: 10px;
            transition: all .2s;
        }
        .hour-item.peak {
            background: rgba(212,101,15,.15);
            border: 1px solid rgba(212,101,15,.3);
        }
        .hour-num { font-size: 11px; font-weight: 700; color: var(--muted); margin-bottom: 4px; }
        .hour-orders { font-size: 16px; font-weight: 800; color: var(--accent); }
        .hour-rev { font-size: 10px; color: var(--muted); margin-top: 3px; }

        .customer-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: var(--surface-2);
            border-radius: 10px;
            margin-bottom: 8px;
        }
        .customer-rank {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #fff;
        }
        .customer-info { flex: 1; }
        .customer-name { font-weight: 700; color: var(--text); font-size: 13px; }
        .customer-orders { font-size: 11px; color: var(--muted); }
        .customer-spent { font-weight: 800; color: var(--accent); }

        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
        }
        .revenue-breakdown {
            background: rgba(212,101,15,.05);
            border-radius: 10px;
            padding: 10px 15px;
            margin-top: 10px;
            font-size: 11px;
        }
        .revenue-breakdown span { color: var(--muted); }
        .revenue-breakdown strong { color: var(--accent); }

        .rev-note {
            font-size: 10px;
            color: var(--muted);
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 3px 8px;
            margin-top: 6px;
            display: inline-block;
        }

        /* Dashboard-match indicator */
        .match-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 700;
            color: #22c55e;
            background: rgba(34,197,94,.1);
            border: 1px solid rgba(34,197,94,.3);
            border-radius: 6px;
            padding: 2px 8px;
        }
    </style>
</head>
<body>

<?php include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">Top Selling <span>Products</span></div>
        <div class="topbar-right">
            <div class="topbar-date"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y') ?></div>
        </div>
    </div>

    <div class="content">

        <!-- Filter Bar -->
        <div class="filter-bar-wrap">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Period</span>
            <div class="range-tabs">
                <?php foreach (['today'=>'Today','7days'=>'7 Days','30days'=>'30 Days','all'=>'All Time'] as $k=>$v): ?>
                <a href="?range=<?= $k ?>&cat=<?= urlencode($cat_filter) ?>"
                   class="range-tab <?= $range===$k?'active':'' ?>"><?= $v ?></a>
                <?php endforeach; ?>
            </div>

            <div class="filter-divider"></div>

            <span class="filter-label"><i class="fas fa-tag"></i> Category</span>
            <select class="cat-select" onchange="location.href='?range=<?= $range ?>&cat='+this.value">
                <option value="all" <?= $cat_filter==='all'?'selected':'' ?>>All Categories</option>
                <?php foreach ($all_cats as $c): ?>
                <option value="<?= htmlspecialchars($c['categoryid']) ?>" <?= $cat_filter===$c['categoryid']?'selected':'' ?>>
                    <?= htmlspecialchars($c['category_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <a href="<?= export_url(['export'=>'csv']) ?>" class="btn-export">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
        </div>

        <!-- Low Stock Warning Banner -->
        <?php if (!empty($low_stock_stars)): ?>
        <div class="low-stock-banner">
            <div class="lsb-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="lsb-title">⚠️ Top Sellers Running Low on Stock!</div>
                <div class="lsb-items">
                    <?php foreach ($low_stock_stars as $p): ?>
                    <span class="lsb-badge">
                        <?= htmlspecialchars($p['name']) ?>
                        — <span class="stock-num"><?= (int)$p['stock'] === 0 ? 'OUT' : (int)$p['stock'].' left' ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card stat-success">
                <div class="sc-icon"><i class="fas fa-boxes"></i></div>
                <div class="sc-val"><?= number_format($total_qty) ?></div>
                <div class="sc-label">Total Units Sold</div>
            </div>
            <div class="stat-card stat-accent">
                <div class="sc-icon"><i class="fas fa-receipt"></i></div>
                <div class="sc-val"><?= number_format($total_unique_orders) ?></div>
                <div class="sc-label">Total Orders (excl. cancelled)</div>
            </div>
            <!-- FIX: Total Revenue now matches dashboard — same SQL filter (status != 'cancelled') -->
            <div class="stat-card stat-warning">
                <div class="sc-icon"><i class="fas fa-coins"></i></div>
                <div class="sc-val">RM <?= number_format($total_rev, 2) ?></div>
                <div class="sc-label">
                    Total Revenue
                    <span class="match-badge" title="Uses same filter as Dashboard"><i class="fas fa-check-circle"></i> = Dashboard</span>
                </div>
            </div>
            <div class="stat-card" style="background:var(--surface);border:1px solid var(--border);">
                <div class="sc-icon" style="background:rgba(168,85,247,.12);color:#a855f7;"><i class="fas fa-chart-line"></i></div>
                <div class="sc-val" style="color:#a855f7;">RM <?= number_format($avg_order_val, 2) ?></div>
                <div class="sc-label">Avg. Order Value</div>
            </div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="revenue-breakdown" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div><span>📊 Items Subtotal (pre-tax):</span> <strong>RM <?= number_format($subtotal_amount, 2) ?></strong></div>
                <div><span>🏷️ Voucher Discounts applied:</span> <strong style="color:#16a34a;">- RM <?= number_format($total_discount, 2) ?></strong></div>
                <div><span>💰 Tax collected:</span> <strong>+ RM <?= number_format($total_tax, 2) ?></strong></div>
                <div><span>✅ Final Revenue (order totals):</span> <strong style="color:#d4650f;">RM <?= number_format($total_rev, 2) ?></strong></div>
            </div>
            <div style="margin-top: 8px; font-size: 10px; color: var(--muted);">
                ℹ️ <em>Revenue = SUM(orders.total_price) WHERE status ≠ 'cancelled' — identical to Dashboard.
                Discount read from orders.discount_rm (stored per order). Items subtotal used only for per-product share %.</em>
            </div>
        </div>

        <?php if (empty($top_products)): ?>
        <div class="empty-panel">
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>No sales data found for <strong><?= $range_label ?></strong><?= $cat_filter !== 'all' ? ' in this category' : '' ?>.</p>
            </div>
        </div>

        <?php else: ?>

        <!-- Charts Row 1: Donuts -->
        <div class="charts-row">
            <div class="chart-panel">
                <div class="chart-panel-title">
                    <i class="fas fa-chart-pie"></i> Units Sold — Top <?= count($chart_products) ?>
                    <span class="chart-panel-subtitle"><?= $range_label ?></span>
                </div>
                <div class="donut-wrap">
                    <canvas id="donutQty"></canvas>
                    <div class="donut-center">
                        <div class="donut-center-val"><?= number_format($total_qty) ?></div>
                        <div class="donut-center-label">Total Units</div>
                    </div>
                </div>
                <div class="chart-legend">
                    <?php foreach ($chart_products as $i => $p):
                        $pct = $total_qty > 0 ? round(($p['total_quantity'] / $total_qty) * 100, 1) : 0;
                    ?>
                    <div class="legend-item">
                        <div class="legend-dot" style="background:<?= $colors[$i % count($colors)] ?>"></div>
                        <span class="legend-name" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars(mb_substr($p['name'],0,22)) ?></span>
                        <span class="legend-val"><?= number_format((int)$p['total_quantity']) ?></span>
                        <span class="legend-pct"><?= $pct ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="chart-panel">
                <div class="chart-panel-title">
                    <i class="fas fa-chart-pie"></i> Item Revenue Share — Top <?= count($chart_products) ?>
                    <span class="chart-panel-subtitle"><?= $range_label ?></span>
                </div>
                <div class="donut-wrap">
                    <canvas id="donutRev"></canvas>
                    <div class="donut-center">
                        <div class="donut-center-val" style="font-size:14px;">RM <?= number_format($subtotal_amount,0) ?></div>
                        <div class="donut-center-label">Items Subtotal</div>
                    </div>
                </div>
                <div class="chart-legend">
                    <?php foreach ($chart_products as $i => $p):
                        $pct = $subtotal_amount > 0 ? round(((float)$p['item_subtotal'] / $subtotal_amount) * 100, 1) : 0;
                    ?>
                    <div class="legend-item">
                        <div class="legend-dot" style="background:<?= $colors[$i % count($colors)] ?>"></div>
                        <span class="legend-name" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars(mb_substr($p['name'],0,22)) ?></span>
                        <span class="legend-val">RM <?= number_format((float)$p['item_subtotal'],0) ?></span>
                        <span class="legend-pct"><?= $pct ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="rev-note">* Item subtotals only — excludes tax &amp; vouchers</div>
            </div>
        </div>

        <!-- Charts Row 2: Trend + Category -->
        <div class="charts-row-3">
            <div class="chart-panel">
                <div class="chart-panel-title">
                    <i class="fas fa-chart-line"></i> 7-Day Sales Trend
                    <span class="chart-panel-subtitle">Units &amp; Item Revenue</span>
                </div>
                <div class="trend-canvas-wrap">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="chart-panel">
                <div class="chart-panel-title">
                    <i class="fas fa-layer-group"></i> By Category
                    <span class="chart-panel-subtitle">Item Revenue</span>
                </div>
                <div class="donut-wrap" style="width:160px;height:160px;">
                    <canvas id="donutCat"></canvas>
                    <div class="donut-center">
                        <div class="donut-center-val" style="font-size:13px;"><?= count($cat_rev) ?></div>
                        <div class="donut-center-label">Categories</div>
                    </div>
                </div>
                <div class="chart-legend">
                    <?php $ci = 0; foreach ($cat_rev as $cn => $cv):
                        $pct = $subtotal_amount > 0 ? round(($cv['rev'] / $subtotal_amount)*100, 1) : 0;
                    ?>
                    <div class="legend-item">
                        <div class="legend-dot" style="background:<?= $colors[$ci % count($colors)] ?>"></div>
                        <span class="legend-name"><?= htmlspecialchars($cn) ?></span>
                        <span class="legend-val" style="font-size:11px;">RM <?= number_format($cv['rev'],0) ?></span>
                        <span class="legend-pct"><?= $pct ?>%</span>
                    </div>
                    <?php $ci++; endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Row 3: Payment Methods + Top Customers + Peak Hours -->
        <div class="charts-row-4">
            <div class="chart-panel">
                <div class="chart-panel-title">
                    <i class="fas fa-credit-card"></i> Payment Methods
                    <span class="chart-panel-subtitle">by order total</span>
                </div>
                <?php if (empty($payment_methods)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 20px;">No payment data available</p>
                <?php else: ?>
                    <canvas id="paymentChart" style="height: 180px; width: 100%;"></canvas>
                    <div style="margin-top: 15px;">
                        <?php
                        $payment_total = array_sum(array_column($payment_methods, 'revenue'));
                        foreach ($payment_methods as $pm):
                            $pct = $payment_total > 0 ? round($pm['revenue'] / $payment_total * 100, 1) : 0;
                        ?>
                        <div class="payment-row">
                            <span><i class="fas <?= $pm['payment_method'] == 'card' ? 'fa-credit-card' : ($pm['payment_method'] == 'cash' ? 'fa-money-bill' : 'fa-mobile-alt') ?>"></i> <?= ucfirst($pm['payment_method']) ?></span>
                            <span><strong><?= $pm['count'] ?> orders</strong> · RM <?= number_format($pm['revenue'], 2) ?> (<?= $pct ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-panel">
                <div class="chart-panel-title">
                    <i class="fas fa-trophy"></i> Top Customers
                    <span class="chart-panel-subtitle">By total spent</span>
                </div>
                <?php if (empty($top_customers)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 20px;">No customer data available</p>
                <?php else: ?>
                    <?php foreach ($top_customers as $i => $c): ?>
                    <div class="customer-card">
                        <div class="customer-rank" style="background: <?= $colors[$i % count($colors)] ?>;">
                            <?= $i + 1 ?>
                        </div>
                        <div class="customer-info">
                            <div class="customer-name"><?= htmlspecialchars($c['username'] ?? 'User') ?></div>
                            <div class="customer-orders"><?= $c['order_count'] ?> orders</div>
                        </div>
                        <div class="customer-spent">RM <?= number_format($c['total_spent'] ?? 0, 2) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="chart-panel">
                <div class="chart-panel-title">
                    <i class="fas fa-clock"></i> Peak Hours
                    <span class="chart-panel-subtitle">When customers order most</span>
                </div>
                <div class="hour-grid">
                    <?php
                    $max_hour_orders = max($hourly_counts) ?: 1;
                    for ($h = 0; $h < 24; $h++):
                        $is_peak = $hourly_counts[$h] > 0 && $hourly_counts[$h] >= $max_hour_orders * 0.6;
                    ?>
                    <div class="hour-item <?= $is_peak ? 'peak' : '' ?>">
                        <div class="hour-num"><?= sprintf('%02d:00', $h) ?></div>
                        <div class="hour-orders"><?= $hourly_counts[$h] ?></div>
                        <div class="hour-rev">RM <?= number_format($hourly_revenue[$h], 0) ?></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Horizontal Bar Chart -->
        <div class="chart-panel" style="margin-bottom:20px;">
            <div class="chart-panel-title"><i class="fas fa-bars"></i> Sales Volume Comparison</div>
            <div class="hbar-list">
                <?php foreach ($chart_products as $i => $p):
                    $pct = $max_qty > 0 ? round(($p['total_quantity'] / $max_qty) * 100, 1) : 0;
                ?>
                <div class="hbar-item">
                    <div class="hbar-top">
                        <span class="hbar-name">
                            <?php
                            if     ($i===0) echo '🥇 ';
                            elseif ($i===1) echo '🥈 ';
                            elseif ($i===2) echo '🥉 ';
                            else           echo '#'.($i+1).' ';
                            echo htmlspecialchars(mb_substr($p['name'],0,28));
                            ?>
                        </span>
                        <span class="hbar-meta"><?= number_format((int)$p['total_quantity']) ?> units · RM <?= number_format((float)$p['item_subtotal'],2) ?> subtotal</span>
                    </div>
                    <div class="hbar-track">
                        <div class="hbar-fill" style="width:<?= $pct ?>%;background:<?= $colors[$i%count($colors)] ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Full Rankings Table -->
        <div class="panel">
            <div class="panel-header" style="flex-wrap:wrap;gap:10px;">
                <div class="panel-title"><i class="fas fa-trophy"></i> Full Rankings
                    <span style="font-size:11px;font-weight:600;color:var(--muted);margin-left:8px;"><?= $range_label ?></span>
                </div>
                <a href="<?= export_url(['export'=>'csv']) ?>" class="btn-export" style="margin-left:auto;">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            </div>
            <div class="table-wrap">
                <table class="dt">
                    <thead>
                        <tr>
                            <th style="width:52px;">Rank</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Qty Sold</th>
                            <th style="text-align:right;">Avg RM/Line</th>
                            <th style="text-align:right;">Item Subtotal</th>
                            <th style="text-align:center;">Stock</th>
                            <th style="text-align:right;" title="Share of total items subtotal">Subtotal Share</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $rank = 1; foreach ($top_products as $p):
                        $share  = $subtotal_amount > 0 ? round(((float)$p['item_subtotal'] / $subtotal_amount)*100, 1) : 0;
                        $avg_l  = $p['total_orders'] > 0 ? (float)$p['item_subtotal'] / (int)$p['total_orders'] : 0;
                        $stock  = (int)$p['stock'];
                        $stkcls = $stock === 0 ? 'stock-out' : ($stock <= 10 ? 'stock-low' : 'stock-ok');
                        $stkicon= $stock === 0 ? '🚫' : ($stock <= 10 ? '⚠️' : '✅');
                    ?>
                    <tr>
                        <td>
                            <?php if ($rank <= 3): ?>
                                <span class="rank-badge rank-<?= $rank ?>">
                                    <?= $rank===1?'🥇':($rank===2?'🥈':'🥉') ?>
                                </span>
                            <?php else: ?>
                                <span class="rank-badge rank-n">#<?= $rank ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-600" style="font-size:13px;"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= $p['productid'] ?></div>
                        </td>
                        <td>
                            <span class="badge badge-customer" style="text-transform:none;letter-spacing:0;">
                                <?= htmlspecialchars($p['category_name'] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td style="text-align:right;" class="text-muted">RM <?= number_format((float)$p['price'],2) ?></td>
                        <td style="text-align:right;">
                            <div class="mini-bar-wrap">
                                <div class="mini-bar-track">
                                    <div class="mini-bar-fill" style="width:<?= $max_qty>0?round(($p['total_quantity']/$max_qty)*100):0 ?>%"></div>
                                </div>
                                <span class="fw-700 text-success"><?= number_format((int)$p['total_quantity']) ?></span>
                            </div>
                        </td>
                        <td style="text-align:right;" class="text-muted">RM <?= number_format($avg_l,2) ?></td>
                        <td style="text-align:right;" class="fw-700 text-accent">RM <?= number_format((float)$p['item_subtotal'],2) ?></td>
                        <td style="text-align:center;">
                            <span class="<?= $stkcls ?>"><?= $stkicon ?> <?= $stock===0?'Out':$stock ?></span>
                        </td>
                        <td style="text-align:right;">
                            <span style="font-size:11px;font-weight:700;color:#d4650f;"><?= $share ?>%</span>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid var(--border); font-size: 11px; color: var(--muted);">
                            <td colspan="6" style="padding: 10px; font-style: italic;">
                                * Item Subtotal = SUM(order_items.subtotal) — pre-tax, pre-voucher.<br>
                                  Total Revenue (RM <?= number_format($total_rev,2) ?>) = SUM(orders.total_price) where status ≠ 'cancelled' — matches Dashboard.
                            </td>
                            <td style="text-align:right; padding: 10px; font-weight: 800; color: var(--accent);">
                                RM <?= number_format($subtotal_amount, 2) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php endif; ?>
    </div>
</main>

<script>
const chartColors = <?= json_encode($colors) ?>;

new Chart(document.getElementById('donutQty'), {
    type: 'doughnut',
    data: {
        labels: [<?= implode(',', array_map(fn($p)=>'"'.addslashes(mb_substr($p['name'],0,20)).'"', $chart_products)) ?>],
        datasets: [{ data: [<?= implode(',', array_column($chart_products,'total_quantity')) ?>], backgroundColor: chartColors, borderWidth: 3, borderColor: '#141416', hoverOffset: 8 }]
    },
    options: { cutout:'68%', plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: ctx=>` ${ctx.label}: ${ctx.parsed.toLocaleString()} units` } } }, animation:{animateRotate:true,duration:900} }
});

new Chart(document.getElementById('donutRev'), {
    type: 'doughnut',
    data: {
        labels: [<?= implode(',', array_map(fn($p)=>'"'.addslashes(mb_substr($p['name'],0,20)).'"', $chart_products)) ?>],
        datasets: [{ data: [<?= implode(',', array_map(fn($p)=>round((float)$p['item_subtotal'],2), $chart_products)) ?>], backgroundColor: chartColors, borderWidth: 3, borderColor: '#141416', hoverOffset: 8 }]
    },
    options: { cutout:'68%', plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: ctx=>` ${ctx.label}: RM ${ctx.parsed.toLocaleString('en-MY',{minimumFractionDigits:2})} (subtotal)` } } }, animation:{animateRotate:true,duration:900} }
});

<?php if (!empty($cat_rev)): ?>
new Chart(document.getElementById('donutCat'), {
    type: 'doughnut',
    data: {
        labels: [<?= implode(',', array_map(fn($k)=>'"'.addslashes($k).'"', array_keys($cat_rev))) ?>],
        datasets: [{ data: [<?= implode(',', array_map(fn($v)=>round($v['rev'],2), array_values($cat_rev))) ?>], backgroundColor: chartColors, borderWidth: 3, borderColor: '#141416', hoverOffset: 6 }]
    },
    options: { cutout:'64%', plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: ctx=>` ${ctx.label}: RM ${ctx.parsed.toLocaleString('en-MY',{minimumFractionDigits:2})}` } } }, animation:{animateRotate:true,duration:900} }
});
<?php endif; ?>

<?php if (!empty($payment_methods)): ?>
new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($payment_methods, 'payment_method')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($payment_methods, 'revenue')) ?>,
            backgroundColor: ['#d4650f', '#22c55e', '#3b82f6', '#a855f7', '#f59e0b'],
            borderWidth: 2,
            borderColor: '#141416'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom', labels: { color: '#9ca3af', font: { size: 10 } } },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: RM ${ctx.parsed.toFixed(2)}` } }
        }
    }
});
<?php endif; ?>

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend_labels) ?>,
        datasets: [
            {
                label: 'Units Sold',
                data: <?= json_encode($trend_qty) ?>,
                borderColor: '#d4650f',
                backgroundColor: 'rgba(212,101,15,.12)',
                pointBackgroundColor: '#d4650f',
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            },
            {
                label: 'Item Revenue (RM)',
                data: <?= json_encode($trend_rev) ?>,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,.08)',
                pointBackgroundColor: '#22c55e',
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: true,
                labels: { color: '#9ca3af', font: { size: 11, weight: '700' }, boxWidth: 10, boxHeight: 10, borderRadius: 3 }
            },
            tooltip: {
                backgroundColor: '#1f2023',
                borderColor: '#2c2d31',
                borderWidth: 1,
                titleColor: '#f5f5f5',
                bodyColor: '#9ca3af',
                callbacks: {
                    label: ctx => ctx.datasetIndex === 0
                        ? ` Units: ${ctx.parsed.y.toLocaleString()}`
                        : ` Item Revenue: RM ${ctx.parsed.y.toLocaleString('en-MY',{minimumFractionDigits:2})}`
                }
            }
        },
        scales: {
            x: { ticks: { color: '#6b7280', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,.04)' } },
            y: {
                position: 'left',
                ticks: { color: '#d4650f', font: { size: 10 } },
                grid: { color: 'rgba(255,255,255,.04)' },
                title: { display: true, text: 'Units', color: '#d4650f', font: { size: 10 } }
            },
            y1: {
                position: 'right',
                ticks: { color: '#22c55e', font: { size: 10 }, callback: v => 'RM '+v },
                grid: { drawOnChartArea: false },
                title: { display: true, text: 'Item Revenue', color: '#22c55e', font: { size: 10 } }
            }
        }
    }
});
</script>

</body>
</html>