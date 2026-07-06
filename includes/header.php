<?php
// includes/header.php - هدر یکپارچه برای کل سایت

// ==================== شروع session ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== تعیین BASE_URL ====================
// مسیر پایه پروژه را به صورت خودکار تشخیص بده
$basePath = '';
$scriptName = $_SERVER['SCRIPT_NAME'];

if (strpos($scriptName, '/lifeplus/lifeplus/') !== false) {
    $basePath = '/lifeplus/lifeplus';
} elseif (strpos($scriptName, '/lifeplus/') !== false) {
    $basePath = '/lifeplus';
} else {
    // اگر در ریشه بود یا مسیر دیگری
    $basePath = '';
}

// اگر قبلاً تعریف نشده بود، تعریف کن
if (!defined('BASE_URL')) {
    define('BASE_URL', $basePath);
}

// تنظیم مسیر مطلق برای هدر
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// تنظیم عنوان صفحه
if (!isset($page_title)) $page_title = 'Life+';

// ==================== تابع بررسی فعال بودن ماژول‌ها ====================
function isModuleEnabled($moduleName) {
    $settingsFile = BASE_PATH . '/data/settings.json';
    if (!file_exists($settingsFile)) {
        return true; // اگر فایل تنظیمات نبود، همه ماژول‌ها فعال باشند
    }
    
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!isset($settings['modules'][$moduleName])) {
        return true; // اگر ماژول در تنظیمات نبود، فعال فرض شود
    }
    
    return isset($settings['modules'][$moduleName]['enabled']) && 
           $settings['modules'][$moduleName]['enabled'] === true;
}

