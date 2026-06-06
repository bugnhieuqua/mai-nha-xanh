<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../config/session.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bảo mật CSRF
    validateCsrfToken();

    // Lấy dữ liệu từ form
    $hoten = trim($_POST['hoten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sodienthoai = trim($_POST['sodienthoai'] ?? '');
    $tieude = trim($_POST['tieude'] ?? '');
    $noidung = trim($_POST['noidung'] ?? '');
    $session_id = trim($_POST['session_id'] ?? ''); // chatbot session
    
    // Validate dữ liệu
    $errors = [];
    
    if (empty($hoten)) {
        $errors[] = "Vui lòng nhập họ tên";
    }
    
    if (empty($email)) {
        $errors[] = "Vui lòng nhập email";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ";
    }
    
    if (empty($sodienthoai)) {
        $errors[] = "Vui lòng nhập số điện thoại";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $sodienthoai)) {
        $errors[] = "Số điện thoại không hợp lệ";
    }
    
    if (empty($tieude)) {
        $errors[] = "Vui lòng nhập tiêu đề";
    }
    
    if (empty($noidung)) {
        $errors[] = "Vui lòng nhập nội dung";
    }
    
    // Nếu có lỗi
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => implode(', ', $errors)
        ]);
        exit;
    }
    
    try {
        // Kết nối database
        $database = new Database();
        $db = $database->getConnection();
        
        $session_id = substr($session_id, 0, 64) ?: null;
        // Chuẩn bị câu query
        $query = "INSERT INTO lienhe (hoten, email, sodienthoai, tieude, noidung, session_id) 
                  VALUES (:hoten, :email, :sodienthoai, :tieude, :noidung, :session_id)";
        
        $stmt = $db->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':hoten', $hoten);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':sodienthoai', $sodienthoai);
        $stmt->bindParam(':tieude', $tieude);
        $stmt->bindParam(':noidung', $noidung);
        $stmt->bindParam(':session_id', $session_id);
        
        // Thực thi query
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Gửi tin nhắn thành công!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lưu dữ liệu'
            ]);
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi hệ thống: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức không hợp lệ'
    ]);
}
?>
