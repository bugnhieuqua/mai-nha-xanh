<?php
/**
 * zego-token.php — Server-side Zego Token04 Generator (Official AES-CBC Spec)
 *
 * Tham chiếu chính thức: https://docs.zegocloud.com/article/11649
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

// Chỉ người đã đăng nhập mới được sinh token
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

// Đọc credentials từ biến môi trường
$appId        = intval($_ENV['ZEGO_APP_ID']     ?? getenv('ZEGO_APP_ID')     ?? 0);
$serverSecret = $_ENV['ZEGO_SERVER_SECRET']      ?? getenv('ZEGO_SERVER_SECRET') ?? '';

if ($appId === 0 || empty($serverSecret)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cấu hình ZegoCloud chưa được thiết lập.']);
    exit;
}

/**
 * Sinh Zego Token04 theo đúng spec chính thức (AES-CBC + IV + Expire time)
 */
function generateZegoToken04(int $appId, string $userId, string $serverSecret, int $effectiveSeconds = 3600, string $payload = ''): string
{
    if ($appId === 0) {
        throw new Exception('appID invalid');
    }
    if ($userId === '') {
        throw new Exception('userID invalid');
    }

    // Zego hỗ trợ 2 định dạng serverSecret:
    // - 32 ký tự: dùng trực tiếp làm AES key (UTF-8 string)
    // - 64 ký tự hex: chuyển sang binary trước (= 32 bytes)
    $keyBin = (strlen($serverSecret) === 64 && ctype_xdigit($serverSecret))
        ? hex2bin($serverSecret)
        : $serverSecret;

    $keyLen = strlen($keyBin);
    switch ($keyLen) {
        case 16:
            $cipher = 'aes-128-cbc';
            break;
        case 24:
            $cipher = 'aes-192-cbc';
            break;
        case 32:
            $cipher = 'aes-256-cbc';
            break;
        default:
            throw new Exception('Secret length must be 16, 24, or 32 bytes (or 64 hex characters). Actual length: ' . $keyLen);
    }

    $timestamp = time();
    $expireTime = $timestamp + $effectiveSeconds;
    
    // Tạo 16 ký tự IV ngẫu nhiên từ bảng chữ cái thường gặp
    $ivChars = '0123456789abcdefghijklmnopqrstuvwxyz';
    $iv = '';
    for ($i = 0; $i < 16; $i++) {
        $iv .= $ivChars[random_int(0, 35)];
    }

    $nonce = random_int(0, 2147483647);

    $data = [
        'app_id'   => $appId,
        'user_id'  => $userId,
        'nonce'    => $nonce,
        'ctime'    => $timestamp,
        'expire'   => $expireTime,
        'payload'  => $payload
    ];

    $plaintext = json_encode($data, JSON_BIGINT_AS_STRING);

    $encrypted = openssl_encrypt($plaintext, $cipher, $keyBin, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        throw new Exception('Encryption failed: ' . openssl_error_string());
    }

    // 64-bit big-endian unsigned integer pack format is 'J'.
    $binary = pack('J', $expireTime);
    $binary .= pack('n', strlen($iv)) . $iv;
    $binary .= pack('n', strlen($encrypted)) . $encrypted;

    return '04' . base64_encode($binary);
}

try {
    // Để cuộc gọi ổn định, ta gán payload phân quyền vào room
    $payload = json_encode([
        'room_id' => $roomId,
        'privilege' => [
            1 => 1, // PrivilegeKeyLogin: Cho phép join room
            2 => 1  // PrivilegeKeyPublish: Cho phép publish audio/video stream
        ],
        'stream_id_list' => []
    ]);

    $token = generateZegoToken04($appId, $userId, $serverSecret, 3600, $payload);

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
