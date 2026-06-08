<?php
/**
 * Chatbot Room Statistics API
 * Cung cấp các hàm thống kê, đếm phòng, lọc phòng cho chatbot
 * POST /api/v2/chatbot_room_stats.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không được hỗ trợ.']);
    exit;
}

$rawInput = trim(file_get_contents('php://input'));
$payload = json_decode($rawInput, true);

if (!$payload || !isset($payload['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu action.']);
    exit;
}

$db = getDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi kết nối cơ sở dữ liệu.']);
    exit;
}

function getDB()
{
    try {
        $database = new Database();
        return $database->getConnection();
    } catch (Exception $e) {
        return null;
    }
}

$action = $payload['action'];

// ========== ACTION: count_total ==========
// Đếm tổng số phòng trong hệ thống
if ($action === 'count_total') {
    try {
        $stmt1 = $db->query("SELECT COUNT(*) as cnt FROM phongtro");
        $cnt1 = $stmt1->fetchColumn() ?? 0;

        $stmt2 = $db->query("SELECT COUNT(*) as cnt FROM dangbai_chothuetro WHERE trangthai = 'da_duyet'");
        $cnt2 = $stmt2->fetchColumn() ?? 0;

        $total = $cnt1 + $cnt2;

        echo json_encode([
            'success' => true,
            'action' => 'count_total',
            'total' => $total,
            'from_phongtro' => $cnt1,
            'from_dangbai' => $cnt2,
            'message' => "Hiện tại hệ thống có *$total phòng trọ* (Bảng phongtro: $cnt1 phòng, Đăng bài: $cnt2 phòng)."
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi đếm phòng: ' . $e->getMessage()]);
    }
    exit;
}

// ========== ACTION: count_by_status ==========
// Đếm phòng theo trạng thái
if ($action === 'count_by_status') {
    try {
        // Đếm từ phongtro
        $stmt1 = $db->query("
            SELECT COALESCE(trangthai_phong, 'con_phong') as status, COUNT(*) as cnt
            FROM phongtro
            GROUP BY status
        ");
        $status1 = $stmt1->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // Đếm từ dangbai
        $stmt2 = $db->query("
            SELECT COALESCE(trangthai_phong, 'con_phong') as status, COUNT(*) as cnt
            FROM dangbai_chothuetro
            WHERE trangthai = 'da_duyet'
            GROUP BY status
        ");
        $status2 = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // Gộp dữ liệu
        $merged = [];
        foreach (['con_phong', 'da_coc', 'da_thue'] as $st) {
            $merged[$st] = ($status1[$st] ?? 0) + ($status2[$st] ?? 0);
        }

        // Format thông báo
        $parts = [];
        if ($merged['con_phong'] > 0)
            $parts[] = "*{$merged['con_phong']} phòng còn trống*";
        if ($merged['da_coc'] > 0)
            $parts[] = "*{$merged['da_coc']} phòng đã đặt cọc*";
        if ($merged['da_thue'] > 0)
            $parts[] = "*{$merged['da_thue']} phòng đã thuê*";

        $message = "Trạng thái phòng trọ:\n" . implode("\n", $parts);

        echo json_encode([
            'success' => true,
            'action' => 'count_by_status',
            'con_phong' => $merged['con_phong'],
            'da_coc' => $merged['da_coc'],
            'da_thue' => $merged['da_thue'],
            'message' => $message
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi đếm theo trạng thái: ' . $e->getMessage()]);
    }
    exit;
}

// ========== ACTION: min_price ==========
// Tìm giá phòng rẻ nhất
if ($action === 'min_price') {
    try {
        $stmt1 = $db->query("SELECT MIN(gia) as min_price FROM phongtro WHERE gia > 0");
        $min1 = $stmt1->fetchColumn() ?? null;

        $stmt2 = $db->query("SELECT MIN(gia) as min_price FROM dangbai_chothuetro WHERE trangthai = 'da_duyet' AND gia > 0");
        $min2 = $stmt2->fetchColumn() ?? null;

        $min = min(array_filter([$min1, $min2])) ?? null;

        if ($min === null) {
            echo json_encode([
                'success' => true,
                'action' => 'min_price',
                'message' => "Hệ thống chưa có thông tin giá phòng."
            ]);
        } else {
            $minFormatted = number_format($min);
            echo json_encode([
                'success' => true,
                'action' => 'min_price',
                'min_price' => $min,
                'message' => "Giá phòng *rẻ nhất* trong hệ thống là *{$minFormatted}đ/tháng*. Hãy để tôi tìm những phòng với mức giá này cho bạn."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi tìm giá rẻ nhất: ' . $e->getMessage()]);
    }
    exit;
}

// ========== ACTION: max_price ==========
// Tìm giá phòng cao nhất
if ($action === 'max_price') {
    try {
        $stmt1 = $db->query("SELECT MAX(gia) as max_price FROM phongtro WHERE gia > 0");
        $max1 = $stmt1->fetchColumn() ?? null;

        $stmt2 = $db->query("SELECT MAX(gia) as max_price FROM dangbai_chothuetro WHERE trangthai = 'da_duyet' AND gia > 0");
        $max2 = $stmt2->fetchColumn() ?? null;

        $max = max(array_filter([$max1, $max2])) ?? null;

        if ($max === null) {
            echo json_encode([
                'success' => true,
                'action' => 'max_price',
                'message' => "Hệ thống chưa có thông tin giá phòng."
            ]);
        } else {
            $maxFormatted = number_format($max);
            echo json_encode([
                'success' => true,
                'action' => 'max_price',
                'max_price' => $max,
                'message' => "Giá phòng *cao nhất* trong hệ thống là *{$maxFormatted}đ/tháng*."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi tìm giá cao nhất: ' . $e->getMessage()]);
    }
    exit;
}

// ========== ACTION: avg_price ==========
// Tìm giá phòng trung bình
if ($action === 'avg_price') {
    try {
        $stmt1 = $db->query("SELECT AVG(gia) as avg_price FROM phongtro WHERE gia > 0");
        $avg1 = $stmt1->fetchColumn() ?? null;

        $stmt2 = $db->query("SELECT AVG(gia) as avg_price FROM dangbai_chothuetro WHERE trangthai = 'da_duyet' AND gia > 0");
        $avg2 = $stmt2->fetchColumn() ?? null;

        // Tính trung bình của hai giá trị
        $count1 = $db->query("SELECT COUNT(*) as cnt FROM phongtro WHERE gia > 0")->fetchColumn();
        $count2 = $db->query("SELECT COUNT(*) as cnt FROM dangbai_chothuetro WHERE trangthai = 'da_duyet' AND gia > 0")->fetchColumn();

        if ($count1 + $count2 > 0) {
            $avg = (($avg1 * $count1) + ($avg2 * $count2)) / ($count1 + $count2);
            $avgFormatted = number_format(round($avg));
            echo json_encode([
                'success' => true,
                'action' => 'avg_price',
                'avg_price' => round($avg),
                'message' => "Giá phòng *trung bình* trong hệ thống là *{$avgFormatted}đ/tháng*."
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'action' => 'avg_price',
                'message' => "Hệ thống chưa có thông tin giá phòng."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi tính giá trung bình: ' . $e->getMessage()]);
    }
    exit;
}

// ========== ACTION: count_by_ward ==========
// Đếm phòng theo phường
if ($action === 'count_by_ward') {
    try {
        // Lấy danh sách phường từ phongtro
        $stmt1 = $db->query("
            SELECT 
                LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(diachi, ',', -2), ',', 1))) as ward,
                COUNT(*) as cnt
            FROM phongtro
            WHERE diachi IS NOT NULL AND diachi != ''
            GROUP BY ward
            ORDER BY cnt DESC
            LIMIT 5
        ");
        $wards = $stmt1->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $message = "Phòng trọ theo phường (top 5):\n";
        foreach ($wards as $w) {
            $message .= "- *" . trim($w['ward']) . "*: {$w['cnt']} phòng\n";
        }

        echo json_encode([
            'success' => true,
            'action' => 'count_by_ward',
            'wards' => $wards,
            'message' => $message
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi đếm theo phường: ' . $e->getMessage()]);
    }
    exit;
}

// ========== ACTION: get_room_list ==========
// Lấy danh sách phòng với các tùy chọn lọc
if ($action === 'get_room_list') {
    try {
        $filter_status = $payload['filter_status'] ?? null; // 'con_phong', 'da_coc', 'da_thue'
        $limit = min($payload['limit'] ?? 5, 10); // Tối đa 10 phòng
        $offset = $payload['offset'] ?? 0;
        $sort_by = $payload['sort_by'] ?? 'ngaydang'; // 'ngaydang', 'gia', 'dientich'

        // Xây dựng WHERE clause
        $whereClause = "WHERE 1=1";
        if ($filter_status) {
            $whereClause .= " AND COALESCE(trangthai_phong, 'con_phong') = '" . $db->quote($filter_status)[1] . "'";
        }

        // Xây dựng ORDER BY
        $orderBy = "ORDER BY ngaydang DESC";
        if ($sort_by === 'gia')
            $orderBy = "ORDER BY gia ASC";
        else if ($sort_by === 'dientich')
            $orderBy = "ORDER BY dientich DESC";

        // Query từ phongtro
        $sql = "
            SELECT id, ten_phong, gia, dientich, diachi, trangthai_phong as trangthai, 'phongtro' as nguon, ngaydang
            FROM phongtro
            $whereClause
            $orderBy
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $db->query($sql);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'success' => true,
            'action' => 'get_room_list',
            'rooms' => $rooms,
            'count' => count($rooms)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi lấy danh sách phòng: ' . $e->getMessage()]);
    }
    exit;
}

// Default: Action không tồn tại
http_response_code(400);
echo json_encode(['error' => 'Action không được hỗ trợ: ' . $action]);
exit;
?>