<?php
/**
 * AI Moderation Engine — Kiểm duyệt vi phạm tự động
 * Luồng: Groq → Gemini → Admin thủ công (fallback)
 * 
 * Inputs (POST JSON):
 *   - conversation_id: ID nhóm chat
 *   - content: Nội dung cần kiểm duyệt (tin nhắn hoặc nội dung tố cáo)
 *   - report_id: (optional) ID báo cáo từ user
 *   - message_id: (optional) ID tin nhắn cụ thể
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/moderation_rules.php';

// ═══ Auto-migration: Tạo bảng moderation_logs ═══
$database = new Database();
$pdo = $database->getConnection();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS moderation_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        report_id INT DEFAULT NULL,
        message_id INT DEFAULT NULL,
        ai_provider VARCHAR(20) DEFAULT NULL,
        ai_response JSON DEFAULT NULL,
        is_violation TINYINT(1) DEFAULT 0,
        severity ENUM('low','medium','high','critical') DEFAULT 'low',
        matched_rule VARCHAR(50) DEFAULT NULL,
        action_taken ENUM('none','warn','lock','delete') DEFAULT 'none',
        processed_by VARCHAR(20) DEFAULT 'ai',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conv (conversation_id),
        INDEX idx_severity (severity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$conversationId = intval($input['conversation_id'] ?? 0);
$content = trim($input['content'] ?? '');
$reportId = !empty($input['report_id']) ? intval($input['report_id']) : null;
$messageId = !empty($input['message_id']) ? intval($input['message_id']) : null;
$reportContent = trim($input['report_content'] ?? ''); // Nội dung tố cáo từ user

if ($conversationId <= 0 || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu conversation_id hoặc nội dung.']);
    exit;
}

// Kiểm tra conversation có phải nhóm không
$convCheck = $pdo->prepare("SELECT id, is_group, group_name, is_locked FROM conversations WHERE id = :id");
$convCheck->execute([':id' => $conversationId]);
$convInfo = $convCheck->fetch(PDO::FETCH_ASSOC);
if (!$convInfo) {
    echo json_encode(['success' => false, 'message' => 'Cuộc hội thoại không tồn tại.']);
    exit;
}

// ═══ Xây dựng AI Prompt ═══
$moderationPrompt = buildModerationPrompt();

$analysisPrompt = $moderationPrompt . "\n\n=== NỘI DUNG CẦN KIỂM DUYỆT ===\n";
$analysisPrompt .= "Nhóm: " . ($convInfo['group_name'] ?? 'Chat 1-1') . "\n";
$analysisPrompt .= "Nội dung tin nhắn: " . $content . "\n";

if (!empty($reportContent)) {
    $analysisPrompt .= "\nNỘI DUNG TỐ CÁO TỪ USER:\n" . $reportContent . "\n";
}

$analysisPrompt .= "\nHãy phân tích nội dung trên và trả lời theo đúng định dạng JSON đã hướng dẫn.";

// ═══ Gọi AI: Groq → Gemini → Admin fallback ═══
$aiResult = null;
$aiProvider = null;

// --- Provider 1: Groq ---
$groqKey = $_ENV['GROQ_API_KEY'] ?? '';
$groqModel = $_ENV['GROQ_MODEL'] ?? 'llama-3.3-70b-versatile';

if (!empty($groqKey) && $groqKey !== 'your_groq_api_key_here') {
    $aiResult = callGroqAI($groqKey, $groqModel, $analysisPrompt);
    if ($aiResult !== null) $aiProvider = 'groq';
}

// --- Provider 2: Gemini (fallback) ---
if ($aiResult === null) {
    $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    if (!empty($geminiKey) && $geminiKey !== 'your_gemini_api_key_here') {
        $aiResult = callGeminiAI($geminiKey, $analysisPrompt);
        if ($aiResult !== null) $aiProvider = 'gemini';
    }
}

// --- Provider 3: Admin thủ công (final fallback) ---
if ($aiResult === null) {
    // AI không khả dụng, đẩy vào hàng đợi admin
    $logStmt = $pdo->prepare("INSERT INTO moderation_logs (conversation_id, report_id, message_id, ai_provider, ai_response, is_violation, severity, action_taken, processed_by) VALUES (:conv, :report, :msg, NULL, :resp, 0, 'medium', 'none', 'pending_admin')");
    $logStmt->execute([
        ':conv' => $conversationId,
        ':report' => $reportId,
        ':msg' => $messageId,
        ':resp' => json_encode(['error' => 'Tất cả AI provider đều không khả dụng. Chuyển sang admin xử lý thủ công.'])
    ]);

    echo json_encode([
        'success' => true,
        'fallback' => 'admin',
        'message' => 'AI không khả dụng. Đã chuyển sang hàng đợi admin xử lý thủ công.',
        'moderation_log_id' => intval($pdo->lastInsertId())
    ]);
    exit;
}

// ═══ Xử lý kết quả AI ═══
$isViolation = $aiResult['is_violation'] ?? false;
$severity = $aiResult['severity'] ?? 'low';
$matchedRule = $aiResult['matched_rule'] ?? null;
$reason = $aiResult['reason'] ?? '';
$confidence = floatval($aiResult['confidence'] ?? 0);
$actionTaken = 'none';

// Quyết định hành động dựa trên severity
if ($isViolation) {
    if (in_array($severity, ['high', 'critical'])) {
        // Auto-lock nhóm
        $actionTaken = 'lock';
        if ($convInfo['is_group']) {
            $lockStmt = $pdo->prepare("UPDATE conversations SET is_locked = 1, locked_reason = :reason, locked_at = NOW(), locked_by = 'ai' WHERE id = :conv");
            $lockStmt->execute([':reason' => "[AI/{$aiProvider}] {$reason}", ':conv' => $conversationId]);
        }
    } elseif ($severity === 'medium') {
        // Cảnh cáo + gửi report cho admin review
        $actionTaken = 'warn';
    }
    // severity === 'low' → chỉ ghi log, không hành động
}

// Ghi log kiểm duyệt
$logStmt = $pdo->prepare("INSERT INTO moderation_logs (conversation_id, report_id, message_id, ai_provider, ai_response, is_violation, severity, matched_rule, action_taken, processed_by) VALUES (:conv, :report, :msg, :provider, :resp, :viol, :sev, :rule, :action, 'ai')");
$logStmt->execute([
    ':conv' => $conversationId,
    ':report' => $reportId,
    ':msg' => $messageId,
    ':provider' => $aiProvider,
    ':resp' => json_encode($aiResult),
    ':viol' => $isViolation ? 1 : 0,
    ':sev' => $severity,
    ':rule' => $matchedRule,
    ':action' => $actionTaken
]);

// Nếu report_id tồn tại, cập nhật trạng thái report
if ($reportId) {
    try {
        $updateReport = $pdo->prepare("UPDATE reports SET status = :status WHERE id = :id");
        $newStatus = $isViolation ? 'resolved' : 'reviewed';
        $updateReport->execute([':status' => $newStatus, ':id' => $reportId]);
    } catch (Exception $e) {}
}

echo json_encode([
    'success' => true,
    'provider' => $aiProvider,
    'is_violation' => $isViolation,
    'severity' => $severity,
    'matched_rule' => $matchedRule,
    'reason' => $reason,
    'confidence' => $confidence,
    'action_taken' => $actionTaken,
    'moderation_log_id' => intval($pdo->lastInsertId())
]);

// ═══════════════════════════════════════════
// HELPER FUNCTIONS: Gọi AI Providers
// ═══════════════════════════════════════════

/**
 * Gọi Groq API
 */
