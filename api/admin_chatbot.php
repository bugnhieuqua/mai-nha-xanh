<?php
/**
 * Admin AI Chatbot API
 * Chatbot dành riêng cho admin - trích xuất dữ liệu & quản lý bài đăng bằng ngôn ngữ tự nhiên.
 * Hỗ trợ đa provider AI (Groq, OpenAI, DeepSeek, Gemini) với cơ chế fallback.
 */

// Xử lý CORS khi Frontend & Backend ở domain khác nhau (Render / Hosting)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
if ($origin !== '*') {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/env_loader.php';

// Chỉ Admin mới được dùng
requireLogin('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input && !empty($_POST)) {
    $input = $_POST;
    if (isset($_POST['history']) && is_string($_POST['history'])) {
        $input['history'] = json_decode($_POST['history'], true) ?: [];
    }
}

if (!$input || empty($input['message'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu nội dung tin nhắn']);
    exit;
}

$userMessage = trim($input['message']);
$history = $input['history'] ?? [];

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'message' => 'Tin nhắn trống']);
    exit;
}

// Khởi tạo DB
$database = new Database();
$db = $database->getConnection();

// ── Hàm lấy dữ liệu từ database ───────────────────────────────────────────
function getPostsSummary(PDO $db): array {
    $stats = [];
    $stmt = $db->query("
        SELECT 
            trangthai,
            COUNT(*) as count,
            AVG(gia) as avg_price,
            MIN(gia) as min_price,
            MAX(gia) as max_price
        FROM dangbai_chothuetro 
        GROUP BY trangthai
    ");
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM dangbai_chothuetro");
    $stats['total'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) as today FROM dangbai_chothuetro WHERE DATE(ngaydang) = CURDATE()");
    $stats['today'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(DISTINCT nguoidang) as total_posters FROM dangbai_chothuetro");
    $stats['total_posters'] = $stmt->fetchColumn();
    
    return $stats;
}

function getPendingPosts(PDO $db, int $limit = 10): array {
    $stmt = $db->prepare("
        SELECT id, tieude, diachi, gia, dientich, nguoidang, ngaydang, trangthai, mota
        FROM dangbai_chothuetro 
        WHERE trangthai = 'cho_duyet'
        ORDER BY ngaydang DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllPosts(PDO $db, int $limit = 20, string $status = ''): array {
    $where = '1=1';
    $params = [];
    if ($status && in_array($status, ['cho_duyet', 'da_duyet', 'tu_choi'])) {
        $where .= " AND trangthai = :status";
        $params[':status'] = $status;
    }
    $stmt = $db->prepare("
        SELECT id, tieude, diachi, gia, dientich, nguoidang, ngaydang, trangthai
        FROM dangbai_chothuetro 
        WHERE $where
        ORDER BY ngaydang DESC
        LIMIT :lim
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchPosts(PDO $db, string $keyword, int $limit = 10): array {
    $stmt = $db->prepare("
        SELECT id, tieude, diachi, gia, dientich, nguoidang, ngaydang, trangthai
        FROM dangbai_chothuetro 
        WHERE tieude LIKE :kw OR diachi LIKE :kw2 OR nguoidang LIKE :kw3 OR mota LIKE :kw4
        ORDER BY ngaydang DESC
        LIMIT :lim
    ");
    $kw = "%$keyword%";
    $stmt->bindValue(':kw', $kw);
    $stmt->bindValue(':kw2', $kw);
    $stmt->bindValue(':kw3', $kw);
    $stmt->bindValue(':kw4', $kw);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPostById(PDO $db, int $id): ?array {
    $stmt = $db->prepare("
        SELECT id, tieude, diachi, gia, dientich, nguoidang, ngaydang, trangthai, mota, sdt_chunha
        FROM dangbai_chothuetro 
        WHERE id = :id
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Lấy thống kê người dùng, bắt lỗi nếu bảng users chưa có cột ngaydang
 */
function getUserStats(PDO $db): array {
    $result = ['total' => 0, 'today' => 0, 'top_posters' => []];
    try {
        // Tổng người dùng đăng ký trong hệ thống (Loại trừ tài khoản Admin)
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin' OR role IS NULL");
        $result['total'] = (int)$stmt->fetchColumn();

        // Người dùng đăng ký hôm nay (Loại trừ Admin)
        try {
            $stmt = $db->query("SELECT COUNT(*) as today FROM users WHERE (role != 'admin' OR role IS NULL) AND DATE(ngaydang) = CURDATE()");
            $result['today'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            try {
                $stmt = $db->query("SELECT COUNT(*) as today FROM users WHERE (role != 'admin' OR role IS NULL) AND DATE(created_at) = CURDATE()");
                $result['today'] = (int)$stmt->fetchColumn();
            } catch (PDOException $e2) {
                $result['today'] = 0;
            }
        }

        // Top người đăng bài (Loại trừ Admin, chỉ xếp hạng Thành viên/Chủ nhà)
        $stmt = $db->query("
            SELECT d.nguoidang as username, MAX(u.hoten) as hoten, MAX(u.avatar) as avatar, COUNT(*) as post_count 
            FROM dangbai_chothuetro d
            INNER JOIN users u ON d.nguoidang = u.username
            WHERE u.role != 'admin' OR u.role IS NULL
            GROUP BY d.nguoidang 
            ORDER BY post_count DESC 
            LIMIT 5
        ");
        $result['top_posters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getUserStats PDO Error: " . $e->getMessage());
    }
    return $result;
}

function approvePost(PDO $db, int $id): array {
    $post = getPostById($db, $id);
    if (!$post) return ['success' => false, 'message' => "Không tìm thấy bài đăng #$id"];
    
    $stmt = $db->prepare("
        UPDATE dangbai_chothuetro 
        SET trangthai = 'da_duyet',
            trangthai_phong = CASE WHEN trangthai_phong IS NULL OR trangthai_phong = '' THEN 'con_phong' ELSE trangthai_phong END,
            admin_note = '',
            duyet_luc = NOW()
        WHERE id = :id
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return ['success' => true, 'message' => "✅ Đã duyệt bài #{$id}: \"{$post['tieude']}\"", 'post' => $post];
}

function rejectPost(PDO $db, int $id, string $reason = ''): array {
    $post = getPostById($db, $id);
    if (!$post) return ['success' => false, 'message' => "Không tìm thấy bài đăng #$id"];
    
    $stmt = $db->prepare("
        UPDATE dangbai_chothuetro 
        SET trangthai = 'tu_choi', admin_note = :note, duyet_luc = NOW()
        WHERE id = :id
    ");
    $stmt->bindValue(':note', $reason);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return ['success' => true, 'message' => "🚫 Đã từ chối bài #{$id}: \"{$post['tieude']}\"" . ($reason ? " (Lý do: $reason)" : ''), 'post' => $post];
}

function deletePost(PDO $db, int $id): array {
    $post = getPostById($db, $id);
    if (!$post) return ['success' => false, 'message' => "Không tìm thấy bài đăng #$id"];
    
    // Xóa ảnh nếu có
    $stmtFiles = $db->prepare("SELECT hinhanh, hinhanh_list, video FROM dangbai_chothuetro WHERE id = :id");
    $stmtFiles->bindValue(':id', $id, PDO::PARAM_INT);
    $stmtFiles->execute();
    $row = $stmtFiles->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if (!empty($row['hinhanh']) && file_exists('../' . $row['hinhanh'])) unlink('../' . $row['hinhanh']);
        if (!empty($row['video']) && file_exists('../' . $row['video'])) unlink('../' . $row['video']);
    }
    
    try { $db->prepare("DELETE FROM reports WHERE reported_post_id = :id")->execute([':id' => $id]); } catch (Exception $e) {}
    
    $stmt = $db->prepare("DELETE FROM dangbai_chothuetro WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return ['success' => true, 'message' => "🗑️ Đã xóa vĩnh viễn bài #{$id}: \"{$post['tieude']}\"", 'post' => $post];
}

// ── Lấy ngữ cảnh database để cung cấp cho AI ──────────────────────────────
$summary = getPostsSummary($db);
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
foreach ($summary['by_status'] as $row) {
    if ($row['trangthai'] === 'cho_duyet') $pendingCount = $row['count'];
    if ($row['trangthai'] === 'da_duyet') $approvedCount = $row['count'];
    if ($row['trangthai'] === 'tu_choi') $rejectedCount = $row['count'];
}

// System prompt cho AI
$systemPrompt = <<<PROMPT
Bạn là AI Assistant dành riêng cho Admin của website "Mái Nhà Xanh" — nền tảng cho thuê phòng trọ.
Bạn có khả năng trả lời câu hỏi về dữ liệu hệ thống VÀ thực hiện hành động quản lý bài đăng theo lệnh admin.

**THỐNG KÊ HIỆN TẠI (cập nhật realtime):**
- Tổng bài đăng: {$summary['total']}
- Bài chờ duyệt: $pendingCount
- Bài đã duyệt: $approvedCount
- Bài từ chối: $rejectedCount
- Bài đăng hôm nay: {$summary['today']}
- Số người đăng bài: {$summary['total_posters']}

**HÀNH ĐỘNG BẠN CÓ THỂ THỰC HIỆN:**
Khi admin yêu cầu thực hiện hành động với bài đăng, hãy phân tích và TRẢ VỀ JSON STRUCTURED theo định dạng sau ở cuối phản hồi (sau phần giải thích):

Để DỰ KIỆN thực hiện một hành động, hãy trả về JSON block được bao bọc trong cặp thẻ <ACTION> và </ACTION>:
<ACTION>{"action": "approve", "id": 123}</ACTION>
<ACTION>{"action": "reject", "id": 123, "reason": "Lý do từ chối"}</ACTION>
<ACTION>{"action": "delete", "id": 123}</ACTION>
<ACTION>{"action": "get_pending", "limit": 10}</ACTION>
<ACTION>{"action": "get_all", "status": "cho_duyet", "limit": 20}</ACTION>
<ACTION>{"action": "search", "keyword": "từ khóa", "limit": 10}</ACTION>
<ACTION>{"action": "get_stats"}</ACTION>
<ACTION>{"action": "get_user_stats"}</ACTION>
<ACTION>{"action": "get_post", "id": 123}</ACTION>
<ACTION>{"action": "export_excel", "type": "posters"}</ACTION>
<ACTION>{"action": "export_excel", "type": "posts", "status": "tu_choi"}</ACTION>
<ACTION>{"action": "export_excel", "type": "posts", "status": "cho_duyet"}</ACTION>
<ACTION>{"action": "export_excel", "type": "posts", "status": "da_duyet"}</ACTION>
<ACTION>{"action": "export_excel", "type": "posts", "status": "all"}</ACTION>
<ACTION>{"action": "export_excel", "type": "users"}</ACTION>

**QUY TẮC PHẢN HỒI NGUYÊN TẮC (CRITICAL):**
1. **MỌI TRUY VẤN, TRA CỨU, THỐNG KÊ, XUẤT EXCEL**: Trả lời ngay lập tức một cách thuần thục, mượt mà, tự nhiên và ĐÍNH KÈM THẺ `<ACTION>` tương ứng ngay trong phản hồi. KHÔNG BAO GIỜ bắt người dùng xác nhận, KHÔNG hỏi đi hỏi lại.
2. **XÁC NHẬN CÓ ĐIỀU KIỆN (CONFIRMATION)**: CHỈ ÁP DỤNG khi thực hiện hành động THAY ĐỔI hoặc XÓA DỮ LIỆU (như Duyệt bài `approve`, Từ chối `reject`, Xóa bài `delete`).
3. **TUYỆT ĐỐI KHÔNG TIẾT LỘ TÊN HÀM/MÃ KỸ THUẬT**: KHÔNG BAO GIỜ in ra các cụm từ như `get_user_stats`, `get_pending`, `export_excel`, `ACTION` hay tên biến hệ thống trong câu trả lời cho Admin. Trả lời bằng tiếng Việt tự nhiên, lịch sự.
PROMPT;

// ── Build messages cho AI ────────────────────────────────────────────────────
$messages = [['role' => 'system', 'content' => $systemPrompt]];

// Thêm lịch sử hội thoại (giới hạn 20 tin nhắn gần nhất)
$recentHistory = array_slice($history, -20);
foreach ($recentHistory as $msg) {
    if (!empty($msg['role']) && !empty($msg['content'])) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

// ── Cấu hình danh sách AI Providers (ưu tiên theo thứ tự) ──────────────────
$providers = [];

// 1. Groq
$groqKey = rtrim((string)(getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '')));
$groqModel = rtrim((string)(getenv('GROQ_MODEL') ?: ($_ENV['GROQ_MODEL'] ?? 'llama-3.3-70b-versatile')));
if (!empty($groqKey)) {
    $providers[] = [
        'name'  => 'Groq',
        'url'   => 'https://api.groq.com/openai/v1/chat/completions',
        'key'   => $groqKey,
        'model' => $groqModel,
        'type'  => 'openai_compatible'
    ];
}

// 2. OpenAI
$openaiKey = rtrim((string)(getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '')));
$openaiModel = rtrim((string)(getenv('OPENAI_MODEL') ?: ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini')));
if (!empty($openaiKey)) {
    $providers[] = [
        'name'  => 'OpenAI',
        'url'   => 'https://api.openai.com/v1/chat/completions',
        'key'   => $openaiKey,
        'model' => $openaiModel,
        'type'  => 'openai_compatible'
    ];
}

// 3. DeepSeek
$deepseekKey = rtrim((string)(getenv('DEEPSEEK_API_KEY') ?: ($_ENV['DEEPSEEK_API_KEY'] ?? '')));
$deepseekModel = rtrim((string)(getenv('DEEPSEEK_MODEL') ?: ($_ENV['DEEPSEEK_MODEL'] ?? 'deepseek-chat')));
if (!empty($deepseekKey)) {
    $providers[] = [
        'name'  => 'DeepSeek',
        'url'   => 'https://api.deepseek.com/v1/chat/completions',
        'key'   => $deepseekKey,
        'model' => $deepseekModel,
        'type'  => 'openai_compatible'
    ];
}

// 4. Gemini (có cú pháp riêng)
$geminiKey = rtrim((string)(getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '')));
$geminiModel = rtrim((string)(getenv('GEMINI_MODEL') ?: ($_ENV['GEMINI_MODEL'] ?? 'gemini-1.5-pro')));
if (!empty($geminiKey)) {
    $providers[] = [
        'name'  => 'Gemini',
        'key'   => $geminiKey,
        'model' => $geminiModel,
        'type'  => 'gemini'
    ];
}

// Nếu không có provider nào được cấu hình -> báo lỗi
if (empty($providers)) {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa cấu hình API key cho bất kỳ dịch vụ AI nào trong file .env. Vui lòng thêm GROQ_API_KEY, OPENAI_API_KEY, DEEPSEEK_API_KEY hoặc GEMINI_API_KEY.'
    ]);
    exit;
}

// ── Hàm gọi OpenAI‑compatible API (Groq, OpenAI, DeepSeek) ──────────────
function callOpenAICompatible(array $provider, array $messages, int $timeout = 30): array {
    $payload = json_encode([
        'model'       => $provider['model'],
        'messages'    => $messages,
        'temperature' => 0.3,
        'max_tokens'  => 2048,
    ]);

    $ch = curl_init($provider['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $provider['key'],
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400) {
        return [
            'success' => false,
            'error'   => $curlError ?: "HTTP $httpCode"
        ];
    }

    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        return [
            'success' => false,
            'error'   => 'Invalid response format from ' . $provider['name']
        ];
    }

    return [
        'success' => true,
        'content' => $data['choices'][0]['message']['content']
    ];
}

// ── Hàm gọi Gemini API ─────────────────────────────────────────────────────
function callGemini(array $provider, array $messages, int $timeout = 30): array {
    // Chuyển đổi từ OpenAI message format sang Gemini format
    $contents = [];
    foreach ($messages as $msg) {
        // Gemini dùng 'model' thay vì 'assistant', 'user' giữ nguyên
        $role = ($msg['role'] === 'assistant') ? 'model' : $msg['role'];
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $msg['content']]]
        ];
    }

    $payload = json_encode([
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 2048,
        ]
    ]);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $provider['model'] . ':generateContent?key=' . $provider['key'];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400) {
        return [
            'success' => false,
            'error'   => $curlError ?: "HTTP $httpCode"
        ];
    }

    $data = json_decode($response, true);
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return [
            'success' => false,
            'error'   => 'Invalid response format from Gemini'
        ];
    }

    return [
        'success' => true,
        'content' => $data['candidates'][0]['content']['parts'][0]['text']
    ];
}

// ── Gọi lần lượt các provider cho đến khi thành công ──────────────────────
$aiText = null;
$lastError = null;
$usedProvider = null;

foreach ($providers as $provider) {
    if ($provider['type'] === 'gemini') {
        $result = callGemini($provider, $messages);
    } else {
        $result = callOpenAICompatible($provider, $messages);
    }

    if ($result['success']) {
        $aiText = $result['content'];
        $usedProvider = $provider['name'];
        break;
    } else {
        $lastError = $result['error'] ?? 'Unknown error';
        // Ghi log lỗi (có thể bật nếu cần)
        // error_log("AI Provider {$provider['name']} failed: $lastError");
    }
}

// ── Fallback offline khi không có AI ──────────────────────────────────────
if ($aiText === null) {
    // Dùng fallback cục bộ để luôn có phản hồi
    $aiText = offlineFallback($userMessage, $db);
    $usedProvider = 'Offline';
}

// ── Parse ACTION tags từ phản hồi AI ──────────────────────────────────────
$actionResults = [];
$cleanText = $aiText;

if (preg_match_all('/<ACTION>(.*?)<\/ACTION>/s', $aiText, $matches)) {
    foreach ($matches[1] as $actionJson) {
        $actionData = json_decode(trim($actionJson), true);
        if (!$actionData || !isset($actionData['action'])) continue;
        
        $actionType = $actionData['action'];
        $result = null;
        
        switch ($actionType) {
            case 'get_pending':
                $limit = (int)($actionData['limit'] ?? 10);
                $posts = getPendingPosts($db, $limit);
                $result = ['type' => 'post_list', 'label' => 'Bài chờ duyệt', 'data' => $posts];
                break;
                
            case 'get_all':
                $limit = (int)($actionData['limit'] ?? 20);
                $status = $actionData['status'] ?? '';
                $posts = getAllPosts($db, $limit, $status);
                $result = ['type' => 'post_list', 'label' => 'Danh sách bài đăng', 'data' => $posts];
                break;
                
            case 'search':
                $keyword = $actionData['keyword'] ?? '';
                $limit = (int)($actionData['limit'] ?? 10);
                $posts = searchPosts($db, $keyword, $limit);
                $result = ['type' => 'post_list', 'label' => "Kết quả tìm kiếm: \"$keyword\"", 'data' => $posts];
                break;
                
            case 'get_stats':
                $s = getPostsSummary($db);
                $result = ['type' => 'stats', 'data' => $s];
                break;
                
            case 'get_user_stats':
                $u = getUserStats($db);
                $result = ['type' => 'user_stats', 'data' => $u];
                break;
                
            case 'get_post':
                $pid = (int)($actionData['id'] ?? 0);
                $post = $pid ? getPostById($db, $pid) : null;
                $result = ['type' => 'post_detail', 'data' => $post];
                break;
                
            case 'approve':
                $pid = (int)($actionData['id'] ?? 0);
                if ($pid) {
                    $r = approvePost($db, $pid);
                    $result = ['type' => 'action_result', 'action' => 'approve', 'result' => $r];
                }
                break;
                
            case 'reject':
                $pid = (int)($actionData['id'] ?? 0);
                $reason = $actionData['reason'] ?? '';
                if ($pid) {
                    $r = rejectPost($db, $pid, $reason);
                    $result = ['type' => 'action_result', 'action' => 'reject', 'result' => $r];
                }
                break;
                
            case 'delete':
                $pid = (int)($actionData['id'] ?? 0);
                if ($pid) {
                    $r = deletePost($db, $pid);
                    $result = ['type' => 'action_result', 'action' => 'delete', 'result' => $r];
                }
                break;

            case 'export_excel':
                $expType = $actionData['type'] ?? 'posts';
                $expStatus = $actionData['status'] ?? '';
                $expKw = $actionData['keyword'] ?? '';

                $count = 0;
                $title = '';
                $url = '../api/excel_export.php?type=' . urlencode($expType);

                if ($expType === 'posters') {
                    $title = 'Danh sách người đăng bài';
                    $stmt = $db->query("SELECT COUNT(DISTINCT nguoidang) FROM dangbai_chothuetro");
                    $count = (int)$stmt->fetchColumn();
                } elseif ($expType === 'users') {
                    $title = 'Danh sách tài khoản người dùng';
                    // Khớp chính xác với query trong admin_export_excel.php: bỏ qua admin
                    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role != 'admin' OR role IS NULL");
                    $count = (int)$stmt->fetchColumn();
                } else {
                    $where = '1=1';
                    $p = [];
                    if ($expStatus && in_array($expStatus, ['cho_duyet', 'da_duyet', 'tu_choi'])) {
                        $where .= " AND trangthai = :st";
                        $p[':st'] = $expStatus;
                        $url .= '&status=' . urlencode($expStatus);
                    }
                    if ($expKw) {
                        $where .= " AND (tieude LIKE :kw OR diachi LIKE :kw2 OR nguoidang LIKE :kw3)";
                        $p[':kw'] = "%$expKw%";
                        $p[':kw2'] = "%$expKw%";
                        $p[':kw3'] = "%$expKw%";
                        $url .= '&keyword=' . urlencode($expKw);
                    }

                    $titleMap = [
                        'cho_duyet' => 'Bài đăng chờ duyệt',
                        'tu_choi'   => 'Bài đăng bị từ chối / hủy',
                        'da_duyet'  => 'Bài đăng đã duyệt',
                    ];
                    $title = $titleMap[$expStatus] ?? ($expKw ? "Kết quả tìm kiếm: \"$expKw\"" : 'Tất cả bài đăng');

                    $stmt = $db->prepare("SELECT COUNT(*) FROM dangbai_chothuetro WHERE $where");
                    $stmt->execute($p);
                    $count = (int)$stmt->fetchColumn();
                }

                $result = [
                    'type' => 'excel_export',
                    'label' => 'Xuất File CSV (Excel)',
                    'title' => $title,
                    'export_type' => $expType,
                    'count' => $count,
                    'download_url' => $url
                ];
                break;
        }
        
        if ($result) {
            $actionResults[] = $result;
        }
    }
    
    // Xóa các ACTION tags khỏi text hiển thị
    $cleanText = preg_replace('/<ACTION>.*?<\/ACTION>/s', '', $aiText);
    // Xóa các thuật ngữ mã hàm kỹ thuật nếu AI lỡ ghi vào text
    $cleanText = preg_replace('/(?:thực hiện hành động|hành động)\s+`?[a_z0-9_]+`?/ui', '', $cleanText);
    $cleanText = preg_replace('/`?(?:get_user_stats|get_pending|get_all|get_stats|export_excel|get_post)`?/ui', '', $cleanText);
    $cleanText = trim($cleanText);
}

// ── Trả về kết quả cho frontend ────────────────────────────────────────────
echo json_encode([
    'success'        => true,
    'reply'          => $cleanText ?: 'Đã xử lý yêu cầu của bạn.',
    'action_results' => $actionResults,
    'used_provider'  => $usedProvider,
    'raw_ai'         => $aiText,
]);

/**
 * Hàm fallback offline - xử lý câu lệnh đơn giản khi không gọi được AI
 */
function offlineFallback(string $message, PDO $db): string {
    $msg = mb_strtolower(trim($message));

    // Xuất excel offline fallback
    if (strpos($msg, 'excel') !== false || strpos($msg, 'xuất') !== false) {
        if (strpos($msg, 'người đăng') !== false || strpos($msg, 'poster') !== false) {
            return 'Tôi đã khởi tạo yêu cầu xuất File Excel tổng hợp người đăng bài. Bạn có thể bấm nút Tải xuống ở trên.<ACTION>{"action": "export_excel", "type": "posters"}</ACTION>';
        }
        if (strpos($msg, 'từ chối') !== false || strpos($msg, 'hủy') !== false || strpos($msg, 'tu choi') !== false) {
            return 'Tôi đã tạo xong yêu cầu xuất File Excel các bài đăng bị từ chối/hủy. Bạn có thể bấm nút Tải xuống dưới đây.<ACTION>{"action": "export_excel", "type": "posts", "status": "tu_choi"}</ACTION>';
        }
        if (strpos($msg, 'chờ duyệt') !== false) {
            return 'Tôi đã khởi tạo file Excel danh sách các bài đăng chờ duyệt. Vui lòng bấm Tải xuống.<ACTION>{"action": "export_excel", "type": "posts", "status": "cho_duyet"}</ACTION>';
        }
        if (strpos($msg, 'người dùng') !== false || strpos($msg, 'user') !== false) {
            return 'Tôi đã khởi tạo file Excel danh sách tài khoản người dùng.<ACTION>{"action": "export_excel", "type": "users"}</ACTION>';
        }
        return 'Tôi đã khởi tạo file Excel toàn bộ dữ liệu bài đăng phòng trọ.<ACTION>{"action": "export_excel", "type": "posts", "status": "all"}</ACTION>';
    }
    
    if (strpos($msg, 'chờ duyệt') !== false || strpos($msg, 'pending') !== false) {
        $posts = getPendingPosts($db, 10);
        if ($posts) {
            $list = array_map(fn($p) => "#{$p['id']} - {$p['tieude']}", $posts);
            return "Hiện có " . count($posts) . " bài chờ duyệt:\n" . implode("\n", $list);
        }
        return "Không có bài nào đang chờ duyệt.";
    }
    
    if (strpos($msg, 'thống kê') !== false && strpos($msg, 'người dùng') !== false) {
        $u = getUserStats($db);
        $top = $u['top_posters'] ?? [];
        $list = array_map(fn($p) => "{$p['username']} ({$p['post_count']} bài)", $top);
        return "Tổng người dùng: {$u['total']}\nĐăng ký hôm nay: {$u['today']}\nTop poster:\n" . implode("\n", $list);
    }
    
    if (strpos($msg, 'thống kê') !== false) {
        $s = getPostsSummary($db);
        return "Tổng bài: {$s['total']}, Hôm nay: {$s['today']}, Người đăng: {$s['total_posters']}";
    }
    
    if (strpos($msg, 'hôm nay') !== false || strpos($msg, 'today') !== false) {
        $s = getPostsSummary($db);
        return "Hôm nay có {$s['today']} bài đăng mới.";
    }
    
    return "Xin lỗi, tôi chưa hiểu rõ lệnh của bạn. Bạn có thể thử các câu hỏi như: \"Xuất excel tổng hợp người đăng bài\", \"Xuất excel bài bị hủy\", \"Xuất excel bài chờ duyệt\".";
}