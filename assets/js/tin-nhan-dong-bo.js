/**
 * tin-nhan-dong-bo.js - Real-time chat & status synchronization client-side
 */

const socketHost = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? 'http://localhost:3000'
    : (window.REALTIME_SERVER_URL || window.location.protocol + '//' + window.location.hostname + ':3000');

console.log(`[Realtime Client] Đang kết nối tới Socket Server tại: ${socketHost}`);
const socket = io(socketHost);

// Đăng ký online ngay khi kết nối thành công
socket.on('connect', () => {
    console.log('[Realtime Client] Đã kết nối thành công với Socket Server. ID:', socket.id);
    const userEl = document.getElementById('current-user-id');
    if (userEl && userEl.value) {
        socket.emit('user-online', userEl.value);
    }

    // Yêu cầu join vào các room nhóm sau khi danh sách nhóm được tải
    joinAllMyGroupRooms();
});

socket.on('disconnect', (reason) => {
    console.warn('[Realtime Client] Mất kết nối:', reason);
});

socket.on('connect_error', (err) => {
    console.error('[Realtime Client] Lỗi kết nối Socket:', err.message);
});

window._cachedOnlineIds = [];

// Hàm áp dụng trạng thái online/offline lên toàn bộ status-dot và status-text trong DOM
window.applyOnlineStatus = function (onlineIds) {
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

socket.on('online-users-list', (onlineIds) => {
    console.log('[Realtime Client] Nhận danh sách online:', onlineIds);
    window.applyOnlineStatus(onlineIds);
});

socket.on('status-change', (data) => {
    const userIdChanged = data.userId;
    const newStatus = data.status;

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

// Hàm join toàn bộ room của các nhóm chat mà user tham gia
function joinAllMyGroupRooms() {
    if (typeof allGroups !== 'undefined' && allGroups.length > 0) {
        const groupIds = allGroups.map(g => g.conversation_id);
        socket.emit('join-group-rooms', groupIds);
        console.log('[Realtime Client] Đã gửi danh sách join room nhóm:', groupIds);
    }
}

// Gửi tin nhắn lên Socket server
function sendRealtimeMessage() {
    const messageInput = document.getElementById('message-input');
    const activeChatUser = document.getElementById('active-chat-user-id');
    const currentConv = document.getElementById('current-conv-id');
    const currentUser = document.getElementById('current-user-id');
    const currentUserName = document.getElementById('current-user-name')?.value || 'Thành viên';
    const isGroup = document.getElementById('is-group-chat')?.value === '1';

    if (!messageInput || !currentConv || !currentUser) return;

    const content = messageInput.value.trim();
    if (content === "") return;

    const conversationId = currentConv.value;
    const receiverId = isGroup ? null : activeChatUser.value;
    const senderId = currentUser.value;

    // Hiển thị tin nhắn ngay lập tức lên UI của mình trước
    appendRealtimeMessageToUI(senderId, content, 'sent', currentUserName, null, 'text');
    messageInput.value = "";

    // Gửi sự kiện lên WebSocket Server
    socket.emit('send-message', {
        conversationId,
        senderId,
        receiverId,
        messageContent: content,
        isGroup: isGroup,
        senderName: currentUserName,
        messageType: 'text'
    });

    // Sắp xếp lại danh sách chat đưa phòng chat này lên đầu
    moveChatToTop(conversationId, isGroup);
}

// Gửi tin nhắn đa phương tiện (Ảnh / File)
function sendRealtimeMediaMessage(url, type) {
    const activeChatUser = document.getElementById('active-chat-user-id');
    const currentConv = document.getElementById('current-conv-id');
    const currentUser = document.getElementById('current-user-id');
    const currentUserName = document.getElementById('current-user-name')?.value || 'Thành viên';
    const isGroup = document.getElementById('is-group-chat')?.value === '1';

    if (!currentConv || !currentUser) return;

    const conversationId = currentConv.value;
    const receiverId = isGroup ? null : activeChatUser.value;
    const senderId = currentUser.value;

    appendRealtimeMessageToUI(senderId, url, 'sent', currentUserName, null, type);

    socket.emit('send-message', {
        conversationId,
        senderId,
        receiverId,
        messageContent: url,
        isGroup: isGroup,
        senderName: currentUserName,
        messageType: type
    });

    moveChatToTop(conversationId, isGroup);
}

// Nhận tin nhắn từ người khác
socket.on('receive-message', (data) => {
    const currentConv = document.getElementById('current-conv-id');
    const currentConvId = currentConv ? currentConv.value : null;

    if (currentConvId && String(data.conversationId) === String(currentConvId)) {
        // Đang mở đúng khung chat
        const direction = String(data.senderId) === String(document.getElementById('current-user-id').value) ? 'sent' : 'received';
        appendRealtimeMessageToUI(data.senderId, data.content, direction, data.senderName, null, data.type || 'text', true);

        // Đánh dấu đã đọc
        fetch('api/api-mark-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                conversation_id: data.conversationId,
                user_id: document.getElementById('current-user-id')?.value || 0
            })
        }).catch(err => console.log(err));
    } else {
        // Không mở đúng khung chat -> Báo tin nhắn mới
        if (typeof NotifSystem !== 'undefined' && typeof NotifSystem.showToast === 'function') {
            NotifSystem.showToast("Tin nhắn mới", `Bạn có tin nhắn mới từ ${data.isGroup ? 'Nhóm' : 'đối tác'}!`, "new_chat");
        }

        // Tăng badge chưa đọc
        incrementUnreadCount(data.conversationId, data.isGroup);
    }

    // Đẩy phòng chat lên đầu danh sách
    moveChatToTop(data.conversationId, data.isGroup);
});

