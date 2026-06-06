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

$data  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id    = intval($data['id'] ?? 0);
$nguon = trim($data['nguon'] ?? 'dangbai');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID bài không hợp lệ']); exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensureDangbaiRoomStatusSchema($db);

    $extractImagePaths = function(array $row): array {
        $paths = [];
        if (!empty($row['hinhanh'])) {
            $paths[] = trim((string)$row['hinhanh']);
        }
        $rawList = trim((string)($row['hinhanh_list'] ?? ''));
        if ($rawList !== '') {
            $decoded = json_decode($rawList, true);
            if (is_array($decoded)) {
                foreach ($decoded as $img) {
                    if (is_string($img) && trim($img) !== '') $paths[] = trim($img);
                }
            } else {
                foreach (explode(',', $rawList) as $img) {
                    $img = trim($img);
                    if ($img !== '') $paths[] = $img;
                }
            }
        }
        return array_values(array_unique($paths));
    };

    if ($nguon === 'dangbai') {
        $check = $db->prepare("SELECT id, hinhanh, hinhanh_list, video FROM dangbai_chothuetro WHERE id = :id AND nguoidang = :u LIMIT 1");
        $check->execute([':id' => $id, ':u' => $_SESSION['username']]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa bài này']); exit;
        }

        // Xóa toàn bộ ảnh/video nếu tồn tại
        $imagePaths = $extractImagePaths($row);
        foreach ($imagePaths as $imgPath) {
            if (file_exists('../' . $imgPath)) {
                @unlink('../' . $imgPath);
            }
        }
        if (!empty($row['video']) && file_exists('../' . $row['video'])) {
            @unlink('../' . $row['video']);
        }

        $db->prepare("DELETE FROM dangbai_chothuetro WHERE id = :id AND nguoidang = :u")
           ->execute([':id' => $id, ':u' => $_SESSION['username']]);

    } else {
        $su = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $su->execute([':u' => $_SESSION['username']]);
        $user = $su->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ']); exit; }

        $check = $db->prepare("SELECT id FROM phongtro WHERE id = :id AND user_id = :uid LIMIT 1");
        $check->execute([':id' => $id, ':uid' => $user['id']]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa bài này']); exit;
        }

        $db->prepare("DELETE FROM phongtro WHERE id = :id AND user_id = :uid")
           ->execute([':id' => $id, ':uid' => $user['id']]);
    }

    echo json_encode(['success' => true, 'message' => 'Xóa bài đăng thành công!']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>
