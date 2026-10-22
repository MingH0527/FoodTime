<?php
session_start();
include 'base.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$code    = strtoupper(trim($_POST['code'] ?? ''));

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a voucher code']);
    exit();
}

// Check voucher: must belong to this user, not yet used
$stmt = mysqli_prepare($connect,
    "SELECT * FROM user_vouchers WHERE code = ? AND userid = ? AND is_used = 0");
mysqli_stmt_bind_param($stmt, 'ss', $code, $user_id);
mysqli_stmt_execute($stmt);
$res     = mysqli_stmt_get_result($stmt);
$voucher = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$voucher) {
    // Give a clearer message: distinguish wrong code vs already used
    $stmt2 = mysqli_prepare($connect,
        "SELECT is_used FROM user_vouchers WHERE code = ? AND userid = ?");
    mysqli_stmt_bind_param($stmt2, 'ss', $code, $user_id);
    mysqli_stmt_execute($stmt2);
    $res2   = mysqli_stmt_get_result($stmt2);
    $check  = mysqli_fetch_assoc($res2);
    mysqli_stmt_close($stmt2);

    if ($check && $check['is_used'] == 1) {
        echo json_encode(['success' => false, 'message' => 'This voucher has already been used']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid voucher code']);
    }
    exit();
}

echo json_encode([
    'success'     => true,
    'code'        => $voucher['code'],
    'discount_rm' => number_format((float)$voucher['discount_rm'], 2),
    'voucherid'   => $voucher['voucherid'],
    'message'     => 'Voucher applied! RM' . number_format((float)$voucher['discount_rm'], 2) . ' discount'
]);