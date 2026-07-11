<?php
require_once 'config/bootstrap.php';
require_once 'includes/media_helper.php';
require_once 'includes/room_status_helper.php';

$database = new Database();
$db = $database->getConnection();
ensureRoomStatusSchema($db);


$page_title = "Trang chủ";
include 'includes/header.php';
?>



<style>
.hero-section {
    background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(6, 78, 59, 0.72)), url('assets/images/home_banner.png') no-repeat center center / cover !important;
}
</style>

<!-- Hero Section -->
<section class="hero-section animate-fade-up">
    <div class="hero-bg-layer"></div>
    <div class="hero-glow-circle hero-glow-1"></div>
    <div class="hero-glow-circle hero-glow-2"></div>
    <div class="container">
        <h1 class="animate-fade-up">
            Chào mừng đến với Mái Nhà Xanh
        </h1>
        <p class="animate-fade-up" style="animation-delay: 0.2s;">
            Không gian sống tiện nghi, an ninh và thân thiện
        </p>
        <div class="hero-buttons animate-fade-up" style="animation-delay: 0.4s;">
            <a href="phong-tro.php" class="btn-hero-primary">
                Xem phòng ngay
            </a>
            <a href="lien-he.php" class="btn-hero-light">
                Liên hệ ngay
            </a>
        </div>
    </div>
</section>

<!-- Scrolling Image Gallery Section -->
<section class="gallery-section">
    <div class="gallery-container">
        <div class="gallery-track">
            <!-- Duplicate images to create seamless loop -->
            <img src="assets/images/rooms/phong-1.jpg" alt="Room 1">
            <img src="assets/images/rooms/phong-2.jpg" alt="Room 2">
            <img src="assets/images/rooms/phong-3.jpg" alt="Room 3">
            <img src="assets/images/rooms/phong-5.jpg" alt="Room 5">
            <!-- Duplicate set for seamless loop -->
            <img src="assets/images/rooms/phong-1.jpg" alt="Room 1">
            <img src="assets/images/rooms/phong-2.jpg" alt="Room 2">
            <img src="assets/images/rooms/phong-3.jpg" alt="Room 3">
            <img src="assets/images/rooms/phong-4.jpg" alt="Room 4">
            <img src="assets/images/rooms/phong-5.jpg" alt="Room 5">
            <img src="assets/images/rooms/phong-2.jpg" alt="Room 6">
        </div>
    </div>
</section>

<!-- House Info Section -->
<section class="about-house-section">
    <div class="container">
        <div class="section-header">
            <h2>Về Mái Nhà Xanh</h2>
            <p>
                Hệ thống phòng trọ hiện đại, đầy đủ tiện nghi, mang lại cảm giác ấm cúng như ở nhà.
            </p>
        </div>

        <div class="info-grid">
            <!-- Info Card 1 -->
            <div class="info-card">
                <div class="icon-wrapper">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>An ninh 24/7</h3>
                <p>Hệ thống camera giám sát, khóa vân tay và bảo vệ túc trực đảm bảo an toàn tuyệt đối cho cư dân.</p>
            </div>

            <!-- Info Card 2 -->
            <div class="info-card">
                <div class="icon-wrapper">
                     <i class="fas fa-couch"></i>
                </div>
                <h3>Tiện nghi đầy đủ</h3>
                <p>Phòng được trang bị đầy đủ giường, tủ, điều hòa, nóng lạnh. Chỉ cần xách vali vào ở ngay.</p>
            </div>

            <!-- Info Card 3 -->
            <div class="info-card">
                <div class="icon-wrapper">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3>Vị trí đắc địa</h3>
                <p>Gần các trường đại học, chợ, siêu thị và trạm xe buýt. Thuận tiện cho việc đi lại và sinh hoạt hàng ngày.</p>
            </div>
        </div>
    </div>
</section>


