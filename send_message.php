<?php
// send_message.php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_type = $_SESSION['user_type']; // 'admin' or 'staff'
$user_id = $_SESSION['user_id'];

if (!isset($_POST['receiver_type']) || !isset($_POST['receiver_id']) || !isset($_POST['message']) || empty($_POST['message'])) {
    echo json_encode(['success' => false, 'error' => 'Missing or empty parameters']);
    exit;
}

$receiver_type = $_POST['receiver_type'];
$receiver_id = intval($_POST['receiver_id']);
$msg = $conn->real_escape_string($_POST['message']);

try {
    $stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sisis', $user_type, $user_id, $receiver_type, $receiver_id, $msg);
    $success = $stmt->execute();
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database insert failed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>