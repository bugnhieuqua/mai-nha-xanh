<?php
/**
 * File kiểm thử cấu hình APP_DEBUG hoạt động cụ thể.
 * Bạn hãy truy cập vào link sau trên trình duyệt để kiểm tra:
 * http://localhost/mai-nha-xanh/test_debug.php
 */

require_once 'config/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$appDebug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false';
$isDebugOn = filter_var($appDebug, FILTER_VALIDATE_BOOLEAN);

echo "<h1>Kiểm thử cấu hình APP_DEBUG</h1>";
echo "<p>Trạng thái hiện tại trong file <b>.env</b>: APP_DEBUG = <strong>" . ($isDebugOn ? "true (BẬT)" : "false (TẮT)") . "</strong></p>";

// 1. Kiểm thử kết nối cURL tới Google API (Gemini) để test cấu hình SSL
echo "<h2>1. Thử nghiệm kết nối cURL tới Google API (Gemini SSL)</h2>";
$ch = curl_init("https://generativelanguage.googleapis.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

if ($isDebugOn) {
    echo "<p style='color:green;'>[Debug Mode ON] Đang bỏ qua xác thực SSL (CURLOPT_SSL_VERIFYPEER = false)...</p>";
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
} else {
    echo "<p style='color:orange;'>[Debug Mode OFF] Đang xác thực SSL nghiêm ngặt (CURLOPT_SSL_VERIFYPEER = true)...</p>";
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
// curl_close($ch);

if ($error) {
    echo "<p style='color:red;'><b>Thất bại:</b> Lỗi cURL khi kết nối Google: " . htmlspecialchars($error) . "</p>";
} else {
    echo "<p style='color:green;'><b>Thành công!</b> Đã kết nối được tới Google API (HTTP Code: $httpCode)</p>";
}

// 1.2 Kiểm thử kết nối cURL tới Groq API (Chatbot)
echo "<h2>1.2 Thử nghiệm kết nối cURL tới Groq API (Chatbot)</h2>";
$groqKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?? '';
if (empty($groqKey)) {
    echo "<p style='color:red; font-weight:bold;'>Cảnh báo: Bạn chưa điền biến GROQ_API_KEY trong file .env!</p>";
    echo "<p><i>(Đây là lý do tại sao Chatbot báo lỗi 'GROQ_API_KEY chưa được cấu hình' khi bạn hỏi đáp)</i></p>";
} else {
    echo "<p style='color:green;'>Đã tìm thấy cấu hình GROQ_API_KEY: <code>" . substr($groqKey, 0, 6) . "..." . substr($groqKey, -4) . "</code></p>";
}

$ch2 = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 5);

if ($isDebugOn) {
    echo "<p style='color:green;'>[Debug Mode ON] Đang bỏ qua xác thực SSL cho Groq...</p>";
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
} else {
    echo "<p style='color:orange;'>[Debug Mode OFF] Đang xác thực SSL nghiêm ngặt cho Groq...</p>";
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, true);
}

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$error2 = curl_error($ch2);
// curl_close($ch2);

if ($error2) {
    echo "<p style='color:red;'><b>Thất bại:</b> Lỗi cURL khi kết nối Groq: " . htmlspecialchars($error2) . "</p>";
} else {
    echo "<p style='color:green;'><b>Thành công!</b> Đã kết nối được tới Groq API (HTTP Code: $httpCode2)</p>";
}

// 2. Thử nghiệm cơ chế thông báo lỗi
echo "<h2>2. Thử nghiệm hiển thị thông báo lỗi PHP thực tế</h2>";
echo "<p>Nhấn vào các liên kết dưới đây để mô phỏng các loại lỗi thực tế:</p>";
echo "<ul>";
echo "<li><a href='test_debug.php?trigger=notice'>1. Lỗi Cảnh báo nhẹ (Notice/Warning)</a> - <i>Trang vẫn tiếp tục chạy tiếp</i></li>";
echo "<li><a href='test_debug.php?trigger=exception'>2. Lỗi Ngoại lệ (Exception - Ví dụ lỗi CSDL)</a> - <i>Script bị ngắt ngang</i></li>";
echo "<li><a href='test_debug.php?trigger=fatal'>3. Lỗi Nghiêm trọng (Fatal Error)</a> - <i>Dừng script ngay lập tức</i></li>";
echo "</ul>";

if (isset($_GET['trigger'])) {
    $type = $_GET['trigger'];
    echo "<hr>";
    
    if ($type === 'notice') {
        echo "<p><b>Đang chạy lệnh lỗi Notice:</b> Sử dụng biến chưa định nghĩa <code>echo \$bien_chua_dinh_nghia;</code></p>";
        // Kích hoạt lỗi Notice
        echo $bien_chua_dinh_nghia;
        echo "<p style='color:green;'><b>Kết quả:</b> Dòng lệnh này vẫn chạy được tiếp sau lỗi cảnh báo!</p>";
        
    } elseif ($type === 'exception') {
        echo "<p><b>Đang chạy lệnh ném Exception:</b> <code>throw new Exception('Lỗi kết nối CSDL: Bảng \"users\" không tồn tại!');</code></p>";
        // Kích hoạt Exception
        throw new Exception("Lỗi kết nối CSDL: Bảng 'users' không tồn tại!");
        
    } elseif ($type === 'fatal') {
        echo "<p><b>Đang chạy lệnh lỗi Fatal:</b> Gọi hàm không tồn tại <code>ham_khong_ton_tai();</code></p>";
        // Kích hoạt Fatal Error
        // ham_khong_ton_tai();
    }
}
?>
