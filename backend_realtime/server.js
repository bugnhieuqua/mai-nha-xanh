const express = require('express');
const app = express();
const server = require('http').createServer(app);
const io = require('socket.io')(server, {
    cors: {
        origin: "*", // Cho phép tất cả các nguồn kết nối
        methods: ["GET", "POST"]
    }
});
const axios = require('axios');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const onlineUsers = new Map(); // Map: userId (string) => socketId (string)

// Đọc file .env từ thư mục gốc để cấu hình PHP_API_BASE
let PHP_API_BASE = 'http://localhost/mai-nha-xanh';
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
    console.error('Không thể nạp tệp .env:', e.message);
}

// Bỏ dấu gạch chéo cuối nếu có
if (PHP_API_BASE.endsWith('/')) {
    PHP_API_BASE = PHP_API_BASE.slice(0, -1);
}

console.log(`[Realtime Server] Địa chỉ API PHP: ${PHP_API_BASE}`);

// Middleware parse JSON
app.use(express.json());

app.get('/', (req, res) => {
    res.send({ status: 'running', online_users: onlineUsers.size });
});

// Hàm hỗ trợ gọi PHP API cập nhật trạng thái hoạt động
async function updateUserStatusInDatabase(userId, isOnline) {
    try {
        const url = `${PHP_API_BASE}/api/update-status.php`;
        await axios.post(url, {
            user_id: parseInt(userId, 10),
            status: isOnline ? 1 : 0
        }, {
            headers: { 'Content-Type': 'application/json' }
        });
        console.log(`[Status DB Sync] Đã cập nhật trạng thái User ${userId} thành ${isOnline ? 'Online' : 'Offline'}`);
    } catch (error) {
        console.error(`[Status DB Error] Không thể cập nhật trạng thái cho User ${userId}:`, error.message);
    }
}

io.on('connection', (socket) => {
    console.log(`[WebSocket] Kết nối mới: ${socket.id}`);

    // Đăng ký trạng thái Online khi kết nối thành công
    socket.on('user-online', async (userId) => {
        if (!userId) return;
        
        onlineUsers.set(String(userId), socket.id);
        
        // 1. Phát tín hiệu cho các Client khác biết User này đã hoạt động
        socket.broadcast.emit('status-change', { userId: userId, status: 'online' });
        
        // 2. Gửi lại cho CHÍNH client vừa kết nối danh sách toàn bộ userId đang online
        //    Để client tự cập nhật giao diện ngay lập tức (giải quyết bug 'luôn hiển thị Ngoại tuyến')
        const currentOnlineIds = Array.from(onlineUsers.keys());
        socket.emit('online-users-list', currentOnlineIds);
        
        // 3. Cập nhật trạng thái xuống database MySQL
        await updateUserStatusInDatabase(userId, true);
        
        console.log(`[User Active] User ${userId} hiện đang Online. Danh sách online: [${currentOnlineIds.join(', ')}]`);
    });

    // Client chủ động yêu cầu danh sách online (ví dụ sau khi DOM đã render xong)
    socket.on('request-online-list', () => {
        const ids = Array.from(onlineUsers.keys());
        socket.emit('online-users-list', ids);
        console.log(`[Online List] Phản hồi danh sách online cho Socket ${socket.id}: [${ids.join(', ')}]`);
    });

    // Xử lý gửi tin nhắn thời gian thực
    socket.on('send-message', async (payload) => {
        const { conversationId, senderId, receiverId, messageContent } = payload;
        if (!conversationId || !senderId || !receiverId || !messageContent) return;

        console.log(`[Message] Từ ${senderId} đến ${receiverId} trong Room ${conversationId}`);

        // Gửi tin nhắn trực tiếp đến người nhận nếu họ online
        const receiverSocketId = onlineUsers.get(String(receiverId));
        if (receiverSocketId) {
            io.to(receiverSocketId).emit('receive-message', {
                conversationId,
                senderId,
                content: messageContent,
                createdAt: new Date()
            });
            console.log(`[Message Route] Đã gửi trực tiếp qua Socket tới socket ID: ${receiverSocketId}`);
        }

        // Gọi API PHP ở background để lưu tin nhắn vào MySQL
        try {
            const url = `${PHP_API_BASE}/api/save-message.php`;
            await axios.post(url, {
                conversation_id: parseInt(conversationId, 10),
                sender_id: parseInt(senderId, 10),
                content: messageContent,
                type: 'text'
            }, {
                headers: { 'Content-Type': 'application/json' }
            });
            console.log(`[Message DB Sync] Đã lưu tin nhắn vào database.`);
        } catch (error) {
            console.error('[Message DB Error] Lỗi lưu tin nhắn qua PHP API:', error.message);
        }
    });

    // Signaling cuộc gọi video/audio (ZegoCloud WebRTC)
    socket.on('initiate-call', (payload) => {
        const { callerId, callerName, receiverId, zegoRoomId } = payload;
        if (!callerId || !receiverId || !zegoRoomId) return;

        console.log(`[Call Signal] Khởi tạo cuộc gọi từ ${callerName} (${callerId}) đến User ${receiverId}`);

        const receiverSocketId = onlineUsers.get(String(receiverId));
        if (receiverSocketId) {
            io.to(receiverSocketId).emit('incoming-call-ring', {
                callerId,
                callerName,
                zegoRoomId
            });
            console.log(`[Call Signal Route] Đã chuyển tiếp cuộc gọi tới socket ID: ${receiverSocketId}`);
        } else {
            // Trả về tín hiệu đối phương offline nếu không tìm thấy socket
            socket.emit('call-rejected', { reason: 'offline' });
        }
    });

    // Sự kiện từ chối cuộc gọi
    socket.on('reject-call', (payload) => {
        const { callerId } = payload;
        if (!callerId) return;

        console.log(`[Call Signal] Cuộc gọi bị từ chối bởi đầu nhận. Báo cho Caller ${callerId}`);
        const callerSocketId = onlineUsers.get(String(callerId));
        if (callerSocketId) {
            io.to(callerSocketId).emit('call-rejected');
        }
    });

    // Sự kiện chấp nhận cuộc gọi
    socket.on('accept-call', (payload) => {
        const { callerId } = payload;
        if (!callerId) return;

        console.log(`[Call Signal] Cuộc gọi được chấp nhận bởi đầu nhận. Báo cho Caller ${callerId}`);
        const callerSocketId = onlineUsers.get(String(callerId));
        if (callerSocketId) {
            io.to(callerSocketId).emit('call-accepted');
        }
    });

    // Khi ngắt kết nối
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
            console.log(`[User Offline] User ${disconnectedUserId} đã ngắt kết nối.`);
            
            // 1. Phát tín hiệu cho toàn bộ client biết người dùng này đã offline
            socket.broadcast.emit('status-change', { userId: disconnectedUserId, status: 'offline' });
            
            // 2. Cập nhật trạng thái xuống database MySQL
            await updateUserStatusInDatabase(disconnectedUserId, false);
        }
    });
});

server.listen(PORT, () => {
    console.log(`[Realtime Server] Đang chạy trên cổng ${PORT}`);
});
