<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

// Pending posts count for sidebar badge
$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai='cho_duyet'");
$pending_posts = $stmt->fetchColumn();

// Stats
$stmt = $db->query("SELECT COUNT(*) FROM chatbot_history WHERE DATE(created_at)=CURDATE()");
$today_msgs = (int)$stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(DISTINCT session_id) FROM chatbot_history WHERE DATE(created_at)=CURDATE()");
$today_sessions = (int)$stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(*) FROM chatbot_history");
$total_msgs = (int)$stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(DISTINCT session_id) FROM chatbot_history");
$total_sessions = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử Tin nhắn — Admin Mái Nhà Xanh</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&family=Lexend:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .stat-card {
            background: #fff; border: 1px solid var(--border); border-radius: 20px;
            padding: 22px; display: flex; align-items: center; gap: 18px; transition: all .3s;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .stat-icon {
            width: 55px; height: 55px; border-radius: 16px; display: flex; align-items: center;
            justify-content: center; font-size: 1.4rem; flex-shrink: 0;
        }
        
        .chat-session-block {
            background: #fff; border: 1px solid var(--border); border-radius: 20px;
            margin-bottom: 20px; overflow: hidden; transition: all .3s;
        }
        .chat-session-block.active { border-color: var(--accent); box-shadow: 0 5px 20px rgba(16,185,129,0.05); }
        
        .bulk-bar {
            display: none; align-items: center; justify-content: space-between;
            padding: 15px 25px; background: #fff; border: 1px solid var(--border);
            border-radius: 18px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.03);
        }
        .bulk-bar.visible { display: flex; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">Tin nhắn <span>Lịch sử AI</span></div>
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
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--accent);"><i class="fas fa-comment-dots"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= $today_msgs ?></div>
                    <div class="stat-label">Tin nhắn hôm nay</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= $today_sessions ?></div>
                    <div class="stat-label">Phiên mới</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(139,92,246,0.1); color: #8b5cf6;"><i class="fas fa-database"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= $total_msgs ?></div>
                    <div class="stat-label">Tổng tin nhắn</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: #f59e0b;"><i class="fas fa-robot"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= $total_sessions ?></div>
                    <div class="stat-label">Tổng phiên AI</div>
                </div>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="card" style="margin-bottom: 25px; border-radius: 20px;">
            <div class="card-body" style="padding: 20px;">
                <div class="admin-filter-grid" style="grid-template-columns: 1fr auto auto auto; gap: 15px; align-items: flex-end;">
                    <div>
                        <label style="font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; display:block;">Nội dung</label>
                        <input type="text" id="filterKeyword" class="filter-input" style="width: 100%; border-radius: 12px;" placeholder="Tìm trong tin nhắn..." onkeydown="if(event.key==='Enter')loadChats()">
                    </div>
                    <div>
                        <label style="font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; display:block;">Từ ngày</label>
                        <input type="date" id="filterFrom" class="filter-input" style="border-radius: 12px;">
                    </div>
                    <div>
                        <label style="font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; display:block;">Đến ngày</label>
                        <input type="date" id="filterTo" class="filter-input" style="border-radius: 12px;">
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-primary" onclick="loadChats(1)" style="height:48px; padding:0 25px; border-radius:12px;"><i class="fas fa-search"></i> Tìm kiếm</button>
                        <button class="btn btn-outline" onclick="clearFilters()" style="height:48px; border-radius:12px;"><i class="fas fa-undo"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- BULK BAR -->
        <div class="bulk-bar" id="bulkBar" style="display:none; justify-content: space-between; align-items: center; padding: 15px 25px; background: #fff; border: 1px solid var(--border); border-radius: 18px; margin-bottom: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.05);">
            <div style="display:flex; align-items:center; gap:25px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-check-circle" style="color:var(--accent); font-size:1.4rem;"></i>
                    <span id="selectedCount" style="font-weight:800; color:var(--danger); font-size:1rem;">0 phiên chat đã chọn</span>
                </div>
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; color:var(--text-muted); font-size:.95rem; background:#f1f5f9; padding:8px 15px; border-radius:10px; transition:all .2s;">
                    <input type="checkbox" id="bulkSelectAll" onchange="toggleSelectAllSessions(this)" style="width:20px; height:20px; accent-color:var(--accent);">
                    Chọn tất cả
                </label>
            </div>
            <div style="display:flex; gap:12px;">
                <button class="btn btn-danger" style="padding: 10px 20px;" onclick="bulkDeleteSessions()"><i class="fas fa-trash-alt"></i> Xóa vĩnh viễn</button>
                <button class="btn btn-outline" style="padding: 10px 20px;" onclick="toggleSelectAllSessions(false)">Hủy bỏ</button>
            </div>
        </div>

        <!-- RESULTS -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding:0 5px;">
            <div id="resultCount" style="font-weight:700; color:var(--text-muted); font-size:.9rem;">— kết quả</div>
            <div style="display:flex; gap:10px;">
                <button class="btn btn-xs btn-outline" onclick="expandAll()"><i class="fas fa-expand-alt"></i> Mở tất cả</button>
                <button class="btn btn-xs btn-outline" onclick="collapseAll()"><i class="fas fa-compress-alt"></i> Thu gọn</button>
                <button class="btn btn-xs btn-primary" onclick="toggleSelectAllSessions()" id="selectAllBtn" style="padding: 6px 15px; border-radius: 8px;"><i class="fas fa-check-square"></i> Chọn hết</button>
            </div>
        </div>

        <div id="chatList">
            <div style="text-align:center; padding:60px; color:var(--text-muted);">
                <i class="fas fa-circle-notch fa-spin fa-2x"></i>
                <p style="margin-top:15px; font-weight:600;">Đang tải nhật ký AI...</p>
            </div>
        </div>
        <div id="paginationContainer" class="pagination" style="margin-top:30px;"></div>
    </div>
