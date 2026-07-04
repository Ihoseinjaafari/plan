<?php
session_start();

// بررسی لاگین بودن کاربر (در صورت نیاز به سیستم لاگین واقعی، این بخش را فعال کنید)
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit;
// }

// تنظیمات فایل‌های ذخیره‌سازی
$habits_file = __DIR__ . '/data/habits.json';
$logs_file = __DIR__ . '/data/habit_logs.json';

// اطمینان از وجود پوشه data
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0777, true);
}

// مقداردهی اولیه فایل‌ها اگر وجود ندارند
if (!file_exists($habits_file)) {
    file_put_contents($habits_file, json_encode([]));
}
if (!file_exists($logs_file)) {
    file_put_contents($logs_file, json_encode([]));
}

// توابع کمکی تاریخ شمسی (مشابه calendar/index.php)
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * ((int)($days / 12053));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    $jy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian($jy, $jm, $jd) {
    $j_d_m = [0, 31, 62, 93, 124, 155, 186, 217, 248, 279, 310, 341, 372];
    $gy = ($jy <= 979) ? 621 : 1600;
    $jy -= ($jy <= 979) ? 0 : 979;
    $days = (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + 78 + $jd + (($jm < 7) ? $j_d_m[$jm - 1] : $j_d_m[$jm - 1] + 6);
    $gy += 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * ((int)(($days) / 1461));
    $days %= 1461;
    $gy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $gd = $days + 1;
    foreach ([0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334] as $gm => $v) {
        if ($gd <= $v) break;
    }
    $gm++;
    $gd = $gd - (($gm == 1) ? 0 : [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334][$gm - 1]);
    return [$gy, $gm, $gd];
}

function get_jalali_date_string($gy, $gm, $gd) {
    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf("%04d-%02d-%02d", $jy, $jm, $jd);
}

function get_current_jalali_date() {
    list($gy, $gm, $gd) = explode('-', date('Y-m-d'));
    return get_jalali_date_string($gy, $gm, $gd);
}

// پردازش درخواست‌های AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    $habits = json_decode(file_get_contents($habits_file), true) ?: [];
    $logs = json_decode(file_get_contents($logs_file), true) ?: [];

    if ($action === 'add_habit') {
        $new_habit = [
            'id' => uniqid(),
            'name' => htmlspecialchars($input['name']),
            'color' => $input['color'] ?? '#8b5cf6',
            'created_at' => date('Y-m-d')
        ];
        $habits[] = $new_habit;
        file_put_contents($habits_file, json_encode($habits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'habit' => $new_habit]);
        exit;
    }

    if ($action === 'delete_habit') {
        $id = $input['id'];
        $habits = array_filter($habits, fn($h) => $h['id'] !== $id);
        // پاک کردن لاگ‌های مربوطه
        $logs = array_filter($logs, fn($l) => $l['habit_id'] !== $id);
        
        file_put_contents($habits_file, json_encode(array_values($habits), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($logs_file, json_encode(array_values($logs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'toggle_log') {
        $habit_id = $input['habit_id'];
        $date = $input['date']; // فرمت YYYY-MM-DD شمسی
        
        $key = array_search($date, array_column($logs, 'date'));
        $found = false;
        
        // جستجو در لاگ‌های این عادت برای این تاریخ
        foreach ($logs as $index => $log) {
            if ($log['habit_id'] === $habit_id && $log['date'] === $date) {
                // اگر وجود داشت، حذفش کن (Toggle off)
                unset($logs[$index]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            // اگر نبود، اضافه کن (Toggle on)
            $logs[] = [
                'habit_id' => $habit_id,
                'date' => $date,
                'completed_at' => date('Y-m-d H:i:s')
            ];
        }

        $logs = array_values($logs); // بازسازی ایندکس‌ها
        file_put_contents($logs_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // محاسبه مجدد استریک برای پاسخ
        $streak = calculate_streak($habit_id, $logs);
        
        echo json_encode(['success' => true, 'streak' => $streak, 'completed' => !$found]);
        exit;
    }

    exit;
}

// تابع محاسبه استریک (رکورد متوالی)
function calculate_streak($habit_id, $logs) {
    // فیلتر لاگ‌های همین عادت
    $habit_logs = array_filter($logs, fn($l) => $l['habit_id'] === $habit_id);
    if (empty($habit_logs)) return 0;

    // مرتب‌سازی بر اساس تاریخ نزولی
    usort($habit_logs, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });

    $today_jalali = get_current_jalali_date();
    $yesterday_parts = jalali_to_gregorian(
        (int)explode('-', $today_jalali)[0],
        (int)explode('-', $today_jalali)[1],
        (int)explode('-', $today_jalali)[2] - 1
    );
    $yesterday_jalali = get_jalali_date_string($yesterday_parts[0], $yesterday_parts[1], $yesterday_parts[2]);

    $streak = 0;
    $current_check = $today_jalali;
    
    // اگر امروز انجام نشده، چک کنیم دیروز انجام شده؟ اگر نه استریک صفر
    $dates = array_column($habit_logs, 'date');
    
    if (!in_array($today_jalali, $dates)) {
        if (!in_array($yesterday_jalali, $dates)) {
            return 0;
        }
        $current_check = $yesterday_jalali;
    }

    while (true) {
        if (in_array($current_check, $dates)) {
            $streak++;
            // رفتن به روز قبل
            $parts = explode('-', $current_check);
            $prev = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2] - 1);
            $current_check = get_jalali_date_string($prev[0], $prev[1], $prev[2]);
        } else {
            break;
        }
    }
    
    return $streak;
}

// خواندن داده‌ها برای نمایش اولیه
$habits = json_decode(file_get_contents($habits_file), true) ?: [];
$logs = json_decode(file_get_contents($logs_file), true) ?: [];

// آماده‌سازی داده‌ها برای فرانت
$habits_with_stats = [];
foreach ($habits as $habit) {
    $streak = calculate_streak($habit['id'], $logs);
    $habits_with_stats[] = array_merge($habit, ['streak' => $streak]);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ردیاب عادت‌ها | Plan</title>
    <link rel="stylesheet" href="../styles/global.css"> <!-- لینک به استایل اصلی پروژه -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #f8f9fa;
            --bg-card: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --accent-color: #8b5cf6;
            --accent-hover: #7c3aed;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-card: #1e293b;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            transition: background-color 0.3s, color 0.3s;
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Header Styles (هماهنگ با بقیه صفحات) */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .page-title h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-title p {
            margin: 0.5rem 0 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .theme-toggle {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            transform: rotate(15deg);
            border-color: var(--accent-color);
        }

        /* Add Habit Section */
        .add-habit-box {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            border: 1px solid var(--border-color);
        }

        .input-group {
            flex: 1;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: inherit;
            outline: none;
            transition: border-color 0.3s;
        }

        .input-group input:focus {
            border-color: var(--accent-color);
        }

        .btn-add {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add:hover {
            background: var(--accent-hover);
        }

        /* Calendar Grid */
        .calendar-wrapper {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow-x: auto;
        }

        .calendar-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .month-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .nav-btn {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-btn:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(31, minmax(30px, 1fr)); /* حداکثر 31 روز */
            gap: 0.5rem;
            min-width: 800px; /* اسکرول افقی در موبایل */
        }

        .day-header {
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-secondary);
            padding: 0.5rem;
            font-weight: bold;
        }

        .day-cell {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            border: 1px solid transparent;
        }

        .day-cell:hover {
            transform: translateY(-2px);
            border-color: var(--accent-color);
        }

        .day-cell.today {
            background: var(--accent-color);
            color: white;
            font-weight: bold;
        }

        .day-cell.empty {
            visibility: hidden;
            pointer-events: none;
        }

        /* Habit Rows inside Calendar */
        .habit-row {
            display: grid;
            grid-template-columns: 200px repeat(31, minmax(30px, 1fr));
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            min-width: 800px;
        }
        
        .habit-row:last-child {
            border-bottom: none;
        }

        .habit-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 500;
            padding-right: 1rem;
        }

        .habit-color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .habit-check {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            margin: 0 auto;
            color: white;
            font-size: 0.8rem;
        }

        .habit-check.checked {
            background: var(--success-color);
            border-color: var(--success-color);
        }

        .habit-check:hover {
            border-color: var(--accent-color);
        }

        .streak-badge {
            font-size: 0.75rem;
            background: var(--bg-primary);
            padding: 2px 8px;
            border-radius: 12px;
            color: var(--text-secondary);
            margin-right: auto;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .delete-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: color 0.2s;
        }

        .delete-btn:hover {
            color: var(--danger-color);
            background: rgba(239, 68, 68, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .calendar-wrapper {
                padding: 1rem;
            }
            .add-habit-box {
                flex-direction: column;
                align-items: stretch;
            }
            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            .habit-row {
                grid-template-columns: 120px repeat(31, 28px);
                gap: 4px;
            }
            .habit-info {
                font-size: 0.85rem;
                padding-right: 0.5rem;
            }
            .streak-badge {
                display: none; /* مخفی کردن استریک در موبایل برای صرفه جویی در جا */
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- هدر صفحه -->
    <header class="page-header">
        <div class="page-title">
            <h1>ردیاب عادت‌ها</h1>
            <p>عادت‌های خود را بسازید و هر روز تیک بزنید</p>
        </div>
        <button class="theme-toggle" id="themeToggle" title="تغییر تم">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <!-- افزودن عادت جدید -->
    <div class="add-habit-box">
        <div class="input-group">
            <input type="text" id="newHabitInput" placeholder="نام عادت جدید (مثلاً: ورزش صبحگاهی)...">
        </div>
        <button class="btn-add" onclick="addHabit()">
            <i class="fas fa-plus"></i>
            افزودن
        </button>
    </div>

    <!-- لیست عادت‌ها و تقویم -->
    <div class="calendar-wrapper">
        <div class="calendar-controls">
            <div class="month-nav">
                <button class="nav-btn" onclick="changeMonth(-1)"><i class="fas fa-chevron-right"></i></button>
                <span id="currentMonthDisplay"></span>
                <button class="nav-btn" onclick="changeMonth(1)"><i class="fas fa-chevron-left"></i></button>
            </div>
            <button class="nav-btn" onclick="goToToday()" title="امروز"><i class="fas fa-calendar-day"></i></button>
        </div>

        <div id="habitsContainer">
            <!-- عادت‌ها اینجا رندر می‌شوند -->
            <?php if (empty($habits_with_stats)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <i class="fas fa-seedling" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>هنوز عادتی اضافه نکرده‌اید.</p>
                    <p>اولین عادت خود را بالا ایجاد کنید!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // داده‌های اولیه از PHP
    const habitsData = <?= json_encode($habits_with_stats, JSON_UNESCAPED_UNICODE) ?>;
    const logsData = <?= json_encode($logs, JSON_UNESCAPED_UNICODE) ?>;
    
    let currentYear, currentMonth;
    
    // راه‌اندازی تاریخ فعلی شمسی
    function initDate() {
        const today = new Date();
        const jDate = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
        currentYear = jDate[0];
        currentMonth = jDate[1];
        renderCalendar();
    }

    // تبدیل میلادی به شمسی (JS Version for UI logic)
    function gregorianToJalali(gy, gm, gd) {
        var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var jy = (gy <= 1600) ? 0 : 979;
        gy -= (gy <= 1600) ? 621 : 1600;
        var gy2 = (gm > 2) ? (gy + 1) : gy;
        var days = (365 * gy) + parseInt((gy2 + 3) / 4) - parseInt((gy2 + 99) / 100) + parseInt((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
        jy += 33 * parseInt(days / 12053);
        days %= 12053;
        jy += 4 * parseInt(days / 1461);
        days %= 1461;
        jy += parseInt((days - 1) / 365);
        if (days > 365) days = (days - 1) % 365;
        var jm = (days < 186) ? 1 + parseInt(days / 31) : 7 + parseInt((days - 186) / 30);
        var jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
        return [jy, jm, jd];
    }

    // تبدیل شمسی به میلادی
    function jalaliToGregorian(jy, jm, jd) {
        var j_d_m = [0, 31, 62, 93, 124, 155, 186, 217, 248, 279, 310, 341, 372];
        var gy = (jy <= 979) ? 621 : 1600;
        jy -= (jy <= 979) ? 0 : 979;
        var days = (365 * jy) + (parseInt(jy / 33) * 8) + parseInt(((jy % 33) + 3) / 4) + 78 + jd + ((jm < 7) ? j_d_m[jm - 1] : j_d_m[jm - 1] + 6);
        gy += 400 * parseInt(days / 146097);
        days %= 146097;
        if (days > 36524) {
            gy += 100 * parseInt(--days / 36524);
            days %= 36524;
            if (days >= 365) days++;
        }
        gy += 4 * parseInt((days) / 1461);
        days %= 1461;
        gy += parseInt((days - 1) / 365);
        if (days > 365) days = (days - 1) % 365;
        var gd = days + 1;
        var sal_a = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var gm;
        for (gm = 0; gm < 12; gm++) {
            if (gd <= sal_a[gm]) break;
        }
        gd = gd - sal_a[gm - 1]; // Fix index error potential
        return [gy, gm + 1, gd];
    }

    function getDaysInMonth(year, month) {
        if (month <= 6) return 31;
        if (month <= 11) return 30;
        // سال کبیسه ساده‌سازی شده
        return ((year % 4) === 3) ? 30 : 29; 
    }

    function getMonthName(month) {
        const names = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
        return names[month - 1];
    }

    function changeMonth(delta) {
        currentMonth += delta;
        if (currentMonth > 12) {
            currentMonth = 1;
            currentYear++;
        } else if (currentMonth < 1) {
            currentMonth = 12;
            currentYear--;
        }
        renderCalendar();
    }

    function goToToday() {
        const today = new Date();
        const jDate = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
        currentYear = jDate[0];
        currentMonth = jDate[1];
        renderCalendar();
    }

    function renderCalendar() {
        document.getElementById('currentMonthDisplay').textContent = `${getMonthName(currentMonth)} ${currentYear}`;
        const container = document.getElementById('habitsContainer');
        container.innerHTML = '';

        if (habitsData.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <i class="fas fa-seedling" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>هنوز عادتی اضافه نکرده‌اید.</p>
                </div>`;
            return;
        }

        const daysInMonth = getDaysInMonth(currentYear, currentMonth);
        const todayStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(new Date().getDate()).padStart(2, '0')}`; // Approximate for UI highlight logic if needed, but we use real conversion below

        // ساخت ردیف برای هر عادت
        habitsData.forEach(habit => {
            const row = document.createElement('div');
            row.className = 'habit-row';

            // ستون نام عادت
            const infoCol = document.createElement('div');
            infoCol.className = 'habit-info';
            infoCol.innerHTML = `
                <div class="habit-color-dot" style="background: ${habit.color}"></div>
                <span>${habit.name}</span>
                <span class="streak-badge"><i class="fas fa-fire" style="color: orange"></i> ${habit.streak} روز</span>
                <button class="delete-btn" onclick="deleteHabit('${habit.id}')"><i class="fas fa-trash"></i></button>
            `;
            row.appendChild(infoCol);

            // ستون‌های روزها
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                
                // بررسی اینکه آیا این تاریخ در لاگ‌ها وجود دارد
                const isCompleted = logsData.some(log => log.habit_id === habit.id && log.date === dateStr);
                
                // بررسی امروز بودن
                const realToday = new Date();
                const jToday = gregorianToJalali(realToday.getFullYear(), realToday.getMonth()+1, realToday.getDate());
                const isToday = (currentYear === jToday[0] && currentMonth === jToday[1] && d === jToday[2]);

                const cell = document.createElement('div');
                cell.className = `habit-check ${isCompleted ? 'checked' : ''}`;
                if (isToday) cell.style.borderColor = 'var(--accent-color)';
                
                cell.innerHTML = isCompleted ? '<i class="fas fa-check"></i>' : '';
                cell.onclick = () => toggleHabit(habit.id, dateStr, cell);
                
                row.appendChild(cell);
            }

            // پر کردن خانه‌های خالی اگر ماه کمتر از 31 روز دارد (برای حفظ گرید)
            // در این طراحی مینیمال، گرید داینامیک است و نیازی به پر کردن نیست چون grid-template-columns روی تعداد روزها تنظیم نمی‌شود، بلکه ثابت است.
            // اما برای زیبایی می‌توانیم خالی بگذاریم یا گرید را دقیق تنظیم کنیم.
            // روش ساده: فقط تا تعداد روزهای ماه حلقه زدیم.

            container.appendChild(row);
        });
    }

    async function addHabit() {
        const input = document.getElementById('newHabitInput');
        const name = input.value.trim();
        if (!name) return alert('لطفاً نام عادت را وارد کنید');

        const colors = ['#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#3b82f6', '#ef4444'];
        const randomColor = colors[Math.floor(Math.random() * colors.length)];

        try {
            const res = await fetch('habits.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'add_habit', name, color: randomColor })
            });
            const data = await res.json();
            if (data.success) {
                habitsData.push(data.habit);
                input.value = '';
                renderCalendar();
            }
        } catch (e) {
            console.error(e);
            alert('خطا در افزودن عادت');
        }
    }

    async function deleteHabit(id) {
        if (!confirm('آیا مطمئن هستید؟ این عمل غیرقابل بازگشت است.')) return;
        try {
            const res = await fetch('habits.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'delete_habit', id })
            });
            const data = await res.json();
            if (data.success) {
                const idx = habitsData.findIndex(h => h.id === id);
                if (idx > -1) habitsData.splice(idx, 1);
                renderCalendar();
            }
        } catch (e) {
            alert('خطا در حذف');
        }
    }

    async function toggleHabit(habitId, date, element) {
        try {
            const res = await fetch('habits.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'toggle_log', habit_id: habitId, date })
            });
            const data = await res.json();
            if (data.success) {
                // آپدیت لوکال
                const exists = logsData.find(l => l.habit_id === habitId && l.date === date);
                if (exists) {
                    logsData = logsData.filter(l => !(l.habit_id === habitId && l.date === date));
                    element.classList.remove('checked');
                    element.innerHTML = '';
                } else {
                    logsData.push({ habit_id: habitId, date, completed_at: new Date() });
                    element.classList.add('checked');
                    element.innerHTML = '<i class="fas fa-check"></i>';
                }
                
                // آپدیت استریک در UI (اختیاری - نیاز به رندر مجدد یا آپدیت جزئی دارد)
                // برای سادگی فعلا فقط تیک را تغییر می‌دهیم. کاربر برای دیدن استریک جدید رفرش می‌کند یا بعدا پیاده‌سازی می‌شود.
            }
        } catch (e) {
            alert('خطا در ثبت');
        }
    }

    // مدیریت تم
    const themeToggle = document.getElementById('themeToggle');
    const icon = themeToggle.querySelector('i');
    
    // بررسی تم ذخیره شده
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateIcon(savedTheme);

    themeToggle.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        updateIcon(next);
    });

    function updateIcon(theme) {
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }

    // شروع برنامه
    initDate();

</script>

</body>
</html>
