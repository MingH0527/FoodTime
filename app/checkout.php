<?php
session_start();
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name = $is_logged_in ? (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '') : '';

if (!$is_logged_in) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FOODTIME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/payment.css">
    <style>
        .checkout-section { padding: 60px 0 80px; min-height: 80vh; }
        .checkout-title { color: var(--primary-color); font-size: 32px; margin: 0 0 40px 0; display: flex; align-items: center; gap: 12px; }
        [data-theme="dark"] .checkout-title { color: var(--accent-color); }
        .payment-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; max-width: 1000px; margin: 0 auto; }
        .payment-card { background: var(--card-bg); border: 2px solid #e0e0e0; border-radius: 14px; padding: 36px 24px 30px; text-align: center; cursor: pointer; transition: all 0.25s ease; position: relative; box-shadow: 0 4px 15px rgba(212,101,15,0.08); }
        [data-theme="dark"] .payment-card { border-color: #444; box-shadow: 0 4px 15px rgba(0,0,0,0.35); }
        .payment-card:hover { border-color: var(--primary-color); transform: translateY(-6px); box-shadow: 0 14px 35px rgba(212,101,15,0.22); }
        .arrow-badge { position: absolute; top: 14px; right: 14px; width: 26px; height: 26px; border-radius: 50%; background: #e0e0e0; color: #888; display: flex; align-items: center; justify-content: center; font-size: 11px; transition: all 0.25s; }
        .payment-card:hover .arrow-badge { background: var(--primary-color); color: white; }
        .payment-icon { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #d4650f 0%, #ff9800 100%); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: white; transition: transform 0.25s ease; }
        .payment-card:hover .payment-icon { transform: scale(1.1); }
        .payment-name { font-size: 18px; font-weight: 700; color: var(--light-text); margin-bottom: 8px; }
        .payment-desc { font-size: 13px; color: var(--light-gray); line-height: 1.5; }
        .order-type-section { max-width: 860px; margin: 0 auto 36px; }
        .section-label { font-size: 16px; font-weight: 600; color: var(--light-text); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .order-type-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .order-type-card { background: var(--card-bg); border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px 24px; cursor: pointer; display: flex; align-items: center; gap: 14px; transition: all 0.25s ease; }
        [data-theme="dark"] .order-type-card { border-color: #444; }
        .order-type-card:hover { border-color: var(--primary-color); }
        .order-type-card.selected { border-color: var(--primary-color); background: linear-gradient(135deg, rgba(212,101,15,0.06) 0%, rgba(255,152,0,0.06) 100%); }
        .order-type-card i { font-size: 22px; color: var(--primary-color); width: 28px; text-align: center; }
        .order-type-card span { font-size: 15px; font-weight: 600; color: var(--light-text); }
        .note-box { max-width: 860px; margin: 18px auto 0; background: rgba(212,101,15,0.07); border-left: 4px solid var(--primary-color); border-radius: 8px; padding: 14px 18px; font-size: 13px; color: var(--light-gray); }
        [data-theme="dark"] .note-box { background: rgba(212,101,15,0.12); }
        .confirm-section { max-width: 860px; margin: 20px auto 0; }
        .btn-back { width: 100%; background: transparent; color: var(--primary-color); padding: 14px; border: 2px solid var(--primary-color); border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 12px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-back:hover { background: var(--primary-color); color: white; }
        .step-header { max-width: 860px; margin: 0 auto 24px; }
        .step-header h2 { font-size: 20px; color: var(--light-text); margin: 0 0 4px; font-weight: 700; }
        .step-header p  { font-size: 14px; color: var(--light-gray); margin: 0; }
        @media (max-width: 640px) { .payment-grid { grid-template-columns: 1fr; } .order-type-grid { grid-template-columns: 1fr; } }
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
                        <span class="user-greeting">&#128075; Hi, <?php echo $user_name; ?></span>
                        <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> <span data-i18n="btn-orders">Orders</span></a>
                        <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> <span data-i18n="btn-cart">Cart</span></a>
                        <a href="logout.php" class="btn btn-logout" data-i18n="btn-logout">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-login"><i class="fas fa-sign-in-alt"></i> <span data-i18n="login">Login</span></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <section class="checkout-section">
        <div class="container">
            <h1 class="checkout-title"><i class="fas fa-credit-card"></i><span data-i18n="checkout-title">Checkout</span></h1>

            <!-- Step 1: Order Type -->
            <div class="order-type-section">
                <div class="section-label">
                    <i class="fas fa-concierge-bell" style="color: var(--primary-color);"></i>
                    <span data-i18n="order-type-label">Order Type</span>
                </div>
                <div class="order-type-grid">
                    <div class="order-type-card selected" id="type-dinein" onclick="selectOrderType('dinein')">
                        <i class="fas fa-utensils"></i>
                        <span data-i18n="dine-in">Dine In</span>
                    </div>
                    <div class="order-type-card" id="type-takeaway" onclick="selectOrderType('takeaway')">
                        <i class="fas fa-bag-shopping"></i>
                        <span data-i18n="takeaway">Takeaway</span>
                    </div>
                </div>
            </div>

            <!-- Step 2: Payment Method -->
            <div class="step-header">
                <h2 data-i18n="choose-payment">Choose Payment Method</h2>
                <p data-i18n="choose-payment-desc">Click a method to continue to payment</p>
            </div>

            <div class="payment-grid">
                <!-- Cash Payment -->
                <div class="payment-card" onclick="goToPayment('cash')">
                    <div class="arrow-badge"><i class="fas fa-arrow-right"></i></div>
                    <div class="payment-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="payment-name" data-i18n="pay-cash">Pay at Counter (Cash)</div>
                    <div class="payment-desc" data-i18n="pay-cash-desc">Pay at the counter with cash when collecting your order</div>
                </div>

                <!-- Credit & Debit Card -->
                <div class="payment-card" onclick="goToPayment('card')">
                    <div class="arrow-badge"><i class="fas fa-arrow-right"></i></div>
                    <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="payment-name" data-i18n="pay-card">Debit / Credit Card</div>
                    <div class="payment-desc" data-i18n="pay-card-desc">Visa, Mastercard & more - Secure payment</div>
                </div>

                <!-- Online Banking -->
                <div class="payment-card" onclick="goToPayment('online')">
                    <div class="arrow-badge"><i class="fas fa-arrow-right"></i></div>
                    <div class="payment-icon"><i class="fas fa-university"></i></div>
                    <div class="payment-name" data-i18n="pay-online">Online Banking</div>
                    <div class="payment-desc" data-i18n="pay-online-desc">Pay securely via FPX or internet banking</div>
                </div>

                <!-- E-Wallet / QR -->
                <div class="payment-card" onclick="goToPayment('ewallet')">
                    <div class="arrow-badge"><i class="fas fa-arrow-right"></i></div>
                    <div class="payment-icon"><i class="fas fa-qrcode"></i></div>
                    <div class="payment-name" data-i18n="pay-ewallet">E-Wallet / QR</div>
                    <div class="payment-desc" data-i18n="pay-ewallet-desc">Touch 'n Go, GrabPay, Boost & more</div>
                </div>
            </div>

            <div class="note-box">
                <i class="fas fa-info-circle" style="color: var(--primary-color);"></i>
                &nbsp;Select your order type above, then click a payment method to proceed.
            </div>

            <div class="confirm-section">
                <button class="btn-back" onclick="window.location.href='cart.php'">
                    <i class="fas fa-arrow-left"></i>
                    <span data-i18n="back-to-cart">Back to Cart</span>
                </button>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section"><h4 data-i18n="about">&#127860; About FOODTIME</h4><a href="#" data-i18n="about-us">About Us</a><a href="#" data-i18n="blog">Blog</a><a href="#" data-i18n="careers">Careers</a></div>
                <div class="footer-section"><h4 data-i18n="support">Support</h4><a href="#" data-i18n="help-center">Help Center</a><a href="#" data-i18n="contact-us">Contact Us</a><a href="#" data-i18n="faq">FAQ</a></div>
                <div class="footer-section"><h4 data-i18n="legal">Legal</h4><a href="#" data-i18n="privacy-policy">Privacy Policy</a><a href="#" data-i18n="terms-conditions">Terms & Conditions</a><a href="#" data-i18n="refund-policy">Refund Policy</a></div>
                <div class="footer-section"><h4 data-i18n="follow-us">Follow Us</h4><div class="social-links"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
            </div>
            <div class="footer-bottom"><p data-i18n="copyright">&copy; 2026 FOODTIME. All rights reserved.</p></div>
        </div>
    </footer>

    <script src="js/translations.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/language.js"></script>
    <script>
        let selectedOrderType = 'dinein';

        function selectOrderType(type) {
            document.querySelectorAll('.order-type-card').forEach(c => c.classList.remove('selected'));
            document.getElementById('type-' + type).classList.add('selected');
            selectedOrderType = type;
        }

        function goToPayment(method) {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            if (cart.length === 0) {
                alert('Your cart is empty!');
                window.location.href = 'cart.php';
                return;
            }
            sessionStorage.setItem('orderType', selectedOrderType);
            window.location.href = 'payment_' + method + '.php';
        }
    </script>
</body>
</html>