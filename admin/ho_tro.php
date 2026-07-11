<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

try {
    $db->exec("UPDATE chatbot_history SET is_read = 1 WHERE is_read = 0 AND sender IN ('user','ai') AND chat_type = 'support'");
} catch (Exception $e) {}


// Pending posts count for sidebar badge
$stmt = $db->query("SELECT COUNT(*) FROM dangbai_chothuetro WHERE trangthai='cho_duyet'");
$pending_posts = $stmt->fetchColumn();

// Pre-selected session from query param (from lien_he.php)
$preSelectSession = trim($_GET['session_id'] ?? '');
$preSelectEmail   = trim($_GET['email'] ?? '');
$preSelectName    = trim($_GET['ho_ten'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Hỗ trợ người dùng — Admin Mái Nhà Xanh</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&family=Lexend:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ─── LAYOUT ─────────────────────────── */
        .chat-layout {
            display: flex;
            height: calc(100vh - 72px);
            overflow: hidden;
        }

        /* ─── SESSION LIST ───────────────────── */
        .session-list {
            width: 340px;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: all .3s;
        }
        .session-list-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }
        .session-search {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg);
            color: var(--text);
            font-size: .85rem;
            outline: none;
            transition: border-color .2s;
        }
        .session-search:focus { border-color: var(--accent); }
        
        .session-items { flex: 1; overflow-y: auto; }
        .session-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            transition: all .2s;
        }
        .session-item:hover { background: #f8fafc; }
        .session-item.active { background: #f0fdf4; border-left: 4px solid var(--accent); }

        .session-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; flex-shrink: 0;
        }
        .session-avatar.contact { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .session-info { flex: 1; min-width: 0; }
        .session-name { font-weight: 700; color: var(--text); font-size: .95rem; margin-bottom: 2px; }
        .session-preview { font-size: .8rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ─── CHAT PANEL ─────────────────────── */
        .chat-panel { flex: 1; display: flex; flex-direction: column; background: #fff; }
        .chat-panel-header {
            padding: 15px 25px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .chat-messages {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: #f8fafc;
        }

        /* ─── BUBBLES ────────────────────────── */
        .bubble-row {
            display: flex;
            width: 100%;
            gap: 12px;
            margin-bottom: 15px;
            align-items: flex-end;
            animation: fadeIn 0.3s ease-out forwards;
        }
        .bubble-row.admin {
            justify-content: flex-start;
            flex-direction: row-reverse;
        }
        .bubble-row.user {
            justify-content: flex-start;
        }
        .bubble-container {
            display: flex;
            flex-direction: column;
            width: fit-content;
        }
        .bubble {
            padding: 10px 15px;
            border-radius: 18px;
            font-size: 0.95rem;
            line-height: 1.5;
            word-wrap: break-word;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            max-width: 75%;
            width: fit-content;
            white-space: normal;
        }
        .bubble-row.admin .bubble {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff;
            border-radius: 18px 4px 18px 18px;
        }
        .bubble-row.user .bubble {
            background: #fff;
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 4px 18px 18px 18px;
        }
        .bubble-time {
            font-size: 0.7rem;
            margin-top: 4px;
            text-align: right;
            display: block;
        }
        .bubble-row.admin .bubble-time {
            color: rgba(255, 255, 255, 0.8);
        }
        .bubble-row.user .bubble-time {
            color: var(--text-muted);
        }

        .bubble-avatar-text {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #475569;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .bubble-avatar-text.admin {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff;
        }
        .bubble-avatar {
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        
        .chat-input-area {
            padding: 15px 25px;
            background: #fff;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
            align-items: center;
        }
        #adminMessageInput {
            flex: 1;
            padding: 12px 18px;
            border: 1px solid var(--border);
            border-radius: 25px;
            font-size: .95rem;
            outline: none;
            resize: none;
            max-height: 100px;
            transition: all .2s;
        }
        #adminMessageInput:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
        
        #sendAdminMsg {
            width: 48px; height: 48px; border-radius: 50%;
            background: var(--accent); color: #fff; border: none;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all .2s;
        }
        #sendAdminMsg:hover { transform: scale(1.1); background: var(--accent-dark); }

        @media (max-width: 768px) {
            .session-list { width: 100%; position: absolute; z-index: 100; height: 100%; left: -100%; transition: .3s; }
            .session-list.open { left: 0; }
            .chat-panel { width: 100%; }
            .back-to-sessions { display: flex !important; }
        }
        .back-to-sessions { display: none; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main" style="padding:0; overflow:hidden;">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
            <button class="btn btn-outline btn-sm show-sessions-btn" style="display:none;" onclick="document.querySelector('.session-list').classList.add('open')">
                <i class="fas fa-users"></i>
            </button>
            <div class="topbar-title">Hệ thống <span>Hỗ trợ trực tuyến</span></div>
        </div>
        <div class="topbar-right">
            <span style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y H:i') ?></span>
            <?php if(!empty($_SESSION['avatar'])): ?><img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" class="admin-avatar" style="object-fit:cover;" alt="Avatar"><?php else: ?><div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="chat-layout">
        <!-- SESSION LIST -->
        <div class="session-list" id="sessionList">
            <div class="session-list-header">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 style="font-size:1.1rem; font-weight:800;">Hội thoại</h3>
                    <button class="btn btn-xs btn-outline" onclick="loadSessions()"><i class="fas fa-sync-alt"></i></button>
                </div>
                <input type="text" class="session-search" id="sessionSearch" placeholder="Tìm người dùng..." oninput="filterSessions(this.value)">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:.8rem; font-weight:700; color:var(--text-muted); cursor:pointer; background:#f1f5f9; padding:6px 12px; border-radius:8px; transition:all .2s;">
                        <input type="checkbox" id="htSelectAllCheck" onchange="toggleSelectAllHt(this)" style="width:17px; height:17px; accent-color:var(--accent);">
                        Tất cả
                    </label>
                    <button class="btn btn-danger btn-xs" id="htBulkDeleteBtn" style="display:none; padding:6px 12px; border-radius:8px;" onclick="bulkDeleteHt()">
                        <span style="display:flex; align-items:center; gap:5px;"><i class="fas fa-trash-alt"></i> (<span id="htSelectedCount">0</span>)</span>
                    </button>
                </div>
            </div>
            <div class="session-items" id="sessionItems">
                <div class="chat-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
            </div>
        </div>

        <!-- CHAT PANEL -->
        <div class="chat-panel" id="chatPanel">
            <div class="empty-chat" id="emptyState" style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text-muted);">
                <i class="fas fa-comments" style="font-size:4rem; opacity:.1; margin-bottom:15px;"></i>
                <p>Vui lòng chọn hội thoại để phản hồi</p>
            </div>

            <!-- Header -->
            <div class="chat-panel-header" id="chatHeader" style="display:none;">
                <button class="btn btn-outline btn-sm back-to-sessions" onclick="goBackToSessions()" style="padding:6px 12px; margin-right:10px;">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="session-avatar" id="headerAvatar">U</div>
                <div style="flex:1; min-width:0;">
                    <h4 id="headerName" style="font-weight:800; margin:0; font-size:1.1rem;">–</h4>
                    <small id="headerMeta" style="color:var(--text-muted); font-size:.8rem;">–</small>
                </div>
                <div id="headerActions"></div>
            </div>

            <!-- Messages -->
            <div class="chat-messages" id="chatMessages" style="display:none;"></div>

            <!-- Input -->
            <div class="chat-input-area" id="chatInputArea" style="display:none;">
                <textarea id="adminMessageInput" placeholder="Viết câu trả lời..." rows="1"></textarea>
                <button id="sendAdminMsg" type="button"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN  = '<?= $_SESSION['csrf_token'] ?? '' ?>';
const PRE_SESSION = <?= json_encode($preSelectSession) ?>;
const PRE_EMAIL   = <?= json_encode($preSelectEmail) ?>;
const PRE_NAME    = <?= json_encode($preSelectName ?? '') ?>;

let currentSession = null;
let pollTimer      = null;
let lastMsgTime    = null;
let allSessions    = [];
let renderedMsgIds = new Set();
let isSendingMessage = false;

// Initial load
window.addEventListener('DOMContentLoaded', () => {
    loadSessions();
    setInterval(loadSessions, 3000);
});

// React to global notifications poller to load support chat sessions instantly
window.addEventListener('adminNotifUpdate', (e) => {
    try {
        if (e.detail && e.detail.support_new > 0) {
            loadSessions();
            if (currentSession) {
                loadMessages();
            }
        }
    } catch(err) {}
});

async function loadSessions() {
    const el = document.getElementById('sessionItems');
    try {
        const res  = await fetch('../api/admin_ho_tro_sessions.php', { cache: 'no-store' });
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Không tải được danh sách hội thoại');
        }
        allSessions = data.data;
        renderSessions(allSessions);

        if (PRE_SESSION && !currentSession) {
            const found = allSessions.find(s => s.session_id === PRE_SESSION);
            if (found) selectSession(found);
            else {
                selectSession({ session_id: PRE_SESSION, hoten: PRE_NAME || 'Người dùng', email: PRE_EMAIL, source: 'contact' });
            }
        }
    } catch (e) {
        if (el) {
            el.innerHTML = '<div style="padding:40px;text-align:center;color:var(--danger)">Không tải được hội thoại</div>';
        }
        console.error('[ho_tro] loadSessions failed:', e);
    }
}

function renderSessions(list) {
    const el = document.getElementById('sessionItems');
    if (!list.length) {
        el.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted)">Hội thoại trống</div>';
        return;
    }
    
    if (el.querySelector('.chat-loading') || el.innerHTML.includes('Hội thoại trống')) {
        el.innerHTML = '';
    }

    const existingItems = Array.from(el.querySelectorAll('.session-item'));
    const existingMap = new Map();
    existingItems.forEach(item => {
        if (item.dataset.sid) existingMap.set(item.dataset.sid, item);
    });

    list.forEach((s, index) => {
        const DOMIndex = index;
        const initials = (s.hoten || 'K').charAt(0).toUpperCase();
        const isActive = currentSession && currentSession.session_id === s.session_id;
        
        let itemNode = existingMap.get(s.session_id);
        let isNew = false;
        if (!itemNode) {
            itemNode = document.createElement('div');
            itemNode.className = `session-item`;
            itemNode.dataset.sid = s.session_id;
            isNew = true;
        } else {
            existingMap.delete(s.session_id);
        }
        
        itemNode.className = `session-item ${isActive ? 'active' : ''}`;
        
        // Show unread messages
        let badgeHtml = '';
        if (s.unread > 0) {
            badgeHtml += `<div class="unread-badge" style="background:#ef4444; color:#fff; border-radius:10px; padding:2px 6px; font-size:0.75rem; font-weight:bold; display:inline-block;">${s.unread}</div>`;
        }

        const htmlContent = `
            <input type="checkbox" class="ht-check" value="${s.session_id}" onclick="event.stopPropagation(); onHtCheckChange()" style="width:18px;height:18px;cursor:pointer;accent-color:var(--accent);">
            <div style="flex:1; display:flex; align-items:center; gap:12px;" onclick='selectSession(${JSON.stringify(s).replace(/'/g, "&apos;")})'>
                <div class="session-avatar ${s.source==='contact'?'contact':''}">
                    ${s.avatar ? `<img src="../${escHtml(s.avatar)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" alt="Avatar">` : initials}
                </div>
                <div class="session-info" style="flex:1;">
                    <div class="session-name">${escHtml(s.hoten || 'Khách')}</div>
                    <div class="session-preview">${escHtml(s.last_message || s.tieude || '...') }</div>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                    ${badgeHtml}
                </div>
            </div>
        `;
        
        const currentHtml = itemNode.innerHTML;
        const wasChecked = itemNode.querySelector('.ht-check') ? itemNode.querySelector('.ht-check').checked : false;
        
        // Update HTML only if it actually changed to prevent blinking
        if (currentHtml.trim() !== htmlContent.trim()) {
            itemNode.innerHTML = htmlContent;
            if (wasChecked) itemNode.querySelector('.ht-check').checked = true;
        }
        
        // Place itemNode at `DOMIndex` in `el`
        if (isNew) {
            if (DOMIndex < el.children.length) {
                el.insertBefore(itemNode, el.children[DOMIndex]);
            } else {
                el.appendChild(itemNode);
            }
        } else {
            if (el.children[DOMIndex] !== itemNode) {
                if (DOMIndex < el.children.length) {
                    el.insertBefore(itemNode, el.children[DOMIndex]);
                } else {
                    el.appendChild(itemNode);
                }
            }
        }
    });
    
    // Remove any items that are no longer in the list
    existingMap.forEach(node => node.remove());
}

function filterSessions(keyword) {
    const q = (keyword || '').trim().toLowerCase();
    if (!q) {
        renderSessions(allSessions);
        return;
    }
    const filtered = allSessions.filter(s => {
        return [s.hoten, s.email, s.tieude, s.last_message, s.session_id]
            .filter(Boolean)
            .some(val => String(val).toLowerCase().includes(q));
    });
    renderSessions(filtered);
}

function goBackToSessions() {
    const list = document.getElementById('sessionList');
    if (list) list.classList.add('open');
}

function selectSession(s) {
    currentSession = s;
    lastMsgTime    = null;
    renderedMsgIds.clear();

    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 1000); 

    if (window.innerWidth <= 768) document.getElementById('sessionList').classList.remove('open');

    document.querySelectorAll('.session-item').forEach(el => el.classList.remove('active'));
    const target = document.querySelector(`.session-item[data-sid="${s.session_id}"]`);
    if(target) target.classList.add('active');

    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('chatHeader').style.display = 'flex';
    document.getElementById('chatMessages').style.display = 'flex';
    document.getElementById('chatInputArea').style.display = 'flex';

    document.getElementById('headerName').textContent = s.hoten || s.session_id.slice(0,10);
    if (s.avatar) {
        document.getElementById('headerAvatar').innerHTML = `<img src="../${escHtml(s.avatar)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" alt="Avatar">`;
    } else {
        document.getElementById('headerAvatar').textContent = (s.hoten || 'K').charAt(0).toUpperCase();
    }
    document.getElementById('headerMeta').textContent = s.email || 'Hội thoại trực tuyến';

    const actions = document.getElementById('headerActions');
    actions.innerHTML = s.email ? `<a href="mailto:${s.email}" class="btn btn-xs btn-primary"><i class="fas fa-envelope"></i> Gửi Mail</a>` : '';

    loadMessages();
}

async function loadMessages() {
    if (!currentSession) return;
    try {
        let url = `../api/get-messages.php?session_id=${encodeURIComponent(currentSession.session_id)}&chat_type=support&mark_read=1`;
        if (lastMsgTime) url += `&since=${encodeURIComponent(lastMsgTime)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (data.success && data.data.length > 0) {
            renderMessages(data.data, !!lastMsgTime);
            lastMsgTime = data.data[data.data.length - 1].created_at;
        }
    } catch(e) {
        console.error('[ho_tro] loadMessages failed:', e);
    }
}

function renderMessages(rows, append) {
    const el = document.getElementById('chatMessages');
    if (!append) {
        el.innerHTML = '';
        renderedMsgIds.clear();
    }
    
    rows.forEach(row => {
        const fingerprint = row.id
            ? `id:${row.id}`
            : `sig:${row.sender || ''}|${row.created_at || ''}|${row.user_message || ''}|${row.admin_message || ''}|${row.bot_response || ''}`;
        if (renderedMsgIds.has(fingerprint)) return;
        renderedMsgIds.add(fingerprint);
        
        if (row.user_message) el.appendChild(makeBubble(row.user_message, 'user', row.created_at, row.id));
        if (row.admin_message) el.appendChild(makeBubble(row.admin_message, 'admin', row.created_at, row.id));
    });
    
    if (rows.length > 0) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
}

function makeBubble(text, type, timeStr, messageId = null) {
    const row = document.createElement('div');
    row.className = `bubble-row ${type}`;
    if (messageId) {
        row.dataset.msgId = messageId;
    }
    
    let avatarHtml = '';
    if (type === 'user') {
        const avatarUrl = currentSession && currentSession.avatar ? '../' + currentSession.avatar : null;
        const initials = currentSession && currentSession.hoten ? currentSession.hoten.charAt(0).toUpperCase() : 'K';
        
        if (avatarUrl) {
            avatarHtml = `<img src="${avatarUrl}" class="bubble-avatar" style="width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0;" onerror="this.outerHTML='<div class=&quot;bubble-avatar-text&quot;>${initials}</div>'">`;
        } else {
            avatarHtml = `<div class="bubble-avatar-text">${initials}</div>`;
        }
    } else {
        avatarHtml = `<div class="bubble-avatar-text admin">👨‍💼</div>`;
    }

    let displayTime = '';
    if (timeStr) {
        const d = new Date(timeStr);
        displayTime = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    } else {
        displayTime = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    }

    // Nút sửa nếu là admin gửi và tin nhắn có id trong CSDL
    const editBtn = (type === 'admin' && messageId) 
        ? `<button class="btn-edit-support" onclick="editSupportMessage(${messageId}, this)" style="background:none; border:none; color:var(--text-muted); cursor:pointer; padding: 4px; margin-right: 6px; font-size: 0.8rem;" title="Sửa tin"><i class="fas fa-edit"></i></button>`
        : '';

    row.innerHTML = `${avatarHtml}
        <div style="display:flex; align-items:center;">
            ${type === 'admin' ? editBtn : ''}
            <div class="bubble" style="word-break:break-word;">
                <p style="margin:0;">${escHtml(text)}</p>
                <div class="bubble-time">${displayTime}</div>
            </div>
            ${type === 'user' ? editBtn : ''}
        </div>`;
    return row;
}

async function editSupportMessage(messageId, btnEl) {
    const rowEl = btnEl.closest('.bubble-row');
    const bubbleTextEl = rowEl.querySelector('.bubble p');
    const currentText = bubbleTextEl.textContent;

    const { value: text } = await Swal.fire({
        title: 'Chỉnh sửa tin nhắn hỗ trợ',
        input: 'textarea',
        inputValue: currentText,
        showCancelButton: true,
        confirmButtonText: 'Lưu thay đổi',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#10b981',
        preConfirm: (value) => {
            if (!value.trim()) {
                Swal.showValidationMessage('Nội dung không được để trống.');
                return false;
            }
            return value.trim();
        }
    });

    if (text) {
        const fd = new FormData();
        fd.append('action', 'edit_support');
        fd.append('message_id', messageId);
        fd.append('content', text);

        try {
            const res = await fetch('../api/edit-chat-message.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                bubbleTextEl.textContent = text;
                Swal.fire({ icon: 'success', title: 'Đã cập nhật tin nhắn', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch(e) {
            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
        }
    }
}

async function sendMessage() {
    const input = document.getElementById('adminMessageInput');
    const msg = input.value.trim();
    if (!msg || !currentSession || isSendingMessage) return;

    // Optimistic UI: hiển thị tin nhắn ngay lập tức
    const chatMessages = document.getElementById('chatMessages');
    const nowStr = new Date().toISOString();
    const optimisticBubble = makeBubble(msg, 'admin', nowStr);
    chatMessages.appendChild(optimisticBubble);
    chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });

    input.value = '';
    input.style.height = '42px';

    const btn = document.getElementById('sendAdminMsg');
    isSendingMessage = true;
    btn.disabled = true;
    
    try {
        const res  = await fetch('../api/admin_ho_tro_send.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({ session_id: currentSession.session_id, message: msg })
        });
        const data = await res.json();
        if (data.success) {
            if (data.id) renderedMsgIds.add(`id:${data.id}`);
            loadMessages(); 
        } else {
            showToast(data.message || 'Không gửi được', 'error');
            optimisticBubble.remove();
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'error');
        optimisticBubble.remove();
    } finally {
        isSendingMessage = false;
        btn.disabled = false;
        input.focus();
    }
}

function deleteHtSession(sid) {
    Swal.fire({
        title: 'Xóa hội thoại?', 
        text: 'Hành động này không thể hoàn tác!',
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonText: 'Xóa ngay', 
        confirmButtonColor: 'var(--danger)'
    }).then(r => {
        if (r.isConfirmed) doDeleteHt([sid]);
    });
}

async function doDeleteHt(sids) {
    const fd = new FormData();
    fd.append('action', 'delete_sessions');
    sids.forEach(s => fd.append('session_ids[]', s));
    try {
        const res = await fetch('../api/admin_ho_tro_xoa.php', { 
            method: 'POST', 
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: fd 
        });
        const d = await res.json();
        if (d.success) {
            showToast(d.message);
            
            // Xóa ngay lập tức các phần tử khỏi giao diện (DOM)
            sids.forEach(sid => {
                const el = document.querySelector(`.session-item[data-sid="${sid}"]`);
                if (el) {
                    el.style.opacity = '0';
                    el.style.transform = 'translateX(-20px)';
                    setTimeout(() => el.remove(), 300);
                }
            });

            // Nếu đang xem hội thoại bị xóa, đóng panel chat ngay
            if (currentSession && sids.includes(currentSession.session_id)) {
                currentSession = null;
                if (pollTimer) clearInterval(pollTimer);
                document.getElementById('emptyState').style.display = 'flex';
                document.getElementById('chatHeader').style.display = 'none';
                document.getElementById('chatMessages').style.display = 'none';
                document.getElementById('chatInputArea').style.display = 'none';
            }
            
            setTimeout(() => {
                loadSessions(); 
            }, 1000); 
        } else {
            showToast(d.message || 'Không thể xóa', 'error');
        }
    } catch(e) {
        showToast('Lỗi kết nối máy chủ', 'error');
    }
}

function toggleSelectAllHt(val) {
    const checks = document.querySelectorAll('.ht-check');
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
    const masterCheck = document.getElementById('htSelectAllCheck');
    if (masterCheck) masterCheck.checked = newState;
    updateBulkBar();
}

function updateBulkBar() {
    const allChecks = document.querySelectorAll('.ht-check');
    const checked = document.querySelectorAll('.ht-check:checked');
    const count = checked.length;
    
    const countEl = document.getElementById('htSelectedCount');
    const btnEl = document.getElementById('htBulkDeleteBtn');
    const masterCheck = document.getElementById('htSelectAllCheck');

    if (countEl) countEl.textContent = count;
    if (btnEl) btnEl.style.display = count > 0 ? 'inline-flex' : 'none';
    if (masterCheck) masterCheck.checked = (count === allChecks.length && allChecks.length > 0);
}

function bulkDeleteHt() {
    const sids = Array.from(document.querySelectorAll('.ht-check:checked')).map(c => c.value);
    Swal.fire({ 
        title: `Xóa ${sids.length} hội thoại?`, 
        text: 'Dữ liệu sẽ bị xóa vĩnh viễn!',
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonText: 'Xóa ngay',
        confirmButtonColor: 'var(--danger)'
    }).then(r => { if(r.isConfirmed) doDeleteHt(sids); });
}

// ── Event listeners ────────────────────────────────────────────
document.getElementById('sendAdminMsg').addEventListener('click', sendMessage);
document.getElementById('adminMessageInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && !e.repeat) { e.preventDefault(); sendMessage(); }
});
document.getElementById('adminMessageInput').addEventListener('input', function() {
    this.style.height = '42px';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// ── Helpers ────────────────────────────────────────────────────
function formatDate(s) {
    const d = new Date(s);
    return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'});
}
function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML.replace(/\n/g,'<br>');
}
function escAttr(s) {
    return (s||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function showToast(msg, type='success') {
    Swal.fire({
        text: msg,
        icon: type,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}
</script>
</body>
</html>
