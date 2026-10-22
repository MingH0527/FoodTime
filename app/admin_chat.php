<?php
/* admin_chat.php — FOODTIME Admin Support Chat
   SHARED ADMIN INBOX edition

   Unread changes vs old version:
   - OLD conv_sql unread subquery:
       cm2.receiver_id='$admin_id' AND cc.admin_id='$admin_id'
       → Only the assigned admin saw unread badges.
   - NEW conv_sql unread subquery:
       customer messages NOT IN chat_message_reads for this admin
       → Every admin sees unread independently; reading marks only for them.

   - OLD total_unread query: same private-admin filter.
   - NEW total_unread query: uses chat_message_reads per-admin table.

   - Removed: auto-assigning admin_id on conversation open.
     admin_id on chat_conversations is kept for "last handled by"
     and reporting, but it no longer controls unread visibility.
*/
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'base.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['position'] ?? '') !== 'admin') { header('Location: index.php'); exit; }

$admin_id   = $_SESSION['user_id'];
$admin_name = htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Admin');
$admin_init = strtoupper(substr($admin_name, 0, 1));

$active_conv_id = isset($_GET['conv_id']) ? trim($_GET['conv_id']) : '';
$filter_status  = isset($_GET['status'])  ? trim($_GET['status'])  : 'all';

/* ── Conversations ── */
$where_status = '';
if (in_array($filter_status, ['open','pending','resolved','closed'])) {
    $safe = mysqli_real_escape_string($connect, $filter_status);
    $where_status = "AND cc.status='$safe'";
}

$safe_admin = mysqli_real_escape_string($connect, $admin_id);

/*
 * NEW unread subquery:
 * Count customer-sent text messages in this conversation that have
 * NO record in chat_message_reads for the current admin.
 * Removed: receiver_id = '$admin_id' AND cc.admin_id = '$admin_id'
 */
$conv_sql = "SELECT cc.*,
                    u.username AS customer_name,
                    u.email    AS customer_email,
                    u.profile_picture AS customer_pic,
                    (SELECT COUNT(*)
                     FROM chat_messages cm2
                     JOIN chat_conversations cc2 ON cc2.conversation_id = cm2.conversation_id
                     WHERE cm2.conversation_id = cc.conversation_id
                       AND cm2.message_type IN ('text')
                       AND cm2.sender_id = cc2.customer_id
                       AND NOT EXISTS (
                           SELECT 1 FROM chat_message_reads cmr
                           WHERE cmr.message_id = cm2.message_id
                             AND cmr.admin_id = '$safe_admin'
                       )
                    ) AS unread,
                    (SELECT cm3.message_text FROM chat_messages cm3
                     WHERE cm3.conversation_id=cc.conversation_id ORDER BY cm3.created_at DESC LIMIT 1) AS last_message,
                    (SELECT cm4.created_at FROM chat_messages cm4
                     WHERE cm4.conversation_id=cc.conversation_id ORDER BY cm4.created_at DESC LIMIT 1) AS last_msg_time
             FROM chat_conversations cc
             JOIN users u ON u.userid=cc.customer_id
             WHERE 1=1 $where_status
             ORDER BY CASE cc.status WHEN 'open' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END ASC, cc.updated_at DESC
             LIMIT 100";

$conversations = [];
$conv_result = mysqli_query($connect, $conv_sql);
while ($row = mysqli_fetch_assoc($conv_result)) { $conversations[] = $row; }

/* ── Active conversation ── */
$active_conv   = null;
$init_messages = [];
$last_id       = 0;

