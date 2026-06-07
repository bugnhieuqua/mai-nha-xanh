<?php 
require_once 'config/bootstrap.php';
$page_title = "Phòng trọ";
include 'includes/header.php'; 
?>
<!-- Leaflet Map Integration -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

<?php
require_once 'includes/media_helper.php';
require_once 'includes/room_status_helper.php';

$database = new Database();
$db = $database -> getConnection();
ensureRoomStatusSchema($db);



// Lấy danh sách phòng trọ từ bảng phongtro
$query = "SELECT *, 'phongtro' as nguon FROM phongtro ORDER BY ngaydang DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách bài đăng cho thuê (đã duyệt hoặc tất cả)
try {


    $query2 = "SELECT id, tieude as ten_phong, mota, hinhanh, hinhanh_list, video, gia, dientich, diachi, tiennghi, 
               ten_chunha, sdt_chunha, nguoidang, ngaydang, COALESCE(trangthai_phong, 'con_phong') as trangthai, 
               COALESCE(lat, 18.6923405) as lat, COALESCE(lng, 105.681627) as lng, 'dangbai' as nguon 
               FROM dangbai_chothuetro WHERE trangthai = 'da_duyet' ORDER BY ngaydang DESC";
    $stmt2 = $db->prepare($query2);
    $stmt2->execute();
    $posted_rooms = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Gộp 2 danh sách
    $rooms = array_merge($rooms, $posted_rooms);
    
    // Sắp xếp theo ngày đăng mới nhất lên đầu
    usort($rooms, function($a, $b) {
        return strtotime($b['ngaydang']) - strtotime($a['ngaydang']);
    });
} catch (PDOException $e) {
    // Bảng chưa tạo thì bỏ qua
}

// Xây dựng danh sách địa điểm duy nhất từ dữ liệu phòng
$locations = [];
foreach ($rooms as $room) {
    $diachi = $room['diachi'] ?? '';
    if (empty($diachi)) continue;
    // Logic lấy địa điểm: ưu tiên "Phường, Quận/Huyện"
    $parts = array_map(function($p) {
        return trim($p, " \t\n\r\0\x0B'\""); // Thêm trim nháy đơn/kép
    }, explode(',', $diachi));
    
    // Loại bỏ phần tỉnh (Nghệ An) nếu có
    if (count($parts) > 1 && (mb_stripos(end($parts), 'Nghệ An') !== false)) {
        array_pop($parts);
    }
    // Lấy tối đa 2 phần cuối còn lại (VD: Phường, TP)
    $loc = implode(', ', array_slice($parts, -2));

    
    if (!empty($loc) && !in_array($loc, $locations)) {
        $locations[] = $loc;
    }
}

sort($locations);
?>

<!-- Page Header -->
<section class="page-header">
    <div class="hero-bg-layer"></div>
    <div class="hero-glow-circle hero-glow-1"></div>
    <div class="hero-glow-circle hero-glow-2"></div>
    <div class="container">
        <h1 class="typing-effect">Danh sách phòng trọ</h1>
        <p class="animate-fade-up" style="animation-delay: 0.2s;">Tìm kiếm phòng trọ phù hợp với nhu cầu của bạn</p>
    </div>
</section>

