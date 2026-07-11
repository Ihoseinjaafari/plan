<?php
// /Habits/pages/dashboard.php - داشبورد عادت‌ها
if (!isset($userId)) {
    header('Location: ../index.php');
    exit;
}

$habitsFile = __DIR__ . '/../../data/habits.json';
$habitLogsFile = __DIR__ . '/../../data/habit_logs.json';

if (!file_exists($habitsFile)) file_put_contents($habitsFile, json_encode([]));
if (!file_exists($habitLogsFile)) file_put_contents($habitLogsFile, json_encode([]));

// توابع تبدیل تاریخ
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

function getJalaliDate() {
    list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('n'), date('j'));
    return sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
}

function getUserHabits($userId) {
    global $habitsFile;
    if (!file_exists($habitsFile)) return [];
    $allHabits = json_decode(file_get_contents($habitsFile), true);
    if (!is_array($allHabits)) return [];
    return array_values(array_filter($allHabits, function($h) use ($userId) {
        return ($h['user_id'] ?? '') == $userId;
    }));
}

function getUserHabitLogs($userId) {
    global $habitLogsFile;
    if (!file_exists($habitLogsFile)) return [];
    $allLogs = json_decode(file_get_contents($habitLogsFile), true);
    if (!is_array($allLogs)) return [];
    return array_values(array_filter($allLogs, function($log) use ($userId) {
        return ($log['user_id'] ?? '') == $userId;
    }));
}

