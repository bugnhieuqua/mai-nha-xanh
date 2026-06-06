# Mái Nhà Xanh — Kiểm Tra Chéo & Kế Hoạch HIỆU CHỈNH

> **Mục đích**: Đối chiếu từng claim trong các file kế hoạch (`MaiNhaXanh_LodTrinh_QuocGia.docx`, `Phuong_an_nang_cap.md`, `Bao_cao.md`, `project_info__1.md`) với codebase thực tế. Phát hiện cái gì đã có, cái gì chưa có, cái gì các file plan nói sai.

---

## BẢNG ĐỐI CHIẾU: Plan nói gì vs Code thực tế có gì

### 1. BẢN ĐỒ — Leaflet / Google Maps

| Plan nói                                                                | Code thực tế                                                                                                                                                                                                     | Kết luận           |
| ----------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| **Bao_cao.md**: "Bản đồ Leaflet chưa triển khai"                        | **SAI**. `phong-tro.php` ĐÃ CÓ Leaflet đầy đủ: marker cho từng phòng, MarkerCluster, popup card, circle bán kính 1.5km quanh ĐH Kinh Tế Nghệ An, toggle giữa OSM và Google Satellite, đồng bộ với bộ lọc sidebar | **ĐÃ CÓ từ trước** |
| **Phuong_an_nang_cap.md**: "Bản đồ tĩnh (Basic Iframe) chưa tương tác"  | **SAI**. Iframe Google Maps chỉ còn trong footer, không còn trong `phong-tro.php`. Trang phòng trọ dùng Leaflet tương tác hoàn chỉnh                                                                             | **ĐÃ CÓ từ trước** |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "Chưa có clustering"              | **SAI**. MarkerCluster (`leaflet.markercluster`) đã được import và sử dụng trong `phong-tro.php`                                                                                                                 | **ĐÃ CÓ**          |
| **project_info\_\_1.md** Tuần 3: "Thay Google Maps iframe bằng Leaflet" | **KHÔNG CẦN**. Đã làm xong                                                                                                                                                                                       | **BỎ task này**    |

### 2. AI — Autofill / Moderation / Chatbot

| Plan nói                                                                     | Code thực tế                                                                                                                                                                                                                                                                                                                         | Kết luận                              |
| ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------- |
| **Bao_cao.md**: "Chatbot & AI chưa triển khai", "chưa có endpoint API"       | **SAI nghiêm trọng**. `api/ai_autofill.php` đã hoàn chỉnh (Gemini Vision → JSON điền form). `api/chatbot_image_handler.php` đã hoàn chỉnh (phân tích ảnh từ chatbot). `api/bot_manager.php` đã hoàn chỉnh (Groq proxy). `includes/ai_moderation_helper.php` đã hoàn chỉnh. Admin `posts.php` đã hiển thị AI score + verdict trong UI | **TẤT CẢ ĐÃ CÓ**                      |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "AI chatbot chỉ gọi Gemini API cơ bản" | **Đúng nhưng không đầy đủ**. Chatbot dùng Groq (qua `bot_manager.php`), có system prompt động nhúng danh sách phòng thực từ DB (RAG đơn giản đã có trong `footer.php`), có image analysis pipeline riêng                                                                                                                             | **Đã có RAG cơ bản**                  |
| **Phuong_an_nang_cap.md**: Component 2 "AI Autofill chưa triển khai"         | **SAI**. `api/ai_autofill.php` + nút "Điền nhanh bằng AI ✨" trong `dang-bai.php` đã hoàn chỉnh. Có cả prompt AI tự động popup sau khi upload ảnh                                                                                                                                                                                    | **ĐÃ CÓ**                             |
| **Phuong_an_nang_cap.md**: Component 3 "Chatbot chưa có Room Cards"          | **SAI**. `assistant.js` đã có `linkifyResponse()` parse `[ROOM:nguon:id]` → HTML card với ảnh, giá, nút chi tiết. System prompt đã hướng dẫn AI dùng syntax này                                                                                                                                                                      | **ĐÃ CÓ**                             |
| **Phuong_an_nang_cap.md**: Component 4 "AI moderation chưa triển khai"       | **SAI**. `ai_moderation_helper.php` + `admin/posts.php` đã hiển thị AI score/verdict/reasons. Có nút "Kiểm duyệt AI" cho từng bài. Có `analyzePostWithAI()` gọi Gemini, lưu vào cột `ai_check`                                                                                                                                       | **ĐÃ CÓ**                             |
| **project_info\_\_1.md** Tuần 5: "Smart Room Matching Engine"                | **Đúng là chưa có entity extraction + similarity scoring**. Nhưng RAG cơ bản (inject DB rooms vào system prompt) đã có trong `footer.php`                                                                                                                                                                                            | **Cần nâng cấp, không phải làm từ 0** |

