<?php
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/session.php';

requireLogin('admin');

$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$date_from  = trim($_GET['date_from'] ?? '');
$date_to    = trim($_GET['date_to'] ?? '');
$keyword    = trim($_GET['keyword'] ?? '');
$session_q  = trim($_GET['v'] ?? '');
if ($session_q) $session_q = base64_decode($session_q);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Build WHERE
    $where = ['1=1'];
    $params = [];

    if ($date_from) {
        $where[] = 'DATE(created_at) >= :date_from';
        $params[':date_from'] = $date_from;
    }
    if ($date_to) {
        $where[] = 'DATE(created_at) <= :date_to';
        $params[':date_to'] = $date_to;
    }
    if ($keyword) {
        $where[] = '(user_message LIKE :kw OR bot_response LIKE :kw2)';
        $params[':kw']  = "%$keyword%";
        $params[':kw2'] = "%$keyword%";
    }
    if ($session_q) {
        $where[] = 'session_id = :session_id';
        $params[':session_id'] = $session_q;
    }

    $whereStr = implode(' AND ', $where);

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM chatbot_history WHERE $whereStr AND chat_type = 'bot'");
    foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
    $countStmt->execute();
    $total = $countStmt->fetchColumn();

    // Fetch data grouped by session
    $stmt = $db->prepare("
        SELECT ch.id, ch.session_id, ch.user_id, ch.user_message, ch.bot_response, ch.admin_message, ch.sender, ch.ip_address, ch.created_at,
               COALESCE(u.username, 'Khách') AS username
        FROM chatbot_history ch
        LEFT JOIN users u ON (ch.user_id = u.id OR (ch.user_id IS NULL AND u.chatbot_session_id = ch.session_id))
        WHERE $whereStr AND ch.chat_type = 'bot'
        ORDER BY ch.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats - Chỉ đếm tin nhắn AI Chatbot
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total_messages,
            COUNT(DISTINCT session_id) as total_sessions,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_messages
        FROM chatbot_history
        WHERE chat_type = 'bot'
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'stats'   => $stats,
        'total'   => $total,
        'page'    => $page,
        'per_page'=> $per_page,
        'pages'   => ceil($total / $per_page),
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
