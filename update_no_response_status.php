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
$no_response_status_id = isset($_POST['no_response_status_id']) ? $_POST['no_response_status_id'] : null;
$no_response_status_id = ($no_response_status_id === '0' || $no_response_status_id === 0) ? null : intval($no_response_status_id);

if ($customer_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz müşteri ID.']);
    exit();
}

// Verify no_response_status_id if not null
if ($no_response_status_id !== null) {
    $sql_check_status = "SELECT id FROM no_response_statuses WHERE id = ? AND is_active = 1";
    $stmt_check_status = $conn->prepare($sql_check_status);
    $stmt_check_status->bind_param('i', $no_response_status_id);
    $stmt_check_status->execute();
    if ($stmt_check_status->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz veya aktif olmayan cevapsız durum ID.']);
        $stmt_check_status->close();
        exit();
    }
    $stmt_check_status->close();
}

// Update customer no response status
$sql_update = "UPDATE customers SET no_response_status_id = ? WHERE id = ? AND assigned_staff_id = ?";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param('iii', $no_response_status_id, $customer_id, $_SESSION['staff_id']);
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