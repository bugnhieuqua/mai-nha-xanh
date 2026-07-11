<?php
/**
 * API Chỉnh Sửa Tin Nhắn
 * Hỗ trợ chỉnh sửa:
 *  - Tin nhắn 1-1 / Nhóm (bảng messages)
 *  - Tin nhắn hỗ trợ kỹ thuật Admin (bảng chatbot_history với chat_type='support')
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');

require_once __DIR__ . '/../config/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để chỉnh sửa tin nhắn.']);
    exit;
}

$currentUserId = intval($_SESSION['user_id']);
$role = $_SESSION['role'] ?? '';

// Validate CSRF
validateCsrfToken();

$database = new Database();
$pdo = $database->getConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL']);
    exit;
}

// Auto-migration for edited_at column
try {
    $pdo->exec("ALTER TABLE messages ADD COLUMN edited_at DATETIME DEFAULT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE chatbot_history ADD COLUMN edited_at DATETIME DEFAULT NULL");
} catch (Exception $e) {}

$action = $_POST['action'] ?? '';
$messageId = intval($_POST['message_id'] ?? 0);
$newContent = trim($_POST['content'] ?? '');

if ($messageId <= 0 || empty($newContent)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

if ($action === 'edit_chat') {
    // Chỉnh sửa tin nhắn 1-1 hoặc Nhóm (bảng messages)
    try {
        // Kiểm tra xem user có phải người gửi tin nhắn này không (hoặc là admin)
        $chk = $pdo->prepare("SELECT sender_id FROM messages WHERE id = :id");
        $chk->execute([':id' => $messageId]);
        $senderId = intval($chk->fetchColumn());

        if (!$senderId) {
            echo json_encode(['success' => false, 'message' => 'Tin nhắn không tồn tại.']);
            exit;
        }

        if ($senderId !== $currentUserId && $role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa tin nhắn này.']);
            exit;
        }

        // Cập nhật
        $stmt = $pdo->prepare("UPDATE messages SET content = :content, edited_at = NOW() WHERE id = :id");
        $stmt->execute([':content' => $newContent, ':id' => $messageId]);

        echo json_encode(['success' => true, 'message' => 'Đã chỉnh sửa tin nhắn thành công.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
} elseif ($action === 'edit_support') {
    // Chỉnh sửa tin nhắn hỗ trợ kỹ thuật (bảng chatbot_history)
    try {
        // Kiểm tra xem tin nhắn này có phải là support không, và người gửi có khớp không
        $chk = $pdo->prepare("SELECT user_id, chat_type, sender FROM chatbot_history WHERE id = :id");
        $chk->execute([':id' => $messageId]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Tin nhắn không tồn tại.']);
            exit;
        }

        if ($row['chat_type'] !== 'support') {
            echo json_encode(['success' => false, 'message' => 'Chỉ có thể chỉnh sửa tin nhắn hỗ trợ (không thể sửa tin nhắn chatbot).']);
            exit;
        }

        // Quyền chỉnh sửa
        $isAuthor = false;
        if ($role === 'admin' && ($row['sender'] === 'ai' || $row['sender'] === 'support')) {
            $isAuthor = true;
        } elseif (intval($row['user_id']) === $currentUserId && $row['sender'] === 'user') {
            $isAuthor = true;
        }

        if (!$isAuthor && $role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa tin nhắn này.']);
            exit;
        }

        // Cập nhật tin nhắn trong chatbot_history
        if ($row['sender'] === 'user') {
            $stmt = $pdo->prepare("UPDATE chatbot_history SET user_message = :content, edited_at = NOW() WHERE id = :id");
        } else {
            $stmt = $pdo->prepare("UPDATE chatbot_history SET bot_response = :content, edited_at = NOW() WHERE id = :id");
        }
        $stmt->execute([':content' => $newContent, ':id' => $messageId]);

        echo json_encode(['success' => true, 'message' => 'Đã chỉnh sửa tin nhắn hỗ trợ thành công.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
}
?>
