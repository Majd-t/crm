<?php
session_start();
include "db.php";

if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// Fetch active customer statuses
$sql_statuses = "SELECT id, name, color FROM customer_statuses WHERE is_active = 1";
$stmt_statuses = $conn->prepare($sql_statuses);
$stmt_statuses->execute();
$statuses = $stmt_statuses->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_statuses->close();

// Fetch active no response statuses
$sql_no_response_statuses = "SELECT id, name, color FROM no_response_statuses WHERE is_active = 1";
$stmt_no_response_statuses = $conn->prepare($sql_no_response_statuses);
$stmt_no_response_statuses->execute();
$no_response_statuses = $stmt_no_response_statuses->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_no_response_statuses->close();

// Fetch statistics
$sql_total_customers = "SELECT COUNT(*) AS total_customers FROM customers WHERE assigned_staff_id = ?";
$stmt_total_customers = $conn->prepare($sql_total_customers);
$stmt_total_customers->bind_param('i', $staff_id);
$stmt_total_customers->execute();
$total_customers = $stmt_total_customers->get_result()->fetch_assoc()['total_customers'] ?? 0;
$stmt_total_customers->close();

// Customer status counts
$status_counts = [];
foreach ($statuses as $status) {
    $sql_status_count = "SELECT COUNT(*) AS count FROM customers WHERE assigned_staff_id = ? AND status_id = ?";
    $stmt_status_count = $conn->prepare($sql_status_count);
    $stmt_status_count->bind_param('ii', $staff_id, $status['id']);
    $stmt_status_count->execute();
    $status_counts[$status['id']] = $stmt_status_count->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_status_count->close();
}

// No response status counts
$no_response_status_counts = [];
$no_response_status_counts[0] = 0;
foreach ($no_response_statuses as $status) {
    $sql_no_response_count = "SELECT COUNT(*) AS count FROM customers WHERE assigned_staff_id = ? AND no_response_status_id = ?";
    $stmt_no_response_count = $conn->prepare($sql_no_response_count);
    $stmt_no_response_count->bind_param('ii', $staff_id, $status['id']);
    $stmt_no_response_count->execute();
    $no_response_status_counts[$status['id']] = $stmt_no_response_count->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_no_response_count->close();
}

$sql_no_response_null = "SELECT COUNT(*) AS count FROM customers WHERE assigned_staff_id = ? AND no_response_status_id IS NULL";
$stmt_no_response_null = $conn->prepare($sql_no_response_null);
$stmt_no_response_null->bind_param('i', $staff_id);
$stmt_no_response_null->execute();
$no_response_status_counts[0] = $stmt_no_response_null->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_no_response_null->close();

// Fetch recent activities
$sql_recent_activities = "SELECT COUNT(*) AS total_activities FROM activity_log WHERE staff_id = ? AND created_at >= NOW() - INTERVAL 7 DAY";
$stmt_recent_activities = $conn->prepare($sql_recent_activities);
$stmt_recent_activities->bind_param('i', $staff_id);
$stmt_recent_activities->execute();
$recent_activities = $stmt_recent_activities->get_result()->fetch_assoc()['total_activities'] ?? 0;
$stmt_recent_activities->close();

// Fetch customers
$sql_customers_list = "SELECT c.id, c.name, c.email, c.status_id, c.no_response_status_id, c.created_at, 
                       cs.name AS status_name, cs.color AS status_color,
                       nrs.name AS no_response_status_name, nrs.color AS no_response_status_color
                       FROM customers c 
                       LEFT JOIN customer_statuses cs ON c.status_id = cs.id 
                       LEFT JOIN no_response_statuses nrs ON c.no_response_status_id = nrs.id 
                       WHERE c.assigned_staff_id = ? 
                       ORDER BY c.name ASC";
$stmt_customers_list = $conn->prepare($sql_customers_list);
$stmt_customers_list->bind_param('i', $staff_id);
$stmt_customers_list->execute();
$result_customers_list = $stmt_customers_list->get_result();

