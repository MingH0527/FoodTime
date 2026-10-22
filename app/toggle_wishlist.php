<?php
ob_start();
session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

include 'base.php';
ob_clean();

$userid    = $_SESSION['user_id'];
$productid = isset($_POST['productid']) ? trim($_POST['productid']) : '';

if ($productid === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

// ✅ Check if already in wishlist
$check = mysqli_prepare($connect,
    "SELECT id FROM wishlist WHERE userid = ? AND productid = ?");

// ✅ If prepare() fails, it returns false — catch it
if (!$check) {
    echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . mysqli_error($connect)]);
    exit;
}

mysqli_stmt_bind_param($check, 'ss', $userid, $productid);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
$exists = mysqli_stmt_num_rows($check) > 0;
mysqli_stmt_close($check);

if ($exists) {
    $del = mysqli_prepare($connect,
        "DELETE FROM wishlist WHERE userid = ? AND productid = ?");
    if (!$del) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($connect)]);
        exit;
    }
    mysqli_stmt_bind_param($del, 'ss', $userid, $productid);
    $ok = mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    echo json_encode([
        'success'    => (bool)$ok,
        'wishlisted' => false,
        'message'    => $ok ? 'Removed from wishlist' : mysqli_error($connect)
    ]);
} else {
    $ins = mysqli_prepare($connect,
        "INSERT INTO wishlist (userid, productid) VALUES (?, ?)");
    if (!$ins) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($connect)]);
        exit;
    }
    mysqli_stmt_bind_param($ins, 'ss', $userid, $productid);
    $ok = mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);
    echo json_encode([
        'success'    => (bool)$ok,
        'wishlisted' => true,
        'message'    => $ok ? 'Added to wishlist' : mysqli_error($connect)
    ]);
}

mysqli_close($connect);