<?php
// /planner/habits.php - سیستم پیگیری عادت‌ها با تقویم شمسی
session_start();
date_default_timezone_set('Asia/Tehran');

// ==================== بررسی فعال بودن ماژول ====================
$settingsFile = __DIR__ . '/../data/settings.json';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (isset($settings['modules']['planner']['enabled']) && 
        $settings['modules']['planner']['enabled'] === false) {
        header('Location: ../disabled_module.php?module=planner');
        exit;
    }
}

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

$_SESSION['user_name'] = $currentUser['name'];
$userId = $_SESSION['user_id'];

// ==================== فایل‌های دیتا ====================
$habitsFile = __DIR__ . '/../data/habits.json';
$habitLogsFile = __DIR__ . '/../data/habit_logs.json';

if (!file_exists($habitsFile)) file_put_contents($habitsFile, json_encode([]));
if (!file_exists($habitLogsFile)) file_put_contents($habitLogsFile, json_encode([]));

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

function getJalaliDate() {
    list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('n'), date('j'));
    return sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
}

function getJalaliMonthName($month) {
    $months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];
    return $months[$month] ?? '';
}

function getDayOfWeek($jy, $jm, $jd) {
    list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
    $dayOfWeek = date('w', mktime(0, 0, 0, $gm, $gd, $gy));
    $persianDays = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'];
    return $persianDays[$dayOfWeek];
}

// ==================== توابع عادت‌ها ====================
function getUserHabits($userId) {
    global $habitsFile;
    if (!file_exists($habitsFile)) return [];
    $allHabits = json_decode(file_get_contents($habitsFile), true);
    if (!is_array($allHabits)) return [];
    return array_values(array_filter($allHabits, function($h) use ($userId) {
        return ($h['user_id'] ?? '') == $userId;
    }));
}

