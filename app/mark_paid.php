<?php
session_start();
include 'base.php';

header('Content-Type: application/json');

// Admin only
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['position'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Get order ID
$order_id = mysqli_real_escape_string($connect, trim($_POST['order_id'] ?? ''));

if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit();
}

// Check order exists
$check = mysqli_query($connect, "
    SELECT orderid, status, payment_method 
    FROM orders 
    WHERE orderid = '$order_id'
");

if (!$check || mysqli_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

$order = mysqli_fetch_assoc($check);

// Validate: cash orders only
if ($order['payment_method'] !== 'cash') {
    echo json_encode(['success' => false, 'message' => 'Only cash orders allowed']);
    exit();
}

// Validate: must be pending
if ($order['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Order is not pending']);
    exit();
}

$now = date('Y-m-d H:i:s');

// START TRANSACTION
mysqli_begin_transaction($connect);

try {

    // ✅ 1. Update order status to paid
    $update_order = mysqli_query($connect, "
        UPDATE orders 
        SET status = 'paid', updated_at = '$now' 
        WHERE orderid = '$order_id'
    ");

    if (!$update_order) {
        throw new Exception('Order update failed: ' . mysqli_error($connect));
    }

    // ✅ 2. Get payment record
    $get_payment = mysqli_query($connect, "
        SELECT paymentid 
        FROM payments 
        WHERE orderid = '$order_id'
        LIMIT 1
    ");

    if (!$get_payment || mysqli_num_rows($get_payment) === 0) {
        throw new Exception('Payment record not found for this order');
    }

    $payment    = mysqli_fetch_assoc($get_payment);
    $payment_id = $payment['paymentid'];

    // ✅ 3. Update payment status to paid
    $update_payment = mysqli_query($connect, "
        UPDATE payments 
        SET status = 'paid' 
        WHERE paymentid = '$payment_id'
    ");

    if (!$update_payment) {
        throw new Exception('Payment update failed: ' . mysqli_error($connect));
    }

    // ✅ FIX: Capture affected rows BEFORE commit
    // (mysqli_commit resets affected_rows to 0, so checking after commit always returns 0
    //  which was causing a false exception → rollback even on successful updates)
    $rows_affected = mysqli_affected_rows($connect);

    // ✅ COMMIT now that both updates succeeded
    mysqli_commit($connect);

    // Inform if payment was already marked paid (no-op), vs freshly updated
    if ($rows_affected === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment was already marked as paid'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Order marked as paid successfully'
        ]);
    }

} catch (Exception $e) {

    // ROLLBACK on any error
    mysqli_rollback($connect);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit();
?>