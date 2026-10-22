<?php
session_start();
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name    = $is_logged_in ? (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '') : '';
$user_email   = $is_logged_in ? (isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '') : '';
$user_phone   = $is_logged_in ? (isset($_SESSION['user_phone']) ? htmlspecialchars($_SESSION['user_phone']) : '') : '';

if (!$is_logged_in) { header("Location: login.php"); exit(); }

$success      = false;
$order_id     = null;
$error_msg    = '';
$paid_total   = 0;
$field_errors = [];

$banks = [
    'maybank'    => 'Maybank2u',
    'cimb'       => 'CIMB Clicks',
    'publicbank' => 'Public Bank',
    'rhb'        => 'RHB Now',
    'hongleong'  => 'Hong Leong Connect',
    'ambank'     => 'AmOnline',
    'bsn'        => 'BSN',
    'bankrakyat' => 'Bank Rakyat',
    'affin'      => 'Affin Bank',
    'ocbc'       => 'OCBC',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_data     = $_POST['cart_data']  ?? '[]';
    $order_type    = $_POST['order_type'] ?? 'dinein';
    $voucher_data  = $_POST['voucher_data'] ?? '';
    $bank          = mysqli_real_escape_string($connect, $_POST['bank']          ?? '');
    $bank_username = trim($_POST['bank_username'] ?? '');
    $bank_password = $_POST['bank_password'] ?? '';
    $cart          = json_decode($cart_data, true);
    $order_type_db = ($order_type === 'dinein') ? 'dine_in' : 'takeaway';

    // Parse voucher
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
    } else {
        if (empty($bank) || !array_key_exists($bank, $banks)) {
            $field_errors['bank'] = 'Please select your bank.';
        }
        if (strlen($bank_username) < 8 || strlen($bank_username) > 20) {
            $field_errors['bank_username'] = 'Username must be 8–20 characters.';
        }
        if (strlen($bank_password) < 8 || strlen($bank_password) > 20) {
            $field_errors['bank_password'] = 'Password must be 8–20 characters.';
        } elseif (!preg_match('/[A-Z]/', $bank_password)) {
            $field_errors['bank_password'] = 'Password must include at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $bank_password)) {
            $field_errors['bank_password'] = 'Password must include at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $bank_password)) {
            $field_errors['bank_password'] = 'Password must include at least one number.';
        } elseif (!preg_match('/[\W_]/', $bank_password)) {
            $field_errors['bank_password'] = 'Password must include at least one special character.';
        }

        if (empty($field_errors)) {
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

            // ── FIX: tax on FULL subtotal, discount deducted after ──
            $tax        = round($subtotal * 0.06, 2);
            $total      = round($subtotal + $tax - $voucher_discount, 2);
            $paid_total = $total;
            $created_at = date('Y-m-d H:i:s');

            // Build special_notes
            $notes_parts = ["Bank: $bank"];
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

            $pay_res = mysqli_query($connect, "SELECT paymentid FROM payments WHERE paymentid REGEXP '^PAY[0-9]{5}$' ORDER BY paymentid DESC LIMIT 1");
            $pay_num = 1;
            if ($pay_res && mysqli_num_rows($pay_res) > 0) {
                $pay_row = mysqli_fetch_assoc($pay_res);
                $pay_num = intval(substr($pay_row['paymentid'], 3)) + 1;
            }
            $new_pay_id = 'PAY' . str_pad($pay_num, 5, '0', STR_PAD_LEFT);

            $uid_esc    = mysqli_real_escape_string($connect, $user_id);
            $name_esc   = mysqli_real_escape_string($connect, $user_name);
            $email_esc  = mysqli_real_escape_string($connect, $user_email);
            $phone_esc  = mysqli_real_escape_string($connect, $user_phone);
            $ot_esc     = mysqli_real_escape_string($connect, $order_type_db);
            $notes_esc  = mysqli_real_escape_string($connect, $special_notes);

            $sql = "INSERT INTO orders (orderid, userid, order_type, tableid, customer_name, customer_phone, customer_email,
                        subtotal, discount_rm, tax, total_price, payment_method, special_notes, status, points_earned, created_at, updated_at)
                    VALUES ('$new_order_id', '$uid_esc', '$ot_esc', NULL, '$name_esc', '$phone_esc', '$email_esc',
                        $subtotal, $voucher_discount, $tax, $total, 'online_banking', '$notes_esc', 'paid', 0, '$created_at', '$created_at')";

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
                    $pname  = mysqli_real_escape_string($connect, $prices[$pid]['name']);
                    $stored_price = isset($item['price']) ? floatval($item['price']) : 0;
                    $pprice = ($stored_price > 0) ? $stored_price : floatval($prices[$pid]['price']);
                    $psub   = round($pprice * $qty, 2);

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
                        VALUES ('$new_oi_id', '$order_id', '$pid', '$pname', $qty, $pprice, $psub, $custom_json)");
                }

                mysqli_query($connect, "INSERT INTO payments (paymentid, orderid, user_id, method, status, amount, created_at)
                    VALUES ('$new_pay_id', '$order_id', '$uid_esc', 'online_banking', 'paid', $total, '$created_at')");

                // Deduct stock
                foreach ($cart as $item) {
                    $pid = mysqli_real_escape_string($connect, trim($item['id'] ?? ''));
                    $qty = intval($item['quantity'] ?? 1);
                    if (empty($pid) || $qty <= 0) continue;
                    mysqli_query($connect, "UPDATE products SET stock = GREATEST(0, stock - $qty) WHERE productid = '$pid'");
                }

                // Award points
                require_once 'award_points.php';
                awardPoints($connect, $uid_esc, $new_order_id, $total);

                // Mark voucher as used
                if ($voucher && isset($voucher['voucherid']) && $voucher_discount > 0) {
                    $vid_esc = mysqli_real_escape_string($connect, $voucher['voucherid']);
                    mysqli_query($connect, "UPDATE user_vouchers SET is_used=1, used_at='$created_at', used_order_id='$new_order_id'
                        WHERE voucherid='$vid_esc' AND userid='$uid_esc'");
                }

                mysqli_commit($connect);
                $success = true;
            } else {
                mysqli_rollback($connect);
                $error_msg = 'Order failed: ' . mysqli_error($connect);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Banking - FOODTIME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/payment.css">
    <style>
        .bank-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:8px;}
        .bank-option{border:2px solid #e0e0e0;border-radius:10px;padding:12px 14px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:all 0.2s;background:var(--card-bg);}
        [data-theme="dark"] .bank-option{border-color:#444;}
        .bank-option:hover{border-color:var(--primary-color);}
        .bank-option.selected{border-color:var(--primary-color);background:linear-gradient(135deg,rgba(212,101,15,0.06),rgba(255,152,0,0.06));}
        .bank-option input[type="radio"]{display:none;}
        .bank-dot{width:16px;height:16px;border-radius:50%;border:2px solid #ccc;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all 0.2s;}
        .bank-option.selected .bank-dot{border-color:var(--primary-color);background:var(--primary-color);}
        .bank-option.selected .bank-dot::after{content:'';width:6px;height:6px;background:white;border-radius:50%;display:block;}
        .bank-name{font-size:13px;font-weight:600;color:var(--light-text);}
        .transfer-box{background:rgba(212,101,15,0.07);border-radius:12px;padding:18px 20px;margin-bottom:24px;}
        .transfer-box-title{margin:0 0 14px;font-weight:700;color:var(--light-text);font-size:14px;display:flex;align-items:center;gap:8px;}
        .transfer-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(212,101,15,0.12);}
        .transfer-row:last-child{border-bottom:none;padding-bottom:0;}
        .transfer-label{font-size:13px;color:var(--light-gray);}
        .transfer-value{font-size:13px;font-weight:700;color:var(--light-text);text-align:right;display:flex;align-items:center;gap:6px;}
        .transfer-value.accent{color:var(--primary-color);letter-spacing:1px;}
        .copy-btn{background:none;border:1px solid var(--primary-color);color:var(--primary-color);border-radius:5px;padding:2px 8px;font-size:11px;cursor:pointer;transition:all 0.2s;white-space:nowrap;}
        .copy-btn:hover{background:var(--primary-color);color:#fff;}
        .copy-btn.copied{background:#27ae60;border-color:#27ae60;color:#fff;}
        .bank-login-panel{display:none;border:2px solid rgba(212,101,15,0.3);border-radius:12px;padding:20px 20px 14px;margin-top:4px;margin-bottom:20px;background:var(--card-bg);}
        .bank-login-panel.visible{display:block;}
        .bank-login-header{display:flex;align-items:center;gap:12px;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid rgba(212,101,15,0.15);}
        .bank-login-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--primary-color),#ff9800);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0;}
        .bank-login-title{font-size:14px;font-weight:700;color:var(--light-text);margin:0 0 2px;}
        .bank-login-sub{font-size:11px;color:var(--light-gray);margin:0;}
        .secure-note{display:flex;align-items:center;gap:6px;font-size:11px;color:#27ae60;margin-top:10px;}
        .strength-wrap{margin-top:6px;}
        .strength-bars{display:flex;gap:4px;margin-bottom:4px;}
        .strength-bar{flex:1;height:4px;border-radius:4px;background:#e0e0e0;transition:background 0.3s;}
        [data-theme="dark"] .strength-bar{background:#444;}
        .strength-label{font-size:11px;font-weight:600;}
        .field-error{display:none;color:#e74c3c;font-size:12px;margin-top:5px;align-items:center;gap:5px;}
        .field-error.visible{display:flex;}
        .form-input.input-invalid{border-color:#e74c3c!important;background:rgba(231,76,60,0.04);}
        .form-input.input-valid{border-color:#27ae60!important;}
        .pw-wrapper{position:relative;}
        .pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--light-gray);cursor:pointer;padding:0;font-size:14px;}
        #bankPassword{padding-right:44px;}
        #err-bank{margin-top:-18px;margin-bottom:12px;}
    </style>
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
                        <span class="user-greeting">👋 Hi, <?php echo $user_name; ?></span>
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
            <h1 class="payment-page-title"><i class="fas fa-university"></i> Online Banking</h1>

            <?php if ($success): ?>
            <div class="payment-layout">
                <div class="success-banner">
                    <div class="s-icon">✅</div>
                    <h2>Payment Successful!</h2>
                    <p>Your online banking payment has been processed. Your order is confirmed!</p>
                    <div class="success-meta">
                        <div class="success-meta-item"><span>Order ID</span><strong><?php echo htmlspecialchars($order_id); ?></strong></div>
                        <div class="success-meta-item"><span>Payment</span><strong>Online Banking</strong></div>
                        <div class="success-meta-item"><span>Amount</span><strong>RM <?php echo number_format($paid_total, 2); ?></strong></div>
                        <div class="success-meta-item"><span>Status</span><strong>Paid</strong></div>
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
                    <div class="pay-icon"><i class="fas fa-university"></i></div>
                    <h2 class="pay-title">Online Banking / FPX</h2>
                    <p class="pay-subtitle">Select your bank and log in with your online banking credentials to proceed.</p>

                    <?php if ($error_msg): ?>
                    <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
                    <?php endif; ?>

                    <div class="transfer-box">
                        <p class="transfer-box-title"><i class="fas fa-building-columns" style="color:var(--primary-color);"></i> Transfer to</p>
                        <div class="transfer-row"><span class="transfer-label">Bank</span><span class="transfer-value">Maybank</span></div>
                        <div class="transfer-row">
                            <span class="transfer-label">Account No.</span>
                            <span class="transfer-value accent">1234 5678 9012
                                <button class="copy-btn" type="button" onclick="copyText('1234567890120', this)"><i class="fas fa-copy"></i> Copy</button>
                            </span>
                        </div>
                        <div class="transfer-row"><span class="transfer-label">Account Name</span><span class="transfer-value">FOODTIME SDN BHD</span></div>
                        <div class="transfer-row"><span class="transfer-label">Account Type</span><span class="transfer-value">Current Account</span></div>
                        <div class="transfer-row"><span class="transfer-label">Amount Due</span><span class="transfer-value accent" id="transferAmountRow">RM —</span></div>
                    </div>

                    <form method="POST" id="onlineForm" novalidate>
                        <input type="hidden" name="cart_data"    id="cartDataInput">
                        <input type="hidden" name="order_type"   id="orderTypeInput" value="dinein">
                        <input type="hidden" name="voucher_data" id="voucherDataInput">

                        <div class="form-group">
                            <label><i class="fas fa-landmark" style="color:var(--primary-color);"></i> &nbsp;Select Your Bank</label>
                            <div class="bank-grid">
                                <?php foreach ($banks as $val => $label): ?>
                                <label class="bank-option <?php echo (isset($_POST['bank']) && $_POST['bank'] === $val) ? 'selected' : ''; ?>" onclick="selectBank(this, '<?php echo $label; ?>')">
                                    <input type="radio" name="bank" value="<?php echo $val; ?>" <?php echo (isset($_POST['bank']) && $_POST['bank'] === $val) ? 'checked' : ''; ?>>
                                    <div class="bank-dot"></div>
                                    <span class="bank-name"><?php echo $label; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="field-error <?php echo isset($field_errors['bank']) ? 'visible' : ''; ?>" id="err-bank">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo $field_errors['bank'] ?? 'Please select your bank.'; ?></span>
                            </div>
                        </div>

                        <div class="bank-login-panel <?php echo (!empty($_POST['bank'])) ? 'visible' : ''; ?>" id="bankLoginPanel">
                            <div class="bank-login-header">
                                <div class="bank-login-icon"><i class="fas fa-lock"></i></div>
                                <div>
                                    <p class="bank-login-title" id="bankLoginTitle">Bank Login</p>
                                    <p class="bank-login-sub">Enter your online banking credentials to authorise</p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bankUsername"><i class="fas fa-user" style="color:var(--primary-color);"></i>&nbsp; Banking Username</label>
                                <input type="text" id="bankUsername" name="bank_username"
                                    class="form-input <?php echo isset($field_errors['bank_username']) ? 'input-invalid' : ''; ?>"
                                    placeholder="Enter your banking username" maxlength="20" autocomplete="username"
                                    value="<?php echo isset($_POST['bank_username']) ? htmlspecialchars($_POST['bank_username']) : ''; ?>">
                                <span style="font-size:11px;color:var(--light-gray);margin-top:4px;display:block;">8–20 characters</span>
                                <div class="field-error <?php echo isset($field_errors['bank_username']) ? 'visible' : ''; ?>" id="err-bank_username">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span><?php echo $field_errors['bank_username'] ?? 'Username must be 8–20 characters.'; ?></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bankPassword"><i class="fas fa-lock" style="color:var(--primary-color);"></i>&nbsp; Banking Password</label>
                                <div class="pw-wrapper">
                                    <input type="password" id="bankPassword" name="bank_password"
                                        class="form-input <?php echo isset($field_errors['bank_password']) ? 'input-invalid' : ''; ?>"
                                        placeholder="Enter your banking password" maxlength="20" autocomplete="current-password"
                                        oninput="checkStrength(this.value)">
                                    <button type="button" class="pw-toggle" id="pwToggle" title="Show/Hide">
                                        <i class="fas fa-eye" id="pwEyeIcon"></i>
                                    </button>
                                </div>
                                <div class="strength-wrap" id="strengthWrap" style="display:none;">
                                    <div class="strength-bars">
                                        <div class="strength-bar" id="sb1"></div>
                                        <div class="strength-bar" id="sb2"></div>
                                        <div class="strength-bar" id="sb3"></div>
                                        <div class="strength-bar" id="sb4"></div>
                                    </div>
                                    <span class="strength-label" id="strengthLabel"></span>
                                </div>
                                <span style="font-size:11px;color:var(--light-gray);margin-top:4px;display:block;">8–20 chars · uppercase · lowercase · number · special character</span>
                                <div class="field-error <?php echo isset($field_errors['bank_password']) ? 'visible' : ''; ?>" id="err-bank_password">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span><?php echo $field_errors['bank_password'] ?? 'Password does not meet requirements.'; ?></span>
                                </div>
                            </div>
                            <div class="secure-note">
                                <i class="fas fa-shield-halved"></i>
                                Your credentials are used for authorisation only and are never stored.
                            </div>
                        </div>

                        <button type="submit" class="btn-pay-primary"><i class="fas fa-paper-plane"></i> Submit Payment</button>
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
                        <div class="summary-row" id="discountRow" style="display:none;color:#16a34a;font-weight:700;">
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
                <div class="footer-section"><h4>🍴 About FOODTIME</h4><a href="#">About Us</a><a href="#">Blog</a><a href="#">Careers</a></div>
                <div class="footer-section"><h4>Support</h4><a href="#">Help Center</a><a href="#">Contact Us</a><a href="#">FAQ</a></div>
                <div class="footer-section"><h4>Legal</h4><a href="#">Privacy Policy</a><a href="#">Terms & Conditions</a><a href="#">Refund Policy</a></div>
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
    const TAX       = 0.06;
    const cart      = JSON.parse(localStorage.getItem('cart') || '[]');
    const orderType = sessionStorage.getItem('orderType') || 'dinein';
    document.getElementById('orderTypeInput').value         = orderType;
    document.getElementById('orderTypeDisplay').textContent = orderType === 'dinein' ? '🍽️  Dine In' : '🛍️  Takeaway';

    let appliedVoucher = null;
    try { const saved = sessionStorage.getItem('appliedVoucher'); if (saved) appliedVoucher = JSON.parse(saved); } catch(e) {}

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
                    html += `<div class="summary-item"><div><div class="summary-item-name">${name}</div><div class="summary-item-qty">x${qty} × RM ${unitPrice.toFixed(2)}</div></div><div class="summary-item-price">RM ${line.toFixed(2)}</div></div>`;
                });

                // ── FIX: tax on FULL subtotal, discount deducted after ──
                const discount = appliedVoucher ? Math.min(parseFloat(appliedVoucher.discount_rm), sub) : 0;
                const tax      = sub * TAX;
                const total    = sub + tax - discount;

                document.getElementById('summaryItems').innerHTML      = html;
                document.getElementById('summarySubtotal').textContent = 'RM ' + sub.toFixed(2);
                document.getElementById('summaryTax').textContent      = 'RM ' + tax.toFixed(2);
                document.getElementById('summaryTotal').textContent    = 'RM ' + total.toFixed(2);
                const amtRow = document.getElementById('transferAmountRow');
                if (amtRow) amtRow.textContent = 'RM ' + total.toFixed(2);
                if (discount > 0) {
                    document.getElementById('discountRow').style.display   = 'flex';
                    document.getElementById('summaryDiscount').textContent = '- RM ' + discount.toFixed(2);
                }
            });
    } else {
        document.getElementById('summaryItems').innerHTML = '<p style="color:var(--light-gray);font-size:14px;">Cart is empty.</p>';
    }

    function selectBank(el, bankName) {
        document.querySelectorAll('.bank-option').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        el.querySelector('input[type="radio"]').checked = true;
        document.getElementById('bankLoginPanel').classList.add('visible');
        document.getElementById('bankLoginTitle').textContent = bankName + ' — Secure Login';
        document.getElementById('err-bank').classList.remove('visible');
    }

    document.getElementById('pwToggle').addEventListener('click', function () {
        const inp = document.getElementById('bankPassword');
        const icon = document.getElementById('pwEyeIcon');
        if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
        else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
    });

    function checkStrength(val) {
        const wrap = document.getElementById('strengthWrap');
        if (!val) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'block';
        let score = 0;
        if (val.length >= 8)   score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[\W_]/.test(val)) score++;
        const colors = ['#e74c3c','#e74c3c','#e67e22','#f1c40f','#27ae60'];
        const labels = ['Very Weak','Weak','Fair','Good','Strong'];
        const fill   = colors[score - 1] || '#e0e0e0';
        for (let i = 1; i <= 4; i++) {
            document.getElementById('sb' + i).style.background = i <= score ? fill : (document.documentElement.getAttribute('data-theme') === 'dark' ? '#444' : '#e0e0e0');
        }
        const lbl = document.getElementById('strengthLabel');
        lbl.textContent = labels[score - 1] || '';
        lbl.style.color = fill;
    }

    function showErr(id, msg) {
        const el = document.getElementById('err-' + id);
        if (el) { el.querySelector('span').textContent = msg; el.classList.add('visible'); }
        const inp = document.getElementById(id === 'bank_username' ? 'bankUsername' : id === 'bank_password' ? 'bankPassword' : null);
        if (inp) { inp.classList.add('input-invalid'); inp.classList.remove('input-valid'); }
    }
    function clearErr(id) {
        const el = document.getElementById('err-' + id);
        if (el) el.classList.remove('visible');
        const inp = document.getElementById(id === 'bank_username' ? 'bankUsername' : id === 'bank_password' ? 'bankPassword' : null);
        if (inp) { inp.classList.remove('input-invalid'); inp.classList.add('input-valid'); }
    }

    document.getElementById('bankUsername').addEventListener('input', function () {
        const v = this.value;
        if (!v) return;
        if (v.length < 8 || v.length > 20) showErr('bank_username', 'Username must be 8–20 characters (currently ' + v.length + ').');
        else clearErr('bank_username');
    });
    document.getElementById('bankPassword').addEventListener('input', function () {
        const v = this.value;
        if (!v) return;
        if (v.length < 8 || v.length > 20)   showErr('bank_password', 'Password must be 8–20 characters.');
        else if (!/[A-Z]/.test(v))            showErr('bank_password', 'Add at least one uppercase letter (A–Z).');
        else if (!/[a-z]/.test(v))            showErr('bank_password', 'Add at least one lowercase letter (a–z).');
        else if (!/[0-9]/.test(v))            showErr('bank_password', 'Add at least one number (0–9).');
        else if (!/[\W_]/.test(v))            showErr('bank_password', 'Add at least one special character (e.g. @, #, !).');
        else                                  clearErr('bank_password');
    });

    function copyText(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            btn.classList.add('copied');
            setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; btn.classList.remove('copied'); }, 2000);
        });
    }

    document.getElementById('onlineForm').addEventListener('submit', function (e) {
        let hasError = false;
        const bankChecked = document.querySelector('input[name="bank"]:checked');
        if (!bankChecked) { document.getElementById('err-bank').classList.add('visible'); hasError = true; }
        const uVal = document.getElementById('bankUsername').value;
        if (uVal.length < 8 || uVal.length > 20) { showErr('bank_username', 'Username must be 8–20 characters.'); hasError = true; }
        else clearErr('bank_username');
        const pVal = document.getElementById('bankPassword').value;
        if (pVal.length < 8 || pVal.length > 20)   { showErr('bank_password', 'Password must be 8–20 characters.');                  hasError = true; }
        else if (!/[A-Z]/.test(pVal))               { showErr('bank_password', 'Add at least one uppercase letter (A–Z).');           hasError = true; }
        else if (!/[a-z]/.test(pVal))               { showErr('bank_password', 'Add at least one lowercase letter (a–z).');           hasError = true; }
        else if (!/[0-9]/.test(pVal))               { showErr('bank_password', 'Add at least one number (0–9).');                     hasError = true; }
        else if (!/[\W_]/.test(pVal))               { showErr('bank_password', 'Add at least one special character (e.g. @, #, !).'); hasError = true; }
        else                                        clearErr('bank_password');
        if (hasError) {
            e.preventDefault();
            const firstErr = document.querySelector('.field-error.visible');
            if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
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