<?php
session_start();
include 'base.php';

// ─── PHPMailer (direct files — no Composer needed) ───────────────────────────
// Download from: https://github.com/PHPMailer/PHPMailer
// Extract and place the src/ folder at:  app/PHPMailer/src/
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$order_id = isset($_GET['orderid']) ? mysqli_real_escape_string($connect, $_GET['orderid']) : '';

if (empty($order_id)) { header("Location: order_history.php"); exit(); }

// Join orders with users to get customer info
$order_result = mysqli_query($connect,
    "SELECT o.*, u.username, u.email, u.phone
     FROM orders o
     LEFT JOIN users u ON o.userid = u.userid
     WHERE o.orderid = '$order_id'"
);
if (!$order_result || mysqli_num_rows($order_result) == 0) { header("Location: order_history.php"); exit(); }
$order = mysqli_fetch_assoc($order_result);

// Resolve customer name: prefer orders.customer_name, fallback to users.username
$customer_name  = !empty($order['customer_name'])  ? $order['customer_name']  : ($order['username'] ?? '—');
$customer_phone = !empty($order['customer_phone']) ? $order['customer_phone'] : ($order['phone']    ?? '—');
$customer_email = !empty($order['customer_email']) ? $order['customer_email'] : ($order['email']    ?? '—');

$items_result = mysqli_query($connect,
    "SELECT oi.*, p.name FROM order_items oi
     LEFT JOIN products p ON oi.productid = p.productid
     WHERE oi.orderid = '$order_id'"
);
$receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad($order['orderid'], 6, '0', STR_PAD_LEFT);

// Parse voucher discount from special_notes
$voucher_discount     = 0;
$voucher_code_display = '';
if (!empty($order['special_notes'])) {
    if (preg_match('/Voucher:\s*([A-Z0-9]+)\s*\(RM\s*([\d.]+)\s*off\)/i', $order['special_notes'], $vm)) {
        $voucher_code_display = $vm[1];
        $voucher_discount     = (float)$vm[2];
    }
}

$db_subtotal = (float)($order['subtotal']    ?? 0);
$db_tax      = (float)($order['tax']         ?? 0);
$db_total    = (float)($order['total_price'] ?? 0);

// Helper: parse custom_options JSON
function parseCustomOptions($json_str) {
    if (empty($json_str) || $json_str === 'NULL') return [];
    $data = json_decode($json_str, true);
    if (!is_array($data)) return [];
    $lines = [];
    if (isset($data['spice_level'])) {
        $lines[] = ['icon' => '🌶', 'text' => 'Spice: ' . ucfirst(str_replace('_', ' ', $data['spice_level'])), 'type' => 'spice'];
    }
    if (isset($data['removes']) && is_array($data['removes'])) {
        foreach ($data['removes'] as $key => $removed) {
            if ($removed === true || $removed === 'true') {
                $nice = ucwords(str_replace(['no_', '_'], ['No ', ' '], $key));
                $lines[] = ['icon' => '✕', 'text' => 'No ' . str_replace('No ', '', $nice), 'type' => 'remove'];
            }
        }
    }
    if (isset($data['add_ons']) && is_array($data['add_ons'])) {
        foreach ($data['add_ons'] as $key => $added) {
            if ($added === true || $added === 'true') {
                $lines[] = ['icon' => '+', 'text' => 'Add ' . ucwords(str_replace('_', ' ', $key)), 'type' => 'addon'];
            }
        }
    }
    $handled = ['spice_level', 'removes', 'add_ons'];
    foreach ($data as $k => $v) {
        if (!in_array($k, $handled)) {
            if ($v === true || $v === 'true') {
                $lines[] = ['icon' => '•', 'text' => ucwords(str_replace('_', ' ', $k)), 'type' => 'extra'];
            } elseif (is_string($v) && $v !== 'false' && $v !== '') {
                $lines[] = ['icon' => '•', 'text' => ucwords(str_replace('_', ' ', $k)) . ': ' . ucfirst($v), 'type' => 'extra'];
            }
        }
    }
    return $lines;
}

// Collect all items into array
$items = [];
if ($items_result && mysqli_num_rows($items_result) > 0) {
    while ($row = mysqli_fetch_assoc($items_result)) {
        $items[] = $row;
    }
}

