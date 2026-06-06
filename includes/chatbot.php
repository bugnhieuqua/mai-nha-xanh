<?php
// Kiểm tra đăng nhập để bảo vệ admin chat
$_chat_logged_in = isset($_SESSION['user_id']);
$_chat_username  = htmlspecialchars($_SESSION['username'] ?? '');
?>
<!-- ===== ADMIN CHAT WIDGET ===== -->
<!-- Admin Chat Toggler Wrapper -->
<div class="admin-chat-wrapper" style="position: fixed; bottom: 90px; right: 15px; width: 50px; height: 50px; z-index: 10000; overflow: visible;">
    <button id="admin-chat-toggler"
            title="Chat với Quản trị viên"
            data-logged-in="<?php echo $_chat_logged_in ? '1' : '0'; ?>"
            style="width: 100%; height: 100%; position: static; margin: 0; padding: 0; display: flex; align-items: center; justify-content: center;">
        <span class="admin-chat-icon">💬</span>
    </button>
    <span id="admin-chat-badge" class="admin-chat-badge" style="display:none;">0</span>
</div>

<?php if ($_chat_logged_in): ?>
<!-- Admin Chat Popup (đã đăng nhập) -->
<div class="admin-chat-popup" id="admin-chat-popup">
    <!-- Header -->
    <div class="admin-chat-header">
        <div class="admin-chat-header-info">
            <div class="admin-avatar-icon">👨‍💼</div>
            <div>
                <h2>Chat với Quản trị viên</h2>
                <span class="admin-status-dot"></span><small>Sẵn sàng hỗ trợ</small>
            </div>
        </div>
        <button id="close-admin-chat" class="admin-close-btn" title="Đóng">✕</button>
    </div>

    <!-- Body -->
    <div class="admin-chat-body" id="admin-chat-body">
        <div class="admin-welcome-msg">
            <div class="admin-bubble admin-bubble-bot">
                <small class="admin-sender-name">Quản trị viên</small>
                Xin chào <strong><?php echo $_chat_username; ?></strong>! 👋 Bạn cần hỗ trợ gì không? Hãy để lại tin nhắn, chúng tôi sẽ phản hồi sớm nhất có thể!
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="admin-chat-footer">
        <form id="admin-chat-form" class="admin-chat-form">
            <textarea
                id="admin-message-input"
                class="admin-message-input"
                placeholder="Nhập tin nhắn cho quản trị viên..."
                rows="1"
                required
            ></textarea>
            <button type="submit" id="admin-send-btn" class="admin-send-btn" title="Gửi">
                ➤
            </button>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Popup yêu cầu đăng nhập (chưa đăng nhập) -->
<div class="admin-chat-popup" id="admin-chat-popup" style="display:none;">
    <div class="admin-chat-header">
        <div class="admin-chat-header-info">
            <div class="admin-avatar-icon">💬</div>
            <div>
                <h2>Chat với Quản trị viên</h2>
                <span class="admin-status-dot"></span><small>Sẵn sàng hỗ trợ</small>
            </div>
        </div>
        <button id="close-admin-chat" class="admin-close-btn" title="Đóng">✕</button>
    </div>
    <div style="padding: 30px 20px; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 12px;">🔒</div>
        <h3 style="margin: 0 0 8px; color: #1e293b; font-size: 1.1rem;">Vui lòng đăng nhập</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 20px;">Bạn cần đăng nhập để có thể nhắn tin với Quản trị viên.</p>
        <a href="login.php" style="display: inline-block; background: linear-gradient(135deg, #10b981, #3b82f6); color: white; padding: 10px 28px; border-radius: 50px; font-weight: 600; text-decoration: none; font-size: 0.95rem; transition: opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            <i class="fas fa-sign-in-alt" style="margin-right:6px;"></i> Đăng nhập ngay
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ===== AI CHATBOT ===== -->
<!-- Chatbot Toggler -->
<button id="chatbot-toggler">
    <img src="assets/images/assistant.png" alt="Chat">
    <span class="material-symbols-rounded">close</span>
</button>

