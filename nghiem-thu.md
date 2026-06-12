# BÁO CÁO NGHIỆM THU - DỰ ÁN MÁI NHÀ XANH

Tài liệu này tổng hợp toàn bộ các nội dung thay đổi, sửa lỗi và nâng cấp đã được thực hiện trong dự án Mái Nhà Xanh cho hệ thống Chatbot RAG và Server Realtime.

---

## 1. NÂNG CẤP & SỬA LỖI HỆ THỐNG AI CHATBOT (RAG)

### Sửa lỗi kết nối & Khắc phục mã lỗi HTTP 503/500:
- Sửa lỗi truy vấn CSDL trong [api/v2/chatbot_room_stats.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/chatbot_room_stats.php): Thay thế cột không tồn tại `trangthai_phong` thành `trangthai` khi truy vấn bảng `phongtro`, giải quyết triệt để lỗi HTTP 500 phát sinh khi chatbot đếm số lượng phòng.
- Khắc phục lỗi cú pháp SQL trích xuất quote tại bộ lọc trạng thái phòng trong hành động `get_room_list` (thay thế lỗi dùng `$db->quote($filter_status)[1]` bằng việc sử dụng trực tiếp `$db->quote($filter_status)`).
- Sửa lỗi cURL SSL Verification trên môi trường localhost/cá nhân trong [includes/ai_helper.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/includes/ai_helper.php) và [api/v2/assistant_proxy.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/assistant_proxy.php) để ngăn ngừa lỗi không kết nối được tới dịch vụ Embedding.
- Sửa lỗi PHP `getenv()` trả về giá trị `false` thay vì `null` trong [api/v2/assistant_proxy.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/assistant_proxy.php) khiến cấu hình model AI mặc định nhận giá trị sai.

### Cơ chế dự phòng AI Provider nâng cao (Auto-Fallback):
- Triển khai cơ chế dự phòng đa nhà cung cấp ở cả hai vòng gọi (Round 1 và Round 2) trong [api/v2/assistant_proxy.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/assistant_proxy.php): Hệ thống sẽ tự động quét và thử lần lượt qua **Groq → OpenAI → Gemini** nếu nhà cung cấp chính bị quá tải/giới hạn lượt gọi (HTTP 429 Rate Limit) hoặc hết số dư tài khoản.
- Tích hợp thêm **Google Gemini (gemini-2.5-flash)** thông qua cổng kết nối tương thích với định dạng của OpenAI làm nhà cung cấp dự phòng thứ ba, giúp chatbot chạy ổn định 24/7.

### Nâng cấp Prompt thông minh & Định dạng hiển thị:
- Cập nhật hệ thống Prompt cốt lõi trong [config/chatbot_prompt.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/config/chatbot_prompt.php) để xử lý riêng biệt các câu hỏi:
  - **Câu hỏi chung chung** (Ví dụ: *"còn bao nhiêu phòng"*, *"trạng thái phòng"*): Chatbot trả lời đầy đủ số lượng tổng kèm breakdown chi tiết của từng loại trạng thái (trống, cọc) phân dòng bằng dấu `-`.
  - **Câu hỏi riêng biệt về phòng trống** (Ví dụ: *"còn bao nhiêu phòng trống"*, *"phòng trống"*): Chatbot chỉ trả lời duy nhất số lượng phòng trống (Ví dụ: *Dạ hiện tại hệ thống còn 37 phòng trống.*), tuyệt đối không đưa thêm thông tin phòng đã cọc hay đã thuê vào gây loãng.
- Loại bỏ hoàn toàn các ký tự dấu sao `*` trang trí trong văn bản đầu ra của chatbot để hiển thị giao diện chữ thuần sạch sẽ, thoáng mát.
- Sửa lỗi cú pháp dấu nháy kép `"` nằm trong các ví dụ của prompt gây lỗi biên dịch PHP.

### Tối ưu hóa UI Frontend Chatbot:
- Cập nhật [assets/js/assistant.js](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/assets/js/assistant.js) để bóc tách thông báo lỗi chi tiết (`data.message`) do server trả về hiển thị trực tiếp lên khung chat thay vì hiển thị thông tin chung chung `"Lỗi kết nối (HTTP 503)"`.
- Đồng bộ hóa bộ nhớ đệm lịch sử chat giữa `localStorage` và `sessionStorage` trong [includes/chatbot.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/includes/chatbot.php) và [assets/js/assistant.js](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/assets/js/assistant.js).
- Bổ sung nút **"Làm mới chat" 🗑️ (Clear History)** để người dùng xóa bộ nhớ đệm và sửa lỗi kẹt lịch sử hội thoại.

---

## 2. TRIỂN KHAI MÁY CHỦ REALTIME LÊN RENDER

### Cấu hình biến môi trường động cho Server Node.js:
- Cải tiến mã nguồn [backend_realtime/server.js](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/backend_realtime/server.js) để ưu tiên nhận cấu hình `PHP_API_BASE` (URL gốc của ứng dụng web PHP chính) từ biến môi trường hệ thống (`process.env.PHP_API_BASE` hoặc `process.env.APP_URL`) trước khi tìm kiếm trong file `.env` cục bộ. Việc này giúp deploy thành công và kết nối nhanh gọn qua Dashboard của Render.

### Kết nối hai Host (liveblog365 & Render):
- Cấu hình truyền biến môi trường `REALTIME_SERVER_URL` từ file `.env` của PHP xuống Javascript thông qua [includes/header.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/includes/header.php).
- Sửa đổi [assets/js/chat-realtime.js](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/assets/js/chat-realtime.js) để kết nối trực tiếp và động đến link Render (`REALTIME_SERVER_URL`) trên production và tự động fallback về `http://localhost:3000` khi phát triển local mà không cần sửa code tay.
- Sửa đổi tệp [.gitignore](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/.gitignore) để bỏ qua các thư mục `node_modules` phát sinh trong tương lai.
- Loại bỏ hoàn toàn thư mục `node_modules/` khổng lồ đã lỡ commit khỏi Git tracking để tối ưu hóa dung lượng repository.

---

## 3. CƠ SỞ DỮ LIỆU & ĐỒNG BỘ VECTOR
- Giải quyết lỗi Fatal Error do khai báo trùng lặp hàm `getDB()` trong [api/v2/chatbot_room_stats.php](file:///c:/Users/Dai%20Thang/Downloads/mai-nha-xanh/api/v2/chatbot_room_stats.php).
- Thực thi lệnh đồng bộ hóa vector `php scripts/sync_embeddings.php` thành công, lưu trữ toàn bộ các phòng trọ hiện có vào bảng `room_embeddings` để chatbot thực hiện tìm kiếm ngữ nghĩa chính xác.
