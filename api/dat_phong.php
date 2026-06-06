<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/room_status_helper.php';

// Bảo vệ CSRF cho mọi request POST
validateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

// Lấy dữ liệu
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$post_id   = intval($data['post_id'] ?? 0);
$nguon     = trim($data['nguon'] ?? 'dangbai'); // 'dangbai' hoặc 'phongtro'
$ho_ten    = trim($data['ho_ten'] ?? '');
$sdt       = trim($data['so_dien_thoai'] ?? '');
$ghi_chu   = trim($data['ghi_chu'] ?? '');
$ngay_thue = trim($data['ngay_muon_thue'] ?? '');

if (!$post_id || !$ho_ten || !$sdt) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ họ tên và số điện thoại']);
    exit;
}
if (!in_array($nguon, ['dangbai', 'phongtro'], true)) {
    echo json_encode(['success' => false, 'message' => 'Nguồn bài đăng không hợp lệ']);
    exit;
}
if (!preg_match('/^[0-9]{10,11}$/', $sdt)) {
    echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ (10-11 chữ số)']);
    exit;
}

try {
    $currentRoomStatus = null;
    $database = new Database();
    $db = $database->getConnection();
    ensureRoomStatusSchema($db);

    // Tự động tạo bảng dat_phong nếu chưa có
    $db->exec("CREATE TABLE IF NOT EXISTS dat_phong (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        nguon VARCHAR(20) DEFAULT 'dangbai',
        ten_phong VARCHAR(200) DEFAULT '',
        ho_ten VARCHAR(100) NOT NULL,
        so_dien_thoai VARCHAR(20) NOT NULL,
        ngay_muon_thue DATE NULL,
        ghi_chu TEXT,
        nguoidang VARCHAR(100) DEFAULT '',
        trang_thai ENUM('cho_xu_ly','da_lien_he','tu_choi','da_thue') DEFAULT 'cho_xu_ly',
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_post_id (post_id),
        INDEX idx_nguoidang (nguoidang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Đảm bảo cột is_read tồn tại nếu bảng đã có sẵn
    try { $db->exec("ALTER TABLE dat_phong ADD COLUMN is_read TINYINT DEFAULT 0"); } catch (Exception $e) {}
    // Cập nhật ENUM trang_thai nếu cần
    try { $db->exec("ALTER TABLE dat_phong MODIFY COLUMN trang_thai ENUM('cho_xu_ly','da_lien_he','tu_choi','da_thue') DEFAULT 'cho_xu_ly'"); } catch (Exception $e) {}

    // Tự động thêm type 'booking' nếu ENUM chưa có (MySQL ALTER COLUMN ENUM)
    try {
        $db->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM('new_post','post_approved','post_rejected','new_contact','new_chat','booking') NOT NULL");
    } catch (Exception $e) {}

    $db->beginTransaction();

    // Lấy thông tin bài đăng (tên phòng, người đăng)
    $ten_phong   = '';
    $nguoidang   = '';
    $owner_uid   = null;
    $roomStatus  = 'con_phong';

    if ($nguon === 'dangbai') {
        $s = $db->prepare("SELECT tieude, nguoidang, trangthai, COALESCE(trangthai_phong, 'con_phong') as trangthai_phong
                           FROM dangbai_chothuetro WHERE id = :id LIMIT 1 FOR UPDATE");
        $s->execute([':id' => $post_id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $ten_phong = $row['tieude'];
            $nguoidang = $row['nguoidang'];
            $roomStatus = normalizeRoomStatusValue($row['trangthai_phong'] ?? 'con_phong');
            $currentRoomStatus = $roomStatus;
        }
        if (!$row || ($row['trangthai'] ?? '') !== 'da_duyet') {
            throw new RuntimeException('Bài đăng này chưa được duyệt hoặc không tồn tại');
        }
    } else {
        $s = $db->prepare("SELECT ten_phong, trangthai FROM phongtro WHERE id = :id LIMIT 1 FOR UPDATE");
        $s->execute([':id' => $post_id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $ten_phong = $row['ten_phong'];
            $roomStatus = normalizeRoomStatusValue($row['trangthai'] ?? 'con_phong');
            $currentRoomStatus = $roomStatus;
        }
        if (!$row) {
            throw new RuntimeException('Phòng trọ không tồn tại');
        }
    }

    if ($roomStatus !== 'con_phong') {
        throw new RuntimeException($roomStatus === 'da_coc' ? 'Phòng này đã có người đặt cọc, tạm khóa đặt phòng' : 'Phòng này đã được thuê');
    }

    // Tìm user_id của chủ bài để gửi thông báo
    if (!empty($nguoidang)) {
        $su = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $su->execute([':u' => $nguoidang]);
        $urow = $su->fetch(PDO::FETCH_ASSOC);
        if ($urow) $owner_uid = $urow['id'];
    }

    // Lưu yêu cầu đặt phòng
    $ins = $db->prepare("INSERT INTO dat_phong (post_id, nguon, ten_phong, ho_ten, so_dien_thoai, ngay_muon_thue, ghi_chu, nguoidang)
                         VALUES (:post_id, :nguon, :ten_phong, :ho_ten, :sdt, :ngay, :ghi_chu, :nguoidang)");
    $ngay_val = !empty($ngay_thue) ? $ngay_thue : null;
    $ins->execute([
        ':post_id'   => $post_id,
        ':nguon'     => $nguon,
        ':ten_phong' => $ten_phong,
        ':ho_ten'    => $ho_ten,
        ':sdt'       => $sdt,
        ':ngay'      => $ngay_val,
        ':ghi_chu'   => $ghi_chu,
        ':nguoidang' => $nguoidang,
    ]);

    // Lấy ID chủ bài để gửi thông báo cá nhân
    if ($nguoidang) {
        $sUser = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $sUser->execute([':u' => $nguoidang]);
        $uRow = $sUser->fetch(PDO::FETCH_ASSOC);
        if ($uRow) $owner_uid = $uRow['id'];
    }

    // 4. KHOÁ PHÒNG TỰ ĐỘNG (Đặt trạng thái da_coc)
    if ($nguon === 'dangbai') {
        $upPost = $db->prepare("UPDATE dangbai_chothuetro SET trangthai_phong = 'da_coc' WHERE id = :pid");
        $upPost->execute([':pid' => $post_id]);
    } else {
        $upPost = $db->prepare("UPDATE phongtro SET trangthai = 'da_coc' WHERE id = :pid");
        $upPost->execute([':pid' => $post_id]);
    }

    // Tạo thông báo cho chủ bài
    if ($owner_uid) {
        $ngay_str = !empty($ngay_thue) ? " (Ngày: $ngay_thue)" : '';
        $title   = "Mái Nhà Xanh: Có yêu cầu đặt phòng!";
        $content = "$ho_ten (SĐT: $sdt) muốn đặt phòng \"$ten_phong\"$ngay_str.";
        
        require_once __DIR__ . '/../includes/one_signal_helper.php';
        sendNotification([
            'type'    => 'booking',
            'target'  => 'user',
            'user_id' => $owner_uid,
            'title'   => $title,
            'content' => $content,
            'link'    => 'bai-dang-cua-toi.php'
        ]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Đặt phòng thành công! Phòng đã chuyển sang trạng thái đã đặt cọc.',
        'room_status' => 'da_coc'
    ]);

} catch (RuntimeException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $resp = ['success' => false, 'message' => $e->getMessage()];
    if (!empty($currentRoomStatus)) {
        $resp['room_status'] = $currentRoomStatus;
    }
    echo json_encode($resp);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>
