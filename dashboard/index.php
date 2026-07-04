<?php
// ============================================
// dashboard/index.php - داشبورد کامل با تقویم سایدبار
// ============================================
session_start();
date_default_timezone_set('Asia/Tehran');

// ==================== تنظیم عنوان صفحه برای هدر ====================
$page_title = 'داشبورد مدیریت زندگی';

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

$userId = $_SESSION['user_id'];

// ==================== فایل‌های دیتا ====================
$dataDir = __DIR__ . '/../data/';
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

$tasksFile = $dataDir . 'tasks.json';
$lifeDataFile = $dataDir . 'life_data_' . $userId . '.json';

// ==================== خواندن تسک‌ها ====================
$allTasks = [];
if (file_exists($tasksFile)) {
    $allTasks = json_decode(file_get_contents($tasksFile), true);
    if (!is_array($allTasks)) $allTasks = [];
}

$userTasks = array_values(array_filter($allTasks, function($task) use ($userId) {
    return ($task['user_id'] ?? '') == $userId;
}));

// ==================== خواندن دیتای زندگی ====================
$lifeData = [];
if (file_exists($lifeDataFile)) {
    $lifeData = json_decode(file_get_contents($lifeDataFile), true);
    if (!is_array($lifeData)) $lifeData = [];
}

// مقدار پیش‌فرض برای بخش‌ها
$finance = $lifeData['finance'] ?? ['expenses' => [], 'savings_goal' => 10000000];
$health = $lifeData['health'] ?? ['steps' => 0, 'water' => 0, 'sleep' => 0, 'workout' => ''];
$habits = $lifeData['habits'] ?? [];
$contacts = $lifeData['contacts'] ?? [];
$leisure = $lifeData['leisure'] ?? ['playlist' => '', 'relax_time' => 0];

// ==================== توابع تبدیل تاریخ ====================
function gregorian_to_jalali($gy, $gm, $gd, $mod = '') {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return ($mod == '') ? [$jy, $jm, $jd] : $jy . $mod . $jm . $mod . $jd;
}

function jalali_to_gregorian($jy, $jm, $jd, $mod = '') {
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0,31,(($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
    for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) $gd -= $sal_a[$gm];
    return ($mod == '') ? [$gy, $gm, $gd] : $gy . $mod . $gm . $mod . $gd;
}

// ==================== دریافت تسک‌های یک روز ====================
function getTasksForDate($year, $month, $day) {
    global $userTasks;
    $jalaliDateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
    
    $filtered = array_filter($userTasks, function($task) use ($jalaliDateStr) {
        if (!isset($task['date']) || empty($task['date'])) return false;
        $parts = explode('-', $task['date']);
        if (count($parts) !== 3) return false;
        list($gy, $gm, $gd) = $parts;
        list($jy, $jm, $jd) = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd);
        $taskJalaliStr = sprintf("%04d-%02d-%02d", $jy, $jm, $jd);
        return $taskJalaliStr === $jalaliDateStr;
    });
    
    usort($filtered, function($a, $b) {
        return ($a['time'] ?? '00:00') <=> ($b['time'] ?? '00:00');
    });
    
    return array_values($filtered);
}

// ==================== پارامترهای تقویم ====================
$jy = isset($_GET['y']) ? (int)$_GET['y'] : null;
$jm = isset($_GET['m']) ? (int)$_GET['m'] : null;

if (!$jy || !$jm || $jm < 1 || $jm > 12) {
    list($gy, $gm, $gd) = explode('-', date('Y-m-d'));
    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
}

list($c_gy, $c_gm, $c_gd) = explode('-', date('Y-m-d'));
list($current_jy, $current_jm, $current_jd) = gregorian_to_jalali($c_gy, $c_gm, $c_gd);

$month_names = [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',
                7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];

$day_names = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

$selectedDate = isset($_GET['date']) ? $_GET['date'] : null;
$selectedTasks = [];
$selectedDateDisplay = '';

if ($selectedDate) {
    list($s_y, $s_m, $s_d) = explode('-', $selectedDate);
    $selectedTasks = getTasksForDate((int)$s_y, (int)$s_m, (int)$s_d);
    $selectedDateDisplay = $s_y . '/' . $s_m . '/' . $s_d;
}

// ==================== آمار ====================
$totalTasks = count($userTasks);
$doneTasks = count(array_filter($userTasks, function($t) { return $t['done'] ?? false; }));
$pendingTasks = $totalTasks - $doneTasks;
$totalExpenses = array_sum(array_column($finance['expenses'] ?? [], 'amount'));
$totalHabits = count($habits);
$doneHabits = count(array_filter($habits, function($h) { return $h['done'] ?? false; }));
$totalContacts = count($contacts);

// ==================== محاسبه ماه‌های قبل و بعد ====================
$prev_m = ($jm == 1) ? 12 : $jm - 1;
$prev_y = ($jm == 1) ? $jy - 1 : $jy;
$next_m = ($jm == 12) ? 1 : $jm + 1;
$next_y = ($jm == 12) ? $jy + 1 : $jy;

