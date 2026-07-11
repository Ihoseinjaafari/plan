<?php
// /Habits/pages/analytics.php - صفحه آنالیتیکس و پیشرفت
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

$userHabits = getUserHabits($userId);
$userLogs = getUserHabitLogs($userId);

// محاسبه آمار
$totalHabits = count($userHabits);
$totalCompletions = count(array_filter($userLogs, fn($log) => $log['completed']));
$today = getJalaliDate();
$todayLogs = array_filter($userLogs, fn($log) => $log['date'] == $today);
$todayCompletions = count(array_filter($todayLogs, fn($log) => $log['completed']));
$completionRate = $totalHabits > 0 ? round(($todayCompletions / $totalHabits) * 100) : 0;

// محاسبه استریک میانگین
$avgStreak = 0;
if ($totalHabits > 0) {
    $streaks = [];
    foreach ($userHabits as $habit) {
        $habitLogs = array_filter($userLogs, fn($log) => $log['habit_id'] == $habit['id'] && $log['completed']);
        $streaks[] = count($habitLogs);
    }
    $avgStreak = round(array_sum($streaks) / count($streaks));
}

// داده‌های هفتگی برای نمودار
$weekData = [];
$daysFa = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
for ($i = 6; $i >= 0; $i--) {
    $date = new DateTime();
    $date->sub(new DateInterval("P{$i}D"));
    $jDate = gregorian_to_jalali($date->format('Y'), $date->format('n'), $date->format('j'), '-');
    $dayIndex = (int) $date->format('w'); // 0=Sunday
    $dayName = $daysFa[($dayIndex + 1) % 7];
    
    $dayLogs = array_filter($userLogs, fn($log) => $log['date'] == $jDate && $log['completed']);
    $weekData[] = [
        'day' => $dayName,
        'date' => $jDate,
        'count' => count($dayLogs)
    ];
}

// عادت‌های برتر
$habitStats = [];
foreach ($userHabits as $habit) {
    $habitLogs = array_filter($userLogs, fn($log) => $log['habit_id'] == $habit['id'] && $log['completed']);
    $habitStats[] = [
        'id' => $habit['id'],
        'name' => $habit['name'],
        'color' => $habit['color'] ?? '#4CAF50',
        'completions' => count($habitLogs)
    ];
}
usort($habitStats, fn($a, $b) => $b['completions'] - $a['completions']);
$topHabits = array_slice($habitStats, 0, 5);
?>

