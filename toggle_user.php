<?php
session_start();
include 'db.php';
if(!isset($_SESSION['admin_logged_in'])){
    header("Location: login.php"); 
    exit;
}

// التحقق من وجود عمود inactive_until وإضافته إذا لم يكن موجودًا
$check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'inactive_until'");
if(mysqli_num_rows($check) == 0){
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN inactive_until DATETIME DEFAULT NULL");
}

if(isset($_GET['id']) && isset($_GET['type'])){
    $id = $_GET['id'];
    $days = isset($_GET['days']) ? intval($_GET['days']) : 0;
    $type = $_GET['type'];

    if($type=="user"){
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT account_status FROM users WHERE id='$id'"));
        if($user['account_status']=="active"){
            $inactive_until = date('Y-m-d H:i:s', strtotime("+$days days"));
            mysqli_query($conn, "UPDATE users SET account_status='inactive', inactive_until='$inactive_until' WHERE id='$id'");
        } else {
            mysqli_query($conn, "UPDATE users SET account_status='active', inactive_until=NULL WHERE id='$id'");
        }
    }

    header("Location: admin_dashboard.php");
    exit;
}
?>
