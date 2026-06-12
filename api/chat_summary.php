<?php
header('Content-Type: application/json');

require_once '../config/session.php';
require_once '../config/database.php';
require_once 'rate_limit.php';
checkRateLimit('chat_summary', 5, 300); // 5 summaries/5 phút

$session = $_GET['session'] ?? '';
if (empty($session)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu session ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Lấy lịch sử chat của session này
    $stmt = $db->prepare("
        SELECT created_at, user_message, bot_response,
               DATE_FORMAT(created_at, '%Y-%m') as month_key,
               CONCAT('Q', QUARTER(created_at), '/', YEAR(created_at)) as quarter_key
        FROM chatbot_history 
        WHERE session_id = :session 
        ORDER BY created_at DESC 
        LIMIT 200  -- Giới hạn để tránh tốn token
    ");
    $stmt->bindParam(':session', $session);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($history)) {
        echo json_encode(['success' => true, 'summary' => '']);
        exit;
    }

    // Gom nhóm theo tháng và quý
    $monthly = [];
    $quarterly = [];
    
    foreach ($history as $row) {
        $month = $row['month_key'];
        $quarter = $row['quarter_key'];
        
        if (!isset($monthly[$month])) $monthly[$month] = [];
        $monthly[$month][] = "U: " . trim($row['user_message']) . " | B: " . trim($row['bot_response']);
        
        if (!isset($quarterly[$quarter])) $quarterly[$quarter] = [];
        $quarterly[$quarter][] = "U: " . trim($row['user_message']);
    }

    // Tạo raw text cho Gemini summarize
    $monthly_text = '';
    foreach (array_slice(array_reverse($monthly), 0, 6) as $month => $msgs) {  // 6 tháng gần nhất
        $month_text = implode(' | ', array_slice($msgs, 0, 10));  // 10 tin gần nhất
        $monthly_text .= "*{$month}:* {$month_text}\n";
    }

    $quarter_text = '';
    foreach (array_reverse($quarterly) as $q => $msgs) {
        $q_summary = implode(' | ', array_slice($msgs, -5));  // 5 tin cuối mỗi quý
        $quarter_text .= "*{$q}:* {$q_summary}\n";
    }

    $full_history = $monthly_text . "\n" . $quarter_text;

    // Gọi Groq API để tạo summary
    require_once '../config/env_loader.php';
    $apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?? '';
    
    if (empty($apiKey) || $apiKey === 'gsk_your_groq_api_key_here') {
        throw new Exception("Chưa cấu hình GROQ_API_KEY");
    }

    $model = $_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?? 'llama-3.3-70b-versatile';
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    
    $prompt = "TÓM TẮT LỊCH SỬ CHAT (Mai Nha Xanh - phòng trọ Vinh):\n\n{$full_history}\n\n
    Tạo summary ngắn gọn theo format:
    *Tháng MM/YYYY:* [1 câu về sở thích phòng/giá/khu vực]
    *Quý Q/YYYY:* [Tóm tắt tổng quát]
    Chỉ key interests: giá, diện tích, tiện ích, vị trí. Max 300 ký tự.";

    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.5,
        'max_tokens' => 2000
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
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

    
    $summary = 'No summary generated';
    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            $summary = $data['choices'][0]['message']['content'];
        }
    }

} catch (Exception $e) {
    $summary = '';  // Fallback to empty
}

echo json_encode([
    'success' => true, 
    'summary' => trim($summary),
    'history_count' => count($history)
]);
?>

