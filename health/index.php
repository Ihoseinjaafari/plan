<?php
// health/index.php - ماژول سلامت زنان با تقویم شمسی یکپارچه
session_start();
date_default_timezone_set('Asia/Tehran');

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
$cyclesFile = __DIR__ . '/cycles.json';
$symptomsFile = __DIR__ . '/symptoms.json';

if (!file_exists($cyclesFile)) {
    file_put_contents($cyclesFile, json_encode([]));
}
if (!file_exists($symptomsFile)) {
    file_put_contents($symptomsFile, json_encode([]));
}

// ==================== توابع ====================
function getUserCycles($userId) {
    global $cyclesFile;
    $allData = json_decode(file_get_contents($cyclesFile), true);
    if (!is_array($allData)) return [];
    return array_values(array_filter($allData, function($c) use ($userId) {
        return ($c['user_id'] ?? '') == $userId;
    }));
}

function saveUserCycles($userId, $cycles) {
    global $cyclesFile;
    $allData = json_decode(file_get_contents($cyclesFile), true);
    if (!is_array($allData)) $allData = [];
    $allData = array_values(array_filter($allData, function($c) use ($userId) {
        return ($c['user_id'] ?? '') != $userId;
    }));
    foreach ($cycles as &$cycle) {
        $cycle['user_id'] = $userId;
    }
    $allData = array_merge($allData, $cycles);
    file_put_contents($cyclesFile, json_encode($allData, JSON_PRETTY_PRINT));
}

function getUserSymptoms($userId) {
    global $symptomsFile;
    $allData = json_decode(file_get_contents($symptomsFile), true);
    if (!is_array($allData)) return [];
    return array_values(array_filter($allData, function($s) use ($userId) {
        return ($s['user_id'] ?? '') == $userId;
    }));
}

function saveUserSymptoms($userId, $symptoms) {
    global $symptomsFile;
    $allData = json_decode(file_get_contents($symptomsFile), true);
    if (!is_array($allData)) $allData = [];
    $allData = array_values(array_filter($allData, function($s) use ($userId) {
        return ($s['user_id'] ?? '') != $userId;
    }));
    foreach ($symptoms as &$symptom) {
        $symptom['user_id'] = $userId;
    }
    $allData = array_merge($allData, $symptoms);
    file_put_contents($symptomsFile, json_encode($allData, JSON_PRETTY_PRINT));
}

function calculateNextPeriod($cycles) {
    if (empty($cycles)) return null;
    usort($cycles, function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
    });
    $lastCycle = end($cycles);
    $avgCycleLength = 28;
    if (count($cycles) >= 2) {
        $totalDays = 0;
        for ($i = 1; $i < count($cycles); $i++) {
            $prev = strtotime($cycles[$i-1]['start_date']);
            $curr = strtotime($cycles[$i]['start_date']);
            $totalDays += ($curr - $prev) / (60 * 60 * 24);
        }
        $avgCycleLength = round($totalDays / (count($cycles) - 1));
    }
    $lastStart = strtotime($lastCycle['start_date']);
    $nextStart = $lastStart + ($avgCycleLength * 24 * 60 * 60);
    $ovulationDate = $nextStart - (14 * 24 * 60 * 60);
    $fertileStart = $ovulationDate - (5 * 24 * 60 * 60);
    $fertileEnd = $ovulationDate + (1 * 24 * 60 * 60);
    return [
        'avg_cycle_length' => $avgCycleLength,
        'next_period_start' => date('Y-m-d', $nextStart),
        'next_period_end' => date('Y-m-d', $nextStart + (5 * 24 * 60 * 60)),
        'ovulation_date' => date('Y-m-d', $ovulationDate),
        'fertile_window_start' => date('Y-m-d', $fertileStart),
        'fertile_window_end' => date('Y-m-d', $fertileEnd),
        'days_until_next' => max(0, ceil(($nextStart - time()) / (60 * 60 * 24)))
    ];
}

