# Mái Nhà Xanh — Lộ Trình 3 Tháng Chuẩn Bị Thi Cấp Quốc Gia

> Tài liệu tổng hợp từ: `MaiNhaXanh_LodTrinh_QuocGia.docx`, `Phuong_an_nang_cap.md`, `Bao_cao.md`, và toàn bộ codebase hiện tại.
> **Mục tiêu**: Từ đồ án sinh viên (~4.8/10) → Sản phẩm đủ tầm tranh giải Quốc gia (~8.4/10).

---

## Tổng Quan Hiện Trạng — Điểm Xuất Phát

### Điểm Mạnh (Đã Có)

| Thành phần        | Chi tiết                                                                           |
| ----------------- | ---------------------------------------------------------------------------------- |
| PWA               | Service Worker (`sw.php`), manifest.json chuẩn, install banner, offline page       |
| Push Notification | OneSignal đã tích hợp, phân quyền admin/user qua tags                              |
| AI Chatbot        | Groq Llama 3 + Gemini Vision, có image analysis, voice input (STT), text-to-speech |
| AI Moderation     | Tự động kiểm duyệt bài đăng qua Gemini (`ai_moderation_helper.php`)                |
| AI Autofill       | Tự động điền thông tin phòng từ ảnh (`ai_autofill.php`)                            |
| Chat Summary      | Tổng hợp lịch sử chat theo tháng/quý qua Groq (`chat_summary.php`)                 |
| Admin Dashboard   | Quản lý bài đăng, users, reports, chat với user                                    |
| Cộng đồng         | Post + Comment đa cấp, report nội dung                                             |
| Bảo mật cơ bản    | CSRF token, session fingerprinting, PDO prepared statements, rate limiting         |
| Dark/Light Mode   | View Transition API, CSS variables                                                 |

### Điểm Yếu (Cần Khắc Phục)

| Vấn đề                                                         | Mức độ        |
| -------------------------------------------------------------- | ------------- |
| PHP thuần, không framework                                     | 🔴 Critical   |
| AI chưa có RAG (chatbot không tra cứu DB phòng)                | 🔴 Critical   |
| OneSignal REST API key bị lộ cứng trong `config/onesignal.php` | 🔴 Critical   |
| Database credentials cứng trong `config/database.php`          | 🔴 Critical   |
| Chưa có deployment thực (Docker, CI/CD)                        | 🔴 Critical   |
| Bản đồ tĩnh (iframe Google Maps), chưa có Leaflet tương tác    | 🟠 Cao        |
| Chưa có API layer chuẩn RESTful                                | 🟠 Cao        |
| UX chưa có real-time (WebSocket), SPA transitions              | 🟡 Trung bình |
| Chưa có architecture diagram, API docs                         | 🟡 Trung bình |
| Chưa có unit/integration test                                  | 🟢 Thấp       |

---

## Lộ Trình 3 Tháng — Chi Tiết Từng Tuần

```
THÁNG 1: NÂNG NỀN KỸ THUẬT
├── Tuần 1 (1-7/6):   Bảo mật + Code cleanup
├── Tuần 2 (8-14/6):  API Layer + RAG Chatbot cơ bản
├── Tuần 3 (15-21/6): Leaflet Map + Deployment thực
└── Tuần 4 (22-28/6): Architecture docs + Dashboard nâng cao

THÁNG 2: ĐỘT PHÁ AI
├── Tuần 5 (29/6-5/7):   Smart Room Matching Engine
├── Tuần 6 (6-12/7):     Sentiment Analysis + Dynamic Pricing
├── Tuần 7 (13-19/7):    Real-time Chat (WebSocket) + UX Polish
└── Tuần 8 (20-26/7):    Test toàn diện + Seed data thực

THÁNG 3: ĐÁNH BÓNG & PITCH
├── Tuần 9 (27/7-2/8):   Slide + Video demo + Tài liệu
├── Tuần 10 (3-9/8):     Stress test + Dry-run demo
└── Tuần 11-12 (10-23/8): Buffer + Final polish
```

