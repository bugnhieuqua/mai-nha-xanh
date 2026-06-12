<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isset($_POST['reset_password_btn'])) {
    // Xác thực CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $_SESSION['error'] = "Lỗi bảo mật: CSRF Token không hợp lệ. Vui lòng thử lại.";
        header("Location: auth-password-forgot.php");
        exit();
    }

    $email = $_POST['email'];

    $database = new Database();
    $db = $database->getConnection();

    // --- Auto-migration for missing columns ---
    try {
        $db->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL AFTER password");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME NULL AFTER reset_token");
    } catch (Exception $e) {}

    // Check if email exists
    $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $token = bin2hex(random_bytes(16));
        // Để đơn giản và tương thích với DB hiện tại, chúng ta lưu token trực tiếp (hoặc dùng hash nếu muốn bảo mật cao hơn)
        // Ở đây tôi lưu trực tiếp để dễ dàng so sánh trong auth-password-reset.php
        $expiry = date("Y-m-d H:i:s", time() + 60 * 30); // 30 phút

        $update = "UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE email = :email";
        $stmt = $db->prepare($update);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expiry', $expiry);
        $stmt->bindParam(':email', $email);

        if ($stmt->execute()) {
            // Gửi Email
            $to = $email;
            $subject = "Đặt lại mật khẩu - Mái Nhà Xanh";
            $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/auth-password-reset.php?token=" . $token;
            
            $message = "Để đặt lại mật khẩu, vui lòng nhấp vào liên kết sau (hết hạn sau 30 phút): \n\n";
            $message .= $reset_link;
            $headers = "From: no-reply@mainhaxanh.com";

            if (@mail($to, $subject, $message, $headers)) {
                $_SESSION['status'] = "Liên kết đặt lại mật khẩu đã được gửi đến email của bạn.";
            } else {
                // Nếu mail() thất bại (thường gặp trên localhost hoặc host free chưa cấu hình mail server)
                $_SESSION['status'] = "Yêu cầu đặt lại mật khẩu đã được ghi nhận. <br><br> Do cấu hình mail server, vui lòng sử dụng liên kết dưới đây để đặt lại mật khẩu ngay: <br> <a href='$reset_link' style='color:#10b981;font-weight:700;'>Bấm vào đây để đặt lại mật khẩu</a>";
            }
        } else {
            $_SESSION['error'] = "Có lỗi xảy ra với cơ sở dữ liệu.";
        }
    } else {
        $_SESSION['error'] = "Email không tồn tại trong hệ thống.";
    }
    
    header("Location: auth-password-forgot.php");
    exit();
}
?>
