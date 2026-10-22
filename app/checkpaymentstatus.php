<?php
// checkpaymentstatus.php — called by JS polling on the computer page every 2 seconds
header('Content-Type: application/json');
header('Cache-Control: no-store');

include 'base.php';

$orderid = isset($_GET['orderid']) ? trim($_GET['orderid']) : '';

if (empty($orderid)) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

$stmt = mysqli_prepare($connect, "SELECT status FROM orders WHERE orderid = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['status' => 'not_found']);
    exit;
}
mysqli_stmt_bind_param($stmt, 's', $orderid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row    = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

// Returns orders.status directly: 'pending', 'paid', 'preparing', 'completed', 'cancelled'
echo json_encode(['status' => $row['status']]);