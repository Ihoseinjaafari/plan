<?php
// lifeplan/index.php - لایف‌پلن با ساختار گروه‌بندی و ویرایش
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
$lifeplanFile = __DIR__ . '/lifeplan_data.json';
$tasksFile = __DIR__ . '/../data/tasks.json';
$categoriesFile = __DIR__ . '/../data/categories.json';
$projectsFile = __DIR__ . '/../data/projects.json';

if (!file_exists($lifeplanFile)) {
    file_put_contents($lifeplanFile, json_encode([]));
}

// ==================== توابع ====================
function getLifePlanGroups($userId) {
    global $lifeplanFile;
    $allData = json_decode(file_get_contents($lifeplanFile), true);
    if (!is_array($allData)) return [];
    $groups = array_values(array_filter($allData, function($g) use ($userId) {
        return ($g['user_id'] ?? '') == $userId;
    }));
    usort($groups, function($a, $b) {
        return ($a['order'] ?? 0) - ($b['order'] ?? 0);
    });
    return $groups;
}

function saveLifePlanGroups($userId, $groups) {
    global $lifeplanFile;
    $allData = json_decode(file_get_contents($lifeplanFile), true);
    if (!is_array($allData)) $allData = [];
    
    $allData = array_values(array_filter($allData, function($g) use ($userId) {
        return ($g['user_id'] ?? '') != $userId;
    }));
    
    foreach ($groups as &$group) {
        $group['user_id'] = $userId;
    }
    
    $allData = array_merge($allData, $groups);
    file_put_contents($lifeplanFile, json_encode($allData, JSON_PRETTY_PRINT));
}

