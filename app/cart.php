<?php
session_start();
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name    = $is_logged_in ? (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '') : '';

if (!$is_logged_in) {
    header("Location: login.php");
    exit();
}

// ── AJAX: get product prices + names + stock + custom_options ─
if (isset($_GET['action']) && $_GET['action'] == 'get_prices') {
    header('Content-Type: application/json');
    $ids      = isset($_GET['ids']) ? $_GET['ids'] : '';
    $products = [];
    if (!empty($ids)) {
        $ids    = preg_replace('/[^a-zA-Z0-9,]/', '', $ids);
        $idList = "'" . implode("','", explode(',', $ids)) . "'";
        $result = mysqli_query($connect,
            "SELECT productid, price, name, stock, custom_options FROM products WHERE productid IN ($idList)");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $products[$row['productid']] = [
                    'price'          => floatval($row['price']),
                    'name'           => $row['name'],
                    'stock'          => (int)$row['stock'],
                    'custom_options' => $row['custom_options'] ? json_decode($row['custom_options'], true) : null,
                ];
            }
        }
    }
    echo json_encode($products);
    exit();
}

// ── AJAX: check auto voucher from URL ────────────────────────────
if (isset($_GET['action']) && $_GET['action'] == 'check_auto_voucher') {
    header('Content-Type: application/json');
    $code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'No voucher code provided']);
        exit();
    }
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM user_vouchers WHERE code = ? AND userid = ? AND is_used = 0");
    mysqli_stmt_bind_param($stmt, 'ss', $code, $user_id);
    mysqli_stmt_execute($stmt);
    $res     = mysqli_stmt_get_result($stmt);
    $voucher = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$voucher) {
        echo json_encode(['success' => false, 'message' => 'Invalid or used voucher']);
        exit();
    }
    echo json_encode([
        'success'     => true,
        'code'        => $voucher['code'],
        'discount_rm' => number_format((float)$voucher['discount_rm'], 2),
        'voucherid'   => $voucher['voucherid']
    ]);
    exit();
}