---

### THÁNG 1: NÂNG NỀN KỸ THUẬT (Tuần 1-4)

#### Tuần 1 — Bảo Mật & Code Cleanup (Ngày 1-7)

**Task 1.1: Fix lộ credentials (CRITICAL — làm ngay)**

- [ ] Tạo file `.env` thực sự với GEMINI_API_KEY, GROQ_API_KEY, ONESIGNAL_APP_ID, ONESIGNAL_REST_API_KEY, DB_HOST, DB_USER, DB_PASS, DB_NAME
- [ ] Sửa `config/database.php`: đọc credentials từ `$_ENV` thay vì hardcode
- [ ] Sửa `config/onesignal.php`: đọc từ `$_ENV` thay vì define cứng
- [ ] Kiểm tra `.gitignore` đã có `.env`
- [ ] Xóa file `.env` hiện tại khỏi Git index: `git rm --cached .env`
- [ ] Tạo `.env.example` với placeholder values

**Task 1.2: Hardening bảo mật**

- [ ] Thêm CSRF token validate vào TẤT CẢ API POST (hiện chỉ có ở admin và một số API)
- [ ] Thêm rate limiting cho login endpoint (`login.php` → `api/rate_limit.php`)
- [ ] Thêm Content Security Policy header trong `.htaccess`
- [ ] Kiểm tra và sanitize tất cả `$_GET`, `$_POST` input chưa được escape
- [ ] Thêm `password_hash()` / `password_verify()` nếu chưa có ở login/register (kiểm tra `login.php`)

**Task 1.3: Code cleanup**

- [ ] Tạo file `config/bootstrap.php` — load DB, session, env một lần duy nhất
- [ ] Refactor các file page để include bootstrap thay vì require riêng lẻ
- [ ] Chuẩn hóa error handling: tạo `includes/error_handler.php` — không để lộ PHP errors ra ngoài
- [ ] Xóa file test/scratch không cần thiết (giữ lại trong thư mục `scratch/`)

#### Tuần 2 — API Layer + RAG Chatbot (Ngày 8-14)

**Task 2.1: Tạo RESTful API Gateway v2**

- [ ] Tạo `api/v2/rooms.php` — GET danh sách phòng (có filter, sort, pagination)
- [ ] Tạo `api/v2/rooms_search.php` — POST tìm kiếm nâng cao (entity extraction từ text)
- [ ] Tạo `api/v2/auth.php` — POST login, POST logout, GET me
- [ ] Tạo `api/v2/chat.php` — POST gửi tin nhắn chatbot (tách khỏi session-based)
- [ ] Chuẩn hóa response format: `{ success: bool, data: {}, message: string, code: int }`

**Task 2.2: RAG Chatbot — Nâng cấp từ "gọi API đơn thuần" lên "Retrieval-Augmented"**

- [ ] Sửa `api/bot_manager.php`: thêm bước query DB trước khi gọi Groq
- [ ] Flow mới: User input → Extract keywords (dùng Groq function calling) → Query DB với keywords → Inject kết quả vào system prompt → Gọi Groq → Trả lời
- [ ] Cập nhật `config/chatbot_prompt.php` với template RAG system prompt
- [ ] Test: chatbot phải biết phòng nào đang trống, giá bao nhiêu, ở đâu

**Task 2.3: API Documentation**

- [ ] Cài đặt Swagger UI (có thể là file HTML tĩnh với swagger.json)
- [ ] Viết `api/docs/swagger.json` mô tả các endpoint chính
- [ ] Link Swagger UI từ Admin Dashboard

#### Tuần 3 — Leaflet Map + Deployment (Ngày 15-21)

**Task 3.1: Thay thế Google Maps iframe bằng Leaflet tương tác**