// Lắng nghe sự kiện AI khoá nhóm
socket.on('group-locked', (data) => {
    const currentConv = document.getElementById('current-conv-id');
    if (currentConv && String(currentConv.value) === String(data.conversationId)) {
        // Đang mở đúng nhóm bị khoá -> Hiện cảnh báo và khoá khung chat
        showGroupLockedUI(data.reason);
    }

    // Cập nhật trạng thái khoá trong allGroups
    if (typeof allGroups !== 'undefined') {
        const group = allGroups.find(g => String(g.conversation_id) === String(data.conversationId));
        if (group) {
            group.is_locked = 1;
            group.locked_reason = data.reason;
        }
    }
});

// Lắng nghe sự kiện thu hồi tin nhắn
socket.on('message-recalled', (data) => {
    const currentConv = document.getElementById('current-conv-id');
    if (currentConv && String(currentConv.value) === String(data.conversationId)) {
        const msgRow = document.getElementById('msg-item-' + data.messageId);
        if (msgRow) {
            const bubble = msgRow.querySelector('.message-bubble-container > div:first-child');
            if (bubble) {
                bubble.innerHTML = '';
                bubble.style.background = 'transparent';
                bubble.style.border = '1px dashed #94a3b8';
                bubble.style.borderRadius = '18px 18px 18px 0';
                bubble.style.padding = '8px 14px';
                const recalledText = document.createElement('span');
                recalledText.textContent = '🔁 Người gửi đã thu hồi tin nhắn này';
                recalledText.style.cssText = 'font-style:italic; color:#94a3b8; font-size:0.85rem;';
                bubble.appendChild(recalledText);
            }
        }
    }
});

// Lắng nghe sự kiện ghim/bỏ ghim tin nhắn (nhóm)
socket.on('message-pinned', (data) => {
    const currentConv = document.getElementById('current-conv-id');
    if (currentConv && String(currentConv.value) === String(data.conversationId)) {
        reloadCurrentChat();
    }
});

