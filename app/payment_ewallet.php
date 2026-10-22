<?php
session_start();
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name    = $is_logged_in ? (isset($_SESSION['user_name'])  ? htmlspecialchars($_SESSION['user_name'])  : '') : '';
$user_email   = $is_logged_in ? (isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '') : '';
$user_phone   = $is_logged_in ? (isset($_SESSION['user_phone']) ? htmlspecialchars($_SESSION['user_phone']) : '') : '';

if (!$is_logged_in) { header("Location: login.php"); exit(); }

$error_msg = '';

// ── Phone normaliser ─────────────────────────────────────────────────────────
function normaliseToLocal(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) >= 11 && substr($digits, 0, 2) === '60') {
        $digits = '0' . substr($digits, 2);
    }
    return $digits;
}

function isValidMalaysiaPhone(string $phone): bool {
    $digits = normaliseToLocal($phone);
    if (!preg_match('/^01/', $digits)) return false;
    return substr($digits, 0, 3) === '011'
        ? strlen($digits) === 11
        : strlen($digits) === 10;
}

// ── Fetch user phone + PIN status from DB ─────────────────────────────────────
$has_pin  = false;
$db_phone = '';

$uq = mysqli_prepare($connect,
    "SELECT phone, ewallet_pin_hash FROM users WHERE userid = ? LIMIT 1"
);
if ($uq) {
    mysqli_stmt_bind_param($uq, 's', $user_id);
    mysqli_stmt_execute($uq);
    $ur = mysqli_stmt_get_result($uq);
    if ($urow = mysqli_fetch_assoc($ur)) {
        $db_phone = normaliseToLocal($urow['phone'] ?? '');
        $has_pin  = !empty($urow['ewallet_pin_hash']);
    }
    mysqli_stmt_close($uq);
}

// ── POST: save new PIN (AJAX from PIN setup modal) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pin') {
    header('Content-Type: application/json');

    $pin         = $_POST['pin']         ?? '';
    $pin_confirm = $_POST['pin_confirm'] ?? '';
    $phone_raw   = trim($_POST['phone']  ?? '');

    if (empty($phone_raw)) {
        echo json_encode(['ok' => false, 'msg' => 'Phone number is required.']);
        exit;
    }

    $phone_local = normaliseToLocal($phone_raw);
    if (!isValidMalaysiaPhone($phone_local)) {
        $msg = substr($phone_local, 0, 3) === '011'
            ? '011 numbers must be exactly 11 digits (e.g. 01112345678).'
            : 'Phone must start with 01 and be exactly 10 digits (e.g. 0184679907).';
        echo json_encode(['ok' => false, 'msg' => $msg]);
        exit;
    }

    if (!preg_match('/^\d{6}$/', $pin)) {
        echo json_encode(['ok' => false, 'msg' => 'PIN must be exactly 6 digits.']);
        exit;
    }
    if ($pin !== $pin_confirm) {
        echo json_encode(['ok' => false, 'msg' => 'PINs do not match. Please try again.']);
        exit;
    }

    $pin_hash = password_hash($pin, PASSWORD_BCRYPT);
    $now      = date('Y-m-d H:i:s');

    $sp = mysqli_prepare($connect,
        "UPDATE users SET ewallet_pin_hash = ?, ewallet_pin_set_at = ?, phone = ? WHERE userid = ?"
    );
    if (!$sp) {
        echo json_encode(['ok' => false, 'msg' => 'Database error. Please try again.']);
        exit;
    }
    mysqli_stmt_bind_param($sp, 'ssss', $pin_hash, $now, $phone_local, $user_id);
    mysqli_stmt_execute($sp);
    mysqli_stmt_close($sp);

    echo json_encode(['ok' => true]);
    exit;
}

