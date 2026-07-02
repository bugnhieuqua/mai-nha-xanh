<?php
/**
 * Admin Export API - Mái Nhà Xanh
 * Xuất dữ liệu hệ thống (UTF-16LE Tab-Delimited)
 * Đảm bảo: Tiếng Việt hiển thị chuẩn 100%, mỗi ô 1 cột, mở trực tiếp bằng Excel không cảnh báo
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireLogin('admin');

$database = new Database();
$db = $database->getConnection();

$type    = $_GET['type']    ?? $_POST['type']    ?? 'posts';
$status  = $_GET['status']  ?? $_POST['status']  ?? '';
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
 * Hàm hỗ trợ xuất file UTF-16LE
 */
function outputExcelCsv(string $filename, array $headers, array $rows): void {
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-16LE');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate');
    header('Pragma: public');

    $lines = [];
    
    // Header
    $lines[] = implode("\t", array_map('cleanCell', $headers));

    // Data rows
    foreach ($rows as $row) {
        $lines[] = implode("\t", array_map('cleanCell', $row));
    }

    $fullText = implode("\r\n", $lines);

    // BOM UTF-16LE
    echo "\xFF\xFE";
    
    // Convert UTF-8 sang UTF-16LE
    if (function_exists('mb_convert_encoding')) {
        echo mb_convert_encoding($fullText, 'UTF-16LE', 'UTF-8');
    } else {
        echo iconv('UTF-8', 'UTF-16LE//IGNORE', $fullText);
    }
    exit;
}

function cleanCell(mixed $val): string {
    $s = strip_tags((string)($val ?? ''));
    // Thay thế tab và newline để không làm vỡ ô
    $s = str_replace(["\t", "\r\n", "\r", "\n"], ' ', $s);
    return $s;
}

/* ─────────────────────────────────────────────────────── */
/* 1. Xuất người đăng bài                                 */
/* ─────────────────────────────────────────────────────── */
function exportPosters(PDO $db, string $dateStr): void {
    $filename = "danh_sach_nguoi_dang_bai_{$dateStr}.csv";

    $rows = [];
    try {
        $sql = "
            SELECT
                d.nguoidang           AS username,
                MAX(u.hoten)          AS hoten,
                MAX(u.email)          AS email,
                MAX(d.sdt_chunha)     AS sdt,
                COUNT(*)              AS total_posts,
                SUM(CASE WHEN d.trangthai = 'da_duyet' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN d.trangthai = 'cho_duyet' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN d.trangthai = 'tu_choi'  THEN 1 ELSE 0 END) AS rejected,
                AVG(d.gia)            AS avg_price,
                MAX(d.ngaydang)       AS latest_post
            FROM dangbai_chothuetro d
            LEFT JOIN users u ON d.nguoidang = u.username
            GROUP BY d.nguoidang
            ORDER BY total_posts DESC
        ";
        $stmt = $db->query($sql);
        $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $i = 1;
        foreach ($dbData as $r) {
            $rows[] = [
                $i++,
                $r['username']  ?? '',
                $r['hoten']     ?? 'Chưa cập nhật',
                $r['email']     ?? 'Chưa có',
                $r['sdt']       ?? 'Chưa có',
                (int)($r['total_posts'] ?? 0),
                (int)($r['approved']    ?? 0),
                (int)($r['pending']     ?? 0),
                (int)($r['rejected']    ?? 0),
                number_format((float)($r['avg_price'] ?? 0), 0, '.', ','),
                !empty($r['latest_post']) ? date('d/m/Y H:i', strtotime($r['latest_post'])) : '',
            ];
        }
    } catch (Exception $e) {
        $rows = [];
    }

    $headers = [
        'STT', 'Username', 'Họ và Tên', 'Email', 'Số Điện Thoại',
        'Tổng Bài Đăng', 'Đã Duyệt', 'Chờ Duyệt', 'Bị Từ Chối/Hủy',
        'Giá Trung Bình (VNĐ)', 'Bài Đăng Mới Nhất'
    ];

    outputExcelCsv($filename, $headers, $rows);
}

