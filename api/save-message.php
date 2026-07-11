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

if (!isset($data['conversation_id'], $data['sender_id'], $data['content'])) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu đầu vào không hợp lệ.']);
    exit;
}

$conversationId = intval($data['conversation_id']);
$senderId       = intval($data['sender_id']);
$content        = trim($data['content']);
$type           = isset($data['type']) ? trim($data['type']) : 'text';

if (empty($content)) {
    echo json_encode(['status' => 'error', 'message' => 'Nội dung tin nhắn trống.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    $sql = "INSERT INTO messages (conversation_id, sender_id, content, type) VALUES (:conv_id, :sender_id, :content, :type)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':conv_id'   => $conversationId,
        ':sender_id' => $senderId,
        ':content'   => $content,
        ':type'      => $type
    ]);
    
    // Khi nhắn tin mới, hiển thị lại cuộc trò chuyện cho tất cả các thành viên (reset deleted_at)
    $updateStmt = $pdo->prepare("UPDATE conversation_members SET deleted_at = NULL WHERE conversation_id = :conv_id");
    $updateStmt->execute([':conv_id' => $conversationId]);

    echo json_encode(['status' => 'success', 'message' => 'Lưu tin nhắn vào cơ sở dữ liệu thành công!', 'message_id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu tin nhắn: ' . $e->getMessage()]);
}
?>
