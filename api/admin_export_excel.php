<?php
/**
 * Admin Excel Export API - Mái Nhà Xanh
 * Xuất dữ liệu hệ thống sang Excel (.xls / UTF-8 BOM) dành riêng cho Admin
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Chỉ Admin mới được phép xuất dữ liệu
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? $_POST['type'] ?? 'posts';
$status = $_GET['status'] ?? $_POST['status'] ?? '';
$keyword = $_GET['keyword'] ?? $_POST['keyword'] ?? '';

$dateStr = date('Ymd_His');

switch ($type) {
    case 'posters':
        exportPosters($db, $dateStr);
        break;

    case 'users':
        exportUsers($db, $dateStr);
        break;

    case 'search':
        exportPosts($db, $dateStr, '', $keyword);
        break;

    case 'posts':
    default:
        exportPosts($db, $dateStr, $status, $keyword);
        break;
}

/**
 * Xuất danh sách người đăng bài
 */
function exportPosters(PDO $db, string $dateStr) {
    $filename = "danh_sach_nguoi_dang_bai_{$dateStr}.xls";

    $sql = "
        SELECT 
            d.nguoidang as username,
            u.hoten,
            u.email,
            u.sdt,
            COUNT(*) as total_posts,
            SUM(CASE WHEN d.trangthai = 'da_duyet' THEN 1 ELSE 0 END) as approved_posts,
            SUM(CASE WHEN d.trangthai = 'cho_duyet' THEN 1 ELSE 0 END) as pending_posts,
            SUM(CASE WHEN d.trangthai = 'tu_choi' THEN 1 ELSE 0 END) as rejected_posts,
            AVG(d.gia) as avg_price,
            MAX(d.ngaydang) as latest_post
        FROM dangbai_chothuetro d
        LEFT JOIN users u ON d.nguoidang = u.username
        GROUP BY d.nguoidang
        ORDER BY total_posts DESC
    ";
    
    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendExcelHeaders($filename);

    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta charset='utf-8'></head><body>";
    echo "<h2>BÁO CÁO TỔNG HỢP NGƯỜI ĐĂNG BÀI - MÁI NHÀ XANH</h2>";
    echo "<p>Thời gian xuất: " . date('d/m/Y H:i:s') . " | Tổng số người đăng: " . count($rows) . "</p>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; font-family:Arial, sans-serif; font-size:13px;'>";
    echo "<thead><tr style='background-color:#107c41; color:#ffffff; font-weight:bold;'>";
    echo "<th>STT</th>";
    echo "<th>Username</th>";
    echo "<th>Họ và Tên</th>";
    echo "<th>Email</th>";
    echo "<th>Số Điện Thoại</th>";
    echo "<th>Tổng Bài Đăng</th>";
    echo "<th>Đã Duyệt</th>";
    echo "<th>Chờ Duyệt</th>";
    echo "<th>Bị Từ Chối / Hủy</th>";
    echo "<th>Giá TB (VNĐ)</th>";
    echo "<th>Bài Đăng Mới Nhất</th>";
    echo "</tr></thead><tbody>";

    $stt = 1;
    foreach ($rows as $r) {
        $bg = ($stt % 2 == 0) ? "#f8fafc" : "#ffffff";
        echo "<tr style='background-color: {$bg};'>";
        echo "<td align='center'>{$stt}</td>";
        echo "<td>" . htmlspecialchars($r['username'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($r['hoten'] ?? 'Chưa cập nhật') . "</td>";
        echo "<td>" . htmlspecialchars($r['email'] ?? 'Chưa có') . "</td>";
        echo "<td>" . htmlspecialchars($r['sdt'] ?? 'Chưa có') . "</td>";
        echo "<td align='center'><b>" . number_format($r['total_posts']) . "</b></td>";
        echo "<td align='center' style='color:#10b981;'>" . number_format($r['approved_posts']) . "</td>";
        echo "<td align='center' style='color:#f59e0b;'>" . number_format($r['pending_posts']) . "</td>";
        echo "<td align='center' style='color:#ef4444;'>" . number_format($r['rejected_posts']) . "</td>";
        echo "<td align='right'>" . number_format($r['avg_price'] ?? 0) . " ₫</td>";
        echo "<td align='center'>" . ($r['latest_post'] ? date('d/m/Y H:i', strtotime($r['latest_post'])) : '—') . "</td>";
        echo "</tr>";
        $stt++;
    }

    echo "</tbody></table></body></html>";
    exit;
}

/**
 * Xuất danh sách bài đăng (Chờ duyệt, Từ chối/Hủy, Đã duyệt, hoặc Tất cả)
 */
function exportPosts(PDO $db, string $dateStr, string $status = '', string $keyword = '') {
    $statusMap = [
        'cho_duyet' => 'cho_duyet',
        'da_duyet'  => 'da_duyet',
        'tu_choi'   => 'tu_choi',
        'rejected'  => 'tu_choi',
        'pending'   => 'cho_duyet',
        'approved'  => 'da_duyet',
    ];

    $cleanStatus = $statusMap[$status] ?? $status;
    
    $where = "1=1";
    $params = [];

    if ($cleanStatus && in_array($cleanStatus, ['cho_duyet', 'da_duyet', 'tu_choi'])) {
        $where .= " AND d.trangthai = :status";
        $params[':status'] = $cleanStatus;
        $statusLabel = ($cleanStatus === 'cho_duyet') ? "cho_duyet" : (($cleanStatus === 'tu_choi') ? "bi_tu_choi" : "da_duyet");
    } else {
        $statusLabel = "tat_ca";
    }

    if ($keyword) {
        $where .= " AND (d.tieude LIKE :kw OR d.diachi LIKE :kw2 OR d.nguoidang LIKE :kw3 OR d.mota LIKE :kw4)";
        $kw = "%$keyword%";
        $params[':kw'] = $kw;
        $params[':kw2'] = $kw;
        $params[':kw3'] = $kw;
        $params[':kw4'] = $kw;
    }

    $filename = "danh_sach_bai_dang_{$statusLabel}_{$dateStr}.xls";

    $sql = "
        SELECT 
            d.id,
            d.tieude,
            d.diachi,
            d.gia,
            d.dientich,
            d.nguoidang,
            d.sdt_chunha,
            d.trangthai,
            d.admin_note,
            d.ngaydang,
            d.duyet_luc
        FROM dangbai_chothuetro d
        WHERE {$where}
        ORDER BY d.ngaydang DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendExcelHeaders($filename);

    $titleMap = [
        'cho_duyet' => 'BÀI ĐĂNG CHỜ DUYỆT',
        'tu_choi'   => 'BÀI ĐĂNG BỊ TỪ CHỐI / HỦY',
        'da_duyet'  => 'BÀI ĐĂNG ĐÃ DUYỆT',
        'tat_ca'    => 'TẤT CẢ BÀI ĐĂNG THUÊ PHÒNG'
    ];
    $reportTitle = $titleMap[$statusLabel] ?? 'DANH SÁCH BÀI ĐĂNG';

    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta charset='utf-8'></head><body>";
    echo "<h2>DANH SÁCH {$reportTitle} - MÁI NHÀ XANH</h2>";
    echo "<p>Thời gian xuất: " . date('d/m/Y H:i:s') . " | Tổng số bài: " . count($rows) . ($keyword ? " | Từ khóa: \"{$keyword}\"" : "") . "</p>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; font-family:Arial, sans-serif; font-size:13px;'>";
    echo "<thead><tr style='background-color:#107c41; color:#ffffff; font-weight:bold;'>";
    echo "<th>STT</th>";
    echo "<th>ID Bài</th>";
    echo "<th>Tiêu Đề Bài Đăng</th>";
    echo "<th>Trạng Thái</th>";
    echo "<th>Người Đăng</th>";
    echo "<th>SĐT Chủ Nhà</th>";
    echo "<th>Giá Cho Thuê (VNĐ)</th>";
    echo "<th>Diện Tích (m²)</th>";
    echo "<th>Địa Chỉ Phòng Trọ</th>";
    echo "<th>Lý Do Từ Chối / Ghi Chú Admin</th>";
    echo "<th>Ngày Đăng</th>";
    echo "<th>Ngày Xử Lý</th>";
    echo "</tr></thead><tbody>";

    $stt = 1;
    foreach ($rows as $r) {
        $bg = ($stt % 2 == 0) ? "#f8fafc" : "#ffffff";
        
        $sttBadge = match($r['trangthai']) {
            'cho_duyet' => "<span style='color:#d97706; font-weight:bold;'>Chờ duyệt</span>",
            'da_duyet'  => "<span style='color:#059669; font-weight:bold;'>Đã duyệt</span>",
            'tu_choi'   => "<span style='color:#dc2626; font-weight:bold;'>Bị từ chối / Hủy</span>",
            default     => htmlspecialchars($r['trangthai'])
        };

        echo "<tr style='background-color: {$bg};'>";
        echo "<td align='center'>{$stt}</td>";
        echo "<td align='center'><b>#{$r['id']}</b></td>";
        echo "<td>" . htmlspecialchars($r['tieude']) . "</td>";
        echo "<td align='center'>{$sttBadge}</td>";
        echo "<td>" . htmlspecialchars($r['nguoidang']) . "</td>";
        echo "<td>" . htmlspecialchars($r['sdt_chunha'] ?: '—') . "</td>";
        echo "<td align='right'>" . number_format($r['gia']) . " ₫</td>";
        echo "<td align='center'>" . ($r['dientich'] ? number_format($r['dientich'], 1) . " m²" : '—') . "</td>";
        echo "<td>" . htmlspecialchars($r['diachi']) . "</td>";
        echo "<td style='color:#dc2626;'>" . htmlspecialchars($r['admin_note'] ?: '—') . "</td>";
        echo "<td align='center'>" . ($r['ngaydang'] ? date('d/m/Y H:i', strtotime($r['ngaydang'])) : '—') . "</td>";
        echo "<td align='center'>" . ($r['duyet_luc'] ? date('d/m/Y H:i', strtotime($r['duyet_luc'])) : '—') . "</td>";
        echo "</tr>";
        $stt++;
    }

    echo "</tbody></table></body></html>";
    exit;
}

/**
 * Xuất danh sách tài khoản người dùng
 */
function exportUsers(PDO $db, string $dateStr) {
    $filename = "danh_sach_nguoi_dung_{$dateStr}.xls";

    $sql = "
        SELECT 
            u.id,
            u.username,
            u.hoten,
            u.email,
            u.role,
            u.status,
            u.created_at,
            (SELECT COUNT(*) FROM dangbai_chothuetro WHERE nguoidang = u.username) as post_count
        FROM users u
        ORDER BY u.id DESC
    ";

    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendExcelHeaders($filename);

    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta charset='utf-8'></head><body>";
    echo "<h2>DANH SÁCH TÀI KHOẢN NGƯỜI DÙNG - MÁI NHÀ XANH</h2>";
    echo "<p>Thời gian xuất: " . date('d/m/Y H:i:s') . " | Tổng số người dùng: " . count($rows) . "</p>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; font-family:Arial, sans-serif; font-size:13px;'>";
    echo "<thead><tr style='background-color:#107c41; color:#ffffff; font-weight:bold;'>";
    echo "<th>STT</th>";
    echo "<th>ID User</th>";
    echo "<th>Username</th>";
    echo "<th>Họ và Tên</th>";
    echo "<th>Email</th>";
    echo "<th>Vai Trò</th>";
    echo "<th>Trạng Thái</th>";
    echo "<th>Số Bài Đăng</th>";
    echo "<th>Ngày Tạo</th>";
    echo "</tr></thead><tbody>";

    $stt = 1;
    foreach ($rows as $r) {
        $bg = ($stt % 2 == 0) ? "#f8fafc" : "#ffffff";
        $roleLabel = ($r['role'] === 'admin') ? "<b style='color:#7c3aed;'>Admin</b>" : "Thành viên";
        $statusLabel = ($r['status'] === 'active' || $r['status'] === '1' || empty($r['status'])) ? "<span style='color:#059669;'>Hoạt động</span>" : "<span style='color:#dc2626;'>Khóa</span>";

        echo "<tr style='background-color: {$bg};'>";
        echo "<td align='center'>{$stt}</td>";
        echo "<td align='center'>#{$r['id']}</td>";
        echo "<td>" . htmlspecialchars($r['username']) . "</td>";
        echo "<td>" . htmlspecialchars($r['hoten'] ?: 'Chưa cập nhật') . "</td>";
        echo "<td>" . htmlspecialchars($r['email'] ?: 'Chưa có') . "</td>";
        echo "<td align='center'>{$roleLabel}</td>";
        echo "<td align='center'>{$statusLabel}</td>";
        echo "<td align='center'><b>" . number_format($r['post_count']) . "</b></td>";
        echo "<td align='center'>" . ($r['created_at'] ? date('d/m/Y H:i', strtotime($r['created_at'])) : '—') . "</td>";
        echo "</tr>";
        $stt++;
    }

    echo "</tbody></table></body></html>";
    exit;
}

/**
 * Gửi HTTP headers để trình duyệt tải file Excel .xls với UTF-8 BOM
 */
function sendExcelHeaders(string $filename) {
    // Clear buffer
    if (ob_get_level()) ob_end_clean();

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Gửi UTF-8 BOM để Excel mở tiếng Việt không lỗi font
    echo "\xEF\xBB\xBF";
}
