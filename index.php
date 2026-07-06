<?php
// index.php - صفحه اصلی (منوی ورود/ثبت‌نام یا هدایت به داشبورد)
session_start();
date_default_timezone_set('Asia/Tehran');

$settingsFile = 'data/settings.json';

// ==================== بررسی فعال بودن ماژول home ====================
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (isset($settings['modules']['home']['enabled']) &&
        $settings['modules']['home']['enabled'] === false) {
        // اگر منوی اصلی غیرفعال است، مستقیماً به داشبورد یا لاگین هدایت شود
        if (isset($_SESSION['user_id'])) {
            header('Location: dashboard/index.php');
        } else {
            header('Location: auth/login.php');
        }
        exit;
    }
}

// ==================== اگر کاربر لاگین است، هدایت به داشبورد ====================
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/index.php');
    exit;
}

// ==================== در غیر این صورت، هدایت به صفحه ورود ====================
header('Location: auth/login.php');
exit;
?>
