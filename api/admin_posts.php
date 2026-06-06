<?php
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/one_signal_helper.php';

requireLogin('admin');
validateCsrfToken(); // Bảo mật chống tấn công CSRF

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

$action = trim($_POST['action'] ?? '');

// ── Bulk approve ─────────────────────────────────────────────────
if ($action === 'approve_many') {
    $raw = $_POST['ids'] ?? [];
    if (!is_array($raw) || empty($raw)) {
        echo json_encode(['success' => false, 'message' => 'Không có ID nào được chọn']);
        exit;
    }
    $ids = array_filter(array_map('intval', $raw), fn($id) => $id > 0);
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }
    try {
        $database = new Database();
        $db = $database->getConnection();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            UPDATE dangbai_chothuetro 
            SET trangthai = 'da_duyet',
                trangthai_phong = CASE
                    WHEN trangthai_phong IS NULL OR trangthai_phong = '' THEN 'con_phong'
                    ELSE trangthai_phong
                END,
                admin_note = '',
                duyet_luc = NOW()
            WHERE id IN ($placeholders)
        ");
        $stmt->execute(array_values($ids));
        $affected = $stmt->rowCount();

        // Gửi thông báo OneSignal cho từng bài vừa duyệt (silent fail)
        try {
            require_once __DIR__ . '/../includes/one_signal_helper.php';
            $stmtInfo = $db->prepare("SELECT id, tieude, diachi FROM dangbai_chothuetro WHERE id IN ($placeholders)");
            $stmtInfo->execute(array_values($ids));
            foreach ($stmtInfo->fetchAll(PDO::FETCH_ASSOC) as $post) {
                try {
                    sendNotification([
                        'type'    => 'post_approved',
                        'target'  => 'all',
                        'title'   => 'Mái Nhà Xanh: Có phòng mới!',
                        'content' => 'Phòng trọ mới tại ' . $post['diachi'] . ': "' . $post['tieude'] . '"',
                        'link'    => getBaseUrl() . '/phong-tro.php?id=' . $post['id']
                    ]);
                } catch (Exception $e) {}
            }
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Đã duyệt ' . $affected . ' bài đăng']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit;
}

// ── Bulk delete ──────────────────────────────────────────────────
if ($action === 'delete_many') {
    $raw = $_POST['ids'] ?? [];
    if (!is_array($raw) || empty($raw)) {
        echo json_encode(['success' => false, 'message' => 'Không có ID nào được chọn']);
        exit;
    }
    $ids = array_filter(array_map('intval', $raw), fn($id) => $id > 0);
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }
    try {
        $database = new Database();
        $db = $database->getConnection();

        $extractImagePaths = function(array $row): array {
            $paths = [];
            if (!empty($row['hinhanh'])) $paths[] = trim((string)$row['hinhanh']);
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

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // Fetch files to delete
        $stmtFiles = $db->prepare("SELECT hinhanh, hinhanh_list, video FROM dangbai_chothuetro WHERE id IN ($placeholders)");
        $stmtFiles->execute(array_values($ids));
        foreach ($stmtFiles->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach ($extractImagePaths($row) as $imgPath) {
                if ($imgPath && file_exists('../' . $imgPath)) unlink('../' . $imgPath);
            }
            if (!empty($row['video']) && file_exists('../' . $row['video'])) unlink('../' . $row['video']);
        }
        // Delete reports
        try { $db->prepare("DELETE FROM reports WHERE reported_post_id IN ($placeholders)")->execute(array_values($ids)); } catch (Exception $e) {}
        // Delete posts
        $stmt2 = $db->prepare("DELETE FROM dangbai_chothuetro WHERE id IN ($placeholders)");
        $stmt2->execute(array_values($ids));
        echo json_encode(['success' => true, 'message' => 'Đã xoá ' . $stmt2->rowCount() . ' bài đăng']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit;
}

// ── Run AI check action ───────────────────────────────────────────
if ($action === 'run_ai_check') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        require_once __DIR__ . '/../includes/ai_moderation_helper.php';
        $res = analyzePostWithAI($db, $id);
        
        if ($res['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Phân tích AI thành công!',
                'ai_check' => $res['data']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi phân tích AI: ' . $res['message']
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }
    exit;
}

// ── Single post action ────────────────────────────────────────────
$id   = intval($_POST['id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if (!$id || !in_array($action, ['approve', 'reject', 'delete'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $extractImagePaths = function(array $row): array {
        $paths = [];
        if (!empty($row['hinhanh'])) $paths[] = trim((string)$row['hinhanh']);
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

    if ($action === 'delete') {
        // Xoá ảnh và video nếu có
        $stmtImg = $db->prepare("SELECT hinhanh, hinhanh_list, video FROM dangbai_chothuetro WHERE id = :id");
        $stmtImg->execute([':id' => $id]);
        $row = $stmtImg->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach ($extractImagePaths($row) as $imgPath) {
                if ($imgPath && file_exists('../' . $imgPath)) unlink('../' . $imgPath);
            }
            if (!empty($row['video']) && file_exists('../' . $row['video'])) unlink('../' . $row['video']);
        }
        
        $stmt = $db->prepare("DELETE FROM dangbai_chothuetro WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Dọn sạch các báo cáo vi phạm liên quan đến bài đăng này
        try {
            $db->prepare("DELETE FROM reports WHERE reported_post_id = :pid")->execute([':pid' => $id]);
        } catch (Exception $e) {}
        
        echo json_encode(['success' => true, 'message' => 'Đã xoá bài đăng và dọn sạch báo cáo liên quan!']);
        exit;
    }

    $trangthai = $action === 'approve' ? 'da_duyet' : 'tu_choi';

    $stmt = $db->prepare("
        UPDATE dangbai_chothuetro 
        SET trangthai = :trangthai,
            trangthai_phong = CASE
                WHEN trangthai_phong IS NULL OR trangthai_phong = '' THEN 'con_phong'
                ELSE trangthai_phong
            END,
            admin_note = :note,
            duyet_luc = NOW()
        WHERE id = :id
    ");
    $stmt->bindParam(':trangthai', $trangthai);
    $stmt->bindParam(':note',      $note);
    $stmt->bindParam(':id',        $id, PDO::PARAM_INT);
    $stmt->execute();

    $msg = $action === 'approve' ? 'Bài đăng đã được duyệt!' : 'Bài đăng đã bị từ chối!';
    
    // --- Gửi thông báo Web Push qua OneSignal ---
    try {
        require_once __DIR__ . '/../includes/one_signal_helper.php';
        
        // Lấy thông tin bài và ID người đăng (để gửi thông báo cá nhân)
        $stmtInfo = $db->prepare("
            SELECT d.tieude, d.diachi, d.hinhanh, u.id as owner_id 
            FROM dangbai_chothuetro d
            LEFT JOIN users u ON d.nguoidang = u.username
            WHERE d.id = :id
        ");
        $stmtInfo->execute([':id' => $id]);
        $post = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        
        if ($post) {
            $ownerId = $post['owner_id'];
            $image   = $post['hinhanh'];
            
            if ($action === 'approve') {
                // 1. Thông báo cho CHỦ BÀI (Cá nhân)
                if ($ownerId) {
                    sendNotification([
                        'type'    => 'post_result',
                        'target'  => 'user',
                        'user_id' => $ownerId,
                        'title'   => 'Tin vui: Bài đăng đã được duyệt!',
                        'content' => 'Bài viết "' . $post['tieude'] . '" của bạn đã hiển thị trên hệ thống.',
                        'link'    => 'phong-tro.php?id=' . $id,
                        'image'   => $image
                    ]);
                }
                
                // 2. Thông báo cho TẤT CẢ (Broadcast)
                sendNotification([
                    'type'    => 'post_approved',
                    'target'  => 'all',
                    'title'   => 'Mái Nhà Xanh: Có phòng mới!',
                    'content' => 'Phòng trọ mới tại ' . $post['diachi'] . ': "' . $post['tieude'] . '"',
                    'link'    => 'phong-tro.php?id=' . $id,
                    'image'   => $image
                ]);
            } else {
                // 3. Thông báo cho CHỦ BÀI khi bị TỪ CHỐI (Cá nhân)
                if ($ownerId) {
                    $reason = !empty($note) ? " Lý do: $note" : "";
                    sendNotification([
                        'type'    => 'post_result',
                        'target'  => 'user',
                        'user_id' => $ownerId,
                        'title'   => 'Thông báo: Bài đăng bị từ chối',
                        'content' => 'Rất tiếc, bài viết "' . $post['tieude'] . '" không được duyệt.' . $reason,
                        'link'    => 'bai-dang-cua-toi.php',
                        'image'   => $image
                    ]);
                }
            }
        }
    } catch (Exception $e) {
        // Lỗi gửi thông báo không làm dừng quy trình chính
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
