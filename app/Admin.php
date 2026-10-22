<?php
session_start();
require_once 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'] ?? '';
$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$current_page = 'dashboard';

// Fetch profile picture for Edit Profile avatar
$sidebar_pic = '';
if ($admin_id) {
    $pic_result = mysqli_query($connect, "SELECT profile_picture FROM users WHERE userid = '$admin_id' LIMIT 1");
    if ($pic_result) {
        $pic_row = mysqli_fetch_assoc($pic_result);
        if (!empty($pic_row['profile_pic'])) {
            $sidebar_pic = htmlspecialchars($pic_row['profile_pic']);
        }
    }
}

function getCount($connect, $sql) {
    $result = mysqli_query($connect, $sql);
    if (!$result) return 0;
    $row = mysqli_fetch_assoc($result);
    return (int)($row['c'] ?? 0);
}

function getSum($connect, $sql) {
    $result = mysqli_query($connect, $sql);
    if (!$result) return 0;
    $row = mysqli_fetch_assoc($result);
    return (float)($row['s'] ?? 0);
}

$stats = [
    'customers' => getCount($connect, "SELECT COUNT(*) AS c FROM users WHERE position='customer'"),
    'admins'    => getCount($connect, "SELECT COUNT(*) AS c FROM users WHERE position='admin'"),
    'blocked'   => getCount($connect, "SELECT COUNT(*) AS c FROM users WHERE status='blocked'"),
    'orders'    => getCount($connect, "SELECT COUNT(*) AS c FROM orders"),
    'pending'   => getCount($connect, "SELECT COUNT(*) AS c FROM orders WHERE status='pending'"),
    'products'  => getCount($connect, "SELECT COUNT(*) AS c FROM products WHERE is_available=1"),
    'revenue'   => getSum($connect, "SELECT COALESCE(SUM(total_price),0) AS s FROM orders WHERE status != 'cancelled'"),
];

