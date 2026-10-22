<?php
/**
 * get_product_options.php
 * Returns the custom_options JSON for a product from the DB.
 * Called by customizeModal.php via fetch().
 *
 * FIXED:
 * - If a product has no custom_options in DB, return success:false so
 *   the modal falls back to LEGACY_OPTIONS or shows empty — NOT generic defaults.
 * - Never auto-saves generic defaults. Admin must configure options explicitly
 *   via Product_form.php for them to appear in the modal.
 *
 * Response:
 *   { success: true,  options: { spice, beverage, dessert, remove: [...], addons: [...] } }
 *     → Product has admin-configured custom_options in DB — use exactly these.
 *
 *   { success: false, options: null }
 *     → Product not found in DB, or has no custom_options saved yet.
 *     → Modal will fall back to LEGACY_OPTIONS[pid] or show empty (no generic defaults).
 */
session_start();
include 'base.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$pid = isset($_GET['pid']) ? preg_replace('/[^a-zA-Z0-9]/', '', trim($_GET['pid'])) : '';

if (empty($pid)) {
    echo json_encode(['success' => false, 'options' => null]);
    exit();
}

$stmt = mysqli_prepare($connect,
    "SELECT custom_options FROM products WHERE productid = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $pid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Product not found in DB at all
if (!$row) {
    echo json_encode(['success' => false, 'options' => null]);
    exit();
}

// Product exists and has custom_options saved by admin — return them
if (!empty($row['custom_options'])) {
    $opts = json_decode($row['custom_options'], true);
    if (is_array($opts)) {
        echo json_encode(['success' => true, 'options' => $opts]);
        exit();
    }
}

// Product exists but has no custom_options configured yet.
// Return false so modal uses LEGACY_OPTIONS or shows empty.
// Admin must go to Product_form.php → Edit to configure options.
echo json_encode(['success' => false, 'options' => null]);
exit();