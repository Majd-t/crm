<?php
// admin_chat.php (with professional compact design)
require 'db.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    error_log("Admin not logged in, redirecting to login.php");
    header("Location: login.php");
    exit;
}

// Set session variables
$_SESSION['user_type'] = 'admin';
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Yönetici';
$currentAdminId = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;

// If admin_id is not set, try to fetch it from the database using admin_name
if ($currentAdminId === null) {
    try {
        $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $stmt->bind_param('s', $admin_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ($row) {
            $currentAdminId = $row['id'];
            $_SESSION['admin_id'] = $currentAdminId; // Set admin_id for future use
        } else {
            error_log("No admin found with username: $admin_name");
            header("Location: login.php?error=invalid_user");
            exit;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching admin_id: " . $e->getMessage());
        header("Location: login.php?error=database_error");
        exit;
    }
}

$_SESSION['user_id'] = $currentAdminId;

// Fetch all users: staff and other admins (excluding self)
$users = [];
try {
    $stmt = $conn->prepare("SELECT id, username, 'staff' AS type FROM staff UNION SELECT id, username, 'admin' AS type FROM admin WHERE id != ?");
    $stmt->bind_param('i', $currentAdminId);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = []; // Fallback to empty array to avoid breaking the page
}

// Fetch counts of unread messages for current admin
$unreadCounts = [];
try {
    $q = $conn->prepare("SELECT sender_type, sender_id, COUNT(*) AS unread_count FROM messages WHERE receiver_type='admin' AND receiver_id=? AND status=0 GROUP BY sender_type, sender_id");
    $q->bind_param('i', $currentAdminId);
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
    <title>Admin Sohbet</title>
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
        .sidebar {
            background: linear-gradient(to bottom, #1E40AF, #3B82F6);
            width: 16rem;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 1.5rem;
            color: white;
        }
        .sidebar h2 {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .sidebar a {
            display: block;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            color: white;
            transition: all 0.3s ease;
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .hover-scale {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .hover-scale:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(30, 64, 175, 0.2);
        }
        .main-content {
            margin-left: 16rem;
            padding: 2rem;
            width: calc(100% - 16rem);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 4rem);
        }
        .chat-container {
            width: 100%;
            max-width: 900px;
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
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
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
        let currentUserType = 'admin';
        let currentUserId = <?php echo $currentAdminId; ?>;
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
                        messagesDiv.empty(); // Clear existing messages to avoid duplicates
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
            $.post('send_message.php', { receiver_type: selectedUserType, receiver_id: selectedUserId, message: message }, function(data) {
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
            setInterval(fetchMessages, 5000);

            $('form').submit(function(e) {
                e.preventDefault();
                sendMessage();
            });

            // Set active class for selected user
            $('.user-list a').each(function() {
                if ($(this).attr('href') === '?user_id=<?php echo $selected_user_id; ?>&user_type=<?php echo htmlspecialchars($selected_user_type, ENT_QUOTES, 'UTF-8'); ?>') {
                    $(this).addClass('active');
                }
            });
        });
    </script>
</head>
<body>
    <div class="flex">
        <!-- Sidebar -->
        <div class="sidebar w-64 h-screen fixed top-0 left-0 p-6 text-white">
            <h2 class="text-2xl font-bold text-center mb-10"><i class="fas fa-user-shield mr-2"></i> <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></h2>
            <nav class="space-y-3">
                <a href="admin_dashboard.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
                <a href="admin_statuses.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-tags mr-2"></i> Durum Yönetimi</a>
                                <a href="ai_analysis.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-brain"></i> AI Analizi</a>

                <a href="assign.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-random mr-2"></i> Müşteri Dağıtımı</a>
                <a href="admin_chat.php" class="block px-4 py-3 rounded-lg text-white bg-white/10 active"><i class="fas fa-envelope mr-2"></i> Mesajlar</a>
                <a href="logout.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-sign-out-alt mr-2"></i> Çıkış Yap</a>
            </nav>
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
    </div>
</body>
</html>