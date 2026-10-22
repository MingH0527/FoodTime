<?php
session_start();
include 'base.php';

$_title = "Reset Password";

$error = '';
$success = '';
$user_not_found = false;

/* ── Keep username after failed reset ── */
$old_username = isset($_SESSION['forgot_old_username'])
    ? htmlspecialchars($_SESSION['forgot_old_username'])
    : '';

if (isset($_SESSION['forgot_old_username'])) {
    unset($_SESSION['forgot_old_username']);
}

/* ── Read remembered username if user agreed to remember me ── */
$cookie_accepted = isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'yes';

$remembered_username = ($cookie_accepted && isset($_COOKIE['remember_username']))
    ? htmlspecialchars($_COOKIE['remember_username'])
    : '';

/* Priority: failed input first, then remembered username */
$prefill_username = $old_username;

/* ═══════════════════════════════════════
   HANDLE FORM SUBMIT
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm  = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    $_SESSION['forgot_old_username'] = $username;

    /* ── Empty field check ── */
    if ($username === '' || $password === '' || $confirm === '') {
        $_SESSION['forgot_error'] = "All fields are required.";
        header("Location: forgot.php");
        exit();
    }

    /* ── Password format check ── */
$valid_length = strlen($password) >= 8 && strlen($password) <= 15;
$valid_upper  = preg_match('/[A-Z]/', $password);
$valid_lower  = preg_match('/[a-z]/', $password);
$valid_number = preg_match('/\d/', $password);
$valid_symbol = preg_match('/[@$!%*?&]/', $password);

if (!($valid_length && $valid_upper && $valid_lower && $valid_number && $valid_symbol)) {
    $_SESSION['forgot_error'] = "Password must be 8 to 15 characters and include uppercase, lowercase, number, and special symbol.";
    header("Location: forgot.php");
    exit();
}

    /* ── Confirm password match ── */
    if ($password !== $confirm) {
        $_SESSION['forgot_error'] = "Passwords do not match.";
        header("Location: forgot.php");
        exit();
    }

    /* ── Check username exists ── */
    $stmt = $connect->prepare("SELECT userid FROM users WHERE username = ?");
    if (!$stmt) {
        $_SESSION['forgot_error'] = "Database error. Please try again.";
        header("Location: forgot.php");
        exit();
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if (!$user_data) {
        $_SESSION['forgot_error'] = "Username not found.";
        header("Location: forgot.php");
        exit();
    }

    /* ── Hash new password ── */
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    /* ── Update password ── */
    $update = $connect->prepare("UPDATE users SET password = ? WHERE username = ?");
    if (!$update) {
        $_SESSION['forgot_error'] = "Database error while updating password.";
        header("Location: forgot.php");
        exit();
    }

    $update->bind_param("ss", $hashed_password, $username);

    if ($update->execute()) {
    header("Location: login.php");
    exit();
     } else {
    $_SESSION['forgot_error'] = "Failed to update password. Please try again.";
    header("Location: forgot.php");
    exit();
}
}

/* ═══════════════════════════════════════
   READ FLASH MESSAGES
═══════════════════════════════════════ */
if (isset($_SESSION['forgot_error'])) {
    $error = $_SESSION['forgot_error'];

    if ($error === "Username not found.") {
        $user_not_found = true;
    }

    unset($_SESSION['forgot_error']);
}

