<?php
/**
 * RESTful RAG Chatbot API v2 (OpenAI Format Compatible with Tool Calling)
 * POST /api/v2/assistant_proxy.php
 * Body: Base64 Encoded JSON string matching { "messages": [...] } (WAF Bypass) or raw JSON
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/room_status_helper.php';
require_once __DIR__ . '/../../includes/ai_helper.php';

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
$appDebug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false';

// 2. Load System Prompt cốt lõi của chatbot
$systemPromptBase = '';
if (file_exists(__DIR__ . '/../../config/chatbot_prompt.php')) {
    $systemPromptBase = require __DIR__ . '/../../config/chatbot_prompt.php';
} else {
    $systemPromptBase = "Bạn là Trợ lý AI thông minh của Mái Nhà Xanh tại TP. Vinh, Nghệ An.";
}

// Bổ sung hướng dẫn thẻ phòng trọ [ROOM:nguon:id] vào system prompt để LLM ghi nhớ
$systemPrompt = $systemPromptBase . "\n\n" .
    "CÚ PHÁP HIỂN THỊ PHÒNG TRỌ (CỰC KỲ QUAN TRỌNG):\n" .
    "Khi giới thiệu bất kỳ phòng trọ nào từ kết quả tìm kiếm (tool search_rooms_semantic), bạn BẮT BUỘC phải chèn thẻ dạng [ROOM:nguon:id] (Ví dụ: [ROOM:phongtro:44] hoặc [ROOM:dangbai:25]) vào ngay cuối mô tả hoặc vị trí thích hợp của phòng đó. Điều này giúp hệ thống render thành thẻ phòng trực quan đẹp mắt. KHÔNG tự chế ID phòng và không bỏ quên thẻ này.\n\n" .
    "HƯỚNG DẪN GỌI HÀM (TOOL CALLING):\n" .
    "- CHỈ gọi hàm `search_rooms_semantic` khi người dùng thực sự muốn tìm kiếm hoặc hỏi về phòng trọ.\n" .
    "- Nếu người dùng chỉ nói các từ chung chung như 'chi tiết', 'xem thêm', 'ok', 'chào bạn' mà chưa có nhu cầu tìm phòng cụ thể, hãy phản hồi tự nhiên để hỏi thêm thông tin thay vì gọi hàm vô ích.\n" .
    "- Khi gọi hàm, tuyệt đối không bọc các đối số trong markdown code blocks hay trả về các ký tự định dạng thừa.";

// 3. Chuẩn bị danh sách tin nhắn gửi đến Groq
$groqMessages = [];
$groqMessages[] = ['role' => 'system', 'content' => $systemPrompt];

// Thêm lịch sử hội thoại (bỏ các tin nhắn system cũ nếu có để tránh nhiễu prompt)
foreach ($payload['messages'] as $msg) {
    if ($msg['role'] === 'system')
        continue;

    // Convert 'model' role from Gemini/Frontend to 'assistant' for Groq
    $role = $msg['role'] === 'model' ? 'assistant' : $msg['role'];
    $content = $msg['content'] ?? ($msg['parts'][0]['text'] ?? '');

    $groqMessages[] = ['role' => $role, 'content' => $content];
}

// 4. Khai báo công cụ (Tools) cho Groq API
$tools = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'search_rooms_semantic',
            'description' => 'Tìm kiếm phòng trọ bằng ngữ nghĩa (Vector Search) kết hợp với các bộ lọc giá và phường tại TP. Vinh.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Nhu cầu tìm kiếm phòng trọ của người dùng bằng ngôn ngữ tự nhiên, ví dụ: "yên tĩnh, an ninh, sạch sẽ", "gần trường học có máy giặt".'
                    ],
                    'max_price' => [
                        'type' => 'string',
                        'description' => 'Giá thuê tối đa mong muốn (VNĐ/tháng), ví dụ: "2000000" hoặc "2 triệu".'
                    ],
                    'ward' => [
                        'type' => 'string',
                        'description' => 'Tên phường cụ thể tại TP. Vinh. CHỈ điền trường này nếu người dùng nhắc đến TÊN PHƯỜNG cụ thể trong câu hỏi (ví dụ: "ở Bến Thủy", "tại phường Lê Lợi"). Không tự suy luận.'
                    ]
                ],
                'required' => []
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_room_statistics',
            'description' => 'Lấy thống kê, đếm phòng trọ theo các tiêu chí khác nhau (tổng số phòng, theo trạng thái, giá, v.v.)',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['count_total', 'count_by_status', 'min_price', 'max_price', 'avg_price', 'count_by_ward', 'get_room_list'],
                        'description' => 'Hành động thống kê cần thực hiện: count_total (tổng số phòng), count_by_status (phòng theo trạng thái), min_price (giá thấp nhất), max_price (giá cao nhất), avg_price (giá trung bình), count_by_ward (phòng theo phường), get_room_list (liệt kê phòng).'
                    ],
                    'filter_status' => [
                        'type' => 'string',
                        'enum' => ['con_phong', 'da_coc', 'da_thue'],
                        'description' => 'Bộ lọc trạng thái phòng (chỉ dùng với action get_room_list): con_phong (còn trống), da_coc (đã cọc), da_thue (đã thuê).'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Số lượng phòng tối đa cần lấy (mặc định 5, tối đa 10). Chỉ dùng với action get_room_list.'
                    ],
                    'sort_by' => [
                        'type' => 'string',
                        'enum' => ['ngaydang', 'gia', 'dientich'],
                        'description' => 'Sắp xếp theo: ngaydang (ngày đăng), gia (giá), dientich (diện tích). Chỉ dùng với action get_room_list.'
                    ]
                ],
                'required' => ['action']
            ]
        ]
    ]
];

// 5. Gọi Groq API lần 1
$url = 'https://api.groq.com/openai/v1/chat/completions';
$postData = [
    'model' => $model,
    'messages' => $groqMessages,
    'temperature' => 0.7,
    'max_tokens' => 2048,
    'tools' => $tools,
    'tool_choice' => 'auto'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
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

if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Kết nối đến AI thất bại (Vòng 1): ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    error_log("Groq API Error (Round 1): Status code: $httpCode. Response: " . $response);
    // Tránh trả về HTML/text từ hosting/gateway khi frontend mong đợi JSON
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'maintenance' => true,
        'message' => 'Hệ thống AI đang bảo trì. Vui lòng thử lại sau ít phút.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);
$choice = $data['choices'][0] ?? null;

// 6. Kiểm tra xem LLM có yêu cầu gọi Tool (Function Calling) không
if ($choice && isset($choice['message']['tool_calls']) && !empty($choice['message']['tool_calls'])) {

    // Thêm tin nhắn assistant chứa yêu cầu gọi tool vào lịch sử
    $assistantMessage = $choice['message'];
    $groqMessages[] = $assistantMessage;

    // Thực thi từng tool call (ở đây chủ yếu có 1 tool tìm kiếm)
    foreach ($choice['message']['tool_calls'] as $toolCall) {
        if ($toolCall['function']['name'] === 'search_rooms_semantic') {
            $args = json_decode($toolCall['function']['arguments'], true) ?: [];

            $query = $args['query'] ?? '';
            $maxPrice = null;
            if (isset($args['max_price']) && $args['max_price'] !== '') {
                $maxPrice = parsePriceString(strval($args['max_price']));
            }
            $ward = $args['ward'] ?? null;

            // Thực thi tìm kiếm ngữ nghĩa
            $searchResult = execute_semantic_search($query, $maxPrice, $ward);

            // Thêm kết quả thực thi tool vào danh sách tin nhắn
            $groqMessages[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCall['id'],
                'name' => 'search_rooms_semantic',
                'content' => $searchResult
            ];
        } elseif ($toolCall['function']['name'] === 'get_room_statistics') {
            $args = json_decode($toolCall['function']['arguments'], true) ?: [];

            $action = $args['action'] ?? 'count_total';
            $filter_status = $args['filter_status'] ?? null;
            $limit = $args['limit'] ?? 5;
            $sort_by = $args['sort_by'] ?? 'ngaydang';

            // Gọi API để lấy dữ liệu thống kê
            $statsResult = execute_room_statistics($action, $filter_status, $limit, $sort_by);

            // Thêm kết quả thực thi tool vào danh sách tin nhắn
            $groqMessages[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCall['id'],
                'name' => 'get_room_statistics',
                'content' => $statsResult
            ];
        }
    }

    // Gọi Groq API lần 2 để nhận câu trả lời cuối cùng
    $postData2 = [
        'model' => $model,
        'messages' => $groqMessages,
        'temperature' => 0.7,
        'max_tokens' => 2048
    ];

    // Tận dụng cURL handle hiện tại
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData2));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($curlError) {
        http_response_code(502);
        echo json_encode(['error' => 'Kết nối đến AI thất bại (Vòng 2): ' . $curlError]);
        exit;
    }

    if ($httpCode === 200) {
        $resArray = json_decode($response, true);
        if ($resArray) {
            $resArray['matched_rooms'] = $GLOBALS['chatbot_matched_rooms'] ?? [];
            echo json_encode($resArray);
            exit;
        }
    }
    error_log("Groq API Error (Round 2): Status code: $httpCode. Response: " . $response);
    http_response_code($httpCode);
    echo $response;
    exit;
}

// Nếu không gọi Tool, trả về phản hồi gốc luôn (Single-Turn phản hồi thẳng)
if ($httpCode === 200) {
    $resArray = json_decode($response, true);
    if ($resArray) {
        $resArray['matched_rooms'] = $GLOBALS['chatbot_matched_rooms'] ?? [];
        echo json_encode($resArray);
        exit;
    }
}
http_response_code($httpCode);
echo $response;
exit;

/**
 * Thực thi truy vấn tìm kiếm Vector và Cosine Similarity
 */
