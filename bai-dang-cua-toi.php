<?php
require_once 'config/bootstrap.php';
require_once 'includes/media_helper.php';
require_once 'includes/room_status_helper.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$page_title = "Bài đăng của tôi";
include 'includes/header.php';

$database = new Database();
$db = $database->getConnection();
ensureRoomStatusSchema($db);

$username = $_SESSION['username'];

// Lấy bài đăng từ dangbai_chothuetro
$myPosts = [];
try {
    $s = $db->prepare("SELECT id, tieude as ten_phong, gia, dientich, diachi, hinhanh,
                              trangthai as trangthai_duyet,
                              COALESCE(trangthai_phong, 'con_phong') as trangthai_phong,
                              ngaydang, 'dangbai' as nguon
                       FROM dangbai_chothuetro WHERE nguoidang = :u ORDER BY ngaydang DESC");
    $s->execute([':u' => $username]);
    $myPosts = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Lấy bài từ phongtro (nếu user_id liên kết)
try {
    $su = $db->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $su->execute([':u' => $username]);
    $user = $su->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $s2 = $db->prepare("SELECT id, ten_phong, gia, dientich, diachi, hinhanh,
                                   NULL as trangthai_duyet,
                                   trangthai as trangthai_phong,
                                   ngaydang, 'phongtro' as nguon
                            FROM phongtro WHERE user_id = :uid ORDER BY ngaydang DESC");
        $s2->execute([':uid' => $user['id']]);
        $phongtro_posts = $s2->fetchAll(PDO::FETCH_ASSOC);
        $myPosts = array_merge($myPosts, $phongtro_posts);
    }
} catch (Exception $e) {}

// Lấy yêu cầu đặt phòng gửi đến bài của mình
$bookings = [];
try {
    $db->exec("CREATE TABLE IF NOT EXISTS dat_phong (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL, nguon VARCHAR(20) DEFAULT 'dangbai',
        ten_phong VARCHAR(200) DEFAULT '', ho_ten VARCHAR(100) NOT NULL,
        so_dien_thoai VARCHAR(20) NOT NULL, ngay_muon_thue DATE NULL,
        ghi_chu TEXT, nguoidang VARCHAR(100) DEFAULT '',
        trang_thai ENUM('cho_xu_ly','da_lien_he','tu_choi','da_thue') DEFAULT 'cho_xu_ly',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_nguoidang (nguoidang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $sb = $db->prepare("SELECT * FROM dat_phong WHERE nguoidang = :u ORDER BY created_at DESC LIMIT 50");
    $sb->execute([':u' => $username]);
    $bookings = $sb->fetchAll(PDO::FETCH_ASSOC);

    // Đếm số yêu cầu chưa xem
    $unreadCount = 0;
    foreach($bookings as $b) { if(!($b['is_read'] ?? 0)) $unreadCount++; }
} catch (Exception $e) {}

$trangthai_label = [
    'da_duyet'  => ['Đã duyệt',   '#10b981'],
    'cho_duyet' => ['Chờ duyệt',  '#f59e0b'],
    'bi_tu_choi'=> ['Bị từ chối', '#ef4444'],
    'con_phong' => ['Còn phòng',  '#10b981'],
    'da_coc'    => ['Đã đặt cọc', '#f59e0b'],
    'da_thue'   => ['Đã thuê',    '#ef4444'],
    'tu_choi'   => ['Bị từ chối', '#ef4444'],
];
?>

<section class="page-header">
    <div class="hero-bg-layer"></div>
    <div class="hero-glow-circle hero-glow-1"></div>
    <div class="hero-glow-circle hero-glow-2"></div>
    <div class="container">
        <h1 class="typing-effect">Bài đăng của tôi</h1>
        <p>Quản lý tất cả bài đăng cho thuê phòng trọ của bạn</p>
    </div>
</section>

<section style="padding:40px 0 80px;">
    <div class="container">

        <!-- Tab điều hướng -->
        <div style="display:flex;gap:12px;margin-bottom:32px;flex-wrap:wrap;">
            <button class="tab-btn active" onclick="switchTab('posts')" id="tab-posts">
                <i class="fas fa-list"></i> Bài đăng (<span id="posts-count"><?= count($myPosts) ?></span>)
            </button>
            <button class="tab-btn" onclick="switchTab('bookings')" id="tab-bookings">
                <i class="fas fa-calendar-check"></i> Yêu cầu đặt phòng
                <?php if($unreadCount > 0): ?>
                    <span id="booking-badge" style="background:#ef4444;color:#fff;border-radius:50%;padding:2px 7px;font-size:.75rem;margin-left:4px;"><?= $unreadCount ?></span>
                <?php endif; ?>
            </button>
            <a href="dang-bai.php" class="tab-btn" style="background:linear-gradient(135deg,#10b981,#3b82f6);color:#fff;text-decoration:none;">
                <i class="fas fa-plus"></i> Đăng bài mới
            </a>
        </div>

        <!-- TAB: BÀI ĐĂNG -->
        <div id="panel-posts">
            <!-- Bulk Action Bar -->
            <div class="bulk-action-bar" id="postsBulkBar" style="display:none;margin-bottom:20px;background:#fff;padding:12px 20px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.05);display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;position:sticky;top:80px;z-index:100;">
                <label class="bulk-action-label" style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:600;color:#374151;">
                    <input type="checkbox" id="postMasterCheck" onchange="togglePostSelectAll(this)" style="width:18px;height:18px;accent-color:#10b981;">
                    Chọn tất cả
                </label>
                <div style="display:flex;gap:12px;align-items:center;">
                    <span style="font-size:.9rem;color:#6b7280;font-weight:500;">Đã chọn: <span id="postSelectedCount" style="color:#10b981;font-weight:700;">0</span></span>
                    <button class="btn btn-danger btn-sm" onclick="bulkDeletePosts()" style="padding:8px 16px;border-radius:8px;font-size:.85rem;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-trash-alt"></i> Xoá bài đã chọn
                    </button>
                </div>
            </div>

            <div id="postsEmptyState" style="text-align:center;padding:80px 20px;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.06);<?= empty($myPosts) ? '' : 'display:none;' ?>">
                <i class="fas fa-inbox" style="font-size:4rem;color:#d1d5db;"></i>
                <h3 style="color:#9ca3af;margin:20px 0 10px;">Bạn chưa có bài đăng nào</h3>
                <a href="dang-bai.php" class="btn btn-primary" style="margin-top:10px;">Đăng bài ngay</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;<?= empty($myPosts) ? 'display:none;' : '' ?>" id="myPostsGrid">
                <?php foreach ($myPosts as $p):
                    $imgPath = buildMediaUrl($p['hinhanh'] ?? '');
                    $img   = $imgPath !== '' ? htmlspecialchars($imgPath) : 'https://via.placeholder.com/400x220?text=No+Image';
                    $approvalStatus = $p['trangthai_duyet'] ?? '';
                    $roomStatus = normalizeRoomStatusValue($p['trangthai_phong'] ?? 'con_phong');
                    $canToggleStatus = ($p['nguon'] !== 'dangbai') || ($approvalStatus === 'da_duyet');
                    $ts = ($p['nguon'] === 'dangbai' && $approvalStatus !== 'da_duyet')
                        ? ($approvalStatus ?: 'cho_duyet')
                        : $roomStatus;
                    $label = $trangthai_label[$ts] ?? [$ts, '#6b7280'];
                    $pJson = htmlspecialchars(json_encode([
                        'id' => $p['id'], 'nguon' => $p['nguon'],
                        'ten_phong' => $p['ten_phong'], 'gia' => $p['gia'],
                        'dientich' => $p['dientich'], 'diachi' => $p['diachi'],
                    ]), ENT_QUOTES);
                ?>
                <div class="my-post-card"
                     id="card-<?= $p['nguon'] ?>-<?= $p['id'] ?>"
                     data-id="<?= $p['id'] ?>"
                     data-nguon="<?= htmlspecialchars($p['nguon']) ?>">
                    <div class="my-post-img js-post-img-wrap" style="<?= ($ts == 'da_thue') ? 'filter: grayscale(1);' : '' ?>">
                        <input type="checkbox" class="post-check-item" 
                               value="<?= $p['id'] ?>" 
                               data-nguon="<?= htmlspecialchars($p['nguon']) ?>"
                               onchange="onPostItemCheckChange()"
                               onclick="event.stopPropagation()"
                               style="position:absolute;top:12px;left:12px;z-index:10;width:20px;height:20px;cursor:pointer;accent-color:#10b981;">
                        <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['ten_phong']) ?>">
                        
                        <?php if($ts == 'da_thue'): ?>
                            <div class="js-post-overlay" style="position:absolute; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:5;">
                                <span class="js-post-overlay-text" style="border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);">ĐÃ THUÊ</span>
                            </div>
                        <?php elseif($ts == 'da_coc'): ?>
                            <div class="js-post-overlay" style="position:absolute; inset:0; background:rgba(245, 158, 11, 0.4); display:flex; align-items:center; justify-content:center; z-index:5;">
                                <span class="js-post-overlay-text" style="border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);">ĐÃ ĐẶT CỌC</span>
                            </div>
                        <?php endif; ?>

                        <span class="my-post-badge js-post-badge"
                              data-status="<?= htmlspecialchars($ts) ?>"
                              style="background:<?= $label[1] ?>; z-index:6;"><?= $label[0] ?></span>
                    </div>
                    <div class="my-post-body">
                        <h3 class="js-post-title"><?= htmlspecialchars($p['ten_phong']) ?></h3>
                        <p class="js-post-address"><i class="fas fa-map-marker-alt" style="color:#ef4444"></i> <span class="js-post-address-text"><?= htmlspecialchars($p['diachi']) ?></span></p>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                            <span class="js-post-price" style="font-weight:700;color:#10b981;"><?= number_format($p['gia']) ?>đ/tháng</span>
                            <span style="color:#9ca3af;font-size:.82rem;"><?= date('d/m/Y', strtotime($p['ngaydang'])) ?></span>
                        </div>
                    </div>
                    <div class="my-post-actions">
                        <?php if ($canToggleStatus): ?>
                        <div style="display:flex; gap:10px; width:100%;">
                            <button class="btn-toggle-status js-post-toggle" data-action="da_thue" onclick="toggleRoomStatus(<?= $p['id'] ?>,'<?= $p['nguon'] ?>', 'da_thue')" 
                                    style="background:#059669;color:#fff;border-color:#059669;flex:1;padding:8px 4px;font-size:0.8rem;" title="Đánh dấu đã thuê">
                                <i class="fas fa-check-circle"></i> Đã thuê
                            </button>
                            <button class="btn-toggle-status js-post-toggle" data-action="da_coc" onclick="toggleRoomStatus(<?= $p['id'] ?>,'<?= $p['nguon'] ?>', 'da_coc')" 
                                    style="background:#d97706;color:#fff;border-color:#d97706;flex:1;padding:8px 4px;font-size:0.8rem;" title="Đánh dấu đã đặt cọc">
                                <i class="fas fa-hand-holding-usd"></i> Đã cọc
                            </button>
                            <button class="btn-toggle-status js-post-toggle" data-action="con_phong" onclick="toggleRoomStatus(<?= $p['id'] ?>,'<?= $p['nguon'] ?>', 'con_phong')"
                                    style="background:#6b7280;color:#fff;border-color:#6b7280;flex:1;padding:8px 4px;font-size:0.8rem;" title="Đánh dấu còn trống">
                                <i class="fas fa-undo"></i> Còn trống
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="js-post-pending-note" style="grid-column:1/-1;flex:1 1 100%;padding:10px 12px;border-radius:10px;background:#fffbeb;color:#92400e;font-size:.84rem;font-weight:600;">
                            Chỉ đổi được trạng thái thuê sau khi bài đã được duyệt.
                        </div>
                        <?php endif; ?>
                        
                        <div style="display:flex; gap:10px; width:100%; margin-top:4px;">
                            <button class="btn-edit-post" onclick="openEditModal(<?= $pJson ?>)">
                                <i class="fas fa-edit"></i> Sửa
                            </button>
                            <button class="btn-delete-post" onclick="deletePost(<?= $p['id'] ?>,'<?= $p['nguon'] ?>','card-<?= $p['nguon'] ?>-<?= $p['id'] ?>')">
                                <i class="fas fa-trash-alt"></i> Xóa
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB: YÊU CẦU ĐẶT PHÒNG -->
        <div id="panel-bookings" style="display:none;">
            <!-- Bulk Action Bar for Bookings -->
            <div class="bulk-action-bar" id="bookingsBulkBar" style="display:none;margin-bottom:20px;background:#fff;padding:12px 20px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.05);display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;position:sticky;top:80px;z-index:100;">
                <label class="bulk-action-label" style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:600;color:#374151;">
                    <input type="checkbox" id="bookingMasterCheck" onchange="toggleBookingSelectAll(this)" style="width:18px;height:18px;accent-color:#10b981;">
                    Chọn tất cả
                </label>
                <div style="display:flex;gap:12px;align-items:center;">
                    <span style="font-size:.9rem;color:#6b7280;font-weight:500;">Đã chọn: <span id="bookingSelectedCount" style="color:#10b981;font-weight:700;">0</span></span>
                    <button class="btn btn-danger btn-sm" onclick="bulkDeleteBookings()" style="padding:8px 16px;border-radius:8px;font-size:.85rem;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-trash-alt"></i> Xoá yêu cầu đã chọn
                    </button>
                </div>
            </div>

            <div id="bookingsEmptyState" style="text-align:center;padding:80px 20px;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.06);<?= empty($bookings) ? '' : 'display:none;' ?>">
                <i class="fas fa-calendar-times" style="font-size:4rem;color:#d1d5db;"></i>
                <h3 style="color:#9ca3af;margin:20px 0 8px;">Chưa có yêu cầu đặt phòng nào</h3>
                <p style="color:#9ca3af;">Khi có người đặt phòng, thông tin sẽ hiển thị tại đây.</p>
            </div>
            <div id="bookingsList" style="display:flex;flex-direction:column;gap:16px;<?= empty($bookings) ? 'display:none;' : '' ?>">
                <?php foreach ($bookings as $b):
                    $bk_label = ['cho_xu_ly' => ['Chờ xử lý','#f59e0b'], 'da_lien_he' => ['Đã liên hệ','#10b981'], 'tu_choi' => ['Từ chối','#ef4444'], 'da_thue' => ['Đã thuê','#ef4444']];
                    $bk = $bk_label[$b['trang_thai']] ?? ['Chờ xử lý','#f59e0b'];
                ?>
                <div class="booking-card"
                     id="booking-card-<?= $b['id'] ?>"
                     data-booking-id="<?= $b['id'] ?>"
                     style="background:#fff;border-radius:12px;padding:20px 24px;box-shadow:0 2px 12px rgba(0,0,0,.06);display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;position:relative;">
                    
                    <div style="padding-top:4px;">
                        <input type="checkbox" class="booking-check-item" 
                               value="<?= $b['id'] ?>" 
                               onchange="onBookingItemCheckChange()"
                               style="width:20px;height:20px;cursor:pointer;accent-color:#10b981;">
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <div style="font-weight:700;font-size:1rem;color:#1e293b;margin-bottom:6px;">
                            <i class="fas fa-home" style="color:#10b981;margin-right:6px;"></i>
                            <?= htmlspecialchars($b['ten_phong'] ?: 'Phòng #'.$b['post_id']) ?>
                        </div>
                        <div style="color:#475569;font-size:.9rem;">
                            <i class="fas fa-user" style="color:#3b82f6;margin-right:4px;"></i> <?= htmlspecialchars($b['ho_ten']) ?>
                            &nbsp;&nbsp;
                            <i class="fas fa-phone" style="color:#10b981;margin-right:4px;"></i>
                            <a href="tel:<?= htmlspecialchars($b['so_dien_thoai']) ?>" style="color:#10b981;font-weight:600;"><?= htmlspecialchars($b['so_dien_thoai']) ?></a>
                        </div>
                        <?php if($b['ngay_muon_thue']): ?>
                        <div style="color:#64748b;font-size:.85rem;margin-top:4px;">
                            <i class="fas fa-calendar" style="color:#f59e0b;margin-right:4px;"></i>
                            Ngày muốn thuê: <strong><?= date('d/m/Y', strtotime($b['ngay_muon_thue'])) ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($b['ghi_chu'])): ?>
                        <div style="margin-top:6px;color:#64748b;font-size:.85rem;background:#f8fafc;padding:8px 12px;border-radius:8px;border-left:3px solid #3b82f6;">
                            <?= htmlspecialchars($b['ghi_chu']) ?>
                        </div>
                        <?php endif; ?>
                        <div style="color:#94a3b8;font-size:.78rem;margin-top:6px;">
                            <i class="fas fa-clock"></i> <?= date('H:i d/m/Y', strtotime($b['created_at'])) ?>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                        <span class="js-booking-status"
                              style="background:<?= $bk[1] ?>;color:#fff;padding:4px 14px;border-radius:20px;font-size:.8rem;font-weight:600;">
                            <i class="fas fa-tag" style="margin-right:2px;"></i> <?= $bk[0] ?>
                        </span>
                        
                        <div class="js-booking-actions" style="display:flex;gap:6px;">
                            <a href="tel:<?= htmlspecialchars($b['so_dien_thoai']) ?>"
                               style="background:#10b981;color:#fff;padding:7px 12px;border-radius:8px;text-decoration:none;font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:4px;"
                               title="Gọi ngay cho người thuê">
                                <i class="fas fa-phone"></i> Gọi
                            </a>

                            <button class="js-booking-action" data-action="da_thue" onclick="updateBookingStatus(<?= $b['id'] ?>, <?= $b['post_id'] ?>, '<?= $b['nguon'] ?>', 'da_thue')"
                                    style="background:#059669;color:#fff;padding:7px 12px;border:none;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;"
                                    title="Đánh dấu đã thuê thành công">
                                <i class="fas fa-check-circle"></i> Đã thuê
                            </button>

                            <button class="js-booking-action" data-action="con_phong" onclick="updateBookingStatus(<?= $b['id'] ?>, <?= $b['post_id'] ?>, '<?= $b['nguon'] ?>', 'con_phong')"
                                    style="background:#6b7280;color:#fff;padding:7px 12px;border:none;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;"
                                    title="Đánh dấu vẫn chưa thuê (Còn phòng)">
                                <i class="fas fa-undo"></i> Chưa thuê
                            </button>

                            <button class="js-booking-delete" onclick="deleteBooking(<?= $b['id'] ?>)"
                                    style="background:#fef2f2;color:#ef4444;padding:7px 12px;border:1px solid #fecaca;border-radius:8px;font-size:.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                                    title="Xoá yêu cầu này">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Modal Sửa Bài -->
<div id="editPostModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:95%;max-width:640px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="background:linear-gradient(135deg,#10b981,#3b82f6);padding:20px 25px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="color:#fff;margin:0;"><i class="fas fa-edit"></i> Chỉnh sửa bài đăng</h3>
            <button onclick="closeEditModal()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>
        <form id="editPostForm" style="padding:24px;">
            <input type="hidden" id="edit_id" name="id">
            <input type="hidden" id="edit_nguon" name="nguon">

            <div style="margin-bottom:16px;">
                <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;">Tiêu đề bài đăng</label>
                <input type="text" id="edit_tieude" name="tieude" class="form-control edit-input" required>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                    <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;">Giá thuê (VNĐ/tháng)</label>
                    <input type="number" id="edit_gia" name="gia" class="form-control edit-input" min="0" required>
                </div>
                <div>
                    <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;">Diện tích (m²)</label>
                    <input type="number" id="edit_dientich" name="dientich" class="form-control edit-input" min="0" step="0.1" required>
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;">Địa chỉ</label>
                <input type="text" id="edit_diachi" name="diachi" class="form-control edit-input" required>
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;">Mô tả</label>
                <textarea id="edit_mota" name="mota" rows="4" class="form-control edit-input" style="resize:vertical;"></textarea>
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;">Tiện nghi</label>
                <input type="text" id="edit_tiennghi" name="tiennghi" class="form-control edit-input" placeholder="VD: Wifi, Máy lạnh, Nóng lạnh">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
                <div>
                    <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;">Tên chủ nhà</label>
                    <input type="text" id="edit_ten_chunha" name="ten_chunha" class="form-control edit-input">
                </div>
                <div>
                    <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;">Số điện thoại</label>
                    <input type="text" id="edit_sdt_chunha" name="sdt_chunha" class="form-control edit-input">
                </div>
            </div>
            <button type="submit" style="width:100%;padding:13px;background:linear-gradient(135deg,#10b981,#3b82f6);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;">
                <i class="fas fa-save"></i> Lưu thay đổi
            </button>
        </form>
    </div>
</div>

<style>
.tab-btn {
    padding: 10px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    font-weight: 600;
    font-size: .9rem;
    cursor: pointer;
    transition: .2s;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #374151;
}
.tab-btn.active {
    border-color: #10b981;
    background: #f0fdf4;
    color: #10b981;
}
.tab-btn:hover:not(.active) { border-color: #d1d5db; background: #f9fafb; }

.my-post-card {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,.07);
    transition: transform .2s, box-shadow .2s;
}
.my-post-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,.12); }

.my-post-img { position: relative; height: 190px; overflow: hidden; }
.my-post-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.my-post-card:hover .my-post-img img { transform: scale(1.05); }
.my-post-badge { position:absolute;top:12px;right:12px;padding:4px 12px;border-radius:20px;color:#fff;font-size:.8rem;font-weight:600; }

.my-post-body { padding: 16px; }
.my-post-body h3 { font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.my-post-body p { font-size: .85rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0; }

.my-post-actions { display: flex; flex-wrap: wrap; gap: 10px; padding: 12px 16px; border-top: 1px solid #f1f5f9; }
.btn-edit-post {
    flex: 1; padding: 9px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #fff;
    border: none; border-radius: 10px; font-weight: 700;
    cursor: pointer; font-size: .88rem; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex; align-items: center; justify-content: center; gap: 6px;
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4), 0 0 0 1px rgba(255,255,255,0.1) inset;
    position: relative; overflow: hidden;
}
.btn-edit-post:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 12px 30px rgba(59, 130, 246, 0.6), 0 0 15px rgba(255,255,255,0.4) inset; filter: brightness(1.1); }
.btn-edit-post::after { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent); transform: rotate(45deg); transition: 0.7s; }
.btn-edit-post:hover::after { left: 100%; }

.btn-delete-post {
    flex: 1; padding: 9px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #fff;
    border: none; border-radius: 10px; font-weight: 700;
    cursor: pointer; font-size: .88rem; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex; align-items: center; justify-content: center; gap: 6px;
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4), 0 0 0 1px rgba(255,255,255,0.1) inset;
    position: relative; overflow: hidden;
}
.btn-delete-post:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 12px 30px rgba(239, 68, 68, 0.6), 0 0 15px rgba(255,255,255,0.4) inset; filter: brightness(1.1); }
.btn-delete-post::after { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent); transform: rotate(45deg); transition: 0.7s; }
.btn-delete-post:hover::after { left: 100%; }

