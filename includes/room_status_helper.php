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
                   SET trangthai = CASE
                       WHEN duyet_luc IS NOT NULL THEN 'da_duyet'
                       ELSE 'cho_duyet'
                   END
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

// Hàm tính tọa độ xấp xỉ theo địa chỉ để định vị chính xác phòng trọ trên bản đồ
function getApproximateCoords($diachi, $id, $storedLat = null, $storedLng = null) {
    $storedLatVal = floatval($storedLat);
    $storedLngVal = floatval($storedLng);
    if (!empty($storedLat) && !empty($storedLng) && 
        $storedLatVal != 18.6734 && $storedLngVal != 105.6812 &&
        $storedLatVal != 18.6923405 && $storedLngVal != 105.681627) {
        return [
            'lat' => $storedLatVal,
            'lng' => $storedLngVal
        ];
    }
    
    // Mặc định: Trường Đại học Kinh tế Nghệ An (TP. Vinh)
    $lat = 18.6923405;
    $lng = 105.681627;
    
    $clean = mb_strtolower($diachi, 'UTF-8');
    
    if (mb_strpos($clean, 'hưng dũng') !== false) {
        $lat = 18.6852;
        $lng = 105.6983;
    } elseif (mb_strpos($clean, 'bến thủy') !== false) {
        $lat = 18.6580;
        $lng = 105.6935;
    } elseif (mb_strpos($clean, 'hà huy tập') !== false || mb_strpos($clean, 'lý tự trọng') !== false) {
        $lat = 18.6923405;
        $lng = 105.681627;
    } elseif (mb_strpos($clean, 'lê lợi') !== false) {
        $lat = 18.6882;
        $lng = 105.6762;
    } elseif (mb_strpos($clean, 'quang trung') !== false) {
        $lat = 18.6791;
        $lng = 105.6781;
    } elseif (mb_strpos($clean, 'vinh phú') !== false || mb_strpos($clean, 'yên toàn') !== false) {
        $lat = 18.6915;
        $lng = 105.6802;
    } elseif (mb_strpos($clean, 'hồng sơn') !== false) {
        $lat = 18.6675;
        $lng = 105.6821;
    }
    
    // Tạo độ lệch nhỏ ngẫu nhiên theo ID để tránh các ghim trùng nhau xếp chồng khít
    $offset_lat = (($id * 17) % 100 - 50) * 0.00008;
    $offset_lng = (($id * 31) % 100 - 50) * 0.00008;
    
    return [
        'lat' => $lat + $offset_lat,
        'lng' => $lng + $offset_lng
    ];
}

