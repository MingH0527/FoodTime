<?php
/* ============================================================
   fetch_messages.php — FOODTIME Support Chat
   SHARED ADMIN INBOX edition

   READ tracking change:
   - BEFORE: UPDATE chat_messages SET is_read=1 WHERE receiver_id = admin
             → only marked read for the one admin who "owned" the conversation.
   - AFTER (admin path):
             INSERT IGNORE INTO chat_message_reads (message_id, admin_id)
             for every unread customer message in this conversation.
             → marks read ONLY for THIS admin; other admins are unaffected.

   Customer side (receiver_id on chat_messages) is unchanged:
             UPDATE chat_messages SET is_read=1 WHERE receiver_id = customer
             → this still works correctly for the customer's own unread count.
   ============================================================ */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'base.php';

header('Content-Type: application/json');

// ── Resolve viewer identity ──
if (isset($_SESSION['user_id']) && ($_SESSION['position'] ?? '') === 'admin') {
    $user_id  = $_SESSION['user_id'];
    $position = 'admin';
    $authed   = true;
}
elseif (isset($_SESSION['user_id']) && ($_SESSION['position'] ?? '') === 'customer') {
    $user_id  = $_SESSION['user_id'];
    $position = 'customer';
    $authed   = true;
}
elseif (
    isset($_SESSION['guest_chat_userid'], $_SESSION['guest_chat_expires']) &&
    $_SESSION['guest_chat_expires'] >= time()
) {
    $_SESSION['guest_chat_expires'] = time() + 3600;
    $user_id  = $_SESSION['guest_chat_userid'];
    $position = 'customer';
    $authed   = true;
}
else {
    $authed = false;
}

if (!$authed) {
    echo json_encode(['success' => false, 'message' => 'Please verify your identity to view messages.']);
    exit;
}

$conv_id = isset($_GET['conv_id']) ? trim($_GET['conv_id']) : '';
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if (empty($conv_id)) {
    echo json_encode(['success' => false, 'message' => 'No conversation specified.']);
    exit;
}

// ── Verify access ──
$stmt = mysqli_prepare($connect,
    "SELECT customer_id, admin_id FROM chat_conversations WHERE conversation_id = ?");
mysqli_stmt_bind_param($stmt, 's', $conv_id);
mysqli_stmt_execute($stmt);
$conv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$conv) {
    echo json_encode(['success' => false, 'message' => 'Conversation not found.']);
    exit;
}

// Customers can only view their own conversations; admins can view all.
if ($position === 'customer' && $conv['customer_id'] !== $user_id) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// ── Fetch messages after last_id ──
$stmt2 = mysqli_prepare($connect,
    "SELECT
        cm.message_id,
        CAST(SUBSTRING(cm.message_id, 4) AS UNSIGNED) AS message_id_num,
        cm.conversation_id,
        cm.sender_id,
        cm.receiver_id,
        cm.message_type,
        cm.message_text,
        cm.is_read,
        DATE_FORMAT(cm.created_at, '%d %b %Y, %h:%i %p') AS created_at_fmt,
        u.position  AS sender_position,
        u.username  AS sender_name
     FROM chat_messages cm
     LEFT JOIN users u ON u.userid = cm.sender_id
     WHERE cm.conversation_id = ?
       AND CAST(SUBSTRING(cm.message_id, 4) AS UNSIGNED) > ?
     ORDER BY cm.created_at ASC, cm.message_id ASC
     LIMIT 50");
mysqli_stmt_bind_param($stmt2, 'si', $conv_id, $last_id);
mysqli_stmt_execute($stmt2);
$result = mysqli_stmt_get_result($stmt2);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    if ($row['message_type'] === 'system' || $row['message_type'] === 'auto_reply') {
        $row['is_mine']     = 0;
        $row['sender_type'] = $row['message_type'];
        $row['sender_name'] = ($row['message_type'] === 'auto_reply') ? 'AutoBot' : 'System';
    } else {
        $row['is_mine']     = ($row['sender_id'] !== null && $row['sender_id'] === $user_id) ? 1 : 0;
        $row['sender_type'] = $row['sender_position'] ?? 'customer';
        if (empty($row['sender_name'])) {
            $row['sender_name'] = ($row['sender_type'] === 'admin') ? 'FOODTIME Support' : 'Customer';
        }
    }
    $row['message_id_num'] = (int)$row['message_id_num'];
    $messages[] = $row;
}
mysqli_stmt_close($stmt2);

// ── Mark messages as read ──
if (!empty($messages)) {

    if ($position === 'admin') {
        // NEW shared-inbox read tracking:
        // Insert a read record for THIS admin for every customer text message in this conversation.
        // Uses INSERT IGNORE so re-opening the conversation is safe (no duplicates).
        // Other admins are NOT affected — they each get their own read record when they open it.
        $stmt3 = mysqli_prepare($connect,
            "INSERT IGNORE INTO chat_message_reads (message_id, admin_id)
             SELECT cm.message_id, ?
             FROM chat_messages cm
             JOIN chat_conversations cc ON cc.conversation_id = cm.conversation_id
             WHERE cm.conversation_id = ?
               AND cm.message_type IN ('text')
               AND cm.sender_id = cc.customer_id");
        mysqli_stmt_bind_param($stmt3, 'ss', $user_id, $conv_id);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);

    } else {
        // Customer side: unchanged — mark admin messages as read using receiver_id.
        $stmt3 = mysqli_prepare($connect,
            "UPDATE chat_messages
             SET is_read = 1
             WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt3, 'ss', $conv_id, $user_id);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);
    }
}

// ── Unread counts for admin sidebar badge ──
// NEW: use chat_message_reads per-admin table instead of receiver_id + admin_id filter.
$unread_counts = [];
if ($position === 'admin') {
    $stmt4 = mysqli_prepare($connect,
        "SELECT cm.conversation_id, COUNT(*) AS cnt
         FROM chat_messages cm
         JOIN chat_conversations cc ON cc.conversation_id = cm.conversation_id
         WHERE cm.message_type IN ('text')
           AND cm.sender_id = cc.customer_id
           AND NOT EXISTS (
               SELECT 1 FROM chat_message_reads cmr
               WHERE cmr.message_id = cm.message_id
                 AND cmr.admin_id = ?
           )
         GROUP BY cm.conversation_id");
    mysqli_stmt_bind_param($stmt4, 's', $user_id);
    mysqli_stmt_execute($stmt4);
    $r4 = mysqli_stmt_get_result($stmt4);
    while ($r = mysqli_fetch_assoc($r4)) {
        $unread_counts[$r['conversation_id']] = (int)$r['cnt'];
    }
    mysqli_stmt_close($stmt4);
}

echo json_encode([
    'success'       => true,
    'messages'      => $messages,
    'unread_counts' => $unread_counts
]);
exit;
?>