// ─── HANDLE EMAIL SEND (AJAX) ─────────────────────────────────────────
if (isset($_POST['send_email']) && $_POST['send_email'] === '1') {
    ob_start(); // Buffer output so PHP warnings don't break JSON
    header('Content-Type: application/json');

    // Turn all PHP errors into JSON responses instead of crashing
    set_error_handler(function($errno, $errstr) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
        exit();
    });

    $to_email = trim($_POST['email_to'] ?? '');

    // Basic validation
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit();
    }

    // Build HTML email body
    $items_html = '';
    foreach ($items as $item) {
        $qty   = intval($item['quantity']);
        $price = floatval($item['price']);
        $sub   = $qty * $price;
        $opts  = parseCustomOptions($item['custom_options'] ?? '');
        $opt_badges = '';
        foreach ($opts as $opt) {
            $colors = [
                'spice'  => 'background:#fff1f0;color:#b91c1c;border:1px solid #fca5a5;',
                'remove' => 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d;',
                'addon'  => 'background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;',
                'extra'  => 'background:#eff6ff;color:#1e40af;border:1px solid #93c5fd;',
            ];
            $style = $colors[$opt['type']] ?? 'background:#f3f4f6;color:#374151;';
            $opt_badges .= '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;margin:2px 2px 0 0;' . $style . '">' . htmlspecialchars($opt['text']) . '</span>';
        }
        $items_html .= '
        <tr>
            <td style="padding:12px 0;border-bottom:1px solid #f0ebe4;vertical-align:top;">
                <strong style="font-size:14px;color:#1a1a1a;">' . htmlspecialchars($item['name'] ?? 'Item') . '</strong><br>
                <span style="font-size:12px;color:#888;">' . $qty . ' × RM ' . number_format($price, 2) . '</span>
                ' . ($opt_badges ? '<br><div style="margin-top:6px;">' . $opt_badges . '</div>' : '') . '
            </td>
            <td style="padding:12px 0;border-bottom:1px solid #f0ebe4;text-align:right;vertical-align:top;font-weight:700;font-size:14px;">RM ' . number_format($sub, 2) . '</td>
        </tr>';
    }

    $voucher_row = '';
    if ($voucher_discount > 0) {
        $voucher_row = '<tr><td style="padding:6px 0;color:#16a34a;font-weight:700;">🏷 Voucher ' . ($voucher_code_display ? "($voucher_code_display)" : '') . '</td><td style="text-align:right;color:#16a34a;font-weight:700;">− RM ' . number_format($voucher_discount, 2) . '</td></tr>';
    }

    $order_type_label = ($order['order_type'] === 'dine_in') ? '🍽 Dine In' : '🛍 Takeaway';
    $status_upper = strtoupper($order['status'] ?? 'PENDING');

    $method_labels = [
        'cash'           => 'Cash at Counter',
        'card'           => 'Credit / Debit Card',
        'online_banking' => 'Online Banking / FPX',
        'ewallet'        => 'E-Wallet / QR'
    ];
    $pm = strtolower($order['payment_method'] ?? '');
    $payment_label = $method_labels[$pm] ?? strtoupper(str_replace('_', ' ', $pm));

    $clean_notes = trim(preg_replace('/Voucher:\s*[A-Z0-9]+\s*\(RM\s*[\d.]+\s*off\)/i', '', $order['special_notes'] ?? ''));
    $notes_section = '';
    if (!empty($clean_notes)) {
        $notes_section = '<div style="margin-top:20px;padding:12px 16px;background:#fef6ee;border-left:4px solid #d4650f;border-radius:0 6px 6px 0;font-size:13px;color:#555;line-height:1.6;"><strong style="color:#d4650f;">Special Instructions</strong><br>' . nl2br(htmlspecialchars($clean_notes)) . '</div>';
    }

    $html_body = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#e8e0d5;font-family:\'Courier New\',monospace;">
