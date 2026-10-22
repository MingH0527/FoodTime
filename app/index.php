<?php
session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");
include 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_name = '';

if ($is_logged_in) {
    if (isset($_SESSION['user_name'])) {
        $user_name = htmlspecialchars($_SESSION['user_name']);
    } elseif (isset($_SESSION['username'])) {
        $user_name = htmlspecialchars($_SESSION['username']);
    }
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
$nav_initial = strtoupper(mb_substr($user_name, 0, 1)) ?: '?';
$nav_row     = null;
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

$search_keyword = '';
$search_results = [];
$is_searching   = false;

if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
    $search_keyword = mysqli_real_escape_string($connect, trim($_GET['keyword']));
    $is_searching   = true;
    $search_query   = "SELECT * FROM products WHERE name LIKE '$search_keyword%' AND is_available = 1 ORDER BY name ASC";
    $search_result  = mysqli_query($connect, $search_query);
    if ($search_result) while ($row = mysqli_fetch_assoc($search_result)) $search_results[] = $row;
}

$categories_query  = "SELECT * FROM categories ORDER BY categoryid ASC";
$categories_result = mysqli_query($connect, $categories_query);
if (!$categories_result) die("Categories query failed: " . mysqli_error($connect));

// Top 4 food (not beverages/desserts)
$featured_query = "SELECT p.* FROM products p 
    LEFT JOIN order_items oi ON p.productid = oi.productid 
    LEFT JOIN categories c ON p.categoryid = c.categoryid
    WHERE p.is_available = 1 
    AND LOWER(c.category_name) NOT IN ('beverages','drinks','juice','coffee','tea','water','beverage','desserts','dessert')
    GROUP BY p.productid ORDER BY SUM(oi.quantity) DESC LIMIT 4";
$featured_result = mysqli_query($connect, $featured_query);
$featured_products = [];
if ($featured_result) while ($row = mysqli_fetch_assoc($featured_result)) $featured_products[] = $row;

// Top 4 beverages/desserts
$featured_query2 = "SELECT p.* FROM products p 
    LEFT JOIN order_items oi ON p.productid = oi.productid 
    LEFT JOIN categories c ON p.categoryid = c.categoryid
    WHERE p.is_available = 1 
    AND LOWER(c.category_name) IN ('beverages','drinks','juice','coffee','tea','water','beverage','desserts','dessert')
    GROUP BY p.productid ORDER BY SUM(oi.quantity) DESC LIMIT 4";
$featured_result2 = mysqli_query($connect, $featured_query2);
$featured_drinks = [];
if ($featured_result2) while ($row = mysqli_fetch_assoc($featured_result2)) $featured_drinks[] = $row;
$featured_result   = mysqli_query($connect, $featured_query);
$featured_products = [];
if ($featured_result) while ($row = mysqli_fetch_assoc($featured_result)) $featured_products[] = $row;


function getModalCategory($cat_name) {
    $cat_lower = strtolower($cat_name ?? '');
    if (in_array($cat_lower, ['desserts','dessert'])) return 'desert';
    if (in_array($cat_lower, ['beverages','drinks','juice','coffee','tea','water','beverage'])) return 'beverage';
    return 'default';
}

