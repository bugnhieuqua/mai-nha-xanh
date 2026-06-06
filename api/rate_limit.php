<?php
/**
 * Rate Limiting Helper — dùng File Cache & IP để giới hạn số request (Chống Session Bypass)
 * Sử dụng: checkRateLimit('chatbot', 20, 60) → max 20 req / 60 giây
 */
function checkRateLimit(string $key, int $maxRequests, int $windowSeconds): bool {
    // 0. Bỏ qua giới hạn tần suất nếu cấu hình ENABLE_RATE_LIMIT = false
    $enableLimit = $_ENV['ENABLE_RATE_LIMIT'] ?? getenv('ENABLE_RATE_LIMIT') ?? 'true';
    if ($enableLimit === 'false') {
        return true;
    }

    // 1. Xác định IP thực (bao gồm cả proxy/Cloudflare nếu có)
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $ip = explode(',', $ip)[0]; // Lấy IP đầu tiên nếu có danh sách
    
    // 2. Tạo file cache cho mỗi IP và Endpoint
    $cacheDir = __DIR__ . '/../assets/cache/rate_limit';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    // Mã hóa tên file bằng md5
    $cacheFile = $cacheDir . '/rl_' . md5($key . '_' . trim($ip)) . '.json';
    
    $now = time();
    $data = ['count' => 0, 'start' => $now];

    if (file_exists($cacheFile)) {
        $stored = json_decode(file_get_contents($cacheFile), true);
        if ($stored && is_array($stored) && isset($stored['start'])) {
            if ($now - $stored['start'] < $windowSeconds) {
                // Vẫn nằm trong window
                $data = $stored;
            }
        }
    }

    $data['count']++;
    file_put_contents($cacheFile, json_encode($data));

    if ($data['count'] > $maxRequests) {
        $retryAfter = $windowSeconds - ($now - $data['start']);
        
        // Định dạng thời gian chờ thân thiện (phút + giây)
        $timeText = '';
        if ($retryAfter >= 60) {
            $minutes = floor($retryAfter / 60);
            $seconds = $retryAfter % 60;
            $timeParts = [];
            if ($minutes > 0) $timeParts[] = "$minutes phút";
            if ($seconds > 0) $timeParts[] = "$seconds giây";
            $timeText = implode(' ', $timeParts);
        } else {
            $timeText = "$retryAfter giây";
        }

        if (!headers_sent()) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $retryAfter);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau ' . $timeText . '.',
            'retry_after' => $retryAfter
        ]);
        exit;
    }

    return true;
}
?>
