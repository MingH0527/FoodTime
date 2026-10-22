<?php
session_start();
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name    = $is_logged_in ? (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '') : '';
$user_email   = $is_logged_in ? (isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '') : '';
$user_phone   = $is_logged_in ? (isset($_SESSION['user_phone']) ? htmlspecialchars($_SESSION['user_phone']) : '') : '';

if (!$is_logged_in) { header("Location: login.php"); exit(); }

$success    = false;
$order_id   = null;
$error_msg  = '';
$paid_total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_data     = $_POST['cart_data']  ?? '[]';
    $order_type    = $_POST['order_type'] ?? 'dinein';
    $voucher_data  = $_POST['voucher_data'] ?? '';
    $cart          = json_decode($cart_data, true);
    $order_type_db = ($order_type === 'dinein') ? 'dine_in' : 'takeaway';

    if (empty($cart) || !is_array($cart)) {
        $error_msg = 'Your cart is empty.';
    } else {
        // Fetch DB prices
        $safe_ids = [];
        foreach ($cart as $item) {
            $safe_ids[] = "'" . mysqli_real_escape_string($connect, trim($item['id'])) . "'";
        }
        $id_list = implode(',', $safe_ids);
        $pq      = mysqli_query($connect, "SELECT productid, price, name FROM products WHERE productid IN ($id_list)");
        $prices  = [];
        if ($pq) {
            while ($r = mysqli_fetch_assoc($pq)) {
                $prices[$r['productid']] = ['price' => (float)$r['price'], 'name' => $r['name']];
            }
        }

        // Calculate subtotal
        $subtotal = 0;
        foreach ($cart as $item) {
            $stored_price = isset($item['price']) ? floatval($item['price']) : 0;
            $db_price     = isset($prices[$item['id']]) ? $prices[$item['id']]['price'] : 0;
            $unit_price   = ($stored_price > 0) ? $stored_price : $db_price;
            $subtotal    += $unit_price * intval($item['quantity'] ?? 1);
        }
        $subtotal = round($subtotal, 2);

        // Apply voucher discount if provided
        $discount     = 0;
        $voucher_id   = null;
        $voucher_code = '';
        $voucher      = null;
        if (!empty($voucher_data)) {
            $vd = json_decode($voucher_data, true);
            if ($vd && isset($vd['discount_rm']) && isset($vd['voucherid'])) {
                $discount     = min(round((float)$vd['discount_rm'], 2), $subtotal);
                $voucher_id   = mysqli_real_escape_string($connect, $vd['voucherid']);
                $voucher_code = mysqli_real_escape_string($connect, $vd['code'] ?? '');
                $voucher      = $vd;
            }
        }

        // ── FIX: Tax is on FULL subtotal, discount deducted after ──
        $tax        = round($subtotal * 0.06, 2);
        $total      = round($subtotal + $tax - $discount, 2);
        $paid_total = $total;
        $created_at = date('Y-m-d H:i:s');

        mysqli_begin_transaction($connect);

        // Generate Order ID
        $res      = mysqli_query($connect, "SELECT orderid FROM orders WHERE orderid REGEXP '^ORD[0-9]{5}$' ORDER BY orderid DESC LIMIT 1 FOR UPDATE");
        $next_num = 1;
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            if (preg_match('/ORD-?(\d+)/i', $row['orderid'], $m)) $next_num = intval($m[1]) + 1;
        }
        $new_order_id = 'ORD' . str_pad($next_num, 5, '0', STR_PAD_LEFT);

        // Generate Payment ID
        $pay_res  = mysqli_query($connect, "SELECT paymentid FROM payments WHERE paymentid REGEXP '^PAY[0-9]{5}$' ORDER BY paymentid DESC LIMIT 1");
        $pay_num  = 1;
        if ($pay_res && mysqli_num_rows($pay_res) > 0) {
            $pay_row = mysqli_fetch_assoc($pay_res);
            $pay_num = intval(substr($pay_row['paymentid'], 3)) + 1;
        }
        $new_pay_id = 'PAY' . str_pad($pay_num, 5, '0', STR_PAD_LEFT);

        $uid_esc   = mysqli_real_escape_string($connect, $user_id);
        $name_esc  = mysqli_real_escape_string($connect, $user_name);
        $email_esc = mysqli_real_escape_string($connect, $user_email);
        $phone_esc = mysqli_real_escape_string($connect, $user_phone);
        $ot_esc    = mysqli_real_escape_string($connect, $order_type_db);

        // Build special notes with voucher info if applicable
        $notes = '';
        if ($discount > 0 && $voucher_code) {
            $notes = mysqli_real_escape_string($connect, "Voucher: $voucher_code (RM " . number_format($discount, 2) . " off)");
        }

        $sql = "INSERT INTO orders (orderid, userid, order_type, tableid, customer_name, customer_phone, customer_email,
                    subtotal, discount_rm, tax, total_price, payment_method, special_notes, status, points_earned, created_at, updated_at)
                VALUES ('$new_order_id', '$uid_esc', '$ot_esc', NULL, '$name_esc', '$phone_esc', '$email_esc',
                    $subtotal, $discount, $tax, $total, 'cash', '$notes', 'pending', 0, '$created_at', '$created_at')";

        if (mysqli_query($connect, $sql)) {
            $order_id = $new_order_id;

            // Order items
            $oi_res = mysqli_query($connect, "SELECT orderitemid FROM order_items WHERE orderitemid REGEXP '^OI[0-9]{5}$' ORDER BY orderitemid DESC LIMIT 1");
            $oi_num = 0;
            if ($oi_res && mysqli_num_rows($oi_res) > 0) {
                $oi_row = mysqli_fetch_assoc($oi_res);
                $oi_num = intval(substr($oi_row['orderitemid'], 2));
            }

            foreach ($cart as $item) {
                $pid = mysqli_real_escape_string($connect, trim($item['id'] ?? ''));
                $qty = intval($item['quantity'] ?? 1);
                if (empty($pid) || $qty <= 0 || !isset($prices[$pid])) continue;

                $pname        = mysqli_real_escape_string($connect, $prices[$pid]['name']);
                $stored_price = isset($item['price']) ? floatval($item['price']) : 0;
                $pprice       = ($stored_price > 0) ? $stored_price : floatval($prices[$pid]['price']);
                $psub         = round($pprice * $qty, 2);

                $custom = [];
                if (!empty($item['spice_level']))                             $custom['spice_level'] = $item['spice_level'];
                if (!empty($item['bev_temp']))                                $custom['bev_temp']    = $item['bev_temp'];
                if (!empty($item['serve_time']))                              $custom['serve_time']  = $item['serve_time'];
                if (!empty($item['removes']) && is_array($item['removes']))  $custom['removes']     = $item['removes'];
                if (!empty($item['addons'])  && is_array($item['addons']))   $custom['addons']      = $item['addons'];
                $custom_json = !empty($custom)
                    ? "'" . mysqli_real_escape_string($connect, json_encode($custom)) . "'"
                    : 'NULL';

                $oi_num++;
                $new_oi_id = 'OI' . str_pad($oi_num, 5, '0', STR_PAD_LEFT);
                mysqli_query($connect, "INSERT INTO order_items (orderitemid, orderid, productid, product_name, quantity, price, subtotal, custom_options)
                    VALUES ('$new_oi_id', '$new_order_id', '$pid', '$pname', $qty, $pprice, $psub, $custom_json)");
            }

            // Deduct stock
            foreach ($cart as $item) {
                $pid = mysqli_real_escape_string($connect, trim($item['id'] ?? ''));
                $qty = intval($item['quantity'] ?? 1);
                if (empty($pid) || $qty <= 0) continue;
                mysqli_query($connect, "UPDATE products SET stock = GREATEST(0, stock - $qty) WHERE productid = '$pid'");
            }

            // Payment record — cash stays 'pending' until counter confirms
            mysqli_query($connect, "INSERT INTO payments (paymentid, orderid, user_id, method, status, amount, created_at)
                VALUES ('$new_pay_id', '$new_order_id', '$uid_esc', 'cash', 'pending', $total, '$created_at')");

            // Mark voucher as used
            if ($voucher_id) {
                mysqli_query($connect, "UPDATE user_vouchers SET is_used = 1, used_at = '$created_at', used_order_id = '$new_order_id'
                    WHERE voucherid = '$voucher_id' AND userid = '$uid_esc'");
            }

            mysqli_commit($connect);
            $success = true;
        } else {
            mysqli_rollback($connect);
            $error_msg = 'Order failed: ' . mysqli_error($connect);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Payment - FOODTIME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/payment.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="index.php" class="logo"><i class="fas fa-burger"></i><span>FOODTIME</span></a>
                <ul class="nav-menu">
                    <li><a href="index.php#categories" data-i18n="nav-category">Category</a></li>
                    <li><a href="menu.php" data-i18n="nav-menu">Menu</a></li>
                    <li><a href="index.php#about" data-i18n="nav-about">About</a></li>
                </ul>
                <div class="auth-buttons">
                    <div class="language-switcher">
                        <button class="lang-btn" id="langBtn" title="Change Language" type="button"><i class="fas fa-globe"></i></button>
                        <div class="language-dropdown" id="languageDropdown">
                            <a href="#" data-lang="en">English</a>
                            <a href="#" data-lang="ms">Bahasa Melayu</a>
                            <a href="#" data-lang="zh">中文</a>
                        </div>
                    </div>
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode" type="button"><i class="fas fa-moon"></i></button>
                    <?php if ($is_logged_in): ?>
                        <span class="user-greeting">&#128075; Hi, <?php echo $user_name; ?></span>
                        <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> <span data-i18n="btn-orders">Orders</span></a>
                        <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> <span data-i18n="btn-cart">Cart</span></a>
                        <a href="logout.php" class="btn btn-logout" data-i18n="btn-logout">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <section class="payment-section">
        <div class="container">
            <h1 class="payment-page-title"><i class="fas fa-money-bill-wave"></i> Pay at Counter (Cash)</h1>

            <?php if ($success): ?>
            <div class="payment-layout">
                <div class="success-banner">
                    <div class="s-icon">&#127881;</div>
                    <h2>Order Confirmed!</h2>
                    <p>Your order has been placed. Please proceed to the counter and pay with cash when collecting your order.</p>
                    <div class="success-meta">
                        <div class="success-meta-item"><span>Order ID</span><strong><?php echo htmlspecialchars($order_id); ?></strong></div>
                        <div class="success-meta-item"><span>Payment</span><strong>Cash at Counter</strong></div>
                        <div class="success-meta-item"><span>Amount</span><strong>RM <?php echo number_format($paid_total, 2); ?></strong></div>
                        <div class="success-meta-item"><span>Status</span><strong>Pending</strong></div>
                    </div>
                    <div class="success-actions">
                        <a href="order_history.php" class="btn-success-primary"><i class="fas fa-history"></i> View My Orders</a>
                        <a href="menu.php" class="btn-success-secondary"><i class="fas fa-utensils"></i> Order More</a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <div class="payment-layout">
                <div class="payment-box">
                    <div class="pay-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <h2 class="pay-title">Pay at Counter (Cash)</h2>
                    <p class="pay-subtitle">Place your order now and pay with cash at the counter when you collect it.</p>

                    <?php if ($error_msg): ?>
                    <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
                    <?php endif; ?>

                    <div style="background:rgba(212,101,15,0.07);border-radius:12px;padding:18px 20px;margin-bottom:28px;text-align:left;">
                        <p style="margin:0 0 10px;font-weight:700;color:var(--light-text);font-size:15px;"><i class="fas fa-info-circle" style="color:var(--primary-color);"></i> &nbsp;Reminder</p>
                        <p style="margin:0 0 7px;font-size:13px;color:var(--light-gray);display:flex;gap:8px;"><i class="fas fa-check" style="color:#27ae60;margin-top:2px;"></i> Please prepare the exact amount shown in the order summary.</p>
                        <p style="margin:0 0 7px;font-size:13px;color:var(--light-gray);display:flex;gap:8px;"><i class="fas fa-check" style="color:#27ae60;margin-top:2px;"></i> Proceed to the counter and show your Order ID to the staff.</p>
                        <p style="margin:0;font-size:13px;color:var(--light-gray);display:flex;gap:8px;"><i class="fas fa-check" style="color:#27ae60;margin-top:2px;"></i> A receipt will be provided after payment at the counter.</p>
                    </div>

                    <form method="POST" id="cashForm">
                        <input type="hidden" name="cart_data"    id="cartDataInput">
                        <input type="hidden" name="order_type"   id="orderTypeInput" value="dinein">
                        <input type="hidden" name="voucher_data" id="voucherDataInput">
                        <button type="submit" class="btn-pay-primary"><i class="fas fa-check-circle"></i> Confirm Order</button>
                    </form>
                    <a href="checkout.php" class="btn-pay-secondary"><i class="fas fa-arrow-left"></i> Back to Checkout</a>
                </div>

                <div class="order-summary-box">
                    <div class="summary-heading"><i class="fas fa-receipt"></i> Order Summary</div>
                    <div class="summary-order-type"><i class="fas fa-concierge-bell"></i><span id="orderTypeDisplay">—</span></div>
                    <div class="summary-items" id="summaryItems">
                        <div class="skeleton" style="width:100%;margin-bottom:10px;"></div>
                        <div class="skeleton" style="width:80%;margin-bottom:10px;"></div>
                        <div class="skeleton" style="width:90%;"></div>
                    </div>
                    <div class="summary-totals">
                        <div class="summary-row"><span>Subtotal</span><strong id="summarySubtotal">RM —</strong></div>
                        <div class="summary-row" id="discountSummaryRow" style="display:none;color:#16a34a;">
                            <span><i class="fas fa-tag"></i> Voucher Discount</span>
                            <strong id="summaryDiscount">- RM 0.00</strong>
                        </div>
                        <div class="summary-row"><span>Service Tax (6%)</span><strong id="summaryTax">RM —</strong></div>
                        <div class="summary-row total"><span>Total</span><strong id="summaryTotal">RM —</strong></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section"><h4>&#127860; About FOODTIME</h4><a href="#">About Us</a><a href="#">Blog</a><a href="#">Careers</a></div>
                <div class="footer-section"><h4>Support</h4><a href="#">Help Center</a><a href="#">Contact Us</a><a href="#">FAQ</a></div>
                <div class="footer-section"><h4>Legal</h4><a href="#">Privacy Policy</a><a href="#">Terms &amp; Conditions</a><a href="#">Refund Policy</a></div>
                <div class="footer-section"><h4>Follow Us</h4><div class="social-links"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
            </div>
            <div class="footer-bottom"><p>&copy; 2026 FOODTIME. All rights reserved.</p></div>
        </div>
    </footer>

    <script src="js/translations.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/language.js"></script>
    <script>
    <?php if (!$success): ?>
        const TAX  = 0.06;
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const orderType = sessionStorage.getItem('orderType') || 'dinein';

        document.getElementById('orderTypeInput').value = orderType;
        document.getElementById('orderTypeDisplay').textContent = orderType === 'dinein' ? '🍽️  Dine In' : '🛍️  Takeaway';

        const voucherRaw = sessionStorage.getItem('appliedVoucher');
        let appliedVoucher = null;
        try { if (voucherRaw) appliedVoucher = JSON.parse(voucherRaw); } catch(e) {}

        function resolveUnitPrice(item, dbPrices) {
            const stored = parseFloat(item.price);
            if (!isNaN(stored) && stored > 0) return stored;
            return parseFloat((dbPrices[item.id] || {}).price) || 0;
        }

        if (cart.length > 0) {
            fetch('cart.php?action=get_prices&ids=' + cart.map(i => i.id).join(','))
                .then(r => r.json())
                .then(dbPrices => {
                    let sub = 0, html = '';
                    cart.forEach(item => {
                        const unitPrice = resolveUnitPrice(item, dbPrices);
                        const name      = (dbPrices[item.id] || {}).name || item.name || ('Product #' + item.id);
                        const qty       = Math.max(1, parseInt(item.quantity) || 1);
                        const line      = unitPrice * qty;
                        sub += line;
                        html += `<div class="summary-item"><div><div class="summary-item-name">${name}</div><div class="summary-item-qty">x${qty} &times; RM ${unitPrice.toFixed(2)}</div></div><div class="summary-item-price">RM ${line.toFixed(2)}</div></div>`;
                    });

                    // ── FIX: tax on FULL subtotal, discount deducted after ──
                    const discount = appliedVoucher ? Math.min(parseFloat(appliedVoucher.discount_rm) || 0, sub) : 0;
                    const tax      = sub * TAX;
                    const total    = sub + tax - discount;

                    document.getElementById('summaryItems').innerHTML      = html;
                    document.getElementById('summarySubtotal').textContent = 'RM ' + sub.toFixed(2);
                    document.getElementById('summaryTax').textContent      = 'RM ' + tax.toFixed(2);
                    document.getElementById('summaryTotal').textContent    = 'RM ' + total.toFixed(2);
                    if (discount > 0) {
                        document.getElementById('discountSummaryRow').style.display = 'flex';
                        document.getElementById('summaryDiscount').textContent = '- RM ' + discount.toFixed(2);
                    }
                })
                .catch(() => {
                    document.getElementById('summaryItems').innerHTML = '<p style="color:red;font-size:14px;">Failed to load order summary.</p>';
                });
        } else {
            document.getElementById('summaryItems').innerHTML = '<p style="color:var(--light-gray);font-size:14px;">Cart is empty.</p>';
        }

        document.getElementById('cashForm').addEventListener('submit', function () {
            document.getElementById('cartDataInput').value    = JSON.stringify(cart);
            document.getElementById('voucherDataInput').value = appliedVoucher ? JSON.stringify(appliedVoucher) : '';
        });
    <?php endif; ?>
    <?php if ($success): ?>
        localStorage.setItem('lastOrderTime', Date.now().toString());
        localStorage.removeItem('cart');
        localStorage.removeItem('cartLastModified');
        sessionStorage.removeItem('orderType');
        sessionStorage.removeItem('appliedVoucher');
        sessionStorage.removeItem('cartTotals');
    <?php endif; ?>
    </script>
</body>
</html>