<?php
/* ============================================================
   customer_chat.php — FOODTIME Support Chat
   ============================================================ */
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'base.php';
include 'guest_chat_auth.php';   // sets $chat_authed, $chat_user_id, etc.

/*
 * Access rules:
 *   - Fully logged-in customers  → full access (search enabled)
 *   - Verified guests / blocked  → chat access, search disabled
 *   - Anyone else                → show username-verification screen
 *     (NO redirect to login.php for this flow)
 */

$is_full_login = isset($_SESSION['user_id']) && ($_SESSION['position'] ?? '') === 'customer';

// If not yet authenticated for chat, show the verify screen and stop.
if (!$chat_authed) {
    // ── Inline verify screen ──────────────────────────────────
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support Chat — FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--orange:#d4650f;--orange-dk:#b3540c;--orange-lt:#fff0e6;--bg:#f0f2f5;--white:#fff;--border:#e4e6eb;--text:#1c1e21;--sub:#65676b;--muted:#8a8d91}
html,body{height:100%;font-family:'Segoe UI',-apple-system,sans-serif;background:var(--bg);color:var(--text)}
.top-nav{height:56px;background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:14px;position:fixed;top:0;left:0;right:0;z-index:200;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.nav-brand{font-size:18px;font-weight:800;color:var(--orange);text-decoration:none;display:flex;align-items:center;gap:8px}
.nav-title{font-size:14px;font-weight:600;color:var(--sub)}
.nav-back{margin-left:auto;color:var(--orange);text-decoration:none;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;transition:background .15s}
.nav-back:hover{background:var(--orange-lt)}
.verify-wrap{display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 56px);margin-top:56px;padding:24px}
.verify-card{background:var(--white);border-radius:18px;box-shadow:0 4px 32px rgba(0,0,0,.10);padding:36px 32px;width:100%;max-width:420px;text-align:center}
.verify-icon{width:72px;height:72px;border-radius:50%;background:var(--orange-lt);display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--orange);margin:0 auto 20px}
.verify-title{font-size:22px;font-weight:800;margin-bottom:6px}
.verify-sub{font-size:14px;color:var(--sub);margin-bottom:24px;line-height:1.6}
.form-input{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:15px;font-family:inherit;outline:none;transition:border .15s;margin-bottom:14px}
.form-input:focus{border-color:var(--orange)}
.btn-verify{width:100%;padding:12px;background:var(--orange);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s}
.btn-verify:hover{background:var(--orange-dk)}
.btn-verify:disabled{background:#ccc;cursor:not-allowed}
.verify-error{display:none;padding:10px 14px;background:#fee2e2;border:1px solid #fca5a5;border-radius:9px;color:#991b1b;font-size:13px;font-weight:600;margin-bottom:14px;text-align:left;align-items:center;gap:8px}
.verify-error.show{display:flex}
.divider{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px;margin:16px 0}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
.btn-login-link{width:100%;padding:11px;background:var(--white);color:var(--orange);border:2px solid var(--orange);border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s}
.btn-login-link:hover{background:var(--orange-lt)}
.info-note{font-size:12px;color:var(--muted);margin-top:18px;line-height:1.6;padding:10px 14px;background:#f9fafb;border-radius:8px;text-align:left}
</style>
</head>
<body>

<nav class="top-nav">
    <a href="index.php" class="nav-brand"><i class="fas fa-burger"></i> FOODTIME</a>
    <span class="nav-title"><i class="fas fa-headset" style="color:var(--orange)"></i> Support Chat</span>
    <a href="index.php" class="nav-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
</nav>

<div class="verify-wrap">
    <div class="verify-card">
        <div class="verify-icon"><i class="fas fa-headset"></i></div>
        <div class="verify-title">Contact Support</div>
        <div class="verify-sub">Enter your FOODTIME username to access the support chat.<br>No login required — even if your account is blocked.</div>

        <div class="verify-error" id="verify-error">
            <i class="fas fa-exclamation-circle"></i>
            <span id="verify-error-text">Something went wrong.</span>
        </div>

        <input type="text" id="verify-username" class="form-input" placeholder="Your username" maxlength="80" autocomplete="username">
        <button class="btn-verify" id="btn-verify" onclick="doVerify()">
            <i class="fas fa-comments"></i> Continue to Chat
        </button>

        <div class="divider">or</div>
        <a href="login.php" class="btn-login-link"><i class="fas fa-sign-in-alt"></i> Log In for Full Access</a>

        <div class="info-note">
            <strong>Why no login?</strong> If your account has been blocked, you may not be able to log in. This chat lets you contact support to appeal or recover your account.
        </div>
    </div>
</div>

<script>
document.getElementById('verify-username').addEventListener('keydown', function(e){
    if (e.key === 'Enter') doVerify();
});

function doVerify() {
    var username = document.getElementById('verify-username').value.trim();
    var errBox   = document.getElementById('verify-error');
    var errText  = document.getElementById('verify-error-text');
    var btn      = document.getElementById('btn-verify');

    errBox.classList.remove('show');

    if (!username) {
        errText.textContent = 'Please enter your username.';
        errBox.classList.add('show');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…';

    fetch('verify_chat_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'username=' + encodeURIComponent(username)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            // Redirect to chat (token passed in URL for session pickup)
            var url = 'customer_chat.php';
            if (res.token) url += '?guest_token=' + encodeURIComponent(res.token);
            window.location.href = url;
        } else {
            errText.textContent = res.message || 'Verification failed. Please try again.';
            errBox.classList.add('show');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-comments"></i> Continue to Chat';
        }
    })
    .catch(function() {
        errText.textContent = 'Network error. Please try again.';
        errBox.classList.add('show');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-comments"></i> Continue to Chat';
    });
}
</script>
</body>
</html>
    <?php
    exit;
}

// ── Authenticated (full login or verified guest) ──
$user_id   = $chat_user_id;
$user_name = htmlspecialchars($chat_username);

// Validate guest token from URL (refreshes session, no hard-block if already set via session)
if ($chat_is_guest && isset($_GET['guest_token'])) {
    // Token already validated inside guest_chat_auth.php via session; nothing more needed here.
}

$active_conv_id = isset($_GET['conv_id']) ? trim($_GET['conv_id']) : '';

/* Conversations with last message preview */
$conversations = [];
$stmt = mysqli_prepare($connect,
    "SELECT cc.*,
            (SELECT COUNT(*) 
             FROM chat_messages cm 
             WHERE cm.conversation_id = cc.conversation_id 
               AND cm.is_read = 0 
               AND cm.receiver_id = ?) AS unread,
            (SELECT cm2.message_text 
             FROM chat_messages cm2 
             WHERE cm2.conversation_id = cc.conversation_id 
             ORDER BY cm2.created_at DESC 
             LIMIT 1) AS last_message,
            (SELECT cm3.created_at
             FROM chat_messages cm3
             WHERE cm3.conversation_id = cc.conversation_id
             ORDER BY cm3.created_at DESC
             LIMIT 1) AS last_msg_time
     FROM chat_conversations cc
     WHERE cc.customer_id = ?
     ORDER BY cc.updated_at DESC");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) { $conversations[] = $row; }
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
}

/* Orders for dropdown — only meaningful for logged-in users */
$orders = [];
if ($is_full_login) {
    $stmt2 = mysqli_prepare($connect,
        "SELECT orderid, total_price, status FROM orders WHERE userid = ? ORDER BY created_at DESC LIMIT 20");
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 's', $user_id);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);
        while ($row = mysqli_fetch_assoc($result2)) { $orders[] = $row; }
        mysqli_free_result($result2);
        mysqli_stmt_close($stmt2);
    }
}

