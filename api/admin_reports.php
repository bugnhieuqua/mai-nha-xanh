<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT NOT NULL,
            reported_user_id INT DEFAULT NULL,
            reported_post_id INT DEFAULT NULL,
            community_post_id INT DEFAULT NULL,
            community_comment_id INT DEFAULT NULL,
            reason TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    } catch (Exception $e) {}
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        try { $db->exec("UPDATE reports SET admin_seen = 1 WHERE admin_seen = 0"); } catch (Exception $e) {}
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT r.*, 
                       u1.username as reporter_name, 
                       u2.username as reported_username
                FROM reports r
                LEFT JOIN users u1 ON r.reporter_id = u1.id
                LEFT JOIN users u2 ON r.reported_user_id = u2.id
                ORDER BY r.id DESC 
                LIMIT :limit OFFSET :offset";
                
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSql = "SELECT COUNT(*) FROM reports";
        $total = $db->query($totalSql)->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'total' => (int)$total,
            'page' => $page,
            'pages' => ceil($total / $limit),
            'data' => $data
        ]);
        
    } elseif ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = in_array($_POST['status'], ['pending', 'reviewed', 'resolved']) ? $_POST['status'] : 'pending';
        
        $stmt = $db->prepare("UPDATE reports SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'delete_report') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM reports WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete_many') {
        $raw = $_POST['ids'] ?? [];
        if (!is_array($raw) || empty($raw)) {
            echo json_encode(['success' => false, 'message' => 'Không có ID nào được chọn']);
        } else {
            $ids = array_filter(array_map('intval', $raw), fn($id) => $id > 0);
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
            } else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $db->prepare("DELETE FROM reports WHERE id IN ($placeholders)");
                $stmt->execute(array_values($ids));
                echo json_encode(['success' => true, 'message' => 'Đã xoá ' . $stmt->rowCount() . ' báo cáo']);
            }
        }

        
    } elseif ($action === 'ban_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $report_id = (int)($_POST['report_id'] ?? 0);
        
        if ($user_id > 0) {
            $stmt = $db->prepare("UPDATE users SET status = 'banned' WHERE id = :uid");
            $stmt->execute([':uid' => $user_id]);
            // Also mark report as resolved
            if ($report_id > 0) {
                $db->prepare("UPDATE reports SET status = 'resolved' WHERE id = :rid")->execute([':rid' => $report_id]);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        }
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]);
}
?>