<div class="analytics-page habits-dashboard">
    <div class="page-header">
        <h1><span class="icon icon-chart"></span> آنالیتیکس و پیشرفت</h1>
    </div>

    <div class="analytics-container">
        <!-- کارت‌های آمار -->
        <div class="stats-overview">
            <div class="stat-card large">
                <div class="stat-icon" style="background: rgba(76, 175, 80, 0.1);">
                    <span class="icon icon-target" style="color: var(--primary-color, #4CAF50);"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $totalHabits ?></div>
                    <div class="stat-label">عادت‌های کل</div>
                </div>
            </div>

            <div class="stat-card large">
                <div class="stat-icon" style="background: rgba(33, 150, 243, 0.1);">
                    <span class="icon icon-check-circle" style="color: #2196F3;"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $totalCompletions ?></div>
                    <div class="stat-label">تکمیل‌های کل</div>
                </div>
            </div>

            <div class="stat-card large">
                <div class="stat-icon" style="background: rgba(255, 152, 0, 0.1);">
                    <span class="icon icon-today" style="color: #FF9800;"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $completionRate ?>%</div>
                    <div class="stat-label">نرخ امروز</div>
                </div>
            </div>

            <div class="stat-card large">
                <div class="stat-icon" style="background: rgba(156, 39, 176, 0.1);">
                    <span class="icon icon-fire" style="color: #9C27B0;"></span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $avgStreak ?></div>
                    <div class="stat-label">میانگین زنجیره</div>
                </div>
            </div>
        </div>

        <!-- نمودار هفتگی -->
        <div class="chart-section">
            <h2>عملکرد هفتگی</h2>
            <div class="bar-chart">
                <?php foreach ($weekData as $item): ?>
                    <div class="bar-item">
                        <div class="bar-container">
                            <div class="bar-fill" 
                                 style="height: <?= $totalHabits > 0 ? min(100, ($item['count'] / $totalHabits) * 100) : 0 ?>%;
                                        background: var(--primary-color, #4CAF50);"></div>
                        </div>
                        <div class="bar-label"><?= $item['day'] ?></div>
                        <div class="bar-value"><?= $item['count'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- عادت‌های برتر -->
        <div class="top-habits-section">
            <h2>عادت‌های برتر</h2>
            <?php if (empty($topHabits)): ?>
                <div class="empty-state">
                    <p>هنوز داده‌ای برای نمایش وجود ندارد</p>
                </div>
            <?php else: ?>
                <div class="habits-ranking">
                    <?php foreach ($topHabits as $index => $habit): ?>
                        <div class="rank-item">
                            <div class="rank-number" style="background: <?= $habit['color'] ?>">
                                <?= $index + 1 ?>
                            </div>
                            <div class="rank-info">
                                <div class="rank-name"><?= htmlspecialchars($habit['name']) ?></div>
                                <div class="rank-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" 
                                             style="width: <?= min(100, ($habit['completions'] / max(1, $totalCompletions)) * 100) ?>%;
                                                    background: <?= $habit['color'] ?>"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="rank-count"><?= $habit['completions'] ?> بار</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- تقویم حرارتی -->
        <div class="heatmap-section">
            <h2>تقویم فعالیت (۳۰ روز اخیر)</h2>
            <div class="heatmap-grid">
                <?php
                $heatmapData = [];
                for ($i = 29; $i >= 0; $i--) {
                    $date = new DateTime();
                    $date->sub(new DateInterval("P{$i}D"));
                    $jDate = gregorian_to_jalali($date->format('Y'), $date->format('n'), $date->format('j'), '-');
                    $dayLogs = array_filter($userLogs, fn($log) => $log['date'] == $jDate && $log['completed']);
                    $heatmapData[] = [
                        'date' => $jDate,
                        'count' => count($dayLogs),
                        'dayOfWeek' => (int) $date->format('w')
                    ];
                }
                
                // گروه‌بندی بر اساس روز هفته
                $weeks = [];
                $currentWeek = [];
                foreach ($heatmapData as $item) {
                    $currentWeek[] = $item;
                    if ($item['dayOfWeek'] == 6 || count($currentWeek) == 7) {
                        $weeks[] = $currentWeek;
                        $currentWeek = [];
                    }
                }
                if (!empty($currentWeek)) {
                    $weeks[] = $currentWeek;
                }
                
                foreach ($weeks as $week):
                ?>
                    <div class="heatmap-week">
                        <?php foreach ($week as $day): ?>
                            <div class="heatmap-day <?= $day['count'] > 0 ? 'active' : '' ?>" 
                                 data-count="<?= $day['count'] ?>"
                                 data-date="<?= $day['date'] ?>"
                                 style="<?= $day['count'] > 0 ? 'background: var(--primary-color, #4CAF50); opacity: ' . min(1, $day['count'] / 5) : '' ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="heatmap-legend">
                <span>کمتر</span>
                <div class="legend-gradient"></div>
                <span>بیشتر</span>
            </div>
        </div>
    </div>

    <style>
    .analytics-container {
        margin-top: 20px;
    }

    .stats-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card.large {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: var(--card-bg, #fff);
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-icon span {
        font-size: 1.8em;
    }

    .stat-info {
        flex: 1;
    }

    .stat-value {
        font-size: 2em;
        font-weight: bold;
        color: var(--text-primary, #333);
    }

    .stat-label {
        color: var(--text-secondary, #666);
        font-size: 0.9em;
    }

    .chart-section,
    .top-habits-section,
    .heatmap-section {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .chart-section h2,
    .top-habits-section h2,
    .heatmap-section h2 {
        margin: 0 0 20px 0;
        color: var(--text-primary, #333);
    }

    .bar-chart {
        display: flex;
        justify-content: space-around;
        align-items: flex-end;
        height: 200px;
        padding: 20px 10px;
    }

    .bar-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        flex: 1;
    }

    .bar-container {
        width: 40px;
        height: 150px;
        background: var(--bg-light, #f5f5f5);
        border-radius: 8px;
        overflow: hidden;
        position: relative;
    }

    .bar-fill {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        border-radius: 8px 8px 0 0;
        transition: height 0.5s ease;
    }

    .bar-label {
        font-size: 0.85em;
        color: var(--text-secondary, #666);
    }

    .bar-value {
        font-weight: bold;
        color: var(--primary-color, #4CAF50);
    }

    .habits-ranking {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .rank-item {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .rank-number {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 1.2em;
    }

    .rank-info {
        flex: 1;
    }

    .rank-name {
        font-weight: bold;
        color: var(--text-primary, #333);
        margin-bottom: 5px;
    }

    .progress-bar {
        height: 8px;
        background: var(--bg-light, #f5f5f5);
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.5s ease;
    }

    .rank-count {
        color: var(--text-secondary, #666);
        font-size: 0.9em;
    }

    .heatmap-grid {
        display: flex;
        flex-direction: column;
        gap: 5px;
        margin-bottom: 15px;
    }

    .heatmap-week {
        display: flex;
        gap: 5px;
    }

    .heatmap-day {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        background: var(--bg-light, #f5f5f5);
        cursor: pointer;
        transition: transform 0.2s;
    }

    .heatmap-day:hover {
        transform: scale(1.2);
    }

    .heatmap-day.active {
        background: var(--primary-color, #4CAF50);
    }

    .heatmap-legend {
        display: flex;
        align-items: center;
        gap: 10px;
        justify-content: center;
        color: var(--text-secondary, #666);
        font-size: 0.85em;
    }

    .legend-gradient {
        width: 150px;
        height: 10px;
        border-radius: 5px;
        background: linear-gradient(to right, #f5f5f5, var(--primary-color, #4CAF50));
    }
    </style>
</div>
