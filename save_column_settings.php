<?php
include "db.php";

$staff_id = $_SESSION['staff_id'];
$columns = $_POST['columns'];

$sql = "INSERT INTO staff_settings (staff_id, setting_type, settings) VALUES (?, 'kanban_columns', ?) 
        ON DUPLICATE KEY UPDATE settings = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $staff_id, $columns, $columns);
$stmt->execute();

$stmt->close();
$conn->close();
echo json_encode(['success' => true]);
?>