<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Mái Nhà Xanh</h3>
                <p>Chuyên cho thuê phòng trọ chất lượng, giá cả hợp lý tại TP.Vinh, Nghệ An</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Liên kết nhanh</h3>
                <ul>
                    <li><a href="index.php">Trang chủ</a></li>
                    <li><a href="phong-tro.php">Phòng trọ</a></li>
                    <li><a href="cong-dong.php">Cộng đồng</a></li>
                    <li><a href="gioi-thieu.php">Giới thiệu</a></li>
                    <li><a href="lien-he.php">Liên hệ</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Liên hệ</h3>
                <ul class="footer-contact">
                    <li><i class="fas fa-phone"></i> 0123 456 789</li>
                    <li><i class="fas fa-envelope"></i> contact@mainhaxanh.com</li>
                    <li><i class="fas fa-map-marker-alt"></i> <a href="https://maps.app.goo.gl/sSY9rKodfubWosh1A"
                            target="_blank" rel="noopener" class="footer-map-link">Trường Đại học Kinh tế Nghệ An, TP.
                            Vinh</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Giờ làm việc</h3>
                <ul>
                    <li>Thứ 2 - Thứ 6: 8:00 - 18:00</li>
                    <li>Thứ 7: 8:00 - 12:00</li>
                    <li>Chủ nhật: Nghỉ</li>
                </ul>
            </div>

            <div class="footer-map">

                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d51966.30698661654!2d105.6157091331863!3d18.6923405!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3139ce0e4264ec49%3A0x419cf16d3b928fa2!2zVHLGsOG7nW5nIMSQ4bqhaSBo4buNYyBLaW5oIHThur8gTmdo4buHIEFu!5e1!3m2!1svi!2s!4v1780655706906!5m2!1svi!2s"
                    width="100%" height="450" style="border:0; border-radius: 10px;" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2025 Mái Nhà Xanh. All rights reserved. | Designed by Đại Thắng</p>
        </div>
    </div>
</footer>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav">
    <a href="index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Trang chủ</span>
    </a>
    <a href="phong-tro.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'phong-tro.php' ? 'active' : '' ?>">
        <i class="fas fa-search"></i>
        <span>Phòng trọ</span>
    </a>
    <div class="nav-item nav-center" id="mobilePostBtn">
        <div class="nav-center-circle">
            <i class="fas fa-plus"></i>
        </div>
        <span>Đăng bài</span>
    </div>
    <a href="cong-dong.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'cong-dong.php' ? 'active' : '' ?>">
        <i class="fas fa-users"></i>
        <span>Cộng đồng</span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="bai-dang-cua-toi.php"
            class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'bai-dang-cua-toi.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Quản lý</span>
        </a>
    <?php else: ?>
        <a href="login.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : '' ?>">
            <i class="fas fa-sign-in-alt"></i>
            <span>Đăng nhập</span>
        </a>
    <?php endif; ?>
</nav>

<!-- Chatbot Component -->
<?php include 'includes/chatbot.php'; ?>

<!-- Scripts -->
<!-- Google Fonts for Chatbot Icons -->
<link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@48,400,1,0" />
<link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.17.2/dist/sweetalert2.all.min.js"></script>
<!-- Notification System -->
<script src="assets/js/notifications.js?v=<?= time() ?>"></script>
<?php if (isset($_SESSION['username'])): ?>
    <script>
        // Khởi động hệ thống thông báo realtime (poll 5 giây)
        document.addEventListener('DOMContentLoaded', function () {
            NotifSystem.init({
                apiUrl: 'api/get-notifications.php',
                role: 'user',
                pollInterval: 5000,
            });
            // Sự kiện click chuông thông báo đã được xử lý tập trung trong header.php
        });
        // Hàm markAllRead cho nút trong dropdown
        function markAllRead() { NotifSystem.markAllRead(); }
    </script>
<?php endif; ?>
<script>
    // Không ghi đè context nếu trang đã tự set (vd: phong-tro.php)
    window.CHATBOT_CONTEXT_ROOMS = (typeof window.CHATBOT_CONTEXT_ROOMS !== 'undefined')
        ? window.CHATBOT_CONTEXT_ROOMS
        : "";
</script>

<script src="assets/js/assistant.js"></script>