// Hàm di chuyển cuộc trò chuyện lên đầu danh sách
function moveChatToTop(convId, isGroup) {
    if (isGroup) {
        if (typeof allGroups !== 'undefined' && typeof renderContacts === 'function') {
            const idx = allGroups.findIndex(g => String(g.conversation_id) === String(convId));
            if (idx > -1) {
                const groupObj = allGroups.splice(idx, 1)[0];
                allGroups.unshift(groupObj);
                renderContacts(allContacts, allGroups);
            }
        }
    } else {
        if (typeof allContacts !== 'undefined' && typeof renderContacts === 'function') {
            // Đối với 1-1, cần tìm user thuộc cuộc trò chuyện này
            // Do trong server.js không truyền receiverId của group nhưng 1-1 có senderId,
            // ta chỉ cần tìm user từ danh sách dựa trên convId (cần gọi api check hoặc check cache)
            // Đơn giản hơn, loadContacts lại nếu cần hoặc duyệt qua contacts để tìm
            // Để tối ưu, ta lưu map convId -> partnerId khi nạp danh sách
            const contact = allContacts.find(c => String(c.id) === String(convId)); // fallback
            if (contact) {
                const idx = allContacts.indexOf(contact);
                allContacts.splice(idx, 1);
                allContacts.unshift(contact);
                renderContacts(allContacts, allGroups);
            } else {
                // Fetch danh sách mới để cập nhật
                loadContacts();
            }
        }
    }
}

// Tăng số tin chưa đọc của cuộc trò chuyện cụ thể
function incrementUnreadCount(convId, isGroup) {
    if (isGroup && typeof allGroups !== 'undefined') {
        const group = allGroups.find(g => String(g.conversation_id) === String(convId));
        if (group) {
            group.unread_count = (parseInt(group.unread_count) || 0) + 1;
            renderContacts(allContacts, allGroups);
        }
    } else if (!isGroup && typeof allContacts !== 'undefined') {
        // Tìm contact theo convId
        // fallback đơn giản là load lại
        loadContacts();
    }

    if (typeof window.updateGlobalMessageBadge === 'function') {
        window.updateGlobalMessageBadge(1);
    }
}

