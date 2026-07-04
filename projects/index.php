<?php
// projects/index.php - صفحه مدیریت پروژه‌ها
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
$projectsFile = __DIR__ . '/../data/projects.json';
$tasksFile = __DIR__ . '/../data/tasks.json';

if (!file_exists($projectsFile)) {
    file_put_contents($projectsFile, json_encode([]));
}
if (!file_exists($tasksFile)) {
    file_put_contents($tasksFile, json_encode([]));
}

// ==================== توابع ====================
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

function getUserTasks($userId) {
    global $tasksFile;
    if (!file_exists($tasksFile)) return [];
    $allTasks = json_decode(file_get_contents($tasksFile), true);
    if (!is_array($allTasks)) $allTasks = [];
    return array_values(array_filter($allTasks, function($t) use ($userId) {
        return ($t['user_id'] ?? '') == $userId;
    }));
}

function saveAllTasks($tasks) {
    global $tasksFile;
    file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
}

function addUserProject($userId, $projectName, $description = '') {
    $projects = getUserProjects($userId);
    if (in_array($projectName, array_column($projects, 'name'))) return false;
    
    $newProject = [
        'id' => time() . rand(100, 999),
        'user_id' => $userId,
        'name' => htmlspecialchars(trim($projectName)),
        'description' => htmlspecialchars(trim($description)),
        'color' => '#' . dechex(rand(0x000000, 0xFFFFFF)),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $projects[] = $newProject;
    saveUserProjects($userId, $projects);
    return true;
}

function deleteUserProject($userId, $projectId) {
    $projects = getUserProjects($userId);
    $projectToDelete = null;
    $projectName = '';
    foreach ($projects as $p) {
        if ($p['id'] == $projectId) {
            $projectToDelete = $p;
            $projectName = $p['name'];
            break;
        }
    }
    if (!$projectToDelete) return false;
    
    $newProjects = array_values(array_filter($projects, function($p) use ($projectId) {
        return $p['id'] != $projectId;
    }));
    saveUserProjects($userId, $newProjects);
    
    // حذف پروژه از تسک‌ها
    $tasks = getUserTasks($userId);
    $changed = false;
    foreach ($tasks as &$task) {
        if (($task['project'] ?? '') === $projectName) {
            $task['project'] = '';
            $changed = true;
        }
    }
    if ($changed) {
        $allTasks = json_decode(file_get_contents($tasksFile), true);
        if (!is_array($allTasks)) $allTasks = [];
        foreach ($allTasks as &$t) {
            if (($t['user_id'] ?? '') == $userId && ($t['project'] ?? '') === $projectName) {
                $t['project'] = '';
            }
        }
        file_put_contents($tasksFile, json_encode($allTasks, JSON_PRETTY_PRINT));
    }
    return true;
}

function updateProject($userId, $projectId, $data) {
    $projects = getUserProjects($userId);
    foreach ($projects as &$p) {
        if ($p['id'] == $projectId) {
            if (isset($data['name'])) $p['name'] = htmlspecialchars(trim($data['name']));
            if (isset($data['description'])) $p['description'] = htmlspecialchars(trim($data['description']));
            if (isset($data['color'])) $p['color'] = $data['color'];
            $p['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    saveUserProjects($userId, $projects);
    return true;
}

function getProjectTasks($userId, $projectName) {
    $tasks = getUserTasks($userId);
    return array_values(array_filter($tasks, function($t) use ($projectName) {
        return ($t['project'] ?? '') == $projectName;
    }));
}

function getProjectProgress($tasks) {
    if (empty($tasks)) return ['total' => 0, 'done' => 0, 'pending' => 0, 'percent' => 0];
    $total = count($tasks);
    $done = count(array_filter($tasks, function($t) {
        return $t['done'] == true;
    }));
    $pending = $total - $done;
    return ['total' => $total, 'done' => $done, 'pending' => $pending, 'percent' => $total > 0 ? round(($done / $total) * 100) : 0];
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'load') {
        $projects = getUserProjects($userId);
        $allTasks = getUserTasks($userId);
        foreach ($projects as &$p) {
            $tasks = getProjectTasks($userId, $p['name']);
            $progress = getProjectProgress($tasks);
            $p['_tasks'] = array_slice($tasks, 0, 3);
            $p['_tasks_count'] = count($tasks);
            $p['_progress'] = $progress;
        }
        $response = ['success' => true, 'projects' => $projects];
    }
    elseif ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (empty($name)) {
            $response = ['success' => false, 'message' => 'نام پروژه الزامی است'];
        } else {
            $result = addUserProject($userId, $name, $description);
            if ($result) {
                $projects = getUserProjects($userId);
                $allTasks = getUserTasks($userId);
                foreach ($projects as &$p) {
                    $tasks = getProjectTasks($userId, $p['name']);
                    $progress = getProjectProgress($tasks);
                    $p['_tasks'] = array_slice($tasks, 0, 3);
                    $p['_tasks_count'] = count($tasks);
                    $p['_progress'] = $progress;
                }
                $response = ['success' => true, 'projects' => $projects];
            } else {
                $response = ['success' => false, 'message' => 'پروژه با این نام قبلاً وجود دارد'];
            }
        }
    }
    elseif ($action === 'edit') {
        $projectId = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '';
        if (empty($projectId)) {
            $response = ['success' => false, 'message' => 'شناسه پروژه نامعتبر است'];
        } else {
            $data = [];
            if (!empty($name)) $data['name'] = $name;
            $data['description'] = $description;
            if (!empty($color)) $data['color'] = $color;
            updateProject($userId, $projectId, $data);
            $projects = getUserProjects($userId);
            $allTasks = getUserTasks($userId);
            foreach ($projects as &$p) {
                $tasks = getProjectTasks($userId, $p['name']);
                $progress = getProjectProgress($tasks);
                $p['_tasks'] = array_slice($tasks, 0, 3);
                $p['_tasks_count'] = count($tasks);
                $p['_progress'] = $progress;
            }
            $response = ['success' => true, 'projects' => $projects];
        }
    }
    elseif ($action === 'delete') {
        $projectId = $_POST['id'] ?? '';
        if (empty($projectId)) {
            $response = ['success' => false, 'message' => 'شناسه پروژه نامعتبر است'];
        } else {
            $result = deleteUserProject($userId, $projectId);
            if ($result) {
                $projects = getUserProjects($userId);
                $allTasks = getUserTasks($userId);
                foreach ($projects as &$p) {
                    $tasks = getProjectTasks($userId, $p['name']);
                    $progress = getProjectProgress($tasks);
                    $p['_tasks'] = array_slice($tasks, 0, 3);
                    $p['_tasks_count'] = count($tasks);
                    $p['_progress'] = $progress;
                }
                $response = ['success' => true, 'projects' => $projects];
            } else {
                $response = ['success' => false, 'message' => 'خطا در حذف پروژه'];
            }
        }
    }
    elseif ($action === 'update_description') {
        $projectId = $_POST['id'] ?? '';
        $description = trim($_POST['description'] ?? '');
        if (empty($projectId)) {
            $response = ['success' => false, 'message' => 'شناسه پروژه نامعتبر است'];
        } else {
            updateProject($userId, $projectId, ['description' => $description]);
            $projects = getUserProjects($userId);
            $allTasks = getUserTasks($userId);
            foreach ($projects as &$p) {
                $tasks = getProjectTasks($userId, $p['name']);
                $progress = getProjectProgress($tasks);
                $p['_tasks'] = array_slice($tasks, 0, 3);
                $p['_tasks_count'] = count($tasks);
                $p['_progress'] = $progress;
            }
            $response = ['success' => true, 'projects' => $projects];
        }
    }
    elseif ($action === 'get_project_detail') {
        $projectId = $_POST['id'] ?? '';
        $projects = getUserProjects($userId);
        $project = null;
        foreach ($projects as $p) {
            if ($p['id'] == $projectId) {
                $project = $p;
                break;
            }
        }
        if (!$project) {
            $response = ['success' => false, 'message' => 'پروژه پیدا نشد'];
        } else {
            $tasks = getProjectTasks($userId, $project['name']);
            $progress = getProjectProgress($tasks);
            $response = [
                'success' => true,
                'project' => $project,
                'tasks' => $tasks,
                'progress' => $progress
            ];
        }
    }
    elseif ($action === 'toggle_task') {
        $taskId = $_POST['id'] ?? '';
        $done = $_POST['done'] ?? '0';
        
        if (empty($taskId)) {
            $response = ['success' => false, 'message' => 'شناسه تسک نامعتبر است'];
        } else {
            $allTasks = json_decode(file_get_contents($tasksFile), true);
            if (!is_array($allTasks)) $allTasks = [];
            
            $found = false;
            foreach ($allTasks as &$task) {
                if ($task['id'] == $taskId && ($task['user_id'] ?? '') == $userId) {
                    $task['done'] = ($done == '1');
                    $task['completed_at'] = ($done == '1') ? date('Y-m-d H:i:s') : null;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                file_put_contents($tasksFile, json_encode($allTasks, JSON_PRETTY_PRINT));
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'message' => 'تسک پیدا نشد'];
            }
        }
    }
    
    echo json_encode($response);
    exit;
}

$projects = getUserProjects($userId);
$allTasks = getUserTasks($userId);
foreach ($projects as &$p) {
    $tasks = getProjectTasks($userId, $p['name']);
    $progress = getProjectProgress($tasks);
    $p['_tasks'] = array_slice($tasks, 0, 3);
    $p['_tasks_count'] = count($tasks);
    $p['_progress'] = $progress;
}

// ==================== تنظیمات هدر ====================
$page_title = 'پروژه‌ها';
include_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* ===== استایل‌های پروژه‌ها ===== */
    .container { 
        max-width: 1200px; 
        margin: 0 auto; 
        padding: 0 20px 40px;
    }

    /* ===== آمار ===== */
    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        border: 1px solid var(--border-color);
    }

    .stat-number {
        font-size: 28px;
        font-weight: bold;
        color: #667eea;
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 13px;
        margin-top: 5px;
    }

    /* ===== دکمه افزودن ===== */
    .btn-add-project {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 10px 24px;
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

    .btn-add-project:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(102,126,234,0.4);
    }

    /* ===== پروژه‌ها ===== */
    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }

    .project-card {
        background: var(--bg-card);
        border-radius: 20px;
        border: 1px solid var(--border-color);
        padding: 24px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .project-card:hover {
        border-color: rgba(102,126,234,0.3);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px var(--shadow-color);
    }

    .project-color-bar {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .project-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
        padding-top: 4px;
    }

    .project-name {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        flex: 1;
    }

    .project-actions {
        display: flex;
        gap: 6px;
    }

    .project-actions button {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 6px;
        transition: all 0.3s;
        font-size: 14px;
    }

    .project-actions .edit-project-btn:hover {
        background: var(--badge-bg);
        color: #667eea;
    }

    .project-actions .delete-project-btn:hover {
        background: rgba(220,53,69,0.15);
        color: #ff6b6b;
    }

    .project-description {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 15px;
        word-break: break-word;
        min-height: 40px;
    }

    .project-description:empty::before {
        content: 'بدون توضیحات';
        color: var(--text-muted);
        font-style: italic;
        opacity: 0.5;
    }

    .project-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }

    .project-tasks-count {
        font-size: 13px;
        color: var(--text-muted);
    }

    .project-tasks-count .icon { color: #667eea; }

    .project-progress-container {
        flex: 1;
        min-width: 100px;
    }

    .project-progress-bar {
        width: 100%;
        height: 6px;
        background: var(--bg-input);
        border-radius: 10px;
        overflow: hidden;
    }

    .project-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 10px;
        transition: width 0.5s ease;
    }

    .project-progress-text {
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 4px;
        text-align: left;
    }

    .project-tasks-preview {
        margin-top: 15px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }

    .project-task-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 0;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .project-task-item .task-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .project-task-item .task-dot.done {
        background: #28a745;
    }

    .project-task-item .task-dot.pending {
        background: #ffc107;
    }

    .project-task-item .task-title-preview {
        flex: 1;
        word-break: break-word;
    }

    .project-task-item .task-title-preview.done {
        text-decoration: line-through;
        opacity: 0.6;
    }

    .project-view-all {
        display: inline-block;
        margin-top: 8px;
        color: #667eea;
        font-size: 13px;
        text-decoration: none;
        cursor: pointer;
    }

    .project-view-all:hover {
        text-decoration: underline;
    }

    /* ===== مودال هماهنگ با هدر ===== */
    .modal {
        display: none;
        position: fixed;
        z-index: 999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
    }

    .modal.show { display: flex; }

    .modal-content {
        background: var(--dropdown-bg);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 30px;
        max-width: 600px;
        width: 92%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
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
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .modal-header .icon {
        font-size: 24px;
        color: #667eea;
    }

    .modal-body label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 14px;
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
        background: var(--bg-input-hover);
        box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
    }

    .modal-body input::placeholder,
    .modal-body textarea::placeholder {
        color: var(--text-muted);
    }

    .modal-body textarea {
        resize: vertical;
        min-height: 80px;
    }

    .modal-body .color-picker {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .modal-body .color-picker .color-option {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: 3px solid transparent;
        cursor: pointer;
        transition: all 0.3s;
    }

    .modal-body .color-picker .color-option:hover {
        transform: scale(1.15);
    }

    .modal-body .color-picker .color-option.active {
        border-color: var(--text-primary);
        box-shadow: 0 0 15px rgba(102,126,234,0.4);
        transform: scale(1.1);
    }

    .modal-footer {
        display: flex;
        gap: 12px;
        margin-top: 16px;
    }

    .btn-cancel {
        background: var(--bg-input);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        padding: 12px 24px;
        border-radius: 12px;
        cursor: pointer;
        flex: 1;
        font-family: inherit;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
    }

    .btn-cancel:hover {
        background: var(--bg-card-hover);
        border-color: var(--text-muted);
    }

    .btn-save {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 12px;
        cursor: pointer;
        flex: 1;
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-save:hover {
        transform: scale(1.02);
        box-shadow: 0 5px 20px rgba(102,126,234,0.3);
    }

    /* ===== مودال جزئیات پروژه ===== */
    .project-stat-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px;
        text-align: center;
        border: 1px solid var(--border-color);
    }

    .project-stat-card .stat-number {
        font-size: 22px;
        font-weight: bold;
    }

    .project-stat-card .stat-label {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 4px;
    }

    .manage-btn {
        padding: 6px 16px;
        color: var(--text-secondary);
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
        font-weight: 500;
        font-family: inherit;
        background: var(--bg-card);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid var(--border-color);
    }

    .manage-btn:hover {
        background: var(--bg-card-hover);
        transform: scale(1.03);
    }

    .desc-edit-area {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        font-size: 14px;
        font-family: inherit;
        background: var(--bg-input);
        color: var(--text-primary);
        resize: vertical;
        min-height: 80px;
        display: none;
    }

    .desc-edit-area:focus {
        outline: none;
        border-color: #667eea;
        background: var(--bg-input-hover);
        box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
    }

    .project-task-item-full {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        margin-bottom: 8px;
        background: var(--bg-card);
        border-radius: 10px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .project-task-item-full:hover {
        background: var(--bg-card-hover);
        border-color: rgba(102,126,234,0.3);
    }

    .project-task-item-full .task-check {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #667eea;
        flex-shrink: 0;
    }

    .project-task-item-full .task-title-full {
        flex: 1;
        font-size: 14px;
        color: var(--text-secondary);
        word-break: break-word;
    }

    .project-task-item-full .task-title-full.done {
        text-decoration: line-through;
        opacity: 0.6;
    }

    .project-task-item-full .task-priority {
        font-size: 11px;
        padding: 2px 10px;
        border-radius: 10px;
        flex-shrink: 0;
    }

    .project-task-item-full .task-priority.high {
        background: rgba(220,53,69,0.2);
        color: #ff6b6b;
    }

    .project-task-item-full .task-priority.medium {
        background: rgba(255,193,7,0.2);
        color: #ffc107;
    }

    .project-task-item-full .task-priority.low {
        background: rgba(40,167,69,0.2);
        color: #28a745;
    }

    .project-task-item-full .task-date {
        font-size: 11px;
        color: var(--text-muted);
        flex-shrink: 0;
    }

    /* ===== Toast ===== */
    .toast {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: var(--dropdown-bg);
        color: var(--text-primary);
        padding: 14px 28px;
        border-radius: 14px;
        z-index: 9999999;
        opacity: 0;
        transition: all 0.4s ease;
        font-family: 'Vazirmatn', sans-serif;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        font-size: 14px;
    }

    .toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    .toast.success { 
        background: #28a745; 
        color: white;
        border-color: #28a745;
    }
    
    .toast.error { 
        background: #dc3545; 
        color: white;
        border-color: #dc3545;
    }

    /* ===== Empty State ===== */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
        grid-column: 1 / -1;
    }

    .empty-state .icon {
        font-size: 48px;
        margin-bottom: 16px;
        display: block;
    }

    .empty-state .title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    /* ===== Responsive ===== */
    @media (max-width: 768px) {
        .container { padding: 0 10px 20px; }
        .projects-grid { grid-template-columns: 1fr; }
        .modal-content { padding: 20px; }
        .project-task-item-full { flex-wrap: wrap; }
        .project-task-item-full .task-date { width: 100%; }
        .stats { grid-template-columns: repeat(2, 1fr); }
        .btn-add-project { width: 100%; justify-content: center; }
        .modal-footer { flex-direction: column; }
    }

    @media (max-width: 480px) {
        .project-name { font-size: 18px; }
        #projectPageStats { grid-template-columns: repeat(2, 1fr) !important; }
        .modal-content { padding: 16px; }
        .modal-header { font-size: 18px; }
    }
</style>

<div class="container">
    <!-- ===== آمار ===== -->
    <div class="stats" id="statsContainer">
        <div class="stat-card">
            <div class="stat-number" id="totalProjects">0</div>
            <div class="stat-label">کل پروژه‌ها</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="totalTasks">0</div>
            <div class="stat-label">کل تسک‌ها</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="completedTasks">0</div>
            <div class="stat-label">تسک‌های انجام شده</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="avgProgress">0%</div>
            <div class="stat-label">میانگین پیشرفت</div>
        </div>
    </div>

    <!-- ===== دکمه افزودن پروژه ===== -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
        <button class="btn-add-project" onclick="openAddProjectModal()">
            <span class="icon">➕</span> پروژه جدید
        </button>
    </div>

    <!-- ===== پروژه‌ها ===== -->
    <div class="projects-grid" id="projectsContainer"></div>
</div>

<!-- ===== مودال پروژه (افزودن/ویرایش) ===== -->
<div class="modal" id="projectModal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="icon">📁</span>
            <span id="projectModalTitle">پروژه جدید</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editProjectId">
            <label for="projectName">نام پروژه <span style="color: #f5576c;">*</span></label>
            <input type="text" id="projectName" placeholder="نام پروژه را وارد کنید..." required>
            
            <label for="projectDescription">توضیحات</label>
            <textarea id="projectDescription" placeholder="توضیحات پروژه (اختیاری)..." rows="3"></textarea>
            
            <label style="font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 6px;">رنگ پروژه:</label>
            <div class="color-picker" id="colorPicker">
                <div class="color-option active" style="background: #667eea;" data-color="#667eea"></div>
                <div class="color-option" style="background: #f5576c;" data-color="#f5576c"></div>
                <div class="color-option" style="background: #f093fb;" data-color="#f093fb"></div>
                <div class="color-option" style="background: #43e97b;" data-color="#43e97b"></div>
                <div class="color-option" style="background: #f9d423;" data-color="#f9d423"></div>
                <div class="color-option" style="background: #ff6b6b;" data-color="#ff6b6b"></div>
                <div class="color-option" style="background: #4ecdc4;" data-color="#4ecdc4"></div>
                <div class="color-option" style="background: #a8a8a8;" data-color="#a8a8a8"></div>
                <div class="color-option" style="background: #ff9ff3;" data-color="#ff9ff3"></div>
                <div class="color-option" style="background: #54a0ff;" data-color="#54a0ff"></div>
            </div>
            <input type="hidden" id="projectColor" value="#667eea">
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeProjectModal()">انصراف</button>
            <button class="btn-save" id="saveProjectBtn">✚ افزودن پروژه</button>
        </div>
    </div>
</div>

<!-- ===== مودال جزئیات پروژه ===== -->
<div class="modal" id="projectPageModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <span class="icon">📁</span>
            <span id="projectPageTitle">پروژه</span>
            <span id="projectPageColor" style="width: 14px; height: 14px; border-radius: 50%; display: inline-block; margin-right: 8px; border: 2px solid var(--border-color);"></span>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);">توضیحات پروژه:</label>
                <div id="projectPageDesc" style="background: var(--bg-input); padding: 15px; border-radius: 12px; min-height: 60px; white-space: pre-wrap; border: 1px solid var(--border-color); color: var(--text-secondary);">توضیحات ندارد</div>
                <textarea id="projectPageDescEdit" class="desc-edit-area" placeholder="توضیحات پروژه را وارد کنید..." rows="3"></textarea>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px;">
                    <button id="editProjectDescBtn" class="manage-btn" style="background: rgba(102,126,234,0.15); color:#667eea; border-color: rgba(102,126,234,0.2);">
                        <span class="icon">✏️</span> ویرایش توضیحات
                    </button>
                    <button id="saveProjectDescBtn" class="manage-btn" style="display: none; background: rgba(40,167,69,0.15); color:#28a745; border-color: rgba(40,167,69,0.2);">
                        <span class="icon">💾</span> ذخیره توضیحات
                    </button>
                    <button id="cancelProjectDescBtn" class="manage-btn" style="display: none; background: rgba(220,53,69,0.15); color:#ff6b6b; border-color: rgba(220,53,69,0.2);">
                        <span class="icon">❌</span> انصراف
                    </button>
                </div>
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);">آمار پروژه:</label>
                <div id="projectPageStats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;">
                    <div class="project-stat-card">
                        <div class="stat-number" style="color: #43e97b;" id="statTotal">0</div>
                        <div class="stat-label">کل تسک‌ها</div>
                    </div>
                    <div class="project-stat-card">
                        <div class="stat-number" style="color: #43e97b;" id="statDone">0</div>
                        <div class="stat-label">انجام شده</div>
                    </div>
                    <div class="project-stat-card">
                        <div class="stat-number" style="color: #f5576c;" id="statPending">0</div>
                        <div class="stat-label">در انتظار</div>
                    </div>
                    <div class="project-stat-card">
                        <div class="stat-number" style="color: #667eea;" id="statPercent">0%</div>
                        <div class="stat-label">پیشرفت</div>
                    </div>
                </div>
            </div>
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);">تسک‌های پروژه:</label>
                <div id="projectPageTasks" style="max-height: 350px; overflow-y: auto; padding-right: 4px;">
                    <div style="text-align:center; padding:30px; color:var(--text-muted);">هیچ تسکی برای این پروژه وجود ندارد</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeProjectPageModal()">بستن</button>
            <button class="btn-save" onclick="window.location.href='../planner/index.php?project=' + encodeURIComponent(currentProjectName)" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                <span class="icon">📋</span> مشاهده در پلنر
            </button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    let projects = [];
    let editProjectId = null;
    let selectedColor = '#667eea';
    let currentProjectId = null;
    let currentProjectName = '';
    let isEditingDesc = false;

    // ===== Toast =====
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast ' + type;
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        let div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== بارگذاری پروژه‌ها =====
    async function loadProjects() {
        try {
            let formData = new FormData();
            formData.append('action', 'load');
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                projects = result.projects || [];
                renderProjects();
                updateStats();
            }
        } catch(e) {
            console.error(e);
            showToast('خطا در بارگذاری پروژه‌ها', 'error');
        }
    }

    // ===== رندر پروژه‌ها =====
    function renderProjects() {
        const container = document.getElementById('projectsContainer');
        
        if (!projects || projects.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="icon">📁</span>
                    <div class="title">هیچ پروژه‌ای وجود ندارد</div>
                    <div style="font-size: 14px; margin-top: 8px;">با کلیک روی دکمه "پروژه جدید" شروع کنید</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = projects.map(project => {
            const progress = project._progress || { total: 0, done: 0, percent: 0 };
            const tasks = project._tasks_count || 0;
            const color = project.color || '#667eea';
            const previewTasks = project._tasks || [];
            
            return `
                <div class="project-card" onclick="openProjectPage('${project.id}')">
                    <div class="project-color-bar" style="background: ${color};"></div>
                    <div class="project-header">
                        <div class="project-name">${escapeHtml(project.name)}</div>
                        <div class="project-actions" onclick="event.stopPropagation();">
                            <button class="edit-project-btn" onclick="openEditProjectModal('${project.id}')" title="ویرایش پروژه">
                                <span class="icon">✏️</span>
                            </button>
                            <button class="delete-project-btn" onclick="deleteProject('${project.id}')" title="حذف پروژه">
                                <span class="icon">🗑️</span>
                            </button>
                        </div>
                    </div>
                    <div class="project-description">${escapeHtml(project.description || '')}</div>
                    <div class="project-meta">
                        <span class="project-tasks-count"><span class="icon">📋</span> ${tasks} تسک</span>
                        <div class="project-progress-container">
                            <div class="project-progress-bar">
                                <div class="project-progress-fill" style="width: ${progress.percent}%;"></div>
                            </div>
                            <div class="project-progress-text">${progress.done} از ${progress.total} انجام شده (${progress.percent}%)</div>
                        </div>
                    </div>
                    <div class="project-tasks-preview">
                        ${previewTasks.length > 0 ? 
                            previewTasks.slice(0, 3).map(task => `
                                <div class="project-task-item">
                                    <span class="task-dot ${task.done ? 'done' : 'pending'}"></span>
                                    <span class="task-title-preview ${task.done ? 'done' : ''}">${escapeHtml(task.title)}</span>
                                </div>
                            `).join('') :
                            '<div style="color: var(--text-muted); font-size: 13px;">هیچ تسکی در این پروژه وجود ندارد</div>'
                        }
                        ${tasks > 3 ? `<div class="project-view-all" onclick="event.stopPropagation(); window.location.href='../planner/index.php?project=${encodeURIComponent(project.name)}'">مشاهده همه ${tasks} تسک...</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    // ===== بروزرسانی آمار =====
    function updateStats() {
        const totalProjects = projects.length;
        let totalTasks = 0;
        let completedTasks = 0;
        let totalProgress = 0;
        
        projects.forEach(p => {
            const progress = p._progress || { total: 0, done: 0, percent: 0 };
            totalTasks += progress.total;
            completedTasks += progress.done;
            totalProgress += progress.percent;
        });
        
        const avgProgress = totalProjects > 0 ? Math.round(totalProgress / totalProjects) : 0;
        
        document.getElementById('totalProjects').textContent = totalProjects;
        document.getElementById('totalTasks').textContent = totalTasks;
        document.getElementById('completedTasks').textContent = completedTasks;
        document.getElementById('avgProgress').textContent = avgProgress + '%';
    }

    // ===== صفحه جزئیات پروژه =====
    async function openProjectPage(projectId) {
        try {
            let formData = new FormData();
            formData.append('action', 'get_project_detail');
            formData.append('id', projectId);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                currentProjectId = projectId;
                currentProjectName = result.project.name;
                
                document.getElementById('projectPageTitle').textContent = result.project.name;
                document.getElementById('projectPageColor').style.background = result.project.color || '#667eea';
                
                // توضیحات
                const desc = result.project.description || 'توضیحات ندارد';
                document.getElementById('projectPageDesc').textContent = desc;
                document.getElementById('projectPageDescEdit').value = result.project.description || '';
                
                // آمار
                const progress = result.progress;
                document.getElementById('statTotal').textContent = progress.total;
                document.getElementById('statDone').textContent = progress.done;
                document.getElementById('statPending').textContent = progress.pending;
                document.getElementById('statPercent').textContent = progress.percent + '%';
                
                // تسک‌ها
                const tasksContainer = document.getElementById('projectPageTasks');
                if (result.tasks && result.tasks.length > 0) {
                    tasksContainer.innerHTML = result.tasks.map(task => `
                        <div class="project-task-item-full">
                            <input type="checkbox" class="task-check" ${task.done ? 'checked' : ''} 
                                   onchange="toggleTaskStatus('${task.id}', this.checked)">
                            <span class="task-title-full ${task.done ? 'done' : ''}">${escapeHtml(task.title)}</span>
                            <span class="task-priority ${task.priority || 'medium'}">${task.priority === 'high' ? '🔴' : task.priority === 'medium' ? '🟡' : '🟢'}</span>
                            <span class="task-date">${task.date || ''}</span>
                        </div>
                    `).join('');
                } else {
                    tasksContainer.innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-muted);">هیچ تسکی برای این پروژه وجود ندارد</div>';
                }
                
                // ریست حالت ویرایش توضیحات
                isEditingDesc = false;
                document.getElementById('projectPageDesc').style.display = 'block';
                document.getElementById('projectPageDescEdit').style.display = 'none';
                document.getElementById('editProjectDescBtn').style.display = 'inline-flex';
                document.getElementById('saveProjectDescBtn').style.display = 'none';
                document.getElementById('cancelProjectDescBtn').style.display = 'none';
                
                document.getElementById('projectPageModal').classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                showToast(result.message || 'خطا در بارگذاری پروژه', 'error');
            }
        } catch(e) {
            console.error(e);
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    function closeProjectPageModal() {
        document.getElementById('projectPageModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    // ===== ویرایش توضیحات پروژه =====
    document.getElementById('editProjectDescBtn')?.addEventListener('click', function() {
        isEditingDesc = true;
        document.getElementById('projectPageDesc').style.display = 'none';
        document.getElementById('projectPageDescEdit').style.display = 'block';
        this.style.display = 'none';
        document.getElementById('saveProjectDescBtn').style.display = 'inline-flex';
        document.getElementById('cancelProjectDescBtn').style.display = 'inline-flex';
        document.getElementById('projectPageDescEdit').focus();
    });

    document.getElementById('cancelProjectDescBtn')?.addEventListener('click', function() {
        isEditingDesc = false;
        document.getElementById('projectPageDesc').style.display = 'block';
        document.getElementById('projectPageDescEdit').style.display = 'none';
        document.getElementById('editProjectDescBtn').style.display = 'inline-flex';
        this.style.display = 'none';
        document.getElementById('saveProjectDescBtn').style.display = 'none';
        document.getElementById('projectPageDescEdit').value = document.getElementById('projectPageDesc').textContent;
    });

    document.getElementById('saveProjectDescBtn')?.addEventListener('click', async function() {
        const description = document.getElementById('projectPageDescEdit').value.trim();
        
        try {
            let formData = new FormData();
            formData.append('action', 'update_description');
            formData.append('id', currentProjectId);
            formData.append('description', description);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                projects = result.projects;
                renderProjects();
                updateStats();
                
                document.getElementById('projectPageDesc').textContent = description || 'توضیحات ندارد';
                document.getElementById('projectPageDesc').style.display = 'block';
                document.getElementById('projectPageDescEdit').style.display = 'none';
                document.getElementById('editProjectDescBtn').style.display = 'inline-flex';
                this.style.display = 'none';
                document.getElementById('cancelProjectDescBtn').style.display = 'none';
                isEditingDesc = false;
                
                showToast('توضیحات پروژه ذخیره شد', 'success');
            } else {
                showToast(result.message || 'خطا در ذخیره توضیحات', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        }
    });

    // ===== تغییر وضعیت تسک =====
    async function toggleTaskStatus(taskId, done) {
        try {
            let formData = new FormData();
            formData.append('action', 'toggle_task');
            formData.append('id', taskId);
            formData.append('done', done ? '1' : '0');
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                await openProjectPage(currentProjectId);
                showToast('وضعیت تسک تغییر کرد', 'success');
            } else {
                showToast(result.message || 'خطا در تغییر وضعیت تسک', 'error');
            }
        } catch(e) {
            console.error(e);
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    // ===== مدیریت رنگ =====
    function initColorPicker() {
        const container = document.getElementById('colorPicker');
        if (!container) return;
        
        container.querySelectorAll('.color-option').forEach(el => {
            el.addEventListener('click', function() {
                container.querySelectorAll('.color-option').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('projectColor').value = this.dataset.color;
                selectedColor = this.dataset.color;
            });
        });
    }

    // ===== مدیریت پروژه =====
    function openAddProjectModal() {
        editProjectId = null;
        document.getElementById('projectModalTitle').textContent = 'پروژه جدید';
        document.getElementById('editProjectId').value = '';
        document.getElementById('projectName').value = '';
        document.getElementById('projectDescription').value = '';
        document.getElementById('projectColor').value = '#667eea';
        selectedColor = '#667eea';
        document.querySelectorAll('#colorPicker .color-option').forEach(b => b.classList.remove('active'));
        document.querySelector('#colorPicker .color-option[data-color="#667eea"]')?.classList.add('active');
        document.getElementById('saveProjectBtn').innerHTML = '✚ افزودن پروژه';
        document.getElementById('projectModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('projectName').focus(), 150);
    }

    function openEditProjectModal(id) {
        const project = projects.find(p => p.id == id);
        if (!project) return;
        
        editProjectId = id;
        document.getElementById('projectModalTitle').textContent = 'ویرایش پروژه';
        document.getElementById('editProjectId').value = id;
        document.getElementById('projectName').value = project.name;
        document.getElementById('projectDescription').value = project.description || '';
        const color = project.color || '#667eea';
        document.getElementById('projectColor').value = color;
        selectedColor = color;
        document.querySelectorAll('#colorPicker .color-option').forEach(b => b.classList.remove('active'));
        document.querySelector(`#colorPicker .color-option[data-color="${color}"]`)?.classList.add('active');
        document.getElementById('saveProjectBtn').innerHTML = '💾 ذخیره تغییرات';
        document.getElementById('projectModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('projectName').focus(), 150);
    }

    function closeProjectModal() {
        document.getElementById('projectModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    async function saveProject() {
        const name = document.getElementById('projectName').value.trim();
        if (!name) {
            showToast('لطفاً نام پروژه را وارد کنید', 'error');
            return;
        }
        
        const description = document.getElementById('projectDescription').value.trim();
        const color = document.getElementById('projectColor').value || '#667eea';
        const id = document.getElementById('editProjectId').value;
        const action = id ? 'edit' : 'add';
        
        const btn = document.getElementById('saveProjectBtn');
        btn.disabled = true;
        btn.innerHTML = '⏳ در حال ذخیره...';
        
        try {
            let formData = new FormData();
            formData.append('action', action);
            if (id) formData.append('id', id);
            formData.append('name', name);
            formData.append('description', description);
            formData.append('color', color);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                projects = result.projects;
                renderProjects();
                updateStats();
                closeProjectModal();
                showToast(id ? 'پروژه ویرایش شد' : 'پروژه اضافه شد', 'success');
            } else {
                showToast(result.message || 'خطا در ذخیره پروژه', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = id ? '💾 ذخیره تغییرات' : '✚ افزودن پروژه';
        }
    }

    async function deleteProject(id) {
        const project = projects.find(p => p.id == id);
        if (!project) return;
        if (!confirm(`آیا از حذف پروژه "${project.name}" و جدا شدن تسک‌های آن مطمئن هستید؟`)) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                projects = result.projects;
                renderProjects();
                updateStats();
                showToast('پروژه حذف شد', 'success');
            } else {
                showToast(result.message || 'خطا در حذف پروژه', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    // ===== Event Listeners =====
    document.getElementById('saveProjectBtn').addEventListener('click', saveProject);
    
    document.getElementById('projectModal').addEventListener('click', function(e) {
        if (e.target === this) closeProjectModal();
    });
    
    document.getElementById('projectPageModal').addEventListener('click', function(e) {
        if (e.target === this) closeProjectPageModal();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeProjectModal();
            closeProjectPageModal();
        }
    });

    // ===== شروع =====
    loadProjects();
    initColorPicker();
</script>

</body>
</html>