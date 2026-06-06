<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Ensure admin only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // ── POST: delete one or many contacts ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCsrfToken();
        $action = trim($_POST['action'] ?? '');
        if ($action === 'delete') {
            // When FormData uses name 'ids[]', PHP exposes it as $_POST['ids'] (an array).
            $raw = $_POST['ids'] ?? [];
            if (!is_array($raw) || empty($raw)) {
                echo json_encode(['success' => false, 'message' => 'Không có ID nào được chọn']);
                exit;
            }
            $ids = array_map('intval', $raw);
            $ids = array_filter($ids, fn($id) => $id > 0);
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
                exit;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("DELETE FROM lienhe WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => 'Đã xoá ' . $stmt->rowCount() . ' liên hệ']);
            exit;
        }
        if ($action === 'mark_seen') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE lienhe SET admin_seen = 1 WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
            }
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        exit;
    }

    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = 15;
    $offset = ($page - 1) * $limit;

    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

    $where = [];
    $params = [];

    if ($keyword !== '') {
        $where[] = "(hoten LIKE :kw OR email LIKE :kw OR sodienthoai LIKE :kw OR tieude LIKE :kw OR noidung LIKE :kw)";
        $params[':kw'] = "%$keyword%";
    }

    // Check if created_at exists in DB (try to use it, else ignore date filters)
    // We'll build the condition assuming created_at exists. If not, it might throw an error.
    // Assuming standard table: id, hoten, email, sodienthoai, tieude, noidung, created_at
    if ($date_from !== '') {
        $where[] = "DATE(created_at) >= :df";
        $params[':df'] = $date_from;
    }
    if ($date_to !== '') {
        $where[] = "DATE(created_at) <= :dt";
        $params[':dt'] = $date_to;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Count Total
    $countSql = "SELECT COUNT(*) FROM lienhe $whereClause";
    $stmt = $db->prepare($countSql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $total = $stmt->fetchColumn();

    // Fetch Data
    // we use id DESC as fallback if created_at is missing, but assume created_at is default.
    $sql = "SELECT * FROM lienhe $whereClause ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => (int) $total,
        'page' => $page,
        'pages' => ceil($total / $limit),
        'data' => $data
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi CSDL. (Chú ý: Bảng lienhe có thể thiếu cột created_at). ' . $e->getMessage()
    ]);
}
?>