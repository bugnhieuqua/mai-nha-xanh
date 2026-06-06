<?php
require_once __DIR__ . '/../../config/bootstrap.php';

// Bảo vệ tài liệu API: Chỉ cho phép Quản trị viên (role = admin) truy cập
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "<!DOCTYPE html>
    <html lang='vi'>
    <head>
        <meta charset='UTF-8'>
        <title>403 Forbidden - Mái Nhà Xanh</title>
        <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
        <style>
            body {
                background-color: #0f172a;
                color: #f8fafc;
                font-family: 'Outfit', sans-serif;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }
            .container {
                text-align: center;
                padding: 40px;
                background-color: #1e293b;
                border-radius: 16px;
                border: 1px solid #334155;
                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                max-width: 500px;
            }
            h1 {
                color: #ef4444;
                font-size: 32px;
                margin-top: 0;
            }
            p {
                color: #94a3b8;
                line-height: 1.6;
            }
            .btn {
                display: inline-block;
                margin-top: 20px;
                background-color: #3b82f6;
                color: #fff;
                padding: 10px 24px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 500;
                transition: background-color 0.2s;
            }
            .btn:hover {
                background-color: #2563eb;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>403 Access Denied</h1>
            <p>Xin lỗi, tài nguyên này chỉ dành riêng cho <strong>Quản trị viên (Admin)</strong>. Tài khoản hiện tại của bạn không có đủ thẩm quyền truy cập tài liệu API.</p>
            <a href='../../login.php' class='btn'>Đăng nhập Admin</a>
        </div>
    </body>
    </html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Mái Nhà Xanh - API Gateway v2 Docs</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #10b981;
            --primary-hover: #059669;
            --accent: #3b82f6;
            --border-color: #334155;
        }

        html, body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
        }

        /* Top navigation header */
        .api-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(to right, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 0.5px;
        }

        .badge-v2 {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--primary);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .back-btn {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            color: var(--text-main);
            background-color: rgba(255,255,255,0.05);
            border-color: var(--text-muted);
        }

        /* Swagger UI Custom Theme overrides */
        .swagger-ui {
            filter: invert(0.9) hue-rotate(180deg);
        }

        .swagger-ui .topbar {
            display: none !important;
        }

        .swagger-ui .info {
            margin: 40px 0 !important;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .swagger-ui .info .title {
            color: #1e293b !important;
            font-family: 'Outfit', sans-serif !important;
            font-weight: 700 !important;
        }

        .swagger-ui .info p, 
        .swagger-ui .info li, 
        .swagger-ui .info td, 
        .swagger-ui .info a {
            color: #475569 !important;
            font-family: 'Outfit', sans-serif !important;
        }

        .swagger-ui .scheme-container {
            background-color: rgba(255, 255, 255, 0.95) !important;
            margin: 20px 0 !important;
            padding: 20px 30px !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03) !important;
        }

        .swagger-ui select {
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 6px 12px !important;
            background-color: #fff !important;
            color: #1e293b !important;
        }

        /* Style the Swagger UI container */
        .swagger-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 40px 60px 40px;
        }

        .footer-banner {
            text-align: center;
            padding: 40px 0;
            color: var(--text-muted);
            font-size: 14px;
            border-top: 1px solid var(--border-color);
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <header class="api-header">
        <div class="brand-container">
            <span class="logo-text">Mái Nhà Xanh</span>
            <span class="badge-v2">API GATEWAY V2</span>
        </div>
        <a href="../../admin/" class="back-btn">
            ← Quay lại Admin Dashboard
        </a>
    </header>

    <div class="swagger-container">
        <div id="swagger-ui"></div>
    </div>

    <footer class="footer-banner">
        &copy; 2026 Mái Nhà Xanh. Hệ thống quản trị nội bộ bảo mật.
    </footer>

    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "swagger.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout"
            });
            window.ui = ui;
        };
    </script>
</body>
</html>
