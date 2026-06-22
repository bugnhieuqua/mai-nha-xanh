const express = require('express');
const app = express();
const server = require('http').createServer(app);
const io = require('socket.io')(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});
const axios = require('axios');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const onlineUsers = new Map(); // userId (string) => socketId (string)
const activeCalls = new Map(); // callId (string) => { callerId, receiverId, callerSocketId, receiverSocketId, timeoutId, startTime }

// ─── Xác định PHP_API_BASE ────────────────────────────────────
// Ưu tiên: process.env.PHP_API_BASE hoặc process.env.APP_URL (trên Render)
// Nếu không có, đọc từ file .env (cho môi trường local)
// Nếu vẫn không có, dùng giá trị mặc định nhưng sẽ báo lỗi nếu không set.
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

// Nếu vẫn chưa có, cảnh báo nhưng vẫn cho chạy (các chức năng DB sẽ không hoạt động)
if (!PHP_API_BASE) {
    console.warn('[Warning] PHP_API_BASE chưa được cấu hình. Các chức năng lưu tin nhắn, cập nhật status sẽ không hoạt động.');
    PHP_API_BASE = ''; // Để tránh lỗi gọi API sai
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
            timeout: 5000
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

async function saveMessage(conversationId, senderId, content) {
    await callPhpApi('/api/save-message.php', {
        conversation_id: parseInt(conversationId, 10),
        sender_id: parseInt(senderId, 10),
        content: content,
        type: 'text'
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

        // Gửi danh sách online cho chính client
        const onlineList = Array.from(onlineUsers.keys());
        socket.emit('online-users-list', onlineList);
        // Báo cho người khác
        socket.broadcast.emit('status-change', { userId: uid, status: 'online' });

        await updateUserStatus(uid, true);
        console.log(`[Online] User ${uid} connected. Online: ${onlineList.length} users`);
    });

    socket.on('request-online-list', () => {
        const ids = Array.from(onlineUsers.keys());
        socket.emit('online-users-list', ids);
    });

    // ── Messaging ──
    socket.on('send-message', async (payload) => {
        const { conversationId, senderId, receiverId, messageContent } = payload;
        if (!conversationId || !senderId || !receiverId || !messageContent) return;

        // Gửi trực tiếp nếu receiver online
        const receiverSocketId = onlineUsers.get(String(receiverId));
        if (receiverSocketId) {
            io.to(receiverSocketId).emit('receive-message', {
                conversationId,
                senderId,
                content: messageContent,
                createdAt: new Date()
            });
        }

        // Lưu DB (background)
        await saveMessage(conversationId, senderId, messageContent);
        console.log(`[Message] ${senderId} → ${receiverId}: ${messageContent.substring(0, 30)}...`);
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

        // Tạo callId duy nhất
        const callId = `${callerId}-${receiverId}-${Date.now()}`;

        // Lưu trạng thái cuộc gọi
        activeCalls.set(callId, {
            callerId,
            receiverId,
            callerSocketId,
            receiverSocketId,
            startTime: Date.now(),
            callType
        });

        // Gửi ring cho receiver
        io.to(receiverSocketId).emit('incoming-call-ring', {
            callId,
            callerId,
            callerName,
            zegoRoomId,
            callType
        });

        console.log(`[Call] Initiated ${callType} call ${callId}: ${callerId} → ${receiverId}`);

        // Timeout 30s
        const timeoutId = setTimeout(() => {
            if (activeCalls.has(callId)) {
                console.log(`[Call] Timeout: ${callId}`);
                // Báo cho cả hai bên
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

        // Gắn timeout vào call info
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
        // Xóa timeout
        if (callInfo.timeoutId) {
            clearTimeout(callInfo.timeoutId);
        }

        // Thông báo cho caller
        if (callInfo.callerSocketId) {
            io.to(callInfo.callerSocketId).emit('call-accepted', { callId });
            console.log(`[Call] Accepted: ${callId}`);
        } else {
            // Caller đã offline, báo lỗi cho receiver
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
            // Thông báo cho caller
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
            // Thông báo cho receiver
            if (callInfo.receiverSocketId) {
                io.to(callInfo.receiverSocketId).emit('call-cancelled', { callId });
            }
            activeCalls.delete(callId);
            console.log(`[Call] Cancelled by caller: ${callId}`);
        }
    });

    // ── Hỗ trợ sự kiện end-call (khi một bên kết thúc cuộc gọi sau khi đã nối) ──
    socket.on('end-call', (payload) => {
        const { callId } = payload;
        if (!callId) return;

        if (activeCalls.has(callId)) {
            const callInfo = activeCalls.get(callId);
            if (callInfo.timeoutId) clearTimeout(callInfo.timeoutId);
            // Thông báo cho bên kia
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
        // Tìm userId từ socket id
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

        // Xử lý các cuộc gọi đang hoạt động có liên quan đến socket này
        for (let [callId, callInfo] of activeCalls.entries()) {
            if (callInfo.callerSocketId === socket.id || callInfo.receiverSocketId === socket.id) {
                // Thông báo cho bên còn lại
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