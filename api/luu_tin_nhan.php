<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/session.php';
require_once '../config/database.php';
require_once 'rate_limit.php';
checkRateLimit('chatbot_msg', 20, 60); // max 20 tin/phút

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

// Bảo mật CSRF
validateCsrfToken();

$input = json_decode(file_get_contents('php://input'), true);

$session_id   = trim($input['session_id'] ?? '');
$user_message = trim($input['user_message'] ?? '');
$bot_response = trim($input['bot_response'] ?? '');

if (empty($session_id) || empty($user_message) || empty($bot_response)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
    exit;
}

// Giới hạn độ dài
$session_id   = substr($session_id, 0, 64);
$user_message = substr($user_message, 0, 5000);
$bot_response = substr($bot_response, 0, 10000);

$ip         = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Auto-migration: thêm cột chatbot_session_id vào users nếu chưa có
    try { $db->exec("ALTER TABLE users ADD COLUMN chatbot_session_id VARCHAR(64) DEFAULT NULL"); } catch (Exception $e) {}

    // Liên kết session với user đang đăng nhập (để admin thấy tên thật)
    $uid = null;
    if (!empty($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $linkStmt = $db->prepare("UPDATE users SET chatbot_session_id = :sid WHERE id = :uid");
        $linkStmt->bindParam(':sid', $session_id);
        $linkStmt->bindParam(':uid', $uid, PDO::PARAM_INT);
        $linkStmt->execute();
    }

    $stmt = $db->prepare("
        INSERT INTO chatbot_history (session_id, user_id, chat_type, user_message, bot_response, ip_address, user_agent, sender)
        VALUES (:session_id, :user_id, 'bot', :user_message, :bot_response, :ip, :ua, 'ai')
    ");
    $stmt->bindParam(':session_id',   $session_id);
    $stmt->bindParam(':user_id',      $uid, PDO::PARAM_INT);
    $stmt->bindParam(':user_message', $user_message);
    $stmt->bindParam(':bot_response', $bot_response);
    $stmt->bindParam(':ip',           $ip);
    $stmt->bindParam(':ua',           $user_agent);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
