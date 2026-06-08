<?php

function normalizeMediaPath(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^(?:\./|\../)+#', '', $path);

    // Auto-prefix files that don't have a path prefix but start with room_, post_, or avatar_
    if (!str_contains($path, '/')) {
        if (str_starts_with($path, 'room_')) {
            $path = 'uploads/rooms/' . $path;
        } elseif (str_starts_with($path, 'post_')) {
            $path = 'uploads/posts/' . $path;
        } elseif (str_starts_with($path, 'avatar_')) {
            $path = 'uploads/avatars/' . $path;
        }
    }

    return ltrim((string)$path, '/');
}

function buildMediaUrl(?string $path, string $relativePrefix = ''): string
{
    $normalized = normalizeMediaPath($path);
    if ($normalized === '') {
        return '';
    }

    if (preg_match('#^(?:https?:)?//#i', $normalized) || str_starts_with($normalized, 'data:')) {
        return $normalized;
    }

    $relativePrefix = trim($relativePrefix);
    if ($relativePrefix === '') {
        return $normalized;
    }

    return rtrim(str_replace('\\', '/', $relativePrefix), '/') . '/' . ltrim($normalized, '/');
}

/**
 * Nén ảnh, tự động chuyển đổi sang WebP và tối ưu hóa kích thước (Scale down)
 * Hỗ trợ các nguồn ảnh phòng trọ, ảnh đại diện, giảm dung lượng tối đa và mượt mà hóa giao diện.
 */
function compressAndUploadImage($tmpPath, $uploadDir, $prefix, $idx, $maxWidth = 1200, $maxHeight = 1200, $quality = 80): string {
    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo) {
        $fileName = $prefix . '_' . time() . '_' . uniqid('', true) . '_' . $idx . '.jpg';
        if (move_uploaded_file($tmpPath, $uploadDir . $fileName)) {
            return $fileName;
        }
        return '';
    }

    $mime = $imageInfo['mime'];
    
    // Nếu có hỗ trợ thư viện GD và hàm imagewebp, ta convert sang WebP và nén tối ưu
    if (extension_loaded('gd') && function_exists('imagewebp') && function_exists('imagecreatetruecolor')) {
        $srcImg = null;
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $srcImg = @imagecreatefromjpeg($tmpPath);
                break;
            case 'image/png':
                $srcImg = @imagecreatefrompng($tmpPath);
                break;
            case 'image/webp':
                $srcImg = @imagecreatefromwebp($tmpPath);
                break;
            case 'image/gif':
                $srcImg = @imagecreatefromgif($tmpPath);
                break;
        }

        if ($srcImg) {
            $origWidth = $imageInfo[0];
            $origHeight = $imageInfo[1];

            // Tính toán kích thước mới (giữ nguyên tỷ lệ ảnh)
            $widthRatio = $maxWidth / $origWidth;
            $heightRatio = $maxHeight / $origHeight;
            $ratio = min($widthRatio, $heightRatio);

            if ($ratio < 1) {
                $newWidth = (int)round($origWidth * $ratio);
                $newHeight = (int)round($origHeight * $ratio);
            } else {
                $newWidth = $origWidth;
                $newHeight = $origHeight;
            }

            $dstImg = imagecreatetruecolor($newWidth, $newHeight);
            if ($dstImg) {
                // Xử lý giữ nguyên độ trong suốt (transparency) cho ảnh PNG, WebP, GIF
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
                $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
                imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, $transparent);

                if (imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight)) {
                    $fileName = $prefix . '_' . time() . '_' . uniqid('', true) . '_' . $idx . '.webp';
                    if (@imagewebp($dstImg, $uploadDir . $fileName, $quality)) {
                        imagedestroy($srcImg);
                        imagedestroy($dstImg);
                        return $fileName;
                    }
                }
                imagedestroy($dstImg);
            }
            imagedestroy($srcImg);
        }
    }

    // Fallback: Nếu không hỗ trợ GD hoặc nén lỗi, giữ nguyên file gốc để đảm bảo hệ thống chạy bình thường
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg'
    };
    $fileName = $prefix . '_' . time() . '_' . uniqid('', true) . '_' . $idx . '.' . $ext;
    if (move_uploaded_file($tmpPath, $uploadDir . $fileName)) {
        return $fileName;
    }
    return '';
}

