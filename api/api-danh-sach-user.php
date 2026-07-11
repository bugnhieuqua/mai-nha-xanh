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

    // ═══ Auto-migration: Thêm cột nhóm chat vào conversations nếu chưa có ═══
    try {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN group_name VARCHAR(100) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN group_avatar VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN group_owner_id INT DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN is_locked TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN locked_reason TEXT DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN locked_at DATETIME DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN locked_by VARCHAR(20) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversation_members ADD COLUMN role ENUM('member','admin','owner') DEFAULT 'member'");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversation_members ADD COLUMN joined_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE conversation_members ADD COLUMN deleted_at DATETIME DEFAULT NULL");
    } catch (Exception $e) {}

    // ═══ Phần 1: Truy vấn danh sách chat 1-1 (Tối ưu: dùng LEFT JOIN thay vì subquery lồng) ═══
    $sql_contacts = "
        SELECT 
            u.id, 
            u.username, 
            u.hoten, 
            u.avatar, 
            u.is_online, 
            u.last_active,
            last_msg.last_msg_time,
            COALESCE(unread.unread_count, 0) as unread_count
        FROM users u
        LEFT JOIN (
            SELECT 
                CASE WHEN cm1.user_id = :uid_a THEN cm2.user_id ELSE cm1.user_id END as partner_id,
                MAX(m.created_at) as last_msg_time
            FROM conversation_members cm1
            JOIN conversation_members cm2 ON cm1.conversation_id = cm2.conversation_id AND cm1.user_id != cm2.user_id
            JOIN conversations c ON c.id = cm1.conversation_id AND c.is_group = 0
            LEFT JOIN messages m ON m.conversation_id = cm1.conversation_id AND (cm1.deleted_at IS NULL OR m.created_at > cm1.deleted_at)
            WHERE cm1.user_id = :uid_b OR cm2.user_id = :uid_c
            GROUP BY partner_id
        ) last_msg ON last_msg.partner_id = u.id
        LEFT JOIN (
            SELECT 
                m.sender_id,
                COUNT(m.id) as unread_count
            FROM messages m
            JOIN conversation_members cm1 ON m.conversation_id = cm1.conversation_id
            JOIN conversation_members cm2 ON m.conversation_id = cm2.conversation_id AND cm1.user_id != cm2.user_id
            JOIN conversations c ON c.id = m.conversation_id AND c.is_group = 0
            WHERE cm1.user_id = :uid_d
              AND m.sender_id = cm2.user_id
              AND m.is_read = 0
              AND (cm1.deleted_at IS NULL OR m.created_at > cm1.deleted_at)
            GROUP BY m.sender_id
        ) unread ON unread.sender_id = u.id
        WHERE u.id != :uid_e
        ORDER BY 
            CASE WHEN last_msg.last_msg_time IS NOT NULL THEN 1 ELSE 0 END DESC,
            last_msg.last_msg_time DESC, 
            u.is_online DESC, 
            u.hoten ASC
    ";
    $stmt = $pdo->prepare($sql_contacts);
    $stmt->execute([
        ':uid_a' => $currentUserId,
        ':uid_b' => $currentUserId,
        ':uid_c' => $currentUserId,
        ':uid_d' => $currentUserId,
        ':uid_e' => $currentUserId
    ]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hóa tên và avatar cho contacts
    foreach ($contacts as &$contact) {
        if (empty($contact['hoten'])) {
            $contact['hoten'] = $contact['username'];
        }
        if (empty($contact['avatar'])) {
            $initial = mb_strtoupper(mb_substr($contact['hoten'], 0, 1, 'UTF-8'), 'UTF-8');
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="#9ca3af"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-size="48" font-family="sans-serif" font-weight="bold" fill="#ffffff">' . $initial . '</text></svg>';
            $contact['avatar'] = 'data:image/svg+xml;base64,' . base64_encode($svg);
        }
        $contact['type'] = 'direct'; // Đánh dấu kiểu chat 1-1
    }
    unset($contact);

    // ═══ Phần 2: Truy vấn danh sách nhóm chat ═══
    $sql_groups = "
        SELECT 
            c.id as conversation_id,
            c.group_name,
            c.group_avatar,
            c.group_owner_id,
            c.is_locked,
            c.locked_reason,
            c.created_at,
            (SELECT COUNT(*) FROM conversation_members cm2 WHERE cm2.conversation_id = c.id) as member_count,
            (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id AND (cm.deleted_at IS NULL OR m.created_at > cm.deleted_at)) as last_msg_time,
            (SELECT COUNT(m.id) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != :uid_g AND m.is_read = 0 AND (cm.deleted_at IS NULL OR m.created_at > cm.deleted_at)) as unread_count,
            cm.role as my_role
        FROM conversations c
        JOIN conversation_members cm ON cm.conversation_id = c.id AND cm.user_id = :uid_f
        WHERE c.is_group = 1
          AND (cm.deleted_at IS NULL OR (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id) > cm.deleted_at OR c.created_at > cm.deleted_at)
        ORDER BY (last_msg_time IS NULL) ASC, last_msg_time DESC, c.created_at DESC
    ";
    $stmtGroups = $pdo->prepare($sql_groups);
    $stmtGroups->execute([
        ':uid_f' => $currentUserId,
        ':uid_g' => $currentUserId
    ]);
    $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hóa dữ liệu nhóm
    foreach ($groups as &$group) {
        if (empty($group['group_avatar'])) {
            // Tạo SVG icon nhóm
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="#10b981" rx="20"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-size="36" font-family="sans-serif" font-weight="bold" fill="#ffffff">👥</text></svg>';
            $group['group_avatar'] = 'data:image/svg+xml;base64,' . base64_encode($svg);
        }
        $group['type'] = 'group'; // Đánh dấu kiểu nhóm chat
    }
    unset($group);

    echo json_encode([
        'status' => 'success', 
        'contacts' => $contacts,
        'groups' => $groups
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $e->getMessage()]);
}
?>
