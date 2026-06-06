<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']); exit;
}
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']); exit;
}

// Bảo mật CSRF
validateCsrfToken();

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$ids  = $data['ids'] ?? [];
$nguon = trim($data['nguon'] ?? 'dangbai');

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Không có bài đăng nào được chọn']); exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $username = $_SESSION['username'];
    $success_count = 0;

    $extractImagePaths = function(array $row): array {
        $paths = [];
        if (!empty($row['hinhanh'])) $paths[] = trim((string)$row['hinhanh']);
        $rawList = trim((string)($row['hinhanh_list'] ?? ''));
        if ($rawList !== '') {
            $decoded = json_decode($rawList, true);
            if (is_array($decoded)) {
                foreach ($decoded as $img) if (is_string($img) && trim($img) !== '') $paths[] = trim($img);
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
        foreach ($ids as $id) {
            $id = intval($id);
            if (!$id) continue;
            
            $check = $db->prepare("SELECT id, hinhanh, hinhanh_list, video FROM dangbai_chothuetro WHERE id = :id AND nguoidang = :u LIMIT 1");
            $check->execute([':id' => $id, ':u' => $username]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Delete media
                $imagePaths = $extractImagePaths($row);
                foreach ($imagePaths as $imgPath) if (file_exists('../' . $imgPath)) @unlink('../' . $imgPath);
                if (!empty($row['video']) && file_exists('../' . $row['video'])) @unlink('../' . $row['video']);
                
                $db->prepare("DELETE FROM dangbai_chothuetro WHERE id = :id AND nguoidang = :u")->execute([':id' => $id, ':u' => $username]);
                $success_count++;
            }
        }
    } else {
        $su = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $su->execute([':u' => $username]);
        $user = $su->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $uid = $user['id'];
            foreach ($ids as $id) {
                $id = intval($id);
                if (!$id) continue;
                
                $check = $db->prepare("DELETE FROM phongtro WHERE id = :id AND user_id = :uid");
                $check->execute([':id' => $id, ':uid' => $uid]);
                if ($check->rowCount() > 0) $success_count++;
            }
        }
    }

    echo json_encode(['success' => true, 'message' => "Đã xóa thành công $success_count bài đăng!"]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>
