<?php
// planner/index.php - نسخه کامل با آیکون‌های جایگزین
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
$usersFile = __DIR__ . '/../data/users.json';
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

    <!-- ===== منوی داخلی پلنر ===== -->
    <div class="planner-nav">
        <div class="planner-nav-content">
            <div class="nav-filters">
                <button class="nav-btn" data-filter="today"><span class="icon icon-calendar-day"></span> امروز</button>
                <button class="nav-btn" data-filter="tomorrow"><span class="icon icon-calendar-plus"></span> فردا</button>
                <button class="nav-btn" data-filter="upcoming"><span class="icon icon-calendar-week"></span> آینده</button>
                <button class="nav-btn" data-filter="past"><span class="icon icon-calendar-minus"></span> گذشته</button>
                <button class="nav-btn" data-filter="completed"><span class="icon icon-check-circle"></span> انجام</button>
                <button class="nav-btn" data-filter="all"><span class="icon icon-list"></span> همه</button>
            </div>
            <div class="nav-actions">
                
                <button class="btn-menu" onclick="openCategoryModal()">
                    <span class="icon icon-tag"></span> دسته‌ها
                </button>
                <button class="btn-menu" onclick="location.href='habits.php'">
                    <span class="icon icon-fire"></span> عادت‌ها
                </button>
                <button class="btn-menu" onclick="location.href='../vision/index.php'">
                    <span class="icon icon-eye"></span> ویژن برد
                </button>
                <button class="btn-menu" id="exportCsvBtn">
                    <span class="icon icon-file-csv"></span> CSV
                </button>
                <?php if ($currentUser && $currentUser['email'] === 'admin@example.com'): ?>
                    <a href="admin.php" class="btn-menu">
                        <span class="icon icon-shield-alt"></span> مدیریت
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== منوی موبایل ===== -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <div class="user-info-mobile">
                <div class="user-avatar-mobile" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                    <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                </div>
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                    <div class="email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>
            </div>
            <button class="mobile-menu-close" onclick="closeMobileMenu()">
                <span class="icon icon-close"></span>
            </button>
        </div>

        <div class="menu-section">
            <div class="menu-section-title"><span class="icon icon-filter"></span> فیلترها</div>
            <div class="menu-section-buttons">
                <button class="nav-btn-mobile" data-filter="today"><span class="icon icon-calendar-day"></span> امروز</button>
                <button class="nav-btn-mobile" data-filter="tomorrow"><span class="icon icon-calendar-plus"></span> فردا</button>
                <button class="nav-btn-mobile" data-filter="upcoming"><span class="icon icon-calendar-week"></span> آینده</button>
                <button class="nav-btn-mobile" data-filter="past"><span class="icon icon-calendar-minus"></span> گذشته</button>
                <button class="nav-btn-mobile" data-filter="completed"><span class="icon icon-check-circle"></span> انجام شده</button>
                <button class="nav-btn-mobile" data-filter="all"><span class="icon icon-list"></span> همه</button>
            </div>
        </div>

        <div class="menu-section">
            <div class="menu-section-title"><span class="icon icon-tools"></span> مدیریت</div>
            <div class="menu-section-buttons">
                <a href="../projects/index.php"><span class="icon icon-project"></span> پروژه‌ها</a>
                <button onclick="openCategoryModal(); closeMobileMenu();"><span class="icon icon-tag"></span> دسته‌بندی</button>
                <button onclick="location.href='habits.php'; closeMobileMenu();"><span class="icon icon-fire"></span> عادت‌ها</button>
                <button onclick="location.href='../vision/index.php'; closeMobileMenu();"><span class="icon icon-eye"></span> ویژن برد</button>
            </div>
        </div>

        <div class="menu-section">
            <div class="menu-section-title"><span class="icon icon-export"></span> خروجی</div>
            <div class="menu-section-buttons">
                <button onclick="exportCSV(); closeMobileMenu();"><span class="icon icon-file-csv"></span> خروجی CSV</button>
            </div>
        </div>
    </div>

    <!-- ===== محتوای اصلی ===== -->
    <div class="main-container">
        <input type="hidden" id="serverToday" value="<?php echo $todayTehran; ?>">
        <input type="hidden" id="serverTomorrow" value="<?php echo $tomorrowTehran; ?>">
        <input type="hidden" id="todayJalali" value="<?php echo $todayJalali; ?>">

        <div id="mainApp" class="main-app">
            <div class="container">
                <!-- ===== فیلترها ===== -->
                <div class="filters-card" id="filtersCard">
                    <div class="filter-group">
                        <div class="date-range-group">
                            <input type="date" id="filterDateFrom" class="date-range-input" placeholder="از تاریخ">
                            <span>تا</span>
                            <input type="date" id="filterDateTo" class="date-range-input" placeholder="تا تاریخ">
                            <button class="apply-date-range" id="applyDateRangeBtn"><span class="icon icon-check"></span> اعمال بازه</button>
                        </div>
                        <select id="filterPriority" class="filter-select">
                            <option value="">همه اولویت‌ها</option>
                            <option value="high">🔴 بالا</option>
                            <option value="medium">🟡 متوسط</option>
                            <option value="low">🟢 پایین</option>
                        </select>
                        <select id="filterCategory" class="filter-select"><option value="">همه دسته‌بندی‌ها</option></select>
                        <select id="filterProject" class="filter-select"><option value="">همه پروژه‌ها</option></select>
                        <button class="clear-filters" onclick="clearFilters()"><span class="icon icon-undo"></span> پاک کردن فیلترها</button>
                    </div>
                </div>

                <!-- ===== آمار ===== -->
                <div class="stats" id="stats"></div>

                <!-- ===== تسک‌ها ===== -->
                <div class="tasks-card">
                    <div class="view-toggle-global">
                        <button class="view-btn-global" id="gridViewBtn">
                            <span class="icon icon-th-large"></span> نمایش کارتی
                        </button>
                        <button class="view-btn-global" id="listViewBtn">
                            <span class="icon icon-list"></span> نمایش لیستی
                        </button>
                    </div>

                    <div class="drag-info">
                        <span class="icon icon-arrows-alt"></span> برای تغییر اولویت، کارها را با ماوس بکشید و جابجا کنید
                    </div>
                    <div id="tasksList"></div>
                </div>
            </div>

            <button class="add-task-fab" id="openAddTaskBtn">
                <span class="icon icon-plus"></span>
            </button>
        </div>
    </div>

    <!-- ===== مودال افزودن کار ===== -->
    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-plus-circle"></span> افزودن کار جدید</div>
            <div class="modal-body">
                <input type="text" id="addTitle" placeholder="عنوان کار..." required>
                <select id="addCategory"></select>
                <select id="addProject"><option value="">بدون پروژه</option></select>

                <!-- Picker تاریخ شمسی -->
                <div class="date-picker-wrapper">
                    <input type="text" id="addDate" class="date-picker-input" placeholder="تاریخ" value="<?php echo $todayJalali; ?>" readonly>
                    <div class="jalali-calendar" id="addDateCalendar"></div>
                </div>

                <!-- انتخاب زمان با اسلایدر -->
                <div class="time-selector-group">
                    <label><span class="icon icon-clock"></span> زمان:</label>
                    <div class="time-selector-wrapper">
                        <input type="range" class="time-selector" id="addTimeRange" min="0" max="1439" value="720">
                        <span class="time-display" id="addTimeDisplay">12:00</span>
                    </div>
                </div>

                <select id="addPriority">
                    <option value="high">🔴 اولویت بالا</option>
                    <option value="medium" selected>🟡 اولویت متوسط</option>
                    <option value="low">🟢 اولویت پایین</option>
                </select>
                <textarea id="addDescription" placeholder="توضیحات (اختیاری)..." rows="3"></textarea>

                <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; border: 1px solid var(--border-color);">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);"><span class="icon icon-sitemap"></span> زیرتسک برای کار مادر</label>
                    <select id="addParentTask">
                        <option value="">بدون والد (تسک اصلی)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddTaskModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" id="saveAddTaskBtn"><span class="icon icon-save"></span> افزودن کار</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال ویرایش کار ===== -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-edit"></span> ویرایش کار</div>
            <div class="modal-body">
                <input type="text" id="editTitle" placeholder="عنوان کار">
                <select id="editCategory"></select>
                <select id="editProject"><option value="">بدون پروژه</option></select>

                <!-- Picker تاریخ شمسی برای ویرایش -->
                <div class="date-picker-wrapper">
                    <input type="text" id="editDate" class="date-picker-input" placeholder="تاریخ" readonly>
                    <div class="jalali-calendar" id="editDateCalendar"></div>
                </div>

                <!-- انتخاب زمان با اسلایدر برای ویرایش -->
                <div class="time-selector-group">
                    <label><span class="icon icon-clock"></span> زمان:</label>
                    <div class="time-selector-wrapper">
                        <input type="range" class="time-selector" id="editTimeRange" min="0" max="1439" value="720">
                        <span class="time-display" id="editTimeDisplay">12:00</span>
                    </div>
                </div>

                <select id="editPriority">
                    <option value="high">🔴 بالا</option>
                    <option value="medium">🟡 متوسط</option>
                    <option value="low">🟢 پایین</option>
                </select>
                <textarea id="editDescription" placeholder="توضیحات..." rows="4"></textarea>

                <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; border: 1px solid var(--border-color);">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);"><span class="icon icon-sitemap"></span> زیرتسک برای کار مادر</label>
                    <select id="editParentTask">
                        <option value="">بدون والد (تسک اصلی)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeEditModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" onclick="saveEdit()"><span class="icon icon-save"></span> ذخیره تغییرات</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال پروژه‌ها ===== -->
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-project"></span> مدیریت پروژه‌ها</div>
            <div class="modal-body">
                <div id="projectList"></div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="text" id="newProjectName" placeholder="نام پروژه جدید" style="flex:1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <button class="btn-save" id="addProjectBtn" style="flex:0;"><span class="icon icon-plus"></span> افزودن</button>
                </div>
                <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; font-size: 12px; color: var(--text-light); border: 1px solid var(--border-color);">
                    <span class="icon icon-info-circle"></span> برای مشاهده صفحه اختصاصی هر پروژه، روی نام آن کلیک کنید
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProjectModal()"><span class="icon icon-close"></span> بستن</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال صفحه پروژه ===== -->
    <div id="projectPageModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header"><span class="icon icon-project"></span> <span id="projectPageTitle"></span></div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">توضیحات پروژه:</label>
                    <div id="projectPageDesc" style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; min-height: 80px; white-space: pre-wrap; border: 1px solid var(--border-color); color: var(--text-secondary);"></div>
                    <button id="editProjectDescBtn" class="manage-btn" style="margin-top: 10px; background: rgba(102,126,234,0.2); color:#667eea; border: 1px solid rgba(102,126,234,0.15);"><span class="icon icon-edit"></span> ویرایش توضیحات</button>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">آمار پروژه:</label>
                    <div id="projectPageStats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;"></div>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">تسک‌های پروژه:</label>
                    <div id="projectPageTasks" style="max-height: 350px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProjectPageModal()"><span class="icon icon-close"></span> بستن</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال دسته‌ها ===== -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-tag"></span> مدیریت دسته‌بندی</div>
            <div class="modal-body">
                <div id="categoryList"></div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="text" id="newCategoryName" placeholder="نام دسته جدید" style="flex:1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <button class="btn-save" id="addCategoryBtn" style="flex:0;"><span class="icon icon-plus"></span> افزودن</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCategoryModal()"><span class="icon icon-close"></span> بستن</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال پروفایل ===== -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-user"></span> پروفایل کاربری</div>
            <div class="modal-body">
                <div class="profile-info">
                    <div class="profile-avatar" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                        <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                    </div>
                    <h3 id="profileName" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentUser['name']); ?></h3>
                    <div class="profile-email" id="profileEmail"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>

                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);"><span class="icon icon-key"></span> تغییر رمز عبور</label>
                    <input type="password" id="newPassword" placeholder="رمز عبور جدید" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <input type="password" id="confirmNewPassword" placeholder="تکرار رمز عبور جدید" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <div id="passwordError" style="color: #dc3545; font-size: 12px; margin-bottom: 10px; display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProfileModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" id="changePasswordBtn"><span class="icon icon-save"></span> تغییر رمز عبور</button>
            </div>
        </div>
    </div>

    <!-- ===== استایل‌های اختصاصی پلنر ===== -->
    <style>
        /* ===== فونت ===== */
        * {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        /* ===== آیکون‌ها ===== */
        .icon {
            display: inline-block;
            font-size: inherit;
            line-height: 1;
            font-style: normal;
            font-weight: normal;
            font-variant: normal;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
        }

        .icon-sm { font-size: 12px; }
        .icon-md { font-size: 16px; }
        .icon-lg { font-size: 20px; }
        .icon-xl { font-size: 24px; }

        .icon-primary { color: #667eea; }
        .icon-success { color: #28a745; }
        .icon-danger { color: #dc3545; }
        .icon-warning { color: #ffc107; }

        .icon-hover:hover {
            transform: scale(1.15);
            transition: transform 0.3s ease;
        }

        /* ===== منوی ناوبری پلنر ===== */
        .planner-nav {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 12px 20px;
            margin: 0 15px 20px 15px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px var(--shadow-color);
            transition: all 0.3s ease;
        }

        .planner-nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .nav-filters {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }

        .nav-filters .nav-btn {
            padding: 6px 14px;
            border-radius: 20px;
            border: none;
            background: rgba(255,255,255,0.03);
            font-size: 13px;
            transition: all 0.3s;
            color: var(--text-muted);
            cursor: pointer;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .nav-filters .nav-btn:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }

        .nav-filters .nav-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .nav-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-menu {
            background: var(--bg-input);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 6px 14px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }

        .btn-menu:hover {
            background: var(--bg-card-hover);
            border-color: #667eea;
            color: var(--text-primary);
        }

        /* ===== منوی موبایل ===== */
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 999;
            display: none;
        }
        .mobile-menu-overlay.active { display: block; }

        .mobile-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 320px;
            height: 100vh;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            z-index: 1000;
            padding: 20px;
            transition: right 0.3s ease;
            box-shadow: -5px 0 30px var(--shadow-color);
            overflow-y: auto;
            border-left: 1px solid var(--border-color);
        }

        body[data-theme="light"] .mobile-menu {
            background: #ffffff;
        }

        .mobile-menu.open { right: 0; }

        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .mobile-menu-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .mobile-menu-close:hover {
            background: rgba(220,53,69,0.15);
            color: #ff6b6b;
        }

        .user-info-mobile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar-mobile {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
            flex-shrink: 0;
        }

        .user-info-mobile .user-details .name {
            font-weight: 600;
            font-size: 15px;
            color: var(--text-primary);
        }

        .user-info-mobile .user-details .email {
            font-size: 12px;
            color: var(--text-light);
        }

        .menu-section { margin-bottom: 20px; }

        .menu-section-title {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 700;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .menu-section-title .icon { color: #667eea; }

        .menu-section-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu-section-buttons button,
        .menu-section-buttons a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border: none;
            border-radius: 12px;
            background: rgba(255,255,255,0.03);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--text-secondary);
            width: 100%;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .menu-section-buttons button:hover,
        .menu-section-buttons a:hover {
            background: rgba(255,255,255,0.06);
            transform: translateX(-5px);
        }

        .menu-section-buttons .active-mobile {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        /* ===== تنظیمات اصلی ===== */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px 20px 15px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== متغیرهای CSS ===== */
        :root {
            --bg-primary: #0f0c29;
            --bg-secondary: #302b63;
            --bg-card: rgba(255,255,255,0.08);
            --bg-card-hover: rgba(255,255,255,0.15);
            --bg-input: rgba(255,255,255,0.05);
            --text-primary: #ffffff;
            --text-secondary: rgba(255,255,255,0.85);
            --text-muted: rgba(255,255,255,0.5);
            --text-light: rgba(255,255,255,0.3);
            --border-color: rgba(255,255,255,0.1);
            --shadow-color: rgba(0,0,0,0.2);
            --shadow-hover: rgba(0,0,0,0.3);
            --modal-overlay: rgba(0,0,0,0.7);
            --badge-bg: rgba(102,126,234,0.15);
            --badge-color: #667eea;
            --done-bg: rgba(40,167,69,0.1);
            --done-border: rgba(40,167,69,0.3);
            --progress-bg: rgba(255,255,255,0.05);
            --progress-fill: linear-gradient(90deg, #667eea, #764ba2);
            --completed-date: #28a745;
            --today-bg: #f1c40f;
            --calendar-selected: #667eea;
        }

        /* ===== تم روشن ===== */
        body[data-theme="light"] {
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
            --modal-overlay: rgba(0,0,0,0.5);
            --done-bg: rgba(40,167,69,0.1);
            --done-border: rgba(40,167,69,0.3);
        }

        /* ===== فیلترها ===== */
        .filters-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            display: none;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .date-range-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-range-input {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 13px;
            cursor: pointer;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .filter-select {
            padding: 8px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .clear-filters {
            background: rgba(220,53,69,0.2);
            color: #ff6b6b;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .clear-filters:hover { background: rgba(220,53,69,0.3); }

        .apply-date-range {
            background: rgba(40,167,69,0.2);
            color: #28a745;
            border: none;
            padding: 8px 15px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .apply-date-range:hover { background: rgba(40,167,69,0.3); }

        /* ===== آمار ===== */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 5px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        /* ===== تسک‌ها ===== */
        .tasks-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .drag-info {
            background: var(--badge-bg);
            color: var(--badge-color);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .view-toggle-global {
            background: var(--bg-card);
            border-radius: 15px;
            padding: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
            border: 1px solid var(--border-color);
        }

        .view-btn-global {
            padding: 8px 25px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.03);
            color: var(--text-muted);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .view-btn-global.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .date-group { margin-bottom: 30px; }

        .date-header {
            background: linear-gradient(135deg, rgba(102,126,234,0.15), rgba(118,75,162,0.15));
            color: var(--text-primary);
            padding: 12px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }

        .task-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 20px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
            cursor: grab;
            display: flex;
            flex-direction: column;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-card:active { cursor: grabbing; }
        .task-card.dragging { opacity: 0.3; }
        .task-card:hover {
            transform: translateY(-3px);
            border-color: #667eea;
            box-shadow: 0 8px 25px var(--shadow-hover);
        }

        .task-card.completed { 
            opacity: 0.75; 
            background: var(--done-bg); 
            border-color: var(--done-border);
        }
        .task-card.completed .task-title-text { text-decoration: line-through; }

        .card-drag-handle {
            position: absolute;
            top: 16px;
            left: 16px;
            cursor: grab;
            color: var(--text-light);
            font-size: 16px;
            z-index: 2;
        }

        .card-check {
            position: absolute;
            top: 16px;
            left: 44px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
            z-index: 2;
        }

        .card-content {
            flex: 1;
            padding-top: 6px;
            padding-right: 10px;
        }

        .task-header-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
        }

        .task-title-text {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.5;
            flex: 1;
            word-break: break-word;
            padding-right: 70px;
            transition: color 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-title-text .task-link { 
            color: var(--text-primary); 
            text-decoration: none; 
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important; 
        }
        .task-title-text .task-link:hover { color: #667eea; }
        .task-title-text.completed { text-decoration: line-through; opacity: 0.6; }

        .subtask-badge {
            font-size: 11px;
            background: var(--badge-bg);
            color: var(--badge-color);
            padding: 2px 8px;
            border-radius: 12px;
            white-space: nowrap;
            display: inline-block;
            margin-right: 5px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-description {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
            margin: 8px 0 10px 0;
            padding: 8px 0;
            border-top: 1px dashed var(--border-color);
            border-bottom: 1px dashed var(--border-color);
            word-break: break-word;
            padding-right: 70px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 8px;
            padding-right: 70px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-meta > span {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .time-badge { background: rgba(102,126,234,0.15); color: #667eea; }
        .category-badge { background: rgba(245,87,108,0.15); color: #f5576c; }
        .project-badge { background: rgba(67,233,123,0.15); color: #43e97b; }
        .priority-high { background: rgba(220,53,69,0.2); color: #ff6b6b; }
        .priority-medium { background: rgba(255,193,7,0.2); color: #ffc107; }
        .priority-low { background: rgba(40,167,69,0.2); color: #28a745; }

        .completed-date {
            font-size: 11px;
            color: var(--completed-date);
            margin: 5px 0;
            font-weight: 500;
            padding-right: 70px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .progress-container {
            margin: 8px 0;
            background: var(--progress-bg);
            border-radius: 20px;
            height: 4px;
            overflow: hidden;
            padding-right: 70px;
        }

        .progress-bar {
            height: 100%;
            background: var(--progress-fill);
            border-radius: 20px;
            transition: width 0.5s ease;
        }

        .progress-text {
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
            margin-top: 3px;
            display: flex;
            justify-content: space-between;
            padding-right: 70px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .subtasks-container {
            margin-top: 10px;
            padding: 10px 14px;
            background: var(--bg-card);
            border-radius: 10px;
            border-right: 3px solid #667eea;
            margin-right: 70px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .subtask-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 0;
            border-bottom: 1px solid var(--border-color);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .subtask-item:last-child { border-bottom: none; }

        .subtask-check {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #667eea;
            flex-shrink: 0;
        }

        .subtask-title-text {
            flex: 1;
            font-size: 13px;
            word-break: break-word;
            color: var(--text-secondary);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .subtask-title-text.completed {
            text-decoration: line-through;
            opacity: 0.6;
        }

        .subtask-actions button {
            background: rgba(255,255,255,0.08);
            border: 1px solid var(--border-color);
            cursor: pointer;
            padding: 4px 6px;
            font-size: 12px;
            border-radius: 6px;
            color: var(--text-secondary);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .subtask-actions button:hover { 
            background: var(--badge-bg);
            color: var(--badge-color);
            border-color: var(--badge-color);
            transform: scale(1.05);
        }
        
        body[data-theme="light"] .subtask-actions button {
            background: rgba(0,0,0,0.05);
            color: var(--text-primary);
        }

        .card-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            padding-right: 70px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .card-btn {
            background: rgba(255,255,255,0.08);
            border: 1px solid var(--border-color);
            font-size: 12px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .edit-card-btn:hover { 
            background: var(--badge-bg); 
            color: var(--badge-color);
            border-color: var(--badge-color);
            transform: scale(1.05);
        }
        .delete-card-btn:hover { 
            background: rgba(220,53,69,0.15); 
            color: #ff6b6b;
            border-color: #ff6b6b;
            transform: scale(1.05);
        }
        
        body[data-theme="light"] .card-btn {
            background: rgba(0,0,0,0.05);
            color: var(--text-primary);
        }
        .subtask-btn { 
            background: none; 
            border: none; 
            font-size: 12px; 
            cursor: pointer; 
            padding: 5px 12px; 
            border-radius: 8px; 
            color: #667eea; 
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .subtask-btn:hover { background: var(--badge-bg); }

        .list-view-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-item-list {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.3s;
            cursor: grab;
            flex-wrap: wrap;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-item-list:active { cursor: grabbing; }
        .task-item-list.dragging { opacity: 0.3; }
        .task-item-list:hover { border-color: #667eea; }
        .task-item-list.completed .task-title-list { text-decoration: line-through; opacity: 0.6; }

        .drag-handle-list { cursor: grab; color: var(--text-light); font-size: 16px; flex-shrink: 0; }
        .task-check-list { width: 18px; height: 18px; cursor: pointer; accent-color: #667eea; flex-shrink: 0; }

        .task-content-list {
            flex: 1;
            min-width: 150px;
        }

        .task-title-list {
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            color: var(--text-primary);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-title-list .task-link { 
            color: var(--text-primary); 
            text-decoration: none; 
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important; 
        }
        .task-title-list .task-link:hover { color: #667eea; }

        .task-meta-list {
            display: flex;
            gap: 6px;
            font-size: 11px;
            flex-wrap: wrap;
            margin-top: 4px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-meta-list > span {
            padding: 2px 8px;
            border-radius: 10px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .task-actions-list {
            display: flex;
            gap: 5px;
            flex-shrink: 0;
        }

        .edit-btn-list, .delete-btn-list {
            background: rgba(255,255,255,0.08);
            border: 1px solid var(--border-color);
            font-size: 14px;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 8px;
            color: var(--text-secondary);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .edit-btn-list:hover { 
            background: var(--badge-bg); 
            color: var(--badge-color);
            border-color: var(--badge-color);
            transform: scale(1.05);
        }
        .delete-btn-list:hover { 
            background: rgba(220,53,69,0.15); 
            color: #ff6b6b;
            border-color: #ff6b6b;
            transform: scale(1.05);
        }
        
        body[data-theme="light"] .edit-btn-list,
        body[data-theme="light"] .delete-btn-list {
            background: rgba(0,0,0,0.05);
            color: var(--text-primary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
            grid-column: 1 / -1;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .add-task-fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            z-index: 998;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .add-task-fab:hover { transform: scale(1.1); }

        /* ===== Picker تاریخ شمسی ===== */
        .date-picker-wrapper {
            position: relative;
            margin-bottom: 18px;
        }

        .date-picker-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            cursor: pointer;
            direction: rtl;
            transition: border-color 0.3s ease, background 0.3s ease, color 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        body[data-theme="light"] .date-picker-input {
            background: #fafafa;
            color: #1a1a2e;
            border-color: #e2e8f0;
        }

        .date-picker-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .jalali-calendar {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            z-index: 100;
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px;
            margin-top: 6px;
            box-shadow: 0 10px 40px var(--shadow-color);
            width: 100%;
            min-width: 280px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .jalali-calendar.show {
            display: block;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding: 0 4px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .calendar-header .month-year {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .calendar-header .nav-btn {
            background: var(--bg-input);
            border: none;
            color: var(--text-secondary);
            padding: 4px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .calendar-header .nav-btn:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-bottom: 8px;
            text-align: center;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .calendar-weekdays div {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 4px 0;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }

        .calendar-day {
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

        .calendar-day:hover:not(.empty) {
            background: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .calendar-day.today {
            background: var(--today-bg);
            color: #1a1a2e;
            font-weight: bold;
        }

        .calendar-day.selected {
            background: var(--calendar-selected);
            color: white;
        }

        .calendar-day.empty {
            cursor: default;
            color: var(--text-light);
        }

        .calendar-day.other-month {
            color: var(--text-light);
            opacity: 0.5;
        }

        /* ===== استایل زمان با اسلایدر ===== */
        .time-selector-group {
            display: flex;
            gap: 15px;
            margin-bottom: 18px;
            align-items: center;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .time-selector-group label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .time-selector-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .time-selector {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 10px;
            background: var(--bg-input);
            outline: none;
            transition: background 0.3s ease;
        }

        .time-selector::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(102,126,234,0.3);
        }

        .time-selector::-webkit-slider-thumb:hover {
            transform: scale(1.15);
        }

        .time-selector::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            cursor: pointer;
            border: none;
        }

        .time-display {
            min-width: 60px;
            text-align: center;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
            background: var(--bg-input);
            padding: 4px 8px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', monospace !important;
        }

        body[data-theme="light"] .time-display {
            background: #fafafa;
            color: #1a1a2e;
            border-color: #e2e8f0;
        }

        /* ===== مودال‌ها ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: var(--modal-overlay);
        }

        .modal-content {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            margin: 5% auto;
            width: 90%;
            max-width: 600px;
            border-radius: 25px;
            max-height: 90vh;
            overflow-y: auto;
            transition: background 0.3s ease, border-color 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        body[data-theme="light"] .modal-content {
            background: #ffffff;
            border-color: #e2e8f0;
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

        .modal-body input, .modal-body select, .modal-body textarea {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 18px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: border-color 0.3s ease, background 0.3s ease, color 0.3s ease;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        body[data-theme="light"] .modal-body input,
        body[data-theme="light"] .modal-body select,
        body[data-theme="light"] .modal-body textarea {
            background: #fafafa;
            color: #1a1a2e;
            border-color: #e2e8f0;
        }

        .modal-body input:focus, .modal-body select:focus, .modal-body textarea:focus {
            outline: none;
            border-color: #667eea;
            background: var(--bg-card-hover);
        }

        body[data-theme="light"] .modal-body input:focus,
        body[data-theme="light"] .modal-body select:focus,
        body[data-theme="light"] .modal-body textarea:focus {
            background: #ffffff;
        }

        .modal-body textarea { resize: vertical; min-height: 100px; }

        .modal-footer {
            padding: 20px 25px 25px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .btn-cancel {
            background: var(--bg-input);
            color: var(--text-secondary);
            padding: 12px 25px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }

        .btn-cancel:hover { background: var(--bg-card-hover); }

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
            transition: transform 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }

        .btn-save:hover { transform: scale(1.02); }

        .project-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .project-link {
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .project-stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .profile-info {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
        }

        .profile-email { 
            color: var(--text-muted); 
            font-size: 14px; 
            margin-top: 5px; 
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important; 
        }

        /* ===== Toast ===== */
        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            color: var(--text-primary);
            padding: 12px 24px;
            border-radius: 14px;
            font-size: 14px;
            z-index: 2000;
            opacity: 0;
            transition: all 0.4s ease;
            font-family: 'Vazirmatn', sans-serif;
            white-space: nowrap;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
        }

        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast.success { background: #28a745; color: white; }
        .toast.error { background: #dc3545; color: white; }

        /* ===== ریسپانسیو ===== */
        @media (max-width: 768px) {
            .planner-nav {
                padding: 10px 15px;
                margin: 0 10px 15px 10px;
            }

            .planner-nav-content {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .nav-filters {
                justify-content: center;
            }

            .nav-actions {
                justify-content: center;
                gap: 4px;
            }

            .nav-actions .btn-menu {
                font-size: 12px;
                padding: 4px 10px;
            }

            .main-container {
                padding: 0 10px 15px 10px;
            }
            
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-group {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-select, .clear-filters {
                width: 100%;
            }
            
            .date-range-group {
                width: 100%;
            }
            
            .date-range-input {
                flex: 1;
            }
            
            .add-task-fab {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .date-header {
                flex-direction: column;
                text-align: center;
            }
            
            .view-btn-global {
                padding: 6px 15px;
                font-size: 12px;
            }
            
            .task-card { padding: 14px; }
            .task-title-text { 
                font-size: 14px; 
                padding-right: 65px;
            }
            .task-description { padding-right: 65px; }
            .task-meta { padding-right: 65px; }
            .card-actions { padding-right: 65px; }
            .progress-container { padding-right: 65px; }
            .progress-text { padding-right: 65px; }
            .subtasks-container { margin-right: 65px; }
            .completed-date { padding-right: 65px; }
            
            .card-check { 
                top: 14px;
                left: 44px;
                width: 18px;
                height: 18px;
            }
            .card-drag-handle { 
                top: 14px;
                left: 14px;
                font-size: 14px;
            }
            
            .task-item-list { padding: 10px 12px; }
            .task-content-list { min-width: 100%; }
            .task-actions-list { width: 100%; justify-content: flex-end; }
            
            .time-selector-group {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @media (max-width: 480px) {
            .planner-nav {
                padding: 8px 10px;
            }

            .nav-filters .nav-btn {
                font-size: 11px;
                padding: 4px 10px;
            }

            .nav-actions .btn-menu {
                font-size: 11px;
                padding: 3px 8px;
            }

            .task-card { padding: 12px; }
            .task-title-text { 
                font-size: 13px; 
                padding-right: 60px;
            }
            .task-description { 
                font-size: 12px; 
                padding-right: 60px;
            }
            .task-meta { padding-right: 60px; }
            .task-meta > span { font-size: 10px; padding: 2px 6px; }
            .card-actions { padding-right: 60px; }
            .card-btn { font-size: 11px; padding: 4px 8px; }
            .progress-container { padding-right: 60px; }
            .progress-text { padding-right: 60px; font-size: 10px; }
            .subtasks-container { 
                margin-right: 60px; 
                padding: 8px 10px;
            }
            .subtask-title-text { font-size: 12px; }
            .completed-date { 
                padding-right: 60px;
                font-size: 10px;
            }
            
            .card-check { 
                top: 12px;
                left: 38px;
                width: 16px;
                height: 16px;
            }
            .card-drag-handle { 
                top: 12px;
                left: 12px;
                font-size: 12px;
            }
            .modal-content { padding: 15px; }
            .time-selector-wrapper { flex-direction: column; }
            .time-display { min-width: 100%; }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        // ============================================
        // مدیریت منوی موبایل
        // ============================================
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            menu.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = menu.classList.contains('open') ? 'hidden' : '';
        }

        function closeMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            menu.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', toggleMobileMenu);
            }

            document.getElementById('mobileMenuOverlay')?.addEventListener('click', closeMobileMenu);
        });

        // ============================================
        // مدیریت فیلترها در منوی پلنر
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nav-filters .nav-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.nav-filters .nav-btn').forEach(function(b) {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                    if (this.dataset.filter) {
                        currentFilter = this.dataset.filter;
                        renderAll();
                    }
                });
            });

            document.querySelectorAll('.nav-btn-mobile').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var filter = this.dataset.filter;
                    if (filter) {
                        document.querySelectorAll('.nav-filters .nav-btn').forEach(function(b) {
                            b.classList.remove('active');
                        });
                        document.querySelector('.nav-filters .nav-btn[data-filter="' + filter + '"]')?.classList.add('active');
                        document.querySelectorAll('.nav-btn-mobile').forEach(function(b) {
                            b.classList.remove('active-mobile');
                        });
                        this.classList.add('active-mobile');
                        currentFilter = filter;
                        renderAll();
                        closeMobileMenu();
                    }
                });
            });
        });

        // ============================================
        // متغیرها
        // ============================================
        const SERVER_TODAY = document.getElementById('serverToday').value;
        const SERVER_TOMORROW = document.getElementById('serverTomorrow').value;
        const TODAY_JALALI = document.getElementById('todayJalali').value;

        let tasks = [];
        let categories = [];
        let projects = [];
        let currentFilter = 'today';
        let currentEditId = null;
        let sortableInstances = {};
        let filters = { date: '', priority: '', category: '', project: '', dateFrom: '', dateTo: '' };
        let currentView = localStorage.getItem('taskView') || 'grid';
        let currentProjectPage = null;
        let datePickers = {};
        let addTimeSelector = null;
        let editTimeSelector = null;

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
        // توابع مدیریت زمان با اسلایدر
        // ============================================
        function initTimeSelector(rangeId, displayId) {
            var range = document.getElementById(rangeId);
            var display = document.getElementById(displayId);
            if (!range || !display) return;
            
            function updateDisplay() {
                var totalMinutes = parseInt(range.value);
                var hours = Math.floor(totalMinutes / 60);
                var minutes = totalMinutes % 60;
                var timeStr = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                display.textContent = timeStr;
            }
            
            range.addEventListener('input', updateDisplay);
            updateDisplay();
            
            return {
                getTime: function() {
                    var totalMinutes = parseInt(range.value);
                    var hours = Math.floor(totalMinutes / 60);
                    var minutes = totalMinutes % 60;
                    return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                },
                setTime: function(timeStr) {
                    if (!timeStr) return;
                    var parts = timeStr.split(':');
                    if (parts.length !== 2) return;
                    var hours = parseInt(parts[0]) || 0;
                    var minutes = parseInt(parts[1]) || 0;
                    var totalMinutes = (hours * 60) + minutes;
                    if (totalMinutes >= 0 && totalMinutes <= 1439) {
                        range.value = totalMinutes;
                        updateDisplay();
                    }
                }
            };
        }

        // ============================================
        // توابع کمکی
        // ============================================
        function toPersianNumbers(str) {
            if (str === undefined || str === null) return '';
            var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return String(str).replace(/[0-9]/g, function(d) { return persianDigits[parseInt(d)]; });
        }

        function validateTime(timeStr) { return /^([0-1][0-9]|2[0-3]):([0-5][0-9])$/.test(timeStr); }

        function formatTimeToPersian(timeStr) {
            if (!timeStr) return '⏰ --:--';
            if (!validateTime(timeStr)) return '⏰ ۱۲:۰۰';
            var parts = timeStr.split(':');
            return toPersianNumbers(parts[0]) + ':' + toPersianNumbers(parts[1]);
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return toPersianNumbers(d.toLocaleDateString('fa-IR', options));
        }

        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return '';
            var d = new Date(dateTimeStr);
            var date = formatDate(d.toISOString().split('T')[0]);
            var time = d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
            return date + ' ساعت ' + time;
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function isToday(dateStr) { return dateStr === SERVER_TODAY; }
        function isTomorrow(dateStr) { return dateStr === SERVER_TOMORROW; }
        function isPast(dateStr) { return dateStr < SERVER_TODAY; }
        function isUpcoming(dateStr) { return dateStr > SERVER_TODAY && dateStr !== SERVER_TOMORROW; }

        function getTaskChildren(taskId) { return tasks.filter(function(t) { return t.parent_id == taskId; }); }

        function getTaskProgress(taskId) {
            var children = getTaskChildren(taskId);
            if (children.length === 0) return null;
            var total = children.length;
            var done = children.filter(function(t) { return t.done; }).length;
            return { total: total, done: done, percent: Math.round((done / total) * 100) };
        }

        function updateParentSelects() {
            var options = '<option value="">بدون والد (تسک اصلی)</option>';
            var sortedTasks = tasks.slice().sort(function(a, b) { return (a.order || 0) - (b.order || 0); });
            var parentCandidates = sortedTasks.filter(function(t) { return !t.parent_id || t.parent_id === ''; });
            parentCandidates.forEach(function(task) {
                options += '<option value="' + task.id + '">' + escapeHtml(task.title) + '</option>';
            });
            document.getElementById('addParentTask').innerHTML = options;
            document.getElementById('editParentTask').innerHTML = options;
        }

        // ============================================
        // توابع دیتا
        // ============================================
        function loadData() {
            var formData = new FormData();
            formData.append('action', 'load');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        tasks = result.tasks || [];
                        categories = result.categories || [];
                        projects = result.projects || [];
                        updateSelects();
                        updateParentSelects();
                        renderAll();
                        initDatePickers();
                        initTimeSelectors();
                    }
                })['catch'](function(e) {
                    console.error('خطا در ارتباط با سرور:', e);
                });
        }

        function sendRequest(action, data) {
            var formData = new FormData();
            formData.append('action', action);
            for (var key in data) {
                formData.append(key, data[key]);
            }
            return fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        if (result.tasks) {
                            tasks = result.tasks;
                            updateParentSelects();
                        }
                        if (result.categories) {
                            categories = result.categories;
                            updateSelects();
                            if (document.getElementById('categoryModal').style.display === 'block') refreshCategoryList();
                        }
                        if (result.projects) {
                            projects = result.projects;
                            updateSelects();
                            if (document.getElementById('projectModal').style.display === 'block') refreshProjectList();
                        }
                        renderAll();
                    }
                    return result;
                });
        }

        function updateSelects() {
            var categoryOptions = categories.map(function(c) { return '<option value="' + c + '">' + c + '</option>'; }).join('');
            var projectOptions = projects.map(function(p) { return '<option value="' + p.name + '">' + p.name + '</option>'; }).join('');
            document.getElementById('addCategory').innerHTML = categoryOptions;
            document.getElementById('addProject').innerHTML = '<option value="">بدون پروژه</option>' + projectOptions;
            document.getElementById('editCategory').innerHTML = categoryOptions;
            document.getElementById('editProject').innerHTML = '<option value="">بدون پروژه</option>' + projectOptions;
            document.getElementById('filterCategory').innerHTML = '<option value="">همه دسته‌بندی‌ها</option>' + categoryOptions;
            document.getElementById('filterProject').innerHTML = '<option value="">همه پروژه‌ها</option>' + projectOptions;
        }

        function refreshCategoryList() {
            var container = document.getElementById('categoryList');
            if (!categories || categories.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-light);">هیچ دسته‌بندی تعریف نشده است</div>';
            } else {
                container.innerHTML = categories.map(function(cat) {
                    return '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);"><span style="color:var(--text-primary);">' + cat + '</span><button onclick="deleteCategory(\'' + cat + '\')" style="background:rgba(220,53,69,0.15); color:#ff6b6b; padding:4px 10px; border:none; border-radius:6px; cursor:pointer;">🗑️</button></div>';
                }).join('');
            }
        }

        function refreshProjectList() {
            var container = document.getElementById('projectList');
            if (!projects || projects.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-light);">هیچ پروژه‌ای تعریف نشده است</div>';
            } else {
                container.innerHTML = projects.map(function(proj) {
                    return '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);"><a onclick="openProjectPage(\'' + encodeURIComponent(proj.name) + '\')" style="cursor:pointer; color:var(--text-primary); text-decoration:none; display:flex; align-items:center; gap:8px;"><span class="icon icon-project" style="color: ' + (proj.color || '#2d6a4f') + '"></span> ' + escapeHtml(proj.name) + '</a><button onclick="deleteProject(\'' + proj.name + '\')" style="background:rgba(220,53,69,0.15); color:#ff6b6b; padding:4px 10px; border:none; border-radius:6px; cursor:pointer;">🗑️</button></div>';
                }).join('');
            }
        }

        // ============================================
        // مدیریت Picker تاریخ شمسی
        // ============================================
        function initDatePickers() {
            var addPicker = new JalaliDatePicker('addDate', 'addDateCalendar', {
                defaultDate: TODAY_JALALI,
                onSelect: function(dateStr) {
                    document.getElementById('addDate').value = dateStr;
                }
            });
            datePickers['addDateCalendar'] = addPicker;

            var editPicker = new JalaliDatePicker('editDate', 'editDateCalendar', {
                defaultDate: TODAY_JALALI,
                onSelect: function(dateStr) {
                    document.getElementById('editDate').value = dateStr;
                }
            });
            datePickers['editDateCalendar'] = editPicker;

            window.datePickers = datePickers;
        }

        // ============================================
        // مدیریت زمان با اسلایدر
        // ============================================
        function initTimeSelectors() {
            addTimeSelector = initTimeSelector('addTimeRange', 'addTimeDisplay');
            editTimeSelector = initTimeSelector('editTimeRange', 'editTimeDisplay');
        }

        function getTimeFromSelector(selector) {
            return selector ? selector.getTime() : '12:00';
        }

        // ============================================
        // توابع فیلتر و رندر
        // ============================================
        function getFilteredTasks() {
            var filtered = tasks.slice();

            if (currentFilter === 'completed') {
                filtered = filtered.filter(function(t) { return t.done === true; });
                if (filters.dateFrom && filters.dateTo) {
                    filtered = filtered.filter(function(t) { return t.date >= filters.dateFrom && t.date <= filters.dateTo; });
                }
                if (filters.priority) filtered = filtered.filter(function(t) { return t.priority === filters.priority; });
                if (filters.category) filtered = filtered.filter(function(t) { return t.category === filters.category; });
                if (filters.project) filtered = filtered.filter(function(t) { return t.project === filters.project; });
            } else {
                switch(currentFilter) {
                    case 'today': filtered = filtered.filter(function(t) { return isToday(t.date) && !t.done; }); break;
                    case 'tomorrow': filtered = filtered.filter(function(t) { return isTomorrow(t.date) && !t.done; }); break;
                    case 'upcoming': filtered = filtered.filter(function(t) { return isUpcoming(t.date) && !t.done; }); break;
                    case 'past': filtered = filtered.filter(function(t) { return isPast(t.date) && !t.done; }); break;
                    case 'all': break;
                }
            }

            filtered.sort(function(a, b) { return (a.order || 0) - (b.order || 0); });
            return filtered;
        }

        function groupByDate(tasksList) {
            var groups = {};
            tasksList.forEach(function(task) {
                if (!groups[task.date]) groups[task.date] = [];
                groups[task.date].push(task);
            });
            return groups;
        }

        function updateStats() {
            var total = tasks.length;
            var completed = tasks.filter(function(t) { return t.done; }).length;
            var todayTasks = tasks.filter(function(t) { return isToday(t.date) && !t.done; }).length;
            var upcoming = tasks.filter(function(t) { return !isPast(t.date) && !t.done && !isToday(t.date); }).length;
            var parentTasks = tasks.filter(function(t) { return !t.parent_id || t.parent_id === ''; }).length;
            var subtasks = tasks.filter(function(t) { return t.parent_id && t.parent_id !== ''; }).length;

            var tasksWithProgress = tasks.filter(function(t) { return getTaskChildren(t.id).length > 0; });
            var avgProgress = 0;
            if (tasksWithProgress.length > 0) {
                var totalProgress = 0;
                tasksWithProgress.forEach(function(t) {
                    var p = getTaskProgress(t.id);
                    if (p) totalProgress += p.percent;
                });
                avgProgress = Math.round(totalProgress / tasksWithProgress.length);
            }

            document.getElementById('stats').innerHTML = 
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(total) + '</div><div class="stat-label">کل وظایف</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(completed) + '</div><div class="stat-label">انجام شده</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(todayTasks) + '</div><div class="stat-label">وظایف امروز</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(upcoming) + '</div><div class="stat-label">روزهای آینده</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(parentTasks) + '</div><div class="stat-label">تسک‌های اصلی</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(subtasks) + '</div><div class="stat-label">زیرتسک‌ها</div></div>' +
                (tasksWithProgress.length > 0 ? '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(avgProgress) + '%</div><div class="stat-label">میانگین پیشرفت</div></div>' : '');
        }

        function setView(view) {
            currentView = view;
            localStorage.setItem('taskView', view);

            if (view === 'grid') {
                document.getElementById('gridViewBtn').classList.add('active');
                document.getElementById('listViewBtn').classList.remove('active');
            } else {
                document.getElementById('gridViewBtn').classList.remove('active');
                document.getElementById('listViewBtn').classList.add('active');
            }
            renderTasks();
        }

        function renderProgressBar(taskId) {
            var progress = getTaskProgress(taskId);
            if (!progress) return '';
            return '<div class="progress-container"><div class="progress-bar" style="width: ' + progress.percent + '%"></div></div><div class="progress-text"><span>پیشرفت</span><span>' + toPersianNumbers(progress.done) + ' از ' + toPersianNumbers(progress.total) + ' (' + toPersianNumbers(progress.percent) + '%)</span></div>';
        }

        function renderSubtasks(taskId, parentTitle) {
            var children = tasks.filter(function(t) { return t.parent_id == taskId; });
            if (children.length === 0) return '';
            return '<div class="subtasks-container"><div style="font-size: 12px; font-weight: bold; margin-bottom: 6px; color: #667eea;"><span class="icon icon-sitemap"></span> زیرتسک‌ها (' + children.length + ')</div>' + 
                children.map(function(child) {
                    return '<div class="subtask-item"><input type="checkbox" class="subtask-check" ' + (child.done ? 'checked' : '') + ' onchange="toggleTask(\'' + child.id + '\', ' + child.done + ')"><span class="subtask-title-text ' + (child.done ? 'completed' : '') + '">' + escapeHtml(child.title) + '</span><div class="subtask-actions"><button onclick="openEditModal(\'' + child.id + '\')"><span class="icon icon-edit"></span></button><button onclick="deleteTask(\'' + child.id + '\')"><span class="icon icon-trash" style="color:#ff6b6b;"></span></button></div></div>';
                }).join('') +
                '<div style="margin-top: 6px;"><button class="subtask-btn" onclick="openAddSubtaskModal(\'' + taskId + '\', \'' + escapeHtml(parentTitle) + '\')"><span class="icon icon-plus"></span> افزودن زیرتسک</button></div></div>';
        }

        function renderGridTasks(tasksList) {
            var mainTasks = tasksList.filter(function(t) { return !t.parent_id || t.parent_id === ''; });
            if (mainTasks.length === 0) {
                return '<div class="cards-grid"><div class="empty-state">هیچ تسک اصلی یافت نشد</div></div>';
            }
            return '<div class="cards-grid sortable-grid">' + 
                mainTasks.map(function(task) {
                    var progress = getTaskProgress(task.id);
                    var isCompleted = task.done;
                    return '<div class="task-card ' + (isCompleted ? 'completed' : '') + '" data-id="' + task.id + '">' +
                        '<div class="card-drag-handle"><span class="icon icon-grip-vertical"></span></div>' +
                        '<input type="checkbox" class="card-check" ' + (isCompleted ? 'checked' : '') + ' onchange="toggleTask(\'' + task.id + '\', ' + isCompleted + ')">' +
                        '<div class="card-content">' +
                            '<div class="task-header-row"><div class="task-title-text ' + (isCompleted ? 'completed' : '') + '"><a href="#" class="task-link" onclick="event.preventDefault();">' + escapeHtml(task.title) + '</a><span class="subtask-badge"><span class="icon icon-sitemap"></span> ' + getTaskChildren(task.id).length + '</span></div></div>' +
                            (task.description ? '<div class="task-description"><span class="icon icon-align-left"></span> ' + escapeHtml(task.description) + '</div>' : '') +
                            (progress ? renderProgressBar(task.id) : '') +
                            '<div class="task-meta"><span class="time-badge"><span class="icon icon-clock"></span> ' + formatTimeToPersian(task.time) + '</span><span class="category-badge"><span class="icon icon-tag"></span> ' + task.category + '</span>' + (task.project ? '<span class="project-badge"><span class="icon icon-project"></span> ' + task.project + '</span>' : '') + '<span class="priority-badge priority-' + task.priority + '">' + (task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین') + '</span></div>' +
                            (task.completed_at ? '<div class="completed-date"><span class="icon icon-check-circle"></span> ' + formatDateTime(task.completed_at) + '</div>' : '') +
                            renderSubtasks(task.id, task.title) +
                        '</div>' +
                        '<div class="card-actions"><button class="card-btn edit-card-btn" onclick="openEditModal(\'' + task.id + '\')"><span class="icon icon-edit"></span> ویرایش</button><button class="card-btn delete-card-btn" onclick="deleteTaskWithChildren(\'' + task.id + '\')"><span class="icon icon-trash"></span> حذف</button></div>' +
                    '</div>';
                }).join('') +
            '</div>';
        }

        function renderListTasks(tasksList) {
            var mainTasks = tasksList.filter(function(t) { return !t.parent_id || t.parent_id === ''; });
            if (mainTasks.length === 0) {
                return '<div class="empty-state">هیچ تسک اصلی یافت نشد</div>';
            }
            return '<div class="list-view-container sortable-list">' +
                mainTasks.map(function(task) {
                    var progress = getTaskProgress(task.id);
                    return '<div class="task-item-list ' + (task.done ? 'completed' : '') + '" data-id="' + task.id + '">' +
                        '<div class="drag-handle-list"><span class="icon icon-grip-vertical"></span></div>' +
                        '<input type="checkbox" class="task-check-list" ' + (task.done ? 'checked' : '') + ' onchange="toggleTask(\'' + task.id + '\', ' + task.done + ')">' +
                        '<div class="task-content-list">' +
                            '<div class="task-title-list"><a href="#" class="task-link" onclick="event.preventDefault();">' + escapeHtml(task.title) + '</a><span class="subtask-badge"><span class="icon icon-sitemap"></span> ' + getTaskChildren(task.id).length + '</span></div>' +
                            (progress ? renderProgressBar(task.id) : '') +
                            '<div class="task-meta-list"><span class="time-badge"><span class="icon icon-clock"></span> ' + formatTimeToPersian(task.time) + '</span><span class="category-badge"><span class="icon icon-tag"></span> ' + task.category + '</span>' + (task.project ? '<span class="project-badge"><span class="icon icon-project"></span> ' + task.project + '</span>' : '') + '<span class="priority-badge priority-' + task.priority + '">' + (task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین') + '</span></div>' +
                            (task.description ? '<div style="font-size: 12px; color: var(--text-muted); margin-top: 5px;"><span class="icon icon-align-left"></span> ' + escapeHtml(task.description) + '</div>' : '') +
                            (task.completed_at ? '<div style="font-size: 11px; color: var(--completed-date); margin-top: 5px;"><span class="icon icon-check-circle"></span> انجام شده در ' + formatDateTime(task.completed_at) + '</div>' : '') +
                            renderSubtasks(task.id, task.title) +
                        '</div>' +
                        '<div class="task-actions-list"><button class="edit-btn-list" onclick="openEditModal(\'' + task.id + '\')"><span class="icon icon-edit"></span></button><button class="delete-btn-list" onclick="deleteTaskWithChildren(\'' + task.id + '\')"><span class="icon icon-trash"></span></button></div>' +
                    '</div>';
                }).join('') +
            '</div>';
        }

        function renderTasks() {
            var filtered = getFilteredTasks();
            var grouped = groupByDate(filtered);
            var sortedDates = Object.keys(grouped).sort().reverse();
            var container = document.getElementById('tasksList');

            if (sortedDates.length === 0) {
                container.innerHTML = '<div class="empty-state"><span class="icon icon-inbox" style="font-size: 48px;"></span><div>هیچ کاری یافت نشد</div></div>';
                updateStats();
                return;
            }

            container.innerHTML = sortedDates.map(function(date) {
                return '<div class="date-group">' +
                    '<div class="date-header"><span><span class="icon icon-calendar-alt"></span> ' + formatDate(date) + '</span><span>' + toPersianNumbers(grouped[date].length) + ' کار</span></div>' +
                    '<div class="tasks-container-' + currentView + '">' +
                    (currentView === 'grid' ? renderGridTasks(grouped[date]) : renderListTasks(grouped[date])) +
                    '</div></div>';
            }).join('');

            initSortables();
            updateStats();
        }

        function initSortables() {
            for (var id in sortableInstances) sortableInstances[id].destroy();
            sortableInstances = {};

            document.querySelectorAll('.sortable-grid').forEach(function(grid) {
                sortableInstances[grid.id] = new Sortable(grid, {
                    animation: 300,
                    handle: '.card-drag-handle',
                    ghostClass: 'dragging',
                    onEnd: function() {
                        var allIds = [];
                        document.querySelectorAll('.sortable-grid').forEach(function(g) {
                            g.querySelectorAll('.task-card').forEach(function(card) {
                                var id = card.getAttribute('data-id');
                                if (id) allIds.push(id);
                            });
                        });
                        if (allIds.length > 0) sendRequest('reorder', { ids: JSON.stringify(allIds) });
                    }
                });
            });

            document.querySelectorAll('.sortable-list').forEach(function(list) {
                sortableInstances[list.id] = new Sortable(list, {
                    animation: 300,
                    handle: '.drag-handle-list',
                    ghostClass: 'dragging',
                    onEnd: function() {
                        var allIds = [];
                        document.querySelectorAll('.sortable-list').forEach(function(l) {
                            l.querySelectorAll('.task-item-list').forEach(function(item) {
                                var id = item.getAttribute('data-id');
                                if (id) allIds.push(id);
                            });
                        });
                        if (allIds.length > 0) sendRequest('reorder', { ids: JSON.stringify(allIds) });
                    }
                });
            });
        }

        // ============================================
        // توابع عملیات تسک
        // ============================================
        function toggleTask(id, currentDone) {
            sendRequest('toggle', { id: id, current_done: currentDone });
            if (currentProjectPage) {
                openProjectPage(encodeURIComponent(currentProjectPage));
            }
        }

        function deleteTask(id) {
            if (confirm('حذف شود؟')) sendRequest('delete', { id: id });
        }

        function deleteTaskWithChildren(id) {
            var task = tasks.find(function(t) { return t.id == id; });
            var children = getTaskChildren(id);
            if (children.length > 0) {
                if (confirm('تسک "' + (task ? task.title : '') + '" دارای ' + children.length + ' زیرتسک است.\nآیا می‌خواهید همه آن‌ها را حذف کنید؟')) {
                    sendRequest('delete', { id: id, delete_children: 'true' });
                }
            } else {
                if (confirm('حذف شود؟')) sendRequest('delete', { id: id });
            }
        }

        function openAddTaskModal() {
            document.getElementById('addTitle').value = '';
            document.getElementById('addDescription').value = '';
            document.getElementById('addDate').value = TODAY_JALALI;
            if (addTimeSelector) {
                addTimeSelector.setTime('12:00');
            }
            document.getElementById('addPriority').value = 'medium';
            document.getElementById('addParentTask').value = '';
            document.getElementById('addTaskModal').style.display = 'block';
            document.body.style.overflow = 'hidden';

            if (datePickers['addDateCalendar']) {
                var parts = TODAY_JALALI.split('/');
                datePickers['addDateCalendar'].currentYear = parseInt(parts[0]);
                datePickers['addDateCalendar'].currentMonth = parseInt(parts[1]);
                datePickers['addDateCalendar'].currentDay = parseInt(parts[2]);
                datePickers['addDateCalendar'].currentDate = TODAY_JALALI;
                datePickers['addDateCalendar'].render();
            }
        }

        function openAddSubtaskModal(parentId, parentTitle) {
            document.getElementById('addTitle').value = '';
            document.getElementById('addDescription').value = '';
            document.getElementById('addDate').value = TODAY_JALALI;
            if (addTimeSelector) {
                addTimeSelector.setTime('12:00');
            }
            document.getElementById('addPriority').value = 'medium';
            document.getElementById('addParentTask').value = parentId;

            if (datePickers['addDateCalendar']) {
                var parts = TODAY_JALALI.split('/');
                datePickers['addDateCalendar'].currentYear = parseInt(parts[0]);
                datePickers['addDateCalendar'].currentMonth = parseInt(parts[1]);
                datePickers['addDateCalendar'].currentDay = parseInt(parts[2]);
                datePickers['addDateCalendar'].currentDate = TODAY_JALALI;
                datePickers['addDateCalendar'].render();
            }

            document.querySelector('#addTaskModal .modal-header').innerHTML = '<span class="icon icon-plus-circle"></span> افزودن زیرتسک برای "' + escapeHtml(parentTitle) + '"';
            document.getElementById('addTaskModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeAddTaskModal() {
            document.getElementById('addTaskModal').style.display = 'none';
            document.body.style.overflow = '';
            document.querySelector('#addTaskModal .modal-header').innerHTML = '<span class="icon icon-plus-circle"></span> افزودن کار جدید';
        }

        function addNewTask() {
            var title = document.getElementById('addTitle').value.trim();
            if (!title) { alert('لطفاً عنوان کار را وارد کنید'); return; }

            var timeValue = getTimeFromSelector(addTimeSelector);
            if (!validateTime(timeValue)) { alert('فرمت زمان صحیح نیست'); return; }

            var jalaliDate = document.getElementById('addDate').value;
            var gregorianDate = toGregorianDate(jalaliDate) || SERVER_TODAY;
            var parentId = document.getElementById('addParentTask').value;

            sendRequest('add', {
                title: title,
                description: document.getElementById('addDescription').value,
                category: document.getElementById('addCategory').value,
                project: document.getElementById('addProject').value,
                date: gregorianDate,
                time: timeValue,
                priority: document.getElementById('addPriority').value,
                parent_id: parentId
            }).then(function() {
                closeAddTaskModal();
                document.querySelector('#addTaskModal .modal-header').innerHTML = '<span class="icon icon-plus-circle"></span> افزودن کار جدید';
            });
        }

        function openEditModal(id) {
            var task = tasks.find(function(t) { return t.id == id; });
            if (!task) return;
            currentEditId = id;
            document.getElementById('editTitle').value = task.title;
            document.getElementById('editCategory').value = task.category;
            document.getElementById('editProject').value = task.project || '';

            var jalaliDate = toJalaliDate(task.date);
            document.getElementById('editDate').value = jalaliDate;
            if (datePickers['editDateCalendar']) {
                var parts = jalaliDate.split('/');
                datePickers['editDateCalendar'].currentYear = parseInt(parts[0]);
                datePickers['editDateCalendar'].currentMonth = parseInt(parts[1]);
                datePickers['editDateCalendar'].currentDay = parseInt(parts[2]);
                datePickers['editDateCalendar'].currentDate = jalaliDate;
                datePickers['editDateCalendar'].render();
            }

            if (editTimeSelector) {
                editTimeSelector.setTime(task.time || '12:00');
            }
            document.getElementById('editPriority').value = task.priority;
            document.getElementById('editDescription').value = task.description || '';
            document.getElementById('editParentTask').value = task.parent_id || '';
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            currentEditId = null;
            document.body.style.overflow = '';
        }

        function saveEdit() {
            if (!currentEditId) return;
            var timeValue = getTimeFromSelector(editTimeSelector);
            if (!validateTime(timeValue)) { alert('فرمت زمان صحیح نیست'); return; }

            var jalaliDate = document.getElementById('editDate').value;
            var gregorianDate = toGregorianDate(jalaliDate) || SERVER_TODAY;

            sendRequest('edit', {
                id: currentEditId,
                title: document.getElementById('editTitle').value,
                description: document.getElementById('editDescription').value,
                category: document.getElementById('editCategory').value,
                project: document.getElementById('editProject').value,
                date: gregorianDate,
                time: timeValue,
                priority: document.getElementById('editPriority').value,
                parent_id: document.getElementById('editParentTask').value
            }).then(function() {
                closeEditModal();
            });
        }

        // ============================================
        // توابع مدیریت دسته و پروژه
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
                sendRequest('add_category', { category: newCat });
                document.getElementById('newCategoryName').value = '';
            } else if (newCat && categories.includes(newCat)) {
                alert('این دسته بندی قبلاً وجود دارد');
            } else {
                alert('لطفاً نام دسته بندی را وارد کنید');
            }
        }

        function deleteCategory(category) {
            if (confirm('حذف دسته "' + category + '"؟')) {
                sendRequest('delete_category', { category: category });
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
                if (result && !result.success && result.message) {
                    alert(result.message);
                } else {
                    document.getElementById('newProjectName').value = '';
                }
            });
        }

        function deleteProject(project) {
            if (confirm('آیا از حذف پروژه "' + project + '" مطمئن هستید؟\nتوجه: تسک‌های این پروژه به "بدون پروژه" تغییر می‌یابند.')) {
                sendRequest('delete_project', { project: project }).then(function() {
                    closeProjectModal();
                });
            }
        }

        // ============================================
        // صفحه پروژه
        // ============================================
        function openProjectPage(projectName) {
            var decodedName = decodeURIComponent(projectName);
            var project = projects.find(function(p) { return p.name === decodedName; });
            if (!project) {
                alert('پروژه یافت نشد');
                return;
            }
            currentProjectPage = decodedName;
            document.getElementById('projectPageTitle').innerHTML = project.name;
            var descText = project.description || 'هنوز توضیحاتی ثبت نشده است';
            document.getElementById('projectPageDesc').innerHTML = escapeHtml(descText).replace(/\n/g, '<br>');

            var projectTasks = tasks.filter(function(t) { return t.project === decodedName; });
            var total = projectTasks.length;
            var completed = projectTasks.filter(function(t) { return t.done; }).length;
            var pending = total - completed;
            var percent = total > 0 ? Math.round((completed / total) * 100) : 0;

            document.getElementById('projectPageStats').innerHTML = 
                '<div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #43e97b;">' + toPersianNumbers(total) + '</div><div>کل تسک‌ها</div></div>' +
                '<div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #43e97b;">' + toPersianNumbers(completed) + '</div><div>انجام شده</div></div>' +
                '<div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #f5576c;">' + toPersianNumbers(pending) + '</div><div>در انتظار</div></div>' +
                '<div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #667eea;">' + toPersianNumbers(percent) + '%</div><div>پیشرفت</div></div>';

            if (projectTasks.length === 0) {
                document.getElementById('projectPageTasks').innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-light);">هیچ تسکی برای این پروژه وجود ندارد</div>';
            } else {
                document.getElementById('projectPageTasks').innerHTML = projectTasks.map(function(task) {
                    var progress = getTaskProgress(task.id);
                    var progressText = progress ? ' | پیشرفت: ' + progress.done + '/' + progress.total + ' (' + progress.percent + '%)' : '';
                    return '<div class="project-task-item"><a href="#" class="task-link" onclick="event.preventDefault();"><div class="task-title ' + (task.done ? 'done' : '') + '">' + escapeHtml(task.title) + (progress ? '<span style="font-size: 11px; color: var(--completed-date);"> (' + progress.percent + '%)</span>' : '') + '</div><div class="task-meta"><span class="icon icon-calendar-alt"></span> ' + formatDate(task.date) + ' - ' + formatTimeToPersian(task.time) + '<span style="background: rgba(245,87,108,0.15); color:#f5576c; padding: 2px 6px; border-radius: 10px; margin-right: 8px;"><span class="icon icon-tag"></span> ' + task.category + '</span>' + (task.parent_id ? '<span style="background: var(--badge-bg); color: var(--badge-color); padding: 2px 6px; border-radius: 10px;"><span class="icon icon-sitemap"></span> زیرتسک</span>' : '') + (task.description ? '<span style="color: var(--text-light); margin-right: 8px;"><span class="icon icon-align-left"></span> ' + escapeHtml(task.description.substring(0, 30)) + (task.description.length > 30 ? '...' : '') + '</span>' : '') + progressText + (task.completed_at ? '<span style="color: var(--completed-date); margin-right: 8px; font-size: 10px;"><span class="icon icon-check-circle"></span> ' + formatDateTime(task.completed_at) + '</span>' : '') + '</div></a><div class="task-actions"><span class="priority-badge priority-' + task.priority + '" style="font-size: 11px; padding: 2px 8px; border-radius: 10px;">' + (task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین') + '</span><input type="checkbox" ' + (task.done ? 'checked' : '') + ' onchange="toggleTaskFromProject(\'' + task.id + '\')" style="width: 22px; height: 22px; cursor: pointer; accent-color: #667eea;"></div></div>';
                }).join('');
            }
            document.getElementById('projectPageModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeProjectPageModal() {
            document.getElementById('projectPageModal').style.display = 'none';
            document.body.style.overflow = '';
            currentProjectPage = null;
        }

        function toggleTaskFromProject(id) {
            var task = tasks.find(function(t) { return t.id == id; });
            if (task) {
                toggleTask(id, task.done);
                if (currentProjectPage) {
                    openProjectPage(encodeURIComponent(currentProjectPage));
                }
            }
        }

        function editProjectDescription() {
            if (!currentProjectPage) return;
            var currentDesc = document.getElementById('projectPageDesc').innerText;
            var newDesc = prompt('توضیحات جدید را وارد کنید:', currentDesc);
            if (newDesc !== null && newDesc !== currentDesc) {
                sendRequest('update_project_description', {
                    name: currentProjectPage,
                    description: newDesc
                }).then(function() {
                    document.getElementById('projectPageDesc').innerHTML = escapeHtml(newDesc).replace(/\n/g, '<br>');
                    var project = projects.find(function(p) { return p.name === currentProjectPage; });
                    if (project) project.description = newDesc;
                });
            }
        }

        // ============================================
        // توابع فیلتر
        // ============================================
        function applyDateRange() {
            var fromDate = document.getElementById('filterDateFrom').value;
            var toDate = document.getElementById('filterDateTo').value;
            if (fromDate && toDate) {
                filters.dateFrom = fromDate;
                filters.dateTo = toDate;
                renderTasks();
            } else {
                alert('لطفاً هر دو تاریخ را انتخاب کنید');
            }
        }

        function clearFilters() {
            filters = { date: '', priority: '', category: '', project: '', dateFrom: '', dateTo: '' };
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            document.getElementById('filterPriority').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('filterProject').value = '';
            renderTasks();
        }

        function setupFilters() {
            var filtersCard = document.getElementById('filtersCard');
            if (currentFilter === 'completed') {
                filtersCard.style.display = 'block';
            } else {
                filtersCard.style.display = 'none';
                clearFilters();
            }
        }

        function renderAll() {
            setupFilters();
            renderTasks();
        }

        // ============================================
        // Event Listeners
        // ============================================
        document.getElementById('openAddTaskBtn')?.addEventListener('click', openAddTaskModal);
        document.getElementById('saveAddTaskBtn')?.addEventListener('click', addNewTask);
        document.getElementById('addCategoryBtn')?.addEventListener('click', addCategory);
        document.getElementById('addProjectBtn')?.addEventListener('click', addProject);
        document.getElementById('editProjectDescBtn')?.addEventListener('click', editProjectDescription);
        document.getElementById('exportCsvBtn')?.addEventListener('click', exportCSV);
        document.getElementById('applyDateRangeBtn')?.addEventListener('click', applyDateRange);
        document.getElementById('changePasswordBtn')?.addEventListener('click', changePassword);

        document.getElementById('gridViewBtn')?.addEventListener('click', function() { setView('grid'); });
        document.getElementById('listViewBtn')?.addEventListener('click', function() { setView('list'); });

        document.getElementById('filterPriority')?.addEventListener('change', function(e) {
            filters.priority = e.target.value;
            renderTasks();
        });
        document.getElementById('filterCategory')?.addEventListener('change', function(e) {
            filters.category = e.target.value;
            renderTasks();
        });
        document.getElementById('filterProject')?.addEventListener('change', function(e) {
            filters.project = e.target.value;
            renderTasks();
        });

        // ============================================
        // خروج و خروجی
        // ============================================
        function logout() {
            if (confirm('آیا از خروج مطمئن هستید؟')) {
                var formData = new FormData();
                formData.append('action', 'logout');
                fetch(window.location.href, { method: 'POST', body: formData }).then(function() {
                    location.href = '../index.php';
                });
            }
        }

        function exportCSV() {
            var formData = new FormData();
            formData.append('action', 'export_csv');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        var blob = new Blob(["\uFEFF" + result.data], { type: 'text/csv;charset=utf-8;' });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'tasks_' + new Date().toISOString().split('T')[0] + '.csv';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    } else {
                        alert('خطا در ایجاد خروجی CSV');
                    }
                })['catch'](function(e) {
                    console.error('خطا:', e);
                    alert('خطا در ارتباط با سرور');
                });
        }

        // ============================================
        // پروفایل
        // ============================================
        function openProfileModal() {
            document.getElementById('profileModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
            document.body.style.overflow = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmNewPassword').value = '';
            document.getElementById('passwordError').style.display = 'none';
        }

        function changePassword() {
            var newPassword = document.getElementById('newPassword').value;
            var confirmPassword = document.getElementById('confirmNewPassword').value;
            var errorDiv = document.getElementById('passwordError');

            if (!newPassword || !confirmPassword) {
                errorDiv.innerText = 'لطفاً رمز عبور جدید را وارد کنید';
                errorDiv.style.display = 'block';
                return;
            }
            if (newPassword.length < 4) {
                errorDiv.innerText = 'رمز عبور باید حداقل ۴ کاراکتر باشد';
                errorDiv.style.display = 'block';
                return;
            }
            if (newPassword !== confirmPassword) {
                errorDiv.innerText = 'رمز عبور و تکرار آن مطابقت ندارند';
                errorDiv.style.display = 'block';
                return;
            }

            var formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('new_password', newPassword);
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        alert('رمز عبور با موفقیت تغییر کرد');
                        closeProfileModal();
                    } else {
                        errorDiv.innerText = result.message || 'خطا در تغییر رمز عبور';
                        errorDiv.style.display = 'block';
                    }
                });
        }

        // ============================================
        // بستن مودال‌ها
        // ============================================
        window.onclick = function(event) {
            if (event.target === document.getElementById('addTaskModal')) {
                closeAddTaskModal();
                document.querySelector('#addTaskModal .modal-header').innerHTML = '<span class="icon icon-plus-circle"></span> افزودن کار جدید';
            }
            if (event.target === document.getElementById('editModal')) closeEditModal();
            if (event.target === document.getElementById('categoryModal')) closeCategoryModal();
            if (event.target === document.getElementById('projectModal')) closeProjectModal();
            if (event.target === document.getElementById('projectPageModal')) closeProjectPageModal();
            if (event.target === document.getElementById('profileModal')) closeProfileModal();
        };

        // ============================================
        // مقداردهی اولیه
        // ============================================
        if (currentView === 'grid') {
            document.getElementById('gridViewBtn')?.classList.add('active');
        } else {
            document.getElementById('listViewBtn')?.classList.add('active');
        }

        // فعال کردن دکمه امروز در منوی پلنر
        document.querySelector('.nav-filters .nav-btn[data-filter="today"]')?.classList.add('active');

        loadData();
    </script>
</body>
</html>