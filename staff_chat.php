<?php
session_start();
require 'db.php';

if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}

$_SESSION['user_type'] = 'staff';
$_SESSION['user_id'] = $_SESSION['staff_id'];
$currentStaffId = $_SESSION['staff_id'];
$staff_name = isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : 'Personel';

// Fetch all users: staff and admins (excluding self)
$users = [];
try {
    $stmt = $conn->prepare("SELECT id, username, 'staff' AS type FROM staff WHERE id != ? UNION SELECT id, username, 'admin' AS type FROM admin");
    $stmt->bind_param('i', $currentStaffId);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = []; // Fallback to empty array
}

// Fetch unread message counts
$unreadCounts = [];
try {
    $q = $conn->prepare("SELECT sender_type, sender_id, COUNT(*) AS unread_count FROM messages WHERE receiver_type='staff' AND receiver_id=? AND status=0 GROUP BY sender_type, sender_id");
    $q->bind_param('i', $currentStaffId);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = $row['sender_type'] . '_' . $row['sender_id'];
        $unreadCounts[$key] = $row['unread_count'];
    }
    $q->close();
} catch (Exception $e) {
    error_log("Error fetching unread counts: " . $e->getMessage());
}

$selected_user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$selected_user_name = '';
if ($selected_user_type && $selected_user_id) {
    try {
        if ($selected_user_type == 'admin') {
            $nameStmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
        } else {
            $nameStmt = $conn->prepare("SELECT username FROM staff WHERE id = ?");
        }
        $nameStmt->bind_param('i', $selected_user_id);
        $nameStmt->execute();
        $nameRes = $nameStmt->get_result();
        $row = $nameRes->fetch_assoc();
        $selected_user_name = $row ? $row['username'] : 'Bilinmeyen Kullanıcı';
        $nameStmt->close();
    } catch (Exception $e) {
        error_log("Error fetching selected user name: " . $e->getMessage());
        $selected_user_name = 'Bilinmeyen Kullanıcı';
    }
}
?>

