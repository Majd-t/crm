<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
$admin_name = $_SESSION['admin_name'];
@$admin_id = $_SESSION['admin_id'];

// Handle Add Customer Status
if (isset($_POST['add_customer_status'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $is_active = 1;

    $stmt = $conn->prepare("INSERT INTO customer_statuses (name, description, color, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param('sssi', $name, $description, $color, $is_active);
    if ($stmt->execute()) {
        $message = "Yeni müşteri durumu başarıyla eklendi!";
    } else {
        $error = "Müşteri durumu eklenirken hata oluştu: " . mysqli_error($conn);
    }
    $stmt->close();
}

// Handle Add No Response Status
if (isset($_POST['add_no_response_status'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $is_active = 1;

    $stmt = $conn->prepare("INSERT INTO no_response_statuses (name, description, color, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param('sssi', $name, $description, $color, $is_active);
    if ($stmt->execute()) {
        $message = "Yeni cevapsız durum başarıyla eklendi!";
    } else {
        $error = "Cevapsız durum eklenirken hata oluştu: " . mysqli_error($conn);
    }
    $stmt->close();
}

// Handle Update Customer Status
if (isset($_POST['update_customer_status'])) {
    $id = (int)$_POST['status_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $is_active = 1;

    $stmt = $conn->prepare("UPDATE customer_statuses SET name = ?, description = ?, color = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('sssii', $name, $description, $color, $is_active, $id);
    if ($stmt->execute()) {
        $message = "Müşteri durumu başarıyla güncellendi!";
    } else {
        $error = "Müşteri durumu güncellenirken hata oluştu: " . mysqli_error($conn);
    }
    $stmt->close();
}

// Handle Update No Response Status
if (isset($_POST['update_no_response_status'])) {
    $id = (int)$_POST['status_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $is_active = 1;

    $stmt = $conn->prepare("UPDATE no_response_statuses SET name = ?, description = ?, color = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('sssii', $name, $description, $color, $is_active, $id);
    if ($stmt->execute()) {
        $message = "Cevapsız durum başarıyla güncellendi!";
    } else {
        $error = "Cevapsız durum güncellenirken hata oluştu: " . mysqli_error($conn);
    }
    $stmt->close();
}

// Handle Delete Customer Status
if (isset($_POST['delete_customer_status'])) {
    $id = (int)$_POST['status_id'];
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM customers WHERE status_id = ?");
    $stmt_check->bind_param('i', $id);
    $stmt_check->execute();
    $count = $stmt_check->get_result()->fetch_assoc()['count'];
    $stmt_check->close();

    if ($count > 0) {
        $error = "Bu müşteri durumu müşterilere bağlı olduğu için silinemez!";
    } else {
        $stmt = $conn->prepare("DELETE FROM customer_statuses WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = "Müşteri durumu başarıyla silindi!";
        } else {
            $error = "Müşteri durumu silinirken hata oluştu: " . mysqli_error($conn);
        }
        $stmt->close();
    }
}

// Handle Delete No Response Status
if (isset($_POST['delete_no_response_status'])) {
    $id = (int)$_POST['status_id'];
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM customers WHERE no_response_status_id = ?");
    $stmt_check->bind_param('i', $id);
    $stmt_check->execute();
    $count = $stmt_check->get_result()->fetch_assoc()['count'];
    $stmt_check->close();

    if ($count > 0) {
        $error = "Bu cevapsız durum müşterilere bağlı olduğu için silinemez!";
    } else {
        $stmt = $conn->prepare("DELETE FROM no_response_statuses WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = "Cevapsız durum başarıyla silindi!";
        } else {
            $error = "Cevapsız durum silinirken hata oluştu: " . mysqli_error($conn);
        }
        $stmt->close();
    }
}

// Handle Reorder Customer Statuses
if (isset($_POST['reorder_customer_statuses'])) {
    $order = json_decode($_POST['order'], true);
    if ($order && is_array($order)) {
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
            mysqli_query($conn, "CREATE TEMPORARY TABLE temp_customer_statuses AS SELECT * FROM customer_statuses");
            mysqli_query($conn, "DELETE FROM customer_statuses");
            mysqli_query($conn, "ALTER TABLE customer_statuses AUTO_INCREMENT = 1");

            $stmt = $conn->prepare("INSERT INTO customer_statuses (name, description, color, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($order as $old_id) {
                $stmt_select = $conn->prepare("SELECT name, description, color, is_active, created_at, updated_at FROM temp_customer_statuses WHERE id = ?");
                $stmt_select->bind_param('i', $old_id);
                $stmt_select->execute();
                $result = $stmt_select->get_result()->fetch_assoc();
                $stmt_select->close();

                if ($result) {
                    $stmt->bind_param('sssiss', $result['name'], $result['description'], $result['color'], $result['is_active'], $result['created_at'], $result['updated_at']);
                    if (!$stmt->execute()) {
                        throw new Exception("Insert failed for old ID $old_id: " . mysqli_error($conn));
                    }
                    $new_id = mysqli_insert_id($conn);
                    $stmt_update = $conn->prepare("UPDATE customers SET status_id = ? WHERE status_id = ?");
                    $stmt_update->bind_param('ii', $new_id, $old_id);
                    if (!$stmt_update->execute()) {
                        throw new Exception("Customer update failed for old ID $old_id: " . mysqli_error($conn));
                    }
                    $stmt_update->close();
                }
            }
            $stmt->close();
            mysqli_query($conn, "DROP TEMPORARY TABLE temp_customer_statuses");
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            mysqli_commit($conn);
            $message = "Müşteri durumları başarıyla yeniden sıralandı!";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            $error = "Müşteri durum sıralama sırasında hata oluştu: " . $e->getMessage();
        }
    } else {
        $error = "Geçersiz müşteri durum sıralama verisi.";
    }
}

// Handle Reorder No Response Statuses
if (isset($_POST['reorder_no_response_statuses'])) {
    $order = json_decode($_POST['order'], true);
    if ($order && is_array($order)) {
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
            mysqli_query($conn, "CREATE TEMPORARY TABLE temp_no_response_statuses AS SELECT * FROM no_response_statuses");
            mysqli_query($conn, "DELETE FROM no_response_statuses");
            mysqli_query($conn, "ALTER TABLE no_response_statuses AUTO_INCREMENT = 1");

            $stmt = $conn->prepare("INSERT INTO no_response_statuses (name, description, color, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($order as $old_id) {
                $stmt_select = $conn->prepare("SELECT name, description, color, is_active, created_at, updated_at FROM temp_no_response_statuses WHERE id = ?");
                $stmt_select->bind_param('i', $old_id);
                $stmt_select->execute();
                $result = $stmt_select->get_result()->fetch_assoc();
                $stmt_select->close();

                if ($result) {
                    $stmt->bind_param('sssiss', $result['name'], $result['description'], $result['color'], $result['is_active'], $result['created_at'], $result['updated_at']);
                    if (!$stmt->execute()) {
                        throw new Exception("Insert failed for old ID $old_id: " . mysqli_error($conn));
                    }
                    $new_id = mysqli_insert_id($conn);
                    $stmt_update = $conn->prepare("UPDATE customers SET no_response_status_id = ? WHERE no_response_status_id = ?");
                    $stmt_update->bind_param('ii', $new_id, $old_id);
                    if (!$stmt_update->execute()) {
                        throw new Exception("Customer update failed for old ID $old_id: " . mysqli_error($conn));
                    }
                    $stmt_update->close();
                }
            }
            $stmt->close();
            mysqli_query($conn, "DROP TEMPORARY TABLE temp_no_response_statuses");
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            mysqli_commit($conn);
            $message = "Cevapsız durumlar başarıyla yeniden sıralandı!";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            $error = "Cevapsız durum sıralama sırasında hata oluştu: " . $e->getMessage();
        }
    } else {
        $error = "Geçersiz cevapsız durum sıralama verisi.";
    }
}

// Fetch all statuses
$sql = "SELECT * FROM customer_statuses ORDER BY id ASC";
$result = mysqli_query($conn, $sql);
$customer_statuses = mysqli_fetch_all($result, MYSQLI_ASSOC);

$sql = "SELECT * FROM no_response_statuses ORDER BY id ASC";
$result = mysqli_query($conn, $sql);
$no_response_statuses = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Durum Yönetimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(to bottom, #F5F7FA, #E5E7EB); }
        .custom-shadow { box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15); }
        .hover-scale { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .hover-scale:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(30, 64, 175, 0.2); }
        .blue-button { background: linear-gradient(to right, #1E40AF, #3B82F6); }
        .blue-button:hover { background: linear-gradient(to right, #1E3A8A, #2563EB); }
        .section-header { background: linear-gradient(to right, #1E40AF, #3B82F6); color: white; }
        .sidebar { background: linear-gradient(to bottom, #1E40AF, #3B82F6); }
        .sidebar a { transition: all 0.3s ease; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.1); }
        .table-container { border-radius: 12px; overflow: hidden; }
        table { border-collapse: separate; border-spacing: 0; }
        th, td { border-bottom: 1px solid #E5E7EB; }
        th:first-child, td:first-child { border-left: 1px solid #E5E7EB; }
        th:last-child, td:last-child { border-right: 1px solid #E5E7EB; }
        th { border-top: 1px solid #E5E7EB; border-radius: 0; }
        tr:last-child td { border-bottom: none; }
        .form-input { 
            padding: 0.75rem; 
            border: 1px solid #d1d5db; 
            border-radius: 0.5rem; 
            width: 100%; 
            transition: border-color 0.3s ease; 
        }
        .form-input:focus { 
            outline: none; 
            border-color: #3B82F6; 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); 
        }
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); 
            justify-content: center; 
            align-items: center; 
            z-index: 1000; 
        }
        .modal-content { 
            background-color: #fff; 
            padding: 2rem; 
            border-radius: 1rem; 
            width: 100%; 
            max-width: 500px; 
            position: relative; 
            box-shadow: 0 10px 15px rgba(0,0,0,0.2);
        }
        .color-picker-container { 
            position: relative; 
            display: flex; 
            align-items: center; 
            gap: 0.5rem; 
        }
        .color-preview { 
            width: 3rem; 
            height: 3rem; 
            border-radius: 0.5rem; 
            border: 1px solid #d1d5db; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            transition: background-color 0.3s ease; 
        }
        .sortable-row { cursor: move; }
        .sortable-row:hover { background-color: #e0f2fe; }
        .sortable-ghost { opacity: 0.4; background-color: #bfdbfe; }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex">
        <!-- Sidebar -->
        <div class="sidebar w-64 h-screen fixed top-0 left-0 p-6 text-white">
            <h2 class="text-2xl font-bold text-center mb-10"><i class="fas fa-user-shield mr-2"></i> <?php echo htmlspecialchars($admin_name); ?></h2>
            <nav class="space-y-3">
                <a href="admin_dashboard.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
                <a href="admin_statuses.php" class="block px-4 py-3 rounded-lg text-white bg-white/10"><i class="fas fa-tags mr-2"></i> Durum Yönetimi</a>
                                <a href="ai_analysis.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-brain"></i> AI Analizi</a>

                <a href="assign.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-random mr-2"></i> Müşteri Dağıtımı</a>
                <a href="admin_chat.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-envelope mr-2"></i> Mesajlar</a>
                <a href="logout.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-sign-out-alt mr-2"></i> Çıkış Yap</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="ml-64 p-8 w-full max-w-7xl mx-auto">
            <h1 class="text-4xl font-bold text-gray-900 mb-10 flex items-center">
                <i class="fas fa-tags mr-2"></i> Durum Yönetimi
            </h1>

            <!-- Add Customer Status Form -->
            <div class="table-container bg-white custom-shadow mb-12">
                <div class="section-header px-6 py-4">
                    <h2 class="text-xl font-semibold">Yeni Müşteri Durumu Ekle</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Durum Adı</label>
                                <input type="text" name="name" class="form-input" placeholder="Durum adı girin" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Açıklama</label>
                                <input type="text" name="description" class="form-input" placeholder="Durum açıklaması girin">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Renk</label>
                                <div class="color-picker-container">
                                    <input type="color" name="color" id="colorPickerAddCustomer" class="form-input w-12 h-12 p-1" value="#ffffff" required>
                                    <div id="colorPreviewAddCustomer" class="color-preview" style="background-color: #ffffff;"></div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_customer_status" class="mt-4 blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale"><i class="fas fa-plus mr-2"></i> Ekle</button>
                    </form>
                </div>
            </div>

            <!-- Add No Response Status Form -->
            <div class="table-container bg-white custom-shadow mb-12">
                <div class="section-header px-6 py-4">
                    <h2 class="text-xl font-semibold">Yeni Cevapsız Durum Ekle</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Durum Adı</label>
                                <input type="text" name="name" class="form-input" placeholder="Durum adı girin" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Açıklama</label>
                                <input type="text" name="description" class="form-input" placeholder="Durum açıklaması girin">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Renk</label>
                                <div class="color-picker-container">
                                    <input type="color" name="color" id="colorPickerAddNoResponse" class="form-input w-12 h-12 p-1" value="#ffffff" required>
                                    <div id="colorPreviewAddNoResponse" class="color-preview" style="background-color: #ffffff;"></div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_no_response_status" class="mt-4 blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale"><i class="fas fa-plus mr-2"></i> Ekle</button>
                    </form>
                </div>
            </div>

            <!-- Customer Statuses Table -->
            <div class="table-container bg-white custom-shadow mb-12">
                <div class="section-header px-6 py-4">
                    <h2 class="text-xl font-semibold">Müşteri Durumları</h2>
                </div>
                <div class="p-6">
                    <input type="text" id="searchCustomerStatus" onkeyup="searchTable('searchCustomerStatus', 'customerStatusTable')" placeholder="Müşteri Durum Ara..." class="w-full sm:w-80 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none mb-6">
                    <div class="overflow-x-auto">
                        <table id="customerStatusTable" class="w-full text-left">
                            <thead>
                                <tr class="section-header text-white">
                                    <th class="p-4">Sıra</th>
                                    <th class="p-4">Durum Adı</th>
                                    <th class="p-4">Açıklama</th>
                                    <th class="p-4">Renk</th>
                                    <th class="p-4">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody id="sortableCustomer">
                                <?php if (count($customer_statuses) > 0): ?>
                                    <?php foreach ($customer_statuses as $status): ?>
                                        <tr class="sortable-row hover:bg-blue-50 transition" data-id="<?php echo $status['id']; ?>">
                                            <td class="p-4 border-b"><i class="fas fa-grip-vertical text-gray-400 mr-2"></i> <?php echo htmlspecialchars($status['id']); ?></td>
                                            <td class="p-4 border-b"><?php echo htmlspecialchars($status['name']); ?></td>
                                            <td class="p-4 border-b"><?php echo htmlspecialchars($status['description'] ?? ''); ?></td>
                                            <td class="p-4 border-b">
                                                <span class="notification-badge" style="background-color: <?php echo htmlspecialchars($status['color']); ?>; color: <?php echo strtolower($status['color']) == '#ffffff' ? '#000000' : '#1f2937'; ?>;">
                                                    <?php echo htmlspecialchars($status['color']); ?>
                                                </span>
                                            </td>
                                            <td class="p-4 border-b flex gap-2">
                                                <button class="blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale edit-status" 
                                                        data-id="<?php echo $status['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($status['name']); ?>" 
                                                        data-description="<?php echo htmlspecialchars($status['description'] ?? ''); ?>" 
                                                        data-color="<?php echo htmlspecialchars($status['color']); ?>" 
                                                        data-type="customer">
                                                    <i class="fas fa-edit mr-1"></i> Düzenle
                                                </button>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Bu müşteri durumunu silmek istediğinizden emin misiniz?');">
                                                    <input type="hidden" name="status_id" value="<?php echo $status['id']; ?>">
                                                    <button type="submit" name="delete_customer_status" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover-scale"><i class="fas fa-trash mr-1"></i> Sil</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="p-4 text-gray-500">Kayıtlı müşteri durum yok.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <form method="POST" action="" id="reorderCustomerForm" class="mt-4">
                        <input type="hidden" name="order" id="orderCustomerInput">
                        <button type="submit" name="reorder_customer_statuses" class="blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale"><i class="fas fa-save mr-2"></i> Sıralamayı Kaydet</button>
                    </form>
                </div>
            </div>

            <!-- No Response Statuses Table -->
            <div class="table-container bg-white custom-shadow">
                <div class="section-header px-6 py-4">
                    <h2 class="text-xl font-semibold">Cevapsız Durumlar</h2>
                </div>
                <div class="p-6">
                    <input type="text" id="searchNoResponseStatus" onkeyup="searchTable('searchNoResponseStatus', 'noResponseStatusTable')" placeholder="Cevapsız Durum Ara..." class="w-full sm:w-80 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none mb-6">
                    <div class="overflow-x-auto">
                        <table id="noResponseStatusTable" class="w-full text-left">
                            <thead>
                                <tr class="section-header text-white">
                                    <th class="p-4">Sıra</th>
                                    <th class="p-4">Durum Adı</th>
                                    <th class="p-4">Açıklama</th>
                                    <th class="p-4">Renk</th>
                                    <th class="p-4">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody id="sortableNoResponse">
                                <?php if (count($no_response_statuses) > 0): ?>
                                    <?php foreach ($no_response_statuses as $status): ?>
                                        <tr class="sortable-row hover:bg-blue-50 transition" data-id="<?php echo $status['id']; ?>">
                                            <td class="p-4 border-b"><i class="fas fa-grip-vertical text-gray-400 mr-2"></i> <?php echo htmlspecialchars($status['id']); ?></td>
                                            <td class="p-4 border-b"><?php echo htmlspecialchars($status['name']); ?></td>
                                            <td class="p-4 border-b"><?php echo htmlspecialchars($status['description'] ?? ''); ?></td>
                                            <td class="p-4 border-b">
                                                <span class="notification-badge" style="background-color: <?php echo htmlspecialchars($status['color']); ?>; color: <?php echo strtolower($status['color']) == '#ffffff' ? '#000000' : '#1f2937'; ?>;">
                                                    <?php echo htmlspecialchars($status['color']); ?>
                                                </span>
                                            </td>
                                            <td class="p-4 border-b flex gap-2">
                                                <button class="blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale edit-status" 
                                                        data-id="<?php echo $status['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($status['name']); ?>" 
                                                        data-description="<?php echo htmlspecialchars($status['description'] ?? ''); ?>" 
                                                        data-color="<?php echo htmlspecialchars($status['color']); ?>" 
                                                        data-type="no_response">
                                                    <i class="fas fa-edit mr-1"></i> Düzenle
                                                </button>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Bu cevapsız durumunu silmek istediğinizden emin misiniz?');">
                                                    <input type="hidden" name="status_id" value="<?php echo $status['id']; ?>">
                                                    <button type="submit" name="delete_no_response_status" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover-scale"><i class="fas fa-trash mr-1"></i> Sil</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="p-4 text-gray-500">Kayıtlı cevapsız durum yok.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <form method="POST" action="" id="reorderNoResponseForm" class="mt-4">
                        <input type="hidden" name="order" id="orderNoResponseInput">
                        <button type="submit" name="reorder_no_response_statuses" class="blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale"><i class="fas fa-save mr-2"></i> Sıralamayı Kaydet</button>
                    </form>
                </div>
            </div>

            <!-- Edit Status Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <h3 class="text-xl font-bold gradient-text mb-4" id="editModalTitle">Durumu Düzenle</h3>
                    <form method="POST" action="" id="editForm">
                        <input type="hidden" name="status_id" id="editStatusId">
                        <input type="hidden" name="type" id="editStatusType">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Durum Adı</label>
                                <input type="text" name="name" id="editName" class="form-input" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Açıklama</label>
                                <input type="text" name="description" id="editDescription" class="form-input" placeholder="Durum açıklaması girin">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Renk</label>
                                <div class="color-picker-container">
                                    <input type="color" name="color" id="editColorPicker" class="form-input w-12 h-12 p-1" required>
                                    <div id="editColorPreview" class="color-preview"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex space-x-4">
                            <button type="submit" name="" id="editSubmitButton" class="blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale"><i class="fas fa-save mr-1"></i> Kaydet</button>
                            <button type="button" class="bg-gray-400 text-white px-4 py-2 rounded-lg font-medium hover-scale" onclick="closeModal()">İptal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Search function for tables
        function searchTable(inputId, tableId) {
            const input = document.getElementById(inputId), filter = input.value.toUpperCase();
            const table = document.getElementById(tableId), tr = table.getElementsByTagName("tr");
            for (let i = 1; i < tr.length; i++) {
                tr[i].style.display = "none";
                const td = tr[i].getElementsByTagName("td");
                for (let j = 0; j < td.length; j++) {
                    if (td[j] && td[j].innerHTML.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        break;
                    }
                }
            }
        }

        // Show Toast Messages
        <?php if (isset($message)): ?>
            Toastify({
                text: "<?php echo $message; ?>",
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#10B981'
            }).showToast();
        <?php endif; ?>
        <?php if (isset($error)): ?>
            Toastify({
                text: "<?php echo $error; ?>",
                duration: 5000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#EF4444'
            }).showToast();
        <?php endif; ?>

        // Edit Status Modal
        $('.edit-status').on('click', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const description = $(this).data('description');
            const color = $(this).data('color');
            const type = $(this).data('type');

            $('#editStatusId').val(id);
            $('#editName').val(name);
            $('#editDescription').val(description);
            $('#editColorPicker').val(color);
            $('#editColorPreview').css('background-color', color);
            $('#editStatusType').val(type);
            $('#editModalTitle').text(type === 'customer' ? 'Müşteri Durumu Düzenle' : 'Cevapsız Durumu Düzenle');
            $('#editSubmitButton').attr('name', type === 'customer' ? 'update_customer_status' : 'update_no_response_status');
            $('#editModal').css('display', 'flex');
        });

        function closeModal() {
            $('#editModal').css('display', 'none');
        }

        // Color Preview for Add Forms
        $('#colorPickerAddCustomer').on('input', function() {
            $('#colorPreviewAddCustomer').css('background-color', this.value);
        });
        $('#colorPickerAddNoResponse').on('input', function() {
            $('#colorPreviewAddNoResponse').css('background-color', this.value);
        });

        // Color Preview for Edit Modal
        $('#editColorPicker').on('input', function() {
            $('#editColorPreview').css('background-color', this.value);
        });

        // Initialize Sortable for Customer Statuses
        new Sortable(document.getElementById('sortableCustomer'), {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                const rows = document.querySelectorAll('#sortableCustomer .sortable-row');
                const order = Array.from(rows).map(row => row.getAttribute('data-id'));
                document.getElementById('orderCustomerInput').value = JSON.stringify(order);
            }
        });

        // Initialize Sortable for No Response Statuses
        new Sortable(document.getElementById('sortableNoResponse'), {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                const rows = document.querySelectorAll('#sortableNoResponse .sortable-row');
                const order = Array.from(rows).map(row => row.getAttribute('data-id'));
                document.getElementById('orderNoResponseInput').value = JSON.stringify(order);
            }
        });
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>