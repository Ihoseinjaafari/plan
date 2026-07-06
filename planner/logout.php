<?php
// planner/logout.php - خروج از حساب کاربری
session_start();
session_destroy();

// هدایت به صفحه ورود (نه index.php که ممکن است غیرفعال باشد)
header('Location: ../auth/login.php');
exit;
?>