<?php
// ============================================
// dashboard/index.php - داشبورد کامل با تقویم سایدبار
// ============================================
session_start();
date_default_timezone_set('Asia/Tehran');

// ==================== بررسی فعال بودن ماژول ====================
$settingsFile = __DIR__ . '/../data/settings.json';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (isset($settings['modules']['dashboard']['enabled']) && 
        $settings['modules']['dashboard']['enabled'] === false) {
        header('Location: ../disabled_module.php?module=dashboard');
        exit;
    }
}

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
$projectsFile = $dataDir . 'projects.json';
$dashboardProjectsFile = $dataDir . 'dashboard_projects_' . $userId . '.json';
$lifeDataFile = $dataDir . 'life_data_' . $userId . '.json';

// ==================== خواندن پروژه‌های انتخاب شده برای داشبورد ====================
$dashboardProjects = [];
if (file_exists($dashboardProjectsFile)) {
    $dashboardProjects = json_decode(file_get_contents($dashboardProjectsFile), true);
    if (!is_array($dashboardProjects)) $dashboardProjects = [];
}

// ==================== خواندن همه پروژه‌ها از فایل اصلی ====================
$allProjects = [];
if (file_exists($projectsFile)) {
    $allProjects = json_decode(file_get_contents($projectsFile), true);
    if (!is_array($allProjects)) $allProjects = [];
}

// فیلتر پروژه‌های کاربر فعلی
$userProjects = array_values(array_filter($allProjects, function($project) use ($userId) {
    return ($project['user_id'] ?? '') == $userId;
}));

// ==================== خواندن تسک‌ها ====================
$allTasks = [];
if (file_exists($tasksFile)) {
    $allTasks = json_decode(file_get_contents($tasksFile), true);
    if (!is_array($allTasks)) $allTasks = [];
}

$userTasks = array_values(array_filter($allTasks, function($task) use ($userId) {
    return ($task['user_id'] ?? '') == $userId;
}));

// ==================== تابع محاسبه پیشرفت پروژه (نسخه اصلاح شده با دیباگ) ====================
function getProjectProgress($projectId, $userTasks) {
    // دیباگ: نمایش آی دی پروژه و تعداد تسک‌ها
    error_log("Checking project ID: " . $projectId);
    error_log("Total user tasks: " . count($userTasks));
    
    // پیدا کردن تسک‌های مربوط به این پروژه
    $projectTasks = array_values(array_filter($userTasks, function($task) use ($projectId) {
        // بررسی می‌کنیم که project_id در تسک با id پروژه برابر باشد
        $hasProjectId = isset($task['project_id']) && $task['project_id'] == $projectId;
        
        // دیباگ: نمایش تسک‌هایی که project_id دارند
        if (isset($task['project_id'])) {
            error_log("Task has project_id: " . $task['project_id'] . " - Title: " . ($task['title'] ?? ''));
        }
        
        return $hasProjectId;
    }));
    
    // دیباگ: نمایش تعداد تسک‌های پیدا شده
    error_log("Found tasks for project: " . count($projectTasks));
    
    if (empty($projectTasks)) {
        return ['total' => 0, 'done' => 0, 'pending' => 0, 'percent' => 0];
    }
    
    $total = count($projectTasks);
    $done = count(array_filter($projectTasks, function($task) {
        return isset($task['done']) && $task['done'] == true;
    }));
    $pending = $total - $done;
    $percent = $total > 0 ? round(($done / $total) * 100) : 0;
    
    return [
        'total' => $total,
        'done' => $done,
        'pending' => $pending,
        'percent' => $percent
    ];
}

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

// افزودن اطلاعات پیشرفت به پروژه‌ها
foreach ($userProjects as &$project) {
    $progress = getProjectProgress($project['id'], $userTasks);
    $project['_progress'] = $progress;
    $project['_selected'] = in_array($project['id'], $dashboardProjects);
}
unset($project);

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

// ==================== پارامترهای تقویم ====================
$jy = isset($_GET['y']) ? (int)$_GET['y'] : null;
$jm = isset($_GET['m']) ? (int)$_GET['m'] : null;

if (!$jy || !$jm || $jm < 1 || $jm > 12) {
    list($gy, $gm, $gd) = explode('-', date('Y-m-d'));
    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
}

// ==================== محاسبه ماه‌های قبل و بعد ====================
$prev_m = ($jm == 1) ? 12 : $jm - 1;
$prev_y = ($jm == 1) ? $jy - 1 : $jy;
$next_m = ($jm == 12) ? 1 : $jm + 1;
$next_y = ($jm == 12) ? $jy + 1 : $jy;

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
$doneTasks = count(array_filter($userTasks, function($t) { return isset($t['done']) && $t['done'] == true; }));
$pendingTasks = $totalTasks - $doneTasks;
$totalExpenses = array_sum(array_column($finance['expenses'] ?? [], 'amount'));
$totalHabits = count($habits);
$doneHabits = count(array_filter($habits, function($h) { return isset($h['done']) && $h['done'] == true; }));
$totalContacts = count($contacts);
$selectedProjects = array_filter($userProjects, function($p) { return isset($p['_selected']) && $p['_selected'] == true; });

// ==================== تسک‌های امروز ====================
$todayTasks = getTasksForDate($current_jy, $current_jm, $current_jd);