<div style="max-width:600px;margin:30px auto;padding:20px;">

  <!-- Header Card -->
  <div style="background:#1a1a1a;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;">
    <div style="font-size:40px;">🍔</div>
    <div style="font-family:Arial,sans-serif;font-size:32px;font-weight:900;color:#fff;letter-spacing:-1px;margin:8px 0 4px;">FOOD<span style="color:#ff8c42;">TIME</span></div>
    <div style="font-size:11px;color:#888;letter-spacing:3px;text-transform:uppercase;">Restaurant &amp; Café</div>
    <div style="margin-top:16px;display:inline-block;padding:6px 20px;border:2px solid #ff8c42;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:3px;color:#ff8c42;text-transform:uppercase;">E-Receipt</div>
  </div>

  <!-- Main Body -->
  <div style="background:#fffdf9;padding:32px 40px;border-left:1px solid #e5ddd4;border-right:1px solid #e5ddd4;">

    <!-- Receipt Meta -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;border-bottom:2px dashed #d4c9bc;padding-bottom:16px;">
      <tr><td style="font-size:12px;color:#666;padding:5px 0;"><strong>Receipt No</strong></td><td style="text-align:right;font-size:12px;font-weight:700;color:#d4650f;">' . $receipt_number . '</td></tr>
      <tr><td style="font-size:12px;color:#666;padding:5px 0;"><strong>Order No</strong></td><td style="text-align:right;font-size:12px;font-weight:700;">#' . str_pad($order['orderid'], 6, '0', STR_PAD_LEFT) . '</td></tr>
      <tr><td style="font-size:12px;color:#666;padding:5px 0;"><strong>Date &amp; Time</strong></td><td style="text-align:right;font-size:12px;font-weight:700;">' . date('d F Y, h:i A', strtotime($order['created_at'])) . '</td></tr>
      <tr><td style="font-size:12px;color:#666;padding:5px 0;"><strong>Order Type</strong></td><td style="text-align:right;font-size:12px;font-weight:700;">' . $order_type_label . '</td></tr>
      <tr><td style="font-size:12px;color:#666;padding:5px 0;"><strong>Status</strong></td><td style="text-align:right;font-size:12px;font-weight:700;">' . $status_upper . '</td></tr>
    </table>

    <!-- Customer Info -->
    <div style="margin-bottom:20px;padding:14px 16px;background:#f8f4ef;border-radius:8px;">
      <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#888;margin-bottom:10px;">Customer</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="font-size:12px;color:#666;padding:3px 0;">👤 Name</td><td style="text-align:right;font-size:12px;font-weight:700;">' . htmlspecialchars($customer_name) . '</td></tr>
        <tr><td style="font-size:12px;color:#666;padding:3px 0;">📞 Phone</td><td style="text-align:right;font-size:12px;font-weight:700;">' . htmlspecialchars($customer_phone) . '</td></tr>
        <tr><td style="font-size:12px;color:#666;padding:3px 0;">✉️ Email</td><td style="text-align:right;font-size:12px;font-weight:700;">' . htmlspecialchars($customer_email) . '</td></tr>
      </table>
    </div>

    <!-- Items -->
    <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#888;margin-bottom:10px;padding-bottom:8px;border-top:2px dashed #d4c9bc;padding-top:16px;">Items Ordered</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
      ' . $items_html . '
    </table>

    <!-- Summary -->
    <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#888;margin-bottom:8px;padding-top:8px;border-top:2px dashed #d4c9bc;">Summary</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
      <tr><td style="padding:5px 0;font-size:13px;color:#444;">Subtotal</td><td style="text-align:right;font-size:13px;">RM ' . number_format($db_subtotal, 2) . '</td></tr>
      ' . $voucher_row . '
      <tr><td style="padding:5px 0;font-size:13px;color:#444;">Service Tax (6%)</td><td style="text-align:right;font-size:13px;">RM ' . number_format($db_tax, 2) . '</td></tr>
    </table>

    <!-- Total Box -->
    <div style="background:#1a1a1a;color:#fffdf9;border-radius:10px;padding:18px 22px;display:flex;justify-content:space-between;margin-bottom:20px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#aaa;">Total Amount</td>
          <td style="text-align:right;font-size:22px;font-weight:700;color:#ff8c42;">RM ' . number_format($db_total, 2) . '</td>
        </tr>
      </table>
    </div>

    <!-- Payment -->
    <div style="padding:12px 16px;background:#f8f4ef;border-radius:8px;margin-bottom:16px;">
      <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#888;margin-bottom:8px;">Payment</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="font-size:12px;color:#666;padding:3px 0;">Method</td><td style="text-align:right;font-size:12px;font-weight:700;">' . htmlspecialchars($payment_label) . '</td></tr>
        <tr><td style="font-size:12px;color:#666;padding:3px 0;">Amount Paid</td><td style="text-align:right;font-size:12px;font-weight:700;color:#d4650f;">RM ' . number_format($db_total, 2) . '</td></tr>
        ' . ($voucher_discount > 0 ? '<tr><td style="font-size:12px;color:#16a34a;padding:3px 0;">You Saved</td><td style="text-align:right;font-size:12px;font-weight:700;color:#16a34a;">RM ' . number_format($voucher_discount, 2) . '</td></tr>' : '') . '
      </table>
    </div>

    ' . $notes_section . '

  </div>

  <!-- Footer -->
  <div style="background:#1a1a1a;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">
    <div style="font-family:Arial,sans-serif;font-size:18px;font-weight:800;color:#ff8c42;margin-bottom:8px;">Thank you for dining with us! 🍴</div>
    <div style="font-size:11px;color:#888;line-height:1.8;">This is an official computer-generated e-receipt.<br>No signature required.<br>info@foodtime.com &nbsp;|&nbsp; +60 12-345 6789</div>
    <div style="margin-top:14px;font-size:10px;color:#555;">Sent on ' . date('d M Y, h:i A') . '</div>
  </div>