<!-- Featured Rooms Section -->
<section class="featured-rooms-section" style="padding: 80px 0; background-color: #f8f9fa;">
    <div class="container">
        <div class="section-header" style="text-align: center; margin-bottom: 50px;">
            <h2 style="font-size: 2.5rem; margin-bottom: 15px; color: var(--primary-color);">Phòng trọ nổi bật</h2>
            <p style="color: #666; max-width: 700px; margin: 0 auto; font-size: 1.1rem;">
                Những phòng trọ mới nhất và được quan tâm nhiều nhất tại Mái Nhà Xanh.
            </p>
        </div>

        <div class="rooms-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
            <?php
            // Fetch latest 3 rooms
            try {
                // Query 1: Lấy bài đăng cho thuê
                $room_query = "SELECT id, tieude as ten_phong, mota, hinhanh, gia, dientich, diachi, tiennghi, 
               ten_chunha, sdt_chunha, ngaydang, COALESCE(trangthai_phong, 'con_phong') as trangthai, 
               0 as lat, 0 as lng, 'dangbai' as nguon
               FROM dangbai_chothuetro WHERE trangthai = 'da_duyet' ORDER BY ngaydang DESC";
                $room_stmt = $db->prepare($room_query);
                $room_stmt->execute();
                $featured_rooms = $room_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Query 2: Lấy phòng trọ còn phòng
                $room_query2 = "SELECT id, ten_phong, hinhanh, gia, diachi, ngaydang, trangthai, 'phongtro' as nguon FROM phongtro WHERE trangthai='con_phong' ORDER BY ngaydang DESC";
                $room_stmt2 = $db->prepare($room_query2);
                $room_stmt2->execute();
                $featured_rooms2 = $room_stmt2->fetchAll(PDO::FETCH_ASSOC);

                // Gộp 2 danh sách và sắp xếp theo ngày đăng mới nhất
                $featured_rooms = array_merge($featured_rooms, $featured_rooms2);
                usort($featured_rooms, function($a, $b) {
                    return strtotime($b['ngaydang']) - strtotime($a['ngaydang']);
                });
                // Giới hạn hiển thị 6 phòng nổi bật
                $featured_rooms = array_slice($featured_rooms, 0, 9);

                if (count($featured_rooms) > 0) {
                    foreach ($featured_rooms as $room) {
                        $imagePath = buildMediaUrl($room['hinhanh'] ?? '');
                        $image_src = $imagePath !== '' ? htmlspecialchars($imagePath) : 'https://via.placeholder.com/400x300?text=Phong+Tro';
                        $badgeClass = '';
                        if (($room['trangthai'] ?? '') === 'da_coc') {
                            $status_label = 'Đã đặt cọc';
                            $status_color = '#f59e0b';
                            $badgeClass = 'badge-warning';
                        } elseif (($room['trangthai'] ?? '') === 'da_thue') {
                            $status_label = 'Đã thuê';
                            $status_color = '#dc3545';
                            $badgeClass = 'badge-danger';
                        } else {
                            $status_label = 'Còn phòng';
                            $status_color = '#28a745';
                            $badgeClass = 'badge-success';
                        }

                        $overlay_html = '';
                        $image_style = '';
                        if (($room['trangthai'] ?? '') === 'da_thue') {
                            $image_style = 'style="filter: grayscale(1);"';
                            $overlay_html = '
                            <div class="js-room-overlay" style="position:absolute; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:5;">
                                <span class="js-room-overlay-text" style="border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);">ĐÃ THUÊ</span>
                            </div>';
                        } elseif (($room['trangthai'] ?? '') === 'da_coc') {
                            $overlay_html = '
                            <div class="js-room-overlay" style="position:absolute; inset:0; background:rgba(245, 158, 11, 0.4); display:flex; align-items:center; justify-content:center; z-index:5;">
                                <span class="js-room-overlay-text" style="border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);">ĐÃ ĐẶT CỌC</span>
                            </div>';
                        }

                        echo '
                        <div class="room-card" style="animation-delay: ' . (rand(0, 40) / 10) . 's;">
                            <div class="room-image" ' . $image_style . '>
                                <img src="' . $image_src . '" alt="' . htmlspecialchars($room['ten_phong']) . '">
                                ' . $overlay_html . '
                                <div class="room-badge js-room-badge ' . $badgeClass . '" ' . ($room['trangthai'] == 'da_coc' ? 'style="background-color: #f59e0b;"' : '') . '>' . $status_label . '</div>
                            </div>
                            <div class="room-info">
                                <h3>' . htmlspecialchars($room['ten_phong']) . '</h3>
                                <p class="room-address"><i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars($room['diachi']) . '</p>
                                <div class="room-details">
                                    <span class="room-price">' . number_format($room['gia']) . ' VNĐ</span>
                                    <a href="phong-tro.php" class="btn-3d-glow">Xem chi tiết</a>

                                </div>
                            </div>
                        </div>

                        ';
                    }
                } else {
                    echo '<p style="text-align: center; width: 100%; color: #666;">Chưa có phòng trọ nào được đăng.</p>';
                }
            } catch (PDOException $e) {
                echo '<p style="text-align: center; width: 100%; color: red;">Lỗi tải dữ liệu: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="phong-tro.php" class="btn-3d-glow">
                Xem tất cả phòng trọ
            </a>

        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="section-header">
            <h2>Tiện ích nổi bật</h2>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-wifi"></i>
                </div>
                <h3>Wifi tốc độ cao</h3>
                <p>Internet cáp quang miễn phí phủ sóng toàn tòa nhà.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-parking"></i>
                </div>
                <h3>Bãi xe rộng rãi</h3>
                <p>Hầm để xe an toàn, có camera và bảo vệ.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-broom"></i>
                </div>
                <h3>Vệ sinh định kỳ</h3>
                <p>Dịch vụ vệ sinh hành lang và khu vực chung hàng tuần.</p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
