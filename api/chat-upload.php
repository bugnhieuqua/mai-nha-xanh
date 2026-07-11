<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

// Kiểm tra đăng nhập
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để tải lên tệp tin.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy tệp tải lên.']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi tải tệp lên: ' . $file['error']]);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/chat/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$originalName = basename($file['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$isImage = in_array(strtolower($extension), $allowedImageExts);

$newFilename = uniqid('chat_', true) . '.' . $extension;
$destPath = $uploadDir . $newFilename;

if ($isImage && function_exists('compressAndUploadImage')) {
    // Sử dụng hàm nén ảnh của hệ thống nếu là ảnh
    try {
        $uploadedName = compressAndUploadImage($file['tmp_name'], $uploadDir, 'chat', uniqid(), 1200, 1200, 85);
        if ($uploadedName) {
            echo json_encode([
                'success' => true,
                'url' => 'uploads/chat/' . $uploadedName,
                'type' => 'image',
                'name' => $originalName
            ]);
            exit;
        }
    } catch (Exception $e) {
        // Fallback to normal upload if helper fails
    }
}

if (move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode([
        'success' => true,
        'url' => 'uploads/chat/' . $newFilename,
        'type' => $isImage ? 'image' : 'file',
        'name' => $originalName
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu tệp tin.']);
}
?>
