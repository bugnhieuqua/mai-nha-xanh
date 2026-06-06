<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/room_status_helper.php';

validateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']); exit;
}
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']); exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id     = intval($data['id'] ?? 0);
$nguon  = trim($data['nguon'] ?? 'dangbai');

// Các trường cập nhật
$tieude     = trim($data['tieude'] ?? '');
$gia        = trim($data['gia'] ?? '');
$dientich   = trim($data['dientich'] ?? '');
$diachi     = trim($data['diachi'] ?? '');
$mota       = trim($data['mota'] ?? '');
$tiennghi   = trim($data['tiennghi'] ?? '');
$ten_chunha = trim($data['ten_chunha'] ?? '');
$sdt_chunha = trim($data['sdt_chunha'] ?? '');

if (!$id || !$tieude || !$gia || !$dientich || !$diachi) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin bắt buộc']); exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensureDangbaiRoomStatusSchema($db);

    if ($nguon === 'dangbai') {
        // Kiểm tra quyền sở hữu
        $check = $db->prepare("SELECT id FROM dangbai_chothuetro WHERE id = :id AND nguoidang = :u LIMIT 1");
        $check->execute([':id' => $id, ':u' => $_SESSION['username']]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền sửa bài này']); exit;
        }

        $stmt = $db->prepare("UPDATE dangbai_chothuetro SET
            tieude = :tieude, gia = :gia, dientich = :dientich, diachi = :diachi,
            mota = :mota, tiennghi = :tiennghi, ten_chunha = :ten_chunha, sdt_chunha = :sdt_chunha,
            trangthai = 'cho_duyet'
            WHERE id = :id AND nguoidang = :u");
        $stmt->execute([
            ':tieude'     => $tieude,
            ':gia'        => $gia,
            ':dientich'   => $dientich,
            ':diachi'     => $diachi,
            ':mota'       => $mota,
            ':tiennghi'   => $tiennghi,
            ':ten_chunha' => $ten_chunha,
            ':sdt_chunha' => $sdt_chunha,
            ':id'         => $id,
            ':u'          => $_SESSION['username'],
        ]);
    } else {
        // phongtro — kiểm tra qua user_id
        $su = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $su->execute([':u' => $_SESSION['username']]);
        $user = $su->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ']); exit; }

        $check = $db->prepare("SELECT id FROM phongtro WHERE id = :id AND user_id = :uid LIMIT 1");
        $check->execute([':id' => $id, ':uid' => $user['id']]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền sửa bài này']); exit;
        }

        $stmt = $db->prepare("UPDATE phongtro SET
            ten_phong = :tieude, gia = :gia, dientich = :dientich, diachi = :diachi,
            mota = :mota, tiennghi = :tiennghi, sdt_chunha = :sdt_chunha
            WHERE id = :id AND user_id = :uid");
        $stmt->execute([
            ':tieude'   => $tieude,
            ':gia'      => $gia,
            ':dientich' => $dientich,
            ':diachi'   => $diachi,
            ':mota'     => $mota,
            ':tiennghi' => $tiennghi,
            ':sdt_chunha' => $sdt_chunha,
            ':id'       => $id,
            ':uid'      => $user['id'],
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Cập nhật bài đăng thành công! Bài sẽ được xét duyệt lại.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>