### 3. UI/UX — Dark Mode / PWA / Real-time

| Plan nói                                                                     | Code thực tế                                                                                                                                                         | Kết luận         |
| ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| **Bao_cao.md**: "Dark mode chữ mờ, khó đọc"                                  | **SAI (đã fix)**. `style.css` có `[data-theme="dark"]` với đầy đủ color variables, contrast đã được tăng cường. `header.php` có View Transition API cho theme switch | **ĐÃ CÓ**        |
| **Bao_cao.md**: "Modal mobile height:100vh → nội dung bị cắt"                | **SAI (đã fix)**. Style.css có rule `max-height:85vh; overflow-y:auto;` cho mobile modals                                                                            | **ĐÃ FIX**       |
| **Bao_cao.md**: "Thiếu PWA manifest & service worker"                        | **SAI**. `manifest.json` chuẩn, `sw.php` với caching strategy, install banner trong `header.php`, PWA install prompt với SweetAlert                                  | **ĐÃ CÓ ĐẦY ĐỦ** |
| **Bao_cao.md**: "Thiếu SEO: title, meta description, alt"                    | **SAI**. `header.php` có meta tags động với Open Graph, Twitter Card, Google verification. `phong-tro.php` có alt cho ảnh. `sitemap.php` đã có                       | **ĐÃ CÓ**        |
| **Phuong_an_nang_cap.md**: Component 5 "Dark mode + micro-animation chưa có" | **SAI**. Đã có View Transition API circular reveal, CSS variables dark mode, float animations, typing effect, scroll gallery                                         | **ĐÃ CÓ**        |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "Chưa có PWA"                          | **SAI**. PWA hoàn chỉnh                                                                                                                                              | **ĐÃ CÓ**        |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "Chưa có real-time"                    | **Đúng**. Admin chat vẫn polling 2 giây. Chưa có WebSocket/SSE                                                                                                       | **CẦN LÀM**      |
| **project_info\_\_1.md** Tuần 7: "Nâng cấp Admin-User Chat lên Real-time"    | **Đúng, cần làm**. SSE là lựa chọn khả thi                                                                                                                           | **GIỮ task này** |

### 4. Bảo Mật

| Plan nói                                                    | Code thực tế                                                                                                                                                             | Kết luận               |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------- |
| **Bao_cao.md**: "Thiếu CSRF token"                          | **SAI**. `config/session.php` có `validateCsrfToken()`, `login.php` có CSRF hidden input, admin APIs có CSRF check. Nhưng chưa đồng nhất tất cả endpoints                | **ĐÃ CÓ, cần mở rộng** |
| **Bao_cao.md**: "API key phải lấy từ .env"                  | **Đúng 1 phần**. `env_loader.php` đã có, `ai_autofill.php`, `bot_manager.php` đọc từ `$_ENV`. NHƯNG `config/onesignal.php` và `config/database.php` vẫn hardcode         | **CẦN FIX GẤP**        |
| **Bao_cao.md**: "Thiếu CSP"                                 | **Đúng**. Chưa có Content Security Policy header                                                                                                                         | **CẦN LÀM**            |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "Thiếu rate limiting" | **SAI**. `api/rate_limit.php` đã có, được dùng trong `bot_manager.php`, `ai_autofill.php`, `chat_summary.php`, `chatbot_image_handler.php`. Nhưng chưa áp dụng cho login | **ĐÃ CÓ, cần mở rộng** |

### 5. API Layer