<div class="chatbot-popup">
    <!-- Chatbot Header -->
    <div class="chat-header">
        <div class="header-info">
            <img class="chatbot-logo" src="assets/images/assistant.png" alt="Chatbot Logo" width="50" height="50">
            <h2 class="logo-text">Trợ lý AI</h2>
        </div>
        <button id="close-chatbot" class="material-symbols-rounded">keyboard_arrow_down</button>

    </div>
    
    <!-- Chatbot Body -->
    <div class="chat-body">
        <div class="message bot-message">
            <svg class="bot-avatar" xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 1024 1024">
                <path d="M738.3 287.6H285.7c-59 0-106.8 47.8-106.8 106.8v303.1c0 59 47.8 106.8 106.8 106.8h81.5v111.1c0 .7.8 1.1 1.4.7l166.9-110.6 41.8-.8h117.4l43.6-.4c59 0 106.8-47.8 106.8-106.8V394.5c0-59-47.8-106.9-106.8-106.9zM351.7 448.2c0-29.5 23.9-53.5 53.5-53.5s53.5 23.9 53.5 53.5-23.9 53.5-53.5 53.5-53.5-23.9-53.5-53.5zm157.9 267.1c-67.8 0-123.8-47.5-132.3-109h264.6c-8.6 61.5-64.5 109-132.3 109zm110-213.7c-29.5 0-53.5-23.9-53.5-53.5s23.9-53.5 53.5-53.5 53.5 23.9 53.5 53.5-23.9 53.5-53.5 53.5zM867.2 644.5V453.1h26.5c19.4 0 35.1 15.7 35.1 35.1v121.1c0 19.4-15.7 35.1-35.1 35.1h-26.5zM95.2 609.4V488.2c0-19.4 15.7-35.1 35.1-35.1h26.5v191.3h-26.5c-19.4 0-35.1-15.7-35.1-35.1zM561.5 149.6c0 23.4-15.6 43.3-36.9 49.7v44.9h-30v-44.9c-21.4-6.5-36.9-26.3-36.9-49.7 0-28.6 23.3-51.9 51.9-51.9s51.9 23.3 51.9 51.9z"/>
            </svg>
            <div class="message-text">Xin chào 👋<br/>Tôi có thể giúp gì cho bạn hôm nay?</div>
        </div>

        <!-- Quick Replies (Suggestions) -->
        <div class="quick-replies" id="quick-replies">
            <button class="chip" onclick="sendQuickReply('Giá phòng khoảng bao nhiêu?')">💰 Giá phòng?</button>
            <button class="chip" onclick="sendQuickReply('Còn phòng trống không?')">🏠 Còn phòng không?</button>
            <button class="chip" onclick="sendQuickReply('Địa chỉ cụ thể ở đâu?')">📍 Địa chỉ?</button>
            <button class="chip" onclick="sendQuickReply('Dẫn đường đến trọ gần nhất')">🗺️ Dẫn đường?</button>
            <button class="chip" onclick="sendQuickReply('Có cho nuôi thú cưng không?')">🐶 Nuôi thú cưng?</button>
            <button class="chip" onclick="sendQuickReply('Tổng hợp lịch sử chat theo quý')">📅 Tổng hợp theo quý</button>
            <button class="chip" onclick="sendQuickReply('Tổng hợp lịch sử chat theo năm')">📆 Tổng hợp theo năm</button>
        </div>
    </div>
    
    <!-- Chatbot Footer -->
    <div class="chat-footer">
        <form action="#" class="chat-form">
            <textarea placeholder="Nhập tin nhắn..." class="message-input" required></textarea>
            <div class="chat-controls">
                <button type="button" id="start-stt" class="material-symbols-outlined" title="Nói để nhập tin">mic</button>
                <canvas id="stt-visualizer" style="display: none; position: absolute; left: 10px; right: 10px; bottom: 65px; height: 36px; background: rgba(255,255,255,0.95); border-radius: 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); pointer-events: none; z-index: 99;"></canvas>
                <button type="button" id="emoji-picker" class="material-symbols-outlined">sentiment_satisfied</button>
                <div class="file-upload-wrapper">
                    <input type="file" id="file-input" hidden accept="image/*"/>
                    <img src="#" alt=""/>
                    <button type="button" id="file-upload" class="material-symbols-rounded">attach_file</button>
                    <button type="button" id="file-cancel" class="material-symbols-rounded">close</button>
                </div>
                <button type="submit" id="send-message" class="material-symbols-rounded">arrow_upward</button>
            </div>
        </form>
    </div>
</div>
