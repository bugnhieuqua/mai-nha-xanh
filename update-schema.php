<?php
/**
 * update-schema.php
 * Tự động nâng cấp cơ sở dữ liệu (Thêm cột is_read vào bảng messages nếu chưa có).
 * Người dùng chỉ cần truy cập file này qua trình duyệt trên hosting để chạy nâng cấp.
 */
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config/bootstrap.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    // Kiểm tra xem cột is_read đã tồn tại trong bảng messages chưa
    $check = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
    if ($check->rowCount() === 0) {
        // Cột chưa tồn tại, tiến hành thêm cột
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
        echo "🎉 THÀNH CÔNG: Đã thêm cột `is_read` (TINYINT) vào bảng `messages`.\n";
    } else {
        echo "✅ THÔNG BÁO: Cột `is_read` đã tồn tại từ trước, không cần cập nhật thêm.\n";
    }
} catch (Exception $e) {
    echo "❌ LỖI: " . $e->getMessage() . "\n";
}