<!DOCTYPE html>
<html lang="tr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Sohbet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(to bottom, #F5F7FA, #E5E7EB);
            margin: 0;
            min-height: 100vh;
        }
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
        .main-content {
            padding: 2rem;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        .chat-container {
            width: 100%;
            height: 650px;
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15);
            display: flex;
            overflow: hidden;
        }
        .user-list {
            width: 280px;
            background-color: #f8fafc;
            padding: 1rem;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
            scrollbar-width: none;
        }
        .user-list::-webkit-scrollbar {
            display: none;
        }
        .user-list h3 {
            color: #1E40AF;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 1rem;
        }
        .user-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .user-list li {
            margin-bottom: 0.5rem;
        }
        .user-list a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #1f2937;
            padding: 0.75rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .user-list a:hover, .user-list a.active {
            background-color: #e0f2fe;
            transform: translateY(-2px);
        }
        .user-list .username {
            flex-grow: 1;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .user-list .user-icon {
            margin-right: 0.75rem;
            color: #3B82F6;
        }
        .user-list .unread-badge {
            background-color: #EF4444;
            color: #fff;
            border-radius: 50%;
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .chat-area {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background-color: #ffffff;
        }
        .chat-header {
            background: linear-gradient(to right, #1E40AF, #3B82F6);
            color: #fff;
            padding: 1rem;
            font-size: 1.125rem;
            font-weight: 600;
            border-bottom: 1px solid #1E3A8A;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chat-header .status-dot {
            width: 0.6rem;
            height: 0.6rem;
            border-radius: 50%;
            margin-left: 0.5rem;
        }
        .chat-header .status-active {
            background-color: #10B981;
        }
        .chat-header .status-inactive {
            background-color: #EF4444;
        }
        .messages {
            flex-grow: 1;
            padding: 1.5rem;
            overflow-y: auto;
            background-color: #f9fafb;
            scrollbar-width: none;
        }
        .messages::-webkit-scrollbar {
            display: none;
        }
        .message {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            max-width: 70%;
            line-height: 1.5;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.3s ease-in;
        }
        .message.sent {
            background: linear-gradient(to right, #1E40AF, #3B82F6);
            color: #fff;
            margin-left: auto;
        }
        .message.received {
            background-color: #e5e7eb;
            color: #1f2937;
            margin-right: auto;
        }
        .message strong {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
            display: block;
        }
        .message .time {
            font-size: 0.65rem;
            opacity: 0.6;
            margin-top: 0.25rem;
            text-align: right;
        }
        .chat-form {
            display: flex;
            align-items: center;
            padding: 1rem;
            background-color: #fff;
            border-top: 1px solid #e5e7eb;
        }
        .chat-form input {
            flex-grow: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 1rem;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s ease;
        }
        .chat-form input:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .chat-form button {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(to right, #1E40AF, #3B82F6);
            color: #fff;
            border: none;
            border-radius: 1rem;
            margin-left: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        .chat-form button:hover {
            background: linear-gradient(to right, #1E3A8A, #2563EB);
            transform: translateY(-2px);
        }
        .error {
            color: #EF4444;
            text-align: center;
            margin: 0.5rem 0;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            .chat-container {
                height: 500px;
            }
            .user-list {
                width: 200px;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let selectedUserType = '<?php echo htmlspecialchars($selected_user_type, ENT_QUOTES, 'UTF-8'); ?>';
        let selectedUserId = <?php echo $selected_user_id; ?>;
        let currentUserType = 'staff';
        let currentUserId = <?php echo $currentStaffId; ?>;
        let lastMessageTime = null;

        function fetchMessages() {
            if (selectedUserType && selectedUserId) {
                let url = 'fetch_messages.php?other_type=' + encodeURIComponent(selectedUserType) + '&other_id=' + selectedUserId;
                if (lastMessageTime) {
                    url += '&last_message_time=' + encodeURIComponent(lastMessageTime);
                }
                $.get(url, function(data) {
                    if (data.success) {
                        let messagesDiv = $('.messages');
                        let isAtBottom = messagesDiv[0].scrollHeight - messagesDiv.scrollTop() <= messagesDiv.outerHeight() + 10;
                        messagesDiv.empty(); // Clear existing messages
                        data.messages.forEach(function(msg) {
                            let sender = (msg.sender_type === currentUserType && msg.sender_id == currentUserId) ? 'Siz' : '<?php echo htmlspecialchars($selected_user_name, ENT_QUOTES, 'UTF-8'); ?>';
                            let messageClass = (msg.sender_type === currentUserType && msg.sender_id == currentUserId) ? 'sent' : 'received';
                            let time = new Date(msg.created_at).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
                            messagesDiv.append('<div class="message ' + messageClass + '"><strong>' + sender + ':</strong> ' + msg.message + '<div class="time">' + time + '</div></div>');
                            lastMessageTime = msg.created_at > lastMessageTime ? msg.created_at : lastMessageTime;
                        });
                        if (isAtBottom && data.messages.length > 0) {
                            messagesDiv.scrollTop(messagesDiv[0].scrollHeight);
                        }
                    } else {
                        console.error('Error fetching messages:', data.error);
                        $('.messages').append('<p class="error">Hata: Mesajlar alınamadı.</p>');
                    }
                }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error fetching messages:', textStatus, errorThrown);
                    $('.messages').append('<p class="error">Hata: Mesajlar alınamadı (sunucu hatası).</p>');
                });
            }
        }

        function sendMessage() {
            let message = $('input[name="message"]').val();
            if (message.trim() === '') return;
            $.post('send_message.php', {
                receiver_type: selectedUserType,
                receiver_id: selectedUserId,
                message: message
            }, function(data) {
                if (data.success) {
                    $('input[name="message"]').val('');
                    fetchMessages();
                } else {
                    console.error('Error sending message:', data.error);
                    $('.messages').append('<p class="error">Hata: Mesaj gönderilemedi: ' + data.error + '</p>');
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error sending message:', textStatus, errorThrown);
                $('.messages').append('<p class="error">Hata: Mesaj gönderilemedi (sunucu hatası).</p>');
            });
        }

        $(document).ready(function() {
            fetchMessages();
            setInterval(fetchMessages, 2000);

            $('form').submit(function(e) {
                e.preventDefault();
                sendMessage();
            });

            $('.user-list a').each(function() {
                if ($(this).attr('href') === '?user_id=<?php echo $selected_user_id; ?>&user_type=<?php echo htmlspecialchars($selected_user_type, ENT_QUOTES, 'UTF-8'); ?>') {
                    $(this).addClass('active');
                }
            });
        });

        function toggleNotifications() {
            document.getElementById('notificationsModal').classList.toggle('hidden');
            document.getElementById('notificationsModal').classList.toggle('flex');
        }
    </script>
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
                <a href="staff_customers.php" class="nav-button bg-white/10 text-white px-4 py-2 rounded-lg"><i class="fas fa-users mr-2"></i>Müşteriler</a>
                <a href="staff_chat.php" class="nav-button bg-white text-blue-900 px-4 py-2 rounded-lg active"><i class="fas fa-envelope mr-2"></i>Mesajlar
                    <?php if(isset($unreadCounts) && array_sum(array_values($unreadCounts)) > 0): ?>
                        <span class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-xs animate-pulse">
                            <?php echo array_sum(array_values($unreadCounts)); ?>
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="chat-container">
            <div class="user-list">
                <h3>Kullanıcılar</h3>
                <?php if (empty($users)): ?>
                    <p style="text-align: center; color: #6b7280;">Kullanıcı bulunamadı.</p>
                <?php else: ?>
                    <ul>
                    <?php foreach ($users as $u): 
                        $key = $u['type'] . '_' . $u['id'];
                        $hasNew = isset($unreadCounts[$key]) && $unreadCounts[$key] > 0;
                    ?>
                        <li>
                            <a href="?user_id=<?php echo $u['id']; ?>&user_type=<?php echo htmlspecialchars($u['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-<?php echo $u['type'] == 'admin' ? 'user-shield' : 'user-tie'; ?> user-icon"></i>
                                <span class="username"><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo $u['type'] == 'admin' ? 'Yönetici' : 'Personel'; ?>)</span>
                                <?php if ($hasNew): ?>
                                    <span class="unread-badge"><?php echo $unreadCounts[$key]; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="chat-area">
                <?php if ($selected_user_type && $selected_user_id): ?>
                    <div class="chat-header">
                        <span><?php echo htmlspecialchars($selected_user_name, ENT_QUOTES, 'UTF-8'); ?> ile Mesajlaşma</span>
                        <span class="status-dot <?php echo $selected_user_type == 'admin' ? 'status-active' : 'status-inactive'; ?>"></span>
                    </div>
                    <div class="messages"></div>
                    <form class="chat-form">
                        <input type="text" name="message" placeholder="Mesajınızı yazın..." required>
                        <button type="submit"><i class="fas fa-paper-plane mr-2"></i>Gönder</button>
                    </form>
                <?php else: ?>
                    <p style="text-align: center; color: #6b7280; font-size: 1rem; margin-top: 2rem;">Lütfen bir kullanıcı seçin.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>