function getHabitStreak($userId, $habitId) {
    $logs = getUserHabitLogs($userId);
    $today = getJalaliDate();
    
    $habitLogs = array_values(array_filter($logs, function($log) use ($habitId) {
        return ($log['habit_id'] ?? '') == $habitId && ($log['completed'] ?? false) == true;
    }));
    
    usort($habitLogs, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
    
    $streak = 0;
    $checkDate = $today;
    
    while (true) {
        $found = false;
        foreach ($habitLogs as $log) {
            if ($log['date'] == $checkDate) {
                $streak++;
                $found = true;
                break;
            }
        }
        
        if (!$found) break;
        
        list($jy, $jm, $jd) = explode('-', $checkDate);
        $jd--;
        if ($jd < 1) {
            $jm--;
            if ($jm < 1) {
                $jy--;
                $jm = 12;
            }
            $lastDayOfMonth = ($jm <= 6) ? 31 : (($jm <= 11) ? 30 : 29);
            $jd = $lastDayOfMonth;
        }
        $checkDate = sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
    }
    
    return $streak;
}

function isHabitCompletedToday($userId, $habitId) {
    $logs = getUserHabitLogs($userId);
    $today = getJalaliDate();
    
    foreach ($logs as $log) {
        if ($log['habit_id'] == $habitId && $log['date'] == $today) {
            return ($log['completed'] ?? false) == true;
        }
    }
    return false;
}

function getHabitCompletionRate($userId, $habitId, $days = 30) {
    $logs = getUserHabitLogs($userId);
    $habitLogs = array_filter($logs, function($log) use ($habitId) {
        return ($log['habit_id'] ?? '') == $habitId;
    });
    
    $total = count($habitLogs);
    $completed = count(array_filter($habitLogs, function($log) {
        return ($log['completed'] ?? false) == true;
    }));
    
    if ($total == 0) return 0;
    return round(($completed / $total) * 100);
}

// دریافت عادت‌های کاربر
$userHabits = getUserHabits($userId);

// محاسبه آمار
$totalHabits = count($userHabits);
$completedToday = 0;
$totalStreak = 0;

foreach ($userHabits as $habit) {
    if (isHabitCompletedToday($userId, $habit['id'])) {
        $completedToday++;
    }
    $totalStreak += getHabitStreak($userId, $habit['id']);
}

$completionRate = $totalHabits > 0 ? round(($completedToday / $totalHabits) * 100) : 0;
?>

<div class="habits-dashboard">
    <!-- کارت‌های آمار -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-value"><?= $totalHabits ?></div>
            <div class="stat-label">کل عادت‌ها</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $completedToday ?>/<<?= $totalHabits ?></div>
            <div class="stat-label">انجام شده امروز</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $completionRate ?>%</div>
            <div class="stat-label">نرخ تکمیل امروز</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><i class="icon-fire" style="font-size: 24px;"></i> <?= $totalStreak ?></div>
            <div class="stat-label">مجموع زنجیره‌ها</div>
        </div>
    </div>

    <!-- لیست عادت‌ها -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0; font-size: 20px; color: var(--text-primary);">عادت‌های من</h3>
        <button class="btn-primary" onclick="openAddHabitModal()" style="padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-family: inherit; display: flex; align-items: center; gap: 8px;">
            <span class="icon icon-plus"></span>
            افزودن عادت
        </button>
    </div>

    <?php if (empty($userHabits)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><span class="icon icon-heart"></span></div>
            <div class="empty-state-title">هنوز عادتی اضافه نکرده‌اید</div>
            <p>اولین عادت خود را برای پیگیری روزانه اضافه کنید</p>
            <button class="btn-primary" onclick="openAddHabitModal()" style="margin-top: 15px; padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-family: inherit;">
                <span class="icon icon-plus"></span>
                افزودن اولین عادت
            </button>
        </div>
    <?php else: ?>
        <div class="habits-grid">
            <?php foreach ($userHabits as $habit): 
                $streak = getHabitStreak($userId, $habit['id']);
                $rate = getHabitCompletionRate($userId, $habit['id']);
                $completed = isHabitCompletedToday($userId, $habit['id']);
            ?>
                <div class="habit-card" id="habit-<?= $habit['id'] ?>">
                    <div class="habit-card-header">
                        <div class="habit-color-indicator" style="background: <?= $habit['color'] ?? '#667eea' ?>;"></div>
                        <div class="habit-title"><?= htmlspecialchars($habit['title']) ?></div>
                        <div class="habit-actions">
                            <button class="habit-action-btn" onclick="editHabit(<?= $habit['id'] ?>)" title="ویرایش">
                                <span class="icon icon-edit"></span>
                            </button>
                            <button class="habit-action-btn" onclick="deleteHabit(<?= $habit['id'] ?>)" title="حذف" style="color: var(--danger-color);">
                                <span class="icon icon-trash"></span>
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($habit['description'])): ?>
                        <div class="habit-description"><?= htmlspecialchars($habit['description']) ?></div>
                    <?php endif; ?>
                    
                    <div class="habit-stats">
                        <div class="habit-stat">
                            <span class="icon icon-fire" style="color: var(--warning-color);"></span>
                            <span>زنجیره: </span>
                            <span class="habit-stat-value"><?= $streak ?> روز</span>
                        </div>
                        <div class="habit-stat">
                            <span class="icon icon-chart"></span>
                            <span>نرخ تکمیل: </span>
                            <span class="habit-stat-value"><?= $rate ?>%</span>
                        </div>
                    </div>
                    
                    <button class="habit-check-btn <?= $completed ? 'completed' : '' ?>" 
                            onclick="toggleHabit(<?= $habit['id'] ?>, this)"
                            data-habit-id="<?= $habit['id'] ?>">
                        <?= $completed ? '✅ انجام شده' : '⬜ علامت‌زدن به عنوان انجام شده' ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- مودال افزودن/ویرایش عادت -->
<div id="habitModal" class="modal-backdrop">
    <div class="modal-box">
        <div class="modal-header">
            <span id="modalTitle">افزودن عادت جدید</span>
            <button class="habit-action-btn" onclick="closeHabitModal()">
                <span class="icon icon-close"></span>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="habitId">
            <div class="form-group">
                <label class="form-label">عنوان عادت <span style="color: var(--danger-color);">*</span></label>
                <input type="text" id="habitTitle" class="form-input" placeholder="مثلاً: ورزش صبحگاهی، مطالعه روزانه...">
            </div>
            <div class="form-group">
                <label class="form-label">توضیحات</label>
                <textarea id="habitDescription" class="form-input" rows="3" placeholder="توضیحات اختیاری درباره این عادت"></textarea>
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">رنگ</label>
                    <input type="color" id="habitColor" class="form-input" value="#667eea" style="height: 45px; padding: 5px;">
                </div>
                <div class="form-group">
                    <label class="form-label">تکرار</label>
                    <select id="habitFrequency" class="form-input">
                        <option value="daily">روزانه</option>
                        <option value="weekly">هفتگی</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-ghost" onclick="closeHabitModal()" style="padding: 10px 20px; border-radius: 10px; cursor: pointer; font-family: inherit;">انصراف</button>
            <button class="btn-primary" onclick="saveHabit()" style="padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-family: inherit;">
                <span class="icon icon-save"></span>
                ذخیره
            </button>
        </div>
    </div>
</div>

<script>
const API_BASE = '../Habits/api.php';

function openAddHabitModal() {
    document.getElementById('habitId').value = '';
    document.getElementById('habitTitle').value = '';
    document.getElementById('habitDescription').value = '';
    document.getElementById('habitColor').value = '#667eea';
    document.getElementById('habitFrequency').value = 'daily';
    document.getElementById('modalTitle').textContent = 'افزودن عادت جدید';
    document.getElementById('habitModal').classList.add('show');
}

function closeHabitModal() {
    document.getElementById('habitModal').classList.remove('show');
}

async function saveHabit() {
    const id = document.getElementById('habitId').value;
    const title = document.getElementById('habitTitle').value.trim();
    
    if (!title) {
        alert('لطفاً عنوان عادت را وارد کنید');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', id ? 'edit_habit' : 'add_habit');
    formData.append('habit_id', id);
    formData.append('title', title);
    formData.append('description', document.getElementById('habitDescription').value.trim());
    formData.append('color', document.getElementById('habitColor').value);
    formData.append('frequency', document.getElementById('habitFrequency').value);
    
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('خطا در ذخیره عادت');
        }
    } catch (error) {
        console.error(error);
        alert('خطا در ارتباط با سرور');
    }
}

async function editHabit(id) {
    // TODO: بارگذاری اطلاعات عادت و نمایش در مودال
    alert('قابلیت ویرایش به زودی اضافه می‌شود');
}

async function deleteHabit(id) {
    if (!confirm('آیا از حذف این عادت مطمئن هستید؟')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_habit');
    formData.append('habit_id', id);
    
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('habit-' + id).remove();
        } else {
            alert('خطا در حذف عادت');
        }
    } catch (error) {
        console.error(error);
        alert('خطا در ارتباط با سرور');
    }
}

async function toggleHabit(id, btn) {
    const completed = !btn.classList.contains('completed');
    
    const formData = new FormData();
    formData.append('action', 'toggle_habit');
    formData.append('habit_id', id);
    formData.append('completed', completed ? 'true' : 'false');
    
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            btn.classList.toggle('completed', completed);
            btn.textContent = completed ? '✅ انجام شده' : '⬜ علامت‌زدن به عنوان انجام شده';
        } else {
            alert('خطا در ثبت وضعیت');
        }
    } catch (error) {
        console.error(error);
        alert('خطا در ارتباط با سرور');
    }
}
</script>
