<?php
session_start();
include 'base.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$orderid   = isset($_GET['orderid']) ? trim($_GET['orderid']) : '';
$order     = null;
$items     = [];
$error_msg = '';

if (empty($orderid)) {
    $error_msg = 'Missing order ID.';
} else {
    $stmt = mysqli_prepare($connect, "SELECT * FROM orders WHERE orderid = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $orderid);
    mysqli_stmt_execute($stmt);
    $res   = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$order) {
        $error_msg = 'Order not found.';
    } else {
        $istmt = mysqli_prepare($connect,
            "SELECT product_name, quantity, price, subtotal
             FROM order_items WHERE orderid = ?"
        );
        mysqli_stmt_bind_param($istmt, 's', $orderid);
        mysqli_stmt_execute($istmt);
        $ires = mysqli_stmt_get_result($istmt);
        while ($row = mysqli_fetch_assoc($ires)) $items[] = $row;
        mysqli_stmt_close($istmt);
    }
}

// ── Build QR URL ─────────────────────────────────────────────────
$method   = $order['payment_method'] ?? 'ewallet';
$base_url = 'https://install-query-ours-rent.trycloudflare.com/ASSFOOD/app';

$qr_pay_url = $base_url
            . '/payment_ewallet_qr.php?orderid=' . urlencode($orderid)
            . '&method=' . urlencode($method);
$qr_img_url = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='
            . urlencode($qr_pay_url);

$wallet_labels = [
    'tng'      => "Touch 'n Go", 'grabpay' => 'GrabPay', 'boost' => 'Boost',
    'shopeepay'=> 'ShopeePay',   'mae'     => 'MAE',     'ewallet'=> 'E-Wallet',
    'other'    => 'E-Wallet',
];
$wallet_emojis = [
    'tng'      => '💚', 'grabpay' => '🟢', 'boost' => '🔴',
    'shopeepay'=> '🟠', 'mae'     => '🟡', 'ewallet'=> '📱', 'other'=> '📱',
];
$wallet_label = $wallet_labels[$method] ?? 'E-Wallet';
$wallet_emoji = $wallet_emojis[$method] ?? '📱';

// Is the order already paid when this page first loads?
$already_paid = $order && $order['status'] !== 'pending';

// ── Extract voucher info from special_notes or discount_rm column ─
$discount_rm  = $order ? (float)($order['discount_rm'] ?? 0) : 0;

