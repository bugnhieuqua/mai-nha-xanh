<?php
/**
 * RESTful AI Vision Auto-fill & Safety Auditing API
 * POST /api/v2/ai_vision_autofill.php
 * Accepts either Multipart Form (file uploaded as 'image') or JSON body { "image_data": "base64_data", "mime_type": "image/jpeg" }
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../rate_limit.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// 1. Rate Limit
if (function_exists('checkRateLimit')) {
    checkRateLimit('ai_autofill', 10, 60);
}

// 2. Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để sử dụng trợ lý AI.']);
    exit;
}

$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
if (empty($apiKey) || $apiKey === 'AIzaSyYourKeyHere') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'GEMINI_API_KEY chưa được cấu hình.']);
    exit;
}

$imageData = '';
$mimeType = '';

// Hỗ trợ cả file upload multipart/form-data và JSON base64
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['image']['size'] > 3 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dung lượng ảnh không được vượt quá 3MB.']);
        exit;
    }
    
    $imagePath = $_FILES['image']['tmp_name'];
    $mimeType = $_FILES['image']['type'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    
    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận các định dạng ảnh JPG, PNG, WEBP, GIF.']);
        exit;
    }
    
    $imageData = base64_encode(file_get_contents($imagePath));
} else {
    $rawInput = trim(file_get_contents('php://input'));
    $payload = json_decode($rawInput, true);
    
    if ($payload && !empty($payload['image_data']) && !empty($payload['mime_type'])) {
        $imageData = $payload['image_data'];
        $mimeType = $payload['mime_type'];
    }
}

if (empty($imageData) || empty($mimeType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu hình ảnh hoặc định dạng không hợp lệ.']);
    exit;
}

// System prompt hướng dẫn Gemini trích xuất dữ liệu phòng trọ dưới dạng JSON & Kiểm duyệt
$prompt = "Bạn là chuyên gia thẩm định, kiểm duyệt và định giá phòng trọ tại TP. Vinh, Nghệ An.
Hãy phân tích hình ảnh này và trả về kết quả dưới dạng JSON có cấu trúc chính xác như sau:
{
  \"safety_status\": \"hop_le\" hoặc \"vi_pham_chinh_sach\",
  \"safety_reason\": \"Mô tả chi tiết nếu ảnh vi phạm chính sách (ví dụ: ảnh nhạy cảm, ảnh rác không liên quan đến phòng trọ, logo thương hiệu khác, bạo lực), nếu hợp lệ để null\",
  \"tieude\": \"Gợi ý tiêu đề phòng trọ thu hút, ví dụ: 'Phòng trọ khép kín mới đẹp gần ĐH Vinh' hoặc 'Căn hộ chung cư mini full nội thất phường Bến Thủy'\",
  \"mota\": \"Mô tả chi tiết phòng trọ dựa trên các chi tiết nội thất nhìn thấy được trong ảnh (khoảng 3-4 câu mô tả không gian sạch sẽ, tiện nghi, thoáng mát, vệ sinh khép kín...)\",
  \"gia\": [Ước tính giá thuê phù hợp thực tế tại TP. Vinh dạng số nguyên. Giá thuê bắt buộc nằm trong khoảng từ 1.000.000đ đến 5.000.000đ cho phòng trọ, hoặc tối đa 15.000.000đ cho nhà lớn nguyên căn. Mặc định 1500000 nếu không rõ],
  \"dientich\": [Diện tích ước lượng bằng m2 từ ảnh chụp dạng số nguyên, ví dụ: 15, 20, 25, 30. Mặc định 20 nếu không rõ],
  \"tiennghi\": [Mảng các tiện nghi được phát hiện từ danh sách sau: 'Wifi', 'Máy lạnh', 'Nóng lạnh', 'Tủ lạnh', 'Máy giặt', 'Giường', 'Bàn ghế', 'Tủ quần áo', 'Gác lửng', 'Chỗ để xe', 'Camera an ninh', 'Tự do giờ giấc']
}
Hãy đảm bảo chỉ trả về JSON thô và CHỈ trả về đúng chuỗi JSON này, không bọc trong markdown ```json.";

// Gọi API Gemini 2.5 Flash
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$postData = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
                [
                    'inlineData' => [
                        'mimeType' => $mimeType,
                        'data' => $imageData
                    ]
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'responseMimeType' => 'application/json'
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 40);

// Bypass SSL on localhost development
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
    echo json_encode(['success' => false, 'message' => 'Kết nối đến Gemini Vision API thất bại: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'message' => 'Gemini Vision API trả về lỗi HTTP ' . $httpCode, 'details' => json_decode($response, true)]);
    exit;
}

$data = json_decode($response, true);
$aiResponseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($aiResponseText)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gemini Vision API không trả về kết quả phân tích.']);
    exit;
}

$jsonResponse = json_decode(trim($aiResponseText), true);
if (!$jsonResponse) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'AI trả về dữ liệu không đúng định dạng JSON.',
        'raw_text' => $aiResponseText
    ]);
    exit;
}

// Xử lý kết quả kiểm duyệt
if (isset($jsonResponse['safety_status']) && $jsonResponse['safety_status'] === 'vi_pham_chinh_sach') {
    echo json_encode([
        'success' => false,
        'safety_status' => 'vi_pham_chinh_sach',
        'message' => 'Ảnh vi phạm chính sách kiểm duyệt: ' . ($jsonResponse['safety_reason'] ?? 'Không rõ lý do.')
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'safety_status' => 'hop_le',
    'data' => $jsonResponse
]);
exit;
?>
