<?php
session_start();
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name    = $is_logged_in ? (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '') : '';
$user_email   = $is_logged_in ? (isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '') : '';

if (!$is_logged_in) { header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Payment - FOODTIME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/payment.css">
    <style>
        .pay-section { padding: 60px 0 80px; min-height: 80vh; }
        .pay-wrapper { max-width: 620px; margin: 0 auto; }
        .pay-title { color: var(--primary-color); font-size: 28px; margin: 0 0 30px; display: flex; align-items: center; gap: 12px; }
        [data-theme="dark"] .pay-title { color: var(--accent-color); }

        /* Order Summary Card */
        .summary-card { background: var(--card-bg); border: 2px solid #e0e0e0; border-radius: 14px; padding: 28px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(212,101,15,0.08); }
        [data-theme="dark"] .summary-card { border-color: #444; box-shadow: 0 4px 15px rgba(0,0,0,0.35); }
        .summary-card h3 { margin: 0 0 18px; font-size: 16px; color: var(--light-text); display: flex; align-items: center; gap: 8px; }
        .summary-card h3 i { color: var(--primary-color); }
        .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; font-size: 14px; color: var(--light-text); }
        [data-theme="dark"] .summary-row { border-bottom-color: #444; }
        .summary-row:last-child { border-bottom: none; }
        .summary-row.total { font-size: 18px; font-weight: 700; color: var(--primary-color); padding-top: 14px; border-top: 2px solid var(--primary-color); margin-top: 10px; }
        [data-theme="dark"] .summary-row.total { color: var(--accent-color); border-top-color: var(--accent-color); }
        .summary-row.discount { color: #16a34a; font-weight: 600; }

        /* Stripe redirect box */
        .stripe-info-box { background: linear-gradient(135deg, rgba(99,91,255,0.07) 0%, rgba(0,163,255,0.07) 100%); border: 2px solid #635bff33; border-radius: 14px; padding: 28px; margin-bottom: 24px; text-align: center; }
        .stripe-logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 16px; }
        .stripe-logo img { height: 36px; }
        .stripe-logo span { font-size: 22px; font-weight: 800; color: #635bff; }
        .stripe-desc { font-size: 14px; color: var(--light-gray); line-height: 1.7; margin-bottom: 20px; }
        .stripe-badges { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
        .stripe-badge { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--light-gray); background: var(--card-bg); border: 1px solid #ddd; border-radius: 20px; padding: 5px 12px; }
        [data-theme="dark"] .stripe-badge { border-color: #555; }
        .stripe-badge i { color: var(--primary-color); }

        /* Pay button */
        .btn-pay { width: 100%; background: linear-gradient(135deg, #635bff 0%, #0073e6 100%); color: white; padding: 16px; border: none; border-radius: 10px; font-size: 17px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s; letter-spacing: 0.3px; }
        .btn-pay:hover { opacity: 0.92; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(99,91,255,0.35); }
        .btn-pay:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-back { width: 100%; background: transparent; color: var(--primary-color); padding: 13px; border: 2px solid var(--primary-color); border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 12px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-back:hover { background: var(--primary-color); color: white; }

        .secure-note { text-align: center; font-size: 12px; color: var(--light-gray); margin-top: 14px; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .secure-note i { color: #16a34a; }

        #cartEmpty { background: rgba(212,101,15,0.08); border: 2px dashed var(--primary-color); border-radius: 12px; padding: 40px; text-align: center; color: var(--light-gray); display: none; }
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
                        <span class="user-greeting">&#128075; Hi, <?php echo $user_name; ?></span>
                        <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> Orders</a>
                        <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Cart</a>
                        <a href="logout.php" class="btn btn-logout">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <section class="pay-section">
        <div class="container">
            <div class="pay-wrapper">
                <h1 class="pay-title"><i class="fas fa-credit-card"></i> Card Payment</h1>

                <!-- Empty cart fallback -->
                <div id="cartEmpty">
                    <i class="fas fa-shopping-cart" style="font-size:40px;color:var(--primary-color);margin-bottom:14px;display:block;"></i>
                    <p>Your cart is empty. <a href="menu.php">Go back to menu</a>.</p>
                </div>

                <!-- Order Summary -->
                <div class="summary-card" id="summaryCard">
                    <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                    <div id="summaryItems"></div>
                    <div id="summaryTotals"></div>
                </div>

                <!-- Stripe Info -->
                <div class="stripe-info-box">
                    <div class="stripe-logo">
                        <i class="fas fa-shield-alt" style="font-size:28px;color:#635bff;"></i>
                        <span>Stripe Checkout</span>
                    </div>
                    <p class="stripe-desc">
                        You'll be securely redirected to Stripe's hosted payment page to complete your payment.
                        Your card details are <strong>never stored</strong> on our servers.
                    </p>
                    <div class="stripe-badges">
                        <span class="stripe-badge"><i class="fas fa-lock"></i> SSL Encrypted</span>
                        <span class="stripe-badge"><i class="fab fa-cc-visa"></i> Visa</span>
                        <span class="stripe-badge"><i class="fab fa-cc-mastercard"></i> Mastercard</span>
                        <span class="stripe-badge"><i class="fas fa-shield-alt"></i> PCI Compliant</span>
                    </div>
                    <button class="btn-pay" id="btnPay" onclick="redirectToStripe()">
                        <i class="fas fa-lock"></i>
                        <span id="btnPayText">Pay Securely with Stripe</span>
                    </button>
                    <p class="secure-note"><i class="fas fa-check-circle"></i> Powered by Stripe — trusted by millions worldwide</p>
                </div>

                <button class="btn-back" onclick="window.location.href='checkout.php'">
                    <i class="fas fa-arrow-left"></i> Back to Checkout
                </button>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section"><h4>&#127860; About FOODTIME</h4><a href="#">About Us</a><a href="#">Blog</a></div>
                <div class="footer-section"><h4>Support</h4><a href="#">Help Center</a><a href="#">Contact Us</a></div>
                <div class="footer-section"><h4>Follow Us</h4><div class="social-links"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
            </div>
            <div class="footer-bottom"><p>&copy; 2026 FOODTIME. All rights reserved.</p></div>
        </div>
    </footer>

    <script src="js/translations.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/language.js"></script>
    <script>
        const TAX           = 0.06;
        const cart          = JSON.parse(localStorage.getItem('cart')) || [];
        const orderType     = sessionStorage.getItem('orderType') || 'dinein';
        const appliedVoucher = (() => {
            try { return JSON.parse(sessionStorage.getItem('appliedVoucher')) || null; } catch(e) { return null; }
        })();

        // ── Render summary ────────────────────────────────────────────────
        if (cart.length === 0) {
            document.getElementById('cartEmpty').style.display  = 'block';
            document.getElementById('summaryCard').style.display = 'none';
            document.getElementById('btnPay').disabled = true;
        } else {
            let subtotal = 0;
            let itemsHtml = '';
            cart.forEach(item => {
                const qty   = parseInt(item.quantity) || 1;
                const price = parseFloat(item.price) || 0;
                subtotal   += price * qty;
                itemsHtml  += `<div class="summary-row">
                    <span>${item.name} × ${qty}</span>
                    <span>RM ${(price * qty).toFixed(2)}</span>
                </div>`;
            });
            document.getElementById('summaryItems').innerHTML = itemsHtml;

            // ── FIX: Tax on FULL subtotal, discount deducted after ──
            let discount   = 0;
            let voucherHtml = '';
            if (appliedVoucher && appliedVoucher.discount_rm) {
                discount = Math.min(parseFloat(appliedVoucher.discount_rm), subtotal);
                voucherHtml = `<div class="summary-row discount">
                    <span><i class="fas fa-tag"></i> Voucher (${appliedVoucher.code})</span>
                    <span>- RM ${discount.toFixed(2)}</span>
                </div>`;
            }

            const tax   = Math.round(subtotal * TAX * 100) / 100;
            const total = Math.round((subtotal + tax - discount) * 100) / 100;

            document.getElementById('summaryTotals').innerHTML = `
                <div class="summary-row" style="margin-top:10px;">
                    <span>Subtotal</span><span>RM ${subtotal.toFixed(2)}</span>
                </div>
                ${voucherHtml}
                <div class="summary-row">
                    <span>Tax (6%)</span><span>RM ${tax.toFixed(2)}</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span><span>RM ${total.toFixed(2)}</span>
                </div>`;

            document.getElementById('btnPayText').textContent = `Pay Securely — RM ${total.toFixed(2)}`;
        }

        // ── Redirect to Stripe ────────────────────────────────────────────
        async function redirectToStripe() {
            if (cart.length === 0) return;

            const btn = document.getElementById('btnPay');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting to Stripe…';

            try {
                const payload = {
                    cart_data:    JSON.stringify(cart),
                    order_type:   orderType,
                    voucher_data: appliedVoucher ? JSON.stringify(appliedVoucher) : ''
                };

                const res  = await fetch('create_checkout_session.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.url) {
                    window.location.href = data.url;
                } else {
                    alert('Error: ' + (data.error || 'Could not create payment session.'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Pay Securely with Stripe';
                }
            } catch (err) {
                console.error(err);
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock"></i> Pay Securely with Stripe';
            }
        }
    </script>
</body>
</html>