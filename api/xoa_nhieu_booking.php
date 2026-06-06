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
$ids  = $data['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Danh sách ID không hợp lệ']); exit;
}

// Chuyển thành list số nguyên để bảo mật
$ids = array_map('intval', $ids);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Kiểm tra sự tồn tại và quyền sở hữu
    $stmt = $db->prepare("SELECT id FROM dat_phong WHERE id IN ($placeholders) AND nguoidang = ?");
    $params = array_merge($ids, [$_SESSION['username']]);
    $stmt->execute($params);
    $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($foundIds)) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy yêu cầu nào để xoá hoặc bạn không có quyền']); exit;
    }

    // 2. Xoá
    $placeholdersFound = implode(',', array_fill(0, count($foundIds), '?'));
    $del = $db->prepare("DELETE FROM dat_phong WHERE id IN ($placeholdersFound)");
    $del->execute($foundIds);

    $count = count($foundIds);
    echo json_encode(['success' => true, 'message' => "Đã xoá thành công $count yêu cầu"]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