function predictFutureCycles($cycles, $count = 12) {
    if (empty($cycles)) return [];
    $prediction = calculateNextPeriod($cycles);
    if (!$prediction) return [];
    
    // محاسبه شدت خونریزی پیش‌فرض بر اساس میانگین سیکل‌های قبلی
    $defaultFlow = 'medium';
    if (!empty($cycles)) {
        $flowCounts = ['light' => 0, 'medium' => 0, 'heavy' => 0];
        foreach ($cycles as $cycle) {
            $flow = $cycle['flow'] ?? 'medium';
            if (isset($flowCounts[$flow])) {
                $flowCounts[$flow]++;
            }
        }
        $defaultFlow = array_keys($flowCounts, max($flowCounts))[0];
    }
    
    $futureCycles = [];
    $currentStart = $prediction['next_period_start'];
    $avgLength = $prediction['avg_cycle_length'];
    
    for ($i = 0; $i < $count; $i++) {
        $start = new DateTime($currentStart);
        $end = clone $start;
        $end->modify('+4 days');
        
        $futureCycles[] = [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'is_predicted' => true,
            'cycle_number' => $i + 1,
            'flow' => $defaultFlow
        ];
        
        $currentStart = date('Y-m-d', strtotime($currentStart . ' + ' . $avgLength . ' days'));
    }
    
    return $futureCycles;
}

function getCyclePhase($cycles) {
    if (empty($cycles)) return 'not_started';
    $lastCycle = end($cycles);
    $lastStart = strtotime($lastCycle['start_date']);
    $currentDate = time();
    $daysSinceStart = floor(($currentDate - $lastStart) / (60 * 60 * 24));
    if ($daysSinceStart <= 5) return 'menstruation';
    if ($daysSinceStart <= 14) return 'follicular';
    if ($daysSinceStart <= 16) return 'ovulation';
    if ($daysSinceStart <= 28) return 'luteal';
    return 'pre_menstrual';
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
    $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 31) + 186);
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

// ==================== دریافت تاریخ فعلی شمسی ====================
list($gy, $gm, $gd) = explode('-', date('Y-m-d'));
list($current_jy, $current_jm, $current_jd) = gregorian_to_jalali($gy, $gm, $gd);
$todayJalali = $current_jy . '/' . $current_jm . '/' . $current_jd;
$todayDateStr = sprintf("%04d-%02d-%02d", $current_jy, $current_jm, $current_jd);

// ==================== پارامترهای تقویم (پشتیبانی از سال‌های مختلف) ====================
$jy = isset($_GET['y']) ? (int)$_GET['y'] : $current_jy;
$jm = isset($_GET['m']) ? (int)$_GET['m'] : $current_jm;

if ($jm < 1) { $jm = 12; $jy--; }
if ($jm > 12) { $jm = 1; $jy++; }

// محدودیت سال (برای نمایش بهتر)
if ($jy < 1300) $jy = 1300;
if ($jy > 1450) $jy = 1450;

$month_names = [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',
                7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];
$day_names = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

