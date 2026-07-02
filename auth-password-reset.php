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
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ số và ký tự đặc biệt.';
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
            font-family: 'Poppins', 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            overflow: hidden;
            position: relative;
        }

        .bg-video {
            position: fixed;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            z-index: -2;
            object-fit: cover;
            filter: brightness(0.7);
        }

        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            z-index: -1;
        }

        .card {
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
            box-sizing: border-box;
        }

        .card-logo {
            text-align: center;
            margin-bottom: 10px;
        }
        .card-logo img {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            text-align: center;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 4px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .card .subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
            margin-bottom: 16px;
        }

        .close-btn {
            position: absolute;
            top: 16px; right: 18px;
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.2s, transform 0.2s;
        }
        .close-btn:hover { color: #fff; transform: rotate(90deg); }

        .form-group { margin-bottom: 12px; text-align: left; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.95);
        }

        .password-wrapper { position: relative; }
        .password-wrapper input {
            width: 100%;
            padding: 12px 42px 12px 16px;
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
        .password-wrapper input::placeholder {
            color: #475569;
            font-weight: 400;
            opacity: 0.9;
        }
        .password-wrapper input:focus {
            background: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #475569;
            font-size: 0.95rem;
            transition: color 0.2s;
            z-index: 10;
        }
        .password-toggle:hover { color: #1d4ed8; }

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
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #5c9e24 0%, #1b5e20 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(46, 125, 50, 0.4);
        }

        /* Password Strength Meter */
        .password-strength-meter {
            margin-top: -4px;
            margin-bottom: 10px;
            text-align: left;
            padding: 6px 10px;
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
            transition: color 0.3s ease;
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
        .match-status {
            font-size: 0.76rem;
            margin-top: -4px;
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
    </style>
</head>
<body>

    <!-- Video Background -->
    <video autoplay loop muted playsinline class="bg-video">
        <source src="mai-nha-xanh.mp4" type="video/mp4">
    </video>
    <div class="bg-overlay"></div>

<div class="card">
    <a href="login.php" class="close-btn" title="Quay lại đăng nhập"><i class="fas fa-times"></i></a>

    <?php if ($token_invalid ?? false): ?>
    <!-- === TOKEN KHÔNG HỢP LỆ === -->
    <div class="invalid-view">
        <div class="icon">⏳</div>
        <h2>Liên kết hết hạn</h2>
        <p>Liên kết đặt lại mật khẩu không hợp lệ hoặc đã quá 30 phút. Vui lòng yêu cầu lại.</p>
        <a href="auth-password-forgot.php" class="back-btn">Gửi lại yêu cầu</a>
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

    <form method="POST" action="auth-password-reset.php" id="reset-pwd-form">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

        <div class="form-group">
            <label for="password">Mật khẩu mới</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Mật khẩu mới" required>
                <i class="fas fa-eye password-toggle" id="togglePassword"></i>
            </div>
            <!-- Password Strength Meter -->
            <div class="password-strength-meter" id="meter-password">
                <div class="strength-bars">
                    <div class="bar" id="bar-password-1"></div>
                    <div class="bar" id="bar-password-2"></div>
                    <div class="bar" id="bar-password-3"></div>
                    <div class="bar" id="bar-password-4"></div>
                </div>
                <div class="strength-status">
                    <span class="strength-text">Mức độ: <strong id="status-password" style="color: #64748b;">Chưa nhập</strong></span>
                </div>
                <ul class="strength-requirements">
                    <li id="req-password-len" class="invalid"><i class="fas fa-circle"></i> Tối thiểu 8 ký tự</li>
                    <li id="req-password-upper" class="invalid"><i class="fas fa-circle"></i> Có chữ hoa (A-Z)</li>
                    <li id="req-password-num" class="invalid"><i class="fas fa-circle"></i> Có chữ số (0-9)</li>
                    <li id="req-password-special" class="invalid"><i class="fas fa-circle"></i> Ký tự đặc biệt</li>
                </ul>
            </div>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Xác nhận mật khẩu</label>
            <div class="password-wrapper">
                <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Nhập lại mật khẩu" required>
                <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
            </div>
            <div id="match-status-reset" class="match-status"></div>
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

// Password Strength Meter & Matching Logic
const pwdInput = document.getElementById('password');
const pwdConfInput = document.getElementById('password_confirmation');
const matchStatusReset = document.getElementById('match-status-reset');

if (pwdInput) {
    const bars = [
        document.getElementById('bar-password-1'),
        document.getElementById('bar-password-2'),
        document.getElementById('bar-password-3'),
        document.getElementById('bar-password-4')
    ];
    const statusEl = document.getElementById('status-password');
    const reqLen = document.getElementById('req-password-len');
    const reqUpper = document.getElementById('req-password-upper');
    const reqNum = document.getElementById('req-password-num');
    const reqSpecial = document.getElementById('req-password-special');

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

    pwdInput.addEventListener('input', function() {
        const val = pwdInput.value;
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

        if (val.length === 0) score = 0;

        const colors = ['#e2e8f0', '#ef4444', '#f59e0b', '#10b981', '#059669'];
        const labels = [
            { text: 'Chưa nhập', color: '#64748b' },
            { text: 'Tệ', color: '#ef4444' },
            { text: 'Trung bình', color: '#f59e0b' },
            { text: 'Tốt', color: '#10b981' },
            { text: 'Rất tốt', color: '#059669' }
        ];

        bars.forEach((bar, idx) => {
            if (bar) {
                bar.style.backgroundColor = idx < score ? colors[score] : '#e2e8f0';
            }
        });

        if (statusEl) {
            statusEl.textContent = labels[score].text;
            statusEl.style.color = labels[score].color;
        }

        checkResetMatch();
    });
}

function checkResetMatch() {
    if (!pwdConfInput || !matchStatusReset) return;
    const val1 = pwdInput ? pwdInput.value : '';
    const val2 = pwdConfInput.value;

    if (val2.length === 0) {
        matchStatusReset.className = 'match-status';
        matchStatusReset.textContent = '';
        return;
    }

    if (val1 === val2) {
        matchStatusReset.className = 'match-status valid';
        matchStatusReset.innerHTML = '<i class="fas fa-check-circle"></i> Mật khẩu trùng khớp';
    } else {
        matchStatusReset.className = 'match-status invalid';
        matchStatusReset.innerHTML = '<i class="fas fa-times-circle"></i> Mật khẩu không trùng khớp!';
    }
}

if (pwdConfInput) {
    pwdConfInput.addEventListener('input', checkResetMatch);
}

const resetForm = document.getElementById('reset-pwd-form');
if (resetForm) {
    resetForm.addEventListener('submit', function(e) {
        if (pwdInput && pwdConfInput) {
            const val = pwdInput.value;
            const confirmVal = pwdConfInput.value;

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
</script>
</body>
</html>
