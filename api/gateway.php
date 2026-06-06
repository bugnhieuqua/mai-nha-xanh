<?php
/**
 * Mái Nhà Xanh - Unified API Gateway
 * Gom nhóm các API rời rạc thành một hệ thống quản lý tập trung.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';

// Bảo vệ CSRF cho toàn bộ Gateway
validateCsrfToken();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    switch ($action) {
        // --- NHÓM BÀI ĐĂNG (POSTS) ---
        case 'delete_post':
            require_once 'xoa_bai.php'; // Tạm thời dùng lại file cũ để đảm bảo ổn định
            break;

        case 'update_post':
            require_once 'sua_bai.php';
            break;

        case 'update_post_status':
            require_once 'toggle_room_status.php';
            break;

        // --- NHÓM ĐẶT PHÒNG (BOOKINGS) ---
        case 'create_booking':
            require_once 'dat_phong.php';
            break;

        case 'update_booking_status':
            require_once 'update_booking_status.php';
            break;

        case 'delete_booking':
            require_once 'delete_booking.php';
            break;

        // --- NHÓM ADMIN (Sẽ mở rộng dần) ---
        case 'admin_get_notifs':
            requireLogin('admin');
            require_once 'admin_get_notifications.php';
            break;

        default:
            echo json_encode(['success' => false, 'message' => "Action '$action' not found in Gateway"]);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gateway Error: ' . $e->getMessage()]);
}
