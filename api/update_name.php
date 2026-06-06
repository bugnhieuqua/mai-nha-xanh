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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$hoten = trim($_POST['hoten'] ?? '');

if (empty($hoten)) {
    echo json_encode(['success' => false, 'message' => 'Họ và tên không được để trống.']);
    exit;
}

if (mb_strlen($hoten) > 100) {
    echo json_encode(['success' => false, 'message' => 'Họ và tên không được quá 100 ký tự.']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    $stmt = $db->prepare("UPDATE users SET hoten = :hoten WHERE id = :id");
    $stmt->execute([
        ':hoten' => $hoten,
        ':id' => (int)$_SESSION['user_id']
    ]);
    
    $_SESSION['hoten'] = $hoten;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Cập nhật họ tên thành công!',
        'hoten' => $hoten
    ]);
    
} catch (Exception $e) {
    error_log("Update Name Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật dữ liệu.']);
}
?>
