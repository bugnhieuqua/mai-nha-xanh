<?php
/**
 * AI Content Moderation Helper
 * Gửi thông tin và ảnh của bài đăng đến Gemini 2.5 Flash để tự động kiểm duyệt nội dung.
 */

require_once __DIR__ . '/../config/env_loader.php';

function analyzePostWithAI(PDO $db, int $postId): array
{
    try {
        // Fetch the post details
        $stmt = $db->prepare("SELECT * FROM dangbai_chothuetro WHERE id = :id");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            return [
                'success' => false,
                'message' => 'Không tìm thấy bài đăng'
            ];
        }

        $apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
        if (empty($apiKey) || $apiKey === 'AIzaSyYourKeyHere') {
            return [
                'success' => false,
                'message' => 'GEMINI_API_KEY chưa được cấu hình trong .env'
            ];
        }

        $title = $post['tieude'] ?? '';
        $description = $post['mota'] ?? '';
        $price = $post['gia'] ?? 0;
        $area = $post['dientich'] ?? 0;
        $address = $post['diachi'] ?? '';
        $amenities = $post['tiennghi'] ?? '';
        $host_name = $post['ten_chunha'] ?? '';
        $host_phone = $post['sdt_chunha'] ?? '';
        $imagePath = $post['hinhanh'] ?? '';

        // Check if the image path exists and load it
        $imageData = null;
        $imageType = null;
        if (!empty($imagePath)) {
            $fullPath = __DIR__ . '/../' . $imagePath;
            if (file_exists($fullPath)) {
                $imageData = base64_encode(file_get_contents($fullPath));
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $imageType = finfo_file($finfo, $fullPath);
                    finfo_close($finfo);
                } elseif (function_exists('mime_content_type')) {
                    $imageType = mime_content_type($fullPath);
                } else {
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    $imageType = match ($ext) {
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        default => 'image/jpeg'
                    };
                }
            }
        }

        $prompt = "Bạn là chuyên gia kiểm duyệt nội dung của ứng dụng Mái Nhà Xanh (nền tảng tìm kiếm phòng trọ tại TP. Vinh và toàn tỉnh Nghệ An).
Hãy phân tích bài đăng phòng trọ và hình ảnh đi kèm để đánh giá mức độ an toàn, phát hiện các rủi ro vi phạm quy chế hoặc nội dung cấm.

Thông tin bài đăng:
- Tiêu đề: {$title}
- Mô tả: {$description}
- Giá thuê: " . number_format($price) . " VNĐ/tháng
- Diện tích: {$area} m2
- Địa chỉ: {$address}
- Tiện nghi: {$amenities}
- Chủ nhà: {$host_name} ({$host_phone})

Yêu cầu đánh giá an toàn cực kỳ chi tiết và tuân thủ chặt chẽ các giới hạn sau đây:

1. ĐỊNH NGHĨA PHÁN QUYẾT (VERDICT RULES):
   - 'SAFE': Bài viết có nội dung phòng trọ bình thường hoặc dữ liệu kiểm thử hợp lệ. Hình ảnh hiển thị phòng trọ, nhà vệ sinh, hành lang, ngõ hẻm, hoặc ảnh minh họa/ảnh mẫu/ảnh stock phòng trọ chất lượng cao. Mô tả viết bằng tiếng Việt thông thường. Địa chỉ nằm trong tỉnh Nghệ An (bao gồm TP. Vinh, thị xã Hoàng Mai, Cửa Lò, Thái Hòa, Diễn Châu, Quỳnh Lưu, Hưng Nguyên...) và mức giá thuê dưới 50.000.000 VNĐ/tháng ĐỀU BẮT BUỘC PHẢI ĐƯỢC ĐÁNH GIÁ LÀ 'SAFE' để hệ thống tự động duyệt và đăng bài lên công khai ngay lập tức, không cần Admin duyệt thủ công.
   - 'WARNING': Chỉ chọn WARNING khi bài đăng có mâu thuẫn địa lý cực kỳ lớn ngoài tỉnh Nghệ An (ví dụ: ghi địa chỉ ở Hà Nội, TP. Hồ Chí Minh, Đà Nẵng, Hải Phòng...) hoặc mức giá cực kỳ bất thường (trên 50.000.000 VNĐ/tháng mà không có giải thích hợp lý). Các bài viết WARNING sẽ cần Admin duyệt thủ công.
   - 'DANGER': Chỉ chọn DANGER khi và chỉ khi có bằng chứng vi phạm nghiêm trọng và không thể chối cãi. Các vi phạm nghiêm trọng bao gồm:
     a) Hình ảnh nhạy cảm/khiêu dâm/khỏa thân/bạo lực.
     b) Nội dung cờ bạc, game bài, cá độ, số đề, ma túy, vũ khí.
     c) Quảng cáo spam không liên quan đến bất động sản (như đa cấp, spa, mỹ phẩm, tiền ảo, việc làm online).
     d) Ngôn từ thù hận, thô tục, chửi bới.
     e) Mô tả ghi rõ yêu cầu người thuê phải chuyển khoản đặt cọc tiền trước khi được đi xem nhà.

