<?php
/**
 * stripe_cancel.php
 *
 * Stripe redirects here if the user clicks "Back" on the Stripe
 * Checkout page. We mark the pending order as cancelled and let
 * the user try again.
 */

session_start();
include 'base.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$order_id  = isset($_GET['orderid']) ? trim($_GET['orderid']) : '';
$user_name = htmlspecialchars($_SESSION['user_name'] ?? '');

// Mark the pending order as cancelled so it doesn't sit in limbo
if (!empty($order_id)) {
    $oid_esc = mysqli_real_escape_string($connect, $order_id);
    mysqli_query($connect, "UPDATE orders   SET status = 'cancelled', updated_at = NOW() WHERE orderid = '$oid_esc' AND status = 'pending'");
    mysqli_query($connect, "UPDATE payments SET status = 'cancelled'                      WHERE orderid = '$oid_esc' AND status = 'pending'");
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - FOODTIME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .cancel-section { padding: 80px 0; min-height: 80vh; display: flex; align-items: center; }
        .cancel-box { max-width: 520px; margin: 0 auto; background: var(--card-bg); border-radius: 18px; padding: 48px 40px; text-align: center; box-shadow: 0 8px 32px rgba(212,101,15,0.10); }
        [data-theme="dark"] .cancel-box { box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .cancel-icon { width: 88px; height: 88px; background: linear-gradient(135deg,#f59e0b,#d97706); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 38px; color: white; }
        .cancel-title { font-size: 26px; font-weight: 800; color: var(--light-text); margin: 0 0 12px; }
        .cancel-sub { font-size: 15px; color: var(--light-gray); line-height: 1.7; margin-bottom: 32px; }
        .btn-group { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-primary-solid { background: var(--primary-color); color: white; padding: 13px 28px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 15px; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary-solid:hover { background: #b85509; transform: translateY(-2px); }
        .btn-outline { color: var(--primary-color); padding: 13px 28px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 15px; border: 2px solid var(--primary-color); transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline:hover { background: var(--primary-color); color: white; }
        .info-note { margin-top: 28px; font-size: 13px; color: var(--light-gray); background: rgba(212,101,15,0.06); border-radius: 8px; padding: 12px 16px; border-left: 4px solid var(--primary-color); text-align: left; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="index.php" class="logo"><i class="fas fa-burger"></i><span>FOODTIME</span></a>
                <div class="auth-buttons">
                    <button class="theme-toggle" id="themeToggle" type="button"><i class="fas fa-moon"></i></button>
                    <span class="user-greeting">&#128075; Hi, <?php echo $user_name; ?></span>
                    <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> Orders</a>
                    <a href="logout.php" class="btn btn-logout">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <section class="cancel-section">
        <div class="container">
            <div class="cancel-box">
                <div class="cancel-icon"><i class="fas fa-times"></i></div>
                <h1 class="cancel-title">Payment Cancelled</h1>
                <p class="cancel-sub">
                    No worries — your payment was not charged.<br>
                    Your cart is still saved and you can try again whenever you're ready.
                </p>
                <div class="btn-group">
                    <a href="checkout.php" class="btn-primary-solid">
                        <i class="fas fa-redo"></i> Try Again
                    </a>
                    <a href="cart.php" class="btn-outline">
                        <i class="fas fa-shopping-cart"></i> Back to Cart
                    </a>
                </div>
                <div class="info-note">
                    <i class="fas fa-info-circle" style="color:var(--primary-color);"></i>
                    &nbsp;You were not charged. The cancelled order has been removed from our system.
                    If you have any questions, contact our support team.
                </div>
            </div>
        </div>
    </section>

    <script src="js/theme.js"></script>
</body>
</html>
