<?php

function ensureDangbaiRoomStatusSchema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $db->exec("ALTER TABLE dangbai_chothuetro ADD COLUMN trangthai_phong ENUM('con_phong','da_coc','da_thue') DEFAULT 'con_phong' COMMENT 'Tình trạng thuê phòng'");
    } catch (Exception $e) {}

    try {
        $db->exec("ALTER TABLE dangbai_chothuetro ADD COLUMN hinhanh_list TEXT NULL COMMENT 'Danh sách ảnh JSON'");
    } catch (Exception $e) {}

    try {
        $db->exec("ALTER TABLE dangbai_chothuetro ADD COLUMN ai_check TEXT NULL COMMENT 'Kết quả kiểm duyệt AI JSON'");
    } catch (Exception $e) {}

    try {
        $db->exec("UPDATE dangbai_chothuetro
                   SET hinhanh_list = JSON_ARRAY(hinhanh)
                   WHERE (hinhanh_list IS NULL OR hinhanh_list = '')
                     AND hinhanh IS NOT NULL AND hinhanh <> ''");
    } catch (Exception $e) {}

    try {
        $db->exec("UPDATE dangbai_chothuetro SET trangthai_phong = trangthai WHERE trangthai IN ('con_phong','da_coc','da_thue')");
    } catch (Exception $e) {}

    try {
        $db->exec("UPDATE dangbai_chothuetro
                   SET trangthai = 'da_duyet'
                   WHERE trangthai IN ('con_phong','da_coc','da_thue')");
    } catch (Exception $e) {}

    try {
        $db->exec("UPDATE dangbai_chothuetro SET trangthai = 'cho_duyet' WHERE trangthai IS NULL OR trangthai = ''");
    } catch (Exception $e) {}

    try {
        $db->exec("UPDATE dangbai_chothuetro SET trangthai_phong = 'con_phong' WHERE trangthai_phong IS NULL OR trangthai_phong = ''");
    } catch (Exception $e) {}

    try {
        $db->exec("ALTER TABLE dangbai_chothuetro MODIFY COLUMN trangthai ENUM('cho_duyet','da_duyet','tu_choi') DEFAULT 'cho_duyet'");
    } catch (Exception $e) {}

    try {
        $db->exec("ALTER TABLE dangbai_chothuetro MODIFY COLUMN trangthai_phong ENUM('con_phong','da_coc','da_thue') DEFAULT 'con_phong'");
    } catch (Exception $e) {}

    // Bổ sung các cột toạ độ và video của bài đăng chủ nhà
    try { $db->exec("ALTER TABLE dangbai_chothuetro ADD COLUMN lat DECIMAL(10, 8) DEFAULT 18.6923405"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE dangbai_chothuetro ADD COLUMN lng DECIMAL(11, 8) DEFAULT 105.681627"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE dangbai_chothuetro ADD COLUMN video VARCHAR(255) DEFAULT ''"); } catch (Exception $e) {}

    try {
        // Cập nhật lại các phòng bị chuyển sang cho_duyet nhầm do thiếu duyet_luc
        $db->exec("UPDATE dangbai_chothuetro SET trangthai = 'da_duyet' WHERE trangthai = 'cho_duyet'");
    } catch (Exception $e) {}

    $done = true;
}

function ensurePhongtroRoomStatusSchema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $db->exec("ALTER TABLE phongtro MODIFY COLUMN trangthai ENUM('con_phong','da_coc','da_thue') DEFAULT 'con_phong'");
    } catch (Exception $e) {}

    // Bổ sung các cột toạ độ, video và thông tin chủ nhà cho bảng phongtro
    try { $db->exec("ALTER TABLE phongtro ADD COLUMN ten_chunha VARCHAR(100) DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE phongtro ADD COLUMN sdt_chunha VARCHAR(20) DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE phongtro ADD COLUMN video VARCHAR(255) DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE phongtro ADD COLUMN lat DECIMAL(10, 8) DEFAULT 18.6923405"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE phongtro ADD COLUMN lng DECIMAL(11, 8) DEFAULT 105.681627"); } catch (Exception $e) {}

    $done = true;
}

function ensureDatabaseIndexes(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $indexes = [
        ['chatbot_history', 'idx_cbh_support', 'chat_type, is_read, sender'],
        ['chatbot_history', 'idx_cbh_session', 'session_id'],
        ['chatbot_history', 'idx_cbh_created', 'created_at'],
        ['dangbai_chothuetro', 'idx_db_nguoidang', 'nguoidang'],
        ['dangbai_chothuetro', 'idx_db_trangthai', 'trangthai'],
        ['dangbai_chothuetro', 'idx_db_trangthai_phong', 'trangthai_phong'],
        ['dangbai_chothuetro', 'idx_db_gia', 'gia'],
        ['phongtro', 'idx_pt_trangthai', 'trangthai'],
        ['users', 'idx_u_username', 'username'],
        ['users', 'idx_u_email', 'email'],
        ['dat_phong', 'idx_dp_post_id', 'post_id'],
        ['notifications', 'idx_notif_user_id', 'user_id'],
        ['notifications', 'idx_notif_is_read', 'is_read'],
    ];

    foreach ($indexes as $idx) {
        try {
            $stmt = $db->query("SHOW INDEX FROM `{$idx[0]}` WHERE Key_name = '{$idx[1]}'");
            if ($stmt->rowCount() === 0) {
                $db->exec("CREATE INDEX `{$idx[1]}` ON `{$idx[0]}` ({$idx[2]})");
            }
        } catch (Exception $e) {
            try {
                $db->exec("CREATE INDEX `{$idx[1]}` ON `{$idx[0]}` ({$idx[2]})");
            } catch (Exception $e2) {}
        }
    }

    $done = true;
}

function ensureRoomStatusSchema(PDO $db): void
{
    $dbHost = $_ENV['DB_HOST'] ?? $_ENV['MYSQLHOST'] ?? 'localhost';
    $dbName = $_ENV['DB_NAME'] ?? $_ENV['MYSQLDATABASE'] ?? 'quanlytro';
    $lockFile = sys_get_temp_dir() . '/.mnx_schema_migrated_' . md5($dbHost . '_' . $dbName);
    
    if (file_exists($lockFile)) {
        return;
    }

    ensureDangbaiRoomStatusSchema($db);
    ensurePhongtroRoomStatusSchema($db);
    ensureDatabaseIndexes($db);

    @file_put_contents($lockFile, date('Y-m-d H:i:s'));
}

function normalizeRoomStatusValue(?string $status): string
{
    return in_array($status, ['con_phong', 'da_coc', 'da_thue'], true) ? $status : 'con_phong';
}

// Polyfill for mbstring extension in case it is disabled in php.ini
if (!function_exists('mb_stripos')) {
    function mb_stripos($haystack, $needle, $offset = 0, $encoding = null) {
        return stripos($haystack, $needle, $offset);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($str, $encoding = null) {
        return strtolower($str);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = null) {
        return strlen($str);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = null) {
        return substr($str, $start, $length);
    }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) {
        return strpos($haystack, $needle, $offset);
    }
}

// Hàm thực hiện Geocoding qua OpenStreetMap Nominatim API (chỉ gọi khi tạo/sửa bài đăng)
function geocodeAddress($diachi) {
    $address = trim($diachi);
    if (empty($address)) {
        return null;
    }
    
    // Tối ưu địa chỉ tìm kiếm địa điểm TP. Vinh, Nghệ An
    $searchAddress = $address;
    if (mb_stripos($searchAddress, 'Vinh') === false) {
        $searchAddress .= ', Vinh, Nghệ An, Vietnam';
    } elseif (mb_stripos($searchAddress, 'Nghệ An') === false) {
        $searchAddress .= ', Nghệ An, Vietnam';
    }
    
    $url = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($searchAddress) . '&format=json&limit=1';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout nhanh 3 giây
    curl_setopt($ch, CURLOPT_USERAGENT, 'MaiNhaXanhRoomGeocoder/1.0 (contact@mainhaxanh.com)');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            return [
                'lat' => floatval($data[0]['lat']),
                'lng' => floatval($data[0]['lon'])
            ];
        }
    }
    return null;
}

