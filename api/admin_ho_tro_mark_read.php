<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Admin only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$session_id = trim($input['session_id'] ?? '');
$chat_type  = trim($input['chat_type'] ?? ''); // 'bot' | 'support'

if (empty($session_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing session_id']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // SPECIAL: Mark ALL as read (for clicking the History menu)
    if ($session_id === 'all_history') {
        $sql = "UPDATE chatbot_history SET is_read = 1 WHERE is_read = 0 AND sender IN ('user','ai')";
        if ($chat_type) {
            $sql .= " AND chat_type = " . $db->quote($chat_type);
        }
        $db->exec($sql);
        echo json_encode(['success' => true]);
        exit;
    }

    // 1. Try to find the user_id associated with this session to mark all their messages
    $uid = null;
    $stmtUser = $db->prepare("SELECT id FROM users WHERE chatbot_session_id = ? LIMIT 1");
    $stmtUser->execute([$session_id]);
    $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($uRow) $uid = (int)$uRow['id'];
    else {
        // Fallback: check chatbot_history for a user_id
        $stmtHist = $db->prepare("SELECT user_id FROM chatbot_history WHERE session_id = ? AND user_id IS NOT NULL LIMIT 1");
        $stmtHist->execute([$session_id]);
        $hRow = $stmtHist->fetch(PDO::FETCH_ASSOC);
        if ($hRow) $uid = (int)$hRow['user_id'];
    }

    // 2. Mark as read
    $typeFilter = $chat_type ? " AND chat_type = " . $db->quote($chat_type) : "";
    
    if ($uid) {
        // Mark all messages for this user as read
        $db->exec("UPDATE chatbot_history SET is_read = 1 WHERE user_id = $uid AND is_read = 0 $typeFilter");
    } else {
        // Guest: Mark only this session's messages as read
        $db->exec("UPDATE chatbot_history SET is_read = 1 WHERE session_id = " . $db->quote($session_id) . " AND is_read = 0 $typeFilter");
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
