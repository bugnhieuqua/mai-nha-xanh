<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['conversation_id'], $data['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$conversationId = intval($data['conversation_id']);
$currentUserId  = intval($data['user_id']);

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    // Đánh dấu tất cả tin nhắn trong cuộc hội thoại này do NGƯỜI KHÁC gửi là đã đọc
    $sql = "UPDATE messages 
            SET is_read = 1 
            WHERE conversation_id = :conv_id 
              AND sender_id != :current_id 
              AND is_read = 0";
              
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':conv_id'    => $conversationId,
        ':current_id' => $currentUserId
    ]);

    echo json_encode(['status' => 'success', 'updated_rows' => $stmt->rowCount()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi DB: ' . $e->getMessage()]);
}
?>
