<?php
// planner/index.php - نسخه ماژولار
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

// ذخیره نام کاربر در سشن برای هدر
$_SESSION['user_name'] = $currentUser['name'];

// ==================== فایل‌های دیتا ====================
$dataFile = __DIR__ . '/../data/tasks.json';
$categoriesFile = __DIR__ . '/../data/categories.json';
$projectsFile = __DIR__ . '/../data/projects.json';
$settingsFile = __DIR__ . '/../data/settings.json';

if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));
if (!file_exists($categoriesFile)) {
    file_put_contents($categoriesFile, json_encode(['کار شخصی', 'کار اداری', 'یادگیری', 'ورزش', 'خرید']));
}
if (!file_exists($projectsFile)) {
    file_put_contents($projectsFile, json_encode([]));
}
if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode(['registration_enabled' => true], JSON_PRETTY_PRINT));
}

// ==================== توابع ====================
function getAllUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return [];
    $users = json_decode(file_get_contents($usersFile), true);
    return is_array($users) ? $users : [];
}

function getAllTasks() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $tasks = json_decode(file_get_contents($dataFile), true);
    return is_array($tasks) ? $tasks : [];
}

function saveAllTasks($tasks) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($tasks, JSON_PRETTY_PRINT));
}

function getUserTasks($userId) {
    $tasks = getAllTasks();
    return array_values(array_filter($tasks, function($task) use ($userId) {
        return ($task['user_id'] ?? '') == $userId;
    }));
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

function updateUserTask($userId, $taskId, $updatedData) {
    $tasks = getAllTasks();
    foreach ($tasks as &$task) {
        if ($task['id'] == $taskId && ($task['user_id'] ?? '') == $userId) {
            foreach ($updatedData as $key => $value) {
                $task[$key] = $value;
            }
            break;
        }
    }
    if (isset($updatedData['done'])) {
        $tasks = updateParentTaskStatus($taskId, $tasks);
    }
    saveAllTasks($tasks);
    return $tasks;
}

function deleteUserTask($userId, $taskId) {
    $tasks = getAllTasks();
    $tasks = array_values(array_filter($tasks, function($task) use ($userId, $taskId) {
        return !($task['id'] == $taskId && ($task['user_id'] ?? '') == $userId);
    }));
    saveAllTasks($tasks);
    return $tasks;
}

function deleteTaskWithChildren($userId, $taskId) {
    $tasks = getAllTasks();
    $toDelete = [$taskId];
    $found = true;
    while ($found) {
        $found = false;
        foreach ($tasks as $task) {
            if (in_array($task['parent_id'] ?? '', $toDelete) && !in_array($task['id'], $toDelete) && ($task['user_id'] ?? '') == $userId) {
                $toDelete[] = $task['id'];
                $found = true;
            }
        }
    }
    $tasks = array_values(array_filter($tasks, function($t) use ($toDelete) {
        return !in_array($t['id'], $toDelete);
    }));
    saveAllTasks($tasks);
    return $tasks;
}

function reorderUserTasks($userId, $ids) {
    $tasks = getAllTasks();
    $newTasks = [];
    foreach ($ids as $index => $id) {
        foreach ($tasks as $task) {
            if ($task['id'] == $id && ($task['user_id'] ?? '') == $userId) {
                $task['order'] = $index;
                $newTasks[] = $task;
                break;
            }
        }
    }
    foreach ($tasks as $task) {
        if (($task['user_id'] ?? '') != $userId) {
            $newTasks[] = $task;
        }
    }
    saveAllTasks($newTasks);
    return $newTasks;
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

function updateUserProjectDescription($userId, $projectName, $description) {
    $projects = getUserProjects($userId);
    foreach ($projects as &$p) {
        if ($p['name'] === $projectName) {
            $p['description'] = $description;
            break;
        }
    }
    saveUserProjects($userId, $projects);
}

// ==================== توابع تبدیل تاریخ شمسی ====================
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

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    $userId = $_SESSION['user_id'];
    
    if ($action === 'logout') {
        session_destroy();
        $response = ['success' => true];
    }
    elseif ($action === 'change_password') {
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) >= 4) {
            $users = getAllUsers();
            foreach ($users as &$user) {
                if ($user['id'] == $userId) {
                    $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    break;
                }
            }
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'رمز عبور باید حداقل ۴ کاراکتر باشد'];
        }
    }
    elseif ($action === 'add') {
        $tasks = getUserTasks($userId);
        $newId = time() . rand(100, 999);
        $newTask = [
            'id' => $newId,
            'user_id' => $userId,
            'title' => htmlspecialchars(trim($_POST['title'] ?? '')),
            'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
            'category' => $_POST['category'] ?? 'بدون دسته',
            'project' => $_POST['project'] ?? '',
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'time' => $_POST['time'] ?? '12:00',
            'priority' => $_POST['priority'] ?? 'medium',
            'order' => count($tasks),
            'done' => false,
            'parent_id' => $_POST['parent_id'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'completed_at' => null
        ];
        $allTasks = saveUserTask($userId, $newTask);
        if (!empty($newTask['parent_id'])) {
            $allTasks = updateParentTaskStatus($newTask['parent_id'], $allTasks);
            saveAllTasks($allTasks);
        }
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'toggle') {
        $task = null;
        $tasksList = getAllTasks();
        foreach ($tasksList as $t) {
            if ($t['id'] == $_POST['id'] && ($t['user_id'] ?? '') == $userId) {
                $task = $t;
                break;
            }
        }
        $currentDone = $task ? $task['done'] : false;
        $newDone = !$currentDone;
        $completedAt = $newDone ? date('Y-m-d H:i:s') : null;
        $allTasks = updateUserTask($userId, $_POST['id'], [
            'done' => $newDone,
            'completed_at' => $completedAt
        ]);
        $allTasks = updateParentTaskStatus($_POST['id'], $allTasks);
        saveAllTasks($allTasks);
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'delete') {
        $deleteChildren = isset($_POST['delete_children']) && $_POST['delete_children'] == 'true';
        if ($deleteChildren) {
            $allTasks = deleteTaskWithChildren($userId, $_POST['id']);
        } else {
            $allTasks = deleteUserTask($userId, $_POST['id']);
        }
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'reorder') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        $allTasks = reorderUserTasks($userId, $ids);
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'edit') {
        $updateData = [];
        if (isset($_POST['title'])) $updateData['title'] = htmlspecialchars(trim($_POST['title']));
        if (isset($_POST['description'])) $updateData['description'] = htmlspecialchars(trim($_POST['description']));
        if (isset($_POST['category'])) $updateData['category'] = $_POST['category'];
        if (isset($_POST['project'])) $updateData['project'] = $_POST['project'];
        if (isset($_POST['date'])) $updateData['date'] = $_POST['date'];
        if (isset($_POST['time'])) $updateData['time'] = $_POST['time'];
        if (isset($_POST['priority'])) $updateData['priority'] = $_POST['priority'];
        if (isset($_POST['parent_id'])) $updateData['parent_id'] = $_POST['parent_id'];
        $allTasks = updateUserTask($userId, $_POST['id'], $updateData);
        saveAllTasks($allTasks);
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'load') {
        $tasks = getUserTasks($userId);
        $allTasks = getAllTasks();
        foreach ($tasks as &$task) {
            $progress = calculateTaskProgress($task['id'], $allTasks);
            if ($progress !== null) {
                $task['_progress'] = $progress;
            }
        }
        $response = [
            'success' => true,
            'tasks' => $tasks,
            'categories' => getCategories(),
            'projects' => getUserProjects($userId),
            'user' => getUserById($userId)
        ];
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
    elseif ($action === 'update_project_description') {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        if ($name) {
            updateUserProjectDescription($userId, $name, $description);
            $response = ['success' => true, 'projects' => getUserProjects($userId)];
        } else {
            $response = ['success' => false];
        }
    }
    elseif ($action === 'export_csv') {
        $tasks = getUserTasks($userId);
        $csv = "عنوان,توضیحات,دسته‌بندی,پروژه,تاریخ,زمان,اولویت,تسک مادر,وضعیت,تاریخ انجام,پیشرفت\n";
        foreach ($tasks as $task) {
            $status = $task['done'] ? 'انجام شده' : 'در انتظار';
            $completedAt = $task['completed_at'] ? date('Y-m-d H:i', strtotime($task['completed_at'])) : '';
            $parentTitle = '';
            foreach ($tasks as $p) {
                if ($p['id'] == ($task['parent_id'] ?? '')) {
                    $parentTitle = $p['title'];
                    break;
                }
            }
            $allTasks = getAllTasks();
            $progress = calculateTaskProgress($task['id'], $allTasks);
            $progressText = $progress !== null ? "{$progress['done']}/{$progress['total']} ({$progress['percent']}%)" : '—';
            $csv .= '"' . str_replace('"', '""', $task['title']) . '",';
            $csv .= '"' . str_replace('"', '""', $task['description'] ?? '') . '",';
            $csv .= '"' . $task['category'] . '",';
            $csv .= '"' . ($task['project'] ?? '') . '",';
            $csv .= '"' . $task['date'] . '",';
            $csv .= '"' . $task['time'] . '",';
            $csv .= '"' . $task['priority'] . '",';
            $csv .= '"' . $parentTitle . '",';
            $csv .= '"' . $status . '",';
            $csv .= '"' . $completedAt . '",';
            $csv .= '"' . $progressText . "\"\n";
        }
        $response = ['success' => true, 'data' => $csv];
    }

    echo json_encode($response);
    exit;
}

$currentDate = date('Y-m-d');
$todayTehran = date('Y-m-d');
$tomorrowTehran = date('Y-m-d', strtotime('+1 days'));
$userId = $_SESSION['user_id'];
$currentUser = getUserById($userId);
$page_title = 'برنامه‌ریز';

// تبدیل تاریخ امروز به شمسی برای نمایش
list($gy, $gm, $gd) = explode('-', $todayTehran);
list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
$todayJalali = $jy . '/' . $jm . '/' . $jd;
$month_names = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
$todayJalaliStr = $jy . ' ' . $month_names[(int)$jm] . ' ' . $jd;

// ==================== هدر یکپارچه ====================
include_once __DIR__ . '/../includes/header.php';
?>

    <!-- لینک به CSS اختصاصی -->
    <link rel="stylesheet" href="css/style.css">

<?php include_once __DIR__ . '/includes/nav.php'; ?>
<?php include_once __DIR__ . '/includes/main-content.php'; ?>
<?php include_once __DIR__ . '/includes/modals.php'; ?>

    <!-- اسکریپت‌های خارجی -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
    <!-- لینک به JS اختصاصی -->
    <script src="js/app.js"></script>
</body>
</html>
