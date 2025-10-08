<?php
session_start();
include "db.php";
include "notification_helper.php";

if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// إحصاءات
$sql_customers = "SELECT COUNT(*) AS total_customers FROM customers WHERE assigned_staff_id=?";
$stmt_customers = $conn->prepare($sql_customers);
$stmt_customers->bind_param('i', $staff_id);
$stmt_customers->execute();
$total_customers = $stmt_customers->get_result()->fetch_assoc()['total_customers'] ?? 0;
$stmt_customers->close();

// استعلام الرسائل غير المقروءة
$sql_messages = "SELECT COUNT(*) AS total_messages 
                 FROM messages 
                 WHERE receiver_type='staff' AND receiver_id=? AND status=0";
$stmt_messages = $conn->prepare($sql_messages);
$stmt_messages->bind_param('i', $staff_id);
$stmt_messages->execute();
$total_messages = $stmt_messages->get_result()->fetch_assoc()['total_messages'] ?? 0;
$stmt_messages->close();

// جلب آخر 5 رسائل
$sql_last_messages = "SELECT * FROM messages 
                      WHERE (sender_type='staff' AND sender_id=?) 
                         OR (receiver_type='staff' AND receiver_id=?)
                      ORDER BY created_at DESC LIMIT 5";
$stmt_last_messages = $conn->prepare($sql_last_messages);
$stmt_last_messages->bind_param('ii', $staff_id, $staff_id);
$stmt_last_messages->execute();
$result_last_messages = $stmt_last_messages->get_result();

// آخر 5 نشاطات من جدول activity_log
$sql_activity = "SELECT action_type, description, created_at 
                 FROM activity_log 
                 WHERE staff_id = ?
                 ORDER BY created_at DESC LIMIT 5";
$stmt_activity = $conn->prepare($sql_activity);
$stmt_activity->bind_param('i', $staff_id);
$stmt_activity->execute();
$result_activity = $stmt_activity->get_result();

// قائمة العملاء
$sql_customers_list = "SELECT * FROM customers WHERE assigned_staff_id=? ORDER BY name ASC";
$stmt_customers_list = $conn->prepare($sql_customers_list);
$stmt_customers_list->bind_param('i', $staff_id);
$stmt_customers_list->execute();
$result_customers_list = $stmt_customers_list->get_result();

// قائمة الإشعارات غير المقروءة
$sql_notifications = "SELECT * FROM notifications WHERE user_type='staff' AND user_id=? AND is_read=0 ORDER BY created_at DESC";
$stmt_notifications = $conn->prepare($sql_notifications);
$stmt_notifications->bind_param('i', $staff_id);
$stmt_notifications->execute();
$result_notifications = $stmt_notifications->get_result();
$notif_count = $result_notifications->num_rows;
?>

