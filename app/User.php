<?php
session_start();
require_once 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id   = $_SESSION['user_id'] ?? '';
$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$current_page = 'users';

/* ── Actions ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['userid'])) {
    $action = trim($_POST['action']);
    $userid = trim($_POST['userid']);

    if ($userid === $admin_id) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'You cannot modify your own account.'];
        header("Location: User.php"); exit();
    }

    $stmt = mysqli_prepare($connect, "SELECT userid, username, position, status, is_blocked FROM users WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $userid);
    mysqli_stmt_execute($stmt);
    $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$target) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'User not found.'];
        header("Location: User.php"); exit();
    }

    $uname = $target['username'];

    switch ($action) {
        case 'upgrade':
            if ($target['position'] !== 'admin') {
                if (($target['status'] ?? 'active') === 'blocked') {
                    $_SESSION['flash'] = ['type'=>'error','msg'=>"Cannot upgrade {$uname} while blocked. Unblock first."];
                } else {
                    $s = mysqli_prepare($connect, "UPDATE users SET position='admin' WHERE userid=?");
                    mysqli_stmt_bind_param($s,"s",$userid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
                    $_SESSION['flash'] = ['type'=>'success','msg'=>"{$uname} upgraded to Admin."];
                }
            } else { $_SESSION['flash'] = ['type'=>'error','msg'=>"{$uname} is already an Admin."]; }
            break;

        case 'downgrade':
            if ($target['position'] === 'admin') {
                $s = mysqli_prepare($connect, "UPDATE users SET position='customer' WHERE userid=?");
                mysqli_stmt_bind_param($s,"s",$userid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
                $_SESSION['flash'] = ['type'=>'success','msg'=>"{$uname} downgraded to Customer."];
            } else { $_SESSION['flash'] = ['type'=>'error','msg'=>"{$uname} is already a Customer."]; }
            break;

        case 'block':
            if ((int)($target['is_blocked'] ?? 0) !== 1) {
                $s = mysqli_prepare($connect, "UPDATE users SET is_blocked=1, status='blocked' WHERE userid=?");
                mysqli_stmt_bind_param($s, "s", $userid);
                mysqli_stmt_execute($s);
                mysqli_stmt_close($s);
                $_SESSION['flash'] = ['type'=>'success','msg'=>"{$uname} has been blocked."];
            } else {
                $_SESSION['flash'] = ['type'=>'error','msg'=>"{$uname} is already blocked."];
            }
            break;

        case 'unblock':
            if ((int)($target['is_blocked'] ?? 0) === 1) {
                $s = mysqli_prepare($connect, "UPDATE users SET is_blocked=0, status='active' WHERE userid=?");
                mysqli_stmt_bind_param($s, "s", $userid);
                mysqli_stmt_execute($s);
                mysqli_stmt_close($s);
                $_SESSION['flash'] = ['type'=>'success','msg'=>"{$uname} has been unblocked."];
            } else {
                $_SESSION['flash'] = ['type'=>'error','msg'=>"{$uname} is not blocked."];
            }
            break;

        case 'delete':
            $s = mysqli_prepare($connect, "DELETE FROM users WHERE userid=?");
            mysqli_stmt_bind_param($s,"s",$userid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            $_SESSION['flash'] = ['type'=>'success','msg'=>"{$uname} account deleted."];
            break;

        default:
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid action.'];
    }

    header("Location: User.php"); exit();
}

/* ── Flash ───────────────────────────────────────────────────── */
$flash = null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

/* ── Filter + Search inputs ──────────────────────────────────── */
$per_page = 5;
$page     = max(1, intval($_GET['page'] ?? 1));
$search   = trim($_GET['q'] ?? '');
$filter   = trim($_GET['filter'] ?? '');   // '', 'admin', 'customer', 'active', 'blocked'

// Validate filter value
$valid_filters = ['admin', 'customer', 'active', 'blocked'];
if (!in_array($filter, $valid_filters, true)) $filter = '';

/* ── Build WHERE clause ──────────────────────────────────────── */
$conditions = [];
$params     = [];
$types      = '';

