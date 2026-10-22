<?php
// payment_ewallet_qr.php — Phone page opened when user scans QR code.
// Customer is already registered — only PIN is required, no phone input.
session_start();
include 'base.php';

$orderid      = isset($_GET['orderid']) ? trim($_GET['orderid']) : '';
$method_param = isset($_GET['method'])  ? trim($_GET['method'])  : 'ewallet';

$error_msg    = '';
$pin_error    = '';
$pay_success  = false;
$already_paid = false;
$order        = null;

// ── Wallet display helpers ────────────────────────────────────────────────────
$wallet_labels = [
    'tng'      => "Touch 'n Go",
    'grabpay'  => 'GrabPay',
    'boost'    => 'Boost',
    'shopeepay'=> 'ShopeePay',
    'mae'      => 'MAE',
    'ewallet'  => 'E-Wallet',
    'other'    => 'E-Wallet',
];
$wallet_emojis = [
    'tng'      => '💚',
    'grabpay'  => '🟢',
    'boost'    => '🔴',
    'shopeepay'=> '🟠',
    'mae'      => '🟡',
    'ewallet'  => '📱',
    'other'    => '📱',
];

// ── Validate order ID ─────────────────────────────────────────────────────────
if (empty($orderid)) {
    $error_msg = 'Missing order ID. Please scan the QR code again.';
} else {
    $stmt = mysqli_prepare($connect,
        "SELECT o.*, u.ewallet_pin_hash, u.phone AS owner_phone
         FROM orders o
         LEFT JOIN users u ON u.userid = o.userid
         WHERE o.orderid = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 's', $orderid);
    mysqli_stmt_execute($stmt);
    $res   = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$order) {
        $error_msg = 'Order not found. Please scan the QR code again.';
    } elseif ($order['status'] !== 'pending') {
        $already_paid = true;
        if (!empty($order['payment_method'])) $method_param = $order['payment_method'];
    } else {
        if (!empty($order['payment_method'])) $method_param = $order['payment_method'];

        if (empty($order['ewallet_pin_hash'])) {
            $error_msg = 'No e-wallet PIN found for this account. Please set up your PIN on the ordering screen first, then scan again.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin_verify'])) {

            $submitted_pin = trim($_POST['pin_verify'] ?? '');

            if (!preg_match('/^\d{6}$/', $submitted_pin)) {
                $pin_error = 'PIN must be exactly 6 digits (numbers only).';
            } elseif (!password_verify($submitted_pin, $order['ewallet_pin_hash'])) {
                $pin_error = 'Incorrect PIN. Please try again.';
            } else {
                // ── PIN correct — process payment ──────────────────────────
                $safe_method    = mysqli_real_escape_string($connect, $method_param);
                $payment_method = 'ewallet';
                $now            = date('Y-m-d H:i:s');
                $uid            = $order['userid'];
                $amt            = $order['total_price'];

                mysqli_begin_transaction($connect);

                $u = mysqli_prepare($connect,
                    "UPDATE orders SET status = 'paid', payment_method = ?, updated_at = ?
                     WHERE orderid = ? AND status = 'pending'"
                );
                mysqli_stmt_bind_param($u, 'sss', $safe_method, $now, $orderid);
                mysqli_stmt_execute($u);
                $affected = mysqli_stmt_affected_rows($u);
                mysqli_stmt_close($u);

                if ($affected > 0) {
                    // Upsert payments table
                    $ps = mysqli_prepare($connect,
                        "SELECT paymentid FROM payments WHERE orderid = ? LIMIT 1"
                    );
                    mysqli_stmt_bind_param($ps, 's', $orderid);
                    mysqli_stmt_execute($ps);
                    $pr   = mysqli_stmt_get_result($ps);
                    $epay = mysqli_fetch_assoc($pr);
                    mysqli_stmt_close($ps);

                    if ($epay) {
                        $pu = mysqli_prepare($connect,
                            "UPDATE payments SET status = 'paid', method = ? WHERE orderid = ?"
                        );
                        mysqli_stmt_bind_param($pu, 'ss', $payment_method, $orderid);
                        mysqli_stmt_execute($pu);
                        mysqli_stmt_close($pu);
                    } else {
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

                        $pi = mysqli_prepare($connect,
                            "INSERT INTO payments
                                (paymentid, orderid, user_id, method, status, amount, created_at)
                             VALUES (?, ?, ?, ?, 'paid', ?, ?)"
                        );
                        mysqli_stmt_bind_param($pi, 'ssssds',
                            $new_pay_id, $orderid, $uid, $payment_method, $amt, $now
                        );
                        mysqli_stmt_execute($pi);
                        mysqli_stmt_close($pi);
                    }

                    mysqli_commit($connect);

                    require_once 'award_points.php';
                    awardPoints($connect, $uid, $orderid, $amt);

                    $pay_success = true;

                } else {
                    mysqli_rollback($connect);
                    $already_paid = true;
                }
            }
        }
    }
}