- [ ] Thêm Leaflet CSS/JS CDN vào `phong-tro.php`
- [ ] Thay `<iframe>` bằng `<div id="map">`
- [ ] Viết JS: fetch danh sách phòng từ API, vẽ marker cho từng phòng
- [ ] Tích hợp MarkerCluster để gom nhóm khi nhiều phòng
- [ ] Popup khi click marker: hiện card phòng thu nhỏ (ảnh, giá, link chi tiết)
- [ ] Vẽ circle bán kính 1-2km quanh ĐH Kinh Tế Nghệ An
- [ ] Thêm trường `lat`, `lng` vào form đăng bài (`dang-bai.php`) — cho phép click chọn vị trí trên bản đồ nhỏ

**Task 3.2: Live Deployment**

- [ ] **Chọn Railway.app** (khuyến nghị — free tier đủ dùng, hỗ trợ PHP + MySQL)
- [ ] Đăng ký domain: `mainhaxanh.vn` hoặc dùng subdomain miễn phí
- [ ] Setup SSL tự động (Railway có sẵn)
- [ ] Setup MySQL trên Railway, import schema + seed data
- [ ] Cấu hình environment variables trên Railway
- [ ] Test deploy: tất cả page load được, chatbot hoạt động, push notification gửi được
- [ ] Setup UptimeRobot để monitor

#### Tuần 4 — Architecture Docs + Admin Nâng Cao (Ngày 22-28)

**Task 4.1: Vẽ Architecture Diagrams**

- [ ] **System Architecture Diagram** (draw.io): User → Nginx → PHP → MySQL + External APIs (Gemini, Groq, OneSignal, Google Maps)
- [ ] **Database ERD**: 10 bảng với relationships (đã có trong MASTER_REPORT_MNX.md, cần vẽ lại đẹp)
- [ ] **User Flow Diagram**: Search → View → Book → Contact Owner
- [ ] **AI Pipeline Diagram**: User Input → Groq(entity extraction) → DB Query → Context Injection → Groq(response) → User
- [ ] **Deployment Diagram**: Railway infrastructure, domain, SSL, CDN
- [ ] Export tất cả sang PNG, embed vào báo cáo

**Task 4.2: Admin Dashboard Nâng Cao**

- [ ] Thêm Analytics Dashboard: số bài đăng, số user, số booking, chatbot usage
- [ ] Thêm biểu đồ thống kê đơn giản (có thể dùng Chart.js CDN)
- [ ] Tích hợp AI Moderation vào UI admin (hiển thị AI score + verdict trong danh sách chờ duyệt)

---

### THÁNG 2: ĐỘT PHÁ AI (Tuần 5-8)

#### Tuần 5 — Smart Room Matching (Ngày 29/6-5/7)

**Đây là tính năng "wow factor" chính cho cuộc thi.**

- [ ] Tạo `api/v2/smart_match.php`:
  - Input: câu hỏi tự nhiên từ user (vd: "Cần phòng gần ĐH Kinh Tế Nghệ An, dưới 2tr, có máy lạnh, cho nuôi mèo")
  - Bước 1: Gọi Groq function calling để extract entities: `{ location, max_price, amenities, pet_friendly }`
  - Bước 2: Query DB với các filter đã extract
  - Bước 3: Tính similarity score cho từng phòng dựa trên mức độ khớp
  - Bước 4: Trả về top 3-5 phòng, kèm giải thích tại sao phù hợp
- [ ] Tích hợp vào chatbot: khi user hỏi tìm phòng, tự động trigger smart_match thay vì trả lời chung chung
- [ ] Hiển thị kết quả dạng Room Cards trong chat (đã có cơ chế `[ROOM:nguon:id]`)
- [ ] Thêm nút "So sánh" — user chọn 2-3 phòng, hệ thống tạo bảng so sánh tiện ích/giá/vị trí

#### Tuần 6 — Sentiment Analysis + Dynamic Pricing (Ngày 6-12/7)

**Task 6.1: Sentiment Analysis cho Reviews**

- [ ] Tạo `api/v2/sentiment_analysis.php`:
  - Nhận text review, gọi Groq API để phân loại: positive/negative/neutral
  - Trả về sentiment score (0-100) + key topics
