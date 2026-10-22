<?php
session_start();
include 'base.php';

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$remember = isset($_POST['remember']);

// Keep username after failed login
$_SESSION['old_username'] = $username;

/* ── Clear stale session data from a DIFFERENT user ── */
$prev_user = $_SESSION['login_attempts_for_user'] ?? '';
if ($prev_user !== '' && $prev_user !== $username) {
    $old_akey = 'fa_' . md5($prev_user);
    unset(
        $_SESSION[$old_akey],
        $_SESSION['login_attempts_left'],
        $_SESSION['login_attempts_for_user'],
        $_SESSION['login_blocked'],
        $_SESSION['login_blocked_user'],
        $_SESSION['login_error']
    );
}

if (isset($_SESSION['login_blocked_user']) && $_SESSION['login_blocked_user'] !== $username) {
    unset(
        $_SESSION['login_blocked'],
        $_SESSION['login_blocked_user'],
        $_SESSION['login_error'],
        $_SESSION['login_attempts_left'],
        $_SESSION['login_attempts_for_user']
    );
}

/* ── Empty field guard ── */
if ($username === '' || $password === '') {
    $_SESSION['login_error'] = "Username and password are required.";
    header("Location: login.php");
    exit();
}

/* ── Look up the account ── */
$stmt = $connect->prepare("SELECT * FROM users WHERE username = ?");
if (!$stmt) {
    $_SESSION['login_error'] = "Database error. Please try again.";
    header("Location: login.php");
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

/* ── Username does not exist ── */
if (!$user_data) {
    unset(
        $_SESSION['login_attempts_left'],
        $_SESSION['login_blocked'],
        $_SESSION['login_blocked_user']
    );
    $_SESSION['login_error'] = "Cannot find this username. Please check and re-enter your username and password.";
    header("Location: login.php");
    exit();
}

/* ── Account already blocked in DB ── */
if ((int)($user_data['is_blocked'] ?? 0) === 1) {
    $_SESSION['login_blocked'] = true;
    $_SESSION['login_blocked_user'] = $user_data['username'];
    $_SESSION['login_error'] = "Your account has been blocked. Please contact support.";

    $akey = 'fa_' . md5($username);
    unset($_SESSION[$akey], $_SESSION['login_attempts_left']);

    header("Location: login.php");
    exit();
}

/* ── Wrong password ── */
if (!password_verify($password, $user_data['password'])) {
    $is_admin = isset($user_data['position']) && strtolower($user_data['position']) === 'admin';

    // ADMIN: never block
    if ($is_admin) {
        unset(
            $_SESSION['login_attempts_left'],
            $_SESSION['login_blocked'],
            $_SESSION['login_blocked_user'],
            $_SESSION['login_attempts_for_user']
        );

        $akey = 'fa_' . md5($username);
        unset($_SESSION[$akey]);

        $_SESSION['login_error'] = "Incorrect Admin password. Please try again.";
        header("Location: login.php");
        exit();
    }

    // NORMAL USER: 3 wrong attempts = block
    $akey = 'fa_' . md5($username);

    if (!isset($_SESSION[$akey])) {
        $_SESSION[$akey] = 0;
    }

    $_SESSION[$akey]++;

    $done = $_SESSION[$akey];
    $remaining = max(0, 3 - $done);

    $_SESSION['login_attempts_left'] = $remaining;
    $_SESSION['login_attempts_for_user'] = $username;

    if ($remaining <= 0) {
        $bs = $connect->prepare("UPDATE users SET is_blocked=1, status='blocked' WHERE username=?");
        if ($bs) {
            $bs->bind_param("s", $username);
            $bs->execute();
            $bs->close();
        }

        $_SESSION['login_blocked'] = true;
        $_SESSION['login_blocked_user'] = $username;
        $_SESSION['login_error'] = "Your account has been blocked after too many failed login attempts.";
        unset($_SESSION[$akey], $_SESSION['login_attempts_left'], $_SESSION['login_attempts_for_user']);
    } else {
        $_SESSION['login_error'] = "Invalid password. Please check and re-enter your password.";
    }

    header("Location: login.php");
    exit();
}

/* ══ Login success ══ */
$akey = 'fa_' . md5($username);
unset(
    $_SESSION[$akey],
    $_SESSION['login_attempts_left'],
    $_SESSION['login_attempts_for_user'],
    $_SESSION['login_blocked'],
    $_SESSION['login_blocked_user'],
    $_SESSION['login_error'],
    $_SESSION['old_username']
);

$_SESSION['user_id']   = $user_data['userid'];
$_SESSION['user_name'] = $user_data['username'];
$_SESSION['username']  = $user_data['username'];
$_SESSION['email']     = $user_data['email'] ?? '';
$_SESSION['position']  = $user_data['position'];
$_SESSION['logged_in'] = true;

/* Remember Me */
$consent = !empty($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'yes';

if ($remember && $consent) {
    setcookie('remember_username', $username, time() + (30 * 86400), '/');
    setcookie('cookie_consent', 'yes', time() + (365 * 86400), '/');
} else {
    setcookie('remember_username', '', time() - 3600, '/');
    setcookie('cookie_consent', '', time() - 3600, '/');
}

header("Location: " . ($user_data['position'] === 'admin' ? 'Admin.php' : 'index.php'));
exit();