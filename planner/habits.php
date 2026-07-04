<?php
// planner/habits.php - مدیریت عادت‌ها
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
$habitsFile = __DIR__ . '/../data/habits.json';

if (!file_exists($habitsFile)) {
    file_put_contents($habitsFile, json_encode([]));
}

// ==================== توابع ====================
function getUserHabits($userId) {
    global $habitsFile;
    $habits = json_decode(file_get_contents($habitsFile), true);
    if (!is_array($habits)) $habits = [];
    return array_values(array_filter($habits, fn($h) => ($h['user_id'] ?? '') == $userId));
}

function saveUserHabits($userId, $habits) {
    global $habitsFile;
    $allHabits = json_decode(file_get_contents($habitsFile), true);
    if (!is_array($allHabits)) $allHabits = [];
    
    $allHabits = array_values(array_filter($allHabits, fn($h) => ($h['user_id'] ?? '') != $userId));
    foreach ($habits as &$habit) {
        $habit['user_id'] = $userId;
    }
    $allHabits = array_merge($allHabits, $habits);
    file_put_contents($habitsFile, json_encode($allHabits, JSON_PRETTY_PRINT));
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    $userId = $_SESSION['user_id'];
    
    if ($action === 'load_habits') {
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    elseif ($action === 'add_habit') {
        $habits = getUserHabits($userId);
        $newId = time() . rand(100, 999);
        $newHabit = [
            'id' => $newId,
            'user_id' => $userId,
            'name' => htmlspecialchars(trim($_POST['name'] ?? '')),
            'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
            'frequency' => $_POST['frequency'] ?? 'daily',
            'target' => intval($_POST['target'] ?? 1),
            'current_streak' => 0,
            'best_streak' => 0,
            'last_done' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'color' => '#' . dechex(rand(0x000000, 0xFFFFFF))
        ];
        $habits[] = $newHabit;
        saveUserHabits($userId, $habits);
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    elseif ($action === 'toggle_habit') {
        $habits = getUserHabits($userId);
        $today = date('Y-m-d');
        foreach ($habits as &$habit) {
            if ($habit['id'] == $_POST['id']) {
                $lastDone = $habit['last_done'] ?? null;
                if ($lastDone === $today) {
                    // امروز انجام شده - برداشتن
                    $habit['current_streak'] = max(0, $habit['current_streak'] - 1);
                    $habit['last_done'] = null;
                } else {
                    // انجام امروز
                    $habit['last_done'] = $today;
                    $habit['current_streak'] = ($habit['current_streak'] ?? 0) + 1;
                    if ($habit['current_streak'] > ($habit['best_streak'] ?? 0)) {
                        $habit['best_streak'] = $habit['current_streak'];
                    }
                }
                break;
            }
        }
        saveUserHabits($userId, $habits);
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    elseif ($action === 'delete_habit') {
        $habits = getUserHabits($userId);
        $habits = array_values(array_filter($habits, fn($h) => $h['id'] != $_POST['id']));
        saveUserHabits($userId, $habits);
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
                break;
            }
        }
        saveUserHabits($userId, $habits);
        $response = ['success' => true, 'habits' => getUserHabits($userId)];
    }
    
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت عادت‌ها</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: linear-gradient(135deg, #f59e0b, #f97316);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header h1 i {
            color: #f59e0b;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            background: #5a6268;
            transform: scale(1.02);
        }
        
        .home-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .home-btn:hover {
            background: #5a6fd6;
            transform: scale(1.02);
        }
        
        .add-btn {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
            border: none;
            padding: 10px 25px;
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
        
        .add-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(245, 158, 11, 0.4);
        }
        
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
        
        .card h2 i {
            color: #f59e0b;
        }
        
        .habits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .habit-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .habit-card:hover {
            border-color: #f59e0b;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .habit-card .habit-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 8px;
        }
        
        .habit-card .habit-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        
        .habit-card .habit-desc {
            font-size: 13px;
            color: #999;
            margin: 8px 0 12px 0;
            min-height: 40px;
        }
        
        .habit-card .habit-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
        }
        
        .habit-card .stat-item {
            text-align: center;
            background: white;
            padding: 10px 5px;
            border-radius: 10px;
        }
        
        .habit-card .stat-item .number {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .habit-card .stat-item .label {
            font-size: 10px;
            color: #999;
            margin-top: 2px;
        }
        
        .habit-card .stat-item .number.done {
            color: #28a745;
        }
        
        .habit-card .stat-item .number.streak {
            color: #f59e0b;
        }
        
        .habit-card .stat-item .number.best {
            color: #667eea;
        }
        
        .habit-card .habit-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .habit-card .habit-actions button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .habit-card .habit-actions .done-btn {
            background: #28a745;
            color: white;
            flex: 1;
            justify-content: center;
        }
        
        .habit-card .habit-actions .done-btn:hover {
            background: #218838;
        }
        
        .habit-card .habit-actions .done-btn.done-today {
            background: #dc3545;
        }
        
        .habit-card .habit-actions .done-btn.done-today:hover {
            background: #c82333;
        }
        
        .habit-card .habit-actions .edit-btn {
            background: #667eea;
            color: white;
        }
        
        .habit-card .habit-actions .edit-btn:hover {
            background: #5a6fd6;
        }
        
        .habit-card .habit-actions .delete-btn {
            background: #dc3545;
            color: white;
        }
        
        .habit-card .habit-actions .delete-btn:hover {
            background: #c82333;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        /* مودال */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            width: 90%;
            max-width: 500px;
            border-radius: 25px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 18px;
            border-radius: 25px 25px 0 0;
        }
        
        .modal-body { padding: 25px; }
        
        .modal-body input, .modal-body select, .modal-body textarea {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .modal-body input:focus, .modal-body select:focus, .modal-body textarea:focus {
            outline: none;
            border-color: #f59e0b;
        }
        
        .modal-body textarea { resize: vertical; min-height: 80px; }
        
        .modal-footer {
            padding: 20px 25px 25px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .btn-cancel {
            background: #e9ecef;
            color: #2c3e50;
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
            font-size: 14px;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-save:hover {
            transform: scale(1.02);
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1a1a2e;
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transform: translateY(100px);
            transition: all 0.3s ease;
            font-family: 'Vazirmatn', sans-serif;
        }
        
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .toast.success { background: #28a745; }
        .toast.error { background: #dc3545; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; }
            .header-actions { flex-direction: column; }
            .habits-grid { grid-template-columns: 1fr; }
            .habit-card .habit-stats { grid-template-columns: 1fr 1fr 1fr; }
            .habit-card .habit-actions { flex-wrap: wrap; }
            .habit-card .habit-actions .done-btn { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-fire"></i> مدیریت عادت‌ها</h1>
            <div class="header-actions">
                <button class="add-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> افزودن عادت جدید
                </button>
                <a href="index.php" class="back-btn"><i class="fas fa-arrow-right"></i> بازگشت</a>
                <a href="../index.php" class="home-btn"><i class="fas fa-home"></i> صفحه اصلی</a>
            </div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-chart-line"></i> عادت‌های من</h2>
            <div class="habits-grid" id="habitsContainer">
                <div class="empty-state">
                    <i class="fas fa-fire"></i>
                    <div>هیچ عادتی تعریف نشده است</div>
                    <div style="font-size: 12px; margin-top: 10px;">با کلیک روی دکمه "افزودن عادت جدید" شروع کنید</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- مودال افزودن/ویرایش -->
    <div id="habitModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" id="modalTitle"><i class="fas fa-plus"></i> افزودن عادت جدید</div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <input type="text" id="habitName" placeholder="نام عادت (مثلاً: مطالعه)" required>
                <textarea id="habitDescription" placeholder="توضیحات (اختیاری)" rows="2"></textarea>
                <select id="habitFrequency">
                    <option value="daily">روزانه</option>
                    <option value="weekly">هفتگی</option>
                    <option value="monthly">ماهانه</option>
                </select>
                <input type="number" id="habitTarget" placeholder="هدف روزانه (تعداد)" value="1" min="1">
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal()">انصراف</button>
                <button class="btn-save" id="saveBtn" onclick="saveHabit()">ذخیره</button>
            </div>
        </div>
    </div>
    
    <div class="toast" id="toast"></div>
    
    <script>
        let habits = [];
        let currentEditId = null;
        
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }
        
        async function loadHabits() {
            let formData = new FormData();
            formData.append('action', 'load_habits');
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                habits = result.habits;
                renderHabits();
            }
        }
        
        function renderHabits() {
            const container = document.getElementById('habitsContainer');
            if (!habits || habits.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-fire"></i>
                        <div>هیچ عادتی تعریف نشده است</div>
                        <div style="font-size: 12px; margin-top: 10px;">با کلیک روی دکمه "افزودن عادت جدید" شروع کنید</div>
                    </div>
                `;
                return;
            }
            
            const today = new Date().toISOString().split('T')[0];
            
            container.innerHTML = habits.map(habit => {
                const isDoneToday = habit.last_done === today;
                const color = habit.color || '#f59e0b';
                
                return `
                    <div class="habit-card">
                        <div class="habit-name">
                            <span class="habit-color" style="background: ${color};"></span>
                            ${escapeHtml(habit.name)}
                        </div>
                        ${habit.description ? `<div class="habit-desc">${escapeHtml(habit.description)}</div>` : ''}
                        <div class="habit-stats">
                            <div class="stat-item">
                                <div class="number done">${habit.target || 1}</div>
                                <div class="label">هدف روزانه</div>
                            </div>
                            <div class="stat-item">
                                <div class="number streak">${habit.current_streak || 0}</div>
                                <div class="label">رکورد فعلی</div>
                            </div>
                            <div class="stat-item">
                                <div class="number best">${habit.best_streak || 0}</div>
                                <div class="label">بهترین رکورد</div>
                            </div>
                        </div>
                        <div class="habit-actions">
                            <button class="done-btn ${isDoneToday ? 'done-today' : ''}" onclick="toggleHabit('${habit.id}')">
                                <i class="fas ${isDoneToday ? 'fa-times' : 'fa-check'}"></i>
                                ${isDoneToday ? 'امروز انجام شد' : 'انجام امروز'}
                            </button>
                            <button class="edit-btn" onclick="openEditModal('${habit.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="delete-btn" onclick="deleteHabit('${habit.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            let div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        async function toggleHabit(id) {
            let formData = new FormData();
            formData.append('action', 'toggle_habit');
            formData.append('id', id);
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                habits = result.habits;
                renderHabits();
                showToast('وضعیت عادت تغییر کرد', 'success');
            }
        }
        
        async function deleteHabit(id) {
            if (!confirm('آیا از حذف این عادت مطمئن هستید؟')) return;
            let formData = new FormData();
            formData.append('action', 'delete_habit');
            formData.append('id', id);
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                habits = result.habits;
                renderHabits();
                showToast('عادت با موفقیت حذف شد', 'success');
            }
        }
        
        function openAddModal() {
            currentEditId = null;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> افزودن عادت جدید';
            document.getElementById('editId').value = '';
            document.getElementById('habitName').value = '';
            document.getElementById('habitDescription').value = '';
            document.getElementById('habitFrequency').value = 'daily';
            document.getElementById('habitTarget').value = '1';
            document.getElementById('saveBtn').textContent = 'افزودن';
            document.getElementById('habitModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function openEditModal(id) {
            const habit = habits.find(h => h.id == id);
            if (!habit) return;
            currentEditId = id;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> ویرایش عادت';
            document.getElementById('editId').value = id;
            document.getElementById('habitName').value = habit.name;
            document.getElementById('habitDescription').value = habit.description || '';
            document.getElementById('habitFrequency').value = habit.frequency || 'daily';
            document.getElementById('habitTarget').value = habit.target || 1;
            document.getElementById('saveBtn').textContent = 'ذخیره تغییرات';
            document.getElementById('habitModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('habitModal').style.display = 'none';
            document.body.style.overflow = '';
        }
        
        async function saveHabit() {
            const name = document.getElementById('habitName').value.trim();
            if (!name) {
                showToast('لطفاً نام عادت را وارد کنید', 'error');
                return;
            }
            
            const editId = document.getElementById('editId').value;
            const action = editId ? 'edit_habit' : 'add_habit';
            
            let formData = new FormData();
            formData.append('action', action);
            if (editId) formData.append('id', editId);
            formData.append('name', name);
            formData.append('description', document.getElementById('habitDescription').value);
            formData.append('frequency', document.getElementById('habitFrequency').value);
            formData.append('target', document.getElementById('habitTarget').value);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                habits = result.habits;
                renderHabits();
                closeModal();
                showToast(editId ? 'عادت با موفقیت ویرایش شد' : 'عادت با موفقیت افزوده شد', 'success');
            } else {
                showToast('خطا در ذخیره عادت', 'error');
            }
        }
        
        document.getElementById('habitModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        loadHabits();
    </script>
</body>
</html>