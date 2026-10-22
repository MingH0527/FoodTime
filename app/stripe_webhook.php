<?php
/**
 * stripe_webhook.php
 *
 * SIMPLIFIED: stripe_success.php is now the sole writer of orders,
 * order_items, and payments. This webhook no longer creates any records.
 *
 * It still validates the Stripe signature (so Stripe keeps sending events
 * without errors) and logs events for your records.
 *
 * Keep this endpoint registered in your Stripe Dashboard — it does no
 * harm and can be extended later if you need async event handling.
 */

include 'base.php';

if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY',     'sk_test_51TIVNs2YxaDgs62n5oTjvCYoyecGQvmEXArp7dUMLS5Dn5T5kRAHmxgBlBSjnWqNYtpBdlx9yDrzJ9PzP4iOAt3Z00R1il7vIy');
}
if (!defined('STRIPE_WEBHOOK_SECRET')) {
    define('STRIPE_WEBHOOK_SECRET', 'whsec_REPLACE_WITH_YOUR_WEBHOOK_SECRET');
}

// ── Load Stripe ───────────────────────────────────────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe library missing.']);
    exit;
}
require_once $autoload;
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// ── Verify webhook signature ──────────────────────────────────────────────────
$payload    = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, STRIPE_WEBHOOK_SECRET);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload.']);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature.']);
    exit;
}

// ── Log and acknowledge — no DB writes ───────────────────────────────────────
// Order creation is handled by stripe_success.php on the redirect.
// The webhook is kept here only for logging and future extensibility.

switch ($event->type) {

    case 'checkout.session.completed':
        $session = $event->data->object;
        error_log('[FOODTIME Webhook] checkout.session.completed — session: ' . $session->id
            . ' | payment_status: ' . ($session->payment_status ?? 'unknown'));
        // No DB writes. stripe_success.php inserted the order when the user
        // was redirected back. The dedup check in that file prevents duplicates.
        break;

    case 'checkout.session.expired':
        $session = $event->data->object;
        error_log('[FOODTIME Webhook] checkout.session.expired — session: ' . $session->id
            . ' (no order was ever inserted, nothing to clean up)');
        break;

    case 'payment_intent.payment_failed':
        $intent = $event->data->object;
        error_log('[FOODTIME Webhook] payment_intent.payment_failed — intent: '
            . ($intent->id ?? 'unknown'));
        break;

    default:
        error_log('[FOODTIME Webhook] Unhandled event type: ' . $event->type);
        break;
}

// ── Always return 200 ─────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['received' => true]);