// ==================== پردازش AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];

    if ($action === 'load') {
        $cycles = getUserCycles($userId);
        $symptoms = getUserSymptoms($userId);
        $prediction = calculateNextPeriod($cycles);
        $phase = getCyclePhase($cycles);
        $futureCycles = predictFutureCycles($cycles);
        $response = ['success' => true, 'cycles' => $cycles, 'symptoms' => $symptoms, 'prediction' => $prediction, 'phase' => $phase, 'future_cycles' => $futureCycles];
    }
    elseif ($action === 'add_cycle') {
        $cycles = getUserCycles($userId);
        $newCycle = [
            'id' => time() . rand(100, 999),
            'start_date' => $_POST['start_date'] ?? date('Y-m-d'),
            'end_date' => $_POST['end_date'] ?? '',
            'flow' => $_POST['flow'] ?? 'medium',
            'notes' => htmlspecialchars(trim($_POST['notes'] ?? '')),
            'created_at' => date('Y-m-d H:i:s')
        ];
        $cycles[] = $newCycle;
        saveUserCycles($userId, $cycles);
        $prediction = calculateNextPeriod($cycles);
        $phase = getCyclePhase($cycles);
        $futureCycles = predictFutureCycles($cycles);
        $response = ['success' => true, 'cycles' => $cycles, 'prediction' => $prediction, 'phase' => $phase, 'future_cycles' => $futureCycles];
    }
    elseif ($action === 'edit_cycle') {
        $cycleId = $_POST['id'] ?? '';
        $cycles = getUserCycles($userId);
        foreach ($cycles as &$cycle) {
            if ($cycle['id'] == $cycleId) {
                $cycle['start_date'] = $_POST['start_date'] ?? $cycle['start_date'];
                $cycle['end_date'] = $_POST['end_date'] ?? $cycle['end_date'];
                $cycle['flow'] = $_POST['flow'] ?? $cycle['flow'];
                $cycle['notes'] = htmlspecialchars(trim($_POST['notes'] ?? ''));
                break;
            }
        }
        saveUserCycles($userId, $cycles);
        $prediction = calculateNextPeriod($cycles);
        $phase = getCyclePhase($cycles);
        $futureCycles = predictFutureCycles($cycles);
        $response = ['success' => true, 'cycles' => $cycles, 'prediction' => $prediction, 'phase' => $phase, 'future_cycles' => $futureCycles];
    }
    elseif ($action === 'add_symptom') {
        $symptoms = getUserSymptoms($userId);
        $newSymptom = [
            'id' => time() . rand(100, 999),
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'type' => $_POST['type'] ?? 'other',
            'severity' => $_POST['severity'] ?? 'medium',
            'notes' => htmlspecialchars(trim($_POST['notes'] ?? '')),
            'created_at' => date('Y-m-d H:i:s')
        ];
        $symptoms[] = $newSymptom;
        saveUserSymptoms($userId, $symptoms);
        $response = ['success' => true, 'symptoms' => $symptoms];
    }
    elseif ($action === 'edit_symptom') {
        $symptomId = $_POST['id'] ?? '';
        $symptoms = getUserSymptoms($userId);
        foreach ($symptoms as &$symptom) {
            if ($symptom['id'] == $symptomId) {
                $symptom['date'] = $_POST['date'] ?? $symptom['date'];
                $symptom['type'] = $_POST['type'] ?? $symptom['type'];
                $symptom['severity'] = $_POST['severity'] ?? $symptom['severity'];
                $symptom['notes'] = htmlspecialchars(trim($_POST['notes'] ?? ''));
                break;
            }
        }
        saveUserSymptoms($userId, $symptoms);
        $response = ['success' => true, 'symptoms' => $symptoms];
    }
    elseif ($action === 'delete_cycle') {
        $cycleId = $_POST['id'] ?? '';
        $cycles = getUserCycles($userId);
        $cycles = array_values(array_filter($cycles, function($c) use ($cycleId) {
            return $c['id'] != $cycleId;
        }));
        saveUserCycles($userId, $cycles);
        $prediction = calculateNextPeriod($cycles);
        $phase = getCyclePhase($cycles);
        $futureCycles = predictFutureCycles($cycles);
        $response = ['success' => true, 'cycles' => $cycles, 'prediction' => $prediction, 'phase' => $phase, 'future_cycles' => $futureCycles];
    }
    elseif ($action === 'delete_symptom') {
        $symptomId = $_POST['id'] ?? '';
        $symptoms = getUserSymptoms($userId);
        $symptoms = array_values(array_filter($symptoms, function($s) use ($symptomId) {
            return $s['id'] != $symptomId;
        }));
        saveUserSymptoms($userId, $symptoms);
        $response = ['success' => true, 'symptoms' => $symptoms];
    }

    echo json_encode($response);
    exit;
}

// ==================== دریافت دیتا ====================
$cycles = getUserCycles($userId);
$symptoms = getUserSymptoms($userId);
$prediction = calculateNextPeriod($cycles);
$phase = getCyclePhase($cycles);
$futureCycles = predictFutureCycles($cycles);
$page_title = 'سلامت زنان';
include_once __DIR__ . '/../includes/header.php';
?>

