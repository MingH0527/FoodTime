<?php
// award_points.php
// Call this after an order is placed/paid successfully
// Usage: include 'award_points.php'; awardPoints($connect, $user_id, $order_id, $total_price);

function awardPoints($connect, $user_id, $order_id, $total_price) {
    // Calculate points: Only full RM counts, each full RM = 5 points
    $full_ringgit = floor((float)$total_price);
    $points = $full_ringgit * 5;
    
    if ($points <= 0) return 0;

    // Check not already awarded
    $check = mysqli_prepare($connect, "SELECT points_earned FROM orders WHERE orderid = ?");
    mysqli_stmt_bind_param($check, 's', $order_id);
    mysqli_stmt_execute($check);
    $result = mysqli_stmt_get_result($check);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check);
    
    if ($row && $row['points_earned'] > 0) return 0; // already done

    // Save points on the order row
    $upd_order = mysqli_prepare($connect, "UPDATE orders SET points_earned = ? WHERE orderid = ?");
    mysqli_stmt_bind_param($upd_order, 'is', $points, $order_id);
    mysqli_stmt_execute($upd_order);
    mysqli_stmt_close($upd_order);

    // Add to user balance (check if columns exist)
    // Check if membership_points column exists, if not create it
    $check_column = mysqli_query($connect, "SHOW COLUMNS FROM users LIKE 'membership_points'");
    if (mysqli_num_rows($check_column) == 0) {
        mysqli_query($connect, "ALTER TABLE users ADD COLUMN membership_points INT DEFAULT 0");
        mysqli_query($connect, "ALTER TABLE users ADD COLUMN total_points_earned INT DEFAULT 0");
    }
    
    $upd_user = mysqli_prepare($connect,
        "UPDATE users SET membership_points = COALESCE(membership_points, 0) + ?,
                          total_points_earned = COALESCE(total_points_earned, 0) + ?
         WHERE userid = ?");
    mysqli_stmt_bind_param($upd_user, 'iis', $points, $points, $user_id);
    mysqli_stmt_execute($upd_user);
    mysqli_stmt_close($upd_user);

    return $points;
}
?>