<?php
// health/index.php - صفحه اصلی ماژول سلامت
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

    // مرتب‌سازی بر اساس تاریخ شروع
    usort($cycles, function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
    });

    $lastCycle = end($cycles);
    $avgCycleLength = 28; // پیش‌فرض

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

// ==================== پردازش درخواست‌های AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];

    if ($action === 'load') {
        $cycles = getUserCycles($userId);
        $symptoms = getUserSymptoms($userId);
        $prediction = calculateNextPeriod($cycles);
        $phase = getCyclePhase($cycles);

        $response = [
            'success' => true,
            'cycles' => $cycles,
            'symptoms' => $symptoms,
            'prediction' => $prediction,
            'phase' => $phase
        ];
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

        $response = [
            'success' => true,
            'cycles' => $cycles,
            'prediction' => $prediction,
            'phase' => $phase
        ];
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
    elseif ($action === 'delete_cycle') {
        $cycleId = $_POST['id'] ?? '';
        $cycles = getUserCycles($userId);
        $cycles = array_values(array_filter($cycles, function($c) use ($cycleId) {
            return $c['id'] != $cycleId;
        }));
        saveUserCycles($userId, $cycles);

        $prediction = calculateNextPeriod($cycles);
        $phase = getCyclePhase($cycles);

        $response = [
            'success' => true,
            'cycles' => $cycles,
            'prediction' => $prediction,
            'phase' => $phase
        ];
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

// ==================== دریافت دیتا برای نمایش ====================
$cycles = getUserCycles($userId);
$symptoms = getUserSymptoms($userId);
$prediction = calculateNextPeriod($cycles);
$phase = getCyclePhase($cycles);

$page_title = 'سلامت زنان';
include_once __DIR__ . '/../includes/header.php';
?>

<!-- ===== استایل‌های سلامت ===== -->
<link rel="stylesheet" href="style.css">

<!-- ===== محتوای اختصاصی ===== -->
<?php include 'template.php'; ?>

<script>
// متغیرهای اولیه از PHP
const userId = '<?php echo $userId; ?>';
const initialCycles = <?php echo json_encode($cycles); ?>;
const initialSymptoms = <?php echo json_encode($symptoms); ?>;
const prediction = <?php echo json_encode($prediction); ?>;
const currentPhase = '<?php echo $phase; ?>';

// اطمینان از لود شدن کامل DOM قبل از اجرای کد
document.addEventListener('DOMContentLoaded', function() {
    // شروع برنامه بعد از لود کامل صفحه
    if (typeof initHealthApp === 'function') {
        initHealthApp();
    }
});
</script>

<script src="script.js"></script>
</body>
</html>