// ==================== هدر یکپارچه ====================
include __DIR__ . '/../includes/header.php';
?>

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

    /* ============================================
       دکشبورد: دو ستون اصلی
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 20px;
        align-items: start;
    }

    /* بخش اصلی با دو ستون داخلی */
    .dashboard-main {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 20px;
        align-items: start;
    }

    /* ===== کارت تایمر ===== */
    .countdown-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px;
        border: 1px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }

    .countdown-card:hover {
        transform: translateY(-3px);
        border-color: rgba(102,126,234,0.3);
    }

    .countdown-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .countdown-title span#countdownTitleSpan {
        cursor: text;
        border-bottom: 1px dashed transparent;
        transition: border-color 0.2s;
        padding: 2px 4px;
        border-radius: 4px;
    }

    .countdown-title span#countdownTitleSpan:hover,
    .countdown-title span#countdownTitleSpan:focus {
        border-bottom-color: var(--primary-color);
        background-color: rgba(0, 0, 0, 0.02);
        outline: none;
    }

    .btn-edit-title {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 4px;
        font-size: 14px;
        opacity: 0.6;
        transition: all 0.2s;
    }

    .btn-edit-title:hover {
        opacity: 1;
        color: var(--primary-color);
        transform: scale(1.1);
    }

    /* تایمر - 6 ستون در یک ردیف */
    .countdown-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 6px;
    }

    .countdown-item {
        background: var(--bg-input);
        border-radius: 10px;
        padding: 8px 4px;
        transition: all 0.3s;
        text-align: center;
    }

    .countdown-item:hover {
        background: var(--bg-card-hover);
    }

    .countdown-value {
        font-size: 16px;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea, #f5576c);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .countdown-label {
        font-size: 9px;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .btn-set-date {
        margin-top: 12px;
        background: var(--bg-input);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        padding: 8px 16px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 12px;
        font-family: inherit;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        justify-content: center;
    }

    .btn-set-date:hover {
        background: var(--bg-card-hover);
        border-color: #667eea;
    }

    /* ===== بخش تسک‌های امروز ===== */
    .today-tasks-section {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .today-tasks-section:hover {
        border-color: rgba(102,126,234,0.3);
    }

    .today-tasks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .today-tasks-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .today-tasks-title i {
        color: #667eea;
    }

    .today-tasks-title .count-badge {
        font-size: 12px;
        background: var(--bg-input);
        padding: 2px 10px;
        border-radius: 12px;
        color: var(--text-muted);
        font-weight: normal;
    }

    .btn-view-all-tasks {
        background: var(--bg-input);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        padding: 4px 14px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 12px;
        font-family: inherit;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        text-decoration: none;
    }

    .btn-view-all-tasks:hover {
        background: var(--bg-card-hover);
        border-color: #667eea;
        color: var(--text-primary);
    }

    /* لیست تسک‌های امروز */
    .today-tasks-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 400px;
        overflow-y: auto;
        padding-right: 5px;
    }

    .today-tasks-list::-webkit-scrollbar {
        width: 4px;
    }

    .today-tasks-list::-webkit-scrollbar-track {
        background: var(--bg-input);
        border-radius: 4px;
    }

    .today-tasks-list::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 4px;
    }

    .today-task-item {
        background: var(--bg-input);
        border-radius: 12px;
        padding: 12px;
        transition: all 0.3s;
        border: 1px solid var(--border-color);
    }

    .today-task-item:hover {
        background: var(--bg-card-hover);
        border-color: rgba(102, 126, 234, 0.3);
    }

    .task-main-info {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .task-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .task-status-dot.done {
        background: #28a745;
    }

    .task-status-dot.pending {
        background: #ffc107;
    }

    .task-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .task-title.done {
        text-decoration: line-through;
        opacity: 0.6;
    }

    .task-meta-info {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }

    .task-time {
        font-size: 11px;
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(255,255,255,0.05);
        padding: 2px 8px;
        border-radius: 6px;
    }

    .task-project-badge {
        font-size: 11px;
        color: var(--text-secondary);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(255,255,255,0.05);
        padding: 2px 8px;
        border-radius: 6px;
        border: 1px solid transparent;
    }

    .task-category-badge {
        font-size: 11px;
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(255,255,255,0.05);
        padding: 2px 8px;
        border-radius: 6px;
    }

    .empty-today-tasks-msg {
        text-align: center;
        padding: 30px 10px;
        color: var(--text-muted);
        font-size: 13px;
    }

    .empty-today-tasks-msg i {
        font-size: 32px;
        display: block;
        margin-bottom: 10px;
        opacity: 0.3;
    }

    .empty-today-tasks-msg .hint {
        font-size: 12px;
        color: var(--text-light);
        margin-top: 8px;
    }

    /* ===== مودال انتخاب پروژه ===== */
    .project-picker-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
        max-height: 300px;
        overflow-y: auto;
        padding-right: 5px;
    }

    .project-picker-grid::-webkit-scrollbar {
        width: 4px;
    }

    .project-picker-grid::-webkit-scrollbar-track {
        background: var(--bg-input);
        border-radius: 4px;
    }

    .project-picker-grid::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 4px;
    }

    .project-picker-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        background: var(--bg-input);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s;
        border: 2px solid transparent;
    }

    .project-picker-item:hover {
        background: var(--bg-card-hover);
        border-color: var(--border-color);
    }

    .project-picker-item.selected {
        border-color: #28a745;
        background: rgba(40, 167, 69, 0.1);
    }

    .project-picker-item .picker-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .project-picker-item .picker-info {
        flex: 1;
    }

    .project-picker-item .picker-name {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .project-picker-item .picker-progress {
        font-size: 11px;
        color: var(--text-muted);
    }

    .project-picker-item .picker-check {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.3s;
        background: transparent;
    }

    .project-picker-item.selected .picker-check {
        background: #28a745;
        border-color: #28a745;
    }

    .project-picker-item .picker-check i {
        font-size: 12px;
        color: white;
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

    /* استایل‌های مودال روزشمار */
    #countdownModal .modal-body input[type="text"] {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        font-size: 16px;
        font-family: inherit;
        background: var(--bg-input);
        color: var(--text-primary);
        text-align: center;
    }

    #countdownModal .picker-day {
        cursor: pointer;
        transition: all 0.2s;
    }

    #countdownModal .picker-day:hover {
        background: var(--bg-card-hover);
    }

    #countdownModal .picker-day.selected {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        font-weight: bold;
    }

    #countdownModal .picker-day.today {
        border: 2px solid #f1c40f;
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
        max-width: 520px;
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
        .dashboard-main {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        body { padding: 0; }
        .container { padding: 0 10px 10px; }
        .dashboard-main {
            grid-template-columns: 1fr;
        }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .stat-number { font-size: 22px; }
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
        .countdown-grid {
            grid-template-columns: repeat(6, 1fr);
        }
        .countdown-value {
            font-size: 14px;
        }
        .countdown-label {
            font-size: 8px;
        }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .calendar-sidebar-body .mini-nav .month-title { font-size: 12px; }
        .calendar-mini-table th { font-size: 9px; }
        .calendar-mini-table td { font-size: 10px; padding: 2px 0; }
        .calendar-mini-table td .day-num { width: 18px; height: 18px; line-height: 18px; font-size: 10px; }
        .countdown-value { font-size: 12px; }
        .countdown-label { font-size: 7px; }
    }
