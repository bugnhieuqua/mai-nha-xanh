<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

try { $db->exec("UPDATE users SET admin_seen = 1 WHERE admin_seen = 0"); } catch (Exception $e) {}

$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai='cho_duyet'");
$pending_posts = $stmt->fetchColumn();

// Stats for users
$stmt = $db->query("SELECT COUNT(*) FROM users");
$total_users = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Người dùng — Admin</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">Người dùng <span>Quản trị viên</span></div>
        </div>
        <div class="topbar-right" style="display:flex;align-items:center;gap:16px;">
            <span style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y') ?></span>
            <?php if(!empty($_SESSION['avatar'])): ?><img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" class="admin-avatar" style="object-fit:cover;" alt="Avatar"><?php else: ?><div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="admin-content">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-shield"></i> Danh sách thành viên</div>
                <div style="display: flex; gap: 10px;">
                    <div class="search-box" style="position:relative;">
                        <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.9rem;"></i>
                        <input type="text" id="filterKeyword" class="filter-input" style="padding-left:35px;width:250px;" placeholder="Tìm tên hoặc email..." onkeydown="if(event.key==='Enter')loadUsers()">
                    </div>
                    <button class="btn btn-primary" onclick="loadUsers()"><i class="fas fa-filter"></i> Lọc</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Thành viên</th>
                            <th>Email</th>
                            <th>Ngày tham gia</th>
                            <th>Trạng thái</th>
                            <th style="text-align:right;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="userList">
                        <tr><td colspan="6" style="text-align: center; padding: 40px;">Đang tải dữ liệu...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer" class="pagination" style="display:none;"></div>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
let currentPage = 1;
window.addEventListener('DOMContentLoaded', () => loadUsers(1));

async function loadUsers(page = 1) {
    currentPage = page;
    const kw = document.getElementById('filterKeyword').value.trim();
    const qs = new URLSearchParams({ action: 'list', keyword: kw, page }).toString();

    const tbody = document.getElementById('userList');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Đang tải...</td></tr>';
    
    try {
        const res = await fetch(`../api/admin_users.php?${qs}`);
        const data = await res.json();
        
        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="6" style="color:var(--danger);text-align:center;padding:40px;">Lỗi: ${data.message}</td></tr>`;
            return;
        }

        if (data.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);"><i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3;"></i> Không có người dùng nào khớp</td></tr>`;
            return;
        }

        let html = '';
        data.data.forEach(item => {
            const isBanned = item.status === 'banned';
            const statusBadge = isBanned 
                ? '<span class="badge badge-rejected"><i class="fas fa-user-slash"></i> Đã khoá</span>'
                : '<span class="badge badge-approved"><i class="fas fa-check-circle"></i> Đang hoạt động</span>';
                
            const banAction = isBanned
                ? `<button class="btn btn-xs btn-primary" onclick="toggleBan(${item.id}, 'active')"><i class="fas fa-unlock"></i> Mở khoá</button>`
                : `<button class="btn btn-xs btn-danger" onclick="toggleBan(${item.id}, 'banned')"><i class="fas fa-user-lock"></i> Khoá tài khoản</button>`;

            const joinDate = item.created_at ? new Date(item.created_at).toLocaleDateString('vi-VN') : '—';
            const initial = item.username ? item.username.charAt(0).toUpperCase() : '?';

            html += `
            <tr style="${isBanned ? 'background: rgba(239, 68, 68, 0.08);' : ''}">
                <td>#${item.id}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#64748b,#94a3b8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.75rem;">${initial}</div>
                        <div style="font-weight:600;color:var(--text);">${escHtml(item.username)}</div>
                    </div>
                </td>
                <td style="color:var(--text-muted);font-size:.85rem;">${escHtml(item.email)}</td>
                <td style="font-size:.85rem;color:var(--text-muted);">${joinDate}</td>
                <td>${statusBadge}</td>
                <td style="text-align:right;">${banAction}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
        renderPagination(data);
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="6" style="color:var(--danger);text-align:center;padding:40px;">Lỗi kết nối máy chủ</td></tr>`;
    }
}

function renderPagination(data) {
    const pc = document.getElementById('paginationContainer');
    if (data.pages <= 1) {
        pc.style.display = 'none';
        return;
    }
    pc.style.display = 'flex';
    let btns = '';
    for (let p = 1; p <= data.pages; p++) {
        btns += `<button class="page-btn ${p===data.page?'active':''}" onclick="loadUsers(${p})">${p}</button>`;
    }
    pc.innerHTML = btns;
}

async function toggleBan(id, newStatus) {
    const isBan = newStatus === 'banned';
    const result = await Swal.fire({
        title: isBan ? 'Khoá tài khoản?' : 'Mở khoá tài khoản?',
        text: isBan ? "Người dùng này sẽ không thể đăng nhập hoặc đăng bài!" : "Người dùng này sẽ được khôi phục quyền truy cập.",
        icon: isBan ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: isBan ? '#ef4444' : '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: isBan ? 'Vâng, Khoá ngay!' : 'Vâng, Mở khoá!',
        cancelButtonText: 'Huỷ bỏ'
    });

    if (result.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'toggle_ban');
        fd.append('user_id', id);
        fd.append('status', newStatus);

        try {
            const res = await fetch('../api/admin_users.php', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd 
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: isBan ? 'Đã khoá!' : 'Đã mở khoá!',
                    timer: 1500,
                    showConfirmButton: false
                });
                loadUsers(currentPage);
            } else {
                Swal.fire('Lỗi!', data.message, 'error');
            }
        } catch(e) {
            Swal.fire('Lỗi!', 'Không thể kết nối API', 'error');
        }
    }
}

function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>
</body>
</html>