// Search condition
if ($search !== '') {
    $conditions[] = "(username LIKE ? OR userid LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

// Filter condition
switch ($filter) {
    case 'admin':
        $conditions[] = "position = 'admin'";
        break;
    case 'customer':
        $conditions[] = "position = 'customer'";
        break;
    case 'active':
        $conditions[] = "(is_blocked = 0 OR status = 'active')";
        break;
    case 'blocked':
        $conditions[] = "(is_blocked = 1 OR status = 'blocked')";
        break;
}

$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

/* ── Filter badge counts (always from full table, no search applied) ── */
function filterCount($connect, $extra_where) {
    $sql = "SELECT COUNT(*) AS c FROM users WHERE $extra_where";
    $r   = mysqli_query($connect, $sql);
    if (!$r) return 0;
    return (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
}
$count_admin    = filterCount($connect, "position = 'admin'");
$count_customer = filterCount($connect, "position = 'customer'");
$count_active   = filterCount($connect, "is_blocked = 0 OR status = 'active'");
$count_blocked  = filterCount($connect, "is_blocked = 1 OR status = 'blocked'");

/* ── Count for current filter+search combination ─────────────── */
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
$data_sql = "SELECT * FROM users $where ORDER BY position DESC, userid ASC LIMIT $per_page OFFSET $offset";
$stmt = mysqli_prepare($connect, $data_sql);
if ($types) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$users_result = mysqli_stmt_get_result($stmt);
$users        = mysqli_fetch_all($users_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$showing_from = $total_users === 0 ? 0 : $offset + 1;
$showing_to   = min($offset + $per_page, $total_users);

/* ── Pagination URL builder ──────────────────────────────────── */
function pgUrl($page, $search, $filter) {
    $qs = 'page=' . $page;
    if ($search !== '') $qs .= '&q=' . urlencode($search);
    if ($filter !== '') $qs .= '&filter=' . urlencode($filter);
    return 'User.php?' . $qs;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>

<?php include 'admin_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <div class="topbar-title">Manage <span>Users</span></div>
        <div class="topbar-right">
            <div class="topbar-date"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y') ?></div>
        </div>
    </div>

    <div class="content">

        <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] ?>">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- Toolbar: search -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;" id="searchForm" autocomplete="off">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Search by username or ID…"
                           value="<?= htmlspecialchars($search) ?>" id="searchInput"
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                           oninput="debounceSearch(this)">
                </div>
                <!-- Preserve active filter when searching -->
                <?php if ($filter !== ''): ?>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <?php endif; ?>
                <input type="hidden" name="page" value="1">
            </form>
            <span class="text-muted" style="font-size:12.5px;">
                <?= $total_users ?> account<?= $total_users !== 1 ? 's' : '' ?> <?= $filter !== '' || $search !== '' ? 'found' : 'total' ?>
            </span>
        </div>

        <!-- Filter tabs -->
        <div class="filter-bar">
            <a href="User.php?page=1<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === '' ? 'active' : '' ?>">
                All
            </a>
            <a href="User.php?page=1&filter=admin<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'admin' ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i> Admin
                <span class="fcount"><?= $count_admin ?></span>
            </a>
            <a href="User.php?page=1&filter=customer<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'customer' ? 'active' : '' ?>">
                <i class="fas fa-user"></i> Customer
                <span class="fcount"><?= $count_customer ?></span>
            </a>
            <a href="User.php?page=1&filter=active<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'active' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Active
                <span class="fcount"><?= $count_active ?></span>
            </a>
            <a href="User.php?page=1&filter=blocked<?= $search !== '' ? '&q='.urlencode($search) : '' ?>"
               class="fpill <?= $filter === 'blocked' ? 'active' : '' ?>">
                <i class="fas fa-ban"></i> Blocked
                <span class="fcount"><?= $count_blocked ?></span>
            </a>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-users"></i> All User Accounts</div>
            </div>

            <div class="table-wrap">
                <table class="dt" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th style="min-width:260px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fas fa-users"></i><p>No users found.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u):
                            $is_self    = ($u['userid'] === $admin_id);
                            $is_admin_u = (($u['position'] ?? '') === 'admin');
                            $is_blocked = ((int)($u['is_blocked'] ?? 0) === 1);
                        ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="uc-avatar <?= $is_admin_u ? 'admin-av' : '' ?>">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="uc-name">
                                            <?= htmlspecialchars($u['username']) ?>
                                            <?php if ($is_self): ?><span class="you-tag">YOU</span><?php endif; ?>
                                        </div>
                                        <div class="uc-sub"><?= htmlspecialchars($u['userid']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <td class="text-muted" style="font-size:12.5px;"><?= htmlspecialchars($u['email'] ?? '') ?></td>

                            <td>
                                <span class="badge <?= $is_admin_u ? 'badge-admin' : 'badge-customer' ?>">
                                    <?= $is_admin_u ? 'Admin' : 'Customer' ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?= $is_blocked ? 'badge-blocked' : 'badge-active' ?>">
                                    <?= $is_blocked ? 'Blocked' : 'Active' ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($is_self): ?>
                                    <span class="text-muted" style="font-size:12px;font-style:italic;">Cannot modify your own account</span>
                                <?php else: ?>
                                    <div class="action-btns">

                                        <?php if (!$is_admin_u): ?>
                                            <?php if (!$is_blocked): ?>
                                                <!-- Upgrade → custom modal -->
                                                <button type="button" class="btn btn-accent" style="font-size:11.5px;padding:5px 10px;"
                                                    onclick="openUpgradeModal('<?= htmlspecialchars($u['userid'], ENT_QUOTES) ?>','<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-arrow-up"></i> Upgrade
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-ghost" disabled style="font-size:11.5px;padding:5px 10px;opacity:.35;" title="Unblock first">
                                                    <i class="fas fa-arrow-up"></i> Upgrade
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Downgrade → custom modal -->
                                            <button type="button" class="btn btn-info" style="font-size:11.5px;padding:5px 10px;"
                                                onclick="openDowngradeModal('<?= htmlspecialchars($u['userid'], ENT_QUOTES) ?>','<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                                <i class="fas fa-arrow-down"></i> Downgrade
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($is_blocked): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="unblock">
                                                <input type="hidden" name="userid" value="<?= htmlspecialchars($u['userid']) ?>">
                                                <button type="submit" class="btn btn-success" style="font-size:11.5px;padding:5px 10px;"
                                                    onclick="return confirm('Unblock <?= addslashes($u['username']) ?>?')">
                                                    <i class="fas fa-lock-open"></i> Unblock
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-warning" style="font-size:11.5px;padding:5px 10px;"
                                                onclick="openBlockModal('<?= htmlspecialchars($u['userid'], ENT_QUOTES) ?>','<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                                <i class="fas fa-ban"></i> Block
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-danger" style="font-size:11.5px;padding:5px 10px;"
                                            onclick="openDeleteModal('<?= htmlspecialchars($u['userid'], ENT_QUOTES) ?>','<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>

                                    </div>
                                <?php endif; ?>
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
                    Showing <?= $showing_from ?>–<?= $showing_to ?> of <?= $total_users ?> users
                </div>
                <div class="pg-nav">
                    <?php
                    // Previous
                    $prev_url = pgUrl(max(1, $page - 1), $search, $filter);
                    echo '<a href="' . $prev_url . '" class="pg-btn' . ($page <= 1 ? '" style="opacity:.3;pointer-events:none;"' : '"') . '>
                        <i class="fas fa-chevron-left"></i>
                    </a>';

                    // Page numbers
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

                    // Next
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

<!-- ═══════════════════════════════════════════════════════════════
     MODALS
     ═══════════════════════════════════════════════════════════════ -->

<!-- Upgrade Modal -->
<div class="modal-overlay" id="upgradeModal">
    <div class="modal">
        <h3><i class="fas fa-arrow-up" style="color:var(--accent);margin-right:8px;"></i>Upgrade User</h3>
        <p>Are you sure you want to upgrade <span id="upgradeUserName" style="color:var(--accent);font-weight:700;"></span> to <strong>Admin</strong>? They will gain full admin access.</p>
        <form method="POST" id="upgradeForm">
            <input type="hidden" name="action" value="upgrade">
            <input type="hidden" name="userid" id="upgradeUserId">
            <div class="modal-btns">
                <button type="button" class="modal-cancel" onclick="closeModal('upgradeModal')">Cancel</button>
                <button type="submit" class="modal-confirm" style="background:var(--accent);color:#fff;">Upgrade User</button>
            </div>
        </form>
    </div>
</div>

<!-- Downgrade Modal -->
<div class="modal-overlay" id="downgradeModal">
    <div class="modal">
        <h3><i class="fas fa-arrow-down" style="color:var(--info);margin-right:8px;"></i>Downgrade User</h3>
        <p>Are you sure you want to downgrade <span id="downgradeUserName" style="color:var(--info);font-weight:700;"></span> to <strong>Customer</strong>? They will lose admin access.</p>
        <form method="POST" id="downgradeForm">
            <input type="hidden" name="action" value="downgrade">
            <input type="hidden" name="userid" id="downgradeUserId">
            <div class="modal-btns">
                <button type="button" class="modal-cancel" onclick="closeModal('downgradeModal')">Cancel</button>
                <button type="submit" class="modal-confirm" style="background:var(--info);color:#fff;">Downgrade User</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h3><i class="fas fa-trash" style="color:var(--danger);margin-right:8px;"></i>Delete Account</h3>
        <p>Are you sure you want to permanently delete <span class="danger-name" id="deleteUserName"></span>'s account? This cannot be undone.</p>
        <div class="modal-btns">
            <button class="modal-cancel" onclick="closeModal('deleteModal')">Cancel</button>
            <form method="POST" id="deleteForm" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="userid" id="deleteUserId">
                <button type="submit" class="modal-confirm">Yes, Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Block Modal -->
<div class="modal-overlay" id="blockModal">
    <div class="modal">
        <h3><i class="fas fa-ban" style="color:var(--warning);margin-right:8px;"></i>Block User</h3>
        <p>Are you sure you want to block <span class="danger-name" id="blockUserName"></span>?</p>
        <form method="POST" id="blockForm">
            <input type="hidden" name="action" value="block">
            <input type="hidden" name="userid" id="blockUserId">
            <div class="modal-btns">
                <button type="button" class="modal-cancel" onclick="closeModal('blockModal')">Cancel</button>
                <button type="submit" class="modal-confirm" style="background:var(--warning);color:#000;">Block User</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Modal openers ──────────────────────────────────────────── */
function openUpgradeModal(id, name) {
    document.getElementById('upgradeUserId').value = id;
    document.getElementById('upgradeUserName').textContent = name;
    document.getElementById('upgradeModal').classList.add('show');
}

function openDowngradeModal(id, name) {
    document.getElementById('downgradeUserId').value = id;
    document.getElementById('downgradeUserName').textContent = name;
    document.getElementById('downgradeModal').classList.add('show');
}

function openDeleteModal(id, name) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
}

function openBlockModal(id, name) {
    document.getElementById('blockUserId').value = id;
    document.getElementById('blockUserName').textContent = name;
    document.getElementById('blockModal').classList.add('show');
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }

/* Close on backdrop click */
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('show'); });
});

/* Block form – legacy reason-select guard (kept for safety) */
document.getElementById('blockForm').addEventListener('submit', function(e) {
    const sel = document.getElementById('blockReasonSelect');
    if (sel && sel.value === 'Other') {
        const c = document.getElementById('blockReasonCustom');
        if (c && !c.value.trim()) { e.preventDefault(); alert('Please enter a reason.'); return; }
        if (c) sel.value = c.value.trim();
    }
});

/* ── Debounce search — resets to page 1 ─────────────────────── */
let searchTimer;
function debounceSearch(input) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { document.getElementById('searchForm').submit(); }, 400);
}
</script>
</body>
</html>