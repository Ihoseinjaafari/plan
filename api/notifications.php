<?php
// api/notifications.php - API برای مدیریت اعلان‌ها

session_start();
header('Content-Type: application/json');

$basePath = '';
$scriptName = $_SERVER['SCRIPT_NAME'];

if (strpos($scriptName, '/lifeplus/lifeplus/') !== false) {
    $basePath = '/lifeplus/lifeplus';
} elseif (strpos($scriptName, '/lifeplus/') !== false) {
    $basePath = '/lifeplus';
} else {
    $basePath = '';
}

define('BASE_URL', $basePath);
define('BASE_PATH', dirname(__DIR__));

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'لطفاً وارد شوید']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    if ($action === 'mark_all_read') {
        // علامت‌گذاری همه اعلان‌ها به عنوان خوانده شده
        if (!isset($_SESSION['read_notifications'])) {
            $_SESSION['read_notifications'] = [];
        }
        
        $notificationsFile = BASE_PATH . '/data/notifications.json';
        if (file_exists($notificationsFile)) {
            $notifications = json_decode(file_get_contents($notificationsFile), true);
            if (is_array($notifications)) {
                foreach ($notifications as $notif) {
                    if (!in_array($notif['id'], $_SESSION['read_notifications'][$_SESSION['user_id']] ?? [])) {
                        $_SESSION['read_notifications'][$_SESSION['user_id']][] = $notif['id'];
                    }
                }
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'mark_read') {
        // علامت‌گذاری یک اعلان خاص به عنوان خوانده شده
        $notificationId = $_POST['id'] ?? null;
        
        if ($notificationId) {
            if (!isset($_SESSION['read_notifications'])) {
                $_SESSION['read_notifications'] = [];
            }
            if (!isset($_SESSION['read_notifications'][$_SESSION['user_id']])) {
                $_SESSION['read_notifications'][$_SESSION['user_id']] = [];
            }
            
            if (!in_array($notificationId, $_SESSION['read_notifications'][$_SESSION['user_id']])) {
                $_SESSION['read_notifications'][$_SESSION['user_id']][] = $notificationId;
            }
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'شناسه اعلان مشخص نشده']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'عملیات نامعتبر']);
?>
