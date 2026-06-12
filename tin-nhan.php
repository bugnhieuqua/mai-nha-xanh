<?php
require_once 'config/bootstrap.php';

// Bảo vệ trang: chỉ cho phép người dùng đã đăng nhập truy cập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = "Tin Nhắn & Gọi Điện";
require_once 'includes/header.php';
?>

<div class="chat-page-wrapper" style="padding: 100px 0 40px 0; background: var(--body-bg, #f4f6f9); min-height: calc(100vh - 60px); display: flex; align-items: center; justify-content: center;">
    <div class="container" style="max-width: 1200px; width: 100%; padding: 0 15px;">
        
        <div class="chat-container-card" style="background: var(--card-bg, #fff); border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid var(--border-color, rgba(0,0,0,0.05)); overflow: hidden; display: flex; height: 650px;">
            
            <!-- SIDEBAR: Contact list -->
            <div class="chat-sidebar" style="width: 350px; border-right: 1px solid var(--border-color, #f1f5f9); display: flex; flex-direction: column; background: var(--sidebar-bg, #fafbfe); flex-shrink: 0;">
                <div class="sidebar-header" style="padding: 24px; border-bottom: 1px solid var(--border-color, #f1f5f9);">
                    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-color, #1e293b); display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-comments" style="color: #10b981;"></i> Trò chuyện
                    </h3>
                    <div style="margin-top: 15px; position: relative;">
                        <input type="text" id="contact-search" placeholder="Tìm kiếm đối tác..." style="width: 100%; padding: 10px 16px 10px 36px; border-radius: 30px; border: 1px solid var(--border-color, #cbd5e1); font-size: 0.88rem; outline: none; background: var(--input-bg, #fff); color: var(--text-color, #334155); transition: border-color 0.2s;">
                        <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem;"></i>
                    </div>
                </div>
                <!-- Danh sách liên hệ -->
                <div id="contacts-list-container" style="flex: 1; overflow-y: auto; padding: 10px 0;">
                    <div style="text-align: center; color: #94a3b8; padding: 40px 20px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 10px; color: #10b981;"></i>
                        <p style="font-size: 0.88rem;">Đang tải danh bạ...</p>
                    </div>
                </div>
            </div>
            
            <!-- MAIN CONTENT: Chat Box -->
            <div class="chat-main-area" style="flex: 1; display: flex; flex-direction: column; background: var(--chat-bg, #fff); position: relative;">
                
                <!-- Chatbox Header -->
                <div id="chat-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color, #f1f5f9); display: none; align-items: center; justify-content: space-between; background: var(--card-bg, #fff); z-index: 10;">
                    <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                        <!-- Back button for mobile responsive view -->
                        <button id="btn-back-to-list" style="background: none; border: none; color: #10b981; font-size: 1.2rem; cursor: pointer; display: none; margin-right: 8px; align-items: center; justify-content: center; padding: 4px 8px;" title="Quay lại danh bạ">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="partner-avatar-wrapper" style="position: relative; flex-shrink: 0;">
                            <img id="partner-avatar" src="assets/images/default_avatar.png" alt="Avatar" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover;">
                            <span id="partner-status-dot" class="status-dot offline" style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.15);"></span>
                        </div>
                        <div style="min-width: 0;">
                            <h4 id="partner-name" style="font-size: 1rem; font-weight: 700; color: var(--text-color, #1f2937); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Đối tác</h4>
                            <span id="partner-status-text" class="status-text" style="font-size: 0.75rem; color: #94a3b8;">Ngoại tuyến</span>
                        </div>
                    </div>
                    <!-- Nút Gọi thoại & follow -->
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button id="btn-follow-partner" style="background: transparent; border: 1px solid #10b981; color: #10b981; padding: 8px 18px; border-radius: 30px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-plus"></i> Theo dõi
                        </button>
                        <button id="btn-call-partner" title="Gọi thoại" style="background: linear-gradient(135deg, #10b981, #059669); border: none; color: #fff; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); transition: all 0.2s;">
                            <i class="fas fa-phone-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Chat History Box -->
                <div id="chat-history-box" style="flex: 1; overflow-y: auto; padding: 24px; display: none; flex-direction: column; background: var(--chat-history-bg, #f8fafc);">
                    <!-- Tin nhắn sẽ được chèn động ở đây -->
                </div>

                <!-- Input area -->
                <div id="chat-input-area" style="padding: 16px 24px; border-top: 1px solid var(--border-color, #f1f5f9); display: none; align-items: center; gap: 14px; background: var(--card-bg, #fff);">
                    <input type="text" id="message-input" placeholder="Nhập tin nhắn..." style="flex: 1; padding: 12px 20px; border-radius: 30px; border: 1px solid var(--border-color, #cbd5e1); font-size: 0.92rem; outline: none; background: var(--input-bg, #f3f4f6); color: var(--text-color, #1f2937); transition: all 0.2s;">
                    <button id="btn-send-message" onclick="sendRealtimeMessage()" style="background: linear-gradient(135deg, #10b981, #059669); border: none; color: #fff; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); transition: all 0.2s;">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>

                <!-- Empty State (Chưa chọn hội thoại) -->
                <div id="chat-empty-state" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 20px; animation: floatIcon 3s ease-in-out infinite;">💬</div>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-color, #334155); margin-bottom: 8px;">Chào mừng bạn đến với mục Nhắn Tin & Gọi Điện!</h3>
                    <p style="color: #94a3b8; font-size: 0.9rem; max-width: 360px;">Hãy chọn một đối tác từ danh sách bên trái để bắt đầu trò chuyện thời gian thực và gọi điện video WebRTC.</p>
                </div>

            </div>

        </div>

    </div>
