# Mái Nhà Xanh — Project Info Tổng Hợp (project_info\_\_3.md)

Tài liệu này tổng hợp **đối chiếu những gì đã có / những gì còn thiếu** để dự thi **Kỳ thi Quốc gia**. Nội dung dựa trên:

- `project_info__1.md`
- `project_info__2.md`
- Codebase hiện tại trong thư mục dự án `mai-nha-xanh`.

---

## 1) Mục tiêu dự thi Quốc gia (đích cần đạt)

Sản phẩm cần thể hiện rõ 3 trụ cột:

1. **Tính hoàn thiện kỹ thuật** (bảo mật, hiệu năng, kiến trúc, tài liệu, triển khai được).
2. **Trải nghiệm người dùng hiện đại** (UI/UX, PWA, thao tác nhanh, tương tác tốt).
3. **Giá trị “AI có ích”** (không chỉ demo chat: có RAG/tra cứu dữ liệu, có pipeline, có tác động thực tới flow).

---

## 2) Tóm tắt hiện trạng: Đã có gì nổi bật

### 2.1 Frontend / UX

- **PWA đầy đủ**: `manifest.json`, `sw.php`, cài đặt & offline cache.
- **Dark/Light Mode**: dùng CSS variables + View Transition API.
- **UI/UX cải tiến**: modal có xử lý mobile overflow/scroll tốt (không còn lỗi `height:100vh` gây cắt nội dung).
- **Notification system**: toast/badge + OneSignal push.
- **Chat UI** có nhiều tiện ích: STT (voice input), text-to-speech (TTS), emoji picker.

### 2.2 Bản đồ / Địa điểm

- **Leaflet map tương tác** trên trang phòng trọ (`phong-tro.php`).
- Có **MarkerCluster**, popup hiển thị card phòng, và **circle bán kính quanh ĐH Vinh**.
- Có toggle hiển thị lớp bản đồ (OSM / satellite) và liên kết hướng dẫn (directions links) cho từng phòng.

### 2.3 AI / Moderation / Autofill / Chatbot

- **AI Autofill** đã hoàn thiện: `api/ai_autofill.php` (Gemini Vision → trả JSON điền form).
- **AI Moderation** đã hoàn thiện: `includes/ai_moderation_helper.php` + tích hợp UI admin (`admin/posts.php`) hiển thị score/verdict/reason.
- **Chatbot** đã có pipeline và UI Room Cards:
  - Token/cú pháp phòng dạng `[ROOM:nguon:id]`.
  - Frontend parse và render thành **Room Card** trong khung chat.
- **Chat Summary** đã có (tổng hợp lịch sử chat theo tháng/quý qua Groq): `api/chat_summary.php`.

### 2.4 Bảo mật nền tảng và cơ chế vận hành

- Có **CSRF token** trong một phần luồng (login + admin APIs).
- Có **session fingerprinting**.
- Có **PDO prepared statements** (theo mô tả ở project_info**1 / project_info**2 và các điểm nhấn bảo mật).
- Có **rate limiting** dùng qua `api/rate_limit.php` cho một số luồng AI/chatbot.

---

## 3) Những điểm còn thiếu / cần nâng cấp để “đủ tầm”

### 3.1 Critical (phải làm ngay)

1. **Lộ credentials (API key + DB password)**
   - `config/onesignal.php`: REST API key đang bị hardcode.
   - `config/database.php`: credentials DB đang bị hardcode.
   - Yêu cầu: chuyển sang **environment variables** (.env).

2. **Xóa `.env` khỏi git index**
   - Có file `.env` ở project root.
   - Cần đảm bảo secrets **không commit**.

3. **Tạo `.env.example`**
   - Cung cấp placeholder để triển khai nhanh.

4. **Chuẩn hóa CSRF cho toàn bộ API POST**
   - Một số endpoint có thể chưa đồng nhất.
   - Mục tiêu: mọi API POST đều enforce CSRF.

5. **Rate limiting cho login endpoint**
   - Tránh brute-force vào `login.php`.

### 3.2 Cao (cần làm trong giai đoạn đầu để có “wow” và chất lượng)

1. **Nâng cấp RAG/Smart Matching**
   - Hiện tại có **RAG cơ bản** (inject danh sách phòng real data trong prompt).
   - Cần nâng cấp thành **entity extraction + similarity scoring** để trả kết quả phù hợp hơn với câu hỏi tự nhiên.
   - Ví dụ: “dưới 2tr, có máy lạnh, gần ĐH Vinh” → trích entity → query DB → chấm điểm → top phòng.

2. **Chuẩn hóa API layer / RESTful API v2**
   - Dự án đang có nhiều endpoint rời rạc.
   - Mục tiêu: có `/api/v2/*` và format response thống nhất.

3. **CSP header**
   - Chưa có/ chưa đầy đủ Content Security Policy trong `.htaccess`.

4. **Deployment & hạ tầng chuyên nghiệp**
   - Hiện tại có host thực nhưng cần nâng lên phương án chuyên nghiệp (Railway/VPS) + setup env + SSL + monitoring.

5. **Architecture diagrams + tài liệu hóa**
   - Cần diagram hệ thống, DB ERD, AI pipeline, deployment.

### 3.3 Trung bình (giúp tăng điểm hoàn thiện)

