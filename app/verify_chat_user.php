<?php
/* ============================================================
   verify_chat_user.php — FOODTIME Support Chat
   Called via AJAX when a visitor wants to use the guest chat.

   Allows BOTH active customers AND blocked customers through,
   so blocked users can contact support to appeal their block.
   Rejects: non-existent usernames, admin accounts.
   ============================================================ */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'base.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your username.']);
    exit;
}

if (strlen($username) > 80) {
    echo json_encode(['success' => false, 'message' => 'Username is too long.']);
    exit;
}

$stmt = mysqli_prepare($connect,
    "SELECT userid, username, position, status FROM users WHERE username = ? LIMIT 1");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'No account found with that username. Please re-enter or register first.'
    ]);
    exit;
}

// Admin accounts must use the admin panel
if ($user['position'] === 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Admin accounts must log in to use the chat system.'
    ]);
    exit;
}

// ── Both active AND blocked customers are allowed ──
// Blocked users need support access precisely because they cannot log in.
$is_blocked = ($user['status'] === 'blocked');

$token = bin2hex(random_bytes(16));

$_SESSION['guest_chat_userid']   = $user['userid'];
$_SESSION['guest_chat_username'] = $user['username'];
$_SESSION['guest_chat_token']    = $token;
$_SESSION['guest_chat_expires']  = time() + 3600;

echo json_encode([
    'success'    => true,
    'token'      => $token,
    'username'   => $user['username'],
    'is_blocked' => $is_blocked
]);
exit;
?>