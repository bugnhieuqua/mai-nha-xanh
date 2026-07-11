<?php
/**
 * API Nhóm Chat — Xử lý CRUD nhóm cho user
 * Actions: create_group, add_member, remove_member, leave_group, rename_group, get_groups, get_group_info
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.']);
    exit;
}

$currentUserId = intval($_SESSION['user_id']);

$database = new Database();
$pdo = $database->getConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Không thể kết nối CSDL.']);
    exit;
}

// ═══ Auto-migration ═══
try { $pdo->exec("ALTER TABLE conversations ADD COLUMN group_name VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversations ADD COLUMN group_avatar VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversations ADD COLUMN group_owner_id INT DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversations ADD COLUMN is_locked TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversations ADD COLUMN locked_reason TEXT DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversations ADD COLUMN locked_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversations ADD COLUMN locked_by VARCHAR(20) DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversation_members ADD COLUMN role ENUM('member','admin','owner') DEFAULT 'member'"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversation_members ADD COLUMN joined_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE conversation_members ADD COLUMN nickname VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE messages ADD COLUMN is_pinned TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE messages ADD COLUMN pinned_by INT DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE messages ADD COLUMN pinned_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}

// Xác định action
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && !empty($_POST)) $input = $_POST;
if (!$input && !empty($_GET)) $input = $_GET;

$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ═══ TẠO NHÓM MỚI ═══
    case 'create_group':
        $groupName = trim($input['name'] ?? '');
        $memberIds = $input['members'] ?? [];
        
        if (empty($groupName)) {
            echo json_encode(['success' => false, 'message' => 'Tên nhóm không được để trống.']);
            exit;
        }
        if (mb_strlen($groupName) > 100) {
            echo json_encode(['success' => false, 'message' => 'Tên nhóm tối đa 100 ký tự.']);
            exit;
        }
        if (!is_array($memberIds) || count($memberIds) < 1) {
            echo json_encode(['success' => false, 'message' => 'Nhóm cần ít nhất 2 thành viên (bao gồm bạn).']);
            exit;
        }
        if (count($memberIds) > 49) { // + owner = 50
            echo json_encode(['success' => false, 'message' => 'Nhóm tối đa 50 thành viên.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Tạo conversation nhóm
            $stmt = $pdo->prepare("INSERT INTO conversations (is_group, group_name, group_owner_id, created_at) VALUES (1, :name, :owner, NOW())");
            $stmt->execute([':name' => $groupName, ':owner' => $currentUserId]);
            $convId = intval($pdo->lastInsertId());

            // Thêm chủ nhóm
            $insertMember = $pdo->prepare("INSERT INTO conversation_members (conversation_id, user_id, role, joined_at) VALUES (:conv, :uid, :role, NOW())");
            $insertMember->execute([':conv' => $convId, ':uid' => $currentUserId, ':role' => 'owner']);

            // Thêm các thành viên
            foreach ($memberIds as $mid) {
                $mid = intval($mid);
                if ($mid > 0 && $mid !== $currentUserId) {
                    $insertMember->execute([':conv' => $convId, ':uid' => $mid, ':role' => 'member']);
                }
            }

            // Tin nhắn hệ thống
            $sysMsg = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content, type) VALUES (:conv, :uid, :content, 'system')");
            $sysMsg->execute([':conv' => $convId, ':uid' => $currentUserId, ':content' => '🎉 Nhóm "' . $groupName . '" đã được tạo!']);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Tạo nhóm thành công!',
                'conversation_id' => $convId,
                'group_name' => $groupName
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Lỗi tạo nhóm: ' . $e->getMessage()]);
        }
        break;

    // ═══ THÊM THÀNH VIÊN ═══
    case 'add_member':
        $convId = intval($input['conversation_id'] ?? 0);
        $newMemberIds = $input['member_ids'] ?? [];
        
        if ($convId <= 0 || empty($newMemberIds)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        // Kiểm tra quyền (phải là owner hoặc admin)
        $checkRole = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkRole->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkRole->fetchColumn();
        if (!in_array($myRole, ['owner', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thêm thành viên.']);
            exit;
        }

        // Kiểm tra giới hạn 50 thành viên
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_members WHERE conversation_id = :conv");
        $countStmt->execute([':conv' => $convId]);
        $currentCount = intval($countStmt->fetchColumn());
        if ($currentCount + count($newMemberIds) > 50) {
            echo json_encode(['success' => false, 'message' => 'Nhóm tối đa 50 thành viên.']);
            exit;
        }

        $added = 0;
        $insertMember = $pdo->prepare("INSERT IGNORE INTO conversation_members (conversation_id, user_id, role, joined_at) VALUES (:conv, :uid, 'member', NOW())");
        foreach ($newMemberIds as $mid) {
            $mid = intval($mid);
            if ($mid > 0) {
                $insertMember->execute([':conv' => $convId, ':uid' => $mid]);
                $added += $insertMember->rowCount();
            }
        }

        echo json_encode(['success' => true, 'message' => "Đã thêm $added thành viên.", 'added' => $added]);
        break;

    // ═══ XOÁ THÀNH VIÊN ═══
    case 'remove_member':
        $convId = intval($input['conversation_id'] ?? 0);
        $removeMemberId = intval($input['member_id'] ?? 0);

        if ($convId <= 0 || $removeMemberId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        // Kiểm tra quyền
        $checkRole = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkRole->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkRole->fetchColumn();
        if (!in_array($myRole, ['owner', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xoá thành viên.']);
            exit;
        }

        // Không cho xoá owner
        $checkTarget = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkTarget->execute([':conv' => $convId, ':uid' => $removeMemberId]);
        $targetRole = $checkTarget->fetchColumn();
        if ($targetRole === 'owner') {
            echo json_encode(['success' => false, 'message' => 'Không thể xoá chủ nhóm.']);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $del->execute([':conv' => $convId, ':uid' => $removeMemberId]);

        echo json_encode(['success' => true, 'message' => 'Đã xoá thành viên khỏi nhóm.']);
        break;

    // ═══ RỜI NHÓM ═══
    case 'leave_group':
        $convId = intval($input['conversation_id'] ?? 0);
        if ($convId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        // Kiểm tra nếu là owner thì phải chuyển quyền trước
        $checkRole = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkRole->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkRole->fetchColumn();

        if ($myRole === 'owner') {
            // Chuyển quyền cho admin hoặc member đầu tiên
            $nextOwner = $pdo->prepare("SELECT user_id FROM conversation_members WHERE conversation_id = :conv AND user_id != :uid ORDER BY FIELD(role, 'admin', 'member') ASC, joined_at ASC LIMIT 1");
            $nextOwner->execute([':conv' => $convId, ':uid' => $currentUserId]);
            $newOwnerId = $nextOwner->fetchColumn();
            
            if ($newOwnerId) {
                $pdo->prepare("UPDATE conversation_members SET role = 'owner' WHERE conversation_id = :conv AND user_id = :uid")
                    ->execute([':conv' => $convId, ':uid' => $newOwnerId]);
                $pdo->prepare("UPDATE conversations SET group_owner_id = :uid WHERE id = :conv")
                    ->execute([':uid' => $newOwnerId, ':conv' => $convId]);
            }
        }

        $del = $pdo->prepare("DELETE FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $del->execute([':conv' => $convId, ':uid' => $currentUserId]);

        // Kiểm tra nếu nhóm không còn ai thì xoá
        $remaining = $pdo->prepare("SELECT COUNT(*) FROM conversation_members WHERE conversation_id = :conv");
        $remaining->execute([':conv' => $convId]);
        if (intval($remaining->fetchColumn()) === 0) {
            $pdo->prepare("DELETE FROM conversations WHERE id = :conv")->execute([':conv' => $convId]);
        }

        echo json_encode(['success' => true, 'message' => 'Bạn đã rời nhóm.']);
        break;

    // ═══ ĐỔI TÊN NHÓM ═══
    case 'rename_group':
        $convId = intval($input['conversation_id'] ?? 0);
        $newName = trim($input['name'] ?? '');
        
        if ($convId <= 0 || empty($newName)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }
        if (mb_strlen($newName) > 100) {
            echo json_encode(['success' => false, 'message' => 'Tên nhóm tối đa 100 ký tự.']);
            exit;
        }

        // Kiểm tra quyền
        $checkRole = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkRole->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkRole->fetchColumn();
        if (!in_array($myRole, ['owner', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền đổi tên nhóm.']);
            exit;
        }

        $pdo->prepare("UPDATE conversations SET group_name = :name WHERE id = :conv AND is_group = 1")
            ->execute([':name' => $newName, ':conv' => $convId]);

        echo json_encode(['success' => true, 'message' => 'Đã đổi tên nhóm.', 'group_name' => $newName]);
        break;

    // ═══ ĐỔI AVATAR NHÓM ═══
    case 'change_avatar':
        $convId = intval($_POST['conversation_id'] ?? 0);
        if ($convId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        // Kiểm tra quyền
        $checkRole = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkRole->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkRole->fetchColumn();
        if (!in_array($myRole, ['owner', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền đổi avatar nhóm.']);
            exit;
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy file ảnh tải lên.']);
            exit;
        }

        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Định dạng ảnh không hợp lệ. Chỉ chấp nhận JPG, PNG, GIF, WEBP.']);
            exit;
        }

        // Kiểm tra size tối đa 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Kích thước ảnh tối đa 5MB.']);
            exit;
        }

        // Tạo thư mục nếu chưa có
        $uploadDir = __DIR__ . '/../uploads/groups/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = 'group_' . $convId . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $avatarUrl = 'uploads/groups/' . $newFilename;

            // Cập nhật CSDL
            $pdo->prepare("UPDATE conversations SET group_avatar = :avatar WHERE id = :conv AND is_group = 1")
                ->execute([':avatar' => $avatarUrl, ':conv' => $convId]);

            // Lấy tên người dùng
            $userStmt = $pdo->prepare("SELECT hoten, username FROM users WHERE id = :uid");
            $userStmt->execute([':uid' => $currentUserId]);
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
            $userName = !empty($userRow['hoten']) ? $userRow['hoten'] : $userRow['username'];

            // Ghi tin nhắn hệ thống
            $sysMsg = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content, type) VALUES (:conv, :uid, :content, 'system')");
            $sysMsg->execute([
                ':conv' => $convId,
                ':uid' => $currentUserId,
                ':content' => '📷 ' . $userName . ' đã thay đổi ảnh đại diện của nhóm.'
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Đã cập nhật ảnh đại diện nhóm.',
                'avatar_url' => $avatarUrl
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi lưu trữ file tải lên.']);
        }
        break;

    // ═══ LẤY THÔNG TIN CHI TIẾT NHÓM ═══
    case 'get_group_info':
        $convId = intval($input['conversation_id'] ?? $_GET['conversation_id'] ?? 0);
        if ($convId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        // Kiểm tra là thành viên
        $checkMember = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkMember->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkMember->fetchColumn();
        if (!$myRole) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải thành viên nhóm này.']);
            exit;
        }

        // Lấy thông tin nhóm
        $groupStmt = $pdo->prepare("SELECT id, group_name, group_avatar, group_owner_id, is_locked, locked_reason, locked_at, locked_by, created_at FROM conversations WHERE id = :conv AND is_group = 1");
        $groupStmt->execute([':conv' => $convId]);
        $groupInfo = $groupStmt->fetch(PDO::FETCH_ASSOC);
        if (!$groupInfo) {
            echo json_encode(['success' => false, 'message' => 'Nhóm không tồn tại.']);
            exit;
        }

        // Lấy danh sách thành viên
        $membersStmt = $pdo->prepare("
            SELECT u.id, u.username, u.hoten, u.avatar, u.is_online, cm.role, cm.joined_at, cm.nickname
            FROM conversation_members cm
            JOIN users u ON u.id = cm.user_id
            WHERE cm.conversation_id = :conv
            ORDER BY FIELD(cm.role, 'owner', 'admin', 'member') ASC, cm.joined_at ASC
        ");
        $membersStmt->execute([':conv' => $convId]);
        $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Chuẩn hóa avatar
        foreach ($members as &$m) {
            if (empty($m['hoten'])) $m['hoten'] = $m['username'];
            if (empty($m['avatar'])) {
                $initial = mb_strtoupper(mb_substr($m['hoten'], 0, 1, 'UTF-8'), 'UTF-8');
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="#9ca3af"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-size="48" font-family="sans-serif" font-weight="bold" fill="#ffffff">' . $initial . '</text></svg>';
                $m['avatar'] = 'data:image/svg+xml;base64,' . base64_encode($svg);
            }
        }
        unset($m);

        echo json_encode([
            'success' => true,
            'group' => $groupInfo,
            'members' => $members,
            'my_role' => $myRole
        ]);
        break;

    // ═══ ĐẶT BIỆT DANH THÀNH VIÊN ═══
    case 'set_nickname':
        $convId = intval($input['conversation_id'] ?? 0);
        $targetUserId = intval($input['user_id'] ?? 0);
        $nickname = trim($input['nickname'] ?? '');

        if ($convId <= 0 || $targetUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        // Kiểm tra quyền: Phải là thành viên trong nhóm
        $checkRole = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkRole->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkRole->fetchColumn();
        if (!$myRole) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải thành viên nhóm này.']);
            exit;
        }

        // Quyền sửa biệt danh: Bản thân tự sửa, hoặc Admin/Owner sửa của người khác
        if ($targetUserId !== $currentUserId && !in_array($myRole, ['owner', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền đặt biệt danh cho người khác.']);
            exit;
        }

        // Cập nhật biệt danh
        $setNick = $pdo->prepare("UPDATE conversation_members SET nickname = :nickname WHERE conversation_id = :conv AND user_id = :uid");
        $setNick->execute([
            ':nickname' => empty($nickname) ? null : $nickname,
            ':conv' => $convId,
            ':uid' => $targetUserId
        ]);

        // Lấy tên thật/biệt danh mới của target
        $targetStmt = $pdo->prepare("SELECT hoten, username FROM users WHERE id = :uid");
        $targetStmt->execute([':uid' => $targetUserId]);
        $tRow = $targetStmt->fetch(PDO::FETCH_ASSOC);
        $targetName = !empty($tRow['hoten']) ? $tRow['hoten'] : $tRow['username'];

        // Lấy tên người sửa biệt danh
        $actorStmt = $pdo->prepare("SELECT hoten, username FROM users WHERE id = :uid");
        $actorStmt->execute([':uid' => $currentUserId]);
        $aRow = $actorStmt->fetch(PDO::FETCH_ASSOC);
        $actorName = !empty($aRow['hoten']) ? $aRow['hoten'] : $aRow['username'];

        // Ghi tin nhắn hệ thống thông báo đổi biệt danh
        $sysContent = empty($nickname)
            ? "✏️ {$actorName} đã gỡ biệt danh của {$targetName}."
            : "✏️ {$actorName} đã đặt biệt danh cho {$targetName} là \"{$nickname}\".";

        $sysMsg = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content, type) VALUES (:conv, :uid, :content, 'system')");
        $sysMsg->execute([
            ':conv' => $convId,
            ':uid' => $currentUserId,
            ':content' => $sysContent
        ]);

        echo json_encode(['success' => true, 'message' => 'Đã cập nhật biệt danh thành công.', 'nickname' => $nickname]);
        break;

    // ═══ GHIM TIN NHẮN ═══
    case 'pin_message':
        $messageId = intval($input['message_id'] ?? 0);
        if ($messageId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
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

        // Kiểm tra quyền: Phải là Admin hoặc Owner của nhóm
        $checkRole = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkRole->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkRole->fetchColumn();
        if (!in_array($myRole, ['owner', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Chỉ quản trị viên mới được ghim tin nhắn.']);
            exit;
        }

        // Cập nhật ghim tin nhắn
        $pinStmt = $pdo->prepare("UPDATE messages SET is_pinned = 1, pinned_by = :pinned_by, pinned_at = NOW() WHERE id = :id");
        $pinStmt->execute([
            ':pinned_by' => $currentUserId,
            ':id' => $messageId
        ]);

        // Lấy tên người ghim
        $actorStmt = $pdo->prepare("SELECT hoten, username FROM users WHERE id = :uid");
        $actorStmt->execute([':uid' => $currentUserId]);
        $aRow = $actorStmt->fetch(PDO::FETCH_ASSOC);
        $actorName = !empty($aRow['hoten']) ? $aRow['hoten'] : $aRow['username'];

        // Ghi tin nhắn hệ thống thông báo ghim tin nhắn
        $snippet = mb_strimwidth($msgInfo['content'], 0, 30, '...');
        $sysMsg = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content, type) VALUES (:conv, :uid, :content, 'system')");
        $sysMsg->execute([
            ':conv' => $convId,
            ':uid' => $currentUserId,
            ':content' => "📌 {$actorName} đã ghim tin nhắn: \"{$snippet}\""
        ]);

        echo json_encode(['success' => true, 'message' => 'Đã ghim tin nhắn.']);
        break;

    // ═══ BỎ GHIM TIN NHẮN ═══
    case 'unpin_message':
        $messageId = intval($input['message_id'] ?? 0);
        if ($messageId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        // Lấy thông tin tin nhắn
        $msgStmt = $pdo->prepare("SELECT conversation_id FROM messages WHERE id = :id");
        $msgStmt->execute([':id' => $messageId]);
        $convId = intval($msgStmt->fetchColumn());

        // Kiểm tra quyền
        $checkRole = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id = :conv AND user_id = :uid");
        $checkRole->execute([':conv' => $convId, ':uid' => $currentUserId]);
        $myRole = $checkRole->fetchColumn();
        if (!in_array($myRole, ['owner', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Chỉ quản trị viên mới được bỏ ghim tin nhắn.']);
            exit;
        }

        // Cập nhật bỏ ghim tin nhắn
        $unpinStmt = $pdo->prepare("UPDATE messages SET is_pinned = 0 WHERE id = :id");
        $unpinStmt->execute([':id' => $messageId]);

        // Lấy tên người bỏ ghim
        $actorStmt = $pdo->prepare("SELECT hoten, username FROM users WHERE id = :uid");
        $actorStmt->execute([':uid' => $currentUserId]);
        $aRow = $actorStmt->fetch(PDO::FETCH_ASSOC);
        $actorName = !empty($aRow['hoten']) ? $aRow['hoten'] : $aRow['username'];

        // Ghi tin nhắn hệ thống thông báo bỏ ghim
        $sysMsg = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content, type) VALUES (:conv, :uid, :content, 'system')");
        $sysMsg->execute([
            ':conv' => $convId,
            ':uid' => $currentUserId,
            ':content' => "📌 {$actorName} đã gỡ một tin nhắn ghim."
        ]);

        echo json_encode(['success' => true, 'message' => 'Đã gỡ ghim tin nhắn.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ: ' . $action]);
}
?>
