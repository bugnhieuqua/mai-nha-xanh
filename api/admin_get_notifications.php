<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/room_status_helper.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?: [];
$sinceRaw = trim((string)($_GET['since'] ?? $data['since'] ?? ''));
$full     = isset($_GET['full']) || isset($data['full']);
$markAll  = !empty($data['mark_all_read']);

function cutText($value, $limit = 140) {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1) . '…' : $text;
    }

    return strlen($text) > $limit ? substr($text, 0, $limit - 1) . '…' : $text;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensureDangbaiRoomStatusSchema($db);

    if ($markAll) {
        try { $db->exec("UPDATE dangbai_chothuetro SET admin_seen = 1 WHERE admin_seen = 0 AND trangthai = 'cho_duyet'"); } catch (Exception $e) {}
        try { $db->exec("UPDATE lienhe SET admin_seen = 1 WHERE admin_seen = 0"); } catch (Exception $e) {}
        try { $db->exec("UPDATE chatbot_history SET is_read = 1 WHERE is_read = 0 AND sender IN ('user','ai') AND chat_type = 'support'"); } catch (Exception $e) {}
        echo json_encode(['success' => true]);
        exit;
    }

    $results = [];

    try {
        $stmtPosts = $db->query("
            SELECT id, tieude, diachi, nguoidang, ngaydang, admin_seen
            FROM dangbai_chothuetro
            WHERE trangthai = 'cho_duyet' AND admin_seen = 0
            ORDER BY ngaydang DESC, id DESC
            LIMIT 10
        ");

        foreach ($stmtPosts->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'id'         => 'post-' . $row['id'],
                'type'       => 'post',
                'title'      => trim((string)($row['tieude'] ?? '')) ?: 'Bài đăng chờ duyệt',
                'content'    => cutText(($row['nguoidang'] ?? 'Người dùng') . ' vừa gửi bài đăng mới' . (!empty($row['diachi']) ? ' tại ' . $row['diachi'] : '')),
                'created_at' => $row['ngaydang'],
                'admin_seen' => (int)($row['admin_seen'] ?? 0),
                'link'       => 'posts.php?status=cho_duyet',
                'name'       => $row['nguoidang'] ?? '',
            ];
        }
    } catch (Exception $e) {}

    try {
        $stmtContacts = $db->query("
            SELECT id, hoten, email, sodienthoai, tieude, noidung, ngaygui, admin_seen,
                   COALESCE(NULLIF(session_id, ''), CONCAT('lienhe_', id)) AS session_key
            FROM lienhe
            WHERE admin_seen = 0
            ORDER BY ngaygui DESC, id DESC
            LIMIT 10
        ");

        foreach ($stmtContacts->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'id'         => 'contact-' . $row['id'],
                'type'       => 'contact',
                'title'      => trim((string)($row['tieude'] ?? '')) ?: ('Liên hệ từ ' . ($row['hoten'] ?: 'người dùng')),
                'content'    => cutText($row['noidung'] ?? 'Người dùng vừa gửi liên hệ mới'),
                'created_at' => $row['ngaygui'],
                'admin_seen' => (int)($row['admin_seen'] ?? 0),
                'link'       => 'ho_tro.php?session_id=' . rawurlencode($row['session_key']),
                'name'       => $row['hoten'] ?? '',
                'phone'      => $row['sodienthoai'] ?? '',
            ];
        }
    } catch (Exception $e) {}

    try {
        $stmtSupport = $db->query("
            SELECT s.session_id, s.user_id, s.created_at, s.unread_count,
                   COALESCE(u.username, '') AS user_name,
                   (
                       SELECT COALESCE(NULLIF(ch2.user_message, ''), NULLIF(ch2.bot_response, ''), 'Người dùng vừa nhắn tin hỗ trợ')
                       FROM chatbot_history ch2
                       WHERE ch2.session_id = s.session_id
                         AND ch2.chat_type = 'support'
                         AND ch2.sender IN ('user', 'ai')
                       ORDER BY ch2.created_at DESC, ch2.id DESC
                       LIMIT 1
                   ) AS preview
            FROM (
                SELECT session_id,
                       MAX(user_id) AS user_id,
                       MAX(created_at) AS created_at,
                       SUM(CASE WHEN sender IN ('user', 'ai') AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count
                FROM chatbot_history
                WHERE chat_type = 'support'
                GROUP BY session_id
                HAVING unread_count > 0
                ORDER BY created_at DESC
                LIMIT 10
            ) s
            LEFT JOIN users u
              ON (u.id = s.user_id OR (s.user_id IS NULL AND u.chatbot_session_id = s.session_id))
            ORDER BY s.created_at DESC
        ");

        foreach ($stmtSupport->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'id'         => 'support-' . $row['session_id'],
                'type'       => 'support',
                'title'      => trim((string)$row['user_name']) ?: 'Yêu cầu hỗ trợ mới',
                'content'    => cutText($row['preview'] ?? 'Người dùng vừa gửi tin nhắn hỗ trợ'),
                'created_at' => $row['created_at'],
                'admin_seen' => 0,
                'link'       => 'ho_tro.php?session_id=' . rawurlencode($row['session_id']),
                'name'       => $row['user_name'] ?? '',
                'unread'     => (int)($row['unread_count'] ?? 0),
            ];
        }
    } catch (Exception $e) {}

    if ($sinceRaw !== '' && !$full) {
        $sinceTs = strtotime($sinceRaw);
        if ($sinceTs !== false) {
            $results = array_values(array_filter($results, function ($item) use ($sinceTs) {
                $createdTs = strtotime((string)($item['created_at'] ?? ''));
                return $createdTs !== false && $createdTs > $sinceTs;
            }));
        }
    }

    usort($results, function ($a, $b) {
        return strtotime((string)$b['created_at']) <=> strtotime((string)$a['created_at']);
    });
    $results = array_slice($results, 0, 30);

    $unreadBookings = 0;
    try { $unreadBookings = (int)$db->query("SELECT COUNT(*) FROM dat_phong WHERE admin_seen = 0")->fetchColumn(); } catch (Exception $e) {}

    $pendingPosts = 0;
    $contactNew = 0;
    $supportNew = 0;
    $reportsNew = 0;
    $usersNew = 0;
    $communityNew = 0;
    $tinNhanNew = 0;

    try { $pendingPosts = (int)$db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai = 'cho_duyet' AND admin_seen = 0")->fetchColumn(); } catch (Exception $e) {}
    try { $contactNew = (int)$db->query("SELECT COUNT(*) FROM lienhe WHERE admin_seen = 0")->fetchColumn(); } catch (Exception $e) {}
    try { $supportNew = (int)$db->query("SELECT COUNT(DISTINCT session_id) FROM chatbot_history WHERE sender IN ('user','ai') AND is_read = 0 AND chat_type = 'support'")->fetchColumn(); } catch (Exception $e) {}
    try { $reportsNew = (int)$db->query("SELECT COUNT(*) FROM reports WHERE admin_seen = 0")->fetchColumn(); } catch (Exception $e) {}
    try { $usersNew = (int)$db->query("SELECT COUNT(*) FROM users WHERE admin_seen = 0")->fetchColumn(); } catch (Exception $e) {}
    try { $communityNew = (int)$db->query("SELECT COUNT(*) FROM community_posts WHERE admin_seen = 0")->fetchColumn(); } catch (Exception $e) {}
    try { $tinNhanNew = (int)$db->query("SELECT COUNT(DISTINCT session_id) FROM chatbot_history WHERE is_read = 0 AND sender IN ('user','ai') AND chat_type = 'bot'")->fetchColumn(); } catch (Exception $e) {}

    echo json_encode([
        'success'         => true,
        'data'            => $results,
        'unread_count'    => $pendingPosts + $contactNew + $supportNew,
        'unread_bookings' => $unreadBookings,
        'unread_contacts' => $contactNew,
        'pending_posts'   => $pendingPosts,
        'contact_new'     => $contactNew,
        'support_new'     => $supportNew,
        'reports_new'     => $reportsNew,
        'users_new'       => $usersNew,
        'community_new'   => $communityNew,
        'tinnhan_new'     => $tinNhanNew,
        'since'           => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
