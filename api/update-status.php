<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Chỉ chấp nhận POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id']) || !isset($data['status'])) {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu tham số đầu vào.']);
    exit;
}

$userId = intval($data['user_id']);
$status = intval($data['status']); // 1: Online, 0: Offline

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    // Cập nhật trạng thái và mốc thời gian hoạt động cuối cùng
    $sql = "UPDATE users SET is_online = :status, last_active = NOW() WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $status,
        ':user_id' => $userId
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Cập nhật trạng thái hoạt động thành công']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL: ' . $e->getMessage()]);
}
?>
