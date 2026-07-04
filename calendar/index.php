<?php
// /calendar/index.php - تقویم شمسی با اتصال به تسک‌های پلنر و امکان افزودن تسک جدید
session_start();
date_default_timezone_set('Asia/Tehran');

// ==================== تنظیم عنوان صفحه برای هدر ====================
$page_title = 'تقویم شمسی';

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
$tasksFile = __DIR__ . '/../data/tasks.json';
$categoriesFile = __DIR__ . '/../data/categories.json';
$projectsFile = __DIR__ . '/../data/projects.json';

if (!file_exists($tasksFile)) file_put_contents($tasksFile, json_encode([]));
if (!file_exists($categoriesFile)) {
    file_put_contents($categoriesFile, json_encode(['کار شخصی', 'کار اداری', 'یادگیری', 'ورزش', 'خرید']));
}
if (!file_exists($projectsFile)) {
    file_put_contents($projectsFile, json_encode([]));
}

// ==================== توابع ====================
function getAllTasks() {
    global $tasksFile;
    if (!file_exists($tasksFile)) return [];
    $tasks = json_decode(file_get_contents($tasksFile), true);
    return is_array($tasks) ? $tasks : [];
}

function saveAllTasks($tasks) {
    global $tasksFile;
    file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
}

function getUserTasks($userId) {
    $tasks = getAllTasks();
    return array_values(array_filter($tasks, function($task) use ($userId) {
        return ($task['user_id'] ?? '') == $userId;
    }));
}

function getCategories() {
    global $categoriesFile;
    if (!file_exists($categoriesFile)) return [];
    $cats = json_decode(file_get_contents($categoriesFile), true);
    return is_array($cats) ? $cats : [];
}

function saveCategories($categories) {
    global $categoriesFile;
    file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT));
}

function getUserProjects($userId) {
    global $projectsFile;
    if (!file_exists($projectsFile)) return [];
    $allProjects = json_decode(file_get_contents($projectsFile), true);
    if (!is_array($allProjects)) $allProjects = [];
    return array_values(array_filter($allProjects, function($p) use ($userId) {
        return ($p['user_id'] ?? '') == $userId;
    }));
}

function saveUserProjects($userId, $projects) {
    global $projectsFile;
    $allProjects = json_decode(file_get_contents($projectsFile), true);
    if (!is_array($allProjects)) $allProjects = [];
    $allProjects = array_values(array_filter($allProjects, function($p) use ($userId) {
        return ($p['user_id'] ?? '') != $userId;
    }));
    foreach ($projects as &$project) {
        $project['user_id'] = $userId;
    }
    $allProjects = array_merge($allProjects, $projects);
    file_put_contents($projectsFile, json_encode($allProjects, JSON_PRETTY_PRINT));
}

function addUserProject($userId, $projectName) {
    $projects = getUserProjects($userId);
    if (in_array($projectName, array_column($projects, 'name'))) return false;
    $newProject = [
        'name' => $projectName,
        'description' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'color' => '#' . dechex(rand(0x000000, 0xFFFFFF)),
        'user_id' => $userId
    ];
    $projects[] = $newProject;
    saveUserProjects($userId, $projects);
    return true;
}

function deleteUserProject($userId, $projectName) {
    $projects = getUserProjects($userId);
    $newProjects = array_values(array_filter($projects, function($p) use ($projectName) {
        return $p['name'] != $projectName;
    }));
    saveUserProjects($userId, $newProjects);
    $tasks = getAllTasks();
    $changed = false;
    foreach ($tasks as &$task) {
        if (($task['user_id'] ?? '') == $userId && ($task['project'] ?? '') === $projectName) {
            $task['project'] = '';
            $changed = true;
        }
    }
    if ($changed) saveAllTasks($tasks);
    return true;
}

function getTaskChildren($taskId, $allTasks) {
    return array_values(array_filter($allTasks, function($t) use ($taskId) {
        return ($t['parent_id'] ?? '') == $taskId;
    }));
}

function calculateTaskProgress($taskId, $allTasks) {
    $children = getTaskChildren($taskId, $allTasks);
    if (empty($children)) return null;
    $total = count($children);
    $done = count(array_filter($children, function($c) {
        return $c['done'] == true;
    }));
    return ['total' => $total, 'done' => $done, 'percent' => round(($done / $total) * 100)];
}

function updateParentTaskStatus($taskId, $allTasks) {
    $task = null;
    foreach ($allTasks as $t) {
        if ($t['id'] == $taskId) { $task = $t; break; }
    }
    if (!$task || empty($task['parent_id'])) return $allTasks;
    
    $parentId = $task['parent_id'];
    $progress = calculateTaskProgress($parentId, $allTasks);
    
    if ($progress !== null) {
        foreach ($allTasks as &$t) {
            if ($t['id'] == $parentId) {
                $wasDone = $t['done'];
                $newDone = ($progress['done'] == $progress['total']);
                if ($newDone != $wasDone) {
                    $t['done'] = $newDone;
                    $t['completed_at'] = $newDone ? date('Y-m-d H:i:s') : null;
                }
                break;
            }
        }
        $allTasks = updateParentTaskStatus($parentId, $allTasks);
    }
    return $allTasks;
}

