<?php
session_start();
include 'db.php';
include 'notification_helper.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

// جلب الموظفين
$staff_result = mysqli_query($conn, "SELECT * FROM staff ORDER BY username ASC");

// جلب العملاء
$all_clients_res = mysqli_query($conn, "SELECT * FROM customers ORDER BY name ASC");
$all_clients = mysqli_fetch_all($all_clients_res, MYSQLI_ASSOC);

// --- التوزيع اليدوي ---
if(isset($_POST['manual_assign'])){
    $staff_id = intval($_POST['staff_id']);
    $clients = isset($_POST['clients']) ? $_POST['clients'] : [];
    if(!empty($clients)){
        $stmt = $conn->prepare("UPDATE customers SET assigned_staff_id = ? WHERE id = ?");
        foreach($clients as $client_id){
            $client_id = intval($client_id);
            $stmt->bind_param('ii', $staff_id, $client_id);
            $stmt->execute();
            // إضافة إشعار للموظف
            $client_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM customers WHERE id = $client_id"))['name'];
            $staff_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM staff WHERE id = $staff_id"))['username'];
            addNotification($conn, 'staff', $staff_id, 'client_assigned', "Yeni müşteri atandı: $client_name", $client_id);
        }
        $stmt->close();
        $message = "Müşteriler başarıyla atandı!";
    } else {
        $message = "Lütfen en az bir müşteri seçin.";
    }
}

// --- سحب العملاء من الموظفين ---
if(isset($_POST['unassign_clients'])){
    $clients = isset($_POST['unassign_ids']) ? $_POST['unassign_ids'] : [];
    if(!empty($clients)){
        $stmt = $conn->prepare("UPDATE customers SET assigned_staff_id = NULL WHERE id = ?");
        foreach($clients as $client_id){
            $client_id = intval($client_id);
            $stmt->bind_param('i', $client_id);
            $stmt->execute();
        }
        $stmt->close();
        $message = "Seçilen müşteriler başarıyla serbest bırakıldı!";
    } else {
        $message = "Lütfen en az bir müşteri seçin.";
    }
}

// --- التوزيع الآلي ---
if(isset($_POST['auto_assign'])){
    $method = $_POST['method'];
    $staff_ids = $_POST['staff_ids'];
    $unassigned_clients_res = mysqli_query($conn, "SELECT * FROM customers WHERE assigned_staff_id IS NULL ORDER BY id ASC");
    $unassigned_clients = mysqli_fetch_all($unassigned_clients_res, MYSQLI_ASSOC);

    $stmt = $conn->prepare("UPDATE customers SET assigned_staff_id = ? WHERE id = ?");
    if($method == "percentage"){
        $ratios = $_POST['ratio'];
        $total_ratio = array_sum($ratios);
        if ($total_ratio <= 0) {
            $message = "Yüzdeler sıfır olamaz!";
        } else {
            $i = 0;
            foreach($unassigned_clients as $client){
                $assigned_staff = array_keys($ratios)[$i % count($ratios)];
                $stmt->bind_param('ii', $assigned_staff, $client['id']);
                $stmt->execute();
                // إشعار للموظف
                $client_name = $client['name'];
                addNotification($conn, 'staff', $assigned_staff, 'client_assigned', "Yeni müşteri atandı: $client_name", $client['id']);
                $i++;
            }
            $message = "Otomatik dağıtım tamamlandı!";
        }
    } elseif($method == "number"){
        $numbers = $_POST['number'];
        $index = 0;
        foreach($numbers as $staff_id => $num){
            $num = intval($num);
            for($i=0; $i<$num && $index<count($unassigned_clients); $i++){
                $stmt->bind_param('ii', $staff_id, $unassigned_clients[$index]['id']);
                $stmt->execute();
                // إشعار للموظف
                $client_name = $unassigned_clients[$index]['name'];
                addNotification($conn, 'staff', $staff_id, 'client_assigned', "Yeni müşteri atandı: $client_name", $unassigned_clients[$index]['id']);
                $index++;
            }
        }
        $message = "Otomatik dağıtım tamamlandı!";
    } elseif($method == "equal"){
        $i = 0;
        foreach($unassigned_clients as $client){
            $assigned_staff = $staff_ids[$i % count($staff_ids)];
            $stmt->bind_param('ii', $assigned_staff, $client['id']);
            $stmt->execute();
            // إشعار للموظف
            $client_name = $client['name'];
            addNotification($conn, 'staff', $assigned_staff, 'client_assigned', "Yeni müşteri atandı: $client_name", $client['id']);
            $i++;
        }
        $message = "Otomatik dağıtım tamamlandı!";
    }
    $stmt->close();
}

