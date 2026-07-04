<?php
// planner/habits.php - هبیت ترکر حرفه‌ای با تقویم شمسی
session_start();
date_default_timezone_set('Asia/Tehran');

$usersFile = __DIR__ . '/../data/users.json';

function getUserById($id) {
    global $usersFile;
    if (!file_exists($usersFile)) return null;
    $users = json_decode(file_get_contents($usersFile), true);
    if (!is_array($users)) return null;
    foreach ($users as $user) {
        if ($user['id'] == $id) return $user;
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

// ==================== توابع تبدیل تاریخ (همانند calendar/index.php) ====================
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

function getJalaliToday() {
    list($gy, $gm, $gd) = explode('-', date('Y-m-d'));
    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf("%04d-%02d-%02d", $jy, $jm, $jd);
}

function getGregorianFromJalali($jy, $jm, $jd) {
    list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
}

$habitsFile = __DIR__ . '/../data/habits.json';
$habitLogsFile = __DIR__ . '/../data/habit_logs.json';

if (!file_exists($habitsFile)) file_put_contents($habitsFile, json_encode([]));
if (!file_exists($habitLogsFile)) file_put_contents($habitLogsFile, json_encode([]));

function getUserHabits($userId) {
    global $habitsFile;
    $habits = json_decode(file_get_contents($habitsFile), true);
    if (!is_array($habits)) $habits = [];
    return array_values(array_filter($habits, fn($h) => ($h['user_id'] ?? '') == $userId));
}

function getHabitLogs($userId) {
    global $habitLogsFile;
    $logs = json_decode(file_get_contents($habitLogsFile), true);
    if (!is_array($logs)) $logs = [];
    return array_values(array_filter($logs, fn($l) => ($l['user_id'] ?? '') == $userId));
}

function saveUserHabits($userId, $habits) {
    global $habitsFile;
    $allHabits = json_decode(file_get_contents($habitsFile), true);
    if (!is_array($allHabits)) $allHabits = [];
    $allHabits = array_values(array_filter($allHabits, fn($h) => ($h['user_id'] ?? '') != $userId));
    foreach ($habits as &$habit) $habit['user_id'] = $userId;
    $allHabits = array_merge($allHabits, $habits);
    file_put_contents($habitsFile, json_encode($allHabits, JSON_PRETTY_PRINT));
}

function saveHabitLog($userId, $habitId, $date, $completed = true) {
    global $habitLogsFile;
    $logs = json_decode(file_get_contents($habitLogsFile), true);
    if (!is_array($logs)) $logs = [];
    $logs = array_values(array_filter($logs, fn($l) => !($l['user_id'] == $userId && $l['habit_id'] == $habitId && $l['date'] == $date)));
    if ($completed) {
        $logs[] = ['user_id' => $userId, 'habit_id' => $habitId, 'date' => $date, 'completed_at' => date('Y-m-d H:i:s')];
    }
    file_put_contents($habitLogsFile, json_encode($logs, JSON_PRETTY_PRINT));
}

function calculateStreak($habitId, $logs, $todayJalali) {
    $habitLogs = array_values(array_filter($logs, fn($l) => $l['habit_id'] == $habitId));
    usort($habitLogs, fn($a, $b) => strcmp($b['date'], $a['date']));
    $streak = 0;
    $checkDate = $todayJalali;
    foreach ($habitLogs as $log) {
        $logDate = $log['date'];
        if ($logDate == $checkDate) {
            $streak++;
            // محاسبه روز قبل
            list($jy, $jm, $jd) = explode('-', $checkDate);
            $jd--;
            if ($jd < 1) {
                $jm--;
                if ($jm < 1) {
                    $jm = 12;
                    $jy--;
                }
                $daysInMonth = ($jm <= 6) ? 31 : (($jm <= 11) ? 30 : ($jy % 4 == 3 ? 30 : 29));
                $jd = $daysInMonth;
            }
            $checkDate = sprintf("%04d-%02d-%02d", $jy, $jm, $jd);
        } elseif ($logDate < $checkDate) {
            break;
        }

    }
    return $streak;
}

function getWeekDates() {
    // دریافت 7 روز اخیر به تاریخ شمسی
    $dates = [];
    list($gy, $gm, $gd) = explode('-', date('Y-m-d'));
    for ($i = 6; $i >= 0; $i--) {
        $timestamp = strtotime("-{$i} days", strtotime(date('Y-m-d')));
        list($jy, $jm, $jd) = gregorian_to_jalali(
            date('Y', $timestamp),
            date('m', $timestamp),
            date('d', $timestamp)
        );
        $dates[] = sprintf("%04d-%02d-%02d", $jy, $jm, $jd);
    }
    return $dates;
}

function getDayName($jalaliDate) {
    list($jy, $jm, $jd) = explode('-', $jalaliDate);
    $gregorian = getGregorianFromJalali($jy, $jm, $jd);
    $dayOfWeek = date('w', strtotime($gregorian));
    $names = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
    return $names[$dayOfWeek];
}

function getShortDayName($jalaliDate) {
    list($jy, $jm, $jd) = explode('-', $jalaliDate);
    $gregorian = getGregorianFromJalali($jy, $jm, $jd);
    $dayOfWeek = date('w', strtotime($gregorian));
    $names = ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'];
    return $names[$dayOfWeek];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    $userId = $_SESSION['user_id'];
    $todayJalali = getJalaliToday();
    $todayGregorian = date('Y-m-d');

    if ($action === 'load_habits') {
        $habits = getUserHabits($userId);
        $logs = getHabitLogs($userId);
        foreach ($habits as &$habit) {
            $habit['streak'] = calculateStreak($habit['id'], $logs, $todayJalali);
            $habit['completed_today'] = false;
            foreach ($logs as $log) {
                if ($log['habit_id'] == $habit['id'] && $log['date'] == $todayJalali) {

                    $habit['completed_today'] = true;
                    break;
                }
            }
        }
        $weekDates = getWeekDates();
        $weeklyData = [];
        foreach ($habits as $habit) {
            $weeklyData[$habit['id']] = [];
            foreach ($weekDates as $date) {
                foreach ($logs as $log) {
                    if ($log['habit_id'] == $habit['id'] && $log['date'] == $date) {
                        $weeklyData[$habit['id']][$date] = true;
                        break;
                    }
                }
            }
        }
        $response = ['success' => true, 'habits' => $habits, 'weekDates' => $weekDates, 'weeklyData' => $weeklyData];

    }
    elseif ($action === 'add_habit') {
        $habits = getUserHabits($userId);
        $colors = ['#f5576c', '#4facfe', '#43e97b', '#fa709a', '#a18cd1', '#fbc2eb', '#667eea', '#764ba2'];
        $newId = time() . rand(100, 999);
        $newHabit = [
            'id' => $newId, 'user_id' => $userId,
            'name' => htmlspecialchars(trim($_POST['name'] ?? '')),
            'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
            'frequency' => $_POST['frequency'] ?? 'daily',
            'target' => intval($_POST['target'] ?? 1),
            'color' => $colors[array_rand($colors)],
            'icon' => $_POST['icon'] ?? 'fa-fire',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $habits[] = $newHabit;
        saveUserHabits($userId, $habits);
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    elseif ($action === 'toggle_habit') {
        $logs = getHabitLogs($userId);
        $habitId = $_POST['id'];
        $alreadyCompleted = false;
        foreach ($logs as $log) {
            if ($log['habit_id'] == $habitId && $log['date'] == $todayJalali) {

                $alreadyCompleted = true;
                break;
            }
        }
        saveHabitLog($userId, $habitId, $todayJalali, !$alreadyCompleted);
        $habits = getUserHabits($userId);
        $logs = getHabitLogs($userId);
        foreach ($habits as &$habit) {
            $habit['streak'] = calculateStreak($habit['id'], $logs, $todayJalali);
            $habit['completed_today'] = false;
            foreach ($logs as $log) {
                if ($log['habit_id'] == $habit['id'] && $log['date'] == $todayJalali) {

                    $habit['completed_today'] = true;
                    break;
                }
            }
        }
        $weekDates = getWeekDates();
        $weeklyData = [];
        foreach ($habits as $habit) {
            $weeklyData[$habit['id']] = [];
            foreach ($weekDates as $date) {
                foreach ($logs as $log) {
                    if ($log['habit_id'] == $habit['id'] && $log['date'] == $date) {
                        $weeklyData[$habit['id']][$date] = true;
                        break;
                    }
                }
            }
        }
        $response = ['success' => true, 'habits' => $habits, 'weekDates' => $weekDates, 'weeklyData' => $weeklyData];
    }
    elseif ($action === 'delete_habit') {
        $habits = getUserHabits($userId);
        $habits = array_values(array_filter($habits, fn($h) => $h['id'] != $_POST['id']));
        saveUserHabits($userId, $habits);
        $logs = getHabitLogs($userId);
        $logs = array_values(array_filter($logs, fn($l) => $l['habit_id'] != $_POST['id']));
        file_put_contents($habitLogsFile, json_encode($logs, JSON_PRETTY_PRINT));
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    elseif ($action === 'edit_habit') {
        $habits = getUserHabits($userId);
        foreach ($habits as &$habit) {
            if ($habit['id'] == $_POST['id']) {
                if (isset($_POST['name'])) $habit['name'] = htmlspecialchars(trim($_POST['name']));
                if (isset($_POST['description'])) $habit['description'] = htmlspecialchars(trim($_POST['description']));
                if (isset($_POST['frequency'])) $habit['frequency'] = $_POST['frequency'];
                if (isset($_POST['target'])) $habit['target'] = intval($_POST['target']);
                if (isset($_POST['icon'])) $habit['icon'] = $_POST['icon'];
                break;
            }
        }
        saveUserHabits($userId, $habits);
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    
    echo json_encode($response);
    exit;
}

$page_title = 'عادت‌های من';
include_once __DIR__ . '/../includes/header.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | Life+</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Vazirmatn',sans-serif!important;background:linear-gradient(135deg,#0f0c29,#302b63);min-height:100vh;padding:20px;color:#fff}
        .container{max-width:1200px;margin:0 auto}
        .habits-header{background:rgba(255,255,255,0.08);backdrop-filter:blur(10px);border-radius:20px;padding:25px 30px;margin-bottom:25px;border:1px solid rgba(255,255,255,0.1);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;box-shadow:0 8px 25px rgba(0,0,0,0.2)}
        .habits-header h1{font-size:24px;color:#fff;display:flex;align-items:center;gap:12px}
        .habits-header h1 i{color:#f5576c}
        .header-actions{display:flex;gap:10px;flex-wrap:wrap}
        .btn{padding:10px 20px;border-radius:12px;cursor:pointer;font-size:14px;font-family:inherit;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;border:none}
        .btn-primary{background:linear-gradient(135deg,#f5576c,#4facfe);color:#fff}
        .btn-primary:hover{transform:scale(1.02);box-shadow:0 5px 20px rgba(245,87,108,0.4)}
        .btn-secondary{background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2)}
        .btn-secondary:hover{background:rgba(255,255,255,0.15);border-color:#667eea}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:25px}
        .stat-card{background:rgba(255,255,255,0.08);backdrop-filter:blur(10px);border-radius:16px;padding:20px;border:1px solid rgba(255,255,255,0.1);text-align:center;transition:all 0.3s}
        .stat-card:hover{transform:translateY(-3px);border-color:#667eea;box-shadow:0 8px 25px rgba(0,0,0,0.2)}
        .stat-card .icon{font-size:32px;margin-bottom:10px}
        .stat-card .number{font-size:32px;font-weight:700;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .stat-card .label{font-size:13px;color:rgba(255,255,255,0.6);margin-top:5px}
        .habits-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-bottom:25px}
        .habit-card{background:rgba(255,255,255,0.08);backdrop-filter:blur(10px);border-radius:20px;padding:25px;border:1px solid rgba(255,255,255,0.1);transition:all 0.3s;position:relative;overflow:hidden}
        .habit-card::before{content:'';position:absolute;top:0;right:0;width:4px;height:100%;background:var(--habit-color,#f5576c)}
        .habit-card:hover{transform:translateY(-5px);border-color:rgba(255,255,255,0.2);box-shadow:0 10px 30px rgba(0,0,0,0.3)}
        .habit-card.completed{background:rgba(40,167,69,0.1);border-color:rgba(40,167,69,0.3)}
        .habit-header{display:flex;align-items:flex-start;gap:15px;margin-bottom:15px}
        .habit-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;flex-shrink:0}
        .habit-info{flex:1}
        .habit-name{font-size:18px;font-weight:600;color:#fff;margin-bottom:5px}
        .habit-desc{font-size:13px;color:rgba(255,255,255,0.6)}
        .habit-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:15px 0}
        .habit-stat{background:rgba(255,255,255,0.05);padding:12px;border-radius:12px;text-align:center}
        .habit-stat .value{font-size:20px;font-weight:700;color:#667eea}
        .habit-stat .label{font-size:11px;color:rgba(255,255,255,0.5);margin-top:3px}
        .habit-actions{display:flex;gap:10px;margin-top:15px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.1)}
        .habit-actions .btn{flex:1;justify-content:center;padding:10px;font-size:13px}
        .btn-check{background:linear-gradient(135deg,#28a745,#20c997);color:#fff}
        .btn-check.completed{background:linear-gradient(135deg,#dc3545,#c82333)}
        .btn-edit{background:rgba(102,126,234,0.2);color:#667eea}
        .btn-delete{background:rgba(220,53,69,0.2);color:#dc3545}
        .weekly-progress{background:rgba(255,255,255,0.08);backdrop-filter:blur(10px);border-radius:20px;padding:25px;border:1px solid rgba(255,255,255,0.1);margin-bottom:25px}
        .weekly-progress h2{font-size:18px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        .week-days{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:15px}
        .day-cell{background:rgba(255,255,255,0.05);border-radius:10px;padding:10px 5px;text-align:center;border:1px solid rgba(255,255,255,0.1)}
        .day-cell.today{border-color:#f5576c;background:rgba(245,87,108,0.1)}
        .day-cell .day-name{font-size:12px;color:rgba(255,255,255,0.6);margin-bottom:5px}
        .day-cell .day-number{font-size:18px;font-weight:600}
        .habit-week-row{display:grid;grid-template-columns:1fr repeat(7,1fr);gap:8px;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05)}
        .habit-week-row:last-child{border-bottom:none}
        .habit-week-name{font-size:13px;color:rgba(255,255,255,0.8);display:flex;align-items:center;gap:8px}
        .week-check{aspect-ratio:1;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.3s}
        .week-check.completed{background:rgba(40,167,69,0.3);border-color:#28a745;color:#28a745}
        .week-check:hover{border-color:#667eea}
        .modal{display:none;position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,0.7);backdrop-filter:blur(5px)}
        .modal.active{display:flex;align-items:center;justify-content:center}
        .modal-content{background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:25px;width:90%;max-width:500px;border:1px solid rgba(255,255,255,0.1);box-shadow:0 20px 60px rgba(0,0,0,0.5)}
        .modal-header{background:linear-gradient(135deg,#f5576c,#4facfe);padding:20px 25px;border-radius:25px 25px 0 0;font-weight:600;font-size:18px}
        .modal-body{padding:25px}
        .form-group{margin-bottom:18px}
        .form-group label{display:block;margin-bottom:8px;font-size:14px;color:rgba(255,255,255,0.8)}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:12px 15px;border:1px solid rgba(255,255,255,0.1);border-radius:12px;background:rgba(255,255,255,0.05);color:#fff;font-size:14px;font-family:inherit;transition:all 0.3s}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
        .form-group textarea{resize:vertical;min-height:80px}
        .icon-selector{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-top:10px}
        .icon-option{aspect-ratio:1;border-radius:10px;background:rgba(255,255,255,0.05);border:2px solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;font-size:20px;cursor:pointer;transition:all 0.3s}
        .icon-option:hover{border-color:#667eea;background:rgba(102,126,234,0.2)}
        .icon-option.selected{border-color:#f5576c;background:rgba(245,87,108,0.2)}
        .modal-footer{padding:20px 25px 25px;display:flex;gap:12px;justify-content:flex-end}
        .modal-footer .btn{flex:1}
        .btn-cancel{background:rgba(255,255,255,0.1);color:#fff}
        .toast{position:fixed;bottom:30px;right:30px;background:#1a1a2e;color:#fff;padding:15px 25px;border-radius:12px;font-size:14px;z-index:9999;opacity:0;transform:translateY(100px);transition:all 0.3s ease;border:1px solid rgba(255,255,255,0.1)}
        .toast.show{opacity:1;transform:translateY(0)}
        .toast.success{background:rgba(40,167,69,0.9);border-color:#28a745}
        .toast.error{background:rgba(220,53,69,0.9);border-color:#dc3545}
        .empty-state{text-align:center;padding:60px 20px;color:rgba(255,255,255,0.5);grid-column:1/-1}
        .empty-state i{font-size:64px;margin-bottom:20px;opacity:0.3}
        .empty-state p{font-size:16px;margin-bottom:10px}
        .empty-state .hint{font-size:13px;opacity:0.6}
        @media(max-width:768px){.habits-header{flex-direction:column;align-items:stretch}.header-actions{flex-direction:column}.habits-grid{grid-template-columns:1fr}.week-days{grid-template-columns:repeat(7,1fr);gap:4px}.day-cell{padding:8px 2px}.day-cell .day-name{font-size:10px}.day-cell .day-number{font-size:14px}.habit-week-row{grid-template-columns:1fr repeat(7,1fr);gap:4px}.habit-week-name{font-size:11px}.week-check{border-radius:6px}}
    </style>
</head>
<body>
    <div class="container">
        <div class="habits-header">
            <h1><i class="fas fa-fire"></i> عادت‌های من</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> عادت جدید</button>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> بازگشت به پلنر</a>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><div class="icon" style="color:#f5576c"><i class="fas fa-fire"></i></div><div class="number" id="totalHabits">0</div><div class="label">کل عادت‌ها</div></div>
            <div class="stat-card"><div class="icon" style="color:#28a745"><i class="fas fa-check-circle"></i></div><div class="number" id="completedToday">0</div><div class="label">انجام شده امروز</div></div>
            <div class="stat-card"><div class="icon" style="color:#667eea"><i class="fas fa-trophy"></i></div><div class="number" id="bestStreak">0</div><div class="label">بهترین رکورد</div></div>
            <div class="stat-card"><div class="icon" style="color:#f093fb"><i class="fas fa-chart-line"></i></div><div class="number" id="completionRate">0%</div><div class="label">نرخ تکمیل</div></div>
        </div>
        <div class="weekly-progress">
            <h2><i class="fas fa-calendar-week"></i> پیشرفت این هفته</h2>
            <div class="week-days" id="weekDays"></div>
            <div id="weeklyData"></div>
        </div>
        <div class="habits-grid" id="habitsContainer">
            <div class="empty-state"><i class="fas fa-seedling"></i><p>هیچ عادتی تعریف نشده است</p><p class="hint">با کلیک روی دکمه "عادت جدید" شروع کنید</p></div>
        </div>
    </div>
    <div class="modal" id="habitModal">
        <div class="modal-content">
            <div class="modal-header" id="modalTitle"><i class="fas fa-plus"></i> افزودن عادت جدید</div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="form-group"><label>نام عادت *</label><input type="text" id="habitName" placeholder="مثلاً: مطالعه روزانه" required></div>
                <div class="form-group"><label>توضیحات</label><textarea id="habitDescription" placeholder="توضیحات اختیاری..."></textarea></div>
                <div class="form-group"><label>تکرار</label><select id="habitFrequency"><option value="daily">روزانه</option><option value="weekly">هفتگی</option><option value="monthly">ماهانه</option></select></div>
                <div class="form-group"><label>هدف روزانه</label><input type="number" id="habitTarget" value="1" min="1"></div>
                <div class="form-group"><label>آیکون</label><div class="icon-selector" id="iconSelector">
                    <div class="icon-option selected" data-icon="fa-fire"><i class="fas fa-fire"></i></div>
                    <div class="icon-option" data-icon="fa-book"><i class="fas fa-book"></i></div>
                    <div class="icon-option" data-icon="fa-running"><i class="fas fa-running"></i></div>
                    <div class="icon-option" data-icon="fa-heart"><i class="fas fa-heart"></i></div>
                    <div class="icon-option" data-icon="fa-tint"><i class="fas fa-tint"></i></div>
                    <div class="icon-option" data-icon="fa-bed"><i class="fas fa-bed"></i></div>
                    <div class="icon-option" data-icon="fa-brain"><i class="fas fa-brain"></i></div>
                    <div class="icon-option" data-icon="fa-smile"><i class="fas fa-smile"></i></div>
                    <div class="icon-option" data-icon="fa-carrot"><i class="fas fa-carrot"></i></div>
                    <div class="icon-option" data-icon="fa-walking"><i class="fas fa-walking"></i></div>
                    <div class="icon-option" data-icon="fa-bicycle"><i class="fas fa-bicycle"></i></div>
                    <div class="icon-option" data-icon="fa-yin-yang"><i class="fas fa-yin-yang"></i></div>
                </div></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-cancel" onclick="closeModal()">انصراف</button>
                <button class="btn btn-primary" id="saveBtn" onclick="saveHabit()">ذخیره</button>
            </div>
        </div>
    </div>
    <div class="toast" id="toast"></div>
    <script>
        let habits=[],weeklyData={},weekDates=[],selectedIcon='fa-fire';
        const todayJalali = '<?= getJalaliToday() ?>';
        
        function showToast(msg,type='success'){const t=document.getElementById('toast');t.textContent=msg;t.className='toast '+type+' show';setTimeout(()=>t.classList.remove('show'),3000)}
        
        async function loadHabits(){
            let fd=new FormData();
            fd.append('action','load_habits');
            let r=await fetch(window.location.href,{method:'POST',body:fd});
            let res=await r.json();
            if(res.success){
                habits=res.habits;
                weekDates=res.weekDates||[];
                weeklyData=res.weeklyData||{};
                renderHabits();
                updateStats();
                renderWeekDays();
                renderWeeklyData();
            }
        }
        
        function renderWeekDays(){
            const c=document.getElementById('weekDays');
            const dn=['ش','ی','د','س','چ','پ','ج'];
            c.innerHTML=weekDates.map((d,i)=>{
                const parts=d.split('-');
                const dayNum=parseInt(parts[2]);
                const isToday=d===todayJalali;
                return `<div class="day-cell ${isToday?'today':''}"><div class="day-name">${dn[i]}</div><div class="day-number">${dayNum}</div></div>`;
            }).join('');
        }
        
        function renderWeeklyData(){
            const c=document.getElementById('weeklyData');
            if(habits.length===0){
                c.innerHTML='<p style="text-align:center;color:rgba(255,255,255,0.5);padding:20px;">هنوز عادت‌ای اضافه نشده است</p>';
                return;
            }
            c.innerHTML=habits.map(h=>{
                const hd=weeklyData[h.id]||{};
                return `<div class="habit-week-row"><div class="habit-week-name"><i class="fas ${h.icon}" style="color:${h.color}"></i>${escapeHtml(h.name)}</div>${weekDates.map(d=>{
                    const cp=hd[d]||false;
                    return `<div class="week-check ${cp?'completed':''}" onclick="toggleHabitOnDate('${h.id}','${d}')">${cp?'<i class="fas fa-check"></i>':''}</div>`;
                }).join('')}</div>`;
            }).join('');
        }
        
        function renderHabits(){
            const c=document.getElementById('habitsContainer');
            if(!habits||habits.length===0){
                c.innerHTML=`<div class="empty-state"><i class="fas fa-seedling"></i><p>هیچ عادتی تعریف نشده است</p><p class="hint\">با کلیک روی دکمه "عادت جدید" شروع کنید</p></div>`;
                return;
            }
            c.innerHTML=habits.map(h=>{
                const ct=h.completed_today;
                return `<div class="habit-card ${ct?'completed':''}" style="--habit-color:${h.color}">
                    <div class="habit-header">
                        <div class="habit-icon" style="background:${h.color}"><i class="fas ${h.icon}"></i></div>
                        <div class="habit-info">
                            <div class="habit-name">${escapeHtml(h.name)}</div>
                            ${h.description?`<div class="habit-desc">${escapeHtml(h.description)}</div>`:''}
                        </div>
                    </div>
                    <div class="habit-stats">
                        <div class="habit-stat"><div class="value">${h.target||1}</div><div class="label">هدف روزانه</div></div>
                        <div class="habit-stat"><div class="value">${h.streak||0}</div><div class="label">رکورد فعلی</div></div>
                        <div class="habit-stat"><div class="value">${h.frequency==='daily'?'روزانه':h.frequency==='weekly'?'هفتگی':'ماهانه'}</div><div class="label">تکرار</div></div>
                    </div>
                    <div class="habit-actions">
                        <button class="btn btn-check ${ct?'completed':''}" onclick="toggleHabit('${h.id}')"><i class="fas ${ct?'fa-times':'fa-check'}"></i>${ct?'انجام شد':'ثبت انجام'}</button>
                        <button class="btn btn-edit" onclick="openEditModal('${h.id}')"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-delete" onclick="deleteHabit('${h.id}')"><i class="fas fa-trash"></i></button>
                    </div>
                </div>`;
            }).join('');
        }
        
        function updateStats(){
            const t=habits.length,
                  c=habits.filter(h=>h.completed_today).length,
                  b=Math.max(...habits.map(h=>h.streak||0),0),
                  r=t>0?Math.round((c/t)*100):0;
            document.getElementById('totalHabits').textContent=t;
            document.getElementById('completedToday').textContent=c;
            document.getElementById('bestStreak').textContent=b;
            document.getElementById('completionRate').textContent=r+'%';
        }
        
        async function toggleHabitOnDate(id,date){
            if(date!==todayJalali){
                showToast('فقط ثبت برای امروز امکان‌پذیر است','error');
                return;
            }
            await toggleHabit(id);
        }
        
        async function toggleHabit(id){
            let fd=new FormData();
            fd.append('action','toggle_habit');
            fd.append('id',id);
            let r=await fetch(window.location.href,{method:'POST',body:fd});
            let res=await r.json();
            if(res.success){
                habits=res.habits;
                weekDates=res.weekDates||[];
                weeklyData=res.weeklyData||{};
                renderHabits();
                updateStats();
                renderWeekDays();
                renderWeeklyData();
                const h=habits.find(x=>x.id==id);
                showToast(h&&h.completed_today?'عالیه! عادت امروز ثبت شد 🎉':'عادت از حالت انجام خارج شد',h&&h.completed_today?'success':'error');
            }
        }
        
        async function deleteHabit(id){
            if(!confirm('آیا از حذف این عادت مطمئن هستید؟'))return;
            let fd=new FormData();
            fd.append('action','delete_habit');
            fd.append('id',id);
            let r=await fetch(window.location.href,{method:'POST',body:fd});
            let res=await r.json();
            if(res.success){
                habits=res.habits;
                renderHabits();
                updateStats();
                renderWeekDays();
                renderWeeklyData();
                showToast('عادت با موفقیت حذف شد','success');
            }
        }
        
        function openAddModal(){
            selectedIcon='fa-fire';
            document.querySelectorAll('.icon-option').forEach(o=>o.classList.remove('selected'));
            document.querySelector('.icon-option[data-icon="fa-fire"]').classList.add('selected');
            document.getElementById('modalTitle').innerHTML='<i class="fas fa-plus"></i> افزودن عادت جدید';
            document.getElementById('editId').value='';
            document.getElementById('habitName').value='';
            document.getElementById('habitDescription').value='';
            document.getElementById('habitFrequency').value='daily';
            document.getElementById('habitTarget').value='1';
            document.getElementById('saveBtn').textContent='افزودن';
            document.getElementById('habitModal').classList.add('active');
        }
        
        function openEditModal(id){
            const h=habits.find(x=>x.id==id);
            if(!h)return;
            selectedIcon=h.icon||'fa-fire';
            document.querySelectorAll('.icon-option').forEach(o=>o.classList.toggle('selected',o.dataset.icon===selectedIcon));
            document.getElementById('modalTitle').innerHTML='<i class="fas fa-edit"></i> ویرایش عادت';
            document.getElementById('editId').value=id;
            document.getElementById('habitName').value=h.name;
            document.getElementById('habitDescription').value=h.description||'';
            document.getElementById('habitFrequency').value=h.frequency||'daily';
            document.getElementById('habitTarget').value=h.target||1;
            document.getElementById('saveBtn').textContent='ذخیره تغییرات';
            document.getElementById('habitModal').classList.add('active');
        }
        
        function closeModal(){
            document.getElementById('habitModal').classList.remove('active');
        }
        
        async function saveHabit(){
            const name=document.getElementById('habitName').value.trim();
            if(!name){
                showToast('لطفاً نام عادت را وارد کنید','error');
                return;
            }
            const eid=document.getElementById('editId').value,
                  act=eid?'edit_habit':'add_habit';
            let fd=new FormData();
            fd.append('action',act);
            if(eid)fd.append('id',eid);
            fd.append('name',name);
            fd.append('description',document.getElementById('habitDescription').value);
            fd.append('frequency',document.getElementById('habitFrequency').value);
            fd.append('target',document.getElementById('habitTarget').value);
            fd.append('icon',selectedIcon);
            let r=await fetch(window.location.href,{method:'POST',body:fd});
            let res=await r.json();
            if(res.success){
                habits=res.habits;
                renderHabits();
                updateStats();
                loadWeeklyData();
                closeModal();
                showToast(eid?'عادت با موفقیت ویرایش شد':'عادت با موفقیت افزوده شد','success');
            }else{
                showToast('خطا در ذخیره عادت','error');
            }
        }
        
        document.getElementById('iconSelector').addEventListener('click',function(e){
            const o=e.target.closest('.icon-option');
            if(o){
                document.querySelectorAll('.icon-option').forEach(x=>x.classList.remove('selected'));
                o.classList.add('selected');
                selectedIcon=o.dataset.icon;
            }
        });
        
        document.getElementById('habitModal').addEventListener('click',function(e){
            if(e.target===this)closeModal();
        });
        
        function escapeHtml(t){
            if(!t)return'';
            let d=document.createElement('div');
            d.textContent=t;
            return d.innerHTML;
        }
        
        loadHabits();
    </script>
</body>
</html>