if (isset($_SESSION['forgot_success'])) {
    $success = $_SESSION['forgot_success'];
    unset($_SESSION['forgot_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="css/forgot.css">
    <style>
        .requirements-container {
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 15px;
            text-align: left;
        }

        .requirements-container p {
            font-size: 13px;
            margin: 5px 0;
            transition: .3s;
        }

        .valid {
            color: #2ecc71;
            font-weight: bold;
        }

        .invalid {
            color: #e74c3c;
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 50px !important;
        }

        .toggle-password {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 20px;
            z-index: 10;
            user-select: none;
        }

        .field-error {
            display: none;
            color: #ff4d4d;
            font-size: 12px;
            margin: 5px 0 0 15px;
            font-weight: 700;
        }

        .alert-box {
            padding: 13px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 14px;
            text-align: left;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #c0392b;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 15px 50px 15px 25px;
            border: none;
            border-radius: 35px;
            background-color: #f1f1f1;
            font-size: 14px;
            outline: none;
            transition: border .3s;
        }

        input.input-error {
            border: 2px solid #ff4d4d !important;
        }

        .remember-note {
            margin-top: 8px;
            font-size: 12px;
            color: #777;
            text-align: left;
        }
    </style>
</head>
<body>
<div class="password-card">
    <a href="login.php" class="back-link">↩️ BACK</a>
    <h2>Reset Password</h2>

    <?php if ($error): ?>
        <div class="alert-box alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-box alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form action="forgot.php" method="post" autocomplete="off" id="forgotForm">

        <div class="input-field">
            <label>👤 Username</label>
           <input type="text" name="username" id="fg-username"
            placeholder="Enter your username"
            maxlength="30"
            value="<?= $prefill_username ?>"
            class="<?= $user_not_found ? 'input-error' : '' ?>">

            <div class="field-error" id="err-fg-user">* Username is required</div>
            <div class="field-error" id="err-fg-user-notfound" style="<?= $user_not_found ? 'display:block;' : 'display:none;' ?>">
                * Username not found
            </div>

            <?php if ($remembered_username !== ''): ?>
                <div class="remember-note">Remembered username loaded from your previous login choice.</div>
            <?php endif; ?>
        </div>

        <div class="input-field">
            <label>🔒 New Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="fg-pw" placeholder="Create new password" maxlength="15">
                <span class="toggle-password" onclick="togglePw('fg-pw',this)">🙈</span>
            </div>
            <div class="field-error" id="err-fg-pw-empty">* Password is required</div>
            <div class="field-error" id="err-fg-pw-fmt">* Password format does not match</div>

            <div class="requirements-container">
                <p id="fg-req-length" class="invalid">❌ 8 to 15 characters</p>
                <p id="fg-req-case" class="invalid">❌ Upper &amp; Lowercase</p>
                <p id="fg-req-number" class="invalid">❌ At least one number</p>
                <p id="fg-req-symbol" class="invalid">❌ Special symbol (@$!%*?&amp;)</p>
            </div>
        </div>

        <div class="input-field">
            <label>🛡️  Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="fg-conf" placeholder="Re-enter new password" maxlength="15">
                <span class="toggle-password" onclick="togglePw('fg-conf',this)">🙈</span>
            </div>
            <div class="field-error" id="err-fg-conf-empty">* Confirm password is required</div>
            <div class="field-error" id="err-fg-conf-match">* Passwords do not match</div>

            <div class="requirements-container">
                <p id="fg-conf-req-length" class="invalid">❌ 8 to 15 characters</p>
                <p id="fg-conf-req-case" class="invalid">❌ Upper &amp; Lowercase</p>
                <p id="fg-conf-req-number" class="invalid">❌ At least one number</p>
                <p id="fg-conf-req-symbol" class="invalid">❌ Special symbol (@$!%*?&amp;)</p>
            </div>
        </div>

        <button type="submit" class="gradient-btn">Done ✅</button>
    </form>
</div>

<script>
function togglePw(id, el) {
    const i = document.getElementById(id);
    const show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    el.textContent = show ? '🙉' : '🙈';
}

const PW_RULES = {
    length: v => v.length >= 8 && v.length <= 15,
    upper:  v => /[A-Z]/.test(v),
    lower:  v => /[a-z]/.test(v),
    number: v => /\d/.test(v),
    symbol: v => /[@$!%*?&]/.test(v)
};

function updateReqs(val, pfx) {
    const set = (id, ok) => {
        const el = document.getElementById(pfx + id);
        if (!el) return;
        const icon = ok ? '✅' : '❌';
        el.className = ok ? 'valid' : 'invalid';
        el.textContent = icon + el.textContent.slice(1);
    };

    set('req-length', PW_RULES.length(val));
    set('req-case', PW_RULES.upper(val) && PW_RULES.lower(val));
    set('req-number', PW_RULES.number(val));
    set('req-symbol', PW_RULES.symbol(val));
}

function isValid(v) {
    return PW_RULES.length(v) &&
           PW_RULES.upper(v) &&
           PW_RULES.lower(v) &&
           PW_RULES.number(v) &&
           PW_RULES.symbol(v);
}

document.getElementById('fg-pw').addEventListener('input', function () {
    updateReqs(this.value, 'fg-');
});

document.getElementById('fg-conf').addEventListener('input', function () {
    updateReqs(this.value, 'fg-conf-');
});

document.getElementById('forgotForm').addEventListener('submit', function(e) {
    let ok = true;

    const IDS = [
        'err-fg-user',
        'err-fg-user-notfound',
        'err-fg-pw-empty',
        'err-fg-pw-fmt',
        'err-fg-conf-empty',
        'err-fg-conf-match'
    ];

    IDS.forEach(id => document.getElementById(id).style.display = 'none');
    ['fg-username', 'fg-pw', 'fg-conf'].forEach(id => document.getElementById(id).classList.remove('input-error'));

    const user = document.getElementById('fg-username').value.trim();
    const pw   = document.getElementById('fg-pw').value;
    const conf = document.getElementById('fg-conf').value;

    if (!user) {
        show('err-fg-user');
        err('fg-username');
        ok = false;
    }

    if (!pw) {
        show('err-fg-pw-empty');
        err('fg-pw');
        ok = false;
    } else if (!isValid(pw)) {
        show('err-fg-pw-fmt');
        err('fg-pw');
        ok = false;
    }

    if (!conf) {
        show('err-fg-conf-empty');
        err('fg-conf');
        ok = false;
    } else if (pw && conf !== pw) {
        show('err-fg-conf-match');
        err('fg-conf');
        ok = false;
    }

    if (!ok) e.preventDefault();

    function show(id) {
        document.getElementById(id).style.display = 'block';
    }

    function err(id) {
        document.getElementById(id).classList.add('input-error');
    }
});
</script>
</body>
</html>