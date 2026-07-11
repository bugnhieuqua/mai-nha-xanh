<?php
/**
 * API thu hồi tin nhắn (recall)
 * Người dùng có thể thu hồi bất kỳ tin nhắn nào đã gửi, không giới hạn thời gian.
 * Khi thu hồi, nội dung tin nhắn sẽ được thay thế bằng một thông báo hệ thống.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn phải đăng nhập để thu hồi tin nhắn.']);
    exit;
}

$action = $_POST['action'] ?? '';
$messageId = intval($_POST['message_id'] ?? 0);

if ($action !== 'recall' || $messageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$pdo = (new Database())->getConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL']);
    exit;
}

// Auto-migration: đảm bảo column edited_at tồn tại
try {
    $pdo->exec("ALTER TABLE messages ADD COLUMN edited_at DATETIME DEFAULT NULL");
} catch (PDOException $e) {
    // Column đã tồn tại — bỏ qua
}

// Kiểm tra quyền sở hữu tin nhắn hoặc admin
$stmt = $pdo->prepare('SELECT sender_id FROM messages WHERE id = :id');
$stmt->execute([':id' => $messageId]);
$senderId = intval($stmt->fetchColumn());
if (!$senderId) {
    echo json_encode(['success' => false, 'message' => 'Tin nhắn không tồn tại.']);
    exit;
}
$currentUser = intval($_SESSION['user_id']);
$role = $_SESSION['role'] ?? '';
if ($senderId !== $currentUser && $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thu hồi tin nhắn này.']);
    exit;
}

// Thực hiện thu hồi
try {
    $newContent = '[Tin nhắn đã được thu hồi]';
    $recallStmt = $pdo->prepare('UPDATE messages SET content = :newContent, edited_at = NOW() WHERE id = :id');
    $recallStmt->execute([':newContent' => $newContent, ':id' => $messageId]);
    echo json_encode(['success' => true, 'message' => 'Thu hồi tin nhắn thành công.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
?>