function callGroqAI(string $apiKey, string $model, string $prompt): ?array {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'Bạn là AI kiểm duyệt nội dung cho nền tảng Mái Nhà Xanh. Trả lời bằng JSON thuần, không kèm markdown.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.1,
        'max_tokens' => 500,
        'response_format' => ['type' => 'json_object']
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("[AI_MODERATION] Groq API failed: HTTP $httpCode — $response");
        return null;
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    
    // Parse JSON từ response
    $result = json_decode($content, true);
    if (!$result || !isset($result['is_violation'])) {
        error_log("[AI_MODERATION] Groq response parse failed: $content");
        return null;
    }

    return $result;
}

/**
 * Gọi Google Gemini API
 */
function callGeminiAI(string $apiKey, string $prompt): ?array {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;
    
    $payload = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 500,
            'responseMimeType' => 'application/json'
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("[AI_MODERATION] Gemini API failed: HTTP $httpCode — $response");
        return null;
    }

    $data = json_decode($response, true);
    $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // Loại bỏ markdown wrapper nếu có
    $content = preg_replace('/^```json\s*/', '', trim($content));
    $content = preg_replace('/\s*```$/', '', $content);
    
    $result = json_decode($content, true);
    if (!$result || !isset($result['is_violation'])) {
        error_log("[AI_MODERATION] Gemini response parse failed: $content");
        return null;
    }

    return $result;
}
?>
