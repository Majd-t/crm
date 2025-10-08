<?php
include "db.php";

$staff_id = $_SESSION['staff_id'];
$sql = "SELECT id, name FROM customers WHERE assigned_staff_id = ? AND created_at >= NOW() - INTERVAL 10 SECOND";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $staff_id);
$stmt->execute();
$result = $stmt->get_result();

$new_customers = [];
while ($row = $result->fetch_assoc()) {
    $new_customers[] = ['id' => $row['id'], 'name' => $row['name']];
}

echo json_encode(['new_customers' => $new_customers]);
$stmt->close();
$conn->close();
?>