function getCategories() {
    global $categoriesFile;
    if (!file_exists($categoriesFile)) return ['کار شخصی', 'کار اداری', 'یادگیری', 'ورزش', 'خرید'];
    $cats = json_decode(file_get_contents($categoriesFile), true);
    return is_array($cats) ? $cats : ['کار شخصی', 'کار اداری', 'یادگیری', 'ورزش', 'خرید'];
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

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'load') {
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'add_group') {
        $groups = getLifePlanGroups($userId);
        $newGroup = [
            'id' => time() . rand(100, 999),
            'user_id' => $userId,
            'title' => htmlspecialchars(trim($_POST['title'] ?? 'گروه جدید')),
            'subtitle' => htmlspecialchars(trim($_POST['subtitle'] ?? '')),
            'order' => count($groups),
            'created_at' => date('Y-m-d H:i:s'),
            'cards' => []
        ];
        $groups[] = $newGroup;
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'edit_group') {
        $editId = $_POST['id'] ?? '';
        $groups = getLifePlanGroups($userId);
        foreach ($groups as &$group) {
            if ($group['id'] == $editId) {
                if (isset($_POST['title'])) $group['title'] = htmlspecialchars(trim($_POST['title']));
                if (isset($_POST['subtitle'])) $group['subtitle'] = htmlspecialchars(trim($_POST['subtitle']));
                break;
            }
        }
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'delete_group') {
        $deleteId = $_POST['id'] ?? '';
        $groups = getLifePlanGroups($userId);
        $groups = array_values(array_filter($groups, function($g) use ($deleteId) {
            return $g['id'] != $deleteId;
        }));
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'add_card') {
        $groupId = $_POST['group_id'] ?? '';
        $groups = getLifePlanGroups($userId);
        foreach ($groups as &$group) {
            if ($group['id'] == $groupId) {
                $newCard = [
                    'id' => time() . rand(100, 999),
                    'title' => htmlspecialchars(trim($_POST['title'] ?? 'کارت جدید')),
                    'icon' => $_POST['icon'] ?? 'fa-file-lines',
                    'items' => [],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $group['cards'][] = $newCard;
                break;
            }
        }
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'edit_card') {
        $editCardId = $_POST['id'] ?? '';
        $groups = getLifePlanGroups($userId);
        foreach ($groups as &$group) {
            foreach ($group['cards'] as &$card) {
                if ($card['id'] == $editCardId) {
                    if (isset($_POST['title'])) $card['title'] = htmlspecialchars(trim($_POST['title']));
                    if (isset($_POST['icon'])) $card['icon'] = $_POST['icon'];
                    break 2;
                }
            }
        }
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'delete_card') {
        $deleteCardId = $_POST['id'] ?? '';
        $groups = getLifePlanGroups($userId);
        foreach ($groups as &$group) {
            $group['cards'] = array_values(array_filter($group['cards'], function($c) use ($deleteCardId) {
                return $c['id'] != $deleteCardId;
            }));
        }
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'reorder_groups') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        $groups = getLifePlanGroups($userId);
        $newGroups = [];
        foreach ($ids as $index => $id) {
            foreach ($groups as $group) {
                if ($group['id'] == $id) {
                    $group['order'] = $index;
                    $newGroups[] = $group;
                    break;
                }
            }
        }
        saveLifePlanGroups($userId, $newGroups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'add_item') {
        $cardId = $_POST['card_id'] ?? '';
        $itemText = htmlspecialchars(trim($_POST['item_text'] ?? ''));
        $itemLink = htmlspecialchars(trim($_POST['item_link'] ?? ''));
        
        if (empty($itemText)) {
            $response = ['success' => false, 'message' => 'متن آیتم الزامی است'];
            echo json_encode($response);
            exit;
        }
        
        $groups = getLifePlanGroups($userId);
        $found = false;
        foreach ($groups as &$group) {
            foreach ($group['cards'] as &$card) {
                if ($card['id'] == $cardId) {
                    $card['items'][] = [
                        'text' => $itemText,
                        'link' => $itemLink
                    ];
                    $found = true;
                    break 2;
                }
            }
        }
        
        if ($found) {
            saveLifePlanGroups($userId, $groups);
            $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
        } else {
            $response = ['success' => false, 'message' => 'کارت پیدا نشد'];
        }
    }
    elseif ($action === 'delete_item') {
        $cardId = $_POST['card_id'] ?? '';
        $itemIndex = intval($_POST['index'] ?? -1);
        $groups = getLifePlanGroups($userId);
        $found = false;
        foreach ($groups as &$group) {
            foreach ($group['cards'] as &$card) {
                if ($card['id'] == $cardId && isset($card['items'][$itemIndex])) {
                    array_splice($card['items'], $itemIndex, 1);
                    $found = true;
                    break 2;
                }
            }
        }
        if ($found) {
            saveLifePlanGroups($userId, $groups);
            $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
        } else {
            $response = ['success' => false, 'message' => 'آیتم پیدا نشد'];
        }
    }
    elseif ($action === 'edit_item') {
        $cardId = $_POST['card_id'] ?? '';
        $itemIndex = intval($_POST['index'] ?? -1);
        $itemText = htmlspecialchars(trim($_POST['item_text'] ?? ''));
        $itemLink = htmlspecialchars(trim($_POST['item_link'] ?? ''));
        
        if (empty($itemText)) {
            $response = ['success' => false, 'message' => 'متن آیتم الزامی است'];
            echo json_encode($response);
            exit;
        }
        
        $groups = getLifePlanGroups($userId);
        $found = false;
        foreach ($groups as &$group) {
            foreach ($group['cards'] as &$card) {
                if ($card['id'] == $cardId && isset($card['items'][$itemIndex])) {
                    $card['items'][$itemIndex] = [
                        'text' => $itemText,
                        'link' => $itemLink
                    ];
                    $found = true;
                    break 2;
                }
            }
        }
        if ($found) {
            saveLifePlanGroups($userId, $groups);
            $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
        } else {
            $response = ['success' => false, 'message' => 'آیتم پیدا نشد'];
        }
    }
    elseif ($action === 'convert_to_task') {
        $title = trim($_POST['title'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        $time = $_POST['time'] ?? '12:00';
        $priority = $_POST['priority'] ?? 'medium';
        $category = $_POST['category'] ?? 'لایف‌پلن';
        $project = $_POST['project'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $cardId = $_POST['card_id'] ?? '';
        
        if (empty($title)) {
            $response = ['success' => false, 'message' => 'عنوان تسک الزامی است'];
            echo json_encode($response);
            exit;
        }
        
        $tasks = [];
        if (file_exists($tasksFile)) {
            $tasks = json_decode(file_get_contents($tasksFile), true);
            if (!is_array($tasks)) $tasks = [];
        }
        
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
            'completed_at' => null,
            'source' => 'lifeplan',
            'source_id' => $cardId
        ];
        
        $tasks[] = $newTask;
        file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
        
        $response = ['success' => true, 'message' => 'تسک با موفقیت به Planner اضافه شد'];
    }
    elseif ($action === 'convert_to_project') {
        $cardId = $_POST['card_id'] ?? '';
        
        // پیدا کردن کارت مورد نظر
        $groups = getLifePlanGroups($userId);
        $cardData = null;
        $groupTitle = '';
        $groupIndex = -1;
        $cardIndex = -1;
        
        foreach ($groups as $gIndex => $group) {
            foreach ($group['cards'] as $cIndex => $card) {
                if ($card['id'] == $cardId) {
                    $cardData = $card;
                    $groupTitle = $group['title'];
                    $groupIndex = $gIndex;
                    $cardIndex = $cIndex;
                    break 2;
                }
            }
        }
        
        if (!$cardData) {
            $response = ['success' => false, 'message' => 'کارت پیدا نشد'];
            echo json_encode($response);
            exit;
        }
        
        // ===== ایجاد پروژه جدید =====
        $projects = [];
        if (file_exists($projectsFile)) {
            $projects = json_decode(file_get_contents($projectsFile), true);
            if (!is_array($projects)) $projects = [];
        }
        
        $newProject = [
            'id' => time() . rand(100, 999),
            'user_id' => $userId,
            'name' => $cardData['title'],
            'description' => 'ایجاد شده از لایف‌پلن - گروه: ' . $groupTitle,
            'color' => '#' . substr(md5(rand()), 0, 6),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $projects[] = $newProject;
        file_put_contents($projectsFile, json_encode($projects, JSON_PRETTY_PRINT));
        
        // ===== ایجاد تسک‌ها برای هر آیتم =====
        $tasks = [];
        if (file_exists($tasksFile)) {
            $tasks = json_decode(file_get_contents($tasksFile), true);
            if (!is_array($tasks)) $tasks = [];
        }
        
        $taskCount = 0;
        if (!empty($cardData['items'])) {
            foreach ($cardData['items'] as $index => $item) {
                // پشتیبانی از دو حالت: رشته ساده یا آبجکت با text/link
                $itemText = is_array($item) ? ($item['text'] ?? '') : $item;
                $itemLink = is_array($item) ? ($item['link'] ?? '') : '';
                
                $taskDescription = 'ایجاد شده از لایف‌پلن - کارت: ' . $cardData['title'];
                if ($itemLink) {
                    $taskDescription .= "\nلینک: " . $itemLink;
                }
                
                $newTask = [
                    'id' => time() . rand(100, 999) . $index,
                    'user_id' => $userId,
                    'title' => htmlspecialchars($itemText),
                    'description' => htmlspecialchars($taskDescription),
                    'category' => 'لایف‌پلن',
                    'project' => $newProject['name'],
                    'date' => date('Y-m-d'),
                    'time' => '12:00',
                    'priority' => 'medium',
                    'order' => count($tasks),
                    'done' => false,
                    'parent_id' => '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'completed_at' => null,
                    'source' => 'lifeplan',
                    'source_id' => $cardId
                ];
                $tasks[] = $newTask;
                $taskCount++;
            }
        }
        
        // اگر آیتمی وجود نداشت، یک تسک پیش‌فرض با عنوان کارت ایجاد کن
        if (empty($cardData['items'])) {
            $newTask = [
                'id' => time() . rand(100, 999),
                'user_id' => $userId,
                'title' => htmlspecialchars($cardData['title']),
                'description' => 'ایجاد شده از لایف‌پلن - گروه: ' . $groupTitle,
                'category' => 'لایف‌پلن',
                'project' => $newProject['name'],
                'date' => date('Y-m-d'),
                'time' => '12:00',
                'priority' => 'medium',
                'order' => count($tasks),
                'done' => false,
                'parent_id' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'completed_at' => null,
                'source' => 'lifeplan',
                'source_id' => $cardId
            ];
            $tasks[] = $newTask;
            $taskCount++;
        }
        
        file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
        
        // ===== حذف کارت از لایف‌پلن =====
        if ($groupIndex !== -1 && $cardIndex !== -1) {
            array_splice($groups[$groupIndex]['cards'], $cardIndex, 1);
            saveLifePlanGroups($userId, $groups);
        }
        
        $response = [
            'success' => true, 
            'message' => 'پروژه و تسک‌ها با موفقیت ایجاد شدند',
            'project' => $newProject,
            'tasks_count' => $taskCount
        ];
    }
    
    echo json_encode($response);
    exit;
}

$groups = getLifePlanGroups($userId);
$categories = getCategories();
$projects = getUserProjects($userId);
$currentDate = date('Y-m-d');
$page_title = 'لایف‌پلن';
$pageSubtitle = 'برنامه‌ریزی اهداف بلندمدت و مسیر زندگی';

// ==================== هدر یکپارچه ====================
include_once __DIR__ . '/../includes/header.php';
?>

    <!-- ===== منوی داخلی لایف‌پلن ===== -->
    <div class="lifeplan-nav">
        <div class="lifeplan-nav-content">
            <div class="nav-actions">
                <button class="btn-add-group" onclick="openAddGroupModal()">
                    <span class="icon icon-plus"></span> گروه جدید
                </button>
                <button class="btn-pdf" id="pdfBtn" onclick="exportPDF()">
                    <span class="icon icon-file-pdf"></span> خروجی PDF
                </button>
                <button class="btn-edit-toggle" id="editToggleBtn" onclick="toggleEditMode()">
                    <span class="icon icon-edit"></span> <span id="editToggleText">ویرایش</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===== محتوای اصلی ===== -->
    <div class="main-container">
        <div id="pdfContent">
            <!-- ===== عنوان ثابت و قابل ویرایش ===== -->
            <div class="lifeplan-title-section">
                <div class="title-edit-wrapper">
                    <div id="titleDisplay" class="main-title">
                        <?php echo htmlspecialchars($page_title); ?>
                    </div>
                    <button class="edit-title-btn" onclick="editTitle()">
                        <span class="icon icon-edit"></span> ویرایش عنوان
                    </button>
                </div>
                <input type="text" id="titleInput" class="main-title-input" value="<?php echo htmlspecialchars($page_title); ?>" maxlength="100">
                
                <div class="sub-title-wrapper">
                    <div id="subtitleDisplay" class="sub-title" onclick="editSubtitle()">
                        <?php echo htmlspecialchars($pageSubtitle); ?>
                        <span class="edit-hint"><span class="icon icon-edit"></span> ویرایش</span>
                    </div>
                    <input type="text" id="subtitleInput" class="sub-title-input" value="<?php echo htmlspecialchars($pageSubtitle); ?>" maxlength="200">
                </div>
            </div>
            
            <div class="groups-container" id="groupsContainer"></div>
        </div>
    </div>

    <!-- ===== مودال گروه ===== -->
    <div id="groupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="icon icon-layer-group"></span>
                <span id="groupModalTitle">گروه جدید</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editGroupId">
                <input type="text" id="groupTitle" placeholder="عنوان گروه..." required>
                <input type="text" id="groupSubtitle" placeholder="زیرعنوان گروه (اختیاری)...">
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeGroupModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" id="saveGroupBtn"><span class="icon icon-save"></span> ذخیره</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال کارت ===== -->
    <div id="cardModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="icon icon-sticky-note"></span>
                <span id="cardModalTitle">کارت جدید</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editCardId">
                <input type="hidden" id="cardGroupId">
                <input type="text" id="cardTitle" placeholder="عنوان کارت..." required>
                <div class="icon-selector" id="iconSelector">
                    <button type="button" class="icon-option active" data-icon="fa-file-lines"><span class="icon icon-file-lines"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-star"><span class="icon icon-star"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-heart"><span class="icon icon-heart"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-book"><span class="icon icon-book"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-graduation-cap"><span class="icon icon-graduation-cap"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-briefcase"><span class="icon icon-briefcase"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-dumbbell"><span class="icon icon-dumbbell"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-car"><span class="icon icon-car"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-plane"><span class="icon icon-plane"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-music"><span class="icon icon-music"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-camera"><span class="icon icon-camera"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-paintbrush"><span class="icon icon-paintbrush"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-rocket"><span class="icon icon-rocket"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-lightbulb"><span class="icon icon-lightbulb"></span></button>
                    <button type="button" class="icon-option" data-icon="fa-trophy"><span class="icon icon-trophy"></span></button>
                </div>
                <input type="hidden" id="cardIcon" value="fa-file-lines">
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCardModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" id="saveCardBtn"><span class="icon icon-save"></span> ذخیره</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال تبدیل به پروژه ===== -->
    <div id="convertModal" class="modal modal-convert">
        <div class="modal-content">
            <div class="modal-header">
                <span class="icon icon-folder-open"></span>
                <span>تبدیل به پروژه</span>
            </div>
            <div class="modal-body">
                <p style="color: var(--text-secondary); margin-bottom: 15px;">
                    این کارت به یک پروژه تبدیل می‌شود و تمام آیتم‌های آن به عنوان تسک در پروژه ایجاد می‌شوند.
                </p>
                <div style="background: var(--bg-input); padding: 15px; border-radius: 12px; margin-bottom: 15px;">
                    <div style="font-weight: 600; color: var(--text-primary);">عنوان کارت:</div>
                    <div id="convertCardTitle" style="color: var(--text-secondary);"></div>
                    <div style="font-weight: 600; color: var(--text-primary); margin-top: 10px;">تعداد آیتم‌ها:</div>
                    <div id="convertItemsCount" style="color: var(--text-secondary);"></div>
                </div>
                <input type="hidden" id="convertCardId">
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeConvertModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" onclick="confirmConvertToProject()"><span class="icon icon-folder-open"></span> تبدیل به پروژه</button>
            </div>
        </div>
    </div>

    <!-- ===== Toast ===== -->
    <div class="toast" id="toast"></div>

    <!-- ===== استایل‌های اختصاصی لایف‌پلن ===== -->
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

        /* ===== منوی ناوبری لایف‌پلن ===== */
        .lifeplan-nav {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 12px 20px;
            margin: 0 15px 20px 15px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px var(--shadow-color);
            transition: all 0.3s ease;
        }

        .lifeplan-nav-content {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .nav-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
        }

        .btn-add-group {
            background: linear-gradient(135deg, #f5576c, #f093fb);
            color: white;
            border: none;
            padding: 8px 20px;
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

        .btn-add-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(245,87,108,0.4);
        }

        .btn-pdf {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 8px 20px;
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

        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(220,53,69,0.4);
        }

        .btn-pdf:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-edit-toggle {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
            border: none;
            padding: 8px 20px;
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

        .btn-edit-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(245,158,11,0.4);
        }

        .btn-edit-toggle.active {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .btn-edit-toggle.active:hover {
            box-shadow: 0 5px 20px rgba(40,167,69,0.4);
        }

        /* ===== تنظیمات اصلی ===== */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px 20px 15px;
        }

        /* ===== عنوان ثابت و قابل ویرایش ===== */
        .lifeplan-title-section {
            background: var(--section-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            border: 1px solid var(--section-border);
            text-align: center;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .title-edit-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .lifeplan-title-section .main-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            transition: color 0.3s ease;
            padding: 5px 0;
            border-radius: 10px;
            display: inline-block;
        }

        .edit-title-btn {
            background: rgba(102,126,234,0.12);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 6px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .edit-title-btn:hover {
            background: rgba(102,126,234,0.25);
            color: var(--badge-color);
            border-color: var(--badge-color);
        }

        .lifeplan-title-section .main-title-input {
            display: none;
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            background: var(--bg-input);
            border: 2px solid #667eea;
            border-radius: 12px;
            padding: 5px 15px;
            text-align: center;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            font-family: inherit;
        }

        .lifeplan-title-section .main-title-input:focus {
            outline: none;
        }

        .lifeplan-title-section .sub-title-wrapper {
            margin-top: 8px;
        }

        .lifeplan-title-section .sub-title {
            font-size: 16px;
            color: var(--text-muted);
            cursor: pointer;
            padding: 3px 10px;
            border-radius: 8px;
            display: inline-block;
            transition: all 0.3s;
        }

        .lifeplan-title-section .sub-title:hover {
            background: var(--bg-input);
        }

        .lifeplan-title-section .sub-title .edit-hint {
            font-size: 11px;
            color: var(--text-light);
            margin-right: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .lifeplan-title-section .sub-title:hover .edit-hint {
            opacity: 1;
        }

        .lifeplan-title-section .sub-title-input {
            display: none;
            font-size: 16px;
            color: var(--text-muted);
            background: var(--bg-input);
            border: 2px solid #667eea;
            border-radius: 12px;
            padding: 3px 15px;
            text-align: center;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            font-family: inherit;
        }

        .lifeplan-title-section .sub-title-input:focus {
            outline: none;
        }

        /* ===== گروه‌ها ===== */
        .groups-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .group-card {
            background: var(--group-bg);
            border-radius: 20px;
            border: 1px solid var(--group-border);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .group-card:hover {
            border-color: rgba(102,126,234,0.3);
        }

        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: var(--group-header-bg);
            border-bottom: 1px solid var(--group-border);
            cursor: grab;
        }

        .group-header .drag-handle {
            color: var(--text-light);
            cursor: grab;
            font-size: 16px;
            margin-left: 12px;
            flex-shrink: 0;
        }

        .group-header .group-title-wrapper {
            flex: 1;
            text-align: center;
        }

        .group-header .group-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--group-title-color);
            transition: color 0.3s ease;
        }

        .group-header .group-subtitle {
            font-size: 14px;
            color: var(--group-subtitle-color);
            margin-top: 2px;
            display: block;
        }

        .group-header .group-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .group-header .group-actions button {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
            display: none;
        }

        .group-header .group-actions button.visible {
            display: inline-flex;
        }

        .group-header .group-actions .edit-group-btn:hover {
            background: var(--badge-bg);
            color: var(--badge-color);
        }

        .group-header .group-actions .delete-group-btn:hover {
            background: rgba(220,53,69,0.15);
            color: #ff6b6b;
        }

        .group-header .group-actions .add-card-btn:hover {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }

        /* ===== کارت‌ها ===== */
        .cards-container {
            padding: 16px 24px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .cards-container.empty {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px;
            color: var(--text-light);
            font-size: 14px;
        }

        .card-item {
            background: var(--item-bg);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--item-border);
            transition: all 0.3s ease;
            position: relative;
        }

        .card-item:hover {
            border-color: rgba(102,126,234,0.3);
            background: var(--item-hover-bg);
        }

        .card-item .card-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--card-title-color);
            margin-bottom: 8px;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-item .card-title .icon {
            color: #667eea;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .card-item .card-actions {
            display: flex;
            gap: 4px;
            position: absolute;
            top: 14px;
            left: 14px;
        }

        .card-item .card-actions button {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 13px;
            display: none;
        }

        .card-item .card-actions button.visible {
            display: inline-flex;
        }

        .card-item .card-actions .edit-card-btn:hover {
            background: var(--badge-bg);
            color: var(--badge-color);
        }

        .card-item .card-actions .delete-card-btn:hover {
            background: rgba(220,53,69,0.15);
            color: #ff6b6b;
        }

        .card-item .card-actions .convert-project-btn:hover {
            background: rgba(102,126,234,0.15);
            color: #667eea;
        }

        /* ===== آیتم‌های کارت ===== */
        .card-items-container {
            margin-top: 8px;
        }

        .card-item-row {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 0;
            font-size: 14px;
            color: var(--card-content-color);
            line-height: 1.4;
        }

        .card-item-row .item-bullet {
            color: #667eea;
            font-weight: 700;
            font-size: 18px;
            margin-left: 4px;
            flex-shrink: 0;
        }

        .card-item-row .item-text {
            flex: 1;
            line-height: 1.4;
            word-break: break-word;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .card-item-row .item-link {
            color: #667eea;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 4px;
            background: rgba(102,126,234,0.1);
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .card-item-row .item-link:hover {
            background: rgba(102,126,234,0.2);
            text-decoration: underline;
        }

        .card-item-row .item-edit-btn,
        .card-item-row .item-delete-btn {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.3s;
            display: none;
            flex-shrink: 0;
        }

        .card-item-row .item-edit-btn:hover {
            background: var(--badge-bg);
            color: var(--badge-color);
        }

        .card-item-row .item-delete-btn:hover {
            background: rgba(220,53,69,0.15);
            color: #ff6b6b;
        }

        .card-item-row:hover .item-edit-btn,
        .card-item-row:hover .item-delete-btn {
            display: inline-flex;
        }

        .add-item-row {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed var(--border-color);
        }

        .add-item-row .add-item-input {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 13px;
            font-family: inherit;
            direction: rtl;
        }

        .add-item-row .add-item-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .add-item-row .add-item-input::placeholder {
            color: var(--text-light);
        }

        .add-item-row .add-item-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .add-item-row .add-item-btn:hover {
            transform: scale(1.05);
        }

        /* ===== خالی ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-state .icon {
            font-size: 48px;
            color: var(--empty-color);
            margin-bottom: 16px;
            display: block;
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
            background: var(--modal-overlay);
            align-items: center;
            justify-content: center;
        }

        .modal.show { display: flex; }

        .modal-content {
            background: var(--modal-bg);
            border: 1px solid var(--modal-border);
            border-radius: 25px;
            padding: 30px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        body[data-theme="light"] .modal-content {
            background: #ffffff;
            border-color: #e8e8e8;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header .icon { color: #f5576c; }

        .modal-body input,
        .modal-body select,
        .modal-body textarea {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.3s;
        }

        body[data-theme="light"] .modal-body input,
        body[data-theme="light"] .modal-body select,
        body[data-theme="light"] .modal-body textarea {
            background: #fafafa;
            color: #1a1a2e;
            border-color: #e8e8e8;
        }

        .modal-body input:focus,
        .modal-body select:focus,
        .modal-body textarea:focus {
            outline: none;
            border-color: #667eea;
            background: var(--bg-input-hover);
        }

        body[data-theme="light"] .modal-body input:focus,
        body[data-theme="light"] .modal-body select:focus,
        body[data-theme="light"] .modal-body textarea:focus {
            background: #ffffff;
        }

        .modal-body textarea { resize: vertical; min-height: 80px; }

        .modal-body .icon-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
            padding: 10px;
            background: var(--bg-input);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .modal-body .icon-selector .icon-option {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            border: 2px solid transparent;
            background: transparent;
            color: var(--text-secondary);
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-body .icon-selector .icon-option:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-color);
        }

        .modal-body .icon-selector .icon-option.active {
            border-color: #667eea;
            background: rgba(102,126,234,0.15);
            color: #667eea;
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .btn-cancel {
            background: var(--bg-input);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }

        .btn-cancel:hover { background: var(--bg-card-hover); }

        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }

        .btn-save:hover { transform: scale(1.02); }

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
            --section-bg: rgba(255,255,255,0.05);
            --section-border: rgba(255,255,255,0.08);
            --group-bg: rgba(255,255,255,0.05);
            --group-border: rgba(255,255,255,0.08);
            --group-header-bg: rgba(255,255,255,0.03);
            --item-bg: rgba(255,255,255,0.03);
            --item-border: rgba(255,255,255,0.06);
            --item-hover-bg: rgba(255,255,255,0.05);
            --group-title-color: #667eea;
            --group-subtitle-color: rgba(255,255,255,0.5);
            --card-title-color: #ffffff;
            --card-content-color: rgba(255,255,255,0.8);
            --empty-color: rgba(255,255,255,0.2);
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
            --section-bg: #ffffff;
            --section-border: #e8e8e8;
            --group-bg: #ffffff;
            --group-border: #e8e8e8;
            --group-header-bg: #f8f9fa;
            --item-bg: #fafafa;
            --item-border: #f0f0f0;
            --item-hover-bg: #f8f9fa;
            --group-title-color: #667eea;
            --group-subtitle-color: #6c757d;
            --card-title-color: #1a1a2e;
            --card-content-color: #333333;
            --empty-color: #999999;
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
            .lifeplan-nav {
                padding: 10px 15px;
                margin: 0 10px 15px 10px;
            }

            .nav-actions {
                flex-direction: column;
                width: 100%;
            }

            .nav-actions button,
            .nav-actions a {
                width: 100%;
                justify-content: center;
            }

            .main-container {
                padding: 0 10px 15px 10px;
            }

            .cards-container {
                grid-template-columns: 1fr;
                padding: 12px 16px;
            }

            .group-header {
                flex-wrap: wrap;
                gap: 10px;
            }

            .group-header .group-title {
                font-size: 20px;
            }

            .modal-content {
                padding: 20px;
            }

            .lifeplan-title-section .main-title {
                font-size: 24px;
            }

            .lifeplan-title-section .main-title-input {
                font-size: 24px;
            }

            .title-edit-wrapper {
                gap: 10px;
            }

            .edit-title-btn {
                padding: 4px 12px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .card-item .card-title {
                font-size: 15px;
            }

            .lifeplan-title-section .main-title {
                font-size: 20px;
            }

            .lifeplan-title-section .main-title-input {
                font-size: 20px;
            }

            .card-item .card-actions {
                position: static;
                margin-top: 10px;
                justify-content: flex-start;
            }

            .group-header .group-title {
                font-size: 18px;
            }

            .modal-content {
                padding: 15px;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script>
    let groups = [];
    let sortableInstances = {};
    let groupEditId = null;
    let cardEditId = null;
    let pageTitle = '<?php echo htmlspecialchars($page_title); ?>';
    let pageSubtitle = '<?php echo htmlspecialchars($pageSubtitle); ?>';
    let editMode = false;
    let selectedIcon = 'fa-file-lines';

    // ===== مدیریت تم =====
    function toggleTheme() {
        const body = document.body;
        body.classList.toggle('light-mode');
        const isLight = body.classList.contains('light-mode');
        localStorage.setItem('lifeplanTheme', isLight ? 'light' : 'dark');
        updateThemeIcon();
    }

    function updateThemeIcon() {
        const isLight = document.body.classList.contains('light-mode');
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    function loadTheme() {
        const savedTheme = localStorage.getItem('lifeplanTheme');
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
        } else {
            document.body.classList.remove('light-mode');
        }
        updateThemeIcon();
    }

    // ===== مدیریت ویرایش =====
    function toggleEditMode() {
        editMode = !editMode;
        const btn = document.getElementById('editToggleBtn');
        const text = document.getElementById('editToggleText');
        if (editMode) {
            btn.classList.add('active');
            text.textContent = 'غیرفعال‌سازی ویرایش';
        } else {
            btn.classList.remove('active');
            text.textContent = 'ویرایش';
        }
        renderGroups();
    }

    // ===== مدیریت عنوان اصلی (همیشه قابل ویرایش) =====
    function editTitle() {
        const display = document.getElementById('titleDisplay');
        const input = document.getElementById('titleInput');
        display.style.display = 'none';
        input.style.display = 'block';
        input.value = pageTitle;
        input.focus();
        input.select();
    }

    function saveTitle() {
        const display = document.getElementById('titleDisplay');
        const input = document.getElementById('titleInput');
        const newTitle = input.value.trim() || 'لایف‌پلن';
        pageTitle = newTitle;
        display.textContent = newTitle;
        display.style.display = 'inline-block';
        input.style.display = 'none';
        localStorage.setItem('lifeplanTitle', newTitle);
    }

    function loadTitle() {
        const savedTitle = localStorage.getItem('lifeplanTitle');
        if (savedTitle) {
            pageTitle = savedTitle;
            document.getElementById('titleDisplay').textContent = savedTitle;
            document.getElementById('titleInput').value = savedTitle;
        }
    }

    // ===== مدیریت زیرعنوان =====
    function editSubtitle() {
        const display = document.getElementById('subtitleDisplay');
        const input = document.getElementById('subtitleInput');
        display.style.display = 'none';
        input.style.display = 'block';
        input.value = pageSubtitle;
        input.focus();
        input.select();
    }

    function saveSubtitle() {
        const display = document.getElementById('subtitleDisplay');
        const input = document.getElementById('subtitleInput');
        const newSubtitle = input.value.trim() || 'برنامه‌ریزی اهداف بلندمدت و مسیر زندگی';
        pageSubtitle = newSubtitle;
        display.textContent = newSubtitle;
        display.innerHTML = newSubtitle + ' <span class="edit-hint"><span class="icon icon-edit"></span> ویرایش</span>';
        display.style.display = 'inline-block';
        input.style.display = 'none';
        localStorage.setItem('lifeplanSubtitle', newSubtitle);
    }

    function loadSubtitle() {
        const savedSubtitle = localStorage.getItem('lifeplanSubtitle');
        if (savedSubtitle) {
            pageSubtitle = savedSubtitle;
            const display = document.getElementById('subtitleDisplay');
            display.innerHTML = savedSubtitle + ' <span class="edit-hint"><span class="icon icon-edit"></span> ویرایش</span>';
            document.getElementById('subtitleInput').value = savedSubtitle;
        }
    }

    // ===== توابع کمکی =====
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

    // ===== بارگذاری دیتا =====
    async function loadGroups() {
        try {
            let formData = new FormData();
            formData.append('action', 'load');
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups || [];
                renderGroups();
            }
        } catch(e) {
            console.error(e);
        }
    }

    // ===== رندر گروه‌ها =====
    function renderGroups() {
        const container = document.getElementById('groupsContainer');
        
        if (!groups || groups.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="icon"><span class="icon icon-compass"></span></span>
                    <div style="font-size: 18px; font-weight: 600; color: var(--text-primary);">هیچ گروهی وجود ندارد</div>
                    <div style="font-size: 14px; margin-top: 8px;">با کلیک روی دکمه "گروه جدید" شروع کنید</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = groups.map(group => `
            <div class="group-card" data-id="${group.id}">
                <div class="group-header">
                    <span class="drag-handle"><span class="icon icon-grip-vertical"></span></span>
                    <div class="group-title-wrapper">
                        <div class="group-title">${escapeHtml(group.title)}</div>
                        ${group.subtitle ? `<span class="group-subtitle">${escapeHtml(group.subtitle)}</span>` : ''}
                    </div>
                    <div class="group-actions">
                        <button class="add-card-btn ${editMode ? 'visible' : ''}" onclick="openAddCardModal('${group.id}')" title="افزودن کارت">
                            <span class="icon icon-plus"></span>
                        </button>
                        <button class="edit-group-btn ${editMode ? 'visible' : ''}" onclick="openEditGroupModal('${group.id}')" title="ویرایش گروه">
                            <span class="icon icon-edit"></span>
                        </button>
                        <button class="delete-group-btn ${editMode ? 'visible' : ''}" onclick="deleteGroup('${group.id}')" title="حذف گروه">
                            <span class="icon icon-trash"></span>
                        </button>
                    </div>
                </div>
                <div class="cards-container ${!group.cards || group.cards.length === 0 ? 'empty' : ''}" data-group-id="${group.id}">
                    ${!group.cards || group.cards.length === 0 ? 
                        '<span style="color: var(--text-light);">هیچ کارتی در این گروه وجود ندارد</span>' :
                        group.cards.map(card => `
                            <div class="card-item" data-id="${card.id}">
                                <div class="card-actions">
                                    <button class="convert-project-btn ${editMode ? 'visible' : ''}" onclick="openConvertModal('${card.id}')" title="تبدیل به پروژه">
                                        <span class="icon icon-folder-open"></span>
                                    </button>
                                    <button class="edit-card-btn ${editMode ? 'visible' : ''}" onclick="openEditCardModal('${group.id}', '${card.id}')" title="ویرایش کارت">
                                        <span class="icon icon-edit"></span>
                                    </button>
                                    <button class="delete-card-btn ${editMode ? 'visible' : ''}" onclick="deleteCard('${group.id}', '${card.id}')" title="حذف کارت">
                                        <span class="icon icon-trash"></span>
                                    </button>
                                </div>
                                <div class="card-title">
                                    <span class="icon ${card.icon || 'fa-file-lines'}"></span>
                                    ${escapeHtml(card.title)}
                                </div>
                                <div class="card-items-container" data-card-id="${card.id}">
                                    ${card.items && card.items.length > 0 ? 
                                        card.items.map((item, index) => {
                                            let itemText = '';
                                            let itemLink = '';
                                            if (typeof item === 'string') {
                                                itemText = item;
                                            } else {
                                                itemText = item.text || '';
                                                itemLink = item.link || '';
                                            }
                                            
                                            const linkHtml = itemLink ? 
                                                `<a href="${escapeHtml(itemLink)}" target="_blank" class="item-link" title="باز کردن لینک">
                                                    <span class="icon icon-external-link-alt"></span> لینک
                                                </a>` : '';
                                            
                                            return `
                                                <div class="card-item-row" data-index="${index}">
                                                    <span class="item-bullet">•</span>
                                                    <span class="item-text">
                                                        ${escapeHtml(itemText)}
                                                        ${linkHtml}
                                                    </span>
                                                    ${editMode ? `
                                                        <button class="item-edit-btn" onclick="editItem('${card.id}', ${index})" title="ویرایش آیتم">
                                                            <span class="icon icon-edit"></span>
                                                        </button>
                                                        <button class="item-delete-btn" onclick="deleteItem('${card.id}', ${index})" title="حذف آیتم">
                                                            <span class="icon icon-close"></span>
                                                        </button>
                                                    ` : ''}
                                                </div>
                                            `;
                                        }).join('') :
                                        '<div style="color: var(--text-light); font-size: 13px; padding: 4px 0;">هیچ آیتمی وجود ندارد</div>'
                                    }
                                    ${editMode ? `
                                        <div class="add-item-row">
                                            <input type="text" class="add-item-input" placeholder="اضافه کردن آیتم جدید..." data-card-id="${card.id}">
                                            <button class="add-item-btn" onclick="addItem('${card.id}')">
                                                <span class="icon icon-plus"></span> اضافه
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `).join('')
                    }
                </div>
            </div>
        `).join('');
        
        initSortables();
        initAddItemListeners();
    }

    // ===== سورت کردن گروه‌ها =====
    function initSortables() {
        for (let id in sortableInstances) sortableInstances[id].destroy();
        sortableInstances = {};
        
        const container = document.getElementById('groupsContainer');
        if (container) {
            sortableInstances['groups'] = new Sortable(container, {
                animation: 300,
                handle: '.drag-handle',
                ghostClass: 'dragging',
                onEnd: async function() {
                    let ids = [];
                    document.querySelectorAll('.group-card').forEach(el => {
                        ids.push(el.dataset.id);
                    });
                    await reorderGroups(ids);
                }
            });
        }
    }

    // ===== تغییر ترتیب گروه‌ها =====
    async function reorderGroups(ids) {
        try {
            let formData = new FormData();
            formData.append('action', 'reorder_groups');
            formData.append('ids', JSON.stringify(ids));
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
            }
        } catch(e) {
            console.error(e);
        }
    }

    // ===== مدیریت گروه =====
    function openAddGroupModal() {
        groupEditId = null;
        document.getElementById('groupModalTitle').textContent = 'گروه جدید';
        document.getElementById('editGroupId').value = '';
        document.getElementById('groupTitle').value = '';
        document.getElementById('groupSubtitle').value = '';
        document.getElementById('saveGroupBtn').textContent = 'افزودن';
        document.getElementById('groupModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('groupTitle').focus(), 150);
    }

    function openEditGroupModal(id) {
        const group = groups.find(g => g.id == id);
        if (!group) return;
        groupEditId = id;
        document.getElementById('groupModalTitle').textContent = 'ویرایش گروه';
        document.getElementById('editGroupId').value = id;
        document.getElementById('groupTitle').value = group.title;
        document.getElementById('groupSubtitle').value = group.subtitle || '';
        document.getElementById('saveGroupBtn').textContent = 'ذخیره تغییرات';
        document.getElementById('groupModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('groupTitle').focus(), 150);
    }

    function closeGroupModal() {
        document.getElementById('groupModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    async function saveGroup() {
        const title = document.getElementById('groupTitle').value.trim();
        if (!title) {
            showToast('لطفاً عنوان گروه را وارد کنید', 'error');
            return;
        }
        
        const subtitle = document.getElementById('groupSubtitle').value.trim();
        const id = document.getElementById('editGroupId').value;
        const action = id ? 'edit_group' : 'add_group';
        
        const btn = document.getElementById('saveGroupBtn');
        btn.disabled = true;
        btn.textContent = 'در حال ذخیره...';
        
        try {
            let formData = new FormData();
            formData.append('action', action);
            if (id) formData.append('id', id);
            formData.append('title', title);
            formData.append('subtitle', subtitle);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                closeGroupModal();
                showToast(id ? 'گروه ویرایش شد' : 'گروه اضافه شد', 'success');
            } else {
                showToast('خطا در ذخیره گروه', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = id ? 'ذخیره تغییرات' : 'افزودن';
        }
    }

    async function deleteGroup(id) {
        if (!confirm('آیا از حذف این گروه و تمام کارت‌های آن مطمئن هستید؟')) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'delete_group');
            formData.append('id', id);
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                showToast('گروه حذف شد', 'success');
            }
        } catch(e) {
            showToast('خطا در حذف گروه', 'error');
        }
    }

    // ===== مدیریت انتخاب آیکون =====
    function initIconSelector() {
        const container = document.getElementById('iconSelector');
        if (!container) return;
        
        container.querySelectorAll('.icon-option').forEach(btn => {
            btn.addEventListener('click', function() {
                container.querySelectorAll('.icon-option').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('cardIcon').value = this.dataset.icon;
                selectedIcon = this.dataset.icon;
            });
        });
    }

    // ===== مدیریت کارت =====
    function openAddCardModal(groupId) {
        cardEditId = null;
        document.getElementById('cardModalTitle').textContent = 'کارت جدید';
        document.getElementById('editCardId').value = '';
        document.getElementById('cardGroupId').value = groupId;
        document.getElementById('cardTitle').value = '';
        document.getElementById('cardIcon').value = 'fa-file-lines';
        selectedIcon = 'fa-file-lines';
        document.querySelectorAll('#iconSelector .icon-option').forEach(b => b.classList.remove('active'));
        document.querySelector('#iconSelector .icon-option[data-icon="fa-file-lines"]')?.classList.add('active');
        document.getElementById('saveCardBtn').textContent = 'افزودن';
        document.getElementById('cardModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('cardTitle').focus(), 150);
    }

    function openEditCardModal(groupId, cardId) {
        const group = groups.find(g => g.id == groupId);
        if (!group) return;
        const card = group.cards.find(c => c.id == cardId);
        if (!card) return;
        
        cardEditId = cardId;
        document.getElementById('cardModalTitle').textContent = 'ویرایش کارت';
        document.getElementById('editCardId').value = cardId;
        document.getElementById('cardGroupId').value = groupId;
        document.getElementById('cardTitle').value = card.title;
        const icon = card.icon || 'fa-file-lines';
        document.getElementById('cardIcon').value = icon;
        selectedIcon = icon;
        document.querySelectorAll('#iconSelector .icon-option').forEach(b => b.classList.remove('active'));
        document.querySelector(`#iconSelector .icon-option[data-icon="${icon}"]`)?.classList.add('active');
        document.getElementById('saveCardBtn').textContent = 'ذخیره تغییرات';
        document.getElementById('cardModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('cardTitle').focus(), 150);
    }

    function closeCardModal() {
        document.getElementById('cardModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    async function saveCard() {
        const title = document.getElementById('cardTitle').value.trim();
        if (!title) {
            showToast('لطفاً عنوان کارت را وارد کنید', 'error');
            return;
        }
        
        const groupId = document.getElementById('cardGroupId').value;
        const cardId = document.getElementById('editCardId').value;
        const icon = document.getElementById('cardIcon').value || 'fa-file-lines';
        const action = cardId ? 'edit_card' : 'add_card';
        
        const btn = document.getElementById('saveCardBtn');
        btn.disabled = true;
        btn.textContent = 'در حال ذخیره...';
        
        try {
            let formData = new FormData();
            formData.append('action', action);
            if (cardId) formData.append('id', cardId);
            formData.append('group_id', groupId);
            formData.append('title', title);
            formData.append('icon', icon);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                closeCardModal();
                showToast(cardId ? 'کارت ویرایش شد' : 'کارت اضافه شد', 'success');
            } else {
                showToast('خطا در ذخیره کارت', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = cardId ? 'ذخیره تغییرات' : 'افزودن';
        }
    }

    async function deleteCard(groupId, cardId) {
        if (!confirm('آیا از حذف این کارت مطمئن هستید؟')) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'delete_card');
            formData.append('id', cardId);
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                showToast('کارت حذف شد', 'success');
            }
        } catch(e) {
            showToast('خطا در حذف کارت', 'error');
        }
    }

    // ===== مدیریت آیتم‌ها (با پشتیبانی از لینک) =====
    async function addItem(cardId) {
        const input = document.querySelector(`.add-item-input[data-card-id="${cardId}"]`);
        if (!input) return;
        
        const itemText = input.value.trim();
        if (!itemText) {
            showToast('لطفاً متن آیتم را وارد کنید', 'error');
            return;
        }
        
        const itemLink = prompt('لینک (اختیاری) - می‌توانید خالی بگذارید:', '');
        if (itemLink === null) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'add_item');
            formData.append('card_id', cardId);
            formData.append('item_text', itemText);
            formData.append('item_link', itemLink || '');
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                input.value = '';
                showToast('آیتم اضافه شد', 'success');
            } else {
                showToast(result.message || 'خطا در اضافه کردن آیتم', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    async function deleteItem(cardId, index) {
        if (!confirm('آیا از حذف این آیتم مطمئن هستید؟')) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('card_id', cardId);
            formData.append('index', index);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                showToast('آیتم حذف شد', 'success');
            } else {
                showToast(result.message || 'خطا در حذف آیتم', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    async function editItem(cardId, index) {
        let card = null;
        let itemData = { text: '', link: '' };
        for (let group of groups) {
            const found = group.cards.find(c => c.id == cardId);
            if (found) {
                card = found;
                const raw = found.items[index];
                if (typeof raw === 'string') {
                    itemData = { text: raw, link: '' };
                } else {
                    itemData = { text: raw.text || '', link: raw.link || '' };
                }
                break;
            }
        }
        if (!card) return;
        
        const newText = prompt('متن آیتم:', itemData.text);
        if (newText === null) return;
        if (!newText.trim()) {
            showToast('متن آیتم نمی‌تواند خالی باشد', 'error');
            return;
        }
        
        const newLink = prompt('لینک (اختیاری):', itemData.link);
        if (newLink === null) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'edit_item');
            formData.append('card_id', cardId);
            formData.append('index', index);
            formData.append('item_text', newText.trim());
            formData.append('item_link', newLink.trim());
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                showToast('آیتم ویرایش شد', 'success');
            } else {
                showToast(result.message || 'خطا در ویرایش آیتم', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    function initAddItemListeners() {
        document.querySelectorAll('.add-item-input').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const cardId = this.dataset.cardId;
                    addItem(cardId);
                }
            });
        });
    }

    // ===== تبدیل به پروژه =====
    function openConvertModal(cardId) {
        let card = null;
        for (let group of groups) {
            const found = group.cards.find(c => c.id == cardId);
            if (found) { 
                card = found; 
                break; 
            }
        }
        if (!card) return;
        
        document.getElementById('convertCardId').value = cardId;
        document.getElementById('convertCardTitle').textContent = card.title;
        const itemsCount = card.items ? card.items.length : 0;
        document.getElementById('convertItemsCount').textContent = itemsCount > 0 ? itemsCount + ' آیتم' : 'بدون آیتم';
        document.getElementById('convertModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeConvertModal() {
        document.getElementById('convertModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    async function confirmConvertToProject() {
        const cardId = document.getElementById('convertCardId').value;
        
        try {
            let formData = new FormData();
            formData.append('action', 'convert_to_project');
            formData.append('card_id', cardId);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                showToast('✅ پروژه با ' + (result.tasks_count || 0) + ' تسک ایجاد شد', 'success');
                closeConvertModal();
                
                for (let group of groups) {
                    const index = group.cards.findIndex(c => c.id == cardId);
                    if (index !== -1) {
                        group.cards.splice(index, 1);
                        break;
                    }
                }
                renderGroups();
                
                setTimeout(() => {
                    if (confirm('آیا می‌خواهید به صفحه پروژه‌ها بروید؟')) {
                        window.location.href = '../projects/index.php';
                    }
                }, 1000);
            } else {
                showToast(result.message || 'خطا در تبدیل به پروژه', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    // ===== خروجی PDF با فاصله‌های منظم =====
async function exportPDF() {
    const btn = document.getElementById('pdfBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="icon icon-spinner"></span> در حال تولید...';
    
    try {
        // ===== ذخیره حالت فعلی تم =====
        const body = document.body;
        const isCurrentlyLight = body.classList.contains('light-mode');
        
        // ===== اعمال تم روشن برای PDF =====
        if (!isCurrentlyLight) {
            body.classList.add('light-mode');
        }
        
        // ===== مخفی کردن المان‌های اضافی =====
        const elementsToHide = document.querySelectorAll('.group-actions, .card-actions, .drag-handle, .add-card-btn, .edit-group-btn, .delete-group-btn, .edit-card-btn, .delete-card-btn, .convert-project-btn, .item-edit-btn, .item-delete-btn, .add-item-row, .edit-title-btn, .lifeplan-nav');
        elementsToHide.forEach(el => {
            if (el) {
                el.dataset.originalDisplay = el.style.display;
                el.style.display = 'none';
            }
        });
        
        // مخفی کردن هدر اصلی
        const mainHeader = document.querySelector('.header');
        if (mainHeader) {
            mainHeader.dataset.originalDisplay = mainHeader.style.display;
            mainHeader.style.display = 'none';
        }
        
        // مخفی کردن input های ویرایش
        document.querySelectorAll('.main-title-input, .sub-title-input').forEach(el => {
            if (el) {
                el.dataset.originalDisplay = el.style.display;
                el.style.display = 'none';
            }
        });
        
        // نمایش عنوان‌ها
        document.querySelectorAll('.main-title, .sub-title').forEach(el => {
            if (el) el.style.display = 'inline-block';
        });
        
        // ===== تنظیم استایل برای PDF با تم روشن و فاصله‌های منظم =====
        const pdfContent = document.getElementById('pdfContent');
        const bgColor = '#f0f2f5';
        
        // تنظیم پس‌زمینه و padding با فاصله مناسب از همه طرف
        pdfContent.style.background = bgColor;
        pdfContent.style.padding = '40px 50px';  // ← فاصله از همه طرف
        pdfContent.style.borderRadius = '0';
        pdfContent.style.width = '100%';
        pdfContent.style.margin = '0';
        pdfContent.style.boxSizing = 'border-box';
        
        // تنظیم کارت‌ها با استایل تم روشن و فاصله‌های داخلی
        document.querySelectorAll('.group-card').forEach(el => {
            el.style.background = '#ffffff';
            el.style.border = '1px solid #e8e8e8';
            el.style.borderRadius = '20px';
            el.style.marginBottom = '30px';
            el.style.padding = '20px 25px';  // ← فاصله داخلی کارت
            el.style.boxSizing = 'border-box';
        });
        
        document.querySelectorAll('.card-item').forEach(el => {
            el.style.background = '#fafafa';
            el.style.border = '1px solid #f0f0f0';
            el.style.borderRadius = '14px';
            el.style.padding = '12px 20px';  // ← فاصله داخلی آیتم‌ها
            el.style.marginBottom = '8px';
            el.style.boxSizing = 'border-box';
        });
        
        // تنظیم بخش عنوان با استایل تم روشن
        document.querySelectorAll('.lifeplan-title-section').forEach(el => {
            el.style.background = '#ffffff';
            el.style.border = '1px solid #e8e8e8';
            el.style.borderRadius = '20px';
            el.style.padding = '25px 30px';
            el.style.marginBottom = '25px';
            el.style.boxSizing = 'border-box';
        });
        
        // ===== اصلاح فاصله لیست‌ها (مهمترین بخش) =====
        document.querySelectorAll('.group-card ul, .group-card ol, .card-item ul, .card-item ol').forEach(el => {
            el.style.paddingRight = '25px';   // ← فاصله از راست
            el.style.paddingLeft = '15px';    // ← فاصله از چپ
            el.style.margin = '10px 0';
            el.style.boxSizing = 'border-box';
        });
        
        document.querySelectorAll('.group-card li, .card-item li').forEach(el => {
            el.style.marginBottom = '6px';
            el.style.lineHeight = '1.8';
            el.style.textAlign = 'justify';
            el.style.paddingRight = '5px';
        });
        
        // تنظیم گلوله‌ها (bullet points)
        document.querySelectorAll('.group-card ul, .card-item ul').forEach(el => {
            el.style.listStyleType = 'disc';
            el.style.listStylePosition = 'outside';
        });
        
        // ===== تنظیم رنگ‌های متن برای تم روشن =====
        document.querySelectorAll('.main-title, .group-title, .card-title, .task-title-text, .sub-title, .date-header, .stat-number, .stat-label, .item-text, .card-content, .group-subtitle, .empty-state div').forEach(el => {
            el.style.color = '#1a1a2e';
        });
        
        document.querySelectorAll('.sub-title, .group-subtitle, .item-text, .card-content, .stat-label').forEach(el => {
            el.style.color = '#333333';
        });
        
        document.querySelectorAll('.item-link, .subtask-badge, .badge-color').forEach(el => {
            el.style.color = '#667eea';
        });
        
        // ===== صبر برای اعمال تغییرات =====
        await new Promise(resolve => setTimeout(resolve, 400));
        
        // ===== گرفتن اسکرین‌شات از بالای صفحه =====
        window.scrollTo(0, 0);
        
        const canvas = await html2canvas(pdfContent, {
            scale: 2,
            useCORS: true,
            backgroundColor: bgColor,
            logging: false,
            width: pdfContent.scrollWidth,
            height: pdfContent.scrollHeight,
            windowWidth: pdfContent.scrollWidth,
            windowHeight: pdfContent.scrollHeight,
            scrollX: 0,
            scrollY: 0,
            x: 0,
            y: 0,
            onclone: function(clonedDoc) {
                const clonedContent = clonedDoc.getElementById('pdfContent');
                if (clonedContent) {
                    clonedContent.style.background = bgColor;
                    clonedContent.style.padding = '40px 50px';  // ← فاصله در کلون
                    clonedContent.style.borderRadius = '0';
                    clonedContent.style.width = '100%';
                    clonedContent.style.margin = '0';
                    clonedContent.style.boxSizing = 'border-box';
                    
                    // تنظیم استایل کلون شده با تم روشن
                    clonedContent.querySelectorAll('.group-card').forEach(el => {
                        el.style.background = '#ffffff';
                        el.style.border = '1px solid #e8e8e8';
                        el.style.borderRadius = '20px';
                        el.style.padding = '20px 25px';
                        el.style.boxSizing = 'border-box';
                    });
                    
                    clonedContent.querySelectorAll('.card-item').forEach(el => {
                        el.style.background = '#fafafa';
                        el.style.border = '1px solid #f0f0f0';
                        el.style.borderRadius = '14px';
                        el.style.padding = '12px 20px';
                        el.style.boxSizing = 'border-box';
                    });
                    
                    clonedContent.querySelectorAll('.lifeplan-title-section').forEach(el => {
                        el.style.background = '#ffffff';
                        el.style.border = '1px solid #e8e8e8';
                        el.style.borderRadius = '20px';
                        el.style.padding = '25px 30px';
                        el.style.boxSizing = 'border-box';
                    });
                    
                    // اصلاح فاصله لیست‌ها در کلون
                    clonedContent.querySelectorAll('.group-card ul, .group-card ol, .card-item ul, .card-item ol').forEach(el => {
                        el.style.paddingRight = '25px';
                        el.style.paddingLeft = '15px';
                        el.style.margin = '10px 0';
                        el.style.boxSizing = 'border-box';
                    });
                }
            }
        });
        
        // ===== بازیابی حالت =====
        if (!isCurrentlyLight) {
            body.classList.remove('light-mode');
        }
        
        document.querySelectorAll('[data-original-display]').forEach(el => {
            el.style.display = el.dataset.originalDisplay || '';
            delete el.dataset.originalDisplay;
        });
        
        if (mainHeader) {
            mainHeader.style.display = mainHeader.dataset.originalDisplay || '';
            delete mainHeader.dataset.originalDisplay;
        }
        
        // برگرداندن استایل‌ها
        pdfContent.style.background = '';
        pdfContent.style.padding = '';
        pdfContent.style.borderRadius = '';
        pdfContent.style.width = '';
        pdfContent.style.margin = '';
        pdfContent.style.boxSizing = '';
        
        document.querySelectorAll('.group-card').forEach(el => {
            el.style.background = '';
            el.style.border = '';
            el.style.borderRadius = '';
            el.style.marginBottom = '';
            el.style.padding = '';
            el.style.boxSizing = '';
        });
        
        document.querySelectorAll('.card-item').forEach(el => {
            el.style.background = '';
            el.style.border = '';
            el.style.borderRadius = '';
            el.style.padding = '';
            el.style.marginBottom = '';
            el.style.boxSizing = '';
        });
        
        document.querySelectorAll('.lifeplan-title-section').forEach(el => {
            el.style.background = '';
            el.style.border = '';
            el.style.borderRadius = '';
            el.style.padding = '';
            el.style.marginBottom = '';
            el.style.boxSizing = '';
        });
        
        document.querySelectorAll('.group-card ul, .group-card ol, .card-item ul, .card-item ol').forEach(el => {
            el.style.paddingRight = '';
            el.style.paddingLeft = '';
            el.style.margin = '';
            el.style.boxSizing = '';
        });
        
        document.querySelectorAll('.group-card li, .card-item li').forEach(el => {
            el.style.marginBottom = '';
            el.style.lineHeight = '';
            el.style.textAlign = '';
            el.style.paddingRight = '';
        });
        
        document.querySelectorAll('.group-card ul, .card-item ul').forEach(el => {
            el.style.listStyleType = '';
            el.style.listStylePosition = '';
        });
        
        document.querySelectorAll('.main-title, .group-title, .card-title, .task-title-text, .sub-title, .date-header, .stat-number, .stat-label, .item-text, .card-content, .group-subtitle, .empty-state div').forEach(el => {
            el.style.color = '';
        });
        
        document.querySelectorAll('.sub-title, .group-subtitle, .item-text, .card-content, .stat-label').forEach(el => {
            el.style.color = '';
        });
        
        document.querySelectorAll('.item-link, .subtask-badge, .badge-color').forEach(el => {
            el.style.color = '';
        });
        
        // ===== ایجاد PDF =====
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const { jsPDF } = window.jspdf;
        
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pdfWidth = 210;
        const pdfHeight = 297;
        
        const imgRatio = canvas.width / canvas.height;
        const pageRatio = pdfWidth / pdfHeight;
        
        let finalWidth, finalHeight;
        if (imgRatio > pageRatio) {
            finalWidth = pdfWidth;
            finalHeight = pdfWidth / imgRatio;
        } else {
            finalHeight = pdfHeight;
            finalWidth = pdfHeight * imgRatio;
        }
        
        const xOffset = (pdfWidth - finalWidth) / 2;
        const yOffset = (pdfHeight - finalHeight) / 2;
        
        pdf.addImage(imgData, 'JPEG', xOffset, yOffset, finalWidth, finalHeight);
        
        const date = new Date().toISOString().split('T')[0];
        pdf.save(`LifePlan_${date}.pdf`);
        
        showToast('✅ PDF با موفقیت دانلود شد', 'success');
    } catch(e) {
        console.error('Error in exportPDF:', e);
        showToast('خطا در تولید PDF: ' + e.message, 'error');
        
        // بازیابی حالت در صورت خطا
        const body = document.body;
        if (body.classList.contains('light-mode') && !window._wasLightBeforeExport) {
            body.classList.remove('light-mode');
        }
        
        document.querySelectorAll('[data-original-display]').forEach(el => {
            el.style.display = el.dataset.originalDisplay || '';
            delete el.dataset.originalDisplay;
        });
        
        const mainHeader = document.querySelector('.header');
        if (mainHeader) {
            mainHeader.style.display = mainHeader.dataset.originalDisplay || '';
            delete mainHeader.dataset.originalDisplay;
        }
        
        const pdfContent = document.getElementById('pdfContent');
        if (pdfContent) {
            pdfContent.style.background = '';
            pdfContent.style.padding = '';
            pdfContent.style.borderRadius = '';
            pdfContent.style.width = '';
            pdfContent.style.margin = '';
            pdfContent.style.boxSizing = '';
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="icon icon-file-pdf"></span> خروجی PDF';
        window._wasLightBeforeExport = false;
    }
}

    // ===== Event Listeners =====
    document.getElementById('saveGroupBtn').addEventListener('click', saveGroup);
    document.getElementById('saveCardBtn').addEventListener('click', saveCard);
    
    document.getElementById('titleInput').addEventListener('blur', saveTitle);
    document.getElementById('titleInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveTitle();
        }
        if (e.key === 'Escape') {
            const display = document.getElementById('titleDisplay');
            const input = document.getElementById('titleInput');
            display.style.display = 'inline-block';
            input.style.display = 'none';
        }
    });
    
    document.getElementById('subtitleInput').addEventListener('blur', saveSubtitle);
    document.getElementById('subtitleInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveSubtitle();
        }
        if (e.key === 'Escape') {
            const display = document.getElementById('subtitleDisplay');
            const input = document.getElementById('subtitleInput');
            display.style.display = 'inline-block';
            input.style.display = 'none';
        }
    });
    
    document.getElementById('groupModal').addEventListener('click', function(e) {
        if (e.target === this) closeGroupModal();
    });
    document.getElementById('cardModal').addEventListener('click', function(e) {
        if (e.target === this) closeCardModal();
    });
    document.getElementById('convertModal').addEventListener('click', function(e) {
        if (e.target === this) closeConvertModal();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeGroupModal();
            closeCardModal();
            closeConvertModal();
        }
    });

    // ===== شروع =====
    loadTheme();
    loadTitle();
    loadSubtitle();
    loadGroups();
    initIconSelector();
    </script>
</body>
</html>