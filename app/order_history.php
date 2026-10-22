<?php
session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name = '';

if ($is_logged_in) {
    if (isset($_SESSION['user_name']))     $user_name = htmlspecialchars($_SESSION['user_name']);
    elseif (isset($_SESSION['username'])) $user_name = htmlspecialchars($_SESSION['username']);
}

if (!$is_logged_in) {
    header("Location: login.php");
    exit();
}

$page   = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

$count_result = mysqli_query($connect, "SELECT COUNT(*) as total FROM orders WHERE userid = '$user_id'");
if (!$count_result) die("Error: " . mysqli_error($connect));
$count_row    = mysqli_fetch_assoc($count_result);
$total_orders = $count_row ? $count_row['total'] : 0;
$total_pages  = ceil($total_orders / $limit);

$points_stmt = mysqli_prepare($connect,
    "SELECT membership_points, total_points_earned FROM users WHERE userid = ?");
mysqli_stmt_bind_param($points_stmt, 's', $user_id);
mysqli_stmt_execute($points_stmt);
$points_res  = mysqli_stmt_get_result($points_stmt);
$points_row  = mysqli_fetch_assoc($points_res);
mysqli_stmt_close($points_stmt);
$current_points      = $points_row ? (int)$points_row['membership_points']   : 0;
$total_points_earned = $points_row ? (int)$points_row['total_points_earned'] : 0;

