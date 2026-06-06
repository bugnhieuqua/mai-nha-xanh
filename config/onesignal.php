<?php
// Cấu hình OneSignal Web Push
// Đọc credentials từ environment variables (.env) — KHÔNG hardcode!
require_once __DIR__ . '/env_loader.php';

define('ONESIGNAL_APP_ID', $_ENV['ONESIGNAL_APP_ID'] ?? '');
define('ONESIGNAL_REST_API_KEY', $_ENV['ONESIGNAL_REST_API_KEY'] ?? '');
?>