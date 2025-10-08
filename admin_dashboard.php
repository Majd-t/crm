<?php
session_start();
include 'db.php';
include 'notification_helper.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
$admin_name = $_SESSION['admin_name'];
@$admin_id = $_SESSION['admin_id'];

// Fetch statistics
$clients_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM customers"));
$staff_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM staff"));
$messages_count_stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type='admin' AND receiver_id=? AND status=0");
$messages_count_stmt->bind_param('i', $admin_id);
$messages_count_stmt->execute();
$messages_count_stmt->bind_result($messages_count);
$messages_count_stmt->fetch();
$messages_count_stmt->close();

// Customer Status distribution
$sql_statuses = "SELECT id, name, color FROM customer_statuses WHERE is_active = 1";
$statuses = mysqli_query($conn, $sql_statuses)->fetch_all(MYSQLI_ASSOC);
$status_counts = [];
foreach ($statuses as $status) {
    $sql_status_count = "SELECT COUNT(*) AS count FROM customers WHERE status_id = {$status['id']}";
    $status_counts[$status['id']] = mysqli_query($conn, $sql_status_count)->fetch_assoc()['count'] ?? 0;
}

// No Response Status distribution
$sql_no_response_statuses = "SELECT id, name, color FROM no_response_statuses WHERE is_active = 1";
$no_response_statuses = mysqli_query($conn, $sql_no_response_statuses)->fetch_all(MYSQLI_ASSOC);
$no_response_status_counts = [];
foreach ($no_response_statuses as $status) {
    $sql_no_response_count = "SELECT COUNT(*) AS count FROM customers WHERE no_response_status_id = {$status['id']}";
    $no_response_status_counts[$status['id']] = mysqli_query($conn, $sql_no_response_count)->fetch_assoc()['count'] ?? 0;
}

// Recent customers
$sql_recent_customers = "SELECT c.id, c.name, c.email, cs.name AS status_name, cs.color 
                        FROM customers c 
                        LEFT JOIN customer_statuses cs ON c.status_id = cs.id 
                        ORDER BY c.created_at DESC LIMIT 5";
$recent_customers = mysqli_query($conn, $sql_recent_customers)->fetch_all(MYSQLI_ASSOC);