</style>

<div class="container">
    <!-- ===== دکشبورد ===== -->
    <div class="dashboard-grid">

        <!-- ===== بخش اصلی (دو ستونی) ===== -->
        <div class="dashboard-main">

            <!-- ===== ستون چپ: تایمر ===== -->
            <div class="countdown-card">
                <div class="countdown-title">
                    <i class="fas fa-hourglass-half"></i>
                    <span id="countdownTitleSpan" contenteditable="true" onblur="saveCountdownTitle()">روزشمار هدف</span>
                    <button class="btn-edit-title" onclick="editCountdownTitle()" title="ویرایش عنوان">
                        <i class="fas fa-pen"></i>
                    </button>
                </div>
                <div class="countdown-grid">
                    <div class="countdown-item">
                        <div class="countdown-value" id="yearsRemaining">-</div>
                        <div class="countdown-label">سال</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="monthsRemaining">-</div>
                        <div class="countdown-label">ماه</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="daysRemaining">-</div>
                        <div class="countdown-label">روز</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="hoursRemaining">-</div>
                        <div class="countdown-label">ساعت</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="minutesRemaining">-</div>
                        <div class="countdown-label">دقیقه</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="secondsRemaining">-</div>
                        <div class="countdown-label">ثانیه</div>
                    </div>
                </div>
                <button class="btn-set-date" onclick="openCountdownModal()">
                    <i class="fas fa-calendar-alt"></i>
                    تنظیم تاریخ هدف
                </button>
            </div>

            <!-- ===== ستون راست: تسک‌های امروز ===== -->
            <div class="today-tasks-section">
                <div class="today-tasks-header">
                    <div class="today-tasks-title">
                        <i class="fas fa-list-check"></i>
                        تسک‌های امروز
                        <span class="count-badge"><?php echo count($todayTasks); ?> عدد</span>
                    </div>
                    <a href="../planner/index.php" class="btn-view-all-tasks">
                        <i class="fas fa-external-link-alt"></i>
                        مشاهده همه
                    </a>
                </div>
                <div class="card-content">
                    <?php if (empty($todayTasks)): ?>
                        <div class="empty-today-tasks-msg">
                            <i class="fas fa-calendar-check"></i>
                            هیچ تسکی برای امروز وجود ندارد
                            <div class="hint">می‌توانید از پلنر تسک جدید اضافه کنید</div>
                        </div>
                    <?php else: ?>
                        <div class="today-tasks-list">
                            <?php foreach ($todayTasks as $task): 
                                // پیدا کردن نام پروژه بر اساس project_id
                                $projectName = '';
                                $projectColor = '#667eea';
                                if (!empty($task['project_id'])) {
                                    foreach ($userProjects as $proj) {
                                        if ($proj['id'] == $task['project_id']) {
                                            $projectName = $proj['name'];
                                            $projectColor = $proj['color'] ?? '#667eea';
                                            break;
                                        }
                                    }
                                } elseif (!empty($task['project'])) {
                                    // برای سازگاری با نسخه قدیمی
                                    $projectName = $task['project'];
                                }
                                $category = $task['category'] ?? 'بدون دسته';
                            ?>
                                <div class="today-task-item">
                                    <div class="task-main-info">
                                        <label class="task-checkbox-wrapper">
                                            <input type="checkbox" 
                                                   class="task-done-checkbox" 
                                                   data-task-id="<?= htmlspecialchars($task['id']) ?>" 
                                                   <?= ($task['done'] ?? false) ? 'checked' : '' ?> 
                                                   onchange="toggleTaskDone(this)">
                                            <span class="custom-checkbox"></span>
                                        </label>
                                        <span class="task-title <?= ($task['done'] ?? false) ? 'done' : '' ?>">
                                            <?= htmlspecialchars($task['title']) ?>
                                        </span>
                                    </div>
                                    <div class="task-meta-info">
                                        <?php if (!empty($task['time'])): ?>
                                            <span class="task-time">
                                                <i class="fas fa-clock"></i>
                                                <?= htmlspecialchars($task['time']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($projectName)): ?>
                                            <span class="task-project-badge" style="border-color: <?= htmlspecialchars($projectColor) ?>;">
                                                <i class="fas fa-folder" style="color: <?= htmlspecialchars($projectColor) ?>;"></i>
                                                <?= htmlspecialchars($projectName) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="task-category-badge">
                                            <i class="fas fa-tag"></i>
                                            <?= htmlspecialchars($category) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ===== سایدبار تقویم ===== -->
        <div class="calendar-sidebar">
            <div class="calendar-sidebar-header">
                <h3><i class="fas fa-calendar-alt"></i> تقویم</h3>
                <div class="nav-links">
                    <a href="?y=<?= $current_jy ?>&m=<?= $current_jm ?>" title="رفرش"><i class="fas fa-sync-alt"></i></a>
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

