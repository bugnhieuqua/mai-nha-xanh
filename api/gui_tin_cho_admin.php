<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/session.php';
require_once '../config/database.php';
require_once 'rate_limit.php';
checkRateLimit('send_admin_msg', 10, 60); // max 10 tin/phút

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

// Bảo mật CSRF
validateCsrfToken();

$input = json_decode(file_get_contents('php://input'), true);

$session_id = trim($input['session_id'] ?? '');
$message    = trim($input['message'] ?? '');

if (empty($session_id) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
    exit;
}

$session_id = substr($session_id, 0, 64);
$message    = substr($message, 0, 2000);

$ip         = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Auto-migration: thêm cột chatbot_session_id vào users nếu chưa có
    try { $db->exec("ALTER TABLE users ADD COLUMN chatbot_session_id VARCHAR(64) DEFAULT NULL"); } catch (Exception $e) {}

    // Liên kết session với user đang đăng nhập (để admin thấy tên thật)
    if (!empty($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $linkStmt = $db->prepare("UPDATE users SET chatbot_session_id = :sid WHERE id = :uid");
        $linkStmt->bindParam(':sid', $session_id);
        $linkStmt->bindParam(':uid', $uid, PDO::PARAM_INT);
        $linkStmt->execute();
    }

    // Insert as a 'user' sender message so admin can see it in ho_tro.php
    $uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $stmt = $db->prepare("
        INSERT INTO chatbot_history (session_id, user_id, chat_type, user_message, ip_address, user_agent, sender)
        VALUES (:session_id, :user_id, 'support', :user_message, :ip, :ua, 'user')
    ");
    $stmt->bindParam(':session_id',   $session_id);
    $stmt->bindParam(':user_id',      $uid, PDO::PARAM_INT);
    $stmt->bindParam(':user_message', $message);
    $stmt->bindParam(':ip',           $ip);
    $stmt->bindParam(':ua',           $user_agent);
    $stmt->execute();
    $lastId = $db->lastInsertId();

    echo json_encode(['success' => true, 'id' => $lastId]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
