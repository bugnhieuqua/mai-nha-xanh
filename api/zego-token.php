<?php
/**
 * zego-token.php — Server-side Zego Token04 Generator
 * 
 * Sinh token Zego (Token04) trực tiếp trên server bằng HMAC-SHA256.
 * An toàn hơn generateKitTokenForTest() phía client vì:
 *  - Không lộ serverSecret ra browser
 *  - Thời gian đồng bộ với server, tránh lỗi token expire (20014)
 *  - Có thể kiểm tra quyền trước khi cấp token
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

// Bảo vệ: chỉ người đã đăng nhập mới được sinh token
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập.']);
    exit;
}

// Lấy room_id từ POST
$roomId = isset($_POST['room_id']) ? trim($_POST['room_id']) : '';
if (empty($roomId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu room_id.']);
    exit;
}

// Thông tin user từ session
$userId   = strval($_SESSION['user_id']);
$userName = !empty($_SESSION['hoten'])
    ? $_SESSION['hoten']
    : (!empty($_SESSION['username']) ? $_SESSION['username'] : ('User_' . $userId));

// Đọc credentials từ .env
$appId        = intval($_ENV['ZEGO_APP_ID']        ?? getenv('ZEGO_APP_ID')        ?? 0);
$serverSecret = $_ENV['ZEGO_SERVER_SECRET']         ?? getenv('ZEGO_SERVER_SECRET') ?? '';

if ($appId === 0 || empty($serverSecret)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cấu hình ZegoCloud chưa được thiết lập.']);
    exit;
}

/**
 * Sinh Zego Token04
 * 
 * Cấu trúc binary (trước khi base64):
 *   [uint32 BE: payload_length] [payload JSON bytes] [HMAC-SHA256 32 bytes]
 * Token = "04" + base64(binary)
 * 
 * @param  int    $appId            AppID từ Zego Console
 * @param  string $userId           User ID (string)
 * @param  string $serverSecret     ServerSecret dạng hex 64 ký tự (= 32 bytes)
 * @param  int    $effectiveSeconds Thời gian hiệu lực tính bằng giây
 * @return string Token04 string
 */
function generateZegoToken04(int $appId, string $userId, string $serverSecret, int $effectiveSeconds = 3600): string
{
    $createTime = time();
    $expireTime = $createTime + $effectiveSeconds;
    $nonce      = random_int(0, 0x7FFFFFFF);

    // Payload JSON — thứ tự key phải khớp với spec Zego
    $payload = json_encode([
        'app_id'   => $appId,
        'user_id'  => $userId,
        'nonce'    => $nonce,
        'ctime'    => $createTime,
        'expire'   => $expireTime,
        'payload'  => ''
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Convert hex secret → binary (64 hex chars = 32 bytes)
    $keyBin = hex2bin($serverSecret);

    // HMAC-SHA256(payload, key)
    $hmac = hash_hmac('sha256', $payload, $keyBin, true); // raw binary = 32 bytes

    // Binary content: length(4B BE) + payload + hmac(32B)
    $binary = pack('N', strlen($payload)) . $payload . $hmac;

    return '04' . base64_encode($binary);
}

try {
    $token = generateZegoToken04($appId, $userId, $serverSecret, 3600);

    echo json_encode([
        'success'   => true,
        'token'     => $token,
        'app_id'    => $appId,
        'user_id'   => $userId,
        'user_name' => $userName,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi sinh token: ' . $e->getMessage()]);
}
