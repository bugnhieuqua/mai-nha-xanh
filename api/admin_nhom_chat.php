<?php
/**
 * Admin API — Quản lý Nhóm Chat
 * GET: Lấy danh sách nhóm (pagination + filter)
 * POST actions: lock_group, unlock_group, delete_group, get_detail
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin('admin');

// Validate CSRF for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
}

$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Không thể kết nối CSDL.']);
    exit;
}

// ═══ GET: Danh sách nhóm ═══
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 15;
    $offset = ($page - 1) * $limit;
    $keyword = trim($_GET['keyword'] ?? '');
    $status = trim($_GET['status'] ?? ''); // 'active', 'locked', or ''

    $where = "WHERE c.is_group = 1";
    $params = [];

    if (!empty($keyword)) {
        $where .= " AND c.group_name LIKE :kw";
        $params[':kw'] = '%' . $keyword . '%';
    }
    if ($status === 'active') {
        $where .= " AND c.is_locked = 0";
    } elseif ($status === 'locked') {
        $where .= " AND c.is_locked = 1";
    }

    // Tổng số
    $countSql = "SELECT COUNT(*) FROM conversations c $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = intval($countStmt->fetchColumn());

    // Dữ liệu
    $dataSql = "
        SELECT 
            c.id,
            c.group_name,
            c.group_owner_id,
            c.is_locked,
            c.locked_reason,
            c.locked_at,
            c.locked_by,
            c.created_at,
            (SELECT COUNT(*) FROM conversation_members cm WHERE cm.conversation_id = c.id) as member_count,
            (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id) as last_activity,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) as total_messages,
            u.hoten as owner_name,
            u.username as owner_username
        FROM conversations c
        LEFT JOIN users u ON u.id = c.group_owner_id
        $where
        ORDER BY c.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($params);
    $groups = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hóa
    foreach ($groups as &$g) {
        $g['owner_name'] = $g['owner_name'] ?: $g['owner_username'] ?: 'Không xác định';
    }
    unset($g);

    // Stats tổng
    $statsActive = $pdo->query("SELECT COUNT(*) FROM conversations WHERE is_group = 1 AND is_locked = 0")->fetchColumn();
    $statsLocked = $pdo->query("SELECT COUNT(*) FROM conversations WHERE is_group = 1 AND is_locked = 1")->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $groups,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit),
        'stats' => [
            'total' => intval($statsActive) + intval($statsLocked),
            'active' => intval($statsActive),
            'locked' => intval($statsLocked)
        ]
    ]);
    exit;
}

// ═══ POST: Các hành động ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input && !empty($_POST)) $input = $_POST;
    
    $action = $input['action'] ?? '';

    switch ($action) {

        // ── Khoá nhóm ──
        case 'lock_group':
            $convId = intval($input['conversation_id'] ?? 0);
            $reason = trim($input['reason'] ?? 'Vi phạm điều khoản sử dụng');
            
            if ($convId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID nhóm không hợp lệ.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE conversations SET is_locked = 1, locked_reason = :reason, locked_at = NOW(), locked_by = 'admin' WHERE id = :id AND is_group = 1");
            $stmt->execute([':reason' => $reason, ':id' => $convId]);

            if ($stmt->rowCount() > 0) {
                // Ghi log
                try {
                    $logStmt = $pdo->prepare("INSERT INTO moderation_logs (conversation_id, ai_provider, is_violation, severity, matched_rule, action_taken, processed_by) VALUES (:conv, NULL, 1, 'high', 'admin_manual', 'lock', 'admin')");
                    $logStmt->execute([':conv' => $convId]);
                } catch (Exception $e) {}

                echo json_encode(['success' => true, 'message' => 'Đã khoá nhóm thành công.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy nhóm hoặc nhóm đã bị khoá.']);
            }
            break;

        // ── Mở khoá nhóm ──
        case 'unlock_group':
            $convId = intval($input['conversation_id'] ?? 0);
            if ($convId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID nhóm không hợp lệ.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE conversations SET is_locked = 0, locked_reason = NULL, locked_at = NULL, locked_by = NULL WHERE id = :id AND is_group = 1");
            $stmt->execute([':id' => $convId]);

            echo json_encode(['success' => true, 'message' => 'Đã mở khoá nhóm.']);
            break;

        // ── Xoá nhóm ──
        case 'delete_group':
            $convId = intval($input['conversation_id'] ?? 0);
            if ($convId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID nhóm không hợp lệ.']);
                exit;
            }

            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM messages WHERE conversation_id = :conv")->execute([':conv' => $convId]);
                $pdo->prepare("DELETE FROM conversation_members WHERE conversation_id = :conv")->execute([':conv' => $convId]);
                $pdo->prepare("DELETE FROM conversations WHERE id = :conv AND is_group = 1")->execute([':conv' => $convId]);
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Đã xoá nhóm và toàn bộ tin nhắn.']);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Lỗi xoá nhóm: ' . $e->getMessage()]);
            }
            break;

        // ── Chi tiết nhóm ──
        case 'get_detail':
            $convId = intval($input['conversation_id'] ?? 0);
            if ($convId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID nhóm không hợp lệ.']);
                exit;
            }

            // Thông tin nhóm
            $gStmt = $pdo->prepare("SELECT c.*, u.hoten as owner_name FROM conversations c LEFT JOIN users u ON u.id = c.group_owner_id WHERE c.id = :id AND c.is_group = 1");
            $gStmt->execute([':id' => $convId]);
            $group = $gStmt->fetch(PDO::FETCH_ASSOC);
            if (!$group) {
                echo json_encode(['success' => false, 'message' => 'Nhóm không tồn tại.']);
                exit;
            }

            // Thành viên
            $mStmt = $pdo->prepare("SELECT u.id, u.hoten, u.username, u.avatar, cm.role, cm.joined_at FROM conversation_members cm JOIN users u ON u.id = cm.user_id WHERE cm.conversation_id = :conv ORDER BY FIELD(cm.role, 'owner', 'admin', 'member')");
            $mStmt->execute([':conv' => $convId]);
            $members = $mStmt->fetchAll(PDO::FETCH_ASSOC);

            // 20 tin nhắn gần nhất
            $msgStmt = $pdo->prepare("SELECT m.*, u.hoten as sender_name FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = :conv ORDER BY m.created_at DESC LIMIT 20");
            $msgStmt->execute([':conv' => $convId]);
            $messages = array_reverse($msgStmt->fetchAll(PDO::FETCH_ASSOC));

            // Logs kiểm duyệt
            $logStmt = $pdo->prepare("SELECT * FROM moderation_logs WHERE conversation_id = :conv ORDER BY created_at DESC LIMIT 10");
            try {
                $logStmt->execute([':conv' => $convId]);
                $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $logs = [];
            }

            echo json_encode([
                'success' => true,
                'group' => $group,
                'members' => $members,
                'messages' => $messages,
                'moderation_logs' => $logs
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
    }
}
?>
