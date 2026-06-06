<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']); exit;
}

// Bảo mật CSRF
validateCsrfToken();

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("UPDATE dat_phong SET is_read = 1 WHERE nguoidang = :u AND is_read = 0");
    $stmt->execute([':u' => $_SESSION['username']]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