.btn-toggle-status {
    flex: 1.2; padding: 9px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff;
    border: none; border-radius: 10px; font-weight: 700;
    cursor: pointer; font-size: .88rem; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex; align-items: center; justify-content: center; gap: 6px;
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4), 0 0 0 1px rgba(255,255,255,0.1) inset;
    position: relative; overflow: hidden;
}
.btn-toggle-status:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 12px 30px rgba(16, 185, 129, 0.6), 0 0 15px rgba(255,255,255,0.4) inset; filter: brightness(1.1); }
.btn-toggle-status::after { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent); transform: rotate(45deg); transition: 0.7s; }
.btn-toggle-status:hover::after { left: 100%; }

.edit-input {
    width: 100%; padding: 10px 14px; border: 2px solid #e5e7eb;
    border-radius: 8px; font-size: .95rem; font-family: inherit;
    transition: border-color .25s;
}
.edit-input:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.12); }

/* ── 3D Glow: Tab Buttons ─────────────────────────────────────── */
.tab-btn {
    position: relative; overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08), 0 0 0 1px rgba(255,255,255,0.5) inset;
    transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
}
.tab-btn:hover {
    transform: translateY(-3px) scale(1.04);
    box-shadow: 0 10px 25px rgba(0,0,0,0.12), 0 0 12px rgba(255,255,255,0.3) inset;
}
.tab-btn.active {
    box-shadow: 0 6px 20px rgba(16,185,129,0.35), 0 0 0 1px rgba(255,255,255,0.3) inset;
}
.tab-btn.active:hover {
    transform: translateY(-3px) scale(1.04);
    box-shadow: 0 12px 30px rgba(16,185,129,0.5), 0 0 15px rgba(255,255,255,0.4) inset;
    filter: brightness(1.08);
}
.tab-btn[style*="background:linear-gradient"] {
    box-shadow: 0 6px 20px rgba(16,185,129,0.4), 0 0 0 1px rgba(255,255,255,0.2) inset !important;
}
.tab-btn[style*="background:linear-gradient"]:hover {
    transform: translateY(-3px) scale(1.05) !important;
    box-shadow: 0 12px 30px rgba(16,185,129,0.55), 0 0 18px rgba(255,255,255,0.3) inset !important;
    filter: brightness(1.1);
}
.tab-btn::after {
    content: ""; position: absolute; top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: rotate(45deg); transition: 0.6s;
}
.tab-btn:hover::after { left: 100%; }

