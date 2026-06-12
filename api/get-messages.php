<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

try {
    $database = new Database();
    $db = $database->getConnection();

    $session_id = trim($_GET['session_id'] ?? '');
    $since = trim($_GET['since'] ?? '');     // e.g. "2024-01-01 00:00:00"
    $chat_type = trim($_GET['chat_type'] ?? ''); // 'bot' | 'support'
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

    if (empty($session_id)) {
        echo json_encode(['success' => false, 'message' => 'Thiếu session_id']);
        exit;
    }

    // NEW: If this session belongs to a user, get their user_id to fetch all their messages
    $uid = null;
    if (!empty($_SESSION['user_id']) && !$is_admin) {
        $uid = (int) $_SESSION['user_id'];
    } else {
        $stmtUser = $db->prepare("SELECT id FROM users WHERE chatbot_session_id = ? LIMIT 1");
        $stmtUser->execute([$session_id]);
        $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($uRow)
            $uid = (int) $uRow['id'];
        else {
            // Find user_id from history if not in users table yet
            $stmtHist = $db->prepare("SELECT user_id FROM chatbot_history WHERE session_id = ? AND user_id IS NOT NULL LIMIT 1");
            $stmtHist->execute([$session_id]);
            $hRow = $stmtHist->fetch(PDO::FETCH_ASSOC);
            if ($hRow)
                $uid = (int) $hRow['user_id'];
        }
    }

    $params = [];
    $whereParts = [];

    if ($uid) {
        $whereParts[] = "(user_id = :uid OR session_id = :sid)";
        $params[':uid'] = $uid;
        $params[':sid'] = $session_id;
    } else {
        $whereParts[] = "session_id = :sid";
        $params[':sid'] = $session_id;
    }

    $sinceClause = '';
    if ($since !== '') {
        $sinceClause = ' AND created_at > :since';
        $params[':since'] = $since;
    }

    $typeClause = '';
    if ($chat_type !== '') {
        $typeClause = ' AND chat_type = :ctype';
        $params[':ctype'] = $chat_type;
    }

    $whereStr = implode(' OR ', $whereParts); // Usually only one, but logic is safe

    // Fetch all messages
    $stmt = $db->prepare("
        SELECT id, session_id, user_id, user_message, bot_response, sender, admin_message, is_read, created_at, chat_type
        FROM chatbot_history
        WHERE ($whereStr)
        $sinceClause
        $typeClause
        ORDER BY created_at ASC, id ASC
        LIMIT 200
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark messages as read depending on who is viewing, ONLY if mark_read=1 is passed
    $should_mark_read = isset($_GET['mark_read']) && $_GET['mark_read'] == '1';
    if ($should_mark_read) {
        if ($is_admin) {
            // Admin is viewing: mark user/AI messages as read
            if ($uid) {
                $db->prepare("UPDATE chatbot_history SET is_read=1 WHERE (user_id=:uid OR session_id=:sid) AND sender IN ('user','ai') AND is_read=0")
                    ->execute([':uid' => $uid, ':sid' => $session_id]);
            } else {
                $db->prepare("UPDATE chatbot_history SET is_read=1 WHERE session_id=:sid AND sender IN ('user','ai') AND is_read=0")
                    ->execute([':sid' => $session_id]);
            }
        } else {
            // User is viewing: mark admin messages as read
            if ($uid) {
                $db->prepare("UPDATE chatbot_history SET is_read=1 WHERE (user_id=:uid OR session_id=:sid) AND sender='admin' AND is_read=0")
                    ->execute([':uid' => $uid, ':sid' => $session_id]);
            } else {
                $db->prepare("UPDATE chatbot_history SET is_read=1 WHERE session_id=:sid AND sender='admin' AND is_read=0")
                    ->execute([':sid' => $session_id]);
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $rows]);

} catch (PDOException $e) {
    // Bảo mật: không lộ chi tiết DB ra ngoài
    echo json_encode(['success' => false, 'message' => 'Hệ thống đang bảo trì. Vui lòng thử lại sau ít phút.']);
}
?>