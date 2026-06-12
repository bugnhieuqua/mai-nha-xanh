# BÁO CÁO NGHIỆM THU CHI TIẾT - DỰ ÁN MÁI NHÀ XANH

Báo cáo này tổng hợp đầy đủ các hạng mục nâng cấp, sửa đổi, sửa lỗi và cấu hình triển khai hệ thống phần mềm Mái Nhà Xanh (bao gồm Trợ lý AI Chatbot thông minh và Máy chủ Realtime).

---

## 1. HỆ THỐNG TRỢ LÝ AI CHATBOT (RAG & VISION)

### Thiết lập Persona và Quy tắc phản hồi của "Môi giới ảo":
- Xây dựng nhân vật Trợ lý Môi giới ảo chuyên nghiệp, lịch sự, đĩnh đạc và am hiểu thị trường phòng trọ tại TP. Vinh, Nghệ An.
- Loại bỏ triệt để các ký tự định dạng Markdown dấu sao `*` hoặc `**` khỏi câu trả lời của AI để văn bản hiển thị sạch sẽ, không bị vỡ giao diện trên các trình duyệt cũ.
- Quy định định dạng liệt kê: Toàn bộ thông tin mô tả chi tiết, so sánh, giá cả, và danh sách tiện nghi bắt buộc phải xuống dòng và phân dòng bằng dấu gạch đầu dòng `-`.
- Cú pháp Card tương tác: AI bắt buộc phải chèn thẻ dạng `[ROOM:nguon:id]` (Ví dụ: `[ROOM:phongtro:3]`) vào cuối mô tả để Frontend tự động render thành thẻ phòng trực quan (có ảnh, giá, địa chỉ, bản đồ, nút so sánh).

### Sửa lỗi SQL đếm phòng & Thống kê phòng trọ:
- Sửa lỗi cột `trangthai_phong` trong [api/v2/chatbot_room_stats.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/chatbot_room_stats.php): Bảng `phongtro` chỉ có cột `trangthai` chứ không có cột `trangthai_phong` như bảng `dangbai_chothuetro`. Đã đổi tất cả tham chiếu sang `trangthai` cho bảng `phongtro`.
- Sửa lỗi logic lấy SQL quote khi lọc danh sách phòng theo trạng thái (từ lỗi sử dụng phần tử mảng `$db->quote($filter_status)[1]` sang `$db->quote($filter_status)`).
- Sửa lỗi Fatal Error trong [api/v2/chatbot_room_stats.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/chatbot_room_stats.php) do khai báo trùng hàm `getDB()` với hệ thống bootstrap.
- Cải tiến thuật toán xử lý chuỗi truy vấn: Phân tách rõ ràng câu hỏi chung chung (trả về breakdown chi tiết các loại trạng thái phòng bằng dấu `-`) và câu hỏi riêng biệt về phòng trống (chỉ trả về duy nhất số lượng phòng trống thực tế, tuyệt đối không đưa số liệu phòng cọc vào để tránh gây nhiễu).

### Cơ chế dự phòng API thông minh (AI Multi-Provider Fallback):
- Khắc phục triệt để lỗi HTTP 503 bằng cách thêm Google Gemini (`gemini-2.5-flash`) qua endpoint tương thích OpenAI vào danh sách AI Provider.
- Cải tiến cơ chế gọi API ở cả Round 1 và Round 2 trong [api/v2/assistant_proxy.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/assistant_proxy.php): Nếu nhà cung cấp chính (Groq) trả về lỗi HTTP 429 (Rate Limit do tài khoản Free hết token trong ngày) hoặc OpenAI trả về lỗi hết hạn mức thanh toán, hệ thống sẽ tự động gọi tiếp sang Gemini để hoàn tất cuộc hội thoại.
- Sửa lỗi PHP `getenv()` trả về giá trị `false` thay vì `null` làm gán sai giá trị của tên model AI.

