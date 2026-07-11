<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

try { $db->exec("UPDATE reports SET admin_seen = 1 WHERE admin_seen = 0"); } catch (Exception $e) {}

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
    <title>Quản lý Báo cáo — Admin</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&family=Lexend:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .status-badge {
            padding: 4px 10px; border-radius: 20px; font-size: .75rem; font-weight: 700; text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-resolved { background: #dcfce3; color: #166534; }
        .status-reviewed { background: #dbeafe; color: #1e40af; }
        
        .bulk-bar {
            display: none; align-items: center; justify-content: space-between;
            padding: 15px 25px; background: #fff; border: 1px solid var(--border);
            border-radius: 16px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }
        .bulk-bar.visible { display: flex; }
        .bulk-info { display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--danger); }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">Báo cáo <span>Quản lý vi phạm</span></div>
        </div>
        <div class="topbar-right">
            <span style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y H:i') ?></span>
            <?php if(!empty($_SESSION['avatar'])): ?><img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" class="admin-avatar" style="object-fit:cover;" alt="Avatar"><?php else: ?><div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="admin-content">
        <!-- BULK BAR -->
        <div class="bulk-bar" id="bulkBar" style="display:none; justify-content: space-between; align-items: center; padding: 15px 25px; background: #fff; border: 1px solid var(--border); border-radius: 18px; margin-bottom: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.05);">
            <div style="display:flex; align-items:center; gap:25px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-circle" style="color:var(--danger); font-size:1.4rem;"></i>
                    <span id="selectedCount" style="font-weight:800; color:var(--danger); font-size:1rem;">0 báo cáo đã chọn</span>
                </div>
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; color:var(--text-muted); font-size:.95rem; background:#f1f5f9; padding:8px 15px; border-radius:10px; transition:all .2s;">
                    <input type="checkbox" id="bulkSelectAll" onchange="toggleSelectAll(this)" style="width:20px; height:20px; accent-color:var(--accent);">
                    Chọn tất cả
                </label>
            </div>
            <div style="display:flex; gap:12px;">
                <button class="btn btn-danger" style="padding: 10px 20px;" onclick="bulkDeleteReports()"><i class="fas fa-trash-alt"></i> Xóa mục chọn</button>
                <button class="btn btn-outline" style="padding: 10px 20px;" onclick="toggleSelectAll(false)">Hủy bỏ</button>
            </div>
        </div>

        <!-- LIST CARD -->
        <div class="card" style="border-radius: 24px; overflow: hidden;">
            <div class="card-header" style="background: #fff; padding: 20px 25px; border-bottom: 1px solid var(--border);">
                <div class="card-title" style="font-weight: 800; font-size: 1.1rem;"><i class="fas fa-flag"></i> Danh sách báo cáo</div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <button class="btn btn-xs btn-outline" onclick="loadReports()"><i class="fas fa-sync-alt"></i> Làm mới</button>
                    <button class="btn btn-xs btn-primary" onclick="toggleSelectAll()" style="padding: 6px 15px; border-radius: 8px;"><i class="fas fa-check-square"></i> Chọn hết</button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">
                                    <input type="checkbox" id="masterCheck" onchange="toggleSelectAll(this)" style="width:18px; height:18px;">
                                </th>
                                <th>ID</th>
                                <th>Người báo cáo</th>
                                <th>Nội dung vi phạm</th>
                                <th>Trạng thái</th>
                                <th style="text-align: right;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="reportList">
                            <tr><td colspan="6" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="paginationContainer" class="pagination" style="padding: 20px 25px;"></div>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
let currentPage = 1;
window.addEventListener('DOMContentLoaded', () => loadReports(1));

window.addEventListener('adminNotifUpdate', (e) => {
    try {
        if (e.detail && e.detail.reports_new > 0) {
            loadReports(currentPage);
        }
    } catch(err) {}
});

async function loadReports(page = 1) {
    currentPage = page;
    const qs = new URLSearchParams({ action: 'list', page }).toString();
    document.getElementById('reportList').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Đang tải dữ liệu...</td></tr>';
    
    try {
        const res = await fetch(`../api/admin_reports.php?${qs}`);
        const data = await res.json();
        if (!data.success) {
            document.getElementById('reportList').innerHTML = `<tr><td colspan="6" style="color:var(--danger);text-align:center;padding:20px;">Lỗi: ${data.message}</td></tr>`;
            return;
        }

        if (!data.data.length) {
            document.getElementById('reportList').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">Không có báo cáo vi phạm nào</td></tr>';
            return;
        }

        let html = '';
        data.data.forEach(item => {
            const isResolved = item.status === 'resolved';
            let actions = `<div style="display:flex; gap:6px; justify-content:flex-end;">`;
            if (!isResolved) {
                actions += `<button class="btn btn-xs btn-success" onclick="updateStatus(${item.id}, 'resolved')"><i class="fas fa-check"></i> Xong</button>`;
                if (item.reported_user_id) {
                    actions += `<button class="btn btn-xs btn-warning" onclick="banUser(${item.reported_user_id}, ${item.id})"><i class="fas fa-ban"></i> Ban</button>`;
                }
            }
            actions += `<button class="btn btn-xs btn-danger" onclick="deleteReport(${item.id})"><i class="fas fa-trash"></i></button>`;
            actions += `</div>`;

            html += `
            <tr id="rpt-${item.id}" style="${isResolved ? 'opacity:0.6;' : ''}">
                <td style="text-align:center;"><input type="checkbox" class="rpt-check" value="${item.id}" onchange="onCheckChange()" style="width:18px;height:18px;"></td>
                <td style="font-weight:700; color:var(--text-muted);">#${item.id}</td>
                <td>
                    <div style="font-weight:700;">${escHtml(item.reporter_name || 'Khách')}</div>
                    <div style="font-size:.75rem; color:var(--text-muted);">${formatDate(item.created_at)}</div>
                </td>
                <td style="max-width:350px;">
                    ${item.reported_username ? `<div style="font-size:.85rem;"><strong>Đối tượng:</strong> <span class="badge badge-outline">${escHtml(item.reported_username)}</span></div>` : ''}
                    ${item.reported_group_name ? `<div style="font-size:.85rem; margin-top:4px;"><strong>Nhóm:</strong> <span class="status-badge status-locked" style="background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:10px;"><i class="fas fa-users"></i> ${escHtml(item.reported_group_name)}</span></div>` : ''}
                    <div style="background:#f8fafc; padding:10px; border-radius:10px; margin-top:6px; font-size:.85rem; border:1px solid var(--border);">${escHtml(item.reason)}</div>
                </td>
                <td><span class="status-badge status-${item.status}">${item.status==='resolved'?'Đã xử lý':(item.status==='pending'?'Chờ':'Đang xem')}</span></td>
                <td style="text-align:right;">${actions}</td>
            </tr>`;
        });
        document.getElementById('reportList').innerHTML = html;
        renderPagination(data);
        if (window.globalAdminPoll) window.globalAdminPoll();
    } catch(e) {
        document.getElementById('reportList').innerHTML = '<tr><td colspan="6" style="color:var(--danger);text-align:center;padding:20px;">Lỗi kết nối máy chủ</td></tr>';
    }
}

function renderPagination(data) {
    const pc = document.getElementById('paginationContainer');
    if (data.pages <= 1) { pc.style.display = 'none'; return; }
    pc.style.display = 'flex';
    let btns = '';
    for (let p = 1; p <= data.pages; p++) {
        btns += `<button class="page-btn ${p===data.page?'active':''}" onclick="loadReports(${p})">${p}</button>`;
    }
    pc.innerHTML = btns;
}

async function updateStatus(id, status) {
    Swal.fire({
        title: 'Xác nhận xử lý?', text: 'Báo cáo này sẽ được đánh dấu là đã giải quyết.', icon: 'question', showCancelButton: true, confirmButtonText: 'Đồng ý'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action', 'update_status'); fd.append('id', id); fd.append('status', status);
        try {
            const res = await fetch('../api/admin_reports.php', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd 
            });
            const data = await res.json();
            if (data.success) loadReports(currentPage);
            else Swal.fire('Lỗi', data.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

async function banUser(userId, reportId) {
    Swal.fire({
        title: 'Khóa tài khoản?', text: 'Người dùng vi phạm sẽ bị khóa vĩnh viễn.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Khóa ngay', confirmButtonColor: 'var(--danger)'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action', 'ban_user'); fd.append('user_id', userId); fd.append('report_id', reportId);
        try {
            const res = await fetch('../api/admin_reports.php', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd 
            });
            const data = await res.json();
            if (data.success) { Swal.fire('Thành công', 'Đã khóa tài khoản người dùng.', 'success'); loadReports(currentPage); }
            else Swal.fire('Lỗi', data.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

async function deleteReport(id) {
    Swal.fire({
        title: 'Xóa báo cáo?', text: 'Dữ liệu báo cáo sẽ bị xóa vĩnh viễn.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa vĩnh viễn'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action', 'delete_report'); fd.append('id', id);
        try {
            const res = await fetch('../api/admin_reports.php', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd 
            });
            const data = await res.json();
            if (data.success) loadReports(currentPage);
            else Swal.fire('Lỗi', data.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

function toggleSelectAll(val) {
    const checks = document.querySelectorAll('.rpt-check');
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
    
    // Sync the master checkbox and bulk-bar checkbox
    const mCheck = document.getElementById('masterCheck');
    if (mCheck) mCheck.checked = newState;
    const bCheck = document.getElementById('bulkSelectAll');
    if (bCheck) bCheck.checked = newState;
    
    onCheckChange();
}

function onCheckChange() {
    const allChecks = document.querySelectorAll('.rpt-check');
    const checked = document.querySelectorAll('.rpt-check:checked');
    
    document.getElementById('selectedCount').textContent = `${checked.length} báo cáo đã chọn`;
    document.getElementById('bulkBar').classList.toggle('visible', checked.length > 0);
    
    // Sync checkboxes
    const isAll = checked.length === allChecks.length && allChecks.length > 0;
    const mCheck = document.getElementById('masterCheck');
    if (mCheck) mCheck.checked = isAll;
    const bCheck = document.getElementById('bulkSelectAll');
    if (bCheck) bCheck.checked = isAll;
}

async function bulkDeleteReports() {
    const ids = Array.from(document.querySelectorAll('.rpt-check:checked')).map(c => c.value);
    Swal.fire({
        title: `Xóa ${ids.length} báo cáo?`, text: 'Hành động này không thể hoàn tác!', icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa tất cả'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action', 'delete_many');
        ids.forEach(id => fd.append('ids[]', id));
        try {
            const res = await fetch('../api/admin_reports.php', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd 
            });
            const data = await res.json();
            if (data.success) { Swal.fire('Đã xóa', data.message, 'success'); loadReports(currentPage); }
            else Swal.fire('Lỗi', data.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function formatDate(s) { const d = new Date(s); return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'}); }
</script>
</body>
</html>
