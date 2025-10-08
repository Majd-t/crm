<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

if(isset($_GET['id'])){
    $id = $_GET['id'];

    if(isset($_POST['update'])){
        $username = $_POST['username'];
        $email = $_POST['email'];

        mysqli_query($conn, "UPDATE staff SET username='$username', email='$email' WHERE id='$id'");
        header("Location: admin_dashboard.php");
        exit;
    }

    $result = mysqli_query($conn, "SELECT * FROM staff WHERE id='$id'");
    $staff = mysqli_fetch_assoc($result);
} else {
    header("Location: admin_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Çalışan Düzenle</title>
</head>
<body>
    <h2>Çalışan Düzenle</h2>
    <form method="post">
        İsim: <input type="text" name="username" value="<?php echo $staff['username']; ?>" required><br><br>
        E-posta: <input type="email" name="email" value="<?php echo $staff['email']; ?>" required><br><br>
        <button type="submit" name="update">Güncelle</button>
    </form>
</body>
</html>