/* ─────────────────────────────────────────────────────── */
/* 2. Xuất bài đăng                                       */
/* ─────────────────────────────────────────────────────── */
function exportPosts(PDO $db, string $dateStr, string $status = '', string $keyword = ''): void {
    $statusMap = [
        'cho_duyet' => 'cho_duyet',
        'da_duyet'  => 'da_duyet',
        'tu_choi'   => 'tu_choi',
        'rejected'  => 'tu_choi',
        'pending'   => 'cho_duyet',
        'approved'  => 'da_duyet',
        'all'       => '',
    ];
    $cleanStatus = $statusMap[$status] ?? $status;

    $where  = '1=1';
    $params = [];

    if ($cleanStatus && in_array($cleanStatus, ['cho_duyet', 'da_duyet', 'tu_choi'])) {
        $where .= ' AND d.trangthai = :status';
        $params[':status'] = $cleanStatus;
        $label = match($cleanStatus) {
            'cho_duyet' => 'cho_duyet',
            'tu_choi'   => 'bi_tu_choi',
            default     => 'da_duyet',
        };
    } else {
        $label = 'tat_ca';
    }

    if ($keyword) {
        $where .= ' AND (d.tieude LIKE :kw OR d.diachi LIKE :kw2 OR d.nguoidang LIKE :kw3)';
        $kw = "%$keyword%";
        $params[':kw'] = $kw; $params[':kw2'] = $kw; $params[':kw3'] = $kw;
    }

    $filename = "danh_sach_bai_dang_{$label}_{$dateStr}.csv";

    $rows = [];
    try {
        $sql = "
            SELECT d.id, d.tieude, d.diachi, d.gia, d.dientich,
                   d.nguoidang, d.sdt_chunha, d.trangthai,
                   d.admin_note, d.ngaydang, d.duyet_luc
            FROM dangbai_chothuetro d
            WHERE {$where}
            ORDER BY d.ngaydang DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statusLabel = [
            'cho_duyet' => 'Chờ duyệt',
            'da_duyet'  => 'Đã duyệt',
            'tu_choi'   => 'Bị từ chối/Hủy',
        ];

        $i = 1;
        foreach ($dbData as $r) {
            $rows[] = [
                $i++,
                '#' . ($r['id'] ?? ''),
                $r['tieude']     ?? '',
                $statusLabel[$r['trangthai'] ?? ''] ?? ($r['trangthai'] ?? ''),
                $r['nguoidang']  ?? '',
                $r['sdt_chunha'] ?? '',
                number_format((float)($r['gia']      ?? 0), 0, '.', ','),
                !empty($r['dientich']) ? number_format((float)$r['dientich'], 1, '.', '') : '',
                $r['diachi']     ?? '',
                $r['admin_note'] ?? '',
                !empty($r['ngaydang'])  ? date('d/m/Y H:i', strtotime($r['ngaydang']))  : '',
                !empty($r['duyet_luc']) ? date('d/m/Y H:i', strtotime($r['duyet_luc'])) : '',
            ];
        }
    } catch (Exception $e) {
        $rows = [];
    }

    $headers = [
        'STT', 'ID Bài', 'Tiêu Đề', 'Trạng Thái', 'Người Đăng',
        'SĐT Chủ Nhà', 'Giá Cho Thuê (VNĐ)', 'Diện Tích (m²)',
        'Địa Chỉ', 'Lý Do Từ Chối / Ghi Chú', 'Ngày Đăng', 'Ngày Xử Lý'
    ];

    outputExcelCsv($filename, $headers, $rows);
}

/* ─────────────────────────────────────────────────────── */
/* 3. Xuất danh sách người dùng                           */
/* ─────────────────────────────────────────────────────── */
function exportUsers(PDO $db, string $dateStr): void {
    $filename = "danh_sach_nguoi_dung_{$dateStr}.csv";

    $rows = [];
    try {
        $sql = "
            SELECT u.id, u.username, u.hoten, u.email, u.role, u.status, u.created_at,
                   (SELECT COUNT(*) FROM dangbai_chothuetro WHERE nguoidang = u.username) AS post_count
            FROM users u
            WHERE u.role != 'admin' OR u.role IS NULL
            ORDER BY u.id DESC
        ";
        $stmt = $db->query($sql);
        $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $i = 1;
        foreach ($dbData as $r) {
            $role   = (($r['role']   ?? '') === 'admin') ? 'Admin' : 'Thành viên';
            $status = (($r['status'] ?? 'active') === 'active') ? 'Hoạt động' : 'Khóa';
            $rows[] = [
                $i++,
                $r['id']       ?? '',
                $r['username'] ?? '',
                $r['hoten']    ?? 'Chưa cập nhật',
                $r['email']    ?? 'Chưa có',
                $role,
                $status,
                (int)($r['post_count'] ?? 0),
                !empty($r['created_at']) ? date('d/m/Y H:i', strtotime($r['created_at'])) : '',
            ];
        }
    } catch (Exception $e) {
        $rows = [];
    }

    $headers = [
        'STT', 'ID', 'Username', 'Họ và Tên', 'Email',
        'Vai Trò', 'Trạng Thái', 'Số Bài Đăng', 'Ngày Tạo'
    ];

    outputExcelCsv($filename, $headers, $rows);
}
