<?php
session_start();
include 'db.php';

$error = "";

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    // --- دالة للتحقق من كلمة السر بأي طريقة ---
    function check_password($input, $db_password){
        if(password_verify($input, $db_password)){ // password_hash
            return true;
        } elseif(md5($input) === $db_password){ // MD5
            return true;
        } elseif($input === $db_password){ // نص عادي
            return true;
        }
        return false;
    }

    // --- Admin ---
    $admin_result = mysqli_query($conn, "SELECT * FROM admin WHERE email='$email'");
    if(mysqli_num_rows($admin_result) > 0){
        $admin = mysqli_fetch_assoc($admin_result);
        if(check_password($password, $admin['password'])){
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_name'] = $admin['username'];
            header("Location: admin_dashboard.php");
            exit;
        }
    }

    // --- Staff ---
    $staff_result = mysqli_query($conn, "SELECT * FROM staff WHERE email='$email'");
    if(mysqli_num_rows($staff_result) > 0){
        $staff = mysqli_fetch_assoc($staff_result);

        // التحقق من انتهاء مدة التعطيل
        if($staff['account_status'] != "active"){
            if($staff['inactive_until'] && strtotime($staff['inactive_until']) < time()){
                mysqli_query($conn, "UPDATE staff SET account_status='active', inactive_until=NULL WHERE id=".$staff['id']);
                $staff['account_status'] = "active";
            } else {
                $error = "Bu hesap ".$staff['inactive_until']." tarihine kadar devre dışı bırakılmıştır.";
            }
        }

        if($staff['account_status']=="active" && check_password($password, $staff['password'])){
            $_SESSION['staff_logged_in'] = true;
            $_SESSION['staff_name'] = $staff['username'];
            $_SESSION['staff_id'] = $staff['id'];
            header("Location: staff_dashboard.php");
            exit;
        }
    }

    // --- Users ---
    $user_result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if(mysqli_num_rows($user_result) > 0){
        $user = mysqli_fetch_assoc($user_result);

        // التحقق من انتهاء مدة التعطيل
        if($user['account_status'] != "active"){
            if($user['inactive_until'] && strtotime($user['inactive_until']) < time()){
                mysqli_query($conn, "UPDATE users SET account_status='active', inactive_until=NULL WHERE id=".$user['id']);
                $user['account_status'] = "active";
            } else {
                $error = "Bu hesap ".$user['inactive_until']." tarihine kadar devre dışı bırakılmıştır.";
            }
        }

        if($user['account_status']=="active" && check_password($password, $user['password'])){
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            header("Location: client_dashboard.php");
            exit;
        }
    }

    if(empty($error)){
        $error = "E-posta veya şifre hatalı.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Giriş</title>
<style>
body { font-family: Arial; background-color:#f4f6f8; display:flex; justify-content:center; align-items:center; height:100vh; }
form { background-color:#fff; padding:30px; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,0.1); width:350px; }
input { width:100%; padding:10px; margin-bottom:15px; border-radius:5px; border:1px solid #ccc; }
button { width:100%; padding:10px; border:none; border-radius:5px; background-color:#2980B9; color:#fff; cursor:pointer; }
button:hover { background-color:#3498DB; }
.error { color:red; margin-bottom:10px; text-align:center; }
</style>
</head>
<body>
<form method="post">
    <h2 style="text-align:center;">Giriş Yap</h2>
    <?php if($error != "") echo "<div class='error'>$error</div>"; ?>
    E-posta: <input type="email" name="email" required>
    Şifre: <input type="password" name="password" required>
    <button type="submit" name="login">Giriş</button>
</form>
</body>
</html>
