<?php
// PWA Diagnostic - Kiểm tra tất cả điều kiện cài đặt PWA
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PWA Diagnostic</title>
<link rel="manifest" href="manifest.json?v=3" crossorigin="use-credentials">
<style>
body { font-family: -apple-system, sans-serif; max-width: 700px; margin: 20px auto; padding: 0 16px; background: #0f172a; color: #e2e8f0; }
h1 { color: #10b981; }
.check { padding: 12px 16px; margin: 8px 0; border-radius: 8px; background: #1e293b; }
.ok { border-left: 4px solid #10b981; }
.fail { border-left: 4px solid #ef4444; }
.warn { border-left: 4px solid #f59e0b; }
code { background: #334155; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
pre { background: #334155; padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 0.8em; }
#js-results { margin-top: 20px; }
</style>
</head>
<body>
<h1>🔍 PWA Diagnostic</h1>
<p>Kiểm tra từ server và trình duyệt</p>

<h2>📋 Server-side Checks</h2>

<?php
// 1. Check manifest.json exists and is valid JSON
$manifest_path = __DIR__ . '/manifest.json';
if (file_exists($manifest_path)) {
    $raw = file_get_contents($manifest_path);
    $json = json_decode($raw, true);
    if ($json !== null) {
        echo '<div class="check ok">✅ <code>manifest.json</code> — File tồn tại, JSON hợp lệ</div>';
        
        // Check required fields
        $required = ['name', 'short_name', 'start_url', 'display', 'icons'];
        foreach ($required as $field) {
            if (isset($json[$field])) {
                echo '<div class="check ok">✅ Manifest có trường <code>' . $field . '</code></div>';
            } else {
                echo '<div class="check fail">❌ Manifest THIẾU trường <code>' . $field . '</code></div>';
            }
        }
        
        // Check icons
        if (isset($json['icons']) && is_array($json['icons'])) {
            $has192 = false; $has512 = false;
            foreach ($json['icons'] as $icon) {
                if (strpos($icon['sizes'] ?? '', '192') !== false) $has192 = true;
                if (strpos($icon['sizes'] ?? '', '512') !== false) $has512 = true;
            }
            echo $has192 ? '<div class="check ok">✅ Có icon 192x192</div>' : '<div class="check fail">❌ THIẾU icon 192x192</div>';
            echo $has512 ? '<div class="check ok">✅ Có icon 512x512</div>' : '<div class="check fail">❌ THIẾU icon 512x512 (cần cho splash screen)</div>';
            
            // Check icon files actually exist
            foreach ($json['icons'] as $icon) {
                $icon_path = __DIR__ . '/' . $icon['src'];
                if (file_exists($icon_path)) {
                    $size = filesize($icon_path);
                    echo '<div class="check ok">✅ <code>' . $icon['src'] . '</code> — tồn tại (' . round($size/1024, 1) . ' KB)</div>';
                } else {
                    echo '<div class="check fail">❌ <code>' . $icon['src'] . '</code> — FILE KHÔNG TỒN TẠI!</div>';
                }
            }
        }
    } else {
        echo '<div class="check fail">❌ <code>manifest.json</code> — JSON KHÔNG HỢP LỆ! Raw bytes đầu: <code>' . bin2hex(substr($raw, 0, 20)) . '</code></div>';
        // Check for BOM
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            echo '<div class="check fail">❌ File có BOM (Byte Order Mark) — cần xoá!</div>';
        }
    }
} else {
    echo '<div class="check fail">❌ <code>manifest.json</code> — FILE KHÔNG TỒN TẠI!</div>';
}

// 2. Check sw.php exists
$sw_path = __DIR__ . '/sw.php';
echo file_exists($sw_path) 
    ? '<div class="check ok">✅ <code>sw.php</code> — tồn tại</div>'
    : '<div class="check fail">❌ <code>sw.php</code> — KHÔNG TỒN TẠI!</div>';

// 3. Check HTTPS
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
echo $is_https 
    ? '<div class="check ok">✅ HTTPS — Đang chạy qua HTTPS</div>'
    : '<div class="check warn">⚠️ HTTP — Trang đang chạy HTTP (PWA yêu cầu HTTPS trên production)</div>';

// 4. Check Content-Type header that would be served for manifest.json
echo '<div class="check warn">ℹ️ Manifest URL: <code>' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/manifest.json</code></div>';

// 5. Check .htaccess
$htaccess_path = __DIR__ . '/.htaccess';
if (file_exists($htaccess_path)) {
    $htaccess = file_get_contents($htaccess_path);
    echo '<div class="check ok">✅ <code>.htaccess</code> tồn tại</div>';
    
    // Check if .htaccess has hotlink protection that might block icons
    if (stripos($htaccess, 'hotlink') !== false || stripos($htaccess, 'RewriteCond.*HTTP_REFERER') !== false) {
        echo '<div class="check warn">⚠️ .htaccess có thể chặn hotlink ảnh icon</div>';
    }
} else {
    echo '<div class="check warn">⚠️ <code>.htaccess</code> không tồn tại</div>';
}
?>


<h2>🖥️ Browser-side Checks</h2>
<div id="js-results">Đang kiểm tra...</div>

<h2>📡 Manifest Response Test</h2>
<div id="manifest-test">Đang tải manifest.json...</div>

<h2>🖼️ Icon Accessibility Test</h2>
<div id="icon-test">Đang kiểm tra icon...</div>

<script>
(async function() {
    const results = document.getElementById('js-results');
    const manifestTest = document.getElementById('manifest-test');
    const iconTest = document.getElementById('icon-test');
    let html = '';

    // 1. Service Worker
    if ('serviceWorker' in navigator) {
        html += '<div class="check ok">✅ Trình duyệt hỗ trợ Service Worker</div>';
        try {
            const regs = await navigator.serviceWorker.getRegistrations();
            if (regs.length > 0) {
                regs.forEach(reg => {
                    html += '<div class="check ok">✅ SW đã đăng ký: <code>' + reg.scope + '</code> (Active: ' + (reg.active ? '✅' : '❌') + ', Status: ' + (reg.installing ? 'installing' : reg.waiting ? 'waiting' : 'active') + ')</div>';
                });
            } else {
                html += '<div class="check fail">❌ Không có Service Worker nào đăng ký! <button id="btn-register-sw" style="padding: 4px 8px; margin-left: 10px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer;">Đăng ký Thử sw.php</button></div>';
            }
        } catch(e) {
            html += '<div class="check fail">❌ Lỗi kiểm tra SW: ' + e.message + '</div>';
        }
    } else {
        html += '<div class="check fail">❌ Trình duyệt KHÔNG hỗ trợ Service Worker</div>';
    }

    // 2. beforeinstallprompt support
    html += '<div class="check warn">ℹ️ beforeinstallprompt: Đang chờ sự kiện...</div>';

    // 3. Display mode
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    html += isStandalone 
        ? '<div class="check warn">⚠️ Đang chạy ở chế độ standalone (đã cài)</div>'
        : '<div class="check ok">✅ Đang chạy trong trình duyệt (chưa cài)</div>';

    // 4. HTTPS
    html += location.protocol === 'https:' 
        ? '<div class="check ok">✅ Trang đang dùng HTTPS</div>'
        : '<div class="check warn">⚠️ Trang đang dùng HTTP: ' + location.protocol + '</div>';

    results.innerHTML = html;

    const btnReg = document.getElementById('btn-register-sw');
    if (btnReg) {
        btnReg.addEventListener('click', async () => {
            btnReg.disabled = true;
            btnReg.textContent = 'Đang đăng ký...';
            try {
                const reg = await navigator.serviceWorker.register('sw.php');
                alert('Đăng ký SW THÀNH CÔNG! Scope: ' + reg.scope);
                location.reload();
            } catch (e) {
                alert('Đăng ký SW THẤT BẠI: ' + e.message);
                console.error(e);
                btnReg.disabled = false;
                btnReg.textContent = 'Đăng ký lại (Lỗi)';
            }
        });
    }

    // Test manifest.json fetch
    try {
        const resp = await fetch('manifest.json?v=2');
        const ct = resp.headers.get('content-type');
        const text = await resp.text();
        let mhtml = '<div class="check ' + (resp.ok ? 'ok' : 'fail') + '">';
        mhtml += (resp.ok ? '✅' : '❌') + ' Manifest status: <code>' + resp.status + '</code>, Content-Type: <code>' + ct + '</code></div>';
        
        // Check first bytes
        const firstBytes = text.substring(0, 3);
        const charCodes = [];
        for (let i = 0; i < Math.min(5, text.length); i++) charCodes.push(text.charCodeAt(i));
        mhtml += '<div class="check warn">ℹ️ Đầu 5 char codes: <code>[' + charCodes.join(', ') + ']</code> (123 = "{" OK)</div>';
        
        if (text.charCodeAt(0) !== 123) {
            mhtml += '<div class="check fail">❌ JSON KHÔNG bắt đầu bằng { — có ký tự thừa trước JSON!</div>';
            mhtml += '<div class="check fail">❌ Raw đầu 50 ký tự: <code>' + JSON.stringify(text.substring(0, 50)) + '</code></div>';
        }
        
        try {
            JSON.parse(text);
            mhtml += '<div class="check ok">✅ JSON.parse() thành công</div>';
        } catch(e) {
            mhtml += '<div class="check fail">❌ JSON.parse() THẤT BẠI: <code>' + e.message + '</code></div>';
        }
        
        manifestTest.innerHTML = mhtml;
    } catch(e) {
        manifestTest.innerHTML = '<div class="check fail">❌ Fetch manifest.json lỗi: ' + e.message + '</div>';
    }

    // Test icon accessibility
    const icons = ['assets/images/myhome.png', 'assets/images/myhome.png'];
    let ihtml = '';
    for (const icon of icons) {
        try {
            const resp = await fetch(icon, { method: 'HEAD' });
            ihtml += '<div class="check ' + (resp.ok ? 'ok' : 'fail') + '">';
            ihtml += (resp.ok ? '✅' : '❌') + ' <code>' + icon + '</code> — Status: <code>' + resp.status + '</code></div>';
        } catch(e) {
            ihtml += '<div class="check fail">❌ <code>' + icon + '</code> — Fetch error: ' + e.message + '</div>';
        }
    }
    iconTest.innerHTML = ihtml;

    // Listen for beforeinstallprompt (update after 3 seconds)
    let bipFired = false;
    window.addEventListener('beforeinstallprompt', function(e) {
        bipFired = true;
        const el = document.createElement('div');
        el.className = 'check ok';
        el.innerHTML = '✅ <strong>beforeinstallprompt ĐÃ BẮN!</strong> PWA có thể cài đặt!';
        results.appendChild(el);
    });
    
    setTimeout(() => {
        if (!bipFired) {
            const el = document.createElement('div');
            el.className = 'check fail';
            el.innerHTML = '❌ <strong>beforeinstallprompt KHÔNG bắn sau 5 giây</strong> — Chrome đánh giá PWA không đủ điều kiện cài đặt';
            results.appendChild(el);
        }
    }, 5000);
})();
</script>
</body>
</html>
