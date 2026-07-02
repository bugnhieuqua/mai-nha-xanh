<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/media_helper.php';
require_once '../includes/room_status_helper.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();
ensureDangbaiRoomStatusSchema($db);

// Thống kê nhanh cho greeting message của Chatbot
$stats = [];
try {
    $stmt = $db->query("SELECT trangthai, COUNT(*) as cnt FROM dangbai_chothuetro GROUP BY trangthai");
    $raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats = [
        'pending'  => $raw['cho_duyet'] ?? 0,
        'approved' => $raw['da_duyet']  ?? 0,
        'total'    => array_sum($raw),
    ];
} catch (Exception $e) {
    $stats = ['pending' => 0, 'approved' => 0, 'total' => 0];
}

$admin_name = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant — Admin Mái Nhà Xanh</title>
    <meta name="description" content="Chatbot AI dành riêng cho admin quản lý bài đăng phòng trọ Mái Nhà Xanh">
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* SCROLL LOCK FOR FULL WINDOW INTEGRATION */
        body {
            overflow: hidden !important;
            height: 100vh !important;
            height: 100dvh !important;
        }
        .admin-main {
            height: 100vh !important;
            height: 100dvh !important;
            overflow: hidden !important;
            display: flex;
            flex-direction: column;
        }
        .chatbot-layout {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        /* Chat panel */
        .chat-panel {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
            background: linear-gradient(180deg, #f0fdf4 0%, #e8f5f0 30%, #dde9f5 100%);
            position: relative;
            overflow: hidden;
        }
        .chat-header { padding: 16px 24px; background: rgba(255,255,255,0.8); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(16,185,129,0.15); display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
        .chat-header-avatar { width: 46px; height: 46px; border-radius: 14px; background: linear-gradient(135deg, #065f46, #0d9488); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #fff; flex-shrink: 0; box-shadow: 0 4px 14px rgba(16,185,129,0.4); }
        .chat-header-info h2 { font-size: 1rem; font-weight: 800; color: var(--text); margin: 0; }
        .chat-header-status { display: flex; align-items: center; gap: 5px; font-size: 0.72rem; color: #10b981; font-weight: 600; }
        .chat-header-status::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #10b981; animation: pulse-dot 2s infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .chat-header-actions { margin-left: auto; display: flex; gap: 8px; }

        /* Messages */
        .chat-messages { flex: 1; min-height: 0; overflow-y: auto; padding: 24px 28px; display: flex; flex-direction: column; gap: 20px; scrollbar-width: thin; scrollbar-color: rgba(0,0,0,0.1) transparent; }
        .chat-messages::-webkit-scrollbar { width: 5px; }
        .chat-messages::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }

        /* Welcome */
        .chat-welcome { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; flex: 1; }
        .welcome-orb { width: 80px; height: 80px; border-radius: 24px; background: linear-gradient(135deg, #065f46, #0d9488, #3b82f6); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: #fff; margin: 0 auto 20px; box-shadow: 0 10px 30px rgba(16,185,129,0.35); animation: float-orb 3s ease-in-out infinite; }
        @keyframes float-orb { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        .welcome-title { font-size: 1.4rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
        .welcome-sub { font-size: 0.9rem; color: var(--text-muted); max-width: 400px; line-height: 1.6; margin-bottom: 28px; }
        .welcome-chips { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; max-width: 500px; }
        .welcome-chip { background: rgba(255,255,255,0.8); border: 1px solid rgba(16,185,129,0.2); border-radius: 20px; padding: 8px 14px; font-size: 0.8rem; font-weight: 600; color: var(--text); cursor: pointer; transition: all 0.2s; backdrop-filter: blur(10px); }
        .welcome-chip:hover { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.4); color: #065f46; transform: translateY(-2px); }
        .welcome-chip i { margin-right: 5px; color: var(--accent); }

        /* Message bubbles */
        .msg-row { display: flex; gap: 12px; align-items: flex-start; animation: msgIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        @keyframes msgIn { from { opacity: 0; transform: translateY(12px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .msg-row.user-row { flex-direction: row-reverse; }
        .msg-avatar { width: 36px; height: 36px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; color: #fff; flex-shrink: 0; }
        .ai-avatar { background: linear-gradient(135deg, #065f46, #0d9488); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .user-avatar-chat { background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        .msg-bubble { max-width: 72%; border-radius: 18px; padding: 14px 18px; font-size: 0.9rem; line-height: 1.6; position: relative; }
        .ai-bubble { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); border: 1px solid rgba(16,185,129,0.15); box-shadow: 0 4px 20px rgba(0,0,0,0.06); color: var(--text); border-radius: 4px 18px 18px 18px; }
        .user-bubble { background: linear-gradient(135deg, #065f46, #0d9488); color: #fff; box-shadow: 0 4px 20px rgba(16,185,129,0.3); border-radius: 18px 4px 18px 18px; }
        .msg-time { font-size: 0.65rem; color: var(--text-muted); margin-top: 5px; display: block; }
        .user-row .msg-time { text-align: right; }

        /* Action cards */
        .action-card { margin-top: 12px; border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08); }
        .action-card-header { padding: 10px 14px; font-size: 0.78rem; font-weight: 700; display: flex; align-items: center; gap: 7px; }
        .action-card-header.list    { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #1d4ed8; }
        .action-card-header.stats   { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #065f46; }
        .action-card-header.success { background: linear-gradient(135deg, #f0fdf4, #bbf7d0); color: #065f46; }
        .action-card-header.error   { background: linear-gradient(135deg, #fff1f2, #fecaca); color: #dc2626; }
        .action-card-body { background: rgba(255,255,255,0.95); }

        /* Post items */
        .post-result-item { display: flex; align-items: center; gap: 8px; padding: 9px 12px; border-bottom: 1px solid rgba(0,0,0,0.04); font-size: 0.82rem; transition: background 0.15s; }
        .post-result-item:last-child { border-bottom: none; }
        .post-result-item:hover { background: rgba(16,185,129,0.04); }
        .post-result-id { font-size: 0.7rem; background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 5px; font-weight: 700; color: var(--text-muted); flex-shrink: 0; }
        .post-result-title { flex: 1; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .post-result-price { font-weight: 700; color: #065f46; font-size: 0.78rem; flex-shrink: 0; }
        .post-result-status { flex-shrink: 0; }
        .post-result-actions { display: flex; gap: 4px; flex-shrink: 0; }
        .post-result-btn { padding: 3px 7px; border-radius: 6px; font-size: 0.68rem; font-weight: 700; border: none; cursor: pointer; transition: all 0.15s; }
        .btn-approve { background: #10b981; color: #fff; }
        .btn-approve:hover { background: #059669; transform: scale(1.05); }
        .btn-reject  { background: #f59e0b; color: #fff; }
        .btn-reject:hover  { background: #d97706; transform: scale(1.05); }
        .btn-delete  { background: #ef4444; color: #fff; }
        .btn-delete:hover  { background: #dc2626; transform: scale(1.05); }

        /* Bulk bar */
        .bulk-bar { padding: 8px 12px; background: rgba(16,185,129,0.07); border-bottom: 1px solid rgba(16,185,129,0.15); align-items: center; gap: 8px; flex-wrap: wrap; }

        /* Stats grid */
        .stats-result-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 12px; }
        .stats-result-item { text-align: center; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.02); }
        .stats-result-num   { font-size: 1.3rem; font-weight: 800; }
        .stats-result-label { font-size: 0.7rem; color: var(--text-muted); }

        /* Typing */
        .typing-row { display: flex; gap: 12px; align-items: center; }
        .typing-bubble { background: rgba(255,255,255,0.9); border: 1px solid rgba(16,185,129,0.15); border-radius: 4px 18px 18px 18px; padding: 14px 18px; display: flex; gap: 5px; align-items: center; }
        .typing-dot { width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: bounce-dot 1.2s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce-dot { 0%, 80%, 100% { transform: translateY(0); opacity: 0.5; } 40% { transform: translateY(-6px); opacity: 1; } }

        /* Input */
        .chat-input-area { padding: 14px 24px 18px; background: rgba(255,255,255,0.8); backdrop-filter: blur(20px); border-top: 1px solid rgba(16,185,129,0.12); flex-shrink: 0; }
        .quick-cmds { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .quick-cmd { background: rgba(255,255,255,0.8); border: 1px solid rgba(16,185,129,0.2); border-radius: 16px; padding: 4px 12px; font-size: 0.74rem; font-weight: 600; color: #065f46; cursor: pointer; transition: all 0.15s; }
        .quick-cmd:hover { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.4); transform: translateY(-1px); }
        .chat-input-wrap { display: flex; gap: 10px; align-items: flex-end; }
        #chatInput { flex: 1; border: 1.5px solid rgba(16,185,129,0.25); border-radius: 14px; padding: 12px 16px; font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.9rem; font-weight: 500; color: var(--text); background: rgba(255,255,255,0.9); outline: none; resize: none; max-height: 120px; min-height: 48px; transition: border-color 0.2s, box-shadow 0.2s; }
        #chatInput:focus { border-color: rgba(16,185,129,0.6); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
        #chatInput::placeholder { color: #94a3b8; }
        #sendBtn { width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #065f46, #10b981); border: none; color: #fff; font-size: 1.05rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0; box-shadow: 0 4px 14px rgba(16,185,129,0.35); }
        #sendBtn:hover { transform: scale(1.08); box-shadow: 0 6px 20px rgba(16,185,129,0.45); }
        #sendBtn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* AI bubble formatting */
        .ai-bubble strong { font-weight: 700; }
        .ai-bubble em { font-style: italic; color: #065f46; }
        .ai-bubble ul, .ai-bubble ol { padding-left: 18px; margin: 8px 0; }
        .ai-bubble li { margin-bottom: 4px; }
        .ai-bubble code { background: rgba(16,185,129,0.08); padding: 1px 5px; border-radius: 4px; font-family: monospace; font-size: 0.85em; color: #065f46; }
        .ai-bubble p { margin: 0 0 6px; }
        .ai-bubble p:last-child { margin-bottom: 0; }

        /* Badges */
        .badge-warn { background: rgba(245,158,11,0.12); color: #92400e; padding: 2px 7px; border-radius: 5px; font-size: 0.68rem; font-weight: 700; }
        .badge-ok   { background: rgba(16,185,129,0.12); color: #065f46; padding: 2px 7px; border-radius: 5px; font-size: 0.68rem; font-weight: 700; }
        .badge-no   { background: rgba(239,68,68,0.1); color: #dc2626; padding: 2px 7px; border-radius: 5px; font-size: 0.68rem; font-weight: 700; }

        .action-card-body-scroll { max-height: 260px; overflow-y: auto; scrollbar-width: thin; }
        .action-card-body-scroll::-webkit-scrollbar { width: 4px; }
        .action-card-body-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">Trợ lý AI <span>Admin Assistant</span></div>
        </div>
        <div class="topbar-right" style="display:flex;align-items:center;gap:16px;">
            <span style="font-size:.8rem;color:var(--text-muted);display:flex;align-items:center;gap:5px;">
                <i class="fas fa-robot" style="color:var(--accent);"></i> Powered by Groq AI
            </span>
            <?php if(!empty($_SESSION['avatar'])): ?>
                <img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" class="admin-avatar" style="object-fit:cover;" alt="Avatar">
            <?php else: ?>
                <div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="chatbot-layout">


        <!-- RIGHT: Chat Panel -->
        <div class="chat-panel">
            <div class="chat-header">
                <div class="chat-header-avatar"><i class="fas fa-robot"></i></div>
                <div class="chat-header-info">
                    <h2>Admin AI Assistant</h2>
                    <div class="chat-header-status">Trực tuyến — Trợ lý AI Quản trị Mái Nhà Xanh</div>
                </div>
                <div class="chat-header-actions">
                    <button class="btn btn-sm btn-outline" onclick="clearChat()" style="height:auto;padding:7px 12px;">
                        <i class="fas fa-broom"></i> Xóa chat
                    </button>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <!-- AI auto greeting will appear here on load -->
            </div>

            <div class="chat-input-area">
                <div class="quick-cmds">
                    <div class="quick-cmd" onclick="sendQuickCommand('Xuất excel tổng hợp toàn bộ người đăng bài')"><i class="fas fa-file-excel" style="color:#107c41;"></i> Excel Người đăng</div>
                    <div class="quick-cmd" onclick="sendQuickCommand('Xuất excel các bài đăng bị từ chối/hủy')"><i class="fas fa-file-excel" style="color:#ef4444;"></i> Excel Bài từ chối</div>
                    <div class="quick-cmd" onclick="sendQuickCommand('Xuất excel các bài đăng chờ duyệt')"><i class="fas fa-file-excel" style="color:#f59e0b;"></i> Excel Bài chờ duyệt</div>
                    <div class="quick-cmd" onclick="sendQuickCommand('Xuất excel tất cả tài khoản người dùng')"><i class="fas fa-file-excel" style="color:#3b82f6;"></i> Excel Người dùng</div>
                    <div class="quick-cmd" onclick="sendQuickCommand('Liệt kê 10 bài chờ duyệt mới nhất')">📋 Chờ duyệt</div>
                    <div class="quick-cmd" onclick="sendQuickCommand('Thống kê tổng quan hệ thống hôm nay')">📊 Thống kê</div>
                    <div class="quick-cmd" onclick="sendQuickCommand('Top 5 người đăng bài nhiều nhất')">🏆 Top poster</div>
                </div>
                <div class="chat-input-wrap">
                    <textarea id="chatInput" placeholder="Nhập lệnh cho AI... (VD: Duyệt bài ID 5, Liệt kê bài chờ, Xóa bài vi phạm...)" rows="1"></textarea>
                    <button id="sendBtn" onclick="sendMessage()" title="Gửi (Enter)">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// ── CONSTANTS ─────────────────────────────────────────────────────
const ADMIN_NAME    = <?= json_encode($admin_name) ?>;
const PENDING_COUNT = <?= (int)$stats['pending'] ?>;
const APPROVED_COUNT= <?= (int)$stats['approved'] ?>;
const TOTAL_COUNT   = <?= (int)$stats['total'] ?>;

let chatHistory = [];
let isLoading = false;
let _cardCounter = 0;

// ── AUTO-RESIZE TEXTAREA ──────────────────────────────────────────
const chatInput = document.getElementById('chatInput');
chatInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
chatInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

function insertCommand(text) {
    chatInput.value = text;
    chatInput.focus();
    chatInput.style.height = 'auto';
    chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
}

function sendQuickCommand(text) {
    if (isLoading) return;
    chatInput.value = text;
    sendMessage();
}

// ── AUTO GREETING khi mở chat ─────────────────────────────────────
function showAutoGreeting() {
    const hour = new Date().getHours();
    const timeGreet = hour < 12 ? 'buổi sáng' : hour < 18 ? 'buổi chiều' : 'buổi tối';

    let urgentHtml = '';
    if (PENDING_COUNT > 0) {
        urgentHtml = `<div style="background:linear-gradient(135deg,rgba(245,158,11,0.12),rgba(245,158,11,0.05));border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:12px 14px;margin-top:12px;">
            <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#92400e;margin-bottom:5px;">
                <i class="fas fa-exclamation-circle" style="color:#f59e0b;font-size:1rem;"></i>
                CẦN XỬ LÝ NGAY: <span style="font-size:1.05em;color:#d97706;">${PENDING_COUNT} bài đang chờ duyệt</span>
            </div>
            <div style="font-size:0.82rem;color:#78350f;line-height:1.5;">
                💬 Gợi ý: <em>"Liệt kê bài chờ duyệt"</em> · <em>"Duyệt bài ID 5"</em> · <em>"Duyệt tất cả"</em>
            </div>
        </div>`;
    } else {
        urgentHtml = `<div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:10px;padding:10px 14px;margin-top:12px;color:#065f46;font-size:0.83rem;">
            <i class="fas fa-check-circle" style="color:#10b981;"></i> Tuyệt vời! Không có bài nào đang chờ duyệt.
        </div>`;
    }

    const greetingMd = `Chào ${timeGreet}, **${ADMIN_NAME}**! 👋\n\nTôi là **Admin AI Assistant** — Mái Nhà Xanh. Tóm tắt hệ thống lúc bạn đăng nhập:\n\n- 📋 **Chờ duyệt:** ${PENDING_COUNT} bài\n- ✅ **Đã duyệt:** ${APPROVED_COUNT} bài\n- 📦 **Tổng cộng:** ${TOTAL_COUNT} bài đăng\n\nTôi hỗ trợ **tra cứu, thống kê, xuất Excel** tự động trơn tru và **duyệt/từ chối/xóa bài đăng** an toàn.`;

    const container = document.getElementById('chatMessages');
    const el = document.createElement('div');
    el.className = 'msg-row';
    const time = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    el.innerHTML = `
        <div class="msg-avatar ai-avatar"><i class="fas fa-robot"></i></div>
        <div style="max-width:82%">
            <div class="msg-bubble ai-bubble">${formatAIText(greetingMd)}${urgentHtml}</div>
            <span class="msg-time">${time}</span>
        </div>`;
    container.appendChild(el);
    container.scrollTop = container.scrollHeight;
    chatHistory.push({ role: 'assistant', content: greetingMd });
}

// ── SEND MESSAGE ──────────────────────────────────────────────────
async function sendMessage() {
    if (isLoading) return;
    const msg = chatInput.value.trim();
    if (!msg) return;
    chatInput.value = '';
    chatInput.style.height = 'auto';

    appendMessage('user', msg);
    chatHistory.push({ role: 'user', content: msg });

    const typingEl = showTyping();
    setLoading(true);

    try {
        const res = await fetch('../api/admin_chatbot.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include',
            body: JSON.stringify({ message: msg, history: chatHistory.slice(-20) })
        });

        if (res.status === 401 || res.status === 403) {
            typingEl.remove();
            const errData = await res.json().catch(() => ({}));
            const msgText = errData.message || 'Phiên đăng nhập đã hết hạn hoặc không có quyền truy cập.';
            appendMessage('ai', '⚠️ ' + msgText);
            Swal.fire({
                title: 'Phiên làm việc hết hạn',
                text: msgText + ' Vui lòng đăng nhập lại.',
                icon: 'warning',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Đăng nhập lại'
            }).then(() => {
                window.location.href = '../login.php';
            });
            return;
        }

        const data = await res.json();
        typingEl.remove();
        if (data.success) {
            appendAIMessage(data.reply, data.action_results || []);
            chatHistory.push({ role: 'assistant', content: data.reply });
        } else {
            appendMessage('ai', '❌ Lỗi: ' + (data.message || 'Không thể kết nối AI'));
        }
    } catch (e) {
        typingEl.remove();
        appendMessage('ai', '❌ Lỗi kết nối server (HTTP 403 / Network Error). Vui lòng kiểm tra lại cấu hình Hosting / Session.');
    } finally {
        setLoading(false);
    }
}

function setLoading(state) {
    isLoading = state;
    document.getElementById('sendBtn').disabled = state;
}

function showTyping() {
    const container = document.getElementById('chatMessages');
    const el = document.createElement('div');
    el.className = 'typing-row';
    el.innerHTML = `<div class="msg-avatar ai-avatar"><i class="fas fa-robot"></i></div>
        <div class="typing-bubble">
            <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
        </div>`;
    container.appendChild(el);
    container.scrollTop = container.scrollHeight;
    return el;
}

function appendMessage(role, text) {
    const container = document.getElementById('chatMessages');
    const el = document.createElement('div');
    el.className = `msg-row ${role === 'user' ? 'user-row' : ''}`;
    const time = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    const avatar = role === 'user'
        ? `<div class="msg-avatar user-avatar-chat"><i class="fas fa-user"></i></div>`
        : `<div class="msg-avatar ai-avatar"><i class="fas fa-robot"></i></div>`;
    const bubbleClass = role === 'user' ? 'user-bubble' : 'ai-bubble';
    const safeText = role === 'user' ? escapeHtml(text) : formatAIText(text);
    el.innerHTML = `${role !== 'user' ? avatar : ''}
        <div><div class="msg-bubble ${bubbleClass}">${safeText}</div><span class="msg-time">${time}</span></div>
        ${role === 'user' ? avatar : ''}`;
    container.appendChild(el);
    container.scrollTop = container.scrollHeight;
    return el;
}

function appendAIMessage(text, actionResults) {
    const container = document.getElementById('chatMessages');
    const el = document.createElement('div');
    el.className = 'msg-row';
    const time = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    let actionHtml = '';
    for (const result of actionResults) actionHtml += buildActionCard(result);
    el.innerHTML = `<div class="msg-avatar ai-avatar"><i class="fas fa-robot"></i></div>
        <div style="max-width:84%">
            <div class="msg-bubble ai-bubble">${formatAIText(text)}</div>
            ${actionHtml}
            <span class="msg-time">${time}</span>
        </div>`;
    container.appendChild(el);
    container.scrollTop = container.scrollHeight;
}

// ── BUILD ACTION CARDS ────────────────────────────────────────────
function buildActionCard(result) {
    if (!result) return '';

    // Post list with bulk checkboxes
    if (result.type === 'post_list') {
        const posts = result.data || [];
        if (!posts.length) return `<div class="action-card" style="margin-top:10px;">
            <div class="action-card-header list"><i class="fas fa-inbox"></i> ${escapeHtml(result.label)} — Không có kết quả</div>
        </div>`;

        const cid = 'c' + (++_cardCounter);
        const hasPending = posts.some(p => p.trangthai === 'cho_duyet');

        const rows = posts.map(p => {
            const badge = p.trangthai === 'cho_duyet'
                ? '<span class="badge-warn"><i class="fas fa-clock"></i> Chờ</span>'
                : p.trangthai === 'da_duyet'
                    ? '<span class="badge-ok"><i class="fas fa-check"></i> Duyệt</span>'
                    : '<span class="badge-no"><i class="fas fa-times"></i> Từ chối</span>';
            const btns = p.trangthai === 'cho_duyet'
                ? `<button class="post-result-btn btn-approve" onclick="confirmApprove(${p.id})" title="Duyệt"><i class="fas fa-check"></i></button>
                   <button class="post-result-btn btn-reject" onclick="confirmReject(${p.id})" title="Từ chối"><i class="fas fa-times"></i></button>
                   <button class="post-result-btn btn-delete" onclick="confirmDelete(${p.id})" title="Xóa"><i class="fas fa-trash"></i></button>`
                : `<button class="post-result-btn btn-delete" onclick="confirmDelete(${p.id})" title="Xóa"><i class="fas fa-trash"></i></button>`;
            return `<div class="post-result-item" data-id="${p.id}">
                <input type="checkbox" class="post-cb-${cid}" value="${p.id}" data-status="${p.trangthai}"
                    style="width:15px;height:15px;accent-color:#10b981;flex-shrink:0;cursor:pointer;"
                    onchange="onBulkChange('${cid}')">
                <span class="post-result-id">#${p.id}</span>
                <span class="post-result-title" title="${escapeHtml(p.tieude)}">${escapeHtml(p.tieude)}</span>
                <span class="post-result-price">${Number(p.gia).toLocaleString('vi-VN')}₫</span>
                <span class="post-result-status">${badge}</span>
                <span class="post-result-actions">${btns}</span>
            </div>`;
        }).join('');

        const bulkBar = `<div class="bulk-bar" id="bb-${cid}" style="display:none;padding:8px 12px;background:rgba(16,185,129,0.07);border-bottom:1px solid rgba(16,185,129,0.15);align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:0.75rem;font-weight:700;color:#065f46;" id="bc-${cid}">0 đã chọn</span>
            <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;">
                ${hasPending ? `<button class="post-result-btn btn-approve" style="padding:4px 10px;font-size:0.72rem;" onclick="bulkAction('${cid}','approve')"><i class="fas fa-check-circle"></i> Duyệt đã chọn</button>
                <button class="post-result-btn btn-reject" style="padding:4px 10px;font-size:0.72rem;" onclick="bulkAction('${cid}','reject')"><i class="fas fa-times-circle"></i> Từ chối đã chọn</button>` : ''}
                <button class="post-result-btn btn-delete" style="padding:4px 10px;font-size:0.72rem;" onclick="bulkAction('${cid}','delete')"><i class="fas fa-trash-alt"></i> Xóa đã chọn</button>
            </div>
        </div>`;

        return `<div class="action-card" style="margin-top:10px;">
            <div class="action-card-header list" style="justify-content:space-between;">
                <span><i class="fas fa-list"></i> ${escapeHtml(result.label)} (${posts.length} bài)</span>
                <label style="display:flex;align-items:center;gap:5px;font-size:0.72rem;font-weight:600;cursor:pointer;color:inherit;white-space:nowrap;">
                    <input type="checkbox" id="sa-${cid}" style="accent-color:#10b981;width:13px;height:13px;" onchange="toggleSelectAll('${cid}',this.checked)">
                    Chọn tất cả
                </label>
            </div>
            ${bulkBar}
            <div class="action-card-body action-card-body-scroll">${rows}</div>
        </div>`;
    }

    // Stats
    if (result.type === 'stats') {
        const d = result.data;
        let pending = 0, approved = 0, rejected = 0;
        (d.by_status || []).forEach(r => {
            if (r.trangthai === 'cho_duyet') pending = r.count;
            if (r.trangthai === 'da_duyet')  approved = r.count;
            if (r.trangthai === 'tu_choi')   rejected = r.count;
        });
        return `<div class="action-card" style="margin-top:10px;">
            <div class="action-card-header stats"><i class="fas fa-chart-bar"></i> Thống kê bài đăng</div>
            <div class="action-card-body">
                <div class="stats-result-grid">
                    <div class="stats-result-item"><div class="stats-result-num" style="color:#f59e0b;">${pending}</div><div class="stats-result-label">Chờ duyệt</div></div>
                    <div class="stats-result-item"><div class="stats-result-num" style="color:#10b981;">${approved}</div><div class="stats-result-label">Đã duyệt</div></div>
                    <div class="stats-result-item"><div class="stats-result-num" style="color:#ef4444;">${rejected}</div><div class="stats-result-label">Từ chối</div></div>
                    <div class="stats-result-item"><div class="stats-result-num" style="color:#3b82f6;">${d.total||0}</div><div class="stats-result-label">Tổng</div></div>
                    <div class="stats-result-item"><div class="stats-result-num" style="color:#8b5cf6;">${d.today||0}</div><div class="stats-result-label">Hôm nay</div></div>
                    <div class="stats-result-item"><div class="stats-result-num" style="color:#0d9488;">${d.total_posters||0}</div><div class="stats-result-label">Người đăng</div></div>
                </div>
            </div>
        </div>`;
    }

    // User stats
    if (result.type === 'user_stats') {
        const d = result.data;
        const posters = (d.top_posters || []).map(p =>
            `<div class="post-result-item">
                <span class="post-result-id" style="background:rgba(16,185,129,0.1);color:#065f46;">${escapeHtml(p.username||p.nguoidang||'—')}</span>
                <span class="post-result-title">${p.post_count} bài đăng</span>
            </div>`).join('');
        return `<div class="action-card" style="margin-top:10px;">
            <div class="action-card-header stats"><i class="fas fa-users"></i> Thống kê người dùng</div>
            <div class="action-card-body">
                <div style="padding:10px 14px;display:flex;gap:20px;border-bottom:1px solid rgba(0,0,0,0.05);">
                    <div style="text-align:center;"><div style="font-size:1.3rem;font-weight:800;color:#7c3aed;">${d.total||0}</div><div style="font-size:0.68rem;color:var(--text-muted);">Tổng users</div></div>
                    <div style="text-align:center;"><div style="font-size:1.3rem;font-weight:800;color:#10b981;">${d.today||0}</div><div style="font-size:0.68rem;color:var(--text-muted);">Đăng hôm nay</div></div>
                </div>
                ${posters ? `<div style="font-size:0.72rem;font-weight:700;color:var(--text-muted);padding:8px 14px 4px;text-transform:uppercase;">Top đăng bài</div>${posters}` : ''}
            </div>
        </div>`;
    }

    // Post detail
    if (result.type === 'post_detail') {
        const p = result.data;
        if (!p) return `<div class="action-card" style="margin-top:10px;"><div class="action-card-header error"><i class="fas fa-exclamation-circle"></i> Không tìm thấy bài đăng</div></div>`;
        const badge = p.trangthai === 'cho_duyet' ? '<span class="badge-warn">⏳ Chờ duyệt</span>'
            : p.trangthai === 'da_duyet' ? '<span class="badge-ok">✅ Đã duyệt</span>' : '<span class="badge-no">🚫 Từ chối</span>';
        return `<div class="action-card" style="margin-top:10px;">
            <div class="action-card-header list"><i class="fas fa-info-circle"></i> Chi tiết bài #${p.id}</div>
            <div class="action-card-body" style="padding:12px 14px;">
                <div style="font-weight:700;font-size:0.9rem;margin-bottom:10px;">${escapeHtml(p.tieude)}</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:0.78rem;color:var(--text-muted);margin-bottom:12px;">
                    <div>📍 ${escapeHtml(p.diachi||'—')}</div>
                    <div>💰 ${Number(p.gia).toLocaleString('vi-VN')}₫</div>
                    <div>📐 ${p.dientich||'—'} m²</div>
                    <div>👤 ${escapeHtml(p.nguoidang||'—')}</div>
                    <div>📅 ${p.ngaydang ? new Date(p.ngaydang).toLocaleDateString('vi-VN') : '—'}</div>
                    <div>${badge}</div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    ${p.trangthai !== 'da_duyet' ? `<button class="post-result-btn btn-approve" style="padding:6px 14px;font-size:0.78rem;" onclick="confirmApprove(${p.id})"><i class="fas fa-check"></i> Duyệt</button>` : ''}
                    ${p.trangthai !== 'tu_choi'  ? `<button class="post-result-btn btn-reject"  style="padding:6px 14px;font-size:0.78rem;" onclick="confirmReject(${p.id})"><i class="fas fa-times"></i> Từ chối</button>` : ''}
                    <button class="post-result-btn btn-delete" style="padding:6px 14px;font-size:0.78rem;" onclick="confirmDelete(${p.id})"><i class="fas fa-trash"></i> Xóa vĩnh viễn</button>
                </div>
            </div>
        </div>`;
    }

    // Action result
    if (result.type === 'action_result') {
        const r = result.result;
        const ok = r && r.success;
        return `<div class="action-card" style="margin-top:10px;">
            <div class="action-card-header ${ok ? 'success' : 'error'}">
                <i class="fas fa-${ok ? 'check-circle' : 'exclamation-circle'}"></i>
                ${ok ? 'Thao tác thành công' : 'Thao tác thất bại'}
            </div>
            <div class="action-card-body" style="padding:10px 14px;font-size:0.82rem;">${escapeHtml(r?.message || '—')}</div>
        </div>`;
    }

    // Excel Export Card
    if (result.type === 'excel_export') {
        return `<div class="action-card" style="margin-top:12px; border: 1.5px solid #107c41; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 15px rgba(16,124,65,0.15);">
            <div class="action-card-header" style="background: linear-gradient(135deg, #107c41 0%, #1f9a55 100%); color: #ffffff; padding: 12px 16px; font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; justify-content: space-between;">
                <span><i class="fas fa-file-excel" style="font-size: 1.1rem; margin-right: 8px;"></i> ${escapeHtml(result.label || 'Xuất File Excel')}</span>
                <span style="font-size: 0.72rem; background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 12px; font-weight: 600;">.XLS (UTF-8)</span>
            </div>
            <div class="action-card-body" style="padding: 14px 16px; background: #ffffff;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div>
                        <div style="font-weight: 700; font-size: 0.95rem; color: #1e293b; margin-bottom: 2px;">${escapeHtml(result.title)}</div>
                        <div style="font-size: 0.78rem; color: #64748b;">Dữ liệu sẵn sàng: <strong style="color: #107c41;">${result.count || 0}</strong> mục</div>
                    </div>
                    <div style="font-size: 2.2rem; color: #107c41; opacity: 0.85;"><i class="fas fa-file-csv"></i></div>
                </div>
                <a href="${escapeHtml(result.download_url)}" target="_blank" class="post-result-btn" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 11px; background: linear-gradient(135deg, #107c41, #0b5c30); color: #ffffff; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.88rem; box-shadow: 0 4px 12px rgba(16,124,65,0.25); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                    <i class="fas fa-download"></i> Tải Xuất File Excel Ngay (${result.count || 0} mục)
                </a>
            </div>
        </div>`;
    }
    return '';
}

// ── BULK SELECT ────────────────────────────────────────────────────
function toggleSelectAll(cid, checked) {
    document.querySelectorAll(`.post-cb-${cid}`).forEach(cb => cb.checked = checked);
    onBulkChange(cid);
}
function onBulkChange(cid) {
    const checked = document.querySelectorAll(`.post-cb-${cid}:checked`);
    const all     = document.querySelectorAll(`.post-cb-${cid}`);
    const bar     = document.getElementById(`bb-${cid}`);
    const count   = document.getElementById(`bc-${cid}`);
    const saEl    = document.getElementById(`sa-${cid}`);
    if (bar)   bar.style.display = checked.length > 0 ? 'flex' : 'none';
    if (count) count.textContent = `${checked.length} đã chọn`;
    if (saEl)  saEl.checked = all.length > 0 && checked.length === all.length;
}

// ── BULK ACTION với confirm ────────────────────────────────────────
async function bulkAction(cid, action) {
    const cbs = document.querySelectorAll(`.post-cb-${cid}:checked`);
    if (!cbs.length) return;

    const ids    = Array.from(cbs).map(cb => cb.value);
    const labels = { approve: 'Duyệt', reject: 'Từ chối', delete: 'Xóa vĩnh viễn' };
    const colors = { approve: '#10b981', reject: '#f59e0b', delete: '#ef4444' };
    const icons  = { approve: '✅', reject: '🚫', delete: '🗑️' };

    // Preview list of posts
    const listHtml = Array.from(cbs).map(cb => {
        const row   = cb.closest('[data-id]');
        const title = row?.querySelector('.post-result-title')?.textContent || '—';
        return `<div style="font-size:0.78rem;padding:4px 0;border-bottom:1px solid #e5e7eb;">
            <strong style="color:#374151;">#${cb.value}</strong>
            <span style="color:#6b7280;margin-left:6px;">${escapeHtml(title)}</span>
        </div>`;
    }).join('');

    const rejectField = action === 'reject'
        ? `<div style="margin-top:12px;text-align:left;">
            <label style="font-size:0.8rem;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Lý do từ chối (áp dụng cho tất cả):</label>
            <input id="bk-reason" class="swal2-input" placeholder="Ví dụ: Vi phạm quy định..." style="margin:0;font-size:0.85rem;">
          </div>` : '';

    const dangerNote = action === 'delete'
        ? `<p style="margin-top:10px;font-size:0.8rem;color:#ef4444;font-weight:700;text-align:left;"><i class="fas fa-exclamation-triangle"></i> KHÔNG THỂ HOÀN TÁC sau khi xóa!</p>` : '';

    const res = await Swal.fire({
        title: `${icons[action]} ${labels[action]} ${ids.length} bài?`,
        html: `<div style="text-align:left;">
            <p style="margin-bottom:10px;font-size:0.88rem;color:#374151;">Danh sách sẽ được <strong style="color:${colors[action]};">${labels[action].toLowerCase()}</strong>:</p>
            <div style="max-height:160px;overflow-y:auto;background:#f8fafc;border-radius:8px;padding:8px 12px;border:1px solid #e5e7eb;">${listHtml}</div>
            ${rejectField}${dangerNote}
        </div>`,
        icon: action === 'delete' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: colors[action],
        confirmButtonText: `${labels[action]} ${ids.length} bài`,
        cancelButtonText: 'Huỷ bỏ',
        reverseButtons: true,
        focusCancel: true,
    });
    if (!res.isConfirmed) return;

    const reason = action === 'reject'
        ? (document.getElementById('bk-reason')?.value?.trim() || '') : '';

    const idList = ids.join(', ');
    let cmd = '';
    if (action === 'approve')     cmd = `Duyệt các bài đăng có ID: ${idList}`;
    else if (action === 'reject') cmd = `Từ chối các bài đăng ID: ${idList}` + (reason ? ` với lý do: ${reason}` : '');
    else if (action === 'delete') cmd = `Xóa vĩnh viễn các bài đăng ID: ${idList}`;

    sendQuickCommand(cmd);
}

// ── SINGLE CONFIRM ACTIONS ─────────────────────────────────────────
async function confirmApprove(id) {
    const res = await Swal.fire({
        title: `✅ Xác nhận duyệt bài #${id}?`,
        html: `<p style="font-size:0.9rem;color:#374151;">Bài đăng sẽ được <strong style="color:#10b981;">duyệt và hiển thị</strong> trên hệ thống ngay lập tức.</p>`,
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#10b981', confirmButtonText: '<i class="fas fa-check"></i> Duyệt bài',
        cancelButtonText: 'Huỷ', reverseButtons: true,
    });
    if (res.isConfirmed) sendQuickCommand(`Duyệt bài đăng có ID ${id}`);
}

async function confirmReject(id) {
    const res = await Swal.fire({
        title: `🚫 Từ chối bài #${id}?`,
        html: `<div>
            <p style="font-size:0.9rem;color:#374151;margin-bottom:10px;">Nhập lý do từ chối để thông báo tới người đăng bài:</p>
            <input id="rj-reason" class="swal2-input" placeholder="Ví dụ: Ảnh mờ, thông tin không chính xác..." style="font-size:0.85rem;">
        </div>`,
        showCancelButton: true,
        confirmButtonColor: '#f59e0b', confirmButtonText: '<i class="fas fa-times"></i> Từ chối',
        cancelButtonText: 'Huỷ', reverseButtons: true,
    });
    if (res.isConfirmed) {
        const reason = document.getElementById('rj-reason')?.value?.trim() || '';
        sendQuickCommand(`Từ chối bài đăng ID ${id}` + (reason ? ` với lý do: ${reason}` : ''));
    }
}

async function confirmDelete(id) {
    const res = await Swal.fire({
        title: `🗑️ Xóa vĩnh viễn bài #${id}?`,
        html: `<p style="font-size:0.9rem;color:#374151;">Tất cả dữ liệu (ảnh, video, báo cáo) sẽ bị <strong style="color:#ef4444;">xóa vĩnh viễn</strong> và <strong>không thể khôi phục</strong>.</p>`,
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#ef4444', confirmButtonText: '<i class="fas fa-trash"></i> Xóa vĩnh viễn',
        cancelButtonText: 'Giữ lại', reverseButtons: true, focusCancel: true,
    });
    if (res.isConfirmed) sendQuickCommand(`Xóa vĩnh viễn bài đăng ID ${id}`);
}

// ── CLEAR CHAT ────────────────────────────────────────────────────
function clearChat() {
    Swal.fire({
        title: 'Xóa lịch sử chat?',
        text: 'AI sẽ gửi lại lời chào và thông báo tổng quan.',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#ef4444', confirmButtonText: 'Xóa & làm mới', cancelButtonText: 'Huỷ'
    }).then(r => {
        if (r.isConfirmed) {
            chatHistory = [];
            document.getElementById('chatMessages').innerHTML = '';
            setTimeout(() => showAutoGreeting(), 300);
        }
    });
}

// ── TEXT FORMATTING ───────────────────────────────────────────────
function formatAIText(text) {
    let html = escapeHtml(text);
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    html = html.replace(/`(.+?)`/g, '<code>$1</code>');
    html = html.replace(/^[-•] (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*?<\/li>(\n|$))+/gs, match => `<ul>${match}</ul>`);
    html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
    html = html.replace(/\n\n/g, '</p><p>');
    html = html.replace(/\n/g, '<br>');
    html = '<p>' + html + '</p>';
    html = html.replace(/<p><\/p>/g, '');
    return html;
}

function escapeHtml(text) {
    if (typeof text !== 'string') return String(text ?? '');
    return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── INIT ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => showAutoGreeting(), 600);
});
</script>
<script src="assets/js/admin-notifications.js"></script>
</body>
</html>
