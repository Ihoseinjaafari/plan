<?php
// planner/admin.php - پنل مدیریت
session_start();
date_default_timezone_set('Asia/Tehran');

// ==================== بررسی احراز هویت ====================
$usersFile = __DIR__ . '/../data/users.json';
$dataFile = __DIR__ . '/../data/tasks.json';
$settingsFile = __DIR__ . '/../data/settings.json';

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
if (!$currentUser || $currentUser['email'] !== 'admin@example.com') {
    header('Location: ../index.php');
    exit;
}

// ==================== توابع ====================
function getAllUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return [];
    $users = json_decode(file_get_contents($usersFile), true);
    return is_array($users) ? $users : [];
}

function getAllTasks() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $tasks = json_decode(file_get_contents($dataFile), true);
    return is_array($tasks) ? $tasks : [];
}

function getSettings() {
    global $settingsFile;
    if (!file_exists($settingsFile)) {
        $default = [
            'registration_enabled' => true,
            'modules' => [
                'planner' => ['enabled' => true, 'name' => 'پلنر تسک‌ها'],
                'projects' => ['enabled' => true, 'name' => 'مدیریت پروژه‌ها'],
                'lifeplan' => ['enabled' => true, 'name' => 'لایف‌پلن'],
                'vision' => ['enabled' => true, 'name' => 'ویژن برد'],
                'finance' => ['enabled' => true, 'name' => 'مدیریت مالی'],
                'health' => ['enabled' => true, 'name' => 'سلامت'],
                'calendar' => ['enabled' => true, 'name' => 'تقویم شمسی'],
                'dashboard' => ['enabled' => true, 'name' => 'داشبورد']
            ]
        ];
        file_put_contents($settingsFile, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!is_array($settings)) {
        $settings = ['registration_enabled' => true];
    }
    if (!isset($settings['modules'])) {
        $settings['modules'] = [
            'planner' => ['enabled' => true, 'name' => 'پلنر تسک‌ها'],
            'projects' => ['enabled' => true, 'name' => 'مدیریت پروژه‌ها'],
            'lifeplan' => ['enabled' => true, 'name' => 'لایف‌پلن'],
            'vision' => ['enabled' => true, 'name' => 'ویژن برد'],
            'finance' => ['enabled' => true, 'name' => 'مدیریت مالی'],
            'health' => ['enabled' => true, 'name' => 'سلامت'],
            'calendar' => ['enabled' => true, 'name' => 'تقویم شمسی'],
            'dashboard' => ['enabled' => true, 'name' => 'داشبورد']
        ];
    }
    return $settings;
}

function saveSettings($settings) {
    global $settingsFile;
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
}

$users = getAllUsers();
$tasks = getAllTasks();
$settings = getSettings();
$registrationEnabled = $settings['registration_enabled'] ?? true;

// آمار
$totalUsers = count($users);
$totalTasks = count($tasks);
$doneTasks = 0;
$pendingTasks = 0;

foreach ($tasks as $task) {
    if ($task['done'] ?? false) {
        $doneTasks++;
    } else {
        $pendingTasks++;
    }
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'toggle_registration') {
        $settings = getSettings();
        $settings['registration_enabled'] = !($settings['registration_enabled'] ?? true);
        saveSettings($settings);
        $response = ['success' => true, 'enabled' => $settings['registration_enabled']];
    }
    elseif ($action === 'toggle_module') {
        $moduleId = $_POST['module_id'] ?? '';
        $settings = getSettings();
        
        if (isset($settings['modules'][$moduleId])) {
            $settings['modules'][$moduleId]['enabled'] = !($settings['modules'][$moduleId]['enabled'] ?? true);
            saveSettings($settings);
            $response = ['success' => true, 'enabled' => $settings['modules'][$moduleId]['enabled']];
        } else {
            $response = ['success' => false, 'message' => 'ماژول یافت نشد'];
        }
    }
    elseif ($action === 'delete_user') {
        $userId = $_POST['user_id'] ?? '';
        if ($userId && $userId != $_SESSION['user_id']) {
            $users = getAllUsers();
            $users = array_values(array_filter($users, fn($u) => $u['id'] != $userId));
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'نمی‌توانید خودتان را حذف کنید'];
        }
    }
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .admin-header {
            background: white;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .admin-header h1 {
            font-size: 24px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .admin-header h1 i {
            color: #667eea;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            background: #5a6268;
            transform: scale(1.02);
        }
        
        .admin-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .admin-card h2 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-card h2 i {
            color: #667eea;
        }
        
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .setting-item .info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .setting-item .info .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .setting-item .info .icon.enabled {
            background: #d4edda;
            color: #28a745;
        }
        
        .setting-item .info .icon.disabled {
            background: #f8d7da;
            color: #dc3545;
        }
        
        .setting-item .info .text .title {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .setting-item .info .text .desc {
            font-size: 13px;
            color: #999;
        }
        
        .toggle-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .toggle-btn.enabled {
            background: #28a745;
            color: white;
        }
        
        .toggle-btn.enabled:hover {
            background: #218838;
        }
        
        .toggle-btn.disabled {
            background: #dc3545;
            color: white;
        }
        
        .toggle-btn.disabled:hover {
            background: #c82333;
        }
        
        .toggle-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-item .number {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-item .label {
            font-size: 14px;
            color: #999;
            margin-top: 5px;
        }
        
        .user-list {
            margin-top: 15px;
        }
        
        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-item .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-item .user-avatar-small {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .user-item .user-email {
            font-size: 13px;
            color: #999;
        }
        
        .badge-admin {
            background: #667eea;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-user {
            background: #e9ecef;
            color: #666;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
        }
        
        .delete-user-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .delete-user-btn:hover {
            background: #fee;
            transform: scale(1.1);
        }
        
        .delete-user-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .home-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .home-btn:hover {
            background: #5a6fd6;
            transform: scale(1.02);
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1a1a2e;
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transform: translateY(100px);
            transition: all 0.3s ease;
            font-family: 'Vazirmatn', sans-serif;
        }
        
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .toast.success { background: #28a745; }
        .toast.error { background: #dc3545; }
        
        @media (max-width: 768px) {
            .admin-header { flex-direction: column; align-items: stretch; }
            .setting-item { flex-direction: column; align-items: stretch; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .user-item { flex-direction: column; align-items: stretch; }
            .user-item .user-info { flex-wrap: wrap; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1><i class="fas fa-shield-alt"></i> پنل مدیریت</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="index.php" class="back-btn"><i class="fas fa-arrow-right"></i> بازگشت</a>
                <a href="../index.php" class="home-btn"><i class="fas fa-home"></i> صفحه اصلی</a>
            </div>
        </div>
        
        <!-- آمار -->
        <div class="admin-card">
            <h2><i class="fas fa-chart-bar"></i> آمار کلی</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="number"><?php echo $totalUsers; ?></div>
                    <div class="label">کاربران</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo $totalTasks; ?></div>
                    <div class="label">تسک‌ها</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo $doneTasks; ?></div>
                    <div class="label">تسک‌های انجام‌شده</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo $pendingTasks; ?></div>
                    <div class="label">تسک‌های در انتظار</div>
                </div>
            </div>
        </div>
        
        <!-- تنظیمات ثبت‌نام -->
        <div class="admin-card">
            <h2><i class="fas fa-cog"></i> تنظیمات کلی</h2>
            <div class="setting-item">
                <div class="info">
                    <div class="icon <?php echo $registrationEnabled ? 'enabled' : 'disabled'; ?>">
                        <i class="fas <?php echo $registrationEnabled ? 'fa-check' : 'fa-times'; ?>"></i>
                    </div>
                    <div class="text">
                        <div class="title">ثبت‌نام کاربران جدید</div>
                        <div class="desc">
                            <?php echo $registrationEnabled ? '✅ فعال - کاربران جدید می‌توانند ثبت‌نام کنند' : '❌ غیرفعال - فقط کاربران موجود می‌توانند وارد شوند'; ?>
                        </div>
                    </div>
                </div>
                <button class="toggle-btn <?php echo $registrationEnabled ? 'enabled' : 'disabled'; ?>" 
                        id="toggleRegistrationBtn"
                        onclick="toggleRegistration()">
                    <i class="fas <?php echo $registrationEnabled ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                    <?php echo $registrationEnabled ? 'غیرفعال‌سازی' : 'فعال‌سازی'; ?>
                </button>
            </div>
        </div>
        
        <!-- مدیریت ماژول‌ها -->
        <div class="admin-card">
            <h2><i class="fas fa-th-large"></i> مدیریت بخش‌های سایت</h2>
            <p style="margin-bottom: 15px; color: #666; font-size: 13px;">می‌توانید هر بخش از سایت را به صورت جداگانه فعال یا غیرفعال کنید. کاربران فقط بخش‌های فعال را خواهند دید.</p>
            <div id="modulesList">
                <?php 
                $modules = $settings['modules'] ?? [];
                $moduleIcons = [
                    'planner' => 'fa-tasks',
                    'projects' => 'fa-project-diagram',
                    'lifeplan' => 'fa-compass',
                    'vision' => 'fa-eye',
                    'finance' => 'fa-wallet',
                    'health' => 'fa-heartbeat',
                    'calendar' => 'fa-calendar-alt',
                    'dashboard' => 'fa-chart-line'
                ];
                foreach ($modules as $moduleId => $module): 
                    $icon = $moduleIcons[$moduleId] ?? 'fa-cube';
                    $enabled = $module['enabled'] ?? true;
                    $name = $module['name'] ?? ucfirst($moduleId);
                ?>
                <div class="setting-item" style="margin-bottom: 10px;">
                    <div class="info">
                        <div class="icon <?php echo $enabled ? 'enabled' : 'disabled'; ?>" style="width: 35px; height: 35px; font-size: 16px;">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="text">
                            <div class="title"><?php echo htmlspecialchars($name); ?></div>
                            <div class="desc">
                                <?php echo $enabled ? '✅ فعال' : '❌ غیرفعال'; ?>
                            </div>
                        </div>
                    </div>
                    <button class="toggle-btn <?php echo $enabled ? 'enabled' : 'disabled'; ?>" 
                            data-module-id="<?php echo htmlspecialchars($moduleId); ?>"
                            onclick="toggleModule('<?php echo htmlspecialchars($moduleId); ?>')">
                        <i class="fas <?php echo $enabled ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                        <?php echo $enabled ? 'غیرفعال' : 'فعال'; ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- لیست کاربران -->
        <div class="admin-card">
            <h2><i class="fas fa-users"></i> لیست کاربران</h2>
            <div class="user-list">
                <?php if (empty($users)): ?>
                    <div style="text-align:center; padding:20px; color:#999;">هیچ کاربری ثبت نشده است</div>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-item" data-id="<?php echo $user['id']; ?>">
                            <div class="user-info">
                                <div class="user-avatar-small" style="background: <?php echo $user['avatar_color'] ?? '#667eea'; ?>">
                                    <?php echo mb_substr($user['name'] ?? 'U', 0, 1); ?>
                                </div>
                                <div>
                                    <div><?php echo htmlspecialchars($user['name'] ?? 'بدون نام'); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <?php if (($user['email'] ?? '') === 'admin@example.com'): ?>
                                    <span class="badge-admin">مدیر</span>
                                <?php else: ?>
                                    <span class="badge-user">کاربر</span>
                                <?php endif; ?>
                                <span style="font-size: 12px; color: #999;">
                                    <?php echo date('Y-m-d', strtotime($user['created_at'] ?? 'now')); ?>
                                </span>
                                <?php if (($user['email'] ?? '') !== 'admin@example.com'): ?>
                                    <button class="delete-user-btn" onclick="deleteUser('<?php echo $user['id']; ?>')" title="حذف کاربر">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="toast" id="toast"></div>
    
    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }
        
        async function toggleRegistration() {
            const btn = document.getElementById('toggleRegistrationBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال تغییر...';
            
            try {
                let formData = new FormData();
                formData.append('action', 'toggle_registration');
                
                let response = await fetch(window.location.href, { method: 'POST', body: formData });
                let result = await response.json();
                
                if (result.success) {
                    const enabled = result.enabled;
                    const icon = document.querySelector('.setting-item .icon');
                    const desc = document.querySelector('.setting-item .desc');
                    
                    if (enabled) {
                        icon.className = 'icon enabled';
                        icon.innerHTML = '<i class="fas fa-check"></i>';
                        btn.className = 'toggle-btn enabled';
                        btn.innerHTML = '<i class="fas fa-toggle-on"></i> غیرفعال‌سازی';
                        desc.textContent = '✅ فعال - کاربران جدید می‌توانند ثبت‌نام کنند';
                        showToast('ثبت‌نام فعال شد', 'success');
                    } else {
                        icon.className = 'icon disabled';
                        icon.innerHTML = '<i class="fas fa-times"></i>';
                        btn.className = 'toggle-btn disabled';
                        btn.innerHTML = '<i class="fas fa-toggle-off"></i> فعال‌سازی';
                        desc.textContent = '❌ غیرفعال - فقط کاربران موجود می‌توانند وارد شوند';
                        showToast('ثبت‌نام غیرفعال شد', 'success');
                    }
                } else {
                    showToast(result.message || 'خطا در تغییر وضعیت', 'error');
                }
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
            
            btn.disabled = false;
        }
        
        async function deleteUser(userId) {
            if (!confirm('آیا از حذف این کاربر مطمئن هستید؟')) return;
            
            try {
                let formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('user_id', userId);
                
                let response = await fetch(window.location.href, { method: 'POST', body: formData });
                let result = await response.json();
                
                if (result.success) {
                    showToast('کاربر با موفقیت حذف شد', 'success');
                    const item = document.querySelector(`.user-item[data-id="${userId}"]`);
                    if (item) item.remove();
                } else {
                    showToast(result.message || 'خطا در حذف کاربر', 'error');
                }
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }
        
        async function toggleModule(moduleId) {
            const btn = document.querySelector(`button[data-module-id="${moduleId}"]`);
            if (!btn) return;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال تغییر...';
            
            try {
                let formData = new FormData();
                formData.append('action', 'toggle_module');
                formData.append('module_id', moduleId);
                
                let response = await fetch(window.location.href, { method: 'POST', body: formData });
                let result = await response.json();
                
                if (result.success) {
                    const enabled = result.enabled;
                    const iconDiv = btn.parentElement.querySelector('.icon');
                    const descDiv = btn.parentElement.querySelector('.desc');
                    
                    if (enabled) {
                        iconDiv.className = 'icon enabled';
                        iconDiv.innerHTML = '<i class="fas fa-check"></i>';
                        btn.className = 'toggle-btn enabled';
                        btn.innerHTML = '<i class="fas fa-toggle-on"></i> غیرفعال';
                        descDiv.textContent = '✅ فعال';
                        showToast('بخش فعال شد', 'success');
                    } else {
                        iconDiv.className = 'icon disabled';
                        iconDiv.innerHTML = '<i class="fas fa-times"></i>';
                        btn.className = 'toggle-btn disabled';
                        btn.innerHTML = '<i class="fas fa-toggle-off"></i> فعال';
                        descDiv.textContent = '❌ غیرفعال';
                        showToast('بخش غیرفعال شد', 'success');
                    }
                } else {
                    showToast(result.message || 'خطا در تغییر وضعیت', 'error');
                }
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
            
            btn.disabled = false;
        }
    </script>
</body>
</html>