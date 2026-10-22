<?php
// wishlist.php
session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");
include 'base.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id  = $_SESSION['user_id'];
$user_name = '';
if (isset($_SESSION['user_name']))     $user_name = htmlspecialchars($_SESSION['user_name']);
elseif (isset($_SESSION['username'])) $user_name = htmlspecialchars($_SESSION['username']);

// ── Fetch profile picture for nav ──
$nav_pic     = null;
$nav_initial = strtoupper(mb_substr($user_name, 0, 1)) ?: '?';
$nav_row     = null;
$nav_stmt = mysqli_prepare($connect,
    "SELECT profile_picture, username FROM users WHERE userid = ?");
mysqli_stmt_bind_param($nav_stmt, 's', $user_id);
mysqli_stmt_execute($nav_stmt);
$nav_res = mysqli_stmt_get_result($nav_stmt);
$nav_row = mysqli_fetch_assoc($nav_res);
mysqli_stmt_close($nav_stmt);
if ($nav_row) {
    $nav_initial = strtoupper(mb_substr($nav_row['username'] ?? $user_name, 0, 1));
    if (!empty($nav_row['profile_picture']) &&
        file_exists('uploads/profiles/' . $nav_row['profile_picture'])) {
        $nav_pic = 'uploads/profiles/' . htmlspecialchars($nav_row['profile_picture']);
    }
}

