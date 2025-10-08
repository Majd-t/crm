<?php
// معلومات الاتصال بقاعدة البيانات
$host = "localhost";      // عادة localhost
$user = "root";           // اسم المستخدم الخاص بـ MySQL
$password = "";           // كلمة المرور (في XAMPP عادة فارغة)
$database = "crm";        // اسم قاعدة البيانات

// إنشاء الاتصال
$conn = mysqli_connect($host, $user, $password, $database);

// التحقق من الاتصال
if (!$conn) {
    die("Bağlantı hatası: " . mysqli_connect_error());
}

// echo "Bağlantı başarılı"; // يمكن تفعيلها للاختبار
?>
