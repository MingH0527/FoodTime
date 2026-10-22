<?php
session_start();
require_once 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'] ?? '';
$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$current_page = 'member-tier';

/* ── Filter + Search inputs ──────────────────────────────────── */
$per_page = 5;
$page     = max(1, intval($_GET['page'] ?? 1));
$search   = trim($_GET['q'] ?? '');
$filter   = trim($_GET['filter'] ?? '');

$valid_filters = ['high_to_low', 'low_to_high', 'gold', 'silver', 'bronze'];
if (!in_array($filter, $valid_filters, true)) $filter = '';

/* ── Build WHERE clause ──────────────────────────────────────── */
$conditions = ["position = 'customer'"];
$params     = [];
$types      = '';

if ($search !== '') {
    $conditions[] = "(username LIKE ? OR userid LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

switch ($filter) {
    case 'gold':
        $conditions[] = "COALESCE(membership_points, 0) >= 3000";
        break;
    case 'silver':
        $conditions[] = "COALESCE(membership_points, 0) BETWEEN 1000 AND 2999";
        break;
    case 'bronze':
        $conditions[] = "COALESCE(membership_points, 0) < 1000";
        break;
}

$where = 'WHERE ' . implode(' AND ', $conditions);

$order = "ORDER BY userid ASC";
if ($filter === 'high_to_low') $order = "ORDER BY COALESCE(membership_points, 0) DESC";
if ($filter === 'low_to_high') $order = "ORDER BY COALESCE(membership_points, 0) ASC";

/* ── Tier badge counts (customers only, no search) ───────────── */
function tierCount($connect, $extra) {
    $sql = "SELECT COUNT(*) AS c FROM users WHERE position='customer' AND $extra";
    $r   = mysqli_query($connect, $sql);
    if (!$r) return 0;
    return (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
}
$count_gold   = tierCount($connect, "COALESCE(membership_points,0) >= 3000");
$count_silver = tierCount($connect, "COALESCE(membership_points,0) BETWEEN 1000 AND 2999");
$count_bronze = tierCount($connect, "COALESCE(membership_points,0) < 1000");

/* ── Count for current filter+search ────────────────────────── */
$count_sql = "SELECT COUNT(*) AS c FROM users $where";
$stmt = mysqli_prepare($connect, $count_sql);
if ($types) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$total_users = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
mysqli_stmt_close($stmt);

$total_pages = max(1, (int)ceil($total_users / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

/* ── Fetch users ─────────────────────────────────────────────── */
$data_sql = "SELECT userid, username, membership_points, total_points_earned FROM users $where $order LIMIT $per_page OFFSET $offset";
$stmt = mysqli_prepare($connect, $data_sql);
if ($types) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$users = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$showing_from = $total_users === 0 ? 0 : $offset + 1;
$showing_to   = min($offset + $per_page, $total_users);

/* ── Tier helper ─────────────────────────────────────────────── */
function getMemberTier($points) {
    $pts = (int)$points;
    if ($pts >= 3000) return ['label' => 'Gold',   'class' => 'badge-gold'];
    if ($pts >= 1000) return ['label' => 'Silver', 'class' => 'badge-silver'];
    return                   ['label' => 'Bronze', 'class' => 'badge-bronze'];
}

/* ── Pagination URL builder ──────────────────────────────────── */
function pgUrl($page, $search, $filter) {
    $qs = 'page=' . $page;
    if ($search !== '') $qs .= '&q=' . urlencode($search);
    if ($filter !== '') $qs .= '&filter=' . urlencode($filter);
    return 'admin_member_tier.php?' . $qs;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Tier — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* Tier badge colours — extend existing badge system */
        .badge-gold   { background: rgba(255, 193, 7, 0.15); color: #FFC107; border: 1px solid rgba(255,193,7,0.35); }
        .badge-silver { background: rgba(160,174,192,0.15);  color: #A0AEC0; border: 1px solid rgba(160,174,192,0.35); }
        .badge-bronze { background: rgba(205,127,50,0.15);   color: #CD7F32; border: 1px solid rgba(205,127,50,0.35); }
        .points-val   { font-weight: 600; color: var(--accent); }
        .total-pts    { font-size: 12px; color: var(--text-muted, #888); }
    </style>
</head>
<body>

<?php include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">Member <span>Tier</span></div>
        <div class="topbar-right">
            <div class="topbar-date"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y') ?></div>
        </div>
    </div>

    <div class="content">

        <!-- Toolbar: search -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;" id="searchForm" autocomplete="off">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search by username or ID… (press Enter)"
                           value="<?= htmlspecialchars($search) ?>" id="searchInput"
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                </div>
                <?php if ($filter !== ''): ?>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <?php endif; ?>
                <input type="hidden" name="page" value="1">
            </form>
            <span class="text-muted" style="font-size:12.5px;">
                <?= $total_users ?> member<?= $total_users !== 1 ? 's' : '' ?> <?= $filter !== '' || $search !== '' ? 'found' : 'total' ?>
            </span>
        </div>

        <!-- Filter tabs -->
        <div class="filter-bar">
            <a href="admin_member_tier.php?page=1<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === '' ? 'active' : '' ?>">
                All
            </a>
            <a href="admin_member_tier.php?page=1&filter=high_to_low<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'high_to_low' ? 'active' : '' ?>">
                <i class="fas fa-sort-amount-down"></i> High to Low
            </a>
            <a href="admin_member_tier.php?page=1&filter=low_to_high<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'low_to_high' ? 'active' : '' ?>">
                <i class="fas fa-sort-amount-up"></i> Low to High
            </a>
            <a href="admin_member_tier.php?page=1&filter=gold<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'gold' ? 'active' : '' ?>">
                <i class="fas fa-medal"></i> Gold
                <span class="fcount"><?= $count_gold ?></span>
            </a>
            <a href="admin_member_tier.php?page=1&filter=silver<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'silver' ? 'active' : '' ?>">
                <i class="fas fa-medal"></i> Silver
                <span class="fcount"><?= $count_silver ?></span>
            </a>
            <a href="admin_member_tier.php?page=1&filter=bronze<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'bronze' ? 'active' : '' ?>">
                <i class="fas fa-medal"></i> Bronze
                <span class="fcount"><?= $count_bronze ?></span>
            </a>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-crown"></i> Member Tier Overview</div>
            </div>

            <div class="table-wrap">
                <table class="dt">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Current Points</th>
                            <th>Total Points Earned</th>
                            <th>Member Tier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fas fa-users"></i><p>No members found.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u):
                            $pts   = (int)($u['membership_points'] ?? 0);
                            $total = (int)($u['total_points_earned'] ?? 0);
                            $tier  = getMemberTier($pts);
                        ?>
                        <tr>
                            <td class="text-muted" style="font-size:12.5px;"><?= htmlspecialchars($u['userid']) ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="uc-avatar">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>
                                    <div class="uc-name"><?= htmlspecialchars($u['username']) ?></div>
                                </div>
                            </td>
                            <td><span class="points-val"><?= number_format($pts) ?> pts</span></td>
                            <td><span class="total-pts"><?= number_format($total) ?> pts</span></td>
                            <td>
                                <span class="badge <?= $tier['class'] ?>">
                                    <?= $tier['label'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="admin_point_detail.php?userid=<?= urlencode($u['userid']) ?>"
                                   class="btn btn-info" style="font-size:11.5px;padding:5px 12px;">
                                    <i class="fas fa-history"></i> Point Detail
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pg-info">
                    Showing <?= $showing_from ?>–<?= $showing_to ?> of <?= $total_users ?> members
                </div>
                <div class="pg-nav">
                    <?php
                    $prev_url = pgUrl(max(1, $page - 1), $search, $filter);
                    echo '<a href="' . $prev_url . '" class="pg-btn' . ($page <= 1 ? '" style="opacity:.3;pointer-events:none;"' : '"') . '>
                        <i class="fas fa-chevron-left"></i>
                    </a>';

                    $start = max(1, $page - 2);
                    $end   = min($total_pages, $page + 2);

                    if ($start > 1) {
                        echo '<a href="' . pgUrl(1, $search, $filter) . '" class="pg-btn">1</a>';
                        if ($start > 2) echo '<span class="pg-btn pg-ellipsis">…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++) {
                        echo '<a href="' . pgUrl($i, $search, $filter) . '" class="pg-btn' . ($i === $page ? ' active' : '') . '">' . $i . '</a>';
                    }
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<span class="pg-btn pg-ellipsis">…</span>';
                        echo '<a href="' . pgUrl($total_pages, $search, $filter) . '" class="pg-btn">' . $total_pages . '</a>';
                    }

                    $next_url = pgUrl(min($total_pages, $page + 1), $search, $filter);
                    echo '<a href="' . $next_url . '" class="pg-btn' . ($page >= $total_pages ? '" style="opacity:.3;pointer-events:none;"' : '"') . '>
                        <i class="fas fa-chevron-right"></i>
                    </a>';
                    ?>
                </div>
            </div>
            <?php endif; ?>
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