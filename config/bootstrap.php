<?php
/**
 * Bootstrap — Unified Loader
 * Load thứ tự: Error Handler → Environment → Database → Session
 * 
 * Sử dụng: require_once 'config/bootstrap.php'; (từ page root)
 *          require_once __DIR__ . '/../config/bootstrap.php'; (từ API/admin)
 */

// 1. Error Handler — Phải load trước tiên để bắt mọi lỗi
require_once __DIR__ . '/../includes/error_handler.php';

// 2. Environment Variables — Đọc .env
require_once __DIR__ . '/env_loader.php';

// 3. Database — Class Database kết nối MySQL
require_once __DIR__ . '/database.php';

// 4. Session — Session config, CSRF token, fingerprinting, requireLogin()
require_once __DIR__ . '/session.php';

/**
 * Helper: Lấy PDO connection nhanh
 * @return PDO|null
 */
function getDB(): ?PDO {
    static $connection = null;
    if ($connection === null) {
        $database = new Database();
        $connection = $database->getConnection();
    }
    return $connection;
}

/**
 * Helper: Lấy Base URL của dự án
 * Tự động detect HTTP/HTTPS và domain
 * @return string Base URL không có trailing slash
 */
function getAppBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Tìm base path từ SCRIPT_NAME
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Nếu đang ở trong thư mục con (admin/, api/), lấy parent
    if (preg_match('#/(admin|api|config|includes|assets)(/|$)#', $scriptDir)) {
        $scriptDir = preg_replace('#/(admin|api|config|includes|assets)(/.*)?$#', '', $scriptDir);
    }
    
    $basePath = rtrim($scriptDir, '/');
    return $protocol . '://' . $host . $basePath;
}
?>
