<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

// Kiểm tra đăng nhập
if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn cần đăng nhập để truy cập cuộc hội thoại.']);
    exit;
}

$currentUserId = intval($_SESSION['user_id']);
$partnerId     = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;

if ($partnerId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Đối tác không hợp lệ.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    // 1. Tìm cuộc hội thoại 1-1 đã tồn tại giữa 2 user
    $findSql = "
        SELECT cm1.conversation_id 
        FROM conversation_members cm1
        JOIN conversation_members cm2 ON cm1.conversation_id = cm2.conversation_id
        JOIN conversations c ON c.id = cm1.conversation_id
        WHERE cm1.user_id = :user_a AND cm2.user_id = :user_b AND c.is_group = 0
        LIMIT 1
    ";
    
    $findStmt = $pdo->prepare($findSql);
    $findStmt->execute([
        ':user_a' => $currentUserId,
        ':user_b' => $partnerId
    ]);
    
    $conv = $findStmt->fetch(PDO::FETCH_ASSOC);
    $conversationId = 0;

    if ($conv) {
        $conversationId = intval($conv['conversation_id']);
    } else {
        // 2. Nếu chưa tồn tại, tạo mới cuộc hội thoại
        $pdo->beginTransaction();
        
        $insertConv = "INSERT INTO conversations (is_group) VALUES (0)";
        $pdo->exec($insertConv);
        $conversationId = intval($pdo->lastInsertId());

        $insertMember = "INSERT INTO conversation_members (conversation_id, user_id) VALUES (:conv_id, :user_id)";
        $memberStmt = $pdo->prepare($insertMember);
        
        // Thêm bản thân
        $memberStmt->execute([
            ':conv_id' => $conversationId,
            ':user_id' => $currentUserId
        ]);
        
        // Thêm đối tác
        $memberStmt->execute([
            ':conv_id' => $conversationId,
            ':user_id' => $partnerId
        ]);
        
        $pdo->commit();
    }

    // 3. Lấy lịch sử tin nhắn của cuộc hội thoại này
    $msgSql = "
        SELECT id, sender_id, content, type, created_at 
        FROM messages 
        WHERE conversation_id = :conv_id 
        ORDER BY created_at ASC
    ";
    $msgStmt = $pdo->prepare($msgSql);
    $msgStmt->execute([':conv_id' => $conversationId]);
    $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'conversation_id' => $conversationId,
        'messages' => $messages
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối hoặc xử lý dữ liệu: ' . $e->getMessage()]);
}
?>