if (!empty($active_conv_id)) {

    $s = mysqli_prepare($connect,
        "SELECT cc.*, u.username AS customer_name, u.email AS customer_email,
                u.profile_picture AS customer_pic
         FROM chat_conversations cc
         JOIN users u ON u.userid = cc.customer_id
         WHERE cc.conversation_id = ?"
    );
    mysqli_stmt_bind_param($s, 's', $active_conv_id);
    mysqli_stmt_execute($s);
    $res = mysqli_stmt_get_result($s);
    $active_conv = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    mysqli_stmt_close($s);

    if ($active_conv) {

        /*
         * REMOVED: auto-assign admin_id on open.
         * admin_id is now only updated when an admin explicitly replies,
         * or you can update it here as a "last viewed by" signal — but
         * it must NOT control unread visibility (chat_message_reads does that).
         *
         * Keeping the update as "last handled by" is optional.
         * Uncomment the block below if you want "last handled by" tracking:
         *
         * $s2 = mysqli_prepare($connect,
         *     "UPDATE chat_conversations SET admin_id = ? WHERE conversation_id = ?");
         * mysqli_stmt_bind_param($s2, 'ss', $admin_id, $active_conv_id);
         * mysqli_stmt_execute($s2);
         * mysqli_stmt_close($s2);
         */

        $s3 = mysqli_prepare($connect,
            "SELECT cm.message_id,
                    cm.conversation_id,
                    cm.sender_id,
                    cm.receiver_id,
                    cm.message_text,
                    cm.message_type,
                    cm.is_read,
                    cm.created_at,
                    DATE_FORMAT(cm.created_at, '%d %b %Y, %h:%i %p') AS created_at_fmt,
                    CAST(SUBSTRING(cm.message_id, 4) AS UNSIGNED) AS message_id_num,
                    u.position AS sender_position,
                    u.username AS sender_name,
                    u.profile_picture AS sender_pic
             FROM chat_messages cm
             LEFT JOIN users u ON u.userid = cm.sender_id
             WHERE cm.conversation_id = ?
             ORDER BY cm.created_at ASC
             LIMIT 200"
        );
        if (!$s3) { die('S3 prepare failed: ' . mysqli_error($connect)); }

        mysqli_stmt_bind_param($s3, 's', $active_conv_id);
        mysqli_stmt_execute($s3);

        $res3 = mysqli_stmt_get_result($s3);
        while ($row = mysqli_fetch_assoc($res3)) {
            if ($row['message_type'] === 'system' || $row['message_type'] === 'auto_reply') {
                $row['is_mine'] = 0;
            } else {
                $row['is_mine'] = ($row['sender_id'] === $admin_id) ? 1 : 0;
            }
            $init_messages[] = $row;
        }
        mysqli_free_result($res3);
        mysqli_stmt_close($s3);

        /*
         * NEW: Mark messages as read for THIS admin only using chat_message_reads.
         * INSERT IGNORE means re-opening the conversation is idempotent.
         * Other admins are NOT marked as read — they each see their own unread state.
         *
         * OLD code was:
         *   UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND receiver_id=?
         *   → This only worked for the assigned admin and broke shared inbox.
         */
        $s4 = mysqli_prepare($connect,
            "INSERT IGNORE INTO chat_message_reads (message_id, admin_id)
             SELECT cm.message_id, ?
             FROM chat_messages cm
             JOIN chat_conversations cc ON cc.conversation_id = cm.conversation_id
             WHERE cm.conversation_id = ?
               AND cm.message_type IN ('text')
               AND cm.sender_id = cc.customer_id"
        );
        mysqli_stmt_bind_param($s4, 'ss', $admin_id, $active_conv_id);
        mysqli_stmt_execute($s4);
        mysqli_stmt_close($s4);

        if (!empty($init_messages)) {
            $last_id = (int) end($init_messages)['message_id_num'];
        }
    }
}

/* ── Counts for filter tabs ── */
$counts = ['all'=>0,'open'=>0,'pending'=>0,'resolved'=>0,'closed'=>0];
$cr = mysqli_query($connect, "SELECT status, COUNT(*) AS cnt FROM chat_conversations GROUP BY status");
while ($r = mysqli_fetch_assoc($cr)) { $counts[$r['status']] = (int)$r['cnt']; $counts['all'] += (int)$r['cnt']; }

/*
 * NEW total_unread: all customer messages across all conversations
 * that have no read record for this admin.
 * Removed: cc.admin_id='$admin_id' AND cm.receiver_id='$admin_id'
 */