- [ ] Tích hợp vào phần đánh giá phòng: mỗi review mới tự động chạy sentiment
- [ ] Tạo dashboard sentiment cho chủ nhà: biểu đồ sentiment theo thời gian
- [ ] Auto-flag review tiêu cực để admin kiểm duyệt

**Task 6.2: Dynamic Pricing Suggestion**

- [ ] Tạo `api/v2/price_suggestion.php`:
  - Input: diện tích, địa chỉ, tiện nghi
  - Query DB tìm phòng tương tự trong bán kính 2km
  - Tính giá trung bình, min, max → gợi ý khoảng giá tối ưu
- [ ] Tích hợp vào form đăng bài: sau khi điền thông tin, hiển thị "Giá đề xuất: 2.2-2.5tr/tháng"
- [ ] Tích hợp vào chatbot: khi chủ nhà hỏi "nên để giá bao nhiêu", trả lời dựa trên dữ liệu thực

#### Tuần 7 — Real-time Chat + UX Polish (Ngày 13-19/7)

**Task 7.1: Nâng cấp Admin-User Chat lên Real-time**

- [ ] **Hiện tại**: Polling mỗi 2 giây (`assistant.js`)
- [ ] **Nâng cấp**: Dùng Server-Sent Events (SSE) hoặc WebSocket
  - Lựa chọn khả thi: **SSE** — PHP có thể implement đơn giản, không cần thư viện ngoài
  - Hoặc dùng **Pusher** (free tier 200k msg/ngày) cho WebSocket managed
- [ ] Tạo `api/sse_chat.php` — streaming endpoint cho real-time message
- [ ] Sửa `assistant.js` thay polling bằng EventSource listener

**Task 7.2: UX Polish**

- [ ] Thêm Skeleton Loaders cho tất cả danh sách (phòng trọ, bài đăng, comment)
- [ ] Thêm Loading States cho tất cả AJAX calls
- [ ] Thêm Empty States đẹp (khi không có kết quả)
- [ ] Thêm Error States (khi API lỗi, hiện UI thân thiện thay vì alert)
- [ ] Page transition animation (fade-in khi chuyển trang)
- [ ] Tối ưu ảnh: thêm lazy loading, chuyển ảnh upload sang WebP
- [ ] Google PageSpeed Insights target > 80

#### Tuần 8 — Test Toàn Diện + Seed Data (Ngày 20-26/7)

**Task 8.1: Seed Database với dữ liệu thực**

- [ ] Thu thập 50-100 phòng trọ thực từ khu vực TP. Vinh, Nghệ An
- [ ] Tạo script `scratch/seed_database.php` để import hàng loạt
- [ ] Tạo 20-30 user ảo để demo community active
- [ ] Tạo sẵn accounts demo: admin, chủ nhà (2-3), người thuê (2-3)

**Task 8.2: Testing**

- [ ] Test chatbot với 30+ câu hỏi đa dạng, log các câu trả lời sai để fix
- [ ] Test toàn bộ user flow: Đăng ký → Đăng nhập → Tìm phòng → Xem chi tiết → Đặt phòng → Chủ nhà nhận thông báo → Admin duyệt bài
- [ ] Test trên mobile thực (Android + iOS)
- [ ] Test trên mạng 3G (dùng Chrome DevTools throttling)
- [ ] Test dark mode trên tất cả trang
- [ ] Test PWA install + offline mode
- [ ] Test push notification thực tế

---

### THÁNG 3: ĐÁNH BÓNG & PITCH (Tuần 9-12)

#### Tuần 9 — Slide, Video, Tài Liệu (Ngày 27/7-2/8)

**Task 9.1: Slide Thuyết Trình (10-15 slides)**