/* ── 3D Glow: Bulk Delete Buttons ───────────────────────────────── */
.btn.btn-danger.btn-sm {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
    border: none !important;
    box-shadow: 0 5px 18px rgba(239,68,68,0.35), 0 0 0 1px rgba(255,255,255,0.1) inset;
    position: relative; overflow: hidden;
    transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
}
.btn.btn-danger.btn-sm:hover {
    transform: translateY(-3px) scale(1.05) !important;
    box-shadow: 0 12px 28px rgba(239,68,68,0.55), 0 0 15px rgba(255,255,255,0.3) inset;
    filter: brightness(1.1);
}
.btn.btn-danger.btn-sm::after {
    content: ""; position: absolute; top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.25), transparent);
    transform: rotate(45deg); transition: 0.6s;
}
.btn.btn-danger.btn-sm:hover::after { left: 100%; }

/* ── 3D Glow: Booking Action Buttons ────────────────────────────── */
.js-booking-action, .js-booking-delete {
    position: relative; overflow: hidden;
    transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
}
.js-booking-action[data-action="da_thue"] {
    box-shadow: 0 4px 14px rgba(5,150,105,0.35), 0 0 0 1px rgba(255,255,255,0.15) inset;
}
.js-booking-action[data-action="con_phong"] {
    box-shadow: 0 4px 14px rgba(107,114,128,0.3), 0 0 0 1px rgba(255,255,255,0.1) inset;
}
.js-booking-action:hover {
    transform: translateY(-3px) scale(1.06) !important;
    filter: brightness(1.12);
}
.js-booking-action[data-action="da_thue"]:hover {
    box-shadow: 0 10px 24px rgba(5,150,105,0.5), 0 0 12px rgba(255,255,255,0.3) inset;
}
.js-booking-action[data-action="con_phong"]:hover {
    box-shadow: 0 10px 24px rgba(107,114,128,0.45), 0 0 12px rgba(255,255,255,0.2) inset;
}
.js-booking-delete {
    box-shadow: 0 3px 10px rgba(239,68,68,0.15);
}
.js-booking-delete:hover {
    transform: translateY(-3px) scale(1.06) !important;
    background: #fde8e8 !important;
    box-shadow: 0 8px 20px rgba(239,68,68,0.3) !important;
}
.js-booking-action::after, .js-booking-delete::after {
    content: ""; position: absolute; top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: rotate(45deg); transition: 0.5s;
}
.js-booking-action:hover::after, .js-booking-delete:hover::after { left: 100%; }

