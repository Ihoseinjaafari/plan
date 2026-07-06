<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// احراز هویت
if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید']);
    exit;
}

$current_user = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'user';
$data_file = __DIR__ . '/data/vision_' . md5($current_user) . '.json';
$upload_dir = __DIR__ . '/uploads/';

// اطمینان از وجود پوشه‌ها
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0777, true);
}
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($method === 'GET' && $action === 'load') {
    // بارگذاری داده‌ها
    if (file_exists($data_file)) {
        $data = file_get_contents($data_file);
        $json = json_decode($data, true);
        if ($json) {
            echo json_encode(['success' => true, 'items' => $json]);
        } else {
            echo json_encode(['success' => true, 'items' => []]);
        }
    } else {
        echo json_encode(['success' => true, 'items' => []]);
    }
} elseif ($method === 'POST' && $action === 'save') {
    // ذخیره داده‌ها
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (isset($data['items'])) {
        $items = $data['items'];
        if (file_put_contents($data_file, json_encode($items, JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => 'ذخیره شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در نوشتن فایل']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'داده‌ای برای ذخیره وجود ندارد']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
}
