/**
 * call-webrtc.js - Real-time video/audio call signaling & ZegoCloud integration
 */

// Đảm bảo các modal và popup cuộc gọi tồn tại trong DOM
function ensureCallUIExists() {
    // 1. Popup cuộc gọi đến (Incoming Call)
    if (!document.getElementById('incoming-call-popup')) {
        const popup = document.createElement('div');
        popup.id = 'incoming-call-popup';
        popup.style.cssText = `
            position: fixed; top: -150px; left: 50%; transform: translateX(-50%);
            width: 90%; max-width: 380px; background: rgba(31, 41, 55, 0.95);
            backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; z-index: 999999; transition: top 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            color: #fff;
        `;
        popup.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                <div id="caller-avatar-display" style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.3rem; flex-shrink: 0; color: #fff; animation: pulseCall 1.5s infinite; text-transform: uppercase;">
                    ?
                </div>
                <div style="min-width: 0;">
                    <div style="font-weight: 700; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="caller-name-display">Đối tác</div>
                    <div style="font-size: 0.8rem; color: #a1a1aa; white-space: nowrap;">📞 Đang gọi cho bạn...</div>
                </div>
            </div>
            <div style="display: flex; gap: 10px; flex-shrink: 0;">
                <button id="btn-accept-call" style="width: 44px; height: 44px; border-radius: 50%; background: #10b981; border: none; color: #fff; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4); transition: transform 0.2s;" title="Chấp nhận">
                    <i class="fas fa-phone"></i>
                </button>
                <button id="btn-reject-call" style="width: 44px; height: 44px; border-radius: 50%; background: #ef4444; border: none; color: #fff; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4); transition: transform 0.2s;" title="Từ chối">
                    <i class="fas fa-phone-slash"></i>
                </button>
            </div>
        `;
        document.body.appendChild(popup);

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulseCall {
                0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
                70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
                100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
            }
            #incoming-call-popup.active {
                top: 20px;
            }
            #incoming-call-popup button:hover {
                transform: scale(1.1);
            }
        `;
        document.head.appendChild(style);
    }

    // 2. Màn hình chờ gọi đi (Outbound Calling Modal)
    if (!document.getElementById('calling-outbound-modal')) {
        const outbound = document.createElement('div');
        outbound.id = 'calling-outbound-modal';
        outbound.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(15px);
            display: none; flex-direction: column; align-items: center; justify-content: center;
            z-index: 999998; color: #fff; text-align: center;
        `;
        outbound.innerHTML = `
            <div style="width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; margin-bottom: 24px; position: relative;">
                <div style="position: absolute; border: 2px solid #10b981; border-radius: 50%; width: 100%; height: 100%; animation: pulseCall 2s infinite;"></div>
                <div style="font-size: 2.5rem;">📞</div>
            </div>
            <h3 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 8px;" id="outbound-caller-name">Đối tác</h3>
            <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 40px;">Đang kết nối cuộc gọi...</p>
            <button id="btn-cancel-outbound" style="padding: 12px 30px; border-radius: 30px; background: #ef4444; border: none; color: #fff; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);">
                <i class="fas fa-phone-slash"></i> Hủy cuộc gọi
            </button>
        `;
        document.body.appendChild(outbound);
    }

    // 3. Khung chứa video cuộc gọi (Zego Container Modal)
    if (!document.getElementById('video-call-modal')) {
        const videoModal = document.createElement('div');
        videoModal.id = 'video-call-modal';
        videoModal.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: #000; display: none; z-index: 1000000;
        `;
        videoModal.innerHTML = `
            <div id="video-call-container" style="width: 100%; height: 100%;"></div>
            <button id="btn-close-video-modal" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; color: #fff; font-size: 1.2rem; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 1000001; transition: background 0.2s;">
                ✕
            </button>
        `;
        document.body.appendChild(videoModal);

        document.getElementById('btn-close-video-modal').onclick = () => {
            document.getElementById('video-call-modal').style.display = 'none';
            // Reload page to disconnect and clean up Zego prebuilt
            window.location.reload();
        };
    }
}

