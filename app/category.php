<?php
session_start();
include 'base.php';

$category_id = isset($_GET['catid']) ? mysqli_real_escape_string($connect, $_GET['catid']) : '';
if (empty($category_id) && isset($_GET['id'])) $category_id = mysqli_real_escape_string($connect, $_GET['id']);
if (empty($category_id)) { header("Location: index.php"); exit(); }

$cat_result = mysqli_query($connect, "SELECT * FROM categories WHERE categoryid = '$category_id'");
if (!$cat_result || mysqli_num_rows($cat_result) == 0) { header("Location: index.php"); exit(); }

$category      = mysqli_fetch_assoc($cat_result);
$category_name = htmlspecialchars($category['category_name'] ?? 'Unknown Category');

$no_spicy_categories = ['beverages','drinks','desserts','dessert','juice','coffee','tea','water','beverage'];
$show_spicy = !in_array(strtolower($category_name), $no_spicy_categories);

$cat_lower = strtolower($category_name);
if (in_array($cat_lower, ['desserts','dessert'])) $modal_category = 'desert';
elseif (in_array($cat_lower, ['beverages','drinks','juice','coffee','tea','water','beverage'])) $modal_category = 'beverage';
else $modal_category = 'default';

$products_result = mysqli_query($connect, "SELECT * FROM products WHERE categoryid = '$category_id' AND is_available = 1 ORDER BY productid DESC");
if (!$products_result) die("Error: " . mysqli_error($connect));

$is_logged_in = isset($_SESSION['user_id']);
$user_name = '';
if ($is_logged_in) {
    if (isset($_SESSION['user_name']))     $user_name = htmlspecialchars($_SESSION['user_name']);
    elseif (isset($_SESSION['username'])) $user_name = htmlspecialchars($_SESSION['username']);
}

$wishlist_ids = [];
if ($is_logged_in) {
    $wl_uid  = $_SESSION['user_id'];
    $wl_stmt = mysqli_prepare($connect, "SELECT productid FROM wishlist WHERE userid = ?");
    mysqli_stmt_bind_param($wl_stmt, 's', $wl_uid);
    mysqli_stmt_execute($wl_stmt);
    $wl_res = mysqli_stmt_get_result($wl_stmt);
    while ($wl_row = mysqli_fetch_assoc($wl_res)) $wishlist_ids[] = $wl_row['productid'];
    mysqli_stmt_close($wl_stmt);
}

$nav_pic     = null;
$nav_row     = null;
$nav_initial = strtoupper(mb_substr($user_name, 0, 1)) ?: '?';
if ($is_logged_in) {
    $nav_uid  = $_SESSION['user_id'];
    $nav_stmt = mysqli_prepare($connect, "SELECT profile_picture, username FROM users WHERE userid = ?");
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
}