</div>
</body>
</html>';

    // ─── PHPMailer via SMTP (Gmail) ───────────────────────────────────────
    $mail = new PHPMailer(true);

    try {
        // ── SMTP Configuration ──────────────────────────────────────────
        // REPLACE the values below with your Gmail credentials
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xiaolilianglaile@gmail.com';
        $mail->Password   = 'ajkgvcnyevoiooel';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0; // Set to 2 temporarily if still failing, check XAMPP error log

        // ── Email Content ───────────────────────────────────────────────
        $mail->setFrom('xiaolilianglaile@gmail.com', 'FOODTIME Restaurant');
        $mail->addAddress($to_email, $customer_name !== '—' ? $customer_name : '');
        $mail->addReplyTo('info@foodtime.com', 'FOODTIME Support');

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = 'Your FOODTIME Receipt - ' . $receipt_number;
        $mail->Body    = $html_body;
        $mail->AltBody = 'Your receipt ' . $receipt_number . ' for RM ' . number_format($db_total, 2) . '. Thank you for dining with FOODTIME!';

        $mail->send();

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Receipt sent successfully to ' . htmlspecialchars($to_email)]);

    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to send: ' . $mail->ErrorInfo]);
    }
    restore_error_handler();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $order['orderid']; ?> — FOODTIME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Sora:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* ─── Reset ─── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        /* ─── Page ─── */
        body {
            font-family: 'IBM Plex Mono', monospace;
            background: #e8e0d5;
            background-image: repeating-linear-gradient(0deg, transparent, transparent 28px, rgba(0,0,0,0.03) 28px, rgba(0,0,0,0.03) 29px);
            min-height: 100vh;
            padding: 40px 16px 80px;
            color: #1a1a1a;
        }

        /* ─── Action Bar ─── */
        .action-bar {
            max-width: 680px;
            margin: 0 auto 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .action-bar .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 10px;
            font-family: 'Sora', sans-serif;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-print  { background: #d4650f; color: #fff; }
        .btn-print:hover  { background: #b85509; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(212,101,15,0.4); }
        .btn-email  { background: #1a73e8; color: #fff; }
        .btn-email:hover  { background: #1558b0; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(26,115,232,0.4); }
        .btn-back   { background: #fff; color: #444; border: 2px solid #ddd; }
        .btn-back:hover   { background: #f5f5f5; transform: translateY(-2px); }

        /* ─── Receipt Paper ─── */
        .receipt {
            max-width: 680px;
            margin: 0 auto;
            background: #fffdf9;
            position: relative;
            filter: drop-shadow(0 8px 32px rgba(0,0,0,0.18));
        }
        .receipt::before {
            content: '';
            display: block;
            height: 18px;
            background:
                radial-gradient(circle at 50% 100%, #e8e0d5 10px, transparent 11px) 0 0 / 22px 18px repeat-x,
                #fffdf9;
            background-position: 0 bottom, 0 0;
        }
        .receipt::after {
            content: '';
            display: block;
            height: 18px;
            background:
                radial-gradient(circle at 50% 0%, #e8e0d5 10px, transparent 11px) 0 0 / 22px 18px repeat-x,
                #fffdf9;
            background-position: 0 top, 0 100%;
        }
        .receipt-inner { padding: 32px 40px; }

        /* ─── Header ─── */
        .r-header { text-align: center; padding-bottom: 24px; margin-bottom: 24px; border-bottom: 3px dashed #d4c9bc; }
        .r-logo-icon { font-size: 3rem; color: #d4650f; display: block; margin-bottom: 8px; }
        .r-brand { font-family: 'Sora', sans-serif; font-size: 2rem; font-weight: 800; color: #1a1a1a; letter-spacing: -0.02em; line-height: 1; }
        .r-brand span { color: #d4650f; }
        .r-tagline { font-size: 0.72rem; color: #888; letter-spacing: 0.15em; text-transform: uppercase; margin-top: 4px; }
        .r-address { font-size: 0.78rem; color: #666; line-height: 1.7; margin-top: 12px; }
        .r-type-stamp { display: inline-block; margin-top: 16px; padding: 7px 22px; border: 2px solid #d4650f; border-radius: 4px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #d4650f; }

        /* ─── Meta rows ─── */
        .r-meta { margin-bottom: 20px; }
        .r-row { display: flex; justify-content: space-between; align-items: baseline; padding: 7px 0; border-bottom: 1px dotted #d4c9bc; font-size: 0.8rem; gap: 16px; }
        .r-row:last-child { border-bottom: none; }
        .r-key { color: #666; font-weight: 500; white-space: nowrap; }
        .r-val { color: #1a1a1a; font-weight: 600; text-align: right; word-break: break-all; }
        .r-val .chip { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
        .chip-paid      { background: #dcfce7; color: #166534; }
        .chip-pending   { background: #fef9c3; color: #854d0e; }
        .chip-completed { background: #dcfce7; color: #166534; }
        .chip-cancelled { background: #fee2e2; color: #991b1b; }
        .chip-dine-in   { background: #dbeafe; color: #1d4ed8; }
        .chip-takeaway  { background: #fff7ed; color: #c2410c; }

        /* ─── Section titles ─── */
        .r-section-title { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #888; padding: 18px 0 10px; border-top: 3px dashed #d4c9bc; margin-top: 4px; }

        /* ─── Items ─── */
        .r-items { margin-bottom: 4px; }
        .r-item { padding: 10px 0; border-bottom: 1px dotted #d4c9bc; }
        .r-item:last-child { border-bottom: none; }
        .r-item-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .r-item-name { font-size: 0.85rem; font-weight: 700; color: #1a1a1a; flex: 1; }
        .r-item-amount { font-size: 0.85rem; font-weight: 700; color: #1a1a1a; white-space: nowrap; }
        .r-item-meta { font-size: 0.75rem; color: #888; margin-top: 2px; }
        .r-custom-opts { margin-top: 7px; display: flex; flex-wrap: wrap; gap: 5px; }
        .r-copt { display: inline-flex; align-items: center; gap: 4px; font-size: 0.7rem; font-weight: 600; padding: 3px 9px; border-radius: 3px; font-family: 'Sora', sans-serif; }
        .r-copt-spice  { background: #fff1f0; color: #b91c1c; border: 1px solid #fca5a5; }
        .r-copt-remove { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .r-copt-addon  { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .r-copt-extra  { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }

        /* ─── Summary ─── */
        .r-summary { padding: 12px 0 4px; }
        .r-sum-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.82rem; color: #444; border-bottom: 1px dotted #d4c9bc; }
        .r-sum-row:last-of-type { border-bottom: none; }
        .r-sum-row.discount { color: #16a34a; font-weight: 700; }
        .r-total-box { margin-top: 16px; background: #1a1a1a; color: #fffdf9; border-radius: 8px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .r-total-box .label { font-size: 0.72rem; letter-spacing: 0.18em; text-transform: uppercase; opacity: 0.7; }
        .r-total-box .amount { font-size: 1.5rem; font-weight: 700; color: #ff8c42; }

        /* ─── Payment ─── */
        .r-payment { margin: 20px 0; }
        .r-notes { margin-top: 4px; padding: 12px 16px; background: #fef6ee; border-left: 4px solid #d4650f; border-radius: 0 6px 6px 0; font-size: 0.78rem; color: #555; line-height: 1.6; }

        /* ─── Barcode ─── */
        .r-barcode { text-align: center; margin: 24px 0 16px; padding: 16px; border-top: 3px dashed #d4c9bc; border-bottom: 3px dashed #d4c9bc; }
        .r-barcode .bars { display: flex; justify-content: center; align-items: flex-end; gap: 2px; height: 48px; margin-bottom: 10px; }
        .r-barcode .bars span { display: inline-block; background: #1a1a1a; width: 2px; }
        .r-barcode .code { font-size: 0.78rem; letter-spacing: 0.12em; color: #555; }

        /* ─── Footer ─── */
        .r-footer { text-align: center; padding-top: 20px; border-top: 3px dashed #d4c9bc; }
        .r-footer .thank-you { font-family: 'Sora', sans-serif; font-size: 1.3rem; font-weight: 800; color: #d4650f; margin-bottom: 10px; }
        .r-footer p { font-size: 0.72rem; color: #888; line-height: 1.8; }
        .r-footer .printed { margin-top: 14px; font-size: 0.68rem; color: #bbb; }

        /* ─── EMAIL MODAL ─── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fffdf9;
            border-radius: 20px;
            padding: 36px;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 24px 60px rgba(0,0,0,0.25);
            animation: modalIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
            position: relative;
        }
        @keyframes modalIn {
            from { transform: scale(0.85) translateY(20px); opacity: 0; }
            to   { transform: scale(1) translateY(0);       opacity: 1; }
        }
        .modal-close {
            position: absolute;
            top: 16px; right: 18px;
            background: none; border: none;
            font-size: 1.3rem; color: #aaa;
            cursor: pointer; line-height: 1;
        }
        .modal-close:hover { color: #333; }
        .modal-icon { font-size: 2.8rem; margin-bottom: 12px; }
        .modal-title { font-family: 'Sora', sans-serif; font-size: 1.3rem; font-weight: 800; color: #1a1a1a; margin-bottom: 6px; }
        .modal-sub { font-size: 0.8rem; color: #777; margin-bottom: 24px; line-height: 1.5; }
        .modal-receipt-ref {
            background: #f8f4ef;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.78rem;
            color: #555;
            margin-bottom: 20px;
        }
        .modal-receipt-ref strong { color: #d4650f; }
        .email-input-wrap { position: relative; margin-bottom: 16px; }
        .email-input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.85rem; }
        .email-input {
            width: 100%;
            padding: 13px 14px 13px 38px;
            border: 2px solid #e5ddd4;
            border-radius: 10px;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 0.85rem;
            color: #1a1a1a;
            background: #fff;
            transition: border-color 0.2s;
            outline: none;
        }
        .email-input:focus { border-color: #1a73e8; }
        .email-hint { font-size: 0.72rem; color: #aaa; margin-bottom: 20px; margin-top: -8px; }
        .btn-send-email {
            width: 100%;
            padding: 14px;
            background: #1a73e8;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'Sora', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .btn-send-email:hover:not(:disabled) { background: #1558b0; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,115,232,0.4); }
        .btn-send-email:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .send-spinner { display: none; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ─── Toast Notification ─── */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: #1a1a1a;
            color: #fff;
            padding: 14px 28px;
            border-radius: 12px;
            font-family: 'Sora', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 28px rgba(0,0,0,0.25);
            z-index: 99999;
            transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s;
            opacity: 0;
            white-space: nowrap;
        }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.success .toast-icon { color: #4ade80; }
        .toast.error   .toast-icon { color: #f87171; }

        /* ─── Print ─── */
        @media print {
            body { background: white; padding: 0; }
            .receipt { filter: none; max-width: 100%; }
            .receipt::before, .receipt::after { display: none; }
            .receipt-inner { padding: 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Action Bar -->
    <div class="action-bar no-print">
        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> Print Receipt</button>
        <button onclick="openEmailModal()" class="btn btn-email"><i class="fab fa-google"></i> Send to Gmail</button>
        <a href="order_details.php?orderid=<?php echo $order_id; ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> Order Details</a>
        <a href="order_history.php" class="btn btn-back"><i class="fas fa-history"></i> All Orders</a>
    </div>

    <!-- Receipt -->
    <div class="receipt" id="receipt-content">
        <div class="receipt-inner">

            <!-- Header -->
            <div class="r-header">
                <i class="fas fa-burger r-logo-icon"></i>
                <div class="r-brand">FOOD<span>TIME</span></div>
                <div class="r-tagline">Restaurant &amp; Café</div>
                <div class="r-address">
                    123 Food Street, Culinary District<br>
                    Tel: +60 12-345 6789 &nbsp;|&nbsp; info@foodtime.com<br>
                    GST Reg: 123-456-789-00001
                </div>
                <div class="r-type-stamp">Official Receipt</div>
            </div>

            <!-- Receipt Meta -->
            <div class="r-meta">
                <div class="r-row"><span class="r-key">Receipt No</span><span class="r-val" style="color:#d4650f;"><?php echo $receipt_number; ?></span></div>
                <div class="r-row"><span class="r-key">Order No</span><span class="r-val">#<?php echo str_pad($order['orderid'], 6, '0', STR_PAD_LEFT); ?></span></div>
                <div class="r-row"><span class="r-key">Date</span><span class="r-val"><?php echo date('d F Y', strtotime($order['created_at'])); ?></span></div>
                <div class="r-row"><span class="r-key">Time</span><span class="r-val"><?php echo date('h:i A', strtotime($order['created_at'])); ?></span></div>
                <div class="r-row">
                    <span class="r-key">Order Type</span>
                    <span class="r-val">
                        <span class="chip <?php echo ($order['order_type'] === 'dine_in') ? 'chip-dine-in' : 'chip-takeaway'; ?>">
                            <?php echo ($order['order_type'] === 'dine_in') ? '🍽 Dine In' : '🛍 Takeaway'; ?>
                        </span>
                    </span>
                </div>
                <div class="r-row">
                    <span class="r-key">Status</span>
                    <span class="r-val">
                        <?php
                        $s = strtolower($order['status'] ?? 'pending');
                        $chip_map = ['paid' => 'chip-paid', 'completed' => 'chip-completed', 'pending' => 'chip-pending', 'cancelled' => 'chip-cancelled'];
                        $chip_cls = $chip_map[$s] ?? 'chip-pending';
                        ?>
                        <span class="chip <?php echo $chip_cls; ?>"><?php echo strtoupper($s); ?></span>
                    </span>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="r-section-title">Customer</div>
            <div class="r-meta">
                <div class="r-row">
                    <span class="r-key"><i class="fas fa-user" style="margin-right:5px;color:#d4650f;"></i>Name</span>
                    <span class="r-val"><?php echo htmlspecialchars($customer_name); ?></span>
                </div>
                <div class="r-row">
                    <span class="r-key"><i class="fas fa-phone" style="margin-right:5px;color:#d4650f;"></i>Phone</span>
                    <span class="r-val">
                        <?php if (!empty($customer_phone) && $customer_phone !== '—'): ?>
                            <a href="tel:<?php echo htmlspecialchars($customer_phone); ?>" style="color:#1a1a1a;text-decoration:none;"><?php echo htmlspecialchars($customer_phone); ?></a>
                        <?php else: ?><span style="color:#bbb;">—</span><?php endif; ?>
                    </span>
                </div>
                <div class="r-row">
                    <span class="r-key"><i class="fas fa-envelope" style="margin-right:5px;color:#d4650f;"></i>Email</span>
                    <span class="r-val">
                        <?php if (!empty($customer_email) && $customer_email !== '—'): ?>
                            <a href="mailto:<?php echo htmlspecialchars($customer_email); ?>" style="color:#1a1a1a;text-decoration:none;"><?php echo htmlspecialchars($customer_email); ?></a>
                        <?php else: ?><span style="color:#bbb;">—</span><?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="r-section-title">Items Ordered</div>
            <div class="r-items">
                <?php foreach ($items as $item):
                    $qty   = intval($item['quantity']);
                    $price = floatval($item['price']);
                    $sub   = $qty * $price;
                    $opts  = parseCustomOptions($item['custom_options'] ?? '');
                ?>
                <div class="r-item">
                    <div class="r-item-top">
                        <div>
                            <div class="r-item-name"><?php echo htmlspecialchars($item['name'] ?? 'Item'); ?></div>
                            <div class="r-item-meta"><?php echo $qty; ?> × RM <?php echo number_format($price, 2); ?></div>
                        </div>
                        <div class="r-item-amount">RM <?php echo number_format($sub, 2); ?></div>
                    </div>
                    <?php if (!empty($opts)): ?>
                    <div class="r-custom-opts">
                        <?php foreach ($opts as $opt): ?>
                        <span class="r-copt r-copt-<?php echo $opt['type']; ?>"><?php echo htmlspecialchars($opt['text']); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <p style="font-size:0.8rem;color:#aaa;text-align:center;padding:12px 0;">No items found.</p>
                <?php endif; ?>
            </div>

            <!-- Summary -->
            <div class="r-section-title">Summary</div>
            <div class="r-summary">
                <div class="r-sum-row"><span>Subtotal</span><span>RM <?php echo number_format($db_subtotal, 2); ?></span></div>
                <?php if ($voucher_discount > 0): ?>
                <div class="r-sum-row discount">
                    <span><i class="fas fa-tag"></i> Voucher <?php echo $voucher_code_display ? "($voucher_code_display)" : ''; ?></span>
                    <span>− RM <?php echo number_format($voucher_discount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="r-sum-row"><span>Service Tax (6%)</span><span>RM <?php echo number_format($db_tax, 2); ?></span></div>
            </div>
            <div class="r-total-box">
                <span class="label">Total Amount</span>
                <span class="amount">RM <?php echo number_format($db_total, 2); ?></span>
            </div>

            <!-- Payment Info -->
            <div class="r-section-title">Payment</div>
            <div class="r-payment">
                <div class="r-row">
                    <span class="r-key">Method</span>
                    <span class="r-val"><?php
                        $method_labels = ['cash' => 'Cash at Counter', 'card' => 'Credit / Debit Card', 'online_banking' => 'Online Banking / FPX', 'ewallet' => 'E-Wallet / QR'];
                        $pm = strtolower($order['payment_method'] ?? '');
                        echo $method_labels[$pm] ?? strtoupper(str_replace('_', ' ', $pm));
                    ?></span>
                </div>
                <div class="r-row">
                    <span class="r-key">Amount Paid</span>
                    <span class="r-val" style="color:#d4650f;">RM <?php echo number_format($db_total, 2); ?></span>
                </div>
                <?php if ($voucher_discount > 0): ?>
                <div class="r-row">
                    <span class="r-key">You Saved</span>
                    <span class="r-val" style="color:#16a34a;">RM <?php echo number_format($voucher_discount, 2); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Special Notes -->
            <?php
            $clean_notes = trim(preg_replace('/Voucher:\s*[A-Z0-9]+\s*\(RM\s*[\d.]+\s*off\)/i', '', $order['special_notes'] ?? ''));
            if (!empty($clean_notes)):
            ?>
            <div class="r-section-title">Special Instructions</div>
            <div class="r-notes"><?php echo nl2br(htmlspecialchars($clean_notes)); ?></div>
            <?php endif; ?>

            <!-- Barcode -->
            <div class="r-barcode">
                <div class="bars">
                    <?php
                    srand(crc32($receipt_number));
                    for ($i = 0; $i < 60; $i++) {
                        $h = rand(16, 48);
                        $w = (rand(0,3) === 0) ? 4 : 2;
                        echo "<span style='height:{$h}px;width:{$w}px;'></span>";
                    }
                    ?>
                </div>
                <div class="code"><?php echo $receipt_number; ?></div>
            </div>

            <!-- Footer -->
            <div class="r-footer">
                <div class="thank-you">Thank you for dining with us! 🍴</div>
                <p>
                    This is an official computer-generated receipt.<br>
                    No signature required.<br>
                    For enquiries: info@foodtime.com | +60 12-345 6789
                </p>
                <p class="printed">Printed on <?php echo date('d M Y, h:i A'); ?></p>
            </div>

        </div>
    </div>

    <!-- ─── EMAIL MODAL ─────────────────────────────────── -->
    <div class="modal-overlay no-print" id="emailModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEmailModal()" title="Close">&times;</button>
            <div class="modal-icon">📧</div>
            <div class="modal-title">Send E-Receipt</div>
            <div class="modal-sub">Enter the email address to deliver this receipt to your inbox.</div>
            <div class="modal-receipt-ref">
                Receipt <strong><?php echo $receipt_number; ?></strong> &nbsp;·&nbsp;
                Order <strong>#<?php echo str_pad($order['orderid'], 6, '0', STR_PAD_LEFT); ?></strong> &nbsp;·&nbsp;
                <strong>RM <?php echo number_format($db_total, 2); ?></strong>
            </div>
            <div class="email-input-wrap">
                <i class="fas fa-envelope"></i>
                <input
                    type="email"
                    id="emailInput"
                    class="email-input"
                    placeholder="customer@gmail.com"
                    value="<?php echo htmlspecialchars($customer_email !== '—' ? $customer_email : ''); ?>"
                    autocomplete="email"
                    onkeydown="if(event.key==='Enter') sendReceipt()"
                />
            </div>
            <p class="email-hint">💡 The customer's email is pre-filled if available. You can change it.</p>
            <button class="btn-send-email" id="sendBtn" onclick="sendReceipt()">
                <div class="send-spinner" id="sendSpinner"></div>
                <i class="fab fa-google" id="sendIcon"></i>
                <span id="sendLabel">Send to Gmail</span>
            </button>
        </div>
    </div>

    <!-- ─── TOAST ─── -->
    <div class="toast" id="toast">
        <span class="toast-icon" id="toastIcon"></span>
        <span id="toastMsg"></span>
    </div>

    <script>
        const ORDER_ID = '<?php echo addslashes($order_id); ?>';

        // ── Modal open/close ──────────────────────────────────
        function openEmailModal() {
            document.getElementById('emailModal').classList.add('active');
            setTimeout(() => document.getElementById('emailInput').focus(), 200);
        }
        function closeEmailModal() {
            document.getElementById('emailModal').classList.remove('active');
        }
        // Close on overlay click
        document.getElementById('emailModal').addEventListener('click', function(e) {
            if (e.target === this) closeEmailModal();
        });
        // Close on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEmailModal();
        });

        // ── Send Receipt ──────────────────────────────────────
        function sendReceipt() {
            const email = document.getElementById('emailInput').value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showToast('error', '⚠️', 'Please enter a valid email address.');
                document.getElementById('emailInput').focus();
                return;
            }

            // Show loading state
            const btn      = document.getElementById('sendBtn');
            const spinner  = document.getElementById('sendSpinner');
            const icon     = document.getElementById('sendIcon');
            const label    = document.getElementById('sendLabel');
            btn.disabled   = true;
            spinner.style.display = 'block';
            icon.style.display    = 'none';
            label.textContent     = 'Sending…';

            const formData = new FormData();
            formData.append('send_email', '1');
            formData.append('email_to',   email);

            fetch('receipt.php?orderid=' + ORDER_ID, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                closeEmailModal();
                if (data.success) {
                    showToast('success', '✅', data.message);
                } else {
                    showToast('error', '❌', data.message);
                }
            })
            .catch(() => {
                showToast('error', '❌', 'Network error. Please try again.');
            })
            .finally(() => {
                btn.disabled          = false;
                spinner.style.display = 'none';
                icon.style.display    = 'inline';
                label.textContent     = 'Send to Gmail';
            });
        }

        // ── Toast ─────────────────────────────────────────────
        let toastTimer;
        function showToast(type, icon, msg) {
            const el = document.getElementById('toast');
            document.getElementById('toastIcon').textContent = icon;
            document.getElementById('toastMsg').textContent  = msg;
            el.className = 'toast ' + type + ' show';
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => el.classList.remove('show'), 4000);
        }
    </script>

</body>
</html>