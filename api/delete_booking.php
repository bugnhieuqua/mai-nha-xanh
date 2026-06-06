<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']); exit;
}
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']); exit;
}

// Bảo mật CSRF
validateCsrfToken();

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id   = intval($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Kiểm tra quyền sở hữu
    $s = $db->prepare("SELECT id FROM dat_phong WHERE id = :id AND nguoidang = :u LIMIT 1");
    $s->execute([':id' => $id, ':u' => $_SESSION['username']]);
    if (!$s->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xoá yêu cầu này']); exit;
    }

    $del = $db->prepare("DELETE FROM dat_phong WHERE id = :id");
    $del->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Đã xoá yêu cầu thành công']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
