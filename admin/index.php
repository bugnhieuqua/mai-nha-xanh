<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/media_helper.php';
require_once '../includes/room_status_helper.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();
ensureDangbaiRoomStatusSchema($db);

// --- Stats ---
$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai = 'cho_duyet'");
$pending_posts = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai='da_duyet' AND DATE(duyet_luc)=CURDATE()");
$approved_today = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro");
$total_posts = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM chatbot_history WHERE DATE(created_at)=CURDATE()");
$chatbot_today = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(DISTINCT session_id) FROM chatbot_history");
$chatbot_sessions = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM lienhe WHERE trangthai='chua_xu_ly'");
$contact_new = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM lienhe");
$total_contacts = (int)$stmt->fetchColumn();

// Latest 8 pending posts
$stmt = $db->query("
    SELECT id, tieude, gia, diachi, nguoidang, ngaydang, hinhanh, trangthai
    FROM dangbai_chothuetro
    ORDER BY
        CASE trangthai WHEN 'cho_duyet' THEN 0 ELSE 1 END,
        ngaydang DESC
    LIMIT 8
");
$recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Monthly Stats for Charts (Last 6 Months) ---
$monthly_posts_query = $db->query("
    SELECT DATE_FORMAT(ngaydang, '%m/%Y') as month, COUNT(*) as count
    FROM dangbai_chothuetro
    WHERE ngaydang >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY MIN(ngaydang) ASC
");
$monthly_posts_data = $monthly_posts_query->fetchAll(PDO::FETCH_ASSOC);

$monthly_chats_query = $db->query("
    SELECT DATE_FORMAT(created_at, '%m/%Y') as month, COUNT(DISTINCT session_id) as count
    FROM chatbot_history
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY MIN(created_at) ASC
");
$monthly_chats_data = $monthly_chats_query->fetchAll(PDO::FETCH_ASSOC);

// Recent 5 chatbot sessions
$stmt = $db->query("
    SELECT session_id, COUNT(*) as msg_count, MAX(created_at) as last_time, MIN(user_message) as first_msg
    FROM chatbot_history
    GROUP BY session_id
    ORDER BY last_time DESC
    LIMIT 5
");
$recent_chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Extra stats & widgets queries ---
$approved_posts = 0;
$rejected_posts = 0;
try {
    $stmt = $db->query("SELECT trangthai, COUNT(*) as cnt FROM dangbai_chothuetro GROUP BY trangthai");
    $raw_trangthai = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $approved_posts = (int)($raw_trangthai['da_duyet'] ?? 0);
    $rejected_posts = (int)($raw_trangthai['tu_choi'] ?? 0);
} catch (Exception $e) {}

$total_users = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role != 'admin' OR role IS NULL");
    $total_users = (int)$stmt->fetchColumn();
} catch (Exception $e) { $total_users = 0; }

$posts_today = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE DATE(ngaydang) = CURDATE()");
    $posts_today = (int)$stmt->fetchColumn();
} catch (Exception $e) { $posts_today = 0; }

$topPosters = [];
try {
    $stmt = $db->query("
        SELECT 
            d.nguoidang, 
            COUNT(*) as post_count,
            MAX(u.hoten) as hoten,
            MAX(u.avatar) as avatar,
            SUM(CASE WHEN d.trangthai='cho_duyet' THEN 1 ELSE 0 END) as pending
        FROM dangbai_chothuetro d
        INNER JOIN users u ON d.nguoidang = u.username
        WHERE d.nguoidang IS NOT NULL AND d.nguoidang != '' AND (u.role != 'admin' OR u.role IS NULL)
        GROUP BY d.nguoidang
        ORDER BY post_count DESC
        LIMIT 5
    ");
    $topPosters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $stmt = $db->query("
            SELECT nguoidang, COUNT(*) as post_count,
                   SUM(CASE WHEN trangthai='cho_duyet' THEN 1 ELSE 0 END) as pending
            FROM dangbai_chothuetro
            WHERE nguoidang IS NOT NULL AND nguoidang != ''
            GROUP BY nguoidang
            ORDER BY post_count DESC
            LIMIT 5
        ");
        $topPosters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) { $topPosters = []; }
}

$recentPending = [];
try {
    $stmt = $db->query("
        SELECT id, tieude, diachi, gia, nguoidang, ngaydang, hinhanh
        FROM dangbai_chothuetro
        WHERE trangthai = 'cho_duyet'
        ORDER BY ngaydang DESC
        LIMIT 8
    ");
    $recentPending = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$admin_name = $_SESSION['username'] ?? 'Admin';
$admin_initial = strtoupper(function_exists('mb_substr')
    ? mb_substr($admin_name, 0, 1, 'UTF-8')
    : substr($admin_name, 0, 1));
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Chào buổi sáng' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối');

$ratio = static function (float $value): string {
    $formatted = number_format($value, 1);
    return rtrim(rtrim($formatted, '0'), '.');
};

$pending_ratio = $total_posts > 0 ? ($pending_posts / $total_posts) * 100 : 0;
$approved_ratio = $total_posts > 0 ? ($approved_today / $total_posts) * 100 : 0;
$contact_ratio = $total_contacts > 0 ? ($contact_new / $total_contacts) * 100 : 0;
$chat_avg_per_session = $chatbot_sessions > 0 ? ($chatbot_today / $chatbot_sessions) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Mái Nhà Xanh</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<!-- Notification Bar Fixed Top -->
<div id="admin-notif-bar" class="admin-notif-bar">
    <i class="fas fa-bell admin-notif-bar-icon"></i>
    <span class="admin-notif-bar-text">Bạn có thông báo mới!</span>
    <button class="admin-notif-bar-close" onclick="document.getElementById('admin-notif-bar').classList.remove('visible')">
        <i class="fas fa-times"></i>
    </button>
</div>

<main class="admin-main">
    <div class="admin-topbar">
        <div class="topbar-left">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            <div class="topbar-greeting">
                <p class="topbar-kicker">Premium Dark Dashboard</p>
                <div class="topbar-title"><?= htmlspecialchars($greeting) ?>, <span><?= htmlspecialchars($admin_name) ?></span></div>
            </div>
        </div>
        <div class="topbar-right">
            <span class="glass-chip" id="admin-clock">
                <i class="fas fa-clock"></i>
                <span id="admin-clock-text"><?= date('d/m/Y H:i') ?></span>
            </span>

            <div id="admin-notif-wrap">
                <button id="admin-bell" type="button" title="Thông báo mới">
                    <i class="fas fa-bell"></i>
                    <span id="admin-notif-badge">0</span>
                </button>
                <div id="admin-notif-dropdown">
                    <div class="admin-notif-header">
                        <span class="admin-notif-title-wrap">
                            <i class="fas fa-bell" style="margin-right:6px;"></i>Thông báo mới
                        </span>
                        <div class="admin-notif-actions">
                            <button type="button" class="btn-glass admin-notif-mark-all">Đọc hết ✓</button>
                            <button type="button" class="btn-glass admin-notif-delete-all">Xoá hết</button>
                        </div>
                    </div>
                    <div id="admin-notif-list"></div>
                </div>
            </div>

            <div class="topbar-avatar-stack">
                <?php if (!empty($_SESSION['avatar'])): ?>
                    <img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" class="admin-avatar" style="object-fit:cover;" alt="Avatar">
                <?php else: ?>
                    <div class="admin-avatar"><?= htmlspecialchars($admin_initial) ?></div>
                <?php endif; ?>
                <div class="topbar-user-meta">
                    <strong><?= htmlspecialchars($admin_name) ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-content">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($pending_posts) ?></div>
                    <div class="stat-label">Bài chờ duyệt</div>
                    <div class="stat-trend <?= $pending_posts > 0 ? 'trend-down' : 'trend-up' ?>">
                        <i class="fas fa-<?= $pending_posts > 0 ? 'triangle-exclamation' : 'circle-check' ?>"></i>
                        <span><?= $pending_posts > 0 ? $ratio($pending_ratio) . '% tổng bài cần duyệt' : 'Không có tồn đọng' ?></span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-double"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($approved_today) ?></div>
                    <div class="stat-label">Duyệt hôm nay</div>
                    <div class="stat-trend <?= $approved_today > 0 ? 'trend-up' : 'trend-neutral' ?>">
                        <i class="fas fa-<?= $approved_today > 0 ? 'arrow-trend-up' : 'minus' ?>"></i>
                        <span><?= $approved_today > 0 ? '+' . $ratio($approved_ratio) . '% trên tổng bài' : 'Chưa có duyệt mới' ?></span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-robot"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($chatbot_today) ?></div>
                    <div class="stat-label">Tin nhắn chatbot hôm nay</div>
                    <div class="stat-trend <?= $chatbot_today > 0 ? 'trend-up' : 'trend-neutral' ?>">
                        <i class="fas fa-bolt"></i>
                        <span>TB <?= $ratio($chat_avg_per_session) ?> tin/phiên (<?= number_format($chatbot_sessions) ?> phiên)</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-envelope-open-text"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($contact_new) ?></div>
                    <div class="stat-label">Liên hệ chưa xử lý</div>
                    <div class="stat-trend <?= $contact_new > 0 ? 'trend-down' : 'trend-up' ?>">
                        <i class="fas fa-envelope"></i>
                        <span><?= $contact_new > 0 ? $ratio($contact_ratio) . '% trong tổng liên hệ' : 'Đã xử lý toàn bộ' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card quick-actions-bar">
            <div class="card-body quick-actions-body">
                <span class="quick-actions-title"><i class="fas fa-bolt"></i> Thao tác nhanh</span>
                <a href="posts.php?status=cho_duyet" class="btn btn-3d-glow btn-3d-amber btn-sm"><i class="fas fa-clipboard-check"></i> Duyệt bài ngay</a>
                <a href="users.php" class="btn btn-3d-glow btn-3d-blue btn-sm"><i class="fas fa-users-cog"></i> Quản lý thành viên</a>
                <a href="ho_tro.php" class="btn btn-3d-glow btn-3d-green btn-sm"><i class="fas fa-headset"></i> Phản hồi hỗ trợ</a>
                <a href="tin_nhan.php" class="btn btn-3d-glow btn-3d-purple btn-sm"><i class="fas fa-robot"></i> Lịch sử AI</a>
            </div>
        </div>

        <div class="dashboard-two-col">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-line"></i> Xu hướng đăng bài (6 tháng)</div>
                    <span class="dashboard-note">Bài đăng theo tháng</span>
                </div>
                <div class="card-body">
                    <canvas id="postsChart" height="220"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar"></i> Lưu lượng Chatbot</div>
                    <span class="dashboard-note">Phiên tương tác theo tháng</span>
                </div>
                <div class="card-body">
                    <canvas id="chatsChart" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-content-grid">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-history"></i> Bài đăng mới cập nhật</div>
                    <a href="posts.php" class="btn btn-sm btn-primary">Xem tất cả <i class="fas fa-arrow-right"></i></a>
                </div>
                <?php if (empty($recent_posts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h4>Chưa có bài đăng mới</h4>
                        <p>Dữ liệu sẽ xuất hiện khi có bài cập nhật gần đây.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Phòng trọ</th>
                                    <th>Giá thuê</th>
                                    <th>Trạng thái</th>
                                    <th style="text-align:right;">Xử lý</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent_posts as $post): ?>
                                <tr>
                                    <td>
                                        <div class="post-cell">
                                            <?php if ($post['hinhanh']): ?>
                                                <img src="<?= htmlspecialchars(buildMediaUrl($post['hinhanh'] ?? '', '..')) ?>" class="post-thumb" onerror="this.onerror=null; this.src='../assets/images/myhome.png';">
                                            <?php endif; ?>
                                            <div class="post-title" title="<?= htmlspecialchars($post['tieude']) ?>">
                                                <?= htmlspecialchars($post['tieude']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="price-text"><?= number_format((float)$post['gia']) ?> ₫</td>
                                    <td>
                                        <?php if ($post['trangthai'] === 'cho_duyet'): ?>
                                            <span class="badge badge-warning">Chờ duyệt</span>
                                        <?php elseif ($post['trangthai'] === 'da_duyet'): ?>
                                            <span class="badge badge-approved">Đã duyệt</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected">Từ chối</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-cell-right">
                                        <a href="posts.php?id=<?= (int)$post['id'] ?>" class="btn btn-xs btn-outline">Chi tiết</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-robot"></i> Phiên hội thoại AI mới</div>
                    <a href="tin_nhan.php" class="btn btn-xs btn-outline">Lịch sử</a>
                </div>
                <?php if (empty($recent_chats)): ?>
                    <div class="empty-state">
                        <p>Chưa có dữ liệu</p>
                    </div>
                <?php else: ?>
                    <div class="card-body chat-mini-list">
                        <?php foreach ($recent_chats as $chat): ?>
                            <?php
                            $first_msg = trim((string)($chat['first_msg'] ?? ''));
                            if ($first_msg === '') {
                                $first_msg = '(Không có nội dung mở đầu)';
                            }
                            ?>
                            <a href="tin_nhan.php?v=<?= base64_encode($chat['session_id']) ?>" class="chat-mini-card">
                                <div class="chat-mini-avatar">
                                    <i class="fas fa-comment-dots"></i>
                                </div>
                                <div class="chat-mini-copy">
                                    <div class="chat-mini-title"><?= htmlspecialchars($first_msg) ?></div>
                                    <div class="chat-mini-meta">
                                        <span><i class="fas fa-envelope"></i> <?= (int)$chat['msg_count'] ?> tin</span>
                                        <span><?= date('H:i, d/m', strtotime((string)$chat['last_time'])) ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- NEW: Pending posts & top posters widgets -->
        <div class="dashboard-content-grid" id="pending-posters-grid" style="grid-template-columns: 2fr 1fr; margin-top: 24px;">
            <!-- CARD 1: Danh sách bài đang chờ duyệt (Pending posts) -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-clock" style="color: #f59e0b;"></i> Bài đăng đang chờ duyệt</div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="badge badge-warning"><?= number_format($pending_posts) ?> bài chờ</span>
                        <a href="posts.php?status=cho_duyet" class="btn btn-xs btn-outline">Quản lý</a>
                    </div>
                </div>
                <?php if (empty($recentPending)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle" style="color: #10b981; font-size: 2.5rem; margin-bottom: 12px;"></i>
                        <h4>Không có bài chờ duyệt</h4>
                        <p>Tất cả bài đăng đã được phê duyệt hoặc từ chối.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Phòng trọ</th>
                                    <th>Người đăng</th>
                                    <th>Giá thuê</th>
                                    <th style="text-align:right;">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentPending as $p): ?>
                                <tr>
                                    <td>
                                        <div class="post-cell">
                                            <?php if ($p['hinhanh']): ?>
                                                <img src="<?= htmlspecialchars(buildMediaUrl($p['hinhanh'] ?? '', '..')) ?>" class="post-thumb" onerror="this.onerror=null; this.src='../assets/images/myhome.png';">
                                            <?php endif; ?>
                                            <div class="post-title" title="<?= htmlspecialchars($p['tieude']) ?>">
                                                <?= htmlspecialchars($p['tieude']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($p['nguoidang'] ?? '—') ?></td>
                                    <td class="price-text"><?= number_format((float)$p['gia']) ?> ₫</td>
                                    <td class="table-cell-right">
                                        <a href="posts.php?id=<?= (int)$p['id'] ?>" class="btn btn-xs btn-primary" style="padding: 4px 8px; font-size: 0.72rem;">Duyệt / Chi tiết</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- CARD 2: Top người đăng bài & Thống kê phụ (Top Posters & Extra Stats) -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-trophy" style="color: #f59e0b;"></i> Top người đăng bài</div>
                    <span class="dashboard-note">Tích cực nhất</span>
                </div>
                <div class="card-body" style="padding: 15px 20px;">
                    <?php if (empty($topPosters)): ?>
                        <p style="text-align: center; color: var(--text-muted); font-size: 0.82rem; margin: 20px 0;">Chưa có dữ liệu thành viên</p>
                    <?php else: ?>
                        <div class="ctx-posters-list" style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($topPosters as $poster): ?>
                                <?php
                                $display_name = !empty($poster['hoten']) ? $poster['hoten'] : ($poster['nguoidang'] ?? 'User');
                                $poster_avatar = $poster['avatar'] ?? '';
                                $initial = strtoupper(function_exists('mb_substr') ? mb_substr($display_name, 0, 1, 'UTF-8') : substr($display_name, 0, 1));
                                ?>
                                <div class="ctx-poster-item" style="display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; background: rgba(255,255,255,0.4); border: 1px solid rgba(16, 185, 129, 0.08);">
                                    <?php if (!empty($poster_avatar)): ?>
                                        <img src="<?= htmlspecialchars(buildMediaUrl($poster_avatar, '..')) ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #10b981; flex-shrink: 0;" alt="Avatar" onerror="this.onerror=null; this.style.display='none';">
                                    <?php else: ?>
                                        <div class="ctx-poster-avatar" style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.85rem; font-weight: 700; flex-shrink: 0;">
                                            <?= htmlspecialchars($initial) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ctx-poster-info" style="flex: 1; min-width: 0;">
                                        <div class="ctx-poster-name" style="font-size: 0.82rem; font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= htmlspecialchars($display_name) ?>
                                            <?php if (!empty($poster['hoten']) && $poster['hoten'] !== $poster['nguoidang']): ?>
                                                <small style="color:var(--text-muted);font-weight:400;">(@<?= htmlspecialchars($poster['nguoidang']) ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--text-muted);">
                                            <?= $poster['post_count'] ?> bài đăng
                                        </div>
                                    </div>
                                    <?php if ($poster['pending'] > 0): ?>
                                        <span class="badge badge-warning" style="font-size: 0.65rem; padding: 2px 6px;"><?= $poster['pending'] ?> chờ</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Extra Quick Stats Summary -->
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed rgba(16, 185, 129, 0.2); display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div style="background: rgba(16, 185, 129, 0.06); padding: 8px 10px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 1.1rem; font-weight: 800; color: #065f46;"><?= number_format($total_users) ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-muted);">Tổng Thành Viên</div>
                        </div>
                        <div style="background: rgba(139, 92, 246, 0.06); padding: 8px 10px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 1.1rem; font-weight: 800; color: #7c3aed;"><?= number_format($posts_today) ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-muted);">Bài mới Hôm nay</div>
                        </div>
                        <div style="background: rgba(59, 130, 246, 0.06); padding: 8px 10px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 1.1rem; font-weight: 800; color: #1d4ed8;"><?= number_format($total_posts) ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-muted);">Tổng số Bài đăng</div>
                        </div>
                        <div style="background: rgba(239, 68, 68, 0.06); padding: 8px 10px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 1.1rem; font-weight: 800; color: #dc2626;"><?= number_format($rejected_posts) ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-muted);">Tổng số Từ chối</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</main>

<script>
function showToast(msg, type = 'success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: msg,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const clockText = document.getElementById('admin-clock-text');
    if (clockText) {
        const updateClock = function() {
            const now = new Date();
            clockText.textContent = now.toLocaleString('vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        };
        updateClock();
        setInterval(updateClock, 30000);
    }

    const commonScale = {
        x: {
            ticks: { color: '#64748b', font: { size: 11, weight: '600' } },
            grid: { display: false }
        },
        y: {
            beginAtZero: true,
            ticks: { color: '#64748b', font: { size: 11, weight: '600' } },
            grid: { color: 'rgba(148, 163, 184, 0.2)' }
        }
    };

    const postsCanvas = document.getElementById('postsChart');
    if (postsCanvas) {
        const postsCtx = postsCanvas.getContext('2d');
        const postsGradient = postsCtx.createLinearGradient(0, 0, 0, 320);
        postsGradient.addColorStop(0, 'rgba(16, 185, 129, 0.42)');
        postsGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

        new Chart(postsCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_posts_data, 'month')) ?>,
                datasets: [{
                    label: 'Bài đăng',
                    data: <?= json_encode(array_column($monthly_posts_data, 'count')) ?>,
                    borderColor: '#10b981',
                    backgroundColor: postsGradient,
                    fill: true,
                    tension: 0.38,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#059669',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: commonScale
            }
        });
    }

    const chatsCanvas = document.getElementById('chatsChart');
    if (chatsCanvas) {
        const chatsCtx = chatsCanvas.getContext('2d');
        const chatsGradient = chatsCtx.createLinearGradient(0, 0, 0, 320);
        chatsGradient.addColorStop(0, 'rgba(59, 130, 246, 0.9)');
        chatsGradient.addColorStop(1, 'rgba(37, 99, 235, 0.5)');

        new Chart(chatsCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($monthly_chats_data, 'month')) ?>,
                datasets: [{
                    label: 'Lượt chat',
                    data: <?= json_encode(array_column($monthly_chats_data, 'count')) ?>,
                    backgroundColor: chatsGradient,
                    borderRadius: 10,
                    maxBarThickness: 36
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: commonScale
            }
        });
    }
});

// --- Dynamic Real-time Dashboard Updates ---
async function refreshDashboard() {
    try {
        const res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const htmlText = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlText, 'text/html');
        
        // Swap stats-grid
        const newStatsGrid = doc.querySelector('.stats-grid');
        const currentStatsGrid = document.querySelector('.stats-grid');
        if (newStatsGrid && currentStatsGrid) {
            currentStatsGrid.innerHTML = newStatsGrid.innerHTML;
        }
        
        // Swap dashboard-content-grid (recent posts table & mini chatbot sessions list)
        const newContentGrid = doc.querySelector('.dashboard-content-grid');
        const currentContentGrid = document.querySelector('.dashboard-content-grid');
        if (newContentGrid && currentContentGrid) {
            currentContentGrid.innerHTML = newContentGrid.innerHTML;
        }

        // Swap pending-posters-grid
        const newPendingGrid = doc.getElementById('pending-posters-grid');
        const currentPendingGrid = document.getElementById('pending-posters-grid');
        if (newPendingGrid && currentPendingGrid) {
            currentPendingGrid.innerHTML = newPendingGrid.innerHTML;
        }
    } catch(e) {
        console.error("Dashboard dynamic refresh failed:", e);
    }
}

let lastNotifState = {};
window.addEventListener('adminNotifUpdate', (e) => {
    try {
        if (e.detail) {
            const data = e.detail;
            const changed = lastNotifState.pending_posts !== data.pending_posts ||
                            lastNotifState.contact_new !== data.contact_new ||
                            lastNotifState.support_new !== data.support_new ||
                            lastNotifState.reports_new !== data.reports_new ||
                            lastNotifState.users_new !== data.users_new ||
                            lastNotifState.community_new !== data.community_new ||
                            lastNotifState.tinnhan_new !== data.tinnhan_new;
            if (changed) {
                refreshDashboard();
            }
            lastNotifState = {
                pending_posts: data.pending_posts,
                contact_new: data.contact_new,
                support_new: data.support_new,
                reports_new: data.reports_new,
                users_new: data.users_new,
                community_new: data.community_new,
                tinnhan_new: data.tinnhan_new
            };
        }
    } catch(err) {}
});
</script>
<script src="assets/js/admin-notifications.js"></script>
</body>
</html>
