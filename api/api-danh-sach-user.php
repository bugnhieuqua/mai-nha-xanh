<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

$currentUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);

if ($currentUserId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Người dùng không hợp lệ. Vui lòng đăng nhập.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    // Truy vấn lấy danh sách những người dùng khác ngoại trừ bản thân
    // Kèm theo thời gian tin nhắn cuối cùng và số lượng tin nhắn chưa đọc từ họ
    $sql = "
        SELECT 
            u.id, 
            u.username, 
            u.hoten, 
            u.avatar, 
            u.is_online, 
            u.last_active,
            (
                SELECT m.created_at 
                FROM messages m
                WHERE m.conversation_id = (
                    SELECT cm1.conversation_id 
                    FROM conversation_members cm1 
                    JOIN conversation_members cm2 ON cm1.conversation_id = cm2.conversation_id
                    WHERE cm1.user_id = :current_id1 AND cm2.user_id = u.id
                    LIMIT 1
                )
                ORDER BY m.id DESC LIMIT 1
            ) as last_msg_time,
            (
                SELECT COUNT(m.id)
                FROM messages m
                WHERE m.conversation_id = (
                    SELECT cm1.conversation_id 
                    FROM conversation_members cm1 
                    JOIN conversation_members cm2 ON cm1.conversation_id = cm2.conversation_id
                    WHERE cm1.user_id = :current_id2 AND cm2.user_id = u.id
                    LIMIT 1
                )
                  AND m.sender_id = u.id
                  AND m.is_read = 0
            ) as unread_count
        FROM users u
        WHERE u.id != :current_id3
        ORDER BY 
            CASE WHEN last_msg_time IS NOT NULL THEN 1 ELSE 0 END DESC,
            last_msg_time DESC, 
            u.is_online DESC, 
            u.hoten ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':current_id1' => $currentUserId,
        ':current_id2' => $currentUserId,
        ':current_id3' => $currentUserId
    ]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hóa tên và avatar
    foreach ($contacts as &$contact) {
        if (empty($contact['hoten'])) {
            $contact['hoten'] = $contact['username'];
        }
        if (empty($contact['avatar'])) {
            // Tự động tạo Avatar SVG xám chữ trắng dựa trên chữ cái đầu của tên
            $initial = mb_strtoupper(mb_substr($contact['hoten'], 0, 1, 'UTF-8'), 'UTF-8');
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="#9ca3af"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-size="48" font-family="sans-serif" font-weight="bold" fill="#ffffff">' . $initial . '</text></svg>';
            $contact['avatar'] = 'data:image/svg+xml;base64,' . base64_encode($svg);
        }
    }

    echo json_encode(['status' => 'success', 'contacts' => $contacts]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $e->getMessage()]);
}
?>