function stockStatus($stock) {
    $stock = (int)$stock;
    if ($stock <= 0)  return 'out';
    if ($stock <= 10) return 'low';
    return 'ok';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
(function(){
    var t = localStorage.getItem('theme') || localStorage.getItem('ft_theme') || 'light';
    localStorage.setItem('theme', t); localStorage.removeItem('ft_theme'); localStorage.removeItem('foodTheme');
    document.documentElement.setAttribute('data-theme', t);
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $category_name; ?> - FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/index.css">
<style>
.nav-profile-wrap { position: relative; display: flex; align-items: center; }
.nav-avatar { display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 3px 8px 3px 3px; border-radius: 50px; transition: background .15s; user-select: none; border: none; background: none; }
.nav-avatar:hover { background: #fff0e6; }
[data-theme="dark"] .nav-avatar:hover { background: rgba(212,101,15,.18); }
.nav-avatar-pic, .nav-avatar-initial { width: 38px; height: 38px; border-radius: 50%; border: 2.5px solid #d4650f; object-fit: cover; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.nav-avatar-pic { display: block; }
.nav-avatar-initial { background: linear-gradient(135deg, #d4650f, #f0874a); color: #fff; font-size: 16px; font-weight: 800; }
.nav-avatar-caret { font-size: 11px; color: #65676b; transition: transform .2s; }
[data-theme="dark"] .nav-avatar-caret { color: #b0b3b8; }
.nav-profile-wrap.open .nav-avatar-caret { transform: rotate(180deg); }
.nav-dropdown { display: none; position: absolute; top: calc(100% + 10px); right: 0; background: #fff; border: 1px solid #e4e6eb; border-radius: 14px; box-shadow: 0 8px 32px rgba(0,0,0,.14); min-width: 250px; z-index: 500; overflow: hidden; animation: ddIn .18s ease; }
[data-theme="dark"] .nav-dropdown { background: #242526; border-color: #3a3b3c; }
@keyframes ddIn { from { opacity:0; transform: translateY(-8px) scale(.97); } to { opacity:1; transform: translateY(0) scale(1); } }
.nav-profile-wrap.open .nav-dropdown { display: block; }
.nav-dropdown-header { display: flex; align-items: center; gap: 11px; padding: 14px 16px; background: #fff8f2; }
[data-theme="dark"] .nav-dropdown-header { background: rgba(212,101,15,.10); }
.nav-dd-avatar { width: 46px; height: 46px; border-radius: 50%; border: 2.5px solid #d4650f; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.nav-dd-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.nav-dd-avatar-initial { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #d4650f, #f0874a); color: #fff; font-size: 20px; font-weight: 900; }
.nav-dd-name { font-size: 14px; font-weight: 800; color: #1c1e21; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
[data-theme="dark"] .nav-dd-name { color: #e4e6eb; }
.nav-dd-sub { font-size: 12px; color: #8a8d91; margin-top: 1px; }
.nav-dropdown-divider { height: 1px; background: #e4e6eb; margin: 4px 0; }
[data-theme="dark"] .nav-dropdown-divider { background: #3a3b3c; }
.nav-dd-item { display: flex; align-items: center; gap: 11px; padding: 10px 16px; font-size: 14px; font-weight: 600; color: #1c1e21; text-decoration: none; transition: background .12s; cursor: pointer; }
[data-theme="dark"] .nav-dd-item { color: #e4e6eb; }
.nav-dd-item i { width: 16px; color: #d4650f; font-size: 14px; flex-shrink: 0; }
.nav-dd-item:hover { background: #fff0e6; }
[data-theme="dark"] .nav-dd-item:hover { background: rgba(212,101,15,.13); }
.nav-dd-logout { color: #dc2626 !important; }
.nav-dd-logout i { color: #dc2626 !important; }
.nav-dd-logout:hover { background: #fee2e2 !important; }
[data-theme="dark"] .nav-dd-logout:hover { background: rgba(220,38,38,.15) !important; }
.nav-dd-settings { display: flex; align-items: center; justify-content: space-between; padding: 8px 16px; gap: 8px; }
.nav-dd-settings-label { font-size: 12px; font-weight: 700; color: #8a8d91; display: flex; align-items: center; gap: 6px; }
.nav-dd-settings-label i { color: #d4650f; font-size: 12px; }
.nav-dd-settings-controls { display: flex; align-items: center; gap: 6px; }
.dd-lang-wrap { position: relative; }
.dd-lang-btn { display: flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 8px; border: 1.5px solid #e4e6eb; background: #fff; cursor: pointer; font-size: 12px; font-weight: 700; color: #374151; transition: border-color .15s, background .15s; }
[data-theme="dark"] .dd-lang-btn { background: #3a3b3c; border-color: #555; color: #e4e6eb; }
.dd-lang-btn:hover { border-color: #d4650f; background: #fff0e6; }
.dd-lang-btn i { color: #d4650f; font-size: 11px; }
.dd-lang-menu { display: none; position: absolute; bottom: calc(100% + 6px); right: 0; background: #fff; border: 1px solid #e4e6eb; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,.12); min-width: 150px; z-index: 600; overflow: hidden; }
[data-theme="dark"] .dd-lang-menu { background: #242526; border-color: #3a3b3c; }
.dd-lang-wrap.open .dd-lang-menu { display: block; }
.dd-lang-menu a { display: block; padding: 9px 14px; font-size: 13px; font-weight: 600; color: #1c1e21; text-decoration: none; transition: background .12s; }
[data-theme="dark"] .dd-lang-menu a { color: #e4e6eb; }
.dd-lang-menu a:hover { background: #fff0e6; color: #d4650f; }
.dd-theme-btn { display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; border: 1.5px solid #e4e6eb; background: #fff; cursor: pointer; font-size: 14px; color: #374151; transition: border-color .15s, background .15s, color .15s; }
[data-theme="dark"] .dd-theme-btn { background: #3a3b3c; border-color: #555; color: #e4e6eb; }
.dd-theme-btn:hover { border-color: #d4650f; background: #fff0e6; color: #d4650f; }
.btn-chat-nav { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 50px; font-size: 13px; font-weight: 700; text-decoration: none; background: #fff0e6; color: #d4650f; border: 1.5px solid #d4650f; transition: background .15s, color .15s; }
.btn-chat-nav:hover { background: #d4650f; color: #fff; }
.quantity-controls { display: flex; align-items: center; justify-content: center; gap: 12px; margin: 12px 0 10px 0; background: rgba(212,101,15,0.08); border-radius: 30px; padding: 6px 12px; }
.qty-btn { width: 28px; height: 28px; border-radius: 50%; border: 2px solid #d4650f; background: transparent; color: #d4650f; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; flex-shrink: 0; }
.qty-btn:hover { background: #d4650f; color: white; transform: scale(1.1); }
.qty-btn.qty-minus:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
.qty-value { font-size: 16px; font-weight: 700; min-width: 28px; text-align: center; color: #d4650f; user-select: none; }
[data-theme="dark"] .quantity-controls { background: rgba(212,101,15,0.15); }
.product-card { position: relative; }
.product-info { text-align: left; }
.product-info .product-title { text-align: left; font-size: 20px; font-weight: 700; letter-spacing: 1px; }
.product-rating-price { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; margin: 8px 0; }
.product-info .product-rating { display: flex; align-items: center; gap: 3px; font-size: 14px; margin: 0; }
.product-info .product-price { margin: 0; }
.product-info .price-tag { font-size: 18px; font-weight: 800; letter-spacing: 1px; }
.product-info .btn-small { display: block; margin: 0 auto; }
/* Stock */
.stock-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; margin-bottom: 8px; }
.stock-badge-low { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.stock-badge-out { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
[data-theme="dark"] .stock-badge-low { background: rgba(251,191,36,.15); color: #fde68a; border-color: rgba(251,191,36,.3); }
[data-theme="dark"] .stock-badge-out { background: rgba(239,68,68,.15); color: #fca5a5; border-color: rgba(239,68,68,.3); }
.product-card.out-of-stock .product-image { position: relative; }
.product-card.out-of-stock .product-image::after { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,0.55); border-radius: inherit; z-index: 2; }
.oos-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); z-index: 3; background: rgba(220,38,38,0.92); color: #fff; font-size: 13px; font-weight: 900; padding: 8px 18px; border-radius: 30px; white-space: nowrap; letter-spacing: .5px; display: flex; align-items: center; gap: 6px; pointer-events: none; }
.product-card.out-of-stock .btn-small, .product-card.out-of-stock .add-to-cart-btn { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.product-card.out-of-stock .quantity-controls { opacity: 0.4; pointer-events: none; }
/* Wishlist */
.wl-heart-toggle { position: absolute; top: 10px; right: 10px; z-index: 10; width: 36px; height: 36px; border-radius: 50%; border: none; background: rgba(255,255,255,0.92); box-shadow: 0 2px 8px rgba(0,0,0,0.16); display: flex; align-items: center; justify-content: center; font-size: 16px; cursor: pointer; transition: background .15s, transform .15s, color .15s; color: #ccc; }
[data-theme="dark"] .wl-heart-toggle { background: rgba(36,37,38,0.92); color: #555; }
.wl-heart-toggle.wishlisted { color: #ef4444; }
.wl-heart-toggle:hover { background: #fee2e2; transform: scale(1.15); color: #ef4444; }
[data-theme="dark"] .wl-heart-toggle:hover { background: rgba(239,68,68,.18); color: #ef4444; }
.wl-heart-toggle.pop .fa-heart { animation: heartBeat .35s ease; }
@keyframes heartBeat { 0% { transform: scale(1); } 40% { transform: scale(1.45); } 100% { transform: scale(1); } }
.wl-toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px); background: #1c1e21; color: #fff; padding: 12px 22px; border-radius: 50px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 20px rgba(0,0,0,.25); opacity: 0; pointer-events: none; transition: opacity .25s, transform .25s; z-index: 9999; display: flex; align-items: center; gap: 8px; white-space: nowrap; }
[data-theme="dark"] .wl-toast { background: #e4e6eb; color: #1c1e21; }
.wl-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.wl-toast.success i { color: #22c55e; }
.wl-toast.error i { color: #ef4444; }
.breadcrumb { margin: 20px 0; font-size: 14px; color: var(--light-gray); display: flex; align-items: center; gap: 8px; }
.breadcrumb a { color: black; text-decoration: none; font-weight: bold; border: 2px solid black; padding: 6px 14px; border-radius: 4px; background-color: #d4650f; }
.breadcrumb span { font-weight: bold; }
.category-products { padding: 80px 0; background: var(--dark-bg); min-height: 600px; }
.category-title { font-size: 40px; color: var(--primary-color); margin: 20px 0; text-align: center; }
[data-theme="dark"] .category-title { color: var(--accent-color); }
.category-description { text-align: center; color: var(--light-gray); font-size: 16px; margin-bottom: 50px; }
</style>
</head>
<body>

<div id="loginModal" class="login-modal">
    <div class="modal-content">
        <div class="modal-icon">🔐</div>
        <h2>Login Required</h2>
        <p>You need to login first before adding items to your cart.</p>
        <div class="modal-buttons">
            <a href="login.php" class="modal-btn modal-btn-login"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            <button type="button" class="modal-btn modal-btn-close" onclick="closeLoginModal()"><i class="fas fa-times"></i> Close</button>
        </div>
    </div>
</div>
<div class="wl-toast" id="wlToast"><i class="fas fa-heart"></i><span id="wlToastMsg">Added to wishlist</span></div>

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
                <?php if ($is_logged_in): ?>
                    <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i><span data-i18n="btn-orders">Orders</span></a>
                    <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i><span data-i18n="btn-cart">Cart</span></a>
                    <a href="wishlist.php" class="btn btn-primary" title="My Wishlist">
                        <i class="fas fa-heart" style="color:#ef4444;"></i><span>Wishlist</span>
                        <?php if (count($wishlist_ids) > 0): ?>
                            <span style="background:#ef4444;color:#fff;border-radius:50px;font-size:11px;font-weight:900;padding:1px 7px;margin-left:2px;"><?php echo count($wishlist_ids); ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="customer_chat.php" class="btn btn-chat-nav"><i class="fas fa-headset"></i><span>Support</span></a>
                    <div class="nav-profile-wrap" id="navProfileWrap">
                        <button class="nav-avatar" onclick="toggleNavDropdown()" type="button">
                            <?php if ($nav_pic): ?><img src="<?php echo $nav_pic; ?>" alt="Profile" class="nav-avatar-pic"><?php else: ?><div class="nav-avatar-initial"><?php echo $nav_initial; ?></div><?php endif; ?>
                            <i class="fas fa-chevron-down nav-avatar-caret"></i>
                        </button>
                        <div class="nav-dropdown" id="navDropdown">
                            <div class="nav-dropdown-header">
                                <div class="nav-dd-avatar">
                                    <?php if ($nav_pic): ?><img src="<?php echo $nav_pic; ?>" alt="Profile"><?php else: ?><div class="nav-dd-avatar-initial"><?php echo $nav_initial; ?></div><?php endif; ?>
                                </div>
                                <div style="min-width:0">
                                    <div class="nav-dd-name"><?php echo htmlspecialchars($nav_row['username'] ?? $user_name); ?></div>
                                    <div class="nav-dd-sub">FOODTIME Member</div>
                                </div>
                            </div>
                            <div class="nav-dropdown-divider"></div>
                            <a href="profile.php" class="nav-dd-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
                            <a href="order_history.php" class="nav-dd-item"><i class="fas fa-history"></i> Order History</a>
                            <a href="cart.php" class="nav-dd-item"><i class="fas fa-shopping-cart"></i> My Cart</a>
                            <a href="wishlist.php" class="nav-dd-item" style="color:#d4650f!important"><i class="fas fa-heart" style="color:#ef4444!important"></i> My Wishlist</a>
                            <a href="customer_chat.php" class="nav-dd-item"><i class="fas fa-headset"></i> Support Chat</a>
                            <div class="nav-dropdown-divider"></div>
                            <div class="nav-dd-settings">
                                <div class="nav-dd-settings-label"><i class="fas fa-sliders-h"></i> Preferences</div>
                                <div class="nav-dd-settings-controls">
                                    <div class="dd-lang-wrap" id="ddLangWrap">
                                        <button class="dd-lang-btn" type="button" onclick="toggleDdLang(event)"><i class="fas fa-globe"></i><span id="ddLangLabel">EN</span></button>
                                        <div class="dd-lang-menu" id="ddLangMenu">
                                            <a href="#" data-lang="en" onclick="setDdLang('en','EN',event)">🇬🇧 English</a>
                                            <a href="#" data-lang="ms" onclick="setDdLang('ms','MY',event)">🇲🇾 Bahasa Melayu</a>
                                            <a href="#" data-lang="zh" onclick="setDdLang('zh','中文',event)">🇨🇳 中文</a>
                                        </div>
                                    </div>
                                    <button class="dd-theme-btn" id="ddThemeBtn" type="button" title="Toggle Dark Mode" onclick="event.stopPropagation(); toggleTheme();"><i class="fas fa-moon" id="ddThemeIcon"></i></button>
                                </div>
                            </div>
                            <div class="nav-dropdown-divider"></div>
                            <a href="logout.php" class="nav-dd-item nav-dd-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="language-switcher">
                        <button class="lang-btn" id="langBtn" type="button"><i class="fas fa-globe"></i></button>
                        <div class="language-dropdown" id="languageDropdown">
                            <a href="#" data-lang="en">English</a>
                            <a href="#" data-lang="ms">Bahasa Melayu</a>
                            <a href="#" data-lang="zh">中文</a>
                        </div>
                    </div>
                    <button class="theme-toggle" id="themeToggle" type="button"><i class="fas fa-moon"></i></button>
                    <a href="login.php" class="btn btn-login"><i class="fas fa-sign-in-alt"></i><span data-i18n="login">Login</span></a>
                    <a href="register.php" class="btn btn-signup"><i class="fas fa-user-plus"></i><span data-i18n="sign-up">Sign Up</span></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<section class="category-products">
    <div class="container">
        <div class="breadcrumb"><a href="index.php">Home</a> / <span><?php echo $category_name; ?></span></div>
        <h1 class="category-title">🍽️ <?php echo $category_name; ?></h1>
        <p class="category-description">Choose from our delicious <?php echo strtolower($category_name); ?> selection</p>
        <div class="products-grid">
            <?php
            if ($products_result && mysqli_num_rows($products_result) > 0) {
                while ($product = mysqli_fetch_assoc($products_result)) {
                    $prod_id        = $product['productid'];
                    $prod_name      = htmlspecialchars($product['name'] ?? 'Unknown');
                    $prod_price     = number_format((float)($product['price'] ?? 0), 2);
                    $prod_price_raw = number_format((float)($product['price'] ?? 0), 2, '.', '');
                    $prod_rating    = $product['rating'] ?? 4.5;
                    $prod_image     = !empty($product['image']) ? htmlspecialchars($product['image']) : '';
                    $is_wishlisted  = in_array($prod_id, $wishlist_ids);
                    $prod_stock     = (int)($product['stock'] ?? 100);
                    $prod_status    = stockStatus($prod_stock);
                    $is_oos         = ($prod_status === 'out');
                    ?>
                    <div class="product-card <?php echo $is_oos ? 'out-of-stock' : ''; ?>">
                        <?php if ($is_logged_in): ?>
                            <button class="wl-heart-toggle <?php echo $is_wishlisted ? 'wishlisted' : ''; ?>" id="wlHeart_<?php echo $prod_id; ?>" onclick="toggleWishlist(event,'<?php echo $prod_id; ?>')" title="<?php echo $is_wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>" type="button"><i class="fas fa-heart"></i></button>
                        <?php else: ?>
                            <button class="wl-heart-toggle" onclick="showLoginModal()" type="button"><i class="fas fa-heart"></i></button>
                        <?php endif; ?>
                        <div class="product-image">
                            <?php if ($prod_image !== ''): ?><img src="uploads/<?php echo $prod_image; ?>" alt="<?php echo $prod_name; ?>"><?php else: ?><i class="fas fa-image"></i><?php endif; ?>
                            <?php if ($is_oos): ?>
                                <div class="oos-overlay"><i class="fas fa-ban"></i> Out of Stock</div>
                            <?php else: ?>
                                <div class="product-overlay">
                                    <button class="add-to-cart-btn" type="button" onclick="handleAddToCart(event,'<?php echo $prod_id; ?>','<?php echo addslashes($prod_name); ?>','<?php echo $prod_price_raw; ?>')"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo $prod_name; ?></h3>
                            <?php if ($prod_status === 'low'): ?>
                                <div class="stock-badge stock-badge-low"><i class="fas fa-exclamation-triangle"></i> Only <?php echo $prod_stock; ?> left!</div>
                            <?php elseif ($is_oos): ?>
                                <div class="stock-badge stock-badge-out"><i class="fas fa-ban"></i> Out of Stock</div>
                            <?php endif; ?>
                            <div class="product-rating-price">
                                <div class="product-rating">
                                    <?php for ($i = 0; $i < 5; $i++): ?><i class="<?php echo $i < floor($prod_rating) ? 'fas' : 'far'; ?> fa-star"></i><?php endfor; ?>
                                </div>
                                <p class="product-price"><span class="price-tag">RM <?php echo $prod_price; ?></span></p>
                            </div>
                            <?php if (!$is_oos): ?>
                            <div class="quantity-controls">
                                <button class="qty-btn qty-minus" type="button" onclick="changeQty(this,-1)" disabled><i class="fas fa-minus"></i></button>
                                <span class="qty-value">1</span>
                                <button class="qty-btn qty-plus" type="button" onclick="changeQty(this,1)"><i class="fas fa-plus"></i></button>
                            </div>
                            <button class="btn btn-small add-to-cart-btn" type="button" onclick="handleAddToCartWithQty(event,'<?php echo $prod_id; ?>','<?php echo addslashes($prod_name); ?>','<?php echo $prod_price_raw; ?>', this)">Add to Cart</button>
                            <?php else: ?>
                            <button class="btn btn-small" type="button" disabled style="opacity:.4;cursor:not-allowed;background:#9ca3af;margin-top:10px;">Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p class="no-products">No products available in this category.</p>';
            }
            ?>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section"><h4>🍴 About FOODTIME</h4><a href="#">About Us</a><a href="#">Blog</a><a href="#">Careers</a></div>
            <div class="footer-section"><h4>Support</h4><a href="customer_chat.php">Help Center</a><a href="customer_chat.php">Contact Us</a><a href="#">FAQ</a></div>
            <div class="footer-section"><h4>Legal</h4><a href="#">Privacy Policy</a><a href="#">Terms & Conditions</a><a href="#">Refund Policy</a></div>
            <div class="footer-section"><h4>Follow Us</h4><div class="social-links"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
        </div>
        <div class="footer-bottom"><p>&copy; 2026 FOODTIME. All rights reserved.</p></div>
    </div>
</footer>

<script src="js/translations.js"></script>
<script src="js/theme.js"></script>
<script src="js/language.js"></script>
<script src="js/cart.js"></script>
<script>
const isLoggedIn    = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
const PAGE_CATEGORY = '<?php echo $modal_category; ?>';

let wlToastTimer = null;
function showWlToast(msg, type = 'success') {
    const toast = document.getElementById('wlToast');
    toast.querySelector('span').textContent = msg;
    toast.className = 'wl-toast show ' + type;
    toast.querySelector('i').className = type === 'success' ? 'fas fa-heart' : 'fas fa-exclamation-circle';
    clearTimeout(wlToastTimer);
    wlToastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}
function toggleWishlist(event, productId) {
    event.preventDefault(); event.stopPropagation();
    if (!isLoggedIn) { showLoginModal(); return; }
    const btn = document.getElementById('wlHeart_' + productId);
    if (!btn) return;
    btn.classList.add('pop');
    setTimeout(() => btn.classList.remove('pop'), 400);
    fetch('toggle_wishlist.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'productid=' + encodeURIComponent(productId) })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.wishlisted) {
    btn.classList.add('wishlisted');
    btn.title = 'Remove from Wishlist';
    showWlToast('Added to wishlist ❤️', 'success');
    updateNavWishlistBadge(1);
} else {
    btn.classList.remove('wishlisted');
    btn.title = 'Add to Wishlist';
    showWlToast('Removed from wishlist', 'success');
    updateNavWishlistBadge(-1);
}
        } else { showWlToast('Could not update wishlist', 'error'); }
    })
    .catch(() => showWlToast('Network error', 'error'));
}
function changeQty(btn, delta) {
    const controls = btn.closest('.quantity-controls');
    const qtySpan  = controls.querySelector('.qty-value');
    const minusBtn = controls.querySelector('.qty-minus');
    const plusBtn  = controls.querySelector('.qty-plus');
    const card = btn.closest('.product-card');
    let pid = null;
    if (card) {
        const addBtn = card.querySelector('[onclick*="handleAddToCartWithQty"]');
        if (addBtn) {
            const m = (addBtn.getAttribute('onclick') || '').match(/'([A-Z0-9]+)'/);
            if (m) pid = m[1];
        }
    }
    let alreadyInCart = 0;
    if (pid) {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        alreadyInCart = cart.filter(i => i.id == pid).reduce((sum, i) => sum + (parseInt(i.quantity) || 1), 0);
    }
    const remaining = Math.max(0, 50 - alreadyInCart);
    const current = Math.min(remaining, Math.max(1, parseInt(qtySpan.textContent) + delta));
    qtySpan.textContent = current;
    minusBtn.disabled = (current === 1);
    plusBtn.disabled  = (current >= remaining);
}
function handleAddToCart(event, productId, productName, productPrice) {
    event.preventDefault(); event.stopPropagation();
    if (!isLoggedIn) { showLoginModal(); return false; }
    const card    = event.currentTarget.closest('.product-card');
    const qtySpan = card ? card.querySelector('.qty-value') : null;
    openCustomizeModal(productId, productName, productPrice, qtySpan ? parseInt(qtySpan.textContent) || 1 : 1, PAGE_CATEGORY);
}
function handleAddToCartWithQty(event, productId, productName, productPrice, btn) {
    event.preventDefault(); event.stopPropagation();
    if (!isLoggedIn) { showLoginModal(); return false; }
    const controls = btn.closest('.product-info').querySelector('.quantity-controls');
    const qty = controls ? Math.max(1, parseInt(controls.querySelector('.qty-value').textContent) || 1) : 1;
    openCustomizeModal(productId, productName, productPrice, qty, PAGE_CATEGORY);
}
function showLoginModal()  { const m = document.getElementById('loginModal'); if (m) m.classList.add('show'); }
function closeLoginModal() { const m = document.getElementById('loginModal'); if (m) m.classList.remove('show'); }
window.onclick = function(e) { const m = document.getElementById('loginModal'); if (e.target === m) closeLoginModal(); };
function toggleNavDropdown() { const wrap = document.getElementById('navProfileWrap'); if (wrap) wrap.classList.toggle('open'); }
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('navProfileWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
    const lw = document.getElementById('ddLangWrap');
    if (lw && !lw.contains(e.target)) lw.classList.remove('open');
});
function toggleDdLang(e) { e.stopPropagation(); document.getElementById('ddLangWrap').classList.toggle('open'); }
function setDdLang(lang, label, e) {
    e.preventDefault(); e.stopPropagation();
    document.getElementById('ddLangLabel').textContent = label;
    document.getElementById('ddLangWrap').classList.remove('open');
    localStorage.setItem('lang', lang);
    if (typeof setLanguage === 'function') setLanguage(lang);
    else document.querySelectorAll('[data-lang]').forEach(el => { if (el.dataset.lang === lang) el.click(); });
}
function updateNavWishlistBadge(delta) {
    const wishlistBtn = document.querySelector('a[href="wishlist.php"].btn');
    if (!wishlistBtn) return;
    let badge = wishlistBtn.querySelector('span[style*="background:#ef4444"]');
    if (badge) {
        let count = parseInt(badge.textContent) + delta;
        if (count <= 0) badge.remove();
        else badge.textContent = count;
    } else if (delta > 0) {
        const newBadge = document.createElement('span');
        newBadge.style.cssText = 'background:#ef4444;color:#fff;border-radius:50px;font-size:11px;font-weight:900;padding:1px 7px;margin-left:2px;';
        newBadge.textContent = '1';
        wishlistBtn.appendChild(newBadge);
    }
}
</script>
<?php include 'customizeModal.php'; ?>
</body>
</html>