# TODO - Fix CSRF khi đổi avatar

- [x] (1) Sửa public upload avatar: `includes/header.php` thêm `csrf_token` vào `FormData` trước khi fetch `api/upload_avatar.php`.
- [x] (2) Sửa admin upload avatar: `admin/includes/sidebar.php` thêm `csrf_token` vào `formData` trước khi fetch `../api/upload_avatar.php`.
- [ ] (3) Chạy kiểm tra nhanh: đổi avatar trên trang public và admin xem còn lỗi CSRF không.


