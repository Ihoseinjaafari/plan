<?php
// index.php - داشبورد اصلی بدون هدر
session_start();
date_default_timezone_set('Asia/Tehran');

// ==================== تنظیمات و فایل‌ها ====================
$settingsFile = 'data/settings.json';
$usersFile = 'data/users.json';

// ==================== توابع ====================
function getSettings() {
    global $settingsFile;
    if (!file_exists($settingsFile)) {
        $default = ['registration_enabled' => true];
        file_put_contents($settingsFile, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    $settings = json_decode(file_get_contents($settingsFile), true);
    return is_array($settings) ? $settings : ['registration_enabled' => true];
}

function getAllUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return [];
    $users = json_decode(file_get_contents($usersFile), true);
    return is_array($users) ? $users : [];
}

function getUserByEmail($email) {
    $users = getAllUsers();
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            return $user;
        }
    }
    return null;
}

function getUserById($id) {
    $users = getAllUsers();
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

function registerUser($name, $email, $password) {
    $users = getAllUsers();
    $settings = getSettings();
    if (!($settings['registration_enabled'] ?? true)) {
        return ['success' => false, 'message' => 'ثبت‌نام جدید در حال حاضر غیرفعال است'];
    }
    if (getUserByEmail($email)) {
        return ['success' => false, 'message' => 'این ایمیل قبلاً ثبت شده است'];
    }
    $newId = time() . rand(100, 999);
    $newUser = [
        'id' => $newId,
        'name' => htmlspecialchars(trim($name)),
        'email' => htmlspecialchars(trim($email)),
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s'),
        'avatar_color' => '#' . dechex(rand(0x000000, 0xFFFFFF))
    ];
    $users[] = $newUser;
    file_put_contents($GLOBALS['usersFile'], json_encode($users, JSON_PRETTY_PRINT));
    return ['success' => true, 'user' => $newUser];
}

function loginUser($email, $password) {
    $user = getUserByEmail($email);
    if (!$user) {
        return ['success' => false, 'message' => 'ایمیل یا رمز عبور اشتباه است'];
    }
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'ایمیل یا رمز عبور اشتباه است'];
    }
    return ['success' => true, 'user' => $user];
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'register') {
        $response = registerUser($_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($response['success']) {
            $_SESSION['user_id'] = $response['user']['id'];
            $_SESSION['user_name'] = $response['user']['name'];
            $_SESSION['user_email'] = $response['user']['email'];
            unset($response['user']['password']);
        }
    } elseif ($action === 'login') {
        $response = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($response['success']) {
            $_SESSION['user_id'] = $response['user']['id'];
            $_SESSION['user_name'] = $response['user']['name'];
            $_SESSION['user_email'] = $response['user']['email'];
            unset($response['user']['password']);
        }
    } elseif ($action === 'logout') {
        session_destroy();
        $response = ['success' => true];
    }
    
    echo json_encode($response);
    exit;
}