<!-- ===== مودال انتخاب پروژه ===== -->
<div class="modal" id="projectPickerModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-folder-open"></i>
            <span>انتخاب پروژه‌ها</span>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px;">
                روی هر پروژه کلیک کنید تا به داشبورد اضافه یا حذف شود.
            </p>
            <?php if (empty($userProjects)): ?>
                <div class="empty-projects-msg">
                    <i class="fas fa-folder-open"></i>
                    هیچ پروژه‌ای وجود ندارد.
                    <div class="hint">
                        <a href="../projects/index.php" style="color:#667eea; text-decoration:none;">
                            به بخش پروژه‌ها بروید
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="project-picker-grid" id="projectPickerGrid">
                    <?php foreach ($userProjects as $project): ?>
                        <div class="project-picker-item <?php echo $project['_selected'] ? 'selected' : ''; ?>" 
                             onclick="toggleProjectSelection('<?php echo $project['id']; ?>')"
                             data-project-id="<?php echo $project['id']; ?>">
                            <span class="picker-dot" style="background: <?php echo htmlspecialchars($project['color'] ?? '#667eea'); ?>;"></span>
                            <div class="picker-info">
                                <div class="picker-name"><?php echo htmlspecialchars($project['name']); ?></div>
                                <div class="picker-progress"><?php echo $project['_progress']['percent']; ?>% پیشرفت</div>
                            </div>
                            <div class="picker-check">
                                <i class="fas fa-check" style="display: <?php echo $project['_selected'] ? 'block' : 'none'; ?>;"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeProjectPickerModal()">بستن</button>
        </div>
    </div>
</div>

<!-- ===== مودال تنظیم تاریخ روزشمار ===== -->
<div class="modal" id="countdownModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-hourglass-half"></i>
            <span>تنظیم تاریخ هدف</span>
        </div>
        <div class="modal-body">
            <label>تاریخ هدف را انتخاب کنید:</label>
            <input type="text" id="targetDateJalali" placeholder="۱۴۰۴/۰۱/۰۱" readonly style="cursor:pointer;">
            <div id="jalaliCalendarPicker" style="margin-top:15px; display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <button class="btn-primary btn-sm" onclick="prevMonthPicker()"><i class="fas fa-chevron-right"></i></button>
                    <span id="pickerMonthYear" style="font-weight:bold;"></span>
                    <button class="btn-primary btn-sm" onclick="nextMonthPicker()"><i class="fas fa-chevron-left"></i></button>
                </div>
                <table class="calendar-mini-table" id="pickerCalendarTable">
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeCountdownModal()">انصراف</button>
            <button class="btn-save" onclick="saveTargetDate()">ذخیره</button>
        </div>
    </div>
</div>

