<?php
require_once __DIR__ . '/../../includes/room_status_helper.php';
ensureDangbaiRoomStatusSchema($db);

// Lấy số lượng bài chờ duyệt
$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai='cho_duyet' AND admin_seen=0");
$pending_posts = $stmt->fetchColumn();

// Lấy số lượng liên hệ chờ xử lý
$stmt = $db->query("SELECT COUNT(*) FROM lienhe WHERE trangthai='chua_xu_ly' AND admin_seen=0");
$contact_new = (int) $stmt->fetchColumn();

// Lấy số lượng hỗ trợ người dùng (chỉ tính chat_type='support')
$support_new = 0;
try {
    $stmt = $db->query("SELECT COUNT(DISTINCT session_id) FROM chatbot_history WHERE is_read=0 AND chat_type='support' AND sender IN ('user','ai')");
    $support_new = (int) $stmt->fetchColumn();
} catch (Exception $e) {
}

// Lấy số lượng báo cáo, cộng đồng, người dùng mới
$reports_new = 0;
$users_new = 0;
$community_new = 0;
try {
    $reports_new = (int) $db->query("SELECT COUNT(*) FROM reports WHERE admin_seen=0")->fetchColumn();
} catch (Exception $e) {
}
try {
    $users_new = (int) $db->query("SELECT COUNT(*) FROM users WHERE admin_seen=0")->fetchColumn();
} catch (Exception $e) {
}
try {
    $community_new = (int) $db->query("SELECT COUNT(*) FROM community_posts WHERE admin_seen=0")->fetchColumn();
} catch (Exception $e) {
}

// Lấy số lượng lịch sử tin nhắn (AI chatbot, chat_type='bot')
$tinnhan_new = 0;
try {
    $tinnhan_new = (int) $db->query("SELECT COUNT(DISTINCT session_id) FROM chatbot_history WHERE is_read=0 AND chat_type='bot' AND sender IN ('user','ai')")->fetchColumn();
} catch (Exception $e) {
}

// Lấy file hiện tại để set active menu
$current_page = basename($_SERVER['PHP_SELF']);

$sidebar_username = $_SESSION['username'] ?? 'Admin';
$sidebar_initial = strtoupper(function_exists('mb_substr')
    ? mb_substr($sidebar_username, 0, 1, 'UTF-8')
    : substr($sidebar_username, 0, 1));
