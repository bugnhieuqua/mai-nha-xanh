<?php
/**
 * AI Helper for Mái Nhà Xanh
 * Handles Gemini Embedding API and Cosine Similarity calculation
 */

require_once __DIR__ . '/../config/env_loader.php';

/**
 * Lấy Vector Embedding cho một đoạn văn bản sử dụng Gemini API
 * Model: models/gemini-embedding-2 (3072 dimensions)
 * 
 * @param string $text
 * @return array|null Mảng float đại diện cho vector hoặc null nếu lỗi
 */
function getEmbedding(string $text): ?array {
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
    if (empty($apiKey)) {
        error_log("GEMINI_API_KEY is not configured.");
        return null;
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent?key=" . $apiKey;
    
    $payload = [
        'content' => [
            'parts' => [
                ['text' => $text]
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    // Bypass SSL on localhost/CLI or debug mode
    $isLocalhost = (php_sapi_name() === 'cli')
        || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'])
        || (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']))
        || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
        
    $appDebug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false';
    if ($isLocalhost || $appDebug === 'true') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    
    if ($curlError) {
        error_log("Gemini Embedding cURL error: " . $curlError);
        return null;
    }
    
    if ($httpCode !== 200) {
        error_log("Gemini Embedding API returned HTTP Code " . $httpCode . ". Response: " . $response);
        return null;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['embedding']['values'])) {
        error_log("Gemini Embedding response invalid: " . $response);
        return null;
    }
    
    return $data['embedding']['values'];
}

/**
 * Tính toán độ tương đồng Cosine giữa 2 vector cùng chiều
 * 
 * @param array $vec1
 * @param array $vec2
 * @return float Độ tương đồng từ -1.0 đến 1.0 (càng gần 1.0 càng giống)
 */
function cosineSimilarity(array $vec1, array $vec2): float {
    $dotProduct = 0.0;
    $normA = 0.0;
    $normB = 0.0;
    $count = count($vec1);
    
    // Nếu độ dài vector khác nhau hoặc trống
    if ($count === 0 || $count !== count($vec2)) {
        return 0.0;
    }
    
    for ($i = 0; $i < $count; $i++) {
        $dotProduct += $vec1[$i] * $vec2[$i];
        $normA += $vec1[$i] * $vec1[$i];
        $normB += $vec2[$i] * $vec2[$i];
    }
    
    if ($normA == 0 || $normB == 0) {
        return 0.0;
    }
    
    return $dotProduct / (sqrt($normA) * sqrt($normB));
}
?>