$list_html = '';
$kanban_customer_html = [];
$kanban_no_response_html = [];
while ($row = $result_customers_list->fetch_assoc()) {
    $list_html .= "<tr class='clickable' data-customer-id='{$row['id']}' data-status-id='{$row['status_id']}' data-no-response-id='".($row['no_response_status_id'] ?? 0)."' data-created-at='{$row['created_at']}' onclick=\"window.location='client_profile.php?id={$row['id']}';\">
        <td class='px-4 py-3 flex items-center'>
            <i class='fas fa-user-circle mr-2 text-blue-600'></i>
            " . htmlspecialchars($row['name']) . "
        </td>
        <td class='px-4 py-3'>" . htmlspecialchars($row['email']) . "</td>
        <td class='px-4 py-3'>
            <span class='status-badge' style='background-color: " . htmlspecialchars($row['status_color']) . "; color: " . (strtolower($row['status_color']) == '#ffffff' ? '#000000' : '#1f2937') . ";'>" . htmlspecialchars($row['status_name']) . "</span>
            <span class='status-badge mt-2' style='background-color: " . htmlspecialchars($row['no_response_status_color'] ?: '#d1d5db') . "; color: " . (strtolower($row['no_response_status_color'] ?: '#d1d5db') == '#ffffff' ? '#000000' : '#1f2937') . ";'>" . htmlspecialchars($row['no_response_status_name'] ?: 'Belirtilmemiş') . "</span>
        </td>
        <td class='px-4 py-3'>
            <a href='client_profile.php?id={$row['id']}' class='action-button'>Profili Görüntüle</a>
        </td>
    </tr>";

    if (!isset($kanban_customer_html[$row['status_id']])) {
        $kanban_customer_html[$row['status_id']] = '';
    }
    $kanban_customer_html[$row['status_id']] .= "<div class='kanban-card' data-customer-id='{$row['id']}' data-status-id='{$row['status_id']}' data-no-response-id='".($row['no_response_status_id'] ?? 0)."' data-created-at='{$row['created_at']}'>
        <p class='font-bold text-gray-800'>" . htmlspecialchars($row['name']) . "</p>
        <p class='text-sm text-gray-500'>" . htmlspecialchars($row['email']) . "</p>
        <a href='client_profile.php?id={$row['id']}' class='text-blue-600 hover:underline'>Profili Görüntüle</a>
    </div>";

    $no_response_id = $row['no_response_status_id'] ?? 0;
    if (!isset($kanban_no_response_html[$no_response_id])) {
        $kanban_no_response_html[$no_response_id] = '';
    }
    $kanban_no_response_html[$no_response_id] .= "<div class='kanban-card' data-customer-id='{$row['id']}' data-status-id='{$row['status_id']}' data-no-response-id='".($row['no_response_status_id'] ?? 0)."' data-created-at='{$row['created_at']}'>
        <p class='font-bold text-gray-800'>" . htmlspecialchars($row['name']) . "</p>
        <p class='text-sm text-gray-500'>" . htmlspecialchars($row['email']) . "</p>
        <a href='client_profile.php?id={$row['id']}' class='text-blue-600 hover:underline'>Profili Görüntüle</a>
    </div>";
}
?>