<!-- Rooms Section -->
<section class="rooms-section">
    <div class="container">
        <!-- Mobile Filter Button - Removed -->
        
        <!-- Filter Overlay - Removed -->

        <div class="rooms-layout">
            <!-- Sidebar Filter -->
            <aside class="filter-sidebar" id="filterSidebar">
                <!-- Mobile Filter Header - Hidden -->
                <div class="mobile-filter-header" style="display:none; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:15px;">
                    <h3 style="margin:0; font-size:1.2rem; color:#fff;"><i class="fas fa-filter"></i> Lọc kết quả</h3>
                    <button onclick="toggleMobileFilter()" style="background:rgba(255,255,255,0.15); border:none; font-size:1.3rem; color:#fff; cursor:pointer; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">&times;</button>
                </div>

                <!-- Desktop Header -->
                <div class="filter-sidebar-header desktop-filter-header">
                    <div class="filter-header-icon"><i class="fas fa-sliders-h"></i></div>
                    <div>
                        <h3><i class="fas fa-filter"></i> Bộ lọc tìm kiếm</h3>
                        <p class="filter-sub">Tìm phòng phù hợp nhất với bạn</p>
                    </div>
                </div>

                <!-- Divider -->
                <div class="filter-divider"></div>

                <!-- Tìm kiếm -->
                <div class="filter-group">
                    <label class="filter-label"><span class="filter-label-icon">🔍</span> Tìm kiếm nhanh</label>
                    <div class="filter-input-wrap">
                        <i class="fas fa-search filter-input-icon"></i>
                        <input type="text" id="searchInput" class="filter-input" placeholder="Tên phòng, địa chỉ..." oninput="applyFilters()">
                    </div>
                </div>

                <!-- Khu vực -->
                <div class="filter-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="filter-label" style="margin:0;"><span class="filter-label-icon">📍</span> Khu vực</label>
                        <span id="locationClearBtn"
                              onclick="event.stopPropagation(); clearLocationFilter()"
                              style="display:none; cursor:pointer; font-size:.72rem; color:#fff;
                                     background:rgba(239,68,68,0.7); padding:3px 10px; border-radius:20px;
                                     font-weight:600; transition:.2s; backdrop-filter:blur(5px);"
                              onmouseover="this.style.background='rgba(239,68,68,0.9)'"
                              onmouseout="this.style.background='rgba(239,68,68,0.7)'"
                              title="Xóa khu vực đã chọn">✕ Xóa</span>
                    </div>
                    <div id="locationWrapper"
                         onclick="toggleLocationList()"
                         class="filter-location-wrap">
                        <i class="fas fa-map-marker-alt filter-input-icon"></i>
                        <input type="text" id="locationInput"
                               placeholder="Tất cả khu vực..."
                               oninput="filterLocationList(this.value)"
                               onclick="event.stopPropagation(); showLocationList()"
                               autocomplete="off"
                               class="filter-input" style="padding-left:36px;">
                        <span id="locationChevron" class="filter-chevron">▼</span>
                    </div>
                    <input type="hidden" id="locationFilter" value="">
                    <ul id="locationListbox">
                        <li data-val="" data-norm="" onclick="selectLocation('', '')">
                            <i class="fas fa-globe" style="margin-right:6px; opacity:.6;"></i> Tất cả khu vực
                        </li>
                        <?php foreach ($locations as $loc): ?>
                         <li data-val="<?php echo htmlspecialchars(mb_strtolower($loc, 'UTF-8')); ?>"
                             data-norm="<?php echo htmlspecialchars(mb_strtolower($loc, 'UTF-8')); ?>"
                             onmousedown="event.stopPropagation(); selectLocation('<?php echo htmlspecialchars(mb_strtolower($loc, 'UTF-8'), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($loc, ENT_QUOTES); ?>')">
                             <i class="fas fa-map-pin" style="margin-right:6px; color:#10b981; opacity:.7;"></i>
                              <?php echo htmlspecialchars($loc); ?>
                         </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Khoảng giá -->
                <div class="filter-group">
                    <label class="filter-label"><span class="filter-label-icon">💰</span> Khoảng giá</label>
                    <div class="filter-price-tags">
                        <button class="price-tag active" data-val="" onclick="setPriceTag(this, '')">Tất cả</button>
                        <button class="price-tag" data-val="0-1000000" onclick="setPriceTag(this, '0-1000000')">Dưới 1tr</button>
                        <button class="price-tag" data-val="1000000-2000000" onclick="setPriceTag(this, '1000000-2000000')">1–2tr</button>
                        <button class="price-tag" data-val="2000000-3000000" onclick="setPriceTag(this, '2000000-3000000')">2–3tr</button>
                        <button class="price-tag" data-val="3000000-5000000" onclick="setPriceTag(this, '3000000-5000000')">3–5tr</button>
                        <button class="price-tag" data-val="5000000-10000000" onclick="setPriceTag(this, '5000000-10000000')">5–10tr</button>
                        <button class="price-tag" data-val="10000000-999999999" onclick="setPriceTag(this, '10000000-999999999')">Trên 10tr</button>
                    </div>
                    <select id="priceFilter" class="form-control" onchange="applyFilters()" style="display:none;">
                        <option value="">Tất cả</option>
                        <option value="0-1000000">Dưới 1 triệu</option>
                        <option value="1000000-2000000">1 - 2 triệu</option>
                        <option value="2000000-3000000">2 - 3 triệu</option>
                        <option value="3000000-5000000">3 - 5 triệu</option>
                        <option value="5000000-10000000">5 - 10 triệu</option>
                        <option value="10000000-999999999">Trên 10 triệu</option>
                    </select>
                </div>

                <!-- Diện tích -->
                <div class="filter-group">
                    <label class="filter-label"><span class="filter-label-icon">📐</span> Diện tích</label>
                    <div class="filter-area-tags">
                        <button class="area-tag active" data-val="" onclick="setAreaTag(this, '')">Tất cả</button>
                        <button class="area-tag" data-val="0-20" onclick="setAreaTag(this, '0-20')">< 20m²</button>
                        <button class="area-tag" data-val="20-30" onclick="setAreaTag(this, '20-30')">20–30m²</button>
                        <button class="area-tag" data-val="30-50" onclick="setAreaTag(this, '30-50')">30–50m²</button>
                        <button class="area-tag" data-val="50-999" onclick="setAreaTag(this, '50-999')">+50m²</button>
                    </div>
                    <select id="areaFilter" class="form-control" onchange="applyFilters()" style="display:none;">
                        <option value="">Tất cả</option>
                        <option value="0-20">Dưới 20m²</option>
                        <option value="20-30">20 - 30m²</option>
                        <option value="30-50">30 - 50m²</option>
                        <option value="50-999">Trên 50m²</option>
                    </select>
                </div>

                <!-- Trạng thái -->
                <div class="filter-group">
                    <label class="filter-label"><span class="filter-label-icon">🏠</span> Trạng thái</label>
                    <div class="filter-status-pills">
                        <label class="status-pill active" for="st-all">
                            <input type="radio" id="st-all" name="statusGroup" value="" onchange="setStatusFilter('')" checked> Tất cả
                        </label>
                        <label class="status-pill status-green" for="st-available">
                            <input type="radio" id="st-available" name="statusGroup" value="con_phong" onchange="setStatusFilter('con_phong')"> ✅ Còn phòng
                        </label>
                        <label class="status-pill status-yellow" for="st-deposited">
                            <input type="radio" id="st-deposited" name="statusGroup" value="da_coc" onchange="setStatusFilter('da_coc')"> 🟡 Đã đặt cọc
                        </label>
                        <label class="status-pill status-red" for="st-rented">
                            <input type="radio" id="st-rented" name="statusGroup" value="da_thue" onchange="setStatusFilter('da_thue')"> 🔴 Đã thuê
                        </label>
                    </div>
                    <select id="statusFilter" class="form-control" onchange="applyFilters()" style="display:none;">
                        <option value="">Tất cả</option>
                        <option value="con_phong">Còn phòng</option>
                        <option value="da_coc">Đã đặt cọc</option>
                        <option value="da_thue">Đã thuê</option>
                    </select>
                </div>

                <!-- Kết quả lọc -->
                <div id="filterResult" style="
                    background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); border-radius:10px;
                    padding:8px 12px; font-size:.85rem; color:#fff; font-weight:600;
                    margin-bottom:12px; display:none; text-align:center; backdrop-filter:blur(5px);
                "></div>

                <!-- Nút đặt lại -->
                <button class="btn-filter-reset" onclick="resetFilters()">
                    <i class="fas fa-undo"></i> Đặt lại bộ lọc
                </button>

            </aside>

            
            <!-- Main Content -->
            <div class="rooms-main">
                <!-- Leaflet Dynamic Map -->
                <div class="map-container" style="margin-bottom: 30px; position: relative;">
                    <div id="leaflet-map" style="width: 100%; height: 400px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 10;"></div>
                    
                    <!-- Custom Map Style Toggle (Google Maps style) -->
                    <style>
                    #custom-map-toggle {
                        position: absolute; 
                        bottom: 20px; 
                        left: 20px; 
                        z-index: 99; 
                        width: 70px; 
                        height: 70px; 
                        border: 2px solid #fff; 
                        border-radius: 12px; 
                        cursor: pointer; 
                        box-shadow: 0 4px 12px rgba(0,0,0,0.25); 
                        overflow: hidden; 
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    }
                    #custom-map-toggle:hover {
                        transform: translateY(-2px) scale(1.06);
                        box-shadow: 0 8px 20px rgba(0,0,0,0.35);
                        border-color: #10b981;
                    }
                    </style>
                    <div id="custom-map-toggle" onclick="toggleMapStyle()" title="Chuyển sang Bản đồ vệ tinh">
                        <div id="toggle-thumbnail" style="width: 100%; height: 100%; background: url('https://mt1.google.com/vt/lyrs=y&x=13001&y=7326&z=14') no-repeat center center; background-size: cover; display: flex; align-items: flex-end; justify-content: center;">
                            <span id="toggle-label" style="background: rgba(0,0,0,0.65); color: #fff; width: 100%; font-size: 0.68rem; text-align: center; padding: 3px 0; font-weight: 700; letter-spacing: 0.3px; text-transform: uppercase;">Vệ tinh</span>
                        </div>
                    </div>
                </div>
                
                <!-- Rooms Grid -->
                <div class="rooms-grid" id="roomsList">
                    <?php foreach ($rooms as $room): 
                        $coords = getApproximateCoords($room['diachi'] ?? '', $room['id'] ?? 0, $room['lat'] ?? null, $room['lng'] ?? null);
                        $roomLat = $coords['lat'];
                        $roomLng = $coords['lng'];

                        $roomImages = [];
                        $rawImageList = trim((string)($room['hinhanh_list'] ?? ''));
                        if ($rawImageList !== '') {
                            $decodedImageList = json_decode($rawImageList, true);
                            if (is_array($decodedImageList)) {
                                foreach ($decodedImageList as $imgPath) {
                                    $imgPath = is_string($imgPath) ? buildMediaUrl($imgPath) : '';
                                    if ($imgPath !== '') $roomImages[] = $imgPath;
                                }
                            } else {
                                foreach (explode(',', $rawImageList) as $imgPath) {
                                    $imgPath = buildMediaUrl($imgPath);
                                    if ($imgPath !== '') $roomImages[] = $imgPath;
                                }
                            }
                        }
                        $singleImage = buildMediaUrl($room['hinhanh'] ?? '');
                        if (empty($roomImages) && $singleImage !== '') {
                            $roomImages[] = $singleImage;
                        }
                        $thumbImage = $roomImages[0] ?? 'https://via.placeholder.com/400x300?text=Phong+Tro';

                        $roomJson = htmlspecialchars(json_encode([
                            'id' => $room['id'] ?? 0,
                            'ten_phong' => $room['ten_phong'] ?? '',
                            'mota' => $room['mota'] ?? '',
                            'hinhanh' => $thumbImage,
                            'images' => $roomImages,
                            'gia' => number_format($room['gia'] ?? 0) . 'đ/tháng',
                            'dientich' => number_format($room['dientich'] ?? 0) . 'm²',
                            'diachi' => $room['diachi'] ?? '',
                            'tiennghi' => explode(',', $room['tiennghi'] ?? ''),
                            'ten_chunha' => $room['ten_chunha'] ?? '',
                            'sdt_chunha' => $room['sdt_chunha'] ?? '',
                            'nguoidang' => $room['nguoidang'] ?? '',
                            'video' => $room['video'] ?? '',
                            'nguon' => $room['nguon'] ?? 'phongtro',
                            'trangthai' => $room['trangthai'] ?? '',
                            'lat' => $roomLat,
                            'lng' => $roomLng
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <?php
                    // Logic lọc: đồng bộ với sidebar (Ward, City)
                    $lp_parts = array_map(function($p) {
                        return trim($p, " \t\n\r\0\x0B'\"");
                    }, explode(',', $room['diachi'] ?? ''));
                    if (count($lp_parts) > 1 && (mb_stripos(end($lp_parts), 'Nghệ An') !== false)) {
                        array_pop($lp_parts);
                    }
                    $loc_key = mb_strtolower(implode(', ', array_slice($lp_parts, -2)), 'UTF-8');

                    ?>

                    <div class="room-card" 
                         style="animation-delay: <?php echo (rand(0, 40) / 10); ?>s;"
                         onclick="openRoomDetails(this)"

                         data-room-id="<?php echo intval($room['id'] ?? 0); ?>"
                         data-nguon="<?php echo htmlspecialchars($room['nguon'] ?? 'phongtro'); ?>"
                         data-room-key="[ROOM:<?php echo htmlspecialchars($room['nguon'] ?? 'phongtro'); ?>:<?php echo intval($room['id'] ?? 0); ?>]"
                         data-room="<?php echo $roomJson; ?>"
                         data-price="<?php echo $room['gia'] ?? 0; ?>" 
                         data-area="<?php echo $room['dientich'] ?? 0; ?>"
                         data-status="<?php echo htmlspecialchars($room['trangthai'] ?? 'con_phong'); ?>"
                         data-location="<?php echo htmlspecialchars($loc_key); ?>"
                         data-lat="<?php echo htmlspecialchars($roomLat); ?>"
                         data-lng="<?php echo htmlspecialchars($roomLng); ?>"
                         data-search="<?php echo mb_strtolower(htmlspecialchars(($room['ten_phong'] ?? '') . ' ' . ($room['diachi'] ?? '')), 'UTF-8'); ?>">
                        <div class="room-image js-room-image" style="<?php echo ($room['trangthai'] == 'da_thue') ? 'filter: grayscale(1);' : ''; ?>">
                            <?php if(!empty($thumbImage)): ?>
                                <img src="<?php echo htmlspecialchars($thumbImage); ?>" alt="<?php echo htmlspecialchars($room['ten_phong']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/400x300?text=Phong+Tro" alt="Phòng trọ">
                            <?php endif; ?>
                            <?php if($room['trangthai'] == 'da_thue'): ?>
                                <div class="js-room-overlay" style="position:absolute; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:5;">
                                    <span class="js-room-overlay-text" style="border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);">ĐÃ THUÊ</span>
                                </div>
                            <?php elseif($room['trangthai'] == 'da_coc'): ?>
                                <div class="js-room-overlay" style="position:absolute; inset:0; background:rgba(245, 158, 11, 0.4); display:flex; align-items:center; justify-content:center; z-index:5;">
                                    <span class="js-room-overlay-text" style="border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);">ĐÃ ĐẶT CỌC</span>
                                </div>
                            <?php endif; ?>
                            <?php 
                                $badgeClass = '';
                                $badgeText = 'Còn phòng';
                                if ($room['trangthai'] == 'da_thue') { $badgeClass = 'badge-danger'; $badgeText = 'Đã thuê'; }
                                elseif ($room['trangthai'] == 'da_coc') { $badgeClass = 'badge-warning'; $badgeText = 'Đã đặt cọc'; }
                            ?>
                            <div class="room-badge js-room-badge <?= $badgeClass ?>" <?= ($room['trangthai'] == 'da_coc') ? 'style="background-color: #f59e0b;"' : '' ?>><?= $badgeText ?></div>
                        </div>
                        <div class="room-info">
                            <h3><?php echo htmlspecialchars($room['ten_phong'] ?? ''); ?></h3>
                            <p class="room-address">
                                <i class="fas fa-map-marker-alt" style="color:#ef4444;"></i> 
                                <?php echo htmlspecialchars($room['diachi'] ?? ''); ?>
                            </p>
                            <div class="room-details">
                                <span><i class="fas fa-ruler-combined"></i> <?php echo number_format($room['dientich'] ?? 0); ?>m²</span>
                                <span class="room-price"><?php echo number_format($room['gia'] ?? 0); ?>đ</span>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>



<style>
    /* Filter sidebar visibility control */
    .filter-hidden {
        display: none !important;
    }
</style>

<script>
    const totalRooms = document.querySelectorAll('.room-card').length;


    // Chuẩn hóa chuỗi: bỏ dấu tiếng Việt, thường hóa (hỗ trợ tìm không dấu)
    function normalizeVN(str) {
        if (!str) return '';
        var res = str.toLowerCase();
        res = res.replace(/[àáạảãâầấậẩẫăằắặẳẵ]/g, "a");
        res = res.replace(/[èéẹẻẽêềếệểễ]/g, "e");
        res = res.replace(/[ìíịỉĩ]/g, "i");
        res = res.replace(/[òóọỏõôồốộổỗơờớợởỡ]/g, "o");
        res = res.replace(/[ùúụủũưừứựửữ]/g, "u");
        res = res.replace(/[ỳýỵỷỹ]/g, "y");
        res = res.replace(/đ/g, "d");
        // Remove combined accents and special chars
        res = res.normalize('NFD').replace(/[\u0300-\u036f]/g, "");
        return res.trim();
    }

    // ===== Listbox địa điểm =====
    function showLocationList() {
        const lb = document.getElementById('locationListbox');
        if (lb) lb.style.display = 'block';
    }
    function filterLocationList(val) {
        const norm = normalizeVN(val);
        const items = document.querySelectorAll('#locationListbox li');
        let any = false;
        items.forEach(li => {
            const liNorm = normalizeVN(li.dataset.norm || li.textContent);
            const visible = !norm || liNorm.includes(norm);
            li.style.display = visible ? '' : 'none';
            if (visible) any = true;
        });
        const lb = document.getElementById('locationListbox');
        if (lb) lb.style.display = any ? 'block' : 'none';
        // Khi xóa hết input → reset filter ngay
        if (!val.trim()) {
            document.getElementById('locationFilter').value = '';
            document.getElementById('locationClearBtn').style.display = 'none';
            applyFilters();
        }
    }
    function selectLocation(val, label) {
        console.log("Selected location:", val);
        document.getElementById('locationFilter').value = val;
        document.getElementById('locationInput').value  = val ? label : '';
        const clearBtn = document.getElementById('locationClearBtn');
        if (clearBtn) clearBtn.style.display = val ? 'inline' : 'none';
        
        const lb = document.getElementById('locationListbox');
        if (lb) lb.style.display = 'none';
        
        const chevron = document.getElementById('locationChevron');
        if (chevron) chevron.style.transform = 'translateY(-50%)';
        
        const wrapper = document.getElementById('locationWrapper');
        if (wrapper) {
            wrapper.style.borderColor = val ? '#10b981' : '#d1d5db';
            wrapper.style.borderWidth = val ? '2px' : '1px';
        }
        applyFilters();
    }

    function clearLocationFilter() {
        document.getElementById('locationFilter').value = '';
        document.getElementById('locationInput').value  = '';
        const clearBtn = document.getElementById('locationClearBtn');
        if (clearBtn) clearBtn.style.display = 'none';
        const wrapper = document.getElementById('locationWrapper');
        if (wrapper) wrapper.style.borderColor = '#d1d5db';
        applyFilters();
    }
    function toggleLocationList() {
        const lb = document.getElementById('locationListbox');
        const chevron = document.getElementById('locationChevron');
        const isOpen = lb && lb.style.display === 'block';
        if (lb) lb.style.display = isOpen ? 'none' : 'block';
        if (chevron) chevron.style.transform = isOpen ? 'translateY(-50%)' : 'translateY(-50%) rotate(180deg)';
        if (!isOpen) document.getElementById('locationInput').focus();
    }
    // Ẩn listbox khi click ra ngoài
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.filter-group')) {
            const lb = document.getElementById('locationListbox');
            if (lb) lb.style.display = 'none';
        }
    });

    // ===== Bộ lọc chính =====
    function applyFilters() {
        const search         = normalizeVN(document.getElementById('searchInput').value || '');
        const priceFilter    = document.getElementById('priceFilter').value;
        const areaFilter     = document.getElementById('areaFilter').value;
        const statusFilter   = document.getElementById('statusFilter').value;
        const locationFilter = document.getElementById('locationFilter').value;
        const rooms          = document.querySelectorAll('.room-card');
        let visible = 0;

        rooms.forEach(room => {
            const roomSearch   = normalizeVN(room.getAttribute('data-search') || '');
            const price        = parseFloat(room.getAttribute('data-price')) || 0;
            const area         = parseFloat(room.getAttribute('data-area')) || 0;
            const status       = room.getAttribute('data-status') || '';
            const roomLocation = (room.getAttribute('data-location') || '').toLowerCase();
            let show = true;

            // Text search (hỗ trợ không dấu)
            if (search && !roomSearch.includes(search)) show = false;

            // Price filter
            if (show && priceFilter) {
                const [min, max] = priceFilter.split('-').map(Number);
                if (price < min || price > max) show = false;
            }

            // Area filter
            if (show && areaFilter) {
                const [min, max] = areaFilter.split('-').map(Number);
                if (area < min || area > max) show = false;
            }

            // Status filter
            if (show && statusFilter && status !== statusFilter) show = false;

            // Location filter
            if (show && locationFilter && roomLocation !== locationFilter) show = false;

            room.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const resultEl = document.getElementById('filterResult');
        const noResult = document.getElementById('no-filter-result');

        if (search || priceFilter || areaFilter || statusFilter || locationFilter) {
            resultEl.style.display = 'block';
            resultEl.textContent   = `Hiển thị ${visible}/${totalRooms} phòng`;
        } else {
            resultEl.style.display = 'none';
        }

        if (visible === 0) {
            if (!noResult) {
                const p = document.createElement('p');
                p.id = 'no-filter-result';
                p.style.cssText = 'text-align:center;padding:40px;color:#888;font-size:1.1rem;grid-column:1/-1;';
                p.innerHTML = '😔 Không tìm thấy phòng trọ phù hợp. <a href="#" onclick="resetFilters();return false;" style="color:#10b981;">Xóa bộ lọc</a>';
                document.getElementById('roomsList').appendChild(p);
            }
        } else if (noResult) {
            noResult.remove();
        }
    }
    
    function setPriceTag(el, val) {
        document.querySelectorAll('.price-tag').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('priceFilter').value = val;
        applyFilters();
    }
    function setAreaTag(el, val) {
        document.querySelectorAll('.area-tag').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('areaFilter').value = val;
        applyFilters();
    }
    function setStatusFilter(val) {
        document.getElementById('statusFilter').value = val;
        document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
        const matched = document.querySelector(`.status-pill input[value="${val}"]`);
        if (matched) matched.closest('.status-pill').classList.add('active');
        applyFilters();
    }

    function resetFilters() {
        document.getElementById('searchInput').value    = '';
        document.getElementById('priceFilter').value    = '';
        document.getElementById('areaFilter').value     = '';
        document.getElementById('statusFilter').value   = '';
        document.getElementById('locationFilter').value = '';
        const li = document.getElementById('locationInput');
        if (li) li.value = '';
        const lb = document.getElementById('locationClearBtn');
        if (lb) lb.style.display = 'none';
        // Reset tag buttons
        document.querySelectorAll('.price-tag').forEach((b,i) => b.classList.toggle('active', i===0));
        document.querySelectorAll('.area-tag').forEach((b,i) => b.classList.toggle('active', i===0));
        document.querySelectorAll('.status-pill').forEach((b,i) => b.classList.toggle('active', i===0));
        document.getElementById('st-all').checked = true;
        document.querySelectorAll('.room-card').forEach(r => r.style.display = '');
        const noResult = document.getElementById('no-filter-result');
        if (noResult) noResult.remove();
        document.getElementById('filterResult').style.display = 'none';
    }
</script>


<!-- Room Details Modal -->
<div id="roomDetailsModal" class="modal-overlay" onclick="if(event.target===this) closeRoomDetails()" style="z-index: 10005; opacity: 0; pointer-events: none; transition: opacity 0.3s; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
    <!-- Mobile Close Button -->
    <button class="modal-close-btn mobile-close-btn" onclick="closeRoomDetails()"><i class="fas fa-times"></i></button>
    
    <div class="modal-box" style="background: white; border-radius: 15px; max-width: 600px; width: 95%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2); transform: translateY(-20px); transition: transform 0.3s; position: relative;">
        <!-- Desktop Close Button -->
        <button class="modal-close-btn desktop-close-btn" onclick="closeRoomDetails()" style="position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.5); border: none; font-size: 1.5rem; cursor: pointer; color: white; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; z-index: 10;"><i class="fas fa-times"></i></button>

        <div style="background:#0f172a; padding:12px 12px 8px; border-top-left-radius:15px; border-top-right-radius:15px;">
            <img id="rdImage" src="" onclick="openMediaLightbox()" style="width: 100%; height: 360px; object-fit: contain; border-radius: 10px; background:#000; cursor:zoom-in;" alt="Hình ảnh">
            <video id="rdVideo" src="" controls onclick="openMediaLightbox()" style="width:100%; height:360px; object-fit:contain; border-radius:10px; display:none; background:#000; cursor:zoom-in;"></video>
            <div style="font-size:.78rem; color:#cbd5e1; margin-top:6px; text-align:right;">Nhấn vào ảnh/video để phóng to</div>
            <div id="rdMediaThumbs" style="display:flex; gap:8px; overflow-x:auto; padding:8px 0 2px;"></div>
        </div>
        
        <div style="padding: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <h2 id="rdTitle" style="margin: 0; color: #1e293b; font-size: 1.5rem; line-height: 1.3;"></h2>
                <span id="rdStatus" class="room-badge" style="position: static; font-size: 0.8rem; padding: 4px 8px; border-radius: 12px; color: white; font-weight: 500;"></span>
            </div>
            
            <p style="color: #64748b; margin-bottom: 15px; font-size: 0.95rem;">
                <i class="fas fa-map-marker-alt" style="color:#ef4444;"></i> <span id="rdAddress"></span>
            </p>
            
            <div style="display: flex; gap: 20px; margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 10px;">
                <div style="flex: 1;">
                    <span style="display:block; font-size:0.85rem; color:#64748b;">Mức giá</span>
                    <strong id="rdPrice" style="color: #10b981; font-size: 1.2rem;"></strong>
                </div>
                <div style="width: 1px; background: #cbd5e1;"></div>
                <div style="flex: 1;">
                    <span style="display:block; font-size:0.85rem; color:#64748b;">Diện tích</span>
                    <strong id="rdArea" style="color: #334155; font-size: 1.1rem;"></strong>
                </div>
            </div>

            <!-- Landlord info -->
            <div id="rdLandlordSection" style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 12px 16px; background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border: 1px solid #d1fae5; border-radius: 12px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                <div style="width: 40px; height: 40px; background: #10b981; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; box-shadow: 0 4px 10px rgba(16,185,129,0.25);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div>
                    <span style="display: block; font-size: 0.78rem; color: #047857; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Chủ phòng trọ</span>
                    <strong id="rdLandlordName" style="color: #064e3b; font-size: 1.05rem; font-weight: 700;">Đang cập nhật...</strong>
                    <span id="rdLandlordPhone" style="display: block; font-size: 0.9rem; color: #047857; margin-top: 2px; font-weight: 500;"></span>
                </div>
            </div>

            <h4 style="margin-bottom: 10px; color: #1e293b;"><i class="fas fa-info-circle" style="color:#3b82f6;"></i> Mô tả chi tiết</h4>
            <div id="rdDesc" style="color: #475569; font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px; white-space: pre-wrap;"></div>

            <h4 style="margin-bottom: 10px; color: #1e293b;"><i class="fas fa-check-circle" style="color:#10b981;"></i> Tiện nghi</h4>
            <div id="rdAmenities" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px;"></div>

            <!-- Nút hành động -->
            <div class="action-buttons-scroll" style="display: flex; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <div id="rdContactBtn" style="flex: 1 0 auto; min-width:140px;"></div>
                <button id="rdBookingBtn" onclick="openBookingModal()" style="flex: 1 0 auto; min-width:130px; background:linear-gradient(135deg,#10b981,#059669); color:white; border:none; padding:10px 15px; border-radius:8px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fas fa-calendar-check"></i> Đặt phòng</button>
                <button id="rdReportBtn" class="btn" style="flex: 1 0 auto; min-width:130px; background:#ef4444; color:white; border:none; padding:10px 15px; border-radius:8px; font-weight:600; cursor:pointer;"><i class="fas fa-flag"></i> Báo cáo</button>
            </div>
        </div>
    </div>
</div>

<script>
const currentUsername = '<?php echo $_SESSION['username'] ?? ''; ?>';
const ROOM_STATUS_SYNC_KEY = 'mnx_room_status_sync';

function normalizeRoomStatus(st) {
    return ['con_phong', 'da_coc', 'da_thue'].includes(st) ? st : 'con_phong';
}

let _currentMediaType = 'image';
let _currentImageIndex = 0;

function getRoomImages(data) {
    const imgs = Array.isArray(data.images) ? data.images.filter(Boolean) : [];
    if (imgs.length) return imgs;
    if (data.hinhanh) return [data.hinhanh];
    return ['https://via.placeholder.com/1280x720?text=Phong+Tro'];
}

function setMainMedia(type, imageIndex = 0) {
    if (!_currentBookingData) return;
    const images = getRoomImages(_currentBookingData);
    const imageEl = document.getElementById('rdImage');
    const videoEl = document.getElementById('rdVideo');

    if (type === 'video' && _currentBookingData.video) {
        _currentMediaType = 'video';
        imageEl.style.display = 'none';
        videoEl.style.display = 'block';
        videoEl.src = _currentBookingData.video;
        videoEl.currentTime = 0;
        return;
    }

    _currentMediaType = 'image';
    _currentImageIndex = Math.max(0, Math.min(imageIndex, images.length - 1));
    videoEl.pause();
    videoEl.src = '';
    videoEl.style.display = 'none';
    imageEl.style.display = 'block';
    imageEl.src = images[_currentImageIndex];
}

function renderMediaThumbs(data) {
    const thumbsEl = document.getElementById('rdMediaThumbs');
    if (!thumbsEl) return;
    thumbsEl.innerHTML = '';

    const images = getRoomImages(data);
    images.forEach((src, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.style.cssText = 'border:2px solid transparent;background:#0b1220;padding:0;border-radius:8px;cursor:pointer;flex:0 0 auto;';
        btn.onclick = function() {
            setMainMedia('image', idx);
            renderMediaThumbs(data);
        };

        const img = document.createElement('img');
        img.src = src;
        img.alt = 'Ảnh ' + (idx + 1);
        img.style.cssText = 'width:72px;height:54px;object-fit:cover;border-radius:6px;display:block;';
        btn.appendChild(img);

        if (_currentMediaType === 'image' && _currentImageIndex === idx) {
            btn.style.borderColor = '#10b981';
        }

        thumbsEl.appendChild(btn);
    });

    if (data.video) {
        const vBtn = document.createElement('button');
        vBtn.type = 'button';
        vBtn.style.cssText = 'border:2px solid transparent;background:#0b1220;padding:0;border-radius:8px;cursor:pointer;flex:0 0 auto;position:relative;';
        vBtn.onclick = function() {
            setMainMedia('video');
            renderMediaThumbs(data);
        };

        const vTag = document.createElement('video');
        vTag.src = data.video;
        vTag.muted = true;
        vTag.preload = 'metadata';
        vTag.style.cssText = 'width:72px;height:54px;object-fit:cover;border-radius:6px;display:block;background:#000;';
        vBtn.appendChild(vTag);

        const playIcon = document.createElement('span');
        playIcon.innerHTML = '<i class="fas fa-play"></i>';
        playIcon.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;background:rgba(0,0,0,.25);border-radius:6px;';
        vBtn.appendChild(playIcon);

        if (_currentMediaType === 'video') {
            vBtn.style.borderColor = '#10b981';
        }

        thumbsEl.appendChild(vBtn);
    }
}

function openMediaLightbox() {
    if (!_currentBookingData) return;
    const lb = document.getElementById('mediaLightbox');
    const lbImg = document.getElementById('lbImage');
    const lbVid = document.getElementById('lbVideo');

    if (_currentMediaType === 'video' && _currentBookingData.video) {
        lbImg.style.display = 'none';
        lbVid.style.display = 'block';
        lbVid.src = _currentBookingData.video;
        lbVid.currentTime = 0;
    } else {
        const imgs = getRoomImages(_currentBookingData);
        lbVid.pause();
        lbVid.src = '';
        lbVid.style.display = 'none';
        lbImg.style.display = 'block';
        lbImg.src = imgs[_currentImageIndex] || imgs[0];
    }

    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeMediaLightbox() {
    const lb = document.getElementById('mediaLightbox');
    const lbVid = document.getElementById('lbVideo');
    lb.style.display = 'none';
    lbVid.pause();
    lbVid.src = '';
    document.body.style.overflow = document.getElementById('roomDetailsModal').style.pointerEvents === 'auto' ? 'hidden' : '';
}

function applyRoomStatusToModal(data) {
    const status = normalizeRoomStatus(data.trangthai);
    const statusEl = document.getElementById('rdStatus');
    const contactContainer = document.getElementById('rdContactBtn');
    const bookingBtn = document.getElementById('rdBookingBtn');
    const sdt = data.sdt_chunha ? data.sdt_chunha : '0123 456 789';

    if (status === 'con_phong') {
        statusEl.textContent = 'Còn phòng';
        statusEl.style.background = '#10b981';
        bookingBtn.style.display = 'flex';
    } else if (status === 'da_coc') {
        statusEl.textContent = 'Đã đặt cọc';
        statusEl.style.background = '#f59e0b';
        bookingBtn.style.display = 'none';
    } else {
        statusEl.textContent = 'Đã thuê';
        statusEl.style.background = '#ef4444';
        bookingBtn.style.display = 'none';
    }

    // Luôn hiển thị số điện thoại chủ trọ để liên hệ
    contactContainer.innerHTML = `<a href="tel:${sdt.replace(/\s+/g, '')}" class="btn" style="display:block; width:100%; text-align:center; padding:10px; border-radius:8px; font-weight:600; background:#3b82f6; color:white; text-decoration:none;"><i class="fas fa-phone"></i> Liên hệ / Gọi: ${sdt}</a>`;
}

function applyRoomStatusToCard(card, status) {
    if (!card) return;
    const normalized = normalizeRoomStatus(status);
    card.setAttribute('data-status', normalized);

    const roomDataRaw = card.getAttribute('data-room') || '{}';
    try {
        const roomData = JSON.parse(roomDataRaw);
        roomData.trangthai = normalized;
        card.setAttribute('data-room', JSON.stringify(roomData));
    } catch (e) {}

    const imageWrap = card.querySelector('.js-room-image');
    const badge = card.querySelector('.js-room-badge');
    let overlay = card.querySelector('.js-room-overlay');

    if (imageWrap) {
        imageWrap.style.filter = normalized === 'da_thue' ? 'grayscale(1)' : '';
    }

    if (badge) {
        badge.classList.remove('badge-danger', 'badge-warning');
        badge.style.backgroundColor = '';
        if (normalized === 'da_thue') {
            badge.classList.add('badge-danger');
            badge.textContent = 'Đã thuê';
        } else if (normalized === 'da_coc') {
            badge.classList.add('badge-warning');
            badge.style.backgroundColor = '#f59e0b';
            badge.textContent = 'Đã đặt cọc';
        } else {
            badge.textContent = 'Còn phòng';
        }
    }

    if (normalized === 'con_phong') {
        if (overlay) overlay.remove();
        return;
    }

    if (!overlay && imageWrap) {
        overlay = document.createElement('div');
        overlay.className = 'js-room-overlay';
        overlay.style.cssText = 'position:absolute; inset:0; display:flex; align-items:center; justify-content:center; z-index:5;';
        const overlayText = document.createElement('span');
        overlayText.className = 'js-room-overlay-text';
        overlayText.style.cssText = 'border:2px solid #fff; color:#fff; padding:6px 15px; font-weight:800; border-radius:4px; transform:rotate(-15deg); font-size:1.2rem; letter-spacing:1px; text-shadow:0 2px 4px rgba(0,0,0,0.5);';
        overlay.appendChild(overlayText);
        imageWrap.appendChild(overlay);
    }

    if (overlay) {
        const overlayText = overlay.querySelector('.js-room-overlay-text');
        if (normalized === 'da_thue') {
            overlay.style.background = 'rgba(0,0,0,0.5)';
            if (overlayText) overlayText.textContent = 'ĐÃ THUÊ';
        } else {
            overlay.style.background = 'rgba(245, 158, 11, 0.4)';
            if (overlayText) overlayText.textContent = 'ĐÃ ĐẶT CỌC';
        }
    }
}

function updateRoomStatusInList(roomId, nguon, status) {
    const selector = `.room-card[data-room-id="${String(roomId)}"][data-nguon="${nguon}"]`;
    const card = document.querySelector(selector);
    applyRoomStatusToCard(card, status);
}

window.addEventListener('storage', function(event) {
    if (event.key !== ROOM_STATUS_SYNC_KEY || !event.newValue) return;

    try {
        const payload = JSON.parse(event.newValue);
        if (!payload || !payload.post_id || !payload.nguon || !payload.status) return;

        updateRoomStatusInList(payload.post_id, payload.nguon, payload.status);
        if (_currentBookingData &&
            String(_currentBookingData.id) === String(payload.post_id) &&
            _currentBookingData.nguon === payload.nguon) {
            _currentBookingData.trangthai = payload.status;
            applyRoomStatusToModal(_currentBookingData);
        }
    } catch (e) {}
});

function openRoomDetails(element) {
    const data = JSON.parse(element.getAttribute('data-room'));
    _currentBookingData = data;
    _currentMediaType = 'image';
    _currentImageIndex = 0;
    setMainMedia('image', 0);
    renderMediaThumbs(data);

    document.getElementById('rdTitle').textContent = data.ten_phong;
    document.getElementById('rdAddress').textContent = data.diachi;
    document.getElementById('rdPrice').textContent = data.gia;
    document.getElementById('rdArea').textContent = data.dientich;
    document.getElementById('rdDesc').textContent = data.mota || 'Không có mô tả.';
    document.getElementById('rdLandlordName').textContent = data.ten_chunha ? data.ten_chunha : 'Liên hệ ban quản trị';
    
    // Điền thông tin số điện thoại chủ nhà ở phần thông tin
    const sdtVal = data.sdt_chunha ? data.sdt_chunha : '0123 456 789';
    document.getElementById('rdLandlordPhone').innerHTML = `<i class="fas fa-phone" style="font-size: 0.8rem; margin-right: 4px;"></i> SĐT: <a href="tel:${sdtVal.replace(/\s+/g, '')}" style="color: #047857; text-decoration: underline; font-weight: 700;">${sdtVal}</a>`;
    
    applyRoomStatusToModal(data);
    
    const amContainer = document.getElementById('rdAmenities');
    amContainer.innerHTML = '';
    if (data.tiennghi && data.tiennghi.length > 0 && data.tiennghi[0].trim() !== '') {
        data.tiennghi.forEach(t => {
            const span = document.createElement('span');
            span.style.cssText = 'background: #f1f5f9; color: #475569; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; border: 1px solid #e2e8f0;';
            span.innerHTML = `<i class="fas fa-check" style="color:#10b981; margin-right:4px;"></i> ${t.trim()}`;
            amContainer.appendChild(span);
        });
    } else {
        amContainer.innerHTML = '<span style="color:#94a3b8; font-size:0.9rem;">Không có dữ liệu tiện nghi</span>';
    }
    
    const reportBtn = document.getElementById('rdReportBtn');
    reportBtn.onclick = function() {
        closeRoomDetails();
        setTimeout(() => { openReportModal(data.id, data.nguon); }, 300);
    };

    const modal = document.getElementById('roomDetailsModal');
    const box = modal.querySelector('.modal-box');
    modal.style.opacity = '1';
    modal.style.pointerEvents = 'auto';
    box.style.transform = 'translateY(0)';
    document.body.style.overflow = 'hidden';
}

function closeRoomDetails() {
    const modal = document.getElementById('roomDetailsModal');
    const box = modal.querySelector('.modal-box');
    modal.style.opacity = '0';
    modal.style.pointerEvents = 'none';
    box.style.transform = 'translateY(-20px)';
    closeMediaLightbox();
    document.body.style.overflow = '';
}
</script>

<!-- Media Lightbox -->
<div id="mediaLightbox" onclick="if(event.target===this) closeMediaLightbox()" style="display:none; position:fixed; inset:0; background:rgba(2,6,23,.92); z-index:10002; align-items:center; justify-content:center; padding:16px;">
    <button onclick="closeMediaLightbox()" style="position:absolute; top:18px; right:18px; background:rgba(255,255,255,.2); border:none; color:#fff; width:38px; height:38px; border-radius:50%; cursor:pointer; font-size:1.2rem; z-index:2;">✕</button>
    <img id="lbImage" src="" alt="Media preview" style="display:none; max-width:min(1200px, 96vw); max-height:92vh; border-radius:12px; object-fit:contain; background:#000;">
    <video id="lbVideo" src="" controls style="display:none; max-width:min(1200px, 96vw); max-height:92vh; border-radius:12px; background:#000;"></video>
</div>

<!-- Booking Modal -->
<div id="bookingModal" class="modal-overlay" style="z-index: 10006; opacity: 0; pointer-events: none; transition: opacity 0.3s; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.65); position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
    <!-- Mobile Close Button -->
    <button class="modal-close-btn mobile-close-btn" onclick="closeBookingModal()">✕</button>
    
    <div class="modal-box" style="background:#fff;border-radius:16px;width:95%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25);position:relative;">
        <div style="background:linear-gradient(135deg,#10b981,#059669);padding:18px 22px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="color:#fff;margin:0;font-size:1.1rem;"><i class="fas fa-calendar-check"></i> Đặt phòng</h3>
            <!-- Desktop Close Button -->
            <button class="modal-close-btn desktop-close-btn" onclick="closeBookingModal()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>
        <div style="padding:22px;">
            <p id="bk_room_name" style="font-weight:700;font-size:.95rem;color:#1e293b;margin-bottom:16px;padding:10px 14px;background:#f0fdf4;border-radius:8px;border-left:3px solid #10b981;"></p>
            <form id="bookingForm">
                <input type="hidden" id="bk_post_id" name="post_id">
                <input type="hidden" id="bk_nguon" name="nguon">
                <div style="margin-bottom:14px;">
                    <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;"><i class="fas fa-user" style="color:#10b981;margin-right:4px;"></i> Họ và tên</label>
                    <input type="text" id="bk_hoten" name="ho_ten" placeholder="Nhập họ và tên của bạn" required
                           style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:.95rem;font-family:inherit;box-sizing:border-box;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;"><i class="fas fa-phone" style="color:#10b981;margin-right:4px;"></i> Số điện thoại</label>
                    <input type="tel" id="bk_sdt" name="so_dien_thoai" placeholder="VD: 0912345678" required pattern="[0-9]{10,11}"
                           style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:.95rem;font-family:inherit;box-sizing:border-box;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;"><i class="fas fa-calendar" style="color:#10b981;margin-right:4px;"></i> Ngày muốn thuê</label>
                    <input type="date" id="bk_ngay" name="ngay_muon_thue"
                           style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:.95rem;font-family:inherit;box-sizing:border-box;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-weight:600;display:block;margin-bottom:6px;color:#374151;"><i class="fas fa-comment" style="color:#10b981;margin-right:4px;"></i> Ghi chú (tuỳ chọn)</label>
                    <textarea id="bk_ghichu" name="ghi_chu" rows="3" placeholder="Yêu cầu đặc biệt, thời gian xem phòng..."
                              style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:.95rem;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
                <button type="submit" style="width:100%;padding:13px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <i class="fas fa-paper-plane"></i> Gửi yêu cầu đặt phòng
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let _currentBookingData = null;

function openBookingModal() {
    if (!_currentBookingData) return;
    if (normalizeRoomStatus(_currentBookingData.trangthai) !== 'con_phong') {
        Swal.fire({
            icon: 'info',
            title: 'Không thể đặt phòng',
            text: 'Phòng này đã có người đặt cọc hoặc đã được thuê.',
            confirmButtonColor: '#10b981'
        });
        return;
    }
    closeRoomDetails();
    setTimeout(() => {
        document.getElementById('bk_post_id').value  = _currentBookingData.id || '';
        document.getElementById('bk_nguon').value    = _currentBookingData.nguon || 'dangbai';
        document.getElementById('bk_room_name').textContent = '📍 ' + (_currentBookingData.ten_phong || 'Phòng trọ');
        document.getElementById('bk_hoten').value   = '';
        document.getElementById('bk_sdt').value     = '';
        document.getElementById('bk_ngay').value    = '';
        document.getElementById('bk_ghichu').value  = '';
        const modal = document.getElementById('bookingModal');
        const box = modal.querySelector('.modal-box');
        modal.style.opacity = '1';
        modal.style.pointerEvents = 'auto';
        if (box) box.style.transform = 'translateY(0)';
        document.body.style.overflow = 'hidden';
    }, 300);
}

function closeBookingModal() {
    const modal = document.getElementById('bookingModal');
    const box = modal.querySelector('.modal-box');
    modal.style.opacity = '0';
    modal.style.pointerEvents = 'none';
    if (box) {
        if (window.innerWidth <= 768) {
            box.style.transform = 'translateY(100%)';
        } else {
            box.style.transform = 'translateY(-20px)';
        }
    }
    document.body.style.overflow = '';
}

document.getElementById('bookingModal').addEventListener('click', function(e) {
    if (e.target === this) closeBookingModal();
});

document.getElementById('bookingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

    const payload = {
        post_id:        document.getElementById('bk_post_id').value,
        nguon:          document.getElementById('bk_nguon').value,
        ho_ten:         document.getElementById('bk_hoten').value,
        so_dien_thoai:  document.getElementById('bk_sdt').value,
        ngay_muon_thue: document.getElementById('bk_ngay').value,
        ghi_chu:        document.getElementById('bk_ghichu').value,
    };

    try {
        const res  = await fetch('api/dat_phong.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if (data.success) {
            const newStatus = data.room_status || 'da_coc';
            updateRoomStatusInList(payload.post_id, payload.nguon, newStatus);
            if (_currentBookingData && String(_currentBookingData.id) === String(payload.post_id) && _currentBookingData.nguon === payload.nguon) {
                _currentBookingData.trangthai = newStatus;
            }
            closeBookingModal();
            Swal.fire({ icon:'success', title:'Đặt phòng thành công!', text: data.message, confirmButtonColor:'#10b981' });
        } else {
            if (data.room_status) {
                updateRoomStatusInList(payload.post_id, payload.nguon, data.room_status);
                if (_currentBookingData && String(_currentBookingData.id) === String(payload.post_id) && _currentBookingData.nguon === payload.nguon) {
                    _currentBookingData.trangthai = data.room_status;
                }
                if (normalizeRoomStatus(data.room_status) !== 'con_phong') {
                    closeBookingModal();
                }
            }
            Swal.fire({ icon:'error', title:'Lỗi!', text: data.message, confirmButtonColor:'#10b981' });
        }
    } catch(err) {
        Swal.fire({ icon:'error', title:'Lỗi kết nối!', text: 'Vui lòng thử lại.', confirmButtonColor:'#10b981' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi yêu cầu đặt phòng';
    }
});
</script>

<!-- Report Modal -->
<div id="reportModal" class="modal-overlay" onclick="if(event.target===this) closeReportModal()" style="z-index: 10007; opacity: 0; pointer-events: none; transition: opacity 0.3s; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
    <!-- Mobile Close Button -->
    <button class="modal-close-btn mobile-close-btn" onclick="closeReportModal()"><i class="fas fa-times"></i></button>
    
    <div class="modal-box" style="background: white; border-radius: 12px; max-width: 400px; width: 90%; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); transform: translateY(-20px); transition: transform 0.3s; position: relative;">
        <!-- Desktop Close Button -->
        <button class="modal-close-btn desktop-close-btn" onclick="closeReportModal()" style="position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.5); border: none; font-size: 1.5rem; cursor: pointer; color: white; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; z-index: 10;"><i class="fas fa-times"></i></button>
        <h3 style="margin-top:0; color: #1e293b; font-size: 1.2rem; margin-bottom: 15px;"><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Báo cáo Bài đăng</h3>
        <p style="font-size:0.9rem; color:#64748b; margin-bottom:15px;">Vui lòng cho chúng tôi biết tại sao bạn muốn báo cáo bài đăng này (VD: Tin giả, lừa đảo, số điện thoại sai, v.v.):</p>
        <textarea id="reportReason" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 0.9rem; min-height: 80px; resize: vertical; margin-bottom: 15px;" placeholder="Nhập lý do..."></textarea>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="closeReportModal()" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 6px;">Huỷ</button>
            <button onclick="submitReport()" class="btn btn-danger" style="padding: 8px 16px; border-radius: 6px; background: #ef4444; color: white; border: none;"><i class="fas fa-paper-plane"></i> Gửi</button>
        </div>
    </div>
</div>

<script>
    let currentReportPostId = null;
    let currentReportNguon = null;

    function openReportModal(postId, nguon) {
        // Need user logged in check, handled by api, but let's check for visual
        <?php if(!isset($_SESSION['user_id'])): ?>
            alert('Vui lòng đăng nhập để có thể báo cáo!');
            window.location.href = 'login.php';
            return;
        <?php endif; ?>
        
        currentReportPostId = postId;
        currentReportNguon = nguon;
        document.getElementById('reportReason').value = '';
        
        const modal = document.getElementById('reportModal');
        const box = modal.querySelector('.modal-box');
        modal.style.opacity = '1';
        modal.style.pointerEvents = 'auto';
        box.style.transform = 'translateY(0)';
    }

    function closeReportModal() {
        const modal = document.getElementById('reportModal');
        const box = modal.querySelector('.modal-box');
        modal.style.opacity = '0';
        modal.style.pointerEvents = 'none';
        box.style.transform = 'translateY(-20px)';
    }

    async function submitReport() {
        const reason = document.getElementById('reportReason').value.trim();
        if (!reason) { alert('Vui lòng nhập lý do!'); return; }
        
        const fd = new FormData();
        fd.append('action', 'report_post');
        fd.append('post_id', currentReportPostId);
        fd.append('nguon', currentReportNguon);
        fd.append('reason', reason);

        try {
            const res = await fetch('api/report.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert('Đã gửi báo cáo thành công! Cám ơn bạn.');
                closeReportModal();
            } else {
                alert(data.message);
            }
        } catch (e) {
            alert('Lỗi kết nối. Vui lòng thử lại sau.');
        }
    }

    // ===== Leaflet Interactive Map Initialization =====
    document.addEventListener("DOMContentLoaded", function() {
        try {
            // Standard OpenStreetMap Tile Layer
            const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            });

            // Google Hybrid/Satellite Tile Layer (Vệ tinh có nhãn đường)
            const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                attribution: '&copy; Google Maps'
            });

            // Center map around Nghe An University
            const map = L.map('leaflet-map', {
                center: [18.6923405, 105.681627],
                zoom: 14,
                layers: [osm]
            });

            let currentLayer = 'osm';
            window.toggleMapStyle = function() {
                const toggleBtn = document.getElementById('custom-map-toggle');
                const thumbnail = document.getElementById('toggle-thumbnail');
                const label = document.getElementById('toggle-label');
                
                if (currentLayer === 'osm') {
                    map.removeLayer(osm);
                    map.addLayer(googleHybrid);
                    currentLayer = 'satellite';
                    thumbnail.style.backgroundImage = "url('https://a.tile.openstreetmap.org/14/13001/7326.png')";
                    label.textContent = "Bản đồ";
                    toggleBtn.setAttribute('title', 'Chuyển sang Bản đồ thường');
                } else {
                    map.removeLayer(googleHybrid);
                    map.addLayer(osm);
                    currentLayer = 'osm';
                    thumbnail.style.backgroundImage = "url('https://mt1.google.com/vt/lyrs=y&x=13001&y=7326&z=14')";
                    label.textContent = "Vệ tinh";
                    toggleBtn.setAttribute('title', 'Chuyển sang Bản đồ vệ tinh');
                }
            };

            // Add Nghe An University marker as a landmark
            L.marker([18.6923405, 105.681627], {
                icon: L.divIcon({
                    html: '<div style="background:#ef4444; color:#fff; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border:2px solid #fff; font-size:14px; font-weight:bold; box-shadow:0 3px 12px rgba(239,68,68,0.4);" title="Đại học Kinh tế Nghệ An">🏫</div>',
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                })
            }).addTo(map).bindPopup("<b>Trường Đại học Kinh tế Nghệ An</b><br><a href='https://maps.app.goo.gl/sSY9rKodfubWosh1A' target='_blank' style='color:#3b82f6; text-decoration:underline; font-weight:bold;'>Mở trên Google Maps ➔</a>");

            // Radius circle (1.5km around Nghe An University)
            L.circle([18.6923405, 105.681627], {
                color: '#10b981',
                fillColor: '#10b981',
                fillOpacity: 0.08,
                radius: 1500
            }).addTo(map);

            // Add room markers
            const cards = document.querySelectorAll(".room-card");
            const markers = L.markerClusterGroup({
                showCoverageOnHover: false,
                maxClusterRadius: 40
            });
            const markerMap = new Map();

            cards.forEach(card => {
                const roomId = card.getAttribute("data-room-id");
                const latVal = parseFloat(card.getAttribute("data-lat"));
                const lngVal = parseFloat(card.getAttribute("data-lng"));
                
                if (isNaN(latVal) || isNaN(lngVal)) return;

                // Jitter coordinate slightly if they overlap
                const lat = latVal + (Math.random() - 0.5) * 0.0006;
                const lng = lngVal + (Math.random() - 0.5) * 0.0006;

                let roomData = {};
                try {
                    roomData = JSON.parse(card.getAttribute("data-room") || "{}");
                } catch(e) {}

                const title = roomData.ten_phong || "Phòng trọ";
                const price = roomData.gia || "Liên hệ";
                const image = roomData.hinhanh || "https://via.placeholder.com/400x300?text=Phong+Tro";
                const diachi = roomData.diachi || "";

                const customIcon = L.divIcon({
                    html: `<div style="background:#10b981; color:#fff; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border:2px solid #fff; font-size:12px; font-weight:bold; box-shadow:0 3px 12px rgba(16,185,129,0.4);" class="leaflet-room-marker">🏠</div>`,
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });

                const marker = L.marker([lat, lng], { icon: customIcon });
                const popupHtml = `
                    <div style="width:190px; font-family:'Poppins', sans-serif; padding:2px;">
                        <img src="${image}" style="width:100%; height:100px; object-fit:cover; border-radius:6px; margin-bottom:8px;" />
                        <h4 style="margin:0 0 4px 0; font-size:0.85rem; font-weight:700; color:#1e293b; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${title}</h4>
                        <p style="margin:0 0 6px 0; font-size:0.72rem; color:#64748b;"><i class="fas fa-map-marker-alt" style="color:#ef4444; margin-right:3px;"></i> ${diachi.substring(0,38)}...</p>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:6px; margin-top:4px;">
                            <span style="font-weight:800; color:#10b981; font-size:0.82rem;">${price}</span>
                            <a href="javascript:void(0)" onclick="openRoomDetailsFromMap('${roomId}')" style="background:#10b981; color:#fff; font-size:0.7rem; font-weight:700; padding:4px 10px; border-radius:20px; text-decoration:none; box-shadow:0 2px 6px rgba(16,185,129,0.3);">Xem</a>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupHtml);
                markers.addLayer(marker);
                markerMap.set(roomId, marker);
            });

            map.addLayer(markers);

            // Detail modal trigger
            window.openRoomDetailsFromMap = function(id) {
                const targetCard = document.querySelector(`.room-card[data-room-id="${id}"]`);
                if (targetCard) {
                    openRoomDetails(targetCard);
                }
            };

            // Map marker updater based on sidebar filter
            window.updateMapMarkersList = function() {
                markers.clearLayers();
                cards.forEach(card => {
                    const id = card.getAttribute("data-room-id");
                    const marker = markerMap.get(id);
                    if (marker && card.style.display !== 'none') {
                        markers.addLayer(marker);
                    }
                });
            };

            // Monkey-patch applyFilters to sync map markers
            const originalFilters = window.applyFilters;
            if (originalFilters) {
                window.applyFilters = function() {
                    originalFilters();
                    if (window.updateMapMarkersList) {
                        window.updateMapMarkersList();
                    }
                };
            }
            const originalReset = window.resetFilters;
            if (originalReset) {
                window.resetFilters = function() {
                    originalReset();
                    if (window.updateMapMarkersList) {
                        window.updateMapMarkersList();
                    }
                };
            }

            // Auto-open room details from URL room_key parameter (redirected from Chatbot)
            const urlParams = new URLSearchParams(window.location.search);
            const roomKey = urlParams.get('room_key');
            if (roomKey) {
                const targetCard = document.querySelector(`.room-card[data-room-key="${roomKey}"]`);
                if (targetCard) {
                    setTimeout(() => {
                        openRoomDetails(targetCard);
                        // Xoá tham số room_key khỏi URL để tránh việc modal tự động bật lên khi tải lại (refresh) trang
                        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
                    }, 500);
                }
            }
        } catch(e) {
            console.error("Leaflet initialization failed: ", e);
        }
    });
</script>
<?php include 'includes/footer.php'; ?>
