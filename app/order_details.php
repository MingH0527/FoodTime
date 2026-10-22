<?php
session_start();
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name    = $is_logged_in ? (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '') : '';

if (!$is_logged_in) { header("Location: login.php"); exit(); }

$order_id = isset($_GET['orderid']) ? mysqli_real_escape_string($connect, $_GET['orderid']) : (isset($_GET['order_id']) ? mysqli_real_escape_string($connect, $_GET['order_id']) : '');

if ($order_id == 0) {
    ?><!DOCTYPE html><html lang="en" data-theme="light"><head><meta charset="UTF-8"><title>Error</title>
    <link rel="stylesheet" href="css/index.css">
    <style>body{padding:60px 20px;background:var(--dark-bg);}.box{max-width:500px;margin:0 auto;background:var(--card-bg);padding:40px;border-radius:20px;box-shadow:0 8px 32px rgba(212,101,15,0.12);text-align:center;color:var(--light-text);}h1{color:var(--primary-color);font-size:1.5rem;margin-bottom:10px;}a{color:var(--primary-color);font-weight:600;text-decoration:none;border-bottom:2px solid var(--primary-color);padding-bottom:2px;}</style></head>
    <body><div class="box"><div style="font-size:3rem;margin-bottom:15px;">⚠️</div><h1>Missing Order ID</h1><p>No order ID was provided.</p><p><a href="order_history.php">← Back to Order History</a></p></div></body></html><?php
    exit();
}

$order_result = mysqli_query($connect, "SELECT * FROM orders WHERE orderid = '$order_id' AND userid = '$user_id'");

if (!$order_result || mysqli_num_rows($order_result) == 0) {
    ?><!DOCTYPE html><html lang="en" data-theme="light"><head><meta charset="UTF-8"><title>Not Found</title>
    <link rel="stylesheet" href="css/index.css">
    <style>body{padding:60px 20px;background:var(--dark-bg);}.box{max-width:500px;margin:0 auto;background:var(--card-bg);padding:40px;border-radius:20px;box-shadow:0 8px 32px rgba(212,101,15,0.12);text-align:center;color:var(--light-text);}h1{color:var(--primary-color);font-size:1.5rem;margin-bottom:10px;}a{color:var(--primary-color);font-weight:600;text-decoration:none;border-bottom:2px solid var(--primary-color);padding-bottom:2px;}</style></head>
    <body><div class="box"><div style="font-size:3rem;margin-bottom:15px;">🔍</div><h1>Order Not Found</h1><p>This order doesn't exist or doesn't belong to your account.</p><p><a href="order_history.php">← Back to Order History</a></p></div></body></html><?php
    exit();
}

$order = mysqli_fetch_assoc($order_result);

