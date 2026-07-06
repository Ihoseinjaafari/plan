<?php
// auth/register.php - صفحه ثبت‌نام کاربران
session_start();
date_default_timezone_set('Asia/Tehran');

$usersFile = '../data/users.json';
$settingsFile = '../data/settings.json';

// اگر کاربر قبلاً لاگین است، هدایت به داشبورد
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

// بررسی تنظیمات
$registrationEnabled = true;
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    $registrationEnabled = $settings['registration_enabled'] ?? true;
}

// توابع کمکی
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

function registerUser($name, $email, $password) {
    $users = getAllUsers();
    
    // بررسی فعال بودن ثبت‌نام
    $settingsFile = '../data/settings.json';
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
        if (!($settings['registration_enabled'] ?? true)) {
            return ['success' => false, 'message' => 'ثبت‌نام جدید در حال حاضر غیرفعال است'];
        }
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

// پردازش درخواست POST
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
    <title>ثبت‌نام حساب کاربری</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: #0a0a0f;
            color: #e8e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px 35px;
            width: 100%;
            max-width: 420px;
            backdrop-filter: blur(12px);
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .auth-logo h2 {
            font-size: 24px;
            color: #e8e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .auth-logo p {
            color: #7a7a9a;
            font-size: 14px;
            margin-top: 4px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            color: #b0b0c8;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.06);
            color: #e8e8f0;
            font-size: 14px;
            font-family: 'Vazirmatn', sans-serif;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .auth-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Vazirmatn', sans-serif;
        }
        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
        }
        .auth-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .auth-links {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #b0b0c8;
        }
        .auth-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .auth-links a:hover {
            text-decoration: underline;
        }
        .error-msg {
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b7a;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13px;
            display: none;
        }
        .success-msg {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-logo">
            <h2><i class="fas fa-user-plus"></i> ثبت‌نام</h2>
            <p>حساب کاربری جدید ایجاد کنید</p>
        </div>
        
        <div id="error-message" class="error-msg"></div>
        <div id="success-message" class="success-msg"></div>
        
        <form id="registerForm">
            <div class="form-group">
                <label for="name">نام و نام خانوادگی</label>
                <input type="text" id="name" name="name" required placeholder="مثال: علی حسینی">
            </div>
            <div class="form-group">
                <label for="email">ایمیل</label>
                <input type="email" id="email" name="email" required placeholder="example@email.com">
            </div>
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password" required placeholder="••••••••" minlength="6">
            </div>
            <button type="submit" class="auth-btn" id="registerBtn">ثبت‌نام</button>
        </form>
        
        <div class="auth-links">
            قبلاً ثبت‌نام کرده‌اید؟ <a href="login.php">وارد شوید</a>
        </div>
    </div>

    <script>
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('error-message');
            const successDiv = document.getElementById('success-message');
            const registerBtn = document.getElementById('registerBtn');
            
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            registerBtn.disabled = true;
            registerBtn.textContent = 'در حال ثبت‌نام...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'register');
                formData.append('name', name);
                formData.append('email', email);
                formData.append('password', password);
                
                const response = await fetch('register.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    successDiv.textContent = 'ثبت‌نام با موفقیت انجام شد! در حال انتقال...';
                    successDiv.style.display = 'block';
                    setTimeout(() => {
                        window.location.href = '../dashboard/index.php';
                    }, 1500);
                } else {
                    errorDiv.textContent = result.message;
                    errorDiv.style.display = 'block';
                    registerBtn.disabled = false;
                    registerBtn.textContent = 'ثبت‌نام';
                }
            } catch (error) {
                errorDiv.textContent = 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';
                errorDiv.style.display = 'block';
                registerBtn.disabled = false;
                registerBtn.textContent = 'ثبت‌نام';
            }
        });
    </script>
</body>
</html>
