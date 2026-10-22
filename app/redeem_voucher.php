<?php
session_start();
include 'base.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$tierid  = isset($_POST['tierid']) ? (int)$_POST['tierid'] : 0;

if ($tierid <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid voucher tier']);
    exit();
}

// Get tier info
$tier_stmt = mysqli_prepare($connect, "SELECT * FROM voucher_tiers WHERE tierid = ? AND is_active = 1");
mysqli_stmt_bind_param($tier_stmt, 'i', $tierid);
mysqli_stmt_execute($tier_stmt);
$tier_res = mysqli_stmt_get_result($tier_stmt);
$tier     = mysqli_fetch_assoc($tier_res);
mysqli_stmt_close($tier_stmt);

if (!$tier) {
    echo json_encode(['success' => false, 'message' => 'Voucher tier not found']);
    exit();
}

// Get user current points
$pts_stmt = mysqli_prepare($connect, "SELECT membership_points FROM users WHERE userid = ?");
mysqli_stmt_bind_param($pts_stmt, 's', $user_id);
mysqli_stmt_execute($pts_stmt);
$pts_res  = mysqli_stmt_get_result($pts_stmt);
$pts_row  = mysqli_fetch_assoc($pts_res);
mysqli_stmt_close($pts_stmt);

if (!$pts_row) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$current_points = (int)$pts_row['membership_points'];
$points_needed  = (int)$tier['points_cost'];

if ($current_points < $points_needed) {
    echo json_encode([
        'success' => false,
        'message' => 'Not enough points. You have ' . $current_points . ' pts but need ' . $points_needed . ' pts.'
    ]);
    exit();
}

// Generate unique voucher code
function generateVoucherCode() {
    return 'FT' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

$code = generateVoucherCode();
$check_stmt = mysqli_prepare($connect, "SELECT voucherid FROM user_vouchers WHERE code = ?");
while (true) {
    mysqli_stmt_bind_param($check_stmt, 's', $code);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    if (mysqli_stmt_num_rows($check_stmt) === 0) break;
    $code = generateVoucherCode();
}
mysqli_stmt_close($check_stmt);

// Deduct points + insert voucher in transaction
mysqli_begin_transaction($connect);
try {
    $upd_stmt = mysqli_prepare($connect,
        "UPDATE users SET membership_points = membership_points - ? WHERE userid = ? AND membership_points >= ?");
    mysqli_stmt_bind_param($upd_stmt, 'isi', $points_needed, $user_id, $points_needed);
    mysqli_stmt_execute($upd_stmt);
    if (mysqli_stmt_affected_rows($upd_stmt) === 0) {
        throw new Exception('Points deduction failed');
    }
    mysqli_stmt_close($upd_stmt);

    $ins_stmt = mysqli_prepare($connect,
        "INSERT INTO user_vouchers (userid, code, discount_rm, points_spent) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($ins_stmt, 'ssdi', $user_id, $code, $tier['discount_rm'], $points_needed);
    mysqli_stmt_execute($ins_stmt);
    mysqli_stmt_close($ins_stmt);

    mysqli_commit($connect);

    // Get updated points
    $new_stmt = mysqli_prepare($connect, "SELECT membership_points FROM users WHERE userid = ?");
    mysqli_stmt_bind_param($new_stmt, 's', $user_id);
    mysqli_stmt_execute($new_stmt);
    $new_res = mysqli_stmt_get_result($new_stmt);
    $new_row = mysqli_fetch_assoc($new_res);
    mysqli_stmt_close($new_stmt);

    echo json_encode([
        'success'      => true,
        'code'         => $code,
        'discount_rm'  => number_format($tier['discount_rm'], 2),
        'points_spent' => $points_needed,
        'points_left'  => (int)$new_row['membership_points'],
        'message'      => 'Voucher redeemed successfully!'
    ]);

} catch (Exception $e) {
    mysqli_rollback($connect);
    echo json_encode(['success' => false, 'message' => 'Redemption failed. Please try again.']);
}