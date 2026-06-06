<?php
/**
 * RESTful RAG Chatbot API v2 (OpenAI Format Compatible)
 * POST /api/v2/chat.php
 * Body: Base64 Encoded JSON string matching { "messages": [...] } (WAF Bypass) or raw JSON
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/room_status_helper.php';

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
    echo json_encode(['error' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// 1. Đọc request body (hỗ trợ cả Base64 để né WAF và raw JSON)
$rawInput = trim(file_get_contents('php://input'));
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu dữ liệu đầu vào.']);
    exit;
}

// Thử giải mã Base64
$jsonString = base64_decode($rawInput);
if ($jsonString === false || json_decode($jsonString) === null) {
    // Nếu không phải Base64, thử sử dụng trực tiếp làm JSON
    $jsonString = $rawInput;
}

$payload = json_decode($jsonString, true);
if (!$payload || !isset($payload['messages']) || !is_array($payload['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Sai định dạng payload tin nhắn.']);
    exit;
}

$apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?? '';
if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'GROQ_API_KEY chưa được cấu hình.']);
    exit;
}

$model = $_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?? 'llama-3.3-70b-versatile';

// 2. Trích xuất tin nhắn cuối cùng của người dùng để thực hiện Retrieval
$userMessage = '';
for ($i = count($payload['messages']) - 1; $i >= 0; $i--) {
    if ($payload['messages'][$i]['role'] === 'user') {
        $userMessage = $payload['messages'][$i]['content'];
        break;
    }
}

$db = getDB();
$retrievedRooms = [];
$isSearchRequest = false;

// Chỉ thực hiện trích xuất thực tế khi người dùng đang có nhu cầu tìm kiếm phòng trọ
if (!empty($userMessage) && preg_match('/(tìm|thuê|phòng|nhà|trọ|giá|diện tích|tiện nghi|ở đâu|bao nhiêu|có)/iu', $userMessage)) {
    $isSearchRequest = true;
    
    // Gọi Groq nhỏ để trích xuất các keywords tìm kiếm
    $extractionPrompt = [
        [
            'role' => 'system',
            'content' => "Bạn là công cụ trích xuất thông tin tìm kiếm phòng trọ tại TP. Vinh, Nghệ An. Hãy trích xuất bộ lọc từ tin nhắn của người dùng thành cấu trúc JSON:
{
  \"ward\": \"[Tên phường ở TP. Vinh viết có dấu hoặc không dấu, ví dụ: 'Hưng Dũng', 'Bến Thủy', 'Lê Lợi', hoặc null]\",
  \"max_price\": [giá tối đa hoặc null],
  \"min_price\": [giá tối thiểu hoặc null],
  \"min_area\": [diện tích tối thiểu m2 hoặc null],
  \"max_area\": [diện tích tối đa m2 hoặc null],
  \"amenities\": [mảng các tiện nghi, hoặc mảng trống]
}
Chỉ trả về chuỗi JSON thô, không kèm markdown ```json."
        ],
        [
            'role' => 'user',
            'content' => $userMessage
        ]
    ];

    $urlExtract = 'https://api.groq.com/openai/v1/chat/completions';
    $postExtract = json_encode([
        'model' => $model,
        'messages' => $extractionPrompt,
        'temperature' => 0.1,
        'response_format' => ['type' => 'json_object']
    ]);

    $ch = curl_init($urlExtract);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postExtract);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    // Bypass SSL trên localhost
    $appDebug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false';
    if ($appDebug === 'true') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $resExtract = curl_exec($ch);
    curl_close($ch);

    $criteria = null;
    if ($resExtract) {
        $resArr = json_decode($resExtract, true);
        $content = $resArr['choices'][0]['message']['content'] ?? '{}';
        $criteria = json_decode(trim($content), true);
    }

    // 3. Thực hiện truy vấn DB để tìm phòng khớp bộ lọc
    if ($criteria) {
        try {
            ensureDangbaiRoomStatusSchema($db);
            
            $stmt1 = $db->query("SELECT id, ten_phong, mota, hinhanh, gia, dientich, diachi, tiennghi, 'phongtro' as nguon FROM phongtro");
            $rooms1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $db->query("SELECT id, tieude as ten_phong, mota, hinhanh, gia, dientich, diachi, tiennghi, 'dangbai' as nguon FROM dangbai_chothuetro WHERE trangthai = 'da_duyet'");
            $rooms2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $allRooms = array_merge($rooms1, $rooms2);

            foreach ($allRooms as $r) {
                // Lọc theo phường
                if (!empty($criteria['ward'])) {
                    $normalizedWard = mb_strtolower($criteria['ward'], 'UTF-8');
                    $normalizedAddress = mb_strtolower($r['diachi'], 'UTF-8');
                    if (mb_strpos($normalizedAddress, $normalizedWard) === false) {
                        $shortWard = str_replace('phường ', '', $normalizedWard);
                        if (mb_strpos($normalizedAddress, $shortWard) === false) {
                            continue;
                        }
                    }
                }

                // Lọc theo giá
                if ($criteria['min_price'] !== null && $r['gia'] < $criteria['min_price']) continue;
                if ($criteria['max_price'] !== null && $r['gia'] > $criteria['max_price']) continue;

                // Lọc theo diện tích
                if ($criteria['min_area'] !== null && $r['dientich'] < $criteria['min_area']) continue;
                if ($criteria['max_area'] !== null && $r['dientich'] > $criteria['max_area']) continue;

                // Lọc theo tiện nghi
                if (!empty($criteria['amenities']) && is_array($criteria['amenities'])) {
                    $hasAll = true;
                    $normalizedTienNghi = mb_strtolower($r['tiennghi'], 'UTF-8');
                    foreach ($criteria['amenities'] as $am) {
                        $amClean = mb_strtolower($am, 'UTF-8');
                        if ($amClean === 'điều hòa') $amClean = 'máy lạnh';
                        if (mb_strpos($normalizedTienNghi, $amClean) === false) {
                            $hasAll = false;
                            break;
                        }
                    }
                    if (!$hasAll) continue;
                }

                $retrievedRooms[] = $r;
                if (count($retrievedRooms) >= 6) break; // Giới hạn 6 phòng tốt nhất
            }
        } catch (Exception $e) {}
    }
}

// Nếu không tìm thấy phòng trọ nào khớp hoặc đây không phải câu hỏi tìm kiếm, 
// lấy 3 phòng trọ mới nhất làm phòng nổi bật để giới thiệu
if (empty($retrievedRooms)) {
    try {
        $stmt1 = $db->query("SELECT id, ten_phong, mota, hinhanh, gia, dientich, diachi, tiennghi, 'phongtro' as nguon, ngaydang FROM phongtro");
        $rooms1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $db->query("SELECT id, tieude as ten_phong, mota, hinhanh, gia, dientich, diachi, tiennghi, 'dangbai' as nguon, ngaydang FROM dangbai_chothuetro WHERE trangthai = 'da_duyet'");
        $rooms2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $all = array_merge($rooms1, $rooms2);
        usort($all, function($a, $b) { return strtotime($b['ngaydang']) - strtotime($a['ngaydang']); });
        $retrievedRooms = array_slice($all, 0, 4);
    } catch (Exception $e) {}
}

// 4. Định dạng danh sách phòng trọ để nhúng vào Prompt RAG
$contextText = '';
foreach ($retrievedRooms as $r) {
    $contextText .= "- ID: " . $r['id'] . "\n";
    $contextText .= "  Nguồn: " . $r['nguon'] . "\n";
    $contextText .= "  Tiêu đề: " . $r['ten_phong'] . "\n";
    $contextText .= "  Giá: " . number_format($r['gia']) . " VNĐ/tháng\n";
    $contextText .= "  Diện tích: " . $r['dientich'] . " m2\n";
    $contextText .= "  Địa chỉ: " . $r['diachi'] . "\n";
    $contextText .= "  Tiện nghi: " . $r['tiennghi'] . "\n";
    $contextText .= "  Thẻ hiển thị card: [ROOM:" . $r['nguon'] . ":" . $r['id'] . "]\n\n";
}

// 5. Load System Prompt cốt lõi của chatbot
$systemPromptBase = '';
if (file_exists(__DIR__ . '/../../config/chatbot_prompt.php')) {
    $systemPromptBase = require __DIR__ . '/../../config/chatbot_prompt.php';
} else {
    $systemPromptBase = "Bạn là Trợ lý AI thông minh của Mái Nhà Xanh tại TP. Vinh, Nghệ An.";
}

// Bổ sung dữ liệu phòng RAG vào system prompt
$systemPrompt = $systemPromptBase . "\n\n" . 
  "DỮ LIỆU PHÒNG TRỌ THỰC TẾ LẤY TỪ HỆ THỐNG ĐỂ BẠN GIỚI THIỆU:\n" . 
  (empty($contextText) ? "Không có phòng trọ nào sẵn sàng." : $contextText) . "\n" .
  "LƯU Ý CỰC KỲ QUAN TRỌNG: Bạn chỉ được gợi ý các phòng trọ có tên trong danh sách phía trên. Dùng thẻ [ROOM:nguon:id] tương ứng để hiển thị thông tin dạng Card phòng trọ đẹp mắt. Không tự chế ID phòng.";

// 6. Chuẩn bị danh sách tin nhắn gửi đến Groq
$groqMessages = [];
$groqMessages[] = ['role' => 'system', 'content' => $systemPrompt];

// Thêm lịch sử hội thoại (bỏ các tin nhắn system cũ nếu có để tránh nhiễu prompt)
foreach ($payload['messages'] as $msg) {
    if ($msg['role'] === 'system') continue;
    
    // Convert 'model' role from Gemini/Frontend to 'assistant' for Groq
    $role = $msg['role'] === 'model' ? 'assistant' : $msg['role'];
    $content = $msg['content'] ?? ($msg['parts'][0]['text'] ?? '');
    
    $groqMessages[] = ['role' => $role, 'content' => $content];
}

// 7. Gửi yêu cầu đến Groq API
$url = 'https://api.groq.com/openai/v1/chat/completions';
$postData = json_encode([
    'model' => $model,
    'messages' => $groqMessages,
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

if ($appDebug === 'true') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Kết nối đến AI thất bại: ' . $curlError]);
    exit;
}

// Trả phản hồi về dạng OpenAI format đồng bộ để tương thích 100% với assets/js/assistant.js
http_response_code($httpCode);
echo $response;