- [ ] Slide 1: Cover — Tên dự án + Logo + Team
- [ ] Slide 2: Problem — "62% sinh viên mất 2-4 tuần tìm phòng" (tìm số liệu thực từ khảo sát)
- [ ] Slide 3: Solution — Mái Nhà Xanh là gì, khác biệt với Phongtro123
- [ ] Slide 4-5: Live Demo screenshots (hoặc video nhúng)
- [ ] Slide 6: Architecture Diagram
- [ ] Slide 7: AI Pipeline — RAG Chatbot + Smart Matching
- [ ] Slide 8: Security Measures
- [ ] Slide 9: Traction — số liệu người dùng beta, feedback
- [ ] Slide 10: Business Model — SaaS cho chủ nhà, mở rộng tỉnh
- [ ] Slide 11: Team — vai trò từng thành viên
- [ ] Slide 12: Roadmap 6 tháng tới
- [ ] Slide 13: Q&A Prepared

**Task 9.2: Video Demo (3-5 phút)**

- [ ] Kịch bản: Giới thiệu → Tìm phòng với chatbot → Smart Matching → Đặt phòng → Admin duyệt → Push Notification
- [ ] Quay màn hình + voiceover (dùng OBS hoặc Screen Recorder)
- [ ] Upload YouTube (unlisted) làm backup

**Task 9.3: Tài Liệu**

- [ ] Cập nhật `Update/Bao_cao.md` với kết quả mới
- [ ] Viết API Documentation (Swagger JSON)
- [ ] Viết User Manual (PDF) — hướng dẫn cho cả người thuê và chủ nhà
- [ ] Cập nhật GitHub README: badges, screenshots, setup guide, live demo link

#### Tuần 10 — Stress Test + Dry-run (Ngày 3-9/8)

- [ ] **Dry-run demo**: Diễn tập toàn bộ bài pitch trước bạn bè/giảng viên
- [ ] Ghi hình buổi dry-run, xem lại để cải thiện
- [ ] Fix tất cả lỗi phát sinh trong quá trình dry-run
- [ ] Stress test: 10+ người dùng cùng lúc (nếu có thể)
- [ ] Chuẩn bị backup plan: video demo offline nếu internet chết
- [ ] In checklist, dán lên tường

#### Tuần 11-12 — Buffer + Final Polish (Ngày 10-23/8)

- [ ] Thời gian dự phòng cho các task chưa hoàn thành
- [ ] Final review toàn bộ hệ thống
- [ ] Đảm bảo tất cả links, buttons, forms hoạt động
- [ ] Kiểm tra lại toàn bộ checklist (xem Section Checklist bên dưới)
- [ ] Nộp bài dự thi

---

## Checklist Trước Ngày Nộp

### Kỹ Thuật & Code

- [ ] `.env` đã xóa khỏi git, `.gitignore` đã cập nhật
- [ ] Tất cả API keys đọc từ environment variables
- [ ] Tất cả form có CSRF protection
- [ ] Prepared statements cho mọi DB query (không có SQL injection)
- [ ] Rate limiting trên login/register/chatbot
- [ ] Content Security Policy header đã cấu hình
- [ ] API endpoints trả về JSON chuẩn
- [ ] RAG Chatbot hoạt động với dữ liệu DB thực
- [ ] Smart Room Matching hoàn chỉnh
- [ ] Leaflet Map hiển thị marker + clustering
- [ ] Mobile responsive test trên thiết bị thực
- [ ] PageSpeed > 80 điểm
- [ ] Dark mode hoạt động trên tất cả trang

### Deployment & Infrastructure

- [ ] Dự án live trên server thực (Railway/VPS)
- [ ] Domain riêng đã trỏ về server
- [ ] SSL/HTTPS hoạt động
- [ ] UptimeRobot monitoring đang chạy
- [ ] Database seed với 50+ phòng thực tế
- [ ] Account demo đã tạo sẵn (admin, chủ nhà, người thuê)

### AI & Tính Năng

- [ ] Smart Matching trả về kết quả chính xác, có giải thích
- [ ] Sentiment Analysis hoạt động cho reviews
- [ ] Dynamic Pricing đưa ra gợi ý có cơ sở
- [ ] Admin-User chat real-time (SSE hoặc WebSocket)
- [ ] AI Moderation hiển thị score trong admin
- [ ] Chatbot đã test 30+ câu hỏi, xử lý edge cases

