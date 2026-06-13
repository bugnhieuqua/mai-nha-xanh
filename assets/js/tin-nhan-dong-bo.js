/**
 * tin-nhan-dong-bo.js - Real-time chat & status synchronization client-side
 */

// Xác định địa chỉ Socket.io server dựa trên cấu hình PHP hoặc hostname hiện tại
const socketHost = window.REALTIME_SERVER_URL || 
    ((window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost:3000'
        : window.location.protocol + '//' + window.location.hostname + ':3000');

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

    // Đẩy người nhận lên đầu danh sách liên hệ của người gửi
    if (typeof allContacts !== 'undefined' && typeof renderContacts === 'function') {
        const receiverIdStr = String(receiverId);
        const idx = allContacts.findIndex(c => String(c.id) === receiverIdStr);
        if (idx > 0) { // idx > 0 nghĩa là chưa ở đầu
            const receiverObj = allContacts.splice(idx, 1)[0];
            allContacts.unshift(receiverObj);
            renderContacts(allContacts);
            // Giữ trạng thái active sau khi vẽ lại
            document.querySelectorAll('.contact-item').forEach(item => {
                item.style.background = String(item.dataset.id) === receiverIdStr
                    ? 'var(--chat-bg-selected, #e2e8f0)'
                    : 'transparent';
            });
        }
    }
}

// Nhận tin nhắn từ người khác
socket.on('receive-message', (data) => {
    const activeChatUser = document.getElementById('active-chat-user-id');
    const senderIdStr = String(data.senderId);
    
    // Nếu người dùng đang mở đúng cuộc hội thoại với người gửi, hiển thị tin nhắn lên UI
    if (activeChatUser && senderIdStr === String(activeChatUser.value)) {
        appendRealtimeMessageToUI(data.senderId, data.content, 'received');
        
        // Vì đang mở sẵn khung chat, gọi API ngầm báo đã đọc để tin nhắn không bị báo chưa đọc
        fetch('api/api-mark-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                conversation_id: document.getElementById('current-conv-id')?.value || 0,
                user_id: document.getElementById('current-user-id')?.value || 0
            })
        }).catch(err => console.log(err));

    } else {
        // Nếu không, hiển thị thông báo đẩy (Toast Notification) ở góc màn hình
        if (typeof NotifSystem !== 'undefined' && typeof NotifSystem.showToast === 'function') {
            NotifSystem.showToast("Tin nhắn mới", "Bạn có tin nhắn mới từ đối tác!", "new_chat");
        } else {
            console.log(`[New Message Toast] Bạn có tin nhắn từ ${data.senderId}: ${data.content}`);
        }
        
        // Tăng huy hiệu đỏ (unread_count) lên +1
        if (typeof allContacts !== 'undefined' && allContacts.length > 0) {
            const idx = allContacts.findIndex(c => String(c.id) === senderIdStr);
            if (idx > -1) {
                allContacts[idx].unread_count = (parseInt(allContacts[idx].unread_count) || 0) + 1;
            }
        } else if (typeof loadContacts === 'function') {
            loadContacts();
        }

        // Cập nhật badge tổng số tin nhắn chưa đọc lên +1
        if (typeof window.updateGlobalMessageBadge === 'function') {
            window.updateGlobalMessageBadge(1);
        }
    }

    // Đẩy người gửi lên vị trí đầu tiên trong danh sách liên hệ (Sắp xếp thời gian thực)
    if (typeof allContacts !== 'undefined' && typeof renderContacts === 'function') {
        const idx = allContacts.findIndex(c => String(c.id) === senderIdStr);
        if (idx > -1) {
            const senderObj = allContacts.splice(idx, 1)[0];
            allContacts.unshift(senderObj); // Đưa lên đầu mảng
            renderContacts(allContacts);    // Vẽ lại danh sách
            
            // Giữ lại hiệu ứng nền (selected/active) cho liên hệ đang trò chuyện
            if (activeChatUser && activeChatUser.value) {
                const activeId = activeChatUser.value;
                document.querySelectorAll('.contact-item').forEach(item => {
                    item.style.background = 'transparent';
                    if (String(item.dataset.id) === String(activeId)) {
                        item.style.background = 'var(--chat-bg-selected, #e2e8f0)';
                    }
                });
            }
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

// Định nghĩa biến lưu trữ tổng số tin nhắn chưa đọc trong memory
window.unreadMsgCount = 0;

// Hàm cập nhật badge tổng số tin nhắn chưa đọc trên thanh menu (Tin nhắn)
window.updateGlobalMessageBadge = function(increment = 0) {
    const badge = document.getElementById('global-msg-badge');
    if (!badge) return;

    if (typeof allContacts !== 'undefined' && allContacts.length > 0) {
        // Nếu đang ở trang tin-nhan.php và danh bạ đã tải xong
        const total = allContacts.reduce((sum, c) => sum + (parseInt(c.unread_count) || 0), 0);
        window.unreadMsgCount = total;
        if (total > 0) {
            badge.innerText = total > 99 ? '99+' : total;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    } else {
        // Nếu ở các trang khác hoặc danh bạ chưa load
        if (increment > 0) {
            window.unreadMsgCount += increment;
            if (window.unreadMsgCount > 0) {
                badge.innerText = window.unreadMsgCount > 99 ? '99+' : window.unreadMsgCount;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        } else {
            // Lần đầu load trang: fetch lấy tổng chưa đọc
            const userEl = document.getElementById('current-user-id');
            if (userEl && userEl.value) {
                fetch(`api/api-danh-sach-user.php?user_id=${userEl.value}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success' && data.contacts) {
                            const total = data.contacts.reduce((sum, c) => sum + (parseInt(c.unread_count) || 0), 0);
                            window.unreadMsgCount = total;
                            if (total > 0) {
                                badge.innerText = total > 99 ? '99+' : total;
                                badge.style.display = 'inline-flex';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    })
                    .catch(err => console.log('Lỗi cập nhật badge tin nhắn:', err));
            }
        }
    }
};

// Lắng nghe phím Enter trong ô nhập tin nhắn & Khởi tạo badge tổng khi load trang
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
    
    // Tự động chạy lần đầu để nạp số tin nhắn chưa đọc lên menu
    window.updateGlobalMessageBadge();
});
