<?php
/**
 * Error Handler — Xử lý lỗi PHP thống nhất
 * - Production: log lỗi vào file, không hiển thị chi tiết ra browser
 * - Development: hiển thị lỗi chi tiết khi APP_DEBUG=true
 */

// Load env nếu chưa load
if (!isset($_ENV['APP_DEBUG'])) {
    if (file_exists(__DIR__ . '/../config/env_loader.php')) {
        require_once __DIR__ . '/../config/env_loader.php';
    }
}

$isDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Thiết lập error reporting
error_reporting(E_ALL);

if ($isDebug) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Log errors vào file
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('log_errors', 1);
ini_set('error_log', $logDir . '/php_errors.log');

/**
 * Custom Error Handler
 * Chuyển PHP warnings/notices thành log entries thay vì hiển thị ra browser
 */
set_error_handler(function ($severity, $message, $file, $line) use ($isDebug) {
    // Nếu error đã bị suppress bởi @, bỏ qua
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $errorType = match ($severity) {
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        (defined('E_STRICT') ? E_STRICT : -1) => 'STRICT',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        default => 'UNKNOWN'
    };

    $logMessage = "[$errorType] $message in $file on line $line";
    error_log($logMessage);

    // Trong production, không hiển thị gì
    if (!$isDebug) {
        return true; // Đã xử lý, không chạy handler mặc định
    }

    // Debug mode: để PHP handler mặc định xử lý (hiển thị lỗi)
    return false;
});

/**
 * Custom Exception Handler
 * Bắt mọi exception chưa được catch
 */
set_exception_handler(function (Throwable $exception) use ($isDebug) {
    error_log("UNCAUGHT EXCEPTION: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());

    if ($isDebug) {
        echo "<div style='padding:20px; background:#fee2e2; border:1px solid #ef4444; border-radius:10px; margin:20px; font-family:monospace;'>";
        echo "<h3 style='color:#dc2626;'>⚠️ Exception: " . htmlspecialchars($exception->getMessage()) . "</h3>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . ":" . $exception->getLine() . "</p>";
        echo "<pre style='background:#1e293b; color:#e2e8f0; padding:15px; border-radius:8px; overflow-x:auto;'>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        // Production: hiển thị trang lỗi thân thiện
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo "<div style='padding:40px; text-align:center; background:#fff; color:#374151; font-family:sans-serif; max-width:600px; margin:100px auto; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.1);'>
                <div style='font-size:3rem; margin-bottom:20px;'>⚠️</div>
                <h2 style='color:#ef4444;'>Có lỗi xảy ra</h2>
                <p>Hệ thống gặp sự cố. Vui lòng thử lại sau hoặc liên hệ quản trị viên.</p>
              </div>";
    }
    exit(1);
});

/**
 * Shutdown Handler
 * Bắt fatal errors (parse error, out of memory, etc.)
 */
register_shutdown_function(function () use ($isDebug) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log("FATAL ERROR: {$error['message']} in {$error['file']}:{$error['line']}");

        if (!$isDebug && !headers_sent()) {
            http_response_code(500);
            echo "<div style='padding:40px; text-align:center; background:#fff; color:#374151; font-family:sans-serif; max-width:600px; margin:100px auto; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.1);'>
                    <div style='font-size:3rem; margin-bottom:20px;'>🛠️</div>
                    <h2 style='color:#ef4444;'>HỆ THỐNG ĐANG BẢO TRÌ</h2>
                    <p>Vui lòng quay lại sau ít phút.</p>
                  </div>";
        }
    }
});
?>
