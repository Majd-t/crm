```php
<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
$admin_name = $_SESSION['admin_name'];

// 🔹 إعداد الاتصال بقاعدة البيانات
$host = "localhost";
$user = "root";       // عدل حسب إعدادات XAMPP
$pass = "";           // عدل إذا كانت لديك كلمة مرور
$db   = "CRM";        // اسم قاعدة البيانات

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("❌ Veritabanı bağlantısı başarısız: " . $conn->connect_error);
}

// 🔹 أسماء الجداول التي نريد أخذ عينات منها
$tables = [
    "admin",
    "staff",
    "customers",
    "customer_statuses",
    "no_response_statuses"
];

// 🔹 جمع عينات البيانات
$allData = [];
foreach ($tables as $table) {
    $result = $conn->query("SELECT * FROM `$table` LIMIT 5");
    if ($result && $result->num_rows > 0) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $allData[$table] = $rows;
    } else {
        $allData[$table] = "Veri yok";
    }
}

// 🔹 تحضير ملخص البيانات للـ API
$dataSummary = "Toplam Müşteriler: " . mysqli_num_rows(mysqli_query($conn, "SELECT * FROM customers")) . "\n";
$dataSummary .= "Toplam Çalışanlar: " . mysqli_num_rows(mysqli_query($conn, "SELECT * FROM staff")) . "\n";
$dataSummary .= "Müşteri Durum Dağılımı:\n";
$statuses = mysqli_query($conn, "SELECT cs.name, COUNT(*) as count FROM customers c JOIN customer_statuses cs ON c.status_id = cs.id GROUP BY cs.id");
if ($statuses) {
    while ($status = mysqli_fetch_assoc($statuses)) {
        $dataSummary .= "- {$status['name']}: {$status['count']}\n";
    }
} else {
    $dataSummary .= "- Durum verisi alınamadı.\n";
}
$dataSummary .= "Cevapsız Durum Dağılımı:\n";
$no_response_statuses = mysqli_query($conn, "SELECT nrs.name, COUNT(*) as count FROM customers c JOIN no_response_statuses nrs ON c.no_response_status_id = nrs.id GROUP BY nrs.id");
if ($no_response_statuses) {
    while ($status = mysqli_fetch_assoc($no_response_statuses)) {
        $dataSummary .= "- {$status['name']}: {$status['count']}\n";
    }
} else {
    $dataSummary .= "- Cevapsız durum verisi alınamadı.\n";
}

// 🔹 معالجة طلب المستخدم
$responseText = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["question"])) {
    $question = $_POST["question"];

    // استدعاء ChatGPT API
    $apiKey = "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"; // ضع مفتاح API الخاص بك
    $url = "https://api.openai.com/v1/chat/completions";

    $postData = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "Sen bir CRM veri analizi asistanısın. Verilen verilere dayanarak doğru ve ayrıntılı raporlar oluştur veya soruları Türkçe yanıtla."],
            ["role" => "user", "content" => "Aşağıdaki verilere dayanarak soruyu yanıtla veya raporu oluştur:\n$dataSummary\n\nSoru/Rapor Talebi: $question"]
        ],
        "max_tokens" => 500
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData, JSON_UNESCAPED_UNICODE));

    $result = curl_exec($ch);
    if ($result === false) {
        $responseText = "❌ API bağlantı hatası: " . curl_error($ch);
    } else {
        $json = json_decode($result, true);
        $responseText = $json["choices"][0]["message"]["content"] ?? "❌ AI'dan yanıt alınamadı.";
    }
    curl_close($ch);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="tr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Analiz Paneli</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1E3A8A',
                        'secondary': '#2563EB',
                        'accent': '#BFDBFE',
                        'neutral': '#F1F5F9',
                        'highlight': '#3B82F6',
                        'dark-blue': '#1E40AF'
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to bottom, #F5F7FA, #E5E7EB);
            margin: 0;
            color: #1E293B;
        }
        .container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .sidebar {
            background: linear-gradient(to bottom, #1E40AF, #3B82F6);
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            padding: 24px;
            transition: transform 0.3s ease-in-out;
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15);
        }
        .sidebar a {
            transition: all 0.3s ease;
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            margin: 8px 0;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #FFFFFF;
            transform: translateX(5px);
        }
        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }
        .sidebar-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
        }
        .main-content {
            padding: 32px;
            margin-left: 280px;
            min-height: 100vh;
            background: #F1F5F9;
            width: calc(100% - 280px);
        }
        .analysis-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .analysis-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(30, 64, 175, 0.2);
        }
        .quick-report-btn {
            background: linear-gradient(to right, #1E40AF, #3B82F6);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quick-report-btn:hover {
            background: linear-gradient(to right, #1E3A8A, #2563EB);
            transform: translateY(-2px);
        }
        .result-box {
            max-height: 400px;
            overflow-y: auto;
            background: #F8FAFC;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-in-out;
        }
        .modal-content {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.2);
            animation: slideIn 0.3s ease-in-out;
        }
        .spinner {
            border: 5px solid #F3F3F3;
            border-top: 5px solid #2563EB;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .table-container {
            border-radius: 12px;
            overflow: hidden;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15);
        }
        .table-header {
            background: linear-gradient(to right, #1E40AF, #3B82F6);
            color: white;
            padding: 16px;
            font-size: 18px;
            font-weight: 600;
        }
        table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        th, td {
            border-bottom: 1px solid #E5E7EB;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #F8FAFC;
            font-weight: 600;
            color: #1E293B;
        }
        tr:hover {
            background: #BFDBFE;
            transition: background 0.2s ease;
        }
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            .quick-report-btn {
                width: 100%;
                justify-content: center;
            }
        }
        .mobile-menu-toggle {
            display: none;
        }
        @media (max-width: 1024px) {
            .mobile-menu-toggle {
                display: block;
                padding: 10px;
                font-size: 24px;
                cursor: pointer;
                color: #1E293B;
                position: fixed;
                top: 16px;
                left: 16px;
                z-index: 110;
                background: #FFFFFF;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2 class="text-2xl font-bold text-center mb-10 text-white"><i class="fas fa-user-shield mr-2"></i> <?php echo htmlspecialchars($admin_name); ?></h2>
            <nav class="space-y-3">
                <a href="admin_dashboard.php" class="sidebar-item"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
                <a href="admin_statuses.php" class="sidebar-item"><i class="fas fa-tags mr-2"></i> Durum Yönetimi</a>
                <a href="ai_analysis.php" class="sidebar-item active"><i class="fas fa-brain mr-2"></i> AI Analizi</a>
                <a href="assign.php" class="sidebar-item"><i class="fas fa-random mr-2"></i> Müşteri Dağıtımı</a>
                <a href="admin_chat.php" class="sidebar-item"><i class="fas fa-envelope mr-2"></i> Mesajlar</a>
                <a href="logout.php" class="sidebar-item text-red-300 hover:bg-red-50 hover:text-red-600"><i class="fas fa-sign-out-alt mr-2"></i> Çıkış Yap</a>
            </nav>
        </div>
        <!-- Main Content -->
        <div class="main-content">
            <button class="mobile-menu-toggle"><i class="fas fa-bars"></i></button>
            <div class="analysis-card">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-brain mr-2 text-primary"></i> AI Analiz ve Raporlama
                </h2>
                <div class="space-y-6">
                    <!-- Form -->
                    <form id="analysisForm" method="POST" class="space-y-4">
                        <textarea name="question" class="w-full p-4 border border-gray-200 rounded-lg focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 resize-y bg-neutral/50" rows="5" placeholder="Soru sor veya rapor talebinde bulun (Örn: 'Müşteri durum dağılımı hakkında bir rapor oluştur' veya 'Neden cevapsız müşteriler artıyor?')"></textarea>
                        <div class="flex flex-wrap gap-4">
                            <button type="submit" class="quick-report-btn">
                                <i class="fas fa-play mr-2"></i> Analizi Başlat
                            </button>
                            <button type="button" class="quick-report-btn" onclick="document.querySelector('textarea[name=question]').value='Müşteri durum dağılıمı hakkında bir rapor oluştur';document.getElementById('analysisForm').submit();">
                                <i class="fas fa-chart-pie mr-2"></i> Müşteri Durum Raporu
                            </button>
                            <button type="button" class="quick-report-btn" onclick="document.querySelector('textarea[name=question]').value='Çalışan performansı hakkında bir rapor oluştur';document.getElementById('analysisForm').submit();">
                                <i class="fas fa-user-tie mr-2"></i> Çalışan Performans Raporu
                            </button>
                            <button type="button" class="quick-report-btn" onclick="document.querySelector('textarea[name=question]').value='Cevapsız müşterilerin nedenlerini analiz et';document.getElementById('analysisForm').submit();">
                                <i class="fas fa-question-circle mr-2"></i> Cevapsız Müşteri Analizi
                            </button>
                        </div>
                    </form>
                    <!-- Modal -->
                    <div id="analysisModal" class="modal">
                        <div class="modal-content">
                            <div class="spinner"></div>
                            <h3 class="text-xl font-semibold text-gray-900">Jari Analiz</h3>
                            <p class="text-gray-600 mt-2">Lütfen bekleyin, analiz yapılıyor...</p>
                        </div>
                    </div>
                    <!-- Result -->
                    <?php if (!empty($responseText)): ?>
                        <div class="result-box mt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-check-circle mr-2 text-green-500"></i> Sonuç
                            </h3>
                            <p class="text-gray-700 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($responseText); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Data Sample -->
            <div class="table-container mt-8">
                <div class="table-header">
                    <h2 class="text-xl font-semibold">Veritabanı Örneği</h2>
                </div>
                <div class="p-6">
                    <?php foreach ($allData as $table => $rows): ?>
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-table mr-2 text-primary"></i> <?php echo htmlspecialchars($table); ?>
                            </h3>
                            <?php if (is_array($rows) && count($rows) > 0): ?>
                                <div class="overflow-x-auto">
                                    <table>
                                        <thead>
                                            <tr>
                                                <?php foreach (array_keys($rows[0]) as $column): ?>
                                                    <th><?php echo htmlspecialchars($column); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rows as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?php echo htmlspecialchars($value ?? ''); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-600">Veri yok.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            $('.mobile-menu-toggle').click(function() {
                $('.sidebar').toggleClass('active');
            });

            $('#analysisForm').on('submit', function() {
                $('#analysisModal').css('display', 'flex');
                setTimeout(() => $('#analysisModal').css('display', 'none'), 10000); // Hide modal after 10s if no response
            });
        });
    </script>
</body>
</html>
```