// ── POST: create pending order then redirect to QR view ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $cart_data    = $_POST['cart_data']    ?? '[]';
    $order_type   = $_POST['order_type']   ?? 'dinein';
    $voucher_data = $_POST['voucher_data'] ?? '';
    $ewallet      = mysqli_real_escape_string($connect, $_POST['ewallet'] ?? '');
    $cart         = json_decode($cart_data, true);
    $order_type_db = ($order_type === 'dinein') ? 'dine_in' : 'takeaway';

    $voucher          = null;
    $voucher_discount = 0;
    if (!empty($voucher_data)) {
        $voucher = json_decode($voucher_data, true);
        if ($voucher && isset($voucher['discount_rm'])) {
            $voucher_discount = floatval($voucher['discount_rm']);
        }
    }

    if (empty($cart) || !is_array($cart)) {
        $error_msg = 'Your cart is empty.';
    } elseif (empty($ewallet)) {
        $error_msg = 'Please select an e-wallet.';
    } else {
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

        $subtotal = 0;
        foreach ($cart as $item) {
            $stored_price = isset($item['price']) ? floatval($item['price']) : 0;
            $db_price     = isset($prices[$item['id']]) ? $prices[$item['id']]['price'] : 0;
            $unit_price   = ($stored_price > 0) ? $stored_price : $db_price;
            $subtotal    += $unit_price * intval($item['quantity'] ?? 1);
        }

        $subtotal         = round($subtotal, 2);
        $voucher_discount = min($voucher_discount, $subtotal);

        // ── FIX: Tax on FULL subtotal, discount deducted after ──
        $tax        = round($subtotal * 0.06, 2);
        $total      = round($subtotal + $tax - $voucher_discount, 2);
        $created_at = date('Y-m-d H:i:s');

        $notes_parts = ['E-Wallet: ' . strtoupper($ewallet)];
        if ($voucher && $voucher_discount > 0) {
            $notes_parts[] = 'Voucher: ' . strtoupper($voucher['code']) . ' (RM ' . number_format($voucher_discount, 2) . ' off)';
        }
        $special_notes = implode(' | ', $notes_parts);

        mysqli_begin_transaction($connect);

        $res      = mysqli_query($connect, "SELECT orderid FROM orders WHERE orderid REGEXP '^ORD[0-9]{5}$' ORDER BY orderid DESC LIMIT 1 FOR UPDATE");
        $next_num = 1;
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            if (preg_match('/ORD-?(\d+)/i', $row['orderid'], $m)) $next_num = intval($m[1]) + 1;
        }
        $new_order_id = 'ORD' . str_pad($next_num, 5, '0', STR_PAD_LEFT);

        $uid_esc   = mysqli_real_escape_string($connect, $user_id);
        $name_esc  = mysqli_real_escape_string($connect, $user_name);
        $email_esc = mysqli_real_escape_string($connect, $user_email);
        $phone_esc = mysqli_real_escape_string($connect, $user_phone);
        $ot_esc    = mysqli_real_escape_string($connect, $order_type_db);
        $ew_esc    = mysqli_real_escape_string($connect, $ewallet);
        $notes_esc = mysqli_real_escape_string($connect, $special_notes);

        $sql = "INSERT INTO orders
                    (orderid, userid, order_type, tableid, customer_name, customer_phone, customer_email,
                     subtotal, tax, discount_rm, total_price, payment_method, special_notes, status,
                     points_earned, created_at, updated_at)
                VALUES
                    ('$new_order_id','$uid_esc','$ot_esc',NULL,'$name_esc','$phone_esc','$email_esc',
                     $subtotal, $tax, $voucher_discount, $total,
                     '$ew_esc','$notes_esc','pending', 0,'$created_at','$created_at')";

        if (mysqli_query($connect, $sql)) {
            $order_id = $new_order_id;

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
                if (!empty($item['spice_level']))                            $custom['spice_level'] = $item['spice_level'];
                if (!empty($item['bev_temp']))                               $custom['bev_temp']    = $item['bev_temp'];
                if (!empty($item['serve_time']))                             $custom['serve_time']  = $item['serve_time'];
                if (!empty($item['removes']) && is_array($item['removes'])) $custom['removes']     = $item['removes'];
                if (!empty($item['addons'])  && is_array($item['addons']))  $custom['addons']      = $item['addons'];
                $custom_json = !empty($custom)
                    ? "'" . mysqli_real_escape_string($connect, json_encode($custom)) . "'"
                    : 'NULL';

                $oi_num++;
                $new_oi_id = 'OI' . str_pad($oi_num, 5, '0', STR_PAD_LEFT);
                mysqli_query($connect, "INSERT INTO order_items
                    (orderitemid, orderid, productid, product_name, quantity, price, subtotal, custom_options)
                    VALUES ('$new_oi_id','$order_id','$pid','$pname',$qty,$pprice,$psub,$custom_json)");
            }

            if ($voucher && isset($voucher['voucherid']) && $voucher_discount > 0) {
                $vid_esc = mysqli_real_escape_string($connect, $voucher['voucherid']);
                mysqli_query($connect, "UPDATE user_vouchers SET is_used=1, used_at='$created_at'
                    WHERE voucherid='$vid_esc' AND userid='$uid_esc' AND is_used=0");
            }

            mysqli_commit($connect);

            header("Location: order_qr_view.php?orderid=" . urlencode($order_id));
            exit();

        } else {
            mysqli_rollback($connect);
            $error_msg = 'Order creation failed: ' . mysqli_error($connect);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
(function(){
    var t = localStorage.getItem('theme') || localStorage.getItem('ft_theme') || 'light';
    localStorage.setItem('theme', t);
    localStorage.removeItem('ft_theme');
    document.documentElement.setAttribute('data-theme', t);
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-Wallet / QR Pay — FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/payment.css">
<script type="module">
import {
  FaceLandmarker,
  FilesetResolver
} from "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3/vision_bundle.mjs";
window.mpVision = { FaceLandmarker, FilesetResolver };
</script>
<style>
.wallet-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-bottom: 24px; }
.wallet-option { border: 2px solid #e0e0e0; border-radius: 12px; padding: 14px 8px; text-align: center; cursor: pointer; transition: all 0.2s; background: var(--card-bg); }
[data-theme="dark"] .wallet-option { border-color: #444; }
.wallet-option:hover { border-color: var(--primary-color); transform: translateY(-2px); }
.wallet-option.selected { border-color: var(--primary-color); background: linear-gradient(135deg,rgba(212,101,15,0.08),rgba(255,152,0,0.06)); }
.wallet-option input { display: none; }
.wallet-emoji { font-size: 26px; margin-bottom: 5px; display: block; }
.wallet-name  { font-size: 11px; font-weight: 700; color: var(--light-text); }
.qr-section { display: none; flex-direction: column; align-items: center; background: rgba(212,101,15,0.05); border-radius: 14px; padding: 24px 20px; margin-bottom: 24px; border: 2px dashed rgba(212,101,15,0.3); }
.qr-section.visible { display: flex; }
.qr-box { width: 180px; height: 180px; background: white; border-radius: 14px; border: 3px solid var(--primary-color); display: flex; align-items: center; justify-content: center; margin-bottom: 14px; box-shadow: 0 6px 20px rgba(212,101,15,0.18); padding: 10px; }
.qr-box svg { width: 100%; height: 100%; }
.qr-wallet-label   { font-size: 14px; font-weight: 700; color: var(--light-text); margin-bottom: 4px; }
.qr-amount-display { font-size: 28px; font-weight: 900; color: var(--primary-color); margin: 2px 0 12px; }
.scan-steps { width: 100%; background: white; border-radius: 10px; padding: 14px 18px; }
[data-theme="dark"] .scan-steps { background: #1e1e1e; }
.scan-step { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--light-gray); padding: 5px 0; }
.step-num { width: 22px; height: 22px; border-radius: 50%; background: var(--primary-color); color: white; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.voucher-applied-indicator { display: none; align-items: center; gap: 10px; background: rgba(34,197,94,0.08); border: 1.5px solid rgba(34,197,94,0.3); border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px; font-weight: 700; color: #16a34a; }
.voucher-applied-indicator i { font-size: 16px; }

/* Shared modal base */
.ft-modal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.65); align-items: center; justify-content: center; }
.ft-modal.open { display: flex; }
.ft-modal-card { background: #fff; border-radius: 20px; padding: 28px 24px 24px; width: 100%; max-width: 420px; box-shadow: 0 12px 40px rgba(0,0,0,0.3); text-align: center; position: relative; }
[data-theme="dark"] .ft-modal-card { background: #1e1e1e; color: #f0f0f0; }
.ft-modal-title    { font-size: 18px; font-weight: 800; color: #d4650f; margin-bottom: 4px; }
.ft-modal-subtitle { font-size: 13px; color: #888; margin-bottom: 16px; }

/* Face modal */
.face-cam-wrap { position: relative; width: 280px; height: 210px; margin: 0 auto 14px; border-radius: 14px; overflow: hidden; border: 3px solid #e0e0e0; background: #111; }
.face-cam-wrap::after { content: ''; position: absolute; inset: 0; border-radius: 14px; box-shadow: inset 0 0 0 3px rgba(212,101,15,0.5); pointer-events: none; z-index: 2; }
#faceVideo  { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: block; }
#faceCanvas { position: absolute; inset: 0; width: 100%; height: 100%; transform: scaleX(-1); z-index: 1; pointer-events: none; }
#faceStatus { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; padding: 8px 18px; border-radius: 30px; margin-bottom: 16px; transition: background 0.3s, color 0.3s; background: #f0f0f0; color: #666; }
#faceStatus.detecting { background: #fff3e0; color: #e65100; }
#faceStatus.detected  { background: #e8f5e9; color: #2e7d32; }
#faceStatus.success   { background: #d4edda; color: #155724; }
#faceStatus.error     { background: #fdecea; color: #b71c1c; }
.face-progress-wrap { height: 8px; background: #eee; border-radius: 10px; overflow: hidden; margin-bottom: 16px; }
#faceProgress { height: 100%; width: 0%; background: linear-gradient(90deg, #d4650f, #ff9800); border-radius: 10px; transition: width 0.1s linear; }
.face-modal-btns { display: flex; gap: 10px; justify-content: center; }
.btn-face-cancel { padding: 10px 22px; border: 2px solid #ccc; border-radius: 10px; background: none; font-size: 14px; font-weight: 700; cursor: pointer; color: #666; transition: all 0.2s; }
.btn-face-cancel:hover { border-color: #d4650f; color: #d4650f; }

/* PIN Setup Modal */
.pin-setup-body { text-align: left; }
.pin-field-group { margin-bottom: 16px; }
.pin-field-group label { display: block; font-size: 13px; font-weight: 700; color: var(--light-text, #555); margin-bottom: 6px; }
.pin-field-group input { width: 100%; padding: 11px 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; font-family: monospace; letter-spacing: 4px; outline: none; transition: border-color 0.2s; background: var(--input-bg, #fff); color: var(--text-color, #222); }
[data-theme="dark"] .pin-field-group input { background: #2a2a2a; border-color: #555; color: #f0f0f0; }
.pin-field-group input:focus { border-color: #d4650f; }
.pin-field-group input.phone-input { letter-spacing: normal; font-family: inherit; }
.pin-field-group input.input-error  { border-color: #e74c3c; }
.phone-hint-row { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; margin-bottom: 4px; }
.phone-hint { font-size: 11px; color: #999; }
.phone-counter { font-size: 11px; font-weight: 700; }
.phone-counter.ok  { color: #16a34a; }
.phone-counter.bad { color: #e74c3c; }
.pin-error-msg { display: none; background: #fff0f0; border-left: 4px solid #e74c3c; border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #c0392b; margin-bottom: 14px; }
.pin-error-msg.visible { display: block; }
.pin-hint { font-size: 12px; color: #999; margin-top: -10px; margin-bottom: 14px; }
.btn-pin-submit { width: 100%; padding: 12px; background: linear-gradient(135deg, #d4650f, #ff9800); color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 800; cursor: pointer; transition: opacity 0.2s; margin-bottom: 10px; }
.btn-pin-submit:hover     { opacity: 0.9; }
.btn-pin-submit:disabled  { opacity: 0.6; cursor: not-allowed; }
.btn-pin-cancel { width: 100%; padding: 10px; border: 2px solid #ccc; border-radius: 10px; background: none; font-size: 14px; font-weight: 700; cursor: pointer; color: #666; transition: all 0.2s; }
.btn-pin-cancel:hover { border-color: #d4650f; color: #d4650f; }
.pin-security-note { display: flex; align-items: center; gap: 8px; background: #f0faf5; border: 1.5px solid #b7e4c7; border-radius: 10px; padding: 10px 14px; font-size: 12px; color: #276749; margin-bottom: 16px; }
.pin-security-note i { font-size: 14px; flex-shrink: 0; }
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
                    <button class="lang-btn" id="langBtn" title="Change Language" type="button"><i class="fas fa-globe"></i></button>
                    <div class="language-dropdown" id="languageDropdown">
                        <a href="#" data-lang="en">English</a>
                        <a href="#" data-lang="ms">Bahasa Melayu</a>
                        <a href="#" data-lang="zh">中文</a>
                    </div>
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode" type="button"><i class="fas fa-moon"></i></button>
                <?php if ($is_logged_in): ?>
                    <span class="user-greeting">👋 Hi, <?php echo $user_name; ?></span>
                    <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> Orders</a>
                    <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Cart</a>
                    <a href="logout.php" class="btn btn-logout">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<section class="payment-section">
    <div class="container">
        <h1 class="payment-page-title"><i class="fas fa-qrcode"></i> E-Wallet / QR Pay</h1>

        <div class="payment-layout">
            <div class="payment-box">
                <div class="pay-icon"><i class="fas fa-qrcode"></i></div>
                <h2 class="pay-title">Scan &amp; Pay</h2>
                <p class="pay-subtitle">Choose your e-wallet — a real QR code will be generated after you confirm.</p>

                <?php if ($error_msg): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <div class="voucher-applied-indicator" id="voucherIndicator">
                    <i class="fas fa-tag"></i>
                    <span id="voucherIndicatorText">Voucher applied</span>
                </div>

                <form method="POST" id="ewalletForm" novalidate>
                    <input type="hidden" name="cart_data"    id="cartDataInput">
                    <input type="hidden" name="order_type"   id="orderTypeInput" value="dinein">
                    <input type="hidden" name="ewallet"      id="ewalletInput"   value="">
                    <input type="hidden" name="voucher_data" id="voucherDataInput">

                    <div class="form-group">
                        <label><i class="fas fa-mobile-alt" style="color:var(--primary-color);"></i> &nbsp;Select E-Wallet</label>
                        <div class="wallet-grid">
                            <label class="wallet-option" onclick="selectWallet(this,'tng')">
                                <input type="radio" value="tng">
                                <span class="wallet-emoji">💚</span>
                                <span class="wallet-name">Touch 'n Go</span>
                            </label>
                            <label class="wallet-option" onclick="selectWallet(this,'grabpay')">
                                <input type="radio" value="grabpay">
                                <span class="wallet-emoji">🟢</span>
                                <span class="wallet-name">GrabPay</span>
                            </label>
                            <label class="wallet-option" onclick="selectWallet(this,'boost')">
                                <input type="radio" value="boost">
                                <span class="wallet-emoji">🔴</span>
                                <span class="wallet-name">Boost</span>
                            </label>
                            <label class="wallet-option" onclick="selectWallet(this,'shopeepay')">
                                <input type="radio" value="shopeepay">
                                <span class="wallet-emoji">🟠</span>
                                <span class="wallet-name">ShopeePay</span>
                            </label>
                            <label class="wallet-option" onclick="selectWallet(this,'mae')">
                                <input type="radio" value="mae">
                                <span class="wallet-emoji">🟡</span>
                                <span class="wallet-name">MAE</span>
                            </label>
                            <label class="wallet-option" onclick="selectWallet(this,'other')">
                                <input type="radio" value="other">
                                <span class="wallet-emoji">📱</span>
                                <span class="wallet-name">Others</span>
                            </label>
                        </div>
                    </div>

                    <div class="qr-section" id="qrSection">
                        <div class="qr-box">
                            <svg viewBox="0 0 37 37" xmlns="http://www.w3.org/2000/svg">
                                <rect width="37" height="37" fill="white"/>
                                <rect x="1" y="1" width="10" height="10" rx="1" fill="#d4650f"/><rect x="3" y="3" width="6" height="6" fill="white"/><rect x="4" y="4" width="4" height="4" fill="#d4650f"/>
                                <rect x="26" y="1" width="10" height="10" rx="1" fill="#d4650f"/><rect x="28" y="3" width="6" height="6" fill="white"/><rect x="29" y="4" width="4" height="4" fill="#d4650f"/>
                                <rect x="1" y="26" width="10" height="10" rx="1" fill="#d4650f"/><rect x="3" y="28" width="6" height="6" fill="white"/><rect x="4" y="29" width="4" height="4" fill="#d4650f"/>
                                <rect x="13" y="2" width="2" height="2" fill="#333"/><rect x="16" y="2" width="2" height="2" fill="#333"/><rect x="19" y="2" width="2" height="2" fill="#333"/><rect x="22" y="2" width="2" height="2" fill="#333"/>
                                <rect x="13" y="5" width="2" height="2" fill="#333"/><rect x="19" y="5" width="2" height="2" fill="#333"/><rect x="13" y="8" width="2" height="2" fill="#333"/><rect x="22" y="8" width="2" height="2" fill="#333"/>
                                <rect x="2" y="13" width="2" height="2" fill="#333"/><rect x="5" y="13" width="2" height="2" fill="#333"/><rect x="8" y="13" width="2" height="2" fill="#333"/><rect x="13" y="13" width="2" height="2" fill="#333"/>
                                <rect x="16" y="16" width="2" height="2" fill="#d4650f"/><rect x="19" y="13" width="2" height="2" fill="#333"/><rect x="22" y="16" width="2" height="2" fill="#333"/>
                                <rect x="25" y="13" width="2" height="2" fill="#333"/><rect x="28" y="16" width="2" height="2" fill="#333"/><rect x="31" y="13" width="2" height="2" fill="#333"/><rect x="34" y="16" width="2" height="2" fill="#333"/>
                                <rect x="2" y="19" width="2" height="2" fill="#333"/><rect x="8" y="19" width="2" height="2" fill="#333"/><rect x="13" y="22" width="2" height="2" fill="#333"/>
                                <rect x="19" y="19" width="2" height="2" fill="#333"/><rect x="25" y="22" width="2" height="2" fill="#333"/><rect x="31" y="19" width="2" height="2" fill="#333"/><rect x="34" y="22" width="2" height="2" fill="#333"/>
                                <rect x="13" y="28" width="2" height="2" fill="#333"/><rect x="16" y="31" width="2" height="2" fill="#333"/><rect x="19" y="28" width="2" height="2" fill="#333"/>
                                <rect x="22" y="31" width="2" height="2" fill="#333"/><rect x="25" y="28" width="2" height="2" fill="#333"/><rect x="28" y="31" width="2" height="2" fill="#333"/><rect x="31" y="28" width="2" height="2" fill="#333"/><rect x="34" y="31" width="2" height="2" fill="#333"/>
                            </svg>
                        </div>
                        <div class="qr-wallet-label" id="qrWalletLabel">—</div>
                        <div class="qr-amount-display" id="qrAmountDisplay">RM —</div>
                        <div class="scan-steps">
                            <div class="scan-step"><div class="step-num">1</div> Click <strong>Confirm &amp; Show QR</strong> below</div>
                            <div class="scan-step"><div class="step-num">2</div> A real QR code will be generated for your order</div>
                            <div class="scan-step"><div class="step-num">3</div> Scan it with your phone camera</div>
                            <div class="scan-step"><div class="step-num">4</div> Enter your registered phone number &amp; 6-digit PIN</div>
                            <div class="scan-step"><div class="step-num">5</div> This page updates automatically ✓</div>
                        </div>
                    </div>

                    <button type="submit" class="btn-pay-primary" id="confirmBtn" style="display:none;">
                        <i class="fas fa-qrcode"></i> Confirm &amp; Show QR Code
                    </button>
                </form>
                <a href="checkout.php" class="btn-pay-secondary"><i class="fas fa-arrow-left"></i> Back to Checkout</a>
            </div>

            <div class="order-summary-box">
                <div class="summary-heading"><i class="fas fa-receipt"></i> Order Summary</div>
                <div class="summary-order-type"><i class="fas fa-concierge-bell"></i><span id="orderTypeDisplay">-</span></div>
                <div class="summary-items" id="summaryItems">
                    <div class="skeleton" style="width:100%;margin-bottom:10px;"></div>
                    <div class="skeleton" style="width:80%;margin-bottom:10px;"></div>
                    <div class="skeleton" style="width:90%;"></div>
                </div>
                <div class="summary-totals">
                    <div class="summary-row"><span>Subtotal</span><strong id="summarySubtotal">RM —</strong></div>
                    <div class="summary-row" id="discountRow" style="display:none;color:#16a34a;font-weight:700;">
                        <span><i class="fas fa-tag"></i> Voucher Discount</span>
                        <strong id="summaryDiscount">- RM 0.00</strong>
                    </div>
                    <div class="summary-row"><span>Service Tax (6%)</span><strong id="summaryTax">RM —</strong></div>
                    <div class="summary-row total"><span>Total</span><strong id="summaryTotal">RM —</strong></div>
                </div>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section"><h4>🍴 About FOODTIME</h4><a href="#">About Us</a><a href="#">Blog</a><a href="#">Careers</a></div>
            <div class="footer-section"><h4>Support</h4><a href="#">Help Center</a><a href="#">Contact Us</a><a href="#">FAQ</a></div>
            <div class="footer-section"><h4>Legal</h4><a href="#">Privacy Policy</a><a href="#">Terms &amp; Conditions</a><a href="#">Refund Policy</a></div>
            <div class="footer-section"><h4>Follow Us</h4><div class="social-links"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
        </div>
        <div class="footer-bottom"><p>&copy; 2026 FOODTIME. All rights reserved.</p></div>
    </div>
</footer>

<!-- FACE VERIFICATION MODAL -->
<div id="faceModal" class="ft-modal" role="dialog" aria-modal="true">
    <div class="ft-modal-card">
        <div class="ft-modal-title">🔐 Face Verification</div>
        <div class="ft-modal-subtitle">Look at the camera and hold still for 2 seconds to verify your identity.</div>
        <div class="face-cam-wrap">
            <video id="faceVideo" autoplay playsinline muted></video>
            <canvas id="faceCanvas"></canvas>
        </div>
        <div id="faceStatus">⏳ Opening camera…</div>
        <div class="face-progress-wrap"><div id="faceProgress"></div></div>
        <div class="face-modal-btns">
            <button type="button" class="btn-face-cancel" id="faceCancelBtn"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<!-- PIN SETUP MODAL -->
<div id="pinSetupModal" class="ft-modal" role="dialog" aria-modal="true">
    <div class="ft-modal-card">
        <div class="ft-modal-title">🔑 Set Your E-Wallet PIN</div>
        <div class="ft-modal-subtitle">
            This PIN secures all your QR payments.<br>
            You will need to enter your phone number and PIN on your phone after scanning.
        </div>
        <div class="pin-security-note">
            <i class="fas fa-shield-alt"></i>
            Your PIN is encrypted and never stored in plain text.
        </div>
        <div class="pin-error-msg" id="pinErrorMsg"></div>
        <div class="pin-setup-body">
            <div class="pin-field-group">
                <label for="pinPhone"><i class="fas fa-phone"></i> Malaysian Phone Number</label>
                <input type="tel"
                       id="pinPhone"
                       class="phone-input"
                       inputmode="numeric"
                       maxlength="11"
                       value="<?php echo htmlspecialchars($db_phone); ?>"
                       placeholder="e.g. 0184679907 or 01112345678"
                       oninput="onPhoneInput(this)">
                <div class="phone-hint-row">
                    <span class="phone-hint">012–019: 10 digits &nbsp;|&nbsp; 011: 11 digits &nbsp;|&nbsp; Must start with 01</span>
                    <span class="phone-counter" id="phoneCounter"></span>
                </div>
            </div>
            <div class="pin-field-group">
                <label for="pinInput"><i class="fas fa-lock"></i> Create 6-Digit PIN</label>
                <input type="password" id="pinInput" maxlength="6"
                       inputmode="numeric" pattern="\d{6}"
                       placeholder="••••••"
                       autocomplete="new-password">
            </div>
            <div class="pin-hint">Numbers only, exactly 6 digits.</div>
            <div class="pin-field-group">
                <label for="pinConfirmInput"><i class="fas fa-lock"></i> Confirm PIN</label>
                <input type="password" id="pinConfirmInput" maxlength="6"
                       inputmode="numeric" pattern="\d{6}"
                       placeholder="••••••"
                       autocomplete="new-password">
            </div>
            <button type="button" class="btn-pin-submit" id="pinSubmitBtn" onclick="submitPin()">
                <i class="fas fa-check-circle"></i> Save PIN &amp; Continue
            </button>
            <button type="button" class="btn-pin-cancel" onclick="closePinModal()">Cancel</button>
        </div>
    </div>
</div>

<script src="js/translations.js"></script>
<script src="js/theme.js"></script>
<script src="js/language.js"></script>
<script>
/* ═══════════════════════════════════════════════════════════
   MALAYSIA PHONE VALIDATION
   ═══════════════════════════════════════════════════════════ */
function normalisePhone(raw) {
    let digits = raw.replace(/\D/g, '');
    if (digits.length >= 11 && digits.startsWith('60')) {
        digits = '0' + digits.slice(2);
    }
    return digits;
}

function isValidMalaysiaPhone(raw) {
    const digits = normalisePhone(raw);
    if (!/^01/.test(digits)) return false;
    return digits.startsWith('011')
        ? digits.length === 11
        : digits.length === 10;
}

function getExpectedLen(digits) {
    if (digits.startsWith('011')) return 11;
    if (digits.startsWith('01'))  return 10;
    return null;
}

function onPhoneInput(el) {
    let digits = normalisePhone(el.value);
    digits  = digits.startsWith('011') ? digits.slice(0, 11) : digits.slice(0, 10);
    el.value = digits;

    const counter  = document.getElementById('phoneCounter');
    const expected = getExpectedLen(digits);
    const len      = digits.length;

    if (expected) {
        counter.textContent = len + '/' + expected;
        counter.className   = 'phone-counter ' + (len === expected ? 'ok' : 'bad');
    } else if (len > 0) {
        counter.textContent = len + '/10–11';
        counter.className   = 'phone-counter bad';
    } else {
        counter.textContent = '';
        counter.className   = 'phone-counter';
    }
    el.classList.toggle('input-error', len > 0 && !isValidMalaysiaPhone(digits));
}

document.addEventListener('DOMContentLoaded', function () {
    const ph = document.getElementById('pinPhone');
    if (ph && ph.value.length > 0) onPhoneInput(ph);
});

/* ═══════════════════════════════════════════════════════════
   CART / WALLET / SUMMARY LOGIC
   ═══════════════════════════════════════════════════════════ */
const TAX       = 0.06;
const cart      = JSON.parse(localStorage.getItem('cart') || '[]');
const orderType = sessionStorage.getItem('orderType') || 'dinein';

document.getElementById('orderTypeInput').value         = orderType;
document.getElementById('orderTypeDisplay').textContent = orderType === 'dinein' ? '🍽️  Dine In' : '🛍️  Takeaway';

let totalAmount    = 0;
let appliedVoucher = null;

try {
    const saved = sessionStorage.getItem('appliedVoucher');
    if (saved) {
        appliedVoucher = JSON.parse(saved);
        const ind = document.getElementById('voucherIndicator');
        document.getElementById('voucherIndicatorText').textContent =
            '✅ Voucher ' + appliedVoucher.code + ' — RM' + appliedVoucher.discount_rm + ' discount applied';
        ind.style.display = 'flex';
    }
} catch(e) { appliedVoucher = null; }

if (cart.length > 0) {
    fetch('cart.php?action=get_prices&ids=' + cart.map(i => i.id).join(','))
        .then(r => r.json())
        .then(dbPrices => {
            let sub = 0, html = '';
            cart.forEach(item => {
                const stored    = parseFloat(item.price);
                const unitPrice = (!isNaN(stored) && stored > 0)
                    ? stored
                    : (parseFloat((dbPrices[item.id] || {}).price) || 0);
                const name = (dbPrices[item.id] || {}).name || item.name || ('Product #' + item.id);
                const qty  = Math.max(1, parseInt(item.quantity) || 1);
                const line = unitPrice * qty;
                sub += line;
                html += `<div class="summary-item">
                    <div>
                        <div class="summary-item-name">${name}</div>
                        <div class="summary-item-qty">x${qty} × RM ${unitPrice.toFixed(2)}</div>
                    </div>
                    <div class="summary-item-price">RM ${line.toFixed(2)}</div>
                </div>`;
            });

            // ── FIX: Tax on FULL subtotal, discount deducted after ──
            const discount  = appliedVoucher ? Math.min(parseFloat(appliedVoucher.discount_rm), sub) : 0;
            const tax       = Math.round(sub * TAX * 100) / 100;
            totalAmount     = Math.round((sub + tax - discount) * 100) / 100;

            document.getElementById('summaryItems').innerHTML      = html;
            document.getElementById('summarySubtotal').textContent = 'RM ' + sub.toFixed(2);
            document.getElementById('summaryTax').textContent      = 'RM ' + tax.toFixed(2);
            document.getElementById('summaryTotal').textContent    = 'RM ' + totalAmount.toFixed(2);
            document.getElementById('qrAmountDisplay').textContent = 'RM ' + totalAmount.toFixed(2);
            if (discount > 0) {
                document.getElementById('discountRow').style.display   = 'flex';
                document.getElementById('summaryDiscount').textContent = '- RM ' + discount.toFixed(2);
            }
        });
}

const walletNames = {
    tng: "Touch 'n Go", grabpay: 'GrabPay', boost: 'Boost',
    shopeepay: 'ShopeePay', mae: 'MAE', other: 'E-Wallet'
};

function selectWallet(el, val) {
    document.querySelectorAll('.wallet-option').forEach(w => w.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('ewalletInput').value        = val;
    document.getElementById('qrWalletLabel').textContent = 'Scan with ' + (walletNames[val] || 'your e-wallet');
    document.getElementById('confirmBtn').style.display  = 'flex';
}

const USER_HAS_PIN = <?php echo $has_pin ? 'true' : 'false'; ?>;

/* ═══════════════════════════════════════════════════════════
   FACE VERIFICATION MODAL
   ═══════════════════════════════════════════════════════════ */
const faceModal      = document.getElementById('faceModal');
const faceVideo      = document.getElementById('faceVideo');
const faceCanvas     = document.getElementById('faceCanvas');
const faceStatusEl   = document.getElementById('faceStatus');
const faceProgressEl = document.getElementById('faceProgress');
const faceCancelBtn  = document.getElementById('faceCancelBtn');

let faceLandmarker = null;
let cameraStream   = null;
let detectionLoop  = null;
let holdStart      = null;
const HOLD_MS      = 2000;

function setStatus(text, cls) {
    faceStatusEl.textContent = text;
    faceStatusEl.className   = '';
    if (cls) faceStatusEl.classList.add(cls);
}
function setProgress(pct) { faceProgressEl.style.width = Math.min(100, pct) + '%'; }

async function openFaceModal() {
    setStatus('⏳ Opening camera…');
    setProgress(0);
    faceModal.classList.add('open');

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: 640, height: 480 },
            audio: false
        });
        faceVideo.srcObject = cameraStream;
        await faceVideo.play();
        setStatus('🔍 Loading face model…');

        if (!window.mpVision) throw new Error('MediaPipe module not available');

        const { FaceLandmarker, FilesetResolver } = window.mpVision;
        const filesetResolver = await FilesetResolver.forVisionTasks(
            'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3/wasm'
        );
        faceLandmarker = await FaceLandmarker.createFromOptions(filesetResolver, {
            baseOptions: {
                modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
                delegate: 'GPU'
            },
            outputFaceBlendshapes: false,
            runningMode: 'VIDEO',
            numFaces: 2
        });

        setStatus('📷 No face detected', 'detecting');
        holdStart = null;
        runDetectionLoop();

    } catch (err) {
        console.error('Face verification error:', err);
        setStatus('❌ ' + (err.name === 'NotAllowedError' ? 'Camera access denied' : err.message), 'error');
    }
}

function runDetectionLoop() {
    if (!faceModal.classList.contains('open')) return;
    const ctx = faceCanvas.getContext('2d');
    faceCanvas.width  = faceVideo.videoWidth  || 280;
    faceCanvas.height = faceVideo.videoHeight || 210;

    const results = faceLandmarker.detectForVideo(faceVideo, performance.now());
    const faces   = results.faceLandmarks;
    ctx.clearRect(0, 0, faceCanvas.width, faceCanvas.height);

    if (!faces || faces.length === 0) {
        holdStart = null; setProgress(0);
        setStatus('📷 No face detected — look at the camera', 'detecting');
    } else if (faces.length > 1) {
        holdStart = null; setProgress(0);
        setStatus('⚠️ Multiple faces detected — only one person please', 'detecting');
    } else {
        const landmarks = faces[0];
        ctx.fillStyle = 'rgba(212,101,15,0.7)';
        landmarks.forEach(pt => {
            ctx.beginPath();
            ctx.arc(pt.x * faceCanvas.width, pt.y * faceCanvas.height, 2, 0, Math.PI * 2);
            ctx.fill();
        });
        const noseTip   = landmarks[1];
        const isCentred = noseTip.x > 0.25 && noseTip.x < 0.75 && noseTip.y > 0.20 && noseTip.y < 0.80;
        if (!isCentred) {
            holdStart = null; setProgress(0);
            setStatus('😊 Face detected — move closer to centre', 'detected');
        } else {
            setStatus('✅ Face detected — hold still…', 'detected');
            if (!holdStart) holdStart = performance.now();
            const elapsed = performance.now() - holdStart;
            setProgress((elapsed / HOLD_MS) * 100);
            if (elapsed >= HOLD_MS) { verificationPassed(); return; }
        }
    }
    detectionLoop = requestAnimationFrame(runDetectionLoop);
}

function verificationPassed() {
    setStatus('🎉 Verification successful!', 'success');
    setProgress(100);
    setTimeout(() => {
        closeFaceModal();
        const wallet = document.getElementById('ewalletInput').value;
        if (!wallet) { alert('Please select an e-wallet.'); return; }
        if (cart.length === 0) { alert('Your cart is empty!'); return; }
        if (!USER_HAS_PIN) {
            openPinSetupModal();
        } else {
            proceedToSubmit();
        }
    }, 900);
}

function closeFaceModal() {
    faceModal.classList.remove('open');
    if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
    if (detectionLoop) { cancelAnimationFrame(detectionLoop); detectionLoop = null; }
    faceVideo.srcObject = null;
    holdStart = null;
    setProgress(0);
}

faceCancelBtn.addEventListener('click', closeFaceModal);
faceModal.addEventListener('click', (e) => { if (e.target === faceModal) closeFaceModal(); });

/* ═══════════════════════════════════════════════════════════
   PIN SETUP MODAL
   ═══════════════════════════════════════════════════════════ */
const pinSetupModal = document.getElementById('pinSetupModal');

function openPinSetupModal() {
    document.getElementById('pinInput').value        = '';
    document.getElementById('pinConfirmInput').value = '';
    hidePinError();
    pinSetupModal.classList.add('open');
    const ph = document.getElementById('pinPhone');
    if (ph) onPhoneInput(ph);
}
function closePinModal() { pinSetupModal.classList.remove('open'); }
function showPinError(msg) {
    const el = document.getElementById('pinErrorMsg');
    el.textContent = msg;
    el.classList.add('visible');
}
function hidePinError() { document.getElementById('pinErrorMsg').classList.remove('visible'); }

['pinInput', 'pinConfirmInput'].forEach(id => {
    document.getElementById(id).addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
});

async function submitPin() {
    hidePinError();
    const rawPhone = document.getElementById('pinPhone').value.trim();
    const phone    = normalisePhone(rawPhone);
    const pin      = document.getElementById('pinInput').value;
    const confirm  = document.getElementById('pinConfirmInput').value;

    if (!phone) {
        showPinError('Please enter your phone number.');
        return;
    }
    if (!isValidMalaysiaPhone(phone)) {
        showPinError(phone.startsWith('011')
            ? '011 numbers must be exactly 11 digits (e.g. 01112345678).'
            : 'Phone must start with 01 and be exactly 10 digits (e.g. 0184679907).');
        return;
    }
    if (!/^\d{6}$/.test(pin)) {
        showPinError('PIN must be exactly 6 digits (numbers only).');
        return;
    }
    if (pin !== confirm) {
        showPinError('PINs do not match. Please try again.');
        return;
    }

    const btn = document.getElementById('pinSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    try {
        const fd = new FormData();
        fd.append('action',      'save_pin');
        fd.append('phone',       phone);
        fd.append('pin',         pin);
        fd.append('pin_confirm', confirm);

        const resp = await fetch('payment_ewallet.php', { method: 'POST', body: fd });
        const data = await resp.json();

        if (data.ok) {
            closePinModal();
            proceedToSubmit();
        } else {
            showPinError(data.msg || 'Failed to save PIN. Please try again.');
        }
    } catch (e) {
        showPinError('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Save PIN &amp; Continue';
    }
}

pinSetupModal.addEventListener('click', (e) => { if (e.target === pinSetupModal) closePinModal(); });

/* ═══════════════════════════════════════════════════════════
   FINAL SUBMIT
   ═══════════════════════════════════════════════════════════ */
function proceedToSubmit() {
    document.getElementById('cartDataInput').value    = JSON.stringify(cart);
    document.getElementById('voucherDataInput').value = appliedVoucher ? JSON.stringify(appliedVoucher) : '';
    document.getElementById('qrSection').classList.add('visible');
    document.getElementById('ewalletForm').submit();
}

document.getElementById('ewalletForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const wallet = document.getElementById('ewalletInput').value;
    if (!wallet) { alert('Please select an e-wallet.'); return; }
    if (cart.length === 0) { alert('Your cart is empty!'); return; }
    openFaceModal();
});
</script>
</body>
</html>