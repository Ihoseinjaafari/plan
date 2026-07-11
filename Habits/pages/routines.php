<?php
// /Habits/pages/routines.php - صفحه روتین‌ها
if (!isset($userId)) {
    header('Location: ../index.php');
    exit;
}

$routinesFile = __DIR__ . '/../../data/routines.json';
$habitLogsFile = __DIR__ . '/../../data/habit_logs.json';

if (!file_exists($routinesFile)) {
    file_put_contents($routinesFile, json_encode([]));
}

function getUserRoutines($userId) {
    global $routinesFile;
    if (!file_exists($routinesFile)) return [];
    $allRoutines = json_decode(file_get_contents($routinesFile), true);
    if (!is_array($allRoutines)) return [];
    return array_values(array_filter($allRoutines, function($r) use ($userId) {
        return ($r['user_id'] ?? '') == $userId;
    }));
}

$userRoutines = getUserRoutines($userId);
?>

<div class="routines-page habits-dashboard">
    <div class="page-header">
        <h1><span class="icon icon-calendar"></span> روتین‌ها</h1>
        <button class="btn btn-primary" onclick="openRoutineModal()">
            <span class="icon icon-plus"></span> افزودن روتین
        </button>
    </div>

    <div class="routines-container">
        <?php if (empty($userRoutines)): ?>
            <div class="empty-state">
                <span class="icon icon-calendar-off"></span>
                <h3>هیچ روتینی وجود ندارد</h3>
                <p>اولین روتین روزانه یا هفتگی خود را ایجاد کنید</p>
            </div>
        <?php else: ?>
            <?php
            $timeSlots = ['morning', 'afternoon', 'evening', 'night'];
            $timeLabels = [
                'morning' => ['صبح', 'icon-sunrise'],
                'afternoon' => ['ظهر', 'icon-sun'],
                'evening' => ['عصر', 'icon-sunset'],
                'night' => ['شب', 'icon-moon']
            ];
            
            foreach ($timeSlots as $slot): 
                $slotRoutines = array_filter($userRoutines, fn($r) => $r['time_slot'] == $slot);
                if (empty($slotRoutines)) continue;
            ?>
                <div class="routine-section">
                    <div class="section-header">
                        <h2>
                            <span class="icon <?= $timeLabels[$slot][1] ?>"></span>
                            <?= $timeLabels[$slot][0] ?>
                        </h2>
                    </div>
                    <div class="routine-list">
                        <?php foreach ($slotRoutines as $routine): ?>
                            <div class="routine-item" data-id="<?= htmlspecialchars($routine['id']) ?>">
                                <div class="routine-checkbox">
                                    <input type="checkbox" 
                                           id="routine-<?= $routine['id'] ?>"
                                           <?= $routine['completed'] ? 'checked' : '' ?>
                                           onchange="toggleRoutine('<?= $routine['id'] ?>', this.checked)">
                                    <label for="routine-<?= $routine['id'] ?>"></label>
                                </div>
                                <div class="routine-content">
                                    <div class="routine-title"><?= htmlspecialchars($routine['title']) ?></div>
                                    <?php if (!empty($routine['habits'])): ?>
                                        <div class="routine-habits">
                                            <?php 
                                            $habits = json_decode($routine['habits'], true) ?? [];
                                            foreach ($habits as $habitId): 
                                            ?>
                                                <span class="habit-tag">#عادت_<?= $habitId ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="routine-actions">
                                    <button class="btn-icon" onclick="editRoutine('<?= htmlspecialchars($routine['id']) ?>')">
                                        <span class="icon icon-edit"></span>
                                    </button>
                                    <button class="btn-icon btn-danger" onclick="deleteRoutine('<?= htmlspecialchars($routine['id']) ?>')">
                                        <span class="icon icon-trash"></span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- مودال افزودن/ویرایش روتین -->
    <div id="routineModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">افزودن روتین جدید</h2>
                <button class="btn-close" onclick="closeRoutineModal()">&times;</button>
            </div>
            <form id="routineForm" onsubmit="saveRoutine(event)">
                <input type="hidden" id="routineId" name="id">
                
                <div class="form-group">
                    <label for="routineTitle">عنوان روتین</label>
                    <input type="text" id="routineTitle" name="title" required placeholder="مثلاً: ورزش صبحگاهی">
                </div>

                <div class="form-group">
                    <label for="routineTimeSlot">زمان</label>
                    <select id="routineTimeSlot" name="time_slot" required>
                        <option value="morning">صبح</option>
                        <option value="afternoon">ظهر</option>
                        <option value="evening">عصر</option>
                        <option value="night">شب</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>عادت‌های مرتبط</label>
                    <div id="habitsList" class="habits-list">
                        <!-- عادت‌ها توسط JS پر می‌شوند -->
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRoutineModal()">انصراف</button>
                    <button type="submit" class="btn btn-primary">ذخیره روتین</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const currentUserId = '<?= $userId ?>';

    // بارگذاری عادت‌ها برای انتخاب
    function loadHabits() {
        fetch('api.php?action=get_habits')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.habits.length > 0) {
                    const container = document.getElementById('habitsList');
                    container.innerHTML = data.habits.map(habit => `
                        <label class="habit-checkbox">
                            <input type="checkbox" name="habits[]" value="${habit.id}">
                            <span style="background:${habit.color || '#4CAF50'}"></span>
                            ${habit.name}
                        </label>
                    `).join('');
                } else {
                    document.getElementById('habitsList').innerHTML = '<p class="empty-text">هیچ عادتی وجود ندارد</p>';
                }
            });
    }

    function openRoutineModal() {
        document.getElementById('modalTitle').textContent = 'افزودن روتین جدید';
        document.getElementById('routineForm').reset();
        document.getElementById('routineId').value = '';
        loadHabits();
        document.getElementById('routineModal').classList.add('active');
    }

    function closeRoutineModal() {
        document.getElementById('routineModal').classList.remove('active');
    }

    function editRoutine(id) {
        fetch('api.php?action=get_routine&id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const routine = data.routine;
                    document.getElementById('modalTitle').textContent = 'ویرایش روتین';
                    document.getElementById('routineId').value = routine.id;
                    document.getElementById('routineTitle').value = routine.title;
                    document.getElementById('routineTimeSlot').value = routine.time_slot;
                    
                    loadHabits().then(() => {
                        const habits = JSON.parse(routine.habits) || [];
                        document.querySelectorAll('.habit-checkbox input').forEach(cb => {
                            cb.checked = habits.includes(cb.value);
                        });
                    });
                    
                    document.getElementById('routineModal').classList.add('active');
                }
            });
    }

    function saveRoutine(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const data = {
            action: formData.get('id') ? 'update_routine' : 'create_routine',
            id: formData.get('id'),
            user_id: currentUserId,
            title: formData.get('title'),
            time_slot: formData.get('time_slot'),
            habits: Array.from(formData.getAll('habits[]'))
        };

        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                alert('روتین با موفقیت ذخیره شد');
                closeRoutineModal();
                location.reload();
            } else {
                alert('خطا: ' + result.message);
            }
        });
    }

    function toggleRoutine(id, completed) {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'toggle_routine', 
                id: id, 
                completed: completed 
            })
        })
        .then(res => res.json())
        .then(result => {
            if (!result.success) {
                alert('خطا در به‌روزرسانی');
            }
        });
    }

    function deleteRoutine(id) {
        if (!confirm('آیا مطمئن هستید که می‌خواهید این روتین را حذف کنید؟')) return;
        
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_routine', id: id })
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                alert('روتین حذف شد');
                location.reload();
            } else {
                alert('خطا: ' + result.message);
            }
        });
    }

    document.getElementById('routineModal').addEventListener('click', function(e) {
        if (e.target === this) closeRoutineModal();
    });
    </script>

    <style>
    .routines-container {
        margin-top: 20px;
    }

    .routine-section {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .section-header h2 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 15px 0;
        color: var(--text-primary, #333);
        font-size: 1.2em;
    }

    .routine-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .routine-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: var(--bg-light, #f5f5f5);
        border-radius: 12px;
        transition: all 0.2s;
    }

    .routine-item:hover {
        transform: translateX(-5px);
    }

    .routine-checkbox {
        display: flex;
        align-items: center;
    }

    .routine-checkbox input {
        width: 20px;
        height: 20px;
        accent-color: var(--primary-color, #4CAF50);
        cursor: pointer;
    }

    .routine-content {
        flex: 1;
    }

    .routine-title {
        font-weight: bold;
        color: var(--text-primary, #333);
        margin-bottom: 5px;
    }

    .routine-habits {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .habit-tag {
        padding: 3px 8px;
        background: var(--primary-color, #4CAF50);
        color: white;
        border-radius: 12px;
        font-size: 0.8em;
    }

    .routine-actions {
        display: flex;
        gap: 8px;
    }

    .habits-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
        max-height: 200px;
        overflow-y: auto;
        padding: 10px;
        background: var(--bg-light, #f5f5f5);
        border-radius: 8px;
    }

    .habit-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .habit-checkbox:hover {
        background: rgba(0,0,0,0.05);
    }

    .habit-checkbox input {
        accent-color: var(--primary-color, #4CAF50);
    }

    .habit-checkbox span {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }
    </style>
</div>
