# 📝 Changelog – Mái Nhà Xanh | 05/06/2026

> Tất cả các chỉnh sửa thực hiện trong phiên làm việc hôm nay.
> Ký hiệu: `[FILE:dòng]` → nội dung thay đổi.

---

## 1. `assets/css/style.css`

### 🔵 Thêm mới – Dark Mode Media Query (`prefers-color-scheme`)
- Dòng ~3083 (cuối file): Thêm `@media (prefers-color-scheme: dark)` để tự động bật dark mode theo hệ điều hành
- Bổ sung các CSS variable: `--text-main`, `--text-sub`, `--card-bg`, `--glass-bg`, `--glass-border`, `--glass-shadow`
- Thêm `.modal-box` trong dark mode: nền glassmorphism (`rgba(31,41,55,0.85)`), border mờ, shadow
- Thêm `.modal-close-btn` dark mode: nền `rgba(255,255,255,0.2)`
- Thêm heading trong modal dark mode: `color: var(--text-main) !important`
- Thêm `.action-buttons-scroll button` dark mode: `background: var(--primary-color)`

### 🔵 Sửa – Footer Link Hover (dòng 1968–1993)
- Dòng 1968–1972: `.footer-section ul li a` thêm `display: inline-block` và `transition: color 0.3s, transform 0.25s`
- Dòng 1975–1980: `.footer-section ul li a:hover` thêm `text-decoration: underline` và `text-shadow: 0 0 8px rgba(9,236,206,0.35) !important`
- Dòng 1982–1993: **THÊM MỚI** class `.footer-contact li a.footer-map-link`:
  - `color: rgba(255,255,255,0.9)`, `text-decoration: none`, `transition` mượt
- Dòng 1993+: **THÊM MỚI** `.footer-contact li a.footer-map-link:hover`:
  - `color: #09ecce`, `text-decoration: underline`, `text-shadow: 0 0 10px rgba(9,236,206,0.5) !important`

### 🔵 Sửa – Dark Mode Text Contrast (dòng 3085–3160)
- Dòng 3085–3117: `[data-theme="dark"] .section-header h2`, `.contact-info h2`, `.contact-form-wrapper h2`, `.about-text h2` → `color: #f8fafc !important`
- Dòng 3119–3126: `[data-theme="dark"] .section-header p`, `.about-text p`, `.vm-card p`, `.why-item p`, `.feature-card p` → `color: rgba(248,250,252,0.92) !important`
- Dòng 3128–3133: `[data-theme="dark"] .about-house-section p/li/span` → `color: rgba(226,232,240,0.95) !important`
- Dòng 3135–3138: `[data-theme="dark"] .about-text p` → `color: #cbd5e1 !important` (slate-300)
- Dòng 3140–3147: `[data-theme="dark"] .room-info h3` → `#f1f5f9`, `.room-address` và `.room-details span` → `#cbd5e1`
- Dòng 3149–3153: `[data-theme="dark"] .filter-label`, `.filter-sub` → `#f1f5f9`
- Dòng 3155–3160: `[data-theme="dark"] p, li, label` → `color: var(--text-sub)`

### 🔵 Sửa – About Page: `.about-text h2` (dòng 1695–1701)
- `color` đổi từ `var(--dark-color)` → `#0f172a` (đen đậm, luôn hiển thị)
- Thêm `font-weight: 800` và `text-shadow: none`

### 🔵 Sửa – About Page: `.about-text p` (dòng 1703–1708)
- `color` đổi từ `var(--gray)` → `#1e293b` (slate-800, đậm hơn)
- Thêm `font-weight: 500`

### 🔵 Sửa – Stat Items: `.stat-item` (dòng 1717–1735)
- `background` đổi từ `var(--light-color)` → `rgba(255,255,255,0.85)` (trắng đục)
- Thêm `border: 1px solid rgba(255,255,255,0.6)`
- Thêm `backdrop-filter: blur(8px)` và `-webkit-backdrop-filter: blur(8px)`
- Thêm `box-shadow: 0 4px 15px rgba(0,0,0,0.08)`
- `.stat-item h3`: thêm `font-weight: 800`
- `.stat-item p`: `color` đổi từ `var(--gray)` → `#334155`, thêm `font-weight: 600`, `font-size: 0.9rem`

### 🔵 Thêm – Dark Mode Overrides cho About / Stat (cuối file, dòng ~3174+)
- `[data-theme="dark"] .about-text h2` → `#f1f5f9`
- `[data-theme="dark"] .about-text p` → `#cbd5e1`
- `[data-theme="dark"] .stat-item` → nền `rgba(30,41,59,0.85)`, border `rgba(255,255,255,0.1)`
- `[data-theme="dark"] .stat-item h3` → `#34d399`
- `[data-theme="dark"] .stat-item p` → `#94a3b8`

