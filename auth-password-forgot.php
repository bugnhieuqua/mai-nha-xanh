<?php
require_once 'config/session.php';
$page_title = "Quên mật khẩu";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu — Mái Nhà Xanh</title>
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
            padding: 26px 28px 22px;
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
            margin-bottom: 18px;
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

        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.88rem;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .input-group {
            position: relative;
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
            padding: 12px 18px 12px 48px;
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

        .back-link {
            display: inline-block;
            margin-top: 14px;
            color: #ffffff;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            opacity: 0.9;
            transition: opacity 0.2s;
        }
        .back-link:hover { opacity: 1; text-decoration: underline; }
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
        <div class="card-logo">
            <img src="assets/images/logo.png" alt="Mái Nhà Xanh">
        </div>
        <h2>Quên mật khẩu</h2>
        <p class="subtitle">Nhập email của bạn để nhận liên kết đặt lại mật khẩu</p>

        <?php if(isset($_SESSION['status'])): ?>
            <div class="alert alert-success" style="padding: 10px; background: rgba(46, 204, 113, 0.3); color: #fff; border: 1px solid #2ecc71; border-radius: 10px; margin-bottom: 14px; font-size: 0.85rem;">
                <?php 
                    echo $_SESSION['status']; 
                    unset($_SESSION['status']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="padding: 10px; background: rgba(231, 76, 60, 0.3); color: #fff; border: 1px solid #e74c3c; border-radius: 10px; margin-bottom: 14px; font-size: 0.85rem;">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form action="api-auth-password-reset-send.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <div class="form-group">
                <label for="email">Email đã đăng ký</label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" id="email" placeholder="example@email.com" required>
                </div>
            </div>
            <button type="submit" name="reset_password_btn" class="btn-submit"><i class="fas fa-paper-plane"></i> Gửi liên kết</button>
        </form>
        
        <div>
            <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Quay lại đăng nhập</a>
        </div>
    </div>
</body>
</html>
