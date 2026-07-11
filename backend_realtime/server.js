const express = require('express');
const app = express();
const server = require('http').createServer(app);
const io = require('socket.io')(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    },
    // Heartbeat optimizations for lower latency and less server load
    pingInterval: 15000, 
    pingTimeout: 10000,
    cookie: false
});
const axios = require('axios');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const onlineUsers = new Map(); // userId (string) => socketId (string)
const activeCalls = new Map(); // callId (string) => { callerId, receiverId, callerSocketId, receiverSocketId, timeoutId, startTime }

// ─── Xác định PHP_API_BASE ────────────────────────────────────
let PHP_API_BASE = process.env.PHP_API_BASE || process.env.APP_URL || '';

if (!PHP_API_BASE) {
    try {
        const envPath = path.join(__dirname, '..', '.env');
        if (fs.existsSync(envPath)) {
            const envContent = fs.readFileSync(envPath, 'utf8');
            const lines = envContent.split('\n');
            for (const line of lines) {
                const trimmed = line.trim();
                if (trimmed && !trimmed.startsWith('#') && trimmed.includes('=')) {
                    const parts = trimmed.split('=');
                    const key = parts[0].trim();
                    const value = parts.slice(1).join('=').trim().replace(/^['"]|['"]$/g, '');
                    if (key === 'PHP_API_BASE' || key === 'APP_URL') {
                        PHP_API_BASE = value;
                    }
                }
            }
        }
    } catch (e) {
        console.error('[Env] Không thể đọc .env:', e.message);
    }
}

if (!PHP_API_BASE) {
    console.warn('[Warning] PHP_API_BASE chưa được cấu hình. Các chức năng lưu tin nhắn, cập nhật status sẽ không hoạt động.');
    PHP_API_BASE = '';
} else {
    if (PHP_API_BASE.endsWith('/')) PHP_API_BASE = PHP_API_BASE.slice(0, -1);
    console.log(`[API Base] ${PHP_API_BASE}`);
}

app.use(express.json());

app.get('/', (req, res) => {
    res.send({ status: 'running', online_users: onlineUsers.size, active_calls: activeCalls.size });
});

// ─── Hàm gọi PHP API (có xử lý lỗi) ──────────────────────────
async function callPhpApi(endpoint, data) {
    if (!PHP_API_BASE) {
        console.error('[API Error] PHP_API_BASE chưa cấu hình, bỏ qua gọi API');
        return null;
    }
    try {
        const url = `${PHP_API_BASE}${endpoint}`;
        const response = await axios.post(url, data, {
            headers: { 'Content-Type': 'application/json' },
            timeout: 8000
        });
        return response.data;
    } catch (error) {
        console.error(`[API Error] ${endpoint}:`, error.message);
        return null;
    }
}

async function updateUserStatus(userId, isOnline) {
    await callPhpApi('/api/update-status.php', {
        user_id: parseInt(userId, 10),
        status: isOnline ? 1 : 0
    });
}

async function saveMessage(conversationId, senderId, content, type = 'text') {
    return await callPhpApi('/api/save-message.php', {
        conversation_id: parseInt(conversationId, 10),
        sender_id: parseInt(senderId, 10),
        content: content,
        type: type
    });
}

async function moderateGroupMessage(conversationId, content, messageId) {
    return await callPhpApi('/api/ai_moderation.php', {
        conversation_id: parseInt(conversationId, 10),
        content: content,
        message_id: messageId ? parseInt(messageId, 10) : null
    });
}

// ─── WebSocket Events ──────────────────────────────────────────
io.on('connection', (socket) => {
    console.log(`[Socket] New connection: ${socket.id}`);

    // ── User Online ──
    socket.on('user-online', async (userId) => {
        if (!userId) return;
        const uid = String(userId);
        onlineUsers.set(uid, socket.id);

        const onlineList = Array.from(onlineUsers.keys());
        socket.emit('online-users-list', onlineList);
        socket.broadcast.emit('status-change', { userId: uid, status: 'online' });

        await updateUserStatus(uid, true);
        console.log(`[Online] User ${uid} connected. Online: ${onlineList.length} users`);
    });

    socket.on('request-online-list', () => {
        const ids = Array.from(onlineUsers.keys());
        socket.emit('online-users-list', ids);
    });

    // ── Group Rooms Joining ──
    socket.on('join-group-rooms', (groupIds) => {
        if (!Array.isArray(groupIds)) return;
        groupIds.forEach(gid => {
            const roomName = `group_${gid}`;
            socket.join(roomName);
            console.log(`[Socket ${socket.id}] joined room: ${roomName}`);
        });
    });

    socket.on('join-group-room', (groupId) => {
        if (!groupId) return;
        const roomName = `group_${groupId}`;
        socket.join(roomName);
        console.log(`[Socket ${socket.id}] joined single room: ${roomName}`);
    });

    // ── Messaging ──
    socket.on('send-message', async (payload) => {
        const { conversationId, senderId, receiverId, messageContent, isGroup, senderName, messageType } = payload;
        if (!conversationId || !senderId || !messageContent) return;

        const mType = messageType || 'text';

        // Lưu vào cơ sở dữ liệu trước (chạy background)
        const saveRes = await saveMessage(conversationId, senderId, messageContent, mType);
        const messageId = saveRes && saveRes.status === 'success' ? saveRes.message_id : null;

        if (isGroup) {
            // Broadcast tin nhắn đến room nhóm
            socket.to(`group_${conversationId}`).emit('receive-message', {
                conversationId,
                senderId,
                content: messageContent,
                type: mType,
                createdAt: new Date(),
                isGroup: true,
                senderName: senderName || 'Thành viên',
                id: messageId
            });
            console.log(`[Group Message] Room group_${conversationId}: ${senderId} → ${messageContent.substring(0, 30)}... [Type: ${mType}]`);

            // Chạy AI kiểm duyệt tin nhắn nhóm
            if (mType === 'text') {
                moderateGroupMessage(conversationId, messageContent, messageId).then(modRes => {
                    if (modRes && modRes.success && modRes.action_taken === 'lock') {
                        console.log(`[AI Blocked Group] Group ${conversationId} locked. Reason: ${modRes.reason}`);
                        io.to(`group_${conversationId}`).emit('group-locked', {
                            conversationId,
                            reason: modRes.reason
                        });
                    }
                }).catch(err => {
                    console.error('[AI Moderation Error]', err.message);
                });
            }

        } else {
            // Direct message 1-1
            if (!receiverId) return;
            const receiverSocketId = onlineUsers.get(String(receiverId));
            if (receiverSocketId) {
                io.to(receiverSocketId).emit('receive-message', {
                    conversationId,
                    senderId,
                    content: messageContent,
                    type: mType,
                    createdAt: new Date(),
                    isGroup: false,
                    id: messageId
                });
            }
        }
        
        // Emit confirmation back to the sender
        socket.emit('message-sent-ack', {
            conversationId,
            messageId: messageId,
            tempId: payload.tempId
        });
    });

    // ── Recall Message ──
    socket.on('recall-message', (payload) => {
        const { conversationId, messageId, isGroup, receiverId } = payload;
        if (!conversationId || !messageId) return;

        if (isGroup) {
            socket.to(`group_${conversationId}`).emit('message-recalled', { conversationId, messageId });
        } else {
            if (receiverId) {
                const receiverSocketId = onlineUsers.get(String(receiverId));
                if (receiverSocketId) {
                    io.to(receiverSocketId).emit('message-recalled', { conversationId, messageId });
                }
            }
        }
    });

    // ── Pin / Unpin Message ──
    socket.on('pin-message', (payload) => {
        const { conversationId, messageId, isGroup } = payload;
        if (!conversationId) return;

        if (isGroup) {
            // Thông báo đến tất cả thành viên nhóm (trừ người gửi)
            socket.to(`group_${conversationId}`).emit('message-pinned', { conversationId, messageId });
        }
        // Chat 1-1: client tự reload sau khi API thành công, không cần broadcast thêm
    });

    // ── Typing status ──
    socket.on('typing', (data) => {
        const { conversationId, senderId, senderName, isTyping, isGroup, receiverId } = data;
        if (!conversationId || !senderId) return;

        if (isGroup) {
            socket.to(`group_${conversationId}`).emit('typing', {
                conversationId,
                senderId,
                senderName,
                isTyping
            });
        } else {
            if (receiverId) {
                const receiverSocketId = onlineUsers.get(String(receiverId));
                if (receiverSocketId) {
                    io.to(receiverSocketId).emit('typing', {
                        conversationId,
                        senderId,
                        senderName,
                        isTyping
                    });
                }
            }
        }
    });

    // ── Call Signaling ──
    socket.on('initiate-call', (payload) => {
        const { callerId, callerName, receiverId, zegoRoomId, callType = 'video' } = payload;
        if (!callerId || !receiverId || !zegoRoomId) {
            socket.emit('call-error', { message: 'Thiếu thông tin cuộc gọi' });
            return;
        }

        const callerSocketId = socket.id;
        const receiverSocketId = onlineUsers.get(String(receiverId));

        if (!receiverSocketId) {
            socket.emit('call-rejected', { reason: 'offline' });
            console.log(`[Call] ${callerId} → ${receiverId} - OFFLINE`);
            return;
        }

        const callId = `${callerId}-${receiverId}-${Date.now()}`;

        activeCalls.set(callId, {
            callerId,
            receiverId,
            callerSocketId,
            receiverSocketId,
            startTime: Date.now(),
            callType
        });

        io.to(receiverSocketId).emit('incoming-call-ring', {
            callId,
            callerId,
            callerName,
            zegoRoomId,
            callType
        });

        console.log(`[Call] Initiated ${callType} call ${callId}: ${callerId} → ${receiverId}`);

        const timeoutId = setTimeout(() => {
            if (activeCalls.has(callId)) {
                console.log(`[Call] Timeout: ${callId}`);
                const callInfo = activeCalls.get(callId);
                if (callInfo.callerSocketId) {
                    io.to(callInfo.callerSocketId).emit('call-timeout', { callId });
                }
                if (callInfo.receiverSocketId) {
                    io.to(callInfo.receiverSocketId).emit('call-timeout', { callId });
                }
                activeCalls.delete(callId);
            }
        }, 30000);

        const callInfo = activeCalls.get(callId);
        callInfo.timeoutId = timeoutId;
        activeCalls.set(callId, callInfo);
    });

    socket.on('accept-call', (payload) => {
        const { callId, callerId } = payload;
        if (!callId || !callerId) return;

        if (!activeCalls.has(callId)) {
            socket.emit('call-error', { message: 'Cuộc gọi không tồn tại hoặc đã kết thúc' });
            return;
        }

        const callInfo = activeCalls.get(callId);
        if (callInfo.timeoutId) {
            clearTimeout(callInfo.timeoutId);
        }

        if (callInfo.callerSocketId) {
            io.to(callInfo.callerSocketId).emit('call-accepted', { callId });
            console.log(`[Call] Accepted: ${callId}`);
        } else {
            socket.emit('call-error', { message: 'Người gọi đã rời khỏi cuộc gọi' });
            activeCalls.delete(callId);
        }
    });

    socket.on('reject-call', (payload) => {
        const { callId, callerId } = payload;
        if (!callId || !callerId) return;

        if (activeCalls.has(callId)) {
            const callInfo = activeCalls.get(callId);
            if (callInfo.timeoutId) clearTimeout(callInfo.timeoutId);
            if (callInfo.callerSocketId) {
                io.to(callInfo.callerSocketId).emit('call-rejected', { callId });
            }
            activeCalls.delete(callId);
            console.log(`[Call] Rejected: ${callId}`);
        }
    });

    socket.on('cancel-call', (payload) => {
        const { callId, receiverId } = payload;
        if (!callId || !receiverId) return;

        if (activeCalls.has(callId)) {
            const callInfo = activeCalls.get(callId);
            if (callInfo.timeoutId) clearTimeout(callInfo.timeoutId);
            if (callInfo.receiverSocketId) {
                io.to(callInfo.receiverSocketId).emit('call-cancelled', { callId });
            }
            activeCalls.delete(callId);
            console.log(`[Call] Cancelled by caller: ${callId}`);
        }
    });

    socket.on('end-call', (payload) => {
        const { callId } = payload;
        if (!callId) return;

        if (activeCalls.has(callId)) {
            const callInfo = activeCalls.get(callId);
            if (callInfo.timeoutId) clearTimeout(callInfo.timeoutId);
            const otherSocketId = (socket.id === callInfo.callerSocketId) 
                ? callInfo.receiverSocketId 
                : callInfo.callerSocketId;
            if (otherSocketId) {
                io.to(otherSocketId).emit('call-ended', { callId });
            }
            activeCalls.delete(callId);
            console.log(`[Call] Ended by ${socket.id}: ${callId}`);
        }
    });

    // ── Disconnect ──
    socket.on('disconnect', async () => {
        let disconnectedUserId = null;
        for (let [userId, socketId] of onlineUsers.entries()) {
            if (socketId === socket.id) {
                disconnectedUserId = userId;
                onlineUsers.delete(userId);
                break;
            }
        }

        if (disconnectedUserId) {
            console.log(`[Offline] User ${disconnectedUserId} disconnected`);
            socket.broadcast.emit('status-change', { userId: disconnectedUserId, status: 'offline' });
            await updateUserStatus(disconnectedUserId, false);
        }

        for (let [callId, callInfo] of activeCalls.entries()) {
            if (callInfo.callerSocketId === socket.id || callInfo.receiverSocketId === socket.id) {
                const otherSocketId = (socket.id === callInfo.callerSocketId) 
                    ? callInfo.receiverSocketId 
                    : callInfo.callerSocketId;
                if (otherSocketId) {
                    io.to(otherSocketId).emit('call-ended', { callId, reason: 'peer_disconnected' });
                }
                if (callInfo.timeoutId) clearTimeout(callInfo.timeoutId);
                activeCalls.delete(callId);
                console.log(`[Call] Terminated due to disconnect: ${callId}`);
            }
        }
    });
});

server.listen(PORT, () => {
    console.log(`[Server] Running on port ${PORT}`);
});