</main>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
let currentPage = 1;
window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(location.search);
    if (params.get('v')) { try { document.getElementById('filterKeyword').value = atob(params.get('v')); } catch(e) {} }
    loadChats(1);
    markAllHistoryRead();
});

window.addEventListener('adminNotifUpdate', (e) => {
    try {
        if (e.detail && e.detail.tinnhan_new > 0) {
            loadChats(currentPage);
            markAllHistoryRead();
        }
    } catch(err) {}
});

async function markAllHistoryRead() {
    try {
        await fetch('../api/admin_ho_tro_mark_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: 'all_history', chat_type: 'bot' }) 
        });
    } catch(e) {}
}

function getFilters() {
    return { keyword: document.getElementById('filterKeyword').value.trim(), date_from: document.getElementById('filterFrom').value, date_to: document.getElementById('filterTo').value };
}

function clearFilters() {
    document.getElementById('filterKeyword').value = ''; document.getElementById('filterFrom').value = ''; document.getElementById('filterTo').value = '';
    loadChats(1);
}

async function loadChats(page = 1) {
    currentPage = page;
    const f = getFilters();
    const qs = new URLSearchParams({...f, page}).toString();
    const container = document.getElementById('chatList');
    container.innerHTML = '<div style="text-align:center; padding:60px;"><i class="fas fa-circle-notch fa-spin fa-2x" style="color:var(--accent);"></i></div>';
    
    try {
        const res = await fetch(`../api/admin_tin_nhan.php?${qs}`);
        const data = await res.json();
        if (!data.success) { container.innerHTML = `<div class="card" style="padding:40px; text-align:center; color:var(--danger);">${data.message}</div>`; return; }
        document.getElementById('resultCount').textContent = `${data.total} kết quả`;
        if (!data.data.length) { container.innerHTML = `<div class="card" style="padding:60px; text-align:center; color:var(--text-muted);"><i class="fas fa-comment-slash fa-3x" style="opacity:0.1; margin-bottom:20px;"></i><p>Không có nhật ký trò chuyện nào.</p></div>`; return; }

        const sessions = {};
        data.data.forEach(msg => { if (!sessions[msg.session_id]) sessions[msg.session_id] = []; sessions[msg.session_id].push(msg); });

        let html = '';
        Object.entries(sessions).forEach(([sid, msgs]) => {
            const safeSid = sid.replace(/[^a-z0-9]/gi,'_');
            html += `
            <div class="chat-session-block" id="session-${safeSid}">
                <div class="chat-session-header" onclick="toggleSession(this)" style="display:flex; align-items:center; justify-content:space-between; padding:20px; background:#fff; cursor:pointer; transition:all .2s;">
                    <div style="display:flex; align-items:center; gap:15px; flex:1; min-width:0;">
                        <input type="checkbox" class="session-check" value="${btoa(sid)}" onclick="event.stopPropagation();updateBulkBar()" style="width:20px;height:20px;">
                        <div style="width:45px;height:45px;border-radius:12px;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff; font-size:1.2rem; flex-shrink:0;">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:800; color:var(--text); font-size:1.05rem;">${escHtml(msgs[0].username || 'Khách truy cập')}</div>
                            <div style="font-size:.8rem; color:var(--text-muted); display:flex; gap:15px; margin-top:2px;">
                                <span><i class="fas fa-clock" style="margin-right:4px;"></i>${formatDate(msgs[0].created_at)}</span>
                                <span><i class="fas fa-comment-alt" style="margin-right:4px;"></i>${msgs.length} tin nhắn</span>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <button class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation(); deleteSession('${sid}')" title="Xóa phiên này">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <i class="fas fa-chevron-down" style="color:var(--text-muted); transition:.3s; margin-left:10px;"></i>
                    </div>
                </div>
                <div class="chat-bubbles" style="display:none; padding:25px; background:#f8fafc; border-top:1px solid var(--border);">
                    ${msgs.map(m => renderBubble(m)).join('')}
                </div>
            </div>`;
        });
        container.innerHTML = html;
        renderPagination(data);
        if (window.globalAdminPoll) window.globalAdminPoll();
    } catch(e) { container.innerHTML = `<div class="card" style="padding:40px; text-align:center; color:var(--danger);">Lỗi kết nối máy chủ</div>`; }
}