<!-- ===== استایل‌های سلامت ===== -->
<link rel="stylesheet" href="style.css">

<!-- ===== HTML ===== -->
<div class="health-wrap">
    <!-- Header -->
    <div class="health-header">
        <h1>🌸 سلامت زنان</h1>
        <p class="subtitle">ثبت و پیگیری چرخه قاعدگی، علائم و پیش‌بینی‌ها</p>
    </div>

    <!-- Status Card -->
    <div class="status-card" id="statusCard">
        <div class="phase-box">
            <span id="phaseIcon">🩸</span>
            <span id="phaseText">در حال بارگذاری...</span>
        </div>
        <div class="status-stats">
            <div><span class="label">روز چرخه</span><span class="value" id="cycleDay">-</span></div>
            <div><span class="label">روزهای باقی‌مانده</span><span class="value" id="daysRemaining">-</span></div>
            <div><span class="label">تاریخ بعدی</span><span class="value" id="nextPeriod">-</span></div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-box"><span class="num" id="statCycles">0</span><span class="lbl">سیکل‌ها</span></div>
        <div class="stat-box"><span class="num" id="statSymptoms">0</span><span class="lbl">علائم</span></div>
        <div class="stat-box"><span class="num" id="statAvgCycle">-</span><span class="lbl">میانگین سیکل</span></div>
    </div>

    <!-- ===== تب‌ها ===== -->
    <div class="health-tabs">
        <button class="tab-btn active" data-tab="calendar">📅 تقویم</button>
        <button class="tab-btn" data-tab="cycles">🔄 سیکل‌ها</button>
        <button class="tab-btn" data-tab="symptoms">💊 علائم</button>
        <button class="tab-btn" data-tab="predict">🔮 پیش‌بینی</button>
    </div>

    <!-- ===== تب تقویم ===== -->
    <div class="tab-panel active" id="panel-calendar">
        <!-- تقویم شمسی -->
        <div class="calendar-wrapper">
            <div class="calendar-nav">
                <?php
                $prev_y = ($jm == 1) ? $jy - 1 : $jy;
                $prev_m = ($jm == 1) ? 12 : $jm - 1;
                $next_y = ($jm == 12) ? $jy + 1 : $jy;
                $next_m = ($jm == 12) ? 1 : $jm + 1;
                ?>
                <a href="?y=<?= $prev_y ?>&m=<?= $prev_m ?>" class="nav-arrow" title="ماه قبل">‹</a>
                <span class="month-title"><?= $month_names[$jm] ?> <?= $jy ?></span>
                <a href="?y=<?= $next_y ?>&m=<?= $next_m ?>" class="nav-arrow" title="ماه بعد">›</a>
                <a href="?y=<?= $current_jy ?>&m=<?= $current_jm ?>" class="today-link">امروز</a>
            </div>

            <div class="calendar-grid" id="calendarGrid">
                <?php foreach ($day_names as $name): ?>
                    <div class="cal-header"><?= $name ?></div>
                <?php endforeach; ?>

                <?php
                list($first_gy, $first_gm, $first_gd) = jalali_to_gregorian($jy, $jm, 1);
                $first_weekday = (int)date('w', mktime(0, 0, 0, $first_gm, $first_gd, $first_gy));
                $weekday_start = ($first_weekday + 1) % 7;
                $days_in_month = ($jm <= 6) ? 31 : (($jm < 12) ? 30 : (($jy % 4 == 3) ? 30 : 29));

                for ($i = 0; $i < $weekday_start; $i++) {
                    echo '<div class="cal-day empty"></div>';
                }

                for ($day = 1; $day <= $days_in_month; $day++) {
                    $is_today = ($jy == $current_jy && $jm == $current_jm && $day == $current_jd);
                    $dateStr = sprintf("%04d-%02d-%02d", $jy, $jm, $day);
                    
                    // تبدیل تاریخ شمسی به میلادی برای مقایسه با داده‌ها
                    list($day_gy, $day_gm, $day_gd) = jalali_to_gregorian($jy, $jm, $day);
                    $gregDateStr = sprintf("%04d-%02d-%02d", $day_gy, $day_gm, $day_gd);
                    
                    // بررسی سیکل‌های واقعی
                    $hasCycle = false;
                    $cycleFlow = '';
                    foreach ($cycles as $cycle) {
                        $start = new DateTime($cycle['start_date']);
                        $end = $cycle['end_date'] ? new DateTime($cycle['end_date']) : (clone $start)->modify('+5 days');
                        $current = new DateTime($gregDateStr);
                        if ($current >= $start && $current <= $end) {
                            $hasCycle = true;
                            $cycleFlow = $cycle['flow'] ?? 'medium';
                            break;
                        }
                    }
                    
                    // بررسی سیکل‌های پیش‌بینی شده
                    $isPredicted = false;
                    $predictedNumber = 0;
                    $predictedFlow = 'medium'; // پیش‌فرض برای پیش‌بینی
                    foreach ($futureCycles as $future) {
                        $start = new DateTime($future['start_date']);
                        $end = new DateTime($future['end_date']);
                        $current = new DateTime($gregDateStr);
                        if ($current >= $start && $current <= $end) {
                            $isPredicted = true;
                            $predictedNumber = $future['cycle_number'] ?? 0;
                            $predictedFlow = $future['flow'] ?? 'medium';
                            break;
                        }
                    }
                    
                    // بررسی علائم ثبت شده برای این روز
                    $hasSymptoms = false;
                    foreach ($symptoms as $symptom) {
                        if ($symptom['date'] === $gregDateStr) {
                            $hasSymptoms = true;
                            break;
                        }
                    }
                    
                    // محاسبه فاز چرخه برای این روز
                    $phaseClass = '';
                    $daysSinceStart = -1;
                    $cycleDay = -1;
                    $isInPeriod = false;
                    
                    // تبدیل تاریخ جاری به timestamp برای مقایسه
                    $currentTs = strtotime($gregDateStr);
                    
                    // 1. بررسی سیکل‌های واقعی ثبت‌شده
                    if (!empty($cycles)) {
                        // مرتب‌سازی بر اساس تاریخ شروع (قدیمی‌ترین اول)
                        usort($cycles, function($a, $b) {
                            return strtotime($a['start_date']) - strtotime($b['start_date']);
                        });
                        
                        // پیدا کردن آخرین سیکلی که قبل از این تاریخ شروع شده
                        $relevantCycle = null;
                        foreach ($cycles as $cycle) {
                            $startTs = strtotime($cycle['start_date']);
                            if ($startTs <= $currentTs) {
                                $relevantCycle = $cycle;
                            } else {
                                break; // چون مرتب شده‌اند، بقیه آینده هستند
                            }
                        }
                        
                        if ($relevantCycle) {
                            $startTs = strtotime($relevantCycle['start_date']);
                            $endTs = $relevantCycle['end_date'] ? strtotime($relevantCycle['end_date']) : null;
                            $daysSinceStart = floor(($currentTs - $startTs) / 86400);
                            $cycleDay = $daysSinceStart + 1;
                            
                            // بررسی آیا در روزهای خونریزی هستیم؟
                            if ($endTs !== null && $currentTs >= $startTs && $currentTs <= $endTs) {
                                // در بازه خونریزی ثبت‌شده
                                $isInPeriod = true;
                                $phaseClass = 'phase-menstruation';
                            } else {
                                // بعد از پایان خونریزی - تعیین فاز بر اساس روز چرخه
                                // طول دوره خونریزی واقعی
                                $periodLength = $endTs ? floor(($endTs - $startTs) / 86400) + 1 : 5;
                                
                                if ($cycleDay <= $periodLength && $currentTs >= $startTs) {
                                    // در بازه قاعدگی (برای وقتی که end_date ثبت نشده)
                                    $phaseClass = 'phase-menstruation';
                                } elseif ($cycleDay > $periodLength && $cycleDay <= 13) {
                                    // فاز فولیکولی: بعد از قاعدگی تا روز 13
                                    $phaseClass = 'phase-follicular';
                                } elseif ($cycleDay >= 14 && $cycleDay <= 16) {
                                    // فاز تخمک‌گذاری: روز 14 تا 16
                                    $phaseClass = 'phase-ovulation';
                                } elseif ($cycleDay >= 17 && $cycleDay <= 28) {
                                    // فاز لوتئال: روز 17 تا 28
                                    $phaseClass = 'phase-luteal';
                                } elseif ($cycleDay > 28) {
                                    // بعد از روز 28 - شروع چرخه جدید (هنوز پریود بعدی شروع نشده)
                                    $phaseClass = 'phase-luteal';
                                }
                            }
                        }
                    }
                    
                    // اگر سیکل واقعی نیست و پیش‌بینی است
                    if (empty($phaseClass) && !empty($futureCycles)) {
                        foreach ($futureCycles as $future) {
                            $startTs = strtotime($future['start_date']);
                            $endTs = strtotime($future['end_date']);
                            
                            if ($currentTs >= $startTs && $currentTs <= $endTs) {
                                // در بازه قاعدگی پیش‌بینی شده
                                $daysInFuture = floor(($currentTs - $startTs) / 86400);
                                $cycleDay = $daysInFuture + 1;
                                $phaseClass = 'phase-menstruation';
                                break;
                            } elseif ($currentTs > $endTs) {
                                // بعد از پایان قاعدگی پیش‌بینی شده - تعیین فاز
                                $startTs = strtotime($future['start_date']);
                                $daysInFuture = floor(($currentTs - $startTs) / 86400);
                                $cycleDay = $daysInFuture + 1;
                                
                                // طول دوره قاعدگی پیش‌بینی شده (معمولاً 5 روز)
                                $periodLength = floor(($endTs - $startTs) / 86400) + 1;
                                
                                if ($cycleDay > $periodLength && $cycleDay <= 13) {
                                    $phaseClass = 'phase-follicular';
                                } elseif ($cycleDay >= 14 && $cycleDay <= 16) {
                                    $phaseClass = 'phase-ovulation';
                                } elseif ($cycleDay >= 17 && $cycleDay <= 28) {
                                    $phaseClass = 'phase-luteal';
                                }
                                break;
                            }
                        }
                    }
                    
                    $class = 'cal-day';
                    if ($is_today) $class .= ' today';
                    if ($phaseClass) {
                        $class .= ' ' . $phaseClass;
                        if ($isPredicted) {
                            $class .= ' predicted';
                        }
                    }
                    if ($hasSymptoms && !$phaseClass) {
                        $class .= ' has-symptoms';
                    }
                    
                    echo '<div class="' . $class . '" data-date="' . $gregDateStr . '" onclick="selectDate(\'' . $gregDateStr . '\')">';
                    echo '<span class="day-number">' . $day . '</span>';
                    if ($phaseClass) {
                        echo '<span class="phase-dot">●</span>';
                    } elseif ($hasSymptoms) {
                        echo '<span class="symptom-dot">◆</span>';
                    }
                    echo '</div>';
                }

                $remaining = (7 - (($weekday_start + $days_in_month) % 7)) % 7;
                for ($i = 0; $i < $remaining; $i++) {
                    echo '<div class="cal-day empty"></div>';
                }
                ?>
            </div>
            
            <!-- راهنما -->
            <div class="calendar-legend">
                <span class="legend-item"><span class="phase-indicator phase-menstruation"></span> قاعدگی</span>
                <span class="legend-item"><span class="phase-indicator phase-follicular"></span> فولیکولی</span>
                <span class="legend-item"><span class="phase-indicator phase-ovulation"></span> تخمک‌گذاری</span>
                <span class="legend-item"><span class="phase-indicator phase-luteal"></span> لوتئال</span>
                <span class="legend-item"><span class="legend-dot symptom-dot">◆</span> دارای علامت</span>
                <span class="legend-item"><span class="legend-dot today-dot">★</span> امروز</span>
                <span class="legend-item" style="opacity: 0.6;">(رنگ‌های کمرنگ = پیش‌بینی)</span>
            </div>
        </div>

        <!-- اطلاعات روز (نمایش علائم و رویدادها) -->
        <div class="day-info" id="dayInfo">
            <div class="day-info-header">
                <span id="selectedDateDisplay">📅 روزی را انتخاب کنید</span>
                <button class="btn-add-small" onclick="openAddCycleModal()">➕ ثبت سیکل</button>
                <button class="btn-add-small" onclick="openAddSymptomModal()">💊 ثبت علامت</button>
            </div>
            <div id="dayDetails">
                <p class="empty">روزی را روی تقویم انتخاب کنید تا علائم و رویدادهای آن روز را ببینید</p>
            </div>
        </div>
    </div>

    <!-- ===== تب سیکل‌ها ===== -->
    <div class="tab-panel" id="panel-cycles">
        <div class="panel-header">
            <h2>📋 تاریخچه سیکل‌ها</h2>
            <button class="btn-add" onclick="openAddCycleModal()">➕ ثبت سیکل جدید</button>
        </div>
        <div id="cyclesList" class="items-list">
            <p class="empty">هیچ سیکلی ثبت نشده است</p>
        </div>
    </div>

    <!-- ===== تب علائم ===== -->
    <div class="tab-panel" id="panel-symptoms">
        <div class="panel-header">
            <h2>📋 تاریخچه علائم</h2>
            <button class="btn-add" onclick="openAddSymptomModal()">➕ ثبت علامت جدید</button>
        </div>
        <div id="symptomsList" class="items-list">
            <p class="empty">هیچ علامتی ثبت نشده است</p>
        </div>
    </div>

    <!-- ===== تب پیش‌بینی ===== -->
    <div class="tab-panel" id="panel-predict">
        <div class="panel-header"><h2>🔮 پیش‌بینی‌ها</h2></div>
        <div class="predict-grid">
            <div class="pcard"><div class="pl">میانگین سیکل</div><div class="pv" id="pAvg">-</div></div>
            <div class="pcard"><div class="pl">شروع بعدی</div><div class="pv" id="pNext">-</div></div>
            <div class="pcard"><div class="pl">تخمک‌گذاری</div><div class="pv" id="pOvulation">-</div></div>
            <div class="pcard"><div class="pl">پنجره باروری</div><div class="pv" id="pFertile">-</div></div>
        </div>
        <div class="note">💡 برای پیش‌بینی دقیق‌تر، حداقل ۳ سیکل کامل ثبت کنید</div>
    </div>
