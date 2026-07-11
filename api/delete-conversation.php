<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$conversationId = isset($input['conversation_id']) ? intval($input['conversation_id']) : 0;
$currentUserId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

if ($conversationId === 0 || $currentUserId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    // Migration
    try {
        $pdo->exec("ALTER TABLE conversation_members ADD COLUMN deleted_at DATETIME DEFAULT NULL");
    } catch (Exception $e) {}

    // Soft delete
    $stmt = $pdo->prepare("
        UPDATE conversation_members 
        SET deleted_at = NOW() 
        WHERE conversation_id = :conv_id AND user_id = :user_id
    ");
    $stmt->execute([
        ':conv_id' => $conversationId,
        ':user_id' => $currentUserId
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Đã xóa cuộc trò chuyện thành công.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $e->getMessage()]);
}
?>