<script>
    // Word-by-Word Reveal Animation Script
    document.addEventListener('DOMContentLoaded', function () {
        const typingElements = document.querySelectorAll('.typing-effect');

        typingElements.forEach(element => {
            const text = element.innerText;
            element.innerHTML = ''; // Clear text

            // Split by spaces to get words
            const words = text.split(' ');

            words.forEach((word, index) => {
                const span = document.createElement('span');
                span.textContent = word; // Removed manual space, using CSS margin instead
                span.className = 'typing-word';

                // Staggered delay: 200ms per word
                span.style.animationDelay = `${index * 0.2}s`;

                element.appendChild(span);
            });
        });
    });

    // Footer Reveal Animation Script
    document.addEventListener('DOMContentLoaded', function () {
        const footer = document.querySelector('.footer');
        if (footer) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        footer.classList.add('animate-visible');
                        observer.unobserve(entry.target); // Run animation once
                    }
                });
            }, {
                threshold: 0.1 // Trigger when 10% valid
            });

            observer.observe(footer);
        }
    });
</script>

<!-- Floating Post Button -->
<div class="floating-post-btn" id="floatingPostBtn" title="Đăng bài">
    <i class="fas fa-plus"></i>
    <span class="floating-post-tooltip">Đăng bài</span>
</div>

<!-- Scroll To Top Button -->
<div class="scroll-to-top" id="scrollToTop" title="Cuộn lên đầu trang">
    <i class="fas fa-arrow-up"></i>
</div>

<script>
    // Scroll To Top Logic
    (function () {
        var scrollBtn = document.getElementById('scrollToTop');
        if (!scrollBtn) return;

        window.addEventListener('scroll', function () {
            if (window.pageYOffset > 300) {
                scrollBtn.classList.add('active');
            } else {
                scrollBtn.classList.remove('active');
            }
        });

        scrollBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    })();
</script>

<style>
    /* Mobile: when typing, hide floating buttons to prevent overlap with keyboard and input controls */
    body.ui-focus-hide #chatbot-toggler,
    body.ui-focus-hide #chatbot,
    body.ui-focus-hide .scroll-to-top {
        display: none !important;
    }

    /* Floating Post Button */
    .floating-post-btn {
        position: fixed !important;
        bottom: 25px;
        left: 25px;
        display: flex;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color, #10b981), var(--secondary-color, #3b82f6));
        color: #fff;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        user-select: none;
        z-index: 999;
    }

    .floating-post-btn:hover {
        transform: scale(1.12);
        box-shadow: 0 6px 28px rgba(118, 75, 162, 0.6);
    }

    .floating-post-btn:active {
        transform: scale(0.95);
    }

    /* Tooltip for non-fixed positioning */
    .floating-post-tooltip {
        position: absolute;
        left: calc(100% + 12px);
        top: 50%;
        transform: translateY(-50%);
        background: rgba(44, 62, 80, 0.92);
        color: #fff;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
        pointer-events: none;
        z-index: 10;
    }

    .floating-post-tooltip::before {
        content: '';
        position: absolute;
        right: 100%;
        top: 50%;
        transform: translateY(-50%);
        border: 6px solid transparent;
        border-right-color: rgba(44, 62, 80, 0.92);
    }

    .floating-post-btn:hover .floating-post-tooltip {
        opacity: 1;
        visibility: visible;
    }

    /* Responsive - Inline adjustments */
    @media (max-width: 768px) {
        .floating-post-btn {
            display: none !important;
            /* Hide on mobile because bottom nav has it */
        }
    }

    @media (max-width: 520px) {
        /* Previously styled floating button, now hidden */
    }
</style>

<script>
    // Floating & Mobile Post Button Logic
    (function () {
        var isLoggedIn = <?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>;
        var postHandler = function () {
            if (isLoggedIn) {
                window.location.href = 'dang-bai.php';
            } else {
                window.location.href = 'login.php';
            }
        };

        var btn = document.getElementById('floatingPostBtn');
        if (btn) btn.addEventListener('click', postHandler);

        var mBtn = document.getElementById('mobilePostBtn');
        if (mBtn) mBtn.addEventListener('click', postHandler);
    })();
</script>

