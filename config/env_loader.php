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

