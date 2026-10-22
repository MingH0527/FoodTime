<?php
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id             = $_SESSION['user_id'];
$user_name           = $_SESSION['user_name'] ?? '';
$membership_points   = 0;
$total_points_earned = 0;
$points_history      = [];
$points_used         = 0;
$points_for_next_reward = 0;
$points_progress     = 0;
$progress_percentage = 0;
$points_value        = 0;

$stmt = mysqli_prepare($connect, "SELECT membership_points, total_points_earned FROM users WHERE userid = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $user_id);
    mysqli_stmt_execute($stmt);
    $result     = mysqli_stmt_get_result($stmt);
    $user_points = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($user_points) {
        $membership_points   = (int)($user_points['membership_points'] ?? 0);
        $total_points_earned = (int)($user_points['total_points_earned'] ?? 0);
        $points_used         = max(0, $total_points_earned - $membership_points);

        $orders_stmt = mysqli_prepare($connect,
            "SELECT orderid, total_price, points_earned, created_at FROM orders
             WHERE userid = ? AND points_earned > 0 ORDER BY created_at DESC LIMIT 20");
        if ($orders_stmt) {
            mysqli_stmt_bind_param($orders_stmt, 's', $user_id);
            mysqli_stmt_execute($orders_stmt);
            $orders_result = mysqli_stmt_get_result($orders_stmt);
            while ($row = mysqli_fetch_assoc($orders_result)) {
                $points_history[] = $row;
            }
            mysqli_stmt_close($orders_stmt);
        }

        $points_for_next_reward = 100 - ($membership_points % 100);
        $points_progress        = $membership_points % 100;
        $progress_percentage    = ($points_progress / 100) * 100;
        $points_value           = ($membership_points / 100) * 5;
    }
}

$tier = 'Bronze'; $tier_icon = '🥉';
if ($total_points_earned >= 2000)    { $tier = 'Gold';   $tier_icon = '🥇'; }
elseif ($total_points_earned >= 500) { $tier = 'Silver'; $tier_icon = '🥈'; }

// Fetch voucher tiers
$tiers_res = mysqli_query($connect, "SELECT * FROM voucher_tiers WHERE is_active = 1 ORDER BY points_cost ASC");
$tiers = [];
while ($t = mysqli_fetch_assoc($tiers_res)) $tiers[] = $t;

// Fetch user's unused vouchers
$uv_stmt = mysqli_prepare($connect,
    "SELECT * FROM user_vouchers WHERE userid = ? AND is_used = 0 ORDER BY created_at DESC");
mysqli_stmt_bind_param($uv_stmt, 's', $user_id);
mysqli_stmt_execute($uv_stmt);
$uv_res      = mysqli_stmt_get_result($uv_stmt);
$my_vouchers = [];
while ($v = mysqli_fetch_assoc($uv_res)) $my_vouchers[] = $v;
mysqli_stmt_close($uv_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
(function(){
    var t = localStorage.getItem('theme') || localStorage.getItem('ft_theme') || 'light';
    localStorage.setItem('theme', t);
    localStorage.removeItem('ft_theme');
    document.documentElement.setAttribute('data-theme', t);
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Membership Points - FOODTIME</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/index.css">
<style>
/* ===== MEMBERSHIP PAGE ===== */
.mem-page { min-height: 100vh; padding: 40px 0 80px; background: var(--body-bg, #f8f6f3); }
[data-theme="dark"] .mem-page { background: #18191a; }
.mem-container { max-width: 1060px; margin: 0 auto; padding: 0 20px; }

.mem-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #9ca3af; margin-bottom: 28px; font-weight: 600; }
.mem-breadcrumb a { color: #d4650f; text-decoration: none; }
.mem-breadcrumb a:hover { text-decoration: underline; }
.mem-breadcrumb i { font-size: 10px; }

.mem-hero { position: relative; border-radius: 24px; overflow: hidden; margin-bottom: 28px; box-shadow: 0 20px 60px rgba(212,101,15,.22); }
.mem-hero-bg { background: linear-gradient(135deg, #d4650f 0%, #f0874a 50%, #e8a45c 100%); padding: 48px 44px 44px; position: relative; }
.mem-hero-bg::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 80% 20%, rgba(255,255,255,.12) 0%, transparent 50%), radial-gradient(circle at 10% 80%, rgba(0,0,0,.08) 0%, transparent 50%); pointer-events: none; }
.mem-hero-bg::after { content: ''; position: absolute; top: -60px; right: -60px; width: 260px; height: 260px; border-radius: 50%; border: 40px solid rgba(255,255,255,.08); pointer-events: none; }
.mem-hero-deco { position: absolute; bottom: -40px; left: -40px; width: 200px; height: 200px; border-radius: 50%; border: 30px solid rgba(255,255,255,.06); pointer-events: none; }
.mem-hero-inner { position: relative; display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 32px; }
.mem-hero-eyebrow { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.2); backdrop-filter: blur(8px); color: #fff; font-size: 12px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; padding: 5px 14px; border-radius: 30px; margin-bottom: 16px; border: 1px solid rgba(255,255,255,.25); }
.mem-hero-title { font-size: 42px; font-weight: 900; color: #fff; line-height: 1; margin: 0 0 6px; letter-spacing: -1px; }
.mem-hero-title small { display: block; font-size: 16px; font-weight: 500; opacity: .85; letter-spacing: 0; margin-top: 6px; }
.mem-hero-worth { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3); color: #fff; padding: 8px 18px; border-radius: 30px; font-size: 15px; font-weight: 700; margin-top: 18px; }
.mem-tier-badge { text-align: center; background: rgba(255,255,255,.15); backdrop-filter: blur(12px); border: 2px solid rgba(255,255,255,.3); border-radius: 20px; padding: 24px 28px; min-width: 160px; }
.mem-tier-emoji { font-size: 48px; display: block; margin-bottom: 8px; }
.mem-tier-label { font-size: 22px; font-weight: 900; color: #fff; letter-spacing: 2px; text-transform: uppercase; }
.mem-tier-sub { font-size: 11px; color: rgba(255,255,255,.75); font-weight: 600; margin-top: 4px; }

.mem-progress-wrap { position: relative; background: rgba(0,0,0,.15); border-top: 1px solid rgba(255,255,255,.12); padding: 20px 44px; }
.mem-progress-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.mem-progress-label { color: rgba(255,255,255,.9); font-size: 13px; font-weight: 700; }
.mem-progress-count { color: #fff; font-size: 13px; font-weight: 800; }
.mem-progress-track { background: rgba(255,255,255,.2); border-radius: 10px; height: 12px; overflow: hidden; }
.mem-progress-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, #fff 0%, rgba(255,255,255,.7) 100%); transition: width .6s cubic-bezier(.4,0,.2,1); position: relative; }
.mem-progress-fill::after { content: ''; position: absolute; top: 2px; right: 0; width: 8px; height: 8px; background: #fff; border-radius: 50%; box-shadow: 0 0 8px rgba(255,255,255,.8); }
.mem-progress-hint { color: rgba(255,255,255,.75); font-size: 12px; font-weight: 600; margin-top: 8px; text-align: right; }

.mem-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 28px; }
.mem-stat { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 18px; padding: 24px 20px; display: flex; align-items: center; gap: 16px; transition: transform .2s, box-shadow .2s; position: relative; overflow: hidden; }
.mem-stat::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(180deg, #d4650f, #f0874a); border-radius: 4px 0 0 4px; }
.mem-stat:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(212,101,15,.15); }
[data-theme="dark"] .mem-stat { background: #242526; border-color: #3a3b3c; }
.mem-stat-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, rgba(212,101,15,.12), rgba(240,135,74,.08)); display: flex; align-items: center; justify-content: center; font-size: 22px; color: #d4650f; flex-shrink: 0; }
.mem-stat-value { font-size: 26px; font-weight: 900; color: var(--text-color, #1f2937); line-height: 1; }
[data-theme="dark"] .mem-stat-value { color: #e4e6eb; }
.mem-stat-label { font-size: 12px; color: #9ca3af; font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: .5px; }

.mem-body { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }
.mem-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 20px; overflow: hidden; }
[data-theme="dark"] .mem-card { background: #242526; border-color: #3a3b3c; }
.mem-card-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color, #e5e7eb); display: flex; align-items: center; justify-content: space-between; }
[data-theme="dark"] .mem-card-header { border-color: #3a3b3c; }
.mem-card-title { font-size: 16px; font-weight: 800; color: var(--text-color, #1f2937); display: flex; align-items: center; gap: 8px; }
[data-theme="dark"] .mem-card-title { color: #e4e6eb; }
.mem-card-title i { color: #d4650f; }
.mem-card-badge { background: rgba(212,101,15,.1); color: #d4650f; font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.mem-table-wrap { overflow-x: auto; }
.mem-table { width: 100%; border-collapse: collapse; }
.mem-table th { padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; color: #9ca3af; border-bottom: 1px solid var(--border-color, #e5e7eb); }
[data-theme="dark"] .mem-table th { border-color: #3a3b3c; }
.mem-table td { padding: 14px 20px; font-size: 14px; color: var(--text-color, #374151); border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
[data-theme="dark"] .mem-table td { border-color: #2d2e30; color: #e4e6eb; }
.mem-table tr:last-child td { border-bottom: none; }
.mem-table tr:hover td { background: rgba(212,101,15,.03); }
.mem-order-id { font-family: monospace; font-size: 12px; background: rgba(212,101,15,.08); color: #d4650f; padding: 3px 8px; border-radius: 6px; font-weight: 700; }
.mem-pts-earned { display: inline-flex; align-items: center; gap: 5px; background: rgba(39,174,96,.1); color: #16a34a; font-weight: 800; padding: 4px 10px; border-radius: 20px; font-size: 13px; }
.mem-date-chip { font-size: 12px; color: #9ca3af; font-weight: 600; }
.mem-empty { text-align: center; padding: 60px 20px; color: #9ca3af; }
.mem-empty-icon { width: 80px; height: 80px; border-radius: 50%; background: rgba(212,101,15,.08); display: flex; align-items: center; justify-content: center; font-size: 32px; color: #d4650f; margin: 0 auto 16px; }
.mem-empty h4 { font-size: 16px; font-weight: 800; color: var(--text-color, #374151); margin: 0 0 6px; }
.mem-empty p { font-size: 13px; margin: 0 0 20px; }

.mem-sidebar { display: flex; flex-direction: column; gap: 18px; }
.mem-how { background: linear-gradient(135deg, #1f2937 0%, #111827 100%); border-radius: 20px; padding: 24px; color: #fff; }
.mem-how-title { font-size: 15px; font-weight: 800; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
.mem-how-title i { color: #f0874a; }
.mem-how-step { display: flex; gap: 12px; margin-bottom: 16px; align-items: flex-start; }
.mem-how-step:last-child { margin-bottom: 0; }
.mem-how-num { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #d4650f, #f0874a); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; flex-shrink: 0; }
.mem-how-text strong { display: block; font-size: 13px; font-weight: 800; }
.mem-how-text span { font-size: 12px; opacity: .7; }

.mem-tiers { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 20px; overflow: hidden; }
[data-theme="dark"] .mem-tiers { background: #242526; border-color: #3a3b3c; }
.mem-tiers-header { padding: 16px 20px; background: linear-gradient(135deg, rgba(212,101,15,.08), rgba(240,135,74,.04)); border-bottom: 1px solid var(--border-color, #e5e7eb); font-size: 14px; font-weight: 800; color: var(--text-color, #1f2937); display: flex; align-items: center; gap: 8px; }
[data-theme="dark"] .mem-tiers-header { border-color: #3a3b3c; color: #e4e6eb; }
.mem-tiers-header i { color: #d4650f; }
.mem-tier-row { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-bottom: 1px solid #f3f4f6; transition: background .12s; }
[data-theme="dark"] .mem-tier-row { border-color: #2d2e30; }
.mem-tier-row:last-child { border-bottom: none; }
.mem-tier-row.active { background: rgba(212,101,15,.06); }
.mem-tier-row-icon { font-size: 24px; flex-shrink: 0; }
.mem-tier-row-info { flex: 1; }
.mem-tier-row-name { font-size: 13px; font-weight: 800; color: var(--text-color, #1f2937); }
[data-theme="dark"] .mem-tier-row-name { color: #e4e6eb; }
.mem-tier-row-req { font-size: 11px; color: #9ca3af; font-weight: 600; margin-top: 1px; }
.mem-tier-row-check { width: 22px; height: 22px; border-radius: 50%; background: linear-gradient(135deg, #d4650f, #f0874a); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 10px; }

.mem-cta { background: linear-gradient(135deg, #d4650f, #f0874a); border-radius: 20px; padding: 24px; text-align: center; color: #fff; position: relative; overflow: hidden; }
.mem-cta::before { content: ''; position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; border-radius: 50%; border: 25px solid rgba(255,255,255,.1); }
.mem-cta-icon { font-size: 32px; margin-bottom: 10px; }
.mem-cta h4 { font-size: 16px; font-weight: 900; margin: 0 0 6px; }
.mem-cta p { font-size: 12px; opacity: .85; margin: 0 0 16px; line-height: 1.5; }
.mem-cta-btn { display: inline-flex; align-items: center; gap: 8px; background: #fff; color: #d4650f; padding: 10px 22px; border-radius: 30px; font-size: 13px; font-weight: 800; text-decoration: none; transition: transform .2s; }
.mem-cta-btn:hover { transform: scale(1.04); }

/* ===== VOUCHER SECTION ===== */
.mem-vouchers { margin-top: 28px; }
.mem-voucher-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
@media (max-width: 900px) { .mem-voucher-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px)  { .mem-voucher-grid { grid-template-columns: 1fr; } }

.voucher-card { border-radius: 18px; overflow: hidden; border: 2px solid var(--border-color, #e5e7eb); background: var(--card-bg, #fff); transition: transform .2s, box-shadow .2s, border-color .2s; position: relative; }
[data-theme="dark"] .voucher-card { background: #242526; border-color: #3a3b3c; }
.voucher-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(212,101,15,.18); border-color: #d4650f; }
.voucher-card.cannot-afford { opacity: .5; }
.voucher-card.cannot-afford:hover { transform: none; box-shadow: none; border-color: var(--border-color, #e5e7eb); }

.voucher-card-top { background: linear-gradient(135deg, #d4650f, #f0874a); padding: 22px 20px 28px; text-align: center; position: relative; }
.voucher-card-top::after { content: ''; position: absolute; bottom: -12px; left: 0; right: 0; height: 24px; background: var(--card-bg, #fff); border-radius: 50% 50% 0 0 / 100% 100% 0 0; }
[data-theme="dark"] .voucher-card-top::after { background: #242526; }
.voucher-discount { font-size: 38px; font-weight: 900; color: #fff; line-height: 1; }
.voucher-discount small { font-size: 13px; font-weight: 600; opacity: .85; display: block; margin-top: 4px; }
.voucher-card-bottom { padding: 22px 20px 20px; text-align: center; }
.voucher-pts-cost { display: inline-flex; align-items: center; gap: 6px; background: rgba(212,101,15,.1); color: #d4650f; font-size: 13px; font-weight: 800; padding: 5px 14px; border-radius: 20px; margin-bottom: 14px; }
.voucher-label { font-size: 13px; font-weight: 700; color: var(--text-color, #374151); margin-bottom: 14px; }
[data-theme="dark"] .voucher-label { color: #e4e6eb; }

.btn-redeem { width: 100%; padding: 11px; background: linear-gradient(135deg, #d4650f, #f0874a); color: #fff; border: none; border-radius: 30px; font-size: 13px; font-weight: 800; cursor: pointer; transition: opacity .2s, transform .2s; display: flex; align-items: center; justify-content: center; gap: 7px; }
.btn-redeem:hover { opacity: .88; transform: scale(1.02); }
.btn-redeem:disabled { background: #9ca3af; cursor: not-allowed; transform: none; opacity: 1; }

/* My Vouchers list - IMPROVED with instant use buttons */
.mem-my-vouchers { margin-top: 28px; margin-bottom: 40px; }
.voucher-list-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--border-color, #f3f4f6); flex-wrap: wrap; }
[data-theme="dark"] .voucher-list-item { border-color: #2d2e30; }
.voucher-list-item:last-child { border-bottom: none; }
.voucher-code-display { font-family: monospace; font-size: 15px; font-weight: 900; background: rgba(212,101,15,.1); color: #d4650f; padding: 7px 14px; border-radius: 8px; letter-spacing: 2px; cursor: pointer; transition: background .15s; display: flex; align-items: center; gap: 8px; }
.voucher-code-display:hover { background: rgba(212,101,15,.2); }
.voucher-disc-badge { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 20px; white-space: nowrap; }
.voucher-date-chip { font-size: 12px; color: #9ca3af; font-weight: 600; }
.voucher-action-buttons { display: flex; gap: 8px; }
.voucher-use-btn, .voucher-use-auto-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; text-decoration: none; transition: all .15s; cursor: pointer; border: none; }
.voucher-use-btn { background: #d4650f; color: #fff; }
.voucher-use-btn:hover { background: #b8540c; transform: translateY(-1px); }
.voucher-use-auto-btn { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
.voucher-use-auto-btn:hover { transform: translateY(-1px); filter: brightness(1.05); }

/* FLOATING QUICK VOUCHER PANEL */
.quick-voucher-panel {
    position: fixed;
    bottom: 100px;
    right: 20px;
    z-index: 1000;
    background: var(--card-bg, #fff);
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,.2);
    border: 1px solid var(--border-color, #e5e7eb);
    width: 320px;
    display: none;
    animation: slideInUp 0.3s ease;
}
[data-theme="dark"] .quick-voucher-panel {
    background: #242526;
    border-color: #3a3b3c;
}
.quick-voucher-panel.show {
    display: block;
}
@keyframes slideInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
.quick-voucher-header {
    padding: 14px 18px;
    background: linear-gradient(135deg, #d4650f, #f0874a);
    color: #fff;
    border-radius: 20px 20px 0 0;
    font-weight: 800;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.quick-voucher-header i { cursor: pointer; opacity: .8; transition: opacity .2s; }
.quick-voucher-header i:hover { opacity: 1; }
.quick-voucher-list {
    max-height: 400px;
    overflow-y: auto;
    padding: 12px;
}
.quick-voucher-item {
    background: rgba(212,101,15,.05);
    border-radius: 14px;
    padding: 12px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all .2s;
    border: 1px solid transparent;
}
.quick-voucher-item:hover {
    background: rgba(212,101,15,.12);
    border-color: #d4650f;
    transform: translateX(-3px);
}
.quick-voucher-discount {
    font-weight: 900;
    color: #d4650f;
    font-size: 14px;
}
.quick-voucher-code {
    font-family: monospace;
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
}
.quick-voucher-empty {
    text-align: center;
    padding: 30px;
    color: #9ca3af;
    font-size: 13px;
}

/* Floating toggle button */
.quick-voucher-toggle {
    position: fixed;
    bottom: 100px;
    right: 20px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #d4650f, #f0874a);
    color: #fff;
    border: none;
    box-shadow: 0 4px 20px rgba(212,101,15,.5);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    transition: transform .2s, box-shadow .2s;
    z-index: 1001;
}
.quick-voucher-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 30px rgba(212,101,15,.7);
}
.quick-voucher-toggle.has-vouchers::after {
    content: '';
    position: absolute;
    top: 5px;
    right: 5px;
    width: 12px;
    height: 12px;
    background: #22c55e;
    border-radius: 50%;
    border: 2px solid #fff;
}

/* Toast */
.redeem-toast { position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%) translateY(20px); background: #1c1e21; color: #fff; padding: 14px 24px; border-radius: 50px; font-size: 14px; font-weight: 700; box-shadow: 0 4px 20px rgba(0,0,0,.3); opacity: 0; pointer-events: none; transition: opacity .25s, transform .25s; z-index: 9999; display: flex; align-items: center; gap: 10px; white-space: nowrap; }
[data-theme="dark"] .redeem-toast { background: #e4e6eb; color: #1c1e21; }
.redeem-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.redeem-toast.success i { color: #22c55e; }
.redeem-toast.error   i { color: #ef4444; }

/* Float chat */
#float-chat-btn { position: fixed; bottom: 28px; right: 28px; z-index: 9000; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #d4650f, #f0874a); color: #fff; border: none; box-shadow: 0 4px 20px rgba(212,101,15,.55); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 23px; transition: transform .2s, box-shadow .2s; }
#float-chat-btn:hover { transform: scale(1.10) translateY(-2px); box-shadow: 0 8px 26px rgba(212,101,15,.65); }
#float-chat-btn::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: rgba(212,101,15,.30); animation: ftPulse 2s ease-out infinite; pointer-events: none; }
@keyframes ftPulse { 0% { transform: scale(1); opacity: .8; } 70% { transform: scale(1.6); opacity: 0; } 100% { transform: scale(1.6); opacity: 0; } }
#float-chat-btn .ft-dot { position: absolute; top: 4px; right: 4px; width: 14px; height: 14px; background: #22c55e; border: 2.5px solid #fff; border-radius: 50%; }

@media (max-width: 900px) {
    .mem-body { grid-template-columns: 1fr; }
    .mem-sidebar { order: -1; }
    .mem-hero-inner { grid-template-columns: 1fr; }
    .mem-tier-badge { display: none; }
    .mem-hero-title { font-size: 32px; }
    .mem-hero-bg { padding: 32px 24px 28px; }
    .mem-progress-wrap { padding: 18px 24px; }
    .mem-stats { grid-template-columns: 1fr; gap: 12px; }
    .quick-voucher-panel { width: 280px; right: 10px; bottom: 170px; }
    .quick-voucher-toggle { bottom: 170px; right: 10px; }
}
@media (max-width: 600px) {
    .mem-stats { grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .mem-stat { flex-direction: column; text-align: center; padding: 16px 12px; gap: 8px; }
    .mem-stat::before { width: 100%; height: 4px; top: 0; left: 0; border-radius: 0; }
}
</style>
</head>
<body>

<div class="redeem-toast" id="redeemToast">
    <i class="fas fa-check-circle"></i>
    <span id="redeemToastMsg"></span>
</div>

<!-- FLOATING QUICK VOUCHER PANEL -->
<div class="quick-voucher-toggle" id="quickVoucherToggle" title="My Vouchers">
    <i class="fas fa-ticket-alt"></i>
</div>

<div class="quick-voucher-panel" id="quickVoucherPanel">
    <div class="quick-voucher-header">
        <span><i class="fas fa-wallet"></i> My Vouchers</span>
        <i class="fas fa-times" onclick="closeQuickVoucher()"></i>
    </div>
    <div class="quick-voucher-list" id="quickVoucherList">
        <!-- Dynamic content -->
    </div>
</div>

<div class="mem-page">
    <div class="mem-container">

        <div class="mem-breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <i class="fas fa-chevron-right"></i>
            <span>My Membership Points</span>
        </div>

        <!-- HERO -->
        <div class="mem-hero">
            <div class="mem-hero-bg">
                <div class="mem-hero-deco"></div>
                <div class="mem-hero-inner">
                    <div class="mem-hero-left">
                        <div class="mem-hero-eyebrow"><i class="fas fa-star"></i> FOODTIME MEMBERSHIP</div>
                        <div class="mem-hero-title">
                            <?php echo number_format($membership_points); ?>
                            <small>membership points available</small>
                        </div>
                        <div class="mem-hero-worth">
                            <i class="fas fa-wallet"></i>
                            Worth <strong>RM <?php echo number_format($points_value, 2); ?></strong> in rewards
                        </div>
                    </div>
                    <div class="mem-tier-badge">
                        <span class="mem-tier-emoji"><?php echo $tier_icon; ?></span>
                        <div class="mem-tier-label"><?php echo $tier; ?></div>
                        <div class="mem-tier-sub">Current Tier</div>
                    </div>
                </div>
            </div>
            <div class="mem-progress-wrap">
                <div class="mem-progress-row">
                    <span class="mem-progress-label">
                        <i class="fas fa-bolt" style="color:#fbbf24;margin-right:5px"></i>
                        <?php echo $points_progress; ?> / 100 pts to next reward
                    </span>
                    <span class="mem-progress-count">Only <strong><?php echo $points_for_next_reward; ?> pts</strong> away!</span>
                </div>
                <div class="mem-progress-track">
                    <div class="mem-progress-fill" style="width:<?php echo $progress_percentage; ?>%"></div>
                </div>
                <div class="mem-progress-hint">Every 100 pts = <strong>RM5</strong> discount &nbsp;🎉</div>
            </div>
        </div>

        <!-- STATS -->
        <div class="mem-stats">
            <div class="mem-stat">
                <div class="mem-stat-icon"><i class="fas fa-gift"></i></div>
                <div class="mem-stat-info">
                    <div class="mem-stat-value"><?php echo number_format($total_points_earned); ?></div>
                    <div class="mem-stat-label">Total Earned</div>
                </div>
            </div>
            <div class="mem-stat">
                <div class="mem-stat-icon"><i class="fas fa-fire-flame-curved"></i></div>
                <div class="mem-stat-info">
                    <div class="mem-stat-value"><?php echo number_format($membership_points); ?></div>
                    <div class="mem-stat-label">Available</div>
                </div>
            </div>
            <div class="mem-stat">
                <div class="mem-stat-icon"><i class="fas fa-shopping-bag"></i></div>
                <div class="mem-stat-info">
                    <div class="mem-stat-value"><?php echo number_format($points_used); ?></div>
                    <div class="mem-stat-label">Points Used</div>
                </div>
            </div>
        </div>

        <!-- MAIN BODY -->
        <div class="mem-body">

            <!-- Points History -->
            <div class="mem-card">
                <div class="mem-card-header">
                    <div class="mem-card-title"><i class="fas fa-clock-rotate-left"></i> Points History</div>
                    <?php if (!empty($points_history)): ?>
                        <span class="mem-card-badge"><?php echo count($points_history); ?> transactions</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($points_history)): ?>
                <div class="mem-table-wrap">
                    <table class="mem-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Amount Spent</th>
                                <th>Points</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($points_history as $order): ?>
                            <tr>
                                <td><span class="mem-order-id">#<?php echo htmlspecialchars($order['orderid']); ?></span></td>
                                <td><strong>RM <?php echo number_format((float)$order['total_price'], 2); ?></strong></td>
                                <td><span class="mem-pts-earned"><i class="fas fa-plus"></i> <?php echo number_format($order['points_earned']); ?> pts</span></td>
                                <td><span class="mem-date-chip"><?php echo date('d M Y', strtotime($order['created_at'])); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="mem-empty">
                    <div class="mem-empty-icon"><i class="fas fa-star"></i></div>
                    <h4>No points history yet</h4>
                    <p>Start ordering to earn your first points!</p>
                    <a href="menu.php" class="btn btn-primary"><i class="fas fa-utensils"></i> Browse Menu</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="mem-sidebar">
                <div class="mem-how">
                    <div class="mem-how-title"><i class="fas fa-circle-info"></i> How Points Work</div>
                    <div class="mem-how-step">
                        <div class="mem-how-num">1</div>
                        <div class="mem-how-text"><strong>Place an Order</strong><span>Earn 5 pts for every RM1 spent</span></div>
                    </div>
                    <div class="mem-how-step">
                        <div class="mem-how-num">2</div>
                        <div class="mem-how-text"><strong>Redeem Voucher</strong><span>Use points to get discount vouchers</span></div>
                    </div>
                    <div class="mem-how-step">
                        <div class="mem-how-num">3</div>
                        <div class="mem-how-text"><strong>Use at Cart</strong><span>Enter voucher code at checkout for discount!</span></div>
                    </div>
                </div>

                <div class="mem-tiers">
                    <div class="mem-tiers-header"><i class="fas fa-layer-group"></i> Membership Tiers</div>
                    <div class="mem-tier-row <?php echo ($tier==='Bronze')?'active':''; ?>">
                        <span class="mem-tier-row-icon">🥉</span>
                        <div class="mem-tier-row-info"><div class="mem-tier-row-name">Bronze</div><div class="mem-tier-row-req">0 – 499 total pts</div></div>
                        <?php if ($tier==='Bronze'): ?><div class="mem-tier-row-check"><i class="fas fa-check"></i></div><?php endif; ?>
                    </div>
                    <div class="mem-tier-row <?php echo ($tier==='Silver')?'active':''; ?>">
                        <span class="mem-tier-row-icon">🥈</span>
                        <div class="mem-tier-row-info"><div class="mem-tier-row-name">Silver</div><div class="mem-tier-row-req">500 – 1,999 total pts</div></div>
                        <?php if ($tier==='Silver'): ?><div class="mem-tier-row-check"><i class="fas fa-check"></i></div><?php endif; ?>
                    </div>
                    <div class="mem-tier-row <?php echo ($tier==='Gold')?'active':''; ?>">
                        <span class="mem-tier-row-icon">🥇</span>
                        <div class="mem-tier-row-info"><div class="mem-tier-row-name">Gold</div><div class="mem-tier-row-req">2,000+ total pts</div></div>
                        <?php if ($tier==='Gold'): ?><div class="mem-tier-row-check"><i class="fas fa-check"></i></div><?php endif; ?>
                    </div>
                </div>

                <div class="mem-cta">
                    <div class="mem-cta-icon">🍔</div>
                    <h4>Keep Earning!</h4>
                    <p>Order more to unlock higher tiers and bigger rewards.</p>
                    <a href="menu.php" class="mem-cta-btn"><i class="fas fa-utensils"></i> Order Now</a>
                </div>
            </div>

        </div>

        <!-- REDEEM VOUCHERS -->
        <div class="mem-vouchers">
            <div class="mem-card">
                <div class="mem-card-header">
                    <div class="mem-card-title"><i class="fas fa-ticket"></i> Redeem Points for Vouchers</div>
                    <span class="mem-card-badge" id="availPtsBadge"><?php echo number_format($membership_points); ?> pts available</span>
                </div>
                <div style="padding:24px;">
                    <p style="font-size:13px;color:#9ca3af;margin:0 0 20px;font-weight:600;">
                        <i class="fas fa-info-circle" style="color:#d4650f"></i>
                        Click any voucher to redeem. Points are deducted immediately and a unique voucher code is generated for you.
                    </p>
                    <div class="mem-voucher-grid">
                        <?php foreach ($tiers as $tier_item):
                            $can_afford = $membership_points >= $tier_item['points_cost'];
                        ?>
                        <div class="voucher-card <?php echo $can_afford ? '' : 'cannot-afford'; ?>">
                            <div class="voucher-card-top">
                                <div class="voucher-discount">
                                    RM<?php echo number_format($tier_item['discount_rm'], 0); ?>
                                    <small>discount voucher</small>
                                </div>
                            </div>
                            <div class="voucher-card-bottom">
                                <div class="voucher-pts-cost">
                                    <i class="fas fa-star"></i>
                                    <?php echo number_format($tier_item['points_cost']); ?> pts
                                </div>
                                <div class="voucher-label"><?php echo htmlspecialchars($tier_item['label']); ?></div>
                                <button class="btn-redeem"
                                        onclick="redeemVoucher(<?php echo $tier_item['tierid']; ?>, <?php echo $tier_item['points_cost']; ?>, <?php echo $tier_item['discount_rm']; ?>, this)"
                                        <?php echo $can_afford ? '' : 'disabled'; ?>>
                                    <?php if ($can_afford): ?>
                                        <i class="fas fa-gift"></i> Redeem Now
                                    <?php else: ?>
                                        <i class="fas fa-lock"></i> Need <?php echo number_format($tier_item['points_cost'] - $membership_points); ?> more pts
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- MY VOUCHERS -->
        <div class="mem-my-vouchers">
            <div class="mem-card">
                <div class="mem-card-header">
                    <div class="mem-card-title"><i class="fas fa-wallet"></i> My Vouchers</div>
                    <?php if (!empty($my_vouchers)): ?>
                        <span class="mem-card-badge"><?php echo count($my_vouchers); ?> unused</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($my_vouchers)): ?>
                    <?php foreach ($my_vouchers as $v): ?>
                    <div class="voucher-list-item">
                        <div class="voucher-code-display" onclick="copyCode('<?php echo $v['code']; ?>')" title="Click to copy">
                            <i class="fas fa-copy" style="font-size:12px;opacity:.7"></i>
                            <?php echo $v['code']; ?>
                        </div>
                        <div class="voucher-disc-badge">RM<?php echo number_format((float)$v['discount_rm'], 2); ?> off</div>
                        <div class="voucher-date-chip"><?php echo date('d M Y', strtotime($v['created_at'])); ?></div>
                        <div class="voucher-action-buttons">
                            <button class="voucher-use-auto-btn" onclick="useVoucherNow('<?php echo $v['code']; ?>', <?php echo $v['discount_rm']; ?>)">
                                <i class="fas fa-bolt"></i> Use Now
                            </button>
                            <a href="cart.php" class="voucher-use-btn">
                                <i class="fas fa-shopping-cart"></i> Go to Cart
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="mem-empty">
                        <div class="mem-empty-icon"><i class="fas fa-ticket"></i></div>
                        <h4>No vouchers yet</h4>
                        <p>Redeem your points above to get discount vouchers!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>

<button id="float-chat-btn" onclick="window.location.href='customer_chat.php'" title="Live Support Chat" type="button">
    <i class="fas fa-headset"></i>
    <span class="ft-dot"></span>
</button>

<script src="js/translations.js"></script>
<script src="js/theme.js"></script>
<script src="js/language.js"></script>
<script>
let currentPoints = <?php echo $membership_points; ?>;

function useVoucherNow(code, discountRm) {
    const voucherData = {
        code: code,
        discount_rm: discountRm,
        auto_applied: true,
        timestamp: Date.now()
    };
    sessionStorage.setItem('auto_voucher', JSON.stringify(voucherData));
    showRedeemToast('✓ Voucher RM' + discountRm + ' off is ready! Redirecting to cart...', 'success');
    setTimeout(() => {
        window.location.href = 'cart.php?auto_voucher=' + encodeURIComponent(code);
    }, 1200);
}

function redeemVoucher(tierId, pointsCost, discountRm, btn) {
    if (currentPoints < pointsCost) {
        showRedeemToast('Not enough points!', 'error');
        return;
    }
    if (!confirm('Redeem ' + pointsCost + ' pts for a RM' + parseFloat(discountRm).toFixed(2) + ' voucher?')) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redeeming...';

    const fd = new FormData();
    fd.append('tierid', tierId);

    fetch('redeem_voucher.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentPoints = data.points_left;
                document.getElementById('availPtsBadge').textContent = data.points_left.toLocaleString() + ' pts available';
                showRedeemToast('✅ Voucher ' + data.code + ' created! RM' + data.discount_rm + ' off', 'success');
                setTimeout(() => {
                    if (confirm('Voucher redeemed! Use it now?')) {
                        useVoucherNow(data.code, data.discount_rm);
                    } else {
                        location.reload();
                    }
                }, 1500);
            } else {
                showRedeemToast(data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-gift"></i> Redeem Now';
            }
        })
        .catch(() => {
            showRedeemToast('Network error. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-gift"></i> Redeem Now';
        });
}

function copyCode(code) {
    navigator.clipboard.writeText(code)
        .then(() => showRedeemToast('Code ' + code + ' copied to clipboard!', 'success'))
        .catch(() => {
            const el = document.createElement('textarea');
            el.value = code;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            showRedeemToast('Code copied!', 'success');
        });
}

function updateQuickVoucherPanel() {
    const panel = document.getElementById('quickVoucherList');
    if (!panel) return;
    
    fetch('get_user_vouchers.php')
        .then(r => r.json())
        .then(data => {
            const hasVouchers = data.vouchers && data.vouchers.length > 0;
            const toggleBtn = document.getElementById('quickVoucherToggle');
            if (hasVouchers) {
                toggleBtn.classList.add('has-vouchers');
            } else {
                toggleBtn.classList.remove('has-vouchers');
            }
            
            if (!hasVouchers) {
                panel.innerHTML = '<div class="quick-voucher-empty"><i class="fas fa-ticket"></i><br>No vouchers available<br><small>Redeem points to get vouchers!</small></div>';
                return;
            }
            
            let html = '';
            data.vouchers.forEach(v => {
                html += `
                    <div class="quick-voucher-item" onclick="useVoucherNow('${v.code}', ${v.discount_rm})">
                        <div class="quick-voucher-discount">
                            <i class="fas fa-tag"></i> RM${parseFloat(v.discount_rm).toFixed(2)} OFF
                        </div>
                        <div class="quick-voucher-code">
                            Code: ${v.code}
                        </div>
                        <small style="font-size:10px;color:#9ca3af;">Click to use instantly →</small>
                    </div>
                `;
            });
            panel.innerHTML = html;
        })
        .catch(() => {
            panel.innerHTML = '<div class="quick-voucher-empty">Unable to load vouchers</div>';
        });
}

function toggleQuickVoucher() {
    const panel = document.getElementById('quickVoucherPanel');
    panel.classList.toggle('show');
    if (panel.classList.contains('show')) {
        updateQuickVoucherPanel();
    }
}

function closeQuickVoucher() {
    document.getElementById('quickVoucherPanel').classList.remove('show');
}

let redeemToastTimer = null;
function showRedeemToast(msg, type = 'success') {
    const toast = document.getElementById('redeemToast');
    const msgEl = document.getElementById('redeemToastMsg');
    const icon  = toast.querySelector('i');
    msgEl.textContent = msg;
    toast.className = 'redeem-toast show ' + type;
    icon.className  = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    clearTimeout(redeemToastTimer);
    redeemToastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
}

document.addEventListener('DOMContentLoaded', function() {
    const fill = document.querySelector('.mem-progress-fill');
    if (fill) {
        const target = fill.style.width;
        fill.style.width = '0%';
        setTimeout(() => { fill.style.width = target; }, 200);
    }
    
    document.querySelectorAll('.mem-stat-value').forEach(function(el) {
        const target = parseInt(el.textContent.replace(/,/g, '')) || 0;
        if (target === 0) return;
        let current = 0;
        const step  = target / (800 / 16);
        const timer = setInterval(function() {
            current = Math.min(current + step, target);
            el.textContent = Math.floor(current).toLocaleString();
            if (current >= target) clearInterval(timer);
        }, 16);
    });
    
    const toggleBtn = document.getElementById('quickVoucherToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleQuickVoucher);
    }
    
    document.addEventListener('click', function(e) {
        const panel = document.getElementById('quickVoucherPanel');
        const toggle = document.getElementById('quickVoucherToggle');
        if (panel && panel.classList.contains('show')) {
            if (!panel.contains(e.target) && !toggle.contains(e.target)) {
                panel.classList.remove('show');
            }
        }
    });
});
</script>
</body>
</html>