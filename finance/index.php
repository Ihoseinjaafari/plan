<?php
// finance/index.php - سیستم مدیریت مالی و حسابداری شخصی
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

$_SESSION['user_name'] = $currentUser['name'];

// ==================== فایل‌های دیتا ====================
$transactionsFile = __DIR__ . '/../data/finance_transactions.json';
$categoriesFile = __DIR__ . '/../data/finance_categories.json';
$recurringFile = __DIR__ . '/../data/finance_recurring.json';

if (!file_exists($transactionsFile)) file_put_contents($transactionsFile, json_encode([]));
if (!file_exists($categoriesFile)) {
    $defaultCategories = [
        ['id' => 'cat_1', 'name' => 'خوراک', 'type' => 'expense', 'icon' => 'fa-utensils'],
        ['id' => 'cat_2', 'name' => 'مسکن', 'type' => 'expense', 'icon' => 'fa-home'],
        ['id' => 'cat_3', 'name' => 'حمل‌ونقل', 'type' => 'expense', 'icon' => 'fa-car'],
        ['id' => 'cat_4', 'name' => 'سلامت', 'type' => 'expense', 'icon' => 'fa-heartbeat'],
        ['id' => 'cat_5', 'name' => 'آموزش', 'type' => 'expense', 'icon' => 'fa-book'],
        ['id' => 'cat_6', 'name' => 'تفریح', 'type' => 'expense', 'icon' => 'fa-film'],
        ['id' => 'cat_7', 'name' => 'حقوق', 'type' => 'income', 'icon' => 'fa-money-bill-wave'],
        ['id' => 'cat_8', 'name' => 'سایر درآمد', 'type' => 'income', 'icon' => 'fa-coins']
    ];
    file_put_contents($categoriesFile, json_encode($defaultCategories, JSON_PRETTY_PRINT));
}
if (!file_exists($recurringFile)) file_put_contents($recurringFile, json_encode([]));

// ==================== توابع ====================
function getAllTransactions() {
    global $transactionsFile;
    if (!file_exists($transactionsFile)) return [];
    $transactions = json_decode(file_get_contents($transactionsFile), true);
    return is_array($transactions) ? $transactions : [];
}

function saveAllTransactions($transactions) {
    global $transactionsFile;
    file_put_contents($transactionsFile, json_encode($transactions, JSON_PRETTY_PRINT));
}

function getCategories() {
    global $categoriesFile;
    if (!file_exists($categoriesFile)) return [];
    $categories = json_decode(file_get_contents($categoriesFile), true);
    return is_array($categories) ? $categories : [];
}

function saveCategories($categories) {
    global $categoriesFile;
    file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT));
}

function getRecurringExpenses() {
    global $recurringFile;
    if (!file_exists($recurringFile)) return [];
    $recurring = json_decode(file_get_contents($recurringFile), true);
    return is_array($recurring) ? $recurring : [];
}

function saveRecurringExpenses($recurring) {
    global $recurringFile;
    file_put_contents($recurringFile, json_encode($recurring, JSON_PRETTY_PRINT));
}

function getUserTransactions($userId) {
    $transactions = getAllTransactions();
    return array_values(array_filter($transactions, function($t) use ($userId) {
        return ($t['user_id'] ?? '') == $userId;
    }));
}

function getUserRecurring($userId) {
    $recurring = getRecurringExpenses();
    return array_values(array_filter($recurring, function($r) use ($userId) {
        return ($r['user_id'] ?? '') == $userId;
    }));
}

// پردازش هزینه‌های تکراری و ایجاد تراکنش برای ماه جاری
function processRecurringExpenses($userId) {
    $recurring = getUserRecurring($userId);
    $transactions = getAllTransactions();
    $currentMonth = date('Y-m');
    $today = date('Y-m-d');
    $modified = false;
    
    foreach ($recurring as $item) {
        if (($item['user_id'] ?? '') != $userId) continue;
        
        // بررسی اینکه آیا برای ماه جاری تراکنش ایجاد شده یا نه
        $alreadyCreated = false;
        foreach ($transactions as $t) {
            if (($t['user_id'] ?? '') == $userId && 
                ($t['recurring_id'] ?? '') == $item['id'] &&
                date('Y-m', strtotime($t['date'])) == $currentMonth) {
                $alreadyCreated = true;
                break;
            }
        }
        
        if (!$alreadyCreated && $item['active']) {
            // محاسبه تاریخ وقوع در ماه جاری
            $dayOfMonth = (int)($item['day_of_month'] ?? 1);
            $daysInMonth = (int)date('t');
            if ($dayOfMonth > $daysInMonth) $dayOfMonth = $daysInMonth;
            $transactionDate = sprintf('%s-%02d', $currentMonth, $dayOfMonth);
            
            // اگر تاریخ گذشته، امروز ثبت کن
            if ($transactionDate > $today) {
                continue; // هنوز نرسیده
            }
            
            $newTransaction = [
                'id' => 'txn_' . time() . '_' . rand(1000, 9999),
                'user_id' => $userId,
                'type' => $item['type'],
                'amount' => $item['amount'],
                'category_id' => $item['category_id'],
                'description' => $item['description'] . ' (تکراری)',
                'date' => $transactionDate,
                'created_at' => date('Y-m-d H:i:s'),
                'recurring_id' => $item['id']
            ];
            
            $transactions[] = $newTransaction;
            $modified = true;
        }
    }
    
    if ($modified) {
        saveAllTransactions($transactions);
    }
}