/* Active conversation messages */
$init_messages = [];
$active_conv   = null;

if (!empty($active_conv_id)) {
    $s3 = mysqli_prepare($connect,
        "SELECT * FROM chat_conversations WHERE conversation_id = ? AND customer_id = ?");
    if ($s3) {
        mysqli_stmt_bind_param($s3, 'ss', $active_conv_id, $user_id);
        mysqli_stmt_execute($s3);
        $result3 = mysqli_stmt_get_result($s3);
        $active_conv = mysqli_fetch_assoc($result3);
        mysqli_free_result($result3);
        mysqli_stmt_close($s3);
    }

    if ($active_conv) {
        $s4 = mysqli_prepare($connect,
            "SELECT cm.*,
                    CAST(SUBSTRING(cm.message_id, 4) AS UNSIGNED) AS message_id_num,
                    DATE_FORMAT(cm.created_at, '%d %b %Y, %h:%i %p') AS created_at_fmt,
                    u.position AS sender_position
             FROM chat_messages cm
             LEFT JOIN users u ON u.userid = cm.sender_id
             WHERE cm.conversation_id = ?
             ORDER BY cm.created_at ASC LIMIT 200");
        if ($s4) {
            mysqli_stmt_bind_param($s4, 's', $active_conv_id);
            mysqli_stmt_execute($s4);
            $result4 = mysqli_stmt_get_result($s4);
            while ($row = mysqli_fetch_assoc($result4)) {
                if ($row['message_type'] === 'system' || $row['message_type'] === 'auto_reply') {
                    $row['is_mine'] = 0;
                } else {
                    $row['is_mine'] = ($row['sender_id'] === $user_id) ? 1 : 0;
                }
                $init_messages[] = $row;
            }
            mysqli_free_result($result4);
            mysqli_stmt_close($s4);
        }

        $s5 = mysqli_prepare($connect,
            "UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0");
        if ($s5) {
            mysqli_stmt_bind_param($s5, 'ss', $active_conv_id, $user_id);
            mysqli_stmt_execute($s5);
            mysqli_stmt_close($s5);
        }
    }
}

$last_id = !empty($init_messages) ? (int) end($init_messages)['message_id_num'] : 0;

function time_ago($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60) return 'Just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    return date('d M', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support Chat — FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --orange:#d4650f;--orange-dk:#b3540c;--orange-lt:#fff0e6;--orange-md:#f0874a;
  --bg:#f0f2f5;--white:#fff;--border:#e4e6eb;
  --text:#1c1e21;--sub:#65676b;--muted:#8a8d91;
  --bubble-them:#e4e6eb;--online:#31a24c;
  --admin-blue:#1d6fa8;--admin-blue-lt:#dbeafe;
  --bot-purple:#7c3aed;--bot-purple-lt:#ede9fe;
}
html,body{height:100%;font-family:'Segoe UI',-apple-system,sans-serif;background:var(--bg);color:var(--text)}

