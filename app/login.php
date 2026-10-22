<?php
session_start();
$_title = "Login - FlavorBite";

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

// Read and clear one-time messages
$error = '';
$blocked_user = '';

if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_SESSION['login_blocked_user'])) {
    $blocked_user = $_SESSION['login_blocked_user'];
}

$attempts_left = isset($_SESSION['login_attempts_left']) ? (int)$_SESSION['login_attempts_left'] : 3;
$is_blocked_sess = isset($_SESSION['login_blocked']) && $_SESSION['login_blocked'] === true;

if ($is_blocked_sess) {
    unset(
        $_SESSION['login_blocked'],
        $_SESSION['login_blocked_user'],
        $_SESSION['login_attempts_left'],
        $_SESSION['login_attempts_for_user']
    );
    $show_blocked_banner = true;
} else {
    $show_blocked_banner = false;
}

// Keep old username after failed login
$old_username = isset($_SESSION['old_username'])
    ? htmlspecialchars($_SESSION['old_username'])
    : '';

if (isset($_SESSION['old_username'])) {
    unset($_SESSION['old_username']);
}

$cookie_accepted = isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'yes';

$remembered_username = $old_username !== ''
    ? $old_username
    : (isset($_COOKIE['remember_username'])
        ? htmlspecialchars($_COOKIE['remember_username'])
        : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="css/login.css">
    <style>
        .alert-box {
            padding: 13px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 14px;
            text-align: left;
            animation: slideDown .3s ease;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #c0392b;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-blocked {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #8b0000;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .alert-blocked .at {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .alert-blocked .as {
            font-size: 13px;
            font-weight: 400;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .remember-container.rb-on .checkmark {
            background: linear-gradient(135deg, #C8E5E4, #E3E2F0, #ECDAEA, #EAC8DF, #DDF0EE) !important;
            border: none !important;
        }
        .remember-container.rb-on .checkmark:after {
            display: block !important;
        }

        .ferr {
            display: none;
            color: #ff4d4d;
            font-size: 12px;
            margin: 4px 0 0 15px;
            font-weight: 700;
        }
        input.bad {
            border: 2px solid #ff4d4d !important;
            border-radius: 35px;
        }

        #toastMsg {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 260px;
            max-width: 360px;
            padding: 14px 18px;
            border-radius: 12px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
            box-shadow: 0 10px 24px rgba(0,0,0,.18);
            z-index: 100000;
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
            transition: .25s ease;
        }
        #toastMsg.show {
            opacity: 1;
            transform: translateY(0);
        }
        #toastMsg.ok {
            background: #28a745;
        }
        #toastMsg.info {
            background: #6c757d;
        }
    </style>
</head>
<body>

<div class="login-card">
    <a href="index.php" class="back-home">↩️ BACK</a>
    <h2>LOGIN</h2>

    <?php if ($show_blocked_banner): ?>
        <div class="alert-box alert-blocked">
            <div class="at">🔒 Account Blocked</div>
            <div>The account <strong><?= htmlspecialchars($blocked_user) ?></strong> has been blocked due to too many failed login attempts.</div>
            <div class="as">Please contact support to unlock your account.</div>
        </div>
    <?php elseif ($error && $attempts_left === 1): ?>
        <div class="alert-box alert-blocked">
            <div class="at">⚠️ Final Warning!</div>
            <div><?= htmlspecialchars($error) ?></div>
            <div class="as">🚨 Only <strong>1 attempt remaining</strong> — your account will be <strong>permanently blocked</strong> on the next wrong attempt!</div>
        </div>
    <?php elseif ($error && $attempts_left === 2): ?>
        <div class="alert-box alert-warning">
            <span>⚠️</span>
            <div><?= htmlspecialchars($error) ?> — <strong>2 attempts remaining</strong> before your account is blocked.</div>
        </div>
    <?php elseif ($error): ?>
        <div class="alert-box alert-error">
            <span>⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="login_process.php" method="post" autocomplete="off" id="loginForm">
        <div class="input-field">
            <label>👤 Username</label>
            <input type="text" name="username" id="f-user" placeholder="Please enter your name" maxlength="30"value="<?= $remembered_username ?>">
            <div class="ferr" id="e-user">* Username is required</div>
        </div>

        <div class="input-field">
            <label>🔒 Password</label>
            <div class="password-wrapper">
                <input type="password" id="f-pass" name="password" placeholder="Please enter your password" maxlength="15">
                <span class="login_password" onclick="togglePw('f-pass',this)">🙈</span>
            </div>
            <div class="ferr" id="e-pass">* Password is required</div>
        </div>

        <div class="auth-options">
            <label class="remember-container <?= ($remembered_username && $cookie_accepted) ? 'rb-on' : '' ?>" id="remLabel">
                <input type="checkbox" name="remember" id="remCB" <?= ($remembered_username && $cookie_accepted) ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                <span class="label-text">Remember me</span>
            </label>
            <a href="forgot.php" class="highlight-link">Forgot password?</a>
        </div>

        <button type="submit" class="gradient-btn">LOGIN</button>
    </form>

    <div class="extra-links">
        <span>Don't have an account?</span>
        <a href="register.php" class="highlight-link">Register Now!</a>
    </div>
</div>

<div id="toastMsg"></div>

<script>
const remCB = document.getElementById('remCB');
const remLabel = document.getElementById('remLabel');

window.addEventListener('DOMContentLoaded', function () {
    setRemState(remCB.checked);
});

function showToast(message, type = 'info') {
    const toast = document.getElementById('toastMsg');
    toast.textContent = message;
    toast.className = '';
    toast.classList.add(type, 'show');

    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.classList.remove('show');
    }, 2200);
}

remCB.addEventListener('change', function () {
    if (this.checked) {
        writeCk('cookie_consent', 'yes', 365);
        setRemState(true);
        showToast('Remember Me is enabled. Your username will be saved for 30 days.', 'ok');
    } else {
        eraseCk('cookie_consent');
        eraseCk('remember_username');
        setRemState(false);
        showToast('Remember Me is turned off.', 'info');
    }
});

function setRemState(isOn) {
    remLabel.classList.remove('rb-on');
    if (isOn) {
        remLabel.classList.add('rb-on');
    }
}

function writeCk(name, val, days) {
    const exp = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(val) + ';expires=' + exp + ';path=/;SameSite=Lax';
}

function eraseCk(name) {
    document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/';
}

function togglePw(id, el) {
    const i = document.getElementById(id);
    const show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    el.textContent = show ? '🙉' : '🙈';
}

document.getElementById('loginForm').addEventListener('submit', function (e) {
    let ok = true;
    const u  = document.getElementById('f-user');
    const p  = document.getElementById('f-pass');
    const eu = document.getElementById('e-user');
    const ep = document.getElementById('e-pass');

    eu.style.display = 'none';
    ep.style.display = 'none';
    u.classList.remove('bad');
    p.classList.remove('bad');

    if (!u.value.trim()) {
        eu.style.display = 'block';
        u.classList.add('bad');
        ok = false;
    }

    if (!p.value.trim()) {
        ep.style.display = 'block';
        p.classList.add('bad');
        ok = false;
    }

    if (!ok) e.preventDefault();
});
</script>
</body>
</html>