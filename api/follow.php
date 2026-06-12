<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

// Kiểm tra đăng nhập
if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    exit;
}

$followerId = intval($_SESSION['user_id']);

// Nhận dữ liệu
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$followingId = isset($data['following_id']) ? intval($data['following_id']) : 0;
$action      = isset($data['action']) ? trim($data['action']) : 'toggle'; // toggle, follow, unfollow

if ($followingId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Người được theo dõi không hợp lệ.']);
    exit;
}

if ($followerId === $followingId) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không thể theo dõi chính mình.']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new Exception("Không thể kết nối cơ sở dữ liệu.");
    }

    // Kiểm tra xem đã follow chưa
    $checkSql = "SELECT id FROM follows WHERE follower_id = :follower_id AND following_id = :following_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':follower_id' => $followerId,
        ':following_id' => $followingId
    ]);
    $followRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

    $isFollowing = false;

    if ($action === 'follow' || ($action === 'toggle' && !$followRecord)) {
        // Thực hiện follow
        if (!$followRecord) {
            $insertSql = "INSERT INTO follows (follower_id, following_id) VALUES (:follower_id, :following_id)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':follower_id' => $followerId,
                ':following_id' => $followingId
            ]);
        }
        $isFollowing = true;
        $message = "Đã theo dõi đối tác.";
    } else {
        // Thực hiện unfollow
        if ($followRecord) {
            $deleteSql = "DELETE FROM follows WHERE follower_id = :follower_id AND following_id = :following_id";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute([
                ':follower_id' => $followerId,
                ':following_id' => $followingId
            ]);
        }
        $isFollowing = false;
        $message = "Đã bỏ theo dõi đối tác.";
    }

    // Lấy số lượng follower mới của người này
    $countSql = "SELECT COUNT(*) FROM follows WHERE following_id = :following_id";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':following_id' => $followingId]);
    $followerCount = intval($countStmt->fetchColumn());

    echo json_encode([
        'status' => 'success',
        'is_following' => $isFollowing,
        'follower_count' => $followerCount,
        'message' => $message
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