</div>

<!-- Hidden variables for state tracking -->
<input type="hidden" id="active-chat-user-id" value="">
<input type="hidden" id="current-conv-id" value="">

<style>
/* CSS cho chấm trạng thái Online / Ngoại tuyến */
.status-dot {
    display: inline-block;
    background-color: #94a3b8; /* mặc định xám = offline */
    transition: background-color 0.4s ease;
}
.status-dot.online {
    background-color: #10b981 !important; /* xanh lá = online */
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.35);
}
.status-dot.offline {
    background-color: #94a3b8 !important; /* xám = offline */
    box-shadow: none;
}
/* CSS cho text trạng thái */
.status-text.text-green-500 { color: #10b981 !important; }
.status-text.text-gray-400  { color: #94a3b8 !important; }

/* CSS Styles for chat interaction */
.contact-item:hover {
    background: var(--notif-hover, #f3f4f6) !important;
}
.contact-item.active {
    background: var(--contact-active-bg, #ecfdf5) !important;
    border-left: 4px solid #10b981 !important;
}
#btn-call-partner:hover {
    transform: scale(1.08);
}
#btn-send-message:hover {
    transform: scale(1.08);
}
@keyframes floatIcon {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

/* Custom Slim Scrollbars for Premium Look */
#contacts-list-container::-webkit-scrollbar,
#chat-history-box::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
#contacts-list-container::-webkit-scrollbar-track,
#chat-history-box::-webkit-scrollbar-track {
    background: transparent;
}
#contacts-list-container::-webkit-scrollbar-thumb,
#chat-history-box::-webkit-scrollbar-thumb {
    background: rgba(16, 185, 129, 0.2);
    border-radius: 10px;
    transition: background 0.3s;
}
#contacts-list-container::-webkit-scrollbar-thumb:hover,
#chat-history-box::-webkit-scrollbar-thumb:hover {
    background: rgba(16, 185, 129, 0.45);
}

/* Responsive Styles for Mobile Layout */
@media (max-width: 768px) {
    .chat-page-wrapper {
        padding: 75px 0 0 0 !important;
        align-items: flex-start !important;
    }
    .chat-container-card {
        height: calc(100vh - 130px) !important; /* 75px header + 55px mobile-nav */
        border-radius: 0 !important;
        margin: 0 !important;
        border-left: none !important;
        border-right: none !important;
        box-shadow: none !important;
    }
    .chat-sidebar {
        width: 100% !important;
        flex: 1 !important;
        display: flex !important;
        border-right: none !important;
    }
    .chat-main-area {
        display: none !important;
        width: 100% !important;
        flex: 1 !important;
    }
    /* Toggle view on mobile when in-chat class is active */
    .chat-container-card.in-chat .chat-sidebar {
        display: none !important;
    }
    .chat-container-card.in-chat .chat-main-area {
        display: flex !important;
    }
    #btn-back-to-list {
        display: flex !important;
    }
    #btn-follow-partner {
        padding: 6px 12px !important;
        font-size: 0.75rem !important;
    }
    #partner-name {
        font-size: 0.9rem !important;
    }
    /* Đảm bảo input area ở trên mobile có padding an toàn */
    #chat-input-area {
        padding: 12px 14px !important;
        padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px)) !important;
    }
}
</style>

<script>
// Logic chính của trang tin-nhan.php
let allContacts = [];

document.addEventListener('DOMContentLoaded', () => {
    loadContacts();

    // Lọc tìm kiếm đối tác
    const searchInput = document.getElementById('contact-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            filterContacts(query);
        });
    }

    // Nút quay lại danh sách đối tác trên mobile
    const backBtn = document.getElementById('btn-back-to-list');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            const container = document.querySelector('.chat-container-card');
            if (container) {
                container.classList.remove('in-chat');
            }
        });
    }
});

