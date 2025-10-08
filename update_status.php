<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Geçersiz istek yöntemi.']);
    exit();
}

$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
$status_id = isset($_POST['status_id']) ? intval($_POST['status_id']) : 0;

if ($customer_id <= 0 || $status_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz müşteri ID veya durum ID.']);
    exit();
}

// Verify status_id exists and is active
$sql_check_status = "SELECT id FROM customer_statuses WHERE id = ? AND is_active = 1";
$stmt_check_status = $conn->prepare($sql_check_status);
$stmt_check_status->bind_param('i', $status_id);
$stmt_check_status->execute();
if ($stmt_check_status->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz veya aktif olmayan durum ID.']);
    $stmt_check_status->close();
    exit();
}
$stmt_check_status->close();

// Update customer status
$sql_update = "UPDATE customers SET status_id = ? WHERE id = ? AND assigned_staff_id = ?";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param('iii', $status_id, $customer_id, $_SESSION['staff_id']);
if ($stmt_update->execute()) {
    if ($stmt_update->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Müşteri bulunamadı veya yetkiniz yok.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $stmt_update->error]);
}
$stmt_update->close();

$conn->close();
?>