| Plan nói                                                              | Code thực tế                                                                                      | Kết luận                       |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- | ------------------------------ |
| **Phuong_an_nang_cap.md**: "Chưa có API Gateway"                      | **Đúng 1 phần**. `api/gateway.php` đã có nhưng chỉ gom một vài action. Chưa có `/api/v2/` RESTful | **Cần nâng cấp**               |
| **Bao_cao.md**: "Chưa có kiến trúc MVC/ORM"                           | **Đúng**. PHP procedural, không có routing                                                        | **Đúng, nhưng không critical** |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "Cần tạo RESTful API endpoints" | **Đúng**. Hiện tại API còn rời rạc, thiếu chuẩn hóa response format                               | **CẦN LÀM**                    |

### 6. Deployment

| Plan nói                                                        | Code thực tế                                                                                                     | Kết luận                           |
| --------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- | ---------------------------------- |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "Chưa có deployment thực" | **Đúng và sai**. `MASTER_REPORT_MNX.md` nói dự án đã deploy trên `liveblog365.com`. Nhưng không có Docker, CI/CD | **Đã có host thực, cần Docker/CI** |
| Tất cả plan: "Cần deployment"                                   | **Đã deploy trên shared hosting**. Cần nâng lên Railway/VPS cho chuyên nghiệp                                    | **CẦN LÀM**                        |

### 7. Các tính năng khác

| Plan nói                                                                | Code thực tế                                                                                                         | Kết luận                    |
| ----------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- | --------------------------- |
| **Bao_cao.md**: "Thiếu schema.org"                                      | **Đúng**. Chưa có structured data                                                                                    | **CẦN LÀM**                 |
| **Bao_cao.md**: "Ảnh chưa nén, chưa WebP"                               | **Đúng**. Upload ảnh giữ nguyên format                                                                               | **CẦN LÀM**                 |
| **Bao_cao.md**: "Chưa có unit/integration test"                         | **Đúng**                                                                                                             | **CẦN LÀM (thấp priority)** |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "Chưa có notification system"     | **SAI**. `notifications.js` có polling 4s, toast popup, badge. OneSignal đã tích hợp. Cả DB notifications + Web Push | **ĐÃ CÓ**                   |
| **MaiNhaXanh_LodTrinh_QuocGia.docx**: "Chưa có Google Maps integration" | **SAI**. Leaflet map có Google Satellite layer + Google Maps direction links cho từng phòng                          | **ĐÃ CÓ**                   |

---

## TÓM TẮT: CÁI GÌ ĐÃ CÓ — CÁI GÌ CẦN LÀM

### ĐÃ CÓ (bị các file plan nói sai là chưa có)

1. ✅ **Leaflet Map** tương tác với MarkerCluster, popup, toggle vệ tinh, circle bán kính
2. ✅ **AI Autofill** hoàn chỉnh (Gemini Vision → form)
3. ✅ **AI Moderation** hoàn chỉnh (score, verdict, reasons trong admin)
4. ✅ **Chatbot Room Cards** (`[ROOM:nguon:id]` → HTML card)
5. ✅ **Dark Mode** với View Transition API, CSS variables
6. ✅ **PWA** đầy đủ (manifest, SW, install banner)
7. ✅ **Notification System** (polling, toast, badge, OneSignal push)
8. ✅ **RAG cơ bản** (system prompt nhúng danh sách phòng thực từ DB)
9. ✅ **SEO cơ bản** (meta tags, OG, Twitter Card, sitemap)
10. ✅ **Rate Limiting** (file-based, dùng cho chatbot/API)
11. ✅ **CSRF Protection** (có ở login và admin APIs)
12. ✅ **Google Maps direction links** cho từng phòng
13. ✅ **Voice Input (STT)** + Text-to-Speech trong chatbot
14. ✅ **Emoji Picker** custom trong chatbot
15. ✅ **Chat History Summary** theo tháng/quý qua Groq
16. ✅ **Community** với post/comment đa cấp, report, media upload
17. ✅ **Booking flow** với đặt phòng, đổi trạng thái, notification
18. ✅ **Google Login** tích hợp

### CẦN LÀM (thực sự chưa có)

