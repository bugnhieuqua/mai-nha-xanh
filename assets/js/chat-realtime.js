/**
 * chat-realtime.js - Real-time chat & status synchronization client-side
 */

// Xác định địa chỉ Socket.io server dựa trên hostname hiện tại
const socketHost = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? 'http://localhost:3000'
    : window.location.protocol + '//' + window.location.hostname + ':3000';

console.log(`[Realtime Client] Đang kết nối tới Socket Server tại: ${socketHost}`);
const socket = io(socketHost);

// Đăng ký online ngay khi kết nối thành công
socket.on('connect', () => {
    console.log('[Realtime Client] Đã kết nối thành công với Socket Server. ID:', socket.id);
    const userEl = document.getElementById('current-user-id');
    if (userEl && userEl.value) {
        socket.emit('user-online', userEl.value);
    }
});

// Khi mất kết nối
socket.on('disconnect', (reason) => {
    console.warn('[Realtime Client] Mất kết nối:', reason);
});

// Khi kết nối lỗi
socket.on('connect_error', (err) => {
    console.error('[Realtime Client] Lỗi kết nối Socket:', err.message);
});

// Cache danh sách online để renderContacts() có thể áp dụng sau khi DOM sẵn sàng
window._cachedOnlineIds = [];

// Hàm áp dụng trạng thái online/offline lên toàn bộ status-dot và status-text trong DOM
window.applyOnlineStatus = function(onlineIds) {
    window._cachedOnlineIds = onlineIds.map(String);

    document.querySelectorAll('.status-dot[data-user-id]').forEach(dot => {
        const uid = String(dot.dataset.userId);
        const isOnline = window._cachedOnlineIds.includes(uid);
        dot.classList.toggle('online', isOnline);
        dot.classList.toggle('offline', !isOnline);
    });

    document.querySelectorAll('.status-text[data-user-id]').forEach(span => {
        const uid = String(span.dataset.userId);
        const isOnline = window._cachedOnlineIds.includes(uid);
        span.innerText = isOnline ? 'Đang hoạt động' : 'Ngoại tuyến';
        span.classList.toggle('text-green-500', isOnline);
        span.classList.toggle('text-gray-400', !isOnline);
    });
};

// Nhận danh sách toàn bộ userId đang online từ server (ngay sau khi đăng ký user-online)
// Dùng để cập nhật giao diện ngay lập tức mà không cần chờ sự kiện riêng lẻ
socket.on('online-users-list', (onlineIds) => {
    console.log('[Realtime Client] Nhận danh sách online:', onlineIds);
    // Gọi hàm áp dụng (nếu DOM chưa có elements, cache sẽ được renderContacts() dùng sau)
    window.applyOnlineStatus(onlineIds);
});

// Lắng nghe thay đổi trạng thái online/offline của người dùng khác
socket.on('status-change', (data) => {
    const userIdChanged = data.userId;
    const newStatus = data.status; // 'online' hoặc 'offline'
    console.log(`[Status Change] User ${userIdChanged} đã chuyển sang ${newStatus}`);

    // Tìm và cập nhật chấm tròn chỉ thị trạng thái
    const statusDots = document.querySelectorAll(`.status-dot[data-user-id="${userIdChanged}"]`);
    statusDots.forEach(dot => {
        if (newStatus === 'online') {
            dot.classList.add('online');
            dot.classList.remove('offline');
        } else {
            dot.classList.add('offline');
            dot.classList.remove('online');
        }
    });

    // Cập nhật text chỉ thị trạng thái
    const statusTexts = document.querySelectorAll(`.status-text[data-user-id="${userIdChanged}"]`);
    statusTexts.forEach(textSpan => {
        if (newStatus === 'online') {
            textSpan.innerText = "Đang hoạt động";
            textSpan.classList.add('text-green-500');
            textSpan.classList.remove('text-gray-400');
        } else {
            textSpan.innerText = "Ngoại tuyến";
            textSpan.classList.add('text-gray-400');
            textSpan.classList.remove('text-green-500');
        }
    });
});