/* ── 3D Glow: Booking Call Button ───────────────────────────────── */
.js-booking-actions a[href^="tel:"] {
    position: relative; overflow: hidden;
    box-shadow: 0 4px 14px rgba(16,185,129,0.35), 0 0 0 1px rgba(255,255,255,0.15) inset !important;
    transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
}
.js-booking-actions a[href^="tel:"]:hover {
    transform: translateY(-3px) scale(1.06) !important;
    box-shadow: 0 10px 24px rgba(16,185,129,0.5), 0 0 12px rgba(255,255,255,0.3) inset !important;
    filter: brightness(1.1);
}
.js-booking-actions a[href^="tel:"]::after {
    content: ""; position: absolute; top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: rotate(45deg); transition: 0.5s;
}
.js-booking-actions a[href^="tel:"]:hover::after { left: 100%; }

/* ── 3D Glow: Modal Submit Button ───────────────────────────────── */
#editPostForm button[type="submit"] {
    position: relative; overflow: hidden;
    box-shadow: 0 6px 22px rgba(16,185,129,0.4), 0 0 0 1px rgba(255,255,255,0.15) inset;
    transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
}
#editPostForm button[type="submit"]:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 14px 35px rgba(16,185,129,0.55), 0 0 18px rgba(255,255,255,0.3) inset;
    filter: brightness(1.08);
}
#editPostForm button[type="submit"]::after {
    content: ""; position: absolute; top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.25), transparent);
    transform: rotate(45deg); transition: 0.7s;
}
#editPostForm button[type="submit"]:hover::after { left: 100%; }