?>
<!-- SIDEBAR -->
<aside class="admin-sidebar">
    <button class="sidebar-close-btn" onclick="document.querySelector('.admin-sidebar').classList.remove('open')"><i
            class="fas fa-times"></i></button>
    <a href="index.php" class="sidebar-brand">
        <span class="sidebar-brand-shimmer" aria-hidden="true"></span>
        <img src="../assets/images/logo.png" alt="Logo" onerror="this.style.background='#10b981';this.src=''">
        <div>
            <span>Mái Nhà Xanh</span>
            <small>Quản trị viên</small>
        </div>
    </a>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Tổng quan</div>
        <a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>"><i
                class="fas fa-th-large"></i> Dashboard</a>
        <div class="nav-divider-glow" aria-hidden="true"></div>

        <div class="nav-section-label">Nội dung</div>
        <a href="posts.php" class="nav-item <?= $current_page == 'posts.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Bài đăng phòng trọ
            <span class="nav-badge badge-tone-amber" id="nav-badge-posts"
                style="<?= $pending_posts > 0 ? '' : 'display:none;' ?>"><?= $pending_posts ?></span>
        </a>
        <a href="lien_he.php" class="nav-item <?= $current_page == 'lien_he.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Quản lý Liên hệ
            <span class="nav-badge badge-tone-blue" id="nav-badge-contacts"
                style="<?= $contact_new > 0 ? '' : 'display:none;' ?>"><?= $contact_new ?></span>
        </a>
        <a href="ho_tro.php" class="nav-item <?= $current_page == 'ho_tro.php' ? 'active' : '' ?>">
            <i class="fas fa-headset"></i> Hỗ trợ người dùng
            <span class="nav-badge badge-tone-orange" id="nav-badge-support"
                style="<?= $support_new > 0 ? '' : 'display:none;' ?>"><?= $support_new ?></span>
        </a>
        <a href="tin_nhan.php" class="nav-item <?= $current_page == 'tin_nhan.php' ? 'active' : '' ?>">
            <i class="fas fa-robot"></i> Lịch sử Tin nhắn
            <span class="nav-badge badge-tone-indigo" id="nav-badge-tinnhan"
                style="<?= $tinnhan_new > 0 ? '' : 'display:none;' ?>"><?= $tinnhan_new ?></span>
        </a>
        <a href="community.php" class="nav-item <?= $current_page == 'community.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Quản lý Cộng đồng
            <span class="nav-badge badge-tone-violet" id="nav-badge-community"
                style="<?= $community_new > 0 ? '' : 'display:none;' ?>"><?= $community_new ?></span>
        </a>
        <div class="nav-divider-glow" aria-hidden="true"></div>

        <div class="nav-section-label">Hệ thống & Người dùng</div>
        <a href="users.php" class="nav-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i> Quản lý Người dùng
            <span class="nav-badge badge-tone-green" id="nav-badge-users"
                style="<?= $users_new > 0 ? '' : 'display:none;' ?>"><?= $users_new ?></span>
        </a>
        <a href="reports.php" class="nav-item <?= $current_page == 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-exclamation-triangle"></i> Báo cáo vi phạm
            <span class="nav-badge badge-tone-red" id="nav-badge-reports"
                style="<?= $reports_new > 0 ? '' : 'display:none;' ?>"><?= $reports_new ?></span>
        </a>
        <div class="nav-divider-glow" aria-hidden="true"></div>
        <div class="nav-section-label">Tiện ích</div>
        <a href="../api/docs/" class="nav-item" target="_blank"><i class="fas fa-code"></i> API (Swagger)</a>
        <a href="../index.php" class="nav-item" target="_blank"><i class="fas fa-external-link-alt"></i> Xem website</a>
        <a href="../logout.php" class="nav-item nav-item-danger"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user-pill" style="cursor: pointer;"
            onclick="document.getElementById('admin-avatar-input').click()" title="Click để đổi ảnh đại diện">
            <div class="sidebar-user-avatar">
                <?php if (!empty($_SESSION['avatar'])): ?>
                    <img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar" id="admin-sidebar-avatar-img">
                <?php else: ?>
                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-meta">
                <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong>
                <span><?= htmlspecialchars($_SESSION['role'] ?? 'Quản trị viên') ?></span>
            </div>
            <input type="file" id="admin-avatar-input" accept="image/*" style="display:none"
                onchange="uploadAdminAvatar(this)">
        </div>
    </div>
</aside>

