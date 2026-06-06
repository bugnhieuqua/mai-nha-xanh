<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để báo cáo']);
    exit();
}

// Bảo mật CSRF
validateCsrfToken();

$database = new Database();
$db = $database->getConnection();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        reported_user_id INT DEFAULT NULL,
        reported_post_id INT DEFAULT NULL,
        reason TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'report_post') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $nguon = $_POST['nguon'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($reason) || $post_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    try {
        // Find numeric user ID of the reported post
        $reported_user_id = null;
        if ($nguon === 'dangbai') {
            // Find user id by joining with users table since dangbai stores username in 'nguoidang'
            // Added COLLATE to avoid collation mismatch errors
            $stmt = $db->prepare("SELECT u.id FROM dangbai_chothuetro d JOIN users u ON d.nguoidang COLLATE utf8mb4_unicode_ci = u.username WHERE d.id = :id");
            $stmt->execute([':id' => $post_id]);
            $reported_user_id = $stmt->fetchColumn() ?: null;
        } else if ($nguon === 'phongtro') {
            // Check if user_id column exists in phongtro to avoid errors
            try {
                $stmt = $db->prepare("SELECT user_id FROM phongtro WHERE id = :id");
                $stmt->execute([':id' => $post_id]);
                $reported_user_id = $stmt->fetchColumn() ?: null;
            } catch (Exception $e) {
                $reported_user_id = null; // Column doesn't exist or other error
            }
        }
        
        $sql = "INSERT INTO reports (reporter_id, reported_user_id, reported_post_id, reason, status) 
                VALUES (:reporter, :reported_user, :post_id, :reason, 'pending')";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':reporter' => $_SESSION['user_id'],
            ':reported_user' => $reported_user_id,
            ':post_id' => $post_id,
            ':reason' => $reason . ($nguon === 'dangbai' ? '' : ' (Nguồn: admin/phongtro)')
        ]);

        echo json_encode(['success' => true, 'message' => 'Đã gửi báo cáo thành công']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
}
?>
