<?php
/* ============================================================
   create_conversation.php — FOODTIME Support Chat

   Flow:
   1. Insert conversation row
   2. Insert exactly ONE auto_reply message based on category
      (no separate "system greeting" — the auto_reply IS the greeting)
   No other automatic messages are inserted here.
   ============================================================ */

ob_start();

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'base.php';
include 'guest_chat_auth.php';
include 'auto_reply_helper.php';

ob_end_clean();
header('Content-Type: application/json');

// ---------- helpers ----------
function json_fail($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function getNextConversationId($connect) {
    $sql = "SELECT MAX(CAST(SUBSTRING(conversation_id, 3) AS UNSIGNED)) AS max_num
            FROM chat_conversations WHERE conversation_id LIKE 'CH%'";
    $res = mysqli_query($connect, $sql);
    if (!$res) return false;
    $row = mysqli_fetch_assoc($res);
    $next = ((int)($row['max_num'] ?? 0)) + 1;
    return 'CH' . str_pad($next, 6, '0', STR_PAD_LEFT);
}

function getNextMessageId($connect) {
    $sql = "SELECT MAX(CAST(SUBSTRING(message_id, 4) AS UNSIGNED)) AS max_num
            FROM chat_messages WHERE message_id LIKE 'MSG%'";
    $res = mysqli_query($connect, $sql);
    if (!$res) return false;
    $row = mysqli_fetch_assoc($res);
    $next = ((int)($row['max_num'] ?? 0)) + 1;
    return 'MSG' . str_pad($next, 6, '0', STR_PAD_LEFT);
}

function getAvailableAdminId($connect) {
    $sql = "SELECT userid FROM users WHERE position = 'admin' AND status = 'active' ORDER BY userid ASC LIMIT 1";
    $res = mysqli_query($connect, $sql);
    if (!$res) return false;
    $row = mysqli_fetch_assoc($res);
    return $row ? $row['userid'] : false;
}

// ---------- auth ----------
if (!$chat_authed) {
    json_fail('Please verify your identity to start a chat.');
}

if (!$chat_is_guest && ($_SESSION['position'] ?? '') !== 'customer') {
    json_fail('Admin accounts cannot use this form.');
}

// ---------- input ----------
$customer_id     = $chat_user_id;
$category        = isset($_POST['category']) ? trim($_POST['category']) : '';
$subject         = isset($_POST['subject'])  ? trim($_POST['subject'])  : '';
$related_orderid = isset($_POST['related_orderid']) ? trim($_POST['related_orderid']) : '';

// ---------- validation ----------
$valid_categories = ['Order Issue', 'Account Blocked', 'Other'];
if ($category === '' || !in_array($category, $valid_categories, true)) {
    json_fail('Please select a valid reason category.');
}

$subject = ($category !== 'Other')
    ? $category
    : (($subject !== '') ? mb_substr($subject, 0, 150) : 'Other');

// Validate order only for logged-in customer
$related_val = null;
if ($related_orderid !== '' && !$chat_is_guest) {
    $stmt = mysqli_prepare($connect, "SELECT orderid FROM orders WHERE orderid = ? AND userid = ?");
    if (!$stmt) json_fail('Database prepare error: ' . mysqli_error($connect));
    mysqli_stmt_bind_param($stmt, 'ss', $related_orderid, $customer_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if (!$res || !mysqli_fetch_assoc($res)) {
        mysqli_stmt_close($stmt);
        json_fail('Invalid order selected.');
    }
    mysqli_stmt_close($stmt);
    $related_val = $related_orderid;
}

// ---------- transaction ----------
mysqli_begin_transaction($connect);

try {
    // 1) Find an available admin
    $admin_id = getAvailableAdminId($connect);
    if ($admin_id === false) {
        throw new Exception('No active admin is available right now.');
    }

    // 2) Generate conversation ID
    $conv_id = getNextConversationId($connect);
    if ($conv_id === false) throw new Exception('Failed to generate conversation ID.');

    // 3) Insert conversation row
    $stmt = mysqli_prepare($connect,
        "INSERT INTO chat_conversations
         (conversation_id, customer_id, admin_id, related_orderid, subject, category, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())");
    if (!$stmt) throw new Exception('Prepare failed: ' . mysqli_error($connect));
    mysqli_stmt_bind_param($stmt, 'ssssss', $conv_id, $customer_id, $admin_id, $related_val, $subject, $category);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('Failed to create conversation: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // 4) Insert ONE auto_reply based on category (this is the only automatic message)
    $auto_text = get_auto_reply($category);
    if ($auto_text !== null) {
        $msg_id = getNextMessageId($connect);
        if ($msg_id === false) throw new Exception('Failed to generate message ID.');

        // sender_id = NULL for system/auto messages
        // NOTE: requires sender_id column to be nullable — run fix_sender_id_nullable.sql if not already done
        $stmt2 = mysqli_prepare($connect,
            "INSERT INTO chat_messages
             (message_id, conversation_id, sender_id, receiver_id, message_type, message_text, is_read, created_at)
             VALUES (?, ?, NULL, ?, 'auto_reply', ?, 0, NOW())");
        if (!$stmt2) throw new Exception('Prepare failed for auto_reply: ' . mysqli_error($connect));
        mysqli_stmt_bind_param($stmt2, 'ssss', $msg_id, $conv_id, $customer_id, $auto_text);
        if (!mysqli_stmt_execute($stmt2)) throw new Exception('Failed to insert auto_reply: ' . mysqli_stmt_error($stmt2));
        mysqli_stmt_close($stmt2);
    }

    mysqli_commit($connect);

    echo json_encode(['success' => true, 'conv_id' => $conv_id]);
    exit;

} catch (Exception $e) {
    mysqli_rollback($connect);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}