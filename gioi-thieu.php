<?php 
require_once 'config/bootstrap.php';
$page_title = "Giới thiệu";
include 'includes/header.php'; 
?>

<style>
/* ============================================================
   TRANG GIỚI THIỆU - Premium Modern Design
   ============================================================ */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

:root {
    --text-color: #0f172a;
    --text-muted: #475569;
    --card-bg: #ffffff;
    --bg-color: #f8fafc;
    --border-color: #e2e8f0;
    --primary-color: #10b981;
}

[data-theme="dark"] {
    --text-color: #f1f5f9;
    --text-muted: #94a3b8;
    --card-bg: #1e293b;
    --bg-color: #0f172a;
    --border-color: rgba(255, 255, 255, 0.08);
}

.gt-hero {
    position: relative;
    min-height: 480px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(rgba(15, 23, 42, 0.72), rgba(6, 78, 59, 0.78)), url('assets/images/about_hero_bg.png') no-repeat center center / cover;
    text-align: center;
    padding: 80px 24px;
}
.gt-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 30% 50%, rgba(16,185,129,0.15) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(5,150,105,0.1) 0%, transparent 50%);
    pointer-events: none;
}
/* Animated circles */
.gt-hero-circle {
    position: absolute;
    border-radius: 50%;
    opacity: 0.06;
    animation: floatCircle 8s ease-in-out infinite;
}
.gt-hero-circle:nth-child(1) { width:300px; height:300px; background:#10b981; top:-100px; left:-80px; animation-delay:0s; }
.gt-hero-circle:nth-child(2) { width:200px; height:200px; background:#059669; bottom:-60px; right:-40px; animation-delay:2s; }
.gt-hero-circle:nth-child(3) { width:150px; height:150px; background:#34d399; top:50%; right:10%; animation-delay:4s; }
@keyframes floatCircle {
    0%,100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-20px) scale(1.05); }
}
.gt-hero-inner { position: relative; z-index: 2; max-width: 720px; margin: 0 auto; }
.gt-hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.5);
    color: #34d399; font-size: 0.8rem; font-weight: 600;
    padding: 6px 16px; border-radius: 50px; margin-bottom: 24px;
    letter-spacing: 0.5px; text-transform: uppercase;
}
.gt-hero h1 {
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 900;
    color: #fff;
    line-height: 1.15;
    margin-bottom: 20px;
    letter-spacing: -1px;
}
.gt-hero h1 span { color: #34d399; text-shadow: 0 2px 10px rgba(52, 211, 153, 0.3); }
.gt-hero-sub {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.85);
    line-height: 1.7;
    margin-bottom: 36px;
    text-shadow: 0 1px 3px rgba(0,0,0,0.5);
}
.gt-hero-actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
.gt-btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff; font-weight: 700; font-size: 0.95rem;
    padding: 14px 28px; border-radius: 12px;
    text-decoration: none; border: none; cursor: pointer;
    transition: all 0.3s; box-shadow: 0 4px 20px rgba(16,185,129,0.35);
}
.gt-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(16,185,129,0.5); color: #fff; }
.gt-btn-outline {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.25);
    color: #fff; font-weight: 600; font-size: 0.95rem;
    padding: 14px 28px; border-radius: 12px;
    text-decoration: none; cursor: pointer; transition: all 0.3s;
}
.gt-btn-outline:hover { background: rgba(255,255,255,0.18); color: #fff; transform: translateY(-2px); }

/* Stats bar */
.gt-stats-bar {
    background: var(--card-bg, #fff);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding: 0;
}
.gt-stats-inner {
    max-width: 1100px; margin: 0 auto;
    display: grid; grid-template-columns: repeat(4, 1fr);
    text-align: center;
}
.gt-stat-item {
    padding: 28px 20px;
    border-right: 1px solid var(--border-color, #e2e8f0);
}
.gt-stat-item:last-child { border-right: none; }
.gt-stat-num {
    font-size: 2.2rem; font-weight: 900;
    background: linear-gradient(135deg, #10b981, #059669);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; display: block; line-height: 1;
}
.gt-stat-label {
    font-size: 0.82rem; color: var(--text-muted, #64748b);
    font-weight: 500; margin-top: 6px; display: block;
}

/* About section */
.gt-about {
    padding: 90px 24px;
    background: var(--bg-color, #f8fafc);
}
.gt-container { max-width: 1100px; margin: 0 auto; }
.gt-about-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center;
}
.gt-about-image-wrap {
    position: relative;
}
.gt-about-image-box {
    border-radius: 24px; overflow: hidden;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    display: flex; align-items: center; justify-content: center;
    height: 360px;
    box-shadow: 0 20px 60px rgba(16,185,129,0.15);
}
[data-theme="dark"] .gt-about-image-box {
    background: linear-gradient(135deg, #064e3b, #022c22);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
}
.gt-about-image-box img {
    width: 240px; height: 240px; object-fit: contain;
    filter: drop-shadow(0 10px 30px rgba(16,185,129,0.2));
    animation: floatLogo 4s ease-in-out infinite;
}
@keyframes floatLogo {
    0%,100%{ transform: translateY(0); }
    50%{ transform: translateY(-12px); }
}
.gt-about-badge-float {
    position: absolute; bottom: -16px; right: -16px;
    background: linear-gradient(135deg,#10b981,#059669);
    color: #fff; font-size: 0.8rem; font-weight: 700;
    padding: 12px 18px; border-radius: 14px;
    box-shadow: 0 8px 24px rgba(16,185,129,0.35);
    display: flex; align-items: center; gap: 8px;
}
.gt-section-tag {
    display: inline-block;
    background: rgba(16,185,129,0.1); color: #059669;
    font-size: 0.78rem; font-weight: 700;
    padding: 5px 14px; border-radius: 50px;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin-bottom: 16px;
}
[data-theme="dark"] .gt-section-tag { background: rgba(16,185,129,0.15); color: #34d399; }
.gt-about-title {
    font-size: clamp(1.6rem,3vw,2.3rem);
    font-weight: 800; line-height: 1.25;
    color: var(--text-color, #0f172a);
    margin-bottom: 20px;
}
.gt-about-title span { color: #10b981; }
.gt-about-desc {
    font-size: 1rem; color: var(--text-muted, #475569);
    line-height: 1.8; margin-bottom: 28px;
}
.gt-check-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
.gt-check-list li {
    display: flex; align-items: flex-start; gap: 12px;
    font-size: 0.95rem; color: var(--text-color, #1e293b); font-weight: 500;
}
.gt-check-icon {
    width: 22px; height: 22px; border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.7rem; flex-shrink: 0; margin-top: 2px;
}

/* Vision/Mission */
.gt-vm {
    padding: 90px 24px;
    background: var(--card-bg, #fff);
}
.gt-section-header { text-align: center; margin-bottom: 60px; }
.gt-section-title {
    font-size: clamp(1.6rem,3vw,2.2rem);
    font-weight: 800; color: var(--text-color,#0f172a);
    margin-bottom: 14px; letter-spacing: -0.5px;
}
.gt-section-sub { font-size: 1rem; color: var(--text-muted,#64748b); max-width: 560px; margin: 0 auto; line-height: 1.7; }
.gt-vm-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 28px; }
.gt-vm-card {
    background: var(--bg-color, #f8fafc);
    border: none;
    border-radius: 20px; padding: 36px 28px;
    transition: all 0.3s; position: relative; overflow: hidden;
}
.gt-vm-card:hover { transform: translateY(-6px); box-shadow: 0 20px 50px rgba(0,0,0,0.08); }
.gt-vm-icon-wrap {
    width: 56px; height: 56px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; margin-bottom: 20px;
}
.gt-vm-card:nth-child(1) .gt-vm-icon-wrap { background: rgba(16,185,129,0.12); color: #10b981; }
.gt-vm-card:nth-child(2) .gt-vm-icon-wrap { background: rgba(59,130,246,0.12); color: #3b82f6; }
.gt-vm-card:nth-child(3) .gt-vm-icon-wrap { background: rgba(245,158,11,0.12); color: #f59e0b; }
.gt-vm-card h3 { font-size: 1.15rem; font-weight: 800; color: var(--text-color,#0f172a); margin-bottom: 12px; }
.gt-vm-card p { font-size: 0.92rem; color: var(--text-muted,#64748b); line-height: 1.75; margin: 0; }

/* Why section */
.gt-why {
    padding: 90px 24px;
    background: linear-gradient(135deg, #0f172a 0%, #064e3b 100%);
    position: relative; overflow: hidden;
}
.gt-why::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 70% 50%, rgba(16,185,129,0.15) 0%, transparent 60%);
    pointer-events: none;
}
.gt-why .gt-section-title { color: #fff; }
.gt-why .gt-section-sub { color: rgba(255,255,255,0.65); }
.gt-why-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
.gt-why-item {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 28px 24px;
    transition: all 0.3s;
}
.gt-why-item:hover { background: rgba(255,255,255,0.1); transform: translateY(-4px); }
.gt-why-item-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: linear-gradient(135deg,#10b981,#059669);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.1rem; margin-bottom: 16px;
}
.gt-why-item h4 { font-size: 1rem; font-weight: 700; color: #fff; margin-bottom: 8px; }
.gt-why-item p { font-size: 0.85rem; color: rgba(255,255,255,0.65); line-height: 1.65; margin: 0; }

/* Team */
.gt-team { padding: 90px 24px; background: var(--bg-color,#f8fafc); }
.gt-team-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 28px; }
.gt-team-card {
    background: var(--card-bg,#fff);
    border: 1px solid var(--border-color,#e2e8f0);
    border-radius: 20px; padding: 36px 24px;
    text-align: center; transition: all 0.3s;
    position: relative; overflow: hidden;
}
.gt-team-card:hover { transform: translateY(-8px); box-shadow: 0 24px 60px rgba(0,0,0,0.1); border-color: transparent; }
.gt-team-avatar {
    width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 20px;
    border: 4px solid #10b981;
    box-shadow: 0 8px 24px rgba(16,185,129,0.2);
    overflow: hidden;
    background: #f1f5f9;
}
.gt-team-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.gt-team-card h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-color,#0f172a); margin-bottom: 6px; }
.gt-team-role {
    display: inline-block;
    background: rgba(16,185,129,0.1); color: #059669;
    font-size: 0.78rem; font-weight: 600;
    padding: 4px 12px; border-radius: 50px; margin-bottom: 20px;
}
[data-theme="dark"] .gt-team-role { background: rgba(16,185,129,0.15); color: #34d399; }
.gt-team-social { display: flex; justify-content: center; gap: 10px; }
.gt-team-social a {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--bg-color,#f1f5f9);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-muted,#64748b); font-size: 0.9rem;
    text-decoration: none; transition: all 0.3s;
    border: 1px solid var(--border-color,#e2e8f0);
}
.gt-team-social a:hover { background: #10b981; color: #fff; border-color: #10b981; transform: translateY(-2px); }

/* CTA */
.gt-cta {
    padding: 90px 24px;
    background: var(--card-bg, #fff);
    text-align: center;
}
.gt-cta-inner {
    max-width: 640px; margin: 0 auto;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border-radius: 28px; padding: 60px 40px;
    border: 1px solid rgba(16,185,129,0.2);
}
[data-theme="dark"] .gt-cta-inner {
    background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.15));
    border-color: rgba(16,185,129,0.3);
}
.gt-cta-inner h2 { font-size: 2rem; font-weight: 800; color: var(--text-color,#0f172a); margin-bottom: 14px; }
.gt-cta-inner p { font-size: 1rem; color: var(--text-muted,#475569); margin-bottom: 32px; line-height: 1.7; }

/* Responsive */
@media (max-width: 900px) {
    .gt-stats-inner { grid-template-columns: repeat(2,1fr); }
    .gt-stat-item:nth-child(2) { border-right: none; }
    .gt-stat-item:nth-child(3) { border-top: 1px solid var(--border-color,#e2e8f0); }
    .gt-about-grid { grid-template-columns: 1fr; gap: 40px; }
    .gt-about-image-wrap { order: -1; }
    .gt-vm-grid, .gt-why-grid, .gt-team-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .gt-stats-inner { grid-template-columns: repeat(2,1fr); }
    .gt-cta-inner { padding: 40px 24px; }
}
</style>

<!-- Hero -->
<section class="gt-hero">
    <div class="gt-hero-circle"></div>
    <div class="gt-hero-circle"></div>
    <div class="gt-hero-circle"></div>
    <div class="gt-hero-inner">
        <div class="gt-hero-badge"><i class="fas fa-leaf"></i> Mái Nhà Xanh</div>
        <h1>Nơi khởi đầu cho<br><span>ước mơ của bạn</span></h1>
        <p class="gt-hero-sub">Chúng tôi mang đến những căn phòng trọ chất lượng, an toàn và giá cả hợp lý cho sinh viên và người đi làm tại TP. Vinh (cũ).</p>
        <div class="gt-hero-actions">
            <a href="phong-tro.php" class="gt-btn-primary"><i class="fas fa-home"></i> Xem phòng ngay</a>
            <a href="lien-he.php" class="gt-btn-outline"><i class="fas fa-phone"></i> Liên hệ chúng tôi</a>
        </div>
    </div>
</section>

<!-- Stats -->
<div class="gt-stats-bar">
    <div class="gt-stats-inner">
        <div class="gt-stat-item">
            <span class="gt-stat-num">500+</span>
            <span class="gt-stat-label">Khách hàng hài lòng</span>
        </div>
        <div class="gt-stat-item">
            <span class="gt-stat-num">50+</span>
            <span class="gt-stat-label">Phòng chất lượng</span>
        </div>
        <div class="gt-stat-item">
            <span class="gt-stat-num">5+</span>
            <span class="gt-stat-label">Năm kinh nghiệm</span>
        </div>
        <div class="gt-stat-item">
            <span class="gt-stat-num">98%</span>
            <span class="gt-stat-label">Tỷ lệ hài lòng</span>
        </div>
    </div>
</div>

<!-- About -->
<section class="gt-about">
    <div class="gt-container">
        <div class="gt-about-grid">
            <div class="gt-about-image-wrap">
                <div class="gt-about-image-box">
                    <img src="assets/images/logo.png" alt="Mái Nhà Xanh Logo">
                </div>
                <div class="gt-about-badge-float">
                    <i class="fas fa-shield-alt"></i> Uy tín — Chất lượng — Tận tâm
                </div>
            </div>
            <div class="gt-about-text">
                <span class="gt-section-tag">Về chúng tôi</span>
                <h2 class="gt-about-title">Về <span>Mái Nhà Xanh</span></h2>
                <p class="gt-about-desc">Mái Nhà Xanh được thành lập với sứ mệnh mang đến những căn phòng trọ chất lượng, an toàn và giá cả hợp lý cho sinh viên và người đi làm tại TP. Vinh. Chúng tôi hiểu rằng một nơi ở tốt là nền tảng quan trọng để bạn phát triển sự nghiệp và học tập.</p>
                <ul class="gt-check-list">
                    <li><span class="gt-check-icon"><i class="fas fa-check"></i></span>Phòng trọ sạch sẽ, tiện nghi, an toàn</li>
                    <li><span class="gt-check-icon"><i class="fas fa-check"></i></span>Giá cả minh bạch, không phát sinh chi phí ẩn</li>
                    <li><span class="gt-check-icon"><i class="fas fa-check"></i></span>Hỗ trợ khách hàng 24/7 nhiệt tình</li>
                    <li><span class="gt-check-icon"><i class="fas fa-check"></i></span>Vị trí thuận lợi, gần trường học & trung tâm</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Vision / Mission -->
<section class="gt-vm">
    <div class="gt-container">
        <div class="gt-section-header">
            <span class="gt-section-tag">Định hướng</span>
            <h2 class="gt-section-title">Tầm nhìn &amp; Sứ mệnh</h2>
            <p class="gt-section-sub">Chúng tôi không chỉ cho thuê phòng — chúng tôi xây dựng cộng đồng sống.</p>
        </div>
        <div class="gt-vm-grid">
            <div class="gt-vm-card">
                <div class="gt-vm-icon-wrap"><i class="fas fa-eye"></i></div>
                <h3>Tầm nhìn</h3>
                <p>Trở thành đơn vị cho thuê phòng trọ hàng đầu tại TP. Vinh, mang lại giá trị tốt nhất cho khách hàng với dịch vụ chuyên nghiệp và tận tâm.</p>
            </div>
            <div class="gt-vm-card">
                <div class="gt-vm-icon-wrap"><i class="fas fa-bullseye"></i></div>
                <h3>Sứ mệnh</h3>
                <p>Cung cấp không gian sống chất lượng, an toàn và giá hợp lý, đồng thời tạo dựng cộng đồng sinh viên và người đi làm gắn kết, hỗ trợ lẫn nhau.</p>
            </div>
            <div class="gt-vm-card">
                <div class="gt-vm-icon-wrap"><i class="fas fa-gem"></i></div>
                <h3>Giá trị cốt lõi</h3>
                <p>Uy tín – Chất lượng – Tận tâm. Chúng tôi cam kết đặt lợi ích của khách hàng lên hàng đầu, luôn lắng nghe và không ngừng cải thiện dịch vụ.</p>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="gt-why">
    <div class="gt-container" style="position:relative;z-index:2;">
        <div class="gt-section-header">
            <span class="gt-section-tag" style="background:rgba(16,185,129,0.2);color:#34d399;">Lý do chọn chúng tôi</span>
            <h2 class="gt-section-title">Vì sao chọn Mái Nhà Xanh?</h2>
            <p class="gt-section-sub" style="color:rgba(255,255,255,0.6);">6 lý do hàng ngàn khách hàng tin tưởng lựa chọn chúng tôi làm mái ấm của họ.</p>
        </div>
        <div class="gt-why-grid">
            <div class="gt-why-item">
                <div class="gt-why-item-icon"><i class="fas fa-tags"></i></div>
                <h4>Giá cả minh bạch</h4>
                <p>Không phát sinh chi phí ngoài hợp đồng, mọi khoản phí được thông báo rõ ràng từ đầu.</p>
            </div>
            <div class="gt-why-item">
                <div class="gt-why-item-icon"><i class="fas fa-bolt"></i></div>
                <h4>Thủ tục nhanh gọn</h4>
                <p>Ký hợp đồng và nhận phòng trong ngày, không mất quá nhiều thời gian làm thủ tục.</p>
            </div>
            <div class="gt-why-item">
                <div class="gt-why-item-icon"><i class="fas fa-headset"></i></div>
                <h4>Hỗ trợ 24/7</h4>
                <p>Đội ngũ luôn sẵn sàng giải đáp mọi thắc mắc và xử lý sự cố kịp thời.</p>
            </div>
            <div class="gt-why-item">
                <div class="gt-why-item-icon"><i class="fas fa-shield-alt"></i></div>
                <h4>An ninh tốt</h4>
                <p>Camera giám sát, bảo vệ túc trực đảm bảo an toàn tuyệt đối cho cư dân.</p>
            </div>
            <div class="gt-why-item">
                <div class="gt-why-item-icon"><i class="fas fa-wifi"></i></div>
                <h4>Tiện ích đầy đủ</h4>
                <p>Wifi tốc độ cao, điện nước, máy giặt, điều hòa – mọi thứ bạn cần đều có sẵn.</p>
            </div>
            <div class="gt-why-item">
                <div class="gt-why-item-icon"><i class="fas fa-map-marker-alt"></i></div>
                <h4>Vị trí thuận lợi</h4>
                <p>Gần trường học, bệnh viện, siêu thị – tiết kiệm thời gian và chi phí di chuyển.</p>
            </div>
        </div>
    </div>
</section>

<!-- Team -->
<section class="gt-team">
    <div class="gt-container">
        <div class="gt-section-header">
            <span class="gt-section-tag">Con người</span>
            <h2 class="gt-section-title">Đội ngũ của chúng tôi</h2>
            <p class="gt-section-sub">Những con người tâm huyết luôn làm việc hết mình vì trải nghiệm sống tốt nhất của bạn.</p>
        </div>
        <div class="gt-team-grid">
            <div class="gt-team-card">
                <div class="gt-team-avatar">
                    <img src="assets/images/team/thang.png" alt="Nguyễn Đại Thắng">
                </div>
                <h3>Nguyễn Đại Thắng</h3>
                <span class="gt-team-role">Chủ nhiệm</span>
                <div class="gt-team-social">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" title="Email"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
            <div class="gt-team-card">
                <div class="gt-team-avatar">
                    <img src="assets/images/team/nhu.png" alt="Cao Thị Quỳnh Như">
                </div>
                <h3>Cao Thị Quỳnh Như</h3>
                <span class="gt-team-role">Cộng sự</span>
                <div class="gt-team-social">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" title="Email"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
            <div class="gt-team-card">
                <div class="gt-team-avatar">
                    <img src="assets/images/team/bong.png" alt="Nguyễn Bá Bổng">
                </div>
                <h3>Nguyễn Bá Bổng</h3>
                <span class="gt-team-role">Cộng sự</span>
                <div class="gt-team-social">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" title="Email"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="gt-cta">
    <div class="gt-container">
        <div class="gt-cta-inner">
            <span class="gt-section-tag">Hành động ngay</span>
            <h2>Bạn muốn tìm hiểu thêm?</h2>
            <p>Liên hệ ngay với chúng tôi để được tư vấn chi tiết và xem phòng miễn phí. Chúng tôi luôn sẵn sàng giúp bạn tìm được mái ấm phù hợp.</p>
            <div class="gt-hero-actions">
                <a href="lien-he.php" class="gt-btn-primary"><i class="fas fa-phone"></i> Liên hệ ngay</a>
                <a href="phong-tro.php" class="gt-btn-outline" style="border-color:rgba(16,185,129,0.4); color:#059669;"><i class="fas fa-search"></i> Xem phòng trọ</a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
