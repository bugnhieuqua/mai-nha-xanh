<?php
// API to delete support sessions (lienhe records + chatbot_history)
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/session.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

// Bảo mật CSRF
validateCsrfToken();

$action = trim($_POST['action'] ?? '');

// Delete one or many sessions (session_ids[])
if ($action === 'delete_sessions') {
    $raw = $_POST['session_ids'] ?? [];
    if (!is_array($raw) || empty($raw)) {
        echo json_encode(['success' => false, 'message' => 'Không có session nào được chọn']);
        exit;
    }
    $sessions = array_filter(array_map('strval', $raw));
    if (empty($sessions)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        $placeholders = implode(',', array_fill(0, count($sessions), '?'));
        $deleted_msgs = 0;
        $deleted_lienhe = 0;

        // Delete chatbot_history for these sessions
        $stmt = $db->prepare("DELETE FROM chatbot_history WHERE session_id IN ($placeholders)");
        $stmt->execute($sessions);
        $deleted_msgs = $stmt->rowCount();

        // Delete lienhe entries linked to these sessions
        // lienhe rows can be linked via session_id or by lienhe_{id} pattern
        foreach ($sessions as $sid) {
            if (preg_match('/^lienhe_(\d+)$/', $sid, $m)) {
                $lhStmt = $db->prepare("DELETE FROM lienhe WHERE id = ?");
                $lhStmt->execute([(int)$m[1]]);
                $deleted_lienhe += $lhStmt->rowCount();
            }
        }
        // Also delete lienhe rows where session_id matches
        $stmt2 = $db->prepare("DELETE FROM lienhe WHERE session_id IN ($placeholders)");
        $stmt2->execute($sessions);
        $deleted_lienhe += $stmt2->rowCount();

        echo json_encode([
            'success' => true,
            'message' => "Đã xoá $deleted_msgs tin nhắn và $deleted_lienhe liên hệ"
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
?>
