<?php
session_start();
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name = $is_logged_in ? (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '') : '';

if(!$is_logged_in) {
    header("Location: login.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if($order_id == 0) {
    header("Location: index.php");
    exit();
}

$order_query = "SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id";
$order_result = mysqli_query($connect, $order_query);

if(mysqli_num_rows($order_result) == 0) {
    header("Location: index.php");
    exit();
}

$order = mysqli_fetch_assoc($order_result);

$items_query = "SELECT * FROM order_items WHERE order_id = $order_id";
$items_result = mysqli_query($connect, $items_query);

$order_message = $order['order_type'] === 'dine_in' ? 
    'Your order is being prepared. Your food will be served at your table shortly!' : 
    'Your order is being prepared. Please collect it at the counter!';

$order_icon = $order['order_type'] === 'dine_in' ? 'fa-chair' : 'fa-shopping-bag';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - FlavorBite</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .success-container {
            max-width: 700px;
            margin: 60px auto;
            background: var(--card-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(212, 101, 15, 0.12);
            text-align: center;
        }

        [data-theme="dark"] .success-container {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }

        .success-icon {
            font-size: 80px;
            color: #27ae60;
            margin-bottom: 20px;
            animation: scaleIn 0.6s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-title {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        [data-theme="dark"] .success-title {
            color: var(--accent-color);
        }

        .success-subtitle {
            font-size: 18px;
            color: var(--light-gray);
            margin-bottom: 30px;
        }

        .order-number {
            background: rgba(212, 101, 15, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid var(--primary-color);
        }

        .order-number p {
            margin: 5px 0;
            color: var(--light-text);
        }

        .order-number .number {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }

        [data-theme="dark"] .order-number .number {
            color: var(--accent-color);
        }

        .order-details {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: left;
        }

        [data-theme="dark"] .order-details {
            background: #333;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            color: var(--light-text);
        }

        [data-theme="dark"] .detail-row {
            border-bottom-color: #555;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
        }

        .detail-value {
            text-align: right;
            color: var(--light-gray);
        }

        .items-table {
            width: 100%;
            margin-bottom: 30px;
            background: #f5f5f5;
            border-radius: 10px;
            overflow: hidden;
        }

        [data-theme="dark"] .items-table {
            background: #333;
        }

        .items-table thead {
            background: var(--primary-color);
            color: white;
        }

        .items-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            color: var(--light-text);
        }

        [data-theme="dark"] .items-table td {
            border-bottom-color: #555;
        }

        .items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .order-type-badge {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .next-steps {
            background: rgba(255, 152, 0, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid var(--accent-color);
            text-align: left;
        }

        .next-steps h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .next-steps p {
            color: var(--light-gray);
            margin: 5px 0;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-home {
            background: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn-home:hover {
            background: #b85509;
            transform: translateY(-2px);
        }

        .btn-order-again {
            background: transparent;
            color: var(--primary-color);
            padding: 12px 30px;
            border: 2px solid var(--primary-color);
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn-order-again:hover {
            background: var(--primary-color);
            color: white;
        }

        @media (max-width: 768px) {
            .success-container {
                margin: 30px 20px;
                padding: 30px 20px;
            }

            .success-icon {
                font-size: 60px;
            }

            .success-title {
                font-size: 24px;
            }

            .detail-row {
                flex-direction: column;
                padding: 15px 0;
            }

            .detail-value {
                text-align: left;
                margin-top: 5px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-home,
            .btn-order-again {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="navbar">
                <a href="index.php" class="logo">
                    <i class="fas fa-burger"></i>
                    <span>FlavorBite</span>
                </a>

                <ul class="nav-menu">
                    <li><a href="index.php#categories">Categories</a></li>
                    <li><a href="index.php#menu">Menu</a></li>
                    <li><a href="index.php#about">About</a></li>
                </ul>

                <div class="auth-buttons">
                    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()">
                        <i class="fas fa-moon"></i>
                    </button>

                    <?php if($is_logged_in): ?>
                        <span class="user-greeting">👋 Hi, <?php echo htmlspecialchars($user_name); ?></span>
                        <a href="logout.php" class="btn btn-logout">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Success Section -->
    <section class="success-section">
        <div class="success-container">
            <!-- Success Icon -->
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>

            <!-- Title -->
            <h1 class="success-title">Order Confirmed! ✓</h1>
            <p class="success-subtitle"><?php echo $order_message; ?></p>

            <!-- Order Type Badge -->
            <span class="order-type-badge">
                <i class="fas <?php echo $order_icon; ?>"></i>
                <?php echo $order['order_type'] === 'dine_in' ? 'DINE IN' : 'TAKEAWAY'; ?>
            </span>

            <!-- Order Number -->
            <div class="order-number">
                <p>Order Number</p>
                <p class="number">#<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></p>
                <p style="font-size: 12px;">Placed on: <?php echo date('M d, Y - g:i A', strtotime($order['created_at'])); ?></p>
            </div>

            <!-- Order Details -->
            <div class="order-details">
                <div class="detail-row">
                    <span class="detail-label">Customer Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                </div>
                <?php if($order['order_type'] === 'dine_in'): ?>
                    <div class="detail-row">
                        <span class="detail-label">Table Number:</span>
                        <span class="detail-value"><strong>Table <?php echo htmlspecialchars($order['table_number']); ?></strong></span>
                    </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))); ?></span>
                </div>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(mysqli_num_rows($items_result) > 0) {
                        while($item = mysqli_fetch_assoc($items_result)) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($item['product_name']) . '</td>';
                            echo '<td>' . $item['quantity'] . '</td>';
                            echo '<td>RM ' . number_format($item['price'], 2) . '</td>';
                            echo '<td>RM ' . number_format($item['subtotal'], 2) . '</td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>

            <!-- Order Summary -->
            <div class="order-details">
                <div class="detail-row">
                    <span class="detail-label">Subtotal:</span>
                    <span class="detail-value">RM <?php echo number_format($order['subtotal'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tax (6%):</span>
                    <span class="detail-value">RM <?php echo number_format($order['tax'], 2); ?></span>
                </div>
                <div class="detail-row" style="border-top: 2px solid var(--primary-color); padding-top: 15px; margin-top: 15px; font-size: 18px; font-weight: bold;">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value" style="color: var(--primary-color);">RM <?php echo number_format($order['total_price'], 2); ?></span>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="next-steps">
                <h4><i class="fas fa-info-circle"></i> What's Next?</h4>
                <?php if($order['order_type'] === 'dine_in'): ?>
                    <p>✓ Your order has been confirmed and sent to the kitchen</p>
                    <p>✓ Please proceed to <strong>Table <?php echo htmlspecialchars($order['table_number']); ?></strong></p>
                    <p>✓ Your food will be served to your table shortly</p>
                    <p>✓ Payment can be made at the table or now via card</p>
                <?php else: ?>
                    <p>✓ Your order has been confirmed and sent to the kitchen</p>
                    <p>✓ Please wait at the counter for your order</p>
                    <p>✓ Your order will be ready in approximately 20-30 minutes</p>
                    <p>✓ Payment can be made at pickup or now</p>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="index.php" class="btn-home">
                    <i class="fas fa-home"></i> Back to Home
                </a>
                <a href="index.php#categories" class="btn-order-again">
                    <i class="fas fa-shopping-cart"></i> Order More
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <h4>🍴 FlavorBite</h4>
                    <a href="#">About Us</a>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <a href="#">Help Center</a>
                </div>
                <div class="footer-section">
                    <h4>Follow Us</h4>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 FlavorBite. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/theme.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            localStorage.removeItem('cart');
        });
    </script>
</body>
</html>