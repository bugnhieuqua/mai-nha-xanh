<?php
// Offline fallback page - Mái Nhà Xanh
// Must return 200 OK for Service Worker cache.add() to succeed!
http_response_code(200);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mất kết nối - Mái Nhà Xanh</title>
    <meta name="robots" content="noindex">
    <link rel="shortcut icon" href="assets/images/myhome.png" type="image/x-icon">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Saira:wght@300;400;600;700;800&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Saira', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a2f 50%, #064e3b 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        /* Animated background orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            animation: float 8s ease-in-out infinite alternate;
        }
        .orb-1 { width: 400px; height: 400px; background: #10b981; top: -100px; left: -100px; }
        .orb-2 { width: 300px; height: 300px; background: #059669; bottom: -80px; right: -80px; animation-delay: -4s; }
        .orb-3 { width: 200px; height: 200px; background: #34d399; top: 50%; left: 60%; animation-delay: -2s; }

        @keyframes float {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, -30px) scale(1.1); }
        }

        .card {
            position: relative;
            z-index: 10;
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 28px;
            padding: 50px 40px;
            text-align: center;
            max-width: 460px;
            width: 90%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
            animation: slideUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .icon-wrapper {
            width: 100px;
            height: 100px;
            background: rgba(16, 185, 129, 0.15);
            border: 2px solid rgba(16, 185, 129, 0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            animation: pulse 2.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.3); }
            50%       { box-shadow: 0 0 0 20px rgba(16,185,129,0); }
        }

        .icon-wrapper svg {
            width: 50px;
            height: 50px;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #fff 0%, #a7f3d0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        p {
            color: rgba(255,255,255,0.7);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 32px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 13px 28px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 8px 20px rgba(16,185,129,0.4);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(16,185,129,0.5);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.18);
            transform: translateY(-3px);
        }

        .status-bar {
            margin-top: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.45);
        }
        .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #ef4444;
            animation: blink 1.4s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.2; }
        }

        .logo-small {
            position: absolute;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,0.8);
            font-weight: 700;
            font-size: 1rem;
        }
        .logo-small img {
            width: 32px; height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="logo-small">
        <img src="assets/images/logo.png" alt="Logo" onerror="this.style.display='none'">
        Mái Nhà Xanh
    </div>

    <div class="card">
        <div class="icon-wrapper">
            <!-- Wifi off SVG -->
            <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <line x1="1" y1="1" x2="23" y2="23"/>
                <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
                <path d="M10.71 5.05A16 16 0 0 1 22.56 9"/>
                <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                <circle cx="12" cy="20" r="1" fill="#10b981" stroke="none"/>
            </svg>
        </div>

        <h1>Mất kết nối mạng</h1>
        <p>Hệ thống không thể kết nối Internet lúc này.<br>Vui lòng kiểm tra Wi-Fi hoặc dữ liệu di động và thử lại.</p>

        <div class="btn-group">
            <button class="btn btn-primary" onclick="window.location.reload()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Thử lại
            </button>
            <a href="/" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Trang chủ
            </a>
        </div>

        <div class="status-bar">
            <div class="dot"></div>
            Không có kết nối
        </div>
    </div>

    <script>
        // Auto-retry when connection is restored
        window.addEventListener('online', function() {
            window.location.reload();
        });

        // Check connection every 5 seconds
        setInterval(function() {
            if (navigator.onLine) window.location.reload();
        }, 5000);
    </script>
</body>
</html>