function saveUserTask($userId, $task) {
    $tasks = getAllTasks();
    $task['user_id'] = $userId;
    $tasks[] = $task;
    saveAllTasks($tasks);
    return $tasks;
}

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
    $userTasks = getUserTasks($_SESSION['user_id']);
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

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    $userId = $_SESSION['user_id'];
    
    if ($action === 'add_task') {
        $title = trim($_POST['title'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        $time = $_POST['time'] ?? '12:00';
        $priority = $_POST['priority'] ?? 'medium';
        $category = $_POST['category'] ?? 'بدون دسته';
        $project = $_POST['project'] ?? '';
        $description = trim($_POST['description'] ?? '');
        
        if (empty($title)) {
            $response = ['success' => false, 'message' => 'عنوان تسک الزامی است'];
            echo json_encode($response);
            exit;
        }
        
        $tasks = getUserTasks($userId);
        $newTask = [
            'id' => time() . rand(100, 999),
            'user_id' => $userId,
            'title' => htmlspecialchars($title),
            'description' => htmlspecialchars($description),
            'category' => $category,
            'project' => $project,
            'date' => $date,
            'time' => $time,
            'priority' => $priority,
            'order' => count($tasks),
            'done' => false,
            'parent_id' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'completed_at' => null
        ];
        $allTasks = saveUserTask($userId, $newTask);
        $response = ['success' => true, 'message' => 'تسک با موفقیت اضافه شد'];
    }
    elseif ($action === 'add_category') {
        $categories = getCategories();
        $newCategory = htmlspecialchars(trim($_POST['category'] ?? ''));
        if ($newCategory && !in_array($newCategory, $categories)) {
            $categories[] = $newCategory;
            saveCategories($categories);
        }
        $response = ['success' => true, 'categories' => $categories];
    }
    elseif ($action === 'delete_category') {
        $categoryToDelete = $_POST['category'] ?? '';
        $categories = getCategories();
        $categories = array_values(array_filter($categories, function($c) use ($categoryToDelete) {
            return $c != $categoryToDelete;
        }));
        saveCategories($categories);
        $response = ['success' => true, 'categories' => $categories];
    }
    elseif ($action === 'add_project') {
        $newProject = htmlspecialchars(trim($_POST['project'] ?? ''));
        if ($newProject) {
            $success = addUserProject($userId, $newProject);
            $response = ['success' => $success, 'projects' => getUserProjects($userId)];
            if (!$success) $response['message'] = 'این پروژه قبلاً وجود دارد';
        } else {
            $response = ['success' => false, 'message' => 'لطفاً نام پروژه را وارد کنید'];
        }
    }
    elseif ($action === 'delete_project') {
        $projectToDelete = $_POST['project'] ?? '';
        if ($projectToDelete) {
            deleteUserProject($userId, $projectToDelete);
            $response = ['success' => true, 'projects' => getUserProjects($userId)];
        } else {
            $response = ['success' => false];
        }
    }
    elseif ($action === 'load_data') {
        $response = [
            'success' => true,
            'categories' => getCategories(),
            'projects' => getUserProjects($userId)
        ];
    }
    
    echo json_encode($response);
    exit;
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

$todayDate = sprintf("%04d-%02d-%02d", $current_jy, $current_jm, $current_jd);
$todayTasks = getTasksForDate($current_jy, $current_jm, $current_jd);

// تبدیل تاریخ امروز به شمسی برای Picker
list($gy, $gm, $gd) = explode('-', date('Y-m-d'));
list($today_jy, $today_jm, $today_jd) = gregorian_to_jalali($gy, $gm, $gd);
$todayJalali = $today_jy . '/' . $today_jm . '/' . $today_jd;

// ==================== هدر یکپارچه ====================
include __DIR__ . '/../includes/header.php';
?>

<!-- ===== استایل‌های اختصاصی تقویم ===== -->
<style>
    /* ===== استایل‌های اختصاصی تقویم ===== */
    .main-wrapper {
        display: flex;
        gap: 20px;
        max-width: 1100px;
        margin: 0 auto;
        align-items: flex-start;
        padding: 0 15px 20px;
    }

    .container {
        flex: 1;
        min-width: 0;
        background: var(--bg-card);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px var(--shadow-color);
        overflow: hidden;
        transition: background 0.3s ease, border-color 0.3s ease;
    }

    .calendar-header-bar {
        background: var(--header-bg);
        padding: 20px 20px;
        border-bottom: 1px solid var(--border-color);
        text-align: center;
    }

    .calendar-header-bar h2 {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }

    .calendar-header-bar h2 i {
        color: #f5576c;
    }

    .nav {
        padding: 12px 16px;
        background: var(--bg-input);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .nav .month-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text-primary);
        min-width: 180px;
        text-align: center;
    }

    .nav a {
        text-decoration: none;
        padding: 7px 16px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .nav a:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(102,126,234,0.4);
    }

    .nav a.today-btn {
        background: linear-gradient(135deg, #f5576c, #f093fb);
    }

    .nav a.today-btn:hover {
        box-shadow: 0 5px 20px rgba(245,87,108,0.4);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 14px 6px;
        border: 1px solid var(--border-color);
        text-align: center;
        transition: background 0.3s ease, color 0.3s ease;
        cursor: default;
    }

    th {
        background: var(--badge-bg);
        color: var(--badge-color);
        font-weight: 600;
        font-size: 13px;
    }

    td {
        font-size: 1rem;
        height: 55px;
        vertical-align: middle;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    td:hover:not(.other-month):not(.empty-cell) {
        background: var(--bg-card-hover);
        transform: scale(1.05);
        z-index: 2;
        border-radius: 8px;
    }

    td .day-number {
        display: inline-block;
        width: 36px;
        height: 36px;
        line-height: 36px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    td:hover:not(.other-month):not(.empty-cell) .day-number {
        background: rgba(102,126,234,0.2);
    }

    .today .day-number {
        background: var(--today-bg) !important;
        color: var(--today-text) !important;
        font-weight: bold;
    }

    td .task-indicator {
        display: block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #f5576c;
        margin: 2px auto 0;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    td .task-indicator.has-tasks {
        opacity: 1;
    }

    .other-month, .empty-cell {
        color: var(--text-light);
        cursor: default !important;
    }

    .other-month:hover:not(.empty-cell) {
        transform: none !important;
        background: transparent !important;
    }

    /* ===== سایدبار ===== */
    .sidebar {
        width: 320px;
        flex-shrink: 0;
        background: var(--bg-card);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px var(--shadow-color);
        overflow: hidden;
        transition: all 0.3s ease;
        max-height: calc(100vh - 40px);
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 20px;
    }

    .sidebar-header {
        padding: 16px 20px;
        background: var(--header-bg);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-header h3 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sidebar-header h3 i {
        color: #667eea;
    }

    .sidebar-close {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 20px;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 8px;
        transition: all 0.3s;
    }

    .sidebar-close:hover {
        background: rgba(220,53,69,0.15);
        color: #ff6b6b;
    }

    .sidebar-body {
        padding: 16px 20px;
        overflow-y: auto;
        flex: 1;
    }

    .sidebar-body::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-body::-webkit-scrollbar-track {
        background: var(--scrollbar-track);
    }

    .sidebar-body::-webkit-scrollbar-thumb {
        background: var(--scrollbar-thumb);
        border-radius: 10px;
    }

    .sidebar-date {
        font-size: 14px;
        color: var(--text-muted);
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sidebar-date i {
        color: #667eea;
    }

    .sidebar-empty {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-light);
    }

    .sidebar-empty i {
        font-size: 40px;
        margin-bottom: 12px;
        display: block;
        opacity: 0.3;
    }

    .sidebar-empty p {
        font-size: 14px;
    }

    .task-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        background: var(--bg-input);
        margin-bottom: 8px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .task-item:hover {
        background: var(--task-hover);
        border-color: rgba(102,126,234,0.3);
        transform: translateX(-4px);
    }

    .task-item .task-status {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .task-item .task-status.done {
        background: #28a745;
    }

    .task-item .task-status.pending {
        background: #ffc107;
    }

    .task-item .task-info {
        flex: 1;
        min-width: 0;
    }

    .task-item .task-title {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-primary);
        word-break: break-word;
    }

    .task-item .task-title.done {
        text-decoration: line-through;
        opacity: 0.6;
    }

    .task-item .task-meta {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .task-item .task-priority {
        font-size: 10px;
        padding: 1px 8px;
        border-radius: 10px;
        font-weight: 500;
    }

    .task-item .task-priority.high {
        background: rgba(220,53,69,0.2);
        color: #ff6b6b;
    }

    .task-item .task-priority.medium {
        background: rgba(255,193,7,0.2);
        color: #ffc107;
    }

    .task-item .task-priority.low {
        background: rgba(40,167,69,0.2);
        color: #28a745;
    }

    .task-item .task-link {
        color: #667eea;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.3s;
    }

    .task-item .task-link:hover {
        color: #764ba2;
        text-decoration: underline;
    }

    .sidebar-legend {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }

    .sidebar-legend .legend-item {
        font-size: 13px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 0;
    }

    .sidebar-legend .legend-item .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .sidebar-legend .legend-item .dot.today-dot {
        background: var(--today-bg);
    }

    .sidebar-legend .legend-item .dot.done-dot {
        background: #28a745;
    }

    .sidebar-legend .legend-item .dot.pending-dot {
        background: #ffc107;
    }

    /* ===== مودال افزودن تسک (اصلاح شده) ===== */
    .modal {
        display: none;
        position: fixed;
        z-index: 999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.7);
        backdrop-filter: blur(4px);
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }

    .modal-content {
        background: var(--dropdown-bg);
        border: 1px solid var(--border-color);
        margin: 5% auto;
        width: 90%;
        max-width: 500px;
        border-radius: 25px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }

    [data-theme="light"] .modal-content {
        background: #ffffff;
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px 25px;
        font-weight: 600;
        font-size: 18px;
        position: sticky;
        top: 0;
        border-radius: 25px 25px 0 0;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-body {
        padding: 25px;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }

    .modal-body input,
    .modal-body select,
    .modal-body textarea {
        width: 100%;
        padding: 12px 16px;
        margin-bottom: 16px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        font-size: 14px;
        background: var(--bg-input);
        color: var(--text-primary);
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    [data-theme="light"] .modal-body input,
    [data-theme="light"] .modal-body select,
    [data-theme="light"] .modal-body textarea {
        background: #fafafa;
        color: #1a1a2e;
        border-color: #e8e8e8;
    }

    .modal-body input:focus,
    .modal-body select:focus,
    .modal-body textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
    }

    .modal-body textarea {
        resize: vertical;
        min-height: 80px;
    }

    .modal-footer {
        padding: 20px 25px 25px;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }

    .btn-cancel {
        background: rgba(255,255,255,0.05);
        color: var(--text-secondary);
        padding: 12px 25px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        cursor: pointer;
        flex: 1;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-cancel:hover {
        background: rgba(255,255,255,0.1);
    }

    .btn-save {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 12px 25px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        flex: 1;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-save:hover {
        transform: scale(1.02);
        box-shadow: 0 5px 20px rgba(102,126,234,0.3);
    }

    .time-input {
        direction: ltr;
        text-align: center;
        font-family: 'Courier New', monospace !important;
        font-size: 16px !important;
        letter-spacing: 3px;
    }

    /* ===== Picker تاریخ شمسی (اصلاح شده) ===== */
    .date-picker-wrapper {
        position: relative;
        margin-bottom: 16px;
    }

    .date-picker-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        font-size: 14px;
        background: var(--bg-input);
        color: var(--text-primary);
        cursor: pointer;
        direction: rtl;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    [data-theme="light"] .date-picker-input {
        background: #fafafa;
        color: #1a1a2e;
        border-color: #e8e8e8;
    }

    .date-picker-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
    }

    .jalali-calendar {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        left: 0;
        z-index: 9999999;
        background: var(--dropdown-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        min-width: 280px;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }

    [data-theme="light"] .jalali-calendar {
        background: #ffffff;
    }

    .jalali-calendar.show {
        display: block;
    }

    .jalali-calendar .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding: 0 4px;
    }

    .jalali-calendar .calendar-header .month-year {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .jalali-calendar .calendar-header .nav-btn {
        background: var(--bg-input);
        border: none;
        color: var(--text-secondary);
        padding: 4px 12px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.3s;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }

    .jalali-calendar .calendar-header .nav-btn:hover {
        background: var(--bg-card-hover);
        color: var(--text-primary);
    }

    .jalali-calendar .calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        margin-bottom: 8px;
        text-align: center;
    }

    .jalali-calendar .calendar-weekdays div {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        padding: 4px 0;
    }

    .jalali-calendar .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
    }

    .jalali-calendar .calendar-day {
        padding: 6px 2px;
        text-align: center;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
        color: var(--text-secondary);
        border: none;
        background: transparent;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
    }

    .jalali-calendar .calendar-day:hover:not(.empty) {
        background: var(--calendar-hover);
        color: var(--text-primary);
    }

    .jalali-calendar .calendar-day.today {
        background: var(--today-bg);
        color: var(--today-text);
        font-weight: bold;
    }

    .jalali-calendar .calendar-day.selected {
        background: var(--calendar-selected);
        color: white;
    }

    .jalali-calendar .calendar-day.empty {
        cursor: default;
        color: var(--text-light);
    }

    .jalali-calendar .calendar-day.other-month {
        color: var(--text-light);
        opacity: 0.5;
    }

    /* ===== Toast ===== */
    .toast {
        position: fixed;
        bottom: 100px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: var(--toast-bg);
        color: white;
        padding: 12px 24px;
        border-radius: 14px;
        font-size: 14px;
        z-index: 9999999;
        opacity: 0;
        transition: all 0.4s ease;
        font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        white-space: nowrap;
        box-shadow: 0 4px 20px var(--shadow-color);
    }

    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast.success { background: #28a745; }
    .toast.error { background: #dc3545; }

    /* ===== ریسپانسیو ===== */
    @media (max-width: 900px) {
        .main-wrapper {
            flex-direction: column;
        }
        
        .sidebar {
            width: 100%;
            max-height: 400px;
            position: static;
            margin-top: 20px;
        }

        .sidebar-body {
            max-height: 300px;
        }
    }

    @media (max-width: 768px) {
        body { padding: 10px; }
        .container { border-radius: 16px; }
        .calendar-header-bar h2 { font-size: 20px; }
        .nav .month-title { font-size: 1.1rem; min-width: 150px; }
        .nav a { padding: 6px 12px; font-size: 12px; }
        th, td { padding: 10px 4px; font-size: 13px; height: 48px; }
        td .day-number { width: 32px; height: 32px; line-height: 32px; font-size: 14px; }
        .sidebar { max-height: 350px; }
        .sidebar-body { max-height: 250px; }
        .modal-content { width: 95%; margin: 10% auto; }
    }

    @media (max-width: 480px) {
        .nav { gap: 6px; }
        .nav a { padding: 4px 10px; font-size: 11px; }
        .nav .month-title { font-size: 0.95rem; min-width: 100px; }
        th, td { padding: 6px 2px; font-size: 11px; height: 40px; }
        td .day-number { width: 28px; height: 28px; line-height: 28px; font-size: 12px; }
        .calendar-header-bar { padding: 12px 15px; }
        .calendar-header-bar h2 { font-size: 17px; }
        .task-item { padding: 8px 12px; }
        .task-item .task-title { font-size: 13px; }
        .sidebar-header h3 { font-size: 14px; }
        .modal-content { padding: 0; }
        .modal-body { padding: 20px; }
    }
</style>

<body>
    <input type="hidden" id="todayJalali" value="<?php echo $todayJalali; ?>">
    
    <div class="main-wrapper">
        <!-- ===== تقویم ===== -->
        <div class="container">
            <div class="calendar-header-bar">
                <h2><i class="fas fa-calendar-alt"></i> تقویم هجری شمسی</h2>
            </div>

            <div class="nav">
                <?php
                $prev_y = ($jm == 1) ? $jy - 1 : $jy;
                $prev_m = ($jm == 1) ? 12 : $jm - 1;
                $next_y = ($jm == 12) ? $jy + 1 : $jy;
                $next_m = ($jm == 12) ? 1 : $jm + 1;
                ?>
                <a href="?y=<?= $prev_y ?>&m=<?= $prev_m ?>">
                    <i class="fas fa-chevron-right"></i> ماه قبل
                </a>
                <span class="month-title">
                    <?= $month_names[$jm] ?> <?= $jy ?>
                </span>
                <a href="?y=<?= $next_y ?>&m=<?= $next_m ?>">
                    ماه بعد <i class="fas fa-chevron-left"></i>
                </a>
                <a href="?" class="today-btn">
                    <i class="fas fa-calendar-day"></i> امروز
                </a>
            </div>

            <table>
                <tr>
                    <?php foreach ($day_names as $name): ?>
                        <th><?= $name ?></th>
                    <?php endforeach; ?>
                </tr>
                <?php
                list($first_gy, $first_gm, $first_gd) = jalali_to_gregorian($jy, $jm, 1);
                $first_weekday = (int)date('w', mktime(0, 0, 0, $first_gm, $first_gd, $first_gy));
                $weekday_start = ($first_weekday + 1) % 7;

                $days_in_month = ($jm <= 6) ? 31 : (($jm < 12) ? 30 : (($jy % 4 == 3) ? 30 : 29));

                echo "<tr>";
                for ($i = 0; $i < $weekday_start; $i++) {
                    echo "<td class='other-month empty-cell'></td>";
                }

                for ($day = 1; $day <= $days_in_month; $day++) {
                    $is_today = ($jy == $current_jy && $jm == $current_jm && $day == $current_jd);
                    $dateStr = sprintf("%04d-%02d-%02d", $jy, $jm, $day);
                    $dayTasks = getTasksForDate($jy, $jm, $day);
                    $hasTasks = count($dayTasks) > 0;
                    $class = $is_today ? "today" : "";
                    echo "<td class='$class' data-date='$dateStr' onclick='window.location.href=\"?date=$dateStr&y=$jy&m=$jm\"'>";
                    echo "<span class='day-number'>$day</span>";
                    echo "<span class='task-indicator " . ($hasTasks ? 'has-tasks' : '') . "'></span>";
                    echo "</td>";

                    if (($weekday_start + $day) % 7 == 0) {
                        echo "</tr><tr>";
                    }
                }

                $remaining = (7 - (($weekday_start + $days_in_month) % 7)) % 7;
                for ($i = 0; $i < $remaining; $i++) {
                    echo "<td class='other-month empty-cell'></td>";
                }
                echo "</tr>";
                ?>
            </table>
        </div>

        <!-- ===== سایدبار تسک‌ها ===== -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tasks"></i> تسک‌های روز</h3>
                <div style="display: flex; gap: 8px;">
                    <button onclick="openAddTaskModal()" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 13px; font-family: 'Vazirmatn','Vazir','Tahoma',sans-serif;">
                        <i class="fas fa-plus"></i> جدید
                    </button>
                    <button class="sidebar-close" onclick="window.location.href='?y=<?= $jy ?>&m=<?= $jm ?>'" title="بستن">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="sidebar-body">
                <?php if ($selectedDate): ?>
                    <div class="sidebar-date">
                        <i class="fas fa-calendar-day"></i>
                        <?= $selectedDateDisplay ?>
                    </div>
                    <?php if (count($selectedTasks) > 0): ?>
                        <?php foreach ($selectedTasks as $task): ?>
                            <div class="task-item" onclick="window.location.href='../planner/index.php'">
                                <span class="task-status <?= $task['done'] ? 'done' : 'pending' ?>"></span>
                                <div class="task-info">
                                    <div class="task-title <?= $task['done'] ? 'done' : '' ?>">
                                        <?= htmlspecialchars($task['title']) ?>
                                    </div>
                                    <div class="task-meta">
                                        <?php if (isset($task['time']) && $task['time']): ?>
                                            <span><i class="fas fa-clock"></i> <?= $task['time'] ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($task['priority'])): ?>
                                            <span class="task-priority <?= $task['priority'] ?>">
                                                <?= $task['priority'] == 'high' ? '🔴 بالا' : ($task['priority'] == 'medium' ? '🟡 متوسط' : '🟢 پایین') ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (isset($task['project']) && $task['project']): ?>
                                            <span><i class="fas fa-project-diagram"></i> <?= htmlspecialchars($task['project']) ?></span>
                                        <?php endif; ?>
                                        <a href="../planner/index.php" class="task-link">
                                            <i class="fas fa-external-link-alt"></i> مشاهده
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sidebar-empty">
                            <i class="fas fa-calendar-plus"></i>
                            <p>هیچ تسکی برای این روز وجود ندارد</p>
                            <button onclick="openAddTaskModal()" style="color: #667eea; background: none; border: none; cursor: pointer; margin-top: 10px; font-size: 14px; font-family: 'Vazirmatn','Vazir','Tahoma',sans-serif;">
                                <i class="fas fa-plus"></i> افزودن تسک جدید
                            </button>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sidebar-empty">
                        <i class="fas fa-hand-pointer"></i>
                        <p>برای مشاهده تسک‌ها، روی یک روز کلیک کنید</p>
                    </div>
                    <div class="sidebar-legend">
                        <div class="legend-item">
                            <span class="dot today-dot"></span> امروز
                        </div>
                        <div class="legend-item">
                            <span class="dot done-dot"></span> تسک انجام شده
                        </div>
                        <div class="legend-item">
                            <span class="dot pending-dot"></span> تسک در انتظار
                        </div>
                        <div class="legend-item">
                            <span style="color: #f5576c; font-size: 10px;">●</span> روز دارای تسک
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== مودال افزودن تسک (اصلاح شده) ===== -->
    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-plus-circle"></i> افزودن تسک جدید
            </div>
            <div class="modal-body">
                <input type="text" id="addTitle" placeholder="عنوان تسک..." required>
                <select id="addCategory"></select>
                <select id="addProject"><option value="">بدون پروژه</option></select>
                
                <!-- Picker تاریخ شمسی -->
                <div class="date-picker-wrapper">
                    <input type="text" id="addDate" class="date-picker-input" placeholder="تاریخ" value="<?php echo $todayJalali; ?>" readonly>
                    <div class="jalali-calendar" id="addDateCalendar"></div>
                </div>
                
                <input type="text" id="addTime" class="time-input" placeholder="ساعت" value="12:00" maxlength="5" autocomplete="off">
                <select id="addPriority">
                    <option value="high">🔴 اولویت بالا</option>
                    <option value="medium" selected>🟡 اولویت متوسط</option>
                    <option value="low">🟢 اولویت پایین</option>
                </select>
                <textarea id="addDescription" placeholder="توضیحات (اختیاری)..." rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddTaskModal()">انصراف</button>
                <button class="btn-save" id="saveAddTaskBtn">افزودن تسک</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال مدیریت دسته‌ها ===== -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><i class="fas fa-tags"></i> مدیریت دسته‌بندی</div>
            <div class="modal-body">
                <div id="categoryList"></div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="text" id="newCategoryName" placeholder="نام دسته جدید" style="flex:1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background: var(--bg-input); color: var(--text-primary); font-family: 'Vazirmatn','Vazir','Tahoma',sans-serif;">
                    <button class="btn-save" id="addCategoryBtn" style="flex:0;">افزودن</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCategoryModal()">بستن</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال مدیریت پروژه‌ها ===== -->
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><i class="fas fa-project-diagram"></i> مدیریت پروژه‌ها</div>
            <div class="modal-body">
                <div id="projectList"></div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="text" id="newProjectName" placeholder="نام پروژه جدید" style="flex:1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background: var(--bg-input); color: var(--text-primary); font-family: 'Vazirmatn','Vazir','Tahoma',sans-serif;">
                    <button class="btn-save" id="addProjectBtn" style="flex:0;">افزودن</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProjectModal()">بستن</button>
            </div>
        </div>
    </div>

    <!-- ===== Toast ===== -->
    <div class="toast" id="toast"></div>

    <script>
        // ============================================
        // توابع تبدیل تاریخ شمسی (جاوااسکریپت)
        // ============================================
        function gregorianToJalali(gy, gm, gd) {
            var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            var gy2 = (gm > 2) ? (gy + 1) : gy;
            var days = 355666 + (365 * gy) + parseInt((gy2 + 3) / 4) - parseInt((gy2 + 99) / 100) + parseInt((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
            var jy = -1595 + (33 * parseInt(days / 12053));
            days %= 12053;
            jy += 4 * parseInt(days / 1461);
            days %= 1461;
            if (days > 365) {
                jy += parseInt((days - 1) / 365);
                days = (days - 1) % 365;
            }
            var jm, jd;
            if (days < 186) {
                jm = 1 + parseInt(days / 31);
                jd = 1 + (days % 31);
            } else {
                jm = 7 + parseInt((days - 186) / 30);
                jd = 1 + ((days - 186) % 30);
            }
            return [jy, jm, jd];
        }

        function jalaliToGregorian(jy, jm, jd) {
            jy += 1595;
            var days = -355668 + (365 * jy) + (parseInt(jy / 33) * 8) + parseInt(((jy % 33) + 3) / 4) + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
            var gy = 400 * parseInt(days / 146097);
            days %= 146097;
            if (days > 36524) {
                gy += 100 * parseInt(--days / 36524);
                days %= 36524;
                if (days >= 365) days++;
            }
            gy += 4 * parseInt(days / 1461);
            days %= 1461;
            if (days > 365) {
                gy += parseInt((days - 1) / 365);
                days = (days - 1) % 365;
            }
            var gd = days + 1;
            var sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            var gm = 0;
            for (gm = 1; gm < 13 && gd > sal_a[gm]; gm++) {
                gd -= sal_a[gm];
            }
            return [gy, gm, gd];
        }

        function toJalaliDate(dateStr) {
            if (!dateStr) return '';
            var parts = dateStr.split('-');
            var gy = parseInt(parts[0]);
            var gm = parseInt(parts[1]);
            var gd = parseInt(parts[2]);
            var jalali = gregorianToJalali(gy, gm, gd);
            return jalali[0] + '/' + jalali[1] + '/' + jalali[2];
        }

        function toGregorianDate(jalaliStr) {
            if (!jalaliStr) return '';
            var parts = jalaliStr.split('/');
            var jy = parseInt(parts[0]);
            var jm = parseInt(parts[1]);
            var jd = parseInt(parts[2]);
            var gregorian = jalaliToGregorian(jy, jm, jd);
            return gregorian[0] + '-' + String(gregorian[1]).padStart(2, '0') + '-' + String(gregorian[2]).padStart(2, '0');
        }

        // ============================================
        // کلاس Picker تاریخ شمسی
        // ============================================
        class JalaliDatePicker {
            constructor(inputId, calendarId, options = {}) {
                this.input = document.getElementById(inputId);
                this.calendar = document.getElementById(calendarId);
                this.onSelect = options.onSelect || null;
                this.currentDate = options.defaultDate || null;
                
                if (!this.input || !this.calendar) return;
                
                if (this.currentDate) {
                    this.input.value = this.currentDate;
                } else if (this.input.value) {
                    this.currentDate = this.input.value;
                } else {
                    var today = toJalaliDate(new Date().toISOString().split('T')[0]);
                    this.currentDate = today;
                    this.input.value = today;
                }
                
                var parts = this.currentDate.split('/');
                this.currentYear = parseInt(parts[0]);
                this.currentMonth = parseInt(parts[1]);
                this.currentDay = parseInt(parts[2]);
                
                this.initEvents();
                this.render();
            }
            
            initEvents() {
                var self = this;
                this.input.addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.toggle();
                });
                
                this.calendar.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
                
                document.addEventListener('click', function(e) {
                    if (!self.calendar.contains(e.target) && e.target !== self.input) {
                        self.calendar.classList.remove('show');
                    }
                });
            }
            
            toggle() {
                this.calendar.classList.toggle('show');
                if (this.calendar.classList.contains('show')) {
                    this.render();
                }
            }
            
            goToMonth(year, month) {
                event.stopPropagation();
                this.currentYear = year;
                this.currentMonth = month;
                this.render();
            }
            
            selectDay(day) {
                this.currentDay = day;
                var dateStr = this.currentYear + '/' + this.currentMonth + '/' + day;
                this.currentDate = dateStr;
                this.input.value = dateStr;
                this.calendar.classList.remove('show');
                if (this.onSelect) {
                    this.onSelect(dateStr);
                }
            }
            
            render() {
                var self = this;
                var monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                var dayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
                
                var firstDayGreg = jalaliToGregorian(this.currentYear, this.currentMonth, 1);
                var firstDayWeekday = new Date(firstDayGreg[0], firstDayGreg[1] - 1, firstDayGreg[2]).getDay();
                var startOffset = (firstDayWeekday + 1) % 7;
                
                var daysInMonth = (this.currentMonth <= 6) ? 31 : (this.currentMonth < 12 ? 30 : ((this.currentYear % 4 === 3) ? 30 : 29));
                
                var today = new Date();
                var todayJalali = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
                var todayStr = todayJalali[0] + '/' + todayJalali[1] + '/' + todayJalali[2];
                
                var html = '';
                html += '<div class="calendar-header">';
                html += '<button class="nav-btn" onclick="event.stopPropagation(); window.datePickers[\'' + this.calendar.id + '\'].goToMonth(' + this.currentYear + ', ' + (this.currentMonth - 1) + ')">‹</button>';
                html += '<span class="month-year">' + monthNames[this.currentMonth - 1] + ' ' + this.currentYear + '</span>';
                html += '<button class="nav-btn" onclick="event.stopPropagation(); window.datePickers[\'' + this.calendar.id + '\'].goToMonth(' + this.currentYear + ', ' + (this.currentMonth + 1) + ')">›</button>';
                html += '</div>';
                
                html += '<div class="calendar-weekdays">';
                for (var i = 0; i < 7; i++) {
                    html += '<div>' + dayNames[i] + '</div>';
                }
                html += '</div>';
                
                html += '<div class="calendar-days">';
                for (var i = 0; i < startOffset; i++) {
                    html += '<div class="calendar-day empty"></div>';
                }
                for (var d = 1; d <= daysInMonth; d++) {
                    var dateStr = this.currentYear + '/' + this.currentMonth + '/' + d;
                    var isToday = (dateStr === todayStr);
                    var isSelected = (dateStr === this.currentDate);
                    var cls = 'calendar-day';
                    if (isToday) cls += ' today';
                    if (isSelected) cls += ' selected';
                    html += '<div class="' + cls + '" onclick="event.stopPropagation(); window.datePickers[\'' + this.calendar.id + '\'].selectDay(' + d + ')">' + d + '</div>';
                }
                html += '</div>';
                
                this.calendar.innerHTML = html;
            }
        }

        // ============================================
        // متغیرها
        // ============================================
        const TODAY_JALALI = document.getElementById('todayJalali').value;
        let categories = [];
        let projects = [];
        let datePickers = {};

        // ============================================
        // توابع کمکی
        // ============================================
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'success') {
            var toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            setTimeout(function() { toast.classList.add('show'); }, 50);
            setTimeout(function() { toast.classList.remove('show'); }, 3000);
        }

        function validateTime(timeStr) {
            return /^([0-1][0-9]|2[0-3]):([0-5][0-9])$/.test(timeStr);
        }

        function autoFormatTime(input) {
            var value = input.value.replace(/[^0-9]/g, '');
            if (value.length >= 3) {
                var hours = value.substring(0, 2);
                var minutes = value.substring(2, 4);
                if (parseInt(hours) > 23) hours = '23';
                if (parseInt(minutes) > 59) minutes = '59';
                input.value = hours + ':' + minutes;
            } else if (value.length === 2) {
                input.value = value + ':';
            } else {
                input.value = value;
            }
        }

        // ============================================
        // توابع دیتا
        // ============================================
        function loadData() {
            var formData = new FormData();
            formData.append('action', 'load_data');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        categories = result.categories || [];
                        projects = result.projects || [];
                        updateSelects();
                        refreshCategoryList();
                        refreshProjectList();
                    }
                })['catch'](function(e) {
                    console.error('خطا در بارگذاری داده:', e);
                });
        }

        function sendRequest(action, data) {
            var formData = new FormData();
            formData.append('action', action);
            for (var key in data) {
                formData.append(key, data[key]);
            }
            return fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); });
        }

        function updateSelects() {
            var categoryOptions = categories.map(function(c) { return '<option value="' + c + '">' + c + '</option>'; }).join('');
            var projectOptions = projects.map(function(p) { return '<option value="' + p.name + '">' + p.name + '</option>'; }).join('');
            document.getElementById('addCategory').innerHTML = categoryOptions;
            document.getElementById('addProject').innerHTML = '<option value="">بدون پروژه</option>' + projectOptions;
        }

        function refreshCategoryList() {
            var container = document.getElementById('categoryList');
            if (!categories || categories.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-light);">هیچ دسته‌بندی تعریف نشده است</div>';
            } else {
                container.innerHTML = categories.map(function(cat) {
                    return '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);"><span style="color:var(--text-primary);">' + cat + '</span><button onclick="deleteCategory(\'' + cat + '\')" style="background:rgba(220,53,69,0.15); color:#ff6b6b; padding:4px 10px; border:none; border-radius:6px; cursor:pointer; font-family: \'Vazirmatn\',\'Vazir\',\'Tahoma\',sans-serif;">🗑️</button></div>';
                }).join('');
            }
        }

        function refreshProjectList() {
            var container = document.getElementById('projectList');
            if (!projects || projects.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-light);">هیچ پروژه‌ای تعریف نشده است</div>';
            } else {
                container.innerHTML = projects.map(function(proj) {
                    return '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);"><span style="color:var(--text-primary);">' + escapeHtml(proj.name) + '</span><button onclick="deleteProject(\'' + proj.name + '\')" style="background:rgba(220,53,69,0.15); color:#ff6b6b; padding:4px 10px; border:none; border-radius:6px; cursor:pointer; font-family: \'Vazirmatn\',\'Vazir\',\'Tahoma\',sans-serif;">🗑️</button></div>';
                }).join('');
            }
        }

        // ============================================
        // مدیریت دسته‌ها و پروژه‌ها
        // ============================================
        function openCategoryModal() {
            refreshCategoryList();
            document.getElementById('categoryModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function addCategory() {
            var newCat = document.getElementById('newCategoryName').value.trim();
            if (newCat && !categories.includes(newCat)) {
                sendRequest('add_category', { category: newCat }).then(function(result) {
                    if (result.success) {
                        categories = result.categories;
                        updateSelects();
                        refreshCategoryList();
                        document.getElementById('newCategoryName').value = '';
                        showToast('دسته اضافه شد', 'success');
                    }
                });
            } else if (newCat && categories.includes(newCat)) {
                alert('این دسته بندی قبلاً وجود دارد');
            } else {
                alert('لطفاً نام دسته بندی را وارد کنید');
            }
        }

        function deleteCategory(category) {
            if (confirm('حذف دسته "' + category + '"؟')) {
                sendRequest('delete_category', { category: category }).then(function(result) {
                    if (result.success) {
                        categories = result.categories;
                        updateSelects();
                        refreshCategoryList();
                        showToast('دسته حذف شد', 'success');
                    }
                });
            }
        }

        function openProjectModal() {
            refreshProjectList();
            document.getElementById('projectModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeProjectModal() {
            document.getElementById('projectModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function addProject() {
            var newProj = document.getElementById('newProjectName').value.trim();
            if (!newProj) {
                alert('لطفاً نام پروژه را وارد کنید');
                return;
            }
            if (projects.some(function(p) { return p.name === newProj; })) {
                alert('این پروژه قبلاً وجود دارد');
                return;
            }
            sendRequest('add_project', { project: newProj }).then(function(result) {
                if (result.success) {
                    projects = result.projects;
                    updateSelects();
                    refreshProjectList();
                    document.getElementById('newProjectName').value = '';
                    showToast('پروژه اضافه شد', 'success');
                } else if (result.message) {
                    alert(result.message);
                }
            });
        }

        function deleteProject(project) {
            if (confirm('آیا از حذف پروژه "' + project + '" مطمئن هستید؟\nتوجه: تسک‌های این پروژه به "بدون پروژه" تغییر می‌یابند.')) {
                sendRequest('delete_project', { project: project }).then(function(result) {
                    if (result.success) {
                        projects = result.projects;
                        updateSelects();
                        refreshProjectList();
                        showToast('پروژه حذف شد', 'success');
                    }
                });
            }
        }

        // ============================================
        // مدیریت مودال افزودن تسک
        // ============================================
        function openAddTaskModal() {
            var selectedDate = '<?php echo $selectedDate ? $selectedDate : $todayDate; ?>';
            var parts = selectedDate.split('-');
            var dateStr = parts[0] + '/' + parts[1] + '/' + parts[2];
            
            document.getElementById('addTitle').value = '';
            document.getElementById('addDescription').value = '';
            document.getElementById('addDate').value = dateStr;
            document.getElementById('addTime').value = '12:00';
            document.getElementById('addPriority').value = 'medium';
            
            if (datePickers['addDateCalendar']) {
                var p = dateStr.split('/');
                datePickers['addDateCalendar'].currentYear = parseInt(p[0]);
                datePickers['addDateCalendar'].currentMonth = parseInt(p[1]);
                datePickers['addDateCalendar'].currentDay = parseInt(p[2]);
                datePickers['addDateCalendar'].currentDate = dateStr;
                datePickers['addDateCalendar'].render();
            }
            
            document.getElementById('addTaskModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            setTimeout(function() {
                document.getElementById('addTitle').focus();
            }, 150);
        }

        function closeAddTaskModal() {
            document.getElementById('addTaskModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function addNewTask() {
            var title = document.getElementById('addTitle').value.trim();
            if (!title) {
                alert('لطفاً عنوان تسک را وارد کنید');
                return;
            }
            
            var timeValue = document.getElementById('addTime').value;
            if (!validateTime(timeValue)) {
                alert('فرمت زمان صحیح نیست. مثال: 14:30');
                return;
            }
            
            var jalaliDate = document.getElementById('addDate').value;
            var gregorianDate = toGregorianDate(jalaliDate);
            if (!gregorianDate) {
                alert('تاریخ نامعتبر است');
                return;
            }
            
            var btn = document.getElementById('saveAddTaskBtn');
            btn.disabled = true;
            btn.textContent = 'در حال ذخیره...';
            
            sendRequest('add_task', {
                title: title,
                description: document.getElementById('addDescription').value,
                category: document.getElementById('addCategory').value,
                project: document.getElementById('addProject').value,
                date: gregorianDate,
                time: timeValue,
                priority: document.getElementById('addPriority').value
            }).then(function(result) {
                btn.disabled = false;
                btn.textContent = 'افزودن تسک';
                
                if (result.success) {
                    showToast('✅ تسک با موفقیت اضافه شد', 'success');
                    closeAddTaskModal();
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    alert(result.message || 'خطا در افزودن تسک');
                }
            })['catch'](function() {
                btn.disabled = false;
                btn.textContent = 'افزودن تسک';
                alert('خطا در ارتباط با سرور');
            });
        }

        // ============================================
        // مدیریت Picker تاریخ
        // ============================================
        function initDatePickers() {
            var addPicker = new JalaliDatePicker('addDate', 'addDateCalendar', {
                defaultDate: TODAY_JALALI,
                onSelect: function(dateStr) {
                    document.getElementById('addDate').value = dateStr;
                }
            });
            datePickers['addDateCalendar'] = addPicker;
            window.datePickers = datePickers;
        }

        // ============================================
        // Event Listeners
        // ============================================
        document.getElementById('saveAddTaskBtn')?.addEventListener('click', addNewTask);
        document.getElementById('addCategoryBtn')?.addEventListener('click', addCategory);
        document.getElementById('addProjectBtn')?.addEventListener('click', addProject);

        document.querySelectorAll('.time-input').forEach(function(input) {
            input.addEventListener('input', function(e) { autoFormatTime(this); });
        });

        // بستن مودال‌ها با کلیک روی پس‌زمینه
        window.onclick = function(event) {
            if (event.target === document.getElementById('addTaskModal')) closeAddTaskModal();
            if (event.target === document.getElementById('categoryModal')) closeCategoryModal();
            if (event.target === document.getElementById('projectModal')) closeProjectModal();
        };

        // بستن مودال‌ها با کلید Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddTaskModal();
                closeCategoryModal();
                closeProjectModal();
            }
        });

        // ============================================
        // شروع
        // ============================================
        loadData();
        initDatePickers();

        // هماهنگ کردن تم تقویم با هدر
        var savedTheme = localStorage.getItem('theme') || 'dark';
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
        } else {
            document.body.classList.remove('light-mode');
        }

        console.log('✅ تقویم با هدر یکپارچه بارگذاری شد.');
    </script>
</body>
</html>