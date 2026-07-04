<?php
// planner/task_detail.php - نمایش جزئیات تسک
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

// ==================== فایل‌های دیتا ====================
$dataFile = __DIR__ . '/../data/tasks.json';
$usersFile = __DIR__ . '/../data/users.json';
$categoriesFile = __DIR__ . '/../data/categories.json';
$projectsFile = __DIR__ . '/../data/projects.json';

// ==================== توابع ====================
function getAllTasks() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $tasks = json_decode(file_get_contents($dataFile), true);
    return is_array($tasks) ? $tasks : [];
}

function getTaskById($id) {
    $tasks = getAllTasks();
    foreach ($tasks as $task) {
        if ($task['id'] == $id) {
            return $task;
        }
    }
    return null;
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

function getCategories() {
    global $categoriesFile;
    if (!file_exists($categoriesFile)) return [];
    $cats = json_decode(file_get_contents($categoriesFile), true);
    return is_array($cats) ? $cats : [];
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

$taskId = $_GET['id'] ?? '';
$task = getTaskById($taskId);

if (!$task) {
    header('Location: index.php');
    exit;
}

// چک کن که تسک متعلق به کاربر فعلی هست
if (($task['user_id'] ?? '') != $_SESSION['user_id']) {
    header('Location: index.php');
    exit;
}

$allTasks = getAllTasks();
$children = getTaskChildren($taskId, $allTasks);
$progress = calculateTaskProgress($taskId, $allTasks);
$categories = getCategories();
$projects = getUserProjects($_SESSION['user_id']);

// پیدا کردن تسک والد
$parentTask = null;
if (!empty($task['parent_id'])) {
    $parentTask = getTaskById($task['parent_id']);
}

$currentDate = date('Y-m-d');
$todayTehran = date('Y-m-d');
$tomorrowTehran = date('Y-m-d', strtotime('+1 days'));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات تسک | <?php echo htmlspecialchars($task['title'] ?? 'بدون عنوان'); ?></title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 800px; margin: 0 auto; }
        
        .header {
            background: white;
            border-radius: 20px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 22px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header h1 i { color: #667eea; }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
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
        
        .back-btn:hover { background: #5a6268; transform: scale(1.02); }
        
        .edit-btn-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .edit-btn-header:hover { transform: scale(1.02); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h2 i { color: #667eea; }
        
        .task-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .task-title.done { text-decoration: line-through; opacity: 0.6; }
        
        .task-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        
        .task-meta .tag {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .tag-category { background: #f0e6ff; color: #7b2cbf; }
        .tag-project { background: #e6f7e6; color: #2d6a4f; }
        .tag-priority-high { background: #f8d7da; color: #721c24; }
        .tag-priority-medium { background: #fff3cd; color: #856404; }
        .tag-priority-low { background: #d4edda; color: #155724; }
        .tag-date { background: #e7f3ff; color: #0066cc; }
        .tag-time { background: #e7f3ff; color: #0066cc; }
        .tag-status-done { background: #d4edda; color: #155724; }
        .tag-status-pending { background: #fff3cd; color: #856404; }
        
        .task-description {
            font-size: 15px;
            color: #555;
            line-height: 1.8;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 15px 0;
            white-space: pre-wrap;
        }
        
        .progress-section {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .progress-bar-container {
            background: #e9ecef;
            border-radius: 20px;
            height: 8px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 20px;
            transition: width 0.5s ease;
        }
        
        .subtask-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .subtask-item:last-child { border-bottom: none; }
        
        .subtask-item .check {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .subtask-item .title {
            flex: 1;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .subtask-item .title.done { text-decoration: line-through; opacity: 0.6; }
        
        .subtask-item .link-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 14px;
            padding: 4px 8px;
        }
        
        .subtask-item .link-btn:hover { background: #f0f0f0; border-radius: 6px; }
        
        .parent-info {
            background: #f0f0f0;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .parent-info a { color: #667eea; text-decoration: none; }
        .parent-info a:hover { text-decoration: underline; }
        
        .created-at {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; }
            .header-actions { flex-direction: column; }
            .header-actions a, .header-actions button { width: 100%; justify-content: center; }
            .task-title { font-size: 20px; }
            .task-meta { gap: 6px; }
            .task-meta .tag { font-size: 11px; padding: 3px 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- هدر -->
        <div class="header">
            <h1><i class="fas fa-tasks"></i> جزئیات تسک</h1>
            <div class="header-actions">
                <a href="index.php" class="back-btn"><i class="fas fa-arrow-right"></i> بازگشت</a>
                <a href="index.php?edit=<?php echo $task['id']; ?>" class="edit-btn-header">
                    <i class="fas fa-edit"></i> ویرایش
                </a>
            </div>
        </div>
        
        <!-- اطلاعات تسک -->
        <div class="card">
            <div class="task-title <?php echo ($task['done'] ?? false) ? 'done' : ''; ?>">
                <?php echo htmlspecialchars($task['title'] ?? 'بدون عنوان'); ?>
            </div>
            
            <?php if ($parentTask): ?>
                <div class="parent-info">
                    <i class="fas fa-level-up-alt"></i> زیرتسک برای: 
                    <a href="task_detail.php?id=<?php echo $parentTask['id']; ?>">
                        <?php echo htmlspecialchars($parentTask['title']); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="task-meta">
                <span class="tag tag-category"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($task['category'] ?? 'بدون دسته'); ?></span>
                
                <?php if (!empty($task['project'])): ?>
                    <span class="tag tag-project"><i class="fas fa-project-diagram"></i> <?php echo htmlspecialchars($task['project']); ?></span>
                <?php endif; ?>
                
                <span class="tag tag-priority-<?php echo $task['priority'] ?? 'medium'; ?>">
                    <i class="fas fa-flag"></i> 
                    <?php echo $task['priority'] === 'high' ? 'اولویت بالا' : ($task['priority'] === 'medium' ? 'اولویت متوسط' : 'اولویت پایین'); ?>
                </span>
                
                <span class="tag tag-date"><i class="far fa-calendar-alt"></i> <?php echo $task['date'] ?? ''; ?></span>
                
                <span class="tag tag-time"><i class="far fa-clock"></i> <?php echo $task['time'] ?? '12:00'; ?></span>
                
                <span class="tag <?php echo ($task['done'] ?? false) ? 'tag-status-done' : 'tag-status-pending'; ?>">
                    <i class="fas <?php echo ($task['done'] ?? false) ? 'fa-check-circle' : 'fa-hourglass-half'; ?>"></i>
                    <?php echo ($task['done'] ?? false) ? 'انجام شده' : 'در انتظار'; ?>
                </span>
            </div>
            
            <?php if ($task['completed_at'] ?? false): ?>
                <div style="font-size: 13px; color: #28a745; margin-bottom: 10px;">
                    <i class="fas fa-check-circle"></i> انجام شده در: <?php echo date('Y-m-d H:i', strtotime($task['completed_at'])); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($task['description'])): ?>
                <div class="task-description">
                    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                </div>
            <?php endif; ?>
            
            <!-- منبع تسک (لایف‌پلن) -->
            <?php if (!empty($task['source']) && $task['source'] === 'lifeplan'): ?>
                <div style="font-size: 13px; color: #f5576c; margin-top: 10px; padding: 10px; background: #fef0f0; border-radius: 10px;">
                    <i class="fas fa-compass"></i> این تسک از <strong>لایف‌پلن</strong> ایجاد شده است
                    <?php if (!empty($task['source_id'])): ?>
                        <a href="../lifeplan/index.php" style="color: #667eea; text-decoration: none; margin-right: 10px;">
                            <i class="fas fa-external-link-alt"></i> مشاهده در لایف‌پلن
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="created-at">
                <i class="far fa-calendar-plus"></i> ایجاد شده در: <?php echo date('Y-m-d H:i', strtotime($task['created_at'] ?? 'now')); ?>
            </div>
        </div>
        
        <!-- پیشرفت -->
        <?php if ($progress !== null): ?>
            <div class="card">
                <h2><i class="fas fa-chart-line"></i> پیشرفت</h2>
                <div class="progress-section">
                    <div style="display: flex; justify-content: space-between; font-size: 14px;">
                        <span><?php echo $progress['done']; ?> از <?php echo $progress['total']; ?> زیرتسک انجام شده</span>
                        <span><?php echo $progress['percent']; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $progress['percent']; ?>%;"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- زیرتسک‌ها -->
        <div class="card">
            <h2><i class="fas fa-sitemap"></i> زیرتسک‌ها (<?php echo count($children); ?>)</h2>
            
            <?php if (empty($children)): ?>
                <div style="text-align:center; padding:20px; color:#999;">
                    <i class="fas fa-inbox" style="font-size: 24px; display:block; margin-bottom:10px;"></i>
                    هیچ زیرتسکی برای این تسک وجود ندارد
                </div>
            <?php else: ?>
                <?php foreach ($children as $child): ?>
                    <div class="subtask-item">
                        <input type="checkbox" class="check" <?php echo ($child['done'] ?? false) ? 'checked' : ''; ?> 
                               onchange="location.href='index.php?toggle=<?php echo $child['id']; ?>'">
                        <span class="title <?php echo ($child['done'] ?? false) ? 'done' : ''; ?>">
                            <?php echo htmlspecialchars($child['title']); ?>
                        </span>
                        <a href="task_detail.php?id=<?php echo $child['id']; ?>" class="link-btn">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div style="margin-top: 15px;">
                <a href="index.php?add_subtask=<?php echo $task['id']; ?>" class="edit-btn-header" style="display: inline-flex; padding: 8px 16px; font-size: 13px;">
                    <i class="fas fa-plus"></i> افزودن زیرتسک جدید
                </a>
            </div>
        </div>
    </div>
</body>
</html>