<?php
/**
 * Groq Chatbot Proxy API (WAF Bypass Version)
 * Nhận request (đã được Base64 Encode để né WAF từ hosting) từ frontend 
 * và forward đến máy chủ GroqCloud.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/env_loader.php';
require_once 'rate_limit.php';

// Rate limit: 30 request/phút
if (function_exists('checkRateLimit')) {
    checkRateLimit('chatbot_proxy', 30, 60);
}

// Lấy API Key từ .env
$apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?? '';
if (empty($apiKey) || $apiKey === 'gsk_your_groq_api_key_here') { 
    http_response_code(500);
    echo json_encode(['error' => 'Chưa cấu hình GROQ_API_KEY trong file .env']);
    exit;
}

// Đọc request body đã được Base64 Encode từ Frontend
$base64Input = trim(file_get_contents('php://input'));

if (empty($base64Input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu dữ liệu đầu vào.']);
    exit;
}

// Giải mã Base64 -> Chuỗi JSON UTF8
$jsonString = base64_decode($base64Input);
if ($jsonString === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Dữ liệu không ở định dạng Base64 hợp lệ.']);
    exit;
}

$payload = json_decode($jsonString, true);

if (!$payload || !isset($payload['messages']) || !is_array($payload['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu hoặc sai định dạng messages sau khi giải mã JSON.']);
    exit;
}

$model = $_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?? 'llama-3.3-70b-versatile';

// Forward đến Groq API (Chuẩn OpenAI)
$url = 'https://api.groq.com/openai/v1/chat/completions';

$postData = json_encode([
    'model' => $model,
    'messages' => $payload['messages'],
    'temperature' => 0.7,
    'max_tokens' => 2048,
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($error) {
    http_response_code(502);
    echo json_encode(['error' => 'Kết nối đến Groq thất bại: ' . $error]);
    exit;
}

// Lọc kết quả trả về nếu Groq gặp lỗi
if ($httpCode >= 400) {
    // Tự động thông báo cho Admin nếu lỗi API Key hoặc Quota (401 hoặc 429)
    if ($httpCode === 401 || $httpCode === 429) {
        try {
            require_once '../includes/one_signal_helper.php';
            
            // Cơ chế cooldown: Chỉ gửi thông báo 1 lần mỗi giờ để tránh spam Admin
            $cooldownFile = '../assets/cache/ai_error_cooldown.txt';
            if (!is_dir('../assets/cache')) mkdir('../assets/cache', 0777, true);
            
            $lastNotify = file_exists($cooldownFile) ? (int)file_get_contents($cooldownFile) : 0;
            if (time() - $lastNotify > 3600) { // 1 giờ
                $errorType = ($httpCode === 401) ? "Sai/Hết hạn API Key" : "Hết hạn mức (Quota/Rate Limit)";
                sendNotification([
                    'type'    => 'admin_alert',
                    'target'  => 'admin',
                    'title'   => '⚠️ CẢNH BÁO: Chatbot AI Ngưng Hoạt Động',
                    'content' => "Lỗi: $errorType. Vui lòng kiểm tra Groq API Key ngay!",
                    'link'    => 'admin/index.php'
                ]);
                file_put_contents($cooldownFile, time());
            }
        } catch (Exception $e) {
            // Lỗi thông báo không làm gián đoạn response chính
        }
    }

    http_response_code($httpCode);
    echo $response; // Groq trả về JSON {error: ...}
    exit;
}

// Forward response về frontend
http_response_code($httpCode);
echo $response;