// Try to pull voucher code from special_notes (e.g. "Voucher: SAVE10 (RM 5.00 off)")
$voucher_code_display = '';
if ($discount_rm > 0 && !empty($order['special_notes'])) {
    if (preg_match('/Voucher:\s*([A-Z0-9_\-]+)/i', $order['special_notes'], $vm)) {
        $voucher_code_display = strtoupper($vm[1]);
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Payment — <?= htmlspecialchars($orderid) ?> — FOODTIME</title>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .qrview-section { padding: 60px 0 80px; min-height: 80vh; }
        .qrview-title {
            color: var(--primary-color); font-size: 28px; font-weight: 800;
            margin: 0 0 32px; display: flex; align-items: center; gap: 10px;
        }
        .qrview-layout {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 28px; max-width: 900px; margin: 0 auto;
        }
        @media (max-width: 700px) { .qrview-layout { grid-template-columns: 1fr; } }

        .qr-panel {
            background: var(--card-bg); border-radius: 20px;
            padding: 32px 28px; text-align: center;
            box-shadow: 0 6px 24px rgba(212,101,15,0.1);
        }
        .wallet-pill {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(212,101,15,0.08); border: 2px solid rgba(212,101,15,0.2);
            border-radius: 30px; padding: 8px 18px;
            font-size: 15px; font-weight: 700; color: var(--primary-color);
            margin-bottom: 18px;
        }
        .qr-frame {
            display: inline-block; background: #fff;
            border: 4px solid var(--primary-color); border-radius: 16px;
            padding: 12px; box-shadow: 0 4px 18px rgba(212,101,15,0.2);
            margin-bottom: 18px;
        }
        .qr-frame img { display: block; border-radius: 8px; }
        .qr-scan-hint { font-size: 13px; color: var(--light-gray); margin-bottom: 12px; }
        .qr-url-box {
            background: #f7f0e8; border-radius: 8px;
            padding: 8px 12px; font-size: 11px; word-break: break-all;
            color: #888; margin-bottom: 20px; text-align: left;
        }
        .qr-url-box strong { color: #d4650f; }
        .qr-steps {
            text-align: left; background: #fff8f2;
            border-radius: 12px; padding: 16px;
        }
        .qr-step {
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; color: var(--light-gray); padding: 5px 0;
        }
        .step-num {
            min-width: 22px; height: 22px; border-radius: 50%;
            background: var(--primary-color); color: #fff;
            font-size: 11px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        .demo-bar {
            margin-top: 18px; background: #fffbe6;
            border: 1.5px dashed #f0c040; border-radius: 12px;
            padding: 14px 16px; text-align: center;
        }
        .demo-bar p { font-size: 11px; color: #b8860b; margin-bottom: 10px; }
        .demo-bar p strong { display: block; font-size: 12px; margin-bottom: 4px; }
        .btn-simulate {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, #f0c040, #e6a800);
            color: #5c4000; border: none; border-radius: 10px;
            padding: 10px 20px; font-size: 13px; font-weight: 700;
            cursor: pointer; transition: opacity 0.15s;
        }
        .btn-simulate:hover    { opacity: 0.85; }
        .btn-simulate:disabled { background: #ccc; color: #999; cursor: not-allowed; }

        .status-panel  { display: flex; flex-direction: column; gap: 20px; }
        .summary-card  {
            background: var(--card-bg); border-radius: 20px;
            padding: 24px; box-shadow: 0 6px 24px rgba(212,101,15,0.1);
        }
        .summary-heading {
            font-size: 16px; font-weight: 700; color: var(--light-text);
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .item-row {
            display: flex; justify-content: space-between;
            font-size: 14px; color: var(--light-text);
            padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .item-row:last-child { border-bottom: none; }
        .item-name { font-weight: 500; }
        .item-qty  { font-size: 12px; color: var(--light-gray); }
        .totals-block {
            background: #f7f0e8; border-radius: 12px;
            padding: 14px 16px; margin-top: 12px;
        }
        .total-row {
            display: flex; justify-content: space-between;
            font-size: 13px; color: #666; padding: 4px 0;
        }
        .total-row.discount {
            color: #16a34a; font-weight: 600;
        }
        .total-row.big {
            font-size: 17px; font-weight: 800; color: #d4650f;
            border-top: 1px solid #e8d8cc; margin-top: 8px; padding-top: 10px;
        }

        .pay-status-card {
            background: var(--card-bg); border-radius: 20px;
            padding: 24px; box-shadow: 0 6px 24px rgba(212,101,15,0.1);
            text-align: center;
        }
        .status-indicator { display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .status-icon      { font-size: 48px; line-height: 1; }
        .status-label     { font-size: 20px; font-weight: 800; }
        .status-sublabel  { font-size: 13px; color: var(--light-gray); }
        .pulse-ring {
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(255,152,0,0.12);
            display: flex; align-items: center; justify-content: center;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(212,101,15,0.35); }
            50%      { box-shadow: 0 0 0 14px rgba(212,101,15,0); }
        }
        .status-pending .status-label { color: #ff9800; }
        .status-paid    .status-label { color: #27ae60; }
        .status-paid    .pulse-ring   { animation: none; background: rgba(39,174,96,0.1); }

        .back-btn {
            display: none;
            align-items: center; gap: 8px;
            color: var(--primary-color); text-decoration: none;
            font-weight: 600; font-size: 14px;
            background: rgba(212,101,15,0.07);
            border: 1.5px solid rgba(212,101,15,0.25);
            border-radius: 10px; padding: 10px 18px;
            transition: background 0.2s;
        }
        .back-btn:hover { background: rgba(212,101,15,0.14); }
        .back-btn.visible { display: inline-flex; }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--primary-color); text-decoration: none;
            font-weight: 600; font-size: 14px; margin-top: 8px;
        }

        .err-box {
            background: #fff0f0; border-left: 4px solid #e74c3c;
            border-radius: 8px; padding: 14px 18px;
            color: #c0392b; font-size: 14px; margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="index.php" class="logo">
                    <i class="fas fa-burger"></i><span>FOODTIME</span>
                </a>
                <div class="auth-buttons">
                    <a href="order_history.php" class="btn btn-primary">
                        <i class="fas fa-history"></i> Orders
                    </a>
                    <a href="logout.php" class="btn btn-logout">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <section class="qrview-section">
        <div class="container">
            <h1 class="qrview-title"><i class="fas fa-qrcode"></i> QR Payment</h1>

            <?php if ($error_msg): ?>
            <div class="err-box">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_msg) ?>
            </div>
            <a href="order_history.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>

            <?php elseif ($order): ?>
            <div class="qrview-layout">

                <!-- ── Left: QR panel ───────────────────── -->
                <div class="qr-panel">
                    <div class="wallet-pill">
                        <?= $wallet_emoji ?> <?= htmlspecialchars($wallet_label) ?>
                    </div>
                    <div class="qr-scan-hint">
                        Scan with <?= htmlspecialchars($wallet_label) ?>
                    </div>
                    <div class="qr-frame">
                        <img src="<?= htmlspecialchars($qr_img_url) ?>"
                             alt="QR Code" width="220" height="220">
                    </div>
                    <div class="qr-url-box">
                        Payment URL:<br>
                        <strong><?= htmlspecialchars($qr_pay_url) ?></strong>
                    </div>
                    <div class="qr-steps">
                        <div class="qr-step">
                            <div class="step-num">1</div>
                            Make sure phone &amp; computer are on the <strong>same Wi-Fi</strong>
                        </div>
                        <div class="qr-step">
                            <div class="step-num">2</div>
                            Scan the QR code with your phone camera
                        </div>
                        <div class="qr-step">
                            <div class="step-num">3</div>
                            Enter your 6-digit e-wallet PIN to confirm
                        </div>
                        <div class="qr-step">
                            <div class="step-num">4</div>
                            This page updates automatically ✓
                        </div>
                    </div>

                    <?php if (!$already_paid): ?>
                    <div class="demo-bar" id="demoBar">
                        <p>
                            <strong>⚠ Demo / Testing only</strong>
                            Can't scan on this network? Use the button below
                            to simulate a successful scan.
                        </p>
                        <button class="btn-simulate" id="btnSimulate"
                                onclick="simulateScan()">
                            <i class="fas fa-mobile-alt"></i>
                            Simulate Scan Success
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── Right: Summary + Status ───────────── -->
                <div class="status-panel">
                    <div class="summary-card">
                        <div class="summary-heading">
                            <i class="fas fa-receipt" style="color:var(--primary-color);"></i>
                            Order Summary — <?= htmlspecialchars($order['orderid']) ?>
                        </div>
                        <?php foreach ($items as $item): ?>
                        <div class="item-row">
                            <div>
                                <div class="item-name">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </div>
                                <div class="item-qty">
                                    x<?= (int)$item['quantity'] ?> ×
                                    RM <?= number_format((float)$item['price'], 2) ?>
                                </div>
                            </div>
                            <div>RM <?= number_format((float)$item['subtotal'], 2) ?></div>
                        </div>
                        <?php endforeach; ?>

                        <div class="totals-block">
                            <div class="total-row">
                                <span>Subtotal</span>
                                <span>RM <?= number_format((float)$order['subtotal'], 2) ?></span>
                            </div>

                            <?php if ($discount_rm > 0): ?>
                            <div class="total-row discount">
                                <span>
                                    <i class="fas fa-tag"></i>
                                    Voucher Discount<?= $voucher_code_display ? ' (' . htmlspecialchars($voucher_code_display) . ')' : '' ?>
                                </span>
                                <span>- RM <?= number_format($discount_rm, 2) ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="total-row">
                                <span>Service Tax (6%)</span>
                                <span>RM <?= number_format((float)$order['tax'], 2) ?></span>
                            </div>
                            <div class="total-row big">
                                <span>Total</span>
                                <span>RM <?= number_format((float)$order['total_price'], 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="pay-status-card" id="statusCard">
                        <div class="status-indicator status-pending" id="statusIndicator">
                            <div class="pulse-ring">
                                <span class="status-icon" id="statusIcon">⏳</span>
                            </div>
                            <div class="status-label" id="statusLabel">
                                Waiting for Payment…
                            </div>
                            <div class="status-sublabel" id="statusSub">
                                Updates automatically every 2 seconds
                            </div>
                        </div>
                    </div>

                    <!-- Hidden until payment is confirmed -->
                    <a href="order_history.php" class="back-btn" id="backBtn">
                        <i class="fas fa-arrow-left"></i> Back to Order History
                    </a>
                </div>

            </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="js/theme.js"></script>
    <script>
    <?php if ($order && !$already_paid): ?>

    const ORDER_ID  = <?= json_encode($orderid) ?>;
    const CHECK_URL = 'checkpaymentstatus.php?orderid=' + encodeURIComponent(ORDER_ID);
    const PHONE_URL = <?= json_encode($qr_pay_url) ?>;

    let pollTimer = null;
    let isPaid    = false;

    function markPaid() {
        if (isPaid) return;
        isPaid = true;
        clearInterval(pollTimer);

        localStorage.setItem('lastOrderTime', Date.now().toString());
        localStorage.removeItem('cart');
        localStorage.removeItem('cartLastModified');
        sessionStorage.removeItem('appliedVoucher');
        sessionStorage.removeItem('cartTotals');
        sessionStorage.removeItem('orderType');

        document.getElementById('statusIndicator').className = 'status-indicator status-paid';
        document.getElementById('statusIcon').textContent    = '✅';
        document.getElementById('statusLabel').textContent   = 'Payment Successful!';
        document.getElementById('statusSub').textContent     = 'Order confirmed and being prepared.';
        document.getElementById('statusCard').style.background =
            'linear-gradient(135deg,rgba(39,174,96,0.06),rgba(39,174,96,0.02))';

        const db = document.getElementById('demoBar');
        if (db) db.style.display = 'none';

        document.getElementById('backBtn').classList.add('visible');
    }

    function checkStatus() {
        if (isPaid) return;
        fetch(CHECK_URL)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'paid' || data.status === 'preparing' || data.status === 'completed') {
                    markPaid();
                }
            })
            .catch(() => {});
    }

    function simulateScan() {
        const btn = document.getElementById('btnSimulate');
        if (!btn || btn.disabled) return;
        btn.disabled    = true;
        btn.textContent = '⏳ Simulating…';
        fetch(PHONE_URL, { mode: 'no-cors' })
            .catch(() => {})
            .finally(() => { setTimeout(checkStatus, 1500); });
    }

    checkStatus();
    pollTimer = setInterval(checkStatus, 2000);

    <?php elseif ($order && $already_paid): ?>
    document.addEventListener('DOMContentLoaded', function () {
        localStorage.setItem('lastOrderTime', Date.now().toString());
        localStorage.removeItem('cart');
        localStorage.removeItem('cartLastModified');
        sessionStorage.removeItem('appliedVoucher');
        sessionStorage.removeItem('cartTotals');
        sessionStorage.removeItem('orderType');

        document.getElementById('statusIndicator').className = 'status-indicator status-paid';
        document.getElementById('statusIcon').textContent    = '✅';
        document.getElementById('statusLabel').textContent   = 'Payment Successful!';
        document.getElementById('statusSub').textContent     = 'This order has already been paid.';

        const db = document.getElementById('demoBar');
        if (db) db.style.display = 'none';

        document.getElementById('backBtn').classList.add('visible');
    });
    <?php endif; ?>
    </script>
</body>
</html>