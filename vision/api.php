<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/data/vision_items.json';
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'load':
        loadData();
        break;
    case 'save':
        saveData();
        break;
    case 'upload':
        uploadImage();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر']);
}

function loadData() {
    global $dataFile;
    try {
        $data = file_get_contents($dataFile);
        $jsonData = json_decode($data, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode([
                'success' => true,
                'items' => $jsonData['items'] ?? []
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'خطا در خواندن داده‌ها',
                'items' => []
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'items' => []
        ]);
    }
}

function saveData() {
    global $dataFile;
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['items'])) {
            $saveData = ['items' => $data['items']];
            if (file_put_contents($dataFile, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                echo json_encode(['success' => true, 'message' => 'ذخیره شد']);
            } else {
                echo json_encode(['success' => false, 'message' => 'خطا در نوشتن فایل']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'داده‌های نامعتبر']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function uploadImage() {
    global $uploadDir;
    
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'فایلی دریافت نشد']);
        return;
    }
    
    $file = $_FILES['image'];
    
    // بررسی خطا
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'خطا در آپلود: ' . $file['error']]);
        return;
    }
    
    // بررسی نوع فایل
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'نوع فایل مجاز نیست']);
        return;
    }
    
    // ایجاد نام منحصر به فرد
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('vision_') . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // انتقال فایل
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // مسیر نسبی برای نمایش در مرورگر
        $relativePath = 'uploads/' . $filename;
        echo json_encode([
            'success' => true,
            'imagePath' => $relativePath,
            'message' => 'آپلود موفق'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در ذخیره فایل']);
    }
}
?>
