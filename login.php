<?php
require_once 'config/bootstrap.php';

// Nếu admin/người dùng đã đăng nhập, chuyển hướng ngay mà không cần ở lại trang đăng nhập
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
        exit;
    } else {
        header("Location: index.php"); // hoặc trang người dùng tĩnh
        exit;
    }
}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Rate Limiting — Chống brute-force: tối đa 5 request POST / 1 phút per IP
    require_once __DIR__ . '/api/rate_limit.php';
    if (!checkRateLimit('login', 5, 60)) {
        exit; // checkRateLimit đã trả JSON response và exit
    }

    $database = new Database();
    $db = $database->getConnection();
    $action = $_POST['action'] ?? 'login';

    // Xác thực CSRF Token cho Login/Register
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $message = "Lỗi bảo mật: CSRF Token không hợp lệ. Vui lòng tải lại trang.";
        $messageType = 'error';
    } else {
        if ($action == 'register') {
            // --- REGISTER LOGIC ---
            $username = $_POST['reg_username'] ?? '';
            $password = $_POST['reg_password'] ?? '';
            $password_confirm = $_POST['reg_password_confirm'] ?? '';
            $email = $_POST['reg_email'] ?? '';
            $showRegisterForm = true; // Giữ form đăng ký hiển thị khi có lỗi

            if ($username && $password && $password_confirm && $email) {
                $hasMinLen = strlen($password) >= 8;
                $hasUpper = preg_match('/[A-Z]/', $password);
                $hasNumber = preg_match('/[0-9]/', $password);
                $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);

                if ($password !== $password_confirm) {
                    $message = "Mật khẩu xác nhận không trùng khớp.";
                    $messageType = 'error';
                } elseif (!$hasMinLen || !$hasUpper || !$hasNumber || !$hasSpecial) {
                    $message = "Mật khẩu bắt buộc tối thiểu 8 ký tự, bao gồm chữ hoa, chữ số và ký tự đặc biệt.";
                    $messageType = 'error';
                } else {
                try {
                    // Kiểm tra trùng username VÀ email cùng lúc
                    $check_query = "SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 2";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':username', $username);
                    $check_stmt->bindParam(':email', $email);
                    $check_stmt->execute();
                    $existing = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $usernameExists = false;
                    $emailExists = false;
                    foreach ($existing as $row) {
                        if ($row['username'] === $username)
                            $usernameExists = true;
                        if ($row['email'] === $email)
                            $emailExists = true;
                    }

                    if ($usernameExists) {
                        $message = "Tên đăng nhập đã tồn tại! Vui lòng chọn tên đăng nhập khác.";
                        $messageType = 'error';
                    } elseif ($emailExists) {
                        $message = "Email này đã được sử dụng! Vui lòng dùng địa chỉ email khác.";
                        $messageType = 'error';
                    } else {
                        $query = "INSERT INTO users (username, password, email) VALUES (:username, :password, :email)";
                        $stmt = $db->prepare($query);
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':email', $email);

                        if ($stmt->execute()) {
                            $message = "Đăng ký thành công! Vui lòng đăng nhập.";
                            $messageType = 'success';
                            $showRegisterForm = false; // Đăng ký xong thì về form đăng nhập
                        } else {
                            $message = "Có lỗi xảy ra, vui lòng thử lại.";
                            $messageType = 'error';
                        }
                    }
                } catch (PDOException $e) {
                    $message = "Lỗi hệ thống: " . $e->getMessage();
                    $messageType = 'error';
                }
            }
            } else {
                $message = "Vui lòng điền đầy đủ thông tin đăng ký.";
                $messageType = 'error';
            }

        } elseif ($action == 'login') {
            // --- LOGIN LOGIC ---
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $now = time();
            if (isset($_SESSION['login_lockout_time']) && $now < $_SESSION['login_lockout_time']) {
                $retryAfter = $_SESSION['login_lockout_time'] - $now;
                $message = "Bạn đã nhập sai 3 lần. Vui lòng thử lại sau {$retryAfter} giây.";
                $messageType = 'error';
            } else {
                if (isset($_SESSION['login_lockout_time']) && $now >= $_SESSION['login_lockout_time']) {
                    unset($_SESSION['login_lockout_time']);
                    unset($_SESSION['login_failed_attempts']);
                }

                if ($username && $password) {
                    try {
                        // 1. Kiểm tra tài khoản tồn tại hay không
                        $query = "SELECT id, username, password, status, role, avatar, hoten FROM users WHERE username = :username LIMIT 1";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':username', $username);
                        $stmt->execute();
                        $usernameExists = ($stmt->rowCount() > 0);
                        $user = $usernameExists ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

                        // 2. Kiểm tra mật khẩu có khớp với bất kỳ tài khoản nào không
                        $pwdQuery = "SELECT password FROM users";
                        $pwdStmt = $db->prepare($pwdQuery);
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

                            if (isset($user['status']) && $user['status'] === 'banned') {
                                $message = "Tài khoản của bạn đã bị khoá do vi phạm tiêu chuẩn cộng đồng!";
                                $messageType = 'error';
                            } else {
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['username'] = $user['username'];
                                $_SESSION['role'] = $user['role'] ?? 'user';
                                $_SESSION['avatar'] = $user['avatar'] ?? null;
                                $_SESSION['hoten'] = $user['hoten'] ?? '';
                                $message = "Đăng nhập thành công!";
                                $messageType = 'success';
                                // Redirect admin to admin panel, others to phong-tro
                                $redirect = (($_SESSION['role'] === 'admin') ? 'admin/index.php' : 'phong-tro.php');
                                echo "<script>window.location.href='{$redirect}';</script>";
                                exit;
                            }
                        } else {
                            // Đăng nhập thất bại -> tăng đếm
                            $_SESSION['login_failed_attempts'] = ($_SESSION['login_failed_attempts'] ?? 0) + 1;
                            $remaining = 3 - $_SESSION['login_failed_attempts'];

                            if ($_SESSION['login_failed_attempts'] >= 3) {
                                $_SESSION['login_lockout_time'] = time() + 60; // khóa 60 giây
                                $message = "Bạn đã nhập sai 3 lần. Vui lòng thử lại sau 60 giây.";
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
                            }
                            $messageType = 'error';
                        }
                    } catch (PDOException $e) {
                        $message = "Lỗi: " . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = "Vui lòng nhập tên đăng nhập và mật khẩu.";
                    $messageType = 'error';
                }
            }
        } elseif ($action == 'google_login') {
            // --- GOOGLE LOGIN LOGIC ---
            $credential = $_POST['credential'] ?? '';
            if ($credential) {
                // Decode the JWT token payload (for production, you should verify the signature with Google API Client)
                $parts = explode('.', $credential);
                if (count($parts) >= 2) {
                    // Replace base64url characters with standard base64 characters before decoding
                    $base64_payload = strtr($parts[1], '-_', '+/');
                    // Add padding if necessary
                    $base64_payload .= str_repeat('=', (4 - strlen($base64_payload) % 4) % 4);
                    $payload = json_decode(base64_decode($base64_payload), true);
                    if ($payload && isset($payload['email'])) {
                        $email = $payload['email'];
                        $name = $payload['name'] ?? 'Google User';
                        $picture = $payload['picture'] ?? '';
                        $google_id = $payload['sub'] ?? '';

                        try {
                            $query = "SELECT id, username, role, status, avatar, hoten FROM users WHERE email = :email LIMIT 1";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':email', $email);
                            $stmt->execute();

                            if ($stmt->rowCount() > 0) {
                                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                                if (isset($user['status']) && $user['status'] === 'banned') {
                                    $message = "Tài khoản của bạn đã bị khoá!";
                                    $messageType = 'error';
                                } else {
                                    session_regenerate_id(true);
                                    $_SESSION['user_id'] = $user['id'];
                                    $_SESSION['username'] = $user['username'];
                                    $_SESSION['role'] = $user['role'] ?? 'user';
                                    $_SESSION['avatar'] = $user['avatar'] ?? $picture;
                                    $_SESSION['hoten'] = $user['hoten'] ?? $name;
                                    $message = "Đăng nhập bằng Google thành công!";
                                    $messageType = 'success';
                                    $redirect = (($user['role'] === 'admin') ? 'admin/index.php' : 'phong-tro.php');
                                    echo "<script>window.location.href='{$redirect}';</script>";
                                    exit;
                                }
                            } else {
                                // Tự động đăng ký người dùng mới từ Google
                                $username = 'user_' . substr(md5($google_id), 0, 8);
                                $randomPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                                $insert = "INSERT INTO users (username, password, email, hoten, avatar) VALUES (:username, :password, :email, :hoten, :avatar)";
                                $stmt_insert = $db->prepare($insert);
                                $stmt_insert->bindParam(':username', $username);
                                $stmt_insert->bindParam(':password', $randomPassword);
                                $stmt_insert->bindParam(':email', $email);
                                $stmt_insert->bindParam(':hoten', $name);
                                $stmt_insert->bindParam(':avatar', $picture);

                                if ($stmt_insert->execute()) {
                                    $newUserId = $db->lastInsertId();
                                    session_regenerate_id(true);
                                    $_SESSION['user_id'] = $newUserId;
                                    $_SESSION['username'] = $username;
                                    $_SESSION['role'] = 'user';
                                    $_SESSION['avatar'] = $picture;
                                    $_SESSION['hoten'] = $name;
                                    $message = "Đăng ký và đăng nhập Google thành công!";
                                    $messageType = 'success';
                                    echo "<script>window.location.href='phong-tro.php';</script>";
                                    exit;
                                } else {
                                    $message = "Có lỗi xảy ra khi tạo tài khoản Google.";
                                    $messageType = 'error';
                                }
                            }
                        } catch (PDOException $e) {
                            $message = "Lỗi hệ thống: " . $e->getMessage();
                            $messageType = 'error';
                        }
                    } else {
                        $message = "Dữ liệu từ Google không hợp lệ.";
                        $messageType = 'error';
                    }
                }
            }
        } // Kết thúc Google Login
    } // Kết thúc CSRF else
} // Kết thúc POST method
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Mái Nhà Xanh | Quản lý phòng trọ Vinh Nghệ An</title>
    <!-- Không cho Google index trang đăng nhập — tránh cảnh báo phishing -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="description"
        content="Trang đăng nhập hệ thống quản lý phòng trọ Mái Nhà Xanh. Chỉ dành cho người dùng đã đăng ký.">
    <meta name="author" content="Mái Nhà Xanh - mainhaxanhh.liveblog365.com">
    <!-- Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        /* Hide browser default password reveal and clear buttons */
        input::-ms-reveal,
        input::-ms-clear,
        input::-webkit-password-reveal-button {
            display: none !important;
        }

        input::-webkit-contacts-auto-fill-button,
        input::-webkit-credentials-auto-fill-button {
            visibility: hidden !important;
            display: none !important;
            position: absolute;
            right: 0;
        }

        body {
            background-image: linear-gradient(135deg, #3c6d69ff 0%, #04c76cff 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        /* Video Background & Overlay */
        .bg-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -3;
        }

        .bg-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.45); /* Dark transparent overlay for text legibility */
            z-index: -2;
        }

        /* Animated Background (Fallback when video is not playing) */
        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -4;
            animation: move 12s infinite alternate;
            opacity: 0.6;
        }

        .shape:nth-child(1) {
            top: -100px;
            left: -100px;
            width: 500px;
            height: 500px;
            background: #ff9a9e;
        }

        .shape:nth-child(2) {
            bottom: -50px;
            right: -50px;
            width: 450px;
            height: 450px;
            background: #a18cd1;
        }

        @keyframes move {
            from {
                transform: translate(0, 0) rotate(0deg);
            }

            to {
                transform: translate(40px, 40px) rotate(10deg);
            }
        }


        .container {
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            width: 380px;
            max-width: 92vw;
            padding: 24px 28px 20px;
            text-align: center;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .close-btn {
            position: absolute;
            top: 16px;
            right: 18px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.3rem;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
        }

        .close-btn:hover {
            color: #fff;
            transform: rotate(90deg);
        }

        .logo {
            width: 75px;
            height: 75px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .form-header h2 {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 14px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .input-group {
            position: relative;
            margin-bottom: 12px;
        }

        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #475569;
            font-size: 0.95rem;
            z-index: 2;
        }

        .input-group input {
            width: 100%;
            padding: 12px 42px 12px 48px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            background: #ebf3fe;
            border-radius: 50px;
            color: #0f172a;
            outline: none;
            transition: 0.3s;
            font-size: 0.95rem;
            font-weight: 500;
            box-sizing: border-box;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .input-group input::placeholder {
            color: #475569;
            font-weight: 400;
            opacity: 0.9;
        }

        .input-group input:focus {
            background: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
        }

        .btn-submit {
            background: linear-gradient(135deg, #74b834 0%, #2e7d32 100%);
            border: none;
            padding: 12px;
            border-radius: 50px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 6px;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #5c9e24 0%, #1b5e20 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(46, 125, 50, 0.4);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 12px 0;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.82rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.25);
        }

        .divider:not(:empty)::before {
            margin-right: .5em;
        }

        .divider:not(:empty)::after {
            margin-left: .5em;
        }

        .btn-google {
            background: #fff;
            border: none;
            padding: 10px;
            border-radius: 50px;
            color: #333;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-google:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .btn-google img.google-icon {
            width: 20px;
            height: 20px;
        }

        .toggle-text {
            margin-top: 12px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
        }

        .toggle-text a {
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .toggle-text a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 8px 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            color: #fff;
            font-size: 0.85rem;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.3);
            border: 1px solid #2ecc71;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.3);
            border: 1px solid #e74c3c;
        }

        /* Hidden class for toggling */
        .hidden {
            display: none;
        }

        /* Password Toggle */
        .input-group i.password-toggle {
            left: auto;
            right: 18px;
            cursor: pointer;
            pointer-events: auto;
            color: #475569;
            transition: color 0.2s;
        }

        .input-group i.password-toggle:hover {
            color: #1d4ed8 !important;
        }

        .match-status {
            font-size: 0.76rem;
            margin-top: -6px;
            margin-bottom: 8px;
            text-align: left;
            padding-left: 12px;
            font-weight: 600;
            transition: color 0.2s;
            display: none;
        }

        .match-status.valid {
            display: block;
            color: #2ecc71;
        }

        .match-status.invalid {
            display: block;
            color: #ff6b6b;
        }

        /* Password Strength Meter */
        .password-strength-meter {
            margin-top: -6px;
            margin-bottom: 16px;
            text-align: left;
            padding: 4px 6px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(5px);
        }

        .strength-bars {
            display: flex;
            gap: 6px;
            margin-bottom: 6px;
            margin-top: 4px;
        }

        .strength-bars .bar {
            height: 6px;
            flex: 1;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .strength-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 6px;
        }

        .strength-text strong {
            font-weight: 700;
            transition: color 0.3s ease;
        }

        .strength-requirements {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 8px;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .strength-requirements li {
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s ease;
        }

        .strength-requirements li i {
            font-size: 0.7rem;
            transition: color 0.3s ease, transform 0.2s ease;
        }

        .strength-requirements li.valid {
            color: #2ecc71;
            font-weight: 600;
        }

        .strength-requirements li.valid i {
            color: #2ecc71;
        }

        .strength-requirements li.invalid {
            color: rgba(255, 255, 255, 0.65);
        }

        .strength-requirements li.invalid i {
            color: rgba(255, 255, 255, 0.4);
        }

        /* Snowfall Effect */
        #snow-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .snowflake {
            position: absolute;
            top: -10px;
            width: 10px;
            height: 10px;
            background: white;
            border-radius: 50%;
            opacity: 0.8;
            animation: fall linear infinite;
        }

        @keyframes fall {
            to {
                transform: translateY(100vh);
            }
        }
    </style>
</head>

<body>
    <!-- Video Background -->
    <video autoplay loop muted playsinline class="bg-video">
        <source src="mai-nha-xanh.mp4" type="video/mp4">
    </video>
    <div class="bg-overlay"></div>

    <div id="snow-container"></div>
    <div class="shape"></div>
    <div class="shape"></div>

    <div class="container">
        <a href="phong-tro.php" class="close-btn"><i class="fas fa-times"></i></a>

        <div class="logo">
            <img src="assets/images/logo.png" alt="Logo">
        </div>

        <?php if ($message): ?>
            <div id="login-alert" class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- LOGIN FORM -->
        <div id="login-form" class="<?php echo (!empty($showRegisterForm) && $showRegisterForm) ? 'hidden' : ''; ?>" >
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Tên đăng nhập" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Mật khẩu" required>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
                <div style="text-align: right; margin-bottom: 12px;">
                    <a href="auth-password-forgot.php"
                        style="color: #fff; font-size: 0.85rem; text-decoration: none; opacity: 0.9;">Quên mật khẩu?</a>
                </div>
                <button type="submit" class="btn-submit">Đăng Nhập</button>

                <div class="divider">Hoặc đăng nhập với</div>

                <!-- Google GIS Button -->
                <div id="g_id_onload"
                    data-client_id="<?php echo htmlspecialchars($_ENV['GOOGLE_CLIENT_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    data-context="signin" data-ux_mode="popup" data-callback="handleGoogleLogin"
                    data-auto_prompt="false">
                </div>
                <div style="display: flex; justify-content: center; width: 100%;">
                    <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
                        data-text="signin_with" data-size="large" data-logo_alignment="center" data-width="340">
                    </div>
                </div>

            </form>
            <form id="google-login-form" method="POST" action="" style="display: none;">
                <input type="hidden" name="action" value="google_login">
                <input type="hidden" name="credential" id="google-credential">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            </form>
            <p class="toggle-text">Chưa có tài khoản? <a onclick="toggleForm()">Đăng Ký ngay</a></p>
        </div>

        <!-- REGISTER FORM -->
        <div id="register-form" class="<?php echo (!empty($showRegisterForm) && $showRegisterForm) ? '' : 'hidden'; ?>">
            <div class="form-header">
                <h2>Đăng Ký</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="reg_username" placeholder="Tên đăng nhập"
                        value="<?php echo htmlspecialchars($_POST['reg_username'] ?? ''); ?>" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="reg_email" placeholder="Email"
                        value="<?php echo htmlspecialchars($_POST['reg_email'] ?? ''); ?>" required>
                </div>
                <div class="input-group" style="margin-bottom: 8px;">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="reg_password" id="reg_password" placeholder="Mật khẩu" required>
                    <i class="fas fa-eye password-toggle" id="toggleRegPassword"></i>
                </div>
                <!-- Password Strength Meter -->
                <div class="password-strength-meter" id="meter-reg_password">
                    <div class="strength-bars">
                        <div class="bar" id="bar-reg_password-1"></div>
                        <div class="bar" id="bar-reg_password-2"></div>
                        <div class="bar" id="bar-reg_password-3"></div>
                        <div class="bar" id="bar-reg_password-4"></div>
                    </div>
                    <div class="strength-status">
                        <span class="strength-text">Mức độ: <strong id="status-reg_password" style="color: #cbd5e1;">Chưa nhập</strong></span>
                    </div>
                    <ul class="strength-requirements">
                        <li id="req-reg_password-len" class="invalid"><i class="fas fa-circle"></i> Tối thiểu 8 ký tự</li>
                        <li id="req-reg_password-upper" class="invalid"><i class="fas fa-circle"></i> Có chữ hoa (A-Z)</li>
                        <li id="req-reg_password-num" class="invalid"><i class="fas fa-circle"></i> Có chữ số (0-9)</li>
                        <li id="req-reg_password-special" class="invalid"><i class="fas fa-circle"></i> Ký tự đặc biệt</li>
                    </ul>
                </div>
                <div class="input-group" style="margin-bottom: 8px;">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="reg_password_confirm" id="reg_password_confirm" placeholder="Nhập lại mật khẩu" required>
                    <i class="fas fa-eye password-toggle" id="toggleRegPasswordConfirm"></i>
                </div>
                <div id="match-status-reg" class="match-status"></div>
                <button type="submit" class="btn-submit">Đăng Ký</button>
            </form>
            <p class="toggle-text">Đã có tài khoản? <a onclick="toggleForm()">Đăng Nhập</a></p>
        </div>
    </div>

    <script>
        function handleGoogleLogin(response) {
            if (response.credential) {
                // Set token vào form ẩn và submit
                document.getElementById('google-credential').value = response.credential;
                document.getElementById('google-login-form').submit();
            }
        }

        function toggleForm() {
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');

            if (loginForm.classList.contains('hidden')) {
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
            } else {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
            }
        }

        // Password Toggle Logic
        function setupPasswordToggle(inputId, toggleId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);

            if (toggle && input) {
                toggle.addEventListener('click', function () {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        }

        setupPasswordToggle('password', 'togglePassword');
        setupPasswordToggle('reg_password', 'toggleRegPassword');
        setupPasswordToggle('reg_password_confirm', 'toggleRegPasswordConfirm');

        // Password Strength Meter Logic
        function setupPasswordStrength(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;

            const bars = [
                document.getElementById(`bar-${inputId}-1`),
                document.getElementById(`bar-${inputId}-2`),
                document.getElementById(`bar-${inputId}-3`),
                document.getElementById(`bar-${inputId}-4`)
            ];
            const statusEl = document.getElementById(`status-${inputId}`);
            const reqLen = document.getElementById(`req-${inputId}-len`);
            const reqUpper = document.getElementById(`req-${inputId}-upper`);
            const reqNum = document.getElementById(`req-${inputId}-num`);
            const reqSpecial = document.getElementById(`req-${inputId}-special`);

            function updateReq(el, isValid) {
                if (!el) return;
                const icon = el.querySelector('i');
                if (isValid) {
                    el.className = 'valid';
                    if (icon) icon.className = 'fas fa-check-circle';
                } else {
                    el.className = 'invalid';
                    if (icon) icon.className = 'fas fa-circle';
                }
            }

            input.addEventListener('input', function () {
                const val = input.value;

                const isLenValid = val.length >= 8;
                const isUpperValid = /[A-Z]/.test(val);
                const isNumValid = /[0-9]/.test(val);
                const isSpecialValid = /[^A-Za-z0-9]/.test(val);

                updateReq(reqLen, isLenValid);
                updateReq(reqUpper, isUpperValid);
                updateReq(reqNum, isNumValid);
                updateReq(reqSpecial, isSpecialValid);

                let score = 0;
                if (isLenValid) score++;
                if (isUpperValid) score++;
                if (isNumValid) score++;
                if (isSpecialValid) score++;

                if (val.length === 0) {
                    score = 0;
                }

                const colors = [
                    'rgba(255, 255, 255, 0.2)', // 0: None
                    '#e74c3c',                 // 1: Tệ (Red)
                    '#f39c12',                 // 2: Trung bình (Orange)
                    '#2ecc71',                 // 3: Tốt (Green)
                    '#00e676'                  // 4: Rất tốt (Bright Green)
                ];

                const labels = [
                    { text: 'Chưa nhập', color: '#cbd5e1' },
                    { text: 'Tệ', color: '#ff6b6b' },
                    { text: 'Trung bình', color: '#f39c12' },
                    { text: 'Tốt', color: '#2ecc71' },
                    { text: 'Rất tốt', color: '#00e676' }
                ];

                // Update 4 bars
                bars.forEach((bar, idx) => {
                    if (bar) {
                        if (idx < score) {
                            bar.style.backgroundColor = colors[score];
                            bar.style.boxShadow = `0 0 8px ${colors[score]}88`;
                        } else {
                            bar.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
                            bar.style.boxShadow = 'none';
                        }
                    }
                });

                // Update text status
                if (statusEl) {
                    statusEl.textContent = labels[score].text;
                    statusEl.style.color = labels[score].color;
                }

                checkMatch();
            });
        }

        const regPwdInput = document.getElementById('reg_password');
        const regPwdConfirmInput = document.getElementById('reg_password_confirm');
        const matchStatusReg = document.getElementById('match-status-reg');

        function checkMatch() {
            if (!regPwdConfirmInput || !matchStatusReg) return;
            const val1 = regPwdInput ? regPwdInput.value : '';
            const val2 = regPwdConfirmInput.value;

            if (val2.length === 0) {
                matchStatusReg.className = 'match-status';
                matchStatusReg.textContent = '';
                return;
            }

            if (val1 === val2) {
                matchStatusReg.className = 'match-status valid';
                matchStatusReg.innerHTML = '<i class="fas fa-check-circle"></i> Mật khẩu trùng khớp';
            } else {
                matchStatusReg.className = 'match-status invalid';
                matchStatusReg.innerHTML = '<i class="fas fa-times-circle"></i> Mật khẩu không trùng khớp!';
            }
        }

        if (regPwdConfirmInput) {
            regPwdConfirmInput.addEventListener('input', checkMatch);
        }

        setupPasswordStrength('reg_password');

        // Prevent registration submission if password criteria are not fully met or passwords do not match
        const regFormElement = document.querySelector('#register-form form');
        if (regFormElement) {
            regFormElement.addEventListener('submit', function (e) {
                if (regPwdInput && regPwdConfirmInput) {
                    const val = regPwdInput.value;
                    const confirmVal = regPwdConfirmInput.value;

                    const isLenValid = val.length >= 8;
                    const isUpperValid = /[A-Z]/.test(val);
                    const isNumValid = /[0-9]/.test(val);
                    const isSpecialValid = /[^A-Za-z0-9]/.test(val);

                    if (!isLenValid || !isUpperValid || !isNumValid || !isSpecialValid) {
                        e.preventDefault();
                        alert('Mật khẩu chưa đủ điều kiện! Bắt buộc tối thiểu 8 ký tự, gồm chữ hoa, chữ số và ký tự đặc biệt.');
                        return;
                    }

                    if (val !== confirmVal) {
                        e.preventDefault();
                        alert('Mật khẩu nhập lại không trùng khớp! Vui lòng kiểm tra lại.');
                        return;
                    }
                }
            });
        }

        // Countdown logic for login lockout
        const alertEl = document.getElementById('login-alert');
        if (alertEl) {
            const match = alertEl.textContent.match(/sau\s+(\d+)\s+giây/);
            if (match) {
                let seconds = parseInt(match[1], 10);

                // Disable login form inputs and submit button to prevent bypass
                const loginFormEl = document.getElementById('login-form');
                const inputs = loginFormEl ? loginFormEl.querySelectorAll('input, button[type=submit]') : [];
                inputs.forEach(el => el.disabled = true);

                const timer = setInterval(() => {
                    seconds--;
                    if (seconds <= 0) {
                        clearInterval(timer);
                        alertEl.textContent = 'Bạn có thể thử đăng nhập lại ngay bây giờ.';
                        alertEl.className = 'alert alert-success';
                        inputs.forEach(el => el.disabled = false);
                    } else {
                        alertEl.textContent = `Bạn đã nhập sai 3 lần. Vui lòng thử lại sau ${seconds} giây.`;
                    }
                }, 1000);
            }
        }

        // Snowfall Effect
        document.addEventListener('DOMContentLoaded', function () {
            const snowContainer = document.getElementById('snow-container');
            const snowCount = 50; // Number of snowflakes

            for (let i = 0; i < snowCount; i++) {
                const snow = document.createElement('div');
                snow.className = 'snowflake';
                snow.style.left = Math.random() * 100 + 'vw';
                snow.style.animationDuration = Math.random() * 3 + 2 + 's'; // 2-5s
                snow.style.opacity = Math.random();
                snowContainer.appendChild(snow);
            }
        });
    </script>
</body>

</html>