<?php
function addNotification($conn, $user_type, $user_id, $type, $message, $related_id = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_type, user_id, type, message, related_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param('sissi', $user_type, $user_id, $type, $message, $related_id);
    $stmt->execute();
    $stmt->close();
}
?>