<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

try {
    $db->exec("UPDATE lienhe SET admin_seen = 1 WHERE admin_seen = 0");
} catch (Exception $e) {
}

// Pending posts count for sidebar badge
$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai='cho_duyet'");
$pending_posts = $stmt->fetchColumn();

// Stats for contacts
$stmt = $db->query("SELECT COUNT(*) FROM lienhe");
$total_contacts = (int) $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên Hệ — Admin Mái Nhà Xanh</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link
        href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&family=Lexend:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .bulk-bar {
            display: none;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .bulk-bar.visible {
            display: flex;
        }

        .bulk-bar span {
            font-weight: 700;
            color: var(--danger);
            font-size: .9rem;
        }

        .contact-card {
            border-radius: 16px;
            padding: 20px;
            transition: all .3s;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .contact-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .contact-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .contact-name {
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--text);
        }

        .contact-meta {
            font-size: .85rem;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .contact-body {
            font-size: .9rem;
            color: var(--text);
            line-height: 1.5;
            background: #f8fafc;
            padding: 12px;
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="mobile-menu-toggle"
                    onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i
                        class="fas fa-bars"></i></button>
                <div class="topbar-title">Hệ thống <span>Liên hệ & Góp ý</span></div>
            </div>
            <div class="topbar-right">
                <span style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y H:i') ?></span>
                <?php if (!empty($_SESSION['avatar'])): ?><img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>"
                        class="admin-avatar" style="object-fit:cover;" alt="Avatar"><?php else: ?>
                    <div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-content">
            <!-- STATS -->
            <div class="stats-grid"
                style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 25px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--accent);"><i
                            class="fas fa-inbox"></i></div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $total_contacts ?></div>
                        <div class="stat-label">Tổng tin nhắn</div>
                    </div>
                </div>
            </div>

            <!-- SEARCH -->
            <div class="card" style="margin-bottom: 25px; border-radius: 20px;">
                <div class="card-body" style="padding: 20px;">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div style="position: relative; flex: 1;">
                            <i class="fas fa-search"
                                style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                            <input type="text" id="filterKeyword" class="filter-input"
                                style="width: 100%; padding-left: 45px; height: 48px; border-radius: 12px; font-size: .95rem;"
                                placeholder="Tìm theo tên, email, sđt..."
                                onkeydown="if(event.key==='Enter')loadContacts(1)">
                        </div>
                        <button class="btn btn-primary" onclick="loadContacts(1)"
                            style="height: 48px; padding: 0 25px; border-radius: 12px;">
                            <i class="fas fa-filter"></i> Lọc dữ liệu
                        </button>
                        <button class="btn btn-outline" onclick="clearFilters()"
                            style="height: 48px; padding: 0 15px; border-radius: 12px;">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button class="btn btn-primary" onclick="toggleSelectAll()"
                            style="height: 48px; padding: 0 25px; border-radius: 12px; margin-left: 10px;">
                            <i class="fas fa-check-square"></i> Chọn hết
                        </button>
                    </div>
                </div>
            </div>

            <!-- BULK BAR -->
            <div class="bulk-bar" id="bulkBar"
                style="display:none; justify-content: space-between; align-items: center; padding: 15px 25px; margin-bottom: 25px;">
                <div style="display:flex; align-items:center; gap:25px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-check-circle" style="color:var(--accent); font-size:1.4rem;"></i>
                        <span id="bulkCount" style="font-weight:800; color:var(--danger); font-size:1rem;">0 đã
                            chọn</span>
                    </div>
                    <label
                        style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; color:var(--text-muted); font-size:.95rem; background:#f1f5f9; padding:8px 15px; border-radius:10px; transition:all .2s;">
                        <input type="checkbox" id="bulkSelectAll" onchange="toggleSelectAll(this)"
                            style="width:20px; height:20px; accent-color:var(--accent);">
                        Chọn tất cả
                    </label>
                </div>
                <div style="display:flex; gap:12px;">
                    <button class="btn btn-danger" style="padding: 10px 20px;" onclick="deleteSelected()"><i
                            class="fas fa-trash-alt"></i> Xóa mục chọn</button>
                    <button class="btn btn-outline" style="padding: 10px 20px;" onclick="clearSelection()">Hủy
                        bỏ</button>
                </div>
            </div>

            <!-- TABLE -->
            <div class="card" style="border-radius: 20px; overflow: hidden;">
                <div class="card-header"
                    style="padding: 20px 25px; border-bottom: 1px solid var(--glass-green-border-soft);">
                    <div class="card-title" style="font-weight: 800; font-size: 1.1rem;"><i class="fas fa-list-ul"></i>
                        Danh sách tin nhắn</div>
                    <div id="resultCount" style="font-size: .85rem; font-weight: 600; color: var(--text-muted);">— tin
                        nhắn</div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px; text-align: center;">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"
                                            style="width:18px; height:18px;">
                                    </th>
                                    <th>Người gửi</th>
                                    <th>Thông tin liên hệ</th>
                                    <th>Tiêu đề</th>
                                    <th style="text-align: right;">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="contactList">
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px;"><i
                                            class="fas fa-spinner fa-spin"></i> Đang tải...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="paginationContainer" class="pagination" style="padding: 20px 25px;"></div>
            </div>
        </div>
    </main>

    <!-- VIEW MODAL (Legacy, but upgraded style) -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-box" style="max-width: 650px; border-radius: 24px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 20px 25px;">
                <h3 style="font-weight: 800;"><i class="fas fa-envelope-open-text" style="color:var(--accent);"></i> Nội
                    dung liên hệ</h3>
                <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="viewContent" style="padding: 25px;"></div>
            <div class="modal-footer"
                style="padding: 20px 25px; background: #f8fafc; border-top: 1px solid var(--border); border-radius: 0 0 24px 24px;">
                <button class="btn btn-outline" onclick="closeModal('viewModal')">Đóng</button>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-danger" onclick="deleteFromModal()"><i class="fas fa-trash"></i> Xóa</button>
                    <button class="btn btn-success" onclick="openChatFromModal()"><i class="fas fa-reply"></i> Phản
                        hồi</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
        let currentPage = 1;
        let pendingDeleteIds = [];
        const currentData = {};

        window.addEventListener('DOMContentLoaded', () => loadContacts(1));

        function getFilters() { return { keyword: document.getElementById('filterKeyword').value.trim() }; }
        function clearFilters() { document.getElementById('filterKeyword').value = ''; loadContacts(1); }

        async function loadContacts(page = 1) {
            currentPage = page;
            const f = getFilters();
            const qs = new URLSearchParams({ ...f, page }).toString();
            document.getElementById('contactList').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Đang tải dữ liệu...</td></tr>';
            clearSelection();

            try {
                // Bypass service worker cache to ensure we get the latest data
                const res = await fetch(`../api/admin_lien_he.php?${qs}&_=${Date.now()}`, { cache: 'no-store' });
                const data = await res.json();
                if (!data.success) {
                    document.getElementById('contactList').innerHTML = `<tr><td colspan="5" style="color:var(--danger);text-align:center;padding:20px;">Lỗi: ${data.message}</td></tr>`;
                    return;
                }

                // Update result text and the top stat card so total count reflects changes immediately
                document.getElementById('resultCount').textContent = `${data.total} tin nhắn`;
                const statEl = document.querySelector('.stat-number');
                if (statEl) statEl.textContent = data.total;

                if (!data.data.length) {
                    document.getElementById('contactList').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">Không tìm thấy tin nhắn nào</td></tr>';
                    return;
                }

                let html = '';
                data.data.forEach(item => {
                    currentData[item.id] = item;
                    html += `
            <tr id="row-lh-${item.id}">
                <td style="text-align:center;"><input type="checkbox" class="row-check" value="${item.id}" onchange="onCheckChange()" style="width:18px;height:18px;"></td>
                <td>
                    <div style="font-weight:700;">${escHtml(item.hoten)}</div>
                    ${item.admin_seen == 0 ? '<span class="badge badge-danger" style="font-size:10px; padding:2px 6px;">MỚI</span>' : ''}
                </td>
                <td>
                    <div style="font-size:.85rem;"><i class="fas fa-envelope" style="width:16px; color:var(--text-muted);"></i> ${escHtml(item.email)}</div>
                    <div style="font-size:.85rem;"><i class="fas fa-phone" style="width:16px; color:var(--text-muted);"></i> ${escHtml(item.sodienthoai)}</div>
                </td>
                <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escHtml(item.tieude)}</td>
                <td style="text-align:right;">
                    <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <button class="btn btn-xs btn-outline" onclick="viewContact(${item.id})">Xem</button>
                        <button class="btn btn-xs btn-primary" onclick="openChat(${item.id})" title="Phản hồi"><i class="fas fa-reply"></i></button>
                        <button class="btn btn-xs btn-danger" onclick="deleteSingle(${item.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`;
                });
                document.getElementById('contactList').innerHTML = html;
                renderPagination(data);
            } catch (e) {
                document.getElementById('contactList').innerHTML = '<tr><td colspan="5" style="color:var(--danger);text-align:center;padding:20px;">Lỗi kết nối máy chủ</td></tr>';
            }
        }

        // React to global notification updates (sidebar polling) so list updates immediately
        window.addEventListener('adminNotifUpdate', (ev) => {
            try {
                const d = ev && ev.detail ? ev.detail : {};
                if (typeof d.contact_new !== 'undefined') {
                    // refresh list if counts differ
                    const statEl = document.querySelector('.stat-number');
                    const currentTotal = statEl ? parseInt(statEl.textContent) : NaN;
                    if (isNaN(currentTotal) || d.contact_new !== currentTotal) {
                        loadContacts(1);
                    }
                }
            } catch (err) { console.error('adminNotifUpdate handler error', err); }
        });

        function toggleSelectAll(val) {
            const checks = document.querySelectorAll('.row-check');
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
            onCheckChange();
        }

        function onCheckChange() {
            const allChecks = document.querySelectorAll('.row-check');
            const checked = document.querySelectorAll('.row-check:checked');
            const bar = document.getElementById('bulkBar');

            document.getElementById('bulkCount').textContent = `${checked.length} đã chọn`;
            bar.classList.toggle('visible', checked.length > 0);

            // Sync the "Select All" checkboxes
            const isAll = checked.length === allChecks.length && allChecks.length > 0;
            document.getElementById('selectAll').checked = isAll;
            const bulkAll = document.getElementById('bulkSelectAll');
            if (bulkAll) bulkAll.checked = isAll;
        }

        function clearSelection() {
            document.querySelectorAll('.row-check').forEach(c => c.checked = false);
            document.getElementById('selectAll').checked = false;
            const bulkAll = document.getElementById('bulkSelectAll');
            if (bulkAll) bulkAll.checked = false;
            document.getElementById('bulkBar').classList.remove('visible');
        }

        function deleteSingle(id) {
            Swal.fire({
                title: 'Xóa tin nhắn?', text: 'Hành động này không thể hoàn tác.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa ngay', confirmButtonColor: 'var(--danger)'
            }).then(r => { if (r.isConfirmed) confirmDelete([id]); });
        }

        function deleteSelected() {
            const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(c => parseInt(c.value)).filter(Boolean);
            if (!ids.length) {
                Swal.fire('Lỗi', 'Chưa chọn mục nào để xóa', 'warning');
                return;
            }
            Swal.fire({
                title: `Xóa ${ids.length} mục?`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa tất cả'
            }).then(r => { if (r.isConfirmed) confirmDelete(ids); });
        }

        async function confirmDelete(ids) {
            console.log('confirmDelete payload:', ids);
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('action', 'delete');
            ids.forEach(id => fd.append('ids[]', id));
            try {
                Swal.fire({ title: 'Đang xóa...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const res = await fetch('../api/admin_lien_he.php', { method: 'POST', body: fd });
                const text = await res.text();
                let d;
                try { d = JSON.parse(text); } catch (err) { d = { success: false, message: 'Invalid response: ' + text }; }
                Swal.close();
                if (d.success) {
                    Swal.fire('Đã xóa', d.message, 'success');
                    loadContacts(currentPage);
                } else {
                    Swal.fire('Lỗi', d.message || 'Xảy ra lỗi', 'error');
                    console.error('Delete failed:', d, text);
                }
            } catch (e) {
                Swal.fire('Lỗi', 'Kết nối thất bại: ' + (e.message || e), 'error');
                console.error(e);
            }
        }

        function renderPagination(data) {
            const pc = document.getElementById('paginationContainer');
            if (data.pages <= 1) { pc.style.display = 'none'; return; }
            pc.style.display = 'flex';
            let btns = '';
            for (let p = 1; p <= data.pages; p++) {
                btns += `<button class="page-btn ${p === data.page ? 'active' : ''}" onclick="loadContacts(${p})">${p}</button>`;
            }
            pc.innerHTML = btns;
        }

        let _currentViewId = null;
        function viewContact(id) {
            const item = currentData[id];
            if (!item) return;
            _currentViewId = id;
            if (item.admin_seen == 0) markAsSeen(id);
            document.getElementById('viewContent').innerHTML = `
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
            <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Họ tên</label><div style="font-weight:700;">${escHtml(item.hoten)}</div></div>
            <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Thời gian</label><div>${item.created_at || '—'}</div></div>
            <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Email</label><div>${escHtml(item.email)}</div></div>
            <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Số điện thoại</label><div>${escHtml(item.sodienthoai)}</div></div>
        </div>
        <div style="margin-bottom:20px;"><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Tiêu đề</label><div style="font-weight:700; font-size:1.1rem;">${escHtml(item.tieude)}</div></div>
        <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Nội dung tin nhắn</label><div style="background:#f8fafc; padding:20px; border-radius:12px; margin-top:10px; line-height:1.6; border:1px solid var(--border);">${escHtml(item.noidung)}</div></div>
    `;
            document.getElementById('viewModal').classList.add('open');
        }

        function deleteFromModal() { if (_currentViewId) { closeModal('viewModal'); deleteSingle(_currentViewId); } }
        function openChatFromModal() { if (_currentViewId) openChat(_currentViewId); }

        function openChat(id) {
            const item = currentData[id];
            if (!item) return;
            const params = new URLSearchParams();
            params.set('session_id', item.session_id || ('lienhe_' + item.id));
            if (item.email) params.set('email', item.email);
            if (item.hoten) params.set('ho_ten', item.hoten);
            window.location.href = 'ho_tro.php?' + params.toString();
        }

        async function markAsSeen(id) {
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('action', 'mark_seen');
            fd.append('id', id);
            try {
                await fetch('../api/admin_lien_he.php', {
                    method: 'POST',
                    body: fd
                });
                const row = document.getElementById(`row-lh-${id}`);
                if (row) { const b = row.querySelector('.badge-danger'); if (b) b.remove(); }
            } catch (e) { }
        }

        function closeModal(id) { document.getElementById(id).classList.remove('open'); }
        function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    </script>
</body>

</html>