// ==================== پردازش درخواست‌های POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['user_id'];
    $response = ['success' => false];
    
    try {
        if ($action === 'add_transaction') {
            $transactions = getAllTransactions();
            $newTransaction = [
                'id' => 'txn_' . time() . '_' . rand(1000, 9999),
                'user_id' => $userId,
                'type' => $_POST['type'] ?? 'expense',
                'amount' => floatval($_POST['amount']),
                'category_id' => $_POST['category_id'] ?? '',
                'description' => htmlspecialchars($_POST['description'] ?? ''),
                'date' => $_POST['date'] ?? date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $transactions[] = $newTransaction;
            saveAllTransactions($transactions);
            $response = ['success' => true, 'transaction' => $newTransaction];
            
        } elseif ($action === 'update_transaction') {
            $transactions = getAllTransactions();
            $txnId = $_POST['id'] ?? '';
            foreach ($transactions as &$t) {
                if ($t['id'] == $txnId && ($t['user_id'] ?? '') == $userId) {
                    $t['type'] = $_POST['type'] ?? $t['type'];
                    $t['amount'] = floatval($_POST['amount'] ?? $t['amount']);
                    $t['category_id'] = $_POST['category_id'] ?? $t['category_id'];
                    $t['description'] = htmlspecialchars($_POST['description'] ?? $t['description']);
                    $t['date'] = $_POST['date'] ?? $t['date'];
                    break;
                }
            }
            saveAllTransactions($transactions);
            $response = ['success' => true];
            
        } elseif ($action === 'delete_transaction') {
            $transactions = getAllTransactions();
            $txnId = $_POST['id'] ?? '';
            $transactions = array_values(array_filter($transactions, function($t) use ($userId, $txnId) {
                return !($t['id'] == $txnId && ($t['user_id'] ?? '') == $userId);
            }));
            saveAllTransactions($transactions);
            $response = ['success' => true];
            
        } elseif ($action === 'add_category') {
            $categories = getCategories();
            $newCategory = [
                'id' => 'cat_' . time() . '_' . rand(1000, 9999),
                'name' => htmlspecialchars($_POST['name']),
                'type' => $_POST['type'] ?? 'expense',
                'icon' => $_POST['icon'] ?? 'fa-tag'
            ];
            $categories[] = $newCategory;
            saveCategories($categories);
            $response = ['success' => true, 'category' => $newCategory];
            
        } elseif ($action === 'update_category') {
            $categories = getCategories();
            $catId = $_POST['id'] ?? '';
            foreach ($categories as &$c) {
                if ($c['id'] == $catId) {
                    $c['name'] = htmlspecialchars($_POST['name'] ?? $c['name']);
                    $c['type'] = $_POST['type'] ?? $c['type'];
                    $c['icon'] = $_POST['icon'] ?? $c['icon'];
                    break;
                }
            }
            saveCategories($categories);
            $response = ['success' => true];
            
        } elseif ($action === 'delete_category') {
            $categories = getCategories();
            $catId = $_POST['id'] ?? '';
            $categories = array_values(array_filter($categories, function($c) use ($catId) {
                return $c['id'] != $catId;
            }));
            saveCategories($categories);
            $response = ['success' => true];
            
        } elseif ($action === 'add_recurring') {
            $recurring = getRecurringExpenses();
            $newItem = [
                'id' => 'rec_' . time() . '_' . rand(1000, 9999),
                'user_id' => $userId,
                'type' => $_POST['type'] ?? 'expense',
                'amount' => floatval($_POST['amount']),
                'category_id' => $_POST['category_id'] ?? '',
                'description' => htmlspecialchars($_POST['description'] ?? ''),
                'day_of_month' => (int)($_POST['day_of_month'] ?? 1),
                'active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $recurring[] = $newItem;
            saveRecurringExpenses($recurring);
            $response = ['success' => true, 'item' => $newItem];
            
        } elseif ($action === 'update_recurring') {
            $recurring = getRecurringExpenses();
            $recId = $_POST['id'] ?? '';
            foreach ($recurring as &$r) {
                if ($r['id'] == $recId && ($r['user_id'] ?? '') == $userId) {
                    $r['type'] = $_POST['type'] ?? $r['type'];
                    $r['amount'] = floatval($_POST['amount'] ?? $r['amount']);
                    $r['category_id'] = $_POST['category_id'] ?? $r['category_id'];
                    $r['description'] = htmlspecialchars($_POST['description'] ?? $r['description']);
                    $r['day_of_month'] = (int)($_POST['day_of_month'] ?? $r['day_of_month']);
                    $r['active'] = isset($_POST['active']) ? ($_POST['active'] === 'true') : $r['active'];
                    break;
                }
            }
            saveRecurringExpenses($recurring);
            $response = ['success' => true];
            
        } elseif ($action === 'delete_recurring') {
            $recurring = getRecurringExpenses();
            $recId = $_POST['id'] ?? '';
            $recurring = array_values(array_filter($recurring, function($r) use ($userId, $recId) {
                return !($r['id'] == $recId && ($r['user_id'] ?? '') == $userId);
            }));
            saveRecurringExpenses($recurring);
            $response = ['success' => true];
            
        } elseif ($action === 'get_reports') {
            processRecurringExpenses($userId);
            $transactions = getUserTransactions($userId);
            $period = $_POST['period'] ?? 'monthly';
            $filterDate = $_POST['filter_date'] ?? date('Y-m-d');
            
            $filteredTransactions = [];
            $startDate = '';
            $endDate = '';
            
            switch ($period) {
                case 'daily':
                    $startDate = $filterDate;
                    $endDate = $filterDate;
                    break;
                case 'weekly':
                    $weekStart = strtotime('monday this week', strtotime($filterDate));
                    $weekEnd = strtotime('sunday this week', strtotime($filterDate));
                    $startDate = date('Y-m-d', $weekStart);
                    $endDate = date('Y-m-d', $weekEnd);
                    break;
                case 'monthly':
                    $startDate = date('Y-m-01', strtotime($filterDate));
                    $endDate = date('Y-m-t', strtotime($filterDate));
                    break;
                case 'yearly':
                    $startDate = date('Y-01-01', strtotime($filterDate));
                    $endDate = date('Y-12-31', strtotime($filterDate));
                    break;
            }
            
            foreach ($transactions as $t) {
                $tDate = $t['date'] ?? '';
                if ($tDate >= $startDate && $tDate <= $endDate) {
                    $filteredTransactions[] = $t;
                }
            }
            
            $totalIncome = 0;
            $totalExpense = 0;
            $byCategory = [];
            
            foreach ($filteredTransactions as $t) {
                $amount = floatval($t['amount']);
                if ($t['type'] === 'income') {
                    $totalIncome += $amount;
                } else {
                    $totalExpense += $amount;
                }
                
                $catId = $t['category_id'] ?? 'uncategorized';
                if (!isset($byCategory[$catId])) {
                    $byCategory[$catId] = ['income' => 0, 'expense' => 0];
                }
                if ($t['type'] === 'income') {
                    $byCategory[$catId]['income'] += $amount;
                } else {
                    $byCategory[$catId]['expense'] += $amount;
                }
            }
            
            $response = [
                'success' => true,
                'report' => [
                    'period' => $period,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'balance' => $totalIncome - $totalExpense,
                    'by_category' => $byCategory,
                    'transaction_count' => count($filteredTransactions)
                ]
            ];
        }
        
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ==================== بارگذاری داده‌ها ====================
processRecurringExpenses($_SESSION['user_id']);
$userTransactions = getUserTransactions($_SESSION['user_id']);
$categories = getCategories();
$recurringItems = getUserRecurring($_SESSION['user_id']);

$page_title = 'مدیریت مالی';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <style>
        /* ===== استایل‌های اختصاصی بخش مالی ===== */
        .finance-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .finance-header {
            text-align: center;
            padding: 25px;
            margin-bottom: 25px;
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(8px);
        }
        
        .finance-header h2 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        /* کارت‌های خلاصه وضعیت */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 22px;
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px var(--shadow-color);
        }
        
        .summary-card .card-title {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        
        .summary-card .card-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .summary-card.income .card-value {
            color: #10b981;
        }
        
        .summary-card.expense .card-value {
            color: #ef4444;
        }
        
        .summary-card.balance .card-value {
            color: #667eea;
        }
        
        /* تب‌ها */
        .tabs-container {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 6px;
            margin-bottom: 25px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            border: 1px solid var(--border-color);
        }
        
        .tab-btn {
            flex: 1;
            min-width: 120px;
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .tab-btn:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        /* محتوای تب‌ها */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* فرم‌ها */
        .form-section {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }
        
        .form-section h3 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-input);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.35);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-sm {
            padding: 8px 14px;
            font-size: 12px;
        }
        
        /* لیست تراکنش‌ها */
        .transactions-list {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 20px;
            border: 1px solid var(--border-color);
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-item:hover {
            background: var(--task-hover);
            border-radius: 12px;
        }
        
        .transaction-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .transaction-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .transaction-icon.income {
            background: rgba(16,185,129,0.15);
            color: #10b981;
        }
        
        .transaction-icon.expense {
            background: rgba(239,68,68,0.15);
            color: #ef4444;
        }
        
        .transaction-details {
            flex: 1;
        }
        
        .transaction-description {
            font-size: 15px;
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .transaction-meta {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .transaction-amount {
            font-size: 18px;
            font-weight: 700;
        }
        
        .transaction-amount.income {
            color: #10b981;
        }
        
        .transaction-amount.expense {
            color: #ef4444;
        }
        
        .transaction-actions {
            display: flex;
            gap: 8px;
            margin-right: 15px;
        }
        
        /* گزارش‌ها */
        .report-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .report-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .report-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .report-card .label {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        .report-card .value {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        /* مودال */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(4px);
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .modal-header h3 {
            font-size: 20px;
            color: var(--text-primary);
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s;
        }
        
        .modal-close:hover {
            color: #ef4444;
        }
        
        /* لیست دسته‌بندی‌ها */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .category-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        
        .category-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .category-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            background: rgba(102,126,234,0.15);
            color: #667eea;
        }
        
        .category-name {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .category-type {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            margin-right: 8px;
        }
        
        .category-type.expense {
            background: rgba(239,68,68,0.15);
            color: #ef4444;
        }
        
        .category-type.income {
            background: rgba(16,185,129,0.15);
            color: #10b981;
        }
        
        /* ریسپانسیو */
        @media (max-width: 768px) {
            .finance-container {
                padding: 15px;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .tabs-container {
                flex-direction: column;
            }
            
            .tab-btn {
                min-width: 100%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .transaction-item {
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .transaction-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="finance-container">
        <!-- هدر بخش مالی -->
        <div class="finance-header">
            <h2><i class="fas fa-wallet"></i> مدیریت مالی و حسابداری</h2>
            <p style="color: var(--text-muted); margin-top: 8px;">مدیریت هزینه‌ها، درآمدها و گزارش‌گیری مالی</p>
        </div>
        
        <!-- کارت‌های خلاصه وضعیت -->
        <div class="summary-cards">
            <div class="summary-card income">
                <div class="card-title">💰 درآمد کل (ماه جاری)</div>
                <div class="card-value" id="totalIncomeDisplay">0 تومان</div>
            </div>
            <div class="summary-card expense">
                <div class="card-title">💸 هزینه کل (ماه جاری)</div>
                <div class="card-value" id="totalExpenseDisplay">0 تومان</div>
            </div>
            <div class="summary-card balance">
                <div class="card-title">📊 مانده حساب</div>
                <div class="card-value" id="balanceDisplay">0 تومان</div>
            </div>
        </div>
        
        <!-- تب‌ها -->
        <div class="tabs-container">
            <button class="tab-btn active" data-tab="transactions">📝 تراکنش‌ها</button>
            <button class="tab-btn" data-tab="recurring">🔄 هزینه‌های ثابت</button>
            <button class="tab-btn" data-tab="reports">📈 گزارش‌ها</button>
            <button class="tab-btn" data-tab="categories">🏷️ دسته‌بندی‌ها</button>
        </div>
        
        <!-- تب تراکنش‌ها -->
        <div class="tab-content active" id="transactionsTab">
            <div class="form-section">
                <h3><i class="fas fa-plus-circle"></i> ثبت تراکنش جدید</h3>
                <form id="transactionForm" onsubmit="handleTransactionSubmit(event)">
                    <input type="hidden" id="editTransactionId" value="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>نوع تراکنش</label>
                            <select id="txnType" required onchange="updateCategoryOptions()">
                                <option value="expense">هزینه</option>
                                <option value="income">درآمد</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>مبلغ (تومان)</label>
                            <input type="number" id="txnAmount" placeholder="0" required min="0">
                        </div>
                        <div class="form-group">
                            <label>دسته‌بندی</label>
                            <select id="txnCategory" required>
                                <!-- پر می‌شود با JS -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label>تاریخ</label>
                            <input type="text" id="txnDate" placeholder="1403/01/01" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>توضیحات</label>
                        <input type="text" id="txnDescription" placeholder="توضیحات تراکنش...">
                    </div>
                    <button type="submit" class="btn btn-primary" id="saveTransactionBtn">
                        <i class="fas fa-save"></i> ثبت تراکنش
                    </button>
                    <button type="button" class="btn btn-danger" id="cancelEditBtn" style="display: none;" onclick="cancelEditTransaction()">
                        <i class="fas fa-times"></i> انصراف
                    </button>
                </form>
            </div>
            
            <div class="transactions-list">
                <h3 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-list"></i> آخرین تراکنش‌ها
                </h3>
                <div id="transactionsListContainer">
                    <!-- تراکنش‌ها اینجا قرار می‌گیرند -->
                </div>
            </div>
        </div>
        
        <!-- تب هزینه‌های ثابت -->
        <div class="tab-content" id="recurringTab">
            <div class="form-section">
                <h3><i class="fas fa-calendar-repeat"></i> ثبت هزینه/درآمد ثابت ماهانه</h3>
                <form id="recurringForm" onsubmit="handleRecurringSubmit(event)">
                    <input type="hidden" id="editRecurringId" value="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>نوع</label>
                            <select id="recType" required>
                                <option value="expense">هزینه</option>
                                <option value="income">درآمد</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>مبلغ (تومان)</label>
                            <input type="number" id="recAmount" placeholder="0" required min="0">
                        </div>
                        <div class="form-group">
                            <label>دسته‌بندی</label>
                            <select id="recCategory" required>
                                <!-- پر می‌شود با JS -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label>روز ماه</label>
                            <input type="number" id="recDay" placeholder="1-31" required min="1" max="31">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>توضیحات</label>
                        <input type="text" id="recDescription" placeholder="مثلاً: اجاره خانه، حقوق ماهانه..." required>
                    </div>
                    <button type="submit" class="btn btn-primary" id="saveRecurringBtn">
                        <i class="fas fa-save"></i> ثبت هزینه ثابت
                    </button>
                    <button type="button" class="btn btn-danger" id="cancelEditRecurringBtn" style="display: none;" onclick="cancelEditRecurring()">
                        <i class="fas fa-times"></i> انصراف
                    </button>
                </form>
            </div>
            
            <div class="transactions-list">
                <h3 style="color: var(--text-primary); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-sync-alt"></i> هزینه‌های ثابت من
                </h3>
                <div id="recurringListContainer">
                    <!-- آیتم‌های تکراری اینجا قرار می‌گیرند -->
                </div>
            </div>
        </div>
        
        <!-- تب گزارش‌ها -->
        <div class="tab-content" id="reportsTab">
            <div class="form-section">
                <h3><i class="fas fa-chart-pie"></i> گزارش‌گیری مالی</h3>
                <div class="report-filters">
                    <div class="form-group">
                        <label>دوره گزارش</label>
                        <select id="reportPeriod" onchange="loadReport()">
                            <option value="daily">روزانه</option>
                            <option value="weekly">هفتگی</option>
                            <option value="monthly" selected>ماهانه</option>
                            <option value="yearly">سالانه</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>تاریخ مرجع</label>
                        <input type="text" id="reportDate" placeholder="1403/01/01" onchange="loadReport()">
                    </div>
                    <button class="btn btn-primary" onclick="loadReport()" style="align-self: flex-end;">
                        <i class="fas fa-redo"></i> بروزرسانی گزارش
                    </button>
                </div>
                
                <div class="report-cards" id="reportCards">
                    <div class="report-card">
                        <div class="label">کل درآمد</div>
                        <div class="value" id="reportIncome" style="color: #10b981;">0 تومان</div>
                    </div>
                    <div class="report-card">
                        <div class="label">کل هزینه</div>
                        <div class="value" id="reportExpense" style="color: #ef4444;">0 تومان</div>
                    </div>
                    <div class="report-card">
                        <div class="label">مانده</div>
                        <div class="value" id="reportBalance" style="color: #667eea;">0 تومان</div>
                    </div>
                    <div class="report-card">
                        <div class="label">تعداد تراکنش</div>
                        <div class="value" id="reportCount">0</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- تب دسته‌بندی‌ها -->
        <div class="tab-content" id="categoriesTab">
            <div class="form-section">
                <h3><i class="fas fa-tags"></i> مدیریت دسته‌بندی‌ها</h3>
                <form id="categoryForm" onsubmit="handleCategorySubmit(event)">
                    <input type="hidden" id="editCategoryId" value="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>نام دسته‌بندی</label>
                            <input type="text" id="catName" placeholder="مثلاً: خوراک" required>
                        </div>
                        <div class="form-group">
                            <label>نوع</label>
                            <select id="catType" required>
                                <option value="expense">هزینه</option>
                                <option value="income">درآمد</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>آیکون</label>
                            <select id="catIcon">
                                <option value="fa-utensils">🍽️ غذا</option>
                                <option value="fa-home">🏠 مسکن</option>
                                <option value="fa-car">🚗 حمل‌ونقل</option>
                                <option value="fa-heartbeat">❤️ سلامت</option>
                                <option value="fa-book">📚 آموزش</option>
                                <option value="fa-film">🎬 تفریح</option>
                                <option value="fa-shopping-cart">🛒 خرید</option>
                                <option value="fa-money-bill-wave">💰 حقوق</option>
                                <option value="fa-coins">🪙 سایر درآمد</option>
                                <option value="fa-tag">🏷️ سایر</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="saveCategoryBtn">
                        <i class="fas fa-plus"></i> افزودن دسته‌بندی
                    </button>
                    <button type="button" class="btn btn-danger" id="cancelEditCategoryBtn" style="display: none;" onclick="cancelEditCategory()">
                        <i class="fas fa-times"></i> انصراف
                    </button>
                </form>
            </div>
            
            <div class="categories-grid" id="categoriesGrid">
                <!-- دسته‌بندی‌ها اینجا قرار می‌گیرند -->
            </div>
        </div>
    </div>
    
    <!-- مودال ویرایش -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">ویرایش</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody">
                <!-- محتوای مودال -->
            </div>
        </div>
    </div>
    
    <script>
        // ==================== داده‌های اولیه ====================
        const transactions = <?php echo json_encode($userTransactions); ?>;
        const categories = <?php echo json_encode($categories); ?>;
        const recurringItems = <?php echo json_encode($recurringItems); ?>;
        
        // ==================== مدیریت تب‌ها ====================
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(this.dataset.tab + 'Tab').classList.add('active');
            });
        });
        
        // ==================== توابع کمکی ====================
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        function toPersianDate(dateStr) {
            // تبدیل ساده تاریخ میلادی به شمسی (برای نمایش)
            const date = new Date(dateStr);
            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const day = date.getDate();
            return `${year}/${String(month).padStart(2, '0')}/${String(day).padStart(2, '0')}`;
        }
        
        function getCategoryById(id) {
            return categories.find(c => c.id === id) || { name: 'بدون دسته', icon: 'fa-tag' };
        }
        
        function updateCategoryOptions() {
            const type = document.getElementById('txnType').value;
            const recType = document.getElementById('recType').value;
            
            const filteredCats = categories.filter(c => c.type === type);
            const txnSelect = document.getElementById('txnCategory');
            txnSelect.innerHTML = filteredCats.map(c => 
                `<option value="${c.id}">${c.name}</option>`
            ).join('');
            
            const filteredRecCats = categories.filter(c => c.type === recType);
            const recSelect = document.getElementById('recCategory');
            recSelect.innerHTML = filteredRecCats.map(c => 
                `<option value="${c.id}">${c.name}</option>`
            ).join('');
        }
        
        // ==================== بارگذاری داده‌ها ====================
        function loadTransactions() {
            const container = document.getElementById('transactionsListContainer');
            const sortedTxns = [...transactions].sort((a, b) => new Date(b.date) - new Date(a.date));
            
            if (sortedTxns.length === 0) {
                container.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 30px;">هیچ تراکنشی ثبت نشده است.</p>';
                return;
            }
            
            container.innerHTML = sortedTxns.slice(0, 50).map(t => {
                const cat = getCategoryById(t.category_id);
                return `
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-icon ${t.type}">
                                <i class="fas ${cat.icon}"></i>
                            </div>
                            <div class="transaction-details">
                                <div class="transaction-description">${t.description || 'بدون توضیح'}</div>
                                <div class="transaction-meta">${toPersianDate(t.date)} • ${cat.name}</div>
                            </div>
                        </div>
                        <div class="transaction-amount ${t.type}">
                            ${t.type === 'income' ? '+' : '-'}${formatNumber(t.amount)} تومان
                        </div>
                        <div class="transaction-actions">
                            <button class="btn btn-sm btn-primary" onclick="editTransaction('${t.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteTransaction('${t.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function loadRecurringItems() {
            const container = document.getElementById('recurringListContainer');
            
            if (recurringItems.length === 0) {
                container.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 30px;">هیچ هزینه ثابتی ثبت نشده است.</p>';
                return;
            }
            
            container.innerHTML = recurringItems.map(r => {
                const cat = getCategoryById(r.category_id);
                return `
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-icon ${r.type}">
                                <i class="fas ${cat.icon}"></i>
                            </div>
                            <div class="transaction-details">
                                <div class="transaction-description">${r.description}</div>
                                <div class="transaction-meta">روز ${r.day_of_month} هر ماه • ${cat.name}</div>
                            </div>
                        </div>
                        <div class="transaction-amount ${r.type}">
                            ${r.type === 'income' ? '+' : '-'}${formatNumber(r.amount)} تومان
                        </div>
                        <div class="transaction-actions">
                            <button class="btn btn-sm btn-primary" onclick="editRecurring('${r.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteRecurring('${r.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function loadCategories() {
            const grid = document.getElementById('categoriesGrid');
            
            grid.innerHTML = categories.map(c => `
                <div class="category-item">
                    <div class="category-info">
                        <div class="category-icon">
                            <i class="fas ${c.icon}"></i>
                        </div>
                        <div>
                            <div class="category-name">${c.name}</div>
                            <span class="category-type ${c.type}">${c.type === 'expense' ? 'هزینه' : 'درآمد'}</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn btn-sm btn-primary" onclick="editCategory('${c.id}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteCategory('${c.id}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }
        
        function loadSummary() {
            const currentMonth = new Date().toISOString().slice(0, 7);
            let totalIncome = 0;
            let totalExpense = 0;
            
            transactions.forEach(t => {
                if (t.date.startsWith(currentMonth)) {
                    if (t.type === 'income') {
                        totalIncome += parseFloat(t.amount);
                    } else {
                        totalExpense += parseFloat(t.amount);
                    }
                }
            });
            
            document.getElementById('totalIncomeDisplay').textContent = formatNumber(totalIncome) + ' تومان';
            document.getElementById('totalExpenseDisplay').textContent = formatNumber(totalExpense) + ' تومان';
            document.getElementById('balanceDisplay').textContent = formatNumber(totalIncome - totalExpense) + ' تومان';
        }
        
        // ==================== مدیریت تراکنش‌ها ====================
        function handleTransactionSubmit(e) {
            e.preventDefault();
            
            const editId = document.getElementById('editTransactionId').value;
            const formData = new FormData();
            formData.append('action', editId ? 'update_transaction' : 'add_transaction');
            if (editId) formData.append('id', editId);
            formData.append('type', document.getElementById('txnType').value);
            formData.append('amount', document.getElementById('txnAmount').value);
            formData.append('category_id', document.getElementById('txnCategory').value);
            formData.append('description', document.getElementById('txnDescription').value);
            formData.append('date', document.getElementById('txnDate').value.replace(/\//g, '-'));
            
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        alert(editId ? 'تراکنش ویرایش شد' : 'تراکنش ثبت شد');
                        location.reload();
                    } else {
                        alert('خطا: ' + (result.error || 'عملیات ناموفق بود'));
                    }
                })
                .catch(err => alert('خطا در ارتباط با سرور'));
        }
        
        function editTransaction(id) {
            const txn = transactions.find(t => t.id === id);
            if (!txn) return;
            
            document.getElementById('editTransactionId').value = txn.id;
            document.getElementById('txnType').value = txn.type;
            document.getElementById('txnAmount').value = txn.amount;
            document.getElementById('txnCategory').value = txn.category_id;
            document.getElementById('txnDescription').value = txn.description || '';
            document.getElementById('txnDate').value = toPersianDate(txn.date);
            document.getElementById('saveTransactionBtn').innerHTML = '<i class="fas fa-edit"></i> ویرایش تراکنش';
            document.getElementById('cancelEditBtn').style.display = 'inline-flex';
            
            updateCategoryOptions();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function cancelEditTransaction() {
            document.getElementById('editTransactionId').value = '';
            document.getElementById('transactionForm').reset();
            document.getElementById('saveTransactionBtn').innerHTML = '<i class="fas fa-save"></i> ثبت تراکنش';
            document.getElementById('cancelEditBtn').style.display = 'none';
            updateCategoryOptions();
        }
        
        function deleteTransaction(id) {
            if (!confirm('آیا از حذف این تراکنش مطمئن هستید؟')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_transaction');
            formData.append('id', id);
            
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        alert('تراکنش حذف شد');
                        location.reload();
                    }
                })
                .catch(err => alert('خطا در ارتباط با سرور'));
        }
        
        // ==================== مدیریت هزینه‌های تکراری ====================
        function handleRecurringSubmit(e) {
            e.preventDefault();
            
            const editId = document.getElementById('editRecurringId').value;
            const formData = new FormData();
            formData.append('action', editId ? 'update_recurring' : 'add_recurring');
            if (editId) formData.append('id', editId);
            formData.append('type', document.getElementById('recType').value);
            formData.append('amount', document.getElementById('recAmount').value);
            formData.append('category_id', document.getElementById('recCategory').value);
            formData.append('description', document.getElementById('recDescription').value);
            formData.append('day_of_month', document.getElementById('recDay').value);
            formData.append('active', 'true');
            
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        alert(editId ? 'هزینه ثابت ویرایش شد' : 'هزینه ثابت ثبت شد');
                        location.reload();
                    } else {
                        alert('خطا: ' + (result.error || 'عملیات ناموفق بود'));
                    }
                })
                .catch(err => alert('خطا در ارتباط با سرور'));
        }
        
        function editRecurring(id) {
            const item = recurringItems.find(r => r.id === id);
            if (!item) return;
            
            document.getElementById('editRecurringId').value = item.id;
            document.getElementById('recType').value = item.type;
            document.getElementById('recAmount').value = item.amount;
            document.getElementById('recCategory').value = item.category_id;
            document.getElementById('recDescription').value = item.description;
            document.getElementById('recDay').value = item.day_of_month;
            document.getElementById('saveRecurringBtn').innerHTML = '<i class="fas fa-edit"></i> ویرایش';
            document.getElementById('cancelEditRecurringBtn').style.display = 'inline-flex';
            
            updateCategoryOptions();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function cancelEditRecurring() {
            document.getElementById('editRecurringId').value = '';
            document.getElementById('recurringForm').reset();
            document.getElementById('saveRecurringBtn').innerHTML = '<i class="fas fa-save"></i> ثبت هزینه ثابت';
            document.getElementById('cancelEditRecurringBtn').style.display = 'none';
            updateCategoryOptions();
        }
        
        function deleteRecurring(id) {
            if (!confirm('آیا از حذف این هزینه ثابت مطمئن هستید؟')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_recurring');
            formData.append('id', id);
            
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        alert('هزینه ثابت حذف شد');
                        location.reload();
                    }
                })
                .catch(err => alert('خطا در ارتباط با سرور'));
        }
        
        // ==================== مدیریت دسته‌بندی‌ها ====================
        function handleCategorySubmit(e) {
            e.preventDefault();
            
            const editId = document.getElementById('editCategoryId').value;
            const formData = new FormData();
            formData.append('action', editId ? 'update_category' : 'add_category');
            if (editId) formData.append('id', editId);
            formData.append('name', document.getElementById('catName').value);
            formData.append('type', document.getElementById('catType').value);
            formData.append('icon', document.getElementById('catIcon').value);
            
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        alert(editId ? 'دسته‌بندی ویرایش شد' : 'دسته‌بندی افزوده شد');
                        location.reload();
                    } else {
                        alert('خطا: ' + (result.error || 'عملیات ناموفق بود'));
                    }
                })
                .catch(err => alert('خطا در ارتباط با سرور'));
        }
        
        function editCategory(id) {
            const cat = categories.find(c => c.id === id);
            if (!cat) return;
            
            document.getElementById('editCategoryId').value = cat.id;
            document.getElementById('catName').value = cat.name;
            document.getElementById('catType').value = cat.type;
            document.getElementById('catIcon').value = cat.icon;
            document.getElementById('saveCategoryBtn').innerHTML = '<i class="fas fa-edit"></i> ویرایش دسته‌بندی';
            document.getElementById('cancelEditCategoryBtn').style.display = 'inline-flex';
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function cancelEditCategory() {
            document.getElementById('editCategoryId').value = '';
            document.getElementById('categoryForm').reset();
            document.getElementById('saveCategoryBtn').innerHTML = '<i class="fas fa-plus"></i> افزودن دسته‌بندی';
            document.getElementById('cancelEditCategoryBtn').style.display = 'none';
        }
        
        function deleteCategory(id) {
            if (!confirm('آیا از حذف این دسته‌بندی مطمئن هستید؟')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_category');
            formData.append('id', id);
            
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        alert('دسته‌بندی حذف شد');
                        location.reload();
                    }
                })
                .catch(err => alert('خطا در ارتباط با سرور'));
        }
        
        // ==================== گزارش‌ها ====================
        function loadReport() {
            const period = document.getElementById('reportPeriod').value;
            const filterDate = document.getElementById('reportDate').value.replace(/\//g, '-') || new Date().toISOString().split('T')[0];
            
            const formData = new FormData();
            formData.append('action', 'get_reports');
            formData.append('period', period);
            formData.append('filter_date', filterDate);
            
            fetch('index.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        const report = result.report;
                        document.getElementById('reportIncome').textContent = formatNumber(report.total_income) + ' تومان';
                        document.getElementById('reportExpense').textContent = formatNumber(report.total_expense) + ' تومان';
                        document.getElementById('reportBalance').textContent = formatNumber(report.balance) + ' تومان';
                        document.getElementById('reportCount').textContent = report.transaction_count;
                    }
                })
                .catch(err => console.error('Error loading report:', err));
        }
        
        // ==================== راه‌اندازی اولیه ====================
        document.addEventListener('DOMContentLoaded', function() {
            updateCategoryOptions();
            loadTransactions();
            loadRecurringItems();
            loadCategories();
            loadSummary();
            loadReport();
            
            // تنظیم تاریخ امروز به شمسی
            const today = new Date();
            const todayStr = `${today.getFullYear()}/${String(today.getMonth() + 1).padStart(2, '0')}/${String(today.getDate()).padStart(2, '0')}`;
            document.getElementById('txnDate').value = todayStr;
            document.getElementById('reportDate').value = todayStr;
        });
    </script>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
