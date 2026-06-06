<?php
/**
 * Environment Variable Loader
 * Đọc file .env và load vào $_ENV / $_SERVER
 */

function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Bỏ qua comment
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Xóa quotes nếu có
            if (strlen($value) > 1 && (($value[0] === '"' && $value[strlen($value)-1] === '"') || ($value[0] === "'" && $value[strlen($value)-1] === "'"))) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
    return true;
}

// Auto-load khi include file này
loadEnv();

// Đảm bảo $_ENV có giá trị từ hệ thống (cho Railway/Docker khi variables_order không chứa 'E')
$envKeys = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 
    'GROQ_API_KEY', 'GROQ_MODEL', 'GEMINI_API_KEY', 
    'ONESIGNAL_APP_ID', 'ONESIGNAL_REST_API_KEY', 
    'APP_DEBUG', 'ENABLE_RATE_LIMIT', 'GOOGLE_CLIENT_ID'
];
foreach ($envKeys as $key) {
    if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
        $val = getenv($key);
        if ($val !== false) {
            $_ENV[$key] = $val;
        } elseif (isset($_SERVER[$key])) {
            $_ENV[$key] = $_SERVER[$key];
        }
    }
}