// Gửi tin nhắn lên Socket server
function sendRealtimeMessage() {
    const messageInput = document.getElementById('message-input');
    const activeChatUser = document.getElementById('active-chat-user-id');
    const currentConv = document.getElementById('current-conv-id');
    const currentUser = document.getElementById('current-user-id');

    if (!messageInput || !activeChatUser || !currentConv || !currentUser) return;

    const content = messageInput.value.trim();
    if (content === "") return;

    const conversationId = currentConv.value;
    const receiverId = activeChatUser.value;
    const senderId = currentUser.value;

    // Hiển thị tin nhắn ngay lập tức lên UI của mình trước
    appendRealtimeMessageToUI(senderId, content, 'sent');
    messageInput.value = "";

    // Gửi sự kiện lên WebSocket Server
    socket.emit('send-message', {
        conversationId,
        senderId,
        receiverId,
        messageContent: content
    });
}

// Nhận tin nhắn từ người khác
socket.on('receive-message', (data) => {
    const activeChatUser = document.getElementById('active-chat-user-id');
    
    // Nếu người dùng đang mở đúng cuộc hội thoại với người gửi, hiển thị tin nhắn lên UI
    if (activeChatUser && String(data.senderId) === String(activeChatUser.value)) {
        appendRealtimeMessageToUI(data.senderId, data.content, 'received');
    } else {
        // Nếu không, hiển thị thông báo đẩy (Toast Notification) ở góc màn hình
        if (typeof NotifSystem !== 'undefined' && typeof NotifSystem.showToast === 'function') {
            NotifSystem.showToast("Tin nhắn mới", "Bạn có tin nhắn mới từ đối tác!", "new_chat");
        } else {
            console.log(`[New Message Toast] Bạn có tin nhắn từ ${data.senderId}: ${data.content}`);
        }
    }
});

// Hàm chèn tin nhắn vào DOM
function appendRealtimeMessageToUI(senderId, text, direction) {
    const chatBox = document.getElementById('chat-history-box');
    if (!chatBox) return;

    const messageWrapper = document.createElement('div');
    messageWrapper.classList.add('message-row', direction); // CSS class: 'sent' hoặc 'received'
    messageWrapper.style.display = 'flex';
    messageWrapper.style.justifyContent = direction === 'sent' ? 'flex-end' : 'flex-start';
    messageWrapper.style.margin = '10px 0';

    const bubble = document.createElement('div');
    bubble.style.maxWidth = window.innerWidth <= 768 ? '85%' : '70%';
    bubble.style.padding = '10px 14px';
    bubble.style.borderRadius = '18px';
    bubble.style.fontSize = '0.92rem';
    bubble.style.lineHeight = '1.4';
    
    if (direction === 'sent') {
        bubble.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        bubble.style.color = '#fff';
        bubble.style.borderRadius = '18px 18px 0 18px';
    } else {
        bubble.style.background = 'var(--chat-bubble-bg-received, #f3f4f6)';
        bubble.style.color = 'var(--chat-bubble-color-received, #1f2937)';
        bubble.style.borderRadius = '18px 18px 18px 0';
    }

    const p = document.createElement('p');
    p.textContent = text;
    p.style.margin = '0';
    
    bubble.appendChild(p);
    messageWrapper.appendChild(bubble);
    chatBox.appendChild(messageWrapper);
    
    // Tự động cuộn xuống dưới cùng
    chatBox.scrollTop = chatBox.scrollHeight;
}

// Lắng nghe phím Enter trong ô nhập tin nhắn
document.addEventListener('DOMContentLoaded', () => {
    const messageInput = document.getElementById('message-input');
    if (messageInput) {
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendRealtimeMessage();
            }
        });
    }
});