/* ── 3D Glow: Modal Close Button ────────────────────────────────── */
#editPostModal button[onclick="closeEditModal()"] {
    position: relative; overflow: hidden;
    box-shadow: 0 3px 12px rgba(255,255,255,0.15) inset;
    transition: all 0.3s ease !important;
}
#editPostModal button[onclick="closeEditModal()"]:hover {
    transform: scale(1.15) rotate(90deg) !important;
    background: rgba(255,255,255,0.35) !important;
}
</style>

<script>
const TAB_STORAGE_KEY = 'mnx_my_posts_active_tab';
const ROOM_STATUS_SYNC_KEY = 'mnx_room_status_sync';
let bookingActionLocked = false;

const POST_STATUS_META = {
    da_duyet:  { label: 'Đã duyệt', color: '#10b981' },
    cho_duyet: { label: 'Chờ duyệt', color: '#f59e0b' },
    tu_choi:   { label: 'Bị từ chối', color: '#ef4444' },
    bi_tu_choi:{ label: 'Bị từ chối', color: '#ef4444' },
    con_phong: { label: 'Còn phòng', color: '#10b981' },
    da_coc:    { label: 'Đã đặt cọc', color: '#f59e0b' },
    da_thue:   { label: 'Đã thuê', color: '#ef4444' },
};

const BOOKING_STATUS_META = {
    cho_xu_ly: { label: 'Chờ xử lý', color: '#f59e0b' },
    da_lien_he:{ label: 'Đã liên hệ', color: '#10b981' },
    tu_choi:   { label: 'Từ chối', color: '#ef4444' },
    da_thue:   { label: 'Đã thuê', color: '#ef4444' },
};

function broadcastRoomStatusChange(payload) {
    try {
        localStorage.setItem(ROOM_STATUS_SYNC_KEY, JSON.stringify({
            ...payload,
            emitted_at: Date.now()
        }));
    } catch (e) {}
}

function getActiveTab() {
    const isBookingsVisible = document.getElementById('panel-bookings').style.display !== 'none';
    return isBookingsVisible ? 'bookings' : 'posts';
}

function setBookingActionLock(isLocked) {
    bookingActionLocked = isLocked;
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const isBookingsTab = btn.id === 'tab-bookings';
        btn.style.pointerEvents = (isLocked && !isBookingsTab) ? 'none' : '';
        btn.style.opacity = (isLocked && !isBookingsTab) ? '0.6' : '';
    });
}