// Stock helper
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
    localStorage.setItem('theme', t);
    localStorage.removeItem('ft_theme');
    localStorage.removeItem('foodTheme');
    document.documentElement.setAttribute('data-theme', t);
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FOODTIME - Food & Beverages Ordering System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/index.css">
<style>
/* ===== QUANTITY CONTROLS ===== */
.quantity-controls { display: flex; align-items: center; justify-content: center; gap: 12px; margin: 12px 0 10px 0; background: rgba(212,101,15,0.08); border-radius: 30px; padding: 6px 12px; }
.qty-btn { width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--primary-color,#d4650f); background: transparent; color: var(--primary-color,#d4650f); font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; flex-shrink: 0; }
.qty-btn:hover { background: var(--primary-color,#d4650f); color: white; transform: scale(1.1); }
.qty-btn:active { transform: scale(0.95); }
.qty-btn.qty-minus:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
.qty-value { font-size: 16px; font-weight: 700; min-width: 28px; text-align: center; color: var(--primary-color,#d4650f); user-select: none; }
[data-theme="dark"] .quantity-controls { background: rgba(212,101,15,0.15); }

/* ===== PRODUCT INFO ===== */
.product-info { text-align: left; }
.product-info .product-title { text-align: left; font-size: 20px; font-weight: 700; letter-spacing: 1px; }
.product-rating-price { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; margin: 8px 0; }
.product-info .product-rating { display: flex; align-items: center; gap: 3px; font-size: 14px; margin: 0; }
.product-info .product-price { margin: 0; }
.product-info .price-tag { font-size: 18px; font-weight: 800; letter-spacing: 1px; }
.product-info .btn-small { display: block; margin: 0 auto; }

/* ===== STOCK BADGES ===== */
.stock-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 800; margin-bottom: 8px;
}
.stock-badge-low { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.stock-badge-out { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
[data-theme="dark"] .stock-badge-low { background: rgba(251,191,36,.15); color: #fde68a; border-color: rgba(251,191,36,.3); }
[data-theme="dark"] .stock-badge-out { background: rgba(239,68,68,.15); color: #fca5a5; border-color: rgba(239,68,68,.3); }

/* ===== OUT OF STOCK OVERLAY ===== */
.product-card { position: relative; }
.product-card.out-of-stock .product-image { position: relative; }
.product-card.out-of-stock .product-image::after {
    content: '';
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.55);
    border-radius: inherit;
    z-index: 2;
}
.oos-overlay {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    z-index: 3;
    background: rgba(220,38,38,0.92);
    color: #fff; font-size: 13px; font-weight: 900;
    padding: 8px 18px; border-radius: 30px;
    white-space: nowrap; letter-spacing: .5px;
    display: flex; align-items: center; gap: 6px;
    pointer-events: none;
}
.product-card.out-of-stock .btn-small,
.product-card.out-of-stock .add-to-cart-btn {
    opacity: 0.4; cursor: not-allowed; pointer-events: none;
}
.product-card.out-of-stock .quantity-controls { opacity: 0.4; pointer-events: none; }

/* ===== WISHLIST HEART ===== */
.wl-heart-toggle { position: absolute; top: 10px; right: 10px; z-index: 10; width: 36px; height: 36px; border-radius: 50%; border: none; background: rgba(255,255,255,0.92); box-shadow: 0 2px 8px rgba(0,0,0,0.16); display: flex; align-items: center; justify-content: center; font-size: 16px; cursor: pointer; transition: background .15s, transform .15s, color .15s; color: #ccc; }
[data-theme="dark"] .wl-heart-toggle { background: rgba(36,37,38,0.92); color: #555; }
.wl-heart-toggle.wishlisted { color: #ef4444; }
.wl-heart-toggle:hover { background: #fee2e2; transform: scale(1.15); color: #ef4444; }
[data-theme="dark"] .wl-heart-toggle:hover { background: rgba(239,68,68,.18); color: #ef4444; }
.wl-heart-toggle.pop .fa-heart { animation: heartBeat .35s ease; }
@keyframes heartBeat { 0% { transform: scale(1); } 40% { transform: scale(1.45); } 100% { transform: scale(1); } }

/* ===== WISHLIST TOAST ===== */
.wl-toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px); background: #1c1e21; color: #fff; padding: 12px 22px; border-radius: 50px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 20px rgba(0,0,0,.25); opacity: 0; pointer-events: none; transition: opacity .25s, transform .25s; z-index: 9999; display: flex; align-items: center; gap: 8px; white-space: nowrap; }
[data-theme="dark"] .wl-toast { background: #e4e6eb; color: #1c1e21; }
.wl-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.wl-toast.success i { color: #22c55e; }
.wl-toast.error i { color: #ef4444; }

/* ===== NAV PROFILE AVATAR + DROPDOWN ===== */
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
[data-theme="dark"] .nav-dropdown { background: #242526; border-color: #3a3b3c; box-shadow: 0 8px 32px rgba(0,0,0,.35); }
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
.hero { background: linear-gradient(135deg, #d4650f 0%, #f0874a 100%); padding: 50px 0; min-height: auto; }
[data-theme="dark"] .hero { background: linear-gradient(135deg, #b8540c 0%, #d4650f 100%); }
.hero-content { text-align: center; max-width: 800px; margin: 0 auto; }
.hero-content h1 { font-size: 36px; margin-bottom: 12px; color: #fff; }
.hero-content p { font-size: 16px; color: rgba(255,255,255,0.95); margin-bottom: 24px; }
.cta-buttons { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
.cta-buttons .btn { padding: 12px 28px; font-size: 15px; }
.cta-buttons .btn-primary { background: #fff; color: #d4650f; border: none; }
.cta-buttons .btn-primary:hover { background: #fff0e6; transform: translateY(-2px); }
.cta-buttons .btn-secondary { background: transparent; color: #fff; border: 2px solid #fff; }
.cta-buttons .btn-secondary:hover { background: rgba(255,255,255,0.1); transform: translateY(-2px); }
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
                <input type="text" name="keyword" placeholder="Search food..." value="<?php echo htmlspecialchars($search_keyword); ?>" maxlength="50" required>
                <button type="submit" title="Search"><i class="fas fa-search"></i></button>
            </form>
            <div class="auth-buttons">
                <?php if ($is_logged_in): ?>
                    <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i><span data-i18n="btn-orders">Orders</span></a>
                    <a href="cart.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i><span data-i18n="btn-cart">Cart</span></a>
                    <a href="wishlist.php" class="btn btn-primary" title="My Wishlist" id="navWishlistBtn">
    <i class="fas fa-heart" style="color:#ef4444;"></i><span>Wishlist</span>
    <span id="navWishlistBadge" style="background:#ef4444;color:#fff;border-radius:50px;font-size:11px;font-weight:900;padding:1px 7px;margin-left:2px;<?php echo count($wishlist_ids) === 0 ? 'display:none;' : ''; ?>"><?php echo count($wishlist_ids); ?></span>
</a>
                    <a href="customer_chat.php" class="btn btn-chat-nav" title="Live Support Chat"><i class="fas fa-headset"></i><span>Support</span></a>
                    <div class="nav-profile-wrap" id="navProfileWrap">
                        <button class="nav-avatar" onclick="toggleNavDropdown()" title="My Account" type="button">
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
                                </div>
                            </div>
                            <div class="nav-dropdown-divider"></div>
                            <a href="profile.php" class="nav-dd-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
                            <a href="order_history.php" class="nav-dd-item"><i class="fas fa-history"></i> Order History</a>
                            <a href="membership.php" class="nav-dd-item"><i class="fas fa-star"></i> My Points</a>
                            <a href="cart.php" class="nav-dd-item"><i class="fas fa-shopping-cart"></i> My Cart</a>
                            <a href="wishlist.php" class="nav-dd-item" style="color:#d4650f!important"><i class="fas fa-heart" style="color:#ef4444!important"></i> My Wishlist</a>
                            <a href="customer_chat.php" class="nav-dd-item"><i class="fas fa-headset"></i> Support Chat</a>
                            <div class="nav-dropdown-divider"></div>
                            <div class="nav-dd-settings">
                                <div class="nav-dd-settings-label"><i class="fas fa-sliders-h"></i> Preferences</div>
                                <div class="nav-dd-settings-controls">
                                    <div class="dd-lang-wrap" id="ddLangWrap">
                                        <button class="dd-lang-btn" type="button" onclick="toggleDdLang(event)" title="Change Language">
                                            <i class="fas fa-globe"></i><span id="ddLangLabel">EN</span>
                                        </button>
                                        <div class="dd-lang-menu" id="ddLangMenu">
                                            <a href="#" data-lang="en" onclick="setDdLang('en','EN',event)">🇬🇧 English</a>
                                            <a href="#" data-lang="ms" onclick="setDdLang('ms','MY',event)">🇲🇾 Bahasa Melayu</a>
                                            <a href="#" data-lang="zh" onclick="setDdLang('zh','中文',event)">🇨🇳 中文</a>
                                        </div>
                                    </div>
                                    <button class="dd-theme-btn" id="ddThemeBtn" type="button" title="Toggle Dark Mode" onclick="event.stopPropagation(); toggleTheme();">
                                        <i class="fas fa-moon" id="ddThemeIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="nav-dropdown-divider"></div>
                            <a href="logout.php" class="nav-dd-item nav-dd-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- ✅ Guest: language switcher & dark mode REMOVED -->
                    <a href="login.php" class="btn btn-login"><i class="fas fa-sign-in-alt"></i><span data-i18n="login">Login</span></a>
                    <a href="register.php" class="btn btn-signup"><i class="fas fa-user-plus"></i><span data-i18n="sign-up">Sign Up</span></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<?php if ($is_searching): ?>
<section class="search-results-section">
    <div class="container">
        <div class="search-header">
            <h2>🔍 Search Results</h2>
            <p>Results for "<strong><?php echo htmlspecialchars($search_keyword); ?></strong>"</p>
        </div>
        <?php if (!empty($search_results)): ?>
            <div class="search-stats">Found <strong><?php echo count($search_results); ?></strong> product(s)</div>
            <div class="products-grid">
                <?php foreach ($search_results as $product):
                    $prod_id        = $product['productid'];
                    $prod_name      = htmlspecialchars($product['name']);
                    $prod_price     = number_format((float)$product['price'], 2);
                    $prod_price_raw = number_format((float)$product['price'], 2, '.', '');
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
                        <button class="btn btn-small" type="button" disabled style="opacity:.4;cursor:not-allowed;background:#9ca3af;">Out of Stock</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align:center;margin-top:30px">
                <a href="index.php" class="clear-search-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No products found</h3>
                <p>Sorry, we couldn't find any products matching "<strong><?php echo htmlspecialchars($search_keyword); ?></strong>"</p>
                <a href="index.php" class="clear-search-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php else: ?>
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1 data-i18n="welcome-title">🍔 Welcome to FOODTIME</h1>
            <p data-i18n="welcome-subtitle">Order delicious food and beverages online instantly</p>
            <div class="cta-buttons">
                <a href="#categories" class="btn btn-primary" data-i18n="order-now"><i class="fas fa-utensils"></i> Order Now</a>
                <a href="#about" class="btn btn-secondary" data-i18n="learn-more"><i class="fas fa-info-circle"></i> Learn More</a>
            </div>
        </div>
    </div>
</section>

<section class="categories-menu-section" id="categories">
    <div class="container">
        <div class="categories-menu-wrapper">
            <button class="menu-scroll-btn" onclick="scrollMenu('left')" title="Scroll Left" type="button"><i class="fas fa-chevron-left"></i></button>
            <div class="categories-menu-container" id="categoriesMenu">
                <span class="menu-label">Menu Category</span>
                <?php
                if (mysqli_num_rows($categories_result) > 0) {
                    while ($category = mysqli_fetch_assoc($categories_result)) {
                        $cat_id   = $category['categoryid'];
                        $cat_name = htmlspecialchars($category['category_name']);
                        $icons = ['Appetizers'=>'fa-utensils','Soups'=>'fa-bowl-food','Chicken Dishes'=>'fa-drumstick-bite','Beef & Lamb'=>'fa-utensils','Seafood'=>'fa-fish','Rice Dishes'=>'fa-bowl-rice','Noodles'=>'fa-bowl-food','Vegetable Dishes'=>'fa-carrot','Special'=>'fa-star','Western'=>'fa-burger','Desserts'=>'fa-cake-candles','Beverages'=>'fa-glass-water'];
                        $icon = $icons[$cat_name] ?? 'fa-utensils';
                        echo '<a href="menu.php?catid=' . $cat_id . '" class="category-menu-item" title="' . $cat_name . '"><i class="fas ' . $icon . '"></i><span>' . $cat_name . '</span></a>';
                    }
                }
                ?>
            </div>
            <button class="menu-scroll-btn" onclick="scrollMenu('right')" title="Scroll Right" type="button"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</section>

<section id="menu" class="featured-section">
    <div class="container">
        <h2 class="section-title">⭐ Top Food</h2>
        <div class="products-grid">
            <?php
            if (!empty($featured_products)) {
                foreach ($featured_products as $product) {
                    $prod_id        = $product['productid'];
                    $prod_name      = htmlspecialchars($product['name']);
                    $prod_price     = number_format((float)$product['price'], 2);
                    $prod_price_raw = number_format((float)$product['price'], 2, '.', '');
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
                            <button class="btn btn-small" type="button" disabled style="opacity:.4;cursor:not-allowed;background:#9ca3af;">Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p class="no-products">No food products available yet.</p>';
            }
            ?>
        </div>

        <h2 class="section-title" style="margin-top:50px;">🥤 Top Beverages & Desserts</h2>
        <div class="products-grid">
            <?php
            if (!empty($featured_drinks)) {
                foreach ($featured_drinks as $product) {
                    $prod_id        = $product['productid'];
                    $prod_name      = htmlspecialchars($product['name']);
                    $prod_price     = number_format((float)$product['price'], 2);
                    $prod_price_raw = number_format((float)$product['price'], 2, '.', '');
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
                            <button class="btn btn-small" type="button" disabled style="opacity:.4;cursor:not-allowed;background:#9ca3af;">Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p class="no-products">No beverages or desserts available yet.</p>';
            }
            ?>
        </div>
    </div>
</section>

<section id="about" class="about-section">
    <div class="container">
        <h2 class="section-title" data-i18n="why-choose">Why Choose FOODTIME?</h2>
        <div class="features-grid">
            <div class="feature-card"><i class="fas fa-shipping-fast feature-icon"></i><h3 data-i18n="fast-delivery">Fast Delivery</h3><p data-i18n="fast-delivery-desc">Get your food in 30 minutes or less.</p></div>
            <div class="feature-card"><i class="fas fa-lock feature-icon"></i><h3 data-i18n="secure-payment">Secure Payment</h3><p data-i18n="secure-payment-desc">All transactions are encrypted and secure.</p></div>
            <div class="feature-card"><i class="fas fa-mobile-alt feature-icon"></i><h3 data-i18n="easy-ordering">Easy Ordering</h3><p data-i18n="easy-ordering-desc">Order anytime, anywhere on all devices.</p></div>
            <div class="feature-card"><i class="fas fa-headset feature-icon"></i><h3 data-i18n="24-7-support">24/7 Support</h3><p data-i18n="24-7-support-desc">Our support team is always ready to help.</p></div>
            <div class="feature-card"><i class="fas fa-leaf feature-icon"></i><h3 data-i18n="fresh-quality">Fresh & Quality</h3><p data-i18n="fresh-quality-desc">Prepared with the freshest ingredients daily.</p></div>
            <div class="feature-card"><i class="fas fa-gift feature-icon"></i><h3 data-i18n="loyalty-rewards">Loyalty Rewards</h3><p data-i18n="loyalty-rewards-desc">Get loyalty points on every order.</p></div>
        </div>
    </div>
</section>
<?php endif; ?>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section"><h4>🍴 About FOODTIME</h4><a href="#">About Us</a><a href="#">Blog</a><a href="#">Careers</a></div>
            <div class="footer-section"><h4 data-i18n="support">Support</h4><a href="customer_chat.php">Help Center</a><a href="customer_chat.php">Contact Us</a><a href="#">FAQ</a></div>
            <div class="footer-section"><h4 data-i18n="legal">Legal</h4><a href="#">Privacy Policy</a><a href="#">Terms & Conditions</a><a href="#">Refund Policy</a></div>
            <div class="footer-section"><h4 data-i18n="follow-us">Follow Us</h4><div class="social-links"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
        </div>
        <div class="footer-bottom"><p>&copy; 2026 FOODTIME. All rights reserved.</p></div>
    </div>
</footer>

<script src="js/translations.js"></script>
<script src="js/theme.js"></script>
<script src="js/language.js"></script>
<script src="js/cart.js"></script>
<script>
const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

let wlToastTimer = null;
function showWlToast(msg, type = 'success') {
    const toast = document.getElementById('wlToast');
    const msgEl = document.getElementById('wlToastMsg');
    const icon  = toast.querySelector('i');
    msgEl.textContent = msg;
    toast.className = 'wl-toast show ' + type;
    icon.className = type === 'success' ? 'fas fa-heart' : 'fas fa-exclamation-circle';
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
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
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
        alreadyInCart = cart
            .filter(i => i.id == pid)
            .reduce((sum, i) => sum + (parseInt(i.quantity) || 1), 0);
    }
    const remaining = Math.max(0, 50 - alreadyInCart);

    let current = parseInt(qtySpan.textContent) || 1;
    current = Math.min(remaining, Math.max(1, current + delta));
    qtySpan.textContent = current;
    minusBtn.disabled = (current === 1);
    plusBtn.disabled  = (current >= remaining);
}

function handleAddToCart(event, productId, productName, productPrice) {
    event.preventDefault(); event.stopPropagation();
    if (!isLoggedIn) { showLoginModal(); return false; }
    const card    = event.currentTarget.closest('.product-card');
    const qtySpan = card ? card.querySelector('.qty-value') : null;
    openCustomizeModal(productId, productName, productPrice, qtySpan ? parseInt(qtySpan.textContent) || 1 : 1);
}

function handleAddToCartWithQty(event, productId, productName, productPrice, btn) {
    event.preventDefault(); event.stopPropagation();
    if (!isLoggedIn) { showLoginModal(); return false; }
    const controls = btn.closest('.product-info').querySelector('.quantity-controls');
    const qty = controls ? Math.max(1, parseInt(controls.querySelector('.qty-value').textContent) || 1) : 1;
    openCustomizeModal(productId, productName, productPrice, qty);
}

function showLoginModal()  { const m = document.getElementById('loginModal'); if (m) m.classList.add('show'); }
function closeLoginModal() { const m = document.getElementById('loginModal'); if (m) m.classList.remove('show'); }
window.onclick = function(e) { const m = document.getElementById('loginModal'); if (e.target === m) closeLoginModal(); };

function scrollMenu(direction) {
    document.getElementById('categoriesMenu').scrollBy({ left: direction === 'left' ? -250 : 250, behavior: 'smooth' });
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft')  scrollMenu('left');
    if (e.key === 'ArrowRight') scrollMenu('right');
});

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
    const badge = document.getElementById('navWishlistBadge');
    if (!badge) return;
    let count = (parseInt(badge.textContent) || 0) + delta;
    if (count <= 0) {
        badge.style.display = 'none';
        badge.textContent = '0';
    } else {
        badge.style.display = '';
        badge.textContent = count;
    }
}
</script>

<style>
#float-chat-btn { position: fixed; bottom: 28px; right: 28px; z-index: 9000; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #d4650f, #f0874a); color: #fff; border: none; box-shadow: 0 4px 20px rgba(212,101,15,.55); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 23px; transition: transform .2s, box-shadow .2s; }
#float-chat-btn:hover { transform: scale(1.10) translateY(-2px); box-shadow: 0 8px 26px rgba(212,101,15,.65); }
#float-chat-btn::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: rgba(212,101,15,.30); animation: ftPulse 2s ease-out infinite; pointer-events: none; }
@keyframes ftPulse { 0% { transform: scale(1); opacity: .8; } 70% { transform: scale(1.6); opacity: 0; } 100% { transform: scale(1.6); opacity: 0; } }
#float-chat-btn .ft-tip { position: absolute; right: 70px; background: #1f2937; color: #fff; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 7px; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity .18s; }
#float-chat-btn:hover .ft-tip { opacity: 1; }
#float-chat-btn .ft-dot { position: absolute; top: 4px; right: 4px; width: 14px; height: 14px; background: #22c55e; border: 2.5px solid #fff; border-radius: 50%; }
#chatGuestModal { display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,.52); align-items: center; justify-content: center; }
#chatGuestModal.show { display: flex; }
.cgm-box { background: #fff; border-radius: 16px; padding: 34px 32px 28px; width: 100%; max-width: 400px; box-shadow: 0 8px 40px rgba(0,0,0,.22); position: relative; animation: cgmIn .22s ease; }
@keyframes cgmIn { from { transform: scale(.92); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.cgm-close { position: absolute; top: 14px; right: 16px; background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; }
.cgm-close:hover { color: #374151; }
.cgm-icon { width: 58px; height: 58px; border-radius: 50%; background: linear-gradient(135deg,#d4650f,#f0874a); color: #fff; font-size: 26px; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
.cgm-box h3 { text-align: center; font-size: 18px; font-weight: 800; color: #1f2937; margin: 0 0 4px; }
.cgm-box p { text-align: center; font-size: 13px; color: #6b7280; margin: 0 0 20px; }
.cgm-label { display: block; font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 7px; }
.cgm-input { width: 100%; padding: 11px 14px; border: 2px solid #e5e7eb; border-radius: 9px; font-size: 14px; outline: none; box-sizing: border-box; transition: border .18s; }
.cgm-input:focus { border-color: #d4650f; }
.cgm-error { display: none; margin-top: 8px; padding: 9px 13px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; color: #991b1b; font-size: 13px; font-weight: 600; align-items: center; gap: 7px; }
.cgm-error.show { display: flex; }
.cgm-error.info { background: #fef3c7; border-color: #fde68a; color: #92400e; }
.cgm-btn { margin-top: 16px; width: 100%; padding: 12px; background: linear-gradient(135deg,#d4650f,#f0874a); color: #fff; border: none; border-radius: 9px; font-size: 15px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: opacity .18s; }
.cgm-btn:hover { opacity: .88; }
.cgm-btn:disabled { opacity: .55; cursor: not-allowed; }
.cgm-divider { text-align: center; font-size: 12px; color: #9ca3af; margin: 14px 0 10px; }
.cgm-login-link { display: block; text-align: center; font-size: 13px; color: #d4650f; font-weight: 700; text-decoration: none; }
.cgm-login-link:hover { text-decoration: underline; }
</style>

<div id="chatGuestModal" role="dialog" aria-modal="true">
    <div class="cgm-box">
        <button class="cgm-close" onclick="closeChatModal()">&times;</button>
        <div class="cgm-icon"><i class="fas fa-headset"></i></div>
        <h3>Live Support Chat</h3>
        <p>Enter your registered username to continue.</p>
        <label class="cgm-label"><i class="fas fa-user" style="color:#d4650f"></i> Username</label>
        <input type="text" id="cgmUsername" class="cgm-input" placeholder="Enter your username..." maxlength="80" autocomplete="off">
        <div class="cgm-error" id="cgmError"><i class="fas fa-exclamation-circle"></i><span id="cgmErrorText"></span></div>
        <button class="cgm-btn" id="cgmSubmitBtn" onclick="verifyChatUser()"><i class="fas fa-comments"></i> Start Chat</button>
        <div class="cgm-divider">— or —</div>
        <a href="login.php" class="cgm-login-link"><i class="fas fa-sign-in-alt"></i> Login for full access</a>
    </div>
</div>
<button id="float-chat-btn" onclick="openChatEntry()" title="Live Support Chat" type="button">
    <i class="fas fa-headset"></i>
    <span class="ft-tip">💬 Support Chat</span>
    <?php if ($is_logged_in): ?><span class="ft-dot"></span><?php endif; ?>
</button>
<script>
const CHAT_IS_LOGGED_IN  = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
const CHAT_USER_POSITION = '<?php echo isset($_SESSION['position']) ? htmlspecialchars($_SESSION['position']) : ''; ?>';
function openChatEntry() {
    if (CHAT_IS_LOGGED_IN) { window.location.href = CHAT_USER_POSITION === 'admin' ? 'admin_chat.php' : 'customer_chat.php'; }
    else { document.getElementById('chatGuestModal').classList.add('show'); document.getElementById('cgmUsername').focus(); }
}
function closeChatModal() { document.getElementById('chatGuestModal').classList.remove('show'); document.getElementById('cgmError').className = 'cgm-error'; document.getElementById('cgmUsername').value = ''; }
function verifyChatUser() {
    const username = document.getElementById('cgmUsername').value.trim();
    if (!username) { showCgmError('Please enter your username.'); return; }
    const btn = document.getElementById('cgmSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    const fd = new FormData(); fd.append('username', username);
    fetch('verify_chat_user.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) { showCgmError('Account found! Opening chat...', true); setTimeout(() => { window.location.href = 'customer_chat.php?guest_token=' + encodeURIComponent(res.token); }, 600); }
            else { showCgmError(res.message); btn.disabled = false; btn.innerHTML = '<i class="fas fa-comments"></i> Start Chat'; }
        })
        .catch(() => { showCgmError('Network error. Please try again.'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-comments"></i> Start Chat'; });
}
function showCgmError(msg, isInfo = false) {
    const box = document.getElementById('cgmError');
    box.className = 'cgm-error show' + (isInfo ? ' info' : '');
    document.getElementById('cgmErrorText').textContent = msg;
}
document.getElementById('cgmUsername').addEventListener('keydown', e => { if (e.key === 'Enter') verifyChatUser(); });
document.getElementById('chatGuestModal').addEventListener('click', e => { if (e.target === document.getElementById('chatGuestModal')) closeChatModal(); });
</script>
<?php include 'customizeModal.php'; ?>
</body>
</html>