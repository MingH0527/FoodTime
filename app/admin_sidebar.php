<?php
/*
 * admin_sidebar.php — include on every admin page
 * SHARED ADMIN INBOX edition
 *
 * Unread badge change:
 * - OLD: fetch_conversations.php?action=unread_counts used
 *        cc.admin_id = $admin_id AND cm.receiver_id = $admin_id
 *        → badge only showed on the assigned admin's sidebar.
 * - NEW: fetch_conversation.php?action=unread_counts uses
 *        chat_message_reads per-admin table, no admin_id restriction.
 *        → badge shows for ALL admins independently.
 *
 * Also fixed: old code called 'fetch_conversations.php' (plural).
 * Correct filename is 'fetch_conversation.php' (singular).
 */
$current_page = $current_page ?? '';

$sidebar_pic = '';
if (!empty($admin_id)) {
    $_pic_res = mysqli_query($connect, "SELECT profile_picture FROM users WHERE userid = '" . mysqli_real_escape_string($connect, $admin_id) . "' LIMIT 1");
    if ($_pic_res) {
        $_pic_row = mysqli_fetch_assoc($_pic_res);
        $pic_file = $_pic_row['profile_picture'] ?? '';
        if (!empty($pic_file) && file_exists('uploads/profiles/' . $pic_file)) {
            $sidebar_pic = 'uploads/profiles/' . htmlspecialchars($pic_file);
        }
    }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="sb-logo-icon">🍔</div>
        <div>
            <div class="sb-logo-text">FOODTIME</div>
            <div class="sb-logo-sub">Admin Panel</div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="sb-section">Main</div>
        <a href="Admin.php" class="sb-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>

        <div class="sb-section">Management</div>
        <a href="User.php" class="sb-link <?= $current_page === 'users' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Manage Users
        </a>
        <a href="Order.php" class="sb-link <?= $current_page === 'orders' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> Orders
        </a>
        <a href="Product.php" class="sb-link <?= $current_page === 'products' ? 'active' : '' ?>">
            <i class="fas fa-utensils"></i> Menu Items
        </a>
        <a href="TopSellingProduct.php" class="sb-link <?= $current_page === 'top-products' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Top Products
        </a>
        <a href="ingredient_analytics.php" class="sb-link <?= $current_page === 'ingredient-analytics' ? 'active' : '' ?>">
            <i class="fas fa-flask"></i> Ingredient Analytics
        </a>
        <a href="admin_member_tier.php" class="sb-link <?= $current_page === 'member-tier' ? 'active' : '' ?>">
            <i class="fas fa-crown"></i> Member Tier
        </a>

        <div class="sb-section">Support</div>
        <a href="admin_chat.php" class="sb-link <?= $current_page === 'chat' ? 'active' : '' ?>" id="nav-chat-link">
            <i class="fas fa-headset"></i> Support Chat
            <span id="nav-unread-badge" class="sb-badge" style="display:none;"></span>
        </a>

        <div class="sb-section">Account</div>
        <a href="admin_profile.php" class="sb-link <?= $current_page === 'profile' ? 'active' : '' ?>">
            <div style="width:20px;height:20px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--accent,#C94B1A),var(--info,#F4784A));color:#fff;font-weight:700;font-size:10px;flex-shrink:0;">
                <?php if ($sidebar_pic): ?>
                    <img src="<?= $sidebar_pic ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                <?php else: ?>
                    <?= strtoupper(substr($admin_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
            Edit Profile
        </a>
    </nav>

    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if ($sidebar_pic): ?>
                    <img src="<?= $sidebar_pic ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                <?php else: ?>
                    <?= strtoupper(substr($admin_name, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="sb-user-name"><?= $admin_name ?></div>
                <div class="sb-user-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="sb-logout">
            <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
    </div>
</aside>

<script>
/*
 * Poll unread chat count every 8 seconds.
 * NEW: fetch_conversation.php (singular, correct filename).
 * NEW: unread_counts uses chat_message_reads — all admins get independent counts.
 * REMOVED: old 'fetch_conversations.php' (plural, was broken) call.
 */
(function pollChat() {
    fetch('fetch_conversation.php?action=unread_counts')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            let total = 0;
            if (res.counts) Object.values(res.counts).forEach(v => total += v);
            const b = document.getElementById('nav-unread-badge');
            if (b) { b.textContent = total; b.style.display = total > 0 ? '' : 'none'; }
        }).catch(() => {});
    setTimeout(pollChat, 8000);
})();
</script>