$total_unread_row = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT COUNT(*) AS cnt
     FROM chat_messages cm
     JOIN chat_conversations cc ON cc.conversation_id = cm.conversation_id
     WHERE cm.message_type IN ('text')
       AND cm.sender_id = cc.customer_id
       AND NOT EXISTS (
           SELECT 1 FROM chat_message_reads cmr
           WHERE cmr.message_id = cm.message_id
             AND cmr.admin_id = '$safe_admin'
       )"));
$total_unread = (int)($total_unread_row['cnt'] ?? 0);

function time_ago_a($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60) return 'Just now';
    if ($d < 3600) return floor($d/60).'m ago';
    if ($d < 86400) return floor($d/3600).'h ago';
    if ($d < 604800) return floor($d/86400).'d ago';
    return date('d M', strtotime($dt));
}

function get_avatar_html($pic, $initial, $classes = '', $size = 46) {
    $path = 'uploads/profiles/' . $pic;
    if (!empty($pic) && file_exists($path)) {
        return '<img src="' . htmlspecialchars($path) . '"
                     alt="' . htmlspecialchars($initial) . '"
                     style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;object-fit:cover;display:block;"
                     class="' . $classes . '">';
    }
    return '<div class="conv-av ' . $classes . '" style="width:' . $size . 'px;height:' . $size . 'px;">' . htmlspecialchars($initial) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Support Chat — Admin | FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/admin.css">
<style>
/* ── CHAT-SPECIFIC OVERRIDES (dark theme) ── */
:root {
    --chat-bg:      #0f0f10;
    --chat-surface: #17181a;
    --chat-surface2:#1f2023;
    --chat-border:  #2c2d31;
    --chat-text:    #f5f5f5;
    --chat-muted:   #9a9ca5;
    --orange:       #ff6b35;
    --orange-dk:    #e05a28;
    --bubble-me:    #ff6b35;
    --bubble-them:  #2c2d31;
    --online:       #4ade80;
    --admin-blue:   #2980d4;
    --admin-blue-dk:#1d6fa8;
}

/* ── Full-height chat layout inside .main ── */
.chat-main-wrap {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 56px);
    overflow: hidden;
}

.chat-main-wrap .topbar { flex-shrink: 0; }

.chat-body {
    flex: 1;
    display: flex;
    overflow: hidden;
}

