<?php
/**
 * API: admin_badge_counts.php
 * Trả về số lượng unread/pending cho các mục trong sidebar Admin.
 * Được gọi mỗi 3 giây bởi ho_tro.php (và các trang admin khác)
 * để cập nhật badge realtime mà không cần reload trang.
 */
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/room_status_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Chỉ Admin mới được gọi API này
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensureDangbaiRoomStatusSchema($db);

    $counts = [];

    // [1] Bài đăng phòng trọ đang chờ duyệt
    $s = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai = 'cho_duyet'");
    $counts['pending_posts'] = (int)$s->fetchColumn();

    // [2] Liên hệ mới chưa xem (Flow 1)
    try {
        $s = $db->query("SELECT COUNT(*) FROM lienhe WHERE admin_seen = 0");
        $counts['unread_lienhe'] = (int)$s->fetchColumn();
    } catch (Exception $e) {
        $counts['unread_lienhe'] = 0;
    }
    
    // [3] Hỗ trợ người dùng: Tin nhắn mới thực tế (Flow 2)
    try {
        // Chỉ đếm những session có tin nhắn chưa đọc và chat_type='support'
        $s = $db->query("
            SELECT COUNT(DISTINCT session_id) FROM chatbot_history 
            WHERE is_read = 0 AND sender IN ('user','ai') AND chat_type = 'support'
        ");
        $counts['unread_hotro'] = (int)$s->fetchColumn();
    } catch (Exception $e) {
        $counts['unread_hotro'] = 0;
    }

    // [4] Lịch sử tin nhắn Chatbot AI chưa xem (unread bot messages)
    try {
        $s = $db->query("SELECT COUNT(DISTINCT session_id) FROM chatbot_history WHERE is_read = 0 AND sender IN ('user','ai') AND chat_type = 'bot'");
        $counts['unread_tinnhan'] = (int)$s->fetchColumn();
    } catch (Exception $e) {
        $counts['unread_tinnhan'] = 0;
    }

    // [5] Báo cáo vi phạm chưa xử lý
    try {
        $s = $db->query("SELECT COUNT(*) FROM reports WHERE trang_thai = 'cho_xu_ly' OR trang_thai IS NULL");
        $counts['pending_reports'] = (int)$s->fetchColumn();
    } catch (Exception $e) {
        $counts['pending_reports'] = 0;
    }

    echo json_encode([
        'success' => true,
        'counts'  => $counts,
        'time'    => date('H:i:s'),
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
