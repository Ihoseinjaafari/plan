<?php
// /Habits/api.php - API برای مدیریت عادت‌ها
session_start();
date_default_timezone_set('Asia/Tehran');

header('Content-Type: application/json');

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
    echo json_encode(['success' => false, 'error' => 'لطفاً وارد شوید']);
    exit;
}

$currentUser = getUserById($_SESSION['user_id']);
if (!$currentUser) {
    echo json_encode(['success' => false, 'error' => 'کاربر یافت نشد']);
    exit;
}

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

function getJalaliDate() {
    list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('n'), date('j'));
    return sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
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
    
    $habitLogs = array_values(array_filter($logs, function($log) use ($habitId) {
        return ($log['habit_id'] ?? '') == $habitId && ($log['completed'] ?? false) == true;
    }));
    
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
        } else {
            $response = ['success' => false, 'error' => 'عنوان عادت نمی‌تواند خالی باشد'];
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
    // ==================== یادآورها ====================
    elseif ($action === 'create_reminder' || $action === 'update_reminder') {
        $remindersFile = __DIR__ . '/../data/reminders.json';
        if (!file_exists($remindersFile)) file_put_contents($remindersFile, json_encode([]));
        
        $allReminders = json_decode(file_get_contents($remindersFile), true) ?? [];
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($action === 'create_reminder') {
            $newReminder = [
                'id' => time() . rand(1000, 9999),
                'user_id' => $input['user_id'],
                'title' => htmlspecialchars($input['title']),
                'description' => htmlspecialchars($input['description'] ?? ''),
                'time' => $input['time'],
                'days' => json_encode($input['days']),
                'enabled' => $input['enabled'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            $allReminders[] = $newReminder;
        } else {
            foreach ($allReminders as &$reminder) {
                if ($reminder['id'] == $input['id']) {
                    $reminder['title'] = htmlspecialchars($input['title']);
                    $reminder['description'] = htmlspecialchars($input['description'] ?? '');
                    $reminder['time'] = $input['time'];
                    $reminder['days'] = json_encode($input['days']);
                    $reminder['enabled'] = $input['enabled'];
                    break;
                }
            }
        }
        
        file_put_contents($remindersFile, json_encode($allReminders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $response = ['success' => true];
    }
    elseif ($action === 'delete_reminder') {
        $remindersFile = __DIR__ . '/../data/reminders.json';
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (file_exists($remindersFile)) {
            $allReminders = json_decode(file_get_contents($remindersFile), true) ?? [];
            $allReminders = array_values(array_filter($allReminders, function($r) use ($input) {
                return ($r['id'] ?? '') != $input['id'];
            }));
            file_put_contents($remindersFile, json_encode($allReminders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $response = ['success' => true];
    }
    // ==================== روتین‌ها ====================
    elseif ($action === 'create_routine' || $action === 'update_routine') {
        $routinesFile = __DIR__ . '/../data/routines.json';
        if (!file_exists($routinesFile)) file_put_contents($routinesFile, json_encode([]));
        
        $allRoutines = json_decode(file_get_contents($routinesFile), true) ?? [];
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($action === 'create_routine') {
            $newRoutine = [
                'id' => time() . rand(1000, 9999),
                'user_id' => $input['user_id'],
                'title' => htmlspecialchars($input['title']),
                'time_slot' => $input['time_slot'],
                'habits' => json_encode($input['habits'] ?? []),
                'completed' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $allRoutines[] = $newRoutine;
        } else {
            foreach ($allRoutines as &$routine) {
                if ($routine['id'] == $input['id']) {
                    $routine['title'] = htmlspecialchars($input['title']);
                    $routine['time_slot'] = $input['time_slot'];
                    $routine['habits'] = json_encode($input['habits'] ?? []);
                    break;
                }
            }
        }
        
        file_put_contents($routinesFile, json_encode($allRoutines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $response = ['success' => true];
    }
    elseif ($action === 'toggle_routine') {
        $routinesFile = __DIR__ . '/../data/routines.json';
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (file_exists($routinesFile)) {
            $allRoutines = json_decode(file_get_contents($routinesFile), true) ?? [];
            foreach ($allRoutines as &$routine) {
                if ($routine['id'] == $input['id']) {
                    $routine['completed'] = $input['completed'];
                    break;
                }
            }
            file_put_contents($routinesFile, json_encode($allRoutines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $response = ['success' => true];
    }
    elseif ($action === 'delete_routine') {
        $routinesFile = __DIR__ . '/../data/routines.json';
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (file_exists($routinesFile)) {
            $allRoutines = json_decode(file_get_contents($routinesFile), true) ?? [];
            $allRoutines = array_values(array_filter($allRoutines, function($r) use ($input) {
                return ($r['id'] ?? '') != $input['id'];
            }));
            file_put_contents($routinesFile, json_encode($allRoutines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $response = ['success' => true];
    }
    // ==================== جلسات تمرکز ====================
    elseif ($action === 'log_focus_session') {
        $focusSessionsFile = __DIR__ . '/../data/focus_sessions.json';
        if (!file_exists($focusSessionsFile)) file_put_contents($focusSessionsFile, json_encode([]));
        
        $input = json_decode(file_get_contents('php://input'), true);
        $allSessions = json_decode(file_get_contents($focusSessionsFile), true) ?? [];
        
        $newSession = [
            'id' => time() . rand(1000, 9999),
            'user_id' => $input['user_id'],
            'duration' => (int)$input['duration'],
            'mode' => $input['mode'],
            'date' => date('Y-m-d'),
            'time' => date('H:i'),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $allSessions[] = $newSession;
        
        file_put_contents($focusSessionsFile, json_encode($allSessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $response = ['success' => true];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== درخواست‌های GET ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'get_habits') {
        $habits = getUserHabits($userId);
        foreach ($habits as &$habit) {
            $habit['streak'] = getHabitStreak($userId, $habit['id']);
            $habit['completion_rate'] = getHabitCompletionRate($userId, $habit['id']);
        }
        $response = ['success' => true, 'habits' => $habits];
    }
    elseif ($action === 'get_reminder') {
        $remindersFile = __DIR__ . '/../data/reminders.json';
        $reminderId = $_GET['id'] ?? '';
        
        if (file_exists($remindersFile)) {
            $allReminders = json_decode(file_get_contents($remindersFile), true) ?? [];
            foreach ($allReminders as $reminder) {
                if ($reminder['id'] == $reminderId && $reminder['user_id'] == $userId) {
                    $response = ['success' => true, 'reminder' => $reminder];
                    break;
                }
            }
        }
    }
    elseif ($action === 'get_routine') {
        $routinesFile = __DIR__ . '/../data/routines.json';
        $routineId = $_GET['id'] ?? '';
        
        if (file_exists($routinesFile)) {
            $allRoutines = json_decode(file_get_contents($routinesFile), true) ?? [];
            foreach ($allRoutines as $routine) {
                if ($routine['id'] == $routineId && $routine['user_id'] == $userId) {
                    $response = ['success' => true, 'routine' => $routine];
                    break;
                }
            }
        }
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => 'درخواست نامعتبر']);
