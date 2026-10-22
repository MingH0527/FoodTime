<?php
/*
 * header.php
 * Shared header included on EVERY page.
 * - Loads translations.js early (in <head> via ob_start trick below)
 * - Fixes setDdLang to call changeLanguage() (correct localStorage key)
 * - Syncs ddLangLabel on load via language.js DOMContentLoaded
 *
 * Usage at top of every page:
 *   <?php include 'header.php'; ?>
 *
 * And at the bottom of every page before </body>:
 *   <?php include 'footer_scripts.php'; ?>
 */

session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");
include_once 'base.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_name = '';

if ($is_logged_in) {
    $user_name = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
}

$nav_pic      = null;
$nav_initial  = strtoupper(mb_substr($user_name, 0, 1)) ?: '?';
$nav_row      = null;
$nav_points   = 0;

if ($is_logged_in) {
    $nav_uid  = $_SESSION['user_id'];
    $nav_stmt = mysqli_prepare($connect,
        "SELECT profile_picture, username, membership_points FROM users WHERE userid = ?");
    mysqli_stmt_bind_param($nav_stmt, 's', $nav_uid);
    mysqli_stmt_execute($nav_stmt);
    $nav_res = mysqli_stmt_get_result($nav_stmt);
    $nav_row = mysqli_fetch_assoc($nav_res);
    mysqli_stmt_close($nav_stmt);

    if ($nav_row) {
        $nav_initial = strtoupper(mb_substr($nav_row['username'] ?? $user_name, 0, 1));
        $nav_points  = (int)($nav_row['membership_points'] ?? 0);
        if (!empty($nav_row['profile_picture']) &&
            file_exists('uploads/profiles/' . $nav_row['profile_picture'])) {
            $nav_pic = 'uploads/profiles/' . htmlspecialchars($nav_row['profile_picture']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- ① Prevent theme flash: read localStorage before paint -->
<script>
(function(){
    var t = localStorage.getItem('theme') || 'light';
    localStorage.setItem('theme', t);
    document.documentElement.setAttribute('data-theme', t);
})();
</script>

<!-- ② Translations MUST load before language.js -->
<script src="js/translations.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Add your page CSS link here, e.g.: -->
<!-- <link rel="stylesheet" href="css/style.css"> -->

<style>
/* ── Nav profile avatar + dropdown ── */
.nav-profile-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.nav-avatar {
    display: flex; align-items: center; gap: 6px;
    cursor: pointer; padding: 3px 8px 3px 3px;
    border-radius: 50px; transition: background .15s;
    user-select: none; border: none; background: none;
}
.nav-avatar:hover { background: #fff0e6; }
[data-theme="dark"] .nav-avatar:hover { background: rgba(212,101,15,.18); }

.nav-avatar-pic,
.nav-avatar-initial {
    width: 38px; height: 38px; border-radius: 50%;
    border: 2.5px solid #d4650f;
    object-fit: cover; display: flex;
    align-items: center; justify-content: center;
    flex-shrink: 0; overflow: hidden;
}
.nav-avatar-pic { display: block; }
.nav-avatar-initial {
    background: linear-gradient(135deg, #d4650f, #f0874a);
    color: #fff; font-size: 16px; font-weight: 800;
}
.nav-avatar-caret { font-size: 11px; color: #65676b; transition: transform .2s; }
[data-theme="dark"] .nav-avatar-caret { color: #b0b3b8; }
.nav-profile-wrap.open .nav-avatar-caret { transform: rotate(180deg); }

.nav-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 10px); right: 0;
    background: #fff;
    border: 1px solid #e4e6eb;
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,.14);
    min-width: 250px; z-index: 500;
    overflow: hidden;
    animation: ddIn .18s ease;
}
[data-theme="dark"] .nav-dropdown {
    background: #242526; border-color: #3a3b3c;
    box-shadow: 0 8px 32px rgba(0,0,0,.35);
}
@keyframes ddIn {
    from { opacity:0; transform: translateY(-8px) scale(.97); }
    to   { opacity:1; transform: translateY(0) scale(1); }
}
.nav-profile-wrap.open .nav-dropdown { display: block; }

.nav-dropdown-header {
    display: flex; align-items: center;
    gap: 11px; padding: 14px 16px;
    background: #fff8f2;
}
[data-theme="dark"] .nav-dropdown-header { background: rgba(212,101,15,.10); }

.nav-dd-avatar {
    width: 46px; height: 46px; border-radius: 50%;
    border: 2.5px solid #d4650f;
    overflow: hidden; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.nav-dd-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.nav-dd-avatar-initial {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #d4650f, #f0874a);
    color: #fff; font-size: 20px; font-weight: 900;
}
.nav-dd-name {
    font-size: 14px; font-weight: 800; color: #1c1e21;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 140px;
}
[data-theme="dark"] .nav-dd-name { color: #e4e6eb; }
.nav-dd-sub { font-size: 12px; color: #8a8d91; margin-top: 1px; }
.nav-dd-points-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: #fff; font-size: 11px; font-weight: 700;
    padding: 2px 8px; border-radius: 20px; margin-top: 4px;
}

.nav-dropdown-divider { height: 1px; background: #e4e6eb; margin: 4px 0; }
[data-theme="dark"] .nav-dropdown-divider { background: #3a3b3c; }

.nav-dd-item {
    display: flex; align-items: center; gap: 11px;
    padding: 10px 16px; font-size: 14px; font-weight: 600;
    color: #1c1e21; text-decoration: none;
    transition: background .12s; cursor: pointer;
}
[data-theme="dark"] .nav-dd-item { color: #e4e6eb; }
.nav-dd-item i { width: 16px; color: #d4650f; font-size: 14px; flex-shrink: 0; }
.nav-dd-item:hover { background: #fff0e6; }
[data-theme="dark"] .nav-dd-item:hover { background: rgba(212,101,15,.13); }
.nav-dd-logout { color: #dc2626 !important; }
.nav-dd-logout i { color: #dc2626 !important; }
.nav-dd-logout:hover { background: #fee2e2 !important; }
[data-theme="dark"] .nav-dd-logout:hover { background: rgba(220,38,38,.15) !important; }

.nav-dd-settings {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 16px; gap: 8px;
}
.nav-dd-settings-label {
    font-size: 12px; font-weight: 700; color: #8a8d91;
    display: flex; align-items: center; gap: 6px;
}
.nav-dd-settings-label i { color: #d4650f; font-size: 12px; }
.nav-dd-settings-controls { display: flex; align-items: center; gap: 6px; }

.dd-lang-wrap { position: relative; }
.dd-lang-btn {
    display: flex; align-items: center; gap: 5px;
    padding: 5px 10px; border-radius: 8px;
    border: 1.5px solid #e4e6eb;
    background: #fff; cursor: pointer;
    font-size: 12px; font-weight: 700; color: #374151;
    transition: border-color .15s, background .15s;
}
[data-theme="dark"] .dd-lang-btn { background: #3a3b3c; border-color: #555; color: #e4e6eb; }
.dd-lang-btn:hover { border-color: #d4650f; background: #fff0e6; }
.dd-lang-btn i { color: #d4650f; font-size: 11px; }

.dd-lang-menu {
    display: none; position: absolute;
    bottom: calc(100% + 6px); right: 0;
    background: #fff; border: 1px solid #e4e6eb;
    border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,.12);
    min-width: 150px; z-index: 600; overflow: hidden;
}
[data-theme="dark"] .dd-lang-menu { background: #242526; border-color: #3a3b3c; }
.dd-lang-wrap.open .dd-lang-menu { display: block; }
.dd-lang-menu a {
    display: block; padding: 9px 14px;
    font-size: 13px; font-weight: 600; color: #1c1e21;
    text-decoration: none; transition: background .12s;
}
[data-theme="dark"] .dd-lang-menu a { color: #e4e6eb; }
.dd-lang-menu a:hover { background: #fff0e6; color: #d4650f; }
.dd-lang-menu a.active { color: #d4650f; font-weight: 800; }

.dd-theme-btn {
    display: flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 8px;
    border: 1.5px solid #e4e6eb;
    background: #fff; cursor: pointer;
    font-size: 14px; color: #374151;
    transition: border-color .15s, background .15s, color .15s;
}
[data-theme="dark"] .dd-theme-btn { background: #3a3b3c; border-color: #555; color: #e4e6eb; }
.dd-theme-btn:hover { border-color: #d4650f; background: #fff0e6; color: #d4650f; }

.btn-chat-nav {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 50px; font-size: 13px;
    font-weight: 700; text-decoration: none;
    background: #fff0e6; color: #d4650f;
    border: 1.5px solid #d4650f;
    transition: background .15s, color .15s;
}
.btn-chat-nav:hover { background: #d4650f; color: #fff; }
</style>
</head>
<body>

<header>
    <div class="container">
        <div class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-burger"></i>
                <span>FOODTIME</span>
            </a>

            <ul class="nav-menu">
                <li><a href="index.php#categories" data-i18n="nav-category">Category</a></li>
                <li><a href="menu.php"              data-i18n="nav-menu">Menu</a></li>
                <li><a href="index.php#about"       data-i18n="nav-about">About</a></li>
            </ul>

            <form method="GET" action="index.php" class="search-form">
                <input type="text" name="keyword" placeholder="Search food..." required>
                <button type="submit" title="Search"><i class="fas fa-search"></i></button>
            </form>

            <div class="auth-buttons">

                <?php if ($is_logged_in): ?>
                    <a href="order_history.php" class="btn btn-primary">
                        <i class="fas fa-history"></i>
                        <span data-i18n="btn-orders">Orders</span>
                    </a>
                    <a href="cart.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i>
                        <span data-i18n="btn-cart">Cart</span>
                    </a>
                    <a href="customer_chat.php" class="btn btn-chat-nav" title="Live Support Chat">
                        <i class="fas fa-headset"></i>
                        <span>Support</span>
                    </a>

                    <!-- Profile avatar + dropdown -->
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
                                    <div class="nav-dd-name">
                                        <?php echo htmlspecialchars($nav_row['username'] ?? $user_name); ?>
                                    </div>
                                    <div class="nav-dd-sub">FOODTIME Member</div>
                                    <div class="nav-dd-points-badge">
                                        <i class="fas fa-star"></i>
                                        <?php echo number_format($nav_points); ?> pts
                                    </div>
                                </div>
                            </div>

                            <div class="nav-dropdown-divider"></div>

                            <a href="profile.php"       class="nav-dd-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
                            <a href="order_history.php" class="nav-dd-item"><i class="fas fa-history"></i> Order History</a>
                            <a href="membership.php"    class="nav-dd-item"><i class="fas fa-star"></i> My Points</a>
                            <a href="cart.php"          class="nav-dd-item"><i class="fas fa-shopping-cart"></i> My Cart</a>
                            <a href="customer_chat.php" class="nav-dd-item"><i class="fas fa-headset"></i> Support Chat</a>

                            <div class="nav-dropdown-divider"></div>

                            <!-- Language & Theme -->
                            <div class="nav-dd-settings">
                                <div class="nav-dd-settings-label">
                                    <i class="fas fa-sliders-h"></i> Preferences
                                </div>
                                <div class="nav-dd-settings-controls">
                                    <div class="dd-lang-wrap" id="ddLangWrap">
                                        <button class="dd-lang-btn" type="button"
                                                onclick="toggleDdLang(event)" title="Change Language">
                                            <i class="fas fa-globe"></i>
                                            <span id="ddLangLabel">EN</span>
                                        </button>
                                        <div class="dd-lang-menu" id="ddLangMenu">
                                            <a href="#" data-lang="en">🇬🇧 English</a>
                                            <a href="#" data-lang="ms">🇲🇾 Bahasa Melayu</a>
                                            <a href="#" data-lang="zh">🇨🇳 中文</a>
                                        </div>
                                    </div>
                                    <button class="dd-theme-btn" id="ddThemeBtn"
                                            type="button" title="Toggle Dark Mode"
                                            onclick="event.stopPropagation(); toggleTheme();">
                                        <i class="fas fa-moon" id="ddThemeIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="nav-dropdown-divider"></div>

                            <a href="logout.php" class="nav-dd-item nav-dd-logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <a href="login.php" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        <span data-i18n="login">Login</span>
                    </a>
                    <a href="register.php" class="btn btn-signup">
                        <i class="fas fa-user-plus"></i>
                        <span data-i18n="sign-up">Sign Up</span>
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </div>
</header>

<script>
/* ── Nav profile dropdown ── */
function toggleNavDropdown() {
    var wrap = document.getElementById('navProfileWrap');
    if (wrap) wrap.classList.toggle('open');
}

/* ── Close all dropdowns when clicking outside ── */
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('navProfileWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
    var lw = document.getElementById('ddLangWrap');
    if (lw && !lw.contains(e.target)) lw.classList.remove('open');
});

/* ── Toggle language picker ── */
function toggleDdLang(e) {
    e.stopPropagation();
    document.getElementById('ddLangWrap').classList.toggle('open');
}

/*
 * ── FIX: use changeLanguage() from language.js ──
 * Old code used localStorage.setItem('lang', ...) — wrong key!
 * language.js reads/writes 'language', not 'lang'.
 * Now we just delegate to changeLanguage() which handles everything:
 *   - saves to localStorage('language')
 *   - applies translations to all [data-i18n] elements
 *   - updates active state on dropdown links
 *   - syncs the ddLangLabel text
 */
document.addEventListener('DOMContentLoaded', function() {
    /* Wire up dd-lang-menu links to changeLanguage() */
    document.querySelectorAll('.dd-lang-menu a').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var lang = this.getAttribute('data-lang');
            document.getElementById('ddLangWrap').classList.remove('open');
            if (typeof changeLanguage === 'function') {
                changeLanguage(lang);
            }
        });
    });
});
</script>