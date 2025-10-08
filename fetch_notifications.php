<?php
include "db.php";

$staff_id = $_SESSION['staff_id'];
$sql = "SELECT * FROM notifications WHERE user_type = 'staff' AND user_id = ? AND is_read = 0 ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $staff_id);
$stmt->execute();
$result = $stmt->get_result();

$list = '';
$count = $result->num_rows;

while ($notif = $result->fetch_assoc()) {
    $list .= "<div class='p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition'>";
    $list .= "<p class='text-blue-800'>" . htmlspecialchars($notif['message']) . "</p>";
    $list .= "<p class='text-gray-500 text-sm'>" . $notif['created_at'] . "</p>";
    if ($notif['type'] == 'client_assigned') {
        $list .= "<a href='client_profile.php?id={$notif['related_id']}' class='text-blue-600 hover:underline text-sm'>Müşteri Profiline Git</a>";
    } elseif ($notif['type'] == 'new_message') {
        $list .= "<a href='staff_chat.php' class='text-blue-600 hover:underline text-sm'>Mesaja Git</a>";
    }
    $list .= "</div>";
}

echo json_encode(['count' => $count, 'list' => $list]);
$stmt->close();
$conn->close();
?>