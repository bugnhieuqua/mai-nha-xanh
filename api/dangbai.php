<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/room_status_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để đăng bài']);
    exit;
}

// Bảo mật CSRF
validateCsrfToken();

// Lấy dữ liệu từ form
$tieude = trim($_POST['tieude'] ?? '');
$mota = trim($_POST['mota'] ?? '');
$gia = trim($_POST['gia'] ?? '');
$dientich = trim($_POST['dientich'] ?? '');
$diachi = trim($_POST['diachi'] ?? '');
$ten_chunha = trim($_POST['ten_chunha'] ?? '');
$sdt_chunha = trim($_POST['sdt_chunha'] ?? '');

// Xử lý tiện nghi (array -> string)
$tiennghi_arr = $_POST['tiennghi'] ?? [];
if (is_array($tiennghi_arr)) {
    $tiennghi = implode(', ', $tiennghi_arr);
} else {
    $tiennghi = trim($tiennghi_arr);
}

// Thêm tiện nghi khác nếu có
$tiennghi_khac = trim($_POST['tiennghi_khac'] ?? '');
if (!empty($tiennghi_khac)) {
    $tiennghi = !empty($tiennghi) ? $tiennghi . ', ' . $tiennghi_khac : $tiennghi_khac;
}

// Validate
$errors = [];

if (empty($tieude)) $errors[] = 'Vui lòng nhập tiêu đề';
if (empty($gia) || !is_numeric($gia)) $errors[] = 'Vui lòng nhập giá thuê hợp lệ';
if (empty($dientich) || !is_numeric($dientich)) $errors[] = 'Vui lòng nhập diện tích hợp lệ';
if (empty($diachi)) $errors[] = 'Vui lòng nhập địa chỉ';
if (empty($ten_chunha)) $errors[] = 'Vui lòng nhập tên chủ nhà';
if (empty($sdt_chunha)) $errors[] = 'Vui lòng nhập số điện thoại';
if (!preg_match('/^[0-9]{10,11}$/', $sdt_chunha)) $errors[] = 'Số điện thoại không hợp lệ (10-11 số)';

$imageField = $_FILES['hinhanh'] ?? null;
$imageNamesRaw = $imageField['name'] ?? [];
$imageTmpRaw = $imageField['tmp_name'] ?? [];
$imageTypesRaw = $imageField['type'] ?? [];
$imageSizesRaw = $imageField['size'] ?? [];
$imageErrorsRaw = $imageField['error'] ?? [];

$imageNames = is_array($imageNamesRaw) ? $imageNamesRaw : [$imageNamesRaw];
$imageTmps = is_array($imageTmpRaw) ? $imageTmpRaw : [$imageTmpRaw];
$imageTypes = is_array($imageTypesRaw) ? $imageTypesRaw : [$imageTypesRaw];
$imageSizes = is_array($imageSizesRaw) ? $imageSizesRaw : [$imageSizesRaw];
$imageErrors = is_array($imageErrorsRaw) ? $imageErrorsRaw : [$imageErrorsRaw];

$imageIndexes = [];
foreach ($imageNames as $idx => $name) {
    $name = trim((string)$name);
    $err = intval($imageErrors[$idx] ?? UPLOAD_ERR_NO_FILE);
    if ($name === '' || $err === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    if ($err !== UPLOAD_ERR_OK) {
        $errors[] = 'Có lỗi khi tải ảnh lên. Vui lòng thử lại.';
        break;
    }
    $imageIndexes[] = $idx;
}

if (count($imageIndexes) < 1) $errors[] = 'Bắt buộc tải lên ít nhất 1 ảnh';
if (count($imageIndexes) > 5) $errors[] = 'Tối đa 5 ảnh cho mỗi bài đăng';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Xử lý upload ảnh (bắt buộc 1-5 ảnh)
$uploadDir = '../uploads/rooms/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$uploadedImages = [];

foreach ($imageIndexes as $idx) {
    $tmp = $imageTmps[$idx] ?? '';
    $origName = $imageNames[$idx] ?? '';
    $size = intval($imageSizes[$idx] ?? 0);
    $clientType = strtolower((string)($imageTypes[$idx] ?? ''));
    $mime = strtolower((string)@mime_content_type($tmp));
    if ($mime === '') $mime = $clientType;

    if (!in_array($mime, $allowedImageTypes, true)) {
        foreach ($uploadedImages as $p) { if (file_exists('../' . $p)) @unlink('../' . $p); }
        echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)']);
        exit;
    }

    $ext = strtolower(pathinfo((string)$origName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };
    }

    $fileName = 'room_' . time() . '_' . uniqid('', true) . '_' . $idx . '.' . $ext;
    $filePath = $uploadDir . $fileName;
    if (!move_uploaded_file($tmp, $filePath)) {
        foreach ($uploadedImages as $p) { if (file_exists('../' . $p)) @unlink('../' . $p); }
        echo json_encode(['success' => false, 'message' => 'Lỗi khi upload ảnh phòng trọ']);
        exit;
    }
    $uploadedImages[] = 'uploads/rooms/' . $fileName;
}

