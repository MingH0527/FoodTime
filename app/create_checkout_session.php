<?php
/**
 * create_checkout_session.php
 *
 * REFACTORED: This file NO LONGER writes anything to the database.
 * It validates the cart, re-prices from DB, builds totals, and
 * creates a Stripe Checkout session — encoding all order data in
 * Stripe metadata so stripe_webhook.php can reconstruct the order
 * after confirmed payment.
 *
 * TAX RULE: Tax is calculated on the FULL subtotal.
 * Voucher discount is deducted AFTER tax is applied.
 * Formula: total = subtotal + tax - discount
 */

ob_start();
error_reporting(0);

session_start();
include 'base.php';

header('Content-Type: application/json');

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = $_SESSION['user_phone'] ?? '';

// ── Stripe key ────────────────────────────────────────────────────────────────
if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY', 'sk_test_51TIVNs2YxaDgs62n5oTjvCYoyecGQvmEXArp7dUMLS5Dn5T5kRAHmxgBlBSjnWqNYtpBdlx9yDrzJ9PzP4iOAt3Z00R1il7vIy');
}

// ── Load Stripe early (fail fast before any work) ─────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    ob_end_clean();
    echo json_encode(['error' => 'Stripe library not found. Run: composer require stripe/stripe-php']);
    exit;
}
require_once $autoload;
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// ── Parse JSON body ───────────────────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
    ob_end_clean();
    echo json_encode(['error' => 'Invalid request body.']);
    exit;
}

$cart_data    = $data['cart_data']    ?? '[]';
$order_type   = $data['order_type']   ?? 'dinein';
$voucher_data = $data['voucher_data'] ?? '';
$cart         = json_decode($cart_data, true);

if (empty($cart) || !is_array($cart)) {
    ob_end_clean();
    echo json_encode(['error' => 'Cart is empty.']);
    exit;
}

// ── Re-fetch prices from DB ───────────────────────────────────────────────────
$safe_ids = [];
foreach ($cart as $item) {
    $safe_ids[] = "'" . mysqli_real_escape_string($connect, trim($item['id'])) . "'";
}
$id_list   = implode(',', $safe_ids);
$pq        = mysqli_query($connect, "SELECT productid, price, name FROM products WHERE productid IN ($id_list)");
$db_prices = [];
if ($pq) {
    while ($r = mysqli_fetch_assoc($pq))
        $db_prices[$r['productid']] = ['price' => (float)$r['price'], 'name' => $r['name']];
}

// ── Calculate subtotal ────────────────────────────────────────────────────────
$subtotal = 0;
foreach ($cart as $item) {
    $db_price   = isset($db_prices[$item['id']]) ? $db_prices[$item['id']]['price'] : floatval($item['price'] ?? 0);
    $unit_price = ($db_price > 0) ? $db_price : floatval($item['price'] ?? 0);
    $subtotal  += $unit_price * intval($item['quantity'] ?? 1);
}
$subtotal = round($subtotal, 2);

// ── Parse voucher ─────────────────────────────────────────────────────────────
$voucher          = null;
$voucher_discount = 0;
$voucher_code_str = '';
if (!empty($voucher_data)) {
    $voucher = json_decode($voucher_data, true);
    if ($voucher && isset($voucher['discount_rm'])) {
        $voucher_discount = min(round(floatval($voucher['discount_rm']), 2), $subtotal);
        $voucher_code_str = strtoupper($voucher['code'] ?? '');
    }
}

// ── FIX: Tax on FULL subtotal, discount deducted after ───────────────────────
$tax         = round($subtotal * 0.06, 2);
$total       = round($subtotal + $tax - $voucher_discount, 2);
$total_cents = intval(round($total * 100));

if ($total_cents < 50) {
    ob_end_clean();
    echo json_encode(['error' => 'Order total is below the minimum payment amount (RM 0.50).']);
    exit;
}

// ── Map order type ────────────────────────────────────────────────────────────
$order_type_db = ($order_type === 'dinein') ? 'dine_in' : 'takeaway';