// Khởi chạy cuộc gọi đi từ giao diện
function startVideoCall(receiverId, receiverName) {
    ensureCallUIExists();

    const currentUser = document.getElementById('current-user-id');
    const currentUserName = document.getElementById('current-user-name');

    if (!currentUser) {
        alert("Bạn cần đăng nhập để sử dụng chức năng này.");
        return;
    }

    // Tạo Room ID ngẫu nhiên cho cuộc gọi
    const zegoRoomId = "room_" + Math.random().toString(36).substring(2, 9);

    // 1. Phát tín hiệu gọi điện qua WebSocket Server
    socket.emit('initiate-call', {
        callerId: currentUser.value,
        callerName: currentUserName ? currentUserName.value : 'Người dùng',
        receiverId: receiverId,
        zegoRoomId: zegoRoomId
    });

    // 2. Hiển thị màn hình chờ kết nối cuộc gọi đi
    const outboundModal = document.getElementById('calling-outbound-modal');
    document.getElementById('outbound-caller-name').innerText = receiverName;
    outboundModal.style.display = 'flex';

    // 3. Xử lý nút Hủy cuộc gọi đi
    document.getElementById('btn-cancel-outbound').onclick = () => {
        outboundModal.style.display = 'none';
        socket.emit('reject-call', { callerId: currentUser.value });
    };

    // 4. Lưu lại ID cuộc gọi hiện tại trong global variable để kích hoạt Zego khi được chấp nhận
    window.currentZegoRoomId = zegoRoomId;
}

// Lắng nghe tín hiệu cuộc gọi đến
socket.on('incoming-call-ring', (data) => {
    ensureCallUIExists();

    const popup = document.getElementById('incoming-call-popup');
    // Hiển thị tên và chữ cái đầu tên người gọi
    const callerName = data.callerName || 'Đối tác';
    document.getElementById('caller-name-display').innerText = callerName;
    const avatarEl = document.getElementById('caller-avatar-display');
    if (avatarEl) avatarEl.innerText = callerName.charAt(0).toUpperCase() || '?';
    popup.style.top = "20px"; // Trượt popup xuống bằng cách sửa inline style trực tiếp

    // Phát âm thanh chuông ảo
    let ringInterval = setInterval(() => {
        try {
            // Có thể tạo một AudioContext phát tiếng bíp bíp nhẹ nếu không có tệp âm thanh
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 440;
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) { }
    }, 1500);

    // Xử lý sự kiện khi nút "ĐỒNG Ý CHẤP NHẬN" được bấm
    document.getElementById('btn-accept-call').onclick = function () {
        clearInterval(ringInterval);
        popup.style.top = "-150px"; // Ẩn popup bằng cách đưa lên trên
        // Báo cho Server là cuộc gọi được chấp nhận để Server báo lại cho Caller
        socket.emit('accept-call', { callerId: data.callerId });
        joinZegoCallRoom(data.zegoRoomId);
    };

    // Xử lý sự kiện khi nút "TỪ CHỐI / DẬP MÁY" được bấm
    document.getElementById('btn-reject-call').onclick = function () {
        clearInterval(ringInterval);
        popup.style.top = "-150px"; // Ẩn popup
        socket.emit('reject-call', { callerId: data.callerId });
    };
});

// Lắng nghe tín hiệu cuộc gọi bị từ chối/không nhấc máy
socket.on('call-rejected', (data) => {
    ensureCallUIExists();

    const outboundModal = document.getElementById('calling-outbound-modal');
    if (outboundModal) {
        outboundModal.style.display = 'none';
    }

    if (data && data.reason === 'offline') {
        alert("Đối phương hiện không trực tuyến.");
    } else {
        alert("Đối phương đã từ chối cuộc gọi.");
    }
});