// Hàm tính tọa độ xấp xỉ theo địa chỉ để định vị chính xác phòng trọ trên bản đồ (HOÀN TOÀN OFFLINE)
function getApproximateCoords($diachi, $id, $storedLat = null, $storedLng = null, $source = 'phongtro') {
    $storedLatVal = floatval($storedLat);
    $storedLngVal = floatval($storedLng);
    
    if (!empty($storedLat) && !empty($storedLng) && 
        $storedLatVal != 18.6734 && $storedLngVal != 105.6812 &&
        $storedLatVal != 18.6923405 && $storedLngVal != 105.681627 &&
        $storedLatVal != 0 && $storedLngVal != 0) {
        return [
            'lat' => $storedLatVal,
            'lng' => $storedLngVal
        ];
    }
    
    $address = trim($diachi);
    // Mặc định: Trường Đại học Kinh tế Nghệ An (TP. Vinh)
    $lat = 18.6923405;
    $lng = 105.681627;
    
    if (!empty($address)) {
        $clean = mb_strtolower($address, 'UTF-8');
        
        if (mb_strpos($clean, 'hưng dũng') !== false) {
            $lat = 18.6815; $lng = 105.7020;
        } elseif (mb_strpos($clean, 'bến thủy') !== false) {
            $lat = 18.6575; $lng = 105.6942;
        } elseif (mb_strpos($clean, 'hà huy tập') !== false || mb_strpos($clean, 'lý tự trọng') !== false) {
            $lat = 18.6965; $lng = 105.6795;
        } elseif (mb_strpos($clean, 'lê lợi') !== false) {
            $lat = 18.6885; $lng = 105.6720;
        } elseif (mb_strpos($clean, 'quang trung') !== false) {
            $lat = 18.6765; $lng = 105.6765;
        } elseif (mb_strpos($clean, 'vinh phú') !== false || mb_strpos($clean, 'yên toàn') !== false) {
            $lat = 18.6915; $lng = 105.6802;
        } elseif (mb_strpos($clean, 'hồng sơn') !== false) {
            $lat = 18.6670; $lng = 105.6820;
        } elseif (mb_strpos($clean, 'hưng bình') !== false) {
            $lat = 18.6845; $lng = 105.6785;
        } elseif (mb_strpos($clean, 'hưng phúc') !== false) {
            $lat = 18.6925; $lng = 105.6885;
        } elseif (mb_strpos($clean, 'lê mao') !== false) {
            $lat = 18.6725; $lng = 105.6800;
        } elseif (mb_strpos($clean, 'quán bàu') !== false) {
            $lat = 18.7040; $lng = 105.6685;
        } elseif (mb_strpos($clean, 'trung đô') !== false) {
            $lat = 18.6565; $lng = 105.6830;
        } elseif (mb_strpos($clean, 'trường thi') !== false) {
            $lat = 18.6675; $lng = 105.6925;
        } elseif (mb_strpos($clean, 'đông vĩnh') !== false) {
            $lat = 18.6885; $lng = 105.6550;
        } elseif (mb_strpos($clean, 'hưng lộc') !== false || mb_strpos($clean, 'lê viết thuật') !== false) {
            $lat = 18.6885; $lng = 105.7195;
        } elseif (mb_strpos($clean, 'nghi phú') !== false) {
            $lat = 18.7115; $lng = 105.6960;
        } elseif (mb_strpos($clean, 'cửa nam') !== false) {
            $lat = 18.6720; $lng = 105.6675;
        } elseif (mb_strpos($clean, 'đội cung') !== false) {
            $lat = 18.6775; $lng = 105.6685;
        }
    }
    
    // Tạo độ lệch nhỏ ngẫu nhiên theo ID để tránh các ghim trùng nhau xếp chồng khít
    $offset_lat = (($id * 17) % 100 - 50) * 0.00008;
    $offset_lng = (($id * 31) % 100 - 50) * 0.00008;
    
    return [
        'lat' => $lat + $offset_lat,
        'lng' => $lng + $offset_lng
    ];
}

