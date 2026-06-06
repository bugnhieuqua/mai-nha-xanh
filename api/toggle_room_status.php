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

$data  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id    = intval($data['id'] ?? 0);
$nguon = trim($data['nguon'] ?? 'dangbai');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensureRoomStatusSchema($db);

    $targetStatus = trim($data['status'] ?? '');
    if ($targetStatus && !in_array($targetStatus, ['con_phong', 'da_thue', 'da_coc'], true)) {
        echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']); exit;
    }

    if ($nguon === 'dangbai') {
        // Kiểm tra quyền sở hữu
        $s = $db->prepare("SELECT trangthai, COALESCE(trangthai_phong, 'con_phong') as trangthai_phong
                           FROM dangbai_chothuetro WHERE id = :id AND nguoidang = :u LIMIT 1");
        $s->execute([':id' => $id, ':u' => $_SESSION['username']]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài đăng']); exit; }
        if (($row['trangthai'] ?? '') !== 'da_duyet') {
            echo json_encode(['success' => false, 'message' => 'Bài đăng chưa được duyệt nên chưa thể đổi trạng thái thuê']); exit;
        }

        $currentStatus = normalizeRoomStatusValue($row['trangthai_phong'] ?? 'con_phong');
        $newStatus = $targetStatus ?: (($currentStatus === 'da_thue') ? 'con_phong' : 'da_thue');
        
        $up = $db->prepare("UPDATE dangbai_chothuetro SET trangthai_phong = :st WHERE id = :id");
        $up->execute([':st' => $newStatus, ':id' => $id]);
    } else {
        // phongtro — kiểm tra qua user_id
        $su = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $su->execute([':u' => $_SESSION['username']]);
        $user = $su->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ']); exit; }

        $s = $db->prepare("SELECT trangthai FROM phongtro WHERE id = :id AND user_id = :uid LIMIT 1");
        $s->execute([':id' => $id, ':uid' => $user['id']]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài đăng']); exit; }

        $currentStatus = normalizeRoomStatusValue($row['trangthai'] ?? 'con_phong');
        $newStatus = $targetStatus ?: (($currentStatus === 'da_thue') ? 'con_phong' : 'da_thue');

        $up = $db->prepare("UPDATE phongtro SET trangthai = :st WHERE id = :id");
        $up->execute([':st' => $newStatus, ':id' => $id]);
    }

    if ($newStatus === 'da_thue') {
        $msg = 'Đã đánh dấu: Đã cho thuê';
    } elseif ($newStatus === 'da_coc') {
        $msg = 'Đã đánh dấu: Đã đặt cọc';
    } else {
        $msg = 'Đã đánh dấu: Còn phòng';
    }
    
    echo json_encode(['success' => true, 'message' => $msg, 'new_status' => $newStatus]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
