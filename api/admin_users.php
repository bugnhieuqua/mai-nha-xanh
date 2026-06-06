<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

requireLogin('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        try { $db->exec("UPDATE users SET admin_seen = 1 WHERE admin_seen = 0"); } catch (Exception $e) {}
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        
        $where = [];
        $params = [];

        // Exclude admin from being banned (optional, but good practice)
        $where[] = "id != :my_id";
        $params[':my_id'] = $_SESSION['user_id'];

        if ($keyword !== '') {
            $where[] = "(username LIKE :kw OR email LIKE :kw)";
            $params[':kw'] = "%$keyword%";
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        $countSql = "SELECT COUNT(*) FROM users $whereClause";
        $stmt = $db->prepare($countSql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        $total = $stmt->fetchColumn();

        $sql = "SELECT id, username, email, created_at, status FROM users $whereClause ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total' => (int)$total,
            'page' => $page,
            'pages' => ceil($total / $limit),
            'data' => $data
        ]);
        
    } elseif ($action === 'toggle_ban') {
        $user_id = (int)$_POST['user_id'];
        $new_status = $_POST['status'] === 'banned' ? 'banned' : 'active';
        
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Không thể khoá chính mình']);
            exit;
        }

        $stmt = $db->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $new_status, ':id' => $user_id]);
        
        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]);
}
?>
