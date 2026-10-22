<?php
/* ============================================================
   auto_reply_helper.php — FOODTIME Support Chat

   PURPOSE: Returns a category-specific auto-reply string for
   use ONLY at conversation creation time.
   Returns null if the category is unrecognised.

   Auto-replies are triggered ONLY by the 3 valid categories:
     - Order Issue
     - Account Blocked
     - Other

   UPGRADED: Richer messages with estimated response time,
   helpful next-step hints, and a warm tone. No structural
   changes — return type and function signature unchanged.
   ============================================================ */

/**
 * Returns the current hour-based greeting (Morning / Afternoon / Evening).
 */
function _chat_greeting(): string {
    $h = (int) date('G'); // 0-23, Asia/KL already set by caller
    if ($h < 12) return 'Good morning';
    if ($h < 17) return 'Good afternoon';
    return 'Good evening';
}

/**
 * Returns an estimated wait time string based on current hour (Asia/KL).
 * Peak hours (11–14, 17–21) → longer estimate.
 */
function _chat_eta(): string {
    $h = (int) date('G');
    $is_peak = ($h >= 11 && $h <= 14) || ($h >= 17 && $h <= 21);
    return $is_peak
        ? 'within 15–30 minutes'
        : 'within 5–15 minutes';
}

/**
 * Main entry point — unchanged signature.
 *
 * @param  string      $category  One of: 'Order Issue', 'Account Blocked', 'Other'
 * @return string|null            Auto-reply text, or null for unknown category
 */
function get_auto_reply($category): ?string {
    $cat     = strtolower(trim($category));
    $greet   = _chat_greeting();
    $eta     = _chat_eta();
    $now     = date('h:i A');      // e.g. "02:35 PM"
    $date    = date('d M Y');      // e.g. "06 Apr 2026"

    switch ($cat) {

        // ── Order Issue ──────────────────────────────────────────
        case 'order issue':
            return implode("\n", [
                "👋 {$greet}! Thank you for reaching out to FOODTIME Support.",
                "",
                "We've received your Order Issue report at {$now} on {$date}.",
                "",
                "⏱ A support agent will be with you {$eta}.",
                "",
                "To help us resolve this faster, please share:",
                "  • Your Order ID (e.g. ORD001234)",
                "  • What went wrong — missing item, wrong order, late delivery, etc.",
                "  • Any photos if relevant",
                "",
                "We're on it! 🍽️",
            ]);

        // ── Account Blocked ──────────────────────────────────────
        case 'account blocked':
            return implode("\n", [
                "👋 {$greet}! Thank you for contacting FOODTIME Support.",
                "",
                "We've received your Account Blocked request at {$now} on {$date}.",
                "",
                "⏱ A support agent will review your case {$eta}.",
                "",
                "To speed up your appeal, please share:",
                "  • Your username or registered email",
                "  • When you noticed the restriction",
                "  • Any error message you received",
                "",
                "We'll do our best to get you back on track! 🔓",
            ]);

        // ── Other / General ──────────────────────────────────────
        case 'other':
            return implode("\n", [
                "👋 {$greet}! Thank you for contacting FOODTIME Support.",
                "",
                "We've received your enquiry at {$now} on {$date}.",
                "",
                "⏱ A support agent will be with you {$eta}.",
                "",
                "Please go ahead and describe your issue in as much detail as you can — the more context you give us, the faster we can help! 💬",
            ]);

        default:
            return null;
    }
}
?>