/* ── LEFT: conversation list panel ── */
.chat-conv-panel {
    width: 320px;
    min-width: 320px;
    background: var(--chat-surface);
    border-right: 1px solid var(--chat-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ccp-top {
    padding: 16px;
    border-bottom: 1px solid var(--chat-border);
    flex-shrink: 0;
}

.ccp-heading {
    font-family: 'Syne', sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--chat-text);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ccp-search {
    display: flex;
    align-items: center;
    background: var(--chat-surface2);
    border: 1px solid var(--chat-border);
    border-radius: 50px;
    padding: 8px 14px;
    gap: 8px;
}
.ccp-search i { color: var(--chat-muted); font-size: 12px; }
.ccp-search input { border: none; background: none; outline: none; font-size: 13px; color: var(--chat-text); width: 100%; }
.ccp-search input::placeholder { color: var(--chat-muted); }

/* Filter tabs */
.filter-strip {
    display: flex;
    gap: 4px;
    padding: 10px 12px 0;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.ftab {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid var(--chat-border);
    color: var(--chat-muted);
    background: none;
    transition: all .15s;
    white-space: nowrap;
}
.ftab:hover { color: var(--chat-text); border-color: var(--orange); }
.ftab.active { background: var(--orange); color: #fff; border-color: var(--orange); }

/* Conversation items */
.conv-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}
.conv-scroll::-webkit-scrollbar { width: 4px; }
.conv-scroll::-webkit-scrollbar-thumb { background: var(--chat-border); border-radius: 4px; }

.conv-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    cursor: pointer;
    text-decoration: none;
    color: var(--chat-text);
    transition: background .12s;
    border-radius: 10px;
    margin: 2px 8px;
}
.conv-item:hover { background: var(--chat-surface2); }
.conv-item.active { background: rgba(255,107,53,.12); border-left: 3px solid var(--orange); padding-left: 13px; }

.conv-av-wrap { position: relative; flex-shrink: 0; }
.conv-av {
    width: 46px; height: 46px;
    border-radius: 50%;
    background: linear-gradient(135deg, #374151, #6b7280);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; font-weight: 700;
}
.conv-online { position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; background: var(--online); border: 2px solid var(--chat-surface); border-radius: 50%; }

.conv-info { flex: 1; min-width: 0; }
.conv-name-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px; }
.conv-name { font-size: 14px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--chat-text); }
.conv-time { font-size: 10px; color: var(--chat-muted); white-space: nowrap; flex-shrink: 0; }
.conv-item.active .conv-time { color: var(--orange); }
.conv-prev { font-size: 12px; color: var(--chat-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conv-badge { min-width: 18px; height: 18px; background: var(--orange); color: #fff; border-radius: 9px; font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center; padding: 0 4px; flex-shrink: 0; }
.sdot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 4px; }
.dot-open { background: #4ade80; } .dot-pending { background: #facc15; } .dot-closed { background: #6b7280; } .dot-resolved { background: #60a5fa; }

.no-conv { padding: 40px 20px; text-align: center; color: var(--chat-muted); font-size: 13px; }
.no-conv i { font-size: 32px; display: block; margin-bottom: 10px; opacity: .3; }

.conv-subject-line {
    font-size: 11px; color: var(--chat-muted); white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;
    display: flex; align-items: center; gap: 4px; flex-wrap: nowrap;
}
.conv-cat-tag {
    display: inline-block; padding: 0px 6px; border-radius: 10px;
    background: rgba(255,107,53,.15); color: var(--orange);
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .3px; white-space: nowrap; flex-shrink: 0;
}
.conv-order-tag {
    display: inline-block; padding: 0px 5px; border-radius: 8px;
    background: rgba(96,165,250,.12); color: #60a5fa;
    font-size: 10px; font-weight: 600; white-space: nowrap; flex-shrink: 0;
}

/* ── RIGHT: Chat window ── */
.chat-window-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--chat-bg);
    overflow: hidden;
}

.chat-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--chat-muted);
    gap: 14px;
    padding: 40px;
    text-align: center;
}
.empty-icon { width: 72px; height: 72px; border-radius: 50%; background: rgba(255,107,53,.1); display: flex; align-items: center; justify-content: center; font-size: 30px; color: var(--orange); }
.chat-empty-state h3 { font-size: 18px; color: var(--chat-text); font-family: 'Syne', sans-serif; }
.chat-empty-state p { font-size: 13px; max-width: 260px; line-height: 1.5; }

