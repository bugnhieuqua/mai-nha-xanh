<?php
/**
 * Media upload helper cho community comments
 * Compatible PHP 7.x+ (không dùng match expression)
 */

function uploadCommentMedia($uploadDir = '../uploads/comments/') {
    $errors = [];

    // Tạo thư mục nếu chưa có
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedVideos = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/mpeg'];

    // ── Upload ảnh (tối đa 5, optional) ─────────────────────────
    $uploadedImages = [];
    if (!empty($_FILES['comment_images']['name'][0])) {
        $field = $_FILES['comment_images'];

        // Normalize về mảng (dù gửi 1 hay nhiều file)
        $names  = is_array($field['name'])     ? $field['name']     : [$field['name']];
        $tmps   = is_array($field['tmp_name']) ? $field['tmp_name'] : [$field['tmp_name']];
        $errs   = is_array($field['error'])    ? $field['error']    : [$field['error']];
        $sizes  = is_array($field['size'])     ? $field['size']     : [$field['size']];

        $valid = [];
        foreach ($names as $i => $name) {
            if ($name && (int)$errs[$i] === UPLOAD_ERR_OK) {
                if ((int)$sizes[$i] > 3 * 1024 * 1024) {
                    $errors[] = 'Dung lượng mỗi ảnh bình luận không được vượt quá 3MB';
                    break;
                }
                $valid[] = $i;
            }
        }


        if (count($valid) > 5) {
            $errors[] = 'Tối đa 5 ảnh';
        } else {
            foreach ($valid as $i) {
                $mime = strtolower((string)@mime_content_type($tmps[$i]));
                if (!in_array($mime, $allowedImages, true)) {
                    $errors[] = 'Chỉ chấp nhận ảnh JPG/PNG/GIF/WEBP';
                    break;
                }
                // Xác định extension — dùng switch thay vì match (PHP 7 compat)
                switch ($mime) {
                    case 'image/png':  $ext = 'png';  break;
                    case 'image/gif':  $ext = 'gif';  break;
                    case 'image/webp': $ext = 'webp'; break;
                    default:           $ext = 'jpg';  break;
                }
                $filename = 'img_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
                $filepath = $uploadDir . $filename;
                if (move_uploaded_file($tmps[$i], $filepath)) {
                    // Lưu relative path từ gốc project
                    $uploadedImages[] = ltrim(substr($filepath, strlen('../')), '/');
                }
            }
        }
    }

    // ── Upload video (optional) ───────────────────────────────────
    $video = '';
    if (!empty($_FILES['comment_video']) && (int)$_FILES['comment_video']['error'] === UPLOAD_ERR_OK) {
        $videoDir = $uploadDir . 'videos/';
        if (!is_dir($videoDir)) @mkdir($videoDir, 0777, true);

        $tmp  = $_FILES['comment_video']['tmp_name'];
        $size = (int)$_FILES['comment_video']['size'];
        $mime = strtolower((string)@mime_content_type($tmp));

        if ($size > 10 * 1024 * 1024) {
            $errors[] = 'Dung lượng video bình luận không được vượt quá 10MB';
        } elseif (!in_array($mime, $allowedVideos, true)) {
            $errors[] = 'Video chỉ chấp nhận MP4/WEBM/OGG/MOV';
        } else {

            switch ($mime) {
                case 'video/webm':     $ext = 'webm'; break;
                case 'video/ogg':      $ext = 'ogv';  break;
                case 'video/quicktime':$ext = 'mov';  break;
                default:               $ext = 'mp4';  break;
            }
            $filename = 'vid_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $videoDir . $filename;
            if (move_uploaded_file($tmp, $filepath)) {
                $video = ltrim(substr($filepath, strlen('../')), '/');
            }
        }
    }

    // ── Cleanup & return ─────────────────────────────────────────
    if (!empty($errors)) {
        foreach ($uploadedImages as $p) { @unlink('../' . $p); }
        if ($video) { @unlink('../' . $video); }
        return ['success' => false, 'errors' => $errors, 'images' => [], 'video' => ''];
    }

    return [
        'success'     => true,
        'images'      => $uploadedImages,
        'video'       => $video,
        'images_json' => json_encode($uploadedImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];
}
?>
