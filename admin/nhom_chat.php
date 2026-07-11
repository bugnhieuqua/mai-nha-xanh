<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

// Pending posts count for sidebar badge
$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai='cho_duyet'");
$pending_posts = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Nhóm Chat — Admin Mái Nhà Xanh</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&family=Lexend:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .status-badge {
            padding: 5px 12px; border-radius: 20px; font-size: .75rem; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-locked { background: #fee2e2; color: #991b1b; }
        .group-card {
            background: #fff; border: 1px solid var(--border); border-radius: 16px;
            padding: 20px; transition: all .3s; margin-bottom: 15px;
        }
        .group-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
        .group-card.locked { border-left: 4px solid #ef4444; }
        .detail-chat-bubble {
            padding: 8px 14px; border-radius: 12px; font-size: .88rem; max-width: 85%; line-height: 1.5;
        }
        .bubble-sent { background: var(--accent); color: #fff; margin-left: auto; border-radius: 12px 12px 4px 12px; }
        .bubble-received { background: #f1f5f9; color: #334155; border-radius: 12px 12px 12px 4px; }
        .bubble-system { background: #fef3c7; color: #92400e; text-align: center; margin: 0 auto; font-size: .8rem; font-weight: 600; }
        .log-item { padding: 10px 14px; border-radius: 10px; background: #f8fafc; border: 1px solid var(--border); margin-bottom: 8px; font-size: .85rem; }
        .log-violation { border-left: 3px solid #ef4444; }
        .log-safe { border-left: 3px solid #10b981; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">Nhóm Chat <span>Quản lý & Kiểm duyệt</span></div>
        </div>
        <div class="topbar-right">
            <span style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y H:i') ?></span>
            <?php if(!empty($_SESSION['avatar'])): ?><img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" class="admin-avatar" style="object-fit:cover;" alt="Avatar"><?php else: ?><div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="admin-content">
        <!-- STATS -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 30px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--accent);"><i class="fas fa-users-rectangle"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="stat-total">—</div>
                    <div class="stat-label">Tổng nhóm</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(34,197,94,0.1); color: #22c55e;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="stat-active">—</div>
                    <div class="stat-label">Đang hoạt động</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: #ef4444;"><i class="fas fa-lock"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="stat-locked">—</div>
                    <div class="stat-label">Đã bị khoá</div>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="card" style="margin-bottom: 25px; border-radius: 20px;">
            <div class="card-body" style="padding: 20px;">
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <div style="position: relative; flex: 1; min-width: 200px;">
                        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" id="filterKeyword" class="filter-input" style="width: 100%; padding-left: 45px; height: 48px; border-radius: 12px; font-size: .95rem;" 
                               placeholder="Tìm theo tên nhóm..." onkeydown="if(event.key==='Enter')loadGroups(1)">
                    </div>
                    <select id="filterStatus" class="filter-input" style="height: 48px; border-radius: 12px; min-width: 160px;" onchange="loadGroups(1)">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active">Hoạt động</option>
                        <option value="locked">Bị khoá</option>
                    </select>
                    <button class="btn btn-primary" onclick="loadGroups(1)" style="height: 48px; padding: 0 25px; border-radius: 12px;">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                    <button class="btn btn-outline" onclick="clearFilters()" style="height: 48px; padding: 0 15px; border-radius: 12px;">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- RESULTS -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding:0 5px;">
            <div id="resultCount" style="font-weight:700; color:var(--text-muted); font-size:.9rem;">— nhóm</div>
        </div>

        <!-- GROUP LIST -->
        <div id="groupList">
            <div style="text-align:center; padding:60px; color:var(--text-muted);">
                <i class="fas fa-circle-notch fa-spin fa-2x"></i>
                <p style="margin-top:15px; font-weight:600;">Đang tải danh sách nhóm...</p>
            </div>
        </div>
        <div id="paginationContainer" class="pagination" style="margin-top:30px;"></div>
    </div>
</main>

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box" style="max-width: 800px; border-radius: 24px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 20px 25px; position: sticky; top: 0; background: #fff; z-index: 10;">
            <h3 style="font-weight: 800;"><i class="fas fa-users-rectangle" style="color:var(--accent);"></i> Chi tiết nhóm</h3>
            <button class="modal-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detailContent" style="padding: 25px;"></div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
let currentPage = 1;

window.addEventListener('DOMContentLoaded', () => loadGroups(1));

function getFilters() {
    return {
        keyword: document.getElementById('filterKeyword').value.trim(),
        status: document.getElementById('filterStatus').value
    };
}
function clearFilters() {
    document.getElementById('filterKeyword').value = '';
    document.getElementById('filterStatus').value = '';
    loadGroups(1);
}

async function loadGroups(page = 1) {
    currentPage = page;
    const f = getFilters();
    const qs = new URLSearchParams({...f, page}).toString();
    const container = document.getElementById('groupList');
    container.innerHTML = '<div style="text-align:center; padding:60px;"><i class="fas fa-circle-notch fa-spin fa-2x" style="color:var(--accent);"></i></div>';

    try {
        const res = await fetch(`../api/admin_nhom_chat.php?${qs}`);
        const data = await res.json();
        if (!data.success) {
            container.innerHTML = `<div class="card" style="padding:40px; text-align:center; color:var(--danger);">${data.message}</div>`;
            return;
        }

        // Update stats
        document.getElementById('stat-total').textContent = data.stats.total;
        document.getElementById('stat-active').textContent = data.stats.active;
        document.getElementById('stat-locked').textContent = data.stats.locked;
        document.getElementById('resultCount').textContent = `${data.total} nhóm`;

        if (!data.data.length) {
            container.innerHTML = `<div class="card" style="padding:60px; text-align:center; color:var(--text-muted);"><i class="fas fa-users-slash fa-3x" style="opacity:0.1; margin-bottom:20px;"></i><p>Chưa có nhóm chat nào.</p></div>`;
            return;
        }

        let html = '';
        data.data.forEach(g => {
            const isLocked = parseInt(g.is_locked);
            const statusBadge = isLocked 
                ? '<span class="status-badge status-locked"><i class="fas fa-lock"></i> Bị khoá</span>'
                : '<span class="status-badge status-active"><i class="fas fa-check-circle"></i> Hoạt động</span>';
            
            const lastActivity = g.last_activity ? formatDate(g.last_activity) : 'Chưa có hoạt động';

            html += `
            <div class="group-card ${isLocked ? 'locked' : ''}" id="group-${g.id}">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                    <div style="display:flex; align-items:center; gap:15px; flex:1; min-width:0;">
                        <div style="width:50px; height:50px; border-radius:14px; background:${isLocked ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)'}; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0;">
                            ${isLocked ? '<i class="fas fa-lock" style="color:#ef4444;"></i>' : '<i class="fas fa-users" style="color:#10b981;"></i>'}
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:800; font-size:1.05rem; color:var(--text); margin-bottom:4px;">${escHtml(g.group_name || 'Nhóm không tên')}</div>
                            <div style="font-size:.82rem; color:var(--text-muted); display:flex; gap:15px; flex-wrap:wrap;">
                                <span><i class="fas fa-user-shield" style="margin-right:4px;"></i>${escHtml(g.owner_name)}</span>
                                <span><i class="fas fa-users" style="margin-right:4px;"></i>${g.member_count} thành viên</span>
                                <span><i class="fas fa-comment-dots" style="margin-right:4px;"></i>${g.total_messages} tin nhắn</span>
                                <span><i class="fas fa-clock" style="margin-right:4px;"></i>${lastActivity}</span>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        ${statusBadge}
                        <div style="display:flex; gap:6px;">
                            <button class="btn btn-xs btn-outline" onclick="viewDetail(${g.id})" title="Xem chi tiết"><i class="fas fa-eye"></i></button>
                            ${isLocked 
                                ? `<button class="btn btn-xs btn-primary" onclick="unlockGroup(${g.id})" title="Mở khoá"><i class="fas fa-unlock"></i></button>`
                                : `<button class="btn btn-xs btn-danger" onclick="lockGroup(${g.id})" title="Khoá nhóm"><i class="fas fa-lock"></i></button>`
                            }
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteGroup(${g.id}, '${escHtml(g.group_name)}')" title="Xoá nhóm"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
                ${isLocked && g.locked_reason ? `<div style="margin-top:12px; padding:10px 14px; background:#fef2f2; border-radius:10px; font-size:.85rem; color:#991b1b;"><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i><strong>Lý do khoá:</strong> ${escHtml(g.locked_reason)}</div>` : ''}
            </div>`;
        });

        container.innerHTML = html;
        renderPagination(data);
    } catch(e) {
        container.innerHTML = '<div class="card" style="padding:40px; text-align:center; color:var(--danger);">Lỗi kết nối máy chủ</div>';
    }
}

// ═══ LOCK GROUP ═══
function lockGroup(id) {
    Swal.fire({
        title: 'Khoá nhóm chat?',
        input: 'textarea',
        inputLabel: 'Lý do khoá nhóm',
        inputPlaceholder: 'Nhập lý do khoá nhóm...',
        inputValue: 'Vi phạm điều khoản sử dụng',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Khoá nhóm',
        confirmButtonColor: '#ef4444',
        cancelButtonText: 'Huỷ'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'lock_group');
        fd.append('conversation_id', id);
        fd.append('reason', r.value || 'Vi phạm điều khoản sử dụng');
        try {
            const res = await fetch('../api/admin_nhom_chat.php', { method: 'POST', headers: {'X-CSRF-TOKEN': CSRF_TOKEN}, body: fd });
            const d = await res.json();
            if (d.success) { Swal.fire('Đã khoá!', d.message, 'success'); loadGroups(currentPage); }
            else Swal.fire('Lỗi', d.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

// ═══ UNLOCK GROUP ═══
async function unlockGroup(id) {
    const r = await Swal.fire({ title: 'Mở khoá nhóm?', icon: 'question', showCancelButton: true, confirmButtonText: 'Mở khoá', confirmButtonColor: '#10b981' });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('action', 'unlock_group');
    fd.append('conversation_id', id);
    try {
        const res = await fetch('../api/admin_nhom_chat.php', { method: 'POST', headers: {'X-CSRF-TOKEN': CSRF_TOKEN}, body: fd });
        const d = await res.json();
        if (d.success) { Swal.fire('Đã mở khoá!', d.message, 'success'); loadGroups(currentPage); }
        else Swal.fire('Lỗi', d.message, 'error');
    } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
}

// ═══ DELETE GROUP ═══
function deleteGroup(id, name) {
    Swal.fire({
        title: `Xoá nhóm "${name}"?`,
        text: 'Toàn bộ tin nhắn và thành viên sẽ bị xoá vĩnh viễn!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xoá vĩnh viễn',
        confirmButtonColor: '#ef4444'
    }).then(async r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'delete_group');
        fd.append('conversation_id', id);
        try {
            const res = await fetch('../api/admin_nhom_chat.php', { method: 'POST', headers: {'X-CSRF-TOKEN': CSRF_TOKEN}, body: fd });
            const d = await res.json();
            if (d.success) { Swal.fire('Đã xoá!', d.message, 'success'); loadGroups(currentPage); }
            else Swal.fire('Lỗi', d.message, 'error');
        } catch(e) { Swal.fire('Lỗi', 'Kết nối thất bại', 'error'); }
    });
}

// ═══ VIEW DETAIL ═══
async function viewDetail(id) {
    const content = document.getElementById('detailContent');
    content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:var(--accent);"></i></div>';
    document.getElementById('detailModal').classList.add('open');

    const fd = new FormData();
    fd.append('action', 'get_detail');
    fd.append('conversation_id', id);

    try {
        const res = await fetch('../api/admin_nhom_chat.php', { method: 'POST', headers: {'X-CSRF-TOKEN': CSRF_TOKEN}, body: fd });
        const data = await res.json();
        if (!data.success) { content.innerHTML = `<p style="color:var(--danger);">${data.message}</p>`; return; }

        const g = data.group;
        const isLocked = parseInt(g.is_locked);

        let html = `
        <!-- Thông tin nhóm -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:25px;">
            <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Tên nhóm</label><div style="font-weight:800; font-size:1.1rem;">${escHtml(g.group_name)}</div></div>
            <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Trạng thái</label><div>${isLocked ? '<span class="status-badge status-locked"><i class="fas fa-lock"></i> Bị khoá</span>' : '<span class="status-badge status-active"><i class="fas fa-check-circle"></i> Hoạt động</span>'}</div></div>
            <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Chủ nhóm</label><div>${escHtml(g.owner_name || 'N/A')}</div></div>
            <div><label style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Ngày tạo</label><div>${formatDate(g.created_at)}</div></div>
        </div>
        ${isLocked && g.locked_reason ? `<div style="padding:12px 16px; background:#fef2f2; border-radius:12px; margin-bottom:20px; font-size:.9rem; color:#991b1b; border:1px solid #fecaca;"><i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i><strong>Lý do khoá:</strong> ${escHtml(g.locked_reason)}<br><small>Khoá bởi: ${g.locked_by || 'N/A'} — ${g.locked_at ? formatDate(g.locked_at) : ''}</small></div>` : ''}`;

        // Thành viên
        html += `<h4 style="font-weight:800; margin-bottom:12px; display:flex; align-items:center; gap:8px;"><i class="fas fa-users" style="color:var(--accent);"></i> Thành viên (${data.members.length})</h4>`;
        html += '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:10px; margin-bottom:25px;">';
        data.members.forEach(m => {
            const roleLabel = m.role === 'owner' ? '👑 Chủ nhóm' : m.role === 'admin' ? '⭐ Quản trị' : '👤 Thành viên';
            html += `<div style="display:flex; align-items:center; gap:10px; padding:10px; background:#f8fafc; border-radius:10px;">
                <div style="width:36px; height:36px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; color:#475569; flex-shrink:0;">${escHtml((m.hoten || m.username || '?').substring(0,1).toUpperCase())}</div>
                <div style="min-width:0;"><div style="font-weight:700; font-size:.88rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escHtml(m.hoten || m.username)}</div><div style="font-size:.75rem; color:var(--text-muted);">${roleLabel}</div></div>
            </div>`;
        });
        html += '</div>';

        // Tin nhắn gần nhất
        html += '<h4 style="font-weight:800; margin-bottom:12px; display:flex; align-items:center; gap:8px;"><i class="fas fa-comment-dots" style="color:var(--accent);"></i> Tin nhắn gần nhất</h4>';
        if (data.messages.length > 0) {
            html += '<div style="max-height:300px; overflow-y:auto; background:#f8fafc; border-radius:14px; padding:15px; border:1px solid var(--border); margin-bottom:25px;">';
            data.messages.forEach(msg => {
                const isSystem = msg.type === 'system';
                const senderName = msg.sender_name || 'Hệ thống';
                html += `<div style="margin-bottom:10px; display:flex; flex-direction:column; ${isSystem ? 'align-items:center;' : ''}">
                    ${!isSystem ? `<span style="font-size:.72rem; font-weight:700; color:var(--text-muted); margin-bottom:3px;">${escHtml(senderName)}</span>` : ''}
                    <div class="detail-chat-bubble ${isSystem ? 'bubble-system' : 'bubble-received'}">${escHtml(msg.content)}</div>
                    <span style="font-size:.68rem; color:#94a3b8; margin-top:3px;">${formatDate(msg.created_at)}</span>
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p style="color:var(--text-muted); font-size:.9rem; margin-bottom:25px;">Chưa có tin nhắn.</p>';
        }

        // Logs kiểm duyệt AI
        if (data.moderation_logs && data.moderation_logs.length > 0) {
            html += '<h4 style="font-weight:800; margin-bottom:12px; display:flex; align-items:center; gap:8px;"><i class="fas fa-shield-alt" style="color:#8b5cf6;"></i> Nhật ký kiểm duyệt AI</h4>';
            data.moderation_logs.forEach(log => {
                const isViol = parseInt(log.is_violation);
                const aiResp = log.ai_response ? JSON.parse(log.ai_response) : {};
                html += `<div class="log-item ${isViol ? 'log-violation' : 'log-safe'}">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="font-weight:700;">${isViol ? '⚠️ Vi phạm' : '✅ An toàn'} (${log.severity})</span>
                        <span style="font-size:.75rem; color:var(--text-muted);">${log.ai_provider || 'admin'} — ${formatDate(log.created_at)}</span>
                    </div>
                    ${aiResp.reason ? `<div style="color:#64748b;">${escHtml(aiResp.reason)}</div>` : ''}
                    <div style="font-size:.78rem; color:var(--text-muted); margin-top:4px;">Hành động: <strong>${log.action_taken}</strong> | Quy tắc: ${log.matched_rule || 'N/A'}</div>
                </div>`;
            });
        }

        content.innerHTML = html;
    } catch(e) {
        content.innerHTML = '<p style="color:var(--danger);">Lỗi kết nối máy chủ</p>';
    }
}

function renderPagination(data) {
    const pc = document.getElementById('paginationContainer');
    if (data.pages <= 1) { pc.style.display = 'none'; return; }
    pc.style.display = 'flex';
    let btns = '';
    for (let p = 1; p <= data.pages; p++) {
        btns += `<button class="page-btn ${p===data.page?'active':''}" onclick="loadGroups(${p})">${p}</button>`;
    }
    pc.innerHTML = btns;
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatDate(s) { if (!s) return '—'; const d = new Date(s); return d.toLocaleString('vi-VN', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
</script>
</body>
</html>
