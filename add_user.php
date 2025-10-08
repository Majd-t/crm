<?php
session_start();
include 'db.php';

// تأكد أن الأدمن مسجل دخول
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

if(isset($_POST['add'])){
    $name = trim($_POST['username']); // اسم العميل
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $status = trim($_POST['status']); // 'active' أو 'inactive'
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // تشفير كلمة المرور

    // تحقق من أن البريد غير موجود مسبقًا
    $check = "SELECT * FROM customers WHERE email='$email'";
    $result_check = $conn->query($check);
    if($result_check->num_rows > 0){
        $error = "Bu email zaten kayıtlı."; // البريد موجود
    } else {
        // إدراج العميل الجديد
        $sql = "INSERT INTO customers (name,email,phone,status,password,created_at) 
                VALUES ('$name','$email','$phone','$status','$password',NOW())";
        if($conn->query($sql)){
            $success = "Müşteri başarıyla eklendi."; // تمت الإضافة بنجاح
        } else {
            $error = "Hata: " . $conn->error; // خطأ في الإدراج
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Yeni Müşteri Ekle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f8; padding: 20px; }
form { background-color: #fff; padding: 20px; border-radius: 10px; max-width: 500px; margin: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
input, select, button { padding: 10px; margin-bottom: 10px; width: 100%; border-radius: 5px; border: 1px solid #ccc; }
button { background-color: #27AE60; color: #fff; border: none; cursor: pointer; }
button:hover { background-color: #2ECC71; }
h2 { text-align: center; margin-bottom: 20px; }
</style>
</head>
<body>
<div class="container mt-5">
    <h2>Yeni Müşteri Ekle</h2>

    <?php if(isset($error)){ ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php } ?>
    <?php if(isset($success)){ ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php } ?>

    <form method="post">
        <div class="mb-3">
            <label for="username" class="form-label">İsim</label>
            <input type="text" name="username" class="form-control" id="username" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">E-posta</label>
            <input type="email" name="email" class="form-control" id="email" required>
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Telefon</label>
            <input type="text" name="phone" class="form-control" id="phone">
        </div>

        <div class="mb-3">
            <label for="status" class="form-label">Durum</label>
            <select name="status" id="status" class="form-select" required>
                <option value="active">Aktif</option>
                <option value="inactive">Pasif</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Şifre</label>
            <input type="password" name="password" class="form-control" id="password" required>
        </div>

        <button type="submit" name="add" class="btn btn-success">Ekle</button>
        <a href="admin_dashboard.php" class="btn btn-secondary">Geri</a>
    </form>
</div>
</body>
</html>