// --- جلب العملاء حسب حالة التوزيع ---
$unassigned_clients_res = mysqli_query($conn, "SELECT * FROM customers WHERE assigned_staff_id IS NULL ORDER BY name ASC");
$unassigned_clients = mysqli_fetch_all($unassigned_clients_res, MYSQLI_ASSOC);

$assigned_clients_res = mysqli_query($conn, "
    SELECT c.*, s.username AS staff_name 
    FROM customers c 
    LEFT JOIN staff s ON c.assigned_staff_id = s.id 
    WHERE c.assigned_staff_id IS NOT NULL 
    ORDER BY c.name ASC
");
$assigned_clients = mysqli_fetch_all($assigned_clients_res, MYSQLI_ASSOC);

// --- جلب الموظفين وعدد العملاء لديهم ---
$staff_with_clients_res = mysqli_query($conn, "
    SELECT s.*, COUNT(c.id) AS client_count 
    FROM staff s 
    LEFT JOIN customers c ON s.id=c.assigned_staff_id 
    GROUP BY s.id
");
$staff_with_clients = mysqli_fetch_all($staff_with_clients_res, MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Müşteri Atama ve Dağıtım</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
.hover-scale { transition: transform 0.3s ease, box-shadow 0.3s ease; }
.hover-scale:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(30, 64, 175, 0.2); }
.blue-button { background: linear-gradient(to right, #1E40AF, #3B82F6); }
.blue-button:hover { background: linear-gradient(to right, #1E3A8A, #2563EB); }
.section-header { background: linear-gradient(to right, #1E40AF, #3B82F6); color: white; }
.custom-shadow { box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15); }
.card { transition: all 0.3s ease; border-radius: 12px; }
.card:hover { transform: translateY(-4px); }
</style>
</head>
<body class="min-h-screen bg-gradient-to-b from-[#F5F7FA] to-[#E5E7EB] font-sans">

<div class="flex">
    <!-- Sidebar -->
    <div class="sidebar w-64 h-screen fixed top-0 left-0 p-6 text-white bg-gradient-to-b from-[#1E40AF] to-[#3B82F6]">
        <h2 class="text-2xl font-bold text-center mb-10"><i class="fas fa-user-shield mr-2"></i> <?php echo htmlspecialchars($_SESSION['admin_name']); ?></h2>
        <nav class="space-y-3">
            <a href="admin_dashboard.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
            <a href="admin_statuses.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-tags mr-2"></i> Durum Yönetimi</a>
                            <a href="ai_analysis.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-brain"></i> AI Analizi</a>

            <a href="assign.php" class="block px-4 py-3 rounded-lg text-white bg-white/10 hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-random mr-2"></i> Müşteri Dağıtımı</a>
            <a href="admin_chat.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-envelope mr-2"></i> Mesajlar</a>
            <a href="logout.php" class="block px-4 py-3 rounded-lg text-white hover:bg-white hover:text-blue-900 hover-scale"><i class="fas fa-sign-out-alt mr-2"></i> Çıkış Yap</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="ml-64 p-8 w-full max-w-7xl mx-auto">
        <h1 class="text-4xl font-bold text-gray-900 mb-10">Müşteri Atama ve Dağıtım</h1>

        <?php if(isset($message)){ ?>
            <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-lg shadow"><?php echo $message; ?></div>
        <?php } ?>

        <!-- قسم العملاء غير الموزعين -->
        <section class="mb-12">
            <div class="bg-white custom-shadow p-6 rounded-lg card">
                <h2 class="text-2xl font-semibold mb-4">Dağıtılmamış Müşteriler</h2>
                <input type="text" id="filter_unassigned" onkeyup="filterTable('filter_unassigned','unassignedTable')" placeholder="Müşteri Ara..." class="w-full p-3 mb-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                <div class="overflow-x-auto max-h-96 border border-gray-200 rounded-lg">
                    <table id="unassignedTable" class="w-full text-left border-collapse">
                        <thead class="section-header text-white">
                            <tr>
                                <th class="p-4">ID</th>
                                <th class="p-4">İsim</th>
                                <th class="p-4">E-posta</th>
                                <th class="p-4">Telefon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($unassigned_clients as $client){ ?>
                                <tr class="hover:bg-blue-50 transition">
                                    <td class="p-4 border-b"><?php echo $client['id']; ?></td>
                                    <td class="p-4 border-b"><?php echo htmlspecialchars($client['name']); ?></td>
                                    <td class="p-4 border-b"><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td class="p-4 border-b"><?php echo htmlspecialchars($client['phone']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- قسم العملاء الموزعين -->
        <section class="mb-12">
            <div class="bg-white custom-shadow p-6 rounded-lg card">
                <h2 class="text-2xl font-semibold mb-4">Dağıtılmış Müşteriler</h2>
                <form method="POST">
                    <label class="block mb-2 font-medium">Çalışan Seçin:</label>
                    <select id="staff_filter" class="w-full p-3 mb-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none" onchange="filterStaffClients()">
                        <option value="">-- Tüm Çalışanlar --</option>
                        <?php foreach($staff_with_clients as $staff){ ?>
                            <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['username']); ?> (<?php echo $staff['client_count']; ?>)</option>
                        <?php } ?>
                    </select>

                    <div class="overflow-x-auto max-h-96 border border-gray-200 rounded-lg">
                        <table id="assignedTable" class="w-full text-left border-collapse">
                            <thead class="section-header text-white">
                                <tr>
                                    <th class="p-4">Seç</th>
                                    <th class="p-4">ID</th>
                                    <th class="p-4">İsim</th>
                                    <th class="p-4">E-posta</th>
                                    <th class="p-4">Telefon</th>
                                    <th class="p-4">Atandığı Çalışan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($assigned_clients as $client){ ?>
                                    <tr data-staff="<?php echo $client['assigned_staff_id']; ?>" class="hover:bg-blue-50 transition">
                                        <td class="p-4 border-b text-center"><input type="checkbox" name="unassign_ids[]" value="<?php echo $client['id']; ?>"></td>
                                        <td class="p-4 border-b"><?php echo $client['id']; ?></td>
                                        <td class="p-4 border-b"><?php echo htmlspecialchars($client['name']); ?></td>
                                        <td class="p-4 border-b"><?php echo htmlspecialchars($client['email']); ?></td>
                                        <td class="p-4 border-b"><?php echo htmlspecialchars($client['phone']); ?></td>
                                        <td class="p-4 border-b"><?php echo htmlspecialchars($client['staff_name']); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" name="unassign_clients" class="mt-4 blue-button text-white px-6 py-3 rounded-lg font-medium hover-scale"><i class="fas fa-undo mr-2"></i>Seçilenleri Serbest Bırak</button>
                </form>
            </div>
        </section>

        <!-- التوزيع اليدوي -->
        <section class="mb-12">
            <div class="bg-white custom-shadow p-6 rounded-lg card">
                <h2 class="text-2xl font-semibold mb-4">Manuel Dağıtım</h2>
                <form method="POST" onsubmit="return confirm('Bu dağıtımı yapmak istediğinizden emin misiniz?')">
                    <label class="block mb-2 font-medium">Çalışan Seçin:</label>
                    <select name="staff_id" class="w-full p-3 mb-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <?php foreach($staff_with_clients as $staff){ ?>
                            <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['username']); ?> (<?php echo $staff['client_count']; ?>)</option>
                        <?php } ?>
                    </select>

                    <label class="block mb-2 font-medium">Müşteriler (Çoklu Seçim):</label>
                    <div class="border p-4 max-h-96 overflow-y-auto mb-4 rounded-lg bg-gray-50">
                        <label class="flex items-center mb-2"><input type="checkbox" id="select_all_clients" class="mr-2"> Tümünü Seç</label>
                        <?php foreach($unassigned_clients as $client){ ?>
                            <label class="flex items-center mb-1"><input type="checkbox" name="clients[]" value="<?php echo $client['id']; ?>" class="mr-2"> <?php echo htmlspecialchars($client['name']); ?></label>
                        <?php } ?>
                    </div>

                    <button type="submit" name="manual_assign" class="blue-button text-white px-6 py-3 rounded-lg font-medium hover-scale"><i class="fas fa-hand-pointer mr-2"></i>Atamayı Başlat</button>
                </form>
            </div>
        </section>

        <!-- التوزيع الآلي -->
        <section class="mb-12">
            <div class="bg-white custom-shadow p-6 rounded-lg card">
                <h2 class="text-2xl font-semibold mb-4">Otomatik Dağıtım</h2>
                <button onclick="openModal()" class="blue-button text-white px-6 py-3 rounded-lg font-medium hover-scale"><i class="fas fa-robot mr-2"></i>Otomatik Dağıtımı Aç</button>

                <div id="autoModal" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center z-50">
                    <div class="bg-white p-8 rounded-lg w-full max-w-lg shadow-2xl">
                        <h3 class="text-2xl font-bold mb-6">Otomatik Dağıtım</h3>
                        <form method="POST" onsubmit="return confirm('Bu dağıtımı yapmak istediğinizden emin misiniz?')">
                            <label class="block mb-2 font-medium">Yöntem Seçin:</label>
                            <select name="method" id="method_select" class="w-full p-3 mb-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none" onchange="showMethodParams()">
                                <option value="percentage">Yüzdelik Dağıtım</option>
                                <option value="number">Belirli Sayı Dağıtımı</option>
                                <option value="equal">Eşit Dağıtım</option>
                            </select>

                            <div id="method_params">
                                <?php foreach($staff_with_clients as $staff){ ?>
                                    <div class="flex items-center mb-2 method_param">
                                        <span class="w-40 font-medium"><?php echo htmlspecialchars($staff['username']); ?></span>
                                        <input type="number" name="ratio[<?php echo $staff['id']; ?>]" placeholder="Yüzde" class="w-24 p-2 border rounded-lg ratio_input focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        <input type="number" name="number[<?php echo $staff['id']; ?>]" placeholder="Sayı" class="w-24 p-2 border rounded-lg number_input hidden focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        <input type="checkbox" name="staff_ids[]" value="<?php echo $staff['id']; ?>" checked class="ml-4">
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="flex justify-end gap-4 mt-6">
                                <button type="button" onclick="closeModal()" class="bg-gray-400 text-white px-6 py-3 rounded-lg hover:bg-gray-500">İptal</button>
                                <button type="submit" name="auto_assign" class="blue-button text-white px-6 py-3 rounded-lg hover-scale">Başlat</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
function filterTable(inputId, tableId){
    var input = document.getElementById(inputId).value.toUpperCase();
    var table = document.getElementById(tableId);
    var tr = table.getElementsByTagName("tr");
    for(var i=1;i<tr.length;i++){
        var tds = tr[i].getElementsByTagName("td");
        var show = false;
        for(var j=0;j<tds.length;j++){
            if(tds[j].innerText.toUpperCase().indexOf(input)>-1){
                show = true; break;
            }
        }
        tr[i].style.display = show ? "" : "none";
    }
}

function filterStaffClients(){
    var staffId = document.getElementById('staff_filter').value;
    var rows = document.querySelectorAll('#assignedTable tbody tr');
    rows.forEach(row => {
        if(staffId === "" || row.dataset.staff === staffId){
            row.style.display = "";
        } else { row.style.display = "none"; }
    });
}

// --- تحديد الكل للعملاء اليدوي ---
document.getElementById('select_all_clients').addEventListener('change', function(){
    var checkboxes = document.querySelectorAll('input[name="clients[]"]');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// --- قوائم منبثقة للتوزيع الآلي ---
function openModal(){ document.getElementById('autoModal').classList.remove('hidden'); document.getElementById('autoModal').classList.add('flex'); }
function closeModal(){ document.getElementById('autoModal').classList.remove('flex'); document.getElementById('autoModal').classList.add('hidden'); }

function showMethodParams(){
    var method = document.getElementById('method_select').value;
    document.querySelectorAll('.method_param').forEach(row=>{
        row.querySelector('.ratio_input').classList.toggle('hidden', method !== 'percentage');
        row.querySelector('.number_input').classList.toggle('hidden', method !== 'number');
    });
}
showMethodParams();
</script>

</body>
</html>