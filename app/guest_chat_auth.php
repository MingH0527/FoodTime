<?php
/* ============================================================
   guest_chat_auth.php — FOODTIME Support Chat
   Place in: (root folder, same level as index.php)

   PURPOSE: Resolve the current request into unified chat-auth
   variables regardless of whether the user is:
     (a) fully logged in as a customer,
     (b) a verified guest (active user who hasn't logged in),
     (c) a verified blocked-user guest.

   After including this file you have:
     $chat_authed        (bool)   — true if chat access is permitted
     $chat_user_id       (string) — userid of the chat user
     $chat_username      (string) — display username
     $chat_position      (string) — always 'customer' when authed
     $chat_is_guest      (bool)   — true when NOT a full login
     $chat_display_name  (string) — short first-name for UI greetings
                                    (first word of username, title-cased)

   UPGRADED (no breaking changes):
     • Token-from-URL: if ?guest_token=XYZ is present and the
       session is not yet active, we look it up in the DB and
       hydrate the session — so sharing the link works reliably.
     • Sliding-window expiry: refreshed on EVERY request, not just
       on Path B/C entry, so an active chat never times out mid-way.
     • $chat_display_name helper added for friendlier UI greetings.
     • Constant GUEST_CHAT_TTL controls the session lifetime (1 h).
   ============================================================ */

if (!defined('GUEST_CHAT_TTL')) {
    define('GUEST_CHAT_TTL', 3600); // seconds — change once here to affect everything
}

$chat_authed       = false;
$chat_user_id      = '';
$chat_username     = '';
$chat_position     = 'customer';
$chat_is_guest     = false;
$chat_display_name = 'there';  // safe fallback for greeting ("Hi there!")

/* ── Helper: derive a short display name from a username ── */
function _chat_first_name(string $username): string {
    $first = explode(' ', trim($username))[0];
    return $first !== '' ? ucfirst(strtolower($first)) : 'there';
}

/* ── Path A: full logged-in customer session ── */
if (
    isset($_SESSION['user_id'], $_SESSION['position']) &&
    $_SESSION['position'] === 'customer'
) {
    $chat_authed       = true;
    $chat_user_id      = $_SESSION['user_id'];
    $chat_username     = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Customer';
    $chat_is_guest     = false;
    $chat_display_name = _chat_first_name($chat_username);

    // Slide guest expiry too, in case they had a guest session that's still alive
    if (isset($_SESSION['guest_chat_expires'])) {
        $_SESSION['guest_chat_expires'] = time() + GUEST_CHAT_TTL;
    }

    return; // done
}

/* ── Path B / C: active guest session already in $_SESSION ── */
if (
    isset($_SESSION['guest_chat_userid'], $_SESSION['guest_chat_expires']) &&
    $_SESSION['guest_chat_expires'] >= time()
) {
    // Sliding-window refresh on every request
    $_SESSION['guest_chat_expires'] = time() + GUEST_CHAT_TTL;

    $chat_authed       = true;
    $chat_user_id      = $_SESSION['guest_chat_userid'];
    $chat_username     = $_SESSION['guest_chat_username'] ?? 'Guest';
    $chat_is_guest     = true;
    $chat_display_name = _chat_first_name($chat_username);

    return;
}

/* ── Path D (UPGRADE): guest_token passed in the URL ──────────
   This covers the case where a user opens a shared link in a
   new browser / tab and their guest session has expired.
   We look up the token in the DB and re-hydrate the session.
   Requires a `guest_chat_tokens` table:

     CREATE TABLE IF NOT EXISTS guest_chat_tokens (
         token        VARCHAR(64)  NOT NULL PRIMARY KEY,
         userid       VARCHAR(32)  NOT NULL,
         username     VARCHAR(80)  NOT NULL,
         expires_at   DATETIME     NOT NULL,
         created_at   DATETIME     NOT NULL DEFAULT NOW()
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   The token is inserted by verify_chat_user.php when it issues
   the guest session. If the table doesn't exist yet, this block
   is silently skipped — no errors, no breakage.
   ─────────────────────────────────────────────────────────── */
if (isset($_GET['guest_token']) && is_string($_GET['guest_token'])) {
    $raw_token = trim($_GET['guest_token']);

    if ($raw_token !== '' && isset($connect)) {
        // Check table exists before querying to avoid fatal errors on old installs
        $tbl_check = @mysqli_query($connect,
            "SELECT 1 FROM guest_chat_tokens LIMIT 1");

        if ($tbl_check !== false) {
            $stmt = mysqli_prepare($connect,
                "SELECT userid, username FROM guest_chat_tokens
                 WHERE token = ? AND expires_at >= NOW()
                 LIMIT 1");

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $raw_token);
                mysqli_stmt_execute($stmt);
                $tok_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);

                if ($tok_row) {
                    // Hydrate session
                    $_SESSION['guest_chat_userid']   = $tok_row['userid'];
                    $_SESSION['guest_chat_username'] = $tok_row['username'];
                    $_SESSION['guest_chat_expires']  = time() + GUEST_CHAT_TTL;

                    $chat_authed       = true;
                    $chat_user_id      = $tok_row['userid'];
                    $chat_username     = $tok_row['username'];
                    $chat_is_guest     = true;
                    $chat_display_name = _chat_first_name($chat_username);

                    return;
                }
            }
        }
    }
}

/* ── Not authenticated ── */
$chat_authed = false;