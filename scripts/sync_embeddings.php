<?php
/**
 * Script to synchronize room descriptions and generate vector embeddings
 * Run via PHP CLI: php scripts/sync_embeddings.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/ai_helper.php';

// Check if running from CLI
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo "<pre>";
}

echo "=== START SYNC EMBEDDINGS ===\n";

$db = getDB();
if (!$db) {
    die("Database connection failed.\n");
}

try {
    // 1. Fetch from phongtro
    $stmt1 = $db->query("SELECT id, ten_phong, mota, diachi, tiennghi FROM phongtro");
    $rooms1 = $stmt1->fetchAll();
    
    // 2. Fetch from dangbai_chothuetro (da_duyet)
    $stmt2 = $db->query("SELECT id, tieude as ten_phong, mota, diachi, tiennghi FROM dangbai_chothuetro WHERE trangthai = 'da_duyet'");
    $rooms2 = $stmt2->fetchAll();
    
    $allRooms = [];
    foreach ($rooms1 as $r) {
        $r['source'] = 'phongtro';
        $allRooms[] = $r;
    }
    foreach ($rooms2 as $r) {
        $r['source'] = 'dangbai';
        $allRooms[] = $r;
    }
    
    echo "Found " . count($allRooms) . " rooms to embed.\n";
    
    $successCount = 0;
    $failCount = 0;
    
    $stmtInsert = $db->prepare("
        INSERT INTO room_embeddings (room_id, source, embedding) 
        VALUES (:room_id, :source, :embedding) 
        ON DUPLICATE KEY UPDATE embedding = VALUES(embedding)
    ");
    
    foreach ($allRooms as $room) {
        $id = $room['id'];
        $source = $room['source'];
        $title = $room['ten_phong'];
        $desc = $room['mota'];
        $address = $room['diachi'];
        $amenities = $room['tiennghi'];
        
        // Tạo chuỗi văn bản mô tả trọn vẹn phòng trọ để tạo ngữ nghĩa tốt nhất
        $inputText = "Tiêu đề: {$title}\n";
        $inputText .= "Địa chỉ: {$address}\n";
        if (!empty($amenities)) {
            $inputText .= "Tiện nghi: {$amenities}\n";
        }
        if (!empty($desc)) {
            $inputText .= "Mô tả: {$desc}\n";
        }
        
        $inputText = trim($inputText);
        
        echo "Generating embedding for [{$source} #{$id}]: \"{$title}\"... ";
        
        // Tránh gọi quá nhanh để không bị rate limit
        usleep(400000); // 400ms delay
        
        $vector = getEmbedding($inputText);
        
        if ($vector !== null) {
            $embeddingJson = json_encode($vector);
            $stmtInsert->execute([
                ':room_id' => $id,
                ':source' => $source,
                ':embedding' => $embeddingJson
            ]);
            echo "SUCCESS\n";
            $successCount++;
        } else {
            echo "FAILED\n";
            $failCount++;
        }
    }
    
    echo "\n=== SYNC COMPLETE ===\n";
    echo "Successfully embedded: {$successCount} rooms.\n";
    echo "Failed: {$failCount} rooms.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

if (!$isCli) {
    echo "</pre>";
}
?>
