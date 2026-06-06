# SIÊU BÁO CÁO (MASTER REPORT): DỰ ÁN MÁI NHÀ XANH
## TỔNG HỢP TOÀN DIỆN KẾT QUẢ HỆ THỐNG & CẨM NANG API THỰC CHIẾN

---

## 🟢 PHẦN 1: TỔNG QUAN HỆ THỐNG ĐÃ TRIỂN KHAI (LIVE ON HOST)

### 1. Thông tin chung:
- **Tên dự án:** Mái Nhà Xanh (Green House).
- **Lĩnh vực:** Bất động sản / Lưu trú (PropTech).
- **Trạng thái:** Đã triển khai thực tế trên tên miền **`liveblog365.com`**.

### 2. Các tính năng cốt lõi (Core Features):
- **Bản đồ tương tác:** Tích hợp Google Maps (iframe & API) để tìm phòng quanh khu vực TP. Vinh.
- **Siêu trí tuệ Chatbot:** Tích hợp đa Model AI (Gemini, Groq Llama 3) hỗ trợ phân tích ảnh và tư vấn 24/7.
- **Hệ thống PWA:** Cho phép người dùng "Cài đặt" website thành ứng dụng trên điện thoại.
- **Quản lý đăng tin chuyên nghiệp:** Giao diện đăng bài thân thiện, quy trình kiểm duyệt Admin minh bạch.
- **Hệ thống Notify thời gian thực:** Thông báo đẩy (Push Notifications) qua OneSignal.
- **Cộng đồng thảo luận:** Hệ thống Social thu nhỏ với bài đăng, bình luận đa cấp.

### 3. Cấu trúc Database (10 Bảng chuẩn hóa):
1. `users`, 2. `phongtro`, 3. `dangbai_chothuetro`, 4. `dat_phong`, 5. `chatbot_history`, 6. `community_posts`, 7. `community_comments`, 8. `lienhe`, 9. `notifications`, 10. `reports`.

---

## 🔵 PHẦN 2: CHIẾN LƯỢC API & BẢO MẬT PHÁT TRIỂN

### 1. API là gì? (Dành cho sinh viên)
API giống như một **"Người bồi bàn"**: Khách hàng (Website) đưa thực đơn (Yêu cầu) cho bồi bàn, bồi bàn mang vào bếp (Server AI) và mang món ăn (Dữ liệu) ra cho khách. API giúp các phần mềm "nói chuyện" được với nhau.

### 2. Hệ sinh thái AI trong dự án:
| Tên công nghệ | Mô hình mô tả | Đặc điểm nhận dạng |
| :--- | :--- | :--- |
| **Google Gemini** | **"Mắt thần Đa phương thức"** | Mạnh về nhìn hình ảnh, đọc video phòng trọ. |
| **Groq (LPU)** | **"Động cơ Siêu thanh"** | Tốc độ phản hồi chat nhanh nhất thế giới hiện nay. |
| **DeepSeek** | **"Bộ não Suy luận Tối ưu"** | Thông minh, logic cao, chi phí cực kỳ tiết kiệm. |

### 3. Google Maps Integration:
- **Cách 1 (iframe Embed):** Lấy trực tiếp từ Google Maps -> Share -> Embed map. Dùng để hiển thị vị trí tĩnh trong `phong-tro.php`.
- **Cách 2 (API Key):** Lấy từ `console.cloud.google.com`. Dùng để chuyển đổi địa chỉ thành tọa độ (Geocoding) lưu vào DB.

### 4. Quy trình bảo mật API 3 lớp:
1. **Lớp 1 (Lưu trữ):** Toàn bộ Key nằm trong file **`.env`** (không đẩy lên GitHub).
2. **Lớp 2 (Nạp):** Dùng `config/env_loader.php` để nạp vào hệ thống.
3. **Lớp 3 (Thực thi):** Frontend mã hóa Base64 -> Backend dùng cURL để gọi API.

---

## 🟠 PHẦN 3: TỐI ƯU HÓA TÌM KIẾM (SEO) & SITEMAP

### 1. Sitemap là gì?
Là **"Tấm bản đồ dẫn đường cho Google"**. Nó liệt kê toàn bộ link phòng trọ để Robot Google không bỏ sót bất kỳ phòng nào.

### 2. Quy trình đăng ký Sitemap:
- **Bước 1:** Khai báo trong `robots.txt`: `Sitemap: https://liveblog365.com/sitemap.php`
- **Bước 2:** Truy cập **Google Search Console**, vào mục "Sitemaps" và nộp file `sitemap.php`.

---

## 🛠️ PHẦN 4: QUY TRÌNH TRIỂN KHAI & TÁI THIẾT LẬP (DEPLOYMENT)

### 1. Đăng ký OneSignal (Thông báo):
1. Vào `onesignal.com` -> Tạo Web Push App.
2. Nhập URL: `https://liveblog365.com`.
3. Tải 2 file SDK (`OneSignalSDKWorker.js`, `OneSignalSDKUpdaterWorker.js`) lên thư mục gốc.
4. Copy **App ID** dán vào `includes/header.php`.

### 2. Đăng ký Tên miền & Hosting:
- Trỏ bản ghi **A** về IP Host.
- **Bắt buộc** cài đặt SSL (HTTPS) để AI và Thông báo hoạt động.

---

## 🎨 PHẦN 5: CHỈ DẪN THẨM MỸ & PROMPT TẠO PPT

### 1. Aesthetics Spec:
- **Màu sắc:** Gradient Emerald (#065f46) to Lime (#a3e635).
- **Hiệu ứng:** Glassmorphism (Kính mờ).

### 2. Prompt cho AI tạo Slide:
> "Hãy tạo cho tôi bài thuyết trình 'Green Tech - Smart Key: Làm chủ thế giới REST API' cho dự án Mái Nhà Xanh. Phong cách Modern Tech, Gradient xanh lá. Giải thích API là gì, cách dùng .env bảo mật, sự tiến hóa từ Gemini sang Groq, và cách SEO bằng Sitemap. Chia làm 8 Slide kèm mô tả hình ảnh."

