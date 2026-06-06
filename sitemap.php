<?php
require_once 'config/session.php';
require_once 'config/database.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

$database = new Database();
$db = $database->getConnection();

// Các trang tĩnh
$static_pages = [
    ['url' => '/',               'priority' => '1.0', 'changefreq' => 'daily'],
    ['url' => '/phong-tro.php', 'priority' => '0.9', 'changefreq' => 'hourly'],
    ['url' => '/gioi-thieu.php','priority' => '0.6', 'changefreq' => 'monthly'],
    ['url' => '/lien-he.php',   'priority' => '0.7', 'changefreq' => 'monthly'],
    ['url' => '/cong-dong.php', 'priority' => '0.8', 'changefreq' => 'daily'],
];

echo '<?xml version="1.0" encoding="UTF-8"' . '?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Trang tĩnh
foreach ($static_pages as $page) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base_url . $page['url']) . "</loc>\n";
    echo "    <changefreq>{$page['changefreq']}</changefreq>\n";
    echo "    <priority>{$page['priority']}</priority>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "  </url>\n";
}

// Phòng trọ từ DB
try {
    $stmt = $db->query("SELECT id, ngaycapnhat FROM phongtro WHERE trangthai='con_phong' ORDER BY ngaydang DESC LIMIT 500");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = date('Y-m-d', strtotime($row['ngaycapnhat'] ?? 'now'));
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($base_url . "/phong-tro.php?id=" . $row['id']) . "</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "    <lastmod>{$lastmod}</lastmod>\n";
        echo "  </url>\n";
    }
} catch (Exception $e) { /* ignore */ }

// Bài đăng đã duyệt
try {
    $stmt = $db->query("SELECT id, ngaydang FROM dangbai_chothuetro WHERE trangthai='da_duyet' ORDER BY ngaydang DESC LIMIT 500");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = date('Y-m-d', strtotime($row['ngaydang'] ?? 'now'));
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($base_url . "/phong-tro.php?baidang=" . $row['id']) . "</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.7</priority>\n";
        echo "    <lastmod>{$lastmod}</lastmod>\n";
        echo "  </url>\n";
    }
} catch (Exception $e) { /* ignore */ }

echo '</urlset>';
?>
