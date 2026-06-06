<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // ── Pure chatbot sessions (not linked to any lienhe) ──────
    $stmt_ch = $db->query("
        SELECT
            NULL AS lienhe_id,
            ch.session_id,
            COALESCE(u.username, 'Khách') AS hoten,
            COALESCE(u.email, '') AS email,
            '' AS sodienthoai,
            '' AS tieude,
            (SELECT user_message FROM chatbot_history WHERE session_id=ch.session_id ORDER BY id ASC LIMIT 1) AS first_message,
            MIN(ch.created_at) AS started_at,
            SUM(CASE WHEN ch.sender='user' AND ch.is_read=0 THEN 1 ELSE 0 END) AS unread,
            MAX(ch.created_at) AS last_message_time,
            'chatbot' AS source,
            -- Quick preview summary (recent activity)
            CONCAT(
                'Tháng ', MONTH(MAX(ch.created_at)), '/',
                YEAR(MAX(ch.created_at)), ': ',
                COUNT(DISTINCT DATE(ch.created_at)), ' ngày chat, ',
                COUNT(*) , ' tin nhắn'
            ) AS activity_summary
        FROM chatbot_history ch
        LEFT JOIN users u ON u.chatbot_session_id = ch.session_id
        WHERE NOT EXISTS (
            SELECT 1 FROM lienhe l WHERE l.session_id = ch.session_id
        )
        GROUP BY ch.session_id, u.username, u.email
        ORDER BY last_message_time DESC
        LIMIT 50
    ");
    $chatbot_sessions = $stmt_ch->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $chatbot_sessions]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