function renderBubble(m) {
    let out = '';
    if (m.user_message) {
        out += `<div style="display:flex; flex-direction:column; align-items:flex-end; margin-bottom:20px;">
            <div style="background:var(--accent); color:#fff; padding:12px 18px; border-radius:18px 18px 4px 18px; max-width:85%; font-size:1rem; line-height:1.5; box-shadow:0 4px 12px rgba(16,185,129,0.15);">${escHtml(m.user_message)}</div>
            <span style="font-size:.7rem; color:var(--text-muted); margin-top:6px; font-weight:600;">${formatDate(m.created_at)}</span>
        </div>`;
    }
    if (m.bot_response) {
        out += `<div style="display:flex; flex-direction:column; align-items:flex-start; margin-bottom:20px;">
            <div style="background:#fff; color:var(--text); padding:12px 18px; border-radius:18px 18px 18px 4px; max-width:85%; font-size:1rem; line-height:1.6; border:1px solid var(--border); box-shadow:0 4px 12px rgba(0,0,0,0.02);">${escHtml(m.bot_response)}</div>
            <span style="font-size:.7rem; color:var(--text-muted); margin-top:6px; font-weight:600;"><i class="fas fa-robot" style="margin-right:4px;"></i>Phản hồi từ AI</span>
        </div>`;
    }
    return out;
}

function toggleSession(header) {
    const bubbles = header.nextElementSibling;
    const chevron = header.querySelector('.fa-chevron-down, .fa-chevron-up');
    const isShowing = bubbles.style.display === 'block';
    bubbles.style.display = isShowing ? 'none' : 'block';
    chevron.className = isShowing ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
    header.parentElement.classList.toggle('active', !isShowing);
}