// Notifications
$notifications_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_type='admin' AND user_id=? AND is_read=0 ORDER BY created_at DESC");
$notifications_stmt->bind_param('i', $admin_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();
$notif_count = $notifications_result->num_rows;
?>

<!DOCTYPE html>
<html lang="tr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
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
        .card { transition: all 0.3s ease; border-radius: 12px; height: 100%; display: flex; flex-direction: column; justify-content: center; }
        .card:hover { transform: translateY(-4px); }
        .notification-badge { background-color: #EF4444; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 9999px; }
        table { border-collapse: separate; border-spacing: 0; }
        th, td { border-bottom: 1px solid #E5E7EB; }
        th:first-child, td:first-child { border-left: 1px solid #E5E7EB; }
        th:last-child, td:last-child { border-right: 1px solid #E5E7EB; }
        th { border-top: 1px solid #E5E7EB; border-radius: 0; }
        tr:last-child td { border-bottom: none; }
        .table-container { border-radius: 12px; overflow: hidden; }
        .status-badge { 
            padding: 0.5rem 1rem; 
            border-radius: 9999px; 
            font-weight: 500; 
            display: inline-block; 
        }
        .chart-card {
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #E2E8F0;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15);
            transition: all 0.3s ease;
        }
        .chart-card:hover {
            box-shadow: 0 12px 32px rgba(30, 64, 175, 0.2);
        }
        .chart-title {
            font-size: 20px;
            font-weight: 600;
            color: white;
            margin-bottom: 16px;
            background: linear-gradient(to right, #edd7d7ff, #3B82F6);
            padding: 8px 16px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .chart-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .chart-canvas {
            width: 250px !important;
            height: 250px !important;
            max-width: 250px;
            max-height: 250px;
        }
        .chart-legend {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            width: 100%;
        }
        .legend-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px;
            border-radius: 12px;
            background: #F8FAFC;
            transition: background 0.2s ease, transform 0.2s ease;
            text-align: center;
            border: 1px solid #E2E8F0;
        }
        .legend-item:hover {
            background: #BFDBFE;
            transform: translateY(-2px);
        }
        .legend-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin-bottom: 12px;
        }
        .count-badge {
            background-color: #E5E7EB;
            color: #1E293B;
            padding: 4px 12px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 14px;
            margin-top: 4px;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .chart-container {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
    <script>
        function searchTable(inputId, tableId) {
            var input = document.getElementById(inputId), filter = input.value.toUpperCase();
            var table = document.getElementById(tableId), tr = table.getElementsByTagName("tr");
            for (var i = 1; i < tr.length; i++) {
                tr[i].style.display = "none";
                var td = tr[i].getElementsByTagName("td");
                for (var j = 0; j < td.length; j++) {
                    if (td[j] && td[j].innerHTML.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        break;
                    }
                }
            }
        }

        function setInactiveUser(id, type) {
            if (confirm("Bu kullanıcıyı devre dışı bırakmak istiyor musunuz?")) {
                let days = prompt("Kaç gün devre dışı kalacak?", "1");
                if (days != null && !isNaN(days)) {
                    window.location.href = "toggle_user.php?id=" + id + "&type=" + type + "&days=" + days;
                }
            }
        }

        function assignLeads(staff_id) {
            window.location.href = "assign.php?staff_id=" + staff_id;
        }

        function toggleNotifications() {
            document.getElementById('notificationsModal').classList.toggle('hidden');
            document.getElementById('notificationsModal').classList.toggle('flex');
            if (!$('#notificationsModal').hasClass('hidden')) {
                loadNotifications();
            }
        }

        function loadNotifications() {
            $.ajax({
                url: 'fetch_notifications.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#notificationList').html(response.list);
                },
                error: function(xhr, status, error) {
                    console.error('Error loading notifications:', error);
                }
            });
        }
    </script>
</head>
<body class="min-h-screen">
    <div class="flex">
        <!-- Sidebar -->
        <div class="sidebar w-64 h-screen fixed top-0 left-0 p-6 text-white">
            <h2 class="text-2xl font-bold text-center mb-10"><i class="fas fa-user-shield mr-2"></i> <?php echo htmlspecialchars($admin_name); ?></h2>
            <nav class="space-y-3">
                <a href="admin_dashboard.php" class="block px-4 py-3 rounded-lg text-white bg-white/10"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
                <a href="admin_statuses.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-tags mr-2"></i> Durum Yönetimi</a>
                <a href="ai_analysis.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-brain"></i> AI Analizi</a>


                <a href="assign.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-random mr-2"></i> Müşteri Dağıtımı</a>
                <a href="admin_chat.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-envelope mr-2"></i> Mesajlar</a>
                <a href="logout.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-sign-out-alt mr-2"></i> Çıkış Yap</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="ml-64 p-8 w-full max-w-7xl mx-auto">
            <h1 class="text-4xl font-bold text-gray-900 mb-10 flex items-center">
                <i class="fas fa-tachometer-alt mr-2"></i> Admin Paneli
                <button onclick="toggleNotifications()" class="ml-auto blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale relative">
                    <i class="fas fa-bell mr-2"></i> Bildirimler
                    <?php if ($notif_count > 0): ?>
                        <span class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-xs animate-pulse">
                            <?php echo $notif_count; ?>
                        </span>
                    <?php endif; ?>
                </button>
            </h1>

            <!-- Notifications Modal -->
            <div id="notificationsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center z-50">
                <div class="bg-white p-6 rounded-lg w-full max-w-sm">
                    <h3 class="text-xl font-bold gradient-text mb-4">İhbarlar</h3>
                    <div id="notificationList" class="space-y-3 max-h-64 overflow-y-auto">
                        <?php if ($notifications_result->num_rows > 0): ?>
                            <?php while ($notif = $notifications_result->fetch_assoc()): ?>
                                <div class="p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                                    <p class="text-blue-800"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <p class="text-gray-500 text-sm"><?php echo $notif['created_at']; ?></p>
                                    <?php if ($notif['type'] == 'new_client'): ?>
                                        <a href="client_profile.php?id=<?php echo $notif['related_id']; ?>" class="text-blue-600 hover:underline text-sm">Müşteri Profiline Git</a>
                                    <?php elseif ($notif['type'] == 'new_message'): ?>
                                        <a href="admin_chat.php" class="text-blue-600 hover:underline text-sm">Mesaja Git</a>
                                    <?php elseif ($notif['type'] == 'client_activity'): ?>
                                        <a href="client_profile.php?id=<?php echo $notif['related_id']; ?>" class="text-blue-600 hover:underline text-sm">Müşteri Profiline Git</a>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                            <form method="POST" action="mark_notifications_read.php">
                                <button type="submit" name="mark_all_read" class="bg-blue-600 text-white px-4 py-2 rounded-lg mt-4 hover:bg-blue-700">Hepsini Okundu İşaretle</button>
                            </form>
                        <?php else: ?>
                            <p class="text-gray-500">Henüz bildirim yok.</p>
                        <?php endif; ?>
                    </div>
                    <button onclick="toggleNotifications()" class="bg-gray-400 text-white px-4 py-2 rounded-lg mt-4 hover:bg-gray-500">Kapat</button>
                </div>
            </div>

            <!-- Add Buttons -->
            <div class="flex gap-4 mb-10">
                <a href="add_user.php"><button class="blue-button text-white px-6 py-3 rounded-lg font-medium hover-scale"><i class="fas fa-user-plus mr-2"></i> Yeni Müşteri Ekle</button></a>
                <a href="add_staff.php"><button class="blue-button text-white px-6 py-3 rounded-lg font-medium hover-scale"><i class="fas fa-user-tie mr-2"></i> Yeni Çalışan Ekle</button></a>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12" id="stats">
                <div class="card bg-white custom-shadow p-6 text-center">
                    <i class="fas fa-users text-3xl text-blue-600 mb-2"></i>
                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $clients_count; ?></h3>
                    <p class="text-gray-600 text-sm">Toplam Müşteri</p>
                </div>
                <div class="card bg-white custom-shadow p-6 text-center">
                    <i class="fas fa-user-tie text-3xl text-blue-600 mb-2"></i>
                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $staff_count; ?></h3>
                    <p class="text-gray-600 text-sm">Toplam Çalışan</p>
                </div>
                <div class="card bg-white custom-shadow p-6 text-center">
                    <i class="fas fa-cogs text-3xl text-blue-600 mb-2"></i>
                    <h3 class="text-3xl font-bold text-gray-900"><?php echo "2"; ?></h3>
                    <p class="text-gray-600 text-sm">Toplam Hizmet</p>
                </div>
                <a href="admin_chat.php" class="card bg-white custom-shadow p-6 text-center hover-scale">
                    <i class="fas fa-envelope text-3xl text-blue-600 mb-2"></i>
                    <h3 class="text-3xl font-bold text-gray-900"><?php echo $messages_count; ?></h3>
                    <p class="text-gray-600 text-sm">Yeni Mesajlar 
                        <?php if ($messages_count > 0): ?>
                            <span class="notification-badge"><?php echo $messages_count; ?></span>
                        <?php endif; ?>
                    </p>
                </a>
            </div>

            <!-- Chart Section -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-title">Durum Dağılımı</div>
                    <div class="chart-container">
                        <canvas class="chart-canvas" id="statusChart"></canvas>
                        <div class="chart-legend">
                            <?php foreach ($statuses as $status): ?>
                                <div class="legend-item">
                                    <div class="legend-icon" style="background-color: <?php echo htmlspecialchars($status['color']); ?>">
                                        <i class="fas fa-square-full"></i>
                                    </div>
                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($status['name']); ?></span>
                                    <span class="count-badge"><?php echo $status_counts[$status['id']]; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-title">Cevapsız Durum Dağılımı</div>
                    <div class="chart-container">
                        <canvas class="chart-canvas" id="noResponseStatusChart"></canvas>
                        <div class="chart-legend">
                            <?php foreach ($no_response_statuses as $status): ?>
                                <div class="legend-item">
                                    <div class="legend-icon" style="background-color: <?php echo htmlspecialchars($status['color']); ?>">
                                        <i class="fas fa-circle"></i>
                                    </div>
                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($status['name']); ?></span>
                                    <span class="count-badge"><?php echo $no_response_status_counts[$status['id']]; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Customers -->
            <div class="table-container bg-white custom-shadow mb-12">
                <div class="section-header px-6 py-4">
                    <h2 class="text-xl font-semibold">Son Eklenen Müşteriler</h2>
                </div>
                <div class="p-6">
                    <input type="text" id="searchCustomers" onkeyup="searchTable('searchCustomers','customerTable')" placeholder="Müşteri Ara..." class="w-full sm:w-80 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none mb-6">
                    <div class="overflow-x-auto">
                        <table id="customerTable" class="w-full text-left">
                            <thead>
                                <tr class="section-header text-white">
                                    <th class="p-4">İsim</th>
                                    <th class="p-4">E-posta</th>
                                    <th class="p-4">Durum</th>
                                    <th class="p-4">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_customers) > 0): ?>
                                    <?php foreach ($recent_customers as $customer): ?>
                                        <tr class="hover:bg-blue-50 transition" onclick="window.location='client_profile.php?id=<?php echo $customer['id']; ?>';">
                                            <td class="p-4 border-b flex items-center">
                                                <i class="fas fa-user-circle mr-2 text-blue-600"></i>
                                                <?php echo htmlspecialchars($customer['name']); ?>
                                            </td>
                                            <td class="p-4 border-b"><?php echo htmlspecialchars($customer['email']); ?></td>
                                            <td class="p-4 border-b">
                                                <span class="status-badge" style="background-color: <?php echo htmlspecialchars($customer['color']); ?>; color: <?php echo strtolower($customer['color']) == '#ffffff' ? '#000000' : '#1f2937'; ?>;">
                                                    <?php echo htmlspecialchars($customer['status_name']); ?>
                                                </span>
                                            </td>
                                            <td class="p-4 border-b">
                                                <a href="client_profile.php?id=<?php echo $customer['id']; ?>" class="blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale"><i class="fas fa-eye mr-1"></i> Profili Görüntüle</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="p-4 text-gray-500">Kayıtlı müşteri yok.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Staff Section -->
            <div class="table-container bg-white custom-shadow mb-12">
                <div class="section-header px-6 py-4">
                    <h2 class="text-xl font-semibold" id="staff">Çalışan Listesi</h2>
                </div>
                <div class="p-6">
                    <input type="text" id="searchStaff" onkeyup="searchTable('searchStaff','staffTable')" placeholder="Çalışan Ara..." class="w-full sm:w-80 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none mb-6">
                    <div class="overflow-x-auto">
                        <table id="staffTable" class="w-full text-left">
                            <thead>
                                <tr class="section-header text-white">
                                    <th class="p-4">ID</th>
                                    <th class="p-4">İsim</th>
                                    <th class="p-4">E-posta</th>
                                    <th class="p-4">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $staff_result = mysqli_query($conn, "SELECT * FROM staff ORDER BY created_at DESC");
                                if (mysqli_num_rows($staff_result) > 0) {
                                    while ($row = mysqli_fetch_assoc($staff_result)) {
                                        $staff_name = isset($row['name']) ? $row['name'] : $row['username'];
                                        $account_status = isset($row['status']) ? $row['status'] : $row['account_status'];
                                        echo "<tr class='hover:bg-blue-50 transition'>
                                            <td class='p-4 border-b'>" . htmlspecialchars($row['id']) . "</td>
                                            <td class='p-4 border-b'>" . htmlspecialchars($staff_name) . "</td>
                                            <td class='p-4 border-b'>" . htmlspecialchars($row['email']) . "</td>
                                            <td class='p-4 border-b flex gap-2'>
                                                <a href='edit_staff.php?id=" . $row['id'] . "'><button class='blue-button text-white px-4 py-2 rounded-lg font-medium hover-scale'><i class='fas fa-edit mr-1'></i> Düzenle</button></a>
                                                <a href='delete_staff.php?id=" . $row['id'] . "' onclick=\"return confirm('Bu çalışanı silmek istediğinizden emin misiniz?')\"><button class='bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover-scale'><i class='fas fa-trash mr-1'></i> Sil</button></a>
                                                <a href='#' onclick='setInactiveUser(" . $row['id'] . ",\"staff\")'><button class='" . ($account_status == "active" ? "bg-red-600" : "bg-green-600") . " text-white px-4 py-2 rounded-lg font-medium hover-scale'><i class='fas " . ($account_status == "active" ? "fa-ban" : "fa-check") . " mr-1'></i> " . ($account_status == "active" ? "Devre Dışı" : "Etkinleştir") . "</button></a>
                                                <button onclick='assignLeads(" . $row['id'] . ")' class='bg-yellow-500 text-white px-4 py-2 rounded-lg font-medium hover-scale'><i class='fas fa-random mr-1'></i> Lead Ata</button>
                                            </td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='p-4 text-gray-500'>Kayıtlı çalışan yok.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.js"></script>
    <script>
        // Initialize Charts
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($statuses, 'name')) . "'"; ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_values($status_counts)); ?>],
                    backgroundColor: [<?php echo "'" . implode("','", array_column($statuses, 'color')) . "'"; ?>],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } }
            }
        });

        const noResponseCtx = document.getElementById('noResponseStatusChart').getContext('2d');
        new Chart(noResponseCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($no_response_statuses, 'name')) . "'"; ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_values($no_response_status_counts)); ?>],
                    backgroundColor: [<?php echo "'" . implode("','", array_column($no_response_statuses, 'color')) . "'"; ?>],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } }
            }
        });

        // Real-time Notifications
        setInterval(function() {
            $.ajax({
                url: 'check_new_customers.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.new_customers && response.new_customers.length > 0) {
                        response.new_customers.forEach(function(customer) {
                            Toastify({
                                text: `Yeni müşteri eklendi: ${customer.name}`,
                                duration: 5000,
                                destination: `client_profile.php?id=${customer.id}`,
                                newWindow: false,
                                close: true,
                                gravity: 'top',
                                position: 'right',
                                backgroundColor: '#3B82F6'
                            }).showToast();
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking new customers:', error);
                }
            });
        }, 10000);

        $(document).ready(function() {
            loadNotifications();
        });
    </script>
</body>
</html>
<?php
$notifications_stmt->close();
mysqli_close($conn);
?>