</div>

<!-- ===== مودال ثبت/ویرایش سیکل ===== -->
<div id="cycleModal" class="modal" style="display:none;">
    <div class="modal-box modal-sheet-new">
        <div class="modal-header-new">
            <h3 id="cycleModalTitle">📅 ثبت سیکل جدید</h3>
            <button class="close-btn-new" onclick="closeCycleModal()">&times;</button>
        </div>
        <div class="modal-body-new">
            <input type="hidden" id="editCycleId">
            
            <div class="form-group-new">
                <label class="form-label">تاریخ شروع قاعدگی</label>
                <div class="date-input-wrapper">
                    <input type="text" id="cycleStartDate" class="form-input-date" placeholder="انتخاب تاریخ" readonly>
                    <button type="button" class="btn-calendar-toggle" id="toggleCycleStartCal">
                        <i class="far fa-calendar-alt"></i>
                    </button>
                </div>
                <div class="calendar-dropdown" id="cycleDateCalendar"></div>
            </div>

            <div class="form-group-new">
                <label class="form-label">تاریخ پایان (اختیاری)</label>
                <div class="date-input-wrapper">
                    <input type="text" id="cycleEndDate" class="form-input-date" placeholder="انتخاب تاریخ" readonly>
                    <button type="button" class="btn-calendar-toggle" id="toggleCycleEndCal">
                        <i class="far fa-calendar-alt"></i>
                    </button>
                </div>
                <div class="calendar-dropdown" id="cycleEndCalendar"></div>
            </div>

            <div class="form-group-new">
                <label class="form-label">مدت خونریزی (روز)</label>
                <input type="number" id="cycleDurationInput" class="form-input" min="2" max="10" value="5">
            </div>

            <div class="form-group-new">
                <label class="form-label">شدت خونریزی</label>
                <select id="cycleFlow" class="form-select">
                    <option value="light">🟢 کم (Light)</option>
                    <option value="medium" selected>🟡 متوسط (Medium)</option>
                    <option value="heavy">🔴 زیاد (Heavy)</option>
                </select>
            </div>

            <div class="form-group-new">
                <label class="form-label">یادداشت</label>
                <textarea id="cycleNotes" class="form-textarea" rows="2" placeholder="یادداشت خود را بنویسید..."></textarea>
            </div>
        </div>
        <div class="modal-footer-new">
            <button class="btn-cancel-new" onclick="closeCycleModal()">انصراف</button>
            <button class="btn-save-new" id="saveCycleBtn">ذخیره سیکل</button>
        </div>
    </div>
