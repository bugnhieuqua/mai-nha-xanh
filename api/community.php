<?php
/**
 * Community API
 * Hỗ trợ: list_posts, create_post, list_comments, create_comment,
 *          delete_post, delete_comment, delete_posts_bulk
 */

// ── Chặn mọi output rác (warnings/notices) trước JSON ──────────
ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once '../config/database.php';
require_once '../config/session.php';

// Xóa bất kỳ output thừa nào từ includes
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ── Kết nối DB ───────────────────────────────────────────────────
$database = new Database();
$db       = $database->getConnection();
if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL']);
    exit;
}

// ── Auto-create / migrate tables ────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS community_posts (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        content    TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS community_comments (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        post_id    INT NOT NULL,
        user_id    INT NOT NULL,
        parent_id  INT NULL DEFAULT NULL,
        content    TEXT NOT NULL,
        images     TEXT NULL,
        video      VARCHAR(512) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* bảng đã tồn tại */ }

// Thêm cột nếu thiếu (safe migration)
foreach ([
    "ALTER TABLE community_posts ADD COLUMN images TEXT NULL AFTER content",
    "ALTER TABLE community_posts ADD COLUMN video VARCHAR(512) NULL AFTER images",
    "ALTER TABLE community_comments ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER user_id",
    "ALTER TABLE community_comments ADD COLUMN images    TEXT NULL AFTER content",
    "ALTER TABLE community_comments ADD COLUMN video     VARCHAR(512) NULL AFTER images",
    "ALTER TABLE reports ADD COLUMN community_post_id INT NULL DEFAULT NULL",
    "ALTER TABLE reports ADD COLUMN community_comment_id INT NULL DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL",
] as $ddl) {
    try { $db->exec($ddl); } catch (Exception $e) { /* cột đã tồn tại */ }
}

// Đảm bảo cột parent_id tồn tại (kiểm tra lần cuối)
try {
    $stmt = $db->query("SHOW COLUMNS FROM community_comments LIKE 'parent_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE community_comments ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER user_id");
    }
} catch (Exception $e) {
    error_log("Lỗi kiểm tra cột parent_id: " . $e->getMessage());
}