<script>
    // Mobile UX: hide floating buttons when an input/textarea is focused (keyboard up)
    (function () {
        function isSmallScreen() {
            try { return window.matchMedia && window.matchMedia('(max-width: 768px)').matches; }
            catch (e) { return window.innerWidth <= 768; }
        }

        function isTypingTarget(el) {
            if (!el) return false;
            const tag = (el.tagName || '').toUpperCase();
            if (tag === 'INPUT' || tag === 'TEXTAREA') return true;
            return !!el.isContentEditable;
        }

        document.addEventListener('focusin', function (e) {
            if (!isSmallScreen()) return;
            if (isTypingTarget(e.target)) document.body.classList.add('ui-focus-hide');
        });

        document.addEventListener('focusout', function () {
            if (!isSmallScreen()) return;
            // Delay reappearing significantly to allow clicks on 'Send' button to process first
            setTimeout(function () {
                if (!isTypingTarget(document.activeElement)) {
                    document.body.classList.remove('ui-focus-hide');
                }
            }, 250); // Increased from 0 to 250ms
        });

        window.addEventListener('resize', function () {
            if (!isSmallScreen()) document.body.classList.remove('ui-focus-hide');
        });
    })();
</script>

<!-- Terms Modal -->
<div id="termsModal" class="modal-overlay"
    style="z-index: 10000; opacity: 0; pointer-events: none; transition: opacity 0.3s; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); position: fixed; top: 0; left: 0; right: 0; bottom: 0; padding: 15px;">
    <div class="modal-box"
        style="background: white; border-radius: 12px; max-width: 420px; width: 95%; padding: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); transform: translateY(-20px); transition: transform 0.3s; max-height: 70vh; display: flex; flex-direction: column; margin: auto;">
        <h3 style="margin-top:0; color: #1e293b; font-size: 1.1rem; margin-bottom: 12px; text-align: center;">Quy Định &
            Điều Khoản Sử Dụng</h3>
        <div
            style="font-size: 0.85rem; color: #475569; line-height: 1.5; overflow-y: auto; margin-bottom: 15px; flex: 1; padding: 0 5px;">
            <p style="margin-bottom: 10px;">Chào mừng bạn đến với Mái Nhà Xanh. Bằng việc truy cập sử dụng website, bạn
                cam kết tuân thủ các quy định cộng đồng sau:</p>
            <ul style="margin-left: 18px; margin-top: 8px; margin-bottom: 8px;">
                <li style="margin-bottom: 6px;"><strong>Không đăng nội dung sai sự thật:</strong> Mọi bài đăng thuê trọ
                    phải mô tả chính xác. Cố ý đăng sai sẽ bị khoá tài khoản.</li>
                <li style="margin-bottom: 6px;"><strong>Không Spam hoặc lừa đảo:</strong> Các bình luận, tin nhắn nhằm
                    mục đích lừa đảo, chửi bới, spam sẽ bị xử lý nghiêm.</li>
                <li style="margin-bottom: 6px;"><strong>Cơ chế báo cáo:</strong> Người dùng có thể báo cáo các bài đăng
                    hoặc tài khoản vi phạm. Ban quản trị sẽ kiểm tra và <strong>khoá vĩnh viễn (ban)</strong> các tài
                    khoản vi phạm nghiêm trọng.</li>
                <li style="margin-bottom: 6px;"><strong>Bảo mật:</strong> Các thông tin cá nhân của bạn sẽ được bảo mật
                    theo quy định của pháp luật.</li>
            </ul>
            <p style="margin-bottom: 0;">Môi trường văn minh, an toàn là mục tiêu hàng đầu của chúng tôi!</p>
        </div>
        <div style="text-align: center;">
            <button id="acceptTermsBtn" class="btn btn-primary"
                style="padding: 9px 28px; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">Tôi Đồng Ý</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!sessionStorage.getItem('termsAccepted_MaiNhaXanh')) {
            const modal = document.getElementById('termsModal');
            const box = modal.querySelector('.modal-box');

            modal.style.opacity = '1';
            modal.style.pointerEvents = 'auto';
            box.style.transform = 'translateY(0)';

            document.getElementById('acceptTermsBtn').addEventListener('click', function () {
                sessionStorage.setItem('termsAccepted_MaiNhaXanh', 'true');
                modal.style.opacity = '0';
                modal.style.pointerEvents = 'none';
                box.style.transform = 'translateY(-20px)';
                setTimeout(() => modal.remove(), 300);
            });
        }
    });
</script>
</body>

</html>