<?php
session_start();
header('Content-Type: application/json');
include 'base.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['vouchers' => []]);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($connect, 
    "SELECT code, discount_rm FROM user_vouchers 
     WHERE userid = ? AND is_used = 0 
     ORDER BY created_at DESC LIMIT 10");

mysqli_stmt_bind_param($stmt, 's', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$vouchers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $vouchers[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode(['vouchers' => $vouchers]);
?>