### Tài Liệu

- [ ] Architecture diagram (system + deployment + DB ERD)
- [ ] Báo cáo kỹ thuật cập nhật (có kết quả test, security analysis)
- [ ] API documentation (Swagger)
- [ ] Slide thuyết trình 10-15 trang
- [ ] Video demo 3-5 phút đã upload
- [ ] GitHub README professional
- [ ] User Manual (PDF)

### Dữ Liệu & Validation

- [ ] Có ít nhất 20 beta user thực
- [ ] Có feedback/quote từ người dùng thực
- [ ] Analytics: số liệu sử dụng thực tế
- [ ] Số liệu thống kê cho slide (số phòng, số user, số booking...)

---

## Bảng Kỹ Năng Cần Học (Theo Thứ Tự Ưu Tiên)

| Kỹ năng / Công nghệ              | Khi nào học | Thời gian | Tài nguyên                     |
| -------------------------------- | ----------- | --------- | ------------------------------ |
| Git: .gitignore + bảo vệ secrets | Ngay        | 1 giờ     | git-scm.com/docs               |
| PDO Prepared Statements          | Ngay        | 2 giờ     | php.net/manual/en/book.pdo.php |
| Environment Variables (.env)     | Ngay        | 1 giờ     | github.com/vlucas/phpdotenv    |
| Railway.app deployment           | Tuần 1      | 3 giờ     | docs.railway.app               |
| REST API design (PHP)            | Tuần 2      | 1 ngày    | restfulapi.net                 |
| Groq API — function calling      | Tuần 2      | 1 ngày    | console.groq.com/docs          |
| Leaflet.js + MarkerCluster       | Tuần 3      | 4 giờ     | leafletjs.com                  |
| draw.io — Architecture diagrams  | Tuần 4      | 4 giờ     | app.diagrams.net               |
| Swagger UI / OpenAPI             | Tuần 4      | 1 ngày    | swagger.io/docs                |
| Server-Sent Events (PHP)         | Tuần 7      | 4 giờ     | developer.mozilla.org          |
| WebSocket / Pusher               | Tuần 7      | 1 ngày    | pusher.com/docs                |
| Chart.js (Admin Dashboard)       | Tuần 4      | 3 giờ     | chartjs.org                    |

---

## Câu Hỏi Khó Giám Khảo & Cách Trả Lời

### Q: "Tại sao dùng PHP, không dùng framework hiện đại?"

> "Chúng em chọn PHP vì team đã có kinh nghiệm, cho phép tập trung vào giá trị AI thay vì học framework mới trong thời gian ngắn. Hệ thống đã được kiến trúc theo pattern MVC với API layer tách biệt, sẵn sàng migrate sang Laravel hoặc microservices khi scale. Roadmap 6 tháng của chúng em đã có kế hoạch cụ thể cho việc này."

### Q: "AI chatbot chỉ gọi API bên thứ 3, không có gì đặc biệt?"

> "Đúng là chúng em dùng Gemini/Groq làm language model. Nhưng giá trị thực nằm ở RAG pipeline — chúng em inject dữ liệu phòng trọ real-time từ database vào context, kết hợp entity extraction để hiểu intent người dùng, tạo ra trải nghiệm tư vấn cá nhân hóa. Đây là kỹ thuật production-grade mà các ứng dụng như Grab, Airbnb đang dùng."

### Q: "Kế hoạch bảo mật dữ liệu người dùng như thế nào?"

> "Chúng em implement CSRF protection, prepared statements chống SQL injection, rate limiting, HTTPS bắt buộc, và hash mật khẩu với bcrypt. Thông tin thanh toán chúng em không lưu — integrate với cổng thanh toán đã được PCI-DSS certified. Roadmap còn có kế hoạch audit bảo mật định kỳ và GDPR compliance khi mở rộng."

### Q: "Làm sao phân biệt với Phongtro123, myCentRO?"