function saveUserHabits($userId, $habits) {
    global $habitsFile;
    $allHabits = json_decode(file_get_contents($habitsFile), true);
    if (!is_array($allHabits)) $allHabits = [];
    $allHabits = array_values(array_filter($allHabits, function($h) use ($userId) {
        return ($h['user_id'] ?? '') != $userId;
    }));
    foreach ($habits as &$habit) {
        $habit['user_id'] = $userId;
    }
    $allHabits = array_merge($allHabits, $habits);
    file_put_contents($habitsFile, json_encode($allHabits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getUserHabitLogs($userId) {
    global $habitLogsFile;
    if (!file_exists($habitLogsFile)) return [];
    $allLogs = json_decode(file_get_contents($habitLogsFile), true);
    if (!is_array($allLogs)) return [];
    return array_values(array_filter($allLogs, function($log) use ($userId) {
        return ($log['user_id'] ?? '') == $userId;
    }));
}

function saveHabitLog($userId, $habitId, $jalaliDate, $completed = true) {
    global $habitLogsFile;
    $logs = getUserHabitLogs($userId);
    
    // پیدا کردن لاگ موجود برای این تاریخ و عادت
    $found = false;
    foreach ($logs as &$log) {
        if ($log['habit_id'] == $habitId && $log['date'] == $jalaliDate) {
            $log['completed'] = $completed;
            $log['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $logs[] = [
            'id' => time() . rand(1000, 9999),
            'user_id' => $userId,
            'habit_id' => $habitId,
            'date' => $jalaliDate,
            'completed' => $completed,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    file_put_contents($habitLogsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getHabitStreak($userId, $habitId) {
    $logs = getUserHabitLogs($userId);
    $today = getJalaliDate();
    
    // فیلتر لاگ‌های این عادت
    $habitLogs = array_values(array_filter($logs, function($log) use ($habitId) {
        return ($log['habit_id'] ?? '') == $habitId && ($log['completed'] ?? false) == true;
    }));
    
    // مرتب‌سازی بر اساس تاریخ
    usort($habitLogs, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
    
    $streak = 0;
    $checkDate = $today;
    
    while (true) {
        $found = false;
        foreach ($habitLogs as $log) {
            if ($log['date'] == $checkDate) {
                $streak++;
                $found = true;
                break;
            }
        }
        
        if (!$found) break;
        
        // رفتن به روز قبل
        list($jy, $jm, $jd) = explode('-', $checkDate);
        $jd--;
        if ($jd < 1) {
            $jm--;
            if ($jm < 1) {
                $jy--;
                $jm = 12;
            }
            $lastDayOfMonth = ($jm <= 6) ? 31 : (($jm <= 11) ? 30 : 29);
            $jd = $lastDayOfMonth;
        }
        $checkDate = sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
    }
    
    return $streak;
}

function getHabitCompletionRate($userId, $habitId, $days = 30) {
    $logs = getUserHabitLogs($userId);
    $habitLogs = array_filter($logs, function($log) use ($habitId) {
        return ($log['habit_id'] ?? '') == $habitId;
    });
    
    $total = count($habitLogs);
    $completed = count(array_filter($habitLogs, function($log) {
        return ($log['completed'] ?? false) == true;
    }));
    
    if ($total == 0) return 0;
    return round(($completed / $total) * 100);
}

function isHabitCompletedToday($userId, $habitId) {
    $logs = getUserHabitLogs($userId);
    $today = getJalaliDate();
    
    foreach ($logs as $log) {
        if ($log['habit_id'] == $habitId && $log['date'] == $today) {
            return ($log['completed'] ?? false) == true;
        }
    }
    return false;
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'add_habit') {
        $habits = getUserHabits($userId);
        $newHabit = [
            'id' => time() . rand(1000, 9999),
            'user_id' => $userId,
            'title' => htmlspecialchars(trim($_POST['title'] ?? '')),
            'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
            'color' => $_POST['color'] ?? '#667eea',
            'frequency' => $_POST['frequency'] ?? 'daily',
            'reminder_time' => $_POST['reminder_time'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'active' => true
        ];
        
        if (!empty($newHabit['title'])) {
            $habits[] = $newHabit;
            saveUserHabits($userId, $habits);
            $response = ['success' => true, 'habits' => getUserHabits($userId)];
        }
    }
    elseif ($action === 'toggle_habit') {
        $habitId = $_POST['habit_id'] ?? '';
        $completed = ($_POST['completed'] ?? 'false') === 'true';
        $today = getJalaliDate();
        
        saveHabitLog($userId, $habitId, $today, $completed);
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    elseif ($action === 'delete_habit') {
        $habitId = $_POST['habit_id'] ?? '';
        $habits = getUserHabits($userId);
        $habits = array_values(array_filter($habits, function($h) use ($habitId) {
            return ($h['id'] ?? '') != $habitId;
        }));
        saveUserHabits($userId, $habits);
        
        // حذف لاگ‌های مربوطه
        $logs = getUserHabitLogs($userId);
        $logs = array_values(array_filter($logs, function($log) use ($habitId) {
            return ($log['habit_id'] ?? '') != $habitId;
        }));
        file_put_contents($habitLogsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    elseif ($action === 'edit_habit') {
        $habitId = $_POST['habit_id'] ?? '';
        $habits = getUserHabits($userId);
        
        foreach ($habits as &$habit) {
            if ($habit['id'] == $habitId) {
                if (isset($_POST['title'])) $habit['title'] = htmlspecialchars(trim($_POST['title']));
                if (isset($_POST['description'])) $habit['description'] = htmlspecialchars(trim($_POST['description']));
                if (isset($_POST['color'])) $habit['color'] = $_POST['color'];
                if (isset($_POST['frequency'])) $habit['frequency'] = $_POST['frequency'];
                if (isset($_POST['reminder_time'])) $habit['reminder_time'] = $_POST['reminder_time'];
                break;
            }
        }
        
        saveUserHabits($userId, $habits);
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    elseif ($action === 'load') {
        $habits = getUserHabits($userId);
        $logs = getUserHabitLogs($userId);
        
        // اضافه کردن اطلاعات اضافی به هر عادت
        foreach ($habits as &$habit) {
            $habit['streak'] = getHabitStreak($userId, $habit['id']);
            $habit['completion_rate'] = getHabitCompletionRate($userId, $habit['id']);
            $habit['completed_today'] = isHabitCompletedToday($userId, $habit['id']);
        }
        
        $response = [
            'success' => true,
            'habits' => $habits,
            'logs' => $logs,
            'today_jalali' => getJalaliDate()
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== آماده‌سازی داده‌ها برای نمایش ====================
$habits = getUserHabits($userId);
$todayJalali = getJalaliDate();
list($currentJy, $currentJm, $currentJd) = explode('-', $todayJalali);
$currentMonthName = getJalaliMonthName((int)$currentJm);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیگیری عادت‌ها | ردیاب عادت</title>
    <link rel="stylesheet" href="../planner/style.css">
    <link rel="stylesheet" href="../assets/calendar.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --habit-card-bg: rgba(255, 255, 255, 0.95);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            font-family: 'Vazirmatn', 'Vazir', Tahoma, sans-serif;
        }
        
        .habits-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .habits-header {
            background: var(--habit-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .habits-title h1 {
            margin: 0;
            color: #333;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .habits-title p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .add-habit-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            font-family: inherit;
        }
        
        .add-habit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: var(--habit-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .habits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .habit-card {
            background: var(--habit-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .habit-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 5px;
            height: 100%;
            background: var(--habit-color);
        }
        
        .habit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .habit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .habit-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .habit-description {
            color: #666;
            font-size: 13px;
            margin: 0 0 15px 0;
        }
        
        .habit-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .habit-stat {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #666;
        }
        
        .habit-stat-icon {
            font-size: 16px;
        }
        
        .habit-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .habit-action-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-complete {
            background: var(--success-color);
            color: white;
        }
        
        .btn-complete.completed {
            background: #9ca3af;
        }
        
        .btn-edit {
            background: #f3f4f6;
            color: #333;
        }
        
        .btn-delete {
            background: #fee2e2;
            color: var(--danger-color);
        }
        
        .calendar-section {
            background: var(--habit-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .calendar-nav {
            display: flex;
            gap: 10px;
        }
        
        .calendar-nav-btn {
            background: #f3f4f6;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        
        .calendar-day-header {
            text-align: center;
            padding: 10px;
            font-weight: bold;
            color: #666;
            font-size: 14px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .calendar-day:hover {
            background: #e5e7eb;
        }
        
        .calendar-day.today {
            background: var(--primary-gradient);
            color: white;
        }
        
        .calendar-day.has-completions {
            border: 2px solid var(--success-color);
        }
        
        .calendar-day-number {
            font-size: 16px;
            font-weight: bold;
        }
        
        .calendar-day-dots {
            display: flex;
            gap: 2px;
            margin-top: 3px;
        }
        
        .completion-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--success-color);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 85vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
            font-weight: bold;
            font-size: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .color-picker {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .color-option {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid transparent;
            transition: transform 0.3s ease;
        }
        
        .color-option:hover {
            transform: scale(1.1);
        }
        
        .color-option.selected {
            border-color: #333;
        }
        
        .btn-submit {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-family: inherit;
            font-weight: bold;
        }
        
        .back-to-planner {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            color: #333;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        @media (max-width: 768px) {
            .habits-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <a href="../planner/index.php" class="back-to-planner">
        <span>← بازگشت به پلنر</span>
    </a>
    
    <div class="habits-container">
        <!-- Header -->
        <div class="habits-header">
            <div class="habits-title">
                <h1>
                    <span style="font-size: 32px;">🎯</span>
                    ردیاب عادت‌ها
                </h1>
                <p id="currentDateDisplay"><?php echo "امروز: {$currentJd} {$currentMonthName} {$currentJy}"; ?></p>
            </div>
            <button class="add-habit-btn" onclick="openAddHabitModal()">
                <span>+</span>
                <span>افزودن عادت جدید</span>
            </button>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-value" id="totalHabits">0</div>
                <div class="stat-label">کل عادت‌ها</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="completedToday">0</div>
                <div class="stat-label">انجام شده امروز</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="currentStreak">0</div>
                <div class="stat-label">بیشترین زنجیره</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="avgCompletion">0%</div>
                <div class="stat-label">میانگین موفقیت</div>
            </div>
        </div>
        
        <!-- Habits Grid -->
        <div class="habits-grid" id="habitsGrid">
            <!-- Habit cards will be rendered here -->
        </div>
        
        <!-- Calendar Section -->
        <div class="calendar-section">
            <div class="calendar-header">
                <div class="calendar-title" id="calendarTitle"><?php echo "{$currentMonthName} {$currentJy}"; ?></div>
                <div class="calendar-nav">
                    <button class="calendar-nav-btn" onclick="previousMonth()">ماه قبل</button>
                    <button class="calendar-nav-btn" onclick="nextMonth()">ماه بعد</button>
                </div>
            </div>
            <div class="calendar-grid">
                <div class="calendar-day-header">شنبه</div>
                <div class="calendar-day-header">یکشنبه</div>
                <div class="calendar-day-header">دوشنبه</div>
                <div class="calendar-day-header">سه‌شنبه</div>
                <div class="calendar-day-header">چهارشنبه</div>
                <div class="calendar-day-header">پنج‌شنبه</div>
                <div class="calendar-day-header">جمعه</div>
            </div>
            <div class="calendar-grid" id="calendarDays">
                <!-- Calendar days will be rendered here -->
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Habit Modal -->
    <div id="habitModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">افزودن عادت جدید</span>
                <button class="modal-close" onclick="closeHabitModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="habitForm" onsubmit="saveHabit(event)">
                    <input type="hidden" id="editHabitId">
                    
                    <div class="form-group">
                        <label for="habitTitle">عنوان عادت *</label>
                        <input type="text" id="habitTitle" class="form-control" required placeholder="مثلاً: ورزش روزانه">
                    </div>
                    
                    <div class="form-group">
                        <label for="habitDescription">توضیحات</label>
                        <textarea id="habitDescription" class="form-control" rows="3" placeholder="توضیحات اختیاری..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="habitFrequency">تکرار</label>
                        <select id="habitFrequency" class="form-control">
                            <option value="daily">روزانه</option>
                            <option value="weekly">هفتگی</option>
                            <option value="monthly">ماهانه</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="habitReminder">زمان یادآوری</label>
                        <input type="time" id="habitReminder" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>رنگ</label>
                        <div class="color-picker" id="colorPicker">
                            <div class="color-option selected" data-color="#667eea" style="background: #667eea;"></div>
                            <div class="color-option" data-color="#764ba2" style="background: #764ba2;"></div>
                            <div class="color-option" data-color="#10b981" style="background: #10b981;"></div>
                            <div class="color-option" data-color="#f59e0b" style="background: #f59e0b;"></div>
                            <div class="color-option" data-color="#ef4444" style="background: #ef4444;"></div>
                            <div class="color-option" data-color="#3b82f6" style="background: #3b82f6;"></div>
                            <div class="color-option" data-color="#8b5cf6" style="background: #8b5cf6;"></div>
                            <div class="color-option" data-color="#ec4899" style="background: #ec4899;"></div>
                        </div>
                        <input type="hidden" id="habitColor" value="#667eea">
                    </div>
                    
                    <button type="submit" class="btn-submit">ذخیره عادت</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let habits = [];
        let logs = [];
        let currentMonth = <?php echo (int)$currentJm; ?>;
        let currentYear = <?php echo (int)$currentJy; ?>;
        let selectedColor = '#667eea';
        
        // Color picker
        document.querySelectorAll('.color-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                selectedColor = this.dataset.color;
                document.getElementById('habitColor').value = selectedColor;
            });
        });
        
        function openAddHabitModal() {
            document.getElementById('modalTitle').textContent = 'افزودن عادت جدید';
            document.getElementById('habitForm').reset();
            document.getElementById('editHabitId').value = '';
            document.getElementById('habitColor').value = '#667eea';
            selectedColor = '#667eea';
            document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
            document.querySelector('.color-option[data-color="#667eea"]').classList.add('selected');
            document.getElementById('habitModal').classList.add('show');
        }
        
        function closeHabitModal() {
            document.getElementById('habitModal').classList.remove('show');
        }
        
        function editHabit(habitId) {
            const habit = habits.find(h => h.id === habitId);
            if (!habit) return;
            
            document.getElementById('modalTitle').textContent = 'ویرایش عادت';
            document.getElementById('editHabitId').value = habit.id;
            document.getElementById('habitTitle').value = habit.title;
            document.getElementById('habitDescription').value = habit.description || '';
            document.getElementById('habitFrequency').value = habit.frequency || 'daily';
            document.getElementById('habitReminder').value = habit.reminder_time || '';
            document.getElementById('habitColor').value = habit.color || '#667eea';
            
            selectedColor = habit.color || '#667eea';
            document.querySelectorAll('.color-option').forEach(o => {
                o.classList.toggle('selected', o.dataset.color === selectedColor);
            });
            
            document.getElementById('habitModal').classList.add('show');
        }
        
        function saveHabit(event) {
            event.preventDefault();
            
            const formData = new FormData();
            const habitId = document.getElementById('editHabitId').value;
            
            if (habitId) {
                formData.append('action', 'edit_habit');
                formData.append('habit_id', habitId);
            } else {
                formData.append('action', 'add_habit');
            }
            
            formData.append('title', document.getElementById('habitTitle').value);
            formData.append('description', document.getElementById('habitDescription').value);
            formData.append('frequency', document.getElementById('habitFrequency').value);
            formData.append('reminder_time', document.getElementById('habitReminder').value);
            formData.append('color', document.getElementById('habitColor').value);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    habits = result.habits;
                    renderHabits();
                    updateStats();
                    closeHabitModal();
                }
            });
        }
        
        function deleteHabit(habitId) {
            if (!confirm('آیا مطمئن هستید که می‌خواهید این عادت را حذف کنید؟')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_habit');
            formData.append('habit_id', habitId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    habits = result.habits;
                    renderHabits();
                    updateStats();
                }
            });
        }
        
        function toggleHabit(habitId, completed) {
            const formData = new FormData();
            formData.append('action', 'toggle_habit');
            formData.append('habit_id', habitId);
            formData.append('completed', completed ? 'true' : 'false');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    habits = result.habits;
                    renderHabits();
                    updateStats();
                }
            });
        }
        
        function renderHabits() {
            const grid = document.getElementById('habitsGrid');
            grid.innerHTML = '';
            
            habits.forEach(habit => {
                const card = document.createElement('div');
                card.className = 'habit-card';
                card.style.setProperty('--habit-color', habit.color);
                
                card.innerHTML = `
                    <div class="habit-header">
                        <h3 class="habit-title">${escapeHtml(habit.title)}</h3>
                    </div>
                    ${habit.description ? `<p class="habit-description">${escapeHtml(habit.description)}</p>` : ''}
                    <div class="habit-stats">
                        <div class="habit-stat">
                            <span class="habit-stat-icon">🔥</span>
                            <span>${habit.streak || 0} روز زنجیره</span>
                        </div>
                        <div class="habit-stat">
                            <span class="habit-stat-icon">📊</span>
                            <span>${habit.completion_rate || 0}% موفقیت</span>
                        </div>
                    </div>
                    <div class="habit-actions">
                        <button class="habit-action-btn btn-complete ${habit.completed_today ? 'completed' : ''}" 
                                onclick="toggleHabit('${habit.id}', ${!habit.completed_today})">
                            ${habit.completed_today ? '✓ انجام شد' : 'انجام دادن'}
                        </button>
                        <button class="habit-action-btn btn-edit" onclick="editHabit('${habit.id}')">ویرایش</button>
                        <button class="habit-action-btn btn-delete" onclick="deleteHabit('${habit.id}')">حذف</button>
                    </div>
                `;
                
                grid.appendChild(card);
            });
        }
        
        function updateStats() {
            const totalHabits = habits.length;
            const completedToday = habits.filter(h => h.completed_today).length;
            const maxStreak = Math.max(...habits.map(h => h.streak || 0), 0);
            const avgCompletion = totalHabits > 0 
                ? Math.round(habits.reduce((sum, h) => sum + (h.completion_rate || 0), 0) / totalHabits)
                : 0;
            
            document.getElementById('totalHabits').textContent = totalHabits;
            document.getElementById('completedToday').textContent = completedToday;
            document.getElementById('currentStreak').textContent = maxStreak;
            document.getElementById('avgCompletion').textContent = avgCompletion + '%';
        }
        
        function renderCalendar() {
            const calendarDays = document.getElementById('calendarDays');
            calendarDays.innerHTML = '';
            
            // Get first day of month
            const firstDay = new Date(currentYear, currentMonth - 1, 1);
            const lastDay = new Date(currentYear, currentMonth, 0);
            
            // Convert to Jalali
            const firstDayJalali = gregorianToJalali(firstDay.getFullYear(), firstDay.getMonth() + 1, firstDay.getDate());
            const lastDayJalali = gregorianToJalali(lastDay.getFullYear(), lastDay.getMonth() + 1, lastDay.getDate());
            
            // Calculate starting day of week (Saturday = 0)
            let startDayOfWeek = new Date(currentYear, currentMonth - 1, 1).getDay();
            startDayOfWeek = (startDayOfWeek + 1) % 7; // Convert to Saturday-based
            
            // Add empty cells for days before the first day
            for (let i = 0; i < startDayOfWeek; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day';
                emptyCell.style.visibility = 'hidden';
                calendarDays.appendChild(emptyCell);
            }
            
            // Add days of the month
            for (let day = 1; day <= lastDayJalali[2]; day++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                
                const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                
                // Check if today
                const today = <?php echo $todayJalali; ?>;
                if (dateStr === today) {
                    dayCell.classList.add('today');
                }
                
                // Count completions for this day
                const dayCompletions = logs.filter(log => log.date === dateStr && log.completed);
                if (dayCompletions.length > 0) {
                    dayCell.classList.add('has-completions');
                }
                
                // Create dots for completions
                const dotsHtml = dayCompletions.length > 0 
                    ? `<div class="calendar-day-dots">${Array(Math.min(dayCompletions.length, 3)).fill('<div class="completion-dot"></div>').join('')}</div>`
                    : '';
                
                dayCell.innerHTML = `
                    <div class="calendar-day-number">${day}</div>
                    ${dotsHtml}
                `;
                
                calendarDays.appendChild(dayCell);
            }
            
            // Update calendar title
            const monthNames = [
                '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
            ];
            document.getElementById('calendarTitle').textContent = `${monthNames[currentMonth]} ${currentYear}`;
        }
        
        function previousMonth() {
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            renderCalendar();
        }
        
        function nextMonth() {
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            renderCalendar();
        }
        
        function gregorianToJalali(gy, gm, gd) {
            const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
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
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function loadData() {
            const formData = new FormData();
            formData.append('action', 'load');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    habits = result.habits || [];
                    logs = result.logs || [];
                    renderHabits();
                    renderCalendar();
                    updateStats();
                }
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('habitModal')) {
                closeHabitModal();
            }
        };
        
        // Initialize
        document.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>
</html>