// ── AJAX: apply voucher ───────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'apply_voucher') {
    header('Content-Type: application/json');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a voucher code']);
        exit();
    }
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM user_vouchers WHERE code = ? AND userid = ? AND is_used = 0");
    mysqli_stmt_bind_param($stmt, 'ss', $code, $user_id);
    mysqli_stmt_execute($stmt);
    $res     = mysqli_stmt_get_result($stmt);
    $voucher = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$voucher) {
        $stmt2 = mysqli_prepare($connect,
            "SELECT is_used FROM user_vouchers WHERE code = ? AND userid = ?");
        mysqli_stmt_bind_param($stmt2, 'ss', $code, $user_id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        $row2 = mysqli_fetch_assoc($res2);
        mysqli_stmt_close($stmt2);
        if ($row2 && $row2['is_used'] == 1) {
            echo json_encode(['success' => false, 'message' => 'This voucher has already been used']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid voucher code. Please check and try again']);
        }
        exit();
    }
    echo json_encode([
        'success'     => true,
        'code'        => $voucher['code'],
        'discount_rm' => number_format((float)$voucher['discount_rm'], 2),
        'voucherid'   => $voucher['voucherid'],
        'message'     => 'Voucher applied! RM' . number_format((float)$voucher['discount_rm'], 2) . ' discount'
    ]);
    exit();
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
<title>Shopping Cart - FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/index.css">
<!-- Translations MUST load before language.js -->
<script src="js/translations.js"></script>
<style>
/* ========== EXISTING STYLES ========== */
.cart-container { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin: 40px 0; }
.cart-items { background: var(--card-bg); padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(212,101,15,0.12); }
[data-theme="dark"] .cart-items { box-shadow: 0 4px 15px rgba(0,0,0,0.4); }
.cart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid var(--primary-color); padding-bottom: 15px; }
.cart-header h2 { color: var(--primary-color); margin: 0; }
[data-theme="dark"] .cart-header h2 { color: var(--accent-color); }

.product-group { border: 1px solid #e0e0e0; border-radius: 10px; margin-bottom: 18px; overflow: hidden; transition: box-shadow 0.2s; }
.product-group:hover { box-shadow: 0 2px 12px rgba(212,101,15,0.10); }
[data-theme="dark"] .product-group { border-color: #444; }
.product-group-header { display: flex; align-items: center; gap: 14px; padding: 14px 18px 12px; border-bottom: 1px solid #e0e0e0; background: rgba(212,101,15,0.04); }
[data-theme="dark"] .product-group-header { border-bottom-color: #444; background: rgba(212,101,15,0.08); }
.product-group-image { width: 48px; height: 48px; background: linear-gradient(135deg, #d4650f 0%, #ff9800 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.product-group-name { font-size: 16px; font-weight: 700; color: var(--light-text); margin: 0 0 2px; }
.product-group-base { font-size: 12px; color: var(--light-gray); margin: 0; }
.variant-row { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid #f0f0f0; }
.variant-row:last-child { border-bottom: none; }
[data-theme="dark"] .variant-row { border-bottom-color: #3a3a3a; }
.variant-tags { flex: 1; min-width: 0; }

.custom-tags { display: flex; flex-wrap: wrap; gap: 5px; }
.custom-tag { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 20px; }
.tag-original { background: rgba(212,101,15,0.10); color: #d4650f; border: 1px solid rgba(212,101,15,0.30); }
.tag-mild     { background: rgba(255,152,0,0.12); color: #e65c00; border: 1px solid rgba(255,152,0,0.35); }
.tag-spicy    { background: rgba(229,62,62,0.10); color: #c53030; border: 1px solid rgba(229,62,62,0.30); }
.tag-addon    { background: rgba(56,142,60,0.10); color: #2e7d32; border: 1px solid rgba(56,142,60,0.28); }
.tag-remove   { background: rgba(229,62,62,0.08); color: #c53030; border: 1px solid rgba(229,62,62,0.25); }
.tag-serve    { background: rgba(56,142,60,0.10); color: #2e7d32; border: 1px solid rgba(56,142,60,0.28); }
.tag-temp     { background: rgba(2,136,209,0.10); color: #0277bd; border: 1px solid rgba(2,136,209,0.28); }
[data-theme="dark"] .tag-original { background: rgba(212,101,15,0.2); color: #ffb74d; border-color: rgba(212,101,15,0.4); }
[data-theme="dark"] .tag-mild     { background: rgba(255,152,0,0.2); color: #ffcc80; border-color: rgba(255,152,0,0.4); }
[data-theme="dark"] .tag-spicy    { background: rgba(229,62,62,0.2); color: #fc8181; border-color: rgba(229,62,62,0.4); }
[data-theme="dark"] .tag-addon    { background: rgba(56,142,60,0.2); color: #81c784; border-color: rgba(56,142,60,0.4); }
[data-theme="dark"] .tag-remove   { background: rgba(229,62,62,0.2); color: #fca5a5; border-color: rgba(229,62,62,0.4); }
[data-theme="dark"] .tag-serve    { background: rgba(56,142,60,0.2); color: #81c784; border-color: rgba(56,142,60,0.4); }
[data-theme="dark"] .tag-temp     { background: rgba(2,136,209,0.2); color: #81d4fa; border-color: rgba(2,136,209,0.4); }

.stock-badge-cart { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; margin-top: 4px; }
.stock-ok-cart  { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.stock-low-cart { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.stock-out-cart { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
[data-theme="dark"] .stock-ok-cart  { background: rgba(34,197,94,.15); color: #86efac; border-color: rgba(34,197,94,.3); }
[data-theme="dark"] .stock-low-cart { background: rgba(251,191,36,.15); color: #fde68a; border-color: rgba(251,191,36,.3); }
[data-theme="dark"] .stock-out-cart { background: rgba(239,68,68,.15); color: #fca5a5; border-color: rgba(239,68,68,.3); }

/* ── NEW: max-qty badge shown in product group header ── */
.qty-limit-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; margin-top: 4px; background: rgba(212,101,15,0.10); color: #d4650f; border: 1px solid rgba(212,101,15,0.30); }
[data-theme="dark"] .qty-limit-badge { background: rgba(212,101,15,0.2); color: #ffb74d; border-color: rgba(212,101,15,0.4); }

.stock-warning-banner {
    display: none; align-items: center; gap: 12px;
    background: rgba(239,68,68,0.08); border: 1.5px solid rgba(239,68,68,0.3);
    border-radius: 12px; padding: 14px 18px; margin-bottom: 20px;
    font-size: 14px; font-weight: 700; color: #dc2626;
}
.stock-warning-banner i { font-size: 20px; }
.stock-warning-banner.show { display: flex; }

.variant-row.out-of-stock { opacity: 0.6; background: rgba(239,68,68,0.03); }
[data-theme="dark"] .variant-row.out-of-stock { background: rgba(239,68,68,0.08); }

.cart-qty-controls { display: flex; align-items: center; gap: 8px; background: rgba(212,101,15,0.08); border-radius: 30px; padding: 5px 10px; flex-shrink: 0; }
[data-theme="dark"] .cart-qty-controls { background: rgba(212,101,15,0.15); }
.cart-qty-btn { width: 26px; height: 26px; border-radius: 50%; border: 2px solid var(--primary-color,#d4650f); background: transparent; color: var(--primary-color,#d4650f); font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; flex-shrink: 0; }
.cart-qty-btn:hover  { background: var(--primary-color,#d4650f); color: white; transform: scale(1.1); }
.cart-qty-btn:active { transform: scale(0.95); }
.cart-qty-btn:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
.cart-qty-value { font-size: 14px; font-weight: 700; min-width: 22px; text-align: center; color: var(--primary-color,#d4650f); user-select: none; }
.variant-price { text-align: right; font-weight: bold; color: var(--primary-color); font-size: 15px; white-space: nowrap; min-width: 70px; flex-shrink: 0; }
[data-theme="dark"] .variant-price { color: var(--accent-color); }
.btn-remove { background: #e74c3c; color: white; border: none; padding: 7px 10px; border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.3s; flex-shrink: 0; }
.btn-remove:hover { background: #c0392b; transform: scale(1.05); }

.empty-cart { text-align: center; padding: 60px 20px; color: var(--light-gray); }
.empty-cart i { font-size: 80px; margin-bottom: 20px; opacity: 0.3; }
.empty-cart p { font-size: 18px; margin-bottom: 30px; }

.cart-summary { background: var(--card-bg); padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(212,101,15,0.12); height: fit-content; position: sticky; top: 100px; }
[data-theme="dark"] .cart-summary { box-shadow: 0 4px 15px rgba(0,0,0,0.4); }
.summary-title { font-size: 20px; font-weight: bold; color: var(--primary-color); margin-bottom: 20px; border-bottom: 2px solid var(--primary-color); padding-bottom: 10px; }
[data-theme="dark"] .summary-title { color: var(--accent-color); border-bottom-color: var(--accent-color); }
.summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; padding: 10px 0; border-bottom: 1px solid #e0e0e0; color: var(--light-text); }
[data-theme="dark"] .summary-row { border-bottom-color: #444; }
.summary-row.discount-row { color: #16a34a; font-weight: 700; border-bottom-color: #dcfce7; }
[data-theme="dark"] .summary-row.discount-row { border-bottom-color: #14532d; }
.summary-row.total { border-bottom: none; font-size: 18px; font-weight: bold; color: var(--primary-color); padding-top: 15px; border-top: 2px solid var(--primary-color); margin-top: 15px; }
[data-theme="dark"] .summary-row.total { color: var(--accent-color); border-top-color: var(--accent-color); }

.voucher-box { margin: 16px 0; padding: 16px; background: rgba(212,101,15,0.06); border-radius: 12px; border: 1.5px dashed #d4650f; }
[data-theme="dark"] .voucher-box { background: rgba(212,101,15,0.1); }
.voucher-box-title { font-size: 12px; font-weight: 800; color: #d4650f; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.voucher-input-row { display: flex; gap: 8px; }
.voucher-input { flex: 1; padding: 9px 12px; border: 1.5px solid #e4e6eb; border-radius: 8px; font-size: 13px; font-weight: 700; font-family: monospace; text-transform: uppercase; outline: none; background: var(--card-bg); color: var(--light-text); transition: border-color .15s; }
.voucher-input:focus { border-color: #d4650f; }
[data-theme="dark"] .voucher-input { border-color: #555; }
.voucher-apply-btn { padding: 9px 16px; background: #d4650f; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; white-space: nowrap; transition: background .15s; }
.voucher-apply-btn:hover { background: #b8540c; }
.voucher-msg { font-size: 12px; font-weight: 700; margin-top: 8px; display: none; }
.voucher-msg.success { color: #16a34a; }
.voucher-msg.error   { color: #ef4444; }
.voucher-msg.warning { color: #f59e0b; }
.voucher-applied-row { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; display: none; }
.voucher-applied-label { font-size: 12px; font-weight: 800; color: #16a34a; display: flex; align-items: center; gap: 5px; }
.voucher-remove-btn { background: none; border: none; color: #ef4444; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 4px; }
.voucher-link { display: block; text-align: right; font-size: 11px; color: #d4650f; font-weight: 700; text-decoration: none; margin-top: 8px; }
.voucher-link:hover { text-decoration: underline; }

.btn-checkout { width: 100%; background: var(--primary-color); color: white; padding: 15px; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 20px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-checkout:hover { background: #b85509; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212,101,15,0.4); }
.btn-checkout:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
.btn-continue-shopping { width: 100%; background: transparent; color: var(--primary-color); padding: 12px; border: 2px solid var(--primary-color); border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; margin-top: 10px; display: block; text-align: center; text-decoration: none; transition: all 0.3s; }
.btn-continue-shopping:hover { background: var(--primary-color); color: white; }

/* ========== MODAL STYLES ========== */
.voucher-modal {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%; background: rgba(0,0,0,0.6);
    z-index: 10000; align-items: center; justify-content: center;
    backdrop-filter: blur(4px);
}
.voucher-modal.show { display: flex; }
.voucher-modal-content {
    background: var(--card-bg, #fff); border-radius: 24px;
    max-width: 400px; width: 90%; padding: 28px 24px;
    text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}
[data-theme="dark"] .voucher-modal-content { background: #242526; }
@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-30px); }
    to   { opacity: 1; transform: translateY(0); }
}
.voucher-modal-icon {
    width: 70px; height: 70px; border-radius: 50%;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px; font-size: 32px; color: #fff;
}
.voucher-modal-title { font-size: 20px; font-weight: 900; color: var(--text-color, #1f2937); margin-bottom: 12px; }
[data-theme="dark"] .voucher-modal-title { color: #e4e6eb; }
.voucher-modal-message { font-size: 14px; color: #9ca3af; margin-bottom: 24px; line-height: 1.5; }
.voucher-modal-buttons { display: flex; gap: 12px; justify-content: center; }
.voucher-modal-btn { padding: 10px 24px; border-radius: 30px; font-size: 13px; font-weight: 800; cursor: pointer; transition: all 0.2s; border: none; }
.voucher-modal-btn.cancel { background: #e5e7eb; color: #374151; }
.voucher-modal-btn.cancel:hover { background: #d1d5db; }
.voucher-modal-btn.confirm { background: linear-gradient(135deg, #d4650f, #f0874a); color: #fff; }
.voucher-modal-btn.confirm:hover { transform: scale(1.02); filter: brightness(1.05); }

.voucher-toast {
    position: fixed; bottom: 30px; left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: #1e293b; color: white; padding: 14px 24px;
    border-radius: 50px; font-size: 14px; font-weight: 700;
    z-index: 10001; display: flex; align-items: center; gap: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2); transition: transform 0.3s ease;
    pointer-events: none;
}
.voucher-toast.show { transform: translateX(-50%) translateY(0); }
.voucher-toast.success { background: #16a34a; }
.voucher-toast.error   { background: #dc2626; }
.voucher-toast.warning { background: #f59e0b; }

@media (max-width: 768px) {
    .cart-container { grid-template-columns: 1fr; }
    .variant-row { flex-wrap: wrap; gap: 8px; }
    .cart-summary { position: static; }
}
</style>
</head>
<body>

<!-- ========== CONFIRMATION MODAL ========== -->
<div class="voucher-modal" id="voucherModal">
    <div class="voucher-modal-content">
        <div class="voucher-modal-icon">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="voucher-modal-title">Change Voucher?</div>
        <div class="voucher-modal-message" id="voucherModalMessage">
            You already have a voucher applied. Do you want to replace it with the new one?
        </div>
        <div class="voucher-modal-buttons">
            <button class="voucher-modal-btn cancel" id="modalCancelBtn">Cancel</button>
            <button class="voucher-modal-btn confirm" id="modalConfirmBtn">Replace Voucher</button>
        </div>
    </div>
</div>

<header>
    <div class="container">
        <div class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-burger"></i>
                <span>FOODTIME</span>
            </a>
            <ul class="nav-menu">
                <li><a href="index.php#categories" data-i18n="nav-category">Category</a></li>
                <li><a href="menu.php" data-i18n="nav-menu">Menu</a></li>
                <li><a href="index.php#about" data-i18n="nav-about">About</a></li>
            </ul>
            <div class="auth-buttons">
                <?php if ($is_logged_in): ?>
                    <span class="user-greeting">👋 Hi, <?php echo htmlspecialchars($user_name); ?></span>
                    <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> <span data-i18n="btn-orders">Orders</span></a>
                    <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> <span data-i18n="btn-cart">Cart</span></a>
                    <a href="logout.php" class="btn btn-logout" data-i18n="btn-logout">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-login" data-i18n="login">Login</a>
                    <a href="register.php" class="btn btn-signup" data-i18n="sign-up">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<section class="cart-section">
    <div class="container">
        <h1 style="color:var(--primary-color);margin:40px 0 20px;">
            <i class="fas fa-shopping-cart"></i> <span data-i18n="cart-title">Shopping Cart</span>
        </h1>

        <div class="stock-warning-banner" id="stockWarningBanner">
            <i class="fas fa-exclamation-triangle"></i>
            <span data-i18n="cart-stock-warning">Some items in your cart are out of stock or exceed available quantity. Please remove them before checking out.</span>
        </div>

        <div class="cart-container">
            <div class="cart-items">
                <div class="cart-header">
                    <h2 data-i18n="cart-your-items">Your Items</h2>
                    <span id="itemCount">0 items</span>
                </div>
                <div id="cartItemsContainer">
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p data-i18n="cart-empty-msg">Your cart is empty</p>
                        <a href="index.php#categories" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> <span data-i18n="cart-continue-shopping">Continue Shopping</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="cart-summary">
                <div class="summary-title" data-i18n="cart-order-summary">Order Summary</div>

                <div class="summary-row">
                    <span data-i18n="cart-subtotal">Subtotal:</span>
                    <span id="subtotal">RM 0.00</span>
                </div>

                <div class="voucher-box">
                    <div class="voucher-box-title">
                        <i class="fas fa-ticket"></i>
                        <span data-i18n="cart-voucher-title">Have a voucher code?</span>
                    </div>
                    <div class="voucher-input-row">
                        <input type="text" id="voucherInput" class="voucher-input"
                               data-i18n-placeholder="cart-voucher-placeholder"
                               placeholder="e.g. FTAB12CD"
                               oninput="this.value=this.value.toUpperCase()"
                               onkeydown="if(event.key==='Enter') applyVoucherWithConfirm()">
                        <button class="voucher-apply-btn" onclick="applyVoucherWithConfirm()" data-i18n="cart-voucher-apply-btn">Apply</button>
                    </div>
                    <div class="voucher-msg" id="voucherMsg"></div>
                    <div class="voucher-applied-row" id="voucherAppliedRow">
                        <div class="voucher-applied-label">
                            <i class="fas fa-check-circle"></i>
                            <span data-i18n="cart-voucher-applied">Voucher applied!</span>
                        </div>
                        <button class="voucher-remove-btn" onclick="removeVoucher()">
                            <i class="fas fa-times"></i>
                            <span data-i18n="cart-voucher-remove">Remove</span>
                        </button>
                    </div>
                    <a href="membership.php" class="voucher-link">
                        <i class="fas fa-star"></i>
                        <span data-i18n="cart-voucher-get-link">Get vouchers from My Points →</span>
                    </a>
                </div>

                <div class="summary-row discount-row" id="discountRow" style="display:none;">
                    <span><i class="fas fa-tag"></i> <span data-i18n="cart-discount">Discount:</span></span>
                    <span id="discountAmt">- RM 0.00</span>
                </div>
                <div class="summary-row">
                    <span data-i18n="cart-service-tax">Service Tax (6%):</span>
                    <span id="tax">RM 0.00</span>
                </div>
                <div class="summary-row total">
                    <span data-i18n="cart-total">Total:</span>
                    <span id="totalPrice">RM 0.00</span>
                </div>

                <button class="btn-checkout" id="checkoutBtn" onclick="proceedToCheckout()">
                    <i class="fas fa-credit-card"></i>
                    <span data-i18n="cart-proceed-checkout">Proceed to Checkout</span>
                </button>
                <a href="index.php#categories" class="btn-continue-shopping">
                    <i class="fas fa-arrow-left"></i>
                    <span data-i18n="cart-continue-shopping">Continue Shopping</span>
                </a>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section"><h4>🍴 FOODTIME</h4><a href="#" data-i18n="about-us">About Us</a><a href="#" data-i18n="blog">Blog</a><a href="#" data-i18n="careers">Careers</a></div>
            <div class="footer-section"><h4 data-i18n="support">Support</h4><a href="#" data-i18n="help-center">Help Center</a><a href="#" data-i18n="contact-us">Contact Us</a><a href="#" data-i18n="faq">FAQ</a></div>
            <div class="footer-section"><h4 data-i18n="follow-us">Follow Us</h4><div class="social-links"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
        </div>
        <div class="footer-bottom"><p data-i18n="copyright">© 2026 FOODTIME. All rights reserved.</p></div>
    </div>
</footer>

<script src="js/theme.js"></script>
<script src="js/language.js"></script>
<script>
// ========== TRANSLATION HELPER ==========
function t(key, fallback) {
    var lang = localStorage.getItem('language') || 'en';
    var map  = (typeof translations !== 'undefined')
               ? (translations[lang] || translations['en'] || {})
               : {};
    return map[key] !== undefined ? map[key] : (fallback || key);
}

function applyPlaceholders() {
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function(el) {
        var key = el.getAttribute('data-i18n-placeholder');
        el.placeholder = t(key, el.placeholder);
    });
}

// ========== CART VARIABLES ==========
const TAX_RATE = 0.06;
const MAX_QTY  = 50;   // ← TOTAL across ALL variants of the same product, not per-variant
let productData    = {};
let appliedVoucher = null;
let hasStockIssues = false;

// ========== HELPER FUNCTIONS ==========

/**
 * Returns the total quantity across ALL cart entries that share the same product id.
 */
function getTotalQtyForProduct(cart, productId) {
    return cart.reduce((sum, c) => sum + (parseInt(c.quantity) || 1), 0);
    // Wait — we only want entries matching productId:
}
// Corrected version used everywhere:
function getProductTotalQty(cart, productId) {
    return cart.reduce((sum, c) => (c.id === productId ? sum + (parseInt(c.quantity) || 1) : sum), 0);
}

/**
 * Returns how many more units THIS variant can accept.
 * Rule 1: total qty for the product must not exceed MAX_QTY (50)
 * Rule 2: total qty must not exceed stock
 * The per-variant ceiling is whichever is lower, minus what other variants already hold.
 */
function maxForVariant(cart, index) {
    const item            = cart[index];
    const dbEntry         = productData[item.id];
    const stock           = (dbEntry && dbEntry.stock !== undefined) ? parseInt(dbEntry.stock) : 9999;
    const currentQty      = parseInt(item.quantity) || 1;

    // Sum of ALL other variants of the same product
    const otherVariantsQty = cart.reduce((sum, c, i) => {
        return (i !== index && c.id === item.id) ? sum + (parseInt(c.quantity) || 1) : sum;
    }, 0);

    // Ceiling = the smaller of (MAX_QTY) and (stock), minus what other variants already hold
    const ceiling = Math.min(MAX_QTY, stock) - otherVariantsQty;
    return Math.max(1, ceiling); // always allow at least 1 (so the item can exist)
}

function getStockBadge(stock, totalQtyForProduct) {
    if (stock <= 0)
        return '<span class="stock-badge-cart stock-out-cart"><i class="fas fa-ban"></i> ' + t('cart-stock-out', 'Out of Stock') + '</span>';
    if (totalQtyForProduct > stock)
        return '<span class="stock-badge-cart stock-out-cart"><i class="fas fa-exclamation-triangle"></i> ' +
               t('cart-stock-exceed', 'Only {n} left (cart has {q})').replace('{n}', stock).replace('{q}', totalQtyForProduct) + '</span>';
    if (stock <= 10)
        return '<span class="stock-badge-cart stock-low-cart"><i class="fas fa-exclamation-triangle"></i> ' +
               t('cart-stock-low', 'Only {n} left').replace('{n}', stock) + '</span>';
    return '<span class="stock-badge-cart stock-ok-cart"><i class="fas fa-check"></i> ' + t('cart-stock-in', 'In Stock') + '</span>';
}

function getItemTotal(item) {
    const qty       = Math.min(MAX_QTY, Math.max(1, parseInt(item.quantity) || 1));
    const unitPrice = resolveUnitPrice(item);
    return unitPrice * qty;
}

function resolveUnitPrice(item) {
    const stored = parseFloat(item.price);
    if (!isNaN(stored) && stored > 0) return stored;
    return parseFloat((productData[item.id] || {}).price) || 0;
}

function buildCustomTags(item) {
    let tags = '';
    if (item.bev_temp) {
        const m = {
            ice:      t('tag-ice',      '🧊 Ice'),
            less_ice: t('tag-less-ice', '🧊 Less Ice'),
            no_ice:   t('tag-no-ice',   '🚫 No Ice'),
            hot:      t('tag-hot',      '🔥 Hot')
        };
        tags += `<span class="custom-tag tag-temp">${m[item.bev_temp] || item.bev_temp}</span>`;
    } else if (item.serve_time) {
        const label = item.serve_time === 'before_meal'
            ? t('tag-before-meal', '🍽 Before Meal')
            : t('tag-after-meal',  '🍽 After Meal');
        tags += `<span class="custom-tag tag-serve">${label}</span>`;
    } else if (item.spice_level) {
        if      (item.spice_level === 'original') tags += `<span class="custom-tag tag-original">${t('tag-original','Original')}</span>`;
        else if (item.spice_level === 'mild')     tags += `<span class="custom-tag tag-mild">${t('tag-mild','🌶 Mild')}</span>`;
        else if (item.spice_level === 'spicy')    tags += `<span class="custom-tag tag-spicy">${t('tag-spicy','🌶🌶 Spicy')}</span>`;
    }
    if (item.removes && typeof item.removes === 'object') {
        Object.entries(item.removes).forEach(([key, active]) => {
            if (active) {
                const label = key.replace(/^no_/,'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
                tags += `<span class="custom-tag tag-remove">${t('tag-no-prefix','🚫 No ')}${label}</span>`;
            }
        });
    }
    if (item.addons && typeof item.addons === 'object') {
        const L = {
            add_rice:'🍚 +Rice', add_sauce:'🥫 +Sauce', add_meat:'🥩 +Meat', add_egg:'🥚 +Egg',
            add_prawn:'🦐 +Prawn', add_cheese:'🧀 +Cheese', add_tofu:'🟨 +Tofu',
            add_mushroom:'🍄 +Mushroom', add_bread:'🍞 +Bread', add_cream:'🍦 +Ice Cream',
            add_peanut:'🥜 +Peanut', add_extra:'➕ +Extra', add_salad:'🥗 +Salad',
            add_sugar:'🍬 +Sugar', add_milk:'🥛 +Milk', add_shot:'☕ +Shot',
            add_pancake:'🫓 +Pancake', add_onion:'🧅 +Onion', add_nuts:'🌰 +Nuts',
            add_coriander:'🌿 +Coriander'
        };
        Object.entries(item.addons).forEach(([key, active]) => {
            if (active) tags += `<span class="custom-tag tag-addon">${L[key] || '+ '+key.replace('add_','').replace(/_/g,' ')}</span>`;
        });
    }
    return tags ? `<div class="custom-tags">${tags}</div>` : '';
}

function groupCartByProduct(cart) {
    const map = {}, order = [];
    cart.forEach((item, index) => {
        if (!map[item.id]) { map[item.id] = { id: item.id, items: [] }; order.push(item.id); }
        map[item.id].items.push({ item, originalIndex: index });
    });
    return order.map(id => map[id]);
}

function loadCart() {
    const cart      = JSON.parse(localStorage.getItem('cart')) || [];
    const container = document.getElementById('cartItemsContainer');

    if (cart.length === 0) {
        container.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>${t('cart-empty-msg','Your cart is empty')}</p>
                <a href="index.php#categories" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> ${t('cart-continue-shopping','Continue Shopping')}
                </a>
            </div>`;
        document.getElementById('itemCount').textContent = '0 items';
        document.getElementById('stockWarningBanner').classList.remove('show');
        updateSummary(cart);
        return;
    }

    fetchProductData(cart, function() {
        hasStockIssues = false;
        const groups = groupCartByProduct(cart);
        let html = '';

        groups.forEach(group => {
            const dbData    = productData[group.id] || {};
            const name      = dbData.name || group.items[0].item.name || ('Product #' + group.id);
            const basePrice = parseFloat(group.items[0].item.basePrice) || parseFloat(dbData.price) || 0;
            const stock     = (dbData.stock !== undefined) ? parseInt(dbData.stock) : 9999;

            // ── Total qty across ALL variants of this product ──
            const totalQtyThisProduct = getProductTotalQty(cart, group.id);

            // ── Cap total qty at MAX_QTY (50) if needed ──
            // Stock badge is shown once per product group header
            const groupStockBadge = getStockBadge(stock, totalQtyThisProduct);

            // ── Show a friendly "max 50 per product" badge when close to or at the limit ──
            const atMaxQty = totalQtyThisProduct >= Math.min(MAX_QTY, stock);
            const maxQtyBadge = atMaxQty
                ? `<span class="qty-limit-badge"><i class="fas fa-lock"></i> Max ${MAX_QTY} per product reached</span>`
                : (totalQtyThisProduct >= Math.min(MAX_QTY, stock) - 5
                    ? `<span class="qty-limit-badge"><i class="fas fa-exclamation"></i> ${Math.min(MAX_QTY, stock) - totalQtyThisProduct} more allowed</span>`
                    : '');

            html += `
                <div class="product-group">
                    <div class="product-group-header">
                        <div class="product-group-image"><i class="fas fa-utensils" style="color:white;"></i></div>
                        <div>
                            <p class="product-group-name">${escapeHtml(name)}</p>
                            <p class="product-group-base">${t('cart-base-price','Base:')} RM ${basePrice.toFixed(2)}</p>
                            ${groupStockBadge}
                            ${maxQtyBadge}
                        </div>
                    </div>`;

            group.items.forEach(({ item, originalIndex }) => {
                const qty       = Math.min(MAX_QTY, Math.max(1, parseInt(item.quantity) || 1));
                const unitPrice = resolveUnitPrice(item);
                const itemTotal = unitPrice * qty;

                // ── Out of stock: product stock is 0 OR total variants exceed available stock ──
                const isOutOfStock = stock <= 0 || totalQtyThisProduct > stock;

                // ── Max this specific variant can go UP to ──
                // = min(MAX_QTY, stock) minus all OTHER variants' qty
                const otherVariantsQty = totalQtyThisProduct - qty;
                // FIX: ceiling is min(MAX_QTY, stock); this variant gets what's left after others
                const maxAllowed = Math.min(MAX_QTY, stock) - otherVariantsQty;

                if (isOutOfStock) hasStockIssues = true;

                html += `
                    <div class="variant-row${isOutOfStock ? ' out-of-stock' : ''}">
                        <div class="variant-tags">
                            ${buildCustomTags(item)}
                        </div>
                        <div class="cart-qty-controls">
                            <button class="cart-qty-btn" onclick="changeCartQty(${originalIndex},-1)"
                                    ${qty <= 1 ? 'disabled' : ''}>
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="cart-qty-value">${qty}</span>
                            <button class="cart-qty-btn" onclick="changeCartQty(${originalIndex},1)"
                                    ${qty >= maxAllowed ? 'disabled' : ''}>
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="variant-price">RM ${itemTotal.toFixed(2)}</div>
                        <button class="btn-remove" onclick="removeFromCart(${originalIndex})" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>`;
            });
            html += `</div>`;
        });

        container.innerHTML = html;
        document.getElementById('itemCount').textContent = `${cart.length} item${cart.length !== 1 ? 's' : ''}`;

        if (hasStockIssues) {
            document.getElementById('stockWarningBanner').classList.add('show');
        } else {
            document.getElementById('stockWarningBanner').classList.remove('show');
        }

        updateSummary(cart);
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

/**
 * changeCartQty — enforces:
 *   1. Total qty across ALL variants of same product ≤ MAX_QTY (50)
 *   2. Total qty across ALL variants of same product ≤ stock
 */
function changeCartQty(index, delta) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    const item    = cart[index];
    const dbEntry = productData[item.id];
    const stock   = (dbEntry && dbEntry.stock !== undefined) ? parseInt(dbEntry.stock) : 9999;

    // Sum qty of ALL OTHER variants of the same product
    const otherVariantsQty = cart.reduce((sum, c, i) => {
        return (i !== index && c.id === item.id) ? sum + (parseInt(c.quantity) || 1) : sum;
    }, 0);

    // ── KEY FIX ──
    // The combined ceiling for ALL variants of this product is min(MAX_QTY, stock).
    // This variant's ceiling = that combined ceiling minus what other variants already hold.
    const combinedCeiling   = Math.min(MAX_QTY, stock);
    const maxForThisVariant = Math.max(1, combinedCeiling - otherVariantsQty);

    const currentQty = parseInt(item.quantity) || 1;
    cart[index].quantity = Math.min(maxForThisVariant, Math.max(1, currentQty + delta));
    localStorage.setItem('cart', JSON.stringify(cart));
    loadCart();
}

function fetchProductData(cart, callback) {
    const ids = [...new Set(cart.map(i => i.id))].join(',');
    fetch('cart.php?action=get_prices&ids=' + ids)
        .then(r => r.json())
        .then(data => { productData = data; callback(); })
        .catch(() => {
            cart.forEach(item => {
                if (!productData[item.id]) productData[item.id] = {
                    price: parseFloat(item.basePrice) || parseFloat(item.price) || 0,
                    name:  item.name || ('Product #' + item.id),
                    stock: 9999,
                    custom_options: null
                };
            });
            callback();
        });
}

function removeFromCart(index) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart.splice(index, 1);
    localStorage.setItem('cart', JSON.stringify(cart));
    loadCart();
    showToastMessage(t('cart-toast-removed', 'Item removed from cart'));
}

function updateSummary(cart) {
    cart = cart || JSON.parse(localStorage.getItem('cart')) || [];

    if (cart.length > 0 && Object.keys(productData).length === 0) return;

    // 1. Subtotal
    let subtotal = 0;
    cart.forEach(item => { subtotal += getItemTotal(item); });

    // 2. Tax on FULL subtotal (before voucher discount)
    const tax = subtotal * TAX_RATE;

    // 3. Discount — cap so it never exceeds subtotal
    const discountRaw = appliedVoucher ? parseFloat(appliedVoucher.discount_rm) : 0;
    const discount    = Math.min(discountRaw, subtotal);

    // 4. Total = subtotal + tax - discount
    const total = subtotal + tax - discount;

    document.getElementById('subtotal').textContent   = `RM ${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent        = `RM ${tax.toFixed(2)}`;
    document.getElementById('totalPrice').textContent = `RM ${total.toFixed(2)}`;

    if (discount > 0) {
        document.getElementById('discountRow').style.display = 'flex';
        document.getElementById('discountAmt').textContent   = `- RM ${discount.toFixed(2)}`;
    } else {
        document.getElementById('discountRow').style.display = 'none';
    }

    const btn = document.getElementById('checkoutBtn');
    if (cart.length === 0) {
        btn.disabled = true; btn.style.opacity = '0.5';
        btn.innerHTML = '<i class="fas fa-credit-card"></i> <span>' + t('cart-proceed-checkout','Proceed to Checkout') + '</span>';
    } else if (hasStockIssues) {
        btn.disabled = true; btn.style.opacity = '0.5';
        btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + t('cart-checkout-stock-error','Cannot Checkout — Fix Stock Issues');
    } else {
        btn.disabled = false; btn.style.opacity = '1';
        btn.innerHTML = '<i class="fas fa-credit-card"></i> <span>' + t('cart-proceed-checkout','Proceed to Checkout') + '</span>';
    }

    // ── Persist totals for checkout page ──
    const orderTotals = {
        subtotal: subtotal.toFixed(2),
        discount: discount.toFixed(2),
        tax:      tax.toFixed(2),
        total:    total.toFixed(2)
    };

    if (appliedVoucher) {
        sessionStorage.setItem('appliedVoucher', JSON.stringify({ ...appliedVoucher, ...orderTotals }));
    } else {
        sessionStorage.removeItem('appliedVoucher');
    }
    sessionStorage.setItem('cartTotals', JSON.stringify(orderTotals));
}

// ========== VOUCHER FUNCTIONS WITH MODAL ==========
function showVoucherModal(message, onConfirm) {
    const modal      = document.getElementById('voucherModal');
    const modalMsg   = document.getElementById('voucherModalMessage');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn  = document.getElementById('modalCancelBtn');

    modalMsg.textContent = message;

    const newConfirmBtn = confirmBtn.cloneNode(true);
    const newCancelBtn  = cancelBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

    newConfirmBtn.onclick = function() { closeVoucherModal(); if (onConfirm) onConfirm(); };
    newCancelBtn.onclick  = function() { closeVoucherModal(); };

    modal.classList.add('show');
}

function closeVoucherModal() {
    document.getElementById('voucherModal').classList.remove('show');
}

function applyVoucherWithConfirm() {
    const code = document.getElementById('voucherInput').value.trim().toUpperCase();
    if (!code) {
        showVoucherMessage('Please enter a voucher code', 'error');
        return;
    }
    if (appliedVoucher) {
        showVoucherModal(
            `You already have a voucher (${appliedVoucher.code}) with RM${appliedVoucher.discount_rm} off applied. Do you want to replace it with the new one?`,
            function() { applyVoucherAPI(code); }
        );
    } else {
        applyVoucherAPI(code);
    }
}

function applyVoucherAPI(code) {
    showVoucherMessage('Checking voucher...', '');
    const fd = new FormData();
    fd.append('action', 'apply_voucher');
    fd.append('code', code);
    fetch('cart.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                appliedVoucher = { code: data.code, discount_rm: data.discount_rm, voucherid: data.voucherid };
                sessionStorage.setItem('appliedVoucher', JSON.stringify(appliedVoucher));
                showVoucherMessage('✅ ' + data.message, 'success');
                document.getElementById('voucherInput').disabled = true;
                document.getElementById('voucherAppliedRow').style.display = 'flex';
                updateSummary();
                showToastMessage('Voucher applied! RM' + data.discount_rm + ' off');
            } else {
                appliedVoucher = null;
                sessionStorage.removeItem('appliedVoucher');
                showVoucherMessage('❌ ' + data.message, 'error');
                document.getElementById('voucherAppliedRow').style.display = 'none';
                updateSummary();
            }
        })
        .catch(() => showVoucherMessage('Network error. Please try again.', 'error'));
}

function removeVoucher() {
    appliedVoucher = null;
    sessionStorage.removeItem('appliedVoucher');
    document.getElementById('voucherInput').value    = '';
    document.getElementById('voucherInput').disabled = false;
    document.getElementById('voucherMsg').style.display         = 'none';
    document.getElementById('voucherAppliedRow').style.display  = 'none';
    document.getElementById('discountRow').style.display        = 'none';
    updateSummary();
    showToastMessage(t('cart-toast-voucher-remove', 'Voucher removed'));
}

function showVoucherMessage(msg, type) {
    const el = document.getElementById('voucherMsg');
    el.style.display = msg ? 'block' : 'none';
    el.className     = 'voucher-msg ' + type;
    el.textContent   = msg;
    if (msg) {
        setTimeout(() => {
            if (el.textContent === msg) el.style.display = 'none';
        }, 3000);
    }
}

function showToastMessage(message) {
    const existing = document.querySelector('.voucher-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'voucher-toast success';
    toast.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

function proceedToCheckout() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    if (cart.length === 0)  { showToastMessage(t('cart-toast-empty', 'Please add items to cart first')); return; }
    if (hasStockIssues)     { showToastMessage(t('cart-toast-stock-error', 'Cannot checkout: remove or reduce out-of-stock items first')); return; }
    window.location.href = 'checkout.php';
}

function applyAutoVoucher(code) {
    fetch('cart.php?action=check_auto_voucher&code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                appliedVoucher = { code: data.code, discount_rm: data.discount_rm, voucherid: data.voucherid };
                sessionStorage.setItem('appliedVoucher', JSON.stringify(appliedVoucher));
                document.getElementById('voucherInput').value    = data.code;
                document.getElementById('voucherInput').disabled = true;
                document.getElementById('voucherAppliedRow').style.display = 'flex';
                showVoucherMessage('✅ Voucher RM' + data.discount_rm + ' off applied!', 'success');
                updateSummary();
                showToastMessage('Voucher applied! RM' + data.discount_rm + ' off');
                window.history.replaceState({}, document.title, window.location.pathname);
            } else {
                showVoucherMessage('❌ ' + data.message, 'error');
            }
        })
        .catch(() => showVoucherMessage('Network error. Please try again.', 'error'));
}

function loadAutoVoucher() {
    const urlParams = new URLSearchParams(window.location.search);
    let autoCode = urlParams.get('auto_voucher');

    if (!autoCode) {
        const stored = sessionStorage.getItem('auto_voucher');
        if (stored) {
            try {
                const v = JSON.parse(stored);
                autoCode = v.code;
                sessionStorage.removeItem('auto_voucher');
            } catch(e) {}
        }
    }

    if (!autoCode) return;

    if (urlParams.get('auto_voucher')) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (appliedVoucher && appliedVoucher.code === autoCode.toUpperCase()) return;

    if (appliedVoucher) {
        showVoucherModal(
            `You already have a voucher (${appliedVoucher.code}) with RM${appliedVoucher.discount_rm} off applied. Do you want to replace it with the new one?`,
            function() { applyAutoVoucher(autoCode); }
        );
    } else {
        applyAutoVoucher(autoCode);
    }
}

// ========== PAGE INITIALIZATION ==========
document.addEventListener('DOMContentLoaded', function() {
    applyPlaceholders();

    const cart = JSON.parse(localStorage.getItem('cart')) || [];

    if (cart.length > 0) {
        const saved = sessionStorage.getItem('appliedVoucher');
        if (saved) {
            try {
                const v = JSON.parse(saved);
                appliedVoucher = v;
                document.getElementById('voucherInput').value    = v.code;
                document.getElementById('voucherInput').disabled = true;
                document.getElementById('voucherAppliedRow').style.display = 'flex';
                showVoucherMessage('✅ Voucher RM' + v.discount_rm + ' off applied', 'success');
            } catch(e) { sessionStorage.removeItem('appliedVoucher'); }
        }
    }

    loadCart();
    loadAutoVoucher();

    const modal = document.getElementById('voucherModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeVoucherModal();
        });
    }
});
</script>
</body>
</html>