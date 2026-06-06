<?php
require_once 'config/session.php';
require_once 'config/database.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');

$database = new Database();
$db = $database->getConnection();

// --- Auto-migration for missing columns ---
try { $db->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL AFTER password"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME NULL AFTER reset_token"); } catch (Exception $e) {}

// --- Check token validity ---
$query = "SELECT * FROM users WHERE reset_token = :token LIMIT 1";
$stmt  = $db->prepare($query);
$stmt->bindParam(':token', $token);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user === false || strtotime($user["reset_token_expiry"]) <= time()) {
    $token_invalid = true;
}

// --- Handle POST (process the reset) ---
$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !($token_invalid ?? false)) {
    $password              = $_POST['password'] ?? '';
    $password_confirmation = $_POST['password_confirmation'] ?? '';
    $token                 = $_POST['token'] ?? '';

    // Xác thực CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $error = "Lỗi bảo mật: CSRF Token không hợp lệ. Vui lòng thử lại.";
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $password_confirmation) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :password_hash, reset_token = NULL, reset_token_expiry = NULL WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':id', $user['id']);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu — Mái Nhà Xanh</title>
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Hide browser default password reveal buttons */
        input::-ms-reveal,
        input::-ms-clear,
        input::-webkit-password-reveal-button { display: none !important; }

        body {
            background: linear-gradient(135deg, #059669 0%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Poppins', 'Inter', sans-serif;
        }

        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            padding: 40px 36px;
            width: 100%;
            max-width: 460px;
            position: relative;
        }

        .card-logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .card-logo img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
        }

        .card h2 {
            text-align: center;
            color: #1e293b;
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .card .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 28px;
        }

        .close-btn {
            position: absolute;
            top: 16px; right: 18px;
            font-size: 1.3rem;
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.2s;
        }
        .close-btn:hover { color: #ef4444; }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            font-size: 0.92rem;
            color: #334155;
        }

        .password-wrapper { position: relative; }
        .password-wrapper input {
            width: 100%;
            padding: 13px 44px 13px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.97rem;
            transition: 0.25s;
            outline: none;
            box-sizing: border-box;
        }
        .password-wrapper input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 0.95rem;
            transition: color 0.2s;
            z-index: 10;
        }
        .password-toggle:hover { color: #3b82f6; }

        /* Hint text dưới ô input */
        .input-hint {
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 5px;
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #059669, #3b82f6);
            color: #fff;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity 0.25s, transform 0.2s;
            letter-spacing: 0.5px;
        }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); }

        /* Alerts */
        .alert {
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-error  { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
        .alert-success{ background: #f0fdf4; color: #166534; border: 1px solid #86efac; }

        /* Success state */
        .success-view { text-align: center; }
        .success-view .icon { font-size: 3.5rem; margin-bottom: 12px; }
        .success-view p { color: #475569; margin-bottom: 20px; }
        .back-btn {
            display: inline-block;
            padding: 12px 32px;
            background: linear-gradient(135deg, #059669, #3b82f6);
            color: #fff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            transition: opacity 0.2s;
        }
        .back-btn:hover { opacity: 0.88; }

        /* Invalid token state */
        .invalid-view { text-align: center; }
        .invalid-view .icon { font-size: 3rem; margin-bottom: 12px; }
        .invalid-view p { color: #64748b; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="card">
    <a href="login.php" class="close-btn" title="Quay lại đăng nhập"><i class="fas fa-times"></i></a>

    <?php if ($token_invalid ?? false): ?>
    <!-- === TOKEN KHÔNG HỢP LỆ === -->
    <div class="invalid-view">
        <div class="icon">⏳</div>
        <h2>Liên kết hết hạn</h2>
        <p>Liên kết đặt lại mật khẩu không hợp lệ hoặc đã quá 30 phút. Vui lòng yêu cầu lại.</p>
        <a href="forgot_password.php" class="back-btn">Gửi lại yêu cầu</a>
    </div>

    <?php elseif ($success): ?>
    <!-- === ĐẶT LẠI THÀNH CÔNG === -->
    <div class="success-view">
        <div class="icon">✅</div>
        <h2 style="color:#059669;">Đặt lại thành công!</h2>
        <p>Mật khẩu của bạn đã được cập nhật. Bạn có thể đăng nhập ngay bây giờ.</p>
        <a href="login.php" class="back-btn">Đăng nhập ngay</a>
    </div>

    <?php else: ?>
    <!-- === FORM ĐẶT LẠI === -->
    <div class="card-logo">
        <img src="assets/images/logo.png" alt="Mái Nhà Xanh">
    </div>
    <h2>Đặt lại mật khẩu</h2>
    <p class="subtitle">Nhập mật khẩu mới cho tài khoản của bạn</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="reset_password.php">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

        <div class="form-group">
            <label for="password">Mật khẩu mới</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Ít nhất 6 ký tự" required>
                <i class="fas fa-eye password-toggle" id="togglePassword"></i>
            </div>
            <div class="input-hint"><i class="fas fa-info-circle"></i> Mật khẩu phải có ít nhất 6 ký tự</div>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Xác nhận mật khẩu</label>
            <div class="password-wrapper">
                <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Nhập lại mật khẩu" required>
                <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
            </div>
        </div>

        <button type="submit" class="btn-submit"><i class="fas fa-lock"></i> Lưu mật khẩu mới</button>
    </form>
    <?php endif; ?>
</div>

<script>
function setupToggle(inputId, toggleId) {
    const input  = document.getElementById(inputId);
    const toggle = document.getElementById(toggleId);
    if (!input || !toggle) return;
    toggle.addEventListener('click', function() {
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        this.classList.toggle('fa-eye',       isText);
        this.classList.toggle('fa-eye-slash', !isText);
    });
}
setupToggle('password', 'togglePassword');
setupToggle('password_confirmation', 'toggleConfirmPassword');

// Client-side validation hint
const passInput = document.getElementById('password');
const confInput = document.getElementById('password_confirmation');
if (passInput && confInput) {
    confInput.addEventListener('input', function() {
        if (this.value && this.value !== passInput.value) {
            this.style.borderColor = '#ef4444';
        } else {
            this.style.borderColor = '';
        }
    });
}
</script>
</body>
</html>
