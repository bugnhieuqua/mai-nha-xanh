<?php
/**
 * RESTful Rooms API v2
 * GET /api/v2/rooms.php (lấy danh sách kèm phân trang, lọc, sắp xếp)
 * POST /api/v2/rooms.php (đăng tin mới)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/room_status_helper.php';
require_once __DIR__ . '/../../includes/ai_moderation_helper.php';
require_once __DIR__ . '/../../includes/one_signal_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Lấy tham số truy vấn
    $min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
    $max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
    $min_area = isset($_GET['min_area']) ? floatval($_GET['min_area']) : 0;
    $max_area = isset($_GET['max_area']) && $_GET['max_area'] !== '' ? floatval($_GET['max_area']) : null;
    $ward = isset($_GET['ward']) ? trim($_GET['ward']) : '';
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'date_desc';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;

    try {
        ensureDangbaiRoomStatusSchema($db);

        // 1. Lấy dữ liệu tĩnh từ phongtro
        $stmt1 = $db->query("SELECT id, ten_phong, mota, hinhanh, '' as hinhanh_list, '' as video, gia, dientich, diachi, tiennghi, 'BQL' as ten_chunha, '0123456789' as sdt_chunha, ngaydang, trangthai, COALESCE(lat, 18.6923405) as lat, COALESCE(lng, 105.681627) as lng, 'phongtro' as nguon FROM phongtro");
        $rooms1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        // 2. Lấy bài đăng được duyệt từ dangbai_chothuetro
        $stmt2 = $db->query("SELECT id, tieude as ten_phong, mota, hinhanh, hinhanh_list, video, gia, dientich, diachi, tiennghi, ten_chunha, sdt_chunha, ngaydang, COALESCE(trangthai_phong, 'con_phong') as trangthai, COALESCE(lat, 18.6923405) as lat, COALESCE(lng, 105.681627) as lng, 'dangbai' as nguon FROM dangbai_chothuetro WHERE trangthai = 'da_duyet'");
        $rooms2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Gộp danh sách
        $allRooms = array_merge($rooms1, $rooms2);

        // Lọc trong PHP
        $filtered = [];
        foreach ($allRooms as $r) {
            if ($min_price > 0 && $r['gia'] < $min_price) continue;
            if ($max_price !== null && $r['gia'] > $max_price) continue;
            if ($min_area > 0 && $r['dientich'] < $min_area) continue;
            if ($max_area !== null && $r['dientich'] > $max_area) continue;

            if (!empty($ward)) {
                $normalizedWard = mb_strtolower($ward, 'UTF-8');
                $normalizedAddress = mb_strtolower($r['diachi'], 'UTF-8');
                if (mb_strpos($normalizedAddress, $normalizedWard) === false) continue;
            }

            if (!empty($q)) {
                $normalizedQ = mb_strtolower($q, 'UTF-8');
                $textToSearch = mb_strtolower($r['ten_phong'] . ' ' . $r['mota'] . ' ' . $r['diachi'] . ' ' . $r['tiennghi'], 'UTF-8');
                if (mb_strpos($textToSearch, $normalizedQ) === false) continue;
            }

            $filtered[] = $r;
        }

        // Sắp xếp
        usort($filtered, function($a, $b) use ($sort_by) {
            if ($sort_by === 'price_asc') {
                return $a['gia'] - $b['gia'];
            } elseif ($sort_by === 'price_desc') {
                return $b['gia'] - $a['gia'];
            } elseif ($sort_by === 'area_asc') {
                return $a['dientich'] - $b['dientich'];
            } elseif ($sort_by === 'area_desc') {
                return $b['dientich'] - $a['dientich'];
            } elseif ($sort_by === 'date_asc') {
                return strtotime($a['ngaydang']) - strtotime($b['ngaydang']);
            } else {
                return strtotime($b['ngaydang']) - strtotime($a['ngaydang']);
            }
        });

        // Phân trang
        $total = count($filtered);
        $pages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($filtered, $offset, $limit);

        echo json_encode([
            'success' => true,
            'code' => 200,
            'message' => 'Lấy danh sách phòng trọ thành công!',
            'data' => [
                'rooms' => $paginated,
                'pagination' => [
                    'total_items' => $total,
                    'total_pages' => $pages,
                    'current_page' => $page,
                    'limit' => $limit
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'code' => 500,
            'message' => 'Lỗi hệ thống: ' . $e->getMessage()
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Yêu cầu đăng nhập để đăng bài
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'code' => 401,
            'message' => 'Vui lòng đăng nhập để thực hiện hành động.'
        ]);
        exit;
    }

    validateCsrfToken();

    $tieude = trim($_POST['tieude'] ?? '');
    $mota = trim($_POST['mota'] ?? '');
    $gia = trim($_POST['gia'] ?? '');
    $dientich = trim($_POST['dientich'] ?? '');
    $diachi = trim($_POST['diachi'] ?? '');
    $ten_chunha = trim($_POST['ten_chunha'] ?? '');
    $sdt_chunha = trim($_POST['sdt_chunha'] ?? '');

    $tiennghi_arr = $_POST['tiennghi'] ?? [];
    $tiennghi = is_array($tiennghi_arr) ? implode(', ', $tiennghi_arr) : trim($tiennghi_arr);
    $tiennghi_khac = trim($_POST['tiennghi_khac'] ?? '');
    if (!empty($tiennghi_khac)) {
        $tiennghi = !empty($tiennghi) ? $tiennghi . ', ' . $tiennghi_khac : $tiennghi_khac;
    }

    // Validate dữ liệu
    $errors = [];
    if (empty($tieude)) $errors[] = 'Thiếu tiêu đề';
    if (empty($gia) || !is_numeric($gia)) $errors[] = 'Giá thuê không hợp lệ';
    if (empty($dientich) || !is_numeric($dientich)) $errors[] = 'Diện tích không hợp lệ';
    if (empty($diachi)) $errors[] = 'Thiếu địa chỉ';
    if (empty($ten_chunha)) $errors[] = 'Thiếu tên chủ nhà';
    if (empty($sdt_chunha) || !preg_match('/^[0-9]{10,11}$/', $sdt_chunha)) $errors[] = 'Số điện thoại không hợp lệ';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'code' => 400,
            'message' => implode(', ', $errors)
        ]);
        exit;
    }

    // Xử lý upload ảnh
    $uploadDir = '../../uploads/rooms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $imageField = $_FILES['hinhanh'] ?? null;
    if (!$imageField || empty($imageField['name'][0])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 400, 'message' => 'Bắt buộc tải lên ít nhất 1 ảnh.']);
        exit;
    }

    $imageNames = is_array($imageField['name']) ? $imageField['name'] : [$imageField['name']];
    $imageTmps = is_array($imageField['tmp_name']) ? $imageField['tmp_name'] : [$imageField['tmp_name']];
    $imageTypes = is_array($imageField['type']) ? $imageField['type'] : [$imageField['type']];
    $imageSizes = is_array($imageField['size']) ? $imageField['size'] : [$imageField['size']];
    
    $uploadedImages = [];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    foreach ($imageNames as $idx => $name) {
        $tmp = $imageTmps[$idx] ?? '';
        $mime = strtolower((string)@mime_content_type($tmp));
        if ($mime === '') $mime = strtolower($imageTypes[$idx] ?? '');

        if (!in_array($mime, $allowedTypes, true)) {
            foreach ($uploadedImages as $p) { if (file_exists('../../' . $p)) @unlink('../../' . $p); }
            http_response_code(400);
            echo json_encode(['success' => false, 'code' => 400, 'message' => 'Định dạng ảnh không hợp lệ.']);
            exit;
        }

        $size = intval($imageSizes[$idx] ?? 0);
        if ($size > 3 * 1024 * 1024) {
            foreach ($uploadedImages as $p) { if (file_exists('../../' . $p)) @unlink('../../' . $p); }
            http_response_code(400);
            echo json_encode(['success' => false, 'code' => 400, 'message' => 'Dung lượng mỗi ảnh không được vượt quá 3MB.']);
            exit;
        }


        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $fileName = 'room_' . time() . '_' . uniqid() . '_' . $idx . '.' . ($ext ?: 'jpg');
        if (move_uploaded_file($tmp, $uploadDir . $fileName)) {
            $uploadedImages[] = 'uploads/rooms/' . $fileName;
        }
    }

    $hinhanh = $uploadedImages[0] ?? '';
    $hinhanh_list = json_encode($uploadedImages, JSON_UNESCAPED_SLASHES);

    try {
        ensureDangbaiRoomStatusSchema($db);

        // Lưu vào DB
        $query = "INSERT INTO dangbai_chothuetro (tieude, mota, hinhanh, hinhanh_list, gia, dientich, diachi, tiennghi, ten_chunha, sdt_chunha, nguoidang, trangthai, trangthai_phong)
                  VALUES (:tieude, :mota, :hinhanh, :hinhanh_list, :gia, :dientich, :diachi, :tiennghi, :ten_chunha, :sdt_chunha, :nguoidang, 'cho_duyet', 'con_phong')";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':tieude', $tieude);
        $stmt->bindParam(':mota', $mota);
        $stmt->bindParam(':hinhanh', $hinhanh);
        $stmt->bindParam(':hinhanh_list', $hinhanh_list);
        $stmt->bindParam(':gia', $gia);
        $stmt->bindParam(':dientich', $dientich);
        $stmt->bindParam(':diachi', $diachi);
        $stmt->bindParam(':tiennghi', $tiennghi);
        $stmt->bindParam(':ten_chunha', $ten_chunha);
        $stmt->bindParam(':sdt_chunha', $sdt_chunha);
        $nguoidang = $_SESSION['username'];
        $stmt->bindParam(':nguoidang', $nguoidang);

        if ($stmt->execute()) {
            $new_id = $db->lastInsertId();

            // Chạy AI kiểm duyệt
            $aiRes = null;
            try {
                $aiRes = analyzePostWithAI($db, $new_id);
            } catch (Exception $e) {
                $aiRes = ['success' => false, 'message' => $e->getMessage()];
            }

            if ($aiRes && $aiRes['success']) {
                $verdict = $aiRes['data']['verdict'] ?? 'WARNING';
                $reasons = $aiRes['data']['reasons'] ?? [];
                $reasonsStr = implode(', ', $reasons);

                if ($verdict === 'SAFE') {
                    // Phê duyệt tự động
                    $upd = $db->prepare("UPDATE dangbai_chothuetro SET trangthai = 'da_duyet', trangthai_phong = 'con_phong', duyet_luc = NOW() WHERE id = :id");
                    $upd->execute([':id' => $new_id]);

                    // Gửi thông báo OneSignal
                    try {
                        sendNotification([
                            'type'    => 'post_approved',
                            'target'  => 'all',
                            'title'   => 'Mái Nhà Xanh: Có phòng mới!',
                            'content' => 'Phòng trọ mới tại ' . $diachi . ': "' . $tieude . '"',
                            'link'    => getAppBaseUrl() . '/phong-tro.php?id=' . $new_id,
                            'image'   => $hinhanh
                        ]);
                    } catch (Exception $e) {}

                    echo json_encode([
                        'success' => true,
                        'code' => 200,
                        'message' => 'Đăng bài thành công và đã được duyệt tự động bởi Trợ lý AI!',
                        'data' => ['id' => $new_id, 'verdict' => 'SAFE']
                    ]);
                } elseif ($verdict === 'DANGER') {
                    // Từ chối tự động: xóa khỏi database và xóa các tệp tin để "huỷ, không nhận"
                    $del = $db->prepare("DELETE FROM dangbai_chothuetro WHERE id = :id");
                    $del->execute([':id' => $new_id]);

                    foreach ($uploadedImages as $p) {
                        if (file_exists('../../' . $p)) @unlink('../../' . $p);
                    }

                    echo json_encode([
                        'success' => false,
                        'code' => 422,
                        'message' => 'Trợ lý AI từ chối bài đăng do vi phạm chính sách: ' . $reasonsStr,
                        'data' => ['id' => $new_id, 'verdict' => 'DANGER', 'reasons' => $reasons]
                    ]);
                } else {
                    // Cảnh báo (WARNING) -> Chờ duyệt thủ công
                    try {
                        sendNotification([
                            'type'    => 'new_post',
                            'target'  => 'admin',
                            'title'   => '🔔 Bài đăng mới chờ duyệt (Cảnh báo AI)',
                            'content' => 'AI cảnh báo bài đăng "' . $tieude . '": ' . $reasonsStr,
                            'link'    => getAppBaseUrl() . '/admin/posts.php?id=' . $new_id
                        ]);
                    } catch (Exception $e) {}

                    echo json_encode([
                        'success' => true,
                        'code' => 202,
                        'message' => 'Đăng bài thành công! Bài viết đang chờ Admin phê duyệt thủ công do có cảnh báo: ' . $reasonsStr,
                        'data' => ['id' => $new_id, 'verdict' => 'WARNING', 'reasons' => $reasons]
                    ]);
                }
            } else {
                // Fallback khi AI lỗi
                try {
                    sendNotification([
                        'type'    => 'new_post',
                        'target'  => 'admin',
                        'title'   => '🔔 Bài đăng mới chờ duyệt',
                        'content' => 'Có bài đăng mới: "' . $tieude . '"',
                        'link'    => getAppBaseUrl() . '/admin/posts.php?id=' . $new_id
                    ]);
                } catch (Exception $e) {}

                echo json_encode([
                    'success' => true,
                    'code' => 202,
                    'message' => 'Đăng bài thành công! Bài viết đang chờ phê duyệt từ quản trị viên.',
                    'data' => ['id' => $new_id, 'verdict' => 'PENDING']
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'code' => 500, 'message' => 'Lỗi lưu thông tin bài đăng.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'code' => 500, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'code' => 405, 'message' => 'Phương thức không được hỗ trợ.']);
}
