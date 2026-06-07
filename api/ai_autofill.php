<?php
/**
 * AI Autofill Room Details API
 * Nhận ảnh phòng trọ và gửi đến Gemini 2.5 Flash để tự động nhận dạng tiện nghi, diện tích, viết mô tả và tiêu đề.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/env_loader.php';
require_once '../config/session.php';
require_once 'rate_limit.php';

// Rate limit: 10 request/phút để tránh lạm dụng API Key
if (function_exists('checkRateLimit')) {
    checkRateLimit('ai_autofill', 10, 60);
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để sử dụng trợ lý AI.']);
    exit;
}

$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
if (empty($apiKey) || $apiKey === 'AIzaSyYourKeyHere') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chưa cấu hình GEMINI_API_KEY trong file .env']);
    exit;
}

// Kiểm tra ảnh gửi lên
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng tải lên một file ảnh phòng trọ hợp lệ.']);
    exit;
}

if ($_FILES['image']['size'] > 3 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dung lượng ảnh không được vượt quá 3MB.']);
    exit;
}


$imagePath = $_FILES['image']['tmp_name'];
$imageType = $_FILES['image']['type'];

// Giới hạn định dạng ảnh
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($imageType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận các định dạng ảnh JPG, PNG, WEBP, GIF.']);
    exit;
}

$imageData = base64_encode(file_get_contents($imagePath));

// API Endpoint Gemini
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$prompt = "Bạn là trợ lý AI thông minh chuyên hỗ trợ điền thông tin đăng bài cho thuê phòng trọ của hệ thống Mái Nhà Xanh tại TP. Vinh, Nghệ An.
Hãy phân tích bức ảnh phòng trọ này và điền thông tin tự động theo đúng cấu trúc JSON sau:
{
  \"tieude\": \"[Sinh một tiêu đề cực kỳ hấp dẫn, ngắn gọn dưới 70 ký tự, nêu bật ưu điểm nổi bật và khu vực/trường học gần đó nếu dự đoán được]\",
  \"gia\": [Ước tính giá thuê phù hợp thực tế tại TP. Vinh dạng số nguyên. Giá thuê bắt buộc phải nằm trong khoảng từ 1.000.000đ đến 5.000.000đ đối với phòng trọ thông thường, hoặc tối đa 15.000.000đ đối với nhà nguyên căn lớn. Không được ước lượng giá phi lý như vài chục triệu đồng hoặc dưới 500.000đ. Nếu không rõ thì mặc định để 1500000 hoặc 1800000],
  \"dientich\": [Ước tính diện tích phòng m2 thực tế dạng số nguyên. Diện tích bắt buộc phải nằm trong khoảng từ 15 đến 50 m2 đối với phòng trọ, hoặc tối đa 150 m2 đối với nhà nguyên căn. Nếu không rõ thì mặc định 20],
  \"mota\": \"[Viết mô tả phòng chi tiết từ 3-4 câu tiếng Việt tự nhiên, nêu rõ các vật dụng nhìn thấy trong ảnh như giường, tủ quần áo gỗ, điều hòa, ban công, ánh sáng tốt, vệ sinh khép kín...]\",
  \"tiennghi\": [\"[Chọn các tiện nghi thực sự nhìn thấy hoặc suy luận được từ ảnh từ danh sách: 'Wifi', 'Máy lạnh', 'Nóng lạnh', 'Tủ lạnh', 'Máy giặt', 'Giường', 'Bàn ghế', 'Tủ quần áo', 'Gác lửng', 'Chỗ để xe', 'Camera an ninh', 'Tự do giờ giấc']\"]
}
Hãy trả về JSON thô và CHỈ trả về đúng chuỗi JSON này, không bao gồm ký tự bao quanh ```json hay bất kỳ ghi chú nào khác.";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt],
                [
                    "inlineData" => [
                        "mimeType" => $imageType,
                        "data" => $imageData
                    ]
                ]
            ]
        ]
    ],
    "generationConfig" => [
        "responseMimeType" => "application/json"
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 40);

// Bỏ qua xác thực SSL nếu đang ở môi trường Debug phát triển cục bộ
$appDebug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false';
if ($appDebug === 'true') {
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
    
    $jsonResponse = json_decode(trim($textResponse), true);
    if ($jsonResponse) {
        echo json_encode([
            'success' => true,
            'data' => $jsonResponse
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'AI trả về dữ liệu không đúng định dạng JSON.',
            'raw_text' => $textResponse
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
