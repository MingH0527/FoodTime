<?php
/**
 * stripe_success.php
 *
 * Flow:
 *  1. Verify Stripe session — confirm payment_status === 'paid'.
 *  2. Deduplication check: has this session ID already been inserted?
 *       YES → load the existing order and show success UI immediately.
 *       NO  → insert orders + order_items + payments, deduct stock,
 *             award points, mark voucher, then show success UI.
 *  3. Clear localStorage / sessionStorage cart data via JS.
 *
 * Deduplication key: payments.special_note stores "stripe_session:<id>"
 * so that refreshing this page never creates a second order.
 */

session_start();
include 'base.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY', 'sk_test_51TIVNs2YxaDgs62n5oTjvCYoyecGQvmEXArp7dUMLS5Dn5T5kRAHmxgBlBSjnWqNYtpBdlx9yDrzJ9PzP4iOAt3Z00R1il7vIy');
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die('Stripe library missing. Run: composer require stripe/stripe-php');
}
require_once $autoload;
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// ── Read session_id from URL ──────────────────────────────────────────────────
$session_id = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';

$error      = '';
$order      = null;
$payment_ok = false;

if (empty($session_id)) {
    $error = 'Missing payment session information.';
} else {

    // ── Step 1: verify with Stripe API ────────────────────────────────────────
    try {
        $stripe_session = \Stripe\Checkout\Session::retrieve($session_id);

        if ($stripe_session->payment_status !== 'paid') {
            $error = 'Payment not completed. Status: ' . ($stripe_session->payment_status ?? 'unknown');
        } else {
            $payment_ok = true;
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        $error = 'Could not verify payment with Stripe: ' . $e->getMessage();
    }

    if ($payment_ok) {

        $sid_esc = mysqli_real_escape_string($connect, $session_id);

        // ── Step 2: deduplication — already inserted on a previous visit? ─────
        $dup_q = mysqli_query($connect,
            "SELECT o.*
             FROM payments p
             JOIN orders o ON o.orderid = p.orderid
             WHERE p.special_note LIKE '%stripe_session:{$sid_esc}%'
               AND p.status = 'paid'
             LIMIT 1"
        );

        if ($dup_q && mysqli_num_rows($dup_q) > 0) {
            // Already in DB — just show success, no second insert.
            $order = mysqli_fetch_assoc($dup_q);

        } else {
            // ── Step 3: first visit — reconstruct from metadata and insert ────

            $meta          = $stripe_session->metadata;
            $user_id       = $meta->user_id      ?? $_SESSION['user_id'];
            $user_name_m   = $meta->user_name    ?? ($_SESSION['user_name']  ?? '');
            $user_email_m  = $meta->user_email   ?? ($_SESSION['user_email'] ?? '');
            $user_phone_m  = $meta->user_phone   ?? ($_SESSION['user_phone'] ?? '');
            $order_type_db = $meta->order_type   ?? 'dine_in';
            $subtotal      = round((float)($meta->subtotal    ?? 0), 2);
            $discount_rm   = round((float)($meta->discount_rm ?? 0), 2);
            $tax           = round((float)($meta->tax         ?? 0), 2);
            $total_price   = round((float)($meta->total_price ?? 0), 2);
            $voucher_code  = $meta->voucher_code ?? '';

            // Reassemble chunked cart payload set by create_checkout_session.php
            $num_chunks = intval($meta->cart_chunks ?? 1);
            $cart_b64   = '';
            for ($i = 0; $i < $num_chunks; $i++) {
                $key       = 'cart_payload_' . $i;
                $cart_b64 .= $meta->$key ?? '';
            }
            $cart = json_decode(base64_decode($cart_b64), true);

            if (empty($cart) || !is_array($cart)) {
                $error      = 'Could not reconstruct order from payment session. '
                            . 'Please contact support with session ID: '
                            . htmlspecialchars($session_id);
                $payment_ok = false;
            } else {

                mysqli_begin_transaction($connect);
                try {

                    // ── Generate Order ID ──────────────────────────────────────
                    $ord_res  = mysqli_query($connect,
                        "SELECT orderid FROM orders
                         WHERE orderid REGEXP '^ORD[0-9]{5}$'
                         ORDER BY orderid DESC LIMIT 1 FOR UPDATE"
                    );
                    $next_num = 1;
                    if ($ord_res && mysqli_num_rows($ord_res) > 0) {
                        $ord_row = mysqli_fetch_assoc($ord_res);
                        if (preg_match('/ORD(\d+)/i', $ord_row['orderid'], $m))
                            $next_num = intval($m[1]) + 1;
                    }
                    $new_order_id = 'ORD' . str_pad($next_num, 5, '0', STR_PAD_LEFT);

                    // ── Generate Payment ID ────────────────────────────────────
                    $pay_res = mysqli_query($connect,
                        "SELECT paymentid FROM payments
                         WHERE paymentid REGEXP '^PAY[0-9]{5}$'
                         ORDER BY paymentid DESC LIMIT 1"
                    );
                    $pay_num = 1;
                    if ($pay_res && mysqli_num_rows($pay_res) > 0) {
                        $pay_row = mysqli_fetch_assoc($pay_res);
                        $pay_num = intval(substr($pay_row['paymentid'], 3)) + 1;
                    }
                    $new_pay_id = 'PAY' . str_pad($pay_num, 5, '0', STR_PAD_LEFT);

                    // ── Build special_notes (contains dedup token) ─────────────
                    $notes_parts = [];
                    if (!empty($voucher_code) && $discount_rm > 0)
                        $notes_parts[] = 'Voucher: ' . strtoupper($voucher_code)
                                       . ' (RM ' . number_format($discount_rm, 2) . ' off)';
                    $notes_parts[] = 'Payment: Stripe Card';
                    $notes_parts[] = 'stripe_session:' . $session_id;
                    $special_notes = implode(' | ', $notes_parts);

                    $created_at = date('Y-m-d H:i:s');

                    // Escape all scalars
                    $uid_esc   = mysqli_real_escape_string($connect, $user_id);
                    $name_esc  = mysqli_real_escape_string($connect, $user_name_m);
                    $email_esc = mysqli_real_escape_string($connect, $user_email_m);
                    $phone_esc = mysqli_real_escape_string($connect, $user_phone_m);
                    $ot_esc    = mysqli_real_escape_string($connect, $order_type_db);
                    $notes_esc = mysqli_real_escape_string($connect, $special_notes);
                    $oid_esc   = mysqli_real_escape_string($connect, $new_order_id);

                    // ── INSERT orders ──────────────────────────────────────────
                    $sql_order = "INSERT INTO orders
                        (orderid, userid, order_type, tableid,
                         customer_name, customer_phone, customer_email,
                         subtotal, discount_rm, tax, total_price,
                         payment_method, special_notes, status,
                         points_earned, created_at, updated_at)
                        VALUES
                        ('$oid_esc', '$uid_esc', '$ot_esc', NULL,
                         '$name_esc', '$phone_esc', '$email_esc',
                         $subtotal, $discount_rm, $tax, $total_price,
                         'card', '$notes_esc', 'paid',
                         0, '$created_at', '$created_at')";

                    if (!mysqli_query($connect, $sql_order))
                        throw new Exception('Failed to insert order: ' . mysqli_error($connect));

                    // ── INSERT order_items ─────────────────────────────────────
                    $item_res = mysqli_query($connect,
                        "SELECT orderitemid FROM order_items
                         WHERE orderitemid REGEXP '^OI[0-9]+$'
                         ORDER BY orderitemid DESC LIMIT 1"
                    );
                    $item_num = 1;
                    if ($item_res && mysqli_num_rows($item_res) > 0) {
                        $item_row = mysqli_fetch_assoc($item_res);
                        preg_match('/OI(\d+)/i', $item_row['orderitemid'], $im);
                        $item_num = intval($im[1] ?? 0) + 1;
                    }

                    foreach ($cart as $item) {
                        $prod_id    = mysqli_real_escape_string($connect, trim($item['id']));
                        $qty        = intval($item['quantity'] ?? 1);
                        $unit_price = round((float)($item['price'] ?? 0), 2);
                        $line_total = round($unit_price * $qty, 2);
                        $item_name  = mysqli_real_escape_string($connect, $item['name'] ?? 'Item');
                        $oi_id      = 'OI' . str_pad($item_num, 5, '0', STR_PAD_LEFT);
                        $item_num++;

                        $custom      = $item['custom'] ?? [];
                        $custom_json = !empty($custom)
                            ? "'" . mysqli_real_escape_string($connect, json_encode($custom)) . "'"
                            : 'NULL';

                        $isql = "INSERT INTO order_items
                            (orderitemid, orderid, productid, product_name,
                             quantity, price, subtotal, custom_options)
                            VALUES
                            ('$oi_id','$oid_esc','$prod_id','$item_name',
                             $qty,$unit_price,$line_total,$custom_json)";

                        if (!mysqli_query($connect, $isql))
                            throw new Exception('Failed to insert order item: ' . mysqli_error($connect));
                    }

                    // ── INSERT payments ────────────────────────────────────────
                    // Dedup token must be inside special_note so the check above
                    // catches the row on any subsequent page refresh.
                    $pay_note_esc = mysqli_real_escape_string($connect,
                        'Stripe Checkout | stripe_session:' . $session_id
                    );
                    $pay_id_esc = mysqli_real_escape_string($connect, $new_pay_id);

                    $psql = "INSERT INTO payments
                        (paymentid, orderid, user_id, method, status, amount, created_at, special_note)
                        VALUES
                        ('$pay_id_esc','$oid_esc','$uid_esc','card','paid',$total_price,'$created_at','$pay_note_esc')";

                    if (!mysqli_query($connect, $psql))
                        throw new Exception('Failed to insert payment: ' . mysqli_error($connect));

                    // ── Deduct stock ───────────────────────────────────────────
                    foreach ($cart as $item) {
                        $pid = mysqli_real_escape_string($connect, trim($item['id']));
                        $qty = intval($item['quantity'] ?? 1);
                        if ($pid && $qty > 0) {
                            mysqli_query($connect,
                                "UPDATE products
                                 SET stock = GREATEST(0, stock - $qty)
                                 WHERE productid = '$pid'"
                            );
                        }
                    }

                    // ── Award loyalty points ───────────────────────────────────
                    if (file_exists(__DIR__ . '/award_points.php')) {
                        include_once 'award_points.php';
                        if (function_exists('awardPoints')) {
                            awardPoints($connect, $user_id, $new_order_id, $total_price);
                        }
                    }

                    // ── Mark voucher as used ───────────────────────────────────
                    if (!empty($voucher_code) && $discount_rm > 0) {
                        $vcode_esc = mysqli_real_escape_string($connect, strtoupper($voucher_code));
                        $uid_esc_v = mysqli_real_escape_string($connect, $user_id);
                        $vcol = mysqli_query($connect,
                            "SHOW COLUMNS FROM user_vouchers LIKE 'used_order_id'"
                        );
                        if ($vcol && mysqli_num_rows($vcol) > 0) {
                            mysqli_query($connect,
                                "UPDATE user_vouchers
                                 SET is_used=1, used_at=NOW(), used_order_id='$oid_esc'
                                 WHERE code='$vcode_esc' AND userid='$uid_esc_v' AND is_used=0"
                            );
                        } else {
                            mysqli_query($connect,
                                "UPDATE user_vouchers
                                 SET is_used=1, used_at=NOW()
                                 WHERE code='$vcode_esc' AND userid='$uid_esc_v' AND is_used=0"
                            );
                        }
                    }

                    mysqli_commit($connect);

                    // Load the freshly inserted order for the display block below
                    $oq    = mysqli_query($connect,
                        "SELECT * FROM orders WHERE orderid = '$oid_esc' LIMIT 1"
                    );
                    $order = ($oq && mysqli_num_rows($oq) > 0)
                           ? mysqli_fetch_assoc($oq)
                           : ['orderid' => $new_order_id, 'total_price' => $total_price];

                } catch (Exception $e) {
                    mysqli_rollback($connect);
                    $error      = 'Order could not be saved: ' . $e->getMessage();
                    $payment_ok = false;
                }

            } // end cart check
        } // end first-visit insert
    } // end payment_ok
} // end session_id check

$user_name = htmlspecialchars($_SESSION['user_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - FOODTIME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .success-section{padding:80px 0;min-height:80vh;display:flex;align-items:center;}
        .success-box{max-width:560px;margin:0 auto;background:var(--card-bg);border-radius:18px;padding:48px 40px;text-align:center;box-shadow:0 8px 32px rgba(212,101,15,.13);}
        [data-theme="dark"] .success-box{box-shadow:0 8px 32px rgba(0,0,0,.45);}
        .success-icon{width:88px;height:88px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:38px;color:white;}
        .success-title{font-size:28px;font-weight:800;color:var(--primary-color);margin:0 0 10px;}
        [data-theme="dark"] .success-title{color:var(--accent-color);}
        .success-sub{font-size:15px;color:var(--light-gray);margin-bottom:28px;line-height:1.7;}
        .btn-group{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:10px;}
        .btn-primary-solid{background:var(--primary-color);color:white;padding:13px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px;transition:all .3s;display:inline-flex;align-items:center;gap:8px;}
        .btn-primary-solid:hover{background:#b85509;transform:translateY(-2px);}
        .btn-outline{color:var(--primary-color);padding:13px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px;border:2px solid var(--primary-color);transition:all .3s;display:inline-flex;align-items:center;gap:8px;}
        .btn-outline:hover{background:var(--primary-color);color:white;}
        .error-box{max-width:560px;margin:0 auto;background:#fef2f2;border:2px solid #fca5a5;border-radius:14px;padding:36px;text-align:center;}
        .error-icon{font-size:48px;color:#dc2626;margin-bottom:16px;}
        .error-title{font-size:22px;font-weight:700;color:#dc2626;margin-bottom:10px;}
        .error-msg{font-size:14px;color:#7f1d1d;line-height:1.6;}
        .meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:20px 0 28px;text-align:left;}
        .meta-item{background:rgba(212,101,15,.05);border-radius:10px;padding:12px 14px;}
        .meta-label{font-size:11px;color:var(--light-gray);font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px;}
        .meta-value{font-size:14px;font-weight:800;color:var(--light-text);}
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="index.php" class="logo"><i class="fas fa-burger"></i><span>FOODTIME</span></a>
                <div class="auth-buttons">
                    <button class="theme-toggle" id="themeToggle" type="button"><i class="fas fa-moon"></i></button>
                    <span class="user-greeting">&#128075; Hi, <?= $user_name ?></span>
                    <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> Orders</a>
                    <a href="logout.php" class="btn btn-logout">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <section class="success-section">
        <div class="container">

        <?php if ($order): ?>
            <div class="success-box">
                <div class="success-icon"><i class="fas fa-check"></i></div>
                <h1 class="success-title">Payment Successful!</h1>
                <p class="success-sub">
                    Thank you, <strong><?= $user_name ?></strong>! Your card payment has been
                    confirmed and your order is now being prepared.
                </p>
                <div class="meta-grid">
                    <div class="meta-item">
                        <div class="meta-label">Order ID</div>
                        <div class="meta-value"><?= htmlspecialchars($order['orderid']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Amount Paid</div>
                        <div class="meta-value">RM <?= number_format((float)($order['total_price'] ?? 0), 2) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Payment Method</div>
                        <div class="meta-value">Stripe Card</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Status</div>
                        <div class="meta-value" style="color:#16a34a;">✅ Paid</div>
                    </div>
                </div>
                <div class="btn-group">
                    <a href="order_history.php" class="btn-primary-solid">
                        <i class="fas fa-history"></i> My Orders
                    </a>
                    <a href="menu.php" class="btn-outline">
                        <i class="fas fa-utensils"></i> Order Again
                    </a>
                </div>
            </div>

        <?php elseif (!empty($error)): ?>
            <div class="error-box">
                <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h2 class="error-title">Payment Verification Failed</h2>
                <p class="error-msg"><?= htmlspecialchars($error) ?></p>
                <br>
                <div class="btn-group" style="justify-content:center;">
                    <a href="order_history.php" class="btn-primary-solid" style="text-decoration:none;">
                        <i class="fas fa-history"></i> Check My Orders
                    </a>
                    <a href="cart.php" class="btn-outline" style="text-decoration:none;">
                        <i class="fas fa-shopping-cart"></i> Back to Cart
                    </a>
                </div>
            </div>
        <?php endif; ?>

        </div>
    </section>

    <script src="js/theme.js"></script>

    <?php if ($payment_ok && $order): ?>
    <script>
        localStorage.removeItem('cart');
        localStorage.removeItem('cartLastModified');
        sessionStorage.removeItem('orderType');
        sessionStorage.removeItem('appliedVoucher');
        sessionStorage.removeItem('cartTotals');
    </script>
    <?php endif; ?>
</body>
</html>