$wallet_label = $wallet_labels[$method_param] ?? 'E-Wallet';
$wallet_emoji = $wallet_emojis[$method_param] ?? '📱';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>FoodTimePay — QR Payment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f7f0e8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .fp-header {
            width: 100%;
            background: linear-gradient(135deg, #d4650f, #ff9800);
            padding: 18px 20px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 3px 12px rgba(212,101,15,0.35);
        }
        .fp-logo-icon { width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .fp-logo-text { color: #fff; font-size: 20px; font-weight: 800; }
        .fp-logo-sub  { color: rgba(255,255,255,0.8); font-size: 11px; margin-top: 1px; }

        .fp-card { width: 100%; max-width: 420px; background: #fff; border-radius: 20px; box-shadow: 0 8px 30px rgba(212,101,15,0.13); margin: 22px 16px 0; padding: 28px 24px; }

        .fp-wallet-badge { display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg,rgba(212,101,15,0.07),rgba(255,152,0,0.05)); border: 1.5px solid rgba(212,101,15,0.2); border-radius: 14px; padding: 14px 16px; margin-bottom: 20px; }
        .fp-wallet-emoji { font-size: 32px; }
        .fp-wallet-name  { font-size: 16px; font-weight: 800; color: #d4650f; }
        .fp-wallet-sub   { font-size: 12px; color: #888; }

        .fp-order-strip { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 22px; }
        .fp-strip-item { background: #fafafa; border: 1px solid #f0e0d0; border-radius: 10px; padding: 10px 12px; }
        .fp-strip-item span   { font-size: 11px; color: #999; display: block; margin-bottom: 2px; }
        .fp-strip-item strong { font-size: 14px; color: #333; }

        /* ── PIN section ── */
        .fp-pin-title    { font-size: 17px; font-weight: 800; color: #333; margin-bottom: 4px; }
        .fp-pin-subtitle { font-size: 13px; color: #777; margin-bottom: 18px; line-height: 1.6; }

        .fp-field-group { margin-bottom: 14px; }
        .fp-field-group label { display: block; font-size: 13px; font-weight: 700; color: #555; margin-bottom: 6px; }

        /* Large PIN dots display */
        .fp-pin-dots {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 6px 0 18px;
        }
        .fp-pin-dot {
            width: 18px; height: 18px;
            border-radius: 50%;
            border: 2px solid #d4650f;
            background: #fff;
            transition: background 0.15s;
        }
        .fp-pin-dot.filled { background: #d4650f; }

        .fp-pin-input-wrap { position: relative; }
        .fp-pin-input-wrap input {
            width: 100%;
            padding: 14px 48px 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 24px;
            letter-spacing: 10px;
            font-family: monospace;
            outline: none;
            transition: border-color 0.2s;
            background: #fff;
            text-align: center;
        }
        .fp-pin-input-wrap input:focus { border-color: #d4650f; }
        .fp-pin-input-wrap input.error { border-color: #e74c3c; }

        .fp-pin-toggle {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            cursor: pointer; color: #aaa; font-size: 16px; user-select: none;
        }

        .fp-pin-error {
            background: #fff0f0; border-left: 4px solid #e74c3c;
            border-radius: 8px; padding: 10px 14px;
            font-size: 13px; color: #c0392b;
            margin-bottom: 14px; display: flex; gap: 8px; align-items: flex-start;
        }
        .fp-pin-error.hidden { display: none !important; }

        .fp-btn-pay {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #d4650f, #ff9800);
            color: white; border: none; border-radius: 14px;
            font-size: 17px; font-weight: 800; cursor: pointer;
            transition: opacity 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .fp-btn-pay:hover  { opacity: 0.92; }
        .fp-btn-pay:active { opacity: 0.80; }
        .fp-btn-pay:disabled { opacity: 0.6; cursor: not-allowed; }

        .fp-security-note {
            margin-top: 14px; display: flex; align-items: center;
            gap: 8px; font-size: 11px; color: #aaa; justify-content: center;
        }

        /* ── Success ── */
        .fp-success { text-align: center; padding: 10px 0; }
        .fp-success-anim {
            width: 90px; height: 90px; border-radius: 50%;
            background: linear-gradient(135deg,#27ae60,#2ecc71);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px; font-size: 44px;
            animation: pop 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes pop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .fp-success-title { font-size: 24px; font-weight: 800; color: #27ae60; margin-bottom: 8px; }
        .fp-success-sub   { font-size: 14px; color: #666; line-height: 1.7; margin-bottom: 20px; }
        .fp-success-meta  { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 6px; }
        .fp-meta-item { background: #f0faf5; border-radius: 10px; padding: 12px; text-align: center; }
        .fp-meta-item span   { font-size: 11px; color: #888; display: block; }
        .fp-meta-item strong { font-size: 13px; color: #27ae60; }
        .fp-return-hint {
            margin-top: 22px; background: #fff8f2;
            border: 1.5px dashed #f0a96a; border-radius: 12px;
            padding: 14px 16px; font-size: 13px; color: #b85a00;
            display: flex; align-items: center; gap: 10px;
        }
        .fp-return-hint i { font-size: 18px; }

        /* ── Already paid / error ── */
        .fp-paid-badge { background: #e8f8f0; border: 2px solid #27ae60; border-radius: 14px; padding: 24px; text-align: center; }
        .fp-paid-badge i     { font-size: 40px; color: #27ae60; display: block; margin-bottom: 10px; }
        .fp-paid-badge p     { font-size: 17px; font-weight: 700; color: #27ae60; }
        .fp-paid-badge small { font-size: 12px; color: #666; }

        .fp-error { background: #fff0f0; border-left: 4px solid #e74c3c; border-radius: 8px; padding: 14px 16px; font-size: 14px; color: #c0392b; display: flex; gap: 10px; align-items: flex-start; }

        .fp-setup-prompt { background: #fff8f0; border: 2px solid #f0a96a; border-radius: 14px; padding: 20px; text-align: center; }
        .fp-setup-prompt i    { font-size: 36px; color: #d4650f; display: block; margin-bottom: 10px; }
        .fp-setup-prompt h3   { font-size: 16px; font-weight: 800; color: #d4650f; margin-bottom: 8px; }
        .fp-setup-prompt p    { font-size: 13px; color: #666; margin-bottom: 16px; line-height: 1.6; }
    </style>
</head>
<body>

<div class="fp-header">
    <div class="fp-logo-icon">🍔</div>
    <div>
        <div class="fp-logo-text">FoodTimePay</div>
        <div class="fp-logo-sub">Secure QR Payment</div>
    </div>
</div>

<?php if ($pay_success): ?>
<!-- ── PAYMENT CONFIRMED ───────────────────────────────────────── -->
<div class="fp-card">
    <div class="fp-success">
        <div class="fp-success-anim">✓</div>
        <div class="fp-success-title">Payment Confirmed!</div>
        <div class="fp-success-sub">
            PIN verified — payment recorded.<br>
            Your order is being prepared. 🎉
        </div>
        <div class="fp-success-meta">
            <div class="fp-meta-item"><span>Order ID</span><strong><?= htmlspecialchars($orderid) ?></strong></div>
            <div class="fp-meta-item"><span>Method</span><strong><?= $wallet_emoji ?> <?= htmlspecialchars($wallet_label) ?></strong></div>
            <div class="fp-meta-item"><span>Amount</span><strong>RM <?= number_format((float)($order['total_price'] ?? 0), 2) ?></strong></div>
            <div class="fp-meta-item"><span>Status</span><strong>Paid ✓</strong></div>
        </div>
        <div class="fp-return-hint">
            <i class="fas fa-desktop"></i>
            The computer screen will update automatically. You may put your phone away.
        </div>
    </div>
</div>

<?php elseif ($already_paid): ?>
<!-- ── ALREADY PAID ───────────────────────────────────────────── -->
<div class="fp-card">
    <div class="fp-paid-badge">
        <i class="fas fa-check-circle"></i>
        <p>Already Paid</p>
        <small>This order has already been completed. No further action needed.</small>
    </div>
</div>

<?php elseif (!empty($error_msg)): ?>
<!-- ── HARD ERROR ─────────────────────────────────────────────── -->
<div class="fp-card">
    <?php if (strpos($error_msg, 'PIN') !== false): ?>
    <div class="fp-setup-prompt">
        <i class="fas fa-lock"></i>
        <h3>E-Wallet PIN Required</h3>
        <p>
            You haven't set up your e-wallet PIN yet.<br><br>
            Please go back to the ordering screen on the computer, click <strong>Confirm &amp; Show QR</strong>,
            and set up your PIN first. Then scan the QR code again.
        </p>
        <p style="font-size:12px;color:#aaa;margin-top:8px;">
            Your PIN is a 6-digit number that protects your QR payments.
        </p>
    </div>
    <?php else: ?>
    <div class="fp-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ── PIN VERIFICATION FORM (no phone input) ─────────────────── -->
<div class="fp-card">

    <div class="fp-wallet-badge">
        <div class="fp-wallet-emoji"><?= $wallet_emoji ?></div>
        <div>
            <div class="fp-wallet-name"><?= htmlspecialchars($wallet_label) ?></div>
            <div class="fp-wallet-sub">Scan &amp; Pay — FOODTIME</div>
        </div>
    </div>

    <div class="fp-order-strip">
        <div class="fp-strip-item"><span>Order ID</span><strong><?= htmlspecialchars($orderid) ?></strong></div>
        <div class="fp-strip-item"><span>Amount</span><strong>RM <?= number_format((float)($order['total_price'] ?? 0), 2) ?></strong></div>
        <div class="fp-strip-item"><span>Order Type</span><strong><?= htmlspecialchars(ucfirst(str_replace('_',' ',$order['order_type'] ?? ''))) ?></strong></div>
        <div class="fp-strip-item"><span>Status</span><strong>⏳ Pending</strong></div>
    </div>

    <div class="fp-pin-title">🔒 Enter Your PIN</div>
    <div class="fp-pin-subtitle">
        Enter your 6-digit e-wallet PIN to confirm this payment.
    </div>

    <?php if (!empty($pin_error)): ?>
    <div class="fp-pin-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($pin_error) ?>
    </div>
    <?php else: ?>
    <div class="fp-pin-error hidden"></div>
    <?php endif; ?>

    <form method="POST"
          action="payment_ewallet_qr.php?orderid=<?= urlencode($orderid) ?>&method=<?= urlencode($method_param) ?>"
          id="pinForm">

        <!-- Visual PIN dots -->
        <div class="fp-pin-dots" id="pinDots">
            <div class="fp-pin-dot" id="dot0"></div>
            <div class="fp-pin-dot" id="dot1"></div>
            <div class="fp-pin-dot" id="dot2"></div>
            <div class="fp-pin-dot" id="dot3"></div>
            <div class="fp-pin-dot" id="dot4"></div>
            <div class="fp-pin-dot" id="dot5"></div>
        </div>

        <div class="fp-field-group">
            <label for="pinVerifyInput">
                <i class="fas fa-lock" style="color:#d4650f;margin-right:4px;"></i> 6-Digit E-Wallet PIN
            </label>
            <div class="fp-pin-input-wrap">
                <input type="password"
                       id="pinVerifyInput"
                       name="pin_verify"
                       maxlength="6"
                       inputmode="numeric"
                       pattern="\d{6}"
                       placeholder="••••••"
                       autocomplete="off"
                       autofocus
                       required>
                <span class="fp-pin-toggle" onclick="togglePinVisibility()" title="Show/hide PIN">
                    <i class="fas fa-eye" id="pinToggleIcon"></i>
                </span>
            </div>
        </div>

        <button type="submit" class="fp-btn-pay" id="payBtn">
            <i class="fas fa-lock-open"></i> Confirm Payment
        </button>
    </form>

    <div class="fp-security-note">
        <i class="fas fa-shield-alt"></i>
        PIN verified securely on server. Never stored in plain text.
    </div>
</div>

<script>
const pinInput = document.getElementById('pinVerifyInput');

/* ── Live PIN dot update ── */
pinInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    const len = this.value.length;
    for (let i = 0; i < 6; i++) {
        document.getElementById('dot' + i).classList.toggle('filled', i < len);
    }
});

/* ── Form submit validation ── */
document.getElementById('pinForm').addEventListener('submit', function (e) {
    const pin   = pinInput.value;
    const errEl = document.querySelector('.fp-pin-error');

    if (!/^\d{6}$/.test(pin)) {
        e.preventDefault();
        errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> PIN must be exactly 6 digits.';
        errEl.classList.remove('hidden');
        return;
    }

    const btn = document.getElementById('payBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…';
});

/* ── Toggle PIN visibility ── */
function togglePinVisibility() {
    const icon = document.getElementById('pinToggleIcon');
    if (pinInput.type === 'password') {
        pinInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pinInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
<?php endif; ?>

</body>
</html>