<?php
session_start();
include 'db.php';

if (!isset($_SESSION['staff_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$user_type = isset($_SESSION['staff_id']) ? 'staff' : 'admin';
$user_id = isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : $_SESSION['admin_id'];

if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = ? AND user_id = ? AND is_read = 0");
    $stmt->bind_param('si', $user_type, $user_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . ($_SESSION['staff_id'] ? "staff_dashboard.php" : "admin_dashboard.php"));
exit;
?>