- **Real-time chat** (SSE/WebSocket) thay polling.
- **Sentiment analysis cho review**.
- **Dynamic pricing suggestion**.
- **Admin analytics dashboard** (Chart.js).
- **Swagger/OpenAPI docs**.
- **Image optimization** (lazy-load, WebP conversion… nếu có pipeline).
- **Skeleton / Empty / Error states** cho trải nghiệm.

### 3.4 Thấp (nice-to-have, làm sau)

- Docker + CI/CD.
- Unit/Integration tests.
- Schema.org structured data.

---

## 4) Bản đồ đối chiếu “Plan nói gì vs Code thực tế” (tổng hợp)

### 4.1 Leaflet / Map

- Plan nói thiếu Leaflet/Cluster → thực tế đã có.
- Task “thay Google Maps bằng Leaflet” → thực tế đã làm.

### 4.2 AI

- AI Autofill / Moderation / Room Cards → thực tế đều đã có.
- Chỉ cần nâng cấp RAG/Smart Matching để xứng tầm.

### 4.3 UI/UX / PWA / Dark mode

- Dark mode & modal mobile issue → thực tế đã fix.
- PWA → thực tế đã có.
- Real-time chat → vẫn là phần còn thiếu.

### 4.4 Bảo mật

- Có CSRF/Rate limit ở một số nơi → cần mở rộng đồng nhất.
- CSP → còn thiếu.
- Secrets management → còn thiếu (đang hardcode ở config).

---

## 5) Kiến trúc nâng cấp đề xuất (mức “đủ để trình bày” cho hội đồng)

### 5.1 Luồng Chatbot (RAG/Smart Matching kỳ thi)

1. User hỏi (text) hoặc gửi intent tự nhiên.
2. **Entity extraction** (Groq function calling / hoặc LLM structured output).
3. Query DB theo entity (location/budget/amenities/pet).
4. Chấm similarity scoring, sắp xếp top phòng.
5. Inject context vào prompt.
6. LLM trả lời + tạo mã Room Cards (`[ROOM:nguon:id]`).
7. Frontend render card trực quan → user click xem chi tiết/đặt phòng.

### 5.2 AI Moderation (admin)

- Khi bài đăng chờ duyệt: chạy moderation → lưu `ai_check` → admin thấy badge score/verdict và lý do.

### 5.3 Deployment/DevEx

- Secrets qua `.env`.
- CSP giúp giảm risk XSS.
- Rate limiting giúp giảm brute-force/abuse.
- Monitoring server uptime.

---

## 6) Danh sách việc cần làm (checklist thi Quốc gia)

### 6.1 Bắt buộc (Blocker)

- [ ] Chuyển `config/database.php` sang đọc DB credentials từ `$_ENV`.
- [ ] Chuyển `config/onesignal.php` sang đọc OneSignal keys từ `$_ENV`.
- [ ] Xóa `.env` khỏi git index (`git rm --cached .env`) và tạo `.env.example`.
- [ ] Chuẩn hóa CSRF cho toàn bộ API POST.
- [ ] Thêm rate limiting cho `login.php`.
- [ ] Thêm CSP header trong `.htaccess`.

### 6.2 Nâng cấp trọng tâm (điểm cộng lớn)

- [ ] Smart Matching v2 (entity extraction + similarity scoring) tích hợp chatbot.
- [ ] REST API v2 chuẩn hóa response format.
- [ ] Real-time chat (SSE hoặc WebSocket) thay polling.
- [ ] Architecture diagrams (system/db/AI/deploy) export PNG để đưa vào báo cáo.
- [ ] Deployment lên Railway/VPS + SSL + env + test end-to-end.

### 6.3 Nâng cấp tăng độ “complete”

- [ ] Sentiment analysis cho reviews.
- [ ] Dynamic pricing suggestion.
- [ ] Admin analytics dashboard.
- [ ] Swagger/OpenAPI docs.
- [ ] Image optimization + skeleton/empty/error states.

---

## 7) Tài liệu/Đầu ra bắt buộc kèm bài dự thi

- [ ] `project_info__3.md` (tổng hợp đối chiếu & kế hoạch).
- [ ] `Update/Bao_cao.md` cập nhật kết quả mới.
- [ ] Swagger JSON + link tài liệu.
- [ ] Architecture diagrams: System + DB ERD + AI pipeline + Deployment.
- [ ] Slide 10–15 trang.
- [ ] Video demo 3–5 phút.
- [ ] README (setup guide, feature list, screenshots, live demo link).

---

## 8) Ghi chú quản trị phạm vi (để tránh làm sai/tốn thời gian)

- Những hạng mục như **Leaflet map, AI Autofill, AI Moderation, Room Cards, Dark mode, PWA, notification** được xác nhận đã có → không cần làm lại.
- Trọng tâm cần đầu tư là: **Secrets management + CSP + CSRF mở rộng + rate limiting login + Smart Matching nâng cao + Real-time chat + chuẩn API docs/deploy/diagram**.

---

## 9) Kết luận

Hiện dự án có nền tảng đủ mạnh ở UX/PWA/AI UI/Moderation/Maps. Để đạt chuẩn “Quốc gia”, phần thiếu chủ đạo nằm ở:

- **Bảo mật & secret management**
- **Nâng cấp RAG thành Smart Matching đúng nghĩa**
- **Chuẩn hóa API & tài liệu (Swagger + diagrams)**
- **Real-time chat**
- **Deployment chuyên nghiệp**

---

_End of project_info\_\_3.md_