#### 🔴 CRITICAL — Làm ngay

1. ❌ **Fix lộ credentials**: `config/onesignal.php` (REST API key cứng), `config/database.php` (root/2310 cứng)
2. ❌ **Xóa `.env` khỏi Git** (hiện có file `.env` trong project root)
3. ❌ **Tạo `.env.example`** với placeholder values
4. ❌ **Thêm CSRF vào tất cả API POST** còn thiếu (đặc biệt `api/dat_phong.php` đã có, nhưng các API khác thì không đồng nhất)
5. ❌ **Thêm rate limiting cho login endpoint**

#### 🟠 CAO — Tuần 1-2

6. ❌ **RAG Chatbot nâng cao**: Thay vì chỉ inject toàn bộ danh sách phòng vào prompt, cần entity extraction + similarity scoring (Smart Matching)
7. ❌ **Chuẩn hóa API Gateway v2** với response format thống nhất
8. ❌ **CSP Header** trong `.htaccess`
9. ❌ **Deployment lên Railway/VPS** (đang trên shared hosting)
10. ❌ **Architecture Diagrams** (system, DB ERD, AI pipeline, deployment) — vẽ bằng draw.io

#### 🟡 TRUNG BÌNH — Tuần 3-4

11. ❌ **Real-time Chat** (SSE thay polling)
12. ❌ **Sentiment Analysis** cho reviews
13. ❌ **Dynamic Pricing Suggestion**
14. ❌ **Admin Analytics Dashboard** với Chart.js
15. ❌ **API Documentation** (Swagger)
16. ❌ **Image optimization** (WebP conversion, lazy loading)
17. ❌ **Skeleton Loaders** + Empty States + Error States

#### 🟢 THẤP — Tuần 5+

18. ❌ **Docker + CI/CD**
19. ❌ **Unit/Integration Tests**
20. ❌ **Schema.org structured data**

---

## KẾ HOẠCH HIỆU CHỈNH — 10 TUẦN

Dựa trên phân tích trên, đây là timeline thực tế, đã loại bỏ những task đã làm xong:

### TUẦN 1: Bảo mật + Nền tảng (Critical fixes)

- [ ] **Ngày 1**: Fix `config/database.php` đọc từ `$_ENV`
- [ ] **Ngày 1**: Fix `config/onesignal.php` đọc từ `$_ENV`
- [ ] **Ngày 1**: Tạo `.env.example`, xóa `.env` khỏi Git index
- [ ] **Ngày 2**: Thêm CSP header vào `.htaccess`
- [ ] **Ngày 2**: Thêm rate limiting cho `login.php`
- [ ] **Ngày 3**: Audit và thêm CSRF vào tất cả API POST còn thiếu
- [ ] **Ngày 4-5**: Tạo `config/bootstrap.php` — unified DB+session+env loader
- [ ] **Ngày 6-7**: Refactor các page dùng bootstrap thay vì require riêng lẻ

### TUẦN 2: API Gateway v2 + Smart Matching cơ bản

- [ ] Tạo `api/v2/rooms.php` — GET/POST rooms với filter, pagination
- [ ] Tạo `api/v2/rooms_search.php` — entity extraction bằng Groq function calling → query DB
- [ ] Tạo `api/v2/auth.php` — login, logout, me
- [ ] Chuẩn hóa response format: `{ success, data, message, code }`
- [ ] **Smart Room Matching v1**: Sửa `api/bot_manager.php` để gọi entity extraction trước khi query DB

### TUẦN 3: Deployment + Architecture Docs

- [ ] Deploy lên Railway.app (PHP + MySQL)
- [ ] Đăng ký domain (mainhaxanh.vn hoặc dùng subdomain)
- [ ] Setup SSL, environment variables trên Railway
- [ ] Vẽ System Architecture Diagram (draw.io)
- [ ] Vẽ Database ERD
- [ ] Vẽ AI Pipeline Diagram
- [ ] Vẽ Deployment Diagram

### TUẦN 4: Smart Matching nâng cao + Admin Dashboard