<!-- ===== مودال افزودن (برای هزینه، عادت، مخاطب) ===== -->
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
    let pickerYear = null;
    let pickerMonth = null;
    let selectedTargetDate = null;
    let currentModalType = '';

    // ============================================
    // توابع کمکی تبدیل تاریخ
    // ============================================
    function gregorian_to_jalali(gy, gm, gd) {
        const g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        const gy2 = (gm > 2) ? (gy + 1) : gy;
        let days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
        let jy = -1595 + (33 * Math.floor(days / 12053));
        days %= 12053;
        jy += 4 * Math.floor(days / 1461);
        days %= 1461;
        if (days > 365) {
            jy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }
        let jm, jd;
        if (days < 186) {
            jm = 1 + Math.floor(days / 31);
            jd = 1 + (days % 31);
        } else {
            jm = 7 + Math.floor((days - 186) / 30);
            jd = 1 + ((days - 186) % 30);
        }
        return [jy, jm, jd];
    }

    function jalali_to_gregorian(jy, jm, jd) {
        jy += 1595;
        let days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        let gy = 400 * Math.floor(days / 146097);
        days %= 146097;
        if (days > 36524) {
            gy += 100 * Math.floor(--days / 36524);
            days %= 36524;
            if (days >= 365) days++;
        }
        gy += 4 * Math.floor(days / 1461);
        days %= 1461;
        if (days > 365) {
            gy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }
        let gd = days + 1;
        const sal_a = [0,31,(gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
        let gm = 0;
        for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
        return [gy, gm, gd];
    }

    function getDaysInJalaliMonth(jy, jm) {
        if (jm <= 6) return 31;
        if (jm < 12) return 30;
        return (jy % 4 == 3) ? 30 : 29;
    }

    // ============================================
    // توابع روزشمار
    // ============================================
    function loadTargetDate() {
        const saved = localStorage.getItem('targetDate');
        if (saved) {
            selectedTargetDate = JSON.parse(saved);
            updateCountdownDisplay();
        }
        loadCountdownTitle();
    }

    function loadCountdownTitle() {
        const savedTitle = localStorage.getItem('countdownTitle');
        if (savedTitle) {
            document.getElementById('countdownTitleSpan').textContent = savedTitle;
        }
    }

    function saveCountdownTitle() {
        const title = document.getElementById('countdownTitleSpan').textContent.trim();
        if (title) {
            localStorage.setItem('countdownTitle', title);
            showToast('عنوان روزشمار ذخیره شد.', 'success');
        } else {
            document.getElementById('countdownTitleSpan').textContent = 'روزشمار هدف';
        }
    }

    function editCountdownTitle() {
        const span = document.getElementById('countdownTitleSpan');
        span.focus();
        const range = document.createRange();
        range.selectNodeContents(span);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function saveTargetDate() {
        if (!selectedTargetDate) {
            showToast('لطفاً یک تاریخ انتخاب کنید.', 'error');
            return;
        }
        localStorage.setItem('targetDate', JSON.stringify(selectedTargetDate));
        updateCountdownDisplay();
        closeCountdownModal();
        showToast('تاریخ هدف ذخیره شد.', 'success');
    }

    function updateCountdownDisplay() {
        if (!selectedTargetDate) return;
        
        const now = new Date();
        const targetGregorian = jalali_to_gregorian(selectedTargetDate.y, selectedTargetDate.m, selectedTargetDate.d);
        const targetDate = new Date(targetGregorian[0], targetGregorian[1] - 1, targetGregorian[2], 0, 0, 0);
        
        const diffTime = targetDate - now;
        if (diffTime <= 0) {
            document.getElementById('yearsRemaining').textContent = '۰';
            document.getElementById('monthsRemaining').textContent = '۰';
            document.getElementById('daysRemaining').textContent = '۰';
            document.getElementById('hoursRemaining').textContent = '۰';
            document.getElementById('minutesRemaining').textContent = '۰';
            document.getElementById('secondsRemaining').textContent = '۰';
            return;
        }
        
        let remainingMs = diffTime;
        const seconds = Math.floor((remainingMs / 1000) % 60);
        const minutes = Math.floor((remainingMs / (1000 * 60)) % 60);
        const hours = Math.floor((remainingMs / (1000 * 60 * 60)) % 24);
        let remainingDays = Math.floor(remainingMs / (1000 * 60 * 60 * 24));
        let years = Math.floor(remainingDays / 365);
        remainingDays %= 365;
        let months = Math.floor(remainingDays / 30);
        let days = remainingDays % 30;
        
        document.getElementById('yearsRemaining').textContent = years.toLocaleString('fa-IR');
        document.getElementById('monthsRemaining').textContent = months.toLocaleString('fa-IR');
        document.getElementById('daysRemaining').textContent = days.toLocaleString('fa-IR');
        document.getElementById('hoursRemaining').textContent = hours.toLocaleString('fa-IR');
        document.getElementById('minutesRemaining').textContent = minutes.toLocaleString('fa-IR');
        document.getElementById('secondsRemaining').textContent = seconds.toLocaleString('fa-IR');
    }

    setInterval(updateCountdownDisplay, 1000);

    function openCountdownModal() {
        const modal = document.getElementById('countdownModal');
        const input = document.getElementById('targetDateJalali');
        const picker = document.getElementById('jalaliCalendarPicker');
        
        if (selectedTargetDate) {
            input.value = `${selectedTargetDate.y}/${selectedTargetDate.m.toString().padStart(2,'0')}/${selectedTargetDate.d.toString().padStart(2,'0')}`;
            pickerYear = selectedTargetDate.y;
            pickerMonth = selectedTargetDate.m;
        } else {
            const now = new Date();
            const todayJalali = gregorian_to_jalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
            pickerYear = todayJalali[0];
            pickerMonth = todayJalali[1];
        }
        
        picker.style.display = 'block';
        renderPickerCalendar();
        modal.classList.add('show');
    }

    function closeCountdownModal() {
        document.getElementById('countdownModal').classList.remove('show');
    }

    function renderPickerCalendar() {
        const monthNames = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
        const dayNames = ['ش','ی','د','س','چ','پ','ج'];
        
        document.getElementById('pickerMonthYear').textContent = `${monthNames[pickerMonth]} ${pickerYear}`;
        
        const table = document.getElementById('pickerCalendarTable');
        let html = '<tr>';
        dayNames.forEach(d => { html += `<th>${d}</th>`; });
        html += '</tr><tr>';
        
        const [firstGy, firstGm, firstGd] = jalali_to_gregorian(pickerYear, pickerMonth, 1);
        const firstWeekday = new Date(firstGy, firstGm - 1, firstGd).getDay();
        const weekdayStart = (firstWeekday + 1) % 7;
        const daysInMonth = getDaysInJalaliMonth(pickerYear, pickerMonth);
        
        for (let i = 0; i < weekdayStart; i++) {
            html += '<td class="other-month"></td>';
        }
        
        const now = new Date();
        const todayJalali = gregorian_to_jalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
        const isCurrentMonth = (pickerYear === todayJalali[0] && pickerMonth === todayJalali[1]);
        
        for (let day = 1; day <= daysInMonth; day++) {
            let classes = 'picker-day';
            if (isCurrentMonth && day === todayJalali[2]) classes += ' today';
            if (selectedTargetDate && selectedTargetDate.y === pickerYear && selectedTargetDate.m === pickerMonth && selectedTargetDate.d === day) {
                classes += ' selected';
            }
            html += `<td class="${classes}" onclick="selectPickerDay(${day})">${day}</td>`;
            
            if ((weekdayStart + day) % 7 === 0 && day !== daysInMonth) {
                html += '</tr><tr>';
            }
        }
        
        const remaining = (7 - ((weekdayStart + daysInMonth) % 7)) % 7;
        for (let i = 0; i < remaining; i++) {
            html += '<td class="other-month"></td>';
        }
        html += '</tr>';
        
        table.innerHTML = html;
    }

    function selectPickerDay(day) {
        selectedTargetDate = { y: pickerYear, m: pickerMonth, d: day };
        document.getElementById('targetDateJalali').value = `${pickerYear}/${pickerMonth.toString().padStart(2,'0')}/${day.toString().padStart(2,'0')}`;
        renderPickerCalendar();
    }

    function prevMonthPicker() {
        pickerMonth--;
        if (pickerMonth < 1) {
            pickerMonth = 12;
            pickerYear--;
        }
        renderPickerCalendar();
    }

    function nextMonthPicker() {
        pickerMonth++;
        if (pickerMonth > 12) {
            pickerMonth = 1;
            pickerYear++;
        }
        renderPickerCalendar();
    }

    document.getElementById('countdownModal').addEventListener('click', function(e) {
        if (e.target === this) closeCountdownModal();
    });

    loadTargetDate();

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
    // مدیریت انتخاب پروژه (از مودال)
    // ============================================
    function openProjectPickerModal() {
        document.getElementById('projectPickerModal').classList.add('show');
    }

    function closeProjectPickerModal() {
        document.getElementById('projectPickerModal').classList.remove('show');
    }

    function toggleTaskDone(checkbox) {
        const taskId = checkbox.dataset.taskId;
        const isChecked = checkbox.checked;
        
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'toggle_task_done');
        formData.append('task_id', taskId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // به‌روزرسانی استایل تسک
                const taskItem = checkbox.closest('.today-task-item');
                const taskTitle = taskItem.querySelector('.task-title');
                
                if (data.done) {
                    taskTitle.classList.add('done');
                    showToast('✅ تسک انجام شد.', 'success');
                } else {
                    taskTitle.classList.remove('done');
                    showToast('🔄 تسک در حالت انجام نشده قرار گرفت.', 'info');
                }
            } else {
                checkbox.checked = !isChecked;
                showToast('❌ خطا: ' + (data.message || 'عملیات ناموفق بود'), 'error');
            }
        })
        .catch(err => {
            console.error('Error toggling task:', err);
            checkbox.checked = !isChecked;
            showToast('❌ خطا در ارتباط با سرور', 'error');
        });
    }

    function toggleProjectSelection(projectId) {
        const item = document.querySelector(`.project-picker-item[data-project-id="${projectId}"]`);
        const isSelected = item.classList.contains('selected');
        const newState = !isSelected;
        
        // به‌روزرسانی فوری UI در مودال
        if (newState) {
            item.classList.add('selected');
            item.querySelector('.picker-check i').style.display = 'block';
        } else {
            item.classList.remove('selected');
            item.querySelector('.picker-check i').style.display = 'none';
        }
        
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'toggle_dashboard_project');
        formData.append('project_id', projectId);
        formData.append('checked', newState ? '1' : '0');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(newState ? '✅ پروژه به داشبورد اضافه شد.' : '❌ پروژه از داشبورد حذف شد.', 'success');
                
                // ===== به‌روزرسانی لیست پروژه‌های داشبورد بدون رفرش =====
                const formData2 = new FormData();
                formData2.append('ajax', '1');
                formData2.append('action', 'get_selected_projects');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData2
                })
                .then(res => res.json())
                .then(projectsData => {
                    if (projectsData.success && projectsData.projects !== undefined) {
                        // به‌روزرسانی تعداد
                        const countBadge = document.querySelector('.projects-title .count-badge');
                        if (countBadge) {
                            countBadge.textContent = projectsData.count + ' عدد';
                        }
                        
                        const section = document.querySelector('.today-tasks-section .card-content');
                        
                        if (projectsData.projects.length === 0) {
                            if (section) {
                                section.innerHTML = `
                                    <div class="empty-projects-msg">
                                        <i class="fas fa-folder-open"></i>
                                        هیچ پروژه‌ای انتخاب نشده است.
                                        <div class="hint">برای اضافه کردن، روی دکمه "انتخاب پروژه" کلیک کنید.</div>
                                    </div>
                                `;
                            }
                        } else {
                            let html = '<div class="selected-projects-grid">';
                            projectsData.projects.forEach(project => {
                                const progress = project._progress || { done: 0, total: 0, percent: 0 };
                                html += `
                                    <div class="project-card">
                                        <div class="project-header">
                                            <div class="project-name">
                                                <span class="project-color-dot" style="background: ${project.color || '#667eea'};"></span>
                                                ${project.name}
                                            </div>
                                            <button class="project-remove-btn" onclick="removeProjectFromDashboard('${project.id}')" title="حذف از داشبورد">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div class="project-progress-container">
                                            <div class="project-progress-bar">
                                                <div class="project-progress-fill" style="width: ${progress.percent}%;"></div>
                                            </div>
                                            <div class="project-progress-text">
                                                <span>${progress.done} از ${progress.total} تسک</span>
                                                <span>${progress.percent}%</span>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            
                            if (section) {
                                section.innerHTML = html;
                            }
                        }
                    }
                })
                .catch(err => {
                    console.log('Error updating projects:', err);
                });
            } else {
                // برگرداندن وضعیت قبلی در صورت خطا
                if (newState) {
                    item.classList.remove('selected');
                    item.querySelector('.picker-check i').style.display = 'none';
                } else {
                    item.classList.add('selected');
                    item.querySelector('.picker-check i').style.display = 'block';
                }
                showToast(data.message || 'خطا در تغییر وضعیت پروژه', 'error');
            }
        })
        .catch(err => {
            // برگرداندن وضعیت قبلی در صورت خطا
            if (newState) {
                item.classList.remove('selected');
                item.querySelector('.picker-check i').style.display = 'none';
            } else {
                item.classList.add('selected');
                item.querySelector('.picker-check i').style.display = 'block';
            }
            showToast('خطا در ارتباط با سرور', 'error');
        });
    }

    function removeProjectFromDashboard(projectId) {
        if (!confirm('آیا از حذف این پروژه از داشبورد اطمینان دارید؟')) return;
        
        // پیدا کردن کارت پروژه و حذف آن از UI
        const projectCards = document.querySelectorAll('.project-card');
        let targetCard = null;
        projectCards.forEach(card => {
            const removeBtn = card.querySelector('.project-remove-btn');
            if (removeBtn && removeBtn.getAttribute('onclick').includes(projectId)) {
                targetCard = card;
            }
        });
        
        // حذف کارت از UI (با انیمیشن)
        if (targetCard) {
            targetCard.style.transition = 'all 0.3s ease';
            targetCard.style.opacity = '0';
            targetCard.style.transform = 'scale(0.9)';
            setTimeout(() => {
                if (targetCard && targetCard.parentNode) {
                    targetCard.remove();
                    const countBadge = document.querySelector('.projects-title .count-badge');
                    const remainingCards = document.querySelectorAll('.project-card');
                    if (countBadge) {
                        countBadge.textContent = remainingCards.length + ' عدد';
                    }
                    if (remainingCards.length === 0) {
                        const section = document.querySelector('.today-tasks-section .card-content');
                        if (section) {
                            section.innerHTML = `
                                <div class="empty-projects-msg">
                                    <i class="fas fa-folder-open"></i>
                                    هیچ پروژه‌ای انتخاب نشده است.
                                    <div class="hint">برای اضافه کردن، روی دکمه "انتخاب پروژه" کلیک کنید.</div>
                                </div>
                            `;
                        }
                    }
                }
            }, 300);
        }
        
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'toggle_dashboard_project');
        formData.append('project_id', projectId);
        formData.append('checked', '0');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('❌ پروژه از داشبورد حذف شد.', 'success');
                const modalItem = document.querySelector(`.project-picker-item[data-project-id="${projectId}"]`);
                if (modalItem) {
                    modalItem.classList.remove('selected');
                    modalItem.querySelector('.picker-check i').style.display = 'none';
                }
            } else {
                showToast(data.message || 'خطا در حذف پروژه', 'error');
                location.reload();
            }
        })
        .catch(err => {
            showToast('خطا در ارتباط با سرور', 'error');
            location.reload();
        });
    }

    // ============================================
    // بستن مودال با کلیک بیرون
    // ============================================
    document.getElementById('projectPickerModal').addEventListener('click', function(e) {
        if (e.target === this) closeProjectPickerModal();
    });

    // ============================================
    // مودال افزودن (هزینه، عادت، مخاطب)
    // ============================================
    function openModal(type) {
        currentModalType = type;
        const modal = document.getElementById('lifeModal');
        const title = document.getElementById('modalTitle');
        const body = document.getElementById('modalBody');

        let html = '';
        switch(type) {
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

    document.getElementById('lifeModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // ============================================
    // بستن مودال‌ها با ESC
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeProjectPickerModal();
            closeCountdownModal();
            closeModal();
        }
    });

    console.log('🚀 داشبورد مدیریت زندگی بارگذاری شد!');
    console.log('📅 امروز:', '<?= $month_names[$current_jm] ?> <?= $current_jd ?> <?= $current_jy ?>');
    console.log('📋 کل تسک‌ها:', <?= $totalTasks ?>);
    console.log('📁 پروژه‌های انتخاب شده:', <?= count($selectedProjects) ?>);
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
    
    // بارگذاری پروژه‌های انتخاب شده
    $dashboardProjects = [];
    if (file_exists($dashboardProjectsFile)) {
        $dashboardProjects = json_decode(file_get_contents($dashboardProjectsFile), true);
        if (!is_array($dashboardProjects)) $dashboardProjects = [];
    }
    
    // ===== تعریف مجدد متغیرها برای استفاده در AJAX =====
    $allProjects = [];
    if (file_exists($projectsFile)) {
        $allProjects = json_decode(file_get_contents($projectsFile), true);
        if (!is_array($allProjects)) $allProjects = [];
    }
    
    $userProjects = array_values(array_filter($allProjects, function($project) use ($userId) {
        return ($project['user_id'] ?? '') == $userId;
    }));
    
    $allTasks = [];
    if (file_exists($tasksFile)) {
        $allTasks = json_decode(file_get_contents($tasksFile), true);
        if (!is_array($allTasks)) $allTasks = [];
    }
    
    $userTasks = array_values(array_filter($allTasks, function($task) use ($userId) {
        return ($task['user_id'] ?? '') == $userId;
    }));
    
// ===== تعریف تابع getProjectProgress در بخش AJAX (نسخه اصلاح شده با دیباگ) =====
function getProjectProgressAjax($projectId, $userTasks) {
    // دیباگ: نمایش آی دی پروژه و تعداد تسک‌ها
    error_log("AJAX - Checking project ID: " . $projectId);
    error_log("AJAX - Total user tasks: " . count($userTasks));
    
    $projectTasks = array_values(array_filter($userTasks, function($task) use ($projectId) {
        $hasProjectId = isset($task['project_id']) && $task['project_id'] == $projectId;
        
        // دیباگ: نمایش تسک‌هایی که project_id دارند
        if (isset($task['project_id'])) {
            error_log("AJAX - Task has project_id: " . $task['project_id'] . " - Title: " . ($task['title'] ?? ''));
        }
        
        return $hasProjectId;
    }));
    
    error_log("AJAX - Found tasks for project: " . count($projectTasks));
    
    if (empty($projectTasks)) {
        return ['total' => 0, 'done' => 0, 'pending' => 0, 'percent' => 0];
    }
    
    $total = count($projectTasks);
    $done = count(array_filter($projectTasks, function($task) {
        return isset($task['done']) && $task['done'] == true;
    }));
    $pending = $total - $done;
    $percent = $total > 0 ? round(($done / $total) * 100) : 0;
    
    return [
        'total' => $total,
        'done' => $done,
        'pending' => $pending,
        'percent' => $percent
    ];
}
    
    switch ($action) {
        case 'get_selected_projects':
            $selectedProjects = [];
            foreach ($userProjects as $project) {
                if (in_array($project['id'], $dashboardProjects)) {
                    $progress = getProjectProgressAjax($project['id'], $userTasks);
                    $project['_progress'] = $progress;
                    $selectedProjects[] = $project;
                }
            }
            $response['success'] = true;
            $response['projects'] = $selectedProjects;
            $response['count'] = count($selectedProjects);
            break;
            
        case 'toggle_dashboard_project':
            $projectId = $_POST['project_id'] ?? '';
            $checked = $_POST['checked'] ?? '0';
            
            if (empty($projectId)) {
                $response['success'] = false;
                $response['message'] = 'شناسه پروژه نامعتبر است.';
                break;
            }
            
            if ($checked == '1') {
                if (!in_array($projectId, $dashboardProjects)) {
                    $dashboardProjects[] = $projectId;
                }
            } else {
                $dashboardProjects = array_values(array_filter($dashboardProjects, function($id) use ($projectId) {
                    return $id != $projectId;
                }));
            }
            
            file_put_contents($dashboardProjectsFile, json_encode($dashboardProjects, JSON_PRETTY_PRINT));
            $response['success'] = true;
            break;
            
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
            
        case 'toggle_task_done':
            $taskId = $_POST['task_id'] ?? '';
            if (empty($taskId)) {
                $response['success'] = false;
                $response['message'] = 'شناسه تسک نامعتبر است';
                break;
            }
            
            $allTasks = [];
            if (file_exists($tasksFile)) {
                $allTasks = json_decode(file_get_contents($tasksFile), true);
                if (!is_array($allTasks)) $allTasks = [];
            }
            
            $taskFound = false;
            foreach ($allTasks as &$task) {
                if (($task['id'] ?? '') == $taskId) {
                    $task['done'] = !($task['done'] ?? false);
                    $taskFound = true;
                    $response['success'] = true;
                    $response['done'] = $task['done'];
                    break;
                }
            }
            unset($task);
            
            if (!$taskFound) {
                $response['success'] = false;
                $response['message'] = 'تسک یافت نشد';
            } else {
                file_put_contents($tasksFile, json_encode($allTasks, JSON_PRETTY_PRINT));
            }
            break;
    }
    
    if ($response['success'] && !in_array($action, ['toggle_dashboard_project', 'get_selected_projects', 'toggle_task_done'])) {
        file_put_contents($lifeDataFile, json_encode($lifeData, JSON_PRETTY_PRINT));
    }
    
    echo json_encode($response);
    exit;
}
?>
