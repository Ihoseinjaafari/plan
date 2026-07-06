<?php
// notifications.php - صفحه نمایش اعلان‌ها به صورت منوی آبشاری

session_start();

// تعیین مسیر پایه
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
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

// دریافت اعلان‌ها از فایل JSON
$notificationsFile = BASE_PATH . '/data/notifications.json';
$notifications = [];

if (file_exists($notificationsFile)) {
    $notifications = json_decode(file_get_contents($notificationsFile), true);
    if (!is_array($notifications)) {
        $notifications = [];
    }
    
    // مرتب‌سازی بر اساس تاریخ (جدیدترین اول)
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// محاسبه تعداد اعلان‌های خوانده نشده
$unreadCount = 0;
$userUnreadKey = 'notifications_unread_' . $_SESSION['user_id'];
if (isset($_SESSION[$userUnreadKey])) {
    $unreadCount = count($_SESSION[$userUnreadKey]);
} else {
    // اگر session نبود، همه اعلان‌های جدید را خوانده نشده فرض کن
    $unreadCount = count($notifications);
}

$page_title = 'اعلان‌ها';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | Life+</title>
    
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-primary: #0f0c29;
            --bg-secondary: #302b63;
            --bg-card: rgba(255,255,255,0.08);
            --text-primary: #ffffff;
            --text-secondary: rgba(255,255,255,0.85);
            --text-muted: rgba(255,255,255,0.5);
            --border-color: rgba(255,255,255,0.1);
            --badge-color: #667eea;
            --badge-bg: rgba(102,126,234,0.15);
            --dropdown-bg: #1a1a2e;
            --dropdown-hover: rgba(255,255,255,0.08);
        }

        [data-theme="light"] {
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --bg-card: rgba(255,255,255,0.95);
            --text-primary: #1a1a2e;
            --text-secondary: #333333;
            --text-muted: #6c757d;
            --border-color: #e2e8f0;
            --dropdown-bg: #ffffff;
            --dropdown-hover: rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary), var(--bg-secondary));
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px;
        }

        .notifications-container {
            max-width: 600px;
            margin: 80px auto 20px;
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .notifications-header h2 {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mark-all-read {
            background: var(--badge-bg);
            color: var(--badge-color);
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 13px;
            transition: all 0.3s;
        }

        .mark-all-read:hover {
            background: var(--badge-color);
            color: white;
        }

        .notification-item {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            position: relative;
        }

        .notification-item:hover {
            background: rgba(255,255,255,0.08);
            transform: translateX(-5px);
        }

        .notification-item.unread {
            border-right: 3px solid var(--badge-color);
            background: rgba(102,126,234,0.08);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .notification-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .notification-time {
            font-size: 12px;
            color: var(--text-muted);
        }

        .notification-message {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .notification-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
        }

        .type-info { background: rgba(102,126,234,0.2); color: #667eea; }
        .type-success { background: rgba(46,204,113,0.2); color: #2ecc71; }
        .type-warning { background: rgba(241,196,15,0.2); color: #f1c40f; }
        .type-error { background: rgba(231,76,60,0.2); color: #e74c3c; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--badge-bg);
            color: var(--badge-color);
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            margin-top: 20px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .back-btn:hover {
            background: var(--badge-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="notifications-container">
        <div class="notifications-header">
            <h2>
                <i class="fas fa-bell"></i>
                اعلان‌ها
                <?php if ($unreadCount > 0): ?>
                    <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;"><?= $unreadCount ?></span>
                <?php endif; ?>
            </h2>
            <?php if ($unreadCount > 0): ?>
                <button class="mark-all-read" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i>
                    علامت‌گذاری همه به عنوان خوانده شده
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-bell-slash"></i></div>
                <p>هیچ اعلانی وجود ندارد</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): 
                $isUnread = !isset($_SESSION['read_notifications'][$_SESSION['user_id']]) || 
                           !in_array($notif['id'], $_SESSION['read_notifications'][$_SESSION['user_id']]);
            ?>
                <div class="notification-item <?= $isUnread ? 'unread' : '' ?>">
                    <div class="notification-header">
                        <span class="notification-title"><?= htmlspecialchars($notif['title']) ?></span>
                        <span class="notification-time"><?= time_ago(strtotime($notif['created_at'])) ?></span>
                    </div>
                    <div class="notification-message"><?= nl2br(htmlspecialchars($notif['message'])) ?></div>
                    <?php if (isset($notif['type'])): ?>
                        <span class="notification-type type-<?= htmlspecialchars($notif['type']) ?>">
                            <?= get_type_label($notif['type']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="<?= BASE_URL ?>/dashboard/index.php" class="back-btn">
                <i class="fas fa-arrow-right"></i>
                بازگشت به داشبورد
            </a>
        </div>
    </div>

    <script>
        function markAllAsRead() {
            fetch('<?= BASE_URL ?>/api/notifications.php?action=mark_all_read', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    </script>
</body>
</html>

<?php
function time_ago($timestamp) {
    $time_ago = time() - $timestamp;
    
    if ($time_ago < 60) {
        return 'همین الان';
    } elseif ($time_ago < 3600) {
        $minutes = floor($time_ago / 60);
        return "$minutes دقیقه پیش";
    } elseif ($time_ago < 86400) {
        $hours = floor($time_ago / 3600);
        return "$hours ساعت پیش";
    } elseif ($time_ago < 604800) {
        $days = floor($time_ago / 86400);
        return "$days روز پیش";
    } else {
        return date('Y/m/d', $timestamp);
    }
}

function get_type_label($type) {
    $labels = [
        'info' => 'اطلاع‌رسانی',
        'success' => 'موفقیت',
        'warning' => 'هشدار',
        'error' => 'خطا'
    ];
    return $labels[$type] ?? 'اعلان';
}
?>