function expandAll() {
    document.querySelectorAll('.chat-bubbles').forEach(b => { b.style.display = 'block'; b.previousElementSibling.querySelector('.fa-chevron-down, .fa-chevron-up').className = 'fas fa-chevron-up'; b.parentElement.classList.add('active'); });
}

function collapseAll() {
    document.querySelectorAll('.chat-bubbles').forEach(b => { b.style.display = 'none'; b.previousElementSibling.querySelector('.fa-chevron-down, .fa-chevron-up').className = 'fas fa-chevron-down'; b.parentElement.classList.remove('active'); });
}

function deleteSession(sid) {
    Swal.fire({
        title: 'Xóa hội thoại?', text: "Dữ liệu phiên chat AI này sẽ bị xóa vĩnh viễn!", icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa ngay', confirmButtonColor: 'var(--danger)'
    }).then(r => { if (r.isConfirmed) doDelete([btoa(sid)]); });
}

async function doDelete(sids) {
    const fd = new FormData(); fd.append('action', 'delete_sessions'); sids.forEach(s => fd.append('sessions[]', s));
    try {
        const res = await fetch('../api/admin_tin_nhan_xoa.php', { 
            method:'POST', 
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body:fd 
        });
        const d = await res.json();
        if (d.success) { Swal.fire('Đã xóa!', d.message, 'success'); loadChats(currentPage); }
        else Swal.fire('Lỗi!', d.message, 'error');
    } catch(e) { Swal.fire('Lỗi!', 'Không thể kết nối máy chủ', 'error'); }
}

function toggleSelectAllSessions(val) {
    const checks = document.querySelectorAll('.session-check');
    if (checks.length === 0) return;

    let newState;
    if (typeof val === 'boolean') {
        newState = val;
    } else if (val && typeof val.checked !== 'undefined') {
        newState = val.checked;
    } else {
        // Toggle logic: if any are unchecked, check all. Else uncheck all.
        const anyUnchecked = Array.from(checks).some(c => !c.checked);
        newState = anyUnchecked;
    }

    checks.forEach(c => c.checked = newState);
    
    // Sync the checkbox inside Bulk Bar
    const bulkAll = document.getElementById('bulkSelectAll');
    if (bulkAll) bulkAll.checked = newState;
    
    updateBulkBar();
}

function updateBulkBar() {
    const allChecks = document.querySelectorAll('.session-check');
    const checked = document.querySelectorAll('.session-check:checked');
    
    document.getElementById('selectedCount').textContent = `${checked.length} phiên chat đã chọn`;
    document.getElementById('bulkBar').classList.toggle('visible', checked.length > 0);
    
    // Sync Select All checkbox
    const isAll = checked.length === allChecks.length && allChecks.length > 0;
    const bulkAll = document.getElementById('bulkSelectAll');
    if (bulkAll) bulkAll.checked = isAll;
}

function bulkDeleteSessions() {
    const sids = Array.from(document.querySelectorAll('.session-check:checked')).map(c => c.value);
    Swal.fire({
        title: `Xóa ${sids.length} phiên chat?`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Xóa tất cả', confirmButtonColor: 'var(--danger)'
    }).then(r => { if(r.isConfirmed) doDelete(sids); });
}

function renderPagination(data) {
    const pc = document.getElementById('paginationContainer');
    if (data.pages <= 1) { pc.style.display = 'none'; return; }
    pc.style.display = 'flex';
    let btns = '';
    for (let p = 1; p <= data.pages; p++) { btns += `<button class="page-btn ${p===data.page?'active':''}" onclick="loadChats(${p})">${p}</button>`; }
    pc.innerHTML = btns;
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>'); }
function formatDate(s) { const d = new Date(s); return d.toLocaleString('vi-VN', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}); }
</script>
</body>
</html>
