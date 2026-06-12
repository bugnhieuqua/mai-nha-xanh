<?php
/**
 * Chatbot Image Handler API
 * Nhận ảnh từ chatbot dưới dạng base64, gửi đến Gemini 2.5 Flash để phân tích và mô tả đặc điểm phòng trọ.
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

// Rate limit: 20 request/phút
if (function_exists('checkRateLimit')) {
    checkRateLimit('chatbot_image', 20, 60);
}

// Lấy JSON payload từ frontend
$input = json_decode(file_get_contents('php://input'), true);
$imageData = $input['image_data'] ?? '';
$mimeType = $input['mime_type'] ?? '';

if (empty($imageData) || empty($mimeType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu hình ảnh hoặc định dạng ảnh.']);
    exit;
}

// Kiểm tra API Key
$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
if (empty($apiKey) || $apiKey === 'AIzaSyYourKeyHere') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chưa cấu hình GEMINI_API_KEY trong file .env']);
    exit;
}

// Giới hạn định dạng ảnh
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận các định dạng ảnh JPG, PNG, WEBP, GIF.']);
    exit;
}

// API Endpoint Gemini
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$prompt = "Bạn là một AI chuyên phân tích hình ảnh phòng trọ. Hãy phân tích kỹ bức ảnh phòng trọ do người dùng gửi lên. Hãy mô tả chi tiết, ngắn gọn trong khoảng 3-4 câu tiếng Việt các đặc điểm của phòng: đồ đạc nội thất thấy được (giường, tủ, điều hòa, tủ lạnh, kệ bếp...), trạng thái phòng (mới, sạch sẽ, rộng rãi...), loại phòng (phòng trọ bình dân, chung cư mini, phòng studio...), và các tiện ích nổi bật. Mô tả này sẽ được chatbot sử dụng để tư vấn phòng trọ phù hợp nhất cho người dùng. Trả về văn bản thuần túy mô tả phòng, không kèm theo bất kỳ ghi chú hay định dạng nào khác.";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt],
                [
                    "inlineData" => [
                        "mimeType" => $mimeType,
                        "data" => $imageData
                    ]
                ]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
// Bỏ qua SSL check trên localhost hoặc khi bật chế độ DEBUG
$hostName = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = (php_sapi_name() === 'cli')
    || in_array($hostName, ['localhost', '127.0.0.1', '::1'])
    || (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']))
    || strpos($hostName, 'localhost:') === 0;

$appDebug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false';
if ($isLocalhost || $appDebug === 'true') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);


if ($curlError) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối API Gemini: ' . $curlError]);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300 && $response) {
    $result = json_decode($response, true);
    $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    $cleanDescription = trim($textResponse);
    if (!empty($cleanDescription)) {
        echo json_encode([
            'success' => true,
            'description' => $cleanDescription
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gemini không trả về mô tả cho ảnh này.'
        ]);
    }
} else {
    http_response_code($httpCode === 0 ? 500 : $httpCode);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi từ máy chủ Gemini API (HTTP ' . $httpCode . ')',
        'details' => json_decode($response, true) ?? $response
    ]);
}
