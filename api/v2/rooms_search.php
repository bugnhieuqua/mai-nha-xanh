<?php
/**
 * RESTful Smart Room Search API v2
 * POST /api/v2/rooms_search.php
 * Body: { "query": "tìm phòng trọ gần đại học sư phạm kỹ thuật vinh giá dưới 2 triệu có wifi máy lạnh" }
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
    echo json_encode([
        'success' => false,
        'code' => 405,
        'message' => 'Phương thức không được hỗ trợ.'
    ]);
    exit;
}

// Lấy tham số truy vấn
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$searchQuery = trim($input['query'] ?? $_POST['query'] ?? '');

if (empty($searchQuery)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'code' => 400,
        'message' => 'Chuỗi truy vấn tìm kiếm không được để trống.'
    ]);
    exit;
}

$apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?? '';
if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'code' => 500,
        'message' => 'Hệ thống chưa cấu hình GROQ_API_KEY.'
    ]);
    exit;
}

$model = $_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?? 'llama-3.3-70b-versatile';

// 1. Dùng Groq để phân tích cú pháp tìm kiếm (Entity Extraction)
$extractionPrompt = [
    [
        'role' => 'system',
        'content' => "Bạn là công cụ trích xuất thông tin tìm kiếm phòng trọ tại TP. Vinh, Nghệ An. 
Hãy đọc yêu cầu tìm phòng của người dùng và chuyển đổi thành bộ lọc JSON có cấu trúc sau:
{
  \"ward\": \"[Tên phường ở TP. Vinh nếu được nhắc tới, ví dụ: 'Hưng Dũng', 'Bến Thủy', 'Lê Lợi', 'Quang Trung', 'Hà Huy Tập', 'Hồng Sâm', 'Đông Vĩnh', hoặc null nếu không nhắc tới]\",
  \"min_price\": [số nguyên giá tối thiểu VNĐ hoặc null],
  \"max_price\": [số nguyên giá tối đa VNĐ hoặc null],
  \"min_area\": [số nguyên diện tích tối thiểu m2 hoặc null],
  \"max_area\": [số nguyên diện tích tối đa m2 hoặc null],
  \"amenities\": [mảng chứa các tiện nghi được yêu cầu từ danh sách: ['Wifi', 'Máy lạnh', 'Nóng lạnh', 'Tủ lạnh', 'Máy giặt', 'Giường', 'Bàn ghế', 'Tủ quần áo', 'Gác lửng', 'Chỗ để xe', 'Camera an ninh', 'Tự do giờ giấc']]
}
Ví dụ: 'tìm phòng ở Hưng Dũng giá dưới 2 triệu có điều hòa và máy giặt' -> { \"ward\": \"Hưng Dũng\", \"min_price\": null, \"max_price\": 2000000, \"min_area\": null, \"max_area\": null, \"amenities\": [\"Máy lạnh\", \"Máy giặt\"] }
Chỉ trả về chuỗi JSON thô và CHỈ trả về đúng chuỗi JSON này, không bao gồm các ký tự markdown như ```json hay bất kỳ văn bản nào khác."
    ],
    [
        'role' => 'user',
        'content' => $searchQuery
    ]
];

$url = 'https://api.groq.com/openai/v1/chat/completions';
$postData = json_encode([
    'model' => $model,
    'messages' => $extractionPrompt,
    'temperature' => 0.1, // Thấp để đảm bảo tính chính xác
    'response_format' => ['type' => 'json_object']
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

// Bypass SSL verify trên môi trường Debug
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
    echo json_encode([
        'success' => false,
        'code' => 502,
        'message' => 'Lỗi kết nối AI để phân tích cú pháp: ' . $curlError
    ]);
    exit;
}

$criteria = [
    'ward' => null,
    'min_price' => null,
    'max_price' => null,
    'min_area' => null,
    'max_area' => null,
    'amenities' => []
];

if ($httpCode === 200 && $response) {
    $resArr = json_decode($response, true);
    $textRes = $resArr['choices'][0]['message']['content'] ?? '{}';
    $extracted = json_decode(trim($textRes), true);
    if ($extracted) {
        $criteria = array_merge($criteria, $extracted);
    }
}

// 2. Query cơ sở dữ liệu dựa trên criteria trích xuất được
try {
    $db = getDB();
    ensureDangbaiRoomStatusSchema($db);

    // Lấy tất cả phòng từ 2 bảng để lọc linh hoạt
    $stmt1 = $db->query("SELECT id, ten_phong, mota, hinhanh, '' as hinhanh_list, '' as video, gia, dientich, diachi, tiennghi, 'BQL' as ten_chunha, '0123456789' as sdt_chunha, ngaydang, trangthai, COALESCE(lat, 18.6923405) as lat, COALESCE(lng, 105.681627) as lng, 'phongtro' as nguon FROM phongtro");
    $rooms1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $db->query("SELECT id, tieude as ten_phong, mota, hinhanh, hinhanh_list, video, gia, dientich, diachi, tiennghi, ten_chunha, sdt_chunha, ngaydang, COALESCE(trangthai_phong, 'con_phong') as trangthai, COALESCE(lat, 18.6923405) as lat, COALESCE(lng, 105.681627) as lng, 'dangbai' as nguon FROM dangbai_chothuetro WHERE trangthai = 'da_duyet'");
    $rooms2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $allRooms = array_merge($rooms1, $rooms2);
    $matched = [];

    foreach ($allRooms as $r) {
        // Lọc theo Phường
        if (!empty($criteria['ward'])) {
            $normalizedWard = mb_strtolower($criteria['ward'], 'UTF-8');
            $normalizedAddress = mb_strtolower($r['diachi'], 'UTF-8');
            if (mb_strpos($normalizedAddress, $normalizedWard) === false) {
                // Thử tìm theo tên phường không có từ "Phường"
                $shortWard = str_replace('phường ', '', $normalizedWard);
                if (mb_strpos($normalizedAddress, $shortWard) === false) {
                    continue;
                }
            }
        }

        // Lọc theo Giá
        if ($criteria['min_price'] !== null && $r['gia'] < $criteria['min_price']) continue;
        if ($criteria['max_price'] !== null && $r['gia'] > $criteria['max_price']) continue;

        // Lọc theo Diện tích
        if ($criteria['min_area'] !== null && $r['dientich'] < $criteria['min_area']) continue;
        if ($criteria['max_area'] !== null && $r['dientich'] > $criteria['max_area']) continue;

        // Lọc theo Tiện nghi (phải khớp tất cả các tiện nghi yêu cầu)
        if (!empty($criteria['amenities']) && is_array($criteria['amenities'])) {
            $hasAll = true;
            $normalizedTienNghi = mb_strtolower($r['tiennghi'], 'UTF-8');
            foreach ($criteria['amenities'] as $am) {
                // Ánh xạ tiện nghi (ví dụ: điều hòa -> máy lạnh)
                $amClean = mb_strtolower($am, 'UTF-8');
                if ($amClean === 'điều hòa') $amClean = 'máy lạnh';
                if ($amClean === 'nóng lạnh' || $amClean === 'bình nóng lạnh') $amClean = 'nóng lạnh';
                
                if (mb_strpos($normalizedTienNghi, $amClean) === false) {
                    $hasAll = false;
                    break;
                }
            }
            if (!$hasAll) continue;
        }

        $matched[] = $r;
    }

    // Sắp xếp theo ngày đăng mới nhất
    usort($matched, function($a, $b) {
        return strtotime($b['ngaydang']) - strtotime($a['ngaydang']);
    });

    echo json_encode([
        'success' => true,
        'code' => 200,
        'message' => 'Tìm kiếm thông minh hoàn tất!',
        'data' => [
            'search_criteria' => $criteria,
            'results' => $matched,
            'total_results' => count($matched)
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'code' => 500,
        'message' => 'Lỗi truy vấn cơ sở dữ liệu: ' . $e->getMessage()
    ]);
}
