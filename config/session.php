<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() == PHP_SESSION_NONE) {
    // Tự động phát hiện HTTPS
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

    ini_set('session.cookie_lifetime', 0);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// --- Session Fingerprinting: Ngăn chặn cướp phiên (Session Hijacking) ---
if (isset($_SESSION['user_id'])) {
    $current_fingerprint = md5($_SERVER['HTTP_USER_AGENT']);
    
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $current_fingerprint;
    } elseif ($_SESSION['fingerprint'] !== $current_fingerprint) {
        // Nếu Fingerprint thay đổi (nghi ngờ bị chiếm quyền), hủy session và đăng xuất
        session_unset();
        session_destroy();
        header("Location: " . ((strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../' : '') . "login.php?error=session_expired");
        exit;
    }
}

// Khởi tạo CSRF Token nếu chưa có
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Hàm kiểm tra CSRF Token từ Request Header
 */
function validateCsrfToken() {
    $receivedToken = '';

    // Ưu tiên 1: POST data (an toàn nhất, hoạt động trên mọi hosting)
    if (!empty($_POST['csrf_token'])) {
        $receivedToken = $_POST['csrf_token'];
    }
    // Ưu tiên 2: Header X-CSRF-TOKEN (AJAX)
    else {
        // getallheaders() có thể không tồn tại trên PHP-CGI/FastCGI
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders() ?: [];
        } else {
            // Fallback: parse từ $_SERVER
            foreach ($_SERVER as $key => $val) {
                if (strncmp($key, 'HTTP_', 5) === 0) {
                    $name = str_replace('_', '-', substr($key, 5));
                    $headers[$name] = $val;
                }
            }
        }
        // Case-insensitive header lookup
        foreach ($headers as $name => $val) {
            if (strtoupper($name) === 'X-CSRF-TOKEN') {
                $receivedToken = $val;
                break;
            }
        }
    }

    if (empty($receivedToken) || !hash_equals($_SESSION['csrf_token'], $receivedToken)) {
        // Xóa bất kỳ output rác nào trước khi gửi JSON
        if (ob_get_level() > 0) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Lỗi bảo mật: CSRF Token không hợp lệ.']);
        exit;
    }
}

// Auto-migrate old sessions that do not have the 'avatar' key
if (isset($_SESSION['user_id']) && !array_key_exists('avatar', $_SESSION)) {
    try {
        require_once __DIR__ . '/database.php';
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT avatar FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $val = $stmt->fetchColumn();
        $_SESSION['avatar'] = $val ? $val : null;
    } catch (Exception $e) {}
}

function requireLogin($role = null) {
    // Tự động kiểm tra nếu đang ở trong thư mục con admin/ để có đường dẫn về đúng
    $is_admin_folder = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
    $prefix = $is_admin_folder ? '../' : '';

    if (!isset($_SESSION['user_id'])) {
        header("Location: {$prefix}login.php");
        exit();
    }
    
    if ($role && (!isset($_SESSION['role']) || $_SESSION['role'] !== $role)) {
        // Redirect or show error for unauthorized access
        header("Location: {$prefix}index.php");
        exit();
    }
}
?>
