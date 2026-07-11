<?php
require_once 'config/bootstrap.php'; // Start session
// requireLogin('student'); // Removed as per user request
$page_title = "Liên hệ";
include 'includes/header.php';
?>

<style>
.page-header {
    background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(6, 78, 59, 0.72)), url('assets/images/contact_banner.png') no-repeat center center / cover !important;
}
</style>

<!-- Page Header -->
<section class="page-header">
    <div class="hero-bg-layer"></div>
    <div class="hero-glow-circle hero-glow-1"></div>
    <div class="hero-glow-circle hero-glow-2"></div>
    <div class="container">
        <h1 class="typing-effect">Liên hệ với chúng tôi</h1>
        <p class="animate-fade-up" style="animation-delay: 0.2s;">Chúng tôi luôn sẵn sàng lắng nghe và hỗ trợ bạn</p>
    </div>
</section>

<!-- Contact Section -->
<section class="contact-section">
    <div class="container">
        <div class="contact-layout">
            <!-- Contact Info — Premium 3D Glassmorphism -->
            <div class="contact-info" style="
                background: linear-gradient(135deg, rgba(6,78,59,0.92), rgba(16,185,129,0.88), rgba(59,130,246,0.85));
                border-radius: 28px;
                padding: 36px 32px;
                color: #fff;
                position: relative;
                overflow: hidden;
                box-shadow: 0 30px 60px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.1) inset;
                border: 1px solid rgba(255,255,255,0.18);
                animation: floatCard 8s ease-in-out infinite;
            ">
                <!-- Background shimmer -->
                <div style="position:absolute;inset:0;border-radius:28px;background:linear-gradient(135deg,rgba(255,255,255,0.08) 0%,transparent 60%);pointer-events:none;"></div>

                <!-- Header -->
                <div style="position:relative;z-index:1;margin-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px;">
                        <div style="width:50px;height:50px;background:rgba(255,255,255,0.18);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;box-shadow:0 4px 14px rgba(0,0,0,0.15);">
                            <i class="fas fa-address-card"></i>
                        </div>
                        <div>
                            <h2 style="margin:0 0 2px;color:#fff;font-size:1.5rem;font-weight:800;text-shadow:0 2px 8px rgba(0,0,0,0.2);">Thông tin liên hệ</h2>
                            <p style="margin:0;font-size:.78rem;color:rgba(255,255,255,0.7);">Luôn sẵn sàng hỗ trợ bạn</p>
                        </div>
                    </div>
                    <div style="height:1px;background:linear-gradient(to right,transparent,rgba(255,255,255,0.35),transparent);margin-bottom:20px;"></div>
                    <p style="color:rgba(255,255,255,0.82);font-size:.9rem;line-height:1.6;margin:0 0 24px;">Hãy liên hệ với chúng tôi qua các hình thức dưới đây hoặc gửi thông tin qua form bên cạnh.</p>
                </div>

                <!-- Info Items -->
                <div class="info-items" style="position:relative;z-index:1;">

                    <!-- Địa chỉ -->
                    <div class="contact-info-item" style="display:flex;align-items:flex-start;gap:16px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.18);margin-bottom:12px;backdrop-filter:blur(8px);transition:all .3s;cursor:default;"
                         onmouseover="this.style.background='rgba(255,255,255,0.18)';this.style.transform='translateX(6px)'"
                         onmouseout="this.style.background='rgba(255,255,255,0.1)';this.style.transform=''">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,#ef4444,#f97316);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;box-shadow:0 6px 16px rgba(239,68,68,0.4);flex-shrink:0;">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h4 style="margin:0 0 4px;color:#fff;font-size:.92rem;font-weight:700;">Địa chỉ</h4>
                            <p style="margin:0;color:rgba(255,255,255,0.82);font-size:.85rem;line-height:1.5;">
                                <a href="https://maps.app.goo.gl/sSY9rKodfubWosh1A" target="_blank" rel="noopener" style="color: inherit; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Trường Đại học Kinh tế Nghệ An</a><br>TP. Vinh, Nghệ An
                            </p>
                        </div>
                    </div>

                    <!-- Điện thoại -->
                    <div class="contact-info-item" style="display:flex;align-items:flex-start;gap:16px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.18);margin-bottom:12px;backdrop-filter:blur(8px);transition:all .3s;cursor:default;"
                         onmouseover="this.style.background='rgba(255,255,255,0.18)';this.style.transform='translateX(6px)'"
                         onmouseout="this.style.background='rgba(255,255,255,0.1)';this.style.transform=''">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,#10b981,#059669);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;box-shadow:0 6px 16px rgba(16,185,129,0.4);flex-shrink:0;">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <h4 style="margin:0 0 4px;color:#fff;font-size:.92rem;font-weight:700;">Điện thoại</h4>
                            <a href="tel:0123456789" style="display:block;color:rgba(255,255,255,0.85);font-size:.85rem;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='#6ee7b7'" onmouseout="this.style.color='rgba(255,255,255,0.85)'">0123 456 789</a>
                            <a href="tel:0987654321" style="display:block;color:rgba(255,255,255,0.85);font-size:.85rem;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='#6ee7b7'" onmouseout="this.style.color='rgba(255,255,255,0.85)'">0987 654 321</a>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="contact-info-item" style="display:flex;align-items:flex-start;gap:16px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.18);margin-bottom:12px;backdrop-filter:blur(8px);transition:all .3s;cursor:default;"
                         onmouseover="this.style.background='rgba(255,255,255,0.18)';this.style.transform='translateX(6px)'"
                         onmouseout="this.style.background='rgba(255,255,255,0.1)';this.style.transform=''">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,#3b82f6,#6366f1);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;box-shadow:0 6px 16px rgba(99,102,241,0.4);flex-shrink:0;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h4 style="margin:0 0 4px;color:#fff;font-size:.92rem;font-weight:700;">Email</h4>
                            <a href="mailto:contact@mainhaxanh.com" style="display:block;color:rgba(255,255,255,0.85);font-size:.85rem;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='rgba(255,255,255,0.85)'">contact@mainhaxanh.com</a>
                            <a href="mailto:support@mainhaxanh.com" style="display:block;color:rgba(255,255,255,0.85);font-size:.85rem;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='rgba(255,255,255,0.85)'">support@mainhaxanh.com</a>
                        </div>
                    </div>

                    <!-- Giờ làm việc -->
                    <div class="contact-info-item" style="display:flex;align-items:flex-start;gap:16px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.18);margin-bottom:20px;backdrop-filter:blur(8px);transition:all .3s;cursor:default;"
                         onmouseover="this.style.background='rgba(255,255,255,0.18)';this.style.transform='translateX(6px)'"
                         onmouseout="this.style.background='rgba(255,255,255,0.1)';this.style.transform=''">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,#f59e0b,#f97316);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;box-shadow:0 6px 16px rgba(245,158,11,0.4);flex-shrink:0;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h4 style="margin:0 0 4px;color:#fff;font-size:.92rem;font-weight:700;">Giờ làm việc</h4>
                            <p style="margin:0 0 2px;color:rgba(255,255,255,0.85);font-size:.85rem;">Thứ 2 – Thứ 6: <strong>8:00 – 18:00</strong></p>
                            <p style="margin:0 0 2px;color:rgba(255,255,255,0.85);font-size:.85rem;">Thứ 7: <strong>8:00 – 12:00</strong></p>
                            <p style="margin:0;color:rgba(255,255,255,0.6);font-size:.82rem;">Chủ nhật: Nghỉ</p>
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div style="position:relative;z-index:1;">
                    <div style="height:1px;background:linear-gradient(to right,transparent,rgba(255,255,255,0.3),transparent);margin-bottom:16px;"></div>
                    <h4 style="color:rgba(255,255,255,0.85);font-size:.82rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;margin:0 0 12px;">Theo dõi chúng tôi</h4>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="#" style="display:flex;align-items:center;gap:8px;padding:8px 16px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:30px;color:#fff;text-decoration:none;font-size:.82rem;font-weight:600;backdrop-filter:blur(5px);transition:all .3s;"
                           onmouseover="this.style.background='#1877f2';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(24,119,242,0.4)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='';this.style.boxShadow=''">
                           <i class="fab fa-facebook"></i> Facebook
                        </a>
                        <a href="#" style="display:flex;align-items:center;gap:8px;padding:8px 16px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:30px;color:#fff;text-decoration:none;font-size:.82rem;font-weight:600;backdrop-filter:blur(5px);transition:all .3s;"
                           onmouseover="this.style.background='#ff0000';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(255,0,0,0.4)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='';this.style.boxShadow=''">
                           <i class="fab fa-youtube"></i> YouTube
                        </a>
                        <a href="#" style="display:flex;align-items:center;gap:8px;padding:8px 16px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:30px;color:#fff;text-decoration:none;font-size:.82rem;font-weight:600;backdrop-filter:blur(5px);transition:all .3s;"
                           onmouseover="this.style.background='linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)';this.style.transform='translateY(-2px)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform=''">
                           <i class="fab fa-instagram"></i> Instagram
                        </a>
                        <a href="#" style="display:flex;align-items:center;gap:8px;padding:8px 16px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:30px;color:#fff;text-decoration:none;font-size:.82rem;font-weight:600;backdrop-filter:blur(5px);transition:all .3s;"
                           onmouseover="this.style.background='#000';this.style.transform='translateY(-2px)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform=''">
                           <i class="fab fa-tiktok"></i> TikTok
                        </a>
                    </div>
                </div>
            </div>


            <!-- Contact Form -->
            <div class="contact-form-wrapper" style="
                background: linear-gradient(135deg, rgba(15,23,42,0.92), rgba(6,78,59,0.88), rgba(14,165,233,0.85));
                border-radius: 28px;
                padding: 36px 32px;
                color: #fff;
                position: relative;
                overflow: hidden;
                box-shadow: 0 30px 60px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.1) inset;
                border: 1px solid rgba(255,255,255,0.18);
                animation: floatCard 8s ease-in-out infinite;
                animation-delay: -2s;
            ">
                <!-- Background shimmer -->
                <div style="position:absolute;inset:0;border-radius:28px;background:linear-gradient(135deg,rgba(255,255,255,0.06) 0%,transparent 60%);pointer-events:none;"></div>

                <h2 style="color: #fff; margin-bottom: 24px; font-weight: 800; text-shadow: 0 2px 10px rgba(0,0,0,0.2);">Gửi tin nhắn cho chúng tôi</h2>
                <style>
                    .contact-form-wrapper .form-group label { color: rgba(255,255,255,0.9) !important; font-weight: 600; }
                    .contact-form-wrapper .form-control { 
                        background: rgba(255,255,255,0.95); 
                        border: 1px solid rgba(255,255,255,0.2);
                        border-radius: 12px;
                        padding: 12px 16px;
                    }
                    .contact-form-wrapper .form-control:focus {
                        background: #fff;
                        box-shadow: 0 0 0 4px rgba(16,185,129,0.2);
                    }
                </style>
                <form id="contactForm" class="contact-form">
                    <input type="hidden" id="contactSessionId" name="session_id" value="">
                    <div class="form-group">
                        <label for="hoten">Họ và tên <span class="required">*</span></label>
                        <input type="text" id="hoten" name="hoten" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="sodienthoai">Số điện thoại <span class="required">*</span></label>
                            <input type="tel" id="sodienthoai" name="sodienthoai" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="tieude">Tiêu đề <span class="required">*</span></label>
                        <input type="text" id="tieude" name="tieude" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="noidung">Nội dung <span class="required">*</span></label>
                        <textarea id="noidung" name="noidung" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn-3d-glow btn-block" style="padding: 15px; font-size: 1.1rem;">
                        <i class="fas fa-paper-plane"></i> Gửi tin nhắn
                    </button>

                </form>
            </div>
        </div>
    </div>