// Nạp danh sách liên hệ từ PHP API
async function loadContacts() {
    const listContainer = document.getElementById('contacts-list-container');
    const currentUserId = document.getElementById('current-user-id').value;

    try {
        const res = await fetch(`api/api-chat-users-get.php?user_id=${currentUserId}`);
        const data = await res.json();
        
        if (data.status === 'success') {
            allContacts = data.contacts;
            renderContacts(allContacts);
            // Sau khi DOM đã sẵn sàng, chủ động yêu cầu server gửi lại danh sách online mới nhất
            if (typeof socket !== 'undefined' && socket.connected) {
                socket.emit('request-online-list');
            }
        } else {
            listContainer.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">${data.message}</p>`;
        }
    } catch (e) {
        listContainer.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">Lỗi kết nối máy chủ.</p>`;
    }
}

// Render danh sách lên giao diện
function renderContacts(contacts) {
    const listContainer = document.getElementById('contacts-list-container');
    if (!contacts || contacts.length === 0) {
        listContainer.innerHTML = `<p style="text-align:center;color:#94a3b8;padding:40px 20px;font-size:0.88rem;">Không tìm thấy đối tác nào.</p>`;
        return;
    }

    listContainer.innerHTML = contacts.map(c => {
        // Ưu tiên dùng cache online từ Socket nếu đã nhận được,
        // fallback về giá trị is_online từ DB nếu Socket chưa kết nối
        const cachedIds = (window._cachedOnlineIds && window._cachedOnlineIds.length > 0)
            ? window._cachedOnlineIds
            : null;
        const isOnline = cachedIds
            ? cachedIds.includes(String(c.id))
            : parseInt(c.is_online, 10) === 1;
        const statusClass = isOnline ? 'online' : 'offline';
        const statusText = isOnline ? 'Đang hoạt động' : 'Ngoại tuyến';
        const avatarUrl = c.avatar.startsWith('http') ? c.avatar : `./${c.avatar}`;

        return `
            <div class="contact-item" data-id="${c.id}" onclick="selectContact(${c.id}, '${c.hoten.replace(/'/g, "\\'")}', '${avatarUrl}')" style="display:flex; align-items:center; padding:12px 20px; cursor:pointer; border-bottom:1px solid var(--border-color, #f1f5f9); transition:all 0.15s; background: transparent;">
                <div style="position:relative; width:44px; height:44px; flex-shrink:0;">
                    <img src="${avatarUrl}" alt="Avatar" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                    <span class="status-dot ${statusClass}" data-user-id="${c.id}" style="position:absolute; bottom:0; right:0; width:12px; height:12px; border-radius:50%; border:2px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,0.15);"></span>
                </div>
                <div style="margin-left:12px; flex:1; min-width:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                        <h4 style="font-size:0.92rem; font-weight:700; color:var(--text-color, #1e293b); margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.hoten}</h4>
                    </div>
                    <span class="status-text text-xs ${isOnline ? 'text-green-500' : 'text-gray-400'}" data-user-id="${c.id}" style="font-size:0.75rem; font-weight:500;">${statusText}</span>
                </div>
            </div>
        `;
    }).join('');

    // Sau khi DOM được tạo, áp dụng lại trạng thái online từ Socket cache (nếu có)
    // Điều này xử lý trường hợp Socket event đến SAU khi renderContacts() đã chạy
    if (window._cachedOnlineIds && window._cachedOnlineIds.length > 0) {
        window.applyOnlineStatus(window._cachedOnlineIds);
    }
}

// Lọc tìm kiếm liên hệ
function filterContacts(query) {
    if (!query) {
        renderContacts(allContacts);
        return;
    }
    const filtered = allContacts.filter(c => 
        c.hoten.toLowerCase().includes(query) || 
        c.username.toLowerCase().includes(query)
    );
    renderContacts(filtered);
}

