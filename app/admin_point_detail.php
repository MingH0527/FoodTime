<?php
// TODO: In future create table:
// point_transactions
// to store ALL point changes (earn, spend, admin adjust, bonus, refund)
// so history becomes 100% accurate

session_start();
require_once 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'] ?? '';
$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$current_page = 'member-tier';

/* ── Validate userid ─────────────────────────────────────────── */
$userid = trim($_GET['userid'] ?? '');
if ($userid === '') {
    header("Location: admin_member_tier.php");
    exit();
}

$user_stmt = mysqli_prepare($connect,
    "SELECT userid, username, membership_points, total_points_earned FROM users WHERE userid = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, 's', $userid);
mysqli_stmt_execute($user_stmt);
$user_res = mysqli_stmt_get_result($user_stmt);
$user     = mysqli_fetch_assoc($user_res);
mysqli_stmt_close($user_stmt);

if (!$user) {
    header("Location: admin_member_tier.php");
    exit();
}

/* ── Tier helper ─────────────────────────────────────────────── */
function getMemberTier($points) {
    $pts = (int)$points;
    if ($pts >= 3000) return ['label' => 'Gold',   'class' => 'badge-gold'];
    if ($pts >= 1000) return ['label' => 'Silver', 'class' => 'badge-silver'];
    return                   ['label' => 'Bronze', 'class' => 'badge-bronze'];
}

/* ── Filter + Search ─────────────────────────────────────────── */
$type_filter = trim($_GET['type'] ?? '');  // '', 'earn', 'spend'
$search      = trim($_GET['q'] ?? '');

$valid_types = ['earn', 'spend'];
if (!in_array($type_filter, $valid_types, true)) $type_filter = '';

/* ── Build combined history using UNION ──────────────────────── */
// (A) Points Earned from orders
// (B) Points Spent from user_vouchers
// We use prepared statements per part, then merge in PHP for full security.

$history = [];

// (A) Earned — filter by orderid search if any
$earn_conditions = [];
$earn_params     = [$userid];
$earn_types      = 's';

if ($search !== '') {
    $earn_conditions[] = "orderid LIKE ?";
    $earn_params[]     = "%{$search}%";
    $earn_types       .= 's';
}

$earn_where = $earn_conditions ? ('AND ' . implode(' AND ', $earn_conditions)) : '';
$earn_sql   = "SELECT orderid AS source_id, points_earned AS points, created_at, 'earn' AS entry_type,
                      NULL AS used_at, NULL AS is_used
               FROM orders
               WHERE userid = ? AND points_earned > 0 $earn_where";

if ($type_filter !== 'spend') {
    $earn_stmt = mysqli_prepare($connect, $earn_sql);
    mysqli_stmt_bind_param($earn_stmt, $earn_types, ...$earn_params);
    mysqli_stmt_execute($earn_stmt);
    $earn_res = mysqli_stmt_get_result($earn_stmt);
    while ($row = mysqli_fetch_assoc($earn_res)) {
        $history[] = $row;
    }
    mysqli_stmt_close($earn_stmt);
}

// (B) Spent — filter by voucher code search if any
$spend_conditions = [];
$spend_params     = [$userid];
$spend_types      = 's';

if ($search !== '') {
    $spend_conditions[] = "code LIKE ?";
    $spend_params[]     = "%{$search}%";
    $spend_types       .= 's';
}

$spend_where = $spend_conditions ? ('AND ' . implode(' AND ', $spend_conditions)) : '';
$spend_sql   = "SELECT code AS source_id, points_spent AS points, created_at, 'spend' AS entry_type,
                       used_at, is_used
                FROM user_vouchers
                WHERE userid = ? AND points_spent > 0 $spend_where";

if ($type_filter !== 'earn') {
    $spend_stmt = mysqli_prepare($connect, $spend_sql);
    mysqli_stmt_bind_param($spend_stmt, $spend_types, ...$spend_params);
    mysqli_stmt_execute($spend_stmt);
    $spend_res = mysqli_stmt_get_result($spend_stmt);
    while ($row = mysqli_fetch_assoc($spend_res)) {
        $history[] = $row;
    }
    mysqli_stmt_close($spend_stmt);
}

// Sort merged results by created_at DESC
usort($history, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

$tier = getMemberTier($user['membership_points'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point Detail — <?= htmlspecialchars($user['username']) ?> — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .badge-gold     { background: rgba(255,193,7,0.15);  color: #FFC107; border: 1px solid rgba(255,193,7,0.35); }
        .badge-silver   { background: rgba(160,174,192,0.15);color: #A0AEC0; border: 1px solid rgba(160,174,192,0.35); }
        .badge-bronze   { background: rgba(205,127,50,0.15); color: #CD7F32; border: 1px solid rgba(205,127,50,0.35); }
        .badge-earn     { background: rgba(72,199,142,0.15); color: #48C78E; border: 1px solid rgba(72,199,142,0.3); }
        .badge-spend    { background: rgba(239,68,68,0.15);  color: #EF4444; border: 1px solid rgba(239,68,68,0.3); }
        .badge-used     { background: rgba(100,116,139,0.15);color: #94A3B8; border: 1px solid rgba(100,116,139,0.3); }
        .badge-unused   { background: rgba(72,199,142,0.15); color: #48C78E; border: 1px solid rgba(72,199,142,0.3); }
        .pts-plus       { color: #48C78E; font-weight: 700; }
        .pts-minus      { color: #EF4444; font-weight: 700; }
        .member-info-bar { display:flex; gap:20px; flex-wrap:wrap; align-items:center; margin-bottom:20px; padding:16px 20px;
                           background:var(--surface, #1e1e2e); border-radius:10px; border:1px solid var(--border, #2a2a3d); }
        .mib-item       { display:flex; flex-direction:column; gap:3px; }
        .mib-label      { font-size:11px; color:var(--text-muted,#888); text-transform:uppercase; letter-spacing:.5px; }
        .mib-value      { font-size:15px; font-weight:700; color:var(--text,#eee); }
        .back-link      { display:inline-flex; align-items:center; gap:6px; font-size:13px;
                          color:var(--text-muted,#888); text-decoration:none; margin-bottom:14px; }
        .back-link:hover { color:var(--accent,#C94B1A); }
        .source-mono    { font-family:monospace; font-size:12px; color:var(--text-muted,#888); }
    </style>
</head>
<body>

<?php include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">Point <span>Detail</span></div>
        <div class="topbar-right">
            <div class="topbar-date"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y') ?></div>
        </div>
    </div>

    <div class="content">

        <a href="admin_member_tier.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Member Tier
        </a>

        <!-- Member info bar -->
        <div class="member-info-bar">
            <div class="uc-avatar" style="width:40px;height:40px;font-size:16px;flex-shrink:0;">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
            </div>
            <div class="mib-item">
                <span class="mib-label">Username</span>
                <span class="mib-value"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="mib-item">
                <span class="mib-label">User ID</span>
                <span class="mib-value" style="font-size:13px;font-family:monospace;"><?= htmlspecialchars($user['userid']) ?></span>
            </div>
            <div class="mib-item">
                <span class="mib-label">Current Points</span>
                <span class="mib-value" style="color:var(--accent);"><?= number_format((int)($user['membership_points'] ?? 0)) ?> pts</span>
            </div>
            <div class="mib-item">
                <span class="mib-label">Total Earned</span>
                <span class="mib-value"><?= number_format((int)($user['total_points_earned'] ?? 0)) ?> pts</span>
            </div>
            <div class="mib-item">
                <span class="mib-label">Member Tier</span>
                <span class="badge <?= $tier['class'] ?>" style="font-size:13px;padding:4px 12px;">
                    <?= $tier['label'] ?>
                </span>
            </div>
        </div>

        <!-- Toolbar: search -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;" id="searchForm" autocomplete="off">
                <input type="hidden" name="userid" value="<?= htmlspecialchars($userid) ?>">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search by order ID or voucher code… (press Enter)"
                           value="<?= htmlspecialchars($search) ?>" id="searchInput"
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                </div>
                <?php if ($type_filter !== ''): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($type_filter) ?>">
                <?php endif; ?>
            </form>
            <span class="text-muted" style="font-size:12.5px;">
                <?= count($history) ?> record<?= count($history) !== 1 ? 's' : '' ?> found
            </span>
        </div>

        <!-- Filter tabs -->
        <div class="filter-bar">
            <a href="admin_point_detail.php?userid=<?= urlencode($userid) ?><?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $type_filter === '' ? 'active' : '' ?>">
                All
            </a>
            <a href="admin_point_detail.php?userid=<?= urlencode($userid) ?>&type=earn<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $type_filter === 'earn' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle"></i> Earned Only
            </a>
            <a href="admin_point_detail.php?userid=<?= urlencode($userid) ?>&type=spend<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $type_filter === 'spend' ? 'active' : '' ?>">
                <i class="fas fa-minus-circle"></i> Spent Only
            </a>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-history"></i> Points History</div>
            </div>

            <div class="table-wrap">
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Source ID</th>
                            <th>Points Change</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-history"></i><p>No point history found.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $row):
                            $is_earn  = ($row['entry_type'] === 'earn');
                            $pts_disp = number_format((int)$row['points']);

                            // Status logic
                            if ($is_earn) {
                                $status_label = '—';
                                $status_class = '';
                            } else {
                                $is_used      = (int)($row['is_used'] ?? 0);
                                $status_label = $is_used ? 'Used' : 'Unused';
                                $status_class = $is_used ? 'badge-used' : 'badge-unused';
                            }
                        ?>
                        <tr>
                            <td class="text-muted" style="font-size:12px; white-space:nowrap;">
                                <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                            </td>
                            <td>
                                <span class="badge <?= $is_earn ? 'badge-earn' : 'badge-spend' ?>">
                                    <?= $is_earn ? 'ORDER EARN' : 'VOUCHER SPEND' ?>
                                </span>
                            </td>
                            <td class="source-mono"><?= htmlspecialchars($row['source_id'] ?? '—') ?></td>
                            <td>
                                <?php if ($is_earn): ?>
                                    <span class="pts-plus">+<?= $pts_disp ?> pts</span>
                                <?php else: ?>
                                    <span class="pts-minus">−<?= $pts_disp ?> pts</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($status_class): ?>
                                    <span class="badge <?= $status_class ?>"><?= $status_label ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
/* Search triggers on Enter only */
document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('searchForm').submit();
    }
});
</script>
</body>
</html>