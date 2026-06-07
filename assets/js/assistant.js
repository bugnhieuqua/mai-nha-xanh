// Guard: Only run if chatbot elements exist on the page
const chatBody = document.querySelector(".chat-body");
const messageInput = document.querySelector(".message-input");
const sendMessageButton = document.querySelector("#send-message");
const chatbotToggler = document.querySelector("#chatbot-toggler");

if (!chatBody || !messageInput || !sendMessageButton || !chatbotToggler) {
  console.log(
    "Chatbot elements not found on this page. Skipping chatbot initialization.",
  );
} else {
  const fileInput = document.querySelector("#file-input");
  const fileUploadWrapper = document.querySelector(".file-upload-wrapper");
  const fileCancelButton = document.querySelector("#file-cancel");
  const closeChatbot = document.querySelector("#close-chatbot");

  // ==================================================================
  // API CONFIGURATION - SECURE PROXY (Bảo vệ Gemini API Key & Chống WAF)
  // ==================================================================
  const PROXY_URL = "api/v2/chat.php";
  const userData = {
    message: null,
    file: {
      data: null,
      mime_type: null,
    },
  };

  const startSTTBtn = document.querySelector("#start-stt");
  let _ttsHeartbeat = null; // Chrome TTS bug workaround timer
  let currentActiveBtn = null; // Track current speaking button

  // --- Ultra-Fast TTS Cache ---
  let vietnameseVoice = null;

  const loadBestVoice = () => {
    const voices = window.speechSynthesis.getVoices();
    // CỰC KỲ QUAN TRỌNG: Ưu tiên giọng LOCAL (Offline) để không bao giờ bị trễ mạng
    // Tìm Microsoft An (Windows) hoặc bất kỳ giọng nào có localService: true
    vietnameseVoice = voices.find(v => v.lang === "vi-VN" && v.name.includes("Microsoft") && v.localService) || 
                      voices.find(v => v.lang === "vi-VN" && v.localService) || 
                      voices.find(v => v.lang === "vi-VN" && v.name.includes("Google")) || 
                      voices.find(v => v.lang === "vi-VN") || 
                      null;
  };

  // Warm up the engine
  window.speechSynthesis.getVoices();
  if (window.speechSynthesis.onvoiceschanged !== undefined) {
    window.speechSynthesis.onvoiceschanged = loadBestVoice;
  }
  loadBestVoice();

  // Engine Warm-up: Forced silent speak to ready the OS audio buffer
  const warmUpTTS = () => {
    if (!vietnameseVoice) loadBestVoice();
    const ut = new SpeechSynthesisUtterance(" ");
    ut.volume = 0;
    window.speechSynthesis.speak(ut);
    console.log("TTS Engine Warmed up (Fast Start Enabled)");
  };
  
  window.addEventListener('load', () => setTimeout(warmUpTTS, 1000));
  document.addEventListener('mousedown', warmUpTTS, { once: true });

  const resetAudioButton = (btn) => {
    if (!btn) return;
    btn.innerText = "volume_up";
    btn.classList.remove("speaking");
    btn.title = "Nghe câu trả lời";
  };

  const speakText = (text, btn = null) => {
    if (!window.speechSynthesis) return;

    // 1. Chỉ cancel nếu thực sự đang nói (để tránh wait buffer không đáng có)
    if (window.speechSynthesis.speaking) {
      window.speechSynthesis.cancel();
      clearInterval(_ttsHeartbeat);
      
      // Nếu click trùng nút loa đang phát -> dừng lại hẳn và không nói tiếp
      if (currentActiveBtn === btn && btn) {
        resetAudioButton(btn);
        currentActiveBtn = null;
        return;
      }
    }

    // Khôi phục trạng thái nút loa cũ nếu có
    if (currentActiveBtn && currentActiveBtn !== btn) {
      resetAudioButton(currentActiveBtn);
    }

    // 2. Tinh gọn văn bản (Loại bỏ các ký tự Markdown/đặc biệt gây lag)
    let cleanText = text.replace(/[*_#`~>\[\]\(\)-]/g, "").replace(/\s+/g, " ").trim();
    if (!cleanText) return;

    // 3. Khởi tạo Utterance siêu tốc
    const utterance = new SpeechSynthesisUtterance(cleanText);
    utterance.lang = "vi-VN";
    if (vietnameseVoice) utterance.voice = vietnameseVoice;
    
    utterance.rate = 1.0; 
    utterance.pitch = 1.0;
    utterance.volume = 1.0;

    // Gắn trạng thái cho nút loa hiện tại
    currentActiveBtn = btn;
    if (btn) {
      btn.innerText = "volume_off";
      btn.classList.add("speaking");
      btn.title = "Dừng nghe";
    }

    // 4. Heartbeat duy trì truyền dẫn cho Chrome
    const startHeartbeat = () => {
      _ttsHeartbeat = setInterval(() => {
        if (window.speechSynthesis.speaking) window.speechSynthesis.resume();
        else clearInterval(_ttsHeartbeat);
      }, 5000);
    };

    utterance.onstart = startHeartbeat;
    
    const handleSpeechEnd = () => {
      clearInterval(_ttsHeartbeat);
      if (btn) resetAudioButton(btn);
      if (currentActiveBtn === btn) currentActiveBtn = null;
    };

    utterance.onend = handleSpeechEnd;
    utterance.onerror = handleSpeechEnd;

    // 5. PHÁT NGAY LẬP TỨC (Dùng local voice nên độ trễ < 0.1s)
    window.speechSynthesis.speak(utterance);
  };




  // STT: Speech to Text
  let recognition = null;
  if ("webkitSpeechRecognition" in window || "SpeechRecognition" in window) {
    recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
    recognition.lang = "vi-VN";
    recognition.continuous = false;
    recognition.interimResults = false;

    // Canvas Audio Wave Visualizer Simulation
    let sttVisualizing = false;
    const sttCanvas = document.getElementById("stt-visualizer");
    let sttAnimFrame = null;

    const startSTTWave = () => {
      if (!sttCanvas) return;
      sttCanvas.style.display = "block";
      const ctx = sttCanvas.getContext("2d");
      sttVisualizing = true;
      sttCanvas.width = sttCanvas.offsetWidth;
      sttCanvas.height = sttCanvas.offsetHeight;
      
      let step = 0;
      const draw = () => {
        if (!sttVisualizing) return;
        ctx.clearRect(0, 0, sttCanvas.width, sttCanvas.height);
        
        ctx.strokeStyle = "rgba(16, 185, 129, 0.75)";
        ctx.lineWidth = 2.5;
        ctx.beginPath();
        for (let i = 0; i < sttCanvas.width; i++) {
          const y = sttCanvas.height / 2 + Math.sin(i * 0.04 + step) * 12 * Math.sin(step * 0.04);
          if (i === 0) ctx.moveTo(i, y);
          else ctx.lineTo(i, y);
        }
        ctx.stroke();

        ctx.strokeStyle = "rgba(59, 130, 246, 0.45)";
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        for (let i = 0; i < sttCanvas.width; i++) {
          const y = sttCanvas.height / 2 + Math.cos(i * 0.02 + step * 1.3) * 9 * Math.sin(step * 0.02);
          if (i === 0) ctx.moveTo(i, y);
          else ctx.lineTo(i, y);
        }
        ctx.stroke();

        step += 0.12;
        sttAnimFrame = requestAnimationFrame(draw);
      };
      draw();
    };

    const stopSTTWave = () => {
      sttVisualizing = false;
      if (sttAnimFrame) cancelAnimationFrame(sttAnimFrame);
      if (sttCanvas) sttCanvas.style.display = "none";
    };

    recognition.onstart = () => {
      startSTTBtn.classList.add("active");
      startSTTWave();
    };

    recognition.onresult = (event) => {
      const transcript = event.results[0][0].transcript;
      messageInput.value = transcript;
      messageInput.dispatchEvent(new Event("input"));
    };

    recognition.onend = () => {
      startSTTBtn.classList.remove("active");
      stopSTTWave();
    };

    recognition.onerror = () => {
      startSTTBtn.classList.remove("active");
      stopSTTWave();
    };
  }

  if (startSTTBtn && recognition) {
    startSTTBtn.addEventListener("click", () => {
      recognition.start();
    });
  } else if (startSTTBtn) {
    startSTTBtn.style.display = "none"; // Not supported
  }

  // Helper to open room details from chat
  window.openRoomDetailsFromChat = (source, id) => {
    const card = document.querySelector(`.room-card[data-room-key="[ROOM:${source}:${id}]"]`);
    if (card) {
      if (typeof openRoomDetails === 'function') {
        openRoomDetails(card);
      }
    } else {
      window.location.href = `phong-tro.php?room_key=[ROOM:${source}:${id}]`;
    }
  };

  // ── Linkify: convert URLs in bot text into clickable links ────
  const linkifyResponse = (text) => {
    // First, escape HTML to prevent XSS
    const escaped = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

    // Convert [ROOM:source:id] to HTML Card
    const withCards = escaped.replace(/\[ROOM:(phongtro|dangbai):(\d+)\]/g, (match, source, id) => {
      const cardKey = `[ROOM:${source}:${id}]`;
      let roomData = null;
      
      // 1. Check Cache
      if (window.chatbotRoomCache && window.chatbotRoomCache[cardKey]) {
        roomData = window.chatbotRoomCache[cardKey];
      }
      
      // 2. Check DOM (fallback)
      if (!roomData) {
        const card = document.querySelector(`.room-card[data-room-key="${cardKey}"]`);
        if (card) {
          try {
            roomData = JSON.parse(card.getAttribute("data-room") || "{}");
          } catch(e) {}
        }
      }
      
      if (roomData) {
        try {
          const title = roomData.ten_phong || roomData.tieude || "Phòng trọ";
          let priceStr = "Liên hệ";
          if (roomData.gia) {
            priceStr = typeof roomData.gia === 'number' 
              ? roomData.gia.toLocaleString('vi-VN') + 'đ' 
              : roomData.gia;
          }
          const image = roomData.hinhanh || "https://via.placeholder.com/400x300?text=Phong+Tro";
          const diachi = roomData.diachi || "";
          
          return `
            <div class="chatbot-room-card" style="margin: 10px 0; background: var(--white, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); font-family: inherit; width: 100%; max-width: 280px; display: inline-block; text-align: left; vertical-align: top;">
              <img src="${image}" style="width: 100%; height: 120px; object-fit: cover; display: block;" />
              <div style="padding: 12px;">
                <h4 style="margin: 0 0 6px 0; font-size: 0.85rem; font-weight: 700; color: var(--text-main, #1e293b); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${title}</h4>
                <p style="margin: 0 0 8px 0; font-size: 0.7rem; color: var(--gray, #6b7280);"><i class="fas fa-map-marker-alt" style="color: #ef4444; margin-right: 3px;"></i> ${diachi.substring(0, 35)}...</p>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--card-border, #e5e7eb); padding-top: 8px; margin-top: 4px;">
                  <span style="font-weight: 800; color: var(--success, #10b981); font-size: 0.8rem;">${priceStr}</span>
                  <div style="display: flex; gap: 4px;">
                    <a href="javascript:void(0)" class="no-tts" onclick="toggleRoomBubbleMap(this, '${source}', '${id}')" style="background: #3b82f6; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 2.5px 7px; border-radius: 20px; text-decoration: none; box-shadow: 0 3px 8px rgba(59,130,246,0.25);">Bản đồ</a>
                    <a href="javascript:void(0)" class="no-tts" onclick="openRoomDetailsFromChat('${source}', '${id}')" style="background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 2.5px 7px; border-radius: 20px; text-decoration: none; box-shadow: 0 3px 8px rgba(16,185,129,0.25);">Chi tiết</a>
                  </div>
                </div>
                <label class="no-tts" style="display: flex; align-items: center; gap: 6px; font-size: 0.7rem; margin-top: 8px; cursor: pointer; color: #4b5563; font-weight: 600;">
                  <input type="checkbox" class="compare-checkbox no-tts" data-room-key="${cardKey}" onchange="handleCompareCheckboxChange()" style="cursor: pointer; width: 14px; height: 14px;" /> So sánh phòng
                </label>
              </div>
            </div>
          `;
        } catch(e) {
          return `<div style="padding:5px; border:1px dashed #ccc; font-size:0.75rem; color:#888;">Lỗi tải thông tin phòng [ID: ${id}]</div>`;
        }
      } else {
        return `<div class="no-tts" style="padding:8px; background: rgba(0,0,0,0.02); border-radius: 8px; font-size:0.75rem; color:#6b7280; margin: 5px 0; border: 1px solid var(--card-border, #e2e8f0);">🏠 Phòng trọ Mã số: #${id} (Nhấn <a href="phong-tro.php?room_key=[ROOM:${source}:${id}]" style="color:#3b82f6;text-decoration:underline;font-weight:700;">Xem chi tiết</a>)</div>`;
      }
    });

    // Then convert URLs to clickable links
    return withCards.replace(
      /(https?:\/\/[^\s<>"']+)/g,
      (url) => {
        const isGoogleMaps = url.includes('google.com/maps');
        const isZalo = url.includes('zalo.me');
        const isFB = url.includes('facebook.com');

        if (isGoogleMaps) {
          return `<a href="${url}" target="_blank" rel="noopener" class="maps-direction-btn no-tts"><i class="fas fa-map-marker-alt"></i> Mở Google Maps</a>`;
        }
        if (isZalo) {
          return `<a href="${url}" target="_blank" rel="noopener" class="zalo-btn no-tts"><i class="fas fa-comment"></i> Chat Zalo ngay</a>`;
        }
        if (isFB) {
          return `<a href="${url}" target="_blank" rel="noopener" class="fb-btn no-tts"><i class="fab fa-facebook"></i> Xem Facebook</a>`;
        }
        return `<a href="${url}" target="_blank" rel="noopener" class="no-tts" style="color:#3b82f6;text-decoration:underline;">${url}</a>`;
      }
    ).replace(/(0[3|5|7|8|9][0-9]{8})/g, '<a href="tel:$1" class="tel-btn no-tts"><i class="fas fa-phone"></i> $1</a>')
     .replace(/\n/g, '<br>');
  };

  const savedHistory = localStorage.getItem("chatbot_history");
  const chatHistory = savedHistory ? JSON.parse(savedHistory) : [];
  const initialInputHeight = messageInput.scrollHeight;

  // === Chatbot Session: Dùng localStorage để lưu qua các lần đóng/mở trình duyệt ===
  let chatSessionId = localStorage.getItem("chatbot_session_id");
  const currentUserId = document.body.dataset.userId || ""; // Giả định body có data-user-id
  const lastUserId = localStorage.getItem("chatbot_last_user_id") || "";

  // Nếu User ID hiện tại khác với User ID lần cuối chat, xóa session cũ để tạo mới
  if (currentUserId && currentUserId !== lastUserId) {
    chatSessionId = null;
    localStorage.removeItem("chatbot_session_id");
    localStorage.setItem("chatbot_last_user_id", currentUserId);
  }

  if (!chatSessionId) {
    chatSessionId =
      "sess_" + Date.now() + "_" + Math.random().toString(36).substring(2, 11);
    localStorage.setItem("chatbot_session_id", chatSessionId);
  }

  // Lưu lịch sử chatbot vào DB
  const saveChatHistory = async (userMsg, botMsg) => {
    try {
      await fetch("api/luu_tin_nhan.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          session_id: chatSessionId,
          user_message: userMsg,
          bot_response: botMsg,
        }),
      });
    } catch (e) {
      // Không ảnh hưởng đến trải nghiệm người dùng
      console.warn("Could not save chat history:", e);
    }
  };

  // Create message element with dynamic classes
  const createMessageElement = (content, ...classes) => {
    const div = document.createElement("div");
    div.classList.add("message", ...classes);
    div.innerHTML = content;
    return div;
  };

  const renderHistory = () => {
    chatHistory.forEach((msg) => {
      if (msg.role === "user") {
        const textPart = msg.parts.find((p) => p.text);
        const filePart = msg.parts.find((p) => p.inline_data);
        let imgContent = "";
        if (filePart) {
          imgContent = `<img src="data:${filePart.inline_data.mime_type};base64,${filePart.inline_data.data}" class="attachment" />`;
        }
        if (textPart || filePart) {
          const messageContent = `<div class="message-text">${textPart ? textPart.text : ""}</div>${imgContent}`;
          const outgoingMessageDiv = createMessageElement(
            messageContent,
            "user-message",
          );
          chatBody.appendChild(outgoingMessageDiv);
        }
      } else if (msg.role === "model") {
        const textPart = msg.parts.find((p) => p.text);
        if (textPart) {
          const msgContent = `
            <svg class="bot-avatar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path d="M738.3 287.6H285.7c-59 0-106.8 47.8-106.8 106.8v303.1c0 59 47.8 106.8 106.8 106.8h81.5v111.1c0 .7.8 1.1 1.4.7l166.9-110.6 41.8-.8h117.4l43.6-.4c59 0 106.8-47.8 106.8-106.8V394.5c0-59-47.8-106.9-106.8-106.9zM351.7 448.2c0-29.5 23.9-53.5 53.5-53.5s53.5 23.9 53.5 53.5-23.9 53.5-53.5 53.5-53.5-23.9-53.5-53.5zm157.9 267.1c-67.8 0-123.8-47.5-132.3-109h264.6c-8.6 61.5-64.5 109-132.3 109zm110-213.7c-29.5 0-53.5-23.9-53.5-53.5s23.9-53.5 53.5-53.5 53.5 23.9 53.5 53.5-23.9 53.5-53.5 53.5zM867.2 644.5V453.1h26.5c19.4 0 35.1 15.7 35.1 35.1v121.1c0 19.4-15.7 35.1-35.1 35.1h-26.5zM95.2 609.4V488.2c0-19.4 15.7-35.1 35.1-35.1h26.5v191.3h-26.5c-19.4 0-35.1-15.7-35.1-35.1zM561.5 149.6c0 23.4-15.6 43.3-36.9 49.7v44.9h-30v-44.9c-21.4-6.5-36.9-26.3-36.9-49.7 0-28.6 23.3-51.9 51.9-51.9s51.9 23.3 51.9 51.9z"/></svg>
            <div class="message-text">${linkifyResponse(textPart.text)}</div>
            <button class="message-audio-btn material-symbols-rounded" title="Nghe câu trả lời">volume_up</button>
          `;
          const incomingMessageDiv = createMessageElement(
            msgContent,
            "bot-message",
          );
          chatBody.appendChild(incomingMessageDiv);
        }
      }
    });
    if (chatHistory.length > 0) {
      const quickReplies = document.getElementById("quick-replies");
      if (quickReplies) quickReplies.style.display = "none";
      setTimeout(
        () =>
          chatBody.scrollTo({ behavior: "smooth", top: chatBody.scrollHeight }),
        100,
      );
    }
  };
  renderHistory();

  // Generate bot response using direct Gemini API
  const generateBotResponse = async (incomingMessageDiv) => {
    const messageElement = incomingMessageDiv.querySelector(".message-text");

    let combinedMessage = userData.message || "";
    let imgFileForHistory = null;

    if (userData.file && userData.file.data) {
      imgFileForHistory = { ...userData.file };
      try {
        const imgRes = await fetch("api/chatbot_image_handler.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            image_data: userData.file.data,
            mime_type: userData.file.mime_type
          })
        });
        const imgResult = await imgRes.json();
        if (imgResult.success && imgResult.description) {
          combinedMessage += (combinedMessage ? "\n" : "") + `[Hình ảnh phòng trọ: ${imgResult.description}]`;
        }
      } catch (e) {
        console.warn("Failed to get image description from Gemini:", e);
      }
    }

    // Thêm câu hỏi của người dùng vào lịch sử trước
    chatHistory.push({
      role: "user",
      parts: [
        { text: combinedMessage },
        ...(imgFileForHistory ? [{ inline_data: imgFileForHistory }] : []),
      ],
    });
    // Cập nhật session storage ngay khi vừa có tin nhắn mới
    sessionStorage.setItem("chatbot_history", JSON.stringify(chatHistory));

    // Lấy context được cung cấp bởi PHP (danh sách phòng)
    let contextText = window.CHATBOT_CONTEXT_ROOMS || "";

    // ------ CHUẨN BỊ DỮ LIỆU ĐỂ GỬI LÊN GROQ (OpenAI Format) ------
    const messages = [];

    // System prompt: Cấu hình nhiệm vụ nâng cao và nhúng danh sách phòng
    const systemText = 
      "Bạn là Trợ lý Môi giới ảo Siêu trí tuệ của Mái Nhà Xanh - nền tảng tìm kiếm phòng trọ số 1 tại TP. Vinh, Nghệ An.\n\n" +
      "NHIỆM VỤ VÀ NĂNG LỰC NGHIỆP VỤ CỦA BẠN:\n" +
      "1. TƯ VẤN & SO SÁNH PHÒNG TRỌ:\n" +
      "   - Phân tích kỹ dữ liệu danh sách phòng trọ hiện có được cung cấp ở bên dưới.\n" +
      "   - Khi người dùng hỏi tìm phòng trọ (gần nhất, rẻ nhất, rộng nhất, có máy lạnh, tự do giờ giấc...), hãy lọc, so sánh và sắp xếp dữ liệu số để trả lời chính xác nhất.\n" +
      "   - Sử dụng trường 'Khoảng cách đến Trường Đại học Kinh tế Nghệ An' để so sánh vị trí địa lý của các phòng trọ. Gợi ý cụ thể khoảng cách của phòng đó đến trường.\n" +
      "2. XỬ LÝ ẢNH ĐA PHƯƠNG THỨC:\n" +
      "   - Nếu người dùng gửi tin nhắn kèm chuỗi '[Hình ảnh phòng trọ: ...]', đó là thông tin mô tả do Gemini phân tích từ ảnh thật họ tải lên.\n" +
      "   - Hãy đọc kỹ mô tả ảnh này để bình luận về phòng của họ (nội thất, không gian, phong cách thiết kế) và đối chiếu với danh sách phòng hiện có trong hệ thống để giới thiệu các phòng tương tự.\n" +
      "3. QUẢN LÝ TRẠNG THÁI PHÒNG:\n" +
      "   - Cực kỳ chú ý trường 'Tình trạng'. Tránh gợi ý các phòng 'ĐÃ THUÊ' làm phòng còn trống. Hãy gợi ý các phòng ở trạng thái 'SẴN SÀNG' hoặc 'Còn phòng'. Nếu phòng khách hỏi đã thuê, hãy thông báo lịch sự và gợi ý ngay các phòng trống khác.\n" +
      "4. QUY TẮC PHẢN HỒI BẮT BUỘC:\n" +
      "   - Để hiển thị thẻ phòng tương tác trong khung chat, bạn BẮT BUỘC phải chèn mã phòng ở định dạng [ROOM:nguon:id] (Ví dụ: [ROOM:phongtro:3] hoặc [ROOM:dangbai:12]) trực tiếp vào vị trí thích hợp trong câu trả lời khi giới thiệu phòng.\n" +
      "   - Khi khách hỏi đường đi hoặc chỉ đường, hãy trả lời bằng đường link Google Maps đầy đủ dạng URL được cung cấp trong danh sách phòng (bắt đầu bằng https://www.google.com/maps/dir/...). Không bọc link này trong Markdown.\n" +
      "   - Không được dùng bảng biểu Markdown. Sử dụng dấu * để in đậm các từ khóa quan trọng thay vì **.\n\n" +
      "PHONG CÁCH: Trò chuyện tự nhiên, chuyên nghiệp, lịch sự, nhiệt tình giúp đỡ và phản hồi ngắn gọn, rõ ràng.\n\n" +
      "DỮ LIỆU PHÒNG TRỌ HIỆN CÓ ĐỂ BẠN TRA CỨU:\n" + 
      contextText;
    
    messages.push({ role: "system", content: systemText });

    // --- TỐI ƯU HÓA NGỮ CẢNH (Sliding Window: Chỉ lấy 10 tin nhắn gần nhất) ---
    const limitedHistory = chatHistory.slice(-10);

    // Dồn lịch sử hội thoại (Chuyển tiếp từ format Gemini/Giao diện -> format Groq)
    limitedHistory.forEach((msg) => {
      const textPart = msg.parts ? msg.parts.find((p) => p.text) : null;
      if (!textPart) return;
      const groqRole = msg.role === "model" ? "assistant" : "user";
      messages.push({ role: groqRole, content: textPart.text });
    });

    const payloadObject = { messages: messages };

    // Mã hóa toàn bộ chuỗi JSON thành Base64 (Bypass WAF Server Block)
    // Dùng unescape/encodeURIComponent để encode an toàn chuỗi Tiếng Việt UTF-8
    const base64Payload = btoa(unescape(encodeURIComponent(JSON.stringify(payloadObject))));

    const requestOptions = {
      method: "POST",
      // Đặt content-type text/plain để Hosting không tự động parse JSON kiểm duyệt WAF
      headers: { "Content-Type": "text/plain" },
      body: base64Payload,
    };

    try {
      const response = await fetch(PROXY_URL, requestOptions);
      const rawText = await response.text();

      let data;
      try {
        data = JSON.parse(rawText);
        if (data && data.matched_rooms && Array.isArray(data.matched_rooms)) {
          window.chatbotRoomCache = window.chatbotRoomCache || {};
          data.matched_rooms.forEach(room => {
            const key = `[ROOM:${room.source}:${room.room_id}]`;
            window.chatbotRoomCache[key] = room;
          });
        }
      } catch (jsonErr) {
        // Encode rawText safe for innerHTML
        let safeHTML = rawText.substring(0, 150).replace(/</g, "&lt;").replace(/>/g, "&gt;");
        let errDesc = `Máy chủ PHP trả về dạng chữ/HTML thay vì JSON. Chi tiết mã gốc: <br><br><code>${safeHTML}</code>`;
        throw new Error(errDesc);
      }

      if (!response.ok) {
        throw new Error(data.error?.message || data.error || "Lỗi kết nối (HTTP " + response.status + ")");
      }

      // Xử lý kết quả trả về từ Groq (Chuẩn OpenAI format: choices[0].message.content)
      const apiResponseText = (data.choices[0].message.content || "")
        .replace(/\*\*(.*?)\*\*/g, "$1") // Loại bỏ in đậm kép
        .trim();
      messageElement.innerHTML = linkifyResponse(apiResponseText);

      // --- Tự động thêm nút loa sau khi in ra phản hồi ---
      const audioBtn = document.createElement("button");
      audioBtn.className = "message-audio-btn material-symbols-rounded";
      audioBtn.innerText = "volume_up";
      audioBtn.title = "Nghe câu trả lời";
      incomingMessageDiv.appendChild(audioBtn);
      // ----------------------------------------------------

      // Lưu câu trả lời của Bot vào lịch sử
      chatHistory.push({
        role: "model",
        parts: [{ text: apiResponseText }],
      });
      // Cập nhật session storage ngay khi nhận được câu trả lời
      sessionStorage.setItem("chatbot_history", JSON.stringify(chatHistory));

      // Lưu đoạn chat vào database sau khi chatbot trả lời thành công
      saveChatHistory(combinedMessage, apiResponseText);

    } catch (error) {
      console.error(error);
      const isRetryable = error.message.includes("429") || error.message.includes("502") || error.message.includes("Lỗi kết nối");
      messageElement.innerHTML = `
        <div style="color:#ef4444; font-size: 0.9em; margin-bottom: 8px;">
          <i class="fas fa-exclamation-triangle"></i> ${error.message || "Lỗi không xác định."}
        </div>
        ${isRetryable ? `<button onclick="this.parentElement.parentElement.remove(); generateBotResponse(createMessageElement('', 'bot-message', 'thinking'))" style="background:#f1f5f9; border:1px solid #cbd5e1; padding:4px 12px; border-radius:15px; font-size:0.8rem; cursor:pointer;"><i class="fas fa-redo"></i> Thử lại</button>` : '<small>Hãy tải lại trang hoặc kiểm tra Server.</small>'}
      `;
    } finally {
      // Reset thông tin file đính kèm
      userData.file = {};
      incomingMessageDiv.classList.remove("thinking");
      chatBody.scrollTo({ behavior: "smooth", top: chatBody.scrollHeight });
    }
  };

  // Sự kiện click cho các nút nghe trong chat (Event Delegation)
  chatBody.addEventListener("click", (e) => {
    if (e.target.classList.contains("message-audio-btn")) {
      const messageDiv = e.target.closest(".message");
      const messageTextEl = messageDiv.querySelector(".message-text");
      if (messageTextEl) {
        // Clone và xóa bỏ các phần tử giao diện phụ không cần đọc
        const clone = messageTextEl.cloneNode(true);
        clone.querySelectorAll(".no-tts").forEach(el => el.remove());
        const text = clone.innerText || clone.textContent;
        speakText(text, e.target);
      }
    }
  });

  // Dừng đọc TTS lập tức khi chuyển hướng trang để tránh lỗi phát lặp nền
  window.addEventListener("beforeunload", () => {
    if (window.speechSynthesis) {
      window.speechSynthesis.cancel();
    }
  });

  // Handle outgoing user message
  const handleOutgoingMessage = (e) => {
    if (e) e.preventDefault();

    userData.message = messageInput.value.trim();
    if (!userData.message && !userData.file.data) return;

    messageInput.value = "";
    fileUploadWrapper.classList.remove("file-uploaded");
    messageInput.dispatchEvent(new Event("input"));

    const quickReplies = document.getElementById("quick-replies");
    if (quickReplies) quickReplies.style.display = "none";

    const messageContent = `<div class="message-text"></div>
        ${userData.file.data ? `<img src="data:${userData.file.mime_type};base64,${userData.file.data}" class="attachment" />` : ""}`;

    const outgoingMessageDiv = createMessageElement(
      messageContent,
      "user-message",
    );
    outgoingMessageDiv.querySelector(".message-text").innerText =
      userData.message;
    chatBody.appendChild(outgoingMessageDiv);
    chatBody.scrollTo({ behavior: "smooth", top: chatBody.scrollHeight });

    setTimeout(() => {
      const msgContent = `
            <svg class="bot-avatar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path d="M738.3 287.6H285.7c-59 0-106.8 47.8-106.8 106.8v303.1c0 59 47.8 106.8 106.8 106.8h81.5v111.1c0 .7.8 1.1 1.4.7l166.9-110.6 41.8-.8h117.4l43.6-.4c59 0 106.8-47.8 106.8-106.8V394.5c0-59-47.8-106.9-106.8-106.9zM351.7 448.2c0-29.5 23.9-53.5 53.5-53.5s53.5 23.9 53.5 53.5-23.9 53.5-53.5 53.5-53.5-23.9-53.5-53.5zm157.9 267.1c-67.8 0-123.8-47.5-132.3-109h264.6c-8.6 61.5-64.5 109-132.3 109zm110-213.7c-29.5 0-53.5-23.9-53.5-53.5s23.9-53.5 53.5-53.5 53.5 23.9 53.5 53.5-23.9 53.5-53.5 53.5zM867.2 644.5V453.1h26.5c19.4 0 35.1 15.7 35.1 35.1v121.1c0 19.4-15.7 35.1-35.1 35.1h-26.5zM95.2 609.4V488.2c0-19.4 15.7-35.1 35.1-35.1h26.5v191.3h-26.5c-19.4 0-35.1-15.7-35.1-35.1zM561.5 149.6c0 23.4-15.6 43.3-36.9 49.7v44.9h-30v-44.9c-21.4-6.5-36.9-26.3-36.9-49.7 0-28.6 23.3-51.9 51.9-51.9s51.9 23.3 51.9 51.9z"/></svg>
            <div class="message-text">
                <div class="thinking-indicator">
                    <div class="dot"></div><div class="dot"></div><div class="dot"></div>
                </div>
            </div>`;

      const incomingMessageDiv = createMessageElement(
        msgContent,
        "bot-message",
        "thinking",
      );
      chatBody.appendChild(incomingMessageDiv);
      chatBody.scrollTo({ behavior: "smooth", top: chatBody.scrollHeight });
      generateBotResponse(incomingMessageDiv);
    }, 600);
  };

  // Global function for Quick Replies
  window.sendQuickReply = (text) => {
    messageInput.value = text;
    handleOutgoingMessage();
  };

  // Event Listeners
  messageInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey && window.innerWidth > 768) {
      handleOutgoingMessage(e);
    }
  });

  messageInput.addEventListener("input", () => {
    messageInput.style.height = "auto";
    messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + "px";
  });

  // Mobile: scroll chatbot body to show input when keyboard appears
  messageInput.addEventListener("focus", () => {
    if (window.innerWidth <= 520) {
      setTimeout(() => {
        chatBody.scrollTo({ behavior: "smooth", top: chatBody.scrollHeight });
        messageInput.scrollIntoView({ behavior: "smooth", block: "nearest" });
      }, 350); // Wait for keyboard animation
    }
  });

  sendMessageButton.addEventListener("click", (e) => handleOutgoingMessage(e));
  document
    .querySelector("#file-upload")
    .addEventListener("click", () => fileInput.click());
  chatbotToggler.addEventListener("click", () =>
    document.body.classList.toggle("show-chatbot"),
  );
  closeChatbot.addEventListener("click", () =>
    document.body.classList.remove("show-chatbot"),
  );

  // File Input Handling
  fileInput.addEventListener("change", (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      fileUploadWrapper.querySelector("img").src = e.target.result;
      fileUploadWrapper.classList.add("file-uploaded");
      userData.file = {
        data: e.target.result.split(",")[1],
        mime_type: file.type,
      };
    };
    reader.readAsDataURL(file);
  });

  fileCancelButton.addEventListener("click", () => {
    userData.file = {};
    fileUploadWrapper.classList.remove("file-uploaded");
  });

  // ==================================================================
  // ADMIN CHAT WIDGET
  // ==================================================================
  const adminChatToggler  = document.getElementById("admin-chat-toggler");
  const adminChatPopup    = document.getElementById("admin-chat-popup");
  const closeAdminChat    = document.getElementById("close-admin-chat");
  const adminChatBody     = document.getElementById("admin-chat-body");
  const adminChatForm     = document.getElementById("admin-chat-form");
  const adminMessageInput = document.getElementById("admin-message-input");
  const adminBadge        = document.getElementById("admin-chat-badge");

  let adminChatOpen      = false;
  let adminUnreadCount   = 0;
  const renderedAdminMsgIds = new Set();
  const optimisticMsgTexts  = new Set(); // Để chặn trùng tin vừa gửi xong poll về ngay

  // --- Helper: Tạo bubble chat ---
  const createAdminBubble = (text, timeStr, isAdmin) => {
    const wrap = document.createElement("div");
    wrap.classList.add("admin-bubble-wrap", isAdmin ? "bot-wrap" : "user-wrap");
    const safeText = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                         .replace(/\n/g,'<br>');
    wrap.innerHTML = `
      <div class="admin-bubble ${isAdmin ? 'admin-bubble-bot' : 'admin-bubble-user'}">
        ${isAdmin ? '<small class="admin-sender-name">Quản trị viên</small>' : ''}
        ${safeText}
      </div>
      <span class="admin-bubble-time">${timeStr}</span>`;
    return wrap;
  };

  // --- Mở/đóng Admin Chat Popup ---
  const openAdminChat = () => {
    adminChatOpen = true;
    adminChatPopup.classList.add("show");
    // Reset badge khi mở
    adminUnreadCount = 0;
    adminBadge.style.display = "none";
    adminBadge.textContent = "0";
    // Scroll xuống dưới
    setTimeout(() => adminChatBody.scrollTo({ behavior: "smooth", top: adminChatBody.scrollHeight }), 100);
    // Focus input
    if (adminMessageInput) adminMessageInput.focus();
    
    // Gửi request đánh dấu đã đọc trên server
    if (chatSessionId) {
       fetch(`api/get_messages.php?session_id=${encodeURIComponent(chatSessionId)}&mark_read=1&_t=${Date.now()}`, { cache: 'no-store' }).catch(()=>{});
    }
  };

  const closeAdminChatFn = () => {
    adminChatOpen = false;
    adminChatPopup.classList.remove("show");
  };

  if (adminChatToggler) {
    adminChatToggler.addEventListener("click", () => {
      // Chưa đăng nhập → hiện thông báo yêu cầu đăng nhập
      if (adminChatToggler.dataset.loggedIn !== "1") {
        showLoginToast();
        return;
      }
      if (adminChatOpen) { closeAdminChatFn(); } else { openAdminChat(); }
      
      // Xin quyền thông báo trình duyệt (giống Messenger/Zalo)
      if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
      }
    });
  }

  // Hiện toast "cần đăng nhập để chat admin"
  function showLoginToast() {
    const existing = document.getElementById("admin-login-toast");
    if (existing) { existing.remove(); }

    const toast = document.createElement("div");
    toast.id = "admin-login-toast";
    toast.innerHTML = `
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:1.5rem;">🔒</span>
        <div>
          <div style="font-weight:700;font-size:.95rem;margin-bottom:2px;">Vui lòng đăng nhập</div>
          <div style="font-size:.82rem;opacity:.9;">Bạn cần đăng nhập để chat với Quản trị viên</div>
        </div>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
        <button onclick="document.getElementById('admin-login-toast').remove()"
                style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);
                       color:#fff;padding:5px 14px;border-radius:20px;cursor:pointer;font-size:.82rem;">
          Đóng
        </button>
        <a href="login.php"
           style="background:#fff;color:#10b981;padding:5px 16px;border-radius:20px;
                  font-weight:700;font-size:.82rem;text-decoration:none;display:inline-block;">
          Đăng nhập ngay →
        </a>
      </div>`;
    toast.style.cssText = `
      position:fixed; bottom:90px; right:15px; max-width:290px;
      background:linear-gradient(135deg,#10b981,#059669);
      color:#fff; border-radius:16px; padding:16px 18px;
      box-shadow:0 8px 30px rgba(16,185,129,.4);
      z-index:10010; animation:toastSlideIn .3s ease;
      font-family:'Inter',sans-serif;`;

    if (!document.getElementById("toast-keyframes")) {
      const style = document.createElement("style");
      style.id = "toast-keyframes";
      style.textContent = `@keyframes toastSlideIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}`;
      document.head.appendChild(style);
    }

    document.body.appendChild(toast);
    setTimeout(() => { if (toast.parentNode) toast.remove(); }, 5000);
  }
  if (closeAdminChat) closeAdminChat.addEventListener("click", closeAdminChatFn);

  // --- Gửi tin nhắn cho admin ---
  const sendAdminMessage = async (text) => {
    if (!text || !chatSessionId) return;

    const now = new Date();
    const timeStr = now.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });
    adminChatBody.appendChild(createAdminBubble(text, timeStr, false));
    adminChatBody.scrollTo({ behavior: "smooth", top: adminChatBody.scrollHeight });
    
    // Lưu tạm text để pollAdminReplies không render lại chính tin này trước khi có ID thật
    optimisticMsgTexts.add(text);
    setTimeout(() => optimisticMsgTexts.delete(text), 15000); // Tự xóa sau 15 giây

    try {
      const res = await fetch("api/gui_tin_cho_admin.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ session_id: chatSessionId, message: text }),
      });
      const data = await res.json();
      if (data.success && data.id) {
        renderedAdminMsgIds.add(data.id);
      }
    } catch(e) { /* ignore */ }
  };

  if (adminChatForm) {
    adminChatForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const text = (adminMessageInput?.value ?? "").trim();
      if (!text) return;
      adminMessageInput.value = "";
      adminMessageInput.style.height = "auto";
      sendAdminMessage(text);
    });
  }

  // Auto-resize textarea
  if (adminMessageInput) {
    adminMessageInput.addEventListener("input", () => {
      adminMessageInput.style.height = "auto";
      adminMessageInput.style.height = Math.min(adminMessageInput.scrollHeight, 100) + "px";
    });
    adminMessageInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !e.shiftKey && window.innerWidth > 520) {
        e.preventDefault();
        adminChatForm.requestSubmit();
      }
    });
    // Mobile: scroll when keyboard appears
    adminMessageInput.addEventListener("focus", () => {
      if (window.innerWidth <= 520) {
        setTimeout(() => adminChatBody.scrollTo({ behavior: "smooth", top: adminChatBody.scrollHeight }), 350);
      }
    });
  }

  // --- Load lịch sử chat với admin khi khởi động ---
  const loadAdminHistory = async () => {
    if (!chatSessionId) return;
    try {
      let url = `api/get_messages.php?session_id=${encodeURIComponent(chatSessionId)}&_t=${Date.now()}`;
      if (adminChatOpen) url += '&mark_read=1';
      const res  = await fetch(url, { cache: 'no-store' });
      const data = await res.json();
      if (!data.success || !data.data.length) return;

      data.data.forEach(row => {
        if (row.id) renderedAdminMsgIds.add(row.id);
        const d = new Date(row.created_at);
        const timeStr = d.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });
        // Tin người dùng gửi (không phải AI)
        if (row.sender === "user" && row.user_message && !row.bot_response) {
          adminChatBody.appendChild(createAdminBubble(row.user_message, timeStr, false));
        }
        // Tin admin phản hồi
        if (row.sender === "admin" && row.admin_message) {
          adminChatBody.appendChild(createAdminBubble(row.admin_message, timeStr, true));
        }
      });

      if (data.data.length > 0) {
        adminPollLastTime = data.data[data.data.length - 1].created_at;
      }
      adminChatBody.scrollTo({ top: adminChatBody.scrollHeight });
    } catch(e) { /* ignore */ }
  };

  // --- Polling tin nhắn mới từ admin ---
  const pollAdminReplies = async () => {
    if (!chatSessionId) return;
    try {
      let url = `api/get_messages.php?session_id=${encodeURIComponent(chatSessionId)}&_t=${Date.now()}`;
      if (adminPollLastTime) url += `&since=${encodeURIComponent(adminPollLastTime)}`;
      if (adminChatOpen) url += '&mark_read=1';

      const res  = await fetch(url, { cache: 'no-store' });
      const data = await res.json();
      if (!data.success || !data.data.length) return;

      let hasNewAdmin = false;
      let lastAdminMsg = "";

      data.data.forEach(row => {
        if (row.id && renderedAdminMsgIds.has(row.id)) return;
        
        // Chặn trùng tin user vừa gửi (optimistic)
        if (row.sender === "user" && row.user_message && optimisticMsgTexts.has(row.user_message)) {
            if (row.id) renderedAdminMsgIds.add(row.id);
            return;
        }

        if (row.id) renderedAdminMsgIds.add(row.id);

        if (row.sender === "admin" && row.admin_message) {
          const d = new Date(row.created_at);
          const timeStr = d.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });
          adminChatBody.appendChild(createAdminBubble(row.admin_message, timeStr, true));
          hasNewAdmin = true;
          lastAdminMsg = row.admin_message;
          if (!adminChatOpen) adminUnreadCount++;
        }
      });

      if (hasNewAdmin) {
        adminChatBody.scrollTo({ behavior: "smooth", top: adminChatBody.scrollHeight });
        
        // --- HIỂN THỊ TOAST THÔNG BÁO TRÊN MÀN HÌNH ---
        if (!adminChatOpen && typeof NotifSystem === 'undefined') {
          // Trang không bật hệ thống thông báo chung, chỉ giữ badge nổi của widget chat.
        }

        // --- ÂM THANH & THÔNG BÁO TRÌNH DUYỆT ---
        // Phát âm thanh khi có tin nhắn (chỉ khi popup đóng hoặc người dùng đang ở tab khác)
        if (!adminChatOpen || document.hidden) {
          try {
            // Âm thanh "Ting" nhẹ nhàng bằng Web Audio API
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
              const ctx = new AudioContext();
              const osc = ctx.createOscillator();
              const gain = ctx.createGain();
              osc.connect(gain);
              gain.connect(ctx.destination);
              osc.type = "sine";
              osc.frequency.setValueAtTime(880, ctx.currentTime); // Nốt La cao (A5)
              gain.gain.setValueAtTime(0.2, ctx.currentTime);
              gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
              osc.start();
              osc.stop(ctx.currentTime + 0.5);
            }
          } catch(e) {}

          // Hiện popup thông báo trình duyệt (Push Notification)
          if ("Notification" in window && Notification.permission === "granted") {
            const notif = new Notification("Quản trị viên - Mái Nhà Xanh", {
              body: "Bạn có tin nhắn phản hồi mới từ quản trị viên.",
              icon: "assets/images/logo.png"
            });
            notif.onclick = () => {
              window.focus();
              if (!adminChatOpen && adminChatToggler) adminChatToggler.click();
            };
          }
        }

        // Cập nhật badge nếu popup đang đóng
        if (!adminChatOpen) {
          adminBadge.textContent = adminUnreadCount > 9 ? "9+" : adminUnreadCount;
          adminBadge.style.display = "flex";
        }
      }

      adminPollLastTime = data.data[data.data.length - 1].created_at;
    } catch(e) { /* silently ignore */ }
  };

  // Tải lịch sử và bắt đầu polling
  loadAdminHistory();
  setTimeout(() => {
    pollAdminReplies();
    setInterval(pollAdminReplies, 2000);
  }, 1000);

  // === PHỤC HỒI BADGE KHI QUAY LẠI TRANG ===
  // Khi user quay lại trang sau khi rời đi, kiểm tra số tin nhắn admin chưa đọc
  // và hiển thị ngửy lên mà không cần mở hộp chat
  const restoreAdminBadge = async () => {
    if (!chatSessionId || !adminBadge) return;
    try {
      const res = await fetch(`api/get_messages.php?session_id=${encodeURIComponent(chatSessionId)}&_t=${Date.now()}`, { cache: 'no-store' });
      const data = await res.json();
      if (!data.success || !data.data.length) return;

      // Đếm số tin nhắn admin chưa đọc (is_read = 0)
      const unreadCount = data.data.filter(
        row => row.sender === 'admin' && row.admin_message && parseInt(row.is_read) === 0
      ).length;

      if (unreadCount > 0) {
        adminUnreadCount = unreadCount;
        adminBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        adminBadge.style.display = 'flex';
      } else {
        adminBadge.style.display = 'none';
        adminUnreadCount = 0;
      }
    } catch(e) { /* ignore */ }
  };
  restoreAdminBadge();

  const maybeOpenAdminChatFromUrl = () => {
    if (!adminChatToggler || adminChatToggler.dataset.loggedIn !== "1") return;

    try {
      const url = new URL(window.location.href);
      if (url.searchParams.get("open_admin_chat") !== "1") return;

      openAdminChat();
      url.searchParams.delete("open_admin_chat");
      const nextUrl = url.pathname + (url.searchParams.toString() ? "?" + url.searchParams.toString() : "") + url.hash;
      window.history.replaceState({}, document.title, nextUrl);
    } catch (e) { /* ignore */ }
  };

  window.addEventListener("mnx:open-admin-chat", () => {
    if (adminChatToggler && adminChatToggler.dataset.loggedIn === "1") {
      openAdminChat();
    }
  });

  maybeOpenAdminChatFromUrl();



  // ==================================================================
  // CUSTOM EMOJI PICKER (no external library needed)
  // ==================================================================
  (function () {
    const EMOJIS = {
      "Mặt cười": [
        "😀",
        "😃",
        "😄",
        "😁",
        "😆",
        "😅",
        "🤣",
        "😂",
        "🙂",
        "😊",
        "😇",
        "🥰",
        "😍",
        "🤩",
        "😘",
        "😗",
        "😚",
        "😙",
        "🥲",
        "😋",
        "😛",
        "😜",
        "🤪",
        "😝",
        "🤑",
        "🤗",
        "🤭",
        "🫣",
        "🤫",
        "🤔",
        "🫡",
        "🤐",
        "🤨",
        "😐",
        "😑",
        "😶",
        "🫥",
        "😏",
        "😒",
        "🙄",
        "😬",
        "🤥",
        "😌",
        "😔",
        "😪",
        "🤤",
        "😴",
        "😷",
        "🤒",
        "🤕",
        "🤢",
        "🤮",
        "🥵",
        "🥶",
        "🥴",
        "😵",
        "🤯",
        "🤠",
        "🥳",
        "🥸",
        "😎",
        "🤓",
        "🧐",
        "😕",
        "🫤",
        "😟",
        "🙁",
        "😮",
        "😯",
        "😲",
        "😳",
        "🥺",
        "🥹",
        "😦",
        "😧",
        "😨",
        "😰",
        "😥",
        "😢",
        "😭",
        "😱",
        "😖",
        "😣",
        "😞",
        "😓",
        "😩",
        "😫",
        "🥱",
        "😤",
        "😡",
        "😠",
        "🤬",
        "😈",
        "👿",
        "💀",
        "☠️",
        "💩",
        "🤡",
        "👹",
        "👺",
        "👻",
        "👽",
        "👾",
        "🤖",
      ],
      "Cử chỉ": [
        "👋",
        "🤚",
        "🖐️",
        "✋",
        "🖖",
        "🫱",
        "🫲",
        "🫳",
        "🫴",
        "👌",
        "🤌",
        "🤏",
        "✌️",
        "🤞",
        "🫰",
        "🤟",
        "🤘",
        "🤙",
        "👈",
        "👉",
        "👆",
        "🖕",
        "👇",
        "☝️",
        "🫵",
        "👍",
        "👎",
        "✊",
        "👊",
        "🤛",
        "🤜",
        "👏",
        "🙌",
        "🫶",
        "👐",
        "🤲",
        "🤝",
        "🙏",
        "✍️",
        "💅",
        "🤳",
        "💪",
        "🦾",
        "🦿",
        "🦵",
        "🦶",
        "👂",
        "🦻",
        "👃",
        "🧠",
        "🫀",
        "🫁",
        "🦷",
        "🦴",
        "👀",
        "👁️",
        "👅",
        "👄",
      ],
      "Trái tim": [
        "❤️",
        "🧡",
        "💛",
        "💚",
        "💙",
        "💜",
        "🖤",
        "🤍",
        "🤎",
        "💔",
        "❤️‍🔥",
        "❤️‍🩹",
        "❣️",
        "💕",
        "💞",
        "💓",
        "💗",
        "💖",
        "💘",
        "💝",
        "💟",
      ],
      "Động vật": [
        "🐶",
        "🐱",
        "🐭",
        "🐹",
        "🐰",
        "🦊",
        "🐻",
        "🐼",
        "🐻‍❄️",
        "🐨",
        "🐯",
        "🦁",
        "🐮",
        "🐷",
        "🐸",
        "🐵",
        "🙈",
        "🙉",
        "🙊",
        "🐒",
        "🐔",
        "🐧",
        "🐦",
        "🐤",
        "🐣",
        "🐥",
        "🦆",
        "🦅",
        "🦉",
        "🦇",
        "🐺",
        "🐗",
        "🐴",
        "🦄",
        "🐝",
        "🪱",
        "🐛",
        "🦋",
        "🐌",
        "🐞",
        "🐜",
        "🪰",
        "🪲",
        "🪳",
        "🦟",
        "🦗",
        "🕷️",
        "🦂",
        "🐢",
        "🐍",
        "🦎",
        "🦖",
        "🦕",
        "🐙",
        "🦑",
        "🦐",
        "🦀",
        "🐡",
        "🐠",
        "🐟",
        "🐬",
        "🐳",
        "🐋",
        "🦈",
        "🐊",
        "🐅",
        "🐆",
        "🦓",
        "🦍",
        "🦧",
        "🐘",
        "🦛",
        "🦏",
        "🐪",
        "🐫",
        "🦒",
        "🦘",
        "🦬",
        "🐃",
        "🐂",
        "🐄",
        "🐎",
        "🐖",
        "🐏",
        "🐑",
        "🦙",
        "🐐",
        "🦌",
        "🐕",
      ],
      "Đồ ăn": [
        "🍏",
        "🍎",
        "🍐",
        "🍊",
        "🍋",
        "🍌",
        "🍉",
        "🍇",
        "🍓",
        "🫐",
        "🍈",
        "🍒",
        "🍑",
        "🥭",
        "🍍",
        "🥥",
        "🥝",
        "🍅",
        "🍆",
        "🥑",
        "🥦",
        "🥬",
        "🥒",
        "🌶️",
        "🫑",
        "🌽",
        "🥕",
        "🫒",
        "🧄",
        "🧅",
        "🥔",
        "🍠",
        "🥐",
        "🍞",
        "🥖",
        "🥨",
        "🧀",
        "🥚",
        "🍳",
        "🧈",
        "🥞",
        "🧇",
        "🥓",
        "🥩",
        "🍗",
        "🍖",
        "🦴",
        "🌭",
        "🍔",
        "🍟",
        "🍕",
        "🫓",
        "🥪",
        "🥙",
        "🧆",
        "🌮",
        "🌯",
        "🫔",
        "🥗",
        "🥘",
        "🫕",
        "🥫",
        "🍝",
        "🍜",
        "🍲",
        "🍛",
        "🍣",
        "🍱",
        "🥟",
        "🦪",
        "🍤",
        "🍙",
        "🍚",
        "🍘",
        "🍥",
        "🥠",
        "🥮",
        "🍢",
        "🍡",
        "🍧",
        "🍨",
        "🍦",
        "🥧",
        "🧁",
        "🍰",
        "🎂",
        "🍮",
        "🍭",
        "🍬",
        "🍫",
        "🍿",
        "🍩",
        "🍪",
        "🌰",
        "🥜",
        "🍯",
        "🥛",
        "🍼",
        "🫖",
        "☕",
        "🍵",
        "🧃",
        "🥤",
        "🧋",
        "🍶",
        "🍺",
        "🍻",
        "🥂",
        "🍷",
        "🥃",
        "🍸",
        "🍹",
        "🧉",
        "🍾",
      ],
      "Hoạt động": [
        "⚽",
        "🏀",
        "🏈",
        "⚾",
        "🥎",
        "🎾",
        "🏐",
        "🏉",
        "🥏",
        "🎱",
        "🪀",
        "🏓",
        "🏸",
        "🏒",
        "🏑",
        "🥍",
        "🏏",
        "🪃",
        "🥅",
        "⛳",
        "🪁",
        "🏹",
        "🎣",
        "🤿",
        "🥊",
        "🥋",
        "🎽",
        "🛹",
        "🛼",
        "🛷",
        "⛸️",
        "🥌",
        "🎿",
        "⛷️",
        "🏂",
        "🪂",
        "🏋️",
        "🤸",
        "🤺",
        "⛹️",
        "🤾",
        "🏌️",
        "🏇",
        "🧘",
        "🏄",
        "🏊",
        "🤽",
        "🚣",
        "🧗",
        "🚴",
        "🚵",
        "🎖️",
        "🏆",
        "🥇",
        "🥈",
        "🥉",
        "🏅",
        "🎗️",
        "🎪",
        "🎭",
        "🎨",
        "🎬",
        "🎤",
        "🎧",
        "🎼",
        "🎹",
        "🥁",
        "🪘",
        "🎷",
        "🎺",
        "🪗",
        "🎸",
        "🪕",
        "🎻",
      ],
      "Du lịch": [
        "🚗",
        "🚕",
        "🚙",
        "🚌",
        "🏎️",
        "🚓",
        "🚑",
        "🚒",
        "🚐",
        "🛻",
        "🚚",
        "🚛",
        "🚜",
        "🏍️",
        "🛵",
        "🚲",
        "🛴",
        "🛺",
        "🚍",
        "🚔",
        "🚘",
        "✈️",
        "🛫",
        "🛬",
        "🛩️",
        "💺",
        "🚀",
        "🛸",
        "🚁",
        "🛶",
        "⛵",
        "🚤",
        "🛥️",
        "🛳️",
        "⛴️",
        "🚢",
        "🏠",
        "🏡",
        "🏘️",
        "🏚️",
        "🏗️",
        "🏢",
        "🏭",
        "🏬",
        "🏣",
        "🏤",
        "🏥",
        "🏦",
        "🏨",
        "🏪",
        "🏫",
        "🏩",
        "💒",
        "🏛️",
        "⛪",
        "🕌",
        "🕍",
        "🛕",
        "🕋",
        "⛩️",
        "🗾",
        "🎑",
        "🏞️",
        "🌅",
        "🌄",
        "🌠",
        "🎇",
        "🎆",
        "🌇",
        "🌆",
        "🏙️",
        "🌃",
        "🌌",
        "🌉",
      ],
      "Biểu tượng": [
        "⭐",
        "🌟",
        "✨",
        "⚡",
        "🔥",
        "💫",
        "🎉",
        "🎊",
        "🎈",
        "🎁",
        "🎀",
        "🎗️",
        "💯",
        "✅",
        "❌",
        "⭕",
        "🚫",
        "♻️",
        "💲",
        "💱",
        "©️",
        "®️",
        "™️",
        "❗",
        "❓",
        "‼️",
        "⁉️",
        "❕",
        "❔",
        "〰️",
        "💠",
        "🔰",
        "⚜️",
        "♾️",
        "🔱",
        "🏳️",
        "🏴",
        "🏁",
        "🚩",
        "🏳️‍🌈",
        "🏳️‍⚧️",
        "🇻🇳",
      ],
    };

    var emojiBtn = document.querySelector("#emoji-picker");
    if (!emojiBtn) return;

    // Create picker container
    var picker = document.createElement("div");
    picker.id = "emoji-picker-wrapper";
    picker.style.cssText =
      "position:fixed; bottom:100px; right:35px; z-index:99999; display:none; width:320px; max-height:400px; background:#fff; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; font-family:sans-serif;";

    // Category tabs
    var tabBar = document.createElement("div");
    tabBar.style.cssText =
      "display:flex; overflow-x:auto; background:#f5f5f5; padding:6px 4px; gap:2px; border-bottom:1px solid #e0e0e0;";

    // Emoji grid container
    var gridContainer = document.createElement("div");
    gridContainer.style.cssText =
      "padding:8px; overflow-y:auto; max-height:340px;";

    var categories = Object.keys(EMOJIS);
    var activeTab = null;

    function showCategory(catName) {
      gridContainer.innerHTML = "";
      var grid = document.createElement("div");
      grid.style.cssText =
        "display:grid; grid-template-columns:repeat(8, 1fr); gap:2px;";
      EMOJIS[catName].forEach(function (emoji) {
        var span = document.createElement("span");
        span.textContent = emoji;
        span.style.cssText =
          "font-size:22px; cursor:pointer; padding:4px; text-align:center; border-radius:6px; transition:background 0.15s;";
        span.addEventListener("mouseenter", function () {
          span.style.background = "#e8e8e8";
        });
        span.addEventListener("mouseleave", function () {
          span.style.background = "transparent";
        });
        span.addEventListener("click", function () {
          var start = messageInput.selectionStart;
          var end = messageInput.selectionEnd;
          var text = messageInput.value;
          messageInput.value =
            text.substring(0, start) + emoji + text.substring(end);
          messageInput.selectionStart = messageInput.selectionEnd =
            start + emoji.length;
          messageInput.dispatchEvent(new Event("input"));
          messageInput.focus();
        });
        grid.appendChild(span);
      });
      gridContainer.appendChild(grid);

      // Update active tab style
      if (activeTab) activeTab.style.background = "transparent";
      var tabs = tabBar.querySelectorAll("button");
      tabs.forEach(function (t) {
        if (t.dataset.cat === catName) {
          t.style.background = "#fff";
          t.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
          activeTab = t;
        } else {
          t.style.background = "transparent";
          t.style.boxShadow = "none";
        }
      });
    }

    // Build category tabs
    var tabEmojis = {
      "Mặt cười": "😊",
      "Cử chỉ": "👋",
      "Trái tim": "❤️",
      "Động vật": "🐶",
      "Đồ ăn": "🍕",
      "Hoạt động": "⚽",
      "Du lịch": "🚗",
      "Biểu tượng": "⭐",
    };
    categories.forEach(function (cat) {
      var btn = document.createElement("button");
      btn.textContent = tabEmojis[cat] || cat.charAt(0);
      btn.title = cat;
      btn.dataset.cat = cat;
      btn.style.cssText =
        "border:none; background:transparent; font-size:18px; padding:4px 8px; cursor:pointer; border-radius:8px; flex-shrink:0;";
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        showCategory(cat);
      });
      tabBar.appendChild(btn);
    });

    picker.appendChild(tabBar);
    picker.appendChild(gridContainer);

    var closePickerBtn = document.createElement("button");
    closePickerBtn.innerHTML = "×";
    closePickerBtn.title = "Đóng";
    closePickerBtn.style.cssText =
      "position:absolute; right:8px; top:8px; border:none; background:rgba(0,0,0,0.05); width:26px; height:26px; border-radius:50%; font-size:20px; color:#555; cursor:pointer; display:flex; align-items:center; justify-content:center; padding-bottom:2px; transition:0.2s;";
    closePickerBtn.addEventListener(
      "mouseenter",
      () => (closePickerBtn.style.background = "rgba(0,0,0,0.1)"),
    );
    closePickerBtn.addEventListener(
      "mouseleave",
      () => (closePickerBtn.style.background = "rgba(0,0,0,0.05)"),
    );
    closePickerBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      picker.style.display = "none";
    });
    picker.appendChild(closePickerBtn);

    document.body.appendChild(picker);

    // Show first category by default
    showCategory(categories[0]);

    // Toggle picker
    emojiBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      e.preventDefault();
      if (picker.style.display === "none") {
        picker.style.display = "block";
        if (window.innerWidth <= 520) {
          picker.style.right = "5px";
          picker.style.left = "5px";
          picker.style.width = "auto";
          picker.style.bottom = "80px";
        }
      } else {
        picker.style.display = "none";
      }
    });

    // Close on outside click
    document.addEventListener("click", function (e) {
      if (!emojiBtn.contains(e.target) && !picker.contains(e.target)) {
        picker.style.display = "none";
      }
    });

    console.log("Custom emoji picker initialized!");
  })();

  // Dynamic Leaflet Loader
  const loadLeaflet = (callback) => {
    if (window.L) {
      callback();
      return;
    }
    // 1. Load CSS
    if (!document.querySelector("link[href*='leaflet.css']")) {
      const link = document.createElement("link");
      link.rel = "stylesheet";
      link.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
      document.head.appendChild(link);
    }
    // 2. Load JS
    if (!document.querySelector("script[src*='leaflet.js']")) {
      const script = document.createElement("script");
      script.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
      script.onload = () => {
        callback();
      };
      document.head.appendChild(script);
    } else {
      const interval = setInterval(() => {
        if (window.L) {
          clearInterval(interval);
          callback();
        }
      }, 100);
    }
  };

  // Toggle Mini Leaflet Map inside Chat bubble
  window.toggleRoomBubbleMap = (btn, source, id) => {
    const roomKey = `[ROOM:${source}:${id}]`;
    const room = window.chatbotRoomCache && window.chatbotRoomCache[roomKey];
    if (!room || !room.coords) {
      Swal.fire({
        title: "Bản đồ",
        text: "Không tìm thấy thông tin tọa độ cho phòng này.",
        icon: "warning",
        confirmButtonColor: "#3b82f6"
      });
      return;
    }
    
    const cardDiv = btn.closest(".chatbot-room-card");
    if (!cardDiv) return;

    let mapContainer = cardDiv.querySelector(".bubble-map-container");
    if (!mapContainer) {
      mapContainer = document.createElement("div");
      mapContainer.className = "bubble-map-container no-tts";
      mapContainer.style.cssText = "width: 100%; height: 180px; margin-top: 10px; border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1; position: relative;";
      cardDiv.appendChild(mapContainer);
      
      const mapId = `bubble-map-${source}-${id}-${Date.now()}`;
      mapContainer.id = mapId;
      
      loadLeaflet(() => {
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19
        });
        const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
          maxZoom: 19
        });

        const map = L.map(mapId, {
          layers: [osm]
        }).setView([room.coords.lat, room.coords.lng], 15);
        
        // Mini Satellite Style Toggle (No text, just icon/thumbnail toggle)
        const toggleBtn = document.createElement("div");
        toggleBtn.style.cssText = "position: absolute; bottom: 8px; left: 8px; z-index: 1000; width: 38px; height: 38px; border: 2px solid #fff; border-radius: 6px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.3); background: url('https://mt1.google.com/vt/lyrs=y&x=13001&y=7326&z=14') no-repeat center center; background-size: cover; transition: transform 0.2s;";
        toggleBtn.title = "Chuyển sang Bản đồ vệ tinh";
        
        toggleBtn.onmouseenter = () => toggleBtn.style.transform = "scale(1.05)";
        toggleBtn.onmouseleave = () => toggleBtn.style.transform = "scale(1)";
        
        let activeLayer = 'osm';
        toggleBtn.onclick = (e) => {
          e.stopPropagation();
          if (activeLayer === 'osm') {
            map.removeLayer(osm);
            map.addLayer(googleHybrid);
            activeLayer = 'satellite';
            toggleBtn.style.backgroundImage = "url('https://a.tile.openstreetmap.org/14/13001/7326.png')";
            toggleBtn.title = "Chuyển sang Bản đồ thường";
          } else {
            map.removeLayer(googleHybrid);
            map.addLayer(osm);
            activeLayer = 'osm';
            toggleBtn.style.backgroundImage = "url('https://mt1.google.com/vt/lyrs=y&x=13001&y=7326&z=14')";
            toggleBtn.title = "Chuyển sang Bản đồ vệ tinh";
          }
        };
        mapContainer.appendChild(toggleBtn);
        
        const marker = L.marker([room.coords.lat, room.coords.lng]).addTo(map);
        marker.bindPopup(`<b>${room.ten_phong || room.tieude || "Phòng trọ"}</b><br>${room.diachi}`).openPopup();
        
        // Fit current location route if geolocation is allowed
        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(position => {
            const userLat = position.coords.latitude;
            const userLng = position.coords.longitude;
            
            L.marker([userLat, userLng], {
              icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
              })
            }).addTo(map).bindPopup("Vị trí của bạn");
            
            // Draw direct routing line
            L.polyline([[userLat, userLng], [room.coords.lat, room.coords.lng]], {
              color: '#3b82f6',
              weight: 4,
              opacity: 0.8,
              dashArray: '5, 10'
            }).addTo(map);
            
            map.fitBounds([[userLat, userLng], [room.coords.lat, room.coords.lng]], { padding: [20, 20] });
          });
        }
        
        setTimeout(() => map.invalidateSize(), 200);
      });
    } else {
      if (mapContainer.style.display === 'none') {
        mapContainer.style.display = 'block';
      } else {
        mapContainer.style.display = 'none';
      }
    }
    
    // Auto scroll chat body to show the map container
    setTimeout(() => {
      chatBody.scrollTo({ behavior: "smooth", top: chatBody.scrollHeight });
    }, 300);
  };

  // Compare checkboxes handling
  window.handleCompareCheckboxChange = () => {
    const checkedBoxes = document.querySelectorAll(".compare-checkbox:checked");
    const keys = Array.from(checkedBoxes).map(cb => cb.getAttribute("data-room-key"));
    
    let bar = document.getElementById("chatbot-compare-bar");
    if (keys.length === 0) {
      if (bar) bar.style.display = "none";
      return;
    }
    
    if (!bar) {
      bar = document.createElement("div");
      bar.id = "chatbot-compare-bar";
      bar.className = "no-tts";
      bar.style.cssText = `
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: linear-gradient(135deg, #1e293b, #0f172a); color: white;
        padding: 12px 24px; border-radius: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        z-index: 99999; display: flex; align-items: center; gap: 16px;
        font-family: 'Inter', sans-serif; transition: all 0.3s ease;
      `;
      document.body.appendChild(bar);
    }
    
    bar.innerHTML = `
      <span style="font-size: 0.9rem; font-weight: 600;">Đang chọn ${keys.length} phòng để so sánh</span>
      <button onclick="showComparisonPopup(${JSON.stringify(keys).replace(/"/g, '&quot;')})" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 6px 16px; border-radius: 20px; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 10px rgba(16,185,129,0.3);">
        <i class="fas fa-balance-scale"></i> So sánh ngay
      </button>
      <button onclick="clearCompareSelection()" style="background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 0.8rem; text-decoration: underline;">Hủy</button>
    `;
    bar.style.display = "flex";
  };

  window.clearCompareSelection = () => {
    document.querySelectorAll(".compare-checkbox").forEach(cb => cb.checked = false);
    const bar = document.getElementById("chatbot-compare-bar");
    if (bar) bar.style.display = "none";
  };

  window.showComparisonPopup = (roomKeys) => {
    const rooms = roomKeys.map(key => {
      if (window.chatbotRoomCache && window.chatbotRoomCache[key]) {
        return window.chatbotRoomCache[key];
      }
      const card = document.querySelector(`.room-card[data-room-key="${key}"]`);
      if (card) {
        try { return JSON.parse(card.getAttribute("data-room") || "{}"); } catch(e) {}
      }
      return null;
    }).filter(Boolean);

    if (rooms.length < 2) {
      Swal.fire({
        title: "So sánh",
        text: "Vui lòng chọn ít nhất 2 phòng để so sánh.",
        icon: "warning",
        confirmButtonColor: "#10b981"
      });
      return;
    }
    
    let modal = document.getElementById("chatbot-compare-modal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "chatbot-compare-modal";
      modal.className = "no-tts";
      modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); z-index: 100000;
        display: flex; align-items: center; justify-content: center;
        font-family: 'Inter', sans-serif;
      `;
      document.body.appendChild(modal);
    }
    
    let cols = rooms.length;
    let gridCols = `repeat(${cols}, 1fr)`;
    if (window.innerWidth <= 768) {
      gridCols = "1fr";
    }

    let headerHtml = `
      <div style="background: white; border-radius: 16px; width: 92%; max-width: 900px; max-height: 85vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.2); padding: 24px; position: relative; animation: modalFadeIn 0.3s ease;">
        <button onclick="document.getElementById('chatbot-compare-modal').style.display='none'" style="position: absolute; top: 16px; right: 16px; border: none; background: none; font-size: 1.8rem; cursor: pointer; color: #9ca3af; hover: color: #4b5563; font-weight: 300;">&times;</button>
        <h3 style="margin: 0 0 20px 0; font-size: 1.3rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;"><i class="fas fa-balance-scale" style="color: #10b981;"></i> So sánh phòng trọ chi tiết</h3>
        <div style="display: grid; grid-template-columns: ${gridCols}; gap: 20px;">
    `;
    
    let cardsHtml = '';
    rooms.forEach(room => {
      const priceStr = typeof room.gia === 'number' ? room.gia.toLocaleString('vi-VN') + ' VNĐ/tháng' : room.gia;
      const amenities = Array.isArray(room.tiennghi) ? room.tiennghi : (typeof room.tiennghi === 'string' ? room.tiennghi.split(',') : []);
      const img = room.hinhanh || "https://via.placeholder.com/400x300?text=Phong+Tro";
      const title = room.ten_phong || room.tieude || "Phòng trọ";
      const desc = room.mota || "Không có mô tả.";
      
      cardsHtml += `
        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; background: #f8fafc; display: flex; flex-direction: column; gap: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
          <img src="${img}" style="width: 100%; height: 160px; object-fit: cover; border-radius: 8px;" />
          <h4 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e293b; min-height: 44px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${title}</h4>
          <div style="margin-top: auto; display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem;">
            <div style="border-top: 1px solid #e2e8f0; padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
              <span style="color: #64748b; font-weight: 500;">Giá thuê:</span>
              <span style="font-weight: 800; color: #10b981; font-size: 1rem;">${priceStr}</span>
            </div>
            <div style="border-top: 1px dashed #e2e8f0; padding-top: 8px; display: flex; justify-content: space-between;">
              <span style="color: #64748b; font-weight: 500;">Diện tích:</span>
              <span style="font-weight: 700; color: #334155;">${room.dientich} m²</span>
            </div>
            <div style="border-top: 1px dashed #e2e8f0; padding-top: 8px; display: flex; flex-direction: column; gap: 2px;">
              <span style="color: #64748b; font-weight: 500;">Địa chỉ:</span>
              <span style="font-weight: 500; color: #475569; line-height: 1.4;">${room.diachi}</span>
            </div>
            <div style="border-top: 1px dashed #e2e8f0; padding-top: 8px; display: flex; flex-direction: column; gap: 4px;">
              <span style="color: #64748b; font-weight: 500;">Mô tả:</span>
              <span style="color: #64748b; font-size: 0.8rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">${desc}</span>
            </div>
            <div style="border-top: 1px dashed #e2e8f0; padding-top: 8px; display: flex; flex-direction: column; gap: 6px;">
              <span style="color: #64748b; font-weight: 500;">Tiện nghi:</span>
              <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                ${amenities.map(a => `<span style="background: #e2e8f0; color: #334155; font-size: 0.72rem; padding: 3px 8px; border-radius: 6px; font-weight: 600;">${a.trim()}</span>`).join('')}
              </div>
            </div>
          </div>
        </div>
      `;
    });
    
    // Add simple CSS animations
    if (!document.getElementById("compare-modal-animation")) {
      const style = document.createElement("style");
      style.id = "compare-modal-animation";
      style.textContent = `
        @keyframes modalFadeIn {
          from { opacity: 0; transform: scale(0.95); }
          to { opacity: 1; transform: scale(1); }
        }
      `;
      document.head.appendChild(style);
    }
    
    modal.innerHTML = headerHtml + cardsHtml + `</div></div>`;
    modal.style.display = 'flex';
  };
} // End of chatbot guard