// Chèn tin nhắn vào UI
// autoScroll=true: cuộn xuống nếu user đang gần đáy (dùng cho realtime)
// autoScroll=false: không cuốn (dùng khi render lịch sử)
function appendRealtimeMessageToUI(senderId, text, direction, senderName = '', messageId = null, type = 'text', autoScroll = true) {
    const chatBox = document.getElementById('chat-history-box');
    if (!chatBox) return;

    const isGroup = document.getElementById('is-group-chat')?.value === '1';

    // Wrapper hàng tin nhắn
    const messageWrapper = document.createElement('div');
    if (messageId) messageWrapper.id = 'msg-item-' + messageId;
    messageWrapper.classList.add('message-row', direction);
    messageWrapper.style.cssText = `
        display: flex;
        flex-direction: column;
        align-items: ${direction === 'sent' ? 'flex-end' : 'flex-start'};
        margin: 6px 0;
    `;

    // Tên người gửi (chỉ hiển thị trong nhóm với tin nhận)
    if (direction === 'received' && isGroup && senderName) {
        const nameEl = document.createElement('span');
        nameEl.innerText = senderName;
        nameEl.style.cssText = `
            font-size: 0.72rem;
            color: #10b981;
            font-weight: 700;
            margin-bottom: 3px;
            margin-left: 12px;
        `;
        messageWrapper.appendChild(nameEl);
    }

    // Container bubble + action buttons
    const bubbleContainer = document.createElement('div');
    bubbleContainer.className = 'message-bubble-container';
    bubbleContainer.style.cssText = `
        display: flex;
        align-items: flex-end;
        gap: 6px;
        max-width: ${window.innerWidth <= 768 ? '85%' : '68%'};
        flex-direction: ${direction === 'sent' ? 'row-reverse' : 'row'};
    `;

    // Bubble chính
    const bubble = document.createElement('div');
    bubble.style.cssText = `
        padding: ${type === 'image' ? '4px' : '10px 14px'};
        font-size: 0.92rem;
        line-height: 1.5;
        word-break: break-word;
        max-width: 100%;
        position: relative;
    `;

    const isRecalled = (text === '[Tin nhắn đã được thu hồi]');

    if (isRecalled) {
        bubble.style.background = 'transparent';
        bubble.style.border = '1px dashed #94a3b8';
        bubble.style.color = '#94a3b8';
        bubble.style.padding = '8px 14px';
        bubble.style.borderRadius = direction === 'sent' ? '18px 18px 0 18px' : '18px 18px 18px 0';
    } else if (direction === 'sent') {
        bubble.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        bubble.style.color = '#fff';
        bubble.style.borderRadius = '18px 18px 0 18px';
    } else {
        bubble.style.background = 'var(--chat-bubble-bg-received, #f1f5f9)';
        bubble.style.color = 'var(--chat-bubble-color-received, #1e293b)';
        bubble.style.borderRadius = '18px 18px 18px 0';
    }

    // Nội dung theo type
    if (isRecalled) {
        const recalledText = document.createElement('span');
        recalledText.textContent = direction === 'sent' ? '🔁 Bạn đã thu hồi tin nhắn này' : '🔁 Đối tác đã thu hồi tin nhắn này';
        recalledText.style.cssText = 'font-style:italic; color:#94a3b8; font-size:0.85rem;';
        bubble.appendChild(recalledText);
    } else if (type === 'image') {
        bubble.style.padding = '4px';
        bubble.style.borderRadius = direction === 'sent' ? '14px 14px 0 14px' : '14px 14px 14px 0';
        bubble.style.background = 'transparent';
        bubble.style.boxShadow = 'none';

        const img = document.createElement('img');
        const src = (text.startsWith('http') || text.startsWith('data:') || text.startsWith('/')) ? text : './' + text;
        img.src = src;
        img.style.cssText = `
            max-width: 220px;
            max-height: 220px;
            border-radius: ${direction === 'sent' ? '14px 14px 0 14px' : '14px 14px 14px 0'};
            cursor: pointer;
            display: block;
            object-fit: cover;
        `;
        img.onerror = () => { img.src = 'assets/images/placeholder.png'; };
        img.onclick = () => {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed; inset: 0; background: rgba(0,0,0,0.92);
                display: flex; align-items: center; justify-content: center;
                z-index: 99999; cursor: zoom-out;
            `;
            const bigImg = document.createElement('img');
            bigImg.src = src;
            bigImg.style.cssText = 'max-width: 90vw; max-height: 90vh; border-radius: 8px; box-shadow: 0 10px 60px rgba(0,0,0,0.6);';
            overlay.appendChild(bigImg);
            overlay.onclick = () => document.body.removeChild(overlay);
            document.body.appendChild(overlay);
        };
        bubble.appendChild(img);
    } else if (type === 'file') {
        const fileName = text.split('/').pop().substring(0, 28);
        const fileUrl = (text.startsWith('http') || text.startsWith('/')) ? text : './' + text;
        bubble.innerHTML = `
            <a href="${fileUrl}" target="_blank" download
               style="display:inline-flex; align-items:center; gap:10px; text-decoration:none; color: ${direction === 'sent' ? '#fff' : '#10b981'};">
                <span style="background:${direction === 'sent' ? 'rgba(255,255,255,0.2)' : 'rgba(16,185,129,0.12)'}; 
                             border-radius:8px; padding:8px; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                    📎
                </span>
                <span>
                    <div style="font-weight:700; font-size:0.88rem; line-height:1.3;">${fileName}</div>
                    <div style="font-size:0.72rem; opacity:0.8;">Nhấn để tải xuống</div>
                </span>
            </a>
        `;
    } else {
        const p = document.createElement('p');
        p.textContent = text;
        p.style.margin = '0';
        bubble.appendChild(p);
    }

    bubbleContainer.appendChild(bubble);

    // ── Nút ··· (3 chấm) + Long press (mobile) ──
    if (!isRecalled) {
        const moreBtn = document.createElement('button');
        moreBtn.className = 'msg-more-trigger';
        moreBtn.title = 'Thao tác';
        moreBtn.innerHTML = '<i class="fas fa-ellipsis-v"></i>';
        moreBtn.style.opacity = '0';
        moreBtn.style.transition = 'opacity 0.2s';

        bubbleContainer.onmouseenter = () => moreBtn.style.opacity = '1';
        bubbleContainer.onmouseleave = () => moreBtn.style.opacity = '0';

        const openMsgMenu = (e) => {
            e.preventDefault();
            e.stopPropagation();

            let menu = document.getElementById('msg-context-menu');
            if (!menu) {
                menu = document.createElement('div');
                menu.id = 'msg-context-menu';
                menu.className = 'msg-context-menu';
                document.body.appendChild(menu);
            }
            menu.innerHTML = '';

            const canPin = true; // Cho phép bất kì ai ghim tin nhắn

            // 🗑️ Xoá cục bộ — luôn hiển thị
            if (messageId) {
                const delBtn = document.createElement('button');
                delBtn.className = 'msg-context-menu-item danger';
                delBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Xoá';
                delBtn.onclick = () => {
                    menu.style.display = 'none';
                    if (typeof deleteMessageLocally === 'function') deleteMessageLocally(messageId);
                };
                menu.appendChild(delBtn);
            }

            // ↩️ Thu hồi — chỉ tin của mình
            if (direction === 'sent' && messageId) {
                const recallBtn = document.createElement('button');
                recallBtn.className = 'msg-context-menu-item';
                recallBtn.innerHTML = '<i class="fas fa-undo-alt"></i> Thu hồi';
                recallBtn.onclick = () => {
                    menu.style.display = 'none';
                    recallChatMessage(messageId, bubble);
                };
                menu.appendChild(recallBtn);
            }

            // 📌 Ghim tin nhắn
            if (messageId && canPin) {
                const pinBtn = document.createElement('button');
                pinBtn.className = 'msg-context-menu-item';
                pinBtn.innerHTML = '<i class="fas fa-thumbtack"></i> Ghim';
                pinBtn.onclick = () => {
                    menu.style.display = 'none';
                    if (typeof pinChatMessage === 'function') pinChatMessage(messageId);
                };
                menu.appendChild(pinBtn);
            }

            if (menu.children.length === 0) return;

            menu.style.display = 'flex';

            // Tính toán vị trí thông minh
            const rect = moreBtn.getBoundingClientRect();
            let posX = rect.right + window.scrollX + 4;
            let posY = rect.top + window.scrollY;
            const mW = 190, mH = menu.children.length * 42 + 12;
            if (posX + mW > window.innerWidth + window.scrollX) posX = rect.left + window.scrollX - mW - 4;
            if (posY + mH > window.innerHeight + window.scrollY) posY = window.innerHeight + window.scrollY - mH - 8;
            menu.style.left = posX + 'px';
            menu.style.top = posY + 'px';
        };

        moreBtn.onclick = openMsgMenu;

        // Long press support cho mobile
        let pressTimer = null;
        bubble.addEventListener('touchstart', (e) => {
            pressTimer = setTimeout(() => openMsgMenu(e), 700);
        }, { passive: true });
        bubble.addEventListener('touchend', () => clearTimeout(pressTimer));
        bubble.addEventListener('touchmove', () => clearTimeout(pressTimer));

        bubbleContainer.appendChild(moreBtn);
    }

    messageWrapper.appendChild(bubbleContainer);
    chatBox.appendChild(messageWrapper);

    // Chỉ cuộn xuống nếu autoScroll=true VÀ user đang ở gần đáy (trong vòng 120px)
    if (autoScroll) {
        const nearBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 120;
        if (nearBottom) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }

}


// Hàm kích hoạt modal sửa tin nhắn chat 1-1 / Nhóm
async function triggerEditChatMessage(messageId, btnEl) {
    const rowEl = btnEl.closest('.message-row');
    const bubbleTextEl = rowEl.querySelector('p');
    const currentText = bubbleTextEl.textContent;

    const { value: text } = await Swal.fire({
        title: 'Chỉnh sửa tin nhắn',
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const fd = new FormData();
        fd.append('action', 'edit_chat');
        fd.append('message_id', messageId);
        fd.append('content', text);
        fd.append('csrf_token', csrfToken);

        try {
            const res = await fetch('api/edit-chat-message.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                bubbleTextEl.textContent = text;
                Swal.fire({ icon: 'success', title: 'Đã cập nhật tin nhắn', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
        }
    }
}

// Thu hồi tin nhắn
async function recallChatMessage(messageId, bubbleEl) {
    const confirm = await Swal.fire({
        title: 'Thu hồi tin nhắn?',
        text: 'Tin nhắn sẽ bị thu hồi. Hành động này không thể hoàn tác.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Thu hồi',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#ef4444',
    });
    if (!confirm.isConfirmed) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const fd = new FormData();
    fd.append('action', 'recall');
    fd.append('message_id', messageId);
    fd.append('csrf_token', csrfToken);

    try {
        const res = await fetch('api/recall-message.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // Cập nhật UI bubble thành dạng "đã thu hồi"
            bubbleEl.innerHTML = '';
            bubbleEl.style.background = 'transparent';
            bubbleEl.style.border = '1px dashed #94a3b8';
            bubbleEl.style.borderRadius = '18px 18px 0 18px';
            bubbleEl.style.padding = '8px 14px';
            const recalledText = document.createElement('span');
            recalledText.textContent = '🔁 Bạn đã thu hồi tin nhắn này';
            recalledText.style.cssText = 'font-style:italic; color:#94a3b8; font-size:0.85rem;';
            bubbleEl.appendChild(recalledText);

            // Emit Socket event to notify other user(s)
            const isGroup = document.getElementById('is-group-chat')?.value === '1';
            const convId = document.getElementById('current-conv-id')?.value;
            const receiverId = isGroup ? null : document.getElementById('active-chat-user-id')?.value;

            if (typeof socket !== 'undefined' && socket.connected) {
                socket.emit('recall-message', {
                    conversationId: convId,
                    messageId: messageId,
                    isGroup: isGroup,
                    receiverId: receiverId
                });
            }

            Swal.fire({ icon: 'success', title: 'Đã thu hồi', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        } else {
            Swal.fire('Lỗi', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
    }
}


window.updateGlobalMessageBadge = function (increment = 0) {
    const badge = document.getElementById('global-msg-badge');
    if (!badge) return;

    let total = 0;
    if (typeof allContacts !== 'undefined') {
        total += allContacts.reduce((sum, c) => sum + (parseInt(c.unread_count) || 0), 0);
    }
    if (typeof allGroups !== 'undefined') {
        total += allGroups.reduce((sum, g) => sum + (parseInt(g.unread_count) || 0), 0);
    }

    window.unreadMsgCount = total;
    if (total > 0) {
        badge.innerText = total > 99 ? '99+' : total;
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
};

// Hiển thị UI nhóm bị khoá
function showGroupLockedUI(reason) {
    const chatInputArea = document.getElementById('chat-input-area');
    const input = document.getElementById('message-input');
    const sendBtn = document.getElementById('btn-send-message');

    if (input) {
        input.disabled = true;
        input.placeholder = `Nhóm đã bị khoá: ${reason || 'Vi phạm điều khoản'}`;
        input.style.background = '#fee2e2';
        input.style.color = '#991b1b';
    }
    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.style.opacity = '0.5';
    }
}

// Mở khoá UI nhóm
function hideGroupLockedUI() {
    const input = document.getElementById('message-input');
    const sendBtn = document.getElementById('btn-send-message');

    if (input) {
        input.disabled = false;
        input.placeholder = 'Nhập tin nhắn...';
        input.style.background = 'var(--input-bg, #f3f4f6)';
        input.style.color = 'var(--text-color, #1f2937)';
    }
    if (sendBtn) {
        sendBtn.disabled = false;
        sendBtn.style.opacity = '1';
    }
}

// Phím Enter gửi tin & Đăng ký sự kiện soạn tin
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
    window.updateGlobalMessageBadge();
    handleTypingEvent();
});

// ===== REALTIME TYPING & PINNING HELPERS =====

let typingTimeout = null;
let isCurrentlyTyping = false;

function handleTypingEvent() {
    const messageInput = document.getElementById('message-input');
    if (!messageInput) return;

    messageInput.addEventListener('input', () => {
        const convId = document.getElementById('current-conv-id').value;
        const currentUser = document.getElementById('current-user-id').value;
        const currentUserName = window.currentUserNickname || document.getElementById('current-user-name')?.value || 'Thành viên';
        const isGroup = document.getElementById('is-group-chat')?.value === '1';
        const activeChatUser = document.getElementById('active-chat-user-id').value;

        if (!convId || !currentUser) return;

        if (!isCurrentlyTyping) {
            isCurrentlyTyping = true;
            socket.emit('typing', {
                conversationId: convId,
                senderId: currentUser,
                senderName: currentUserName,
                isTyping: true,
                isGroup: isGroup,
                receiverId: isGroup ? null : activeChatUser
            });
        }

        clearTimeout(typingTimeout);

        typingTimeout = setTimeout(() => {
            isCurrentlyTyping = false;
            socket.emit('typing', {
                conversationId: convId,
                senderId: currentUser,
                senderName: currentUserName,
                isTyping: false,
                isGroup: isGroup,
                receiverId: isGroup ? null : activeChatUser
            });
        }, 3000);
    });
}

// Lắng nghe typing
socket.on('typing', (data) => {
    const currentConv = document.getElementById('current-conv-id');
    const currentConvId = currentConv ? currentConv.value : null;

    if (currentConvId && String(data.conversationId) === String(currentConvId)) {
        updateTypingIndicatorUI(data.senderId, data.senderName, data.isTyping);
    }
});

function updateTypingIndicatorUI(senderId, senderName, isTyping) {
    const chatBox = document.getElementById('chat-history-box');
    if (!chatBox) return;

    let indicator = document.getElementById(`typing-indicator-${senderId}`);

    if (isTyping) {
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = `typing-indicator-${senderId}`;
            indicator.style.display = 'flex';
            indicator.style.alignItems = 'center';
            indicator.style.gap = '8px';
            indicator.style.margin = '10px 0';
            indicator.style.paddingLeft = '12px';
            indicator.style.fontSize = '0.82rem';
            indicator.style.color = '#64748b';
            indicator.style.fontWeight = '600';

            indicator.innerHTML = `
                <span>${senderName} đang soạn tin</span>
                <div class="typing-dots" style="display:flex; gap:3px;">
                    <span class="dot" style="width:5px; height:5px; background:#10b981; border-radius:50%; animation: blink 1.4s infinite both;"></span>
                    <span class="dot" style="width:5px; height:5px; background:#10b981; border-radius:50%; animation: blink 1.4s infinite both; animation-delay: .2s;"></span>
                    <span class="dot" style="width:5px; height:5px; background:#10b981; border-radius:50%; animation: blink 1.4s infinite both; animation-delay: .4s;"></span>
                </div>
            `;

            if (!document.getElementById('typing-animation-style')) {
                const style = document.createElement('style');
                style.id = 'typing-animation-style';
                style.textContent = `
                    @keyframes blink {
                        0% { opacity: .2; }
                        20% { opacity: 1; }
                        100% { opacity: .2; }
                    }
                `;
                document.head.appendChild(style);
            }

            chatBox.appendChild(indicator);
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    } else {
        if (indicator) {
            indicator.remove();
        }
    }
}

// Ghim tin nhắn
async function pinChatMessage(messageId) {
    const confirmRes = await Swal.fire({
        title: 'Ghim tin nhắn?',
        text: 'Bạn có muốn ghim tin nhắn này lên đầu cuộc hội thoại?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ghim',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#10b981'
    });

    if (!confirmRes.isConfirmed) return;

    try {
        const res = await fetch('api/pin-message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'pin', message_id: messageId })
        });
        const data = await res.json();
        if (data.success) {
            const convId = document.getElementById('current-conv-id').value;
            const isGroup = document.getElementById('is-group-chat')?.value === '1';

            // Broadcast sự kiện ghim đến các thành viên khác
            if (typeof socket !== 'undefined' && socket.connected) {
                socket.emit('pin-message', { conversationId: convId, messageId, isGroup });
            }

            reloadCurrentChat();
            Swal.fire({ icon: 'success', title: 'Đã ghim', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        } else {
            Swal.fire('Lỗi', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
    }
}

// Bỏ ghim tin nhắn
async function unpinCurrentMessage() {
    const bar = document.getElementById('pinned-message-bar');
    const messageId = bar?.getAttribute('data-pinned-id');
    if (!messageId) return;

    const confirmRes = await Swal.fire({
        title: 'Bỏ ghim tin nhắn?',
        text: 'Bạn có chắc chắn muốn gỡ bỏ ghim của tin nhắn này không?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Bỏ ghim',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#ef4444'
    });

    if (!confirmRes.isConfirmed) return;

    try {
        const res = await fetch('api/pin-message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'unpin', message_id: messageId })
        });
        const data = await res.json();
        if (data.success) {
            const convId = document.getElementById('current-conv-id').value;
            const isGroup = document.getElementById('is-group-chat')?.value === '1';

            // Broadcast sự kiện bỏ ghim
            if (typeof socket !== 'undefined' && socket.connected) {
                socket.emit('pin-message', { conversationId: convId, messageId: null, isGroup });
            }

            // Ẩn bar ghim ngay lập tức (không cần reload)
            bar.style.display = 'none';
            bar.removeAttribute('data-pinned-id');
            Swal.fire({ icon: 'success', title: 'Đã gỡ ghim', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        } else {
            Swal.fire('Lỗi', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
    }
}

// Cuộn tới tin nhắn ghim
function scrollToPinnedMessage() {
    const bar = document.getElementById('pinned-message-bar');
    const pinnedId = bar?.getAttribute('data-pinned-id');
    if (!pinnedId) return;

    const msgEl = document.getElementById(`msg-item-${pinnedId}`);
    if (msgEl) {
        msgEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        msgEl.style.transition = 'background 0.5s';
        const originalBg = msgEl.style.background;
        msgEl.style.background = '#fef08a';
        setTimeout(() => {
            msgEl.style.background = originalBg;
        }, 1500);
    } else {
        Swal.fire('Thông báo', 'Tin nhắn ghim này ở quá xa trong lịch sử chat cũ.', 'info');
    }
}

// Tải lại chat hiện tại
function reloadCurrentChat() {
    const convId = document.getElementById('current-conv-id').value;
    const isGroup = document.getElementById('is-group-chat')?.value === '1';

    if (isGroup) {
        const groupName = document.getElementById('partner-name').innerText;
        const avatarUrl = document.getElementById('partner-avatar').src;
        selectGroup(parseInt(convId, 10), groupName, avatarUrl, 0, '');
    } else {
        const activeChatUser = document.getElementById('active-chat-user-id').value;
        const partnerName = document.getElementById('partner-name').innerText;
        const avatarUrl = document.getElementById('partner-avatar').src;
        selectContact(parseInt(activeChatUser, 10), partnerName, avatarUrl);
    }
}

// Hiển thị thanh ghim tin nhắn
function renderPinnedMessage(pinnedMessages) {
    const bar = document.getElementById('pinned-message-bar');
    const content = document.getElementById('pinned-msg-content');
    const unpinBtn = document.getElementById('btn-unpin-current');

    if (!pinnedMessages || pinnedMessages.length === 0) {
        bar.style.display = 'none';
        bar.removeAttribute('data-pinned-id');
        return;
    }

    const latestPin = pinnedMessages[0];
    bar.style.display = 'flex';
    bar.setAttribute('data-pinned-id', latestPin.id);

    const isGroup = document.getElementById('is-group-chat')?.value === '1';
    const canUnpin = true; // Ai cũng có quyền bỏ ghim
    unpinBtn.style.display = canUnpin ? 'flex' : 'none';

    const senderPrefix = latestPin.sender_name ? `${latestPin.sender_name}: ` : '';
    content.innerText = `${senderPrefix}${latestPin.content}`;
}

// ── Xoá tin nhắn cục bộ (chỉ ẩn phía mình, không xoá DB) ──
function deleteMessageLocally(messageId) {
    const el = document.getElementById('msg-item-' + messageId);
    if (el) {
        el.style.transition = 'opacity 0.3s, transform 0.3s';
        el.style.opacity = '0';
        el.style.transform = 'scale(0.95)';
        setTimeout(() => el.remove(), 300);
    }

    // Lưu vào localStorage để ẩn khi reload (dùng key giống renderChatHistory)
    const userId = document.getElementById('current-user-id')?.value;
    if (userId) {
        const key = `deleted_msg_ids_${userId}`;
        const existing = JSON.parse(localStorage.getItem(key) || '[]');
        if (!existing.includes(String(messageId))) {
            existing.push(String(messageId));
            localStorage.setItem(key, JSON.stringify(existing));
        }
    }
}

// ── Đóng context menu khi click ngoài ──
document.addEventListener('click', function (e) {
    const menu = document.getElementById('msg-context-menu');
    if (menu && menu.style.display === 'flex') {
        if (!menu.contains(e.target) && !e.target.closest('.msg-more-trigger')) {
            menu.style.display = 'none';
        }
    }
});