$hinhanh = $uploadedImages[0] ?? '';
$hinhanh_list = json_encode($uploadedImages, JSON_UNESCAPED_SLASHES);

// Xử lý upload video (không bắt buộc, không giới hạn dung lượng)
$video = '';
if (isset($_FILES['video']) && intval($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (intval($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Có lỗi khi tải video lên']);
        exit;
    }

    $videoDir = '../uploads/videos/';
    if (!is_dir($videoDir)) {
        mkdir($videoDir, 0777, true);
    }

    $videoType = strtolower((string)($_FILES['video']['type'] ?? ''));
    $detectedVideoType = strtolower((string)@mime_content_type($_FILES['video']['tmp_name'] ?? ''));
    if ($detectedVideoType !== '') {
        $videoType = $detectedVideoType;
    }
    $allowedVideoTypes = [
        'video/mp4', 'video/webm', 'video/ogg',
        'video/quicktime', 'video/x-msvideo', 'video/mpeg'
    ];
    if (!in_array($videoType, $allowedVideoTypes, true)) {
        echo json_encode(['success' => false, 'message' => 'Định dạng video không hợp lệ (hỗ trợ MP4, WEBM, OGG, MOV, AVI, MPEG)']);
        exit;
    }

    $vExt = strtolower(pathinfo((string)($_FILES['video']['name'] ?? ''), PATHINFO_EXTENSION));
    if ($vExt === '') {
        $vExt = match ($videoType) {
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/mpeg' => 'mpeg',
            default => 'mp4'
        };
    }
    $vName = 'vid_' . time() . '_' . uniqid() . '.' . $vExt;
    $vPath = $videoDir . $vName;
    
    if (move_uploaded_file($_FILES['video']['tmp_name'], $vPath)) {
        $video = 'uploads/videos/' . $vName;
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi upload video']);
        exit;
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensureDangbaiRoomStatusSchema($db);

    // Tự động thêm các cột nếu chưa có
    try { $db->exec("ALTER TABLE dangbai_chothuetro ADD COLUMN video VARCHAR(255) DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE dangbai_chothuetro ADD COLUMN hinhanh_list TEXT NULL"); } catch (Exception $e) {}

    $query = "INSERT INTO dangbai_chothuetro (tieude, mota, hinhanh, hinhanh_list, video, gia, dientich, diachi, tiennghi, ten_chunha, sdt_chunha, nguoidang, trangthai, trangthai_phong)
              VALUES (:tieude, :mota, :hinhanh, :hinhanh_list, :video, :gia, :dientich, :diachi, :tiennghi, :ten_chunha, :sdt_chunha, :nguoidang, 'cho_duyet', 'con_phong')";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':tieude', $tieude);
    $stmt->bindParam(':mota', $mota);
    $stmt->bindParam(':hinhanh', $hinhanh);
    $stmt->bindParam(':hinhanh_list', $hinhanh_list);
    $stmt->bindParam(':video', $video);
    $stmt->bindParam(':gia', $gia);
    $stmt->bindParam(':dientich', $dientich);
    $stmt->bindParam(':diachi', $diachi);
    $stmt->bindParam(':tiennghi', $tiennghi);
    $stmt->bindParam(':ten_chunha', $ten_chunha);
    $stmt->bindParam(':sdt_chunha', $sdt_chunha);
    $nguoidang = $_SESSION['username'];
    $stmt->bindParam(':nguoidang', $nguoidang);

    if ($stmt->execute()) {
        $new_id = $db->lastInsertId();
        
        // Nạp các helper cần thiết
        require_once __DIR__ . '/../includes/ai_moderation_helper.php';
        require_once __DIR__ . '/../includes/one_signal_helper.php';
        
        $aiRes = null;
        try {
            // Chạy AI phân tích bài đăng và lưu vào DB
            $aiRes = analyzePostWithAI($db, $new_id);
        } catch (Exception $e) {
            $aiRes = ['success' => false, 'message' => $e->getMessage()];
        }
        
        if ($aiRes && $aiRes['success']) {
            $verdict = $aiRes['data']['verdict'] ?? 'WARNING';
            $reasons = $aiRes['data']['reasons'] ?? [];
            $reasonsStr = !empty($reasons) ? implode(', ', $reasons) : '';
            
            if ($verdict === 'SAFE') {
                // Tự động duyệt bài
                $upd = $db->prepare("UPDATE dangbai_chothuetro SET trangthai = 'da_duyet', trangthai_phong = 'con_phong', duyet_luc = NOW() WHERE id = :id");
                $upd->execute([':id' => $new_id]);
                
                // Broadcast thông báo tới tất cả người dùng
                try {
                    sendNotification([
                        'type'    => 'post_approved',
                        'target'  => 'all',
                        'title'   => 'Mái Nhà Xanh: Có phòng mới!',
                        'content' => 'Phòng trọ mới tại ' . $diachi . ': "' . $tieude . '"',
                        'link'    => getBaseUrl() . '/phong-tro.php?id=' . $new_id,
                        'image'   => $hinhanh
                    ]);
                } catch (Exception $e) {}
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Đăng bài thành công và đã được duyệt tự động bởi Trợ lý AI! Bài đăng của bạn đã hiển thị.'
                ]);
                exit;
            } elseif ($verdict === 'DANGER') {
                // Tự động từ chối bài đăng
                $upd = $db->prepare("UPDATE dangbai_chothuetro SET trangthai = 'tu_choi', admin_note = :note WHERE id = :id");
                $upd->execute([
                    ':note' => 'Tự động từ chối bởi AI. Lý do: ' . $reasonsStr,
                    ':id' => $new_id
                ]);
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Đăng bài thất bại! Trợ lý AI từ chối bài viết này do phát hiện rủi ro: ' . $reasonsStr
                ]);
                exit;
            } else {
                // Trường hợp WARNING (Cảnh báo)
                // Gửi thông báo cho Admin
                try {
                    sendNotification([
                        'type'    => 'new_post',
                        'target'  => 'admin',
                        'title'   => '🔔 Bài đăng mới chờ duyệt (Cảnh báo AI)',
                        'content' => 'AI cảnh báo rủi ro bài đăng "' . $tieude . '": ' . $reasonsStr,
                        'link'    => getBaseUrl() . '/admin/posts.php?id=' . $new_id
                    ]);
                } catch (Exception $e) {}
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Đăng bài thành công! Bài viết đang chờ Admin duyệt thủ công do Trợ lý AI phát hiện cảnh báo: ' . $reasonsStr
                ]);
                exit;
            }
        } else {
            // Trường hợp AI phân tích lỗi (Fallback)
            // Gửi thông báo cho Admin
            try {
                sendNotification([
                    'type'    => 'new_post',
                    'target'  => 'admin',
                    'title'   => '🔔 Bài đăng mới chờ duyệt',
                    'content' => 'Có bài đăng mới: "' . $tieude . '" từ người dung ' . $nguoidang,
                    'link'    => getBaseUrl() . '/admin/posts.php?id=' . $new_id
                ]);
            } catch (Exception $e) {}
            
            echo json_encode([
                'success' => true,
                'message' => 'Đăng bài thành công! Bài viết đang chờ duyệt.'
            ]);
            exit;
        }
    } else {

        echo json_encode([
            'success' => false,
            'message' => 'Có lỗi xảy ra khi lưu bài đăng'
        ]);
    }

} catch (PDOException $e) {
    foreach ($uploadedImages as $p) { if (file_exists('../' . $p)) @unlink('../' . $p); }
    if (!empty($video) && file_exists('../' . $video)) @unlink('../' . $video);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}
?>
