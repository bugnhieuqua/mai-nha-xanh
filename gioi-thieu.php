<?php 
require_once 'config/bootstrap.php'; // Start session
$page_title = "Giới thiệu";
include 'includes/header.php'; 
?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1 class="typing-effect">Giới thiệu về chúng tôi</h1>
        <p>Mái Nhà Xanh - Nơi khởi đầu cho ước mơ của bạn</p>
    </div>
</section>

<!-- About Section Styles - Override -->
<style>
/* ── Trang Giới thiệu: Luôn dùng chữ tối để đọc được trên nền mint ── */
.about-section .about-text h2 {
  color: #0f172a !important;
  font-weight: 800 !important;
  font-size: 2.5rem;
}
.about-section .about-text p {
  color: #1e293b !important;
  font-weight: 500 !important;
  line-height: 1.8;
}
.about-section .stat-item {
  background: rgba(255, 255, 255, 0.88) !important;
  border: 1px solid rgba(255,255,255,0.7) !important;
  backdrop-filter: blur(10px) !important;
  -webkit-backdrop-filter: blur(10px) !important;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important;
  border-radius: 12px !important;
}
.about-section .stat-item h3 {
  color: #059669 !important;
  font-weight: 800 !important;
}
.about-section .stat-item p {
  color: #1e293b !important;
  font-weight: 600 !important;
}

/* Dark mode: vẫn dùng chữ tối vì nền section này vẫn là teal/mint */
[data-theme="dark"] .about-section .about-text h2 {
  color: #0f172a !important;
}
[data-theme="dark"] .about-section .about-text p {
  color: #1e293b !important;
  font-weight: 500 !important;
}
[data-theme="dark"] .about-section .stat-item {
  background: rgba(255, 255, 255, 0.88) !important;
  border-color: rgba(255,255,255,0.7) !important;
}
[data-theme="dark"] .about-section .stat-item h3 {
  color: #059669 !important;
}
[data-theme="dark"] .about-section .stat-item p {
  color: #1e293b !important;
}
</style>

<!-- About Section -->
<section class="about-section">
    <div class="container">
        <div class="about-content">
            <div class="about-image">
                <img src="assets/images/logo.png" alt="Mái Nhà Xanh">
            </div>
            <div class="about-text">
                <h2>Về Mái Nhà Xanh</h2>
                <p>Mái Nhà Xanh được thành lập với sứ mệnh mang đến những căn phòng trọ chất lượng, an toàn và giá cả hợp lý cho sinh viên và người đi làm tại TP.Vinh.</p>
                <p>Chúng tôi hiểu rằng một nơi ở tốt là nền tảng quan trọng để bạn phát triển sự nghiệp và học tập. Vì vậy, chúng tôi luôn nỗ lực để tạo ra môi trường sống thoải mái, tiện nghi và thân thiện nhất cho khách hàng.</p>
                <div class="about-stats">
                    <div class="stat-item">
                        <h3>500+</h3>
                        <p>Khách hàng hài lòng</p>
                    </div>
                    <div class="stat-item">
                        <h3>50+</h3>
                        <p>Phòng trọ chất lượng</p>
                    </div>
                    <div class="stat-item">
                        <h3>5+</h3>
                        <p>Năm kinh nghiệm</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Vision Mission Section -->
<section class="vision-mission">
    <div class="container">
        <div class="vm-grid">
            <div class="vm-card">
                <div class="vm-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <h3>Tầm nhìn</h3>
                <p>Trở thành đơn vị cho thuê phòng trọ hàng đầu tại TP.Vinh, mang lại giá trị tốt nhất cho khách hàng với dịch vụ chuyên nghiệp và tận tâm.</p>
            </div>
            
            <div class="vm-card">
                <div class="vm-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h3>Sứ mệnh</h3>
                <p>Cung cấp không gian sống chất lượng, an toàn và giá cả hợp lý, đồng thành tạo dựng cộng đồng sinh viên và người đi làm gắn kết, hỗ trợ lẫn nhau.</p>
            </div>
            
            <div class="vm-card">
                <div class="vm-icon">
                    <i class="fas fa-gem"></i>
                </div>
                <h3>Giá trị cốt lõi</h3>
                <p>Uy tín - Chất lượng - Tận tâm. Chúng tôi cam kết đặt lợi ích của khách hàng lên hàng đầu, luôn lắng nghe và cải thiện dịch vụ.</p>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="team-section">
    <div class="container">
        <div class="section-header">
            <h2>Đội ngũ của chúng tôi</h2>
            <p>Những người luôn sẵn sàng hỗ trợ bạn</p>
        </div>
        
        <div class="team-grid">
            <div class="team-card">
                <div class="team-image">
                    <img src="https://via.placeholder.com/300x300?text=Team+Member" alt="Team Member">
                </div>
                <h3>Nguyễn Đại Thắng</h3>
                <p class="team-role">Giám đốc điều hành</p>
                <div class="team-social">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
            
            <div class="team-card">
                <div class="team-image">
                    <img src="https://via.placeholder.com/300x300?text=Team+Member" alt="Team Member">
                </div>
                <h3>Cao Thị Quỳnh Như</h3>
                <p class="team-role">Quản lý kinh doanh</p>
                <div class="team-social">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
            
            <div class="team-card">
                <div class="team-image">
                    <img src="https://via.placeholder.com/300x300?text=Team+Member" alt="Team Member">
                </div>
                <h3>Nguyễn Bá Bổng</h3>
                <p class="team-role">Trưởng phòng chăm sóc KH</p>
                <div class="team-social">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="why-choose">
    <div class="container">
        <div class="section-header">
            <h2>Vì sao chọn Mái Nhà Xanh?</h2>
        </div>
        
        <div class="why-grid">
            <div class="why-item">
                <i class="fas fa-check-circle"></i>
                <h4>Giá cả minh bạch</h4>
                <p>Không phát sinh chi phí ngoài hợp đồng</p>
            </div>
            
            <div class="why-item">
                <i class="fas fa-check-circle"></i>
                <h4>Thủ tục nhanh gọn</h4>
                <p>Ký hợp đồng và nhận phòng trong ngày</p>
            </div>
            
            <div class="why-item">
                <i class="fas fa-check-circle"></i>
                <h4>Hỗ trợ 24/7</h4>
                <p>Đội ngũ luôn sẵn sàng giải đáp mọi thắc mắc</p>
            </div>
            
            <div class="why-item">
                <i class="fas fa-check-circle"></i>
                <h4>An ninh tốt</h4>
                <p>Camera giám sát, bảo vệ túc trực</p>
            </div>
            
            <div class="why-item">
                <i class="fas fa-check-circle"></i>
                <h4>Tiện ích đầy đủ</h4>
                <p>Wifi, điện nước, máy giặt, điều hòa</p>
            </div>
            
            <div class="why-item">
                <i class="fas fa-check-circle"></i>
                <h4>Vị trí thuận lợi</h4>
                <p>Gần trường học, bệnh viện, siêu thị</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2 class="animate-fade-up">Bạn muốn tìm hiểu thêm?</h2>
            <p class="animate-fade-up" style="animation-delay: 0.2s;">Liên hệ ngay với chúng tôi để được tư vấn chi tiết</p>
            <a href="lien-he.php" class="btn-hero-primary animate-fade-up" style="animation-delay: 0.4s; display: inline-block;">Liên hệ ngay</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