/* TOP NAV */
.top-nav{height:56px;background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:14px;position:fixed;top:0;left:0;right:0;z-index:200;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.nav-brand{font-size:18px;font-weight:800;color:var(--orange);text-decoration:none;display:flex;align-items:center;gap:8px}
.nav-title{font-size:14px;font-weight:600;color:var(--sub)}
.nav-back{margin-left:auto;color:var(--orange);text-decoration:none;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;transition:background .15s}
.nav-back:hover{background:var(--orange-lt)}

/* LAYOUT */
.chat-app{display:flex;height:calc(100vh - 56px);margin-top:56px;overflow:hidden}

/* SIDEBAR */
.chat-sidebar{width:360px;min-width:360px;background:var(--white);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.sidebar-top{padding:16px 16px 10px;border-bottom:1px solid var(--border);flex-shrink:0}
.sidebar-heading{font-size:22px;font-weight:800;margin-bottom:12px}

/* Search — locked for guests */
.sidebar-search{display:flex;align-items:center;background:#f0f2f5;border-radius:50px;padding:8px 14px;gap:8px;position:relative;cursor:<?php echo $is_full_login ? 'text' : 'not-allowed'; ?>}
.sidebar-search i{color:<?php echo $is_full_login ? 'var(--muted)' : 'var(--orange)'; ?>;font-size:13px}
.sidebar-search input{border:none;background:none;outline:none;font-size:14px;width:100%;color:var(--text)}
.sidebar-search input::placeholder{color:var(--muted)}
.sidebar-search input:disabled{cursor:not-allowed}
/* Tooltip shown on hover when search is locked */
.search-locked-tip{
    display:none;position:absolute;top:calc(100% + 8px);left:0;right:0;
    background:#1c1e21;color:#fff;font-size:12px;border-radius:8px;
    padding:8px 12px;text-align:center;z-index:20;pointer-events:none;
    white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.18);
}
.search-locked-tip::before{content:'';position:absolute;top:-5px;left:20px;border-width:0 5px 5px;border-style:solid;border-color:transparent transparent #1c1e21}
<?php if (!$is_full_login): ?>
.sidebar-search:hover .search-locked-tip{display:block}
<?php endif; ?>

.guest-banner{margin:10px 16px 0;padding:9px 13px;background:#fff8e1;border:1px solid #ffe082;border-radius:9px;font-size:12px;color:#7a5c00;display:flex;align-items:center;gap:7px}
.guest-banner a{color:var(--orange);font-weight:700;text-decoration:none}
.guest-banner a:hover{text-decoration:underline}

.btn-new-chat{margin:10px 16px;padding:10px 16px;background:var(--orange);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s}
.btn-new-chat:hover{background:var(--orange-dk)}

.conv-list{flex:1;overflow-y:auto}
.conv-list::-webkit-scrollbar{width:4px}
.conv-list::-webkit-scrollbar-thumb{background:#d4d4d4;border-radius:4px}
.conv-list-label{padding:10px 16px 4px;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

/* PART 3 FIX: Sort controls */
.sort-controls{display:flex;align-items:center;gap:6px;padding:6px 16px 6px;border-bottom:1px solid var(--border)}
.sort-label{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap}
.sort-btn{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;border:1px solid var(--border);background:none;color:var(--sub);cursor:pointer;transition:all .15s;white-space:nowrap}
.sort-btn:hover{border-color:var(--orange);color:var(--orange)}
.sort-btn.sort-btn-active{background:var(--orange);color:#fff;border-color:var(--orange)}

.conv-item{display:flex;align-items:center;gap:12px;padding:10px 16px;cursor:pointer;border-radius:10px;margin:2px 8px;text-decoration:none;color:var(--text);transition:background .12s}
.conv-item:hover{background:#f2f2f2}
.conv-item.active{background:var(--orange-lt)}
.conv-avatar-wrap{position:relative;flex-shrink:0}
.conv-avatar{width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--orange),var(--orange-md));color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px}
.conv-dot{position:absolute;bottom:2px;right:2px;width:13px;height:13px;background:var(--online);border:2.5px solid var(--white);border-radius:50%}
.conv-info{flex:1;min-width:0}
.conv-name-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:3px}
.conv-name{font-size:15px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-time{font-size:11px;color:var(--muted);white-space:nowrap;flex-shrink:0;margin-left:8px}
.conv-item.active .conv-time{color:var(--orange)}
.conv-preview{font-size:13px;color:var(--sub);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-badge{min-width:20px;height:20px;background:var(--orange);color:#fff;border-radius:10px;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;padding:0 5px;flex-shrink:0}
.sdot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:4px}
.dot-open{background:#31a24c}.dot-pending{background:#f5a623}.dot-closed{background:#bbb}.dot-resolved{background:#4a90d9}
.no-conv{padding:48px 24px;text-align:center;color:var(--muted);font-size:14px}
.no-conv i{font-size:40px;display:block;margin-bottom:12px;opacity:.3}

/* CHAT PANEL */
.chat-panel{flex:1;display:flex;flex-direction:column;background:var(--bg);overflow:hidden}
.chat-empty-state{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);gap:14px;padding:40px;text-align:center}
.big-icon{width:80px;height:80px;border-radius:50%;background:var(--orange-lt);display:flex;align-items:center;justify-content:center;font-size:34px;color:var(--orange)}
.chat-empty-state h3{font-size:20px;color:var(--text);font-weight:700}
.chat-empty-state p{font-size:14px;max-width:280px;line-height:1.5}
.btn-start-empty{padding:11px 24px;background:var(--orange);color:#fff;border:none;border-radius:50px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background .15s}
.btn-start-empty:hover{background:var(--orange-dk)}

/* HEADER */
.chat-header{background:var(--white);border-bottom:1px solid var(--border);padding:12px 20px;display:flex;align-items:center;gap:14px;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.ch-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#374151,#6b7280);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;position:relative;flex-shrink:0}
.ch-online{position:absolute;bottom:1px;right:1px;width:11px;height:11px;background:var(--online);border:2px solid var(--white);border-radius:50%}
.ch-info{flex:1}
.ch-name{font-size:16px;font-weight:700}
.ch-sub{font-size:12px;color:var(--sub);margin-top:1px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.ch-actions{display:flex;gap:8px;align-items:center}
.btn-icon{width:36px;height:36px;border:none;border-radius:50%;background:#f0f2f5;color:var(--sub);font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;text-decoration:none}
.btn-icon:hover{background:var(--border);color:var(--text)}
.sbadge{padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase}

/* DATE SEP */
.date-sep{display:flex;align-items:center;gap:12px;padding:8px 20px;color:var(--muted);font-size:12px}
.date-sep::before,.date-sep::after{content:'';flex:1;height:1px;background:var(--border)}

/* MESSAGES */
.chat-messages{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:4px}
.chat-messages::-webkit-scrollbar{width:5px}
.chat-messages::-webkit-scrollbar-thumb{background:#d4d4d4;border-radius:4px}
.msg-group{display:flex;flex-direction:column;gap:2px;margin-bottom:8px}
.group-me{align-items:flex-end}
.group-them{align-items:flex-start}
.group-admin{align-items:flex-start}
.group-system{align-items:center}
.msg-sender-name{font-size:12px;font-weight:600;color:var(--sub);padding:0 12px;margin-bottom:3px}
.msg-row{display:flex;align-items:flex-end;gap:8px;max-width:72%}
.group-me .msg-row{flex-direction:row-reverse}
.group-admin .msg-row{flex-direction:row}
.group-admin .msg-time-label{text-align:left}
.msg-av{width:28px;height:28px;border-radius:50%;background:#374151;color:#fff;font-size:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-bottom:2px}
.av-bot{background:#7c3aed}
.msg-bubble{padding:9px 14px;border-radius:18px;font-size:14px;line-height:1.5;word-break:break-word}
.group-me .msg-bubble{background:var(--orange);color:#fff;border-bottom-right-radius:5px}
.group-them .msg-bubble{background:var(--bubble-them);color:var(--text);border-bottom-left-radius:5px}
.bubble-auto{background:#ede9fe!important;color:#4c1d95!important;border:1px solid #ddd6fe!important}
.bubble-system{background:transparent!important;color:var(--muted)!important;font-size:12px!important;font-style:italic;border:none!important;text-align:center;padding:4px 10px!important}
/* ── Admin blue bubble (seen by customer) ── */
.bubble-admin,.group-admin .msg-bubble{background:var(--admin-blue)!important;color:#fff!important;border-bottom-left-radius:5px}
/* ── Bot/auto purple bubble ── */
.av-bot{background:var(--bot-purple)}
.av-admin{background:var(--admin-blue)}
.av-customer{background:var(--orange)}
/* ── Customer own message (orange, right) ── */
.bubble-customer,.group-me .msg-bubble{background:var(--orange)!important;color:#fff!important;border-bottom-right-radius:5px}
.bubble-self{background:var(--orange);color:#fff;border-bottom-right-radius:5px}
.msg-time-label{font-size:10px;color:var(--muted);padding:0 4px;margin-top:2px}
.group-me .msg-time-label{text-align:right}
.group-them .msg-time-label{text-align:left}

/* INPUT */
.chat-input-wrap{background:var(--white);border-top:1px solid var(--border);padding:12px 16px;display:flex;align-items:flex-end;gap:10px}
.input-box-wrap{flex:1;background:#f0f2f5;border-radius:22px;display:flex;align-items:flex-end;padding:8px 14px;gap:10px}
#msg-input{flex:1;background:none;border:none;outline:none;font-size:15px;font-family:inherit;resize:none;max-height:120px;overflow-y:auto;line-height:1.4;color:var(--text)}
#msg-input::placeholder{color:var(--muted)}
.chat-send-btn{width:42px;height:42px;border:none;border-radius:50%;background:var(--orange);color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,transform .1s}
.chat-send-btn:hover{background:var(--orange-dk)}
.chat-send-btn:active{transform:scale(.92)}
.chat-send-btn:disabled{background:#ccc;cursor:not-allowed}
.closed-bar{background:var(--white);border-top:1px solid var(--border);padding:14px 20px;text-align:center;color:var(--sub);font-size:13px;display:flex;align-items:center;justify-content:center;gap:10px}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;z-index:5000;background:rgba(0,0,0,.45);align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-box{background:var(--white);border-radius:16px;width:100%;max-width:460px;padding:28px;box-shadow:0 8px 40px rgba(0,0,0,.18);animation:mIn .2s ease;position:relative}
@keyframes mIn{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}
.modal-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;color:#9ca3af;cursor:pointer}
.modal-close:hover{color:#374151}
.modal-title{font-size:17px;font-weight:800;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.modal-sub{font-size:13px;color:var(--sub);margin-bottom:20px}
.form-field{margin-bottom:14px}
.form-label{display:block;font-size:13px;font-weight:700;color:var(--text);margin-bottom:5px}
.form-input{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box;transition:border .15s}
.form-input:focus{border-color:var(--orange)}
.reason-options{display:flex;flex-direction:column;gap:8px}
.reason-option{display:flex;align-items:center;gap:10px;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:all .15s;font-size:14px}
.reason-option:hover{border-color:var(--orange);background:var(--orange-lt)}
.reason-option input[type=radio]{accent-color:var(--orange);width:16px;height:16px;flex-shrink:0}
.reason-option.selected{border-color:var(--orange);background:var(--orange-lt)}
.reason-icon{font-size:18px;width:24px;text-align:center}
.reason-label{font-weight:700;font-size:14px}
.reason-desc{font-size:12px;color:var(--muted);margin-top:1px}
#other-text-wrap{display:none;margin-top:8px}
.btn-submit{width:100%;padding:12px;background:var(--orange);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s;margin-top:4px}
.btn-submit:hover{background:var(--orange-dk)}
.btn-submit:disabled{background:#ccc;cursor:not-allowed}
.form-error{display:none;padding:9px 13px;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;font-size:13px;font-weight:600;align-items:center;gap:7px;margin-bottom:12px}
.form-error.show{display:flex}

/* ALERT */
#chat-alert-box{position:fixed;bottom:80px;left:50%;transform:translateX(-50%);z-index:9999}
.chat-alert{padding:10px 18px;border-radius:9px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,.15)}
.chat-alert.success{background:#d1fae5;color:#065f46}
.chat-alert.error{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>

<nav class="top-nav">
    <a href="index.php" class="nav-brand"><i class="fas fa-burger"></i> FOODTIME</a>
    <span class="nav-title"><i class="fas fa-headset" style="color:var(--orange)"></i> Support Chat</span>
    <a href="index.php" class="nav-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
</nav>

<div id="chat-alert-box" style="display:none;"></div>

<div class="chat-app">

    <!-- SIDEBAR -->
    <div class="chat-sidebar">
        <div class="sidebar-top">
            <div class="sidebar-heading">Messages</div>

            <!-- Search bar: enabled for full-login only, locked with tooltip for guests -->
            <div class="sidebar-search">
                <i class="fas fa-<?php echo $is_full_login ? 'search' : 'lock'; ?>"></i>
                <input type="text" id="conv-search"
                    placeholder="<?php echo $is_full_login ? 'Search… press Enter' : 'Log in to search'; ?>"
                    <?php if (!$is_full_login): ?>disabled<?php endif; ?>>
                <?php if (!$is_full_login): ?>
                <span class="search-locked-tip">
                    <i class="fas fa-info-circle"></i>&nbsp; Please log in to get full access.
                </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($chat_is_guest): ?>
        <div class="guest-banner">
            <i class="fas fa-user-clock"></i>
            Chatting as <strong><?php echo $user_name; ?></strong> &nbsp;—&nbsp;
            <a href="login.php">Log in for full access</a>
        </div>
        <?php endif; ?>

        <button class="btn-new-chat" onclick="openNewConvModal()">
            <i class="fas fa-plus"></i> New Conversation
        </button>

        <!-- PART 3 FIX: Sort controls (color-priority / reverse) -->
        <div class="sort-controls">
            <span class="sort-label"><i class="fas fa-sort-amount-down"></i> Sort:</span>
            <button class="sort-btn sort-btn-active" id="sort-priority-btn" title="Open → Pending → Resolved → Closed">Priority</button>
            <button class="sort-btn" id="sort-reverse-btn" title="Closed → Resolved → Pending → Open">Reverse</button>
        </div>

        <div class="conv-list" id="conv-list">
            <?php if (empty($conversations)): ?>
                <div class="no-conv"><i class="fas fa-comments"></i>No conversations yet.<br>Start one to get help!</div>
            <?php else: ?>
                <div class="conv-list-label">All Messages &nbsp;<span style="background:#e4e6eb;padding:1px 7px;border-radius:10px;font-size:11px;"><?php echo count($conversations); ?></span></div>
                <?php foreach ($conversations as $conv):
                    $is_active = ($conv['conversation_id'] === $active_conv_id);
                    $unread    = (int)$conv['unread'];
                    $prev      = $conv['last_message'] ? mb_strimwidth($conv['last_message'], 0, 48, '…') : 'No messages yet';
                    $ago       = $conv['last_msg_time'] ? time_ago($conv['last_msg_time']) : '';
                    $search_str = strtolower($conv['subject'] . ' ' . ($conv['category'] ?? ''));
                ?>
                <a class="conv-item <?php echo $is_active ? 'active' : ''; ?>"
                   href="customer_chat.php?conv_id=<?php echo urlencode($conv['conversation_id']); ?>"
                   data-search="<?php echo htmlspecialchars($search_str); ?>">
                    <div class="conv-avatar-wrap">
                        <div class="conv-avatar"><i class="fas fa-headset"></i></div>
                        <?php if (in_array($conv['status'], ['open','pending'])): ?><div class="conv-dot"></div><?php endif; ?>
                    </div>
                    <div class="conv-info">
                        <div class="conv-name-row">
                            <div class="conv-name"><?php echo htmlspecialchars($conv['subject']); ?></div>
                            <div class="conv-time"><?php echo $ago; ?></div>
                        </div>
                        <div class="conv-preview">
                            <span class="sdot dot-<?php echo $conv['status']; ?>"></span>
                            <?php echo htmlspecialchars($prev); ?>
                        </div>
                    </div>
                    <?php if ($unread > 0): ?><span class="conv-badge"><?php echo $unread; ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- CHAT PANEL -->
    <div class="chat-panel">
        <?php if (!$active_conv): ?>
        <div class="chat-empty-state">
            <div class="big-icon"><i class="fas fa-headset"></i></div>
            <h3>FOODTIME Support</h3>
            <p>Select a conversation or start a new one. We're here to help!</p>
            <button class="btn-start-empty" onclick="openNewConvModal()"><i class="fas fa-plus"></i> New Conversation</button>
        </div>

        <?php else: ?>
        <div class="chat-header">
            <div class="ch-avatar">
                <i class="fas fa-headset"></i>
                <?php if (in_array($active_conv['status'], ['open','pending'])): ?><div class="ch-online"></div><?php endif; ?>
            </div>
            <div class="ch-info">
                <div class="ch-name">FOODTIME Support</div>
                <div class="ch-sub">
                    <?php echo htmlspecialchars($active_conv['subject']); ?>
                    <?php $sc=['open'=>['#d1fae5','#065f46'],'pending'=>['#fef3c7','#92400e'],'resolved'=>['#dbeafe','#1e40af'],'closed'=>['#f3f4f6','#6b7280']]; $sc2=$sc[$active_conv['status']]??['#f3f4f6','#6b7280']; ?>
                    <span class="sbadge" style="background:<?php echo $sc2[0];?>;color:<?php echo $sc2[1];?>"><?php echo ucfirst($active_conv['status']); ?></span>
                    <?php if (!empty($active_conv['category'])): ?>
                        &nbsp;·&nbsp; <i class="fas fa-tag" style="color:var(--orange)"></i> <?php echo htmlspecialchars($active_conv['category']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ch-actions">
                <a href="customer_chat.php" class="btn-icon" title="Back"><i class="fas fa-arrow-left"></i></a>
            </div>
        </div>

        <!-- MESSAGES -->
        <div class="chat-messages" id="chat-messages">
            <?php $prev_date = '';
            foreach ($init_messages as $msg):
                $is_mine  = $msg['is_mine'];
                $msg_type = $msg['message_type'];
                $mdate    = date('d F Y', strtotime($msg['created_at']));
                if ($mdate !== $prev_date): $prev_date = $mdate; ?>
            <div class="date-sep"><?php echo ($mdate === date('d F Y') ? 'Today' : $mdate); ?></div>
            <?php endif;
            if ($msg_type === 'system'): ?>
                <div class="msg-group group-system"><div class="msg-row"><div class="msg-bubble bubble-system"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div></div></div>
            <?php elseif ($msg_type === 'auto_reply'): ?>
                <div class="msg-group group-them">
                    <div class="msg-sender-name">🤖 FOODTIME Support</div>
                    <div class="msg-row">
                        <div class="msg-av av-bot"><i class="fas fa-robot"></i></div>
                        <div><div class="msg-bubble bubble-auto"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div><div class="msg-time-label"><?php echo htmlspecialchars($msg['created_at_fmt']); ?></div></div>
                    </div>
                </div>
            <?php elseif ($is_mine): ?>
                <div class="msg-group group-me">
                    <div class="msg-row"><div><div class="msg-bubble bubble-customer"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div><div class="msg-time-label"><?php echo htmlspecialchars($msg['created_at_fmt']); ?></div></div></div>
                </div>
            <?php elseif (($msg['sender_position'] ?? '') === 'admin'): ?>
                <div class="msg-group group-admin">
                    <div class="msg-sender-name">FOODTIME Support</div>
                    <div class="msg-row">
                        <div class="msg-av av-admin"><i class="fas fa-headset"></i></div>
                        <div><div class="msg-bubble bubble-admin"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div><div class="msg-time-label"><?php echo htmlspecialchars($msg['created_at_fmt']); ?></div></div>
                    </div>
                </div>
            <?php else: ?>
                <!-- sender_position is 'customer' or unknown but is_mine=0: show as customer's own sent msg -->
                <!-- This branch should not occur on the customer page if is_mine logic is correct,
                     but keep as a safety fallback showing as support message -->
                <div class="msg-group group-admin">
                    <div class="msg-sender-name">FOODTIME Support</div>
                    <div class="msg-row">
                        <div class="msg-av av-admin"><i class="fas fa-headset"></i></div>
                        <div><div class="msg-bubble bubble-admin"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div><div class="msg-time-label"><?php echo htmlspecialchars($msg['created_at_fmt']); ?></div></div>
                    </div>
                </div>
            <?php endif; endforeach; ?>
        </div>

        <!-- INPUT -->
        <?php if ($active_conv['status'] !== 'closed'): ?>
        <div class="chat-input-wrap">
            <div class="input-box-wrap">
                <textarea id="msg-input" rows="1" placeholder="Send a message… (Enter to send)" maxlength="400"></textarea>
            </div>
            <button class="chat-send-btn" id="btn-send-msg" title="Send"><i class="fas fa-paper-plane"></i></button>
        </div>
        <div style="text-align:right;padding:2px 18px 6px;font-size:11px;color:var(--muted);"><span id="msg-char-counter">0/400</span></div>
        <?php else: ?>
        <div class="closed-bar"><i class="fas fa-lock"></i> This conversation has been closed by support.
            <button class="btn-new-chat" style="width:auto;padding:6px 14px;margin:0" onclick="openNewConvModal()">New Chat</button>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- NEW CONVERSATION MODAL -->
<div class="modal-overlay" id="newConvModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeNewConvModal()">&times;</button>
        <div class="modal-title"><i class="fas fa-plus-circle" style="color:var(--orange)"></i> New Conversation</div>
        <div class="modal-sub">Select a reason and our team will respond shortly.</div>
        <div class="form-error" id="conv-form-error"><i class="fas fa-exclamation-circle"></i><span id="conv-error-text"></span></div>
        <form id="form-new-conv">
            <input type="hidden" name="subject"  id="conv-subject-hidden">
            <input type="hidden" name="category" id="conv-category-hidden">

            <div class="form-field">
                <label class="form-label">What do you need help with? <span style="color:red">*</span></label>
                <div class="reason-options" id="reason-options">
                    <label class="reason-option" onclick="selectReason(this,'Order Issue')">
                        <input type="radio" name="_reason" value="Order Issue">
                        <span class="reason-icon">🛍️</span>
                        <div><div class="reason-label">Order Issue</div><div class="reason-desc">Missing items, wrong order, delivery problems</div></div>
                    </label>
                    <label class="reason-option" onclick="selectReason(this,'Account Blocked')">
                        <input type="radio" name="_reason" value="Account Blocked">
                        <span class="reason-icon">🔒</span>
                        <div><div class="reason-label">Account Blocked</div><div class="reason-desc">My account has been restricted or blocked</div></div>
                    </label>
                    <label class="reason-option" onclick="selectReason(this,'Other')">
                        <input type="radio" name="_reason" value="Other">
                        <span class="reason-icon">💬</span>
                        <div><div class="reason-label">Other</div><div class="reason-desc">General enquiry or anything else</div></div>
                    </label>
                </div>
                <div id="other-text-wrap">
                    <input type="text" id="other-detail" class="form-input" style="margin-top:10px" placeholder="Please describe your issue briefly…" maxlength="150">
                </div>
            </div>

            <?php if ($is_full_login && !empty($orders)): ?>
            <div class="form-field">
                <label class="form-label">Related Order (optional)</label>
                <select name="related_orderid" class="form-input">
                    <option value="">— No specific order —</option>
                    <?php foreach ($orders as $o): ?>
                    <option value="<?php echo htmlspecialchars($o['orderid']); ?>">
                        <?php echo htmlspecialchars($o['orderid']); ?> — RM <?php echo number_format($o['total_price'],2); ?> (<?php echo htmlspecialchars($o['status']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-submit" id="conv-submit-btn"><i class="fas fa-comments"></i> Start Chat</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="js/chat.js"></script>
<script>
function openNewConvModal()  { document.getElementById('newConvModal').classList.add('show'); }
function closeNewConvModal() { document.getElementById('newConvModal').classList.remove('show'); }
document.getElementById('newConvModal').addEventListener('click', function(e){ if(e.target===this) closeNewConvModal(); });

function selectReason(el, value) {
    document.querySelectorAll('.reason-option').forEach(function(o){ o.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('conv-category-hidden').value = value;
    var subject = value;
    if (value === 'Other') {
        document.getElementById('other-text-wrap').style.display = 'block';
        subject = document.getElementById('other-detail').value.trim() || 'Other';
    } else {
        document.getElementById('other-text-wrap').style.display = 'none';
    }
    document.getElementById('conv-subject-hidden').value = subject;
}

document.getElementById('other-detail').addEventListener('input', function(){
    document.getElementById('conv-subject-hidden').value = this.value.trim() || 'Other';
});

/* Form submit is handled by chat.js jQuery handler — do NOT add a native
   submit listener here or it will conflict and cause a page reload. */

/* Search is handled by chat.js shared listener on #conv-search (data-search attribute on .conv-item) */

<?php if ($active_conv && $active_conv['status'] !== 'closed'): ?>
$(function(){
    $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
    // Pass $last_id so chat.js starts polling AFTER already-rendered messages.
    // This prevents the auto-reply (and all other messages) from appearing twice.
    window.initChatPolling('<?php echo addslashes($active_conv_id); ?>', <?php echo (int)$last_id; ?>);
});
<?php endif; ?>
</script>
</body>
</html>