<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/one_signal_helper.php';

header('Content-Type: application/json');

// Admin only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}
validateCsrfToken();

$input = json_decode(file_get_contents('php://input'), true);

$session_id = trim($input['session_id'] ?? '');
$message    = trim($input['message'] ?? '');

if (empty($session_id) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu session_id hoặc nội dung tin nhắn']);
    exit;
}

$session_id = substr($session_id, 0, 64);
$message    = substr($message, 0, 5000);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Try to find if this session belongs to a user_id
    $uid = null;
    $stmtUser = $db->prepare("SELECT id FROM users WHERE chatbot_session_id = ? LIMIT 1");
    $stmtUser->execute([$session_id]);
    $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($uRow) $uid = (int)$uRow['id'];
    else {
        // Fallback: check if chatbot_history already has a user_id for this session
        $stmtHist = $db->prepare("SELECT user_id FROM chatbot_history WHERE session_id = ? AND user_id IS NOT NULL LIMIT 1");
        $stmtHist->execute([$session_id]);
        $hRow = $stmtHist->fetch(PDO::FETCH_ASSOC);
        if ($hRow) $uid = (int)$hRow['user_id'];
    }

    // Insert message as admin
    $stmt = $db->prepare("
        INSERT INTO chatbot_history (session_id, user_id, chat_type, admin_message, sender, ip_address, user_agent, is_read, created_at)
        VALUES (:sid, :uid, 'support', :msg, 'admin', '', 'Admin Dashboard', 0, NOW())
    ");
    $stmt->bindParam(':sid', $session_id);
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':msg', $message);
    $stmt->execute();
    $lastId = $db->lastInsertId();

    // Tự động đánh dấu tất cả tin nhắn cũ là Đã đọc khi Admin gửi tin phản hồi
    if ($uid) {
        $db->prepare("UPDATE chatbot_history SET is_read = 1 WHERE user_id = ? AND is_read = 0 AND sender IN ('user','ai')")->execute([$uid]);
    } else {
        $db->prepare("UPDATE chatbot_history SET is_read = 1 WHERE session_id = ? AND is_read = 0 AND sender IN ('user','ai')")->execute([$session_id]);
    }

    if ($uid) {
        // Removed INSERT INTO notifications for admin replies
        // User requested to keep chat notifications strictly separate from the system Notification Bell
    }

    echo json_encode(['success' => true, 'message' => 'Đã gửi tin nhắn', 'id' => $lastId]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