// ==================== دریافت لیست ماژول‌های فعال ====================
function getEnabledModules() {
    $settingsFile = BASE_PATH . '/data/settings.json';
    if (!file_exists($settingsFile)) {
        return ['home', 'planner', 'projects', 'lifeplan', 'vision', 'finance', 'health', 'calendar', 'dashboard'];
    }
    
    $settings = json_decode(file_get_contents($settingsFile), true);
    $enabledModules = [];
    
    if (isset($settings['modules'])) {
        foreach ($settings['modules'] as $key => $module) {
            if (isset($module['enabled']) && $module['enabled'] === true) {
                $enabledModules[] = $key;
            }
        }
    }
    
    return $enabledModules;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | Life+</title>
    
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    
    <!-- Font Awesome برای آیکون‌ها -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ===== آیکون‌های جایگزین با CSS ===== */
        .icon-seedling::before { content: "🌱"; }
        .icon-home::before { content: "🏠"; }
        .icon-bell::before { content: "🔔"; }
        .icon-moon::before { content: "🌙"; }
        .icon-sun::before { content: "☀️"; }
        .icon-user::before { content: "👤"; }
        .icon-cog::before { content: "⚙️"; }
        .icon-tasks::before { content: "📋"; }
        .icon-compass::before { content: "🧭"; }
        .icon-project::before { content: "📊"; }
        .icon-calendar::before { content: "📅"; }
        .icon-logout::before { content: "🚪"; }
        .icon-login::before { content: "🔑"; }
        .icon-bars::before { content: "☰"; }
        .icon-search::before { content: "🔍"; }
        .icon-message::before { content: "💬"; }
        .icon-heart::before { content: "❤️"; }
        .icon-chart::before { content: "📈"; }
        .icon-lifeplan::before { content: "🧭"; }
        .icon-fire::before { content: "🔥"; }
        .icon-edit::before { content: "✏️"; }
        .icon-trash::before { content: "🗑️"; }
        .icon-tag::before { content: "🏷️"; }
        .icon-file-csv::before { content: "📊"; }
        .icon-shield-alt::before { content: "🛡️"; }
        .icon-close::before { content: "❌"; }
        .icon-filter::before { content: "🔽"; }
        .icon-calendar-day::before { content: "📆"; }
        .icon-calendar-plus::before { content: "📅➕"; }
        .icon-calendar-week::before { content: "📅📅"; }
        .icon-calendar-minus::before { content: "📅➖"; }
        .icon-check-circle::before { content: "✅"; }
        .icon-list::before { content: "📝"; }
        .icon-clock::before { content: "⏰"; }
        .icon-align-left::before { content: "📄"; }
        .icon-grip-vertical::before { content: "⋮⋮"; }
        .icon-heart-pulse::before { content: "💓"; }
        .icon-plus::before { content: "➕"; }
        .icon-save::before { content: "💾"; }
        .icon-chart-line::before { content: "📈"; }
        .icon-info-circle::before { content: "ℹ️"; }
        .icon-sitemap::before { content: "🌳"; }
        
        /* کلاس‌های آیکون */
        .icon {
            display: inline-block;
            font-size: inherit;
            line-height: 1;
        }
        
        .icon-btn .icon {
            font-size: 1.4rem;
        }
        
        .hamburger-btn .icon {
            font-size: 1.6rem;
        }
        
        .dropdown-item .icon {
            width: 20px;
            font-size: 16px;
        }
        
        .header h1 .icon {
            font-size: 24px;
        }

        /* ===== متغیرهای CSS ===== */
        :root {
            --bg-primary: #0f0c29;
            --bg-secondary: #302b63;
            --bg-card: rgba(255,255,255,0.08);
            --bg-card-hover: rgba(255,255,255,0.15);
            --text-primary: #ffffff;
            --text-secondary: rgba(255,255,255,0.85);
            --text-muted: rgba(255,255,255,0.5);
            --border-color: rgba(255,255,255,0.1);
            --shadow-color: rgba(0,0,0,0.2);
            --badge-color: #667eea;
            --badge-bg: rgba(102,126,234,0.15);
            --dropdown-bg: #1a1a2e;
            --dropdown-text: #ffffff;
            --dropdown-hover: rgba(255,255,255,0.08);
            --btn-color: #ffffff;
            --btn-hover-bg: rgba(255,255,255,0.15);
            --header-bg: rgba(255,255,255,0.05);
            --bg-input: rgba(255,255,255,0.05);
            --today-bg: #f1c40f;
            --today-text: #1a1a2e;
            --scrollbar-track: transparent;
            --scrollbar-thumb: rgba(255,255,255,0.2);
            --modal-overlay: rgba(0,0,0,0.7);
            --toast-bg: #1a1a2e;
            --calendar-bg: #1a1a2e;
            --calendar-hover: rgba(102,126,234,0.2);
            --calendar-selected: #667eea;
            --calendar-today: #f1c40f;
            --task-hover: rgba(255,255,255,0.05);
            --mobile-menu-bg: rgba(15, 12, 41, 0.98);
        }

        /* ===== تم روشن ===== */
        [data-theme="light"] {
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --bg-card: rgba(255,255,255,0.95);
            --bg-card-hover: rgba(0,0,0,0.03);
            --text-primary: #1a1a2e;
            --text-secondary: #333333;
            --text-muted: #6c757d;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0,0,0,0.08);
            --dropdown-bg: #ffffff;
            --dropdown-text: #1a1a2e;
            --dropdown-hover: rgba(0,0,0,0.05);
            --btn-color: #333333;
            --btn-hover-bg: rgba(0,0,0,0.05);
            --header-bg: #ffffff;
            --bg-input: #fafafa;
            --today-bg: #f1c40f;
            --today-text: #1a1a2e;
            --calendar-bg: #ffffff;
            --task-hover: rgba(0,0,0,0.03);
            --toast-bg: #1a1a2e;
            --scrollbar-thumb: rgba(0,0,0,0.2);
            --mobile-menu-bg: rgba(240, 242, 245, 0.98);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: linear-gradient(135deg, var(--bg-primary), var(--bg-secondary));
            color: var(--text-primary);
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ===== هدر ===== */
        .header {
            position: relative;
            z-index: 9999;
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 16px 28px;
            margin: 15px 15px 15px 15px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 8px 25px var(--shadow-color);
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .header h1 .icon {
            background: linear-gradient(135deg, #667eea, #f5576c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header h1 a {
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 a:hover {
            color: var(--text-primary);
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* ===== دکمه‌های هدر ===== */
        .icon-btn {
            font-size: 1.4rem;
            color: var(--btn-color) !important;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 6px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            text-decoration: none;
            position: relative;
        }

        .icon-btn:hover {
            color: #667eea !important;
            background: var(--badge-bg);
            transform: scale(1.05);
            border-color: #667eea;
        }

        .theme-toggle { 
            font-size: 1.4rem; 
        }

        /* ===== دکمه منوی موبایل ===== */
        .hamburger-btn {
            display: none;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--btn-color) !important;
            font-size: 1.6rem;
            cursor: pointer;
            padding: 6px;
            border-radius: 10px;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
        }

        .hamburger-btn:hover {
            color: #667eea !important;
            background: var(--badge-bg);
            border-color: #667eea;
        }

        /* ===== آواتار کاربر ===== */
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            flex-shrink: 0;
            border: 2px solid transparent;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(102,126,234,0.3);
            border-color: #667eea;
        }

        /* ===== منوی کشویی ===== */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: auto;
            min-width: 180px;
            background: var(--dropdown-bg);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            border: 1px solid var(--border-color);
            padding: 8px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px) scale(0.96);
            transition: all 0.25s ease;
            z-index: 999999;
            transform-origin: top left;
            pointer-events: none;
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            width: 100%;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            color: var(--dropdown-text);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            transition: all 0.2s ease;
            text-align: right;
            text-decoration: none;
            border-radius: 0;
        }

        .dropdown-item:hover {
            background: var(--dropdown-hover);
            color: #667eea;
        }

        .dropdown-item .icon {
            width: 20px;
            font-size: 16px;
            color: #667eea;
        }

        .dropdown-item.logout-item {
            border-top: 1px solid var(--border-color);
            margin-top: 4px;
            padding-top: 12px;
        }

        .dropdown-item.logout-item .icon {
            color: #dc3545;
        }

        .dropdown-item.logout-item:hover {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
        }

        /* ===== دکمه‌های ناوبری در هدر ===== */
        .nav-btn {
            color: var(--btn-color) !important;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-btn:hover {
            color: #667eea !important;
            background: var(--badge-bg);
            transform: scale(1.02);
            border-color: #667eea;
        }

        .nav-btn .icon {
            font-size: 1.2rem;
        }

        /* ===== منوی موبایل (همبرگری) ===== */
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 99998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .mobile-menu-overlay.active {
            display: block;
            opacity: 1;
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 80%;
            max-width: 320px;
            height: 100vh;
            background: var(--mobile-menu-bg);
            backdrop-filter: blur(20px);
            z-index: 99999;
            padding: 30px 20px;
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            border-left: 1px solid var(--border-color);
            box-shadow: -10px 0 40px rgba(0,0,0,0.3);
        }

        .mobile-menu.open {
            right: 0;
        }

        .mobile-menu .close-btn {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .mobile-menu .close-btn:hover {
            background: var(--badge-bg);
            border-color: #667eea;
            color: #667eea;
        }

        .mobile-menu .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px 0 25px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .mobile-menu .user-info .avatar-large {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }

        .mobile-menu .user-info .user-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .mobile-menu .user-info .user-email {
            font-size: 13px;
            color: var(--text-muted);
        }

        .mobile-menu .menu-items {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .mobile-menu .menu-items a,
        .mobile-menu .menu-items button {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 15px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: none;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: right;
            transition: all 0.3s;
        }

        .mobile-menu .menu-items a:hover,
        .mobile-menu .menu-items button:hover {
            background: var(--bg-card-hover);
            color: #667eea;
        }

        .mobile-menu .menu-items a .icon,
        .mobile-menu .menu-items button .icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
        }

        .mobile-menu .menu-items .logout-mobile {
            border-top: 1px solid var(--border-color);
            margin-top: 10px;
            padding-top: 16px;
            color: #dc3545;
        }

        .mobile-menu .menu-items .logout-mobile:hover {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
        }

        /* ===== ریسپانسیو ===== */
        @media (max-width: 992px) {
            .nav-btn span:not(.icon) {
                display: none;
            }
            .nav-btn {
                padding: 8px 12px;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 12px 18px;
                margin: 10px 10px 20px 10px;
                border-radius: 16px;
                z-index: 9999;
            }

            .header h1 {
                font-size: 20px;
            }

            .hamburger-btn {
                display: flex !important;
            }

            /* مخفی کردن دکمه‌های ناوبری در موبایل */
            .nav-btn {
                display: none !important;
            }

            .header-actions .icon-btn:not(.hamburger-btn):not(.theme-toggle) {
                display: none;
            }

            .header-actions {
                gap: 8px;
            }

            .icon-btn {
                width: 36px;
                height: 36px;
                font-size: 1.2rem;
            }

            .user-avatar {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }

            .dropdown-menu {
                position: fixed;
                top: 80px;
                left: 10px;
                right: 10px;
                min-width: auto;
                width: auto;
                z-index: 999999;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 10px 14px;
                margin: 8px 8px 16px 8px;
                border-radius: 14px;
                z-index: 9999;
            }

            .header h1 {
                font-size: 18px;
            }

            .header h1 .icon {
                font-size: 20px;
            }

            .icon-btn {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }

            .user-avatar {
                width: 30px;
                height: 30px;
                font-size: 12px;
            }

            .dropdown-menu {
                top: 70px;
                left: 8px;
                right: 8px;
                z-index: 999999;
            }

            .mobile-menu {
                width: 85%;
                max-width: 300px;
                padding: 20px 16px;
            }
        }
    </style>
</head>
<body>
    <header class="header" id="mainHeader">
        <!-- لوگو و عنوان -->
        <h1>
            <a href="<?= BASE_URL ?>/auth/login.php">
                <span class="icon icon-seedling"></span>
                <?= htmlspecialchars($page_title) ?>
            </a>
        </h1>

        <!-- دکمه‌های هدر -->
        <div class="header-actions">
            <!-- دکمه منوی اصلی (خانه) -->
            <?php if (isModuleEnabled('home')): ?>
            <a href="<?= BASE_URL ?>/auth/login.php" class="nav-btn" title="منوی اصلی">
                <span class="icon icon-home"></span>
                <span>منوی اصلی</span>
            </a>
            <?php endif; ?>
            
            <!-- دکمه داشبورد -->
            <?php if (isModuleEnabled('dashboard')): ?>
            <a href="<?= BASE_URL ?>/dashboard" class="nav-btn" title="داشبورد">
                <span class="icon icon-home"></span>
                <span>داشبورد</span>
            </a>
            <?php endif; ?>
            
            <!-- دکمه لایف‌ پلن -->
            <?php if (isModuleEnabled('lifeplan')): ?>
            <a href="<?= BASE_URL ?>/lifeplan/index.php" class="nav-btn" title="لایف‌ پلن">
                <span class="icon icon-lifeplan"></span>
                <span>لایف‌ پلن</span>
            </a>
            <?php endif; ?>

            <!-- دکمه مدیریت مالی -->
            <?php if (isModuleEnabled('finance')): ?>
            <a href="<?= BASE_URL ?>/finance/index.php" class="nav-btn" title="مدیریت مالی">
                <span class="icon icon-chart"></span>
                <span>مالی</span>
            </a>
            <?php endif; ?>

            <!-- دکمه تقویم -->
            <?php if (isModuleEnabled('calendar')): ?>
            <a href="<?= BASE_URL ?>/calendar/index.php" class="nav-btn" title="تقویم">
                <span class="icon icon-calendar"></span>
                <span>تقویم</span>
            </a>
            <?php endif; ?>

            <!-- دکمه پروژه‌ها -->
            <?php if (isModuleEnabled('projects')): ?>
            <a href="<?= BASE_URL ?>/projects/index.php" class="nav-btn" title="پروژه‌ها">
                <span class="icon icon-project"></span>
                <span>پروژه‌ها</span>
            </a>
            <?php endif; ?>
            
            <!-- دکمه پلنر -->
            <?php if (isModuleEnabled('planner')): ?>
            <a href="<?= BASE_URL ?>/planner/index.php" class="nav-btn" title="پلنر">
                <span class="icon icon-fire"></span>
                <span>پلنر</span>
            </a>
            <?php endif; ?>

            <!-- دکمه اعلان‌ها با شمارنده -->
            <?php
            $notifCount = 0;
            $notifFile = BASE_PATH . '/data/notifications.json';
            if (file_exists($notifFile)) {
                $allNotifs = json_decode(file_get_contents($notifFile), true);
                if (is_array($allNotifs)) {
                    $readNotifs = $_SESSION['read_notifications'][$_SESSION['user_id']] ?? [];
                    foreach ($allNotifs as $n) {
                        if (!in_array($n['id'], $readNotifs)) {
                            $notifCount++;
                        }
                    }
                }
            }
            ?>
            <button class="icon-btn" id="notificationsBtn" title="اعلان‌ها" style="position: relative;">
                <span class="icon icon-bell"></span>
                <?php if ($notifCount > 0): ?>
                    <span id="notifBadge" style="position: absolute; top: -5px; right: -5px; background: #e74c3c; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; display: flex; align-items: center; justify-content: center; font-weight: bold;"><?php echo $notifCount; ?></span>
                <?php endif; ?>
            </button>

            <!-- دکمه تغییر تم -->
            <button class="icon-btn theme-toggle" id="themeToggle" title="تغییر تم">
                <span class="icon icon-moon" id="themeIcon"></span>
            </button>

            <!-- منوی کاربر -->
            <div class="user-dropdown">
                <div class="user-avatar" id="userAvatar">
                    <?php 
                    if (isset($_SESSION['user_name'])) {
                        echo mb_substr($_SESSION['user_name'], 0, 1);
                    } else {
                        echo 'H';
                    }
                    ?>
                </div>
                <div class="dropdown-menu" id="userDropdown">
                    <!-- فقط پروفایل و خروج -->
                    <a href="<?= BASE_URL ?>/profile.php" class="dropdown-item">
                        <span class="icon icon-user"></span> پروفایل
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button class="dropdown-item logout-item" onclick="logout()">
                            <span class="icon icon-logout"></span> خروج
                        </button>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="dropdown-item">
                            <span class="icon icon-login"></span> ورود
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- دکمه منوی موبایل -->
            <button class="hamburger-btn" id="hamburgerBtn" title="منو">
                <span class="icon icon-bars"></span>
            </button>
        </div>
    </header>

    <!-- ===== منوی موبایل (همبرگری) ===== -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-menu" id="mobileMenu">
        <button class="close-btn" id="mobileMenuClose">
            <i class="fas fa-times"></i>
        </button>

        <!-- اطلاعات کاربر -->
        <div class="user-info">
            <div class="avatar-large">
                <?php 
                if (isset($_SESSION['user_name'])) {
                    echo mb_substr($_SESSION['user_name'], 0, 1);
                } else {
                    echo 'H';
                }
                ?>
            </div>
            <div>
                <div class="user-name">
                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'کاربر مهمان'); ?>
                </div>
                <div class="user-email">
                    <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'guest@lifeplus.com'); ?>
                </div>
            </div>
        </div>

        <!-- آیتم‌های منو -->
        <div class="menu-items">
            <!-- منوی اصلی -->
            <?php if (isModuleEnabled('home')): ?>
            <a href="<?= BASE_URL ?>/auth/login.php">
                <span class="icon icon-home"></span>
                منوی اصلی
            </a>
            <?php endif; ?>
            
            <!-- برنامه ریز -->
            <?php if (isModuleEnabled('planner')): ?>
            <a href="<?= BASE_URL ?>/planner/index.php">
                <span class="icon icon-fire"></span>
                برنامه‌ریز
            </a>
            <?php endif; ?>

            <!-- لایف پلن -->
            <?php if (isModuleEnabled('lifeplan')): ?>
            <a href="<?= BASE_URL ?>/lifeplan/index.php">
                <span class="icon icon-lifeplan"></span>
                لایف‌ پلن
            </a>
            <?php endif; ?>

            <!-- مدیریت مالی -->
            <?php if (isModuleEnabled('finance')): ?>
            <a href="<?= BASE_URL ?>/finance/index.php">
                <span class="icon icon-chart"></span>
                مدیریت مالی
            </a>
            <?php endif; ?>

            <!-- پروژه‌ها -->
            <?php if (isModuleEnabled('projects')): ?>
            <a href="<?= BASE_URL ?>/projects/index.php">
                <span class="icon icon-project"></span>
                پروژه‌ها
            </a>
            <?php endif; ?>

            <!-- تقویم -->
            <?php if (isModuleEnabled('calendar')): ?>
            <a href="<?= BASE_URL ?>/calendar/index.php">
                <span class="icon icon-calendar"></span>
                تقویم
            </a>
            <?php endif; ?>

            <!-- خروج -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <button class="logout-mobile" onclick="logoutMobile()">
                    <span class="icon icon-logout"></span>
                    خروج
                </button>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php">
                    <span class="icon icon-login"></span>
                    ورود
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== اسکریپت‌های هدر ===== -->
    <script>
        // ============================================
        // مدیریت تم
        // ============================================
        const toggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const body = document.body;
        
        function setTheme(theme) {
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            if (themeIcon) {
                themeIcon.className = 'icon ' + (theme === 'dark' ? 'icon-sun' : 'icon-moon');
            }
        }
        
        if (toggle) {
            toggle.addEventListener('click', () => {
                const current = body.getAttribute('data-theme') || 'dark';
                setTheme(current === 'dark' ? 'light' : 'dark');
            });
        }
        
        const saved = localStorage.getItem('theme') || 'dark';
        setTheme(saved);

        // ============================================
        // مدیریت منوی کاربر
        // ============================================
        const userAvatar = document.getElementById('userAvatar');
        const userDropdown = document.getElementById('userDropdown');

        if (userAvatar && userDropdown) {
            userAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!userDropdown.contains(e.target) && e.target !== userAvatar) {
                    userDropdown.classList.remove('show');
                }
            });

            userDropdown.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', function() {
                    userDropdown.classList.remove('show');
                });
            });
        }

        // ============================================
        // دکمه اعلان‌ها
        // ============================================
        document.getElementById('notificationsBtn')?.addEventListener('click', function() {
            window.location.href = '<?= BASE_URL ?>/notifications.php';
        });

        // ============================================
        // منوی موبایل (همبرگری)
        // ============================================
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
        const mobileMenuClose = document.getElementById('mobileMenuClose');

        function openMobileMenu() {
            if (mobileMenu && mobileMenuOverlay) {
                mobileMenu.classList.add('open');
                mobileMenuOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMobileMenu() {
            if (mobileMenu && mobileMenuOverlay) {
                mobileMenu.classList.remove('open');
                mobileMenuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', openMobileMenu);
        }

        if (mobileMenuClose) {
            mobileMenuClose.addEventListener('click', closeMobileMenu);
        }

        if (mobileMenuOverlay) {
            mobileMenuOverlay.addEventListener('click', closeMobileMenu);
        }

        // بستن منو با کلید Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });

        // ============================================
        // تابع خروج
        // ============================================
        function logout() {
            if (confirm('آیا از خروج مطمئن هستید؟')) {
                let formData = new FormData();
                formData.append('action', 'logout');
                // ارسال درخواست به فایل logout.php برای اطمینان از خروج صحیح
                fetch('<?= BASE_URL ?>/planner/logout.php', { method: 'POST', body: formData })
                    .then(() => {
                        // پاک کردن session و هدایت به صفحه ورود
                        document.cookie.split(";").forEach(function(c) { 
                            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
                        });
                        window.location.href = '<?= BASE_URL ?>/auth/login.php';
                    })
                    .catch(() => {
                        // در صورت خطا، مستقیماً به فایل logout.php هدایت می‌شویم
                        window.location.href = '<?= BASE_URL ?>/planner/logout.php';
                    });
            }
        }

        function logoutMobile() {
            closeMobileMenu();
            setTimeout(() => {
                logout();
            }, 300);
        }

        console.log('✅ هدر یکپارچه با موفقیت بارگذاری شد. (BASE_URL: <?= BASE_URL ?>)');
    </script>
<!-- ===== هدر اینجا تموم میشه، بستن body و html رو به صفحات اختصاصی می‌سپاریم ===== -->