// Khi cuộc gọi được chấp nhận (dành cho người gọi đi), WebSocket server sẽ không kích hoạt trực tiếp,
// mà do người nhận join room WebRTC của Zego trước, sau đó Zego SDK tự kết nối stream của cả 2 khi người gọi join room.
// Vì vậy, khi người nhận nhấn Accept, họ vào room. Người gọi sau khi gửi tín hiệu, ta sẽ cho họ tự động vào room ngay lập tức!
// Hoặc để mượt mà hơn, khi A nhấn gọi, A hiển thị container video cuộc gọi, còn modal outbound chỉ hiển thị nhỏ phía trên.
// Dưới đây là hàm tích hợp Zego Cloud Prebuilt Call Kit.
async function joinZegoCallRoom(roomId) {
    ensureCallUIExists();

    // Ẩn các modal chờ
    const outboundModal = document.getElementById('calling-outbound-modal');
    if (outboundModal) outboundModal.style.display = 'none';

    // Hiển thị khung chứa video/audio call
    const videoModal = document.getElementById('video-call-modal');
    videoModal.style.display = 'block';

    const container = document.getElementById('video-call-container');
    container.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #fff; gap: 20px; font-family: sans-serif;">
            <div style="width: 50px; height: 50px; border: 3px solid rgba(255,255,255,0.1); border-top-color: #10b981; border-radius: 50%; animation: callSpin 1s linear infinite;"></div>
            <div style="font-weight: 500; font-size: 1.1rem; letter-spacing: 0.5px;">Đang thiết lập kết nối an toàn...</div>
        </div>
    `;

    // Thêm CSS keyframe cho spinner nếu chưa có
    if (!document.getElementById('call-spin-keyframes')) {
        const style = document.createElement('style');
        style.id = 'call-spin-keyframes';
        style.textContent = `
            @keyframes callSpin {
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }

    try {
        // Gọi API PHP lấy token từ phía server
        const formData = new FormData();
        formData.append('room_id', roomId);

        const response = await fetch('api/zego-token.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Phản hồi mạng không hợp lệ (Mã: ${response.status})`);
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Không thể tạo mã xác thực cuộc gọi từ máy chủ.');
        }

        const kitToken = data.token;

        const zp = ZegoUIKitPrebuilt.create(kitToken);
        zp.joinRoom({
            container: container,
            turnOnMicrophoneWhenJoining: true,
            turnOnCameraWhenJoining: false,       // Tắt camera khi tham gia cuộc gọi
            showMyCameraToggleButton: false,       // Ẩn nút camera để tránh bật nhầm
            showMyMicrophoneToggleButton: true,
            showAudioVideoSettingsButton: false,   // Ẩn nút cài đặt camera/audio
            showScreenSharingButton: false,       // Ẩn chia sẻ màn hình
            showTextChat: false,                  // Ẩn chat phụ của Zego vì đã dùng chat của trang
            showUserList: false,                  // Ẩn danh sách user
            maxUsers: 2,
            layout: "Auto",
            showLayoutButton: false,
            scenario: {
                mode: ZegoUIKitPrebuilt.OneONoneCall, // Chế độ gọi 1-1
            },
            onLeaveRoom: () => {
                videoModal.style.display = 'none';
                window.location.reload();
            }
        });
    } catch (err) {
        console.error("Lỗi khởi tạo cuộc gọi WebRTC:", err);
        alert("Không thể kết nối cuộc gọi: " + err.message);
        videoModal.style.display = 'none';
    }
}

// Lắng nghe khi người nhận đồng ý cuộc gọi và vào room, 
// ta sẽ tự động đưa người gọi vào cùng room nếu họ đang ở trạng thái Outbound Call.
socket.on('call-accepted', () => {
    if (window.currentZegoRoomId) {
        joinZegoCallRoom(window.currentZegoRoomId);
        window.currentZegoRoomId = null;
    }
});

// Khi tải trang xong, khởi tạo UI cuộc gọi sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    ensureCallUIExists();
});
