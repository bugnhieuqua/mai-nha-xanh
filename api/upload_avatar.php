<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập.']);
    exit;
}

// Bảo mật CSRF
validateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['avatar'])) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMessage = 'Lỗi tải lên: ' . $file['error'];
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errMessage = 'Dung lượng ảnh quá lớn và đã bị Server (Hosting) tự động từ chối. Vui lòng chọn ảnh nhỏ hơn!';
    }
    echo json_encode(['success' => false, 'message' => $errMessage]);
    exit;
}

if ($file['size'] > 3 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Dung lượng ảnh đại diện không được vượt quá 3MB.']);
    exit;
}


// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận ảnh định dạng JPG, PNG, WebP.']);
    exit;
}

// Tạo thư mục nếu chưa có
$uploadDir = '../uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Tạo tên file độc nhất để tránh cache
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$ext) $ext = 'jpg';
$filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
$targetPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu file trên máy chủ.']);
    exit;
}

// Cập nhật CSDL
try {
    $db = (new Database())->getConnection();
    
    // Xoá ảnh cũ nếu có trong session
    if (!empty($_SESSION['avatar'])) {
        $oldPath = '../uploads/avatars/' . basename($_SESSION['avatar']);
        if (file_exists($oldPath) && is_file($oldPath)) {
            unlink($oldPath);
        }
    }
    
    $avatarPath = 'uploads/avatars/' . $filename;
    
    $stmt = $db->prepare("UPDATE users SET avatar = :avatar WHERE id = :id");
    $stmt->execute([
        ':avatar' => $avatarPath,
        ':id' => (int)$_SESSION['user_id']
    ]);
    
    $_SESSION['avatar'] = $avatarPath;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Cập nhật ảnh đại diện thành công!',
        'avatar_url' => $avatarPath
    ]);
    
} catch (Exception $e) {
    error_log("Upload Avatar Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật dữ liệu.']);
}
?>