2. CÁC QUY TẮC BẮT BUỘC ĐỂ TRÁNH LỖI DUYỆT OAN (CRITICAL RULES):
   - BẤT KỲ ĐỊA CHỈ NÀO nằm trong tỉnh Nghệ An (kể cả ngoài TP. Vinh như Hoàng Mai, Cửa Lò, Thái Hòa, Diễn Châu...) với mức giá dưới 50.000.000 VNĐ/tháng bắt buộc phải được phân loại là 'SAFE'.
   - TUYỆT ĐỐI KHÔNG ĐƯỢC chọn WARNING hoặc DANGER đối với sự mâu thuẫn địa điểm/địa chỉ trong cùng tỉnh Nghệ An (Ví dụ: Tiêu đề ghi 'tại TP. Vinh' nhưng địa chỉ chi tiết ghi 'Hoàng Mai, Nghệ An' hoặc ngược lại thì VẪN PHẢI xếp vào 'SAFE' vì cả hai đều thuộc địa bàn tỉnh Nghệ An).
   - TUYỆT ĐỐI KHÔNG ĐƯỢC chọn WARNING hoặc DANGER đối với các bài đăng chỉ vì chúng có giá thuê bất thường nhưng vẫn dưới 50.000.000 VNĐ/tháng (ví dụ giá 15.000.000 VNĐ/tháng cho biệt thự/nhà nguyên căn diện tích lớn là hoàn toàn hợp lý và phải xếp vào 'SAFE').
   - TUYỆT ĐỐI KHÔNG ĐƯỢC đánh giá 'DANGER' đối với các bài đăng chỉ vì mô tả sơ sài hoặc có hình ảnh mẫu/ảnh stock chất lượng cao từ internet. Hãy xếp vào 'SAFE'.
   - Chấp nhận tất cả dữ liệu giả lập/kiểm thử của nhà phát triển. Các tên chủ nhà như 'Nguyễn Văn A', 'Cao Thị Quỳnh Như', hoặc số điện thoại bất kỳ (kể cả số định dạng không chuẩn như '06668854445', '0912345678') và các địa danh kiểm thử nội bộ như 'Vinh Phú', 'Yên Toàn' BẮT BUỘC phải được đánh giá là 'SAFE'.

3. ĐIỂM RỦI RO (RISK SCORE):
   - SAFE: điểm rủi ro dưới 30.
   - WARNING: điểm rủi ro từ 30 đến 70.
   - DANGER: điểm rủi ro từ 71 đến 100.

Hãy trả về kết quả theo cấu trúc JSON sau:
{
  \"risk_score\": [số nguyên từ 0 đến 100],
  \"verdict\": \"[SAFE / WARNING / DANGER]\",
  \"reasons\": [
     \"[Lý do cụ thể 1]\",
     \"[Lý do cụ thể 2]\"
  ],
  \"details\": \"[Tóm tắt nhận xét chi tiết của AI bằng tiếng Việt]\"
}

Chỉ trả về chuỗi JSON hợp lệ, không bao gồm các ký tự markdown như ```json hay ```.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

        $parts = [
            ["text" => $prompt]
        ];

        if ($imageData && $imageType) {
            $parts[] = [
                "inlineData" => [
                    "mimeType" => $imageType,
                    "data" => $imageData
                ]
            ];
        }

        $payload = [
            "contents" => [
                [
                    "parts" => $parts
                ]
            ],
            "generationConfig" => [
                "responseMimeType" => "application/json"
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Bỏ qua xác thực SSL nếu đang ở môi trường Debug phát triển cục bộ
        $appDebug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false';
        if ($appDebug === 'true') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);


        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Lỗi kết nối API Gemini: ' . $curlError
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            $result = json_decode($response, true);
            $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            $jsonResponse = json_decode(trim($textResponse), true);
            if ($jsonResponse) {
                // Save analysis to the database
                $updateStmt = $db->prepare("UPDATE dangbai_chothuetro SET ai_check = :ai_check WHERE id = :id");
                $updateStmt->execute([
                    ':ai_check' => json_encode($jsonResponse, JSON_UNESCAPED_UNICODE),
                    ':id' => $postId
                ]);

                return [
                    'success' => true,
                    'data' => $jsonResponse
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'AI trả về dữ liệu không đúng định dạng JSON.',
                    'raw_text' => $textResponse
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Lỗi từ máy chủ Gemini API (HTTP ' . $httpCode . ')',
                'details' => json_decode($response, true) ?? $response
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Lỗi hệ thống: ' . $e->getMessage()
        ];
    }
}