</div>

<!-- ===== مودال ثبت/ویرایش علامت ===== -->
<div id="symptomModal" class="modal" style="display:none;">
    <div class="modal-box modal-sheet-new">
        <div class="modal-header-new">
            <h3 id="symptomModalTitle">💊 ثبت علامت جدید</h3>
            <button class="close-btn-new" onclick="closeSymptomModal()">&times;</button>
        </div>
        <div class="modal-body-new">
            <input type="hidden" id="editSymptomId">
            
            <div class="form-group-new">
                <label class="form-label">تاریخ ثبت علامت</label>
                <div class="date-input-wrapper">
                    <input type="text" id="symptomDate" class="form-input-date" placeholder="انتخاب تاریخ" readonly>
                    <button type="button" class="btn-calendar-toggle" id="toggleSymptomCal">
                        <i class="far fa-calendar-alt"></i>
                    </button>
                </div>
                <div class="calendar-dropdown" id="symptomDateCalendar"></div>
            </div>

            <div class="form-group-new">
                <label class="form-label">دسته‌بندی علامت</label>
                <select id="symptomType" class="form-select">
                    <option value="pain">😣 درد (Pain)</option>
                    <option value="mood">😊 خلق و خو (Mood)</option>
                    <option value="physical">💪 جسمی (Physical)</option>
                    <option value="digestive">🍽️ گوارشی (Digestive)</option>
                    <option value="other">📌 سایر (Other)</option>
                </select>
            </div>

            <div class="form-group-new">
                <label class="form-label">شدت علامت</label>
                <select id="symptomSeverity" class="form-select">
                    <option value="low">کم (Low)</option>
                    <option value="medium" selected>متوسط (Medium)</option>
                    <option value="high">زیاد (High)</option>
                </select>
            </div>

            <div class="form-group-new">
                <label class="form-label">یادداشت</label>
                <textarea id="symptomNotes" class="form-textarea" rows="2" placeholder="توضیحات بیشتر..."></textarea>
            </div>
        </div>
        <div class="modal-footer-new">
            <button class="btn-cancel-new" onclick="closeSymptomModal()">انصراف</button>
            <button class="btn-save-new" id="saveSymptomBtn">ذخیره علامت</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
// داده‌های اولیه از PHP
const userId = '<?php echo $userId; ?>';
const todayJalali = '<?php echo $todayJalali; ?>';
const currentJalaliDate = '<?php echo $todayDateStr; ?>';
let cycles = <?php echo json_encode($cycles); ?> || [];
let symptoms = <?php echo json_encode($symptoms); ?> || [];
let predictionData = <?php echo json_encode($prediction); ?> || null;
let futureCycles = <?php echo json_encode($futureCycles); ?> || [];
let currentPhase = '<?php echo $phase; ?>';
let selectedDate = null;
let datePickers = {};
</script>

<script src="script.js"></script>
</body>
</html>