// ==================== متغیرهای جلسه ====================
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = $isLoggedIn ? getUserById($_SESSION['user_id']) : null;
$registrationEnabled = getSettings()['registration_enabled'] ?? true;
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت زندگی</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- فونت وزیرمتن -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    
    <style>
        /* ============================================
           استایل‌های عمومی و تم
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: #0a0a0f;
            color: #e8e8f0;
            min-height: 100vh;
            transition: background 0.4s ease, color 0.4s ease;
        }

        body.light-mode {
            background: #f0f2f8;
            color: #1a1a2e;
        }

        /* ===== متغیرهای تم ===== */
        :root {
            --bg-body: #0a0a0f;
            --bg-card: rgba(255, 255, 255, 0.05);
            --bg-input: rgba(255, 255, 255, 0.06);
            --text-primary: #e8e8f0;
            --text-secondary: #b0b0c8;
            --text-muted: #7a7a9a;
            --border-color: rgba(255, 255, 255, 0.08);
            --shadow-color: rgba(0, 0, 0, 0.4);
        }

        body.light-mode {
            --bg-body: #f0f2f8;
            --bg-card: rgba(255, 255, 255, 0.7);
            --bg-input: rgba(255, 255, 255, 0.5);
            --text-primary: #1a1a2e;
            --text-secondary: #3a3a5a;
            --text-muted: #7a7a9a;
            --border-color: rgba(0, 0, 0, 0.08);
            --shadow-color: rgba(0, 0, 0, 0.08);
        }

        /* ===== استایل‌های اختصاصی داشبورد ===== */
        .dashboard-wrapper {
            padding: 20px 25px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .dashboard-header {
            text-align: center;
            padding: 30px 20px 25px;
            margin-bottom: 30px;
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(8px);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .dashboard-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .dashboard-header h2 i {
            color: #f5576c;
        }

        .dashboard-header p {
            color: var(--text-muted);
            font-size: 16px;
            margin-top: 4px;
        }

        /* ===== وضعیت کاربر ===== */
        .user-status-bar {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 24px;
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            flex-wrap: wrap;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .user-status-bar .avatar-mini {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .user-status-bar .welcome-text {
            color: var(--text-secondary);
            font-size: 15px;
        }

        .user-status-bar .welcome-text .user-name {
            color: var(--text-primary);
            font-weight: 600;
        }

        .user-status-bar .divider {
            color: var(--text-muted);
            opacity: 0.3;
        }

        .user-status-bar .logout-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 14px;
            padding: 6px 14px;
            border-radius: 10px;
            transition: all 0.3s;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .user-status-bar .logout-btn:hover {
            background: rgba(220,53,69,0.12);
            color: #dc3545;
        }

        /* ===== منوی اصلی ===== */
        .main-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .menu-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 28px 22px 22px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            backdrop-filter: blur(4px);
        }

        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .menu-card:hover {
            transform: translateY(-6px);
            border-color: rgba(102,126,234,0.3);
            box-shadow: 0 12px 40px var(--shadow-color);
        }

        .menu-card:hover::before {
            opacity: 1;
        }

        .menu-card .card-icon {
            font-size: 2.8rem;
            margin-bottom: 14px;
            line-height: 1;
            transition: transform 0.3s ease;
        }

        .menu-card:hover .card-icon {
            transform: scale(1.1) rotate(-3deg);
        }

        .menu-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 6px 0;
            color: var(--text-primary);
        }

        .menu-card p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.6;
        }

        .menu-card .badge {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .menu-card .card-arrow {
            margin-top: 14px;
            font-size: 13px;
            color: var(--text-muted);
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(4px);
        }

        .menu-card:hover .card-arrow {
            opacity: 1;
            transform: translateY(0);
            color: #667eea;
        }

        /* ===== رنگ‌های خاص کارت‌ها ===== */
        .menu-card.planner .card-icon { color: #f5576c; }
        .menu-card.lifeplan .card-icon { color: #4facfe; }
        .menu-card.projects .card-icon { color: #43e97b; }
        .menu-card.calendar .card-icon { color: #fa709a; }
        .menu-card.settings .card-icon { color: #a18cd1; }
        .menu-card.profile .card-icon { color: #fbc2eb; }

        /* ===== فرم‌های احراز هویت ===== */
        .auth-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 60vh;
            padding: 20px;
        }

        .auth-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px 35px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px var(--shadow-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .auth-card .auth-logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .auth-card .auth-logo h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .auth-card .auth-logo p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .auth-card .form-group {
            margin-bottom: 18px;
        }

        .auth-card .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .auth-card .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            transition: all 0.3s ease;
        }

        .auth-card .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }

        .auth-card .form-group input::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }

        .auth-card .auth-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .auth-card .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.35);
        }

        .auth-card .auth-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .auth-card .auth-switch {
            text-align: center;
            margin-top: 16px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .auth-card .auth-switch a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.3s;
        }

        .auth-card .auth-switch a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .auth-card .error-msg {
            background: rgba(220,53,69,0.12);
            color: #dc3545;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }

        .auth-card .success-msg {
            background: rgba(40,167,69,0.12);
            color: #28a745;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }

        .auth-card .registration-disabled {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
        }

        .auth-card .registration-disabled i {
            font-size: 40px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.4;
        }

        /* ===== ریسپانسیو ===== */
        @media (max-width: 768px) {
            .dashboard-wrapper {
                padding: 10px 12px 30px;
            }

            .dashboard-header h2 {
                font-size: 22px;
            }

            .dashboard-header p {
                font-size: 14px;
            }

            .main-menu {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 14px;
            }

            .menu-card {
                padding: 20px 16px 18px;
            }

            .menu-card .card-icon {
                font-size: 2.2rem;
            }

            .menu-card h3 {
                font-size: 16px;
            }

            .menu-card p {
                font-size: 12px;
            }

            .user-status-bar {
                padding: 12px 18px;
                gap: 12px;
            }

            .user-status-bar .welcome-text {
                font-size: 14px;
            }

            .auth-card {
                padding: 30px 22px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-header h2 {
                font-size: 19px;
            }

            .main-menu {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .menu-card {
                padding: 16px 12px 14px;
            }

            .menu-card .card-icon {
                font-size: 1.8rem;
                margin-bottom: 8px;
            }

            .menu-card h3 {
                font-size: 14px;
            }

            .menu-card p {
                display: none;
            }

            .menu-card .badge {
                font-size: 8px;
                padding: 2px 10px;
                top: 8px;
                right: 8px;
            }

            .user-status-bar {
                padding: 10px 14px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .user-status-bar .divider {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <!-- هدر داشبورد -->
    <div class="dashboard-header">
        <h2><i class="fas fa-rocket"></i> سیستم مدیریت زندگی</h2>
        <p>همه ابزارهای مدیریت زندگی در یک مکان</p>
    </div>
    
    <?php if ($isLoggedIn && $currentUser): ?>
        <!-- وضعیت کاربر -->
        <div class="user-status-bar">
            <div class="avatar-mini" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                <?php echo mb_substr($currentUser['name'], 0, 1); ?>
            </div>
            <span class="welcome-text">
                خوش آمدی، <span class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
            </span>
            <span class="divider">|</span>
            <button class="logout-btn" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i> خروج
            </button>
        </div>

        <!-- منوی اصلی -->
        <div class="main-menu">
            <!-- Planner -->
            <a href="planner/index.php" class="menu-card planner">
                <span class="badge">فعال</span>
                <div class="card-icon"><i class="fas fa-tasks"></i></div>
                <h3>📋 Planner</h3>
                <p>برنامه‌ریزی روزانه، مدیریت تسک‌ها و پروژه‌ها</p>
                <span class="card-arrow"><i class="fas fa-arrow-left"></i> ورود</span>
            </a>

            <!-- LifePlan -->
            <a href="lifeplan/index.php" class="menu-card lifeplan">
                <div class="card-icon"><i class="fas fa-compass"></i></div>
                <h3>🧭 LifePlan</h3>
                <p>برنامه‌ریزی بلندمدت، اهداف و چشم‌انداز زندگی</p>
                <span class="card-arrow"><i class="fas fa-arrow-left"></i> ورود</span>
            </a>

            <!-- پروژه‌ها -->
            <a href="projects/index.php" class="menu-card projects">
                <div class="card-icon"><i class="fas fa-project-diagram"></i></div>
                <h3>📊 پروژه‌ها</h3>
                <p>مدیریت پروژه‌های شخصی و تیمی</p>
                <span class="card-arrow"><i class="fas fa-arrow-left"></i> ورود</span>
            </a>

            <!-- تقویم -->
            <a href="/calendar/index.php" class="menu-card calendar">
                <div class="card-icon"><i class="fas fa-calendar-alt"></i></div>
                <h3>📅 تقویم</h3>
                <p>تقویم شمسی با نمایش تسک‌ها</p>
                <span class="card-arrow"><i class="fas fa-arrow-left"></i> ورود</span>
            </a>

            <!-- پروفایل -->
            <a href="profile.php" class="menu-card profile">
                <div class="card-icon"><i class="fas fa-user-circle"></i></div>
                <h3>👤 پروفایل</h3>
                <p>مشاهده و ویرایش اطلاعات شخصی</p>
                <span class="card-arrow"><i class="fas fa-arrow-left"></i> ورود</span>
            </a>

            <!-- تنظیمات -->
            <a href="settings.php" class="menu-card settings">
                <div class="card-icon"><i class="fas fa-cog"></i></div>
                <h3>⚙️ تنظیمات</h3>
                <p>تنظیمات سیستم و مدیریت کاربران</p>
                <span class="card-arrow"><i class="fas fa-arrow-left"></i> ورود</span>
            </a>
        </div>

    <?php else: ?>
        <!-- فرم‌های احراز هویت -->
        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-logo">
                    <h2>🚀 Life+</h2>
                    <p>به سیستم مدیریت زندگی خوش آمدید</p>
                </div>

                <div id="authError" class="error-msg"></div>
                <div id="authSuccess" class="success-msg"></div>

                <!-- فرم ورود -->
                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="form-group">
                        <label>ایمیل</label>
                        <input type="email" id="loginEmail" placeholder="example@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" id="loginPassword" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="auth-btn" id="loginBtn">ورود</button>
                </form>

                <!-- فرم ثبت‌نام -->
                <form id="registerForm" onsubmit="handleRegister(event)" style="display: none; margin-top: 20px;">
                    <div class="form-group">
                        <label>نام کامل</label>
                        <input type="text" id="registerName" placeholder="نام و نام خانوادگی" required>
                    </div>
                    <div class="form-group">
                        <label>ایمیل</label>
                        <input type="email" id="registerEmail" placeholder="example@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" id="registerPassword" placeholder="حداقل ۶ کاراکتر" minlength="6" required>
                    </div>
                    <?php if (!$registrationEnabled): ?>
                        <div class="registration-disabled">
                            <i class="fas fa-lock"></i>
                            <p>ثبت‌نام جدید در حال حاضر غیرفعال است</p>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="auth-btn" id="registerBtn" <?php echo !$registrationEnabled ? 'disabled' : ''; ?>>
                        ثبت‌نام
                    </button>
                </form>

                <div class="auth-switch">
                    <span id="switchText">حساب کاربری ندارید؟</span>
                    <a id="switchLink" onclick="toggleAuthMode()">ثبت‌نام کنید</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ===== اسکریپت‌های داشبورد ===== -->
<script>
    // ============================================
    // مدیریت احراز هویت
    // ============================================
    let isLoginMode = true;

    function toggleAuthMode() {
        isLoginMode = !isLoginMode;
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const switchText = document.getElementById('switchText');
        const switchLink = document.getElementById('switchLink');

        if (isLoginMode) {
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
            switchText.textContent = 'حساب کاربری ندارید؟';
            switchLink.textContent = 'ثبت‌نام کنید';
        } else {
            loginForm.style.display = 'none';
            registerForm.style.display = 'block';
            switchText.textContent = 'حساب کاربری دارید؟';
            switchLink.textContent = 'وارد شوید';
        }
        hideMessages();
    }

    function showMessage(msg, type = 'error') {
        const errorEl = document.getElementById('authError');
        const successEl = document.getElementById('authSuccess');
        errorEl.style.display = 'none';
        successEl.style.display = 'none';
        if (type === 'error') {
            errorEl.textContent = msg;
            errorEl.style.display = 'block';
        } else {
            successEl.textContent = msg;
            successEl.style.display = 'block';
        }
    }

    function hideMessages() {
        document.getElementById('authError').style.display = 'none';
        document.getElementById('authSuccess').style.display = 'none';
    }

    function setButtonLoading(btn, loading) {
        if (loading) {
            btn.disabled = true;
            btn.textContent = 'در حال پردازش...';
        } else {
            btn.disabled = false;
            btn.textContent = isLoginMode ? 'ورود' : 'ثبت‌نام';
        }
    }

    function handleLogin(e) {
        e.preventDefault();
        hideMessages();
        const btn = document.getElementById('loginBtn');
        setButtonLoading(btn, true);

        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('email', document.getElementById('loginEmail').value.trim());
        formData.append('password', document.getElementById('loginPassword').value);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                setButtonLoading(btn, false);
                if (result.success) {
                    showMessage('✅ ورود موفق! در حال انتقال...', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showMessage('❌ ' + (result.message || 'خطا در ورود'), 'error');
                }
            })
            .catch(() => {
                setButtonLoading(btn, false);
                showMessage('❌ خطا در ارتباط با سرور', 'error');
            });
    }

    function handleRegister(e) {
        e.preventDefault();
        hideMessages();
        const btn = document.getElementById('registerBtn');
        setButtonLoading(btn, true);

        const name = document.getElementById('registerName').value.trim();
        const email = document.getElementById('registerEmail').value.trim();
        const password = document.getElementById('registerPassword').value;

        if (password.length < 6) {
            showMessage('❌ رمز عبور باید حداقل ۶ کاراکتر باشد', 'error');
            setButtonLoading(btn, false);
            return;
        }

        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('name', name);
        formData.append('email', email);
        formData.append('password', password);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                setButtonLoading(btn, false);
                if (result.success) {
                    showMessage('✅ ثبت‌نام موفق! در حال انتقال...', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showMessage('❌ ' + (result.message || 'خطا در ثبت‌نام'), 'error');
                }
            })
            .catch(() => {
                setButtonLoading(btn, false);
                showMessage('❌ خطا در ارتباط با سرور', 'error');
            });
    }

    // ============================================
    // تابع خروج
    // ============================================
    function logout() {
        if (confirm('آیا از خروج مطمئن هستید؟')) {
            const formData = new FormData();
            formData.append('action', 'logout');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(() => location.reload())
                .catch(() => location.reload());
        }
    }

    // ============================================
    // ورود با کلید Enter
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            if (isLoginMode) {
                document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            } else {
                document.getElementById('registerForm').dispatchEvent(new Event('submit'));
            }
        }
    });

    // ============================================
    // هماهنگی تم
    // ============================================
    var savedTheme = localStorage.getItem('theme') || 'dark';
    if (savedTheme === 'light') {
        document.body.classList.add('light-mode');
    } else {
        document.body.classList.remove('light-mode');
    }

    console.log('✅ داشبورد بدون هدر بارگذاری شد.');
</script>

</body>
</html>