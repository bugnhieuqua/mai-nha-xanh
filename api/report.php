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
        reported_conversation_id INT DEFAULT NULL,
        reason TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

// Auto-migration check column
try {
    $db->exec("ALTER TABLE reports ADD COLUMN reported_conversation_id INT DEFAULT NULL");
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
        $reported_user_id = null;
        if ($nguon === 'dangbai') {
            $stmt = $db->prepare("SELECT u.id FROM dangbai_chothuetro d JOIN users u ON d.nguoidang COLLATE utf8mb4_unicode_ci = u.username WHERE d.id = :id");
            $stmt->execute([':id' => $post_id]);
            $reported_user_id = $stmt->fetchColumn() ?: null;
        } else if ($nguon === 'phongtro') {
            try {
                $stmt = $db->prepare("SELECT user_id FROM phongtro WHERE id = :id");
                $stmt->execute([':id' => $post_id]);
                $reported_user_id = $stmt->fetchColumn() ?: null;
            } catch (Exception $e) {
                $reported_user_id = null;
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
} elseif ($action === 'report_group') {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($reason) || $conversation_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    try {
        $sql = "INSERT INTO reports (reporter_id, reported_conversation_id, reason, status) 
                VALUES (:reporter, :conv_id, :reason, 'pending')";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':reporter' => $_SESSION['user_id'],
            ':conv_id' => $conversation_id,
            ':reason' => $reason
        ]);
        $reportId = $db->lastInsertId();

        // Lấy 15 tin nhắn gần nhất
        $msgStmt = $db->prepare("SELECT content FROM messages WHERE conversation_id = :conv ORDER BY created_at DESC LIMIT 15");
        $msgStmt->execute([':conv' => $conversation_id]);
        $msgs = array_reverse($msgStmt->fetchAll(PDO::FETCH_COLUMN));
        $combinedContent = implode("\n", $msgs);

        // Gọi AI Moderation Engine
        $apiBase = $_ENV['PHP_API_BASE'] ?? $_ENV['APP_URL'] ?? 'http://localhost:8000';
        if (substr($apiBase, -1) === '/') {
            $apiBase = substr($apiBase, 0, -1);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiBase . '/api/ai_moderation.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'conversation_id' => $conversation_id,
                'content' => $combinedContent ?: 'Không có tin nhắn',
                'report_id' => $reportId,
                'report_content' => $reason
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 8
        ]);
        curl_exec($ch);
        curl_close($ch);

        echo json_encode(['success' => true, 'message' => 'Báo cáo nhóm thành công. AI đang tiến hành phân tích điều khoản và kiểm duyệt nhóm.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi báo cáo nhóm: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
}
?>
