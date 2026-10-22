<?php
/* ============================================================
   fetch_conversation.php — FOODTIME Support Chat
   SHARED ADMIN INBOX edition

   Unread model change:
   - BEFORE: unread = cm.receiver_id = $admin_id AND cc.admin_id = $admin_id
             → only ONE assigned admin ever saw unread badges.
   - AFTER:  unread = customer messages NOT YET in chat_message_reads for this admin
             → ALL admins see unread badges independently.

   chat_message_reads tracks per-admin read state.
   A customer message is unread for every admin until each admin opens it.
   Opening a conversation only marks it read for THAT admin — not others.

   Handles:
   - GET  ?action=unread_counts  → return unread badge counts (all admins)
   - GET  ?action=list           → full conv list for admin sidebar polling
   - POST action=close           → close a conversation
   - POST action=set_status      → change status; inserts resolved message
     only when transitioning TO resolved and only once.
   ============================================================ */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'base.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id  = $_SESSION['user_id'];
$position = $_SESSION['position'] ?? 'customer';

// ── GET requests ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // ── GET unread_counts (admin only) ──
    // NEW: counts customer messages that have NO read record for this admin.
    // Completely removed: cc.admin_id = ? filter (that was the private-inbox bug).
    if ($action === 'unread_counts' && $position === 'admin') {
        $stmt = mysqli_prepare($connect,
            "SELECT cm.conversation_id, COUNT(*) AS cnt
             FROM chat_messages cm
             JOIN chat_conversations cc ON cc.conversation_id = cm.conversation_id
             WHERE cm.message_type IN ('text')
               AND cm.sender_id = cc.customer_id          -- customer-sent only
               AND NOT EXISTS (
                   SELECT 1 FROM chat_message_reads cmr
                   WHERE cmr.message_id = cm.message_id
                     AND cmr.admin_id = ?
               )
             GROUP BY cm.conversation_id");
        mysqli_stmt_bind_param($stmt, 's', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $counts = [];
        while ($r = mysqli_fetch_assoc($result)) {
            $counts[$r['conversation_id']] = (int)$r['cnt'];
        }
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'counts' => $counts]);
        exit;
    }

    // ── GET list: full conversation list for admin sidebar polling ──
    // NEW: unread subquery uses chat_message_reads, no admin_id restriction.
    // All admins get the same conversation list; each gets their own unread counts.
    if ($action === 'list' && $position === 'admin') {
        $safe_admin = mysqli_real_escape_string($connect, $user_id);

        $sql = "SELECT cc.conversation_id,
                       cc.subject,
                       cc.category,
                       cc.status,
                       cc.related_orderid,
                       cc.admin_id,
                       cc.updated_at,
                       u.username AS customer_name,
                       /* NEW unread: customer messages not yet read by this admin */
                       (SELECT COUNT(*) FROM chat_messages cm2
                        JOIN chat_conversations cc2 ON cc2.conversation_id = cm2.conversation_id
                        WHERE cm2.conversation_id = cc.conversation_id
                          AND cm2.message_type IN ('text')
                          AND cm2.sender_id = cc2.customer_id
                          AND NOT EXISTS (
                              SELECT 1 FROM chat_message_reads cmr2
                              WHERE cmr2.message_id = cm2.message_id
                                AND cmr2.admin_id = '$safe_admin'
                          )
                       ) AS unread,
                       (SELECT cm3.message_text FROM chat_messages cm3
                        WHERE cm3.conversation_id = cc.conversation_id
                        ORDER BY cm3.created_at DESC LIMIT 1) AS last_message,
                       (SELECT cm4.created_at FROM chat_messages cm4
                        WHERE cm4.conversation_id = cc.conversation_id
                        ORDER BY cm4.created_at DESC LIMIT 1) AS last_msg_time
                FROM chat_conversations cc
                JOIN users u ON u.userid = cc.customer_id
                ORDER BY CASE cc.status WHEN 'open' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END ASC,
                         cc.updated_at DESC
                LIMIT 100";

        $result = mysqli_query($connect, $sql);
        $conversations = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $last_time = $row['last_msg_time'] ?: $row['updated_at'];
                $diff = time() - strtotime($last_time);
                if ($diff < 60)         $time_ago = 'Just now';
                elseif ($diff < 3600)   $time_ago = floor($diff/60).'m ago';
                elseif ($diff < 86400)  $time_ago = floor($diff/3600).'h ago';
                elseif ($diff < 604800) $time_ago = floor($diff/86400).'d ago';
                else                    $time_ago = date('d M', strtotime($last_time));

                $prev_text  = $row['last_message'] ?? '';
                $search_str = strtolower(
                    $row['customer_name'] . ' ' .
                    $row['subject'] . ' ' .
                    $row['category'] . ' ' .
                    ($row['related_orderid'] ?? '') . ' ' .
                    $prev_text
                );

                $conversations[] = [
                    'conversation_id' => $row['conversation_id'],
                    'customer_name'   => $row['customer_name'],
                    'subject'         => $row['subject'],
                    'category'        => $row['category'],
                    'status'          => $row['status'],
                    'related_orderid' => $row['related_orderid'],
                    'admin_id'        => $row['admin_id'],
                    'unread'          => (int)$row['unread'],
                    'last_message'    => $prev_text,
                    'time_ago'        => $time_ago,
                    'search_str'      => $search_str,
                ];
            }
        }

        echo json_encode(['success' => true, 'conversations' => $conversations]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $conv_id = trim($_POST['conv_id'] ?? '');

    if ($position !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required.']);
        exit;
    }

    if (empty($conv_id)) {
        echo json_encode(['success' => false, 'message' => 'No conversation specified.']);
        exit;
    }

    // ── Close conversation ──
    if ($action === 'close') {
        $stmt = mysqli_prepare($connect,
            "UPDATE chat_conversations SET status = 'closed', updated_at = NOW()
             WHERE conversation_id = ?");
        mysqli_stmt_bind_param($stmt, 's', $conv_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Set status ──
    if ($action === 'set_status') {
        $new_status = $_POST['status'] ?? '';
        $valid = ['open', 'pending', 'resolved', 'closed'];
        if (!in_array($new_status, $valid)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            exit;
        }

        $s0 = mysqli_prepare($connect,
            "SELECT status, customer_id FROM chat_conversations WHERE conversation_id = ?");
        mysqli_stmt_bind_param($s0, 's', $conv_id);
        mysqli_stmt_execute($s0);
        $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($s0));
        mysqli_stmt_close($s0);

        if (!$cur) {
            echo json_encode(['success' => false, 'message' => 'Conversation not found.']);
            exit;
        }

        $stmt = mysqli_prepare($connect,
            "UPDATE chat_conversations SET status = ?, updated_at = NOW() WHERE conversation_id = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $new_status, $conv_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Insert resolved system message only once, only on transition TO resolved
        if ($new_status === 'resolved' && $cur['status'] !== 'resolved') {
            $safe_conv = mysqli_real_escape_string($connect, $conv_id);
            $already = mysqli_fetch_assoc(mysqli_query($connect,
                "SELECT COUNT(*) AS cnt FROM chat_messages
                 WHERE conversation_id = '$safe_conv'
                   AND message_type = 'system'
                   AND message_text LIKE '%resolved%'"));

            if ((int)($already['cnt'] ?? 0) === 0) {
                $last_msg = mysqli_fetch_assoc(mysqli_query($connect,
                    "SELECT message_id FROM chat_messages ORDER BY created_at DESC LIMIT 1"));
                $msg_num = $last_msg ? ((int) preg_replace('/[^0-9]/', '', $last_msg['message_id']) + 1) : 1;
                $msg_id  = 'MSG' . str_pad($msg_num, 6, '0', STR_PAD_LEFT);

                $resolved_text = "This issue has been resolved. Thank you for contacting FOODTIME Support.";
                $customer_id   = $cur['customer_id'];

                $stmt2 = mysqli_prepare($connect,
                    "INSERT INTO chat_messages
                     (message_id, conversation_id, sender_id, receiver_id, message_type, message_text, is_read, created_at)
                     VALUES (?, ?, NULL, ?, 'system', ?, 0, NOW())");
                mysqli_stmt_bind_param($stmt2, 'ssss', $msg_id, $conv_id, $customer_id, $resolved_text);
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_close($stmt2);
            }
        }

        echo json_encode(['success' => true, 'new_status' => $new_status]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method.']);
exit;
?>