> "Phongtro123 là listing platform — đăng tin, tìm kiếm thụ động. Mái Nhà Xanh là intelligent assistant — AI tư vấn chủ động, hiểu ngữ cảnh tiếng Việt địa phương, phù hợp với bài toán đặc thù sinh viên vùng. Chúng em không cạnh tranh về số lượng, mà cạnh tranh về quality of matching và user experience."

---

## Cấu Trúc Pitch 15 Phút

| Thời gian   | Nội dung                                                                        |
| ----------- | ------------------------------------------------------------------------------- |
| 0:00-1:30   | **Hook**: "62% sinh viên mất 2-4 tuần tìm phòng trọ" (số liệu thực từ khảo sát) |
| 1:30-3:00   | **Problem**: Thông tin phân tán, không AI tư vấn, chủ nhà thiếu công cụ         |
| 3:00-5:00   | **Solution**: Mái Nhà Xanh — không phải web cho thuê thông thường               |
| 5:00-9:00   | **Live Demo**: Smart Matching + RAG Chatbot — điểm nhấn chính                   |
| 9:00-11:00  | **Technical Depth**: Architecture, AI Pipeline, Security                        |
| 11:00-12:30 | **Traction**: Beta users, feedback, số liệu                                     |
| 12:30-14:00 | **Business**: Mở rộng ra Hà Tĩnh, Thanh Hóa, SaaS cho chủ nhà                   |
| 14:00-15:00 | **Team & Roadmap**: Vai trò, kế hoạch 6 tháng                                   |

---

## Ghi Chú Triển Khai

### Các file hiện có trong codebase cần chú ý:

| File                                | Lưu ý                                                        |
| ----------------------------------- | ------------------------------------------------------------ |
| `config/onesignal.php`              | **API key bị lộ** — cần chuyển sang `.env` ngay              |
| `config/database.php`               | **Credentials cứng** — cần đọc từ `.env`                     |
| `api/bot_manager.php`               | Có sẵn Groq proxy, đã có Base64 encoding chống WAF           |
| `api/ai_autofill.php`               | AI autofill từ ảnh — đã hoàn chỉnh, giữ lại                  |
| `includes/ai_moderation_helper.php` | AI moderation — đã hoàn chỉnh, giữ lại                       |
| `includes/chatbot.php`              | Chatbot UI — cần nâng cấp RAG logic                          |
| `assets/js/assistant.js`            | ~2500 dòng — xử lý chatbot + admin chat + emoji picker + STT |
| `api/community.php`                 | Community API — đã có CRUD đầy đủ                            |
| `api/rate_limit.php`                | Rate limiting — đã có, tái sử dụng                           |
| `config/session.php`                | CSRF token + session fingerprinting — đã có, tốt             |
| `sw.php`                            | Service Worker — PWA caching strategy tốt                    |
| `manifest.json`                     | PWA manifest — đầy đủ                                        |

### Các file mới cần tạo:

| File                            | Mục đích                                  |
| ------------------------------- | ----------------------------------------- |
| `.env.example`                  | Template environment variables            |
| `config/bootstrap.php`          | Unified bootstrap (DB + session + env)    |
| `api/v2/rooms.php`              | RESTful room listing endpoint             |
| `api/v2/rooms_search.php`       | Advanced search with NLP                  |
| `api/v2/auth.php`               | Auth endpoints                            |
| `api/v2/smart_match.php`        | AI-powered room matching                  |
| `api/v2/sentiment_analysis.php` | Review sentiment analysis                 |
| `api/v2/price_suggestion.php`   | Dynamic pricing suggestion                |
| `api/sse_chat.php`              | Server-Sent Events for real-time chat     |
| `api/docs/swagger.json`         | API documentation                         |
| `assets/js/map.js`              | Leaflet map logic (tách từ phong-tro.php) |
| `assets/js/admin-dashboard.js`  | Chart.js analytics                        |
| `scratch/seed_database.php`     | Database seeding script                   |