.chat-win-header {
    background: var(--chat-surface);
    border-bottom: 1px solid var(--chat-border);
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-shrink: 0;
}
.cwh-av { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--orange), #ffb347); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; flex-shrink: 0; position: relative; overflow: hidden; padding: 0; }
.cwh-online { position: absolute; bottom: 1px; right: 1px; width: 11px; height: 11px; background: var(--online); border: 2px solid var(--chat-surface); border-radius: 50%; }
.cwh-info { flex: 1; }
.cwh-name { font-size: 15px; font-weight: 700; color: var(--chat-text); }
.cwh-sub { font-size: 12px; color: var(--chat-muted); margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.cwh-actions { display: flex; gap: 8px; align-items: center; }
.btn-icon-dark { width: 34px; height: 34px; border: none; border-radius: 50%; background: var(--chat-surface2); color: var(--chat-muted); font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s; }
.btn-icon-dark:hover { background: var(--chat-border); color: var(--chat-text); }
.sbadge { padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.btn-close-conv { padding: 6px 12px; background: rgba(248,113,113,.15); color: #f87171; border: 1px solid rgba(248,113,113,.3); border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background .15s; }
.btn-status-action { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; border: 1px solid; transition: background .15s; }
.btn-set-pending { background: rgba(250,204,21,.15); color: #facc15; border-color: rgba(250,204,21,.3); }
.btn-set-pending:hover { background: rgba(250,204,21,.28); }
.btn-set-resolved { background: rgba(96,165,250,.15); color: #60a5fa; border-color: rgba(96,165,250,.3); }
.btn-set-resolved:hover { background: rgba(96,165,250,.28); }
.btn-close-conv:hover { background: rgba(248,113,113,.25); }

.date-sep { display: flex; align-items: center; gap: 12px; padding: 8px 20px; color: var(--chat-muted); font-size: 11px; }
.date-sep::before, .date-sep::after { content: ''; flex: 1; height: 1px; background: var(--chat-border); }

.chat-messages { flex: 1; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 4px; }
.chat-messages::-webkit-scrollbar { width: 5px; }
.chat-messages::-webkit-scrollbar-thumb { background: var(--chat-border); border-radius: 4px; }

.msg-group { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.group-me { align-items: flex-end; }
.group-them { align-items: flex-start; }
.group-system { align-items: center; }

.msg-sender-name { font-size: 11px; font-weight: 600; color: var(--chat-muted); padding: 0 12px; margin-bottom: 3px; }

.msg-row { display: flex; align-items: flex-end; gap: 8px; max-width: 72%; }
.group-me .msg-row { flex-direction: row-reverse; }

.msg-av { width: 26px; height: 26px; border-radius: 50%; background: #374151; color: #fff; font-size: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; object-fit: cover; }
.av-bot { background: #7c3aed; }
.av-admin { background: var(--admin-blue) !important; }

.msg-bubble { padding: 9px 14px; border-radius: 18px; font-size: 14px; line-height: 1.5; word-break: break-word; }
.bubble-admin-me { background: var(--admin-blue); color: #fff; border-bottom-right-radius: 5px; }
.group-them .msg-bubble { background: var(--bubble-them); color: var(--chat-text); border-bottom-left-radius: 5px; }
.bubble-auto { background: rgba(124,58,237,.18) !important; color: #c4b5fd !important; border: 1px solid rgba(124,58,237,.3) !important; }
.bubble-system { background: transparent !important; color: var(--chat-muted) !important; font-size: 11px !important; font-style: italic; border: none !important; text-align: center; padding: 3px 8px !important; }
.bubble-customer-admin { background: var(--bubble-them); color: var(--chat-text); border-bottom-left-radius: 5px; }
.msg-time-label { font-size: 10px; color: var(--chat-muted); padding: 0 4px; margin-top: 2px; }
.group-me .msg-time-label { text-align: right; }
.group-them .msg-time-label { text-align: left; }

.chat-input-wrap { background: var(--chat-surface); border-top: 1px solid var(--chat-border); padding: 12px 16px; display: flex; align-items: flex-end; gap: 10px; flex-shrink: 0; }
.input-box-wrap { flex: 1; background: var(--chat-surface2); border: 1px solid var(--chat-border); border-radius: 22px; display: flex; align-items: flex-end; padding: 8px 14px; gap: 10px; }
#msg-input { flex: 1; background: none; border: none; outline: none; font-size: 14px; font-family: inherit; resize: none; max-height: 120px; overflow-y: auto; line-height: 1.4; color: var(--chat-text); }
#msg-input::placeholder { color: var(--chat-muted); }
.chat-send-btn { width: 42px; height: 42px; border: none; border-radius: 50%; background: var(--orange); color: #fff; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background .15s, transform .1s; }
.chat-send-btn:hover { background: var(--orange-dk); }
.chat-send-btn:active { transform: scale(.92); }
.chat-send-btn:disabled { background: #555; cursor: not-allowed; }
.closed-bar { background: var(--chat-surface); border-top: 1px solid var(--chat-border); padding: 14px 20px; text-align: center; color: var(--chat-muted); font-size: 13px; display: flex; align-items: center; justify-content: center; gap: 8px; flex-shrink: 0; }
.status-btn.active { box-shadow: 0 0 0 2px rgba(255,255,255,.12) inset;transform: translateY(-1px);}
#chat-alert-box { position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%); z-index: 9999; }
.chat-alert { padding: 10px 18px; border-radius: 9px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(0,0,0,.3); }
.chat-alert.success { background: rgba(74,222,128,.15); color: #4ade80; border: 1px solid rgba(74,222,128,.3); }
.chat-alert.error   { background: rgba(248,113,113,.15); color: #f87171; border: 1px solid rgba(248,113,113,.3); }

.nav-badge { background: var(--orange); color: #fff; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 10px; margin-left: auto; }
</style>
</head>
<body class="admin-chat-page">

<?php $current_page = 'chat'; include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="chat-main-wrap">

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-title">Support <span>Chat</span></div>
            <div class="topbar-right">
                <div class="topbar-date"><i class="far fa-calendar-alt"></i> <?php echo date('l, d F Y'); ?></div>
                <div class="live-clock"><i class="fas fa-clock"></i><span id="clock-hm"></span><span id="clock-s"></span><span id="clock-ampm"></span></div>
            </div>
        </div>

        <div id="chat-alert-box" style="display:none;"></div>

        <div class="chat-body">

            <!-- ── LEFT: Conversation list ── -->
            <div class="chat-conv-panel">
                <div class="ccp-top">
                    <div class="ccp-heading">
                        Messages
                        <?php if ($counts['all'] > 0): ?>
                            <span style="background:var(--chat-surface2);border:1px solid var(--chat-border);padding:2px 10px;border-radius:10px;font-size:12px;color:var(--chat-muted);"><?php echo $counts['all']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ccp-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="admin-conv-search" placeholder="Search by name or topic… press Enter">
                    </div>
                </div>

                <!-- Filter tabs -->
                <div class="filter-strip">
                    <?php
                    $tabs = ['all'=>'All','open'=>'Open','pending'=>'Pending','resolved'=>'Resolved','closed'=>'Closed'];
                    foreach ($tabs as $key => $label):
                        $url = 'admin_chat.php?status='.$key.(!empty($active_conv_id)?'&conv_id='.urlencode($active_conv_id):'');
                    ?>
                    <a href="<?php echo $url; ?>" class="ftab <?php echo $filter_status===$key?'active':''; ?>">
                        <?php echo $label; ?>
                        <?php if ($counts[$key] > 0): ?><span style="opacity:.7">(<?php echo $counts[$key]; ?>)</span><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Conversation list -->
                <div class="conv-scroll" id="conv-scroll">
                    <?php if (empty($conversations)): ?>
                        <div class="no-conv"><i class="fas fa-inbox"></i>No conversations found.</div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv):
                            $is_active = ($conv['conversation_id'] === $active_conv_id);
                            $unread    = (int)$conv['unread'];
                            $prev      = $conv['last_message'] ? mb_strimwidth($conv['last_message'],0,40,'…') : 'No messages yet';
                            $ago       = $conv['last_msg_time'] ? time_ago_a($conv['last_msg_time']) : '';
                            $init_av   = strtoupper(substr($conv['customer_name'],0,1));
                        ?>
                        <?php
                            $search_str = strtolower(
                                $conv['customer_name'] . ' ' .
                                ($conv['subject'] ?? '') . ' ' .
                                ($conv['category'] ?? '')
                            );
                            $subject_display = htmlspecialchars($conv['subject'] ?? '');
                            $cat_display     = ($conv['category'] ?? '');
                            if ($cat_display && $cat_display !== ($conv['subject'] ?? '')) {
                                $subject_display .= ' <span class="conv-cat-tag">' . htmlspecialchars($cat_display) . '</span>';
                            }
                        ?>
                        <a class="conv-item <?php echo $is_active?'active':''; ?>"
                           href="admin_chat.php?status=<?php echo urlencode($filter_status); ?>&conv_id=<?php echo urlencode($conv['conversation_id']); ?>"
                           data-conv-id="<?php echo htmlspecialchars($conv['conversation_id']); ?>"
                           data-search="<?php echo htmlspecialchars($search_str); ?>">
                            <div class="conv-av-wrap">
                                <?php echo get_avatar_html($conv['customer_pic'] ?? '', $init_av); ?>
                                <?php if (in_array($conv['status'],['open','pending'])): ?><div class="conv-online"></div><?php endif; ?>
                            </div>
                            <div class="conv-info">
                                <div class="conv-name-row">
                                    <div class="conv-name"><?php echo htmlspecialchars($conv['customer_name']); ?></div>
                                    <div class="conv-time"><?php echo $ago; ?></div>
                                </div>
                                <div class="conv-subject-line"><?php echo $subject_display; ?><?php if (!empty($conv['related_orderid'])): ?> <span class="conv-order-tag"><i class="fas fa-shopping-bag"></i> <?php echo htmlspecialchars($conv['related_orderid']); ?></span><?php endif; ?></div>
                                <div class="conv-prev">
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

            <!-- ── RIGHT: Chat window ── -->
            <div class="chat-window-panel">

                <?php if (!$active_conv): ?>
                <div class="chat-empty-state">
                    <div class="empty-icon"><i class="fas fa-headset"></i></div>
                    <h3>Support Inbox</h3>
                    <p>Select a conversation from the list to start replying to customers.</p>
                </div>

                <?php else: ?>

                <!-- HEADER -->
                <div class="chat-win-header">
                    <div class="cwh-av">
                        <?php
                        $cpic  = $active_conv['customer_pic'] ?? '';
                        $cinit = strtoupper(substr($active_conv['customer_name'], 0, 1));
                        if (!empty($cpic) && file_exists('uploads/profiles/' . $cpic)): ?>
                            <img src="uploads/profiles/<?php echo htmlspecialchars($cpic); ?>"
                                 style="width:40px;height:40px;object-fit:cover;border-radius:50%;display:block;" alt="">
                        <?php else: ?>
                            <?php echo htmlspecialchars($cinit); ?>
                        <?php endif; ?>
                        <?php if (in_array($active_conv['status'],['open','pending'])): ?><div class="cwh-online"></div><?php endif; ?>
                    </div>
                    <div class="cwh-info">
                        <div class="cwh-name"><?php echo htmlspecialchars($active_conv['customer_name']); ?></div>
                        <div class="cwh-sub">
                            <?php echo htmlspecialchars($active_conv['subject']); ?>
                            &nbsp;·&nbsp; <?php echo htmlspecialchars($active_conv['conversation_id']); ?>
                            <?php
                            $sc=['open'=>['rgba(74,222,128,.15)','#4ade80'],'pending'=>['rgba(250,204,21,.15)','#facc15'],
                                 'resolved'=>['rgba(96,165,250,.15)','#60a5fa'],'closed'=>['rgba(107,114,128,.15)','#9ca3af']];
                            $sc2=$sc[$active_conv['status']]??['rgba(107,114,128,.15)','#9ca3af'];
                            ?>
                            <span class="sbadge" style="background:<?php echo $sc2[0];?>;color:<?php echo $sc2[1];?>"><?php echo $active_conv['status']; ?></span>
                            <?php if (!empty($active_conv['category'])): ?>
                                &nbsp;·&nbsp; <i class="fas fa-tag" style="color:var(--chat-muted)"></i> <?php echo htmlspecialchars($active_conv['category']); ?>
                            <?php endif; ?>
                            <?php if (!empty($active_conv['related_orderid'])): ?>
                                &nbsp;·&nbsp; <i class="fas fa-shopping-bag" style="color:var(--orange)"></i> <?php echo htmlspecialchars($active_conv['related_orderid']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cwh-actions">
                        <?php if ($active_conv['status'] !== 'closed'): ?>
                        <?php if ($active_conv['status'] !== 'pending'): ?>
                        <button class="status-btn btn-status-action btn-set-pending" onclick="setConvStatus('<?php echo addslashes($active_conv_id); ?>','pending')"><i class="fas fa-clock"></i> Set Pending</button>
                        <?php endif; ?>
                        <?php if ($active_conv['status'] !== 'resolved'): ?>
                        <button class="status-btn btn-status-action btn-set-resolved" onclick="setConvStatus('<?php echo addslashes($active_conv_id); ?>','resolved')"><i class="fas fa-check"></i> Resolved</button>
                        <?php endif; ?>
                        <button class="btn-close-conv" onclick="setConvStatus('<?php echo addslashes($active_conv_id); ?>','closed')"><i class="fas fa-times"></i> Close</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MESSAGES -->
                <div class="chat-messages" id="chat-messages">
                    <?php
                    $prev_date = '';
                    foreach ($init_messages as $msg):
                        $is_mine  = $msg['is_mine'];
                        $msg_type = $msg['message_type'];
                        $pos      = $msg['sender_position'] ?? '';
                        $mdate    = date('d F Y', strtotime($msg['created_at']));
                        $sname    = htmlspecialchars($msg['sender_name'] ?? '');
                        if ($mdate !== $prev_date): $prev_date = $mdate; ?>
                    <div class="date-sep"><?php echo ($mdate === date('d F Y') ? 'Today' : $mdate); ?></div>
                    <?php endif;

                    if ($msg_type === 'system'): ?>
                        <div class="msg-group group-system">
                            <div class="msg-row"><div class="msg-bubble bubble-system"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div></div>
                        </div>

                    <?php elseif ($msg_type === 'auto_reply'): ?>
                        <div class="msg-group group-them">
                            <div class="msg-sender-name">🤖 AutoBot</div>
                            <div class="msg-row">
                                <div class="msg-av av-bot"><i class="fas fa-robot"></i></div>
                                <div>
                                    <div class="msg-bubble bubble-auto"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                    <div class="msg-time-label"><?php echo htmlspecialchars($msg['created_at_fmt']); ?></div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($is_mine || $pos === 'admin'): ?>
                        <div class="msg-group group-me">
                            <div class="msg-row">
                                <div>
                                    <div class="msg-bubble bubble-admin-me"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                    <div class="msg-time-label"><?php echo htmlspecialchars($msg['created_at_fmt']); ?></div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="msg-group group-them">
                            <div class="msg-sender-name"><?php echo $sname ?: 'Customer'; ?></div>
                            <div class="msg-row">
                                <?php
                                $spic      = $msg['sender_pic'] ?? '';
                                $spic_path = 'uploads/profiles/' . $spic;
                                if (!empty($spic) && file_exists($spic_path)): ?>
                                    <img src="<?php echo htmlspecialchars($spic_path); ?>"
                                         class="msg-av" style="object-fit:cover;" alt="">
                                <?php else: ?>
                                    <div class="msg-av av-customer"><?php echo strtoupper(substr($msg['sender_name'] ?? 'C', 0, 1)); ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="msg-bubble bubble-customer-admin"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                    <div class="msg-time-label"><?php echo htmlspecialchars($msg['created_at_fmt']); ?></div>
                                </div>
                            </div>
                        </div>

                    <?php endif; endforeach; ?>
                </div>

                <!-- INPUT -->
                <?php if ($active_conv['status'] !== 'closed'): ?>
                <div class="chat-input-wrap">
                    <div class="input-box-wrap">
                        <textarea id="msg-input" rows="1" placeholder="Reply to customer… (Enter to send, Shift+Enter for new line)" maxlength="400"></textarea>
                    </div>
                    <button class="chat-send-btn" id="btn-send-msg" title="Send"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div style="text-align:right;padding:2px 18px 6px;font-size:11px;color:var(--chat-muted);"><span id="msg-char-counter">0/400</span></div>
                <?php else: ?>
                <div class="closed-bar"><i class="fas fa-lock"></i> This conversation is closed.</div>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="js/chat.js"></script>
<script>
/* Clock */
function updateClock(){
    var n=new Date(),h=n.getHours(),m=n.getMinutes(),s=n.getSeconds(),ap=h>=12?'PM':'AM';
    h=h%12||12;
    document.getElementById('clock-hm').textContent=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');
    document.getElementById('clock-s').textContent=':'+String(s).padStart(2,'0');
    document.getElementById('clock-ampm').textContent=ap;
}
updateClock(); setInterval(updateClock,1000);

<?php if ($active_conv && $active_conv['status'] !== 'closed'): ?>
$(function(){
    $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
    window.initChatPolling('<?php echo addslashes($active_conv_id); ?>', <?php echo (int)$last_id; ?>);
    window.startConvListPolling();
});
<?php else: ?>
$(function(){ window.startConvListPolling(); });
<?php endif; ?>
</script>

</body>
</html>