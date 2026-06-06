<?php
// API to delete chatbot sessions (all messages with that session_id)
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}
validateCsrfToken();

$action = trim($_POST['action'] ?? '');

// ── Single session delete (existing) ────────────────────────────
if ($action === 'delete_session') {
    $session_id = trim($_POST['v'] ?? '');
    if ($session_id) $session_id = base64_decode($session_id);
    if (empty($session_id)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }
    try {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("DELETE FROM chatbot_history WHERE session_id = :sid");
        $stmt->bindParam(':sid', $session_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã xoá ' . $stmt->rowCount() . ' tin nhắn của session này.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Bulk session delete ──────────────────────────────────────────
if ($action === 'delete_sessions') {
    $raw = $_POST['sessions'] ?? [];
    if (!is_array($raw) || empty($raw)) {
        echo json_encode(['success' => false, 'message' => 'Không có session nào được chọn']);
        exit;
    }
    // Decode each session id (they are base64-encoded)
    $sessions = [];
    foreach ($raw as $v) {
        $decoded = base64_decode($v);
        if ($decoded) $sessions[] = $decoded;
    }
    if (empty($sessions)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }
    try {
        $database = new Database();
        $db = $database->getConnection();
        $placeholders = implode(',', array_fill(0, count($sessions), '?'));
        $stmt = $db->prepare("DELETE FROM chatbot_history WHERE session_id IN ($placeholders)");
        $stmt->execute($sessions);
        echo json_encode(['success' => true, 'message' => 'Đã xoá ' . $stmt->rowCount() . ' tin nhắn của ' . count($sessions) . ' sessions.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
?>
