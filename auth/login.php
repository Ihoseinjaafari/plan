<?php
// auth/login.php - صفحه ورود کاربران
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

// پردازش درخواست POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'login') {
        $response = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
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
    <title>ورود به حساب کاربری</title>
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
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-logo">
            <h2><i class="fas fa-sign-in-alt"></i> ورود به حساب</h2>
            <p>خوش آمدید! لطفاً وارد شوید</p>
        </div>
        
        <div id="error-message" class="error-msg"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <label for="email">ایمیل</label>
                <input type="email" id="email" name="email" required placeholder="example@email.com">
            </div>
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="auth-btn">ورود</button>
        </form>
        
        <div class="auth-links">
            حساب ندارید؟ <a href="register.php">ثبت‌نام کنید</a>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('error-message');
            
            try {
                const formData = new FormData();
                formData.append('action', 'login');
                formData.append('email', email);
                formData.append('password', password);
                
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = '../dashboard/index.php';
                } else {
                    errorDiv.textContent = result.message;
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';
                errorDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>