// ── Session context ──────────────────────────────────────────────
$user_id  = $_SESSION['user_id']  ?? null;
$username = $_SESSION['username'] ?? 'Người dùng';
$role     = $_SESSION['role']     ?? null;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helper: gửi thông báo DB nội bộ ────────────────────────────
function insertNotif(PDO $db, int $toUser, string $title, string $body, string $link): void {
    try {
        $db->prepare("INSERT INTO notifications (user_id, type, title, content, link, is_read, created_at)
                      VALUES (:uid, 'new_comment', :title, :content, :link, 0, NOW())")
           ->execute([':uid' => $toUser, ':title' => $title, ':content' => $body, ':link' => $link]);
    } catch (Exception $e) { /* notifications table mungkin belum ada */ }
}

// ── Helper: base URL ─────────────────────────────────────────────
function baseUrl(): string {
    if (function_exists('getBaseUrl')) return getBaseUrl();
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Lấy đúng thư mục gốc project (loại bỏ /api ở cuối vì file này nằm trong /api/)
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_dir   = preg_replace('/(\\/api|\\/includes|\\/admin)$/', '', $script_dir);
    return $protocol . '://' . $host . rtrim($base_dir, '/');
}

// ── Helper: check column exists ────────────────────────────────
function tableHasColumn(PDO $db, string $table, string $column): bool {
    try {
        $st = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

// ── Helper: delete physical files ──────────────────────────────
function deleteCommunityMedia($imagesJson, $videoPath): void {
    if (!empty($imagesJson)) {
        $imgs = json_decode($imagesJson, true);
        if (is_array($imgs)) {
            foreach ($imgs as $img) {
                $p = __DIR__ . '/../' . $img;
                if ($img && file_exists($p)) @unlink($p);
            }
        }
    }
    if (!empty($videoPath)) {
        $p = __DIR__ . '/../' . $videoPath;
        if (file_exists($p)) @unlink($p);
    }
}

switch ($action) {

    // ════════════════════════════════════════════════════════════
    // LIST POSTS
    // ════════════════════════════════════════════════════════════
    case 'list_posts':
        try {
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 10;
            $offset = ($page - 1) * $limit;

            $stmt = $db->prepare("SELECT p.id, p.user_id, p.content, p.images, p.video, p.created_at, u.username, u.avatar
                                  FROM community_posts p
                                  JOIN users u ON p.user_id = u.id
                                  ORDER BY p.created_at DESC
                                  LIMIT :lim OFFSET :off");
            $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Đếm comment cho từng bài
            $counts = [];
            if (!empty($posts)) {
                $ids = implode(',', array_column($posts, 'id'));
                $cs  = $db->query("SELECT post_id, COUNT(*) AS c
                                   FROM community_comments WHERE post_id IN ($ids)
                                   GROUP BY post_id");
                foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $counts[(int)$r['post_id']] = (int)$r['c'];
                }
            }

            foreach ($posts as &$p) {
                $p['comment_count'] = $counts[(int)$p['id']] ?? 0;
                $p['is_owner']      = ($user_id && ((int)$p['user_id'] === (int)$user_id || $role === 'admin'));
            }
            unset($p);

            echo json_encode(['success' => true, 'data' => $posts]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ════════════════════════════════════════════════════════════
    // CREATE POST
    // ════════════════════════════════════════════════════════════
    case 'create_post':
        validateCsrfToken();
        if (!$user_id) { echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']); exit; }
        $content = trim($_POST['content'] ?? '');
        
        require_once __DIR__ . '/community_media_helper.php';
        $media = uploadCommentMedia('../uploads/posts/');
        if (!$media['success']) {
            echo json_encode(['success' => false, 'message' => implode('; ', $media['errors'])]);
            exit;
        }

        $imagesVal = !empty($media['images']) ? $media['images_json'] : null;
        $videoVal  = ($media['video'] !== '') ? $media['video'] : null;

        if ($content === '' && !$imagesVal && !$videoVal) { 
            echo json_encode(['success' => false, 'message' => 'Nội dung và media trống']); 
            exit; 
        }

        try {
            $db->prepare("INSERT INTO community_posts (user_id, content, images, video) VALUES (:uid, :c, :img, :vid)")
               ->execute([':uid' => $user_id, ':c' => $content, ':img' => $imagesVal, ':vid' => $videoVal]);

            // Thông báo admin (best-effort)
            try {
                require_once __DIR__ . '/../includes/one_signal_helper.php';
                sendNotification([
                    'type'    => 'new_community_post',
                    'target'  => 'admin',
                    'title'   => '💬 Bài mới trong Cộng đồng',
                    'content' => $username . ': ' . mb_substr($content, 0, 60),
                    'link'    => baseUrl() . '/admin/community.php',
                ]);
            } catch (Exception $e) {}

            echo json_encode(['success' => true, 'message' => 'Đăng bài thành công']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ════════════════════════════════════════════════════════════
    // LIST COMMENTS
    // ════════════════════════════════════════════════════════════
    case 'list_comments':
        $post_id = (int)($_GET['post_id'] ?? 0);
        if (!$post_id) { echo json_encode(['success' => false, 'message' => 'Thiếu post_id']); exit; }

        try {
            // Chỉ dùng một query duy nhất, không fallback gây nhiễu
            $rows = $db->prepare("
                SELECT c.id, c.post_id, c.user_id, c.parent_id, c.content,
                       c.images, c.video, c.created_at,
                       u.username, u.avatar,
                       pu.username AS reply_to_username
                FROM community_comments c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN community_comments pc ON c.parent_id = pc.id
                LEFT JOIN users pu ON pc.user_id = pu.id
                WHERE c.post_id = :pid
                ORDER BY c.created_at ASC");
            $rows->execute([':pid' => $post_id]);

            $comments = $rows->fetchAll(PDO::FETCH_ASSOC);
            foreach ($comments as &$c) {
                $c['is_owner']  = ($user_id && ((int)$c['user_id'] === (int)$user_id || $role === 'admin'));
                $c['parent_id'] = isset($c['parent_id']) ? $c['parent_id'] : null;
            }
            unset($c);

            echo json_encode(['success' => true, 'data' => $comments]);
        } catch (Exception $e) {
            error_log("List comments error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn bình luận. Vui lòng thử lại sau.']);
        }
        break;

    // ════════════════════════════════════════════════════════════
    // CREATE COMMENT
    // ════════════════════════════════════════════════════════════
    case 'create_comment':
        validateCsrfToken();
        if (!$user_id) { echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']); exit; }

        $post_id   = (int)($_POST['post_id']   ?? 0);
        $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;
        $content   = trim($_POST['content']    ?? '');

        if (!$post_id) {
            echo json_encode(['success' => false, 'message' => 'Lỗi: thiếu post_id']);
            exit;
        }

        // Validate reply parent (must exist and match post)
        if ($parent_id !== null) {
            try {
                $chk = $db->prepare("SELECT post_id FROM community_comments WHERE id=:id");
                $chk->execute([':id' => $parent_id]);
                $parentPost = (int)($chk->fetchColumn() ?? 0);
                if (!$parentPost) {
                    echo json_encode(['success' => false, 'message' => 'Bình luận gốc không tồn tại hoặc đã bị xoá']);
                    exit;
                }
                if ($parentPost !== (int)$post_id) {
                    echo json_encode(['success' => false, 'message' => 'Bình luận gốc không thuộc bài đăng này']);
                    exit;
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Không kiểm tra được bình luận gốc: ' . $e->getMessage()]);
                exit;
            }

            // Ensure schema supports replies (best-effort migrate)
            if (!tableHasColumn($db, 'community_comments', 'parent_id')) {
                try {
                    $db->exec("ALTER TABLE community_comments ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER user_id");
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Chưa bật được chức năng trả lời (thiếu cột parent_id). Vui lòng cập nhật CSDL.']);
                    exit;
                }
            }
        }

        // Upload media FIRST, to check if we can bypass $content emptiness
        require_once __DIR__ . '/community_media_helper.php';
        $media = uploadCommentMedia('../uploads/comments/');
        if (!$media['success']) {
            echo json_encode(['success' => false, 'message' => implode('; ', $media['errors'])]);
            exit;
        }

        $imagesVal = !empty($media['images']) ? $media['images_json'] : null;
        $videoVal  = ($media['video'] !== '') ? $media['video'] : null;

        if ($content === '' && !$imagesVal && !$videoVal) {
            echo json_encode(['success' => false, 'message' => 'Nội dung và media không được để trống']);
            exit;
        }

        try {
            // INSERT
            if ($parent_id !== null) {
                $db->prepare("INSERT INTO community_comments
                              (post_id, user_id, parent_id, content, images, video)
                              VALUES (:pid, :uid, :par, :c, :img, :vid)")
                   ->execute([':pid'=>$post_id,':uid'=>$user_id,':par'=>$parent_id,
                              ':c'=>$content,':img'=>$imagesVal,':vid'=>$videoVal]);
            } else {
                $db->prepare("INSERT INTO community_comments
                              (post_id, user_id, content, images, video)
                              VALUES (:pid, :uid, :c, :img, :vid)")
                   ->execute([':pid'=>$post_id,':uid'=>$user_id,
                              ':c'=>$content,':img'=>$imagesVal,':vid'=>$videoVal]);
            }

            $comment_id = (int)$db->lastInsertId();

            // Fetch inserted comment for immediate UI update (best-effort)
            $commentObj = null;
            try {
                $q = $db->prepare("
                    SELECT c.id, c.post_id, c.user_id, c.parent_id, c.content,
                           c.images, c.video, c.created_at,
                           u.username, u.avatar,
                           pu.username AS reply_to_username
                    FROM community_comments c
                    JOIN users u ON c.user_id = u.id
                    LEFT JOIN community_comments pc ON c.parent_id = pc.id
                    LEFT JOIN users              pu ON pc.user_id  = pu.id
                    WHERE c.id = :id
                    LIMIT 1");
                $q->execute([':id' => $comment_id]);
                $commentObj = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Exception $qe) {
                // Fallback for legacy schema (no parent_id)
                try {
                    $q = $db->prepare("
                        SELECT c.id, c.post_id, c.user_id, c.content,
                               c.images, c.video, c.created_at,
                               u.username, u.avatar,
                               NULL AS parent_id,
                               NULL AS reply_to_username
                        FROM community_comments c
                        JOIN users u ON c.user_id = u.id
                        WHERE c.id = :id
                        LIMIT 1");
                    $q->execute([':id' => $comment_id]);
                    $commentObj = $q->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (Exception $qe2) {
                    $commentObj = null;
                }
            }
            if (is_array($commentObj)) {
                $commentObj['is_owner'] = true;
                $commentObj['parent_id'] = isset($commentObj['parent_id']) ? $commentObj['parent_id'] : null;
            }

            // ── Bọc toàn bộ phần thông báo trong try-catch riêng lẻ để không làm hỏng phản hồi chính ──
            try {
                $contentStr = (string)$content;
                $usernameStr = (string)$username;
                $link = baseUrl() . '/cong-dong.php#post-card-' . $post_id;

                // ── Notification cho chủ bài ─────────────────────────
                $pr = $db->prepare("SELECT user_id FROM community_posts WHERE id=:id");
                $pr->execute([':id' => $post_id]);
                $postAuthorId = (int)($pr->fetchColumn() ?? 0);

                if ($postAuthorId && $postAuthorId !== (int)$user_id) {
                    insertNotif($db, $postAuthorId,
                        '💬 Bình luận mới trên bài của bạn',
                        $usernameStr . ': ' . mb_substr($contentStr, 0, 80),
                        $link);
                    
                    $helperPath = __DIR__ . '/../includes/one_signal_helper.php';
                    if (file_exists($helperPath)) {
                        @include_once $helperPath;
                        if (function_exists('sendNotification')) {
                            sendNotification([
                                'type'    => 'new_comment',
                                'user_id' => $postAuthorId,
                                'title'   => '💬 Bình luận mới!',
                                'content' => $usernameStr . ' đã bình luận bài của bạn',
                                'link'    => $link
                            ]);
                        }
                    }
                }

                // ── Notification cho chủ comment bị reply ────────────
                if ($parent_id !== null) {
                    $pr2 = $db->prepare("SELECT user_id FROM community_comments WHERE id=:id");
                    $pr2->execute([':id' => $parent_id]);
                    $parentAuthor = (int)($pr2->fetchColumn() ?? 0);
                    
                    if ($parentAuthor && $parentAuthor !== (int)$user_id && $parentAuthor !== $postAuthorId) {
                        insertNotif($db, $parentAuthor,
                            '↩️ ' . $usernameStr . ' đã trả lời bình luận của bạn',
                            mb_substr($contentStr, 0, 80),
                            $link);
                    }
                }
            } catch (Exception $notifError) {
                // Lỗi thông báo không được làm ngắt quãng phản hồi trả về cho người dùng
                error_log("Community Notification Error: " . $notifError->getMessage());
            }

            echo json_encode([
                'success'    => true,
                'comment_id' => $comment_id,
                'comment'    => $commentObj,
                'message'    => 'Gửi bình luận thành công'
            ]);
        } catch (Exception $e) {
            foreach ($media['images'] as $p) { @unlink('../' . $p); }
            if ($videoVal) { @unlink('../' . $videoVal); }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ════════════════════════════════════════════════════════════
    // DELETE POST
    // ════════════════════════════════════════════════════════════
    case 'delete_post':
        validateCsrfToken();
        if (!$user_id) { echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']); exit; }
        $id = (int)($_POST['id'] ?? 0);
        try {
            $s = $db->prepare("SELECT user_id, images, video FROM community_posts WHERE id=:id");
            $s->execute([':id' => $id]);
            $post = $s->fetch(PDO::FETCH_ASSOC);
            if (!$post) { echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại']); break; }
            
            if ((int)$post['user_id'] !== (int)$user_id && $role !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Không có quyền']); break;
            }

            // Thu thập media từ comments trước khi xóa
            $sc = $db->prepare("SELECT images, video FROM community_comments WHERE post_id=:id");
            $sc->execute([':id' => $id]);
            $commentsMedia = $sc->fetchAll(PDO::FETCH_ASSOC);

            $db->prepare("DELETE FROM community_comments WHERE post_id=:id")->execute([':id'=>$id]);
            $db->prepare("DELETE FROM community_posts   WHERE id=:id")->execute([':id'=>$id]);

            // Xóa file rác
            deleteCommunityMedia($post['images'], $post['video']);
            foreach ($commentsMedia as $cm) {
                deleteCommunityMedia($cm['images'], $cm['video']);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ════════════════════════════════════════════════════════════
    // DELETE COMMENT
    // ════════════════════════════════════════════════════════════
    case 'delete_comment':
        validateCsrfToken();
        if (!$user_id) { echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']); exit; }
        $id = (int)($_POST['id'] ?? 0);
        try {
            // Lấy media của comment này và tất cả replies của nó
            $s = $db->prepare("SELECT id, user_id, images, video FROM community_comments WHERE id=:id OR parent_id=:id2");
            $s->execute([':id' => $id, ':id2' => $id]);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC);
            
            // Comment chính (để check quyền)
            $main = null;
            foreach ($rows as $r) { if ((int)$r['id'] === $id) { $main = $r; break; } }

            if (!$main || ((int)$main['user_id'] !== (int)$user_id && $role !== 'admin')) {
                echo json_encode(['success' => false, 'message' => 'Không có quyền hoặc không tồn tại']); break;
            }

            // Xoá DB
            $db->prepare("DELETE FROM community_comments WHERE id=:id OR parent_id=:id2")
               ->execute([':id'=>$id, ':id2'=>$id]);

            // Xoá files
            foreach ($rows as $r) {
                deleteCommunityMedia($r['images'], $r['video']);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ════════════════════════════════════════════════════════════
    // BULK DELETE POSTS (Admin)
    // ════════════════════════════════════════════════════════════
    case 'delete_posts_bulk':
        validateCsrfToken();
        if ($role !== 'admin') { echo json_encode(['success' => false, 'message' => 'Chỉ admin']); exit; }
        $raw = $_POST['ids'] ?? [];
        if (!is_array($raw) || empty($raw)) { echo json_encode(['success' => false, 'message' => 'Không có ID']); break; }
        $ids = array_values(array_filter(array_map('intval', $raw), fn($x) => $x > 0));
        if (empty($ids)) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); break; }
        try {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            
            // Thu thập media từ posts
            $s1 = $db->prepare("SELECT images, video FROM community_posts WHERE id IN ($ph)");
            $s1->execute($ids);
            $pMedia = $s1->fetchAll(PDO::FETCH_ASSOC);

            // Thu thập media từ comments
            $s2 = $db->prepare("SELECT images, video FROM community_comments WHERE post_id IN ($ph)");
            $s2->execute($ids);
            $cMedia = $s2->fetchAll(PDO::FETCH_ASSOC);

            // Xóa DB
            $db->prepare("DELETE FROM community_comments WHERE post_id IN ($ph)")->execute($ids);
            $s = $db->prepare("DELETE FROM community_posts WHERE id IN ($ph)");
            $s->execute($ids);
            $count = $s->rowCount();

            // Xóa file rác
            foreach ($pMedia as $m) deleteCommunityMedia($m['images'], $m['video']);
            foreach ($cMedia as $m) deleteCommunityMedia($m['images'], $m['video']);

            echo json_encode(['success' => true, 'message' => "Đã xóa $count bài viết và file rác đi kèm", 'deleted' => $count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ════════════════════════════════════════════════════════════
    // REPORT CONTENT
    // ════════════════════════════════════════════════════════════
    case 'report_content':
        validateCsrfToken();
        if (!$user_id) { echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']); exit; }
        $post_id    = (int)($_POST['post_id']    ?? 0);
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        $reason     = trim($_POST['reason']      ?? '');

        if (!$reason) { echo json_encode(['success' => false, 'message' => 'Vui lòng cung cấp lý do báo cáo']); break; }
        if (!$post_id && !$comment_id) { echo json_encode(['success' => false, 'message' => 'Thiếu ID nội dung']); break; }

        try {
            // Xác định user bị báo cáo
            $reported_user_id = 0;
            if ($comment_id) {
                $st = $db->prepare("SELECT user_id FROM community_comments WHERE id = ?");
                $st->execute([$comment_id]);
                $reported_user_id = (int)$st->fetchColumn();
            } else {
                $st = $db->prepare("SELECT user_id FROM community_posts WHERE id = ?");
                $st->execute([$post_id]);
                $reported_user_id = (int)$st->fetchColumn();
            }

            $stmt = $db->prepare("INSERT INTO reports (reporter_id, reported_user_id, community_post_id, community_comment_id, reason, status)
                                  VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([
                $user_id, 
                $reported_user_id ?: null, 
                $post_id ?: null, 
                $comment_id ?: null, 
                $reason
            ]);

            echo json_encode(['success' => true, 'message' => 'Cảm ơn bạn đã báo cáo. Admin sẽ xem xét nội dung này.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ: ' . htmlspecialchars($action)]);
        break;
}
// Flush buffer sạch
ob_end_flush();
?>
