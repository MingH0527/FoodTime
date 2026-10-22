<?php
/* ============================================================
   send_message.php — FOODTIME Support Chat
   SHARED ADMIN INBOX edition

   receiver_id change:
   - BEFORE: customer messages sent to conv['admin_id'] only.
             This meant only the assigned admin could see the message
             as "received", breaking shared inbox.
   - AFTER:  customer messages have receiver_id = NULL.
             Unread detection for admins now uses chat_message_reads
             (per-admin read tracking table), not receiver_id.
             Admin-to-customer messages still set receiver_id = customer_id
             so the customer's own unread count works correctly.

   This file NEVER changes conversation status.
   ============================================================ */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'base.php';

header('Content-Type: application/json');

// ── Resolve sender identity ──
if (isset($_SESSION['user_id']) && ($_SESSION['position'] ?? '') === 'admin') {
    $sender_id = $_SESSION['user_id'];
    $position  = 'admin';
    $authed    = true;
} elseif (isset($_SESSION['user_id']) && ($_SESSION['position'] ?? '') === 'customer') {
    $sender_id = $_SESSION['user_id'];
    $position  = 'customer';
    $authed    = true;
} elseif (
    isset($_SESSION['guest_chat_userid'], $_SESSION['guest_chat_expires']) &&
    $_SESSION['guest_chat_expires'] >= time()
) {
    $_SESSION['guest_chat_expires'] = time() + 3600;
    $sender_id = $_SESSION['guest_chat_userid'];
    $position  = 'customer';
    $authed    = true;
} else {
    $authed = false;
}

if (!$authed) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$conv_id = trim($_POST['conv_id'] ?? '');
$text    = trim($_POST['message']  ?? '');

if (empty($conv_id)) {
    echo json_encode(['success' => false, 'message' => 'No conversation specified.']);
    exit;
}
if ($text === '') {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
    exit;
}
if (mb_strlen($text) > 400) {
    echo json_encode(['success' => false, 'message' => 'Message too long (max 400 characters).']);
    exit;
}

// ── Load conversation & verify access ──
$sc = mysqli_prepare($connect,
    "SELECT conversation_id, customer_id, admin_id, status FROM chat_conversations
     WHERE conversation_id = ?");
mysqli_stmt_bind_param($sc, 's', $conv_id);
mysqli_stmt_execute($sc);
$conv = mysqli_fetch_assoc(mysqli_stmt_get_result($sc));
mysqli_stmt_close($sc);

if (!$conv) {
    echo json_encode(['success' => false, 'message' => 'Conversation not found.']);
    exit;
}
if ($conv['status'] === 'closed') {
    echo json_encode(['success' => false, 'message' => 'This conversation is closed.']);
    exit;
}
if ($position === 'customer' && $conv['customer_id'] !== $sender_id) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// ── Determine receiver_id ──
// Admin → customer: set receiver_id = customer so customer sees unread badge.
// Customer → admins: receiver_id = NULL.
//   Admin unread is now tracked via chat_message_reads (shared table),
//   NOT via receiver_id. Setting receiver_id to one admin would break
//   shared inbox by hiding the message from all other admins.
if ($position === 'admin') {
    $receiver_id = $conv['customer_id'];
} else {
    $receiver_id = null;   // shared inbox: all admins detect via chat_message_reads
}

// ── Generate message ID ──
$last_row = mysqli_fetch_assoc(mysqli_query($connect,
    "SELECT message_id FROM chat_messages ORDER BY created_at DESC, message_id DESC LIMIT 1"));
$msg_num = $last_row
    ? ((int) preg_replace('/[^0-9]/', '', $last_row['message_id']) + 1)
    : 1;
$msg_id = 'MSG' . str_pad($msg_num, 6, '0', STR_PAD_LEFT);

// ── Insert message ──
$si = mysqli_prepare($connect,
    "INSERT INTO chat_messages
     (message_id, conversation_id, sender_id, receiver_id, message_type, message_text, is_read, created_at)
     VALUES (?, ?, ?, ?, 'text', ?, 0, NOW())");
mysqli_stmt_bind_param($si, 'sssss', $msg_id, $conv_id, $sender_id, $receiver_id, $text);

if (!mysqli_stmt_execute($si)) {
    mysqli_stmt_close($si);
    echo json_encode(['success' => false, 'message' => 'Database error inserting message.']);
    exit;
}
mysqli_stmt_close($si);

// ── Touch conversation updated_at only (NO status change) ──
$su = mysqli_prepare($connect,
    "UPDATE chat_conversations SET updated_at = NOW() WHERE conversation_id = ?");
mysqli_stmt_bind_param($su, 's', $conv_id);
mysqli_stmt_execute($su);
mysqli_stmt_close($su);

echo json_encode(['success' => true, 'message_id' => $msg_id]);
exit;
?>