<?php
header('Content-Type: application/json');

$dataFile = __DIR__ . '/data/vision_board.json';
$uploadDir = __DIR__ . '/uploads/';

// ایجاد پوشه‌ها در صورت عدم وجود
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir(dirname($dataFile))) mkdir(dirname($dataFile), 0777, true);
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode(['items' => []]));

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'load') {
    $data = json_decode(file_get_contents($dataFile), true);
    echo json_encode(['success' => true, 'items' => $data['items'] ?? []]);
}
elseif ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    $items = $input['items'] ?? [];
    if (file_put_contents($dataFile, json_encode(['items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در ذخیره']);
    }
}
elseif ($action === 'upload') {
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'error' => 'فایلی ارسال نشده']);
        exit;
    }
    
    $file = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'error' => 'فرمت مجاز نیست']);
        exit;
    }
    
    $filename = uniqid() . '_' . basename($file['name']);
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => true, 'path' => 'uploads/' . $filename]);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در آپلود']);
    }
}
else {
    echo json_encode(['success' => false, 'error' => 'عملیات نامعتبر']);
}
