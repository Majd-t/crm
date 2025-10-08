<?php
// fetch_messages.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_type']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_type = $_SESSION['user_type']; // 'admin' or 'staff'
$user_id = $_SESSION['user_id'];

if (isset($_GET['other_type']) && isset($_GET['other_id'])) {
    $other_type = $_GET['other_type'];
    $other_id = intval($_GET['other_id']);
    
    // Fetch messages
    $stmt = $conn->prepare("SELECT * FROM messages 
        WHERE (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
           OR (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
        ORDER BY created_at ASC");
    $stmt->bind_param('sisssiss', $user_type, $user_id, $other_type, $other_id, $other_type, $other_id, $user_type, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    
    // Mark as read
    $updateStmt = $conn->prepare("UPDATE messages SET status = 1 
        WHERE sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ? AND status = 0");
    $updateStmt->bind_param('siss', $other_type, $other_id, $user_type, $user_id);
    $updateStmt->execute();
    
    echo json_encode(['success' => true, 'messages' => $messages]);
} else {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
}
?>