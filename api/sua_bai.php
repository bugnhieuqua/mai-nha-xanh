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

// Thực hiện Geocoding lấy tọa độ và lưu cứng vào CSDL
$coords = geocodeAddress($diachi);
if (!$coords) {
    $coords = getApproximateCoords($diachi, $id);
}
$lat = $coords['lat'];
$lng = $coords['lng'];


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
            trangthai = 'cho_duyet', lat = :lat, lng = :lng
            WHERE id = :id AND nguoidang = :u");
        
        if ($stmt->execute([
            ':tieude'     => $tieude,
            ':gia'        => $gia,
            ':dientich'   => $dientich,
            ':diachi'     => $diachi,
            ':mota'       => $mota,
            ':tiennghi'   => $tiennghi,
            ':ten_chunha' => $ten_chunha,
            ':sdt_chunha' => $sdt_chunha,
            ':lat'        => $lat,
            ':lng'        => $lng,
            ':id'         => $id,
            ':u'          => $_SESSION['username'],
        ])) {
            require_once __DIR__ . '/../includes/ai_moderation_helper.php';
            require_once __DIR__ . '/../includes/one_signal_helper.php';
            
            $aiRes = null;
            try {
                $aiRes = analyzePostWithAI($db, $id);
            } catch (Exception $e) {
                $aiRes = ['success' => false, 'message' => $e->getMessage()];
            }
            
            if ($aiRes && $aiRes['success']) {
                $verdict = $aiRes['data']['verdict'] ?? 'WARNING';
                $reasons = $aiRes['data']['reasons'] ?? [];
                $reasonsStr = !empty($reasons) ? implode(', ', $reasons) : '';
                
                if ($verdict === 'SAFE') {
                    // Phê duyệt tự động
                    $upd = $db->prepare("UPDATE dangbai_chothuetro SET trangthai = 'da_duyet', duyet_luc = NOW() WHERE id = :id");
                    $upd->execute([':id' => $id]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cập nhật bài đăng thành công và đã được duyệt tự động bởi Trợ lý AI! Bài đăng của bạn đã hiển thị.'
                    ]);
                    exit;
                } elseif ($verdict === 'DANGER') {
                    // Từ chối tự động: xóa khỏi database và xóa các tệp tin để "huỷ, không nhận"
                    // Lấy thông tin các tệp tin trước
                    $stmtInfo = $db->prepare("SELECT hinhanh, hinhanh_list, video FROM dangbai_chothuetro WHERE id = :id");
                    $stmtInfo->execute([':id' => $id]);
                    $post = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                    
                    $del = $db->prepare("DELETE FROM dangbai_chothuetro WHERE id = :id");
                    $del->execute([':id' => $id]);
                    
                    if ($post) {
                        if (!empty($post['hinhanh']) && file_exists('../' . $post['hinhanh'])) @unlink('../' . $post['hinhanh']);
                        if (!empty($post['video']) && file_exists('../' . $post['video'])) @unlink('../' . $post['video']);
                        if (!empty($post['hinhanh_list'])) {
                            $decoded = json_decode($post['hinhanh_list'], true);
                            if (is_array($decoded)) {
                                foreach ($decoded as $img) {
                                    $img = trim($img);
                                    if (!empty($img) && file_exists('../' . $img)) @unlink('../' . $img);
                                }
                            } else {
                                foreach (explode(',', $post['hinhanh_list']) as $img) {
                                    $img = trim($img);
                                    if (!empty($img) && file_exists('../' . $img)) @unlink('../' . $img);
                                }
                            }
                        }
                    }
                    
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cập nhật bài đăng thất bại! Trợ lý AI phát hiện rủi ro vi phạm chính sách và huỷ bài đăng này: ' . $reasonsStr
                    ]);
                    exit;
                } else {
                    // Cảnh báo (WARNING) -> Chờ duyệt thủ công
                    try {
                        sendNotification([
                            'type'    => 'new_post',
                            'target'  => 'admin',
                            'title'   => '🔔 Bài đăng chỉnh sửa chờ duyệt (Cảnh báo AI)',
                            'content' => 'AI cảnh báo rủi ro bài đăng chỉnh sửa "' . $tieude . '": ' . $reasonsStr,
                            'link'    => getBaseUrl() . '/admin/posts.php?id=' . $id
                        ]);
                    } catch (Exception $e) {}
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cập nhật bài đăng thành công! Bài viết đang chờ Admin duyệt thủ công do Trợ lý AI phát hiện cảnh báo: ' . $reasonsStr
                    ]);
                    exit;
                }
            } else {
                // Fallback khi lỗi AI
                try {
                    sendNotification([
                        'type'    => 'new_post',
                        'target'  => 'admin',
                        'title'   => '🔔 Bài đăng chỉnh sửa chờ duyệt',
                        'content' => 'Bài đăng "' . $tieude . '" của ' . $_SESSION['username'] . ' vừa được cập nhật và đang chờ duyệt.',
                        'link'    => getBaseUrl() . '/admin/posts.php?id=' . $id
                    ]);
                } catch (Exception $e) {}
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật bài đăng thành công! Bài viết đang chờ xét duyệt.'
                ]);
                exit;
            }
        }
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
            mota = :mota, tiennghi = :tiennghi, sdt_chunha = :sdt_chunha, lat = :lat, lng = :lng
            WHERE id = :id AND user_id = :uid");
        $stmt->execute([
            ':tieude'   => $tieude,
            ':gia'      => $gia,
            ':dientich' => $dientich,
            ':diachi'   => $diachi,
            ':mota'     => $mota,
            ':tiennghi' => $tiennghi,
            ':sdt_chunha' => $sdt_chunha,
            ':lat'      => $lat,
            ':lng'      => $lng,
            ':id'       => $id,
            ':uid'      => $user['id'],
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Cập nhật bài đăng thành công! Bài sẽ được xét duyệt lại.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>