<!DOCTYPE html>
<html lang="tr" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Çalışan Paneli</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
body { font-family: 'Inter', sans-serif; background: linear-gradient(to bottom, #F5F7FA, #E5E7EB); }
.card { background-color: #ffffff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15); transition: transform 0.3s ease, box-shadow 0.3s ease; }
.card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(30, 64, 175, 0.2); }
.gradient-text { background: linear-gradient(90deg, #1E40AF, #3B82F6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.status-active { background-color: #D1FAE5; color: #065F46; padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 500; }
.status-inactive { background-color: #FEE2E2; color: #991B1B; padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 500; }
tr.clickable:hover { background-color: #BFDBFE; cursor: pointer; transition: background-color 0.2s ease; }
.navbar { background: linear-gradient(90deg, #1E40AF, #3B82F6); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3); }
.nav-button { transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease; border-radius: 8px; }
.nav-button:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(30, 64, 175, 0.3); background-color: #ffffff; color: #1E40AF; }
.nav-button.active { background-color: #ffffff; color: #1E40AF; }
.table-container { border-radius: 12px; overflow: hidden; border: 1px solid #E2E8F0; }
th, td { border-bottom: 1px solid #E5E7EB; }
th:first-child, td:first-child { border-left: 1px solid #E5E7EB; }
th:last-child, td:last-child { border-right: 1px solid #E5E7EB; }
th { border-top: 1px solid #E5E7EB; }
tr:last-child td { border-bottom: none; }
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
            <a href="staff_dashboard.php" class="nav-button bg-white/10 text-white px-4 py-2 rounded-lg active"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
            <a href="staff_customers.php" class="nav-button bg-white/10 text-white px-4 py-2 rounded-lg"><i class="fas fa-users mr-2"></i>Müşteriler</a>
            <a href="staff_chat.php" class="nav-button bg-white/10 text-white px-4 py-2 rounded-lg relative">
                <i class="fas fa-envelope mr-2"></i>Mesajlar
                <?php if($total_messages > 0): ?>
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
            <?php if($notif_count > 0): ?>
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
            <?php if ($result_notifications->num_rows > 0): ?>
                <?php while ($notif = $result_notifications->fetch_assoc()): ?>
                    <div class="p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                        <p class="text-blue-800"><?php echo htmlspecialchars($notif['message']); ?></p>
                        <p class="text-gray-500 text-sm"><?php echo $notif['created_at']; ?></p>
                        <?php if ($notif['type'] == 'client_assigned' || $notif['type'] == 'client_activity'): ?>
                            <a href="client_profile.php?id=<?php echo $notif['related_id']; ?>" class="text-blue-600 hover:underline text-sm">Müşteri Profiline Git</a>
                        <?php elseif ($notif['type'] == 'new_message'): ?>
                            <a href="staff_chat.php" class="text-blue-600 hover:underline text-sm">Mesaja Git</a>
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

<!-- Main -->
<main class="max-w-7xl mx-auto px-8 py-8 space-y-8">
    <div class="text-center text-blue-800">
        <h2 class="text-4xl font-bold gradient-text mb-2">Panelinize Hoşgeldiniz</h2>
        <p class="text-gray-600">Müşterileri, mesajları ve aktiviteleri kolayca takip edin</p>
    </div>

    <!-- إحصاءات -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="card text-center">
            <i class="fas fa-users text-3xl text-blue-600 mb-2"></i>
            <p class="text-gray-500">Müşteri Sayısı</p>
            <p class="text-3xl font-bold text-blue-700"><?php echo $total_customers; ?></p>
        </div>
        <a href="staff_chat.php" class="card text-center">
            <i class="fas fa-envelope text-3xl text-blue-600 mb-2"></i>
            <p class="text-gray-500">Yeni Mesajlar</p>
            <p class="text-3xl font-bold text-blue-700"><?php echo $total_messages; ?>
                <?php if($total_messages > 0): ?>
                    <span class="relative -top-2 w-6 h-6 bg-red-500 rounded-full inline-flex items-center justify-center text-xs text-white animate-pulse">
                        <?php echo $total_messages; ?>
                    </span>
                <?php endif; ?>
            </p>
        </a>
        <div class="card text-center">
            <i class="fas fa-bell text-3xl text-blue-600 mb-2"></i>
            <p class="text-gray-500">Bildirimler</p>
            <p class="text-3xl font-bold text-blue-700"><?php echo $notif_count; ?></p>
        </div>
    </div>

    <!-- Müşteriler -->
    <div class="card">
        <h3 class="text-xl font-bold gradient-text mb-4 flex items-center"><i class="fas fa-users mr-2"></i> Müşteriler</h3>
        <div class="table-container">
            <table class="w-full text-left text-blue-800">
                <thead class="bg-gradient-to-r from-[#1E40AF] to-[#3B82F6] text-white">
                    <tr>
                        <th class="px-6 py-3">İsim</th>
                        <th class="px-6 py-3">E-posta</th>
                        <th class="px-6 py-3">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($result_customers_list->num_rows > 0): ?>
                        <?php while($row = $result_customers_list->fetch_assoc()): ?>
                            <tr class="clickable" onclick="window.location='client_profile.php?id=<?php echo $row['id']; ?>'">
                                <td class="px-6 py-4"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo ($row['status']=='active') ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-gray-500">Müşteri yok</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Son Aktiviteler -->
    <div class="card">
        <h3 class="text-xl font-bold gradient-text mb-4 flex items-center"><i class="fas fa-history mr-2"></i> Son Aktiviteler</h3>
        <div class="space-y-3">
            <?php if ($result_activity->num_rows > 0): ?>
                <?php while($act = $result_activity->fetch_assoc()): ?>
                    <div class="p-3 bg-blue-50 rounded-lg flex justify-between items-center hover:bg-blue-100 transition">
                        <p class="text-blue-800"><?php echo htmlspecialchars($act['description']); ?></p>
                        <span class="text-gray-500 text-sm"><?php echo $act['created_at']; ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-gray-500">Henüz aktivite yok</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function toggleNotifications() {
    document.getElementById('notificationsModal').classList.toggle('hidden');
    document.getElementById('notificationsModal').classList.toggle('flex');
}
</script>
</body>
</html>
<?php
// إغلاق الاستعلامات
$stmt_last_messages->close();
$stmt_activity->close();
$stmt_customers_list->close();
$stmt_notifications->close();
?>