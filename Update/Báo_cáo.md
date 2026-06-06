# 📋 Báo cáo tổng quan & đề xuất nâng cấp hệ thống "Mái Nhà Xanh"

## 1️⃣ Đánh giá hiện trạng (tới 18:06 30 / 06 / 2026)

| Thành phần | Trạng thái | Nhận xét chi tiết |
|------------|------------|-------------------|
| **Frontend (HTML + CSS + JS)** | **Cơ bản – còn nhiều hạn chế** | - Giao diện chưa áp dụng **glassmorphism**, gradient, micro‑animation. <br>- **Dark mode**: màu chữ mờ, khó đọc. <br>- Modal chi tiết phòng trên mobile có `height:100vh` → nội dung bị cắt, không thể scroll. <br>- Các thành phần filter, dropdown, toggle chưa có hiệu ứng mượt, chưa tối ưu cho cảm ứng. |
| **Backend (PHP)** | **Ổn định, có một số cải tiến** | - Đã **thêm polyfill** cho `mbstring`. <br>- **Hàm `getApproximateCoords`** tự động tính tọa độ dựa trên địa chỉ. <br>- Tự động tạo cột `lat`, `lng` nếu chưa tồn tại. <br>- Chưa có lớp trừu tượng, chưa chuẩn **MVC/ORM**. |
| **Cơ sở dữ liệu** | **Đủ cấu trúc cho MVP** | - Bảng `phongtro`, `dangbai_chothuetro` có các trường cần thiết. <br>- Thiếu **index** trên `diachi`, `lat`, `lng` gây performance khi lọc. |
| **Bản đồ (Leaflet + MarkerCluster)** | **Hoạt động nhưng còn lẻ** | - Pin dựa trên `lat/lng` từ DB hoặc hàm dự đoán, đã khắc phục pin trùng bằng offset. <br>- Chưa có **clustering** hiệu quả khi số lượng > 200. |
| **Chatbot & AI (Gemini / Groq)** | **Chưa triển khai** | - Chưa có endpoint API, chưa có **key management** qua `.env`. |
| **SEO & Accessibility** | **Cơ bản** | - Thiếu `<title>`, `<meta description>`, thẻ heading hợp lý. <br>- Ảnh chưa có `alt`. <br>- Không có **schema.org**. |
| **Performance** | **Chưa tối ưu** | - Tải toàn bộ JS/CSS trong một file, không có **code splitting**. <br>- Ảnh chưa nén, dùng placeholder lớn. |
| **Bảo mật** | **Cần cải thiện** | - API key phải lấy từ `.env`. <br>- Thiếu **CSRF token**, **CSP**, **HTTPS**. |
| **Kiểm thử** | **Thiếu** | - Không có unit/integration test. |

---

## 2️⃣ Các vấn đề tồn tại (độ ưu tiên)

| Vấn đề | Mức độ |
|--------|--------|
| Độ đọc trong Dark mode | **Cao** |
| Modal trên mobile (height) | **Cao** |
| UI hiện đại (glassmorphism, micro‑animation) | **Trung bình** |
| Kiến trúc backend (MVC/ORM) | **Cao** |
| Tích hợp AI (Gemini, Groq) | **Cao** |
| Bảo mật (CSRF, CSP, HTTPS) | **Cao** |
| Performance (CDN, nén ảnh) | **Cao** |
| SEO & Accessibility | **Trung bình** |

---

## 3️⃣ Đề xuất nâng cấp (theo ưu tiên)

### 🔥 Nâng cấp ưu tiên cao
1. **Cải thiện Dark mode**: sửa màu chữ, background, thêm media query `@media (prefers-color-scheme: dark)`.
2. **Sửa Modal mobile**: bỏ `height:100vh`, thêm `max-height:85vh; overflow-y:auto;` và áp dụng **glassmorphism**.
3. **Cập nhật `phong-tro.php`** để gọi `getApproximateCoords` cho các phòng chưa có `lat/lng`.
4. **Bảo mật**: chuyển API key sang `.env`, thêm CSRF token cho form, cấu hình CSP.
5. **Tích hợp AI**: tạo micro‑service Docker cho Gemini (xác thực ảnh) và Groq (chatbot). 

### 📈 Nâng cấp trung bình
- Thêm **index** cho DB (`diachi`, `lat`, `lng`).
- Áp dụng **React + Vite** hoặc **Vue** để xây dựng SPA, chuyển UI sang component.
- Thêm **lazy‑load** ảnh, chuyển sang WebP, sử dụng CDN.
- Thêm **PWA** manifest & service worker.
- Viết **unit/integration test** (PHPUnit, Jest).
- Cải thiện SEO: meta, schema, sitemap.

---

## 4️⃣ Kế hoạch thực hiện (8 tuần)
| Tuần | Nội dung |
|------|----------|
| 1‑2 | Fix Dark mode & Modal, integrate `getApproximateCoords`. |
| 3‑4 | Refactor backend (ORM, API), triển khai micro‑service AI. |
| 5 | Chuyển frontend sang React/Vite, áp dụng glassmorphism, micro‑animation. |
| 6 | Tối ưu performance (CDN, lazy‑load, PWA). |
| 7 | Bảo mật (CSRF, CSP, HTTPS), viết test. |
| 8 | Kiểm thử toàn diện (Lighthouse, axe, Cypress), chuẩn bị demo. |

---

## 5️⃣ Kết luận
- Hệ thống hiện tại đáp ứng **MVP**, nhưng còn **cơ bản** về UI/UX, kiến trúc và bảo mật.
- Các nâng cấp trên sẽ đưa sản phẩm lên **tiêu chuẩn hiện đại**, tạo ấn tượng “đẳng cấp” cho cuộc thi cấp Quốc gia.
- Đề xuất bắt đầu với các **ưu tiên cao** (dark mode, modal, tọa độ) để nhanh chóng cải thiện trải nghiệm người dùng.

*Hãy xác nhận để tôi bắt đầu thực hiện các cải tiến ưu tiên.*