$orders_result = mysqli_query($connect,
    "SELECT * FROM orders WHERE userid = '$user_id' ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
if (!$orders_result) die("Error: " . mysqli_error($connect));

// Nav profile
$nav_pic     = null;
$nav_initial = strtoupper(mb_substr($user_name, 0, 1)) ?: '?';
$nav_row     = null;
$nav_points  = $current_points;
$nav_uid     = $user_id;
$nav_stmt    = mysqli_prepare($connect, "SELECT profile_picture, username FROM users WHERE userid = ?");
mysqli_stmt_bind_param($nav_stmt, 's', $nav_uid);
mysqli_stmt_execute($nav_stmt);
$nav_res = mysqli_stmt_get_result($nav_stmt);
$nav_row = mysqli_fetch_assoc($nav_res);
mysqli_stmt_close($nav_stmt);
if ($nav_row) {
    $nav_initial = strtoupper(mb_substr($nav_row['username'] ?? $user_name, 0, 1));
    if (!empty($nav_row['profile_picture']) && file_exists('uploads/profiles/' . $nav_row['profile_picture']))
        $nav_pic = 'uploads/profiles/' . htmlspecialchars($nav_row['profile_picture']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

<!-- ① Prevent theme flash -->
<script>
(function(){
    var t = localStorage.getItem('theme') || 'light';
    localStorage.setItem('theme', t);
    document.documentElement.setAttribute('data-theme', t);
})();
</script>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order History - FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/index.css">

<!-- ② translations.js MUST be in <head> so language.js finds it on DOMContentLoaded -->
<script src="js/translations.js"></script>

<style>
/* ── Nav dropdown (same as other pages) ── */
.nav-profile-wrap { position: relative; display: flex; align-items: center; }
.nav-avatar { display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 3px 8px 3px 3px; border-radius: 50px; transition: background .15s; user-select: none; border: none; background: none; }
.nav-avatar:hover { background: #fff0e6; }
[data-theme="dark"] .nav-avatar:hover { background: rgba(212,101,15,.18); }
.nav-avatar-pic, .nav-avatar-initial { width: 38px; height: 38px; border-radius: 50%; border: 2.5px solid #d4650f; object-fit: cover; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.nav-avatar-pic { display: block; }
.nav-avatar-initial { background: linear-gradient(135deg,#d4650f,#f0874a); color:#fff; font-size:16px; font-weight:800; }
.nav-avatar-caret { font-size:11px; color:#65676b; transition:transform .2s; }
[data-theme="dark"] .nav-avatar-caret { color:#b0b3b8; }
.nav-profile-wrap.open .nav-avatar-caret { transform:rotate(180deg); }
.nav-dropdown { display:none; position:absolute; top:calc(100% + 10px); right:0; background:#fff; border:1px solid #e4e6eb; border-radius:14px; box-shadow:0 8px 32px rgba(0,0,0,.14); min-width:250px; z-index:500; overflow:hidden; animation:ddIn .18s ease; }
[data-theme="dark"] .nav-dropdown { background:#242526; border-color:#3a3b3c; }
@keyframes ddIn { from{opacity:0;transform:translateY(-8px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }
.nav-profile-wrap.open .nav-dropdown { display:block; }
.nav-dropdown-header { display:flex; align-items:center; gap:11px; padding:14px 16px; background:#fff8f2; }
[data-theme="dark"] .nav-dropdown-header { background:rgba(212,101,15,.10); }
.nav-dd-avatar { width:46px; height:46px; border-radius:50%; border:2.5px solid #d4650f; overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
.nav-dd-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.nav-dd-avatar-initial { width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#d4650f,#f0874a); color:#fff; font-size:20px; font-weight:900; }
.nav-dd-name { font-size:14px; font-weight:800; color:#1c1e21; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px; }
[data-theme="dark"] .nav-dd-name { color:#e4e6eb; }
.nav-dd-sub { font-size:12px; color:#8a8d91; margin-top:1px; }
.nav-dd-points-badge { display:inline-flex; align-items:center; gap:4px; background:linear-gradient(135deg,#f39c12,#e67e22); color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; margin-top:4px; }
.nav-dropdown-divider { height:1px; background:#e4e6eb; margin:4px 0; }
[data-theme="dark"] .nav-dropdown-divider { background:#3a3b3c; }
.nav-dd-item { display:flex; align-items:center; gap:11px; padding:10px 16px; font-size:14px; font-weight:600; color:#1c1e21; text-decoration:none; transition:background .12s; cursor:pointer; }
[data-theme="dark"] .nav-dd-item { color:#e4e6eb; }
.nav-dd-item i { width:16px; color:#d4650f; font-size:14px; flex-shrink:0; }
.nav-dd-item:hover { background:#fff0e6; }
[data-theme="dark"] .nav-dd-item:hover { background:rgba(212,101,15,.13); }
.nav-dd-logout { color:#dc2626!important; }
.nav-dd-logout i { color:#dc2626!important; }
.nav-dd-logout:hover { background:#fee2e2!important; }
[data-theme="dark"] .nav-dd-logout:hover { background:rgba(220,38,38,.15)!important; }
.nav-dd-settings { display:flex; align-items:center; justify-content:space-between; padding:8px 16px; gap:8px; }
.nav-dd-settings-label { font-size:12px; font-weight:700; color:#8a8d91; display:flex; align-items:center; gap:6px; }
.nav-dd-settings-label i { color:#d4650f; font-size:12px; }
.nav-dd-settings-controls { display:flex; align-items:center; gap:6px; }
.dd-lang-wrap { position:relative; }
.dd-lang-btn { display:flex; align-items:center; gap:5px; padding:5px 10px; border-radius:8px; border:1.5px solid #e4e6eb; background:#fff; cursor:pointer; font-size:12px; font-weight:700; color:#374151; transition:border-color .15s,background .15s; }
[data-theme="dark"] .dd-lang-btn { background:#3a3b3c; border-color:#555; color:#e4e6eb; }
.dd-lang-btn:hover { border-color:#d4650f; background:#fff0e6; }
.dd-lang-btn i { color:#d4650f; font-size:11px; }
.dd-lang-menu { display:none; position:absolute; bottom:calc(100% + 6px); right:0; background:#fff; border:1px solid #e4e6eb; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.12); min-width:150px; z-index:600; overflow:hidden; }
[data-theme="dark"] .dd-lang-menu { background:#242526; border-color:#3a3b3c; }
.dd-lang-wrap.open .dd-lang-menu { display:block; }
.dd-lang-menu a { display:block; padding:9px 14px; font-size:13px; font-weight:600; color:#1c1e21; text-decoration:none; transition:background .12s; }
[data-theme="dark"] .dd-lang-menu a { color:#e4e6eb; }
.dd-lang-menu a:hover { background:#fff0e6; color:#d4650f; }
.dd-lang-menu a.active { color:#d4650f; font-weight:800; }
.dd-theme-btn { display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; border:1.5px solid #e4e6eb; background:#fff; cursor:pointer; font-size:14px; color:#374151; transition:border-color .15s,background .15s,color .15s; }
[data-theme="dark"] .dd-theme-btn { background:#3a3b3c; border-color:#555; color:#e4e6eb; }
.dd-theme-btn:hover { border-color:#d4650f; background:#fff0e6; color:#d4650f; }
.btn-chat-nav { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:50px; font-size:13px; font-weight:700; text-decoration:none; background:#fff0e6; color:#d4650f; border:1.5px solid #d4650f; transition:background .15s,color .15s; }
.btn-chat-nav:hover { background:#d4650f; color:#fff; }

/* ── Page styles ── */
.order-history-section { padding: 5px 0; min-height: 600px; }
.orders-container { margin: 40px 0; }
.orders-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--primary-color); }
.orders-header h2 { color: var(--primary-color); margin: 0; }
[data-theme="dark"] .orders-header h2 { color: var(--accent-color); }
.order-stats { display: flex; gap: 20px; flex-wrap: wrap; }
.stat-card { background: var(--card-bg); padding: 15px 25px; border-radius: 8px; text-align: center; }
.stat-number { font-size: 24px; font-weight: bold; color: var(--primary-color); }
[data-theme="dark"] .stat-number { color: var(--accent-color); }
.stat-label { font-size: 12px; color: var(--light-gray); margin-top: 5px; }
.empty-orders { text-align: center; padding: 60px 20px; background: var(--card-bg); border-radius: 10px; color: var(--light-gray); }
.empty-orders i { font-size: 80px; margin-bottom: 20px; opacity: 0.3; }
.empty-orders p { font-size: 18px; margin-bottom: 30px; }
.orders-table { width: 100%; background: var(--card-bg); border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(212,101,15,.12); }
[data-theme="dark"] .orders-table { box-shadow: 0 4px 15px rgba(0,0,0,.4); }
.orders-table table { width: 100%; border-collapse: collapse; }
.orders-table th { background: var(--primary-color); color: white; padding: 15px; text-align: left; font-weight: 600; }
.orders-table td { padding: 15px; border-bottom: 1px solid #e0e0e0; color: var(--light-text); }
[data-theme="dark"] .orders-table td { border-bottom-color: #444; }
.orders-table tbody tr:hover { background: rgba(212,101,15,.05); }
.order-id { font-weight: 600; color: var(--primary-color); }
[data-theme="dark"] .order-id { color: var(--accent-color); }
.btn-view { background: var(--primary-color); color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; transition: all 0.3s; }
.btn-view:hover { background: #b85509; }
.points-badge { display:inline-flex; align-items:center; gap:4px; background:linear-gradient(135deg,#fef3c7,#fde68a); color:#92400e; padding:4px 10px; border-radius:50px; font-size:12px; font-weight:800; border:1px solid #fbbf24; }
.points-badge i { color:#d97706; font-size:11px; }
.points-badge.zero { background:#f9fafb; color:#9ca3af; border-color:#e5e7eb; }
.points-badge.zero i { color:#d1d5db; }
.points-banner { background:linear-gradient(135deg,#d4650f 0%,#f0874a 60%,#fbbf7a 100%); border-radius:16px; padding:24px 28px; margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; box-shadow:0 4px 20px rgba(212,101,15,.28); }
.points-banner-left { display:flex; align-items:center; gap:16px; }
.points-banner-icon { width:56px; height:56px; border-radius:50%; background:rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; font-size:26px; color:#fff; flex-shrink:0; border:2px solid rgba(255,255,255,.35); }
.points-banner-info h3 { color:#fff; font-size:14px; font-weight:700; margin:0 0 2px; opacity:.88; letter-spacing:.5px; text-transform:uppercase; }
.points-banner-score { color:#fff; font-size:38px; font-weight:900; line-height:1; letter-spacing:-1px; }
.points-banner-score span { font-size:16px; font-weight:600; opacity:.8; }
.points-banner-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; }
.points-banner-total { color:rgba(255,255,255,.82); font-size:13px; font-weight:600; }
.points-banner-rate { background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.35); border-radius:50px; padding:6px 14px; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; gap:6px; }
.points-banner-rate i { color:#fde68a; }
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
            <form method="GET" action="index.php" class="search-form">
                <input type="text" name="keyword" placeholder="Search food..." required>
                <button type="submit" title="Search"><i class="fas fa-search"></i></button>
            </form>
            <div class="auth-buttons">
                <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i><span data-i18n="btn-orders">Orders</span></a>
                <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i><span data-i18n="btn-cart">Cart</span></a>
                <a href="customer_chat.php" class="btn btn-chat-nav"><i class="fas fa-headset"></i><span>Support</span></a>

                <!-- Profile avatar + dropdown -->
                <div class="nav-profile-wrap" id="navProfileWrap">
                    <button class="nav-avatar" onclick="toggleNavDropdown()" type="button">
                        <?php if ($nav_pic): ?>
                            <img src="<?php echo $nav_pic; ?>" alt="Profile" class="nav-avatar-pic">
                        <?php else: ?>
                            <div class="nav-avatar-initial"><?php echo $nav_initial; ?></div>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down nav-avatar-caret"></i>
                    </button>
                    <div class="nav-dropdown" id="navDropdown">
                        <div class="nav-dropdown-header">
                            <div class="nav-dd-avatar">
                                <?php if ($nav_pic): ?>
                                    <img src="<?php echo $nav_pic; ?>" alt="Profile">
                                <?php else: ?>
                                    <div class="nav-dd-avatar-initial"><?php echo $nav_initial; ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="min-width:0">
                                <div class="nav-dd-name"><?php echo htmlspecialchars($nav_row['username'] ?? $user_name); ?></div>
                                <div class="nav-dd-sub">FOODTIME Member</div>
                                <div class="nav-dd-points-badge"><i class="fas fa-star"></i> <?php echo number_format($nav_points); ?> pts</div>
                            </div>
                        </div>
                        <div class="nav-dropdown-divider"></div>
                        <a href="profile.php"       class="nav-dd-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
                        <a href="order_history.php" class="nav-dd-item"><i class="fas fa-history"></i> Order History</a>
                        <a href="membership.php"    class="nav-dd-item"><i class="fas fa-star"></i> My Points</a>
                        <a href="cart.php"          class="nav-dd-item"><i class="fas fa-shopping-cart"></i> My Cart</a>
                        <a href="customer_chat.php" class="nav-dd-item"><i class="fas fa-headset"></i> Support Chat</a>
                        <div class="nav-dropdown-divider"></div>
                        <div class="nav-dd-settings">
                            <div class="nav-dd-settings-label"><i class="fas fa-sliders-h"></i> Preferences</div>
                            <div class="nav-dd-settings-controls">
                                <div class="dd-lang-wrap" id="ddLangWrap">
                                    <button class="dd-lang-btn" type="button" onclick="toggleDdLang(event)">
                                        <i class="fas fa-globe"></i><span id="ddLangLabel">EN</span>
                                    </button>
                                    <!-- ③ No inline onclick — language.js wires these via data-lang -->
                                    <div class="dd-lang-menu" id="ddLangMenu">
                                        <a href="#" data-lang="en">🇬🇧 English</a>
                                        <a href="#" data-lang="ms">🇲🇾 Bahasa Melayu</a>
                                        <a href="#" data-lang="zh">🇨🇳 中文</a>
                                    </div>
                                </div>
                                <button class="dd-theme-btn" id="ddThemeBtn" type="button" title="Toggle Dark Mode"
                                        onclick="event.stopPropagation(); toggleTheme();">
                                    <i class="fas fa-moon" id="ddThemeIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="nav-dropdown-divider"></div>
                        <a href="logout.php" class="nav-dd-item nav-dd-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<section class="order-history-section">
    <div class="container">
        <div class="orders-container">

            <!-- Membership Points Banner -->
            <div class="points-banner">
                <div class="points-banner-left">
                    <div class="points-banner-icon"><i class="fas fa-star"></i></div>
                    <div class="points-banner-info">
                        <h3>🏅 Membership Points</h3>
                        <div class="points-banner-score"><?php echo number_format($current_points); ?><span>pts</span></div>
                    </div>
                </div>
                <div class="points-banner-right">
                    <div class="points-banner-total">Total earned: <strong><?php echo number_format($total_points_earned); ?> pts</strong></div>
                    <div class="points-banner-rate"><i class="fas fa-coins"></i> RM 1 spent = 5 points</div>
                </div>
            </div>

            <div class="orders-header">
                <h2><i class="fas fa-history"></i> <span data-i18n="order-history">Order History</span></h2>
                <div class="order-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_orders; ?></div>
                        <div class="stat-label" data-i18n="total-orders">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color:#d97706;">
                            <i class="fas fa-star" style="font-size:18px;"></i>
                            <?php echo number_format($current_points); ?>
                        </div>
                        <div class="stat-label">My Points</div>
                    </div>
                </div>
            </div>

            <?php if ($total_orders == 0 || !$orders_result): ?>
                <div class="empty-orders">
                    <i class="fas fa-inbox"></i>
                    <p data-i18n="no-orders">No orders yet</p>
                    <a href="index.php#categories" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i>
                        <span data-i18n="start-ordering">Start Ordering</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="orders-table">
                    <table>
                        <thead>
                            <tr>
                                <th data-i18n="order-id">Order #</th>
                                <th data-i18n="date">Date</th>
                                <th data-i18n="order-type">Type</th>
                                <th data-i18n="status">Status</th>
                                <th data-i18n="total">Total</th>
                                <th>Points Earned</th>
                                <th data-i18n="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        if (mysqli_num_rows($orders_result) > 0) {
                            while ($order = mysqli_fetch_assoc($orders_result)) {
                                $order_id       = $order['orderid'];
                                $order_type     = $order['order_type']    ?? 'dine_in';
                                $created_at     = $order['created_at']    ?? date('Y-m-d H:i:s');
                                $total_price    = $order['total_price']   ?? 0;
                                $payment_method = strtolower($order['payment_method'] ?? 'cash');
                                $pts_earned     = (int)($order['points_earned'] ?? 0);

                                $raw_status = strtolower($order['status'] ?? 'pending');
                                if ($payment_method === 'cash') {
                                    $status = $raw_status;
                                } else {
                                    $status = in_array($raw_status, ['completed','cancelled']) ? $raw_status : 'paid';
                                }

                                // Type badge — uses data-i18n-dynamic for JS translation
                                $type_key   = ($order_type === 'dine_in') ? 'dine-in' : 'takeaway';
                                $type_icon  = ($order_type === 'dine_in') ? 'fa-chair' : 'fa-shopping-bag';
                                $type_style = ($order_type === 'dine_in')
                                    ? 'background:#e3f2fd;color:#1976d2;'
                                    : 'background:#fff3e0;color:#f57c00;';
                                $type_label = ($order_type === 'dine_in') ? 'Dine In' : 'Takeaway';
                                $type_badge = '<span class="dyn-translate" data-dyn-key="' . $type_key . '" style="' . $type_style . 'padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;"><i class="fas ' . $type_icon . '"></i> ' . $type_label . '</span>';

                                // Status badge
                                $status_colors = [
                                    'pending'   => ['bg'=>'#fff3cd','text'=>'#856404'],
                                    'paid'      => ['bg'=>'#d4edda','text'=>'#155724'],
                                    'preparing' => ['bg'=>'#cce5ff','text'=>'#004085'],
                                    'completed' => ['bg'=>'#d4edda','text'=>'#155724'],
                                    'cancelled' => ['bg'=>'#f8d7da','text'=>'#721c24'],
                                ];
                                $sc = $status_colors[$status] ?? ['bg'=>'#e0e0e0','text'=>'#333'];
                                $status_badge = '<span class="dyn-translate" data-dyn-key="' . $status . '" style="background:' . $sc['bg'] . ';color:' . $sc['text'] . ';padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;">' . ucfirst($status) . '</span>';

                                // Points badge
                                $pts_badge = $pts_earned > 0
                                    ? '<span class="points-badge"><i class="fas fa-star"></i> +' . $pts_earned . ' pts</span>'
                                    : '<span class="points-badge zero"><i class="fas fa-star"></i> —</span>';

                                $formatted_date = date('M d, Y - g:i A', strtotime($created_at));
                                ?>
                                <tr>
                                    <td><span class="order-id">#<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></span></td>
                                    <td class="date-translate" data-date="<?php echo htmlspecialchars($created_at); ?>"><?php echo $formatted_date; ?></td>
                                    <td><?php echo $type_badge; ?></td>
                                    <td><?php echo $status_badge; ?></td>
                                    <td><strong>RM <?php echo number_format($total_price, 2); ?></strong></td>
                                    <td><?php echo $pts_badge; ?></td>
                                    <td>
                                        <a href="order_details.php?orderid=<?php echo $order_id; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> <span data-i18n="view">View</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section">
                <h4 data-i18n="about">About FOODTIME</h4>
                <a href="#" data-i18n="about-us">About Us</a>
                <a href="#" data-i18n="blog">Blog</a>
                <a href="#" data-i18n="careers">Careers</a>
            </div>
            <div class="footer-section">
                <h4 data-i18n="support">Support</h4>
                <a href="#" data-i18n="help-center">Help Center</a>
                <a href="#" data-i18n="contact-us">Contact Us</a>
                <a href="#" data-i18n="faq">FAQ</a>
            </div>
            <div class="footer-section">
                <h4 data-i18n="legal">Legal</h4>
                <a href="#" data-i18n="privacy-policy">Privacy Policy</a>
                <a href="#" data-i18n="terms-conditions">Terms &amp; Conditions</a>
                <a href="#" data-i18n="refund-policy">Refund Policy</a>
            </div>
            <div class="footer-section">
                <h4 data-i18n="follow-us">Follow Us</h4>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p data-i18n="copyright">&copy; 2026 FOODTIME. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- ④ Load order: theme.js → language.js (translations.js already in <head>) -->
<script src="js/theme.js"></script>
<script src="js/language.js"></script>

<script>
/* ── Nav dropdown ── */
function toggleNavDropdown() {
    var wrap = document.getElementById('navProfileWrap');
    if (wrap) wrap.classList.toggle('open');
}
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('navProfileWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
    var lw = document.getElementById('ddLangWrap');
    if (lw && !lw.contains(e.target)) lw.classList.remove('open');
});
function toggleDdLang(e) {
    e.stopPropagation();
    document.getElementById('ddLangWrap').classList.toggle('open');
}

/* ⑤ Translate dynamic table content (status/type badges + dates)
   Called automatically by the hook below whenever language changes */
function translateDynamicContent(lang) {
    if (typeof translations === 'undefined') return;
    var t = translations[lang] || translations['en'];

    // Status & type badges use class="dyn-translate" data-dyn-key="..."
    document.querySelectorAll('.dyn-translate').forEach(function(el) {
        var key = el.getAttribute('data-dyn-key');
        if (!t[key]) return;
        var icon = el.querySelector('i');
        if (icon) {
            el.innerHTML = icon.outerHTML + ' ' + t[key];
        } else {
            el.textContent = t[key];
        }
    });

    // Date cells
    var months = {
        en: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        ms: ['Jan','Feb','Mac','Apr','Mei','Jun','Jul','Ogo','Sep','Okt','Nov','Dis'],
        zh: null
    };
    document.querySelectorAll('.date-translate').forEach(function(el) {
        var dateStr = el.getAttribute('data-date');
        if (!dateStr) return;
        var d = new Date(dateStr);
        var time = d.toLocaleTimeString('en-US');
        if (lang === 'zh') {
            var mm = String(d.getMonth() + 1).padStart(2, '0');
            var dd = String(d.getDate()).padStart(2, '0');
            el.textContent = d.getFullYear() + '年' + mm + '月' + dd + '日 - ' + time;
        } else {
            var mList = months[lang] || months['en'];
            el.textContent = mList[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + ' - ' + time;
        }
    });
}

/* ⑥ Hook into language.js's changeLanguage so dynamic content
   also updates whenever the user switches language */
document.addEventListener('DOMContentLoaded', function() {
    // Apply on load using the correct key 'language' (not 'foodLanguage')
    var lang = localStorage.getItem('language') || 'en';
    translateDynamicContent(lang);

    // Wrap changeLanguage to also run our dynamic translator
    var _orig = window.changeLanguage;
    window.changeLanguage = function(l) {
        if (typeof _orig === 'function') _orig(l);
        translateDynamicContent(l);
    };
});
</script>
</body>
</html>