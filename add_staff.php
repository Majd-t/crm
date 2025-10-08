<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

if(isset($_POST['add'])){
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    mysqli_query($conn, "INSERT INTO staff (username,email,password,created_at) 
                        VALUES ('$username','$email','$password',NOW())");

    header("Location: admin_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Yeni Çalışan Ekle</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f8; padding: 20px; }
form { background-color: #fff; padding: 20px; border-radius: 10px; max-width: 500px; margin: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
input, button { padding: 10px; margin-bottom: 10px; width: 100%; border-radius: 5px; border: 1px solid #ccc; }
button { background-color: #2980B9; color: #fff; border: none; cursor: pointer; }
button:hover { background-color: #3498DB; }
h2 { text-align: center; }
</style>
</head>
<body>
<h2>Yeni Çalışan Ekle</h2>
<form method="post">
    İsim: <input type="text" name="username" required>
    E-posta: <input type="email" name="email" required>
    Şifre: <input type="password" name="password" required>
    <button type="submit" name="add">Ekle</button>
</form>
</body>
</html>
