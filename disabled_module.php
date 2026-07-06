<?php
// disabled_module.php - صفحه نمایش داده شده وقتی یک بخش غیرفعال است

session_start();

// دریافت نام ماژول از پارامتر URL
$module_name = isset($_GET['module']) ? $_GET['module'] : 'section';

// اگر ماژول home بود، مستقیماً به داشبورد هدایت شود (بدون نمایش صفحه غیرفعال)
if ($module_name === 'home') {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

// لیست نام‌های فارسی ماژول‌ها
$module_names = [
    'home' => 'منوی اصلی',
    'planner' => 'پلنر تسک‌ها',
    'projects' => 'مدیریت پروژه‌ها',
    'lifeplan' => 'لایف‌پلن',
    'vision' => 'ویژن برد',
    'finance' => 'مدیریت مالی',
    'health' => 'سلامت',
    'calendar' => 'تقویم شمسی',
    'dashboard' => 'داشبورد'
];

$display_name = isset($module_names[$module_name]) ? $module_names[$module_name] : 'این بخش';

$page_title = 'بخش غیرفعال';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .disabled-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
        padding: 40px 20px;
        text-align: center;
    }
    
    .disabled-icon {
        font-size: 80px;
        margin-bottom: 30px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
    }
    
    .disabled-title {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 15px;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }
    
    .disabled-message {
        font-size: 18px;
        color: var(--text-secondary);
        margin-bottom: 30px;
        line-height: 1.8;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        max-width: 600px;
    }
    
    .back-btn {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 14px 40px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        text-decoration: none;
        display: inline-block;
        box-shadow: 0 8px 20px rgba(102,126,234,0.3);
    }
    
    .back-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(102,126,234,0.4);
    }
    
    .timer-text {
        margin-top: 20px;
        font-size: 14px;
        color: var(--text-muted);
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }
    
    .timer-countdown {
        color: #f1c40f;
        font-weight: 700;
        font-size: 16px;
    }
</style>

<div class="disabled-container">
    <div class="disabled-icon">🚧</div>
    
    <h1 class="disabled-title"><?= htmlspecialchars($display_name) ?> فعلاً غیرفعال است</h1>
    
    <p class="disabled-message">
        این بخش فعلا غیرفعال و در حال توسعه می‌باشد.<br>
        لطفاً از بخش‌های در دسترس استفاده بفرمایید.
    </p>
    
    <a href="<?= BASE_URL ?>/index.php" class="back-btn" id="backBtn">
        بازگشت به منوی اصلی
    </a>
    
    <p class="timer-text">
        انتقال خودکار به منوی اصلی در 
        <span class="timer-countdown" id="countdown">10</span>
        ثانیه دیگر...
    </p>
</div>

<script>
    // تایمر معکوس برای انتقال خودکار
    let countdown = 10;
    const countdownElement = document.getElementById('countdown');
    const backBtn = document.getElementById('backBtn');
    
    const timer = setInterval(() => {
        countdown--;
        countdownElement.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(timer);
            window.location.href = backBtn.href;
        }
    }, 1000);
    
    // اگر کاربر کلیک کرد، تایمر را متوقف کن
    backBtn.addEventListener('click', () => {
        clearInterval(timer);
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
