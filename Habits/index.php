<?php
// /Habits/index.php - صفحه اصلی ماژول عادت‌ها
session_start();
date_default_timezone_set('Asia/Tehran');

// ==================== بررسی فعال بودن ماژول ====================
$settingsFile = __DIR__ . '/../data/settings.json';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (isset($settings['modules']['habits']['enabled']) && 
        $settings['modules']['habits']['enabled'] === false) {
        header('Location: ../disabled_module.php?module=habits');
        exit;
    }
}

// ==================== بررسی احراز هویت ====================
$usersFile = __DIR__ . '/../data/users.json';

function getUserById($id) {
    global $usersFile;
    if (!file_exists($usersFile)) return null;
    $users = json_decode(file_get_contents($usersFile), true);
    if (!is_array($users)) return null;
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$currentUser = getUserById($_SESSION['user_id']);
if (!$currentUser) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$_SESSION['user_name'] = $currentUser['name'];
$userId = $_SESSION['user_id'];

$page_title = 'عادت‌ها';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | Life+</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="habits-module">
        <!-- ناوبری داخلی -->
        <nav class="habits-nav">
            <div class="habits-nav-content">
                <div class="nav-links">
                    <a href="index.php?page=dashboard" class="nav-link active" data-page="dashboard">
                        <span class="icon icon-home"></span>
                        داشبورد
                    </a>
                    <a href="index.php?page=reminders" class="nav-link" data-page="reminders">
                        <span class="icon icon-bell"></span>
                        یادآورها
                    </a>
                    <a href="index.php?page=focus" class="nav-link" data-page="focus">
                        <span class="icon icon-clock"></span>
                        تمرکز
                    </a>
                    <a href="index.php?page=routines" class="nav-link" data-page="routines">
                        <span class="icon icon-calendar"></span>
                        روتین‌ها
                    </a>
                    <a href="index.php?page=analytics" class="nav-link" data-page="analytics">
                        <span class="icon icon-chart"></span>
                        آنالیتیکس
                    </a>
                </div>
            </div>
        </nav>

        <!-- محتوای اصلی -->
        <main class="habits-content">
            <?php
            $page = $_GET['page'] ?? 'dashboard';
            $pageFile = __DIR__ . "/pages/{$page}.php";
            
            if ($page === 'dashboard') {
                include __DIR__ . '/pages/dashboard.php';
            } elseif (file_exists($pageFile)) {
                include $pageFile;
            } else {
                echo '<div class="error-page">صفحه مورد نظر یافت نشد.</div>';
            }
            ?>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>