// ── Build cart payload for metadata (base64-encoded JSON) ─────────────────────
$enriched_cart = [];
foreach ($cart as $item) {
    $pid        = trim($item['id']);
    $db_p       = isset($db_prices[$pid]) ? $db_prices[$pid]['price'] : floatval($item['price'] ?? 0);
    $db_name    = isset($db_prices[$pid]) ? $db_prices[$pid]['name']  : ($item['name'] ?? 'Item');
    $qty        = intval($item['quantity'] ?? 1);

    $custom = [];
    if (!empty($item['spice_level'])) $custom['spice_level'] = $item['spice_level'];
    if (!empty($item['bev_temp']))    $custom['bev_temp']    = $item['bev_temp'];
    if (!empty($item['serve_time']))  $custom['serve_time']  = $item['serve_time'];
    if (!empty($item['removes']))     $custom['removes']     = $item['removes'];
    if (!empty($item['addons']))      $custom['addons']      = $item['addons'];

    $enriched_cart[] = [
        'id'       => $pid,
        'name'     => $db_name,
        'price'    => $db_p,
        'quantity' => $qty,
        'custom'   => $custom,
    ];
}

$cart_payload    = base64_encode(json_encode($enriched_cart));
$voucher_payload = $voucher ? base64_encode(json_encode($voucher)) : '';

$chunk_size    = 480;
$payload_parts = str_split($cart_payload, $chunk_size);
$metadata      = [
    'user_id'         => (string)$user_id,
    'user_name'       => mb_substr($user_name,  0, 100),
    'user_email'      => mb_substr($user_email, 0, 200),
    'user_phone'      => mb_substr($user_phone, 0, 30),
    'order_type'      => $order_type_db,
    'subtotal'        => (string)$subtotal,
    'discount_rm'     => (string)$voucher_discount,
    'tax'             => (string)$tax,
    'total_price'     => (string)$total,
    'voucher_code'    => $voucher_code_str,
    'voucher_payload' => mb_substr($voucher_payload, 0, 480),
    'cart_chunks'     => (string)count($payload_parts),
];

foreach ($payload_parts as $i => $part) {
    $metadata['cart_payload_' . $i] = $part;
}

if (count($metadata) > 50) {
    ob_end_clean();
    echo json_encode(['error' => 'Cart is too large to process via Stripe Checkout. Please reduce your order.']);
    exit;
}

// ── Build Stripe line items ───────────────────────────────────────────────────
// Items at full price (tax not yet applied on Stripe side — handled via metadata)
$line_items = [];
foreach ($enriched_cart as $ec) {
    $line_items[] = [
        'price_data' => [
            'currency'     => 'myr',
            'unit_amount'  => intval(round($ec['price'] * 100)),
            'product_data' => ['name' => $ec['name']],
        ],
        'quantity' => $ec['quantity'],
    ];
}

// Add tax as a separate line item
if ($tax > 0) {
    $line_items[] = [
        'price_data' => [
            'currency'     => 'myr',
            'unit_amount'  => intval(round($tax * 100)),
            'product_data' => ['name' => 'Service Tax (6%)'],
        ],
        'quantity' => 1,
    ];
}

// ── Voucher as Stripe coupon (deducted AFTER tax, matching our formula) ───────
$stripe_discounts = [];
if ($voucher_discount > 0) {
    try {
        $coupon = \Stripe\Coupon::create([
            'name'            => 'Voucher: ' . $voucher_code_str,
            'amount_off'      => intval(round($voucher_discount * 100)),
            'currency'        => 'myr',
            'duration'        => 'once',
            'max_redemptions' => 1,
            'id'              => 'VOUCHER-' . strtoupper(substr(md5(uniqid($user_id . $voucher_code_str, true)), 0, 8)),
        ]);
        $stripe_discounts[] = ['coupon' => $coupon->id];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[FOODTIME] Stripe coupon creation failed: ' . $e->getMessage());
        // Non-fatal: discount is recorded in metadata, webhook will apply it.
    }
}

// ── Build URLs ────────────────────────────────────────────────────────────────
$base_url    = 'http://localhost:8000/';
$success_url = $base_url . 'stripe_success.php?session_id={CHECKOUT_SESSION_ID}';
$cancel_url  = $base_url . 'stripe_cancel.php';

// ── Create Stripe Checkout Session ───────────────────────────────────────────
try {
    $session_params = [
        'payment_method_types' => ['card'],
        'line_items'           => $line_items,
        'mode'                 => 'payment',
        'success_url'          => $success_url,
        'cancel_url'           => $cancel_url,
        'metadata'             => $metadata,
    ];

    if (!empty($stripe_discounts)) $session_params['discounts']      = $stripe_discounts;
    if (!empty($user_email))       $session_params['customer_email'] = $user_email;

    $session = \Stripe\Checkout\Session::create($session_params);

    ob_end_clean();
    echo json_encode(['url' => $session->url]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    ob_end_clean();
    echo json_encode(['error' => 'Stripe error: ' . $e->getMessage()]);
}