function switchTab(tab, options = {}) {
    const { persist = true, force = false } = options;

    if (bookingActionLocked && !force && tab !== getActiveTab()) {
        Swal.fire({
            icon: 'info',
            title: 'Đang xử lý yêu cầu',
            text: 'Vui lòng chờ thao tác đặt phòng hoàn tất.',
            timer: 1400,
            showConfirmButton: false
        });
        return;
    }

    document.getElementById('panel-posts').style.display    = tab === 'posts' ? '' : 'none';
    document.getElementById('panel-bookings').style.display = tab === 'bookings' ? '' : 'none';
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab)?.classList.add('active');

    if (persist) {
        sessionStorage.setItem(TAB_STORAGE_KEY, tab);
    }

    if (tab === 'bookings') {
        markBookingsAsRead();
    }
}

function restoreActiveTab() {
    const remembered = sessionStorage.getItem(TAB_STORAGE_KEY);
    if (remembered === 'bookings' || remembered === 'posts') {
        switchTab(remembered, { persist: false, force: true });
    }
}

function getPostCard(id, nguon) {
    return document.getElementById(`card-${nguon}-${id}`);
}

function updatePostsCount(delta) {
    const countEl = document.getElementById('posts-count');
    if (!countEl) return;
    const current = parseInt(countEl.textContent || '0', 10) || 0;
    countEl.textContent = String(Math.max(0, current + delta));
}

function syncPostsEmptyState() {
    const grid = document.getElementById('myPostsGrid');
    const empty = document.getElementById('postsEmptyState');
    if (!grid || !empty) return;
    const hasPosts = grid.querySelectorAll('.my-post-card').length > 0;
    grid.style.display = hasPosts ? 'grid' : 'none';
    empty.style.display = hasPosts ? 'none' : '';
}

function syncBookingsEmptyState() {
    const list = document.getElementById('bookingsList');
    const empty = document.getElementById('bookingsEmptyState');
    if (!list || !empty) return;
    const hasBookings = list.querySelectorAll('.booking-card').length > 0;
    list.style.display = hasBookings ? 'flex' : 'none';
    empty.style.display = hasBookings ? 'none' : '';
}

function setPostToggleAvailability(card, canToggle) {
    if (!card) return;
    const actions = card.querySelector('.my-post-actions');
    if (!actions) return;

    const toggleButtons = actions.querySelectorAll('.js-post-toggle');
    const note = actions.querySelector('.js-post-pending-note');

    toggleButtons.forEach(btn => {
        btn.style.display = canToggle ? '' : 'none';
        btn.disabled = !canToggle;
    });

    if (canToggle) {
        if (note) note.remove();
        return;
    }

    if (!note) {
        const pendingNote = document.createElement('div');
        pendingNote.className = 'js-post-pending-note';
        pendingNote.style.cssText = 'grid-column:1/-1;flex:1 1 100%;padding:10px 12px;border-radius:10px;background:#fffbeb;color:#92400e;font-size:.84rem;font-weight:600;';
        pendingNote.textContent = 'Chỉ đổi được trạng thái thuê sau khi bài đã được duyệt.';
        actions.insertBefore(pendingNote, actions.firstChild);
    }
}