- [ ] **Smart Room Matching v2**: Similarity scoring (giá, diện tích, vị trí, tiện nghi)
- [ ] Tích hợp smart matching vào chatbot flow
- [ ] Admin Dashboard: Chart.js analytics (số bài, user, booking)
- [ ] Swagger API docs (`api/docs/swagger.json`)

### TUẦN 5: Sentiment Analysis + Dynamic Pricing

- [ ] `api/v2/sentiment_analysis.php` — Groq-based sentiment
- [ ] Tích hợp sentiment vào review flow
- [ ] `api/v2/price_suggestion.php` — query DB phòng tương tự → gợi ý giá
- [ ] Tích hợp vào form đăng bài + chatbot

### TUẦN 6: Real-time Chat + UX Polish

- [ ] `api/sse_chat.php` — Server-Sent Events cho admin chat
- [ ] Sửa `assistant.js` thay polling bằng EventSource
- [ ] Skeleton Loaders cho danh sách
- [ ] Empty States + Error States
- [ ] PageSpeed optimization (ảnh WebP, lazy load)
- [ ] Google PageSpeed Insights target > 80

### TUẦN 7: Seed Data + Testing

- [ ] Seed 50-100 phòng thực từ TP. Vinh
- [ ] Tạo accounts demo: admin, chủ nhà x3, người thuê x3
- [ ] Test chatbot 30+ câu hỏi
- [ ] Test toàn bộ user flow
- [ ] Test mobile thực (Android + iOS)
- [ ] Test PWA install + offline
- [ ] Test push notification

### TUẦN 8: Slide + Video + Tài liệu

- [ ] Slide thuyết trình 10-15 slides
- [ ] Video demo 3-5 phút (OBS + voiceover)
- [ ] Cập nhật báo cáo kỹ thuật
- [ ] Viết User Manual PDF
- [ ] Cập nhật GitHub README

### TUẦN 9: Dry-run + Stress test

- [ ] Diễn tập pitch trước bạn bè/giảng viên
- [ ] Fix lỗi phát sinh
- [ ] Stress test (10+ concurrent users nếu có thể)
- [ ] Backup plan: video offline nếu internet chết

### TUẦN 10: Buffer + Final Polish

- [ ] Thời gian dự phòng
- [ ] Final review
- [ ] Nộp bài

---

## GHI CHÚ: Các file không cần đụng đến (đã hoàn chỉnh)

| File                                | Lý do                                              |
| ----------------------------------- | -------------------------------------------------- |
| `api/ai_autofill.php`               | AI autofill hoàn chỉnh                             |
| `includes/ai_moderation_helper.php` | AI moderation hoàn chỉnh                           |
| `api/chatbot_image_handler.php`     | Image analysis hoàn chỉnh                          |
| `api/chat_summary.php`              | Chat summary hoàn chỉnh                            |
| `assets/js/notifications.js`        | Notification system hoàn chỉnh                     |
| `config/session.php`                | CSRF + session fingerprinting tốt                  |
| `config/chatbot_prompt.php`         | System prompt template tốt                         |
| `api/rate_limit.php`                | Rate limiting hoàn chỉnh (cần áp dụng thêm)        |
| `api/community.php`                 | Community CRUD hoàn chỉnh                          |
| `sw.php`                            | Service Worker tốt                                 |
| `manifest.json`                     | PWA manifest chuẩn                                 |
| `assets/js/assistant.js`            | Chatbot UI hoàn chỉnh (chỉ cần nâng cấp RAG + SSE) |
| `phong-tro.php`                     | Leaflet map hoàn chỉnh                             |
| `admin/posts.php`                   | Admin moderation UI hoàn chỉnh                     |

## GHI CHÚ: Các file cần sửa

| File                   | Cần sửa gì                                        |
| ---------------------- | ------------------------------------------------- |
| `config/onesignal.php` | Chuyển credentials sang `$_ENV`                   |
| `config/database.php`  | Chuyển credentials sang `$_ENV`                   |
| `api/bot_manager.php`  | Thêm entity extraction + DB query trước Groq call |
| `.htaccess`            | Thêm CSP header                                   |
| `login.php`            | Thêm rate limiting                                |
| `dang-bai.php`         | Thêm trường lat/lng (map picker)                  |
