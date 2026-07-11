<?php
// /Habits/pages/focus.php - صفحه تمرکز (تایمر پومودورو)
if (!isset($userId)) {
    header('Location: ../index.php');
    exit;
}

$focusSessionsFile = __DIR__ . '/../../data/focus_sessions.json';

if (!file_exists($focusSessionsFile)) {
    file_put_contents($focusSessionsFile, json_encode([]));
}

function getUserFocusSessions($userId) {
    global $focusSessionsFile;
    if (!file_exists($focusSessionsFile)) return [];
    $allSessions = json_decode(file_get_contents($focusSessionsFile), true);
    if (!is_array($allSessions)) return [];
    return array_values(array_filter($allSessions, function($s) use ($userId) {
        return ($s['user_id'] ?? '') == $userId;
    }));
}

$userSessions = getUserFocusSessions($userId);
$totalMinutes = array_sum(array_column($userSessions, 'duration'));
$totalSessions = count($userSessions);
?>

<div class="focus-page habits-dashboard">
    <div class="page-header">
        <h1><span class="icon icon-clock"></span> تمرکز</h1>
    </div>

    <div class="focus-container">
        <!-- تایمر -->
        <div class="timer-section">
            <div class="timer-modes">
                <button class="mode-btn active" data-mode="pomodoro" onclick="setTimerMode('pomodoro')">
                    <span class="icon icon-tomato"></span> پومودورو
                </button>
                <button class="mode-btn" data-mode="short-break" onclick="setTimerMode('short-break')">
                    <span class="icon icon-coffee"></span> استراحت کوتاه
                </button>
                <button class="mode-btn" data-mode="long-break" onclick="setTimerMode('long-break')">
                    <span class="icon icon-bed"></span> استراحت بلند
                </button>
            </div>

            <div class="timer-display">
                <div class="timer-circle">
                    <svg viewBox="0 0 100 100">
                        <circle class="timer-bg" cx="50" cy="50" r="45"/>
                        <circle class="timer-progress" cx="50" cy="50" r="45" 
                                stroke-dasharray="<?= 2 * M_PI * 45 ?>" 
                                stroke-dashoffset="<?= 2 * M_PI * 45 ?>"/>
                    </svg>
                    <div class="timer-time" id="timerTime">25:00</div>
                </div>
                <div class="timer-status" id="timerStatus">آماده شروع</div>
            </div>

            <div class="timer-controls">
                <button class="btn btn-primary btn-large" id="startBtn" onclick="toggleTimer()">
                    <span class="icon icon-play"></span> شروع
                </button>
                <button class="btn btn-secondary btn-large" onclick="resetTimer()">
                    <span class="icon icon-refresh"></span> ریست
                </button>
            </div>

            <div class="timer-settings">
                <div class="setting-item">
                    <label>مدت پومودورو (دقیقه)</label>
                    <input type="number" id="pomodoroDuration" value="25" min="1" max="60" onchange="updateSettings()">
                </div>
                <div class="setting-item">
                    <label>استراحت کوتاه (دقیقه)</label>
                    <input type="number" id="shortBreakDuration" value="5" min="1" max="30" onchange="updateSettings()">
                </div>
                <div class="setting-item">
                    <label>استراحت بلند (دقیقه)</label>
                    <input type="number" id="longBreakDuration" value="15" min="1" max="60" onchange="updateSettings()">
                </div>
            </div>
        </div>

        <!-- آمار -->
        <div class="stats-section">
            <h2>آمار تمرکز</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $totalSessions ?></div>
                    <div class="stat-label">جلسات کل</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= floor($totalMinutes / 60) ?>:<span style="font-size:0.8em"><?= str_pad($totalMinutes % 60, 2, '0', STR_PAD_LEFT) ?></span></div>
                    <div class="stat-label">ساعت تمرکز</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= count(array_filter($userSessions, fn($s) => $s['date'] == date('Y-m-d'))) ?></div>
                    <div class="stat-label">امروز</div>
                </div>
            </div>

            <div class="recent-sessions">
                <h3>جلسات اخیر</h3>
                <?php if (empty($userSessions)): ?>
                    <p class="empty-text">هنوز جلسه‌ای ثبت نشده است</p>
                <?php else: ?>
                    <?php 
                    usort($userSessions, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
                    $recent = array_slice($userSessions, 0, 5);
                    foreach ($recent as $session): 
                    ?>
                        <div class="session-item">
                            <div class="session-info">
                                <span class="session-duration"><?= $session['duration'] ?> دقیقه</span>
                                <span class="session-date"><?= $session['date'] ?></span>
                            </div>
                            <span class="session-time"><?= $session['time'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    let timerMode = 'pomodoro';
    let timeLeft = 25 * 60;
    let totalTime = 25 * 60;
    let timerInterval = null;
    let isRunning = false;

    const modes = {
        'pomodoro': { label: 'پومودورو', default: 25 },
        'short-break': { label: 'استراحت کوتاه', default: 5 },
        'long-break': { label: 'استراحت بلند', default: 15 }
    };

    function setTimerMode(mode) {
        timerMode = mode;
        document.querySelectorAll('.mode-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });
        
        const duration = parseInt(document.getElementById(mode + 'Duration').value);
        totalTime = duration * 60;
        timeLeft = totalTime;
        updateTimerDisplay();
        resetProgress();
    }

    function updateTimerDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        document.getElementById('timerTime').textContent = 
            `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // به‌روزرسانی حلقه پیشرفت
        const progress = timeLeft / totalTime;
        const circumference = 2 * Math.PI * 45;
        const offset = circumference * (1 - progress);
        document.querySelector('.timer-progress').style.strokeDashoffset = offset;
    }

    function resetProgress() {
        const circumference = 2 * Math.PI * 45;
        document.querySelector('.timer-progress').style.strokeDashoffset = circumference;
    }

    function toggleTimer() {
        const btn = document.getElementById('startBtn');
        if (isRunning) {
            pauseTimer();
        } else {
            startTimer();
        }
    }

    function startTimer() {
        isRunning = true;
        document.getElementById('startBtn').innerHTML = '<span class="icon icon-pause"></span> توقف';
        document.getElementById('timerStatus').textContent = 'در حال اجرا...';
        
        timerInterval = setInterval(() => {
            timeLeft--;
            updateTimerDisplay();
            
            if (timeLeft <= 0) {
                completeSession();
            }
        }, 1000);
    }

    function pauseTimer() {
        isRunning = false;
        clearInterval(timerInterval);
        document.getElementById('startBtn').innerHTML = '<span class="icon icon-play"></span> ادامه';
        document.getElementById('timerStatus').textContent = 'متوقف شده';
    }

    function resetTimer() {
        pauseTimer();
        const duration = parseInt(document.getElementById(timerMode + 'Duration').value);
        totalTime = duration * 60;
        timeLeft = totalTime;
        updateTimerDisplay();
        resetProgress();
        document.getElementById('startBtn').innerHTML = '<span class="icon icon-play"></span> شروع';
        document.getElementById('timerStatus').textContent = 'آماده شروع';
    }

    function completeSession() {
        pauseTimer();
        
        // پخش صدا (اختیاری)
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQQAAAAAAA==');
            audio.play().catch(() => {});
        } catch(e) {}
        
        alert('زمان به پایان رسید!');
        
        // ذخیره جلسه
        if (timerMode === 'pomodoro') {
            const duration = parseInt(document.getElementById('pomodoroDuration').value);
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'log_focus_session',
                    user_id: '<?= $userId ?>',
                    duration: duration,
                    mode: timerMode
                })
            });
        }
        
        document.getElementById('timerStatus').textContent = 'پایان یافت';
        setTimeout(() => {
            resetTimer();
        }, 2000);
    }

    function updateSettings() {
        if (!isRunning) {
            const duration = parseInt(document.getElementById(timerMode + 'Duration').value);
            totalTime = duration * 60;
            timeLeft = totalTime;
            updateTimerDisplay();
        }
    }

    // مقداردهی اولیه
    updateTimerDisplay();
    </script>

    <style>
    .focus-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }

    @media (max-width: 900px) {
        .focus-container {
            grid-template-columns: 1fr;
        }
    }

    .timer-section {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .timer-modes {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
    }

    .mode-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        border: 2px solid var(--border-color, #ddd);
        background: var(--bg-light, #f5f5f5);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
        font-size: 0.95em;
    }

    .mode-btn:hover {
        background: var(--bg-hover, #e0e0e0);
    }

    .mode-btn.active {
        border-color: var(--primary-color, #4CAF50);
        background: rgba(76, 175, 80, 0.1);
        color: var(--primary-color, #4CAF50);
    }

    .timer-display {
        text-align: center;
        margin: 40px 0;
    }

    .timer-circle {
        position: relative;
        width: 250px;
        height: 250px;
        margin: 0 auto 20px;
    }

    .timer-circle svg {
        width: 100%;
        height: 100%;
        transform: rotate(-90deg);
    }

    .timer-bg {
        fill: none;
        stroke: var(--bg-light, #f5f5f5);
        stroke-width: 8;
    }

    .timer-progress {
        fill: none;
        stroke: var(--primary-color, #4CAF50);
        stroke-width: 8;
        stroke-linecap: round;
        transition: stroke-dashoffset 1s linear;
    }

    .timer-time {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 3em;
        font-weight: bold;
        color: var(--text-primary, #333);
    }

    .timer-status {
        font-size: 1.2em;
        color: var(--text-secondary, #666);
    }

    .timer-controls {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-bottom: 30px;
    }

    .btn-large {
        padding: 15px 30px;
        font-size: 1.1em;
    }

    .timer-settings {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }

    .setting-item {
        text-align: center;
    }

    .setting-item label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.9em;
        color: var(--text-secondary, #666);
    }

    .setting-item input {
        width: 100%;
        padding: 8px;
        border: 1px solid var(--border-color, #ddd);
        border-radius: 8px;
        text-align: center;
        font-family: inherit;
    }

    .stats-section {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .stats-section h2 {
        margin-bottom: 20px;
        color: var(--text-primary, #333);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--bg-light, #f5f5f5);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
    }

    .stat-value {
        font-size: 2em;
        font-weight: bold;
        color: var(--primary-color, #4CAF50);
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 0.9em;
        color: var(--text-secondary, #666);
    }

    .recent-sessions h3 {
        margin-bottom: 15px;
        color: var(--text-primary, #333);
    }

    .session-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid var(--border-color, #eee);
    }

    .session-item:last-child {
        border-bottom: none;
    }

    .session-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .session-duration {
        font-weight: bold;
        color: var(--text-primary, #333);
    }

    .session-date {
        font-size: 0.85em;
        color: var(--text-secondary, #666);
    }

    .session-time {
        color: var(--text-secondary, #666);
        font-size: 0.9em;
    }

    .empty-text {
        color: var(--text-secondary, #666);
        text-align: center;
        padding: 20px;
    }
    </style>
</div>