<!DOCTYPE html>
<html lang="tr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Yönetimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .navbar {
            background: linear-gradient(90deg, #1E40AF, #3B82F6);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }
        .nav-button {
            transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
            border-radius: 8px;
        }
        .nav-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(30, 64, 175, 0.3);
            background-color: #ffffff;
            color: #1E40AF;
        }
        .nav-button.active {
            background-color: #ffffff;
            color: #1E40AF;
        }
        .kanban-container { 
            display: flex; 
            flex-wrap: nowrap; 
            overflow-x: auto; 
            gap: 1rem; 
            padding-bottom: 1rem; 
        }
        .kanban-column { 
            flex: 0 0 300px; 
            min-width: 300px; 
            background-color: #ffffff; 
            border-radius: 0.75rem; 
            padding: 1.5rem; 
            box-shadow: 0 6px 20px rgba(0,0,0,0.15); 
            border: 1px solid #e5e7eb; 
            transition: transform 0.3s ease; 
        }
        .kanban-column:hover { 
            transform: translateY(-5px); 
        }
        .kanban-column-header { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 0.5rem; 
            border-radius: 0.5rem; 
            margin-bottom: 1rem; 
        }
        .kanban-cards { 
            max-height: 500px; 
            overflow-y: auto; 
            scrollbar-width: thin; 
            scrollbar-color: #3B82F6 #e5e7eb; 
        }
        .kanban-card { 
            background-color: #f9fafb; 
            border-radius: 0.5rem; 
            padding: 1rem; 
            margin-bottom: 0.75rem; 
            box-shadow: 0 3px 10px rgba(0,0,0,0.1); 
            transition: transform 0.2s, box-shadow 0.2s; 
            border-left: 4px solid transparent; 
        }
        .kanban-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 20px rgba(0,0,0,0.15); 
            border-left-color: #3B82F6; 
        }
        .gradient-text { 
            background: linear-gradient(90deg, #3B82F6, #06B6D4); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .card { 
            background-color: #ffffff; 
            border-radius: 1rem; 
            padding: 1.5rem; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        .table-container { 
            background: linear-gradient(180deg, #ffffff, #f0f7ff); 
            border-radius: 1rem; 
            box-shadow: 0 6px 20px rgba(0,0,0,0.1); 
            overflow: hidden; 
        }
        table { 
            border-collapse: separate; 
            border-spacing: 0; 
        }
        th { 
            background: linear-gradient(90deg, #3B82F6, #06B6D4); 
            color: white; 
            font-weight: 600; 
            padding: 1rem; 
            text-align: left; 
        }
        th:first-child { border-top-left-radius: 0.5rem; }
        th:last-child { border-top-right-radius: 0.5rem; }
        tr.clickable { 
            transition: background-color 0.3s ease; 
        }
        tr.clickable:hover { 
            background-color: #e0f2fe; 
            cursor: pointer; 
        }
        td { 
            border-bottom: 1px solid #e5e7eb; 
            padding: 1rem; 
        }
        .status-badge { 
            padding: 0.5rem 1rem; 
            border-radius: 9999px; 
            font-weight: 500; 
            display: inline-block; 
        }
        .action-button { 
            background-color: #3B82F6; 
            color: white; 
            padding: 0.5rem 1rem; 
            border-radius: 0.375rem; 
            transition: background-color 0.3s ease; 
        }
        .action-button:hover { 
            background-color: #2563eb; 
        }
        .view-button { 
            padding: 0.5rem 1rem; 
            border-radius: 0.375rem; 
            font-size: 0.875rem; 
        }
        .view-button.active { 
            background-color: #3B82F6; 
            color: white; 
        }
        .status-counter { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            width: 1.75rem; 
            height: 1.75rem; 
            background-color: #3B82F6; 
            color: white; 
            border-radius: 9999px; 
            font-size: 0.875rem; 
            font-weight: 600; 
            margin-left: 0.5rem; 
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: -320px;
            width: 320px;
            height: 100%;
            background: linear-gradient(180deg, #ffffff, #e0f2fe);
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            transition: left 0.3s ease;
            z-index: 100;
            padding: 2rem;
            overflow-y: auto;
            border-right: 1px solid #d1d5db;
        }
        .sidebar.open { left: 0; }
        .close-sidebar {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: #374151;
            font-size: 1.25rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .close-sidebar:hover { color: #EF4444; }
        .filter-button {
            background: linear-gradient(90deg, #3B82F6, #06B6D4);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .filter-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        .filter-input, .filter-select {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background-color: #f9fafb;
            margin-bottom: 1rem;
        }
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .filter-checkbox {
            margin-right: 0.75rem;
            accent-color: #3B82F6;
        }
        .filter-label {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: #374151;
        }
        .filter-section {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1.5rem;
        }
        .filter-section:last-child { border-bottom: none; }
        .filter-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .toggle-sidebar {
            background: linear-gradient(90deg, #3B82F6, #06B6D4);
            color: white;
            padding: 0.75rem;
            border-radius: 0.5rem;
            position: fixed;
            top: 80px;
            left: 10px;
            z-index: 60;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        .toggle-sidebar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        main.sidebar-open {
            margin-left: 320px;
            transition: margin-left 0.3s ease;
        }
        .flatpickr-calendar {
            font-family: 'Inter', sans-serif;
            border-radius: 0.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange {
            background: #3B82F6;
            border-color: #3B82F6;
        }
        .flatpickr-day:hover { background: #e0f2fe; }
        .chart-container { max-width: 200px; margin: 0 auto; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar flex items-center justify-between px-8 py-4 text-white sticky top-0 z-50">
        <div class="flex items-center space-x-4">
            <i class="fas fa-user-tie text-white text-2xl"></i>
            <h1 class="text-2xl font-bold">Çalışan Paneli</h1>
        </div>
        <div class="flex items-center space-x-4">
            <div class="flex space-x-2">
                <a href="staff_dashboard.php" class="nav-button bg-white/10 text-white px-4 py-2 rounded-lg"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
                <a href="staff_customers.php" class="nav-button bg-white text-blue-900 px-4 py-2 rounded-lg active"><i class="fas fa-users mr-2"></i>Müşteriler</a>
                <a href="staff_chat.php" class="nav-button bg-white/10 text-white px-4 py-2 rounded-lg"><i class="fas fa-envelope mr-2"></i>Mesajlar
                    <?php if(isset($total_messages) && $total_messages > 0): ?>
                        <span class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-xs animate-pulse">
                            <?php echo $total_messages; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <button class="nav-button bg-white/10 text-white px-3 py-2 rounded-lg relative" onclick="toggleNotifications()">
                <i class="fas fa-bell text-xl"></i>
                <?php if(isset($notif_count) && $notif_count > 0): ?>
                    <span class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-xs animate-pulse">
                        <?php echo $notif_count; ?>
                    </span>
                <?php endif; ?>
            </button>
            <a href="logout.php" class="nav-button bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"><i class="fas fa-sign-out-alt mr-2"></i>Çıkış Yap</a>
        </div>
    </nav>

    <!-- Notifications Modal -->
    <div id="notificationsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center z-50">
        <div class="bg-white p-6 rounded-lg w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold gradient-text mb-4">İhbarlar</h3>
            <div class="space-y-3 max-h-64 overflow-y-auto">
                <p class="text-gray-500">Henüz bildirim yok.</p>
            </div>
            <button onclick="toggleNotifications()" class="bg-gray-400 text-white px-4 py-2 rounded-lg mt-4 hover:bg-gray-500">Kapat</button>
        </div>
    </div>

    <button class="toggle-sidebar" onclick="toggleSidebar()"><i class="fas fa-filter"></i> </button>
    <div class="sidebar" id="filterSidebar">
        <button class="close-sidebar" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        <h3 class="text-xl font-bold gradient-text mb-6 flex items-center gap-2">
            <i class="fas fa-sliders-h"></i> Gelişmiş Filtreler
        </h3>
        <div class="filter-section">
            <h4><i class="fas fa-search mr-2"></i> Arama</h4>
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="searchInput" class="filter-input pl-10" placeholder="Müşteri adı veya e-posta">
            </div>
        </div>
        <div class="filter-section">
            <h4><i class="fas fa-tags mr-2"></i> Müşteri Durumları</h4>
            <?php foreach ($statuses as $status): ?>
                <label class="filter-label">
                    <input type="checkbox" class="filter-checkbox" name="status_ids[]" value="<?php echo $status['id']; ?>">
                    <span style="color: <?php echo htmlspecialchars($status['color']); ?>;">
                        <?php echo htmlspecialchars($status['name']); ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="filter-section">
            <h4><i class="fas fa-phone-slash mr-2"></i> Cevapsız Durumları</h4>
            <?php foreach ($no_response_statuses as $status): ?>
                <label class="filter-label">
                    <input type="checkbox" class="filter-checkbox" name="no_response_status_ids[]" value="<?php echo $status['id']; ?>">
                    <span style="color: <?php echo htmlspecialchars($status['color']); ?>;">
                        <?php echo htmlspecialchars($status['name']); ?>
                    </span>
                </label>
            <?php endforeach; ?>
            <label class="filter-label">
                <input type="checkbox" class="filter-checkbox" name="no_response_status_ids[]" value="0">
                <span style="color: #d1d5db;">Belirtilmemiş</span>
            </label>
        </div>
        <div class="filter-section">
            <h4><i class="fas fa-calendar-alt mr-2"></i> Tarih Aralığı</h4>
            <input type="text" id="dateRange" class="filter-input" placeholder="Tarih aralığını seç">
        </div>
        <button id="resetFilters" class="filter-button"><i class="fas fa-undo"></i> Filtreleri Sıfırla</button>
    </div>

    <main class="max-w-7xl mx-auto px-8 py-8 space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="card">
                <p class="text-gray-500">Müşteri Sayısı</p>
                <p class="text-3xl font-bold text-blue-700"><?php echo $total_customers; ?></p>
            </div>
            <div class="card">
                <p class="text-gray-500">Müşteri Durum Dağılımı</p>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            <div class="card">
                <p class="text-gray-500">Cevapsız Durum Dağılımı</p>
                <div class="chart-container">
                    <canvas id="noResponseStatusChart"></canvas>
                </div>
            </div>
            <div class="card">
                <p class="text-gray-500">Son Aktiviteler</p>
                <p class="text-3xl font-bold text-blue-700"><?php echo $recent_activities; ?></p>
            </div>
        </div>

        <div class="flex justify-start mb-4 items-center space-x-2">
            <button id="listViewBtn" class="view-button bg-gray-300 text-gray-800 hover:bg-gray-400">Liste Görünümü</button>
            <button id="kanbanViewBtn" class="view-button bg-blue-600 text-white hover:bg-blue-700 active">Kanban Görünümü</button>
        </div>

        <div id="listView" class="table-container">
            <h3 class="text-xl font-bold gradient-text mb-4 flex items-center px-4 pt-4"><i class="fas fa-users mr-2"></i> Müşteriler</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-blue-800">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">İsim</th>
                            <th class="px-4 py-3">E-posta</th>
                            <th class="px-4 py-3">Durumlar</th>
                            <th class="px-4 py-3">Eylemler</th>
                        </tr>
                    </thead>
                    <tbody id="customerTable" class="divide-y divide-gray-200">
                        <?php echo $list_html ?: '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Müşteri yok</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="kanbanView" class="space-y-8 hidden">
            <div>
                <h3 class="text-xl font-bold gradient-text mb-4 flex items-center"><i class="fas fa-tags mr-2"></i> Müşteri Durumları</h3>
                <div class="kanban-container">
                    <?php foreach ($statuses as $status): ?>
                        <div class="kanban-column" data-status-id="<?php echo $status['id']; ?>">
                            <div class="kanban-column-header" style="background: linear-gradient(90deg, <?php echo htmlspecialchars($status['color']); ?>, <?php echo htmlspecialchars($status['color']); ?>80);">
                                <div class="flex items-center">
                                    <i class="fas fa-circle mr-2 text-sm" style="color: <?php echo htmlspecialchars($status['color']); ?>;"></i>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($status['name']); ?></h3>
                                </div>
                                <span class="status-counter" data-status-id="<?php echo $status['id']; ?>"><?php echo $status_counts[$status['id']] ?? 0; ?></span>
                            </div>
                            <div class="kanban-cards space-y-2">
                                <?php echo $kanban_customer_html[$status['id']] ?? ''; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h3 class="text-xl font-bold gradient-text mb-4 flex items-center"><i class="fas fa-phone-slash mr-2"></i> Cevapsız Durumları</h3>
                <div class="kanban-container">
                    <?php foreach ($no_response_statuses as $status): ?>
                        <div class="kanban-column" data-no-response-id="<?php echo $status['id']; ?>">
                            <div class="kanban-column-header" style="background: linear-gradient(90deg, <?php echo htmlspecialchars($status['color']); ?>, <?php echo htmlspecialchars($status['color']); ?>80);">
                                <div class="flex items-center">
                                    <i class="fas fa-circle mr-2 text-sm" style="color: <?php echo htmlspecialchars($status['color']); ?>;"></i>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($status['name']); ?></h3>
                                </div>
                                <span class="status-counter" data-no-response-id="<?php echo $status['id']; ?>"><?php echo $no_response_status_counts[$status['id']] ?? 0; ?></span>
                            </div>
                            <div class="kanban-cards space-y-2">
                                <?php echo $kanban_no_response_html[$status['id']] ?? ''; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="kanban-column" data-no-response-id="0">
                        <div class="kanban-column-header" style="background: linear-gradient(90deg, #d1d5db, #d1d5db80);">
                            <div class="flex items-center">
                                <i class="fas fa-circle mr-2 text-sm" style="color: #d1d5db;"></i>
                                <h3 class="text-lg font-semibold text-gray-800">Belirtilmemiş</h3>
                            </div>
                            <span class="status-counter" data-no-response-id="0"><?php echo $no_response_status_counts[0] ?? 0; ?></span>
                        </div>
                        <div class="kanban-cards space-y-2">
                            <?php echo $kanban_no_response_html[0] ?? ''; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script>
        function toggleNotifications() {
            document.getElementById('notificationsModal').classList.toggle('hidden');
            document.getElementById('notificationsModal').classList.toggle('flex');
        }

        const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);

        // Status and No Response Status ID to index mappings
        const statusIdToIndex = {};
        <?php foreach ($statuses as $index => $status): ?>
            statusIdToIndex[<?php echo $status['id']; ?>] = <?php echo $index; ?>;
        <?php endforeach; ?>

        const noResponseIdToIndex = {};
        <?php foreach ($no_response_statuses as $index => $status): ?>
            noResponseIdToIndex[<?php echo $status['id']; ?>] = <?php echo $index; ?>;
        <?php endforeach; ?>
        noResponseIdToIndex[0] = <?php echo count($no_response_statuses); ?>;

        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "Y-m-d",
            locale: {
                firstDayOfWeek: 1,
                weekdays: { shorthand: ["Paz", "Pzt", "Sal", "Çar", "Per", "Cum", "Cmt"], longhand: ["Pazar", "Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi"] },
                months: { shorthand: ["Oca", "Şub", "Mar", "Nis", "May", "Haz", "Tem", "Ağu", "Eyl", "Eki", "Kas", "Ara"], longhand: ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"] }
            }
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('filterSidebar');
            const main = document.querySelector('main');
            sidebar.classList.toggle('open');
            main.classList.toggle('sidebar-open');
        }

        function setView(view) {
            const listView = document.getElementById('listView');
            const kanbanView = document.getElementById('kanbanView');
            const listViewBtn = document.getElementById('listViewBtn');
            const kanbanViewBtn = document.getElementById('kanbanViewBtn');

            listView.classList.toggle('hidden', view !== 'list');
            kanbanView.classList.toggle('hidden', view !== 'kanban');
            listViewBtn.classList.toggle('active', view === 'list');
            listViewBtn.classList.toggle('bg-blue-600', view === 'list');
            listViewBtn.classList.toggle('text-white', view === 'list');
            listViewBtn.classList.toggle('bg-gray-300', view !== 'list');
            listViewBtn.classList.toggle('text-gray-800', view !== 'list');
            kanbanViewBtn.classList.toggle('active', view === 'kanban');
            kanbanViewBtn.classList.toggle('bg-blue-600', view === 'kanban');
            kanbanViewBtn.classList.toggle('text-white', view === 'kanban');
            kanbanViewBtn.classList.toggle('bg-gray-300', view !== 'kanban');
            kanbanViewBtn.classList.toggle('text-gray-800', view !== 'kanban');

            localStorage.setItem('preferredView', view);
            applyFilters();
        }

        function initSortableCustomerStatuses() {
            document.querySelectorAll('.kanban-column[data-status-id] .kanban-cards').forEach(el => {
                new Sortable(el, {
                    group: 'customer-statuses',
                    animation: 150,
                    onEnd: evt => {
                        const customerId = evt.item.getAttribute('data-customer-id');
                        const oldStatusId = evt.item.getAttribute('data-status-id');
                        const newStatusId = evt.to.parentElement.getAttribute('data-status-id');

                        if (oldStatusId !== newStatusId) {
                            const scrollX = window.scrollX, scrollY = window.scrollY;
                            $.ajax({
                                url: basePath + 'update_status.php',
                                method: 'POST',
                                data: { customer_id: customerId, status_id: newStatusId },
                                dataType: 'json',
                                success: response => {
                                    if (response.success) {
                                        evt.item.setAttribute('data-status-id', newStatusId);
                                        const oldCounter = document.querySelector(`.status-counter[data-status-id="${oldStatusId}"]`);
                                        const newCounter = document.querySelector(`.status-counter[data-status-id="${newStatusId}"]`);
                                        oldCounter.textContent = parseInt(oldCounter.textContent) - 1;
                                        newCounter.textContent = parseInt(newCounter.textContent) + 1;
                                        const chart = Chart.getChart('statusChart');
                                        chart.data.datasets[0].data[statusIdToIndex[oldStatusId]]--;
                                        chart.data.datasets[0].data[statusIdToIndex[newStatusId]]++;
                                        chart.update();
                                        window.scrollTo(scrollX, scrollY);
                                    } else {
                                        console.error('Server error:', response.error);
                                        evt.to.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                                        window.scrollTo(scrollX, scrollY);
                                    }
                                },
                                error: (xhr, status, error) => {
                                    console.error('Ajax error:', status, error);
                                    evt.to.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                                    window.scrollTo(scrollX, scrollY);
                                }
                            });
                        }
                    }
                });
            });
        }

        function initSortableNoResponseStatuses() {
            document.querySelectorAll('.kanban-column[data-no-response-id] .kanban-cards').forEach(el => {
                new Sortable(el, {
                    group: 'no-response-statuses',
                    animation: 150,
                    onEnd: evt => {
                        const customerId = evt.item.getAttribute('data-customer-id');
                        const oldNoResponseId = evt.item.getAttribute('data-no-response-id');
                        const newNoResponseId = evt.to.parentElement.getAttribute('data-no-response-id');

                        if (oldNoResponseId !== newNoResponseId) {
                            const scrollX = window.scrollX, scrollY = window.scrollY;
                            $.ajax({
                                url: basePath + 'update_no_response_status.php',
                                method: 'POST',
                                data: { customer_id: customerId, no_response_status_id: newNoResponseId },
                                dataType: 'json',
                                success: response => {
                                    if (response.success) {
                                        evt.item.setAttribute('data-no-response-id', newNoResponseId);
                                        const oldCounter = document.querySelector(`.status-counter[data-no-response-id="${oldNoResponseId}"]`);
                                        const newCounter = document.querySelector(`.status-counter[data-no-response-id="${newNoResponseId}"]`);
                                        oldCounter.textContent = parseInt(oldCounter.textContent) - 1;
                                        newCounter.textContent = parseInt(newCounter.textContent) + 1;
                                        const chart = Chart.getChart('noResponseStatusChart');
                                        chart.data.datasets[0].data[noResponseIdToIndex[oldNoResponseId]]--;
                                        chart.data.datasets[0].data[noResponseIdToIndex[newNoResponseId]]++;
                                        chart.update();
                                        window.scrollTo(scrollX, scrollY);
                                    } else {
                                        console.error('Server error:', response.error);
                                        evt.to.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                                        window.scrollTo(scrollX, scrollY);
                                    }
                                },
                                error: (xhr, status, error) => {
                                    console.error('Ajax error:', status, error);
                                    evt.to.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                                    window.scrollTo(scrollX, scrollY);
                                }
                            });
                        }
                    }
                });
            });
        }

        function applyFilters() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusIds = Array.from(document.querySelectorAll('input[name="status_ids[]"]:checked')).map(el => el.value);
            const noResponseStatusIds = Array.from(document.querySelectorAll('input[name="no_response_status_ids[]"]:checked')).map(el => el.value);
            const dateRange = document.getElementById('dateRange').value.split(' to ');
            const dateFrom = dateRange[0] || '', dateTo = dateRange[1] || dateRange[0] || '';

            const filterElement = (el, isKanban = false) => {
                const name = (isKanban ? el.querySelector('p:nth-child(1)') : el.querySelector('td:nth-child(1)')).textContent.toLowerCase();
                const email = (isKanban ? el.querySelector('p:nth-child(2)') : el.querySelector('td:nth-child(2)')).textContent.toLowerCase();
                const statusId = el.getAttribute('data-status-id');
                const noResponseId = el.getAttribute('data-no-response-id') || '0';
                const createdAt = el.getAttribute('data-created-at');
                const matchesSearch = searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm);
                const matchesStatus = statusIds.length === 0 || statusIds.includes(statusId);
                const matchesNoResponse = noResponseStatusIds.length === 0 || noResponseStatusIds.includes(noResponseId);
                const matchesDate = (!dateFrom || createdAt >= dateFrom) && (!dateTo || createdAt <= dateTo);
                el.style.display = matchesSearch && matchesStatus && matchesNoResponse && matchesDate ? '' : 'none';
                return matchesSearch && matchesStatus && matchesNoResponse && matchesDate;
            };

            document.querySelectorAll('#customerTable tr').forEach(row => filterElement(row));

            const statusChart = Chart.getChart('statusChart');
            const noResponseChart = Chart.getChart('noResponseStatusChart');

            document.querySelectorAll('.kanban-column[data-status-id]').forEach(column => {
                let visibleCards = 0;
                column.querySelectorAll('.kanban-card').forEach(card => {
                    if (filterElement(card, true)) visibleCards++;
                });
                column.querySelector('.status-counter').textContent = visibleCards;
                if (statusChart) {
                    statusChart.data.datasets[0].data[statusIdToIndex[column.getAttribute('data-status-id')]] = visibleCards;
                }
            });

            document.querySelectorAll('.kanban-column[data-no-response-id]').forEach(column => {
                let visibleCards = 0;
                column.querySelectorAll('.kanban-card').forEach(card => {
                    if (filterElement(card, true)) visibleCards++;
                });
                column.querySelector('.status-counter').textContent = visibleCards;
                if (noResponseChart) {
                    noResponseChart.data.datasets[0].data[noResponseIdToIndex[column.getAttribute('data-no-response-id')]] = visibleCards;
                }
            });

            statusChart?.update();
            noResponseChart?.update();
        }

        document.getElementById('resetFilters').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            document.querySelectorAll('input[name="status_ids[]"]').forEach(el => el.checked = false);
            document.querySelectorAll('input[name="no_response_status_ids[]"]').forEach(el => el.checked = false);
            document.getElementById('dateRange').value = '';
            applyFilters();
        });

        document.getElementById('searchInput').addEventListener('input', applyFilters);
        document.querySelectorAll('input[name="status_ids[]"]').forEach(el => el.addEventListener('change', applyFilters));
        document.querySelectorAll('input[name="no_response_status_ids[]"]').forEach(el => el.addEventListener('change', applyFilters));
        document.getElementById('dateRange').addEventListener('change', applyFilters);

        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: [<?php echo implode(',', array_map(function($s) { return "'".addslashes($s['name'])."'"; }, $statuses)); ?>],
                datasets: [{ data: [<?php echo implode(',', array_values($status_counts)); ?>], backgroundColor: [<?php echo implode(',', array_map(function($s) { return "'".addslashes($s['color'])."'"; }, $statuses)); ?>] }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        const noResponseCtx = document.getElementById('noResponseStatusChart').getContext('2d');
        const noResponseStatusChart = new Chart(noResponseCtx, {
            type: 'pie',
            data: {
                labels: [<?php echo implode(',', array_map(function($s) { return "'".addslashes($s['name'])."'"; }, $no_response_statuses)) . (count($no_response_statuses) > 0 ? ',' : '') . "'Belirtilmemiş'"; ?>],
                datasets: [{ data: [<?php echo implode(',', array_values($no_response_status_counts)) . ',' . ($no_response_status_counts[0] ?? 0); ?>], backgroundColor: [<?php echo implode(',', array_map(function($s) { return "'".addslashes($s['color'])."'"; }, $no_response_statuses)) . (count($no_response_statuses) > 0 ? ',' : '') . "'#d1d5db'"; ?>] }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        document.addEventListener('DOMContentLoaded', () => {
            initSortableCustomerStatuses();
            initSortableNoResponseStatuses();
            setView(localStorage.getItem('preferredView') || 'list');
            document.getElementById('listViewBtn').addEventListener('click', () => setView('list'));
            document.getElementById('kanbanViewBtn').addEventListener('click', () => setView('kanban'));
            applyFilters();
        });
    </script>
</body>
</html>
<?php
$stmt_customers_list->close();
$conn->close();
?>