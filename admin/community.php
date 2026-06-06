<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

try { $db->exec("UPDATE community_posts SET admin_seen = 1 WHERE admin_seen = 0"); } catch (Exception $e) {}

$pending_posts = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai='cho_duyet'");
    if($stmt) $pending_posts = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$pending_reports = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM reports WHERE status='pending'");
    if($stmt) $pending_reports = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Cộng Đồng — Admin</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&family=Lexend:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .admin-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 15px; }
        .admin-media-item { width: 100%; aspect-ratio: 1; border-radius: 12px; object-fit: cover; border: 1px solid var(--border); cursor: pointer; transition: transform .2s; }
        .admin-media-item:hover { transform: scale(1.03); }
        .admin-video-item { width: 100%; border-radius: 12px; border: 1px solid var(--border); margin-top: 15px; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">Cộng đồng <span>Giám sát nội dung</span></div>
        </div>
        <div class="topbar-right">
            <span style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y H:i') ?></span>
            <?php if(!empty($_SESSION['avatar'])): ?><img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" class="admin-avatar" style="object-fit:cover;" alt="Avatar"><?php else: ?><div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="admin-content">
        <!-- BULK BAR -->
        <div class="bulk-bar" id="bulkBar" style="display:none; justify-content: space-between; align-items: center; padding: 15px 25px; background: #fff; border: 1px solid var(--border); border-radius: 16px; margin-bottom: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.05);">
            <div style="display:flex; align-items:center; gap:25px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-check-circle" style="color:var(--accent); font-size:1.4rem;"></i>
                    <span id="comSelectedCount" style="font-weight:800; color:var(--danger); font-size:1rem;">0 bài viết đã chọn</span>
                </div>
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; color:var(--text-muted); font-size:.95rem; background:#f1f5f9; padding:8px 15px; border-radius:10px; transition:all .2s;">
                    <input type="checkbox" id="bulkSelectAll" onchange="toggleSelectAllPosts(this)" style="width:20px; height:20px; accent-color:var(--accent);">
                    Chọn tất cả
                </label>
            </div>
            <div style="display:flex; gap:12px;">
                <button class="btn btn-danger" style="padding: 10px 20px;" onclick="bulkDeletePosts()"><i class="fas fa-trash-alt"></i> Xóa mục chọn</button>
                <button class="btn btn-outline" style="padding: 10px 20px;" onclick="toggleSelectAllPosts(false)">Hủy bỏ</button>
            </div>
        </div>

        <div class="card" style="border:none; background:transparent;">
            <div class="card-header" style="background:transparent; padding:0 0 20px 0; border:none; display:flex; justify-content:space-between; align-items:center;">
                <div class="card-title" style="font-weight: 800; font-size: 1.2rem;"><i class="fas fa-comments"></i> Dòng thời gian</div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <button class="btn btn-xs btn-outline" onclick="loadPosts()"><i class="fas fa-sync-alt"></i> Làm mới</button>
                    <button class="btn btn-xs btn-primary" id="comSelectAllBtn" onclick="toggleSelectAllPosts()" style="padding: 6px 15px; border-radius: 8px;"><i class="fas fa-check-square"></i> Chọn hết</button>
                </div>
            </div>
            
            <div id="postsContainer">
                <div style="text-align: center; padding: 60px; color: var(--text-muted);">
                    <i class="fas fa-circle-notch fa-spin fa-2x"></i>
                    <p style="margin-top: 15px; font-weight:600;">Đang tải dữ liệu cộng đồng...</p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
window.addEventListener('DOMContentLoaded', loadPosts);

window.addEventListener('adminNotifUpdate', (e) => {
    try {
        if (e.detail && e.detail.community_new > 0) {
            loadPosts();
        }
    } catch(err) {}
});

async function loadPosts() {
    try {
        const res = await fetch('../api/community.php?action=list_posts');
        const data = await res.json();
        const container = document.getElementById('postsContainer');
        if (!data.success) { container.innerHTML = `<div class="card" style="padding:40px; text-align:center; color:var(--danger);">${data.message}</div>`; return; }
        if (window.globalAdminPoll) {
            window.globalAdminPoll();
        }
        if (!data.data.length) { container.innerHTML = `<div class="card" style="padding:60px; text-align:center; color:var(--text-muted);"><i class="fas fa-ghost fa-3x" style="opacity:0.1; margin-bottom:20px;"></i><p>Chưa có bài viết nào.</p></div>`; return; }

        let html = '';
        data.data.forEach(post => {
            html += `
            <div class="post-card" id="post-${post.id}">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <input type="checkbox" class="post-check" value="${post.id}" onchange="onComCheckChange()" style="width:20px; height:20px;">
                        <div style="width: 45px; height: 45px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem;">
                            ${(post.username||'K').charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div style="font-weight: 800; color: var(--text); font-size: 1.05rem;">${escHtml(post.username)}</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">${formatDate(post.created_at)}</div>
                        </div>
                    </div>
                    <button onclick="deletePost(${post.id})" class="btn btn-xs btn-outline-danger" title="Xóa bài viết">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                
                <div style="color: var(--text); line-height: 1.6; margin-bottom: 15px; font-size: 1rem;">${escHtml(post.content)}</div>
                
                ${(function(){
                    let media = '';
                    try {
                        let imgs = post.images ? JSON.parse(post.images) : [];
                        if (imgs.length > 0) {
                            media += '<div class="admin-media-grid">';
                            imgs.forEach(src => { media += `<img src="../${src}" class="admin-media-item" onclick="window.open('../${src}', '_blank')">`; });
                            media += '</div>';
                        }
                    } catch(e) {}
                    if (post.video) media += `<video controls src="../${post.video}" class="admin-video-item"></video>`;
                    return media;
                })()}
                
                <div style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 15px; display:flex; justify-content:space-between;">
                    <button onclick="toggleComments(${post.id})" class="btn btn-xs btn-outline" style="border:none; color:var(--accent); font-weight:700;">
                        <i class="fas fa-comment-dots"></i> Bình luận (${post.comment_count})
                    </button>
                    <span style="font-size:.75rem; color:var(--text-muted);">ID: #${post.id}</span>
                </div>
                
                <div id="comments-${post.id}" style="display: none; margin-top: 15px; background: #f8fafc; border-radius: 12px; padding: 15px;">
                    <div id="commentList-${post.id}"></div>
                </div>
            </div>`;
        });
        container.innerHTML = html;
    } catch(e) { document.getElementById('postsContainer').innerHTML = `<div class="card" style="padding:40px; text-align:center; color:var(--danger);">Lỗi kết nối máy chủ</div>`; }
}

async function deletePost(id) {
    Swal.fire({
        title: 'Xóa bài viết?', text: 'Hành động này sẽ gỡ bài viết vĩnh viễn.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa ngay', confirmButtonColor: 'var(--danger)'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action', 'delete_post'); fd.append('id', id);
        try {
            const res = await fetch('../api/community.php?action=delete_post', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd 
            });
            const data = await res.json();
            if (data.success) { Swal.fire('Đã xóa', 'Bài viết đã được gỡ bỏ.', 'success'); loadPosts(); }
            else Swal.fire('Lỗi', data.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

async function deleteComment(id, postId) {
    Swal.fire({
        title: 'Xóa bình luận?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action', 'delete_comment'); fd.append('id', id);
        try {
            const res = await fetch('../api/community.php?action=delete_comment', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd 
            });
            const data = await res.json();
            if (data.success) { loadComments(postId); loadPosts(); }
            else Swal.fire('Lỗi', data.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

function toggleComments(postId) {
    const section = document.getElementById(`comments-${postId}`);
    if (section.style.display === 'none') { section.style.display = 'block'; loadComments(postId); }
    else section.style.display = 'none';
}

async function loadComments(postId) {
    const list = document.getElementById(`commentList-${postId}`);
    list.innerHTML = '<div style="text-align:center; padding:10px; font-size:.85rem; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i></div>';
    try {
        const res = await fetch(`../api/community.php?action=list_comments&post_id=${postId}`);
        const data = await res.json();
        if (!data.success || !data.data.length) { list.innerHTML = '<div style="text-align:center; padding:10px; font-size:.85rem; color:var(--text-muted);">Chưa có bình luận nào.</div>'; return; }
        
        let html = '';
        data.data.forEach(c => {
            html += `
            <div style="display: flex; gap: 12px; margin-bottom: 12px; background:#fff; padding:12px; border-radius:10px; border:1px solid var(--border);">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; color: #334155; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem; flex-shrink: 0;">
                    ${(c.username||'K').charAt(0).toUpperCase()}
                </div>
                <div style="flex: 1; min-width:0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px; align-items:center;">
                        <span style="font-weight: 700; font-size: 0.85rem; color: var(--text);">${escHtml(c.username)}</span>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span style="font-size: 0.7rem; color: var(--text-muted);">${formatDate(c.created_at)}</span>
                            <button onclick="deleteComment(${c.id}, ${postId})" style="background:none; border:none; color:var(--danger); font-size:0.75rem; cursor:pointer;" title="Xóa bình luận"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--text); line-height:1.4;">${escHtml(c.content)}</div>
                </div>
            </div>`;
        });
        list.innerHTML = html;
    } catch(e) { list.innerHTML = '<div style="color:var(--danger); text-align:center;">Lỗi tải bình luận</div>'; }
}

function toggleSelectAllPosts(val) {
    const checks = document.querySelectorAll('.post-check');
    if (checks.length === 0) return;

    let newState;
    if (typeof val === 'boolean') {
        newState = val;
    } else if (val && typeof val.checked !== 'undefined') {
        newState = val.checked;
    } else {
        const anyUnchecked = Array.from(checks).some(c => !c.checked);
        newState = anyUnchecked;
    }

    checks.forEach(c => c.checked = newState);
    const bulkAll = document.getElementById('bulkSelectAll');
    if (bulkAll) bulkAll.checked = newState;
    onComCheckChange();
}

function onComCheckChange() {
    const checked = document.querySelectorAll('.post-check:checked');
    document.getElementById('comSelectedCount').textContent = `${checked.length} bài viết đã chọn`;
    document.getElementById('bulkBar').classList.toggle('visible', checked.length > 0);
}

async function bulkDeletePosts() {
    const ids = Array.from(document.querySelectorAll('.post-check:checked')).map(c => c.value);
    Swal.fire({
        title: `Xóa ${ids.length} bài viết?`, text: 'Dữ liệu sẽ bị gỡ vĩnh viễn!', icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa tất cả'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action', 'delete_posts_bulk');
        ids.forEach(id => fd.append('ids[]', id));
        try {
            const res = await fetch('../api/community.php', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd 
            });
            const data = await res.json();
            if (data.success) { Swal.fire('Thành công', data.message, 'success'); loadPosts(); }
            else Swal.fire('Lỗi', data.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function formatDate(s) { const d = new Date(s); return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'}); }
</script>
</body>
</html>
