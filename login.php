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
        $email    = $_POST['reg_email'] ?? '';
        $showRegisterForm = true; // Giữ form đăng ký hiển thị khi có lỗi

        if ($username && $password && $email) {
            try {
                // Kiểm tra trùng username VÀ email cùng lúc
                $check_query = "SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 2";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->bindParam(':email', $email);
                $check_stmt->execute();
                $existing = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

                $usernameExists = false;
                $emailExists    = false;
                foreach ($existing as $row) {
                    if ($row['username'] === $username) $usernameExists = true;
                    if ($row['email']    === $email)    $emailExists    = true;
                }

                if ($usernameExists) {
                    $message     = "Tên đăng nhập đã tồn tại! Vui lòng chọn tên đăng nhập khác.";
                    $messageType = 'error';
                } elseif ($emailExists) {
                    $message     = "Email này đã được sử dụng! Vui lòng dùng địa chỉ email khác.";
                    $messageType = 'error';
                } else {
                    $query = "INSERT INTO users (username, password, email) VALUES (:username, :password, :email)";
                    $stmt  = $db->prepare($query);
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':email',    $email);

                    if ($stmt->execute()) {
                        $message          = "Đăng ký thành công! Vui lòng đăng nhập.";
                        $messageType      = 'success';
                        $showRegisterForm = false; // Đăng ký xong thì về form đăng nhập
                    } else {
                        $message     = "Có lỗi xảy ra, vui lòng thử lại.";
                        $messageType = 'error';
                    }
                }
            } catch (PDOException $e) {
                $message     = "Lỗi hệ thống: " . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message     = "Vui lòng điền đầy đủ thông tin đăng ký.";
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
                $payload = json_decode(base64_decode($parts[1]), true);
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
    <meta name="description" content="Trang đăng nhập hệ thống quản lý phòng trọ Mái Nhà Xanh. Chỉ dành cho người dùng đã đăng ký.">
    <meta name="author" content="Mái Nhà Xanh - mainhaxanhh.liveblog365.com">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        
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
        /* Animated Background */
        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            animation: move 12s infinite alternate;
            opacity: 0.6;
        }
        .shape:nth-child(1) { top: -100px; left: -100px; width: 500px; height: 500px; background: #ff9a9e; }
        .shape:nth-child(2) { bottom: -50px; right: -50px; width: 450px; height: 450px; background: #a18cd1; }
        
        @keyframes move {
            from { transform: translate(0, 0) rotate(0deg); }
            to { transform: translate(40px, 40px) rotate(10deg); }
        }


        .container {
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(12px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            width: 420px;
            padding: 40px;
            text-align: center;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            color: rgba(255,255,255,0.7);
            font-size: 1.5rem;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
        }
        .close-btn:hover { color: #fff; transform: rotate(90deg); }

        .logo {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

        .form-header h2 {
            color: #fff;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .input-group { position: relative; margin-bottom: 20px; }
        .input-group i {
            position: absolute; left: 20px; top: 50%;
            transform: translateY(-50%);
            color: #1e293b; /* Dark Slate for visibility */
            z-index: 2;
        }
        .input-group input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.15);
            border-radius: 50px;
            color: #fff;
            outline: none;
            transition: 0.3s;
            font-size: 1rem;
        }
        .input-group input::placeholder { color: #64748b; }
        .input-group input:focus {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 15px rgba(255,255,255,0.2);
        }

        .btn-submit {
            background: linear-gradient(135deg, #b6cf7bff 0%, #3ea51fff 100%);
            border: none;
            padding: 15px;
            border-radius: 50px;
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(0, 242, 254, 0.3);
            
        }
        .btn-submit:hover { background: linear-gradient(135deg, #436d5bff 0%, #1896d1ff 100%);transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 242, 254, 0.4); }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }

        .btn-google {
            background: #fff;
            border: none;
            padding: 12px;
            border-radius: 50px;
            color: #333;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn-google:hover {
            background: #f8f9fa;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .btn-google img.google-icon {
            width: 22px;
            height: 22px;
        }


        .toggle-text {
            margin-top: 20px;
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
        }
        .toggle-text a {
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .toggle-text a:hover { text-decoration: underline; }

        .alert {
            padding: 10px; border-radius: 10px; margin-bottom: 20px; color: #fff; font-size: 0.9rem;
        }
        .alert-success { background: rgba(46, 204, 113, 0.3); border: 1px solid #2ecc71; }
        .alert-error { background: rgba(231, 76, 60, 0.3); border: 1px solid #e74c3c; }

        /* Hidden class for toggling */
        .hidden { display: none; }

        /* Password Toggle */
        .input-group i.password-toggle {
            left: auto;
            right: 20px;
            cursor: pointer;
            pointer-events: auto;
            transition: color 0.2s;
        }
        
        .input-group i.password-toggle:hover {
            color: #3b82f6 !important;
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
    <div id="snow-container"></div>
    <div class="shape"></div><div class="shape"></div>

    <div class="container">
        <a href="phong-tro.php" class="close-btn"><i class="fas fa-times"></i></a>
        
        <div class="logo">
            <img src="assets/images/logo.png" alt="Logo">
        </div>

        <?php if($message): ?>
            <div id="login-alert" class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- LOGIN FORM -->
        <div id="login-form" class="<?php echo (!empty($showRegisterForm) && $showRegisterForm) ? 'hidden' : ''; ?>">
            <div class="form-header">
                <h2>Đăng Nhập</h2>
            </div>
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
                <div style="text-align: right; margin-bottom: 20px;">
                    <a href="forgot_password.php" style="color: #fff; font-size: 0.9rem; text-decoration: none; opacity: 0.9;">Quên mật khẩu?</a>
                </div>
                <button type="submit" class="btn-submit">Đăng Nhập</button>

                <div class="divider">Hoặc đăng nhập với</div>

                <!-- Nút Custom Google Login -->
                <button type="button" class="btn-google" onclick="document.querySelector('.g_id_signin div[role=button]').click();">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google" class="google-icon">
                    Đăng nhập bằng Google
                </button>

                <!-- Hidden Google GIS Button (Thực tế thực hiện auth) -->
                <div style="display: none;">
                    <div id="g_id_onload"
                        data-client_id="548444871359-3v82e42qe9fjk3f3i8u8k27e2vp6pfn5.apps.googleusercontent.com"
                        data-context="signin"
                        data-ux_mode="popup"
                        data-callback="handleGoogleLogin"
                        data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin"
                        data-type="standard"
                        data-shape="rectangular"
                        data-theme="outline"
                        data-text="signin_with"
                        data-size="large"
                        data-logo_alignment="left">
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
                    <input type="text" name="reg_username" placeholder="Tên đăng nhập" value="<?php echo htmlspecialchars($_POST['reg_username'] ?? ''); ?>" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="reg_email" placeholder="Email" value="<?php echo htmlspecialchars($_POST['reg_email'] ?? ''); ?>" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="reg_password" id="reg_password" placeholder="Mật khẩu" required>
                    <i class="fas fa-eye password-toggle" id="toggleRegPassword"></i>
                </div>
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
            
            if(toggle && input) {
                toggle.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        }

        setupPasswordToggle('password', 'togglePassword');
        setupPasswordToggle('reg_password', 'toggleRegPassword');

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
        document.addEventListener('DOMContentLoaded', function() {
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
