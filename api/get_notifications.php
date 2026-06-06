<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
require_once '../config/database.php';
require_once '../config/session.php';

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$since   = trim($data['since'] ?? '') ?: ($_GET['since'] ?? '');
$mark_id = intval($data['mark_read'] ?? 0);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Lấy user_id từ username session
    $su = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $su->execute([':u' => $_SESSION['username']]);
    $user = $su->fetch(PDO::FETCH_ASSOC);
    if (!$user) { echo json_encode(['success' => false, 'message' => 'Người dùng không tồn tại']); exit; }
    $uid = $user['id'];

    // Xoá 1 thông báo
    if (isset($data['delete_id']) && intval($data['delete_id']) > 0) {
        $db->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :uid")
           ->execute([':id' => intval($data['delete_id']), ':uid' => $uid]);
    }

    // Xoá toàn bộ thông báo của người dùng
    if (!empty($data['delete_all'])) {
        $db->prepare("DELETE FROM notifications WHERE user_id = :uid")
           ->execute([':uid' => $uid]);
    }

    // Đánh dấu 1 thông báo đã đọc nếu có yêu cầu
    if ($mark_id > 0) {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid")
           ->execute([':id' => $mark_id, ':uid' => $uid]);
    }

    // Đánh dấu toàn bộ đã đọc nếu yêu cầu
    if (!empty($data['mark_all_read'])) {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")
           ->execute([':uid' => $uid]);
    }

    // Lấy thông báo (lấy 20 mới nhất, hoặc từ since)
    if (!empty($since)) {
        $stmt = $db->prepare("SELECT id, type, title, content, link, is_read, created_at
                              FROM notifications
                              WHERE user_id = :uid AND created_at > :since
                              ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([':uid' => $uid, ':since' => $since]);
    } else {
        $stmt = $db->prepare("SELECT id, type, title, content, link, is_read, created_at
                              FROM notifications
                              WHERE user_id = :uid
                              ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([':uid' => $uid]);
    }
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Đếm tổng chưa đọc
    $cnt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $cnt->execute([':uid' => $uid]);
    $unread_count = (int)$cnt->fetchColumn();

    echo json_encode([
        'success'      => true,
        'data'         => $notifications,
        'unread_count' => $unread_count,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