function execute_semantic_search(string $query, ?float $maxPrice, ?string $ward): string
{
    $db = getDB();
    if (!$db) {
        return "Lỗi: Không thể kết nối cơ sở dữ liệu.";
    }

    $query = trim($query);
    $queryVector = null;

    if (empty($query) && $maxPrice === null && empty($ward)) {
        return "Hãy hỏi tôi cụ thể hơn về nhu cầu của bạn (ví dụ: phòng trọ ở khu vực nào, giá khoảng bao nhiêu, có tiện nghi gì).";
    }

    if (!empty($query)) {
        // 1. Sinh vector embedding cho truy vấn của người dùng
        $queryVector = getEmbedding($query);
        if ($queryVector === null) {
            return "Lỗi: Không thể sinh vector embedding cho truy vấn tìm kiếm này.";
        }
    }

    // Nhận diện nếu câu hỏi có chứa các từ khóa tìm phòng TRỐNG / CÒN PHÒNG
    $lookForVacantOnly = false;
    $cleanQuery = mb_strtolower($query, 'UTF-8');
    if (
        mb_strpos($cleanQuery, 'trống') !== false ||
        mb_strpos($cleanQuery, 'còn phòng') !== false ||
        mb_strpos($cleanQuery, 'chứa thuê') !== false ||
        mb_strpos($cleanQuery, 'chưa thuê') !== false ||
        mb_strpos($cleanQuery, 'sẵn sàng') !== false ||
        mb_strpos($cleanQuery, 'trong') !== false ||
        mb_strpos($cleanQuery, 'con phong') !== false
    ) {
        $lookForVacantOnly = true;
    }

    try {
        // 2. Query tách biệt hai bảng để tránh lỗi Collation Mismatch trong UNION ALL
        $stmt1 = $db->query("
            SELECT r.room_id, r.source, r.embedding,
                   p.ten_phong, p.mota, p.hinhanh, p.gia, p.dientich, p.diachi, p.tiennghi, p.trangthai as trangthai_phong
            FROM room_embeddings r
            JOIN phongtro p ON r.room_id = p.id AND r.source = 'phongtro'
        ");
        $rooms1 = $stmt1->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt2 = $db->query("
            SELECT r.room_id, r.source, r.embedding,
                   d.tieude as ten_phong, d.mota, d.hinhanh, d.gia, d.dientich, d.diachi, d.tiennghi, d.trangthai_phong
            FROM room_embeddings r
            JOIN dangbai_chothuetro d ON r.room_id = d.id AND r.source = 'dangbai' AND d.trangthai = 'da_duyet'
        ");
        $rooms2 = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rooms = array_merge($rooms1, $rooms2);

        if (empty($rooms)) {
            return "Thông báo: Hiện tại chưa có dữ liệu phòng trọ nào được nạp vector index.";
        }

        $scoredRooms = [];
        $scoredRoomsNoWard = []; // Fallback list nếu bộ lọc phường bị loại bỏ hết
        foreach ($rooms as $r) {
            $roomVector = json_decode($r['embedding'], true);

            // Lọc theo tình trạng phòng trống nếu người dùng yêu cầu phòng trống
            $status = normalizeRoomStatusValue($r['trangthai_phong'] ?? 'con_phong');
            if ($lookForVacantOnly && $status !== 'con_phong') {
                continue;
            }

            // Lọc theo giá
            if ($maxPrice !== null && $r['gia'] > $maxPrice) {
                continue;
            }

            // Tính cosine similarity
            if ($queryVector !== null && !empty($roomVector) && is_array($roomVector)) {
                $score = cosineSimilarity($queryVector, $roomVector);
            } else {
                $score = 1.0; // Điểm tối đa mặc định nếu lọc thuần thuộc tính
            }
            $r['similarity'] = $score;

            // Thêm vào danh sách không lọc phường làm fallback
            $scoredRoomsNoWard[] = $r;

            // Lọc theo phường
            if (!empty($ward)) {
                $normalizedWard = mb_strtolower($ward, 'UTF-8');
                $normalizedAddress = mb_strtolower($r['diachi'], 'UTF-8');
                if (mb_strpos($normalizedAddress, $normalizedWard) === false) {
                    $shortWard = str_replace('phường ', '', $normalizedWard);
                    if (mb_strpos($normalizedAddress, $shortWard) === false) {
                        continue;
                    }
                }
            }

            $scoredRooms[] = $r;
        }

        // Fallback: nếu lọc phường làm danh sách rỗng, nhưng danh sách không lọc phường vẫn có kết quả
        if (empty($scoredRooms) && !empty($scoredRoomsNoWard)) {
            $scoredRooms = $scoredRoomsNoWard;
        }

        // Sắp xếp theo độ tương đồng giảm dần
        usort($scoredRooms, function ($a, $b) {
            if ($a['similarity'] == $b['similarity'])
                return 0;
            return ($a['similarity'] > $b['similarity']) ? -1 : 1;
        });

        // Chỉ lấy top 3 phòng khớp nhất
        $topRooms = array_slice($scoredRooms, 0, 3);
        if (empty($topRooms)) {
            return "Thông báo: Không tìm thấy phòng trọ nào thỏa mãn bộ lọc giá/khu vực.";
        }

        // Sinh coordinates cho từng phòng để vẽ map ở frontend
        foreach ($topRooms as &$tr) {
            $tr['coords'] = getApproximateCoords($tr['diachi'], $tr['room_id'], $tr['lat'] ?? null, $tr['lng'] ?? null, $tr['source'] ?? 'phongtro');
        }
        unset($tr);

        $GLOBALS['chatbot_matched_rooms'] = $topRooms;

        // Định dạng dữ liệu gửi lại cho LLM
        $resultText = "Dưới đây là các phòng trọ khớp nhất tìm được bằng Vector Search:\n";
        foreach ($topRooms as $tr) {
            $statusVal = normalizeRoomStatusValue($tr['trangthai_phong'] ?? 'con_phong');
            $statusText = 'Còn phòng';
            if ($statusVal === 'da_coc') {
                $statusText = 'Đã đặt cọc';
            } elseif ($statusVal === 'da_thue') {
                $statusText = 'Đã thuê';
            }

            $resultText .= "- ID: " . $tr['room_id'] . "\n";
            $resultText .= "  Nguồn: " . $tr['source'] . "\n";
            $resultText .= "  Tiêu đề: " . $tr['ten_phong'] . "\n";
            $resultText .= "  Giá: " . number_format($tr['gia']) . " VNĐ/tháng\n";
            $resultText .= "  Diện tích: " . $tr['dientich'] . " m2\n";
            $resultText .= "  Địa chỉ: " . $tr['diachi'] . "\n";
            $resultText .= "  Tiện nghi: " . $tr['tiennghi'] . "\n";
            $resultText .= "  Tình trạng phòng: " . $statusText . "\n";
            $resultText .= "  Mô tả: " . mb_substr(strip_tags($tr['mota']), 0, 120) . "...\n";
            $resultText .= "  Độ khớp ngữ nghĩa: " . round($tr['similarity'] * 100, 1) . "%\n";
            $resultText .= "  Thẻ hiển thị card bắt buộc dùng khi tư vấn: [ROOM:" . $tr['source'] . ":" . $tr['room_id'] . "]\n\n";
        }

        return $resultText;

    } catch (Exception $e) {
        return "Lỗi thực thi truy vấn tìm kiếm: " . $e->getMessage();
    }
}

/**
 * Hàm phân tích giá dạng chuỗi (2tr, 2 triệu, 2000000) thành số float
 */
function parsePriceString(string $priceStr): float
{
    $clean = str_replace([' ', ',', '.', 'đ', 'vnd', 'vnđ', '/tháng', '/thang'], '', mb_strtolower($priceStr, 'UTF-8'));

    if (mb_strpos($clean, 'triệu') !== false) {
        $num = floatval(str_replace('triệu', '', $clean));
        return $num * 1000000;
    }
    if (mb_strpos($clean, 'tr') !== false) {
        $num = floatval(str_replace('tr', '', $clean));
        return $num * 1000000;
    }
    if (mb_strpos($clean, 'nghìn') !== false) {
        $num = floatval(str_replace('nghìn', '', $clean));
        return $num * 1000;
    }
    if (mb_strpos($clean, 'nghin') !== false) {
        $num = floatval(str_replace('nghin', '', $clean));
        return $num * 1000;
    }
    if (mb_strpos($clean, 'k') !== false) {
        $num = floatval(str_replace('k', '', $clean));
        return $num * 1000;
    }

    return floatval($clean);
}

/**
 * Thực thi truy vấn thống kê phòng bằng cách gọi API chatbot_room_stats.php
 */
function execute_room_statistics(string $action, ?string $filterStatus, int $limit, string $sortBy): string
{
    $url = 'http://' . $_SERVER['HTTP_HOST'] . '/api/v2/chatbot_room_stats.php';

    $payload = [
        'action' => $action,
        'filter_status' => $filterStatus,
        'limit' => $limit,
        'sort_by' => $sortBy
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return "Lỗi: Không thể kết nối đến dịch vụ thống kê phòng (" . $error . ").";
    }

    if ($httpCode !== 200) {
        error_log("Room Stats API Error: HTTP " . $httpCode . " - " . $response);
        return "Lỗi: Dịch vụ thống kê phòng trả về lỗi (HTTP " . $httpCode . ").";
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['success'])) {
        return "Lỗi: Phản hồi thống kê phòng không hợp lệ.";
    }

    if (!$data['success']) {
        return "Lỗi thống kê: " . ($data['error'] ?? 'Lỗi không xác định');
    }

    // Kiểm tra xem có message trong kết quả không
    if (isset($data['message'])) {
        return $data['message'];
    }

    // Nếu là danh sách phòng (action: get_room_list)
    if ($action === 'get_room_list' && isset($data['rooms'])) {
        $result = "Danh sách phòng trọ:\n";
        foreach ($data['rooms'] as $room) {
            $giaFmt = number_format($room['gia']);
            $result .= "- **" . htmlspecialchars($room['ten_phong']) . "** | " . htmlspecialchars($room['diachi']) . " | " . $giaFmt . "đ/tháng | " . $room['dientich'] . "m² | [ROOM:" . $room['nguon'] . ":" . $room['id'] . "]\n";
        }
        return $result;
    }

    return "Không thể xử lý kết quả thống kê.";
}
?>