// Khi người dùng chọn một đối tác từ danh sách
async function selectContact(partnerId, partnerName, avatarUrl) {
    // Thêm class in-chat cho giao diện responsive trên mobile
    const container = document.querySelector('.chat-container-card');
    if (container) {
        container.classList.add('in-chat');
    }

    // 1. Cập nhật trạng thái Active trên danh sách liên hệ
    document.querySelectorAll('.contact-item').forEach(item => {
        item.classList.remove('active');
        if (parseInt(item.dataset.id, 10) === partnerId) {
            item.classList.add('active');
        }
    });

    // 2. Cập nhật biến hidden theo dõi
    document.getElementById('active-chat-user-id').value = partnerId;
    
    // 3. Hiển thị UI Chatbox
    document.getElementById('chat-empty-state').style.display = 'none';
    document.getElementById('chat-header').style.display = 'flex';
    document.getElementById('chat-history-box').style.display = 'flex';
    document.getElementById('chat-input-area').style.display = 'flex';

    // 4. Cập nhật thông tin Header đối tác
    document.getElementById('partner-name').innerText = partnerName;
    document.getElementById('partner-avatar').src = avatarUrl;
    
    // Đồng bộ class và text online/offline từ danh sách sang header
    const partnerDot = document.querySelector(`.status-dot[data-user-id="${partnerId}"]`);
    const headerDot = document.getElementById('partner-status-dot');
    const headerText = document.getElementById('partner-status-text');
    
    headerDot.dataset.userId = partnerId;
    headerText.dataset.userId = partnerId;
    
    if (partnerDot && partnerDot.classList.contains('online')) {
        headerDot.className = 'status-dot online';
        headerText.innerText = 'Đang hoạt động';
        headerText.className = 'status-text text-xs text-green-500';
    } else {
        headerDot.className = 'status-dot offline';
        headerText.innerText = 'Ngoại tuyến';
        headerText.className = 'status-text text-xs text-gray-400';
    }

    // 5. Nạp/Tạo phòng chat để lấy conversation_id và lịch sử nhắn tin
    const chatBox = document.getElementById('chat-history-box');
    chatBox.innerHTML = `
        <div style="text-align:center;color:#94a3b8;padding:40px;">
            <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;margin-bottom:10px;color:#10b981;"></i>
            <p style="font-size:0.88rem;">Đang nạp cuộc trò chuyện...</p>
        </div>
    `;

    try {
        const res = await fetch(`api/api-conversation-get-or-create.php?partner_id=${partnerId}`);
        const data = await res.json();
        
        if (data.status === 'success') {
            document.getElementById('current-conv-id').value = data.conversation_id;
            renderChatHistory(data.messages);
        } else {
            chatBox.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">Không thể tải tin nhắn.</p>`;
        }
    } catch(e) {
        chatBox.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">Lỗi kết nối máy chủ.</p>`;
    }

    // 6. Gán sự kiện gọi video cho nút Call
    const callBtn = document.getElementById('btn-call-partner');
    callBtn.onclick = () => {
        startVideoCall(partnerId, partnerName);
    };

    // 7. Nạp trạng thái follow của người này
    checkFollowStatus(partnerId);
}

// Render lịch sử chat vào khung tin nhắn
function renderChatHistory(messages) {
    const chatBox = document.getElementById('chat-history-box');
    const currentUserId = parseInt(document.getElementById('current-user-id').value, 10);
    
    if (!messages || messages.length === 0) {
        chatBox.innerHTML = `<div style="text-align:center;color:#94a3b8;padding:40px;font-size:0.88rem;">Chưa có tin nhắn nào. Hãy gửi lời chào đầu tiên! 👋</div>`;
        return;
    }

    chatBox.innerHTML = '';
    messages.forEach(msg => {
        const direction = parseInt(msg.sender_id, 10) === currentUserId ? 'sent' : 'received';
        appendRealtimeMessageToUI(msg.sender_id, msg.content, direction);
    });
}

// Kiểm tra và hiển thị nút follow/unfollow
async function checkFollowStatus(partnerId) {
    const followBtn = document.getElementById('btn-follow-partner');
    try {
        // Gửi request toggle mock với action view hoặc kiểm tra gián tiếp
        // Để đơn giản, ta gọi follow.php với action 'check' (nếu có) hoặc xem response từ server
        // Ở đây follow.php được thiết kế dạng toggle, vậy ta có thể viết thêm logic kiểm tra hoặc chỉ hiển thị nút hoạt động bình thường
        followBtn.onclick = async () => {
            const res = await fetch('api/follow.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ following_id: partnerId, action: 'toggle' })
            });
            const data = await res.json();
            if (data.status === 'success') {
                updateFollowButtonUI(data.is_following);
            } else {
                alert(data.message);
            }
        };
    } catch(e) {}
}

function updateFollowButtonUI(isFollowing) {
    const followBtn = document.getElementById('btn-follow-partner');
    if (isFollowing) {
        followBtn.innerHTML = `<i class="fas fa-check"></i> Đang theo dõi`;
        followBtn.style.background = '#e2e8f0';
        followBtn.style.color = '#475569';
        followBtn.style.borderColor = '#cbd5e1';
    } else {
        followBtn.innerHTML = `<i class="fas fa-plus"></i> Theo dõi`;
        followBtn.style.background = 'transparent';
        followBtn.style.color = '#10b981';
        followBtn.style.borderColor = '#10b981';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