$recent = [];
$recent_result = mysqli_query($connect, "
    SELECT o.orderid, o.customer_name, o.total_price, o.status, o.created_at, u.username
    FROM orders o
    LEFT JOIN users u ON o.userid = u.userid
    ORDER BY o.created_at DESC
    LIMIT 5
");
if ($recent_result) {
    while ($row = mysqli_fetch_assoc($recent_result)) $recent[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>

<?php include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">Dashboard <span>Overview</span></div>
        <div class="topbar-right">
            <div class="topbar-date">
                <i class="far fa-calendar-alt"></i> <?= date('l, d F Y') ?>
            </div>
            <div class="live-clock">
                <i class="fas fa-clock"></i>
                <span id="clock-hm"></span><span id="clock-s"></span>
                <span id="clock-ampm"></span>
            </div>
        </div>
    </div>

    <div class="content">

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card stat-info">
                <div class="sc-icon"><i class="fas fa-users"></i></div>
                <div class="sc-val"><?= number_format($stats['customers']) ?></div>
                <div class="sc-label">Customers</div>
            </div>
            <div class="stat-card stat-accent">
                <div class="sc-icon"><i class="fas fa-user-shield"></i></div>
                <div class="sc-val"><?= number_format($stats['admins']) ?></div>
                <div class="sc-label">Admins</div>
            </div>
            <div class="stat-card stat-danger">
                <div class="sc-icon"><i class="fas fa-ban"></i></div>
                <div class="sc-val"><?= number_format($stats['blocked']) ?></div>
                <div class="sc-label">Blocked Users</div>
            </div>
            <div class="stat-card stat-purple">
                <div class="sc-icon"><i class="fas fa-receipt"></i></div>
                <div class="sc-val"><?= number_format($stats['orders']) ?></div>
                <div class="sc-label">Total Orders</div>
            </div>
            <div class="stat-card stat-warning">
                <div class="sc-icon"><i class="fas fa-clock"></i></div>
                <div class="sc-val"><?= number_format($stats['pending']) ?></div>
                <div class="sc-label">Pending Orders</div>
            </div>
            <div class="stat-card stat-success">
                <div class="sc-icon"><i class="fas fa-coins"></i></div>
                <div class="rev-val" style="margin-bottom:4px;">RM <?= number_format($stats['revenue'], 2) ?></div>
                <div class="sc-label">Total Revenue</div>
            </div>
        </div>

        <!-- Main grid -->
        <div class="dash-grid">
            <!-- Recent Orders -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-receipt"></i> Recent Orders</div>
                    <a href="Order.php" class="panel-action">View all →</a>
                </div>
                <div class="table-wrap">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i><p>No orders yet.</p></td></tr>
                        <?php else: ?>
                            <?php foreach ($recent as $r): ?>
                            <tr>
                                <td class="order-id-cell"><?= htmlspecialchars($r['orderid']) ?></td>
                                <td><?= htmlspecialchars($r['customer_name'] ?? $r['username'] ?? '—') ?></td>
                                <td class="text-accent fw-600">RM <?= number_format((float)$r['total_price'], 2) ?></td>
                                <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                                <td class="text-muted" style="font-size:12px;"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-bolt"></i> Quick Actions</div>
                </div>
                <div class="quick-actions">
                    <a href="User.php" class="qa-btn">
                        <i class="fas fa-user-cog" style="color:var(--info)"></i>
                        <div>
                            <span class="qa-title">Manage Users</span>
                            <span class="qa-sub">Block, unblock, upgrade, delete</span>
                        </div>
                    </a>
                    <a href="Order.php" class="qa-btn">
                        <i class="fas fa-clipboard-list" style="color:var(--accent)"></i>
                        <div>
                            <span class="qa-title">View Orders</span>
                            <span class="qa-sub">Review all transactions</span>
                        </div>
                    </a>
                    <a href="Product.php" class="qa-btn">
                        <i class="fas fa-utensils" style="color:var(--success)"></i>
                        <div>
                            <span class="qa-title">Menu Items</span>
                            <span class="qa-sub">Add and update food items</span>
                        </div>
                    </a>
                    <a href="TopSellingProduct.php" class="qa-btn">
                        <i class="fas fa-chart-bar" style="color:var(--warning)"></i>
                        <div>
                            <span class="qa-title">Top Products</span>
                            <span class="qa-sub">Sales performance report</span>
                        </div>
                    </a>
                    <a href="ingredient_analytics.php" class="qa-btn">
                        <i class="fas fa-flask" style="color:var(--info)"></i>
                        <div>
                            <span class="qa-title">Ingredient Analytics</span>
                            <span class="qa-sub">Remove &amp; add-on insights</span>
                        </div>
                    </a>
                    <a href="admin_chat.php" class="qa-btn" id="qa-chat-btn">
                        <i class="fas fa-headset" style="color:var(--purple)"></i>
                        <div>
                            <span class="qa-title">Support Chat</span>
                            <span class="qa-sub" id="qa-chat-sub">Respond to customer messages</span>
                        </div>
                    </a>

                    <!-- Edit Profile (ported from 400-line version) -->
                    <a href="admin_profile.php" class="qa-btn">
                        <div style="width:22px;height:22px;border-radius:50%;border:1.5px solid var(--accent);overflow:hidden;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--accent),var(--info));color:#fff;font-weight:700;font-size:11px;flex-shrink:0;">
                            <?php if ($sidebar_pic): ?>
                                <img src="<?= $sidebar_pic ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                            <?php else: ?>
                                <?= strtoupper(substr($admin_name, 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="qa-title">Edit Profile</span>
                            <span class="qa-sub">Update your account details</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
function updateClock() {
    const now = new Date();
    let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    document.getElementById('clock-hm').textContent = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
    document.getElementById('clock-s').textContent  = ':' + String(s).padStart(2,'0');
    document.getElementById('clock-ampm').textContent = ampm;
}
updateClock();
setInterval(updateClock, 1000);

// update quick actions chat sub text from sidebar badge
(function syncChatSub() {
    const badge = document.getElementById('nav-unread-badge');
    const sub   = document.getElementById('qa-chat-sub');
    if (!badge || !sub) return;
    const n = parseInt(badge.textContent) || 0;
    sub.textContent = n > 0 ? `${n} unread message${n > 1 ? 's' : ''}` : 'Respond to customer messages';
    setTimeout(syncChatSub, 9000);
})();
</script>

</body>
</html>