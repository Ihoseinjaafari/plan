<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$dataFile = __DIR__ . '/data/vision_board.json';
$uploadDir = __DIR__ . '/uploads/';

// اطمینان از وجود پوشه‌ها
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!is_dir(dirname($dataFile))) {
    mkdir(dirname($dataFile), 0777, true);
}
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode(['items' => []]));
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'load_board':
        $data = json_decode(file_get_contents($dataFile), true);
        echo json_encode([
            'success' => true,
            'items' => $data['items'] ?? []
        ]);
        break;

    case 'save_board':
        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];
        
        $data = ['items' => $items];
        if (file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'خطا در ذخیره‌سازی']);
        }
        break;

    case 'upload_image':
        if (!isset($_FILES['image'])) {
            echo json_encode(['success' => false, 'error' => 'فایلی ارسال نشده']);
            break;
        }

        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'فرمت فایل مجاز نیست']);
            break;
        }

        $filename = uniqid() . '_' . basename($file['name']);
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            echo json_encode([
                'success' => true,
                'path' => 'uploads/' . $filename
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'خطا در آپلود فایل']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'عملیات نامعتبر']);
        break;
}