$items_result = mysqli_query($connect, "SELECT oi.*, p.name FROM order_items oi
    JOIN products p ON oi.productid = p.productid
    WHERE oi.orderid = '$order_id'");

// Status logic
$payment_method = strtolower($order['payment_method'] ?? 'cash');
$raw_status     = strtolower($order['status'] ?? 'pending');
if ($payment_method === 'cash') {
    $display_status = $raw_status;
} else {
    $display_status = in_array($raw_status, ['completed', 'cancelled']) ? $raw_status : 'paid';
}

// Parse voucher from special_notes
$voucher_discount     = 0;
$voucher_code_display = '';
if (!empty($order['special_notes'])) {
    if (preg_match('/Voucher:\s*([A-Z0-9]+)\s*\(RM\s*([\d.]+)\s*off\)/i', $order['special_notes'], $vm)) {
        $voucher_code_display = $vm[1];
        $voucher_discount     = (float)$vm[2];
    }
}

// Helper: parse custom_options JSON into readable tags
function parseCustomOptions($json_str) {
    if (empty($json_str) || $json_str === 'NULL') return [];
    $data = json_decode($json_str, true);
    if (!is_array($data)) return [];
    $tags = [];
    if (isset($data['spice_level'])) {
        $tags[] = ['label' => '🌶 Spice', 'value' => ucfirst(str_replace('_', ' ', $data['spice_level'])), 'type' => 'spice'];
    }
    if (isset($data['removes']) && is_array($data['removes'])) {
        foreach ($data['removes'] as $key => $removed) {
            if ($removed === true || $removed === 'true') {
                $nice = ucwords(str_replace(['no_', '_'], ['No ', ' '], $key));
                $tags[] = ['label' => '✗ Remove', 'value' => $nice, 'type' => 'remove'];
            }
        }
    }
    if (isset($data['add_ons']) && is_array($data['add_ons'])) {
        foreach ($data['add_ons'] as $key => $added) {
            if ($added === true || $added === 'true') {
                $nice = ucwords(str_replace('_', ' ', $key));
                $tags[] = ['label' => '+ Add-on', 'value' => $nice, 'type' => 'addon'];
            }
        }
    }
    $handled = ['spice_level', 'removes', 'add_ons'];
    foreach ($data as $k => $v) {
        if (!in_array($k, $handled)) {
            if (is_bool($v) || $v === 'true' || $v === 'false') {
                if ($v === true || $v === 'true') {
                    $tags[] = ['label' => ucwords(str_replace('_', ' ', $k)), 'value' => '', 'type' => 'extra'];
                }
            } else {
                $tags[] = ['label' => ucwords(str_replace('_', ' ', $k)), 'value' => is_string($v) ? ucfirst($v) : '', 'type' => 'extra'];
            }
        }
    }
    return $tags;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details — FOODTIME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        /* ── Page wrapper ── */
        .order-details-section {
            padding: 40px 0 60px;
            background: var(--dark-bg);
            min-height: 600px;
        }

        .od-wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ── Page Header ── */
        .od-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 36px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
            flex-wrap: wrap;
        }
        .od-page-header h1 {
            color: var(--primary-color);
            margin: 0 0 8px;
            font-size: 2rem;
        }
        [data-theme="dark"] .od-page-header h1 { color: var(--accent-color); }
        .od-page-header p {
            color: var(--light-gray);
            font-size: 1rem;
            margin: 0;
        }

        /* ── Status & Type Badges ── */
        .od-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status-pending   { background: #fff3cd; color: #856404; }
        .status-paid      { background: #d4edda; color: #155724; }
        .status-preparing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .type-dine-in  { background: #e3f2fd; color: #1976d2; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; }
        .type-takeaway { background: #fff3e0; color: #f57c00; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; }

        /* ── Info Grid ── */
        .od-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 640px) { .od-info-grid { grid-template-columns: 1fr; } }

        /* ── Cards ── */
        .od-card {
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(212, 101, 15, 0.12);
        }
        [data-theme="dark"] .od-card { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4); }

        .od-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 17px 22px;
            border-bottom: 1px solid #e0e0e0;
            background: var(--primary-color);
        }
        [data-theme="dark"] .od-card-header { border-bottom-color: #444; }
        .od-card-header h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 600;
            color: white;
        }
        .od-card-header i { color: white; font-size: 1.1rem; }

        /* ── Info Rows ── */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 22px;
            border-bottom: 1px solid #e0e0e0;
            gap: 12px;
        }
        [data-theme="dark"] .info-row { border-bottom-color: #444; }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--light-gray);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .info-value {
            font-size: 1rem;
            color: var(--light-text);
            text-align: right;
            font-weight: 500;
        }

        /* ── Items Table ── */
        .od-items-table {
            width: 100%;
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(212, 101, 15, 0.12);
            margin-bottom: 20px;
        }
        [data-theme="dark"] .od-items-table { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4); }
        .od-items-table table { width: 100%; border-collapse: collapse; }
        .od-items-table th {
            background: var(--primary-color);
            color: white;
            padding: 16px 22px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .od-items-table th:last-child { text-align: right; }
        .od-items-table td {
            padding: 16px 22px;
            border-bottom: 1px solid #e0e0e0;
            color: var(--light-text);
            font-size: 0.98rem;
            vertical-align: top;
        }
        [data-theme="dark"] .od-items-table td { border-bottom-color: #444; }
        .od-items-table tbody tr:last-child td { border-bottom: none; }
        .od-items-table tbody tr:hover { background: rgba(212, 101, 15, 0.05); }
        .od-items-table td:last-child { text-align: right; font-weight: 600; }
        .item-name { font-weight: 600; margin-bottom: 6px; color: var(--light-text); font-size: 1rem; }

        /* ── Custom Options Tags ── */
        .custom-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
        .ctag {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            line-height: 1.4;
        }
        .ctag-spice  { background: #fff1f0; color: #dc2626; border: 1px solid #fca5a5; }
        .ctag-remove { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .ctag-addon  { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .ctag-extra  { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }

        /* ── Summary Card ── */
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 22px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 1rem;
            color: var(--light-text);
        }
        [data-theme="dark"] .summary-row { border-bottom-color: #444; }
        .summary-row:last-child { border-bottom: none; }
        .summary-row.discount { color: #16a34a; font-weight: 700; }
        .summary-row.total {
            background: var(--primary-color);
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            padding: 20px 22px;
        }
        .summary-row.total span:first-child { opacity: 0.88; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .summary-row.total span:last-child { font-size: 1.35rem; }

        /* ── Voucher chip ── */
        .voucher-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: #d4edda; color: #155724;
            border: 1px dashed #86efac;
            padding: 5px 14px; border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        /* ── Action Buttons ── */
        .od-actions {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        .btn-od {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        .btn-od-primary { background: var(--primary-color); color: white; }
        .btn-od-primary:hover { background: #b85509; }
        .btn-od-outline { background: var(--card-bg); color: var(--primary-color); border: 2px solid var(--primary-color); }
        .btn-od-outline:hover { background: rgba(212, 101, 15, 0.08); }
        .btn-od-blue { background: #2563eb; color: white; }
        .btn-od-blue:hover { background: #1d4ed8; }

        /* ── Language switcher ── */
        .language-switcher { position: relative; display: inline-block; }
        .language-dropdown { position: absolute; top: 100%; right: 0; background: var(--card-bg); border: 1px solid #e0e0e0; border-radius: 8px; min-width: 150px; display: none; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        [data-theme="dark"] .language-dropdown { border-color: #444; box-shadow: 0 4px 12px rgba(0,0,0,0.4); }
        .language-dropdown.show { display: block; }
        .language-dropdown a { display: block; padding: 10px 15px; color: var(--light-text); text-decoration: none; border-bottom: 1px solid #e0e0e0; transition: background 0.3s; }
        [data-theme="dark"] .language-dropdown a { border-bottom-color: #444; }
        .language-dropdown a:last-child { border-bottom: none; }
        .language-dropdown a:hover { background: rgba(212,101,15,0.1); color: var(--primary-color); }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="index.php" class="logo"><i class="fas fa-burger"></i><span>FOODTIME</span></a>
                <ul class="nav-menu">
                    <li><a href="index.php#categories">Category</a></li>
                    <li><a href="menu.php">Menu</a></li>
                    <li><a href="index.php#about">About</a></li>
                </ul>
                <div class="auth-buttons">
                    <div class="language-switcher">
                        <button class="lang-btn" id="langBtn" title="Change Language" type="button">
                            <i class="fas fa-globe"></i>
                        </button>
                        <div class="language-dropdown" id="languageDropdown">
                            <a href="#" data-lang="en">English</a>
                            <a href="#" data-lang="ms">Bahasa Melayu</a>
                            <a href="#" data-lang="zh">中文</a>
                        </div>
                    </div>
                    <button class="theme-toggle" id="themeToggle" type="button"><i class="fas fa-moon"></i></button>
                    <?php if ($is_logged_in): ?>
                        <span class="user-greeting">👋 Hi, <?php echo htmlspecialchars($user_name); ?></span>
                        <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> Orders</a>
                        <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Cart</a>
                        <a href="logout.php" class="btn btn-logout">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <section class="order-details-section">
        <div class="container">
            <div class="od-wrap">

                <!-- Page Header -->
                <div class="od-page-header">
                    <div>
                        <h1><i class="fas fa-receipt"></i> Order #<?php echo str_pad($order['orderid'], 6, '0', STR_PAD_LEFT); ?></h1>
                        <p>Placed on <?php echo date('d F Y, g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="od-badges">
                        <?php
                        $order_type = $order['order_type'] ?? 'dine_in';
                        if ($order_type === 'dine_in') {
                            echo '<span class="type-dine-in"><i class="fas fa-chair"></i> Dine In</span>';
                        } else {
                            echo '<span class="type-takeaway"><i class="fas fa-shopping-bag"></i> Takeaway</span>';
                        }
                        echo '<span class="status-badge status-' . $display_status . '">' . ucfirst($display_status) . '</span>';
                        ?>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="od-info-grid">

                    <!-- Order Info Card -->
                    <div class="od-card">
                        <div class="od-card-header">
                            <i class="fas fa-info-circle"></i>
                            <h3>Order Information</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date</span>
                            <span class="info-value"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Time</span>
                            <span class="info-value"><?php echo date('g:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Type</span>
                            <span class="info-value"><?php echo ($order_type === 'dine_in') ? '🍽️ Dine In' : '🛍️ Takeaway'; ?></span>
                        </div>
                        <?php if ($voucher_discount > 0): ?>
                        <div class="info-row">
                            <span class="info-label">Voucher</span>
                            <span class="info-value">
                                <span class="voucher-chip"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($voucher_code_display); ?> &minus; RM <?php echo number_format($voucher_discount, 2); ?></span>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Info Card -->
                    <div class="od-card">
                        <div class="od-card-header">
                            <i class="fas fa-credit-card"></i>
                            <h3>Payment Details</h3>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Method</span>
                            <span class="info-value"><?php
                                $method_labels = ['cash' => '💵 Cash', 'card' => '💳 Card', 'online_banking' => '🏦 Online Banking', 'ewallet' => '📱 E-Wallet'];
                                echo $method_labels[$payment_method] ?? ucfirst($payment_method);
                            ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Payment</span>
                            <span class="info-value"><?php echo ($display_status === 'paid' || $display_status === 'completed') ? '✅ Paid' : '⏳ Pending'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status</span>
                            <span class="info-value">
                                <span class="status-badge status-<?php echo $display_status; ?>"><?php echo ucfirst($display_status); ?></span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total</span>
                            <span class="info-value" style="color:var(--primary-color);font-size:1.15rem;font-weight:700;">RM <?php echo number_format((float)($order['total_price'] ?? 0), 2); ?></span>
                        </div>
                    </div>

                </div>

                <!-- Order Items -->
                <?php if ($items_result && mysqli_num_rows($items_result) > 0): ?>
                <div class="od-items-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = mysqli_fetch_assoc($items_result)):
                                $tags = parseCustomOptions($item['custom_options'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <?php if (!empty($tags)): ?>
                                    <div class="custom-tags">
                                        <?php foreach ($tags as $tag): ?>
                                        <span class="ctag ctag-<?php echo $tag['type']; ?>">
                                            <?php echo htmlspecialchars($tag['label']); ?>
                                            <?php if (!empty($tag['value'])): ?>: <strong><?php echo htmlspecialchars($tag['value']); ?></strong><?php endif; ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval($item['quantity']); ?>×</td>
                                <td>RM <?php echo number_format(floatval($item['price']), 2); ?></td>
                                <td>RM <?php echo number_format(intval($item['quantity']) * floatval($item['price']), 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Order Summary Card -->
                <div class="od-card">
                    <div class="od-card-header">
                        <i class="fas fa-calculator"></i>
                        <h3>Order Summary</h3>
                    </div>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>RM <?php echo number_format((float)($order['subtotal'] ?? 0), 2); ?></span>
                    </div>
                    <?php if ($voucher_discount > 0): ?>
                    <div class="summary-row discount">
                        <span><i class="fas fa-tag"></i> Voucher <?php echo $voucher_code_display ? "($voucher_code_display)" : ''; ?></span>
                        <span>− RM <?php echo number_format($voucher_discount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row">
                        <span>Tax (6%)</span>
                        <span>RM <?php echo number_format((float)($order['tax'] ?? 0), 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total Amount</span>
                        <span>RM <?php echo number_format((float)($order['total_price'] ?? 0), 2); ?></span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="od-actions">
                    <a href="receipt.php?orderid=<?php echo $order['orderid']; ?>" class="btn-od btn-od-blue">
                        <i class="fas fa-file-invoice"></i> View Receipt
                    </a>
                    <a href="order_history.php" class="btn-od btn-od-outline">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                </div>

            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <h4>🍴 About FOODTIME</h4>
                    <a href="#">About Us</a>
                    <a href="#">Blog</a>
                    <a href="#">Careers</a>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <a href="#">Help Center</a>
                    <a href="#">Contact Us</a>
                    <a href="#">FAQ</a>
                </div>
                <div class="footer-section">
                    <h4>Legal</h4>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms & Conditions</a>
                    <a href="#">Refund Policy</a>
                </div>
                <div class="footer-section">
                    <h4>Follow Us</h4>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 FOODTIME. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/translations.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/language.js"></script>
</body>
</html>