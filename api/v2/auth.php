<?php
/**
 * RESTful Auth API v2
 * GET /api/v2/auth.php?action=me
 * POST /api/v2/auth.php?action=login
 * POST /api/v2/auth.php?action=logout
 */

require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        // Rate Limiting — Chống brute-force: tối đa 5 request POST / 1 phút per IP
        require_once __DIR__ . '/../rate_limit.php';
        checkRateLimit('api_login', 5, 60);

        $now = time();
        if (isset($_SESSION['login_lockout_time']) && $now < $_SESSION['login_lockout_time']) {
            $retryAfter = $_SESSION['login_lockout_time'] - $now;
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'code' => 429,
                'message' => "Bạn đã nhập sai 3 lần. Vui lòng thử lại sau {$retryAfter} giây."
            ]);
            exit;
        }

        if (isset($_SESSION['login_lockout_time']) && $now >= $_SESSION['login_lockout_time']) {
            unset($_SESSION['login_lockout_time']);
            unset($_SESSION['login_failed_attempts']);
        }

        // Đọc dữ liệu từ cả POST form và JSON input
        $input = [];
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
        }

        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $req_role = trim($input['role'] ?? '');

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'code' => 400,
                'message' => 'Tên đăng nhập và mật khẩu không được để trống.'
            ]);
            exit;
        }

        try {
            $db = getDB();
            // 1. Kiểm tra tài khoản tồn tại hay không
            $stmt = $db->prepare("SELECT id, username, password, status, role, avatar, hoten FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $usernameExists = ($user !== false);

            // 2. Kiểm tra mật khẩu có khớp với bất kỳ tài khoản nào không
            $pwdStmt = $db->prepare("SELECT password FROM users");
            $pwdStmt->execute();
            $allUsers = $pwdStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $passwordMatchesAny = false;
            foreach ($allUsers as $u) {
                if (password_verify($password, $u['password'])) {
                    $passwordMatchesAny = true;
                    break;
                }
            }

            if ($usernameExists && password_verify($password, $user['password'])) {
                // Đăng nhập thành công -> reset failed attempts
                unset($_SESSION['login_failed_attempts']);
                unset($_SESSION['login_lockout_time']);

                if (($user['status'] ?? '') === 'banned') {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'code' => 403,
                        'message' => 'Tài khoản của bạn đã bị khóa do vi phạm chính sách cộng đồng.'
                    ]);
                    exit;
                }

                // Tái tạo ID session để tránh Session Fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Gán vai trò theo CSDL mặc định, hoặc ghi đè nếu được yêu cầu
                $role = $user['role'] ?? 'user';
                if ($req_role === 'admin' || $req_role === 'user') {
                    $role = $req_role;
                }
                $_SESSION['role'] = $role;
                
                $_SESSION['avatar'] = $user['avatar'] ?? null;
                $_SESSION['hoten'] = $user['hoten'] ?? '';

                // Cập nhật lại CSRF token mới
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                echo json_encode([
                    'success' => true,
                    'code' => 200,
                    'message' => 'Đăng nhập thành công!',
                    'data' => [
                        'user' => [
                            'id' => (int)$user['id'],
                            'username' => $user['username'],
                            'role' => $role,
                            'hoten' => $user['hoten'] ?? '',
                            'avatar' => $user['avatar'] ?? ''
                        ],
                        'csrf_token' => $_SESSION['csrf_token']
                    ]
                ]);
            } else {
                // Đăng nhập thất bại -> tăng đếm
                $_SESSION['login_failed_attempts'] = ($_SESSION['login_failed_attempts'] ?? 0) + 1;
                $remaining = 3 - $_SESSION['login_failed_attempts'];

                if ($_SESSION['login_failed_attempts'] >= 3) {
                    $_SESSION['login_lockout_time'] = time() + 60; // khóa 60 giây
                    http_response_code(429);
                    echo json_encode([
                        'success' => false,
                        'code' => 429,
                        'message' => 'Bạn đã nhập sai 3 lần. Vui lòng thử lại sau 60 giây.'
                    ]);
                } else {
                    if ($usernameExists) {
                        // Đúng tên nhưng sai mật khẩu
                        $message = "Đăng nhập thất bại: Lỗi mật khẩu! (Còn {$remaining} lần nhập)";
                    } else {
                        if ($passwordMatchesAny) {
                            // Đúng mật khẩu của tài khoản khác nhưng sai tên đăng nhập
                            $message = "Đăng nhập thất bại: Sai tên tài khoản! (Còn {$remaining} lần nhập)";
                        } else {
                            // Sai cả tài khoản và mật khẩu
                            $message = "Đăng nhập thất bại: Sai cả tài khoản và mật khẩu! (Còn {$remaining} lần nhập)";
                        }
                    }
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'code' => 401,
                        'message' => $message
                    ]);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'code' => 500,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ]);
        }
    } elseif ($action === 'register') {
        // Rate Limiting — Chống spam đăng ký: tối đa 5 request POST / 1 phút per IP
        require_once __DIR__ . '/../rate_limit.php';
        checkRateLimit('api_register', 5, 60);

        // Đọc dữ liệu từ cả POST form và JSON input
        $input = [];
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
        }

        $username = trim($input['username'] ?? $input['reg_username'] ?? '');
        $password = $input['password'] ?? $input['reg_password'] ?? '';
        $email = trim($input['email'] ?? $input['reg_email'] ?? '');
        $hoten = trim($input['hoten'] ?? '');
        $avatar = trim($input['avatar'] ?? '');

        if (empty($username) || empty($password) || empty($email)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'code' => 400,
                'message' => 'Tên đăng nhập, mật khẩu và email không được để trống.'
            ]);
            exit;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'code' => 400,
                'message' => 'Định dạng email không hợp lệ.'
            ]);
            exit;
        }

        try {
            $db = getDB();
            // Kiểm tra trùng username VÀ email cùng lúc
            $check_stmt = $db->prepare("SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 2");
            $check_stmt->execute([':username' => $username, ':email' => $email]);
            $existing = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

            $usernameExists = false;
            $emailExists = false;
            foreach ($existing as $row) {
                if ($row['username'] === $username) {
                    $usernameExists = true;
                }
                if ($row['email'] === $email) {
                    $emailExists = true;
                }
            }

            if ($usernameExists) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'code' => 409,
                    'message' => 'Tên đăng nhập đã tồn tại! Vui lòng chọn tên đăng nhập khác.'
                ]);
                exit;
            } elseif ($emailExists) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'code' => 409,
                    'message' => 'Email này đã được sử dụng! Vui lòng dùng địa chỉ email khác.'
                ]);
                exit;
            }

            // Thêm tài khoản mới vào database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, email, hoten, avatar, role, status) VALUES (:username, :password, :email, :hoten, :avatar, 'user', 'active')");
            $result = $stmt->execute([
                ':username' => $username,
                ':password' => $hashed_password,
                ':email' => $email,
                ':hoten' => $hoten,
                ':avatar' => $avatar
            ]);

            if ($result) {
                $newUserId = $db->lastInsertId();
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'code' => 201,
                    'message' => 'Đăng ký tài khoản thành công!',
                    'data' => [
                        'id' => (int)$newUserId,
                        'username' => $username,
                        'email' => $email,
                        'hoten' => $hoten,
                        'avatar' => $avatar
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'code' => 500,
                    'message' => 'Có lỗi xảy ra khi tạo tài khoản, vui lòng thử lại.'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'code' => 500,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ]);
        }
    } elseif ($action === 'logout') {
        validateCsrfToken();
        
        session_unset();
        session_destroy();
        
        echo json_encode([
            'success' => true,
            'code' => 200,
            'message' => 'Đăng xuất thành công!'
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'code' => 400,
            'message' => 'Hành động không hợp lệ.'
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'me') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => true,
            'code' => 200,
            'message' => 'Lấy thông tin thành công!',
            'data' => [
                'id' => (int)$_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'] ?? 'user',
                'hoten' => $_SESSION['hoten'] ?? '',
                'avatar' => $_SESSION['avatar'] ?? '',
                'csrf_token' => $_SESSION['csrf_token']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'code' => 401,
            'message' => 'Bạn chưa đăng nhập.'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'code' => 405,
        'message' => 'Phương thức không được hỗ trợ.'
    ]);
}
