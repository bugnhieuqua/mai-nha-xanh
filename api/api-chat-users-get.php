<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

$currentUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);

if ($currentUserId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Người dùng không hợp lệ. Vui lòng đăng nhập.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    // Truy vấn lấy danh sách những người dùng khác ngoại trừ bản thân
    // Lấy thông tin ID, Username, Họ tên (hoten), Ảnh đại diện, Trạng thái online, và mốc hoạt động cuối
    $sql = "SELECT id, username, hoten, avatar, is_online, last_active FROM users WHERE id != :current_id ORDER BY is_online DESC, hoten ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':current_id' => $currentUserId]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hóa tên và avatar
    foreach ($contacts as &$contact) {
        if (empty($contact['hoten'])) {
            $contact['hoten'] = $contact['username'];
        }
        if (empty($contact['avatar'])) {
            $contact['avatar'] = 'default_avatar.png'; // Avatar mặc định
        }
    }

    echo json_encode(['status' => 'success', 'contacts' => $contacts]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $e->getMessage()]);
}
?>
