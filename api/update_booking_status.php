<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/room_status_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']); exit;
}
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']); exit;
}

// Bảo mật CSRF
validateCsrfToken();

$data      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$bookingId = intval($data['booking_id'] ?? 0);
$postId    = intval($data['post_id'] ?? 0);
$nguon     = trim($data['nguon'] ?? 'dangbai');
$action    = trim($data['action'] ?? ''); // 'da_thue' hoặc 'con_phong'

if (!$bookingId || !$postId) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không đầy đủ']); exit;
}
if (!in_array($action, ['da_thue', 'con_phong'], true)) {
    echo json_encode(['success' => false, 'message' => 'Thao tác không hợp lệ']); exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensureRoomStatusSchema($db);

    // 1. Kiểm tra quyền sở hữu yêu cầu đặt phòng
    $s = $db->prepare("SELECT id FROM dat_phong WHERE id = :bid AND nguoidang = :u LIMIT 1");
    $s->execute([':bid' => $bookingId, ':u' => $_SESSION['username']]);
    if (!$s->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xử lý yêu cầu này']); exit;
    }

    // Tự động cập nhật ENUM nếu cần (Migration nhẹ)
    try {
        $db->exec("ALTER TABLE dat_phong MODIFY COLUMN trang_thai ENUM('cho_xu_ly','da_lien_he','tu_choi','da_thue') DEFAULT 'cho_xu_ly'");
    } catch (Exception $e) {}

    $db->beginTransaction();

    // 2. Cập nhật trạng thái yêu cầu đặt phòng
    // Nếu là 'da_thue', set trạng thái booking là 'da_thue'. 
    // Nếu là 'con_phong', set trạng thái booking về 'da_lien_he' (đã xử lý nhưng chưa chốt)
    $bookingStatus = ($action === 'da_thue') ? 'da_thue' : 'da_lien_he';
    $ub = $db->prepare("UPDATE dat_phong SET trang_thai = :st WHERE id = :bid");
    $ub->execute([':st' => $bookingStatus, ':bid' => $bookingId]);

    // 3. Cập nhật trạng thái bài đăng gốc
    $postStatus = ($action === 'da_thue') ? 'da_thue' : 'con_phong';
    if ($nguon === 'dangbai') {
        $up = $db->prepare("UPDATE dangbai_chothuetro SET trangthai_phong = :st WHERE id = :pid AND nguoidang = :u");
        $up->execute([':st' => $postStatus, ':pid' => $postId, ':u' => $_SESSION['username']]);
    } else {
        // phongtro - cần lấy user_id
        $su = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $su->execute([':u' => $_SESSION['username']]);
        $user = $su->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $up = $db->prepare("UPDATE phongtro SET trangthai = :st WHERE id = :pid AND user_id = :uid");
            $up->execute([':st' => $postStatus, ':pid' => $postId, ':uid' => $user['id']]);
        }
    }

    $db->commit();

    $msg = ($action === 'da_thue') ? 'Đã xác nhận: Đã thuê thành công' : 'Đã chuyển trạng thái: Còn phòng trống';
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
