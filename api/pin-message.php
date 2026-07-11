<?php
/**
 * API ghim / bỏ ghim tin nhắn — hỗ trợ cả chat 1-1 và nhóm.
 * Chat 1-1 : cả hai người đều được ghim/bỏ ghim.
 * Nhóm     : chỉ owner / admin.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = $input['action']     ?? ($_POST['action']     ?? '');
$messageId = intval($input['message_id'] ?? ($_POST['message_id'] ?? 0));
$currentUserId = intval($_SESSION['user_id']);

if (!in_array($action, ['pin', 'unpin']) || $messageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$pdo = (new Database())->getConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL.']);
    exit;
}

// Lấy thông tin tin nhắn
$msgStmt = $pdo->prepare("SELECT conversation_id, content FROM messages WHERE id = :id");
$msgStmt->execute([':id' => $messageId]);
$msgInfo = $msgStmt->fetch(PDO::FETCH_ASSOC);
if (!$msgInfo) {
    echo json_encode(['success' => false, 'message' => 'Tin nhắn không tồn tại.']);
    exit;
}
$convId = intval($msgInfo['conversation_id']);

// Kiểm tra loại hội thoại
$convStmt = $pdo->prepare("SELECT is_group FROM conversations WHERE id = :id");
$convStmt->execute([':id' => $convId]);
$convInfo = $convStmt->fetch(PDO::FETCH_ASSOC);
if (!$convInfo) {
    echo json_encode(['success' => false, 'message' => 'Hội thoại không tồn tại.']);
    exit;
}

$isGroup = (bool)$convInfo['is_group'];

// Kiểm tra: chỉ cần là thành viên của hội thoại (không phân biệt role, group hay 1-1)
$memberStmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
$memberStmt->execute([':conv' => $convId, ':uid' => $currentUserId]);
if (!$memberStmt->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn không phải thành viên của hội thoại này.']);
    exit;
}

// Tên người dùng để ghi log
$actorStmt = $pdo->prepare("SELECT hoten, username FROM users WHERE id = :uid");
$actorStmt->execute([':uid' => $currentUserId]);
$aRow = $actorStmt->fetch(PDO::FETCH_ASSOC);
$actorName = !empty($aRow['hoten']) ? $aRow['hoten'] : $aRow['username'];

if ($action === 'pin') {
    $pdo->prepare("UPDATE messages SET is_pinned = 1, pinned_by = :uid, pinned_at = NOW() WHERE id = :id")
        ->execute([':uid' => $currentUserId, ':id' => $messageId]);

    $snippet = mb_strimwidth($msgInfo['content'], 0, 30, '...');
    $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content, type) VALUES (:conv, :uid, :content, 'system')")
        ->execute([
            ':conv'    => $convId,
            ':uid'     => $currentUserId,
            ':content' => "📌 {$actorName} đã ghim tin nhắn: \"{$snippet}\""
        ]);

    echo json_encode(['success' => true, 'message' => 'Đã ghim tin nhắn.']);
} else {
    $pdo->prepare("UPDATE messages SET is_pinned = 0 WHERE id = :id")
        ->execute([':id' => $messageId]);

    $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content, type) VALUES (:conv, :uid, :content, 'system')")
        ->execute([
            ':conv'    => $convId,
            ':uid'     => $currentUserId,
            ':content' => "📌 {$actorName} đã gỡ một tin nhắn ghim."
        ]);

    echo json_encode(['success' => true, 'message' => 'Đã gỡ ghim tin nhắn.']);
}