// ==================== هدر یکپارچه ====================
include __DIR__ . '/../includes/header.php';
?>

<!-- ============================================
     استایل‌های اختصاصی داشبورد
     ============================================ -->
<style>
    /* ============================================
       متغیرهای CSS
       ============================================ */
    :root {
        --bg-primary: #0f0c29;
        --bg-secondary: #302b63;
        --bg-card: rgba(255,255,255,0.05);
        --bg-card-hover: rgba(255,255,255,0.08);
        --bg-input: rgba(255,255,255,0.05);
        --text-primary: #ffffff;
        --text-secondary: rgba(255,255,255,0.8);
        --text-muted: rgba(255,255,255,0.5);
        --text-light: rgba(255,255,255,0.3);
        --border-color: rgba(255,255,255,0.08);
        --shadow-color: rgba(0,0,0,0.2);
        --shadow-hover: rgba(0,0,0,0.4);
        --badge-color: #667eea;
        --badge-bg: rgba(102,126,234,0.15);
        --today-bg: #f1c40f;
        --today-text: #1a1a2e;
        --weekend-color: #ff6b6b;
        --color-work: #667eea;
        --color-finance: #f093fb;
        --color-health: #4facfe;
        --color-learning: #43e97b;
        --color-relationships: #fa709a;
        --color-leisure: #f6d365;
        --toast-bg: #1a1a2e;
        --modal-overlay: rgba(0,0,0,0.7);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        background: linear-gradient(135deg, var(--bg-primary), var(--bg-secondary));
        min-height: 100vh;
        color: var(--text-primary);
        transition: background 0.3s ease, color 0.3s ease;
    }

    [data-theme="light"] {
        --bg-primary: #f0f2f5;
        --bg-secondary: #ffffff;
        --bg-card: rgba(255,255,255,0.95);
        --bg-card-hover: rgba(0,0,0,0.03);
        --bg-input: #fafafa;
        --text-primary: #1a1a2e;
        --text-secondary: #333333;
        --text-muted: #6c757d;
        --text-light: #999999;
        --border-color: #e2e8f0;
        --shadow-color: rgba(0,0,0,0.08);
        --shadow-hover: rgba(0,0,0,0.15);
        --today-bg: #f1c40f;
        --today-text: #1a1a2e;
        --weekend-color: #dc3545;
        --toast-bg: #f8f9fa;
        --modal-overlay: rgba(0,0,0,0.5);
    }

    .container { 
        max-width: 1400px; 
        margin: 0 auto;
        padding: 0 20px 20px;
    }

    /* ===== هدر ===== */
    .header {
        background: var(--bg-card);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 20px 30px;
        margin-bottom: 25px;
        border: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        transition: all 0.3s ease;
    }

    .header h1 {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .header h1 i { 
        background: linear-gradient(135deg, #667eea, #f5576c);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .user-greeting {
        color: var(--text-secondary);
        font-size: 14px;
        padding: 8px 16px;
        background: var(--bg-input);
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .user-greeting strong { color: var(--text-primary); }

    .btn-back {
        background: var(--bg-input);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        padding: 8px 18px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 14px;
        font-family: inherit;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }

    .btn-back:hover {
        background: var(--bg-card-hover);
        border-color: #667eea;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(102,126,234,0.4);
    }

    .btn-sm {
        padding: 4px 12px;
        font-size: 12px;
        border-radius: 8px;
    }

    .btn-danger-sm {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.3);
        padding: 4px 12px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
        font-family: inherit;
    }

    .btn-danger-sm:hover {
        background: #dc3545;
        color: white;
    }

    .btn-planner {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.3);
        padding: 6px 14px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 12px;
        font-family: inherit;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-planner:hover {
        background: #28a745;
        color: white;
    }

    .theme-toggle-btn {
        background: var(--bg-input);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        width: 40px;
        height: 40px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 18px;
        transition: all 0.3s;
    }

    .theme-toggle-btn:hover {
        border-color: #667eea;
    }

    /* ===== آمار ===== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 15px 20px;
        border: 1px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        border-color: rgba(102,126,234,0.3);
    }

    .stat-number {
        font-size: 28px;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea, #f5576c);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stat-label {
        font-size: 13px;
        color: var(--text-muted);
        margin-top: 4px;
    }

    /* ===== دکمه اضافه کردن ===== */
    .add-fab {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        font-size: 28px;
        cursor: pointer;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .add-fab:hover {
        transform: scale(1.1);
    }

    /* ============================================
       دکشبورد: کارت‌های اصلی
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 20px;
        align-items: start;
    }

    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .card:hover {
        border-color: rgba(102,126,234,0.3);
        background: var(--bg-card-hover);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px var(--shadow-hover);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .card-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-title i { font-size: 18px; }
    .card-title .work-color { color: var(--color-work); }
    .card-title .finance-color { color: var(--color-finance); }
    .card-title .health-color { color: var(--color-health); }
    .card-title .learning-color { color: var(--color-learning); }
    .card-title .relationships-color { color: var(--color-relationships); }
    .card-title .leisure-color { color: var(--color-leisure); }

    .card-content {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.7;
    }

    .card-content .empty-state {
        text-align: center;
        padding: 20px 10px;
        color: var(--text-muted);
        font-size: 13px;
    }

    /* ===== لیست آیتم‌ها ===== */
    .list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-radius: 10px;
        background: var(--bg-input);
        margin-bottom: 6px;
        transition: all 0.3s;
    }

    .list-item:hover {
        background: var(--bg-card-hover);
    }

    .list-item .item-info {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        flex-wrap: wrap;
    }

    .list-item .item-info .check {
        cursor: pointer;
        width: 20px;
        height: 20px;
        min-width: 20px;
        border: 2px solid var(--border-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        background: transparent;
        color: transparent;
    }

    .list-item .item-info .check.done {
        background: #28a745;
        border-color: #28a745;
        color: white;
    }

    .list-item .item-info .item-title {
        font-size: 14px;
        color: var(--text-secondary);
        word-break: break-word;
    }

    .list-item .item-info .item-title.done {
        text-decoration: line-through;
        color: var(--text-muted);
    }

    .list-item .item-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    /* ============================================
       تقویم سایدبار
       ============================================ */
    .calendar-sidebar {
        background: var(--bg-card);
        border-radius: 16px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
        position: sticky;
        top: 20px;
    }

    .calendar-sidebar:hover {
        border-color: rgba(102,126,234,0.3);
    }

    .calendar-sidebar-header {
        padding: 14px 16px;
        background: var(--bg-input);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .calendar-sidebar-header h3 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .calendar-sidebar-header h3 i {
        color: #667eea;
    }

    .calendar-sidebar-header .nav-links {
        display: flex;
        gap: 4px;
    }

    .calendar-sidebar-header .nav-links a {
        color: var(--text-muted);
        text-decoration: none;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 12px;
        transition: all 0.3s;
    }

    .calendar-sidebar-header .nav-links a:hover {
        background: var(--bg-card-hover);
        color: var(--text-primary);
    }

    .calendar-sidebar-body {
        padding: 12px 14px;
    }

    .calendar-sidebar-body .mini-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .calendar-sidebar-body .mini-nav .month-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .calendar-sidebar-body .mini-nav a {
        color: var(--text-muted);
        text-decoration: none;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 13px;
        transition: all 0.3s;
    }

    .calendar-sidebar-body .mini-nav a:hover {
        background: var(--bg-card-hover);
        color: var(--text-primary);
    }

    .calendar-mini-table {
        width: 100%;
        border-collapse: collapse;
    }

    .calendar-mini-table th {
        padding: 4px 2px;
        font-size: 10px;
        font-weight: 600;
        color: var(--text-muted);
        text-align: center;
    }

    .calendar-mini-table th.weekend {
        color: var(--weekend-color);
    }

    .calendar-mini-table td {
        padding: 3px 0;
        text-align: center;
        font-size: 12px;
        color: var(--text-secondary);
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.2s;
        position: relative;
    }

    .calendar-mini-table td:hover:not(.other-month) {
        background: var(--bg-card-hover);
    }

    .calendar-mini-table td .day-num {
        display: inline-block;
        width: 24px;
        height: 24px;
        line-height: 24px;
        border-radius: 50%;
        transition: all 0.2s;
    }

    .calendar-mini-table td .day-num.has-task {
        color: #f5576c;
        font-weight: 600;
    }

    .calendar-mini-table td.today .day-num {
        background: var(--today-bg);
        color: var(--today-text) !important;
        font-weight: bold;
    }

    .calendar-mini-table td.weekend .day-num {
        color: var(--weekend-color);
    }

    .calendar-mini-table td.other-month .day-num {
        color: var(--text-light);
        opacity: 0.4;
    }

    .calendar-mini-table td .task-dot-mini {
        display: block;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: #f5576c;
        margin: 1px auto 0;
    }

    /* ===== سایدبار تسک‌های روز ===== */
    .day-tasks-section {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }

    .day-tasks-section .day-label {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .day-tasks-section .day-label i {
        color: #667eea;
    }

    .day-tasks-section .mini-task {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 10px;
        border-radius: 8px;
        background: var(--bg-input);
        margin-bottom: 4px;
        font-size: 12px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.2s;
        cursor: pointer;
    }

    .day-tasks-section .mini-task:hover {
        background: var(--bg-card-hover);
    }

    .day-tasks-section .mini-task .task-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .day-tasks-section .mini-task .task-status-dot.done {
        background: #28a745;
    }

    .day-tasks-section .mini-task .task-status-dot.pending {
        background: #ffc107;
    }

    .day-tasks-section .mini-task .task-title-mini {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .day-tasks-section .mini-task .task-title-mini.done {
        text-decoration: line-through;
        opacity: 0.6;
    }

    .day-tasks-section .empty-mini {
        text-align: center;
        padding: 15px 10px;
        color: var(--text-light);
        font-size: 12px;
    }

    .day-tasks-section .empty-mini i {
        font-size: 20px;
        display: block;
        margin-bottom: 6px;
        opacity: 0.3;
    }

    /* ============================================
       مودال
       ============================================ */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: var(--modal-overlay);
        align-items: center;
        justify-content: center;
    }

    .modal.show { display: flex; }

    .modal-content {
        background: var(--bg-card);
        backdrop-filter: blur(20px);
        border: 1px solid var(--border-color);
        border-radius: 25px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        transition: all 0.3s ease;
    }

    .modal-header {
        font-size: 20px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-header i { 
        background: linear-gradient(135deg, #667eea, #f5576c);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .modal-body input,
    .modal-body select,
    .modal-body textarea {
        width: 100%;
        padding: 12px 15px;
        margin-bottom: 15px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        font-size: 14px;
        font-family: inherit;
        background: var(--bg-input);
        color: var(--text-primary);
        transition: all 0.3s;
    }

    .modal-body input:focus,
    .modal-body select:focus,
    .modal-body textarea:focus {
        outline: none;
        border-color: #667eea;
        background: var(--bg-card-hover);
    }

    .modal-body label {
        display: block;
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 4px;
    }

    .modal-footer {
        display: flex;
        gap: 12px;
        margin-top: 10px;
    }

    .btn-cancel {
        background: var(--bg-input);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        padding: 12px 25px;
        border-radius: 12px;
        cursor: pointer;
        flex: 1;
        font-family: inherit;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-cancel:hover { background: var(--bg-card-hover); }

    .btn-save {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 12px;
        cursor: pointer;
        flex: 1;
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-save:hover { transform: scale(1.02); }

    /* ============================================
       Toast
       ============================================ */
    .toast {
        position: fixed;
        bottom: 100px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: var(--toast-bg);
        color: var(--text-primary);
        padding: 12px 25px;
        border-radius: 12px;
        z-index: 9999;
        opacity: 0;
        transition: all 0.4s ease;
        font-family: 'Vazirmatn', sans-serif;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        border: 1px solid var(--border-color);
    }

    .toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    .toast.success { background: #28a745; color: white; }
    .toast.error { background: #dc3545; color: white; }
    .toast.info { background: #17a2b8; color: white; }

    /* ============================================
       Responsive
       ============================================ */
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .calendar-sidebar {
            position: static;
            order: -1;
        }
    }

    @media (max-width: 768px) {
        body { padding: 0; }
        .container { padding: 0 10px 10px; }
        .cards-grid { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .stat-number { font-size: 22px; }
        .add-fab {
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            font-size: 24px;
        }
        .calendar-sidebar-header .nav-links a {
            font-size: 11px;
            padding: 2px 6px;
        }
        .calendar-mini-table td .day-num {
            width: 20px;
            height: 20px;
            line-height: 20px;
            font-size: 11px;
        }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .card-title { font-size: 14px; }
        .calendar-sidebar-body .mini-nav .month-title { font-size: 12px; }
        .calendar-mini-table th { font-size: 9px; }
        .calendar-mini-table td { font-size: 10px; padding: 2px 0; }
        .calendar-mini-table td .day-num { width: 18px; height: 18px; line-height: 18px; font-size: 10px; }
    }
</style>

<div class="container">
    <!-- ===== آمار ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalTasks; ?></div>
            <div class="stat-label">📋 کل تسک‌ها</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $doneTasks; ?></div>
            <div class="stat-label">✅ انجام شده</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $pendingTasks; ?></div>
            <div class="stat-label">⏳ در انتظار</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($totalExpenses); ?></div>
            <div class="stat-label">💰 هزینه کل</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $doneHabits . '/' . $totalHabits; ?></div>
            <div class="stat-label">📚 عادت‌ها</div>
        </div>
    </div>

    <!-- ===== دکشبورد ===== -->
    <div class="dashboard-grid">

        <!-- ===== کارت‌های اصلی ===== -->
        <div class="cards-grid">

            <!-- 1. کار و بهره‌وری (تسک‌های امروز) -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-briefcase work-color"></i> کار و بهره‌وری</div>
                    <a href="../planner/index.php" class="btn-primary btn-sm"><i class="fas fa-plus"></i></a>
                </div>
                <div class="card-content">
                    <?php
                    $todayTasks = getTasksForDate($current_jy, $current_jm, $current_jd);
                    $displayTasks = array_slice($todayTasks, 0, 5);
                    ?>
                    <?php if (empty($displayTasks)): ?>
                        <div class="empty-state">هیچ وظیفه‌ای برای امروز ثبت نشده است.</div>
                    <?php else: ?>
                        <?php foreach ($displayTasks as $task): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <span class="check <?php echo ($task['done'] ?? false) ? 'done' : ''; ?>">
                                        <?php if ($task['done'] ?? false): ?><i class="fas fa-check"></i><?php endif; ?>
                                    </span>
                                    <span class="item-title <?php echo ($task['done'] ?? false) ? 'done' : ''; ?>">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($todayTasks) > 5): ?>
                            <div style="text-align:center; margin-top:8px; font-size:12px; color:var(--text-muted);">
                                + <?php echo count($todayTasks) - 5; ?> وظیفه دیگر
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. مالی -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-wallet finance-color"></i> مالی</div>
                    <button class="btn-primary btn-sm" onclick="openModal('expense')"><i class="fas fa-plus"></i></button>
                </div>
                <div class="card-content">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; flex-wrap:wrap; gap:5px;">
                        <span>💰 کل هزینه‌ها: <strong><?php echo number_format($totalExpenses); ?></strong> تومان</span>
                        <span>🎯 هدف: <strong><?php echo number_format($finance['savings_goal'] ?? 0); ?></strong></span>
                    </div>
                    <?php 
                    $expenses = array_slice($finance['expenses'] ?? [], -4);
                    if (empty($expenses)): ?>
                        <div class="empty-state">هیچ هزینه‌ای ثبت نشده است.</div>
                    <?php else: ?>
                        <?php foreach ($expenses as $expense): ?>
                            <div class="list-item">
                                <span><?php echo htmlspecialchars($expense['title']); ?></span>
                                <span><strong><?php echo number_format($expense['amount']); ?></strong> تومان</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. سلامت -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-heartbeat health-color"></i> سلامت</div>
                </div>
                <div class="card-content">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:13px; color:var(--text-muted);">👣 گام</label>
                            <input type="number" value="<?php echo $health['steps'] ?? 0; ?>" 
                                   onchange="updateHealth('steps', this.value)" 
                                   style="width:100%; padding:8px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-input); color:var(--text-primary);">
                        </div>
                        <div>
                            <label style="font-size:13px; color:var(--text-muted);">💧 آب</label>
                            <input type="number" value="<?php echo $health['water'] ?? 0; ?>" 
                                   onchange="updateHealth('water', this.value)"
                                   style="width:100%; padding:8px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-input); color:var(--text-primary);">
                        </div>
                        <div>
                            <label style="font-size:13px; color:var(--text-muted);">😴 خواب</label>
                            <input type="number" value="<?php echo $health['sleep'] ?? 0; ?>" 
                                   onchange="updateHealth('sleep', this.value)"
                                   style="width:100%; padding:8px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-input); color:var(--text-primary);">
                        </div>
                        <div>
                            <label style="font-size:13px; color:var(--text-muted);">🏋️ ورزش</label>
                            <input type="text" value="<?php echo htmlspecialchars($health['workout'] ?? ''); ?>" 
                                   placeholder="مثلاً: ۲۰ دقیقه دویدن"
                                   onchange="updateHealth('workout', this.value)"
                                   style="width:100%; padding:8px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-input); color:var(--text-primary);">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. عادت‌ها -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-graduation-cap learning-color"></i> عادت‌ها</div>
                    <button class="btn-primary btn-sm" onclick="openModal('habit')"><i class="fas fa-plus"></i></button>
                </div>
                <div class="card-content">
                    <?php 
                    $displayHabits = array_slice($habits, -5);
                    if (empty($displayHabits)): ?>
                        <div class="empty-state">هیچ عادتی ثبت نشده است.</div>
                    <?php else: ?>
                        <?php foreach ($displayHabits as $habit): ?>
                            <div class="list-item" data-id="<?php echo $habit['id']; ?>">
                                <div class="item-info">
                                    <span class="check <?php echo ($habit['done'] ?? false) ? 'done' : ''; ?>" onclick="toggleHabit('<?php echo $habit['id']; ?>')">
                                        <?php if ($habit['done'] ?? false): ?><i class="fas fa-check"></i><?php endif; ?>
                                    </span>
                                    <span class="item-title <?php echo ($habit['done'] ?? false) ? 'done' : ''; ?>">
                                        <?php echo htmlspecialchars($habit['name']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 5. روابط -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-users relationships-color"></i> روابط</div>
                    <button class="btn-primary btn-sm" onclick="openModal('contact')"><i class="fas fa-plus"></i></button>
                </div>
                <div class="card-content">
                    <?php 
                    $displayContacts = array_slice($contacts, -5);
                    if (empty($displayContacts)): ?>
                        <div class="empty-state">هیچ مخاطبی ثبت نشده است.</div>
                    <?php else: ?>
                        <?php foreach ($displayContacts as $contact): ?>
                            <div class="list-item">
                                <span><strong><?php echo htmlspecialchars($contact['name']); ?></strong> 
                                    <span style="font-size:12px; color:var(--text-muted);">(<?php echo htmlspecialchars($contact['relation']); ?>)</span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 6. تفریح -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-music leisure-color"></i> تفریح</div>
                </div>
                <div class="card-content">
                    <div style="margin-bottom:12px;">
                        <label style="font-size:13px; color:var(--text-muted);">🎵 پلی‌لیست</label>
                        <input type="text" value="<?php echo htmlspecialchars($leisure['playlist'] ?? ''); ?>" 
                               placeholder="لینک یا نام پلی‌لیست..."
                               onchange="updateLeisure('playlist', this.value)"
                               style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-input); color:var(--text-primary); margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-size:13px; color:var(--text-muted);">⏱️ زمان استراحت (دقیقه)</label>
                        <input type="number" value="<?php echo $leisure['relax_time'] ?? 0; ?>" 
                               onchange="updateLeisure('relax_time', this.value)"
                               style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-input); color:var(--text-primary); margin-top:5px;">
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== سایدبار تقویم ===== -->
        <div class="calendar-sidebar">
            <div class="calendar-sidebar-header">
                <h3><i class="fas fa-calendar-alt"></i> تقویم</h3>
                <div class="nav-links">
                    <a href="?y=<?= $jy ?>&m=<?= $jm ?>" title="رفرش"><i class="fas fa-sync-alt"></i></a>
                    <a href="../planner/index.php" title="پلنر"><i class="fas fa-external-link-alt"></i></a>
                </div>
            </div>
            <div class="calendar-sidebar-body">
                <div class="mini-nav">
                    <a href="?y=<?= $prev_y ?>&m=<?= $prev_m ?>"><i class="fas fa-chevron-right"></i></a>
                    <span class="month-title"><?= $month_names[$jm] ?> <?= $jy ?></span>
                    <a href="?y=<?= $next_y ?>&m=<?= $next_m ?>"><i class="fas fa-chevron-left"></i></a>
                </div>

                <table class="calendar-mini-table">
                    <tr>
                        <?php foreach ($day_names as $index => $name): ?>
                            <th class="<?= ($index == 0 || $index == 6) ? 'weekend' : '' ?>"><?= mb_substr($name, 0, 1) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <?php
                    list($first_gy, $first_gm, $first_gd) = jalali_to_gregorian($jy, $jm, 1);
                    $first_weekday = (int)date('w', mktime(0, 0, 0, $first_gm, $first_gd, $first_gy));
                    $weekday_start = ($first_weekday + 1) % 7;
                    $days_in_month = ($jm <= 6) ? 31 : (($jm < 12) ? 30 : (($jy % 4 == 3) ? 30 : 29));

                    echo "<tr>";
                    for ($i = 0; $i < $weekday_start; $i++) {
                        echo "<td class='other-month'></td>";
                    }

                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $is_today = ($jy == $current_jy && $jm == $current_jm && $day == $current_jd);
                        $dateStr = sprintf("%04d-%02d-%02d", $jy, $jm, $day);
                        $dayTasks = getTasksForDate($jy, $jm, $day);
                        $hasTasks = count($dayTasks) > 0;
                        $dayOfWeek = ($weekday_start + $day - 1) % 7;
                        $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
                        
                        $class = "";
                        if ($is_today) $class = "today";
                        if ($isWeekend) $class .= " weekend";
                        
                        echo "<td class='$class' onclick='window.location.href=\"?date=$dateStr&y=$jy&m=$jm\"'>";
                        echo "<span class='day-num " . ($hasTasks ? 'has-task' : '') . "'>$day</span>";
                        echo "</td>";

                        if (($weekday_start + $day) % 7 == 0) {
                            echo "</tr><tr>";
                        }
                    }

                    $remaining = (7 - (($weekday_start + $days_in_month) % 7)) % 7;
                    for ($i = 0; $i < $remaining; $i++) {
                        echo "<td class='other-month'></td>";
                    }
                    echo "</tr>";
                    ?>
                </table>

                <!-- ===== تسک‌های روز انتخاب‌شده ===== -->
                <div class="day-tasks-section">
                    <div class="day-label">
                        <i class="fas fa-tasks"></i>
                        <?php if ($selectedDate): ?>
                            تسک‌های <?= $selectedDateDisplay ?>
                        <?php else: ?>
                            تسک‌های امروز
                            <?php
                            $defaultTasks = getTasksForDate($current_jy, $current_jm, $current_jd);
                            ?>
                        <?php endif; ?>
                    </div>
                    <?php 
                    $displayTasks = $selectedDate ? $selectedTasks : getTasksForDate($current_jy, $current_jm, $current_jd);
                    if (empty($displayTasks)): ?>
                        <div class="empty-mini">
                            <i class="fas fa-calendar-plus"></i>
                            هیچ تسکی برای این روز وجود ندارد
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($displayTasks, 0, 5) as $task): ?>
                            <a href="../planner/index.php" class="mini-task">
                                <span class="task-status-dot <?= ($task['done'] ?? false) ? 'done' : 'pending' ?>"></span>
                                <span class="task-title-mini <?= ($task['done'] ?? false) ? 'done' : '' ?>">
                                    <?= htmlspecialchars($task['title']) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($displayTasks) > 5): ?>
                            <div style="text-align:center; font-size:10px; color:var(--text-muted); padding:4px 0;">
                                + <?= count($displayTasks) - 5; ?> تسک دیگر
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ===== دکمه افزودن ===== -->
<button class="add-fab" onclick="openModal('task')" title="افزودن تسک جدید">
    <i class="fas fa-plus"></i>
</button>

<!-- ===== مودال ===== -->
<div class="modal" id="lifeModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-plus-circle"></i>
            <span id="modalTitle">عنوان</span>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- محتوای داینامیک -->
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal()">انصراف</button>
            <button class="btn-save" id="modalSaveBtn" onclick="saveModal()">ذخیره</button>
        </div>
    </div>
</div>

<!-- ===== Toast ===== -->
<div class="toast" id="toast"></div>

<script>
    // ============================================
    // متغیرها
    // ============================================
    let currentModalType = '';

    // ============================================
    // Toast
    // ============================================
    function showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast ' + type;
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // ============================================
    // مودال
    // ============================================
    function openModal(type) {
        currentModalType = type;
        const modal = document.getElementById('lifeModal');
        const title = document.getElementById('modalTitle');
        const body = document.getElementById('modalBody');

        let html = '';
        switch(type) {
            case 'task':
                title.textContent = 'افزودن تسک جدید (به پلنر)';
                html = `
                    <label>عنوان تسک</label>
                    <input type="text" id="taskTitle" placeholder="مثلاً: تکمیل گزارش پروژه">
                    <label>توضیحات</label>
                    <textarea id="taskDesc" placeholder="توضیحات بیشتر..." rows="2"></textarea>
                    <label>تاریخ</label>
                    <input type="date" id="taskDate" value="<?php echo date('Y-m-d'); ?>">
                    <label>اولویت</label>
                    <select id="taskPriority">
                        <option value="high">بالا</option>
                        <option value="medium" selected>متوسط</option>
                        <option value="low">پایین</option>
                    </select>
                    <p style="font-size:12px; color:var(--text-muted); margin-top:8px;">
                        <i class="fas fa-info-circle"></i> این تسک در <a href="../planner/index.php" style="color:#667eea;">پلنر</a> ذخیره می‌شود
                    </p>
                `;
                break;
            case 'expense':
                title.textContent = 'ثبت هزینه جدید';
                html = `
                    <label>عنوان هزینه</label>
                    <input type="text" id="expenseTitle" placeholder="مثلاً: خرید مواد غذایی">
                    <label>مبلغ (تومان)</label>
                    <input type="number" id="expenseAmount" placeholder="مثلاً: ۵۰۰۰۰۰">
                    <label>دسته‌بندی</label>
                    <select id="expenseCategory">
                        <option value="food">خوراک</option>
                        <option value="housing">مسکن</option>
                        <option value="transport">حمل و نقل</option>
                        <option value="entertainment">تفریح</option>
                        <option value="other">سایر</option>
                    </select>
                `;
                break;
            case 'habit':
                title.textContent = 'افزودن عادت جدید';
                html = `
                    <label>نام عادت</label>
                    <input type="text" id="habitName" placeholder="مثلاً: ۳۰ دقیقه مطالعه">
                `;
                break;
            case 'contact':
                title.textContent = 'افزودن مخاطب جدید';
                html = `
                    <label>نام</label>
                    <input type="text" id="contactName" placeholder="نام مخاطب">
                    <label>شماره تماس</label>
                    <input type="text" id="contactPhone" placeholder="۰۹۱۲...">
                    <label>نسبت</label>
                    <select id="contactRelation">
                        <option value="family">خانواده</option>
                        <option value="friend">دوست</option>
                        <option value="colleague">همکار</option>
                        <option value="other">سایر</option>
                    </select>
                `;
                break;
            default:
                return;
        }
        body.innerHTML = html;
        modal.classList.add('show');
    }

    function closeModal() {
        document.getElementById('lifeModal').classList.remove('show');
        currentModalType = '';
    }

    function saveModal() {
        const type = currentModalType;
        
        switch(type) {
            case 'task':
                const title = document.getElementById('taskTitle')?.value;
                if (!title) { showToast('عنوان تسک را وارد کنید.', 'error'); return; }
                // هدایت به پلنر برای افزودن تسک
                window.location.href = '../planner/index.php';
                break;
            case 'expense':
                const expenseTitle = document.getElementById('expenseTitle')?.value;
                const amount = document.getElementById('expenseAmount')?.value;
                if (!expenseTitle || !amount) { showToast('عنوان و مبلغ را وارد کنید.', 'error'); return; }
                
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('action', 'add_expense');
                formData.append('title', expenseTitle);
                formData.append('amount', amount);
                formData.append('category', document.getElementById('expenseCategory')?.value || 'other');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('هزینه ثبت شد.', 'success');
                        closeModal();
                        setTimeout(() => location.reload(), 500);
                    }
                });
                break;
            case 'habit':
                const habitName = document.getElementById('habitName')?.value;
                if (!habitName) { showToast('نام عادت را وارد کنید.', 'error'); return; }
                
                const habitData = new FormData();
                habitData.append('ajax', '1');
                habitData.append('action', 'add_habit');
                habitData.append('name', habitName);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: habitData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('عادت افزوده شد.', 'success');
                        closeModal();
                        setTimeout(() => location.reload(), 500);
                    }
                });
                break;
            case 'contact':
                const contactName = document.getElementById('contactName')?.value;
                if (!contactName) { showToast('نام مخاطب را وارد کنید.', 'error'); return; }
                
                const contactData = new FormData();
                contactData.append('ajax', '1');
                contactData.append('action', 'add_contact');
                contactData.append('name', contactName);
                contactData.append('phone', document.getElementById('contactPhone')?.value || '');
                contactData.append('relation', document.getElementById('contactRelation')?.value || 'friend');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: contactData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('مخاطب افزوده شد.', 'success');
                        closeModal();
                        setTimeout(() => location.reload(), 500);
                    }
                });
                break;
            default:
                return;
        }
    }

    // ============================================
    // توابع AJAX
    // ============================================
    function toggleHabit(id) {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'toggle_habit');
        formData.append('habit_id', id);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('وضعیت عادت تغییر کرد.', 'success');
                setTimeout(() => location.reload(), 300);
            }
        });
    }

    function updateHealth(field, value) {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'update_health');
        formData.append('field', field);
        formData.append('value', value);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) showToast('ذخیره شد.', 'success');
        });
    }

    function updateLeisure(field, value) {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'update_leisure');
        formData.append('field', field);
        formData.append('value', value);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) showToast('ذخیره شد.', 'success');
        });
    }

    // ============================================
    // بستن مودال
    // ============================================
    document.getElementById('lifeModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    console.log('🚀 داشبورد مدیریت زندگی بارگذاری شد!');
    console.log('📅 امروز:', '<?= $month_names[$current_jm] ?> <?= $current_jd ?> <?= $current_jy ?>');
    console.log('📋 کل تسک‌ها:', <?= $totalTasks ?>);
</script>

<?php
// ==================== پردازش AJAX ====================
if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    // بارگذاری دیتا
    $lifeData = [];
    if (file_exists($lifeDataFile)) {
        $lifeData = json_decode(file_get_contents($lifeDataFile), true);
        if (!is_array($lifeData)) $lifeData = [];
    }
    
    switch ($action) {
        case 'add_expense':
            $expense = [
                'id' => uniqid(),
                'title' => htmlspecialchars($_POST['title'] ?? ''),
                'amount' => intval($_POST['amount'] ?? 0),
                'category' => htmlspecialchars($_POST['category'] ?? 'other'),
                'date' => date('Y-m-d H:i:s')
            ];
            if (!isset($lifeData['finance'])) $lifeData['finance'] = ['expenses' => [], 'savings_goal' => 10000000];
            $lifeData['finance']['expenses'][] = $expense;
            $response['success'] = true;
            break;
            
        case 'add_habit':
            $habit = [
                'id' => uniqid(),
                'name' => htmlspecialchars($_POST['name'] ?? ''),
                'done' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
            if (!isset($lifeData['habits'])) $lifeData['habits'] = [];
            $lifeData['habits'][] = $habit;
            $response['success'] = true;
            break;
            
        case 'toggle_habit':
            $habitId = $_POST['habit_id'] ?? '';
            if (isset($lifeData['habits'])) {
                foreach ($lifeData['habits'] as &$h) {
                    if ($h['id'] == $habitId) {
                        $h['done'] = !($h['done'] ?? false);
                        $response['success'] = true;
                        break;
                    }
                }
            }
            break;
            
        case 'update_health':
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            if (!isset($lifeData['health'])) $lifeData['health'] = ['steps' => 0, 'water' => 0, 'sleep' => 0, 'workout' => ''];
            $lifeData['health'][$field] = $value;
            $response['success'] = true;
            break;
            
        case 'update_leisure':
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            if (!isset($lifeData['leisure'])) $lifeData['leisure'] = ['playlist' => '', 'relax_time' => 0];
            $lifeData['leisure'][$field] = $value;
            $response['success'] = true;
            break;
            
        case 'add_contact':
            $contact = [
                'id' => uniqid(),
                'name' => htmlspecialchars($_POST['name'] ?? ''),
                'phone' => htmlspecialchars($_POST['phone'] ?? ''),
                'relation' => htmlspecialchars($_POST['relation'] ?? 'friend')
            ];
            if (!isset($lifeData['contacts'])) $lifeData['contacts'] = [];
            $lifeData['contacts'][] = $contact;
            $response['success'] = true;
            break;
    }
    
    if ($response['success']) {
        file_put_contents($lifeDataFile, json_encode($lifeData, JSON_PRETTY_PRINT));
    }
    
    echo json_encode($response);
    exit;
}
?>