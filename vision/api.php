<?php
/**
 * Vision Board API Handler
 * Handles CRUD operations for vision board items
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataDir = __DIR__ . '/data';
$uploadDir = __DIR__ . '/uploads';
$dataFile = $dataDir . '/vision_items.json';

// Ensure directories exist and are writable
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}

// Check if directories are writable
if (!is_writable($dataDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Data directory is not writable']);
    exit();
}
if (!is_writable($uploadDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Upload directory is not writable']);
    exit();
}

// Get current user ID (in real app, this would come from session/auth)
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : 'default_user';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get':
        getItems($dataFile, $userId);
        break;
    case 'add':
        addItem($dataFile, $uploadDir, $userId);
        break;
    case 'update':
        updateItem($dataFile, $uploadDir, $userId);
        break;
    case 'delete':
        deleteItem($dataFile, $userId);
        break;
    case 'reorder':
        reorderItems($dataFile, $userId);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getItems($dataFile, $userId) {
    $data = json_decode(file_get_contents($dataFile), true) ?: [];
    $userItems = array_filter($data, fn($item) => $item['user_id'] === $userId);
    echo json_encode(['success' => true, 'items' => array_values($userItems)]);
}

function addItem($dataFile, $uploadDir, $userId) {
    $data = json_decode(file_get_contents($dataFile), true) ?: [];
    
    $type = $_POST['type'] ?? 'text';
    $content = $_POST['content'] ?? '';
    $imagePath = '';
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['image']['type'], $allowedTypes)) {
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . '/' . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $imagePath = 'vision/uploads/' . $filename;
            }
        }
    }
    
    $newItem = [
        'id' => uniqid(),
        'user_id' => $userId,
        'type' => $type,
        'content' => htmlspecialchars($content),
        'image' => $imagePath,
        'position' => ['x' => rand(50, 300), 'y' => rand(50, 400)],
        'size' => ['width' => 200, 'height' => $imagePath ? 250 : 100],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $data[] = $newItem;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'item' => $newItem]);
}

function updateItem($dataFile, $uploadDir, $userId) {
    $data = json_decode(file_get_contents($dataFile), true) ?: [];
    $itemId = $_POST['id'] ?? '';
    
    $itemIndex = -1;
    foreach ($data as $index => $item) {
        if ($item['id'] === $itemId && $item['user_id'] === $userId) {
            $itemIndex = $index;
            break;
        }
    }
    
    if ($itemIndex === -1) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        return;
    }
    
    $item = &$data[$itemIndex];
    
    if (isset($_POST['content'])) {
        $item['content'] = htmlspecialchars($_POST['content']);
    }
    
    if (isset($_POST['position'])) {
        $item['position'] = json_decode($_POST['position'], true);
    }
    
    if (isset($_POST['size'])) {
        $item['size'] = json_decode($_POST['size'], true);
    }
    
    // Handle new image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['image']['type'], $allowedTypes)) {
            // Delete old image if exists
            if (!empty($item['image'])) {
                $oldPath = __DIR__ . '/../' . $item['image'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . '/' . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $item['image'] = 'vision/uploads/' . $filename;
            }
        }
    }
    
    $item['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'item' => $item]);
}

function deleteItem($dataFile, $userId) {
    $data = json_decode(file_get_contents($dataFile), true) ?: [];
    $itemId = $_GET['id'] ?? '';
    
    $itemIndex = -1;
    foreach ($data as $index => $item) {
        if ($item['id'] === $itemId && $item['user_id'] === $userId) {
            $itemIndex = $index;
            break;
        }
    }
    
    if ($itemIndex === -1) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        return;
    }
    
    // Delete associated image
    if (!empty($data[$itemIndex]['image'])) {
        $imagePath = __DIR__ . '/../' . $data[$itemIndex]['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    array_splice($data, $itemIndex, 1);
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true]);
}

function reorderItems($dataFile, $userId) {
    $data = json_decode(file_get_contents($dataFile), true) ?: [];
    $positions = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($positions)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid positions data']);
        return;
    }
    
    foreach ($positions as $posData) {
        foreach ($data as &$item) {
            if ($item['id'] === $posData['id'] && $item['user_id'] === $userId) {
                $item['position'] = $posData['position'];
                $item['updated_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
    }
    
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
}
?>
