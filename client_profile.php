```php
<?php
session_start();
include 'db.php';
include 'notification_helper.php';

// التحقق من تسجيل الدخول وصلاحيات الوصول
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['staff_logged_in'])) {
    header("Location: login.php");
    exit;
}

// التحقق من وجود ID العميل
if (!isset($_GET['id'])) {
    echo "Müşteri ID eksik!";
    exit;
}

$client_id = intval($_GET['id']);

// التحقق من صلاحيات الموظف
if (isset($_SESSION['staff_logged_in'])) {
    $client_query = mysqli_query($conn, "SELECT assigned_staff_id FROM customers WHERE id = $client_id");
    $result = mysqli_fetch_assoc($client_query);
    if ($result['assigned_staff_id'] != $_SESSION['staff_id']) {
        echo "Bu müşteriye erişim yetkiniz yok!";
        exit;
    }
}

// جلب بيانات العميل
$client_query = mysqli_query($conn, "SELECT * FROM customers WHERE id = $client_id");
if (mysqli_num_rows($client_query) == 0) {
    echo "Müşteri bulunamadı!";
    exit;
}
$client = mysqli_fetch_assoc($client_query);

// تأكيد وجود الأعمدة لتجنب التحذيرات
$client_name = isset($client['username']) ? $client['username'] : (isset($client['name']) ? $client['name'] : 'İsimsiz');
$client_email = isset($client['email']) ? $client['email'] : '';
$client_phone = isset($client['phone']) ? $client['phone'] : '';
$client_status = isset($client['account_status']) ? $client['account_status'] : (isset($client['status']) ? $client['status'] : 'Bilinmiyor');
$client_created_at = isset($client['created_at']) ? $client['created_at'] : 'Bilinmiyor';

// معرفة المستخدم الحالي لإضافته في النشاط
$current_user = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : (isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : 'Unknown');
$current_staff_id = isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : NULL;

// إضافة ملاحظة جديدة
if (isset($_POST['add_note'])) {
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    $insert_note_query = mysqli_query($conn, "INSERT INTO client_notes (client_id, added_by, note, created_at) VALUES ($client_id, '$current_user', '$note', NOW())");
    if ($insert_note_query) {
        // تسجيل النشاط في Activity Log
        mysqli_query($conn, "INSERT INTO customer_status_log (customer_id, staff_id, type, description, added_by, created_at) VALUES ($client_id, ".($current_staff_id ?? "NULL").", 'note', '$note', '$current_user', NOW())");
        // إشعار للموظف المعين
        if ($client['assigned_staff_id'] && (!isset($_SESSION['staff_id']) || $client['assigned_staff_id'] != $_SESSION['staff_id'])) {
            addNotification($conn, 'staff', $client['assigned_staff_id'], 'client_activity', "Müşteri $client_name için yeni not eklendi: $note", $client_id);
        }
    } else {
        $error_msg = "Not eklenirken hata oluştu: " . mysqli_error($conn);
    }
    header("Location: client_profile.php?id=$client_id");
    exit;
}

// رفع ملف/صورة جديدة
if (isset($_POST['upload_file'])) {
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    if (isset($_FILES['file']['name']) && $_FILES['file']['name'] != "") {
        $filename = time().'_'.basename($_FILES['file']['name']);
        $target_dir = "Uploads/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
            $insert_file_query = mysqli_query($conn, "INSERT INTO client_files (client_id, file_name, description, uploaded_by, created_at) VALUES ($client_id, '$filename', '$description', '$current_user', NOW())");
            if ($insert_file_query) {
                // تسجيل النشاط في Activity Log
                $desc_log = "Dosya yüklendi: $filename | Açıklama: $description";
                mysqli_query($conn, "INSERT INTO customer_status_log (customer_id, staff_id, type, description, added_by, created_at) VALUES ($client_id, ".($current_staff_id ?? "NULL").", 'file', '$desc_log', '$current_user', NOW())");
                // إشعار للموظف المعين
                if ($client['assigned_staff_id'] && (!isset($_SESSION['staff_id']) || $client['assigned_staff_id'] != $_SESSION['staff_id'])) {
                    addNotification($conn, 'staff', $client['assigned_staff_id'], 'client_activity', "Müşteri $client_name için yeni dosya yüklendi: $filename", $client_id);
                }
            } else {
                $error_msg = "Dosya kaydedilirken hata oluştu: " . mysqli_error($conn);
            }
        } else {
            $error_msg = "Dosya yüklenirken hata oluştu!";
        }
    } else {
        $error_msg = "Dosya seçilmedi!";
    }
    header("Location: client_profile.php?id=$client_id");
    exit;
}

// تغيير الحالة
if (isset($_POST['change_status'])) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $old_status = $client_status;
    $update_status_query = mysqli_query($conn, "UPDATE customers SET status='$new_status' WHERE id=$client_id");
    if ($update_status_query) {
        $desc_log = "Eski Durum: $old_status, Yeni Durum: $new_status, Not: $comment";
        mysqli_query($conn, "INSERT INTO customer_status_log (customer_id, staff_id, type, description, added_by, created_at) VALUES ($client_id, ".($current_staff_id ?? "NULL").", 'status', '$desc_log', '$current_user', NOW())");
        // إشعار للموظف المعين
        if ($client['assigned_staff_id'] && (!isset($_SESSION['staff_id']) || $client['assigned_staff_id'] != $_SESSION['staff_id'])) {
            addNotification($conn, 'staff', $client['assigned_staff_id'], 'client_activity', "Müşteri $client_name durumu güncellendi: $new_status", $client_id);
        }
    } else {
        $error_msg = "Durum güncellenirken hata oluştu: " . mysqli_error($conn);
    }
    header("Location: client_profile.php?id=$client_id");
    exit;
}

// تحديث معلومات العميل
if (isset($_POST['update_client'])) {
    $new_name = mysqli_real_escape_string($conn, $_POST['name']);
    $new_email = mysqli_real_escape_string($conn, $_POST['email']);
    $new_phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    // جلب القيم القديمة للمقارنة
    $old_name = $client_name;
    $old_email = $client_email;
    $old_phone = $client_phone;
    
    // تحديث البيانات في قاعدة البيانات
    $update_query = "UPDATE customers SET name='$new_name', email='$new_email', phone='$new_phone' WHERE id=$client_id";
    if (mysqli_query($conn, $update_query)) {
        // تسجيل التغييرات في سجل النشاط
        $changes = [];
        if ($old_name !== $new_name) {
            $changes[] = "İsim: $old_name → $new_name";
        }
        if ($old_email !== $new_email) {
            $changes[] = "E-posta: $old_email → $new_email";
        }
        if ($old_phone !== $new_phone) {
            $changes[] = "Telefon: $old_phone → $new_phone";
        }
        if (!empty($changes)) {
            $desc_log = "Müşteri bilgileri güncellendi: " . implode(', ', $changes);
            mysqli_query($conn, "INSERT INTO customer_status_log (customer_id, staff_id, type, description, added_by, created_at) VALUES ($client_id, ".($current_staff_id ?? "NULL").", 'profile_update', '$desc_log', '$current_user', NOW())");
            if ($client['assigned_staff_id'] && (!isset($_SESSION['staff_id']) || $client['assigned_staff_id'] != $_SESSION['staff_id'])) {
                addNotification($conn, 'staff', $client['assigned_staff_id'], 'client_activity', "Müşteri $client_name bilgileri güncellendi: " . implode(', ', $changes), $client_id);
            }
        }
    } else {
        $error_msg = "Bilgiler güncellenirken hata oluştu: " . mysqli_error($conn);
    }
    header("Location: client_profile.php?id=$client_id");
    exit;
}

// جلب الملاحظات والملفات السابقة
$notes_result = mysqli_query($conn, "SELECT * FROM client_notes WHERE client_id = $client_id ORDER BY created_at DESC");
$files_result = mysqli_query($conn, "SELECT * FROM client_files WHERE client_id = $client_id ORDER BY created_at DESC");

// جلب النشاطات
$activity_result = mysqli_query($conn, "SELECT cs.*, s.username AS staff_name 
                                        FROM customer_status_log cs 
                                        LEFT JOIN staff s ON cs.staff_id = s.id 
                                        WHERE cs.customer_id = $client_id 
                                        ORDER BY cs.created_at DESC");
?>

<!DOCTYPE html>
<html lang="tr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($client_name); ?> - Müşteri Profili</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary-50: #f8fafc;
            --primary-100: #f1f5f9;
            --primary-200: #e2e8f0;
            --primary-300: #cbd5e1;
            --primary-400: #94a3b8;
            --primary-500: #64748b;
            --primary-600: #475569;
            --primary-700: #334155;
            --primary-800: #1e293b;
            --primary-900: #0f172a;
            --accent-500: #3b82f6;
            --accent-600: #2563eb;
            --accent-700: #1d4ed8;
            --success-500: #10b981;
            --warning-500: #f59e0b;
            --danger-500: #ef4444;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .modern-card {
            background: white;
            border: 1px solid var(--primary-200);
            border-radius: 16px;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .client-header-card {
            background: linear-gradient(135deg, var(--accent-600) 0%, var(--accent-700) 100%);
            border-radius: 16px;
            border: none;
        }

        .form-input {
            background: white;
            border: 2px solid var(--primary-200);
            border-radius: 10px;
            padding: 12px 16px;
            width: 100%;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            color: var(--primary-800);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-500);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--accent-600);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-700);
        }

        .btn-secondary {
            background: var(--primary-100);
            color: var(--primary-700);
            border: 2px solid var(--primary-200);
        }

        .btn-secondary:hover {
            background: var(--primary-200);
            border-color: var(--primary-300);
        }

        .status-badge {
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .status-aktif { background: rgba(16, 185, 129, 0.2); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
        .status-pasif { background: rgba(239, 68, 68, 0.2); color: #ef4444; border-color: rgba(239, 68, 68, 0.3); }
        .status-beklemede { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3); }
        .status-bilinmiyor { background: rgba(148, 163, 184, 0.2); color: #94a3b8; border-color: rgba(148, 163, 184, 0.3); }

        .contact-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 20px;
        }

        .contact-icon:hover {
            transform: translateY(-3px) scale(1.05);
        }

        .whatsapp-btn { background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); }
        .telegram-btn { background: linear-gradient(135deg, #0088cc 0%, #005580 100%); }

        .info-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 16px;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .section-divider {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .section-divider::after {
            content: '';
            position: absolute;
            bottom: -0.75rem;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-500), transparent);
            border-radius: 1px;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-100);
        }

        .section-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent-500), var(--accent-600));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            color: white;
            font-size: 20px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-800);
            margin: 0;
        }

        .scrollable-content {
            max-height: 350px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-300) transparent;
        }

        .scrollable-content::-webkit-scrollbar {
            width: 6px;
        }

        .scrollable-content::-webkit-scrollbar-track {
            background: var(--primary-100);
            border-radius: 3px;
        }

        .scrollable-content::-webkit-scrollbar-thumb {
            background: var(--primary-300);
            border-radius: 3px;
        }

        .content-card {
            background: var(--primary-50);
            border: 1px solid var(--primary-100);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .content-card:hover {
            background: white;
            border-color: var(--primary-200);
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .activity-card {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border: 1px solid var(--primary-200);
            border-radius: 16px;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
        }

        .activity-timeline {
            position: relative;
            padding-left: 32px;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 16px;
            bottom: 16px;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary-400), var(--primary-200));
            border-radius: 2px;
        }

        .activity-timeline-item {
            position: relative;
            margin-bottom: 24px;
            background: white;
            border: 2px solid var(--primary-100);
            border-radius: 12px;
            padding: 16px;
            transition: all 0.3s ease;
        }

        .activity-timeline-item:hover {
            transform: translateX(6px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            border-color: var(--accent-500);
        }

        .activity-timeline-item::before {
            content: '';
            position: absolute;
            left: -44px;
            top: 20px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--accent-500);
            z-index: 2;
        }

        .activity-timeline-item.note-activity::before { border-color: #8b5cf6; }
        .activity-timeline-item.file-activity::before { border-color: #f59e0b; }
        .activity-timeline-item.status-activity::before { border-color: #10b981; }
        .activity-timeline-item.profile_update-activity::before { border-color: #3b82f6; }

        input[type="file"]::-webkit-file-upload-button {
            background: var(--accent-500);
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        input[type="file"]::-webkit-file-upload-button:hover {
            background: var(--accent-600);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-100);
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-800);
            margin: 0;
        }

        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--primary-500);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--primary-700);
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .modern-card { border-radius: 12px; }
            .client-header-card { border-radius: 12px; }
            .btn { padding: 10px 20px; font-size: 14px; }
            .contact-icon { width: 40px; height: 40px; font-size: 18px; }
            .section-icon { width: 40px; height: 40px; font-size: 18px; margin-right: 12px; }
            .section-title { font-size: 20px; }
            .activity-timeline { padding-left: 28px; }
            .activity-timeline::before { left: 14px; }
            .activity-timeline-item::before { left: -40px; }
            .modal-content { max-width: 90%; }
        }
    </style>
</head>
<body>
    <div class="min-h-screen p-3 md:p-5 lg:p-6">
        <div class="max-w-6xl mx-auto">
            <!-- Page Header -->
            <div class="text-center mb-6 fade-in">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-3">Müşteri Profili</h1>
                <p class="text-gray-600 text-base md:text-lg">Detaylı müşteri bilgileri ve işlem geçmişi</p>
            </div>

            <!-- Error Message Display -->
            <?php if (isset($error_msg)) { ?>
                <div class="alert alert-error mx-auto max-w-6xl mb-6">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo $error_msg; ?>
                </div>
            <?php } ?>

            <!-- Main Grid Layout -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 md:gap-6">
                
                <!-- Client Information Card -->
                <div class="xl:col-span-8">
                    <div class="client-header-card p-5 md:p-6 text-white relative">
                        <div class="relative z-10">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between mb-6">
                                <div class="mb-3 md:mb-0">
                                    <h2 class="text-2xl md:text-3xl font-bold mb-2"><?php echo htmlspecialchars($client_name); ?></h2>
                                    <p class="text-white/80 text-base">Müşteri ID: #<?php echo htmlspecialchars($client_id); ?></p>
                                </div>
                                <div class="status-badge status-<?php echo htmlspecialchars(strtolower($client_status)); ?>">
                                    <?php echo htmlspecialchars($client_status); ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div class="info-card">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-envelope text-xl"></i>
                                    </div>
                                    <p class="text-white/70 text-sm font-medium mb-1">E-posta Adresi</p>
                                    <p class="text-white text-base font-semibold truncate"><?php echo htmlspecialchars($client_email); ?></p>
                                </div>
                                
                                <div class="info-card">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-phone text-xl"></i>
                                    </div>
                                    <p class="text-white/70 text-sm font-medium mb-1">Telefon Numarası</p>
                                    <p class="text-white text-base font-semibold"><?php echo htmlspecialchars($client_phone); ?></p>
                                </div>
                                
                                <div class="info-card">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-calendar text-xl"></i>
                                    </div>
                                    <p class="text-white/70 text-sm font-medium mb-1">Kayıt Tarihi</p>
                                    <p class="text-white text-base font-semibold"><?php echo htmlspecialchars($client_created_at); ?></p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <?php if (!empty($client_phone)) { 
                                    $clean_phone = preg_replace('/[^0-9]/', '', $client_phone);
                                    if (substr($clean_phone, 0, 1) == '0') {
                                        $clean_phone = '90' . substr($clean_phone, 1);
                                    }
                                ?>
                                    <a href="https://wa.me/<?php echo $clean_phone; ?>" target="_blank" class="contact-icon whatsapp-btn" title="WhatsApp ile Mesaj Gönder">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    <a href="https://t.me/+<?php echo $clean_phone; ?>" target="_blank" class="contact-icon telegram-btn" title="Telegram ile Mesaj Gönder">
                                        <i class="fab fa-telegram-plane"></i>
                                    </a>
                                <?php } ?>
                                <button class="btn btn-primary" onclick="openModal()">
                                    <i class="fas fa-edit mr-2"></i>
                                    Profili Düzenle
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Update Card -->
                <div class="xl:col-span-4">
                    <div class="status-card modern-card p-5">
                        <div class="section-divider">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <h3 class="section-title">Durum Güncelle</h3>
                            </div>
                        </div>
                        
                        <form method="post" class="space-y-3">
                            <select name="status" required class="form-input">
                                <option value="">Yeni Durumu Seçin</option>
                                <option value="Aktif">Aktif</option>
                                <option value="Pasif">Pasif</option>
                                <option value="Beklemede">Beklemede</option>
                                <option value="İptal">İptal</option>
                            </select>
                            
                            <textarea name="comment" placeholder="Durum ile ilgili not veya açıklama..." rows="3" class="form-input resize-none"></textarea>
                            
                            <button type="submit" name="change_status" class="btn btn-primary w-full">
                                <i class="fas fa-check mr-2"></i>
                                Durumu Güncelle
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Notes Section -->
                <div class="xl:col-span-7">
                    <div class="notes-card modern-card p-5">
                        <div class="section-divider">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-sticky-note"></i>
                                </div>
                                <h3 class="section-title">Notlar ve Yorumlar</h3>
                            </div>
                        </div>

                        <?php if (isset($error_msg)) { ?>
                            <div class="alert alert-error mb-4">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <?php echo $error_msg; ?>
                            </div>
                        <?php } ?>

                        <form method="post" class="mb-5 space-y-3">
                            <textarea name="note" placeholder="Müşteri ile ilgili notunuzu buraya yazın..." rows="4" class="form-input resize-none"></textarea>
                            <button type="submit" name="add_note" class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i>
                                Not Ekle
                            </button>
                        </form>

                        <div class="scrollable-content">
                            <?php if (mysqli_num_rows($notes_result) > 0) { ?>
                                <div class="space-y-3">
                                    <?php while ($note = mysqli_fetch_assoc($notes_result)) { ?>
                                        <div class="content-card">
                                            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
                                                <span class="font-semibold text-gray-800 text-base"><?php echo htmlspecialchars($note['added_by']); ?></span>
                                                <span class="text-sm text-gray-500 mt-1 md:mt-0"><?php echo $note['created_at']; ?></span>
                                            </div>
                                            <p class="text-gray-700 text-sm leading-relaxed"><?php echo htmlspecialchars($note['note']); ?></p>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="text-center py-10">
                                    <i class="fas fa-sticky-note text-3xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-500 text-base">Henüz not eklenmemiş</p>
                                    <p class="text-gray-400 text-sm">İlk notu eklemek için yukarıdaki formu kullanın</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- Files Section -->
                <div class="xl:col-span-5">
                    <div class="files-card modern-card p-5">
                        <div class="section-divider">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <h3 class="section-title">Dosya Yönetimi</h3>
                            </div>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="mb-5 space-y-3">
                            <input type="file" name="file" required class="form-input">
                            <input type="text" name="description" placeholder="Dosya açıklaması..." required class="form-input">
                            <button type="submit" name="upload_file" class="btn btn-primary w-full">
                                <i class="fas fa-upload mr-2"></i>
                                Dosya Yükle
                            </button>
                        </form>

                        <div class="scrollable-content">
                            <?php if (mysqli_num_rows($files_result) > 0) { ?>
                                <div class="space-y-3">
                                    <?php while ($file = mysqli_fetch_assoc($files_result)) { ?>
                                        <div class="content-card">
                                            <div class="flex items-start mb-2">
                                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                    <i class="fas fa-file text-blue-600 text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <a href="Uploads/<?php echo htmlspecialchars($file['file_name']); ?>" target="_blank" class="text-gray-800 font-semibold hover:text-blue-600 transition-colors block truncate text-sm">
                                                        <?php echo htmlspecialchars($file['file_name']); ?>
                                                    </a>
                                                    <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($file['description']); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex justify-between items-center text-xs text-gray-500">
                                                <span><?php echo htmlspecialchars($file['uploaded_by']); ?></span>
                                                <span><?php echo $file['created_at']; ?></span>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="text-center py-10">
                                    <i class="fas fa-folder-open text-3xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-500 text-base">Henüz dosya yüklenmemiş</p>
                                    <p class="text-gray-400 text-sm">İlk dosyayı yüklemek için yukarıdaki formu kullanın</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- Activity Log Section -->
                <div class="xl:col-span-12">
                    <div class="activity-card p-5 md:p-6">
                        <div class="section-divider">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <h3 class="section-title">Aktivite Geçmişi</h3>
                            </div>
                        </div>

                        <div class="scrollable-content" style="max-height: 500px;">
                            <?php if (mysqli_num_rows($activity_result) > 0) { ?>
                                <div class="activity-timeline">
                                    <?php while ($act = mysqli_fetch_assoc($activity_result)) { ?>
                                        <div class="activity-timeline-item <?php echo $act['type']; ?>-activity">
                                            <div class="flex items-start mb-3">
                                                <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3 flex-shrink-0 <?php 
                                                    echo $act['type'] == 'note' ? 'bg-purple-100 text-purple-600' : 
                                                        ($act['type'] == 'file' ? 'bg-yellow-100 text-yellow-600' : 
                                                        ($act['type'] == 'profile_update' ? 'bg-blue-100 text-blue-600' : 
                                                        'bg-green-100 text-green-600')); 
                                                ?>">
                                                    <i class="fas <?php echo $act['type'] == 'note' ? 'fa-sticky-note' : ($act['type'] == 'file' ? 'fa-file' : ($act['type'] == 'profile_update' ? 'fa-user-edit' : 'fa-sync-alt')); ?>"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
                                                        <h4 class="font-bold text-gray-800 text-base">
                                                            <?php echo htmlspecialchars($act['type'] == 'note' ? 'Not Eklendi' : ($act['type'] == 'file' ? 'Dosya Yüklendi' : ($act['type'] == 'profile_update' ? 'Profil Güncellendi' : 'Durum Güncellendi'))); ?>
                                                        </h4>
                                                        <span class="text-xs text-gray-500 md:ml-3"><?php echo $act['created_at']; ?></span>
                                                    </div>
                                                    <p class="text-gray-700 text-sm leading-relaxed mb-2">
                                                        <?php echo htmlspecialchars($act['description']); ?>
                                                    </p>
                                                    <div class="text-xs text-gray-500">
                                                        <span class="font-medium">
                                                            <?php echo htmlspecialchars($act['added_by']) . ($act['staff_name'] ? " (".htmlspecialchars($act['staff_name']).")" : ""); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="text-center py-12">
                                    <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                                    <h4 class="text-xl font-bold text-gray-600 mb-2">Henüz Aktivite Yok</h4>
                                    <p class="text-gray-500 text-base">Bu müşteri için henüz herhangi bir aktivite kaydedilmemiş</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Editing Client Information -->
    <div id="editClientModal" class="modal">
        <div class="modal-content fade-in">
            <button class="modal-close" onclick="closeModal()">✕</button>
            <div class="modal-header">
                <div class="section-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <h3 class="modal-title">Müşteri Bilgilerini Düzenle</h3>
            </div>
            <form method="post" class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">İsim</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($client_name); ?>" required class="form-input">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-posta Adresi</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($client_email); ?>" required class="form-input">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefon Numarası</label>
                    <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($client_phone); ?>" required class="form-input">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">İptal</button>
                    <button type="submit" name="update_client" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('editClientModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editClientModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('editClientModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
```