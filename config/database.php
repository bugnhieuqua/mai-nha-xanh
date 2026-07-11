<?php
// Cấu hình kết nối cơ sở dữ liệu
// Đọc credentials từ environment variables (.env) — KHÔNG hardcode!
require_once __DIR__ . '/env_loader.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    // Singleton: Lưu trữ instance PDO duy nhất trong toàn bộ request
    private static $sharedConnection = null;

    public function __construct() {
        $this->host     = $_ENV['DB_HOST'] ?? $_ENV['MYSQLHOST'] ?? 'localhost';
        $this->db_name  = $_ENV['DB_NAME'] ?? $_ENV['MYSQLDATABASE'] ?? 'quanlytro';
        $this->username = $_ENV['DB_USER'] ?? $_ENV['MYSQLUSER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? $_ENV['MYSQLPASSWORD'] ?? '';
        $this->port     = $_ENV['DB_PORT'] ?? $_ENV['MYSQLPORT'] ?? '3306';
    }

    // Kết nối database — Singleton: chỉ tạo 1 connection cho mỗi request
    public function getConnection(): ?PDO {
        // Nếu đã có connection hợp lệ, tái sử dụng
        if (self::$sharedConnection !== null) {
            $this->conn = self::$sharedConnection;
            return $this->conn;
        }

        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // Chống SQL Injection thực thụ
                    PDO::ATTR_PERSISTENT => true, // Persistent connection — tái sử dụng qua các request
                ]
            );

            // Set charset + timezone ngay sau khi connect để tránh cảnh báo deprecation trên PHP 8.5+
            $this->conn->exec("SET NAMES utf8mb4");
            $this->conn->exec("SET time_zone = '+07:00'");

            // Lưu vào singleton
            self::$sharedConnection = $this->conn;
        } catch(PDOException $exception) {
            error_log($exception->getMessage());
            die("<div style='padding:40px; text-align:center; background:#fff; color:#374151; font-family:sans-serif; max-width:600px; margin:100px auto; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.1);'>
                    <div style='font-size:3rem; margin-bottom:20px;'>🛠️</div>
                    <h2 style='color:#ef4444;'>HỆ THỐNG ĐANG BẢO TRÌ</h2>
                    <p>Chúng tôi đang tối ưu hóa hệ thống. Vui lòng quay lại sau ít phút.</p>
                 </div>");
        }
        
        return $this->conn;
    }

    // Reset connection (dùng khi cần force reconnect)
    public static function resetConnection(): void {
        self::$sharedConnection = null;
    }
}
?>
