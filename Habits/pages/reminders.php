<?php
// /Habits/pages/reminders.php - صفحه یادآورها
if (!isset($userId)) {
    header('Location: ../index.php');
    exit;
}

$remindersFile = __DIR__ . '/../../data/reminders.json';

if (!file_exists($remindersFile)) {
    file_put_contents($remindersFile, json_encode([]));
}

function getUserReminders($userId) {
    global $remindersFile;
    if (!file_exists($remindersFile)) return [];
    $allReminders = json_decode(file_get_contents($remindersFile), true);
    if (!is_array($allReminders)) return [];
    return array_values(array_filter($allReminders, function($r) use ($userId) {
        return ($r['user_id'] ?? '') == $userId;
    }));
}

$userReminders = getUserReminders($userId);
?>

<div class="reminders-page habits-dashboard">
    <div class="page-header">
        <h1><span class="icon icon-bell"></span> یادآورها</h1>
        <button class="btn btn-primary" onclick="openReminderModal()">
            <span class="icon icon-plus"></span> افزودن یادآور
        </button>
    </div>

    <div class="reminders-grid">
        <?php if (empty($userReminders)): ?>
            <div class="empty-state">
                <span class="icon icon-bell-off"></span>
                <h3>هیچ یادآوری وجود ندارد</h3>
                <p>اولین یادآور خود را برای عادت‌هایتان ایجاد کنید</p>
            </div>
        <?php else: ?>
            <?php foreach ($userReminders as $reminder): ?>
                <div class="reminder-card" data-id="<?= htmlspecialchars($reminder['id']) ?>">
                    <div class="reminder-header">
                        <div class="reminder-title"><?= htmlspecialchars($reminder['title']) ?></div>
                        <div class="reminder-actions">
                            <button class="btn-icon" onclick="editReminder('<?= htmlspecialchars($reminder['id']) ?>')">
                                <span class="icon icon-edit"></span>
                            </button>
                            <button class="btn-icon btn-danger" onclick="deleteReminder('<?= htmlspecialchars($reminder['id']) ?>')">
                                <span class="icon icon-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="reminder-body">
                        <div class="reminder-description"><?= htmlspecialchars($reminder['description'] ?? '') ?></div>
                        <div class="reminder-meta">
                            <div class="reminder-time">
                                <span class="icon icon-clock"></span>
                                <?= htmlspecialchars($reminder['time']) ?>
                            </div>
                            <div class="reminder-days">
                                <span class="icon icon-calendar"></span>
                                <?php
                                $daysMap = [
                                    'saturday' => 'ش',
                                    'sunday' => 'ی',
                                    'monday' => 'د',
                                    'tuesday' => 'س',
                                    'wednesday' => 'چ',
                                    'thursday' => 'پ',
                                    'friday' => 'ج'
                                ];
                                $days = json_decode($reminder['days'], true) ?? [];
                                foreach ($daysMap as $key => $label):
                                    $isActive = in_array($key, $days);
                                ?>
                                    <span class="day-badge <?= $isActive ? 'active' : '' ?>"><?= $label ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="reminder-status">
                            <span class="status-badge <?= $reminder['enabled'] ? 'enabled' : 'disabled' ?>">
                                <?= $reminder['enabled'] ? 'فعال' : 'غیرفعال' ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- مودال افزودن/ویرایش یادآور -->
    <div id="reminderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">افزودن یادآور جدید</h2>
                <button class="btn-close" onclick="closeReminderModal()">&times;</button>
            </div>
            <form id="reminderForm" onsubmit="saveReminder(event)">
                <input type="hidden" id="reminderId" name="id">
                
                <div class="form-group">
                    <label for="reminderTitle">عنوان یادآور</label>
                    <input type="text" id="reminderTitle" name="title" required placeholder="مثلاً: ورزش صبحگاهی">
                </div>

                <div class="form-group">
                    <label for="reminderDescription">توضیحات</label>
                    <textarea id="reminderDescription" name="description" rows="3" placeholder="توضیحات اضافی..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="reminderTime">زمان</label>
                        <input type="time" id="reminderTime" name="time" required>
                    </div>

                    <div class="form-group">
                        <label for="reminderEnabled">وضعیت</label>
                        <select id="reminderEnabled" name="enabled">
                            <option value="1">فعال</option>
                            <option value="0">غیرفعال</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>روزهای تکرار</label>
                    <div class="days-selector">
                        <?php
                        $daysNames = [
                            'saturday' => 'شنبه',
                            'sunday' => 'یکشنبه',
                            'monday' => 'دوشنبه',
                            'tuesday' => 'سه‌شنبه',
                            'wednesday' => 'چهارشنبه',
                            'thursday' => 'پنج‌شنبه',
                            'friday' => 'جمعه'
                        ];
                        foreach ($daysNames as $key => $name):
                        ?>
                            <label class="day-checkbox">
                                <input type="checkbox" name="days[]" value="<?= $key ?>" checked>
                                <span><?= $name ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeReminderModal()">انصراف</button>
                    <button type="submit" class="btn btn-primary">ذخیره یادآور</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const currentUserId = '<?= $userId ?>';

    function openReminderModal() {
        document.getElementById('modalTitle').textContent = 'افزودن یادآور جدید';
        document.getElementById('reminderForm').reset();
        document.getElementById('reminderId').value = '';
        document.querySelectorAll('.day-checkbox input').forEach(cb => cb.checked = true);
        document.getElementById('reminderModal').classList.add('active');
    }

    function closeReminderModal() {
        document.getElementById('reminderModal').classList.remove('active');
    }

    function editReminder(id) {
        fetch('api.php?action=get_reminder&id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const reminder = data.reminder;
                    document.getElementById('modalTitle').textContent = 'ویرایش یادآور';
                    document.getElementById('reminderId').value = reminder.id;
                    document.getElementById('reminderTitle').value = reminder.title;
                    document.getElementById('reminderDescription').value = reminder.description || '';
                    document.getElementById('reminderTime').value = reminder.time;
                    document.getElementById('reminderEnabled').value = reminder.enabled ? '1' : '0';
                    
                    const days = JSON.parse(reminder.days) || [];
                    document.querySelectorAll('.day-checkbox input').forEach(cb => {
                        cb.checked = days.includes(cb.value);
                    });
                    
                    document.getElementById('reminderModal').classList.add('active');
                }
            });
    }

    function saveReminder(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const data = {
            action: formData.get('id') ? 'update_reminder' : 'create_reminder',
            id: formData.get('id'),
            user_id: currentUserId,
            title: formData.get('title'),
            description: formData.get('description'),
            time: formData.get('time'),
            enabled: formData.get('enabled') == '1',
            days: Array.from(formData.getAll('days[]'))
        };

        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                alert('یادآور با موفقیت ذخیره شد');
                closeReminderModal();
                location.reload();
            } else {
                alert('خطا: ' + result.message);
            }
        });
    }

    function deleteReminder(id) {
        if (!confirm('آیا مطمئن هستید که می‌خواهید این یادآور را حذف کنید؟')) return;
        
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_reminder', id: id })
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                alert('یادآور حذف شد');
                location.reload();
            } else {
                alert('خطا: ' + result.message);
            }
        });
    }

    // بستن مودال با کلیک بیرون
    document.getElementById('reminderModal').addEventListener('click', function(e) {
        if (e.target === this) closeReminderModal();
    });
    </script>

    <style>
    .reminders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .reminder-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .reminder-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .reminder-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .reminder-title {
        font-weight: bold;
        font-size: 1.1em;
        color: var(--text-primary, #333);
    }

    .reminder-actions {
        display: flex;
        gap: 8px;
    }

    .reminder-body {
        color: var(--text-secondary, #666);
    }

    .reminder-description {
        margin-bottom: 15px;
        line-height: 1.6;
    }

    .reminder-meta {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 15px;
    }

    .reminder-time, .reminder-days {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .day-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--bg-light, #f5f5f5);
        color: var(--text-secondary, #999);
        font-size: 0.85em;
        font-weight: bold;
    }

    .day-badge.active {
        background: var(--primary-color, #4CAF50);
        color: white;
    }

    .reminder-status {
        text-align: left;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: bold;
    }

    .status-badge.enabled {
        background: rgba(76, 175, 80, 0.1);
        color: var(--success-color, #4CAF50);
    }

    .status-badge.disabled {
        background: rgba(158, 158, 158, 0.1);
        color: var(--text-secondary, #999);
    }

    .days-selector {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }

    .day-checkbox {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 8px;
        background: var(--bg-light, #f5f5f5);
        transition: background 0.2s;
    }

    .day-checkbox:hover {
        background: var(--bg-hover, #e0e0e0);
    }

    .day-checkbox input {
        accent-color: var(--primary-color, #4CAF50);
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid var(--border-color, #eee);
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.3em;
    }

    .btn-close {
        background: none;
        border: none;
        font-size: 1.5em;
        cursor: pointer;
        color: var(--text-secondary, #999);
    }

    .form-group {
        padding: 15px 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: var(--text-primary, #333);
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--border-color, #ddd);
        border-radius: 8px;
        font-family: inherit;
        font-size: 1em;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 20px;
        border-top: 1px solid var(--border-color, #eee);
    }
    </style>
</div>
