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

function ensureRoomStatusSchema(PDO $db): void
{
    ensureDangbaiRoomStatusSchema($db);
    ensurePhongtroRoomStatusSchema($db);
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