### 🔵 Thêm – Mobile Navigation Dark Mode (dòng 3027–3047)
- `[data-theme="dark"] .nav-menu` → nền gradient tối, border-left emerald
- `[data-theme="dark"] .nav-menu a` → màu `var(--text-main)`
- `[data-theme="dark"] .nav-menu a:hover` → `var(--primary-color)` + bg xanh nhạt
- `[data-theme="dark"] .nav-menu a.active` → `var(--primary-color)`

### 🔵 Thêm – Dark Mode Landlord Card (dòng 3162–3172)
- `[data-theme="dark"] #rdLandlordSection` → nền gradient xanh tối
- `[data-theme="dark"] #rdLandlordSection span` → `#34d399`
- `[data-theme="dark"] #rdLandlordSection strong` → `#a7f3d0`

---

## 2. `includes/footer.php`

### 🔴 Sửa – Địa chỉ trường (dòng 32)
- **XÓA**: `style="color: inherit; text-decoration: none;"` (inline style)
- **XÓA**: `onmouseover="this.style.textDecoration='underline'"` (inline JS)
- **XÓA**: `onmouseout="this.style.textDecoration='none'"` (inline JS)
- **THÊM**: `class="footer-map-link"` → dùng CSS class để quản lý hover nhất quán
- Kết quả: hover hiện underline + glow xanh `#09ecce`, transition 0.3s mượt

---

## 3. `includes/header.php`

### 🔴 Sửa – PWA Install Banner Logic (dòng 809–921)

#### Thay đổi hành vi nút "Bỏ qua":
- **XÓA** toàn bộ khối `sessionStorage` (lý do: sessionStorage tồn tại qua F5, không ẩn banner khi reload)
- **XÓA** logic mini badge (không còn hiện mini badge góc phải sau khi bỏ qua)
- **THÊM**: Nút "Bỏ qua" chỉ ẩn banner với animation `opacity:0 + translateY(100%)` trong 300ms
- **Không lưu gì vào storage** → Load lại trang: JS chạy lại từ đầu, banner hiện lại sau 1.5 giây

#### Thay đổi hành vi nút "Cài đặt":
- Thêm SweetAlert thông báo thành công sau khi cài:
  - `icon: 'success'`, title `🎉 Cài đặt thành công!`
  - Text: `Mái Nhà Xanh đã được thêm vào màn hình của bạn`
  - `confirmButtonText: 'Tuyệt vời!'`, `timer: 3000`, `timerProgressBar: true`

#### Giữ nguyên:
- Nếu đã cài (`pwa-installed=1` trong localStorage) → không hiện banner
- Nếu đang standalone mode → không hiện banner
- Hướng dẫn thủ công (iOS/Android) khi `beforeinstallprompt` chưa kích hoạt
- Banner hiện sau 1.5 giây delay (tránh che nội dung ngay lúc load)

---

## 4. `gioi-thieu.php`

### 🟢 Thêm – Style Block (dòng 15–62)
- Thêm `<style>` block riêng cho trang Giới thiệu với specificity cao
- **Light mode & Dark mode (đều dùng chữ tối)**:
  - `.about-section .about-text h2` → `color: #0f172a !important`, `font-weight: 800`
  - `.about-section .about-text p` → `color: #1e293b !important`, `font-weight: 500`
  - `.about-section .stat-item` → nền trắng 88%, border trắng, backdrop-filter blur 10px, shadow
  - `.about-section .stat-item h3` → `color: #059669 !important` (emerald), `font-weight: 800`
  - `.about-section .stat-item p` → `color: #1e293b !important`, `font-weight: 600`
- **Dark mode**: ghi đè toàn bộ bằng cùng màu tối (chữ đen, nền trắng đục)
  - Lý do: nền section `.about-section` là teal/mint → chữ tối đọc dễ hơn trong cả 2 mode

---

## 📊 Tổng kết

| File | Số thay đổi | Loại |
|------|------------|------|
| `assets/css/style.css` | ~14 khối | Sửa + Thêm |
| `includes/footer.php` | 1 dòng (dòng 32) | Sửa |
| `includes/header.php` | ~110 dòng (dòng 809–921) | Viết lại |
| `gioi-thieu.php` | ~48 dòng (dòng 15–62) | Thêm mới |

---

*Changelog được tạo tự động: 05/06/2026 18:50 (GMT+7)*
