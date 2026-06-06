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

    // Migration đã hoàn tất thành công - Các cột database đã ổn định
    // (Lưu ý cho dev: Nếu cần thay đổi cấu trúc DB sau này, vui lòng dùng scripts riêng)

    // Migration đã hoàn tất - Xóa bỏ logic UPDATE để tăng tốc độ load trang
    // (Đã đảm bảo chat_type được gán đúng trong các file xử lý tin nhắn mới)

    // chatbot_session_id column in users (linked when user chats)
    try { $db->exec("ALTER TABLE users ADD COLUMN chatbot_session_id VARCHAR(64) DEFAULT NULL"); } catch (Exception $e) {}

    // ── Unified Session List (Grouped by User ID if available) ────────
    $sql = "
        (
            /* Form-based sessions */
            SELECT
                l.id AS lienhe_id,
                COALESCE(l.session_id, CONCAT('lienhe_', l.id)) AS session_id,
                l.hoten,
                l.email,
                l.sodienthoai,
                l.tieude,
                COALESCE(
                    (SELECT COALESCE(NULLIF(admin_message, ''), NULLIF(bot_response, ''), NULLIF(user_message, ''))
                     FROM chatbot_history ch3 
                     WHERE ch3.session_id = COALESCE(l.session_id, CONCAT('lienhe_', l.id))
                     AND ch3.chat_type = 'support'
                     ORDER BY id DESC LIMIT 1),
                    l.noidung
                ) AS last_message,
                (SELECT sender FROM chatbot_history ch4 
                 WHERE ch4.session_id = COALESCE(l.session_id, CONCAT('lienhe_', l.id)) 
                 AND ch4.chat_type = 'support'
                 ORDER BY id DESC LIMIT 1) AS last_sender,
                l.ngaygui AS started_at,
                COALESCE(
                    (SELECT MAX(created_at) FROM chatbot_history ch1 
                     WHERE ch1.session_id = COALESCE(l.session_id, CONCAT('lienhe_', l.id))
                     AND ch1.chat_type = 'support'),
                    l.ngaygui
                ) AS last_activity,
                COALESCE(
                    (SELECT COUNT(*) FROM chatbot_history ch2
                     WHERE ch2.session_id = COALESCE(l.session_id, CONCAT('lienhe_', l.id)) AND ch2.sender='user' AND ch2.is_read=0 AND ch2.chat_type = 'support'),
                    0
                ) AS unread,
                COALESCE(
                    (SELECT COUNT(*) FROM chatbot_history ch_tot
                     WHERE ch_tot.session_id = COALESCE(l.session_id, CONCAT('lienhe_', l.id)) AND ch_tot.chat_type = 'support'),
                    0
                ) AS total_msgs,
                'contact' AS source,
                NULL AS avatar
            FROM lienhe l
            WHERE EXISTS (
                SELECT 1 FROM chatbot_history ch_check 
                WHERE ch_check.session_id = COALESCE(l.session_id, CONCAT('lienhe_', l.id))
                AND ch_check.chat_type = 'support'
            )
        )
        UNION ALL
        (
            SELECT
                NULL AS lienhe_id,
                ch.session_id,
                COALESCE(MAX(u.username), 'Khách') AS hoten,
                COALESCE(MAX(u.email), '') AS email,
                '' AS sodienthoai,
                '' AS tieude,
                (SELECT COALESCE(NULLIF(admin_message, ''), NULLIF(bot_response, ''), NULLIF(user_message, ''))
                 FROM chatbot_history ch3 
                 WHERE ch3.session_id = ch.session_id 
                 AND ch3.chat_type = 'support'
                 ORDER BY id DESC LIMIT 1) AS last_message,
                (SELECT sender FROM chatbot_history ch4 
                 WHERE ch4.session_id = ch.session_id 
                 AND ch4.chat_type = 'support'
                 ORDER BY id DESC LIMIT 1) AS last_sender,
                MIN(ch.created_at) AS started_at,
                MAX(ch.created_at) AS last_activity,
                SUM(CASE WHEN ch.sender IN ('user','ai') AND ch.is_read=0 THEN 1 ELSE 0 END) AS unread,
                COUNT(ch.id) AS total_msgs,
                'chatbot' AS source,
                MAX(u.avatar) AS avatar
            FROM chatbot_history ch
            LEFT JOIN users u ON (ch.user_id = u.id AND ch.sender = 'user' AND u.role != 'admin')
            WHERE ch.session_id NOT IN (
                SELECT COALESCE(session_id, CONCAT('lienhe_', id)) FROM lienhe
            )
            AND ch.chat_type = 'support'
            GROUP BY ch.session_id
        )
        ORDER BY last_activity DESC
        LIMIT 100
    ";

    $stmt_lh = $db->query($sql);
    $contacts = $stmt_lh->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $contacts]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