<script>
    async function uploadAdminAvatar(input) {
        if (!input.files || !input.files[0]) return;

        const formData = new FormData();
        formData.append('avatar', input.files[0]);
        formData.append('action', 'update_avatar');

        try {
            // Sử dụng SweetAlert2 để thông báo nếu có sẵn
            const res = await fetch('../api/upload_avatar.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                // Cập nhật ảnh trên UI ngay lập tức
                const newUrl = '../' + data.avatar_url + '?t=' + Date.now();
                const sidebarImg = document.getElementById('admin-sidebar-avatar-img');
                if (sidebarImg) sidebarImg.src = newUrl;

                const topbarImg = document.querySelector('.admin-avatar');
                if (topbarImg && topbarImg.tagName === 'IMG') topbarImg.src = newUrl;

                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Đã cập nhật ảnh đại diện', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                }
            } else {
                alert(data.message || 'Lỗi khi cập nhật ảnh');
            }
        } catch (e) {
            console.error(e);
            alert('Không thể kết nối máy chủ');
        }
    }

    // Logic đóng Sidebar trên Mobile
    document.addEventListener('click', function (e) {
        const sidebar = document.querySelector('.admin-sidebar');
        const toggleBtn = document.querySelector('.mobile-menu-toggle');

        // Nếu màn hình nhỏ thiết bị mobile
        if (window.innerWidth <= 768) {
            if (sidebar && sidebar.classList.contains('open')) {
                // Nếu click ra ngoài sidebar và không nằm trong khu vực nút bấm toggle.
                if (!sidebar.contains(e.target) && (!toggleBtn || !toggleBtn.contains(e.target))) {
                    sidebar.classList.remove('open');
                }
            }
        }
    });

    // Chỉnh lại nút close cho mobile nếu DOM đã load
    document.addEventListener('DOMContentLoaded', function () {
        if (window.innerWidth <= 768) {
            const cBtn = document.querySelector('.sidebar-close-btn');
            if (cBtn) cBtn.style.display = 'flex';
        }
    });

    // Cập nhật realtime cho các badge ở Sidebar trên TẤT CẢ các trang Admin
    let _lastSupportCount = -1;

    function playAdminMessageSound() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = "sine";
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
            osc.start();
            osc.stop(ctx.currentTime + 0.5);
        } catch (e) { }
    }

    function showAdminMessageToast() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Tin nhắn hỗ trợ mới',
                text: 'Bạn có tin nhắn mới từ người dùng!',
                icon: 'info',
                toast: true,
                position: 'bottom-end',
                showConfirmButton: true,
                confirmButtonText: 'Xem ngay',
                confirmButtonColor: '#10b981',
                timer: 6000,
                timerProgressBar: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'ho_tro.php';
                }
            });
        }
    }

    async function globalAdminPoll() {
        try {
            // Bypass service worker / cache to ensure fresh notifications
            const res = await fetch('../api/admin_get_notifications.php?_=' + Date.now(), { cache: 'no-store' });
            const data = await res.json();
            if (data.success) {
                const badgePosts = document.getElementById('nav-badge-posts');
                if (badgePosts && data.pending_posts !== undefined) {
                    badgePosts.textContent = data.pending_posts;
                    badgePosts.style.display = data.pending_posts > 0 ? '' : 'none';
                }

                const badgeContacts = document.getElementById('nav-badge-contacts');
                if (badgeContacts && data.contact_new !== undefined) {
                    badgeContacts.textContent = data.contact_new;
                    badgeContacts.style.display = data.contact_new > 0 ? '' : 'none';
                }

                const badgeSupport = document.getElementById('nav-badge-support');
                if (badgeSupport && data.support_new !== undefined) {
                    badgeSupport.textContent = data.support_new;
                    badgeSupport.style.display = data.support_new > 0 ? '' : 'none';

                    if (_lastSupportCount !== -1 && data.support_new > _lastSupportCount) {
                        playAdminMessageSound();
                        showAdminMessageToast();
                    }
                    _lastSupportCount = data.support_new;
                }

                const badgeReports = document.getElementById('nav-badge-reports');
                if (badgeReports && data.reports_new !== undefined) {
                    badgeReports.textContent = data.reports_new;
                    badgeReports.style.display = data.reports_new > 0 ? '' : 'none';
                }

                const badgeUsers = document.getElementById('nav-badge-users');
                if (badgeUsers && data.users_new !== undefined) {
                    badgeUsers.textContent = data.users_new;
                    badgeUsers.style.display = data.users_new > 0 ? '' : 'none';
                }

                const badgeCommunity = document.getElementById('nav-badge-community');
                if (badgeCommunity && data.community_new !== undefined) {
                    badgeCommunity.textContent = data.community_new;
                    badgeCommunity.style.display = data.community_new > 0 ? '' : 'none';
                }

                const badgeTinNhan = document.getElementById('nav-badge-tinnhan');
                if (badgeTinNhan && data.tinnhan_new !== undefined) {
                    badgeTinNhan.textContent = data.tinnhan_new;
                    badgeTinNhan.style.display = data.tinnhan_new > 0 ? '' : 'none';
                }

                // Dispatch a custom event in case specific pages (like index.php) want to reuse the payload
                window.dispatchEvent(new CustomEvent('adminNotifUpdate', { detail: data }));
            }
        } catch (e) { }
    }

    globalAdminPoll();
    setInterval(globalAdminPoll, 3000);
</script>