// ── Fetch wishlist items (include stock) ──
$wl_stmt = mysqli_prepare($connect,
    "SELECT w.id AS wl_id, w.created_at,
            p.productid, p.name, p.price, p.image, p.is_available, p.stock,
            c.category_name
     FROM wishlist w
     JOIN products p  ON p.productid  = w.productid
     LEFT JOIN categories c ON c.categoryid = p.categoryid
     WHERE w.userid = ?
     ORDER BY w.created_at DESC");
mysqli_stmt_bind_param($wl_stmt, 's', $user_id);
mysqli_stmt_execute($wl_stmt);
$wl_result = mysqli_stmt_get_result($wl_stmt);
$wl_items  = [];
while ($row = mysqli_fetch_assoc($wl_result)) $wl_items[] = $row;
mysqli_stmt_close($wl_stmt);

$total_items = count($wl_items);

function getModalCategory($cat_name) {
    $cat_lower = strtolower($cat_name ?? '');
    if (in_array($cat_lower, ['desserts','dessert']))                                               return 'desert';
    if (in_array($cat_lower, ['beverages','drinks','juice','coffee','tea','water','beverage']))     return 'beverage';
    return 'default';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Wishlist — FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/index.css">
<style>
/* ===== NAV STYLES ===== */
.nav-profile-wrap{position:relative;display:flex;align-items:center}
.nav-avatar{display:flex;align-items:center;gap:6px;cursor:pointer;padding:3px 8px 3px 3px;border-radius:50px;transition:background .15s;user-select:none;border:none;background:none}
.nav-avatar:hover{background:#fff0e6}
[data-theme="dark"] .nav-avatar:hover{background:rgba(212,101,15,.18)}
.nav-avatar-pic,.nav-avatar-initial{width:38px;height:38px;border-radius:50%;border:2.5px solid #d4650f;object-fit:cover;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.nav-avatar-pic{display:block}
.nav-avatar-initial{background:linear-gradient(135deg,#d4650f,#f0874a);color:#fff;font-size:16px;font-weight:800}
.nav-avatar-caret{font-size:11px;color:#65676b;transition:transform .2s}
[data-theme="dark"] .nav-avatar-caret{color:#b0b3b8}
.nav-profile-wrap.open .nav-avatar-caret{transform:rotate(180deg)}
.nav-dropdown{display:none;position:absolute;top:calc(100% + 10px);right:0;background:#fff;border:1px solid #e4e6eb;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.14);min-width:250px;z-index:500;overflow:hidden;animation:ddIn .18s ease}
[data-theme="dark"] .nav-dropdown{background:#242526;border-color:#3a3b3c;box-shadow:0 8px 32px rgba(0,0,0,.35)}
@keyframes ddIn{from{opacity:0;transform:translateY(-8px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.nav-profile-wrap.open .nav-dropdown{display:block}
.nav-dropdown-header{display:flex;align-items:center;gap:11px;padding:14px 16px;background:#fff8f2}
[data-theme="dark"] .nav-dropdown-header{background:rgba(212,101,15,.10)}
.nav-dd-avatar{width:46px;height:46px;border-radius:50%;border:2.5px solid #d4650f;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.nav-dd-avatar img{width:100%;height:100%;object-fit:cover;display:block}
.nav-dd-avatar-initial{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#d4650f,#f0874a);color:#fff;font-size:20px;font-weight:900}
.nav-dd-name{font-size:14px;font-weight:800;color:#1c1e21;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px}
[data-theme="dark"] .nav-dd-name{color:#e4e6eb}
.nav-dd-sub{font-size:12px;color:#8a8d91;margin-top:1px}
.nav-dropdown-divider{height:1px;background:#e4e6eb;margin:4px 0}
[data-theme="dark"] .nav-dropdown-divider{background:#3a3b3c}
.nav-dd-item{display:flex;align-items:center;gap:11px;padding:10px 16px;font-size:14px;font-weight:600;color:#1c1e21;text-decoration:none;transition:background .12s;cursor:pointer}
[data-theme="dark"] .nav-dd-item{color:#e4e6eb}
.nav-dd-item i{width:16px;color:#d4650f;font-size:14px;flex-shrink:0}
.nav-dd-item:hover{background:#fff0e6}
[data-theme="dark"] .nav-dd-item:hover{background:rgba(212,101,15,.13)}
.nav-dd-logout{color:#dc2626!important}
.nav-dd-logout i{color:#dc2626!important}
.nav-dd-logout:hover{background:#fee2e2!important}
[data-theme="dark"] .nav-dd-logout:hover{background:rgba(220,38,38,.15)!important}
.nav-dd-settings{display:flex;align-items:center;justify-content:space-between;padding:8px 16px;gap:8px}
.nav-dd-settings-label{font-size:12px;font-weight:700;color:#8a8d91;display:flex;align-items:center;gap:6px}
.nav-dd-settings-label i{color:#d4650f;font-size:12px}
.nav-dd-settings-controls{display:flex;align-items:center;gap:6px}
.dd-lang-wrap{position:relative}
.dd-lang-btn{display:flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;border:1.5px solid #e4e6eb;background:#fff;cursor:pointer;font-size:12px;font-weight:700;color:#374151;transition:border-color .15s,background .15s}
[data-theme="dark"] .dd-lang-btn{background:#3a3b3c;border-color:#555;color:#e4e6eb}
.dd-lang-btn:hover{border-color:#d4650f;background:#fff0e6}
[data-theme="dark"] .dd-lang-btn:hover{background:rgba(212,101,15,.18)}
.dd-lang-btn i{color:#d4650f;font-size:11px}
.dd-lang-menu{display:none;position:absolute;bottom:calc(100% + 6px);right:0;background:#fff;border:1px solid #e4e6eb;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.12);min-width:150px;z-index:600;overflow:hidden}
[data-theme="dark"] .dd-lang-menu{background:#242526;border-color:#3a3b3c}
.dd-lang-wrap.open .dd-lang-menu{display:block}
.dd-lang-menu a{display:block;padding:9px 14px;font-size:13px;font-weight:600;color:#1c1e21;text-decoration:none;transition:background .12s}
[data-theme="dark"] .dd-lang-menu a{color:#e4e6eb}
.dd-lang-menu a:hover{background:#fff0e6;color:#d4650f}
[data-theme="dark"] .dd-lang-menu a:hover{background:rgba(212,101,15,.13)}
.dd-theme-btn{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1.5px solid #e4e6eb;background:#fff;cursor:pointer;font-size:14px;color:#374151;transition:border-color .15s,background .15s,color .15s}
[data-theme="dark"] .dd-theme-btn{background:#3a3b3c;border-color:#555;color:#e4e6eb}
.dd-theme-btn:hover{border-color:#d4650f;background:#fff0e6;color:#d4650f}
.btn-chat-nav{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:50px;font-size:13px;font-weight:700;text-decoration:none;background:#fff0e6;color:#d4650f;border:1.5px solid #d4650f;transition:background .15s,color .15s}
.btn-chat-nav:hover{background:#d4650f;color:#fff}

/* ===== WISHLIST PAGE STYLES ===== */
.wl-hero {
    background: linear-gradient(135deg, #fff8f2 0%, #ffe8d6 100%);
    padding: 50px 0 40px;
    border-bottom: 1px solid #f0d9c8;
}
[data-theme="dark"] .wl-hero {
    background: linear-gradient(135deg, #1a1a1a 0%, #2a1a0e 100%);
    border-bottom-color: #3a2a1a;
}
.wl-hero-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.wl-hero-title {
    display: flex;
    align-items: center;
    gap: 14px;
}
.wl-hero-icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, #d4650f, #f0874a);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 16px rgba(212,101,15,.35);
}
.wl-hero-title h1 {
    font-size: 28px; font-weight: 900;
    color: #1c1e21; margin: 0;
}
[data-theme="dark"] .wl-hero-title h1 { color: #e4e6eb; }
.wl-hero-title p {
    font-size: 14px; color: #65676b; margin: 4px 0 0;
}
[data-theme="dark"] .wl-hero-title p { color: #b0b3b8; }
.wl-hero-actions { display: flex; align-items: center; gap: 10px; }
.wl-count-badge {
    background: #d4650f;
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 50px;
}
.btn-clear-all {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 50px;
    border: 1.5px solid #dc2626;
    background: transparent; color: #dc2626;
    font-size: 13px; font-weight: 700;
    cursor: pointer; transition: background .15s, color .15s;
}
.btn-clear-all:hover { background: #dc2626; color: #fff; }
.btn-browse {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 50px;
    background: #d4650f; color: #fff;
    font-size: 13px; font-weight: 700;
    text-decoration: none; transition: background .15s;
}
.btn-browse:hover { background: #b8540c; }

/* Grid */
.wl-section { padding: 40px 0 60px; }
.wl-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 22px;
}

/* Cards */
.wl-card {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    transition: transform .2s, box-shadow .2s;
    position: relative;
}
[data-theme="dark"] .wl-card { background: #242526; }
.wl-card:hover { transform: translateY(-4px); box-shadow: 0 8px 28px rgba(0,0,0,.14); }

.wl-card-unavailable { opacity: .65; }
.wl-unavailable-badge {
    position: absolute; top: 12px; left: 12px;
    background: #6b7280; color: #fff;
    font-size: 11px; font-weight: 700;
    padding: 4px 10px; border-radius: 50px;
    z-index: 2;
}

/* Heart button */
.wl-heart-btn {
    position: absolute; top: 12px; right: 12px;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,.9);
    border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; color: #d4650f;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    transition: background .15s, transform .15s;
    z-index: 3;
}
[data-theme="dark"] .wl-heart-btn { background: rgba(36,37,38,.9); }
.wl-heart-btn:hover { background: #fee2e2; transform: scale(1.15); }
.wl-heart-btn.removing { animation: heartPop .3s ease forwards; }
@keyframes heartPop {
    0%  { transform: scale(1); }
    50% { transform: scale(1.4); }
    100%{ transform: scale(0); opacity: 0; }
}

/* Image */
.wl-card-img {
    width: 100%; height: 190px;
    object-fit: cover; display: block;
    background: #f5f5f5;
}
.wl-card-img-placeholder {
    width: 100%; height: 190px;
    background: linear-gradient(135deg, #f5f5f5, #e8e8e8);
    display: flex; align-items: center; justify-content: center;
    font-size: 48px; color: #ccc;
}
[data-theme="dark"] .wl-card-img-placeholder { background: linear-gradient(135deg,#3a3b3c,#2d2e2f); }

/* Body */
.wl-card-body { padding: 16px; }
.wl-card-category {
    font-size: 11px; font-weight: 700; color: #d4650f;
    text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: 4px;
}
.wl-card-name {
    font-size: 15px; font-weight: 800;
    color: #1c1e21; margin: 0 0 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
[data-theme="dark"] .wl-card-name { color: #e4e6eb; }
.wl-card-meta {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.wl-card-price {
    font-size: 17px; font-weight: 900; color: #d4650f;
}

/* Stock badges — same as normal cards */
.stock-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 800; margin-bottom: 8px;
}
.stock-badge-low { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.stock-badge-out { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
[data-theme="dark"] .stock-badge-low { background: rgba(251,191,36,.15); color: #fde68a; border-color: rgba(251,191,36,.3); }
[data-theme="dark"] .stock-badge-out { background: rgba(239,68,68,.15); color: #fca5a5; border-color: rgba(239,68,68,.3); }

/* Quantity controls — SAME format as normal product cards */
.quantity-controls {
    display: flex; align-items: center; justify-content: center;
    gap: 0; background: #f5f5f5; border-radius: 25px;
    padding: 4px; margin-bottom: 10px;
}
[data-theme="dark"] .quantity-controls { background: #3a3b3c; }
.qty-btn {
    width: 32px; height: 32px; border-radius: 50%;
    border: none; background: #fff; color: #d4650f;
    font-size: 16px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 1px 4px rgba(0,0,0,.12);
    transition: background .15s, color .15s, transform .1s; flex-shrink: 0;
}
[data-theme="dark"] .qty-btn { background: #555; color: #f0874a; }
.qty-btn:hover:not(:disabled) { background: #d4650f; color: #fff; transform: scale(1.08); }
.qty-btn:disabled { opacity: 0.35; cursor: not-allowed; transform: none !important; }
.qty-display {
    width: 40px; text-align: center; font-size: 15px;
    font-weight: 700; color: #333; background: transparent;
    border: none; outline: none;
}
[data-theme="dark"] .qty-display { color: #e4e6eb; }

/* Add to cart */
.wl-atc-btn {
    width: 100%; padding: 10px;
    border-radius: 25px; font-size: 14px; font-weight: 700;
    background: #d4650f; color: #fff;
    border: none; cursor: pointer;
    transition: background .15s, transform .1s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.wl-atc-btn:hover:not(:disabled) { background: #b8540c; transform: translateY(-1px); }
.wl-atc-btn:disabled { background: #9ca3af; cursor: not-allowed; }

/* Added date footer */
.wl-card-footer {
    padding: 8px 16px 12px;
    font-size: 11px; color: #8a8d91;
    border-top: 1px solid #f0f0f0;
    display: flex; align-items: center; gap: 5px;
}
[data-theme="dark"] .wl-card-footer { border-top-color: #3a3b3c; }

/* Empty state */
.wl-empty {
    text-align: center;
    padding: 80px 20px;
    grid-column: 1 / -1;
}
.wl-empty-icon {
    font-size: 72px;
    margin-bottom: 20px;
    display: block;
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
    0%,100% { transform: scale(1); }
    50%      { transform: scale(1.08); }
}
.wl-empty h2 { font-size: 24px; font-weight: 800; color: #1c1e21; margin-bottom: 10px; }
[data-theme="dark"] .wl-empty h2 { color: #e4e6eb; }
.wl-empty p  { color: #65676b; margin-bottom: 24px; }
[data-theme="dark"] .wl-empty p { color: #b0b3b8; }

/* Toast */
.wl-toast {
    position: fixed; bottom: 28px; left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: #1c1e21; color: #fff;
    padding: 12px 22px; border-radius: 50px;
    font-size: 14px; font-weight: 600;
    box-shadow: 0 4px 20px rgba(0,0,0,.25);
    opacity: 0; pointer-events: none;
    transition: opacity .25s, transform .25s;
    z-index: 9999; display: flex; align-items: center; gap: 8px;
    white-space: nowrap;
}
[data-theme="dark"] .wl-toast { background: #e4e6eb; color: #1c1e21; }
.wl-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.wl-toast.success i { color: #22c55e; }
.wl-toast.error   i { color: #ef4444; }

/* Card remove animation */
.wl-card.fade-out {
    animation: cardFadeOut .4s ease forwards;
}
@keyframes cardFadeOut {
    0%  { opacity:1; transform: scale(1); }
    100%{ opacity:0; transform: scale(.85); }
}
</style>
</head>
<body>

<!-- Login Required Modal -->
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

<!-- Toast -->
<div class="wl-toast" id="wlToast">
    <i class="fas fa-check-circle"></i>
    <span id="wlToastMsg">Done</span>
</div>

<!-- ===== HEADER ===== -->
<header>
    <div class="container">
        <div class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-burger"></i><span>FOODTIME</span>
            </a>
            <ul class="nav-menu">
                <li><a href="index.php#categories">Category</a></li>
                <li><a href="menu.php">Menu</a></li>
                <li><a href="index.php#about">About</a></li>
            </ul>
            <form method="GET" action="index.php" class="search-form">
                <input type="text" name="keyword" placeholder="Search food...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <div class="auth-buttons">
                <a href="order_history.php" class="btn btn-primary"><i class="fas fa-history"></i> Orders</a>
                <a href="cart.php"          class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Cart</a>
                <a href="wishlist.php"      class="btn btn-primary" style="background:#d4650f;border-color:#d4650f;">
                    <i class="fas fa-heart"></i> Wishlist
                    <?php if($total_items > 0): ?>
                        <span id="navWishlistBadge" style="background:#fff;color:#d4650f;border-radius:50px;font-size:11px;font-weight:900;padding:1px 7px;margin-left:2px;"><?php echo $total_items; ?></span>
                    <?php else: ?>
                        <span id="navWishlistBadge" style="background:#fff;color:#d4650f;border-radius:50px;font-size:11px;font-weight:900;padding:1px 7px;margin-left:2px;display:none;"><?php echo $total_items; ?></span>
                    <?php endif; ?>
                </a>
                <a href="customer_chat.php" class="btn-chat-nav"><i class="fas fa-headset"></i> Support</a>

                <!-- Profile Dropdown -->
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
                            </div>
                        </div>
                        <div class="nav-dropdown-divider"></div>
                        <a href="profile.php"       class="nav-dd-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
                        <a href="order_history.php" class="nav-dd-item"><i class="fas fa-history"></i> Order History</a>
                        <a href="cart.php"          class="nav-dd-item"><i class="fas fa-shopping-cart"></i> My Cart</a>
                        <a href="wishlist.php"      class="nav-dd-item" style="color:#d4650f!important"><i class="fas fa-heart" style="color:#d4650f"></i> My Wishlist</a>
                        <a href="customer_chat.php" class="nav-dd-item"><i class="fas fa-headset"></i> Support Chat</a>
                        <div class="nav-dropdown-divider"></div>
                        <div class="nav-dd-settings">
                            <div class="nav-dd-settings-label"><i class="fas fa-sliders-h"></i> Preferences</div>
                            <div class="nav-dd-settings-controls">
                                <div class="dd-lang-wrap" id="ddLangWrap">
                                    <button class="dd-lang-btn" type="button" onclick="toggleDdLang(event)">
                                        <i class="fas fa-globe"></i><span id="ddLangLabel">EN</span>
                                    </button>
                                    <div class="dd-lang-menu" id="ddLangMenu">
                                        <a href="#" onclick="setDdLang('en','EN',event)">🇬🇧 English</a>
                                        <a href="#" onclick="setDdLang('ms','MY',event)">🇲🇾 Bahasa Melayu</a>
                                        <a href="#" onclick="setDdLang('zh','中文',event)">🇨🇳 中文</a>
                                    </div>
                                </div>
                                <button class="dd-theme-btn" id="ddThemeBtn" type="button">
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

<!-- ===== HERO ===== -->
<div class="wl-hero">
    <div class="container">
        <div class="wl-hero-inner">
            <div class="wl-hero-title">
                <div class="wl-hero-icon">❤️</div>
                <div>
                    <h1>My Wishlist</h1>
                    <p id="wlHeroSubtitle"><?php echo $total_items > 0 ? "$total_items saved item" . ($total_items > 1 ? 's' : '') : 'Your favourite items, saved for later'; ?></p>
                </div>
            </div>
            <div class="wl-hero-actions" id="wlHeroActions">
                <?php if ($total_items > 0): ?>
                    <span class="wl-count-badge" id="wlCountBadge"><?php echo $total_items; ?> items</span>
                    <button class="btn-clear-all" id="wlClearAllBtn" onclick="clearAllWishlist()" type="button">
                        <i class="fas fa-trash-alt"></i> Clear All
                    </button>
                <?php endif; ?>
                <a href="menu.php" class="btn-browse">
                    <i class="fas fa-utensils"></i> Browse Menu
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ===== WISHLIST GRID ===== -->
<section class="wl-section">
    <div class="container">
        <div class="wl-grid" id="wlGrid">
            <?php if (empty($wl_items)): ?>
                <div class="wl-empty">
                    <span class="wl-empty-icon">💔</span>
                    <h2>Your wishlist is empty</h2>
                    <p>Save your favourite dishes by clicking the heart icon on any menu item.</p>
                    <a href="menu.php" class="btn-browse">
                        <i class="fas fa-utensils"></i> Explore Menu
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($wl_items as $item):
                    $prod_id      = $item['productid'];
                    $prod_name    = htmlspecialchars($item['name']);
                    $prod_price   = number_format((float)$item['price'], 2);
                    $prod_price_r = number_format((float)$item['price'], 2, '.', '');
                    $prod_cat     = htmlspecialchars($item['category_name'] ?? '');
                    $prod_modal   = getModalCategory($item['category_name'] ?? '');
                    $prod_img     = !empty($item['image']) ? htmlspecialchars($item['image']) : '';
                    $available    = (bool)$item['is_available'];
                    $prod_stock   = (int)$item['stock'];
                    $saved_date   = date('d M Y', strtotime($item['created_at']));

                    // Stock status same logic as normal cards
                    $stock_status = 'ok';
                    if ($prod_stock <= 0)  $stock_status = 'out';
                    elseif ($prod_stock <= 10) $stock_status = 'low';
                    $is_oos = ($stock_status === 'out') || !$available;
                ?>
                <div class="wl-card <?php echo $is_oos ? 'wl-card-unavailable' : ''; ?>"
                     id="wlCard_<?php echo $prod_id; ?>"
                     data-prodid="<?php echo $prod_id; ?>"
                     data-stock="<?php echo $prod_stock; ?>"
                     data-price="<?php echo $prod_price_r; ?>"
                     data-available="<?php echo $available ? '1' : '0'; ?>">

                    <?php if (!$available): ?>
                        <div class="wl-unavailable-badge">Unavailable</div>
                    <?php endif; ?>

                    <!-- Heart (remove) button -->
                    <button class="wl-heart-btn" id="heartBtn_<?php echo $prod_id; ?>"
                            onclick="removeFromWishlist('<?php echo $prod_id; ?>')"
                            title="Remove from wishlist" type="button">
                        <i class="fas fa-heart"></i>
                    </button>

                    <!-- Image -->
                    <?php if ($prod_img !== ''): ?>
                        <img src="uploads/<?php echo $prod_img; ?>"
                             alt="<?php echo $prod_name; ?>" class="wl-card-img">
                    <?php else: ?>
                        <div class="wl-card-img-placeholder">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>

                    <!-- Body -->
                    <div class="wl-card-body">
                        <?php if ($prod_cat): ?>
                            <div class="wl-card-category"><?php echo $prod_cat; ?></div>
                        <?php endif; ?>
                        <h3 class="wl-card-name"><?php echo $prod_name; ?></h3>
                        <div class="wl-card-meta">
                            <span class="wl-card-price">RM <?php echo $prod_price; ?></span>
                        </div>

                        <!-- Stock badge — same as normal cards -->
                        <?php if ($stock_status === 'low' && $available): ?>
                            <div class="stock-badge stock-badge-low">
                                <i class="fas fa-exclamation-triangle"></i> Only <?php echo $prod_stock; ?> left!
                            </div>
                        <?php elseif ($is_oos): ?>
                            <div class="stock-badge stock-badge-out">
                                <i class="fas fa-ban"></i> <?php echo !$available ? 'Unavailable' : 'Out of Stock'; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$is_oos): ?>
                        <!-- Quantity controls — same format as normal product cards, stock-aware -->
                        <div class="quantity-controls">
                            <button class="qty-btn" type="button"
                                    onclick="wlChangeQty(this, -1)"
                                    disabled>−</button>
                            <span class="qty-display">1</span>
                            <button class="qty-btn" type="button"
                                    onclick="wlChangeQty(this, 1)"
                                    <?php echo $prod_stock <= 1 ? 'disabled' : ''; ?>>+</button>
                        </div>
                        <?php endif; ?>

                        <!-- Add to Cart -->
                        <button class="wl-atc-btn"
                                <?php echo $is_oos ? 'disabled' : ''; ?>
                                onclick="wlAddToCart(event,'<?php echo $prod_id; ?>','<?php echo addslashes($prod_name); ?>','<?php echo $prod_price_r; ?>','<?php echo $prod_modal; ?>',this)"
                                type="button">
                            <i class="fas fa-shopping-cart"></i>
                            <?php echo $is_oos ? ((!$available) ? 'Unavailable' : 'Out of Stock') : 'Add to Cart'; ?>
                        </button>
                    </div>

                    <div class="wl-card-footer">
                        <i class="fas fa-clock"></i> Saved on <?php echo $saved_date; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section">
                <h4>🍴 About FOODTIME</h4>
                <a href="#">About Us</a><a href="#">Blog</a><a href="#">Careers</a>
            </div>
            <div class="footer-section">
                <h4>Support</h4>
                <a href="customer_chat.php">Help Center</a>
                <a href="customer_chat.php">Contact Us</a>
                <a href="#">FAQ</a>
            </div>
            <div class="footer-section">
                <h4>Legal</h4>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms & Conditions</a>
                <a href="#">Refund Policy</a>
            </div>
            <div class="footer-section">
                <h4>Follow Us</h4>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 FOODTIME. All rights reserved.</p>
        </div>
    </div>
</footer>

<script src="js/translations.js"></script>
<script src="js/theme.js"></script>
<script src="js/language.js"></script>
<script src="js/cart.js"></script>

<script>
const MAX_QTY_PER_PRODUCT = 50;

/* ── Toast ── */
let toastTimer = null;
function showToast(msg, type = 'success') {
    const toast = document.getElementById('wlToast');
    const msgEl = document.getElementById('wlToastMsg');
    const icon  = toast.querySelector('i');
    msgEl.textContent = msg;
    toast.className = 'wl-toast show ' + type;
    icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
}

/* ─────────────────────────────────────────────────
   Qty controls — stock-aware + cart-aware
   Same rules as normal product cards:
     max = min(50, stock) - already_in_cart_for_this_product
   ───────────────────────────────────────────────── */
function wlChangeQty(btn, delta) {
    const card     = btn.closest('.wl-card');
    const prodId   = card.dataset.prodid;
    const stock    = parseInt(card.dataset.stock) || 0;
    const controls = btn.closest('.quantity-controls');
    const display  = controls.querySelector('.qty-display');
    const minusBtn = controls.querySelector('.qty-btn:first-child');
    const plusBtn  = controls.querySelector('.qty-btn:last-child');

    // How many of this product are already in the cart?
    const cart    = JSON.parse(localStorage.getItem('cart')) || [];
    const inCart  = cart
        .filter(i => i.id == prodId)
        .reduce((sum, i) => sum + (parseInt(i.quantity) || 1), 0);

    // Combined ceiling: min(MAX, stock)
    const ceiling = Math.min(MAX_QTY_PER_PRODUCT, stock);
    // Max we can add from here = ceiling minus what's already in cart
    const maxAdd  = Math.max(1, ceiling - inCart);

    let current = parseInt(display.textContent) || 1;
    current = Math.min(maxAdd, Math.max(1, current + delta));
    display.textContent = current;

    minusBtn.disabled = (current <= 1);
    plusBtn.disabled  = (current >= maxAdd);
}

/* Recompute all card qty limits on page init (in case cart changed) */
function initAllQtyControls() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];

    document.querySelectorAll('.wl-card').forEach(card => {
        const prodId   = card.dataset.prodid;
        const stock    = parseInt(card.dataset.stock) || 0;
        const controls = card.querySelector('.quantity-controls');
        if (!controls) return; // out-of-stock cards have no qty controls

        const display  = controls.querySelector('.qty-display');
        const minusBtn = controls.querySelector('.qty-btn:first-child');
        const plusBtn  = controls.querySelector('.qty-btn:last-child');

        const inCart  = cart
            .filter(i => i.id == prodId)
            .reduce((sum, i) => sum + (parseInt(i.quantity) || 1), 0);

        const ceiling = Math.min(MAX_QTY_PER_PRODUCT, stock);
        const maxAdd  = Math.max(1, ceiling - inCart);

        // Clamp current display value
        let current = parseInt(display.textContent) || 1;
        current = Math.min(current, maxAdd);
        display.textContent = current;

        minusBtn.disabled = (current <= 1);
        plusBtn.disabled  = (current >= maxAdd);

        // If stock is 1 and 1 already in cart, disable both and show stock warning
        if (maxAdd <= 1 && inCart >= ceiling) {
            plusBtn.disabled = true;
        }
    });
}

/* ── Remove single item ── */
function removeFromWishlist(productId) {
    if (!confirm('Are you sure you want to remove this item from your wishlist?')) return;

    const card     = document.getElementById('wlCard_' + productId);
    const heartBtn = document.getElementById('heartBtn_' + productId);
    if (!card) return;

    heartBtn.classList.add('removing');

    fetch('toggle_wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'productid=' + encodeURIComponent(productId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            card.classList.add('fade-out');
            setTimeout(() => {
                card.remove();
                updateCountDisplay();
                showToast('Removed from wishlist', 'success');
            }, 400);
        } else {
            heartBtn.classList.remove('removing');
            showToast(data.message || 'Could not remove item', 'error');
        }
    })
    .catch(err => {
        heartBtn.classList.remove('removing');
        showToast('Network error', 'error');
        console.error('FETCH ERROR:', err);
    });
}

/* ── Update all count displays after removal ── */
function updateCountDisplay() {
    const grid  = document.getElementById('wlGrid');
    const cards = grid ? grid.querySelectorAll('.wl-card[data-prodid]') : [];
    const count = cards.length;

    // Update hero subtitle
    const heroP = document.getElementById('wlHeroSubtitle');
    if (heroP) {
        heroP.textContent = count > 0
            ? count + ' saved item' + (count > 1 ? 's' : '')
            : 'Your favourite items, saved for later';
    }

    // Update count badge in hero — uses dedicated element ID
    const badge = document.getElementById('wlCountBadge');
    if (badge) badge.textContent = count + ' items';

    // Update nav wishlist badge — uses dedicated element ID
    const navBadge = document.getElementById('navWishlistBadge');
    if (navBadge) {
        if (count > 0) {
            navBadge.textContent = count;
            navBadge.style.display = '';
        } else {
            navBadge.style.display = 'none';
        }
    }

    // Hide clear all button and count badge when empty
    if (count === 0) {
        const clearBtn = document.getElementById('wlClearAllBtn');
        if (clearBtn) clearBtn.style.display = 'none';
        if (badge)    badge.style.display    = 'none';

        // Show empty state
        grid.innerHTML = `
            <div class="wl-empty">
                <span class="wl-empty-icon">💔</span>
                <h2>Your wishlist is empty</h2>
                <p>Save your favourite dishes by clicking the heart icon on any menu item.</p>
                <a href="menu.php" class="btn-browse"><i class="fas fa-utensils"></i> Explore Menu</a>
            </div>`;
    }
}

/* ── Clear all ── */
function clearAllWishlist() {
    if (!confirm('Remove all items from your wishlist?')) return;
    const cards = document.querySelectorAll('.wl-card[data-prodid]');
    if (!cards.length) return;

    let done = 0;
    const total = cards.length;

    cards.forEach(card => {
        const pid = card.getAttribute('data-prodid');
        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'productid=' + encodeURIComponent(pid)
        })
        .then(r => r.json())
        .then(() => {
            card.classList.add('fade-out');
            setTimeout(() => {
                card.remove();
                done++;
                if (done === total) {
                    updateCountDisplay();
                    showToast('Wishlist cleared', 'success');
                }
            }, 400);
        })
        .catch(() => {
            done++;
            if (done === total) {
                updateCountDisplay();
            }
        });
    });
}

/* ── Add to cart from wishlist ──
   Reads qty from the card's quantity control (stock-aware),
   then opens the same customizeModal as the normal "Add to Cart" flow. */
function wlAddToCart(event, productId, productName, productPrice, productCategory, btn) {
    event.preventDefault();

    const card     = btn.closest('.wl-card');
    const stock    = parseInt(card.dataset.stock) || 0;
    const controls = card.querySelector('.quantity-controls');
    let qty = 1;

    if (controls) {
        qty = parseInt(controls.querySelector('.qty-display').textContent) || 1;
    }

    // Clamp against stock and MAX_QTY
    const cartItems = JSON.parse(localStorage.getItem('cart')) || [];
    const inCart    = cartItems
        .filter(i => i.id == productId)
        .reduce((sum, i) => sum + (parseInt(i.quantity) || 1), 0);
    const ceiling   = Math.min(MAX_QTY_PER_PRODUCT, stock);
    const maxAdd    = Math.max(0, ceiling - inCart);

    if (maxAdd === 0) {
        showToast(
            inCart >= MAX_QTY_PER_PRODUCT
                ? 'Maximum 50 items for this product already in cart!'
                : 'No more stock available!',
            'error'
        );
        return;
    }

    qty = Math.min(qty, maxAdd);
    openCustomizeModal(productId, productName, productPrice, qty, productCategory);
}

function closeLoginModal() { const m = document.getElementById('loginModal'); if(m) m.classList.remove('show'); }

/* ── Nav dropdown ── */
function toggleNavDropdown() {
    document.getElementById('navProfileWrap').classList.toggle('open');
}
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
    if (typeof setLanguage === 'function') setLanguage(lang);
}

/* ── Dark mode ── */
const ddThemeBtn  = document.getElementById('ddThemeBtn');
const ddThemeIcon = document.getElementById('ddThemeIcon');
function syncDdThemeIcon() {
    if (!ddThemeIcon) return;
    ddThemeIcon.className = document.documentElement.getAttribute('data-theme') === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
}
syncDdThemeIcon();
if (ddThemeBtn) {
    ddThemeBtn.addEventListener('click', function() {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('ft_theme', next);
        syncDdThemeIcon();
    });
}

/* ── Init qty controls on page load ── */
document.addEventListener('DOMContentLoaded', function() {
    initAllQtyControls();
});
</script>

<?php include 'customizeModal.php'; ?>
</body>
</html>