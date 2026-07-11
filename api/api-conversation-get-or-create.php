<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

// Kiểm tra đăng nhập
if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn cần đăng nhập để truy cập cuộc hội thoại.']);
    exit;
}

$currentUserId = intval($_SESSION['user_id']);
$partnerId     = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
$conversationId = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;

if ($partnerId === 0 && $conversationId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Đối tác hoặc phòng chat không hợp lệ.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    $isGroup = 0;
    $groupInfo = null;

    if ($conversationId > 0) {
        // 1. Kiểm tra xem user có là thành viên của conversation_id này không
        $checkMemberSql = "SELECT role FROM conversation_members WHERE conversation_id = :conv_id AND user_id = :user_id";
        $checkStmt = $pdo->prepare($checkMemberSql);
        $checkStmt->execute([':conv_id' => $conversationId, ':user_id' => $currentUserId]);
        $myRole = $checkStmt->fetchColumn();

        if (!$myRole) {
            echo json_encode(['status' => 'error', 'message' => 'Bạn không phải là thành viên của cuộc trò chuyện này.']);
            exit;
        }

        // Lấy thông tin conversation
        $convInfoSql = "SELECT id, is_group, group_name, group_avatar, is_locked, locked_reason FROM conversations WHERE id = :conv_id";
        $convInfoStmt = $pdo->prepare($convInfoSql);
        $convInfoStmt->execute([':conv_id' => $conversationId]);
        $groupInfo = $convInfoStmt->fetch(PDO::FETCH_ASSOC);
        if ($groupInfo) {
            $isGroup = intval($groupInfo['is_group']);
        }
    } else {
        // 2. Tìm cuộc hội thoại 1-1 đã tồn tại giữa 2 user
        $findSql = "
            SELECT cm1.conversation_id 
            FROM conversation_members cm1
            JOIN conversation_members cm2 ON cm1.conversation_id = cm2.conversation_id
            JOIN conversations c ON c.id = cm1.conversation_id
            WHERE cm1.user_id = :user_a AND cm2.user_id = :user_b AND c.is_group = 0
            LIMIT 1
        ";
        
        $findStmt = $pdo->prepare($findSql);
        $findStmt->execute([
            ':user_a' => $currentUserId,
            ':user_b' => $partnerId
        ]);
        
        $conv = $findStmt->fetch(PDO::FETCH_ASSOC);

        if ($conv) {
            $conversationId = intval($conv['conversation_id']);
        } else {
            // 3. Nếu chưa tồn tại, tạo mới cuộc hội thoại 1-1
            $pdo->beginTransaction();
            
            $insertConv = "INSERT INTO conversations (is_group) VALUES (0)";
            $pdo->exec($insertConv);
            $conversationId = intval($pdo->lastInsertId());

            $insertMember = "INSERT INTO conversation_members (conversation_id, user_id) VALUES (:conv_id, :user_id)";
            $memberStmt = $pdo->prepare($insertMember);
            
            // Thêm bản thân
            $memberStmt->execute([
                ':conv_id' => $conversationId,
                ':user_id' => $currentUserId
            ]);
            
            // Thêm đối tác
            $memberStmt->execute([
                ':conv_id' => $conversationId,
                ':user_id' => $partnerId
            ]);
            
            $pdo->commit();
        }

        // Lấy thông tin 1-1 conversation (mặc định không khoá)
        $groupInfo = [
            'id' => $conversationId,
            'is_group' => 0,
            'group_name' => null,
            'group_avatar' => null,
            'is_locked' => 0,
            'locked_reason' => null
        ];
    }

    // Lấy biệt danh, vai trò và trạng thái xóa (deleted_at) của chính mình trong cuộc hội thoại
    $myNickname = '';
    $myRole = 'member';
    $myDeletedAt = null;
    $myMemberStmt = $pdo->prepare("SELECT nickname, role, deleted_at FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
    $myMemberStmt->execute([':conv' => $conversationId, ':uid' => $currentUserId]);
    $memberRow = $myMemberStmt->fetch(PDO::FETCH_ASSOC);
    if ($memberRow) {
        $myNickname = $memberRow['nickname'] ?: '';
        $myRole = $memberRow['role'] ?: 'member';
        $myDeletedAt = $memberRow['deleted_at'] ?: null;
    }

    // Lấy danh sách tin nhắn đang ghim
    $pinnedSql = "
        SELECT m.id, m.sender_id, m.content, m.type, m.created_at, 
               COALESCE(cm.nickname, u.hoten, u.username) as sender_name
        FROM messages m
        LEFT JOIN users u ON u.id = m.sender_id
        LEFT JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_id = m.sender_id
        WHERE m.conversation_id = :conv_id AND m.is_pinned = 1
    ";
    if ($myDeletedAt !== null) {
        $pinnedSql .= " AND m.created_at > :deleted_at ";
    }
    $pinnedSql .= " ORDER BY m.pinned_at DESC ";
    
    $pinnedStmt = $pdo->prepare($pinnedSql);
    $pinnedParams = [':conv_id' => $conversationId];
    if ($myDeletedAt !== null) {
        $pinnedParams[':deleted_at'] = $myDeletedAt;
    }
    $pinnedStmt->execute($pinnedParams);
    $pinnedMessages = $pinnedStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Lấy lịch sử tin nhắn của cuộc hội thoại này (kèm theo tên người gửi để hiển thị tin nhắn nhóm)
    $msgSql = "
        SELECT m.id, m.sender_id, m.content, m.type, m.created_at, m.is_pinned,
               COALESCE(cm.nickname, u.hoten, u.username) as sender_name
        FROM messages m 
        LEFT JOIN users u ON u.id = m.sender_id
        LEFT JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_id = m.sender_id
        WHERE m.conversation_id = :conv_id 
    ";
    if ($myDeletedAt !== null) {
        $msgSql .= " AND m.created_at > :deleted_at ";
    }
    $msgSql .= " ORDER BY m.created_at ASC ";

    $msgStmt = $pdo->prepare($msgSql);
    $msgParams = [':conv_id' => $conversationId];
    if ($myDeletedAt !== null) {
        $msgParams[':deleted_at'] = $myDeletedAt;
    }
    $msgStmt->execute($msgParams);
    $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Nếu là nhóm, lấy danh sách thành viên
    $members = [];
    if ($isGroup === 1) {
        $memSql = "
            SELECT u.id, u.username, u.hoten, u.avatar, cm.role, cm.nickname 
            FROM conversation_members cm
            JOIN users u ON u.id = cm.user_id
            WHERE cm.conversation_id = :conv_id
            ORDER BY cm.role DESC, u.hoten ASC
        ";
        $memStmt = $pdo->prepare($memSql);
        $memStmt->execute([':conv_id' => $conversationId]);
        
        while ($row = $memStmt->fetch(PDO::FETCH_ASSOC)) {
            // Fix avatar
            $av = $row['avatar'];
            if ($av && !str_starts_with($av, 'http') && !str_starts_with($av, 'data:')) {
                $av = './' . $av;
            }
            $row['avatar'] = $av;
            $members[] = $row;
        }
    }

    echo json_encode([
        'status' => 'success',
        'conversation_id' => $conversationId,
        'is_group' => $isGroup,
        'group_info' => $groupInfo,
        'my_nickname' => $myNickname,
        'my_role' => $myRole,
        'pinned_messages' => $pinnedMessages,
        'messages' => $messages,
        'members' => $members
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối hoặc xử lý dữ liệu: ' . $e->getMessage()]);
}
?>