function syncPostToggleButtons(card, status) {
    if (!card) return;
    card.querySelectorAll('.js-post-toggle').forEach(btn => {
        const isActive = btn.dataset.action === status;
        btn.style.transform = isActive ? 'translateY(-1px)' : '';
        btn.style.boxShadow = isActive ? '0 0 0 2px rgba(15,23,42,.12)' : '';
        btn.style.filter = isActive ? 'none' : 'saturate(.78)';
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function applyPostStatusToCard(card, status) {
    if (!card) return;
    const badge = card.querySelector('.js-post-badge');
    if (!badge) return;

    const meta = POST_STATUS_META[status] || { label: status, color: '#6b7280' };
    badge.textContent = meta.label;
    badge.style.background = meta.color;
    badge.dataset.status = status;
    syncPostToggleButtons(card, status);

    // Update image grayscale & overlay dynamically
    const imgWrap = card.querySelector('.js-post-img-wrap');
    if (imgWrap) {
        imgWrap.style.filter = status === 'da_thue' ? 'grayscale(1)' : '';
        
        let overlay = imgWrap.querySelector('.js-post-overlay');
        if (!overlay && (status === 'da_thue' || status === 'da_coc')) {
            overlay = document.createElement('div');
            overlay.className = 'js-post-overlay';
            imgWrap.insertBefore(overlay, badge);
        }
        
        if (status === 'da_thue') {
            overlay.style.cssText = 'position:absolute; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:5;';
            overlay.innerHTML = '<span class="js-post-overlay-text" style="border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);">ĐÃ THUÊ</span>';
        } else if (status === 'da_coc') {
            overlay.style.cssText = 'position:absolute; inset:0; background:rgba(245, 158, 11, 0.4); display:flex; align-items:center; justify-content:center; z-index:5;';
            overlay.innerHTML = '<span class="js-post-overlay-text" style="border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);">ĐÃ ĐẶT CỌC</span>';
        } else {
            if (overlay) overlay.remove();
        }
    }
}

function setPostToggleLoading(card, isLoading) {
    if (!card) return;
    card.querySelectorAll('.js-post-toggle').forEach(btn => {
        btn.disabled = isLoading;
        btn.style.opacity = isLoading ? '0.6' : '';
    });
}

function setBookingCardLoading(card, isLoading) {
    if (!card) return;
    card.querySelectorAll('.js-booking-action, .js-booking-delete').forEach(btn => {
        btn.disabled = isLoading;
        btn.style.opacity = isLoading ? '0.6' : '';
        btn.style.pointerEvents = isLoading ? 'none' : '';
    });
}

function applyBookingStatusToCard(card, bookingStatus) {
    if (!card) return;
    const statusEl = card.querySelector('.js-booking-status');
    if (!statusEl) return;
    const meta = BOOKING_STATUS_META[bookingStatus] || { label: bookingStatus, color: '#6b7280' };
    statusEl.style.background = meta.color;
    statusEl.innerHTML = `<i class="fas fa-tag" style="margin-right:2px;"></i> ${meta.label}`;
    card.dataset.status = bookingStatus;
}

function formatVndPerMonth(value) {
    const num = Number(value);
    const safe = Number.isFinite(num) ? num : 0;
    return `${new Intl.NumberFormat('vi-VN').format(safe)}đ/tháng`;
}

function updatePostCardFromPayload(payload) {
    const card = getPostCard(payload.id, payload.nguon);
    if (!card) return;

    const titleEl = card.querySelector('.js-post-title');
    const addressEl = card.querySelector('.js-post-address-text');
    const priceEl = card.querySelector('.js-post-price');

    if (titleEl) titleEl.textContent = payload.tieude || '';
    if (addressEl) addressEl.textContent = payload.diachi || '';
    if (priceEl) priceEl.textContent = formatVndPerMonth(payload.gia);

    if (payload.nguon === 'dangbai') {
        applyPostStatusToCard(card, 'cho_duyet');
        setPostToggleAvailability(card, false);
    }
}

function updateLinkedPostStatus(postId, nguon, action) {
    const card = getPostCard(postId, nguon);
    if (!card) return;
    const roomStatus = action === 'da_thue' ? 'da_thue' : 'con_phong';
    applyPostStatusToCard(card, roomStatus);
    setPostToggleAvailability(card, true);
}

async function markBookingsAsRead() {
    const badge = document.getElementById('booking-badge');
    if (!badge) return;

    try {
        const res = await fetch('api/mark_bookings_read.php');
        const data = await res.json();
        if (data.success) {
            badge.style.display = 'none';
        }
    } catch (e) {}
}

function openEditModal(data) {
    document.getElementById('edit_id').value         = data.id;
    document.getElementById('edit_nguon').value      = data.nguon;
    document.getElementById('edit_tieude').value     = data.ten_phong || '';
    document.getElementById('edit_gia').value        = data.gia || '';
    document.getElementById('edit_dientich').value   = data.dientich || '';
    document.getElementById('edit_diachi').value     = data.diachi || '';
    document.getElementById('edit_mota').value       = '';
    document.getElementById('edit_tiennghi').value   = '';
    document.getElementById('edit_ten_chunha').value = '';
    document.getElementById('edit_sdt_chunha').value = '';
    const modal = document.getElementById('editPostModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editPostModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('editPostForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';

    const payload = {
        id: document.getElementById('edit_id').value,
        nguon: document.getElementById('edit_nguon').value,
        tieude: document.getElementById('edit_tieude').value,
        gia: document.getElementById('edit_gia').value,
        dientich: document.getElementById('edit_dientich').value,
        diachi: document.getElementById('edit_diachi').value,
        mota: document.getElementById('edit_mota').value,
        tiennghi: document.getElementById('edit_tiennghi').value,
        ten_chunha: document.getElementById('edit_ten_chunha').value,
        sdt_chunha: document.getElementById('edit_sdt_chunha').value,
    };

    try {
        const res = await fetch('api/gateway.php?action=update_post', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeEditModal();
            updatePostCardFromPayload(payload);
            Swal.fire({ icon: 'success', title: 'Thành công!', text: data.message, confirmButtonColor: '#10b981' });
        } else {
            Swal.fire({ icon: 'error', title: 'Lỗi!', text: data.message, confirmButtonColor: '#10b981' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối!', text: 'Vui lòng thử lại.', confirmButtonColor: '#10b981' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Lưu thay đổi';
    }
});

async function toggleRoomStatus(id, nguon, status) {
    const card = getPostCard(id, nguon);
    const previousStatus = card?.querySelector('.js-post-badge')?.dataset.status || '';
    applyPostStatusToCard(card, status);
    setPostToggleLoading(card, true);

    try {
        const res = await fetch('api/gateway.php?action=update_post_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, nguon, status })
        });
        const data = await res.json();
        if (data.success) {
            const nextStatus = data.new_status || status;
            applyPostStatusToCard(card, nextStatus);
            setPostToggleAvailability(card, true);
            broadcastRoomStatusChange({ post_id: id, nguon, status: nextStatus });
            Swal.fire({ icon: 'success', title: 'Thành công!', text: data.message, timer: 1200, showConfirmButton: false });
        } else {
            applyPostStatusToCard(card, previousStatus);
            Swal.fire({ icon: 'error', title: 'Lỗi!', text: data.message });
        }
    } catch (err) {
        applyPostStatusToCard(card, previousStatus);
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối!', text: 'Vui lòng thử lại.' });
    } finally {
        setPostToggleLoading(card, false);
    }
}

async function updateBookingStatus(bookingId, postId, nguon, action) {
    if (bookingActionLocked) return;

    const actionText = action === 'da_thue' ? 'Đã thuê' : 'Chưa thuê (Vẫn còn phòng)';
    const confirmResult = await Swal.fire({
        title: 'Xác nhận xử lý?',
        text: `Bạn muốn đánh dấu phòng này là [${actionText}]?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'da_thue' ? '#059669' : '#6b7280',
        cancelButtonColor: '#d1d5db',
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy'
    });
    if (!confirmResult.isConfirmed) return;

    const card = document.getElementById(`booking-card-${bookingId}`);
    setBookingActionLock(true);
    setBookingCardLoading(card, true);

    try {
        const res = await fetch('api/gateway.php?action=update_booking_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: bookingId, post_id: postId, nguon, action })
        });
        const data = await res.json();
        if (data.success) {
            const bookingStatus = action === 'da_thue' ? 'da_thue' : 'da_lien_he';
            applyBookingStatusToCard(card, bookingStatus);
            updateLinkedPostStatus(postId, nguon, action);
            broadcastRoomStatusChange({
                post_id: postId,
                nguon,
                status: action === 'da_thue' ? 'da_thue' : 'con_phong'
            });
            Swal.fire({ icon: 'success', title: 'Thành công!', text: data.message, timer: 1200, showConfirmButton: false });
        } else {
            Swal.fire({ icon: 'error', title: 'Lỗi!', text: data.message });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối!', text: 'Vui lòng thử lại.' });
    } finally {
        setBookingCardLoading(card, false);
        setBookingActionLock(false);
    }
}

async function deleteBooking(id) {
    if (bookingActionLocked) return;

    const confirmResult = await Swal.fire({
        title: 'Xoá yêu cầu?',
        text: 'Bạn chắc chắn muốn xoá yêu cầu đặt phòng này?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Xoá ngay',
        cancelButtonText: 'Hủy'
    });
    if (!confirmResult.isConfirmed) return;

    const card = document.getElementById(`booking-card-${id}`);
    setBookingActionLock(true);
    setBookingCardLoading(card, true);

    try {
        const res = await fetch('api/gateway.php?action=delete_booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            card.remove();
            syncBookingsEmptyState();
            Swal.fire({ icon: 'success', title: 'Đã xoá!', text: data.message, timer: 1200, showConfirmButton: false });
        } else {
            Swal.fire({ icon: 'error', title: 'Lỗi!', text: data.message });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối!', text: 'Vui lòng thử lại.' });
    } finally {
        setBookingCardLoading(card, false);
        setBookingActionLock(false);
    }
}

// Bulk Actions for Bookings
function toggleBookingSelectAll(master) {
    const checks = document.querySelectorAll('.booking-check-item');
    checks.forEach(c => c.checked = master.checked);
    updateBookingBulkBarState();
}

function onBookingItemCheckChange() {
    updateBookingBulkBarState();
}

function updateBookingBulkBarState() {
    const checks = document.querySelectorAll('.booking-check-item');
    const checked = Array.from(checks).filter(c => c.checked);
    const bar = document.getElementById('bookingsBulkBar');
    const countEl = document.getElementById('bookingSelectedCount');
    const master = document.getElementById('bookingMasterCheck');

    if (bar) bar.style.display = checks.length > 0 ? 'flex' : 'none';
    if (countEl) countEl.textContent = checked.length;
    if (master) {
        master.checked = checks.length > 0 && checked.length === checks.length;
        master.indeterminate = checked.length > 0 && checked.length < checks.length;
    }
}

async function bulkDeleteBookings() {
    const checked = Array.from(document.querySelectorAll('.booking-check-item:checked'));
    if (checked.length === 0) return;

    const result = await Swal.fire({
        title: `Xoá ${checked.length} yêu cầu?`,
        text: "Bạn không thể hoàn tác thao tác này!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Xoá ngay',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    const ids = checked.map(c => c.value);
    
    Swal.fire({ title: 'Đang xoá...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('api/xoa_nhieu_booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids })
        });
        const data = await res.json();
        if (data.success) {
            ids.forEach(id => {
                const el = document.getElementById(`booking-card-${id}`);
                if (el) el.remove();
            });
            updateBookingBulkBarState();
            syncBookingsEmptyState();
            Swal.fire({ icon: 'success', title: 'Thành công!', text: data.message, timer: 1500, showConfirmButton: false });
        } else {
            Swal.fire({ icon: 'error', title: 'Lỗi!', text: data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối!', text: 'Vui lòng thử lại sau.' });
    }
}

// ── Bulk Actions for Posts ──────────────────────────────────────
function togglePostSelectAll(cb) {
    const isChecked = cb.checked;
    document.querySelectorAll('.post-check-item').forEach(item => item.checked = isChecked);
    onPostItemCheckChange();
}

function onPostItemCheckChange() {
    const checks = document.querySelectorAll('.post-check-item:checked');
    const total = document.querySelectorAll('.post-check-item').length;
    const count = checks.length;
    
    const bar = document.getElementById('postsBulkBar');
    const master = document.getElementById('postMasterCheck');
    const countDisplay = document.getElementById('postSelectedCount');
    
    if (bar) bar.style.display = total > 0 ? 'flex' : 'none';
    if (countDisplay) countDisplay.textContent = count;
    
    if (master) {
        master.checked = count === total && total > 0;
        master.indeterminate = count > 0 && count < total;
    }
}

async function bulkDeletePosts() {
    const checks = document.querySelectorAll('.post-check-item:checked');
    if (!checks.length) return;

    // Group by source (dangbai or phongtro)
    const grouped = { dangbai: [], phongtro: [] };
    checks.forEach(c => {
        const nguon = c.dataset.nguon;
        if (grouped[nguon]) grouped[nguon].push(c.value);
    });

    const result = await Swal.fire({
        title: 'Xóa hàng loạt?',
        text: `Bạn chắc chắn muốn xóa vĩnh viễn ${checks.length} bài đăng đã chọn?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    let totalDeleted = 0;
    try {
        Swal.fire({ title: 'Đang xử lý...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        for (const [nguon, ids] of Object.entries(grouped)) {
            if (ids.length === 0) continue;
            const res = await fetch('api/xoa_nhieu_bai.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids, nguon })
            });
            const data = await res.json();
            if (data.success) {
                ids.forEach(id => {
                    const card = document.getElementById(`card-${nguon}-${id}`);
                    if (card) card.remove();
                });
                totalDeleted += ids.length;
            }
        }

        updatePostsCount(-totalDeleted);
        syncPostsEmptyState();
        onPostItemCheckChange();
        
        Swal.fire({ icon: 'success', title: 'Thành công!', text: `Đã xóa ${totalDeleted} bài đăng thành công.`, confirmButtonColor: '#10b981', timer: 1500, showConfirmButton: false });
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối!', text: 'Vui lòng thử lại.' });
    }
}

async function deletePost(id, nguon, cardId) {
    const result = await Swal.fire({
        title: 'Xóa bài đăng?',
        text: 'Bài đăng sẽ bị xóa vĩnh viễn, không thể khôi phục!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
    });
    if (!result.isConfirmed) return;

    try {
        const res = await fetch('api/gateway.php?action=delete_post', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, nguon })
        });
        const data = await res.json();
        if (data.success) {
            const card = document.getElementById(cardId);
            if (card) {
                card.style.transition = 'opacity .3s ease';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    updatePostsCount(-1);
                    syncPostsEmptyState();
                }, 300);
            } else {
                updatePostsCount(-1);
                syncPostsEmptyState();
            }
            Swal.fire({ icon: 'success', title: 'Đã xóa!', text: data.message, confirmButtonColor: '#10b981', timer: 1500, showConfirmButton: false });
        } else {
            Swal.fire({ icon: 'error', title: 'Lỗi!', text: data.message, confirmButtonColor: '#10b981' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối!', text: 'Vui lòng thử lại.', confirmButtonColor: '#10b981' });
    }
}

document.getElementById('editPostModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

window.addEventListener('storage', function(event) {
    if (event.key !== ROOM_STATUS_SYNC_KEY || !event.newValue) return;

    try {
        const payload = JSON.parse(event.newValue);
        if (!payload || !payload.post_id || !payload.nguon || !payload.status) return;

        const card = getPostCard(payload.post_id, payload.nguon);
        if (!card) return;

        applyPostStatusToCard(card, payload.status);
        setPostToggleAvailability(card, true);
    } catch (e) {}
});

restoreActiveTab();
syncPostsEmptyState();
syncBookingsEmptyState();
document.querySelectorAll('.my-post-card').forEach(card => {
    const currentStatus = card.querySelector('.js-post-badge')?.dataset.status || '';
    syncPostToggleButtons(card, currentStatus);
});
onPostItemCheckChange();
</script>

<?php include 'includes/footer.php'; ?>