</section>

<!-- Map Section -->
<section class="map-section">
    <div class="container">
        <div class="map-container">
            <h2><i class="fas fa-location-arrow"></i> Vị Trí Của Chúng Tôi</h2>
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d51966.30698661654!2d105.6157091331863!3d18.6923405!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3139ce0e4264ec49%3A0x419cf16d3b928fa2!2zVHLGsOG7nW5nIMSQ4bqhaSBo4buNYyBLaW5oIHThur8gTmdo4buHIEFu!5e1!3m2!1svi!2s!4v1780655706906!5m2!1svi!2s"
                width="100%" 
                height="600" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>

<!-- Google Maps Script Removed -->
<script>
    // Map script removed
</script>

<script>
    // Gán session_id từ chatbot
    const sid = sessionStorage.getItem('chatbot_session_id') || '';
    document.getElementById('contactSessionId').value = sid;

    // Xử lý submit form
    document.getElementById('contactForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('api/contact.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Thành công!',
                    text: 'Tin nhắn của bạn đã được gửi. Chúng tôi sẽ liên hệ lại sớm!',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1E90FF'
                });
                this.reset();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi!',
                    text: result.message || 'Có lỗi xảy ra. Vui lòng thử lại!',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1E90FF'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi!',
                text: 'Không thể kết nối đến server. Vui lòng thử lại!',
                confirmButtonText: 'OK',
                confirmButtonColor: '#1E90FF'
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
