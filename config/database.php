<?php
// Cấu hình kết nối cơ sở dữ liệu
// Đọc credentials từ environment variables (.env) — KHÔNG hardcode!
require_once __DIR__ . '/env_loader.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host     = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name  = $_ENV['DB_NAME'] ?? 'quanlytro';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
    }

    // Kết nối database
    public function getConnection(): ?PDO {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // Chống SQL Injection thực thụ
                ]
            );
            $this->conn->exec("set names utf8mb4");
            $this->conn->exec("SET time_zone = '+07:00'");
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
}
?>
