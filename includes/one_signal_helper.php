<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/onesignal.php';

/**
 * Lấy Base URL tự động (hỗ trợ cả thư mục con)
 */
function getBaseUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    // Lấy thư mục đặt project (bỏ qua phần tên file script đang chạy)
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Bỏ /api hoặc /includes hoặc /admin ở cuối nếu có
    $base_dir = preg_replace('/(\/api|\/includes|\/admin)$/', '', $script_dir);
    return $protocol . "://" . $host . rtrim($base_dir, '/');
}

/**
 * Gửi thông báo OneSignal và lưu vào Database
 *
 * @param array $data ['type', 'title', 'content', 'link', 'user_id', 'target', 'image', 'player_ids']
 * @return array ['success' => bool, 'response' => string]
 */
function sendNotification($data) {
    $type    = $data['type'] ?? 'new_post';
    $title   = $data['title'] ?? 'Thông báo mới';
    $content = $data['content'] ?? '';
    $link    = $data['link'] ?? '';
    $user_id = $data['user_id'] ?? null; // ID người nhận thông báo trong DB
    $target  = $data['target'] ?? 'all'; // 'all', 'admin', hoặc 'user'
    $image   = $data['image'] ?? null;   // URL ảnh thông báo (nếu có)

    // 1. Lưu vào bảng notifications trong Database để hiển thị trong chuông
    try {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, content, link) VALUES (:uid, :type, :title, :content, :link)");
        $stmt->execute([
            ':uid'     => $user_id,
            ':type'    => $type,
            ':title'   => $title,
            ':content' => $content,
            ':link'    => $link
        ]);
    } catch (Exception $e) {}

    // 2. Gửi Web Push qua OneSignal
    try {
        $baseUrl = getBaseUrl();
        $finalLink = (!empty($link) && !preg_match('/^http/', $link)) ? $baseUrl . '/' . ltrim($link, '/') : $link;
        $finalImage = (!empty($image) && !preg_match('/^http/', $image)) ? $baseUrl . '/' . ltrim($image, '/') : $image;

        $fields = [
            'app_id' => ONESIGNAL_APP_ID,
            'contents' => ["vi" => $content, "en" => $content],
            'headings' => ["vi" => $title, "en" => $title],
            'url' => $finalLink
        ];

        // Hỗ trợ ảnh trong thông báo (Big Picture)
        if ($finalImage) {
            $fields['chrome_web_image'] = $finalImage;
            $fields['big_picture'] = $finalImage;
        }

        if ($target === 'admin') {
            $fields['filters'] = [["field" => "tag", "key" => "role", "relation" => "=", "value" => "admin"]];
        } elseif ($target === 'user' && !empty($user_id)) {
            // Gửi riêng cho một người dùng cụ thể dựa trên External ID
            $fields['include_external_user_ids'] = [(string)$user_id];
        } else {
            $fields['included_segments'] = ['Total Subscriptions'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . ONESIGNAL_REST_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

        $response = curl_exec($ch);
        curl_close($ch);
        
        return ['success' => true, 'response' => $response];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>