<?php
require_once 'config/session.php';
$page_title = "Quên mật khẩu";
include 'includes/header.php';
?>

<div class="container" style="padding: 100px 20px; min-height: 60vh; display: flex; justify-content: center; align-items: center;">
    <div style="background: rgba(255, 255, 255, 0.9); padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 500px;">
        <h2 style="text-align: center; margin-bottom: 20px; color: var(--primary-color);">Quên mật khẩu</h2>
        <p style="text-align: center; margin-bottom: 30px; color: #666;">Nhập email của bạn để nhận liên kết đặt lại mật khẩu.</p>
        
        <?php if(isset($_SESSION['status'])): ?>
            <div class="alert alert-success" style="padding: 15px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 20px;">
                <?php 
                    echo $_SESSION['status']; 
                    unset($_SESSION['status']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 5px; margin-bottom: 20px;">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form action="send_password_reset.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="email" style="display: block; margin-bottom: 8px; font-weight: 600;">Email đã đăng ký</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="example@email.com" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
            </div>
            <button type="submit" name="reset_password_btn" class="btn btn-primary btn-block" style="width: 100%; padding: 12px; font-size: 1.1rem; border-radius: 50px;">Gửi liên kết</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="login.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600;"><i class="fas fa-arrow-left"></i> Quay lại đăng nhập</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