### Phân tích hình ảnh Đa phương thức (Gemini Vision):
- Thiết lập quy trình xử lý hình ảnh tải lên của khách hàng trong [assets/js/assistant.js](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/assets/js/assistant.js): Ảnh được gửi lên qua proxy mô tả ảnh bằng Gemini Vision, sau đó chuyển kết quả dạng text `[Hình ảnh phòng trọ: <Mô tả>]` làm ngữ cảnh đầu vào cho LLM để AI đánh giá nội thất, không gian và gợi ý phòng trọ tương tự.

### Bảo mật & Chống tấn công Prompt Injection:
- Xây dựng server-side firewall trong [api/v2/assistant_proxy.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/assistant_proxy.php) để quét và chặn các hành vi can thiệp hệ thống, jailbreak, yêu cầu tiết lộ System Prompt bằng cách trả về HTTP 400.

---

## 2. CHAT CHUYÊN BIỆT & ĐỒNG BỘ REALTIME (NODE.JS & WEBSOCKET)

### Tách biệt hoàn toàn AI Chatbot và Support Chat:
- Kiến trúc cô lập hoàn toàn phiên trò chuyện của Trợ lý AI và kênh chat hỗ trợ của admin/chủ trọ.
- Trợ lý AI chỉ phản hồi trong phạm vi session được chỉ định, không can thiệp vào các cuộc hội thoại riêng tư giữa khách hàng và tư vấn viên con người.

### Cấu hình deploy động cho Server Realtime Node.js:
- Sửa đổi [backend_realtime/server.js](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/backend_realtime/server.js) để ưu tiên đọc URL API gốc PHP thông qua các biến môi trường của Render (`process.env.PHP_API_BASE` hoặc `process.env.APP_URL`) thay vị chỉ đọc từ tệp cấu hình `.env` cục bộ.

### Đồng bộ hóa cross-host động (Localhost & Production):
- Tích hợp biến `REALTIME_SERVER_URL` trong file `.env` phía PHP và tự động truyền xuống giao diện client thông qua [includes/header.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/includes/header.php).
- Sửa đổi [assets/js/chat-realtime.js](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/assets/js/chat-realtime.js) để kết nối trực tiếp đến địa chỉ WebSocket trên Render khi chạy trên server production, đồng thời tự động nhận diện chạy trên cổng `http://localhost:3000` khi phát triển local.
- Dọn dẹp mã nguồn: Cập nhật file `.gitignore` để chặn việc commit thư mục `node_modules` và xóa toàn bộ các gói thư viện Node.js thừa thãi đang theo dõi trong kho Git.

---

## 3. CÁC TỐI ƯU HÓA FRONTEND UX & HỆ THỐNG THÔNG BÁO

### Tối ưu hóa chuyển văn bản thành giọng nói (TTS) & STT:
- Tăng tốc độ phát âm thanh phản hồi bằng giọng nói tiếng Việt chuẩn cục bộ (Microsoft An / Google Vi) trong [assets/js/assistant.js](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/assets/js/assistant.js) với độ trễ phản hồi dưới 0.1 giây.
- Triển khai thuật toán duy trì kết nối SpeechSynthesis (Heartbeat timer) nhằm khắc phục bug trình duyệt Chrome tự ngắt tiếng khi phát đoạn văn dài.
- Xây dựng canvas sóng âm thanh động mô phỏng trong quá trình kích hoạt chức năng nhận diện giọng nói (Speech-to-Text).

### Hệ thống thông báo đẩy & Sidebar:
- Thiết lập đồng bộ số lượng thông báo chưa đọc hiển thị dạng Badge đỏ trên thanh Sidebar của trang quản trị admin.
- Tích hợp chuông báo âm thanh nhẹ và Popup Toast hiển thị ở góc màn hình khi người dùng nhận được tin nhắn hoặc phản hồi mới.
- Hỗ trợ đầy đủ các tính năng đánh dấu đã đọc hoặc xóa toàn bộ thông báo.

---

## 4. ĐỒNG BỘ VECTOR EMBEDDINGS TRONG HỆ THỐNG
- Sửa lỗi kết nối database và chạy thành công script `php scripts/sync_embeddings.php`.
- Tạo vector index và lưu trữ embeddings cho tất cả **43 phòng trọ** vào bảng `room_embeddings` trong MySQL để phục vụ tính năng tìm kiếm ngữ nghĩa Cosine Similarity.
