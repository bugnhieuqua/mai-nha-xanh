<!DOCTYPE html>
<html lang="vi">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem("theme") || (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
            document.documentElement.setAttribute("data-theme", savedTheme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Mái Nhà Xanh</title>
    <meta name="description" content="<?php echo isset($page_description) ? htmlspecialchars($page_description) : 'Mái Nhà Xanh - Chuyên cho thuê phòng trọ chất lượng, giá cả hợp lý tại TP.Vinh, Nghệ An. Tìm phòng trọ đẹp, tiện nghi, an toàn.'; ?>">
    <meta name="keywords" content="phòng trọ Vinh, thuê phòng Nghệ An, nhà trọ giá rẻ, Mái Nhà Xanh">
    <meta name="robots" content="index, follow">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

    <!-- Google Search Console Verification -->
    <meta name="google-site-verification" content="TROq-Y2Z8RyZG5jBqrIKEaLHGKVij1P1cfIHZn4rTeQ" />

    <!-- Open Graph -->
    <?php
    $og_url   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $og_title = (isset($page_title) ? $page_title . ' - ' : '') . 'Mái Nhà Xanh';
    $og_desc  = isset($page_description) ? htmlspecialchars($page_description) : 'Mái Nhà Xanh - Chuyên cho thuê phòng trọ chất lượng, giá cả hợp lý tại TP.Vinh, Nghệ An.';
    $og_image = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/assets/images/og-banner.jpg';
    if (isset($page_og_image)) $og_image = $page_og_image;
    ?>
    <meta property="og:type"        content="website">
    <meta property="og:locale"      content="vi_VN">
    <meta property="og:site_name"   content="Mái Nhà Xanh">
    <meta property="og:url"         content="<?php echo htmlspecialchars($og_url); ?>">
    <meta property="og:title"       content="<?php echo htmlspecialchars($og_title); ?>">
    <meta property="og:description" content="<?php echo $og_desc; ?>">
    <meta property="og:image"       content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?php echo htmlspecialchars($og_title); ?>">
    <meta name="twitter:description" content="<?php echo $og_desc; ?>">
    <meta name="twitter:image"       content="<?php echo htmlspecialchars($og_image); ?>">

    <link rel="shortcut icon" href="assets/images/myhome.png" type="image/x-icon">
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json?v=3" crossorigin="use-credentials">
    <!-- Theme & Status Bar -->
    <meta name="theme-color" content="#10b981" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#064e3b" media="(prefers-color-scheme: dark)">
    <!-- iOS PWA Support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Mái Nhà Xanh">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="assets/images/myhome.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/images/myhome.png">
    <link rel="apple-touch-icon" sizes="167x167" href="assets/images/myhome.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/myhome.png">
    <link rel="apple-touch-startup-image" href="assets/images/myhome.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Saira:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/assistant.css?v=<?= time() ?>">


    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* Fix iOS auto-zoom khi focus input < 16px */
    input, textarea, select { font-size: max(16px, 1em); }
    body {
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
    }

    /* Badge chuông */
    #notif-badge {
        display: none;
        position: absolute;
        top: 0px; right: 0px;
        background: #ef4444;
        color: #fff;
        font-size: .6rem;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        line-height: 18px;
        text-align: center;
        padding: 0 4px;
        border: 2px solid #fff;
        box-sizing: border-box;
    }

    /* Mobile Responsive for Dropdowns */
    @media screen and (max-width: 768px) {
        #notif-dropdown {
            display: none;
            flex-direction: column;
        }
        #notif-dropdown.show-mobile {
            display: flex !important;
        }
        #notif-list {
            flex: 1 !important;
            max-height: none !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        .mobile-only-close {
            display: flex !important;
        }
        /* Mobile header for notifications */
        .notif-header-area {
            padding: 16px !important;
            border-radius: 0 !important;
        }
    }
    </style>
    <!-- PWA Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
            // Use a versioned URL only if necessary, but keep it consistent with OneSignal config
            navigator.serviceWorker.register('sw.php')
                    .then(reg => {
                        console.log('SW registered:', reg.scope);

                        // On localhost, optionally clean up older registrations if needed
                        try {
                            const host = (location && location.hostname) ? location.hostname : '';
                            if (host === 'localhost' || host === '127.0.0.1') {
                                // Cleanup logic can be added here if specific versions need to be purged
                            }
                        } catch (e) {}

                        // Try to update immediately so users don't stay on old cached SW
                        try { reg.update(); } catch (e) {}

                        // If there's a waiting worker, activate it now
                        try {
                            if (reg.waiting) {
                                reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                            }
                        } catch (e) {}

                        // When a new SW is found, ask it to skip waiting after install
                        try {
                            reg.addEventListener('updatefound', () => {
                                const nw = reg.installing;
                                if (!nw) return;
                                nw.addEventListener('statechange', () => {
                                    if (nw.state === 'installed' && navigator.serviceWorker.controller) {
                                        try { nw.postMessage({ type: 'SKIP_WAITING' }); } catch (e) {}
                                    }
                                });
                            });
                        } catch (e) {}

                        // Prevent infinite loops and forced reloads by removing auto-reload
                        try {
                            let refreshing = false;
                            navigator.serviceWorker.addEventListener('controllerchange', () => {
                                if (refreshing) return;
                                refreshing = true;
                                // We purposefully do NOT call window.location.reload() here.
                                // The new Service Worker will take over on the next navigation naturally,
                                // avoiding the "reload 2-3 times" bug caused by free hosting code injections.
                            });
                        } catch (e) {}
                    })
                    .catch(err => console.log('SW error:', err));
            });
        }
    </script>


    <!-- OneSignal SDK for Web Push -->
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      // Chỉ khởi tạo OneSignal trên môi trường production để tránh lỗi domain trên localhost
      const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
      if (!isLocalhost) {
        OneSignalDeferred.push(async function(OneSignal) {
          await OneSignal.init({
            appId: "b8fdf8dc-ae62-4636-96e3-d52edaef7bd3",
            serviceWorkerPath: 'sw.php',
            notifyButton: {
              enable: true, size: 'medium', position: 'bottom-left',
              text: {
                  'tip.state.unsubscribed': 'Đăng ký nhận tin trọ mới',
                  'tip.state.subscribed': "Bạn đã đăng ký nhận tin",
                  'tip.state.blocked': "Bạn đã chặn thông báo",
                  'message.prenotify': 'Nhấn để đăng ký nhận thông báo khi có phòng trọ mới!',
                  'message.action.subscribed': "Cảm ơn bạn đã đăng ký!",
                  'message.action.resubscribed': "Bạn đã đăng ký lại thành công",
                  'message.action.unsubscribed': "Bạn đã hủy đăng ký",
                  'dialog.main.title': 'Quản lý thông báo',
                  'dialog.main.button.subscribe': 'ĐĂNG KÝ',
                  'dialog.main.button.unsubscribe': 'HỦY ĐĂNG KÝ',
                  'dialog.blocked.title': 'Mở khóa thông báo',
                  'dialog.blocked.message': "Vui lòng làm theo hướng dẫn để mở lại thông báo."
              }
            }
          });

          // Gán nhãn cho người dùng để gửi thông báo đúng đối tượng (Admin / User)
          <?php if(isset($_SESSION['user_id'])): ?>
              OneSignal.login("<?= $_SESSION['user_id'] ?>");
              <?php if(isset($_SESSION['role'])): ?>
                  if (OneSignal.User) OneSignal.User.addTag("role", "<?= $_SESSION['role'] ?>");
              <?php endif; ?>
          <?php else: ?>
              OneSignal.logout();
              if (OneSignal.User) OneSignal.User.removeTag("role");
          <?php endif; ?>
        });
      } else {
        console.log('[OneSignal] Bỏ qua khởi tạo OneSignal trên môi trường localhost.');
      }
    </script>
</head>
<style>
/* Mobile Nav Fix: Ensure dropdowns don't overlap awkwardly with elements */
@media (max-width: 768px) {
    .user-menu .dropdown {
        position: absolute !important;
        top: auto !important;
        bottom: calc(100% + 10px) !important;
        right: 0 !important;
        box-shadow: 0 -8px 24px rgba(0,0,0,0.18) !important;
        background: linear-gradient(150deg, rgba(255,255,255,.72), rgba(223,247,236,.48)) !important;
        backdrop-filter: blur(18px) saturate(155%) !important;
        -webkit-backdrop-filter: blur(18px) saturate(155%) !important;
        padding: 8px !important;
        width: 220px !important;
        border-radius: 14px 14px 0 14px !important;
        z-index: 2200 !important;
        display: none; /* JS will toggle this to flex */
        flex-direction: column-reverse !important; /* Đảo ngược để dễ thao tác */
    }
    .user-menu .dropdown.show-mobile {
        display: flex !important;
    }
    /* Căn giữa chuông trên mobile */
    .notif-container {
        margin: 0 auto;
    }
}

/* Đảm bảo trên desktop chuông nằm cạnh menu phải */
@media (min-width: 769px) {
    .navbar {
        justify-content: flex-start;
        gap: 15px;
    }
    .nav-menu {
        margin-left: auto;
    }
    .notif-container {
        order: 3;
    }
}
</style>
<?php $current_page_slug = pathinfo(basename($_SERVER['PHP_SELF']), PATHINFO_FILENAME); ?>
<body class="page-<?= htmlspecialchars($current_page_slug, ENT_QUOTES, 'UTF-8') ?>" data-user-id="<?= $_SESSION['user_id'] ?? '' ?>">
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo" style="text-decoration: none;">
                    <img src="assets/images/logo.png" alt="Mái Nhà Xanh" onerror="this.style.display='none'">
                    <span>Mái Nhà Xanh</span>
                </a>

                <?php if(isset($_SESSION['username'])): ?>
                    <!-- Chuông thông báo (Centered on mobile) -->
                    <div class="notif-container" style="position:relative;" id="notif-bell-li">
                        <a href="#" id="notif-bell"
                           style="display:flex;align-items:center;gap:4px;font-weight:600;color:var(--dark-color);
                                  position:relative;padding:0 10px;border-radius:8px;
                                  transition:transform .2s,color .2s; height:40px;"
                           title="Thông báo">
                            <i class="fas fa-bell" style="font-size:1.2rem;"></i>
                            <span id="notif-badge" style="display:none; position:absolute; top:-6px; right:-6px; background:#ef4444; color:#fff; font-size:0.75rem; font-weight:700; min-width:22px; height:22px; border-radius:50%; align-items:center; justify-content:center; padding:0 6px; border:2px solid #fff; box-shadow:0 2px 5px rgba(0,0,0,0.2); z-index:99; line-height:1;">0</span>
                        </a>
                        <!-- Dropdown thông báo -->
                        <div id="notif-dropdown"
                             style="display:none;position:absolute;top:calc(100% + 10px);right:-10px;
                                    width:330px;background:#fff;border-radius:14px;
                                    box-shadow:0 12px 40px rgba(0,0,0,.18);z-index:2000;overflow:hidden;">
                            <div class="notif-header-area"
                                 style="display:flex; flex-direction:column; gap:12px; padding:20px 16px; 
                                        border-bottom:1px solid #f1f5f9;
                                        background:linear-gradient(135deg,#10b981,#059669);">
                                <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                    <span style="font-weight:800; font-size:1.05rem; color:#fff; white-space:nowrap; letter-spacing:0.5px;">
                                        <i class="fas fa-bell" style="margin-right:8px; font-size:1.1rem;"></i>Thông báo
                                    </span>
                                    <button onclick="if(typeof NotifUI !== 'undefined') NotifUI.close(); else { const nd=document.getElementById('notif-dropdown'); nd.classList.remove('show-mobile'); nd.style.display='none'; } event.stopPropagation();"
                                            class="mobile-only-close"
                                            style="display:none; background:rgba(255,255,255,0.2); border:none; color:#fff;
                                                   font-size:0.9rem; cursor:pointer; width:26px; height:26px; border-radius:50%;
                                                   align-items:center; justify-content:center; flex-shrink:0;">
                                        ✕
                                    </button>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <button onclick="NotifSystem.markAllRead()"
                                            style="flex:1; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); 
                                                   color:#fff; font-size:0.75rem; cursor:pointer; font-weight:700;
                                                   padding:6px 10px; border-radius:8px; white-space:nowrap;
                                                   transition: all 0.2s ease; display:flex; align-items:center; justify-content:center; gap:4px;"
                                            onmouseover="this.style.background='rgba(255,255,255,0.25)'"
                                            onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                                        <i class="fas fa-check-double"></i> Đọc hết
                                    </button>
                                    <button onclick="NotifSystem.deleteAllNotifs()"
                                            style="flex:1; background:rgba(239,68,68,0.25); border:1px solid rgba(255,255,255,0.2); 
                                                   color:#fff; font-size:0.75rem; cursor:pointer; font-weight:700;
                                                   padding:6px 10px; border-radius:8px; white-space:nowrap;
                                                   transition: all 0.2s ease; display:flex; align-items:center; justify-content:center; gap:4px;"
                                            onmouseover="this.style.background='rgba(239,68,68,0.4)'"
                                            onmouseout="this.style.background='rgba(239,68,68,0.25)'">
                                        <i class="fas fa-trash-alt"></i> Xoá hết
                                    </button>
                                </div>
                            </div>
                            <div id="notif-list" style="max-height:340px;overflow-y:auto;"></div>
                            <div style="padding:10px 16px;border-top:1px solid #f1f5f9;text-align:center;">
                                <a href="bai-dang-cua-toi.php"
                                   style="color:#10b981;font-size:.85rem;font-weight:600;text-decoration:none;">
                                    Xem bài đăng của tôi →
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <ul class="nav-menu" id="nav-menu">
                    <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Trang chủ</a></li>
                    <li><a href="phong-tro.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'phong-tro.php' ? 'active' : ''; ?>">Phòng trọ</a></li>
                    <li><a href="gioi-thieu.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'gioi-thieu.php' ? 'active' : ''; ?>">Giới thiệu</a></li>
                    <li><a href="lien-he.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'lien-he.php' ? 'active' : ''; ?>">Liên hệ</a></li>
                    <li><a href="cong-dong.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'cong-dong.php' ? 'active' : ''; ?>">Cộng đồng</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li>
                            <a href="tin-nhan.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tin-nhan.php' ? 'active' : ''; ?>" style="position: relative; display: inline-flex; align-items: center; gap: 6px;">
                                Tin nhắn
                                <span id="global-msg-badge" style="display: none; background: #ef4444; color: #fff; font-size: 0.68rem; font-weight: 800; border-radius: 50%; width: 16px; height: 16px; align-items: center; justify-content: center; line-height: 1; flex-shrink: 0;">0</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if(isset($_SESSION['username'])): ?>


                        <!-- Menu tài khoản -->
                        <li class="user-menu" style="position: relative;">
                            <a href="#" style="font-weight: 600; color: var(--primary-color); display: flex; align-items: center; gap: 6px; padding: 0 10px; height: 36px; border-radius: 5px; transition: color 0.3s; white-space: nowrap; font-size: 14px;">
                                <?php if (!empty($_SESSION['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--primary-color, #10b981); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: bold;">
                                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <?php echo htmlspecialchars(!empty($_SESSION['hoten']) ? $_SESSION['hoten'] : $_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown"
                                style="display:none; flex-direction: column; position:absolute; top:100%; right:0; background:#fff;
                                       box-shadow:0 8px 24px rgba(0,0,0,.12); padding:8px; border-radius:10px;
                                       min-width:180px; z-index:1001; list-style:none;">
                                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <li><a href="admin/index.php" target="_blank"><i class="fas fa-tachometer-alt"></i> Trang Quản trị</a></li>
                                <?php endif; ?>
                                <li><a href="#" onclick="document.getElementById('headerAvatarInput').click(); return false;"><i class="fas fa-camera"></i> Đổi ảnh đại diện</a></li>
                                <input type="file" id="headerAvatarInput" accept="image/jpeg, image/png, image/webp" style="display: none;" onchange="uploadHeaderAvatar(this)">
                                <li><a href="#" onclick="changeDisplayName(); return false;"><i class="fas fa-edit"></i> Đổi họ và tên</a></li>
                                
                                <!-- <li style="display:none;"><a href="dang-bai.php"><i class="fas fa-plus-circle"></i> Đăng bài</a></li> -->
                                <li><a href="tin-nhan.php"><i class="fas fa-comments"></i> Tin nhắn & Gọi điện</a></li>
                                <li><a href="bai-dang-cua-toi.php"><i class="fas fa-list"></i> Bài đăng của tôi</a></li>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
                            </ul>
                            
                            <script>
                            async function uploadHeaderAvatar(input) {
                                if (!input.files || input.files.length === 0) return;
                                const file = input.files[0];
                                
                                const fd = new FormData();
                                fd.append('avatar', file);

                                // CSRF token (API yêu cầu)
                                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
                                if (csrfToken) fd.append('csrf_token', csrfToken);

                                try {
                                    const res = await fetch('api/upload_avatar.php', {
                                        method: 'POST',
                                        body: fd
                                    });
                                    const data = await res.json();
                                    if (data.success) {
                                        window.location.reload();
                                    } else {
                                        alert(data.message || 'Lỗi tải lên!');
                                    }
                                } catch(e) {
                                    alert('Lỗi kết nối. Vui lòng thử lại.');
                                }
                            }

                            async function changeDisplayName() {
                                let currentName = "<?= htmlspecialchars($_SESSION['hoten'] ?? $_SESSION['username'] ?? '') ?>";
                                let newName = '';
                                
                                if (typeof Swal !== 'undefined') {
                                    const { value: formValues } = await Swal.fire({
                                        title: 'Đổi họ và tên',
                                        input: 'text',
                                        inputLabel: 'Họ và tên mới',
                                        inputValue: currentName,
                                        showCancelButton: true,
                                        confirmButtonColor: '#10b981',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Lưu',
                                        cancelButtonText: 'Hủy',
                                        inputValidator: (value) => {
                                            if (!value || !value.trim()) {
                                                return 'Họ tên không được để trống!';
                                            }
                                            if (value.length > 100) {
                                                return 'Họ tên không quá 100 ký tự!';
                                            }
                                        }
                                    });
                                    newName = formValues;
                                } else {
                                    const val = prompt('Nhập họ và tên mới của bạn:', currentName);
                                    if (val === null) return;
                                    if (!val.trim()) {
                                        alert('Họ tên không được để trống!');
                                        return;
                                    }
                                    newName = val.trim();
                                }
                                
                                if (newName) {
                                    try {
                                        const fd = new FormData();
                                        fd.append('hoten', newName);
                                        const res = await fetch('api/update_name.php', {
                                            method: 'POST',
                                            body: fd
                                        });
                                        const data = await res.json();
                                        if (data.success) {
                                            if (typeof Swal !== 'undefined') {
                                                await Swal.fire({
                                                    icon: 'success',
                                                    title: 'Thành công',
                                                    text: data.message,
                                                    timer: 1500,
                                                    showConfirmButton: false
                                                });
                                            } else {
                                                alert(data.message);
                                            }
                                            window.location.reload();
                                        } else {
                                            if (typeof Swal !== 'undefined') {
                                                Swal.fire('Lỗi', data.message || 'Lỗi cập nhật họ tên', 'error');
                                            } else {
                                                alert(data.message || 'Lỗi cập nhật họ tên');
                                            }
                                        }
                                    } catch(e) {
                                        if (typeof Swal !== 'undefined') {
                                            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
                                        } else {
                                            alert('Không thể kết nối máy chủ.');
                                        }
                                    }
                                }
                            }
                            </script>
                        </li>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const userMenu = document.querySelector('.user-menu');
                            const userDropdown = document.querySelector('.user-menu .dropdown');
                            const notifBell = document.getElementById('notif-bell');
                            const notifDropdown = document.getElementById('notif-dropdown');
                            const hamburger = document.getElementById('hamburger');
                            const navMenu = document.getElementById('nav-menu');

                            // Đối tượng điều khiển UI thông báo tập trung
                            window.NotifUI = {
                                close: function() {
                                    if (notifDropdown) {
                                        notifDropdown.classList.remove('show-mobile');
                                        notifDropdown.style.display = 'none';
                                    }
                                },
                                open: function() {
                                    if (notifDropdown) {
                                        if (window.innerWidth <= 768) {
                                            notifDropdown.classList.add('show-mobile');
                                            notifDropdown.style.display = 'block';
                                        } else {
                                            notifDropdown.style.display = 'block';
                                        }
                                        if (typeof NotifSystem !== 'undefined') NotifSystem.loadAll({ markViewed: true });
                                    }
                                },
                                toggle: function() {
                                    const isOpen = notifDropdown.classList.contains('show-mobile') || notifDropdown.style.display === 'block';
                                    if (isOpen) this.close();
                                    else this.open();
                                }
                            };

                            // Toggle menu chính (hamburger)
                            if (hamburger && navMenu) {
                                hamburger.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    if (userDropdown) userDropdown.style.display = 'none';
                                    if (typeof NotifUI !== 'undefined') NotifUI.close();
                                });
                            }

                            // Toggle user dropdown
                            if (userMenu && userDropdown) {
                                userMenu.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    const isOpen = userDropdown.style.display === 'flex';
                                    userDropdown.style.display = isOpen ? 'none' : 'flex';
                                    if (typeof NotifUI !== 'undefined') NotifUI.close();
                                });
                            }

                            // Toggle notification dropdown
                            if (notifBell && notifDropdown) {
                                notifBell.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    
                                    const isOpen = notifDropdown.classList.contains('show-mobile') || notifDropdown.style.display === 'block';

                                    if (!isOpen) {
                                        NotifUI.open();
                                        const badge = document.getElementById('notif-badge');
                                        if (badge) badge.style.display = 'none';
                                    } else {
                                        NotifUI.close();
                                    }

                                    if (userDropdown) userDropdown.style.display = 'none';
                                });
                            }

                            // Ngăn chặn đóng khi click bên trong dropdown (Persistent)
                            [userDropdown, notifDropdown].forEach(el => {
                                if (el) {
                                    el.addEventListener('click', (e) => e.stopPropagation());
                                }
                            });

                            // Đóng dropdown khi click ra ngoài
                            document.addEventListener('click', function(e) {
                                if (!e.target.closest('.user-menu') && userDropdown) {
                                    userDropdown.style.display = 'none';
                                }
                                
                                if (notifDropdown) {
                                    if (document.querySelector('.swal2-container') || e.target.closest('.swal2-container')) {
                                        return;
                                    }

                                    const isBellClick = e.target.closest('#notif-bell-li');
                                    const isDropdownClick = e.target.closest('#notif-dropdown');
                                    
                                    if (window.innerWidth > 1024) {
                                        if (!isBellClick && !isDropdownClick) {
                                            NotifUI.close();
                                        }
                                    }
                                }

                                if (window.innerWidth <= 768 && navMenu) {
                                    if (!e.target.closest('#nav-menu') && !e.target.closest('#hamburger')) {
                                        navMenu.classList.remove('active');
                                    }
                                }
                            });


                        });
                        </script>

                    <?php else: ?>
                        <li><a href="login.php" class="btn-login-nav" style="border: 1px solid var(--primary-color); padding: 5px 15px; border-radius: 20px;">Đăng nhập</a></li>
                    <?php endif; ?>
                    <li style="display: flex; align-items: center; justify-content: center; padding: 0 10px;">
                        <button id="theme-toggle-btn" class="theme-toggle-btn" title="Chuyển đổi giao diện" style="background: none; border: none; cursor: pointer; color: var(--dark-color); font-size: 1.2rem; display: flex; align-items: center; justify-content: center; height: 36px; width: 36px; border-radius: 50%; transition: background 0.3s; padding: 0;">
                            <i class="fas fa-moon"></i>
                        </button>
                    </li>
                </ul>

                <div class="hamburger" id="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </nav>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Theme Toggle
            const themeBtn = document.getElementById("theme-toggle-btn");
            if (themeBtn) {
                const updateThemeIcon = (theme) => {
                    const icon = themeBtn.querySelector("i");
                    if (icon) {
                        if (theme === "dark") {
                            icon.className = "fas fa-sun";
                            icon.style.color = "#fbbf24";
                        } else {
                            icon.className = "fas fa-moon";
                            icon.style.color = "";
                        }
                    }
                };
                
                const initTheme = document.documentElement.getAttribute("data-theme") || "light";
                updateThemeIcon(initTheme);

                themeBtn.addEventListener("click", function(e) {
                    const isDark = document.documentElement.getAttribute("data-theme") === "dark";
                    const newTheme = isDark ? "light" : "dark";

                    if (document.startViewTransition) {
                        const x = e.clientX || window.innerWidth / 2;
                        const y = e.clientY || window.innerHeight / 2;
                        const endRadius = Math.hypot(
                            Math.max(x, window.innerWidth - x),
                            Math.max(y, window.innerHeight - y)
                        );

                        const transition = document.startViewTransition(() => {
                            document.documentElement.setAttribute("data-theme", newTheme);
                            localStorage.setItem("theme", newTheme);
                            updateThemeIcon(newTheme);
                        });

                        transition.ready.then(() => {
                            document.documentElement.animate(
                                {
                                    clipPath: [
                                        `circle(0px at ${x}px ${y}px)`,
                                        `circle(${endRadius}px at ${x}px ${y}px)`
                                    ]
                                },
                                {
                                    duration: 450,
                                    easing: "ease-in-out",
                                    pseudoElement: "::view-transition-new(root)"
                                }
                            );
                        });
                    } else {
                        document.documentElement.setAttribute("data-theme", newTheme);
                        localStorage.setItem("theme", newTheme);
                        updateThemeIcon(newTheme);
                    }
                });
            }
        });
        </script>
    </header>

    <?php if (in_array(basename($_SERVER['PHP_SELF']), ['index.php', 'index.html'])): ?>
    <!-- ═══════════════════════════════════════════ -->
    <!-- PWA Install Banner                          -->
    <!-- ═══════════════════════════════════════════ -->
    <style>
    @keyframes slideUpBanner {
        from { transform: translateY(100%); opacity: 0; }
        to   { transform: translateY(0);   opacity: 1; }
    }
    @keyframes popIn {
        from { transform: scale(0.6); opacity: 0; }
        to   { transform: scale(1);   opacity: 1; }
    }

    /* ── Full Banner ── */
    #pwa-install-banner {
        display: none;
        position: fixed;
        bottom: 0; left: 0; right: 0;
        z-index: 9999;
        padding: 12px 16px;
        background: linear-gradient(135deg, #064e3b 0%, #0f172a 100%);
        border-top: 1px solid rgba(16,185,129,0.4);
        box-shadow: 0 -8px 32px rgba(0,0,0,0.35);
        animation: slideUpBanner 0.4s cubic-bezier(0.34,1.56,0.64,1);
    }
    #pwa-install-banner .pwa-inner {
        max-width: 600px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    #pwa-install-banner .pwa-icon-img {
        width: 52px; height: 52px;
        border-radius: 14px;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    #pwa-install-banner .pwa-text { flex: 1; }
    #pwa-install-banner .pwa-text strong {
        display: block;
        color: #fff;
        font-size: 0.98rem;
        font-weight: 700;
    }
    #pwa-install-banner .pwa-text span {
        color: rgba(255,255,255,0.6);
        font-size: 0.8rem;
    }
    #pwa-install-banner .pwa-actions { display: flex; gap: 8px; flex-shrink: 0; }
    #pwa-btn-install {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 50px;
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        box-shadow: 0 4px 14px rgba(16,185,129,0.45);
        transition: all 0.2s;
    }
    #pwa-btn-install:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(16,185,129,0.55); }
    #pwa-btn-dismiss {
        background: transparent;
        color: rgba(255,255,255,0.5);
        border: 1px solid rgba(255,255,255,0.2);
        padding: 10px 14px;
        border-radius: 50px;
        font-size: 0.82rem;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s;
    }
    #pwa-btn-dismiss:hover { color: #fff; border-color: rgba(255,255,255,0.5); }

    /* ── Mini floating badge (sau khi bỏ qua) ── */
    #pwa-mini-badge {
        display: none;
        position: fixed;
        bottom: 20px;
        right: 16px;
        z-index: 9999;
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        border: none;
        border-radius: 50px;
        padding: 10px 16px;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        gap: 8px;
        align-items: center;
        box-shadow: 0 6px 20px rgba(16,185,129,0.5);
        animation: popIn 0.35s cubic-bezier(0.34,1.56,0.64,1);
        transition: transform 0.2s, box-shadow 0.2s;
        white-space: nowrap;
    }
    #pwa-mini-badge:hover {
        transform: translateY(-3px) scale(1.04);
        box-shadow: 0 10px 28px rgba(16,185,129,0.6);
    }
    #pwa-mini-badge .pwa-mini-icon {
        width: 22px; height: 22px;
        border-radius: 6px;
        flex-shrink: 0;
    }
    
    @media (max-width: 768px) {
        #pwa-install-banner { bottom: 65px; } /* Tránh đè lên bottom navigation trên mobile */
        #pwa-mini-badge { bottom: 75px; }
    }
    </style>

    <!-- Full Banner -->
    <div id="pwa-install-banner">
        <div class="pwa-inner">
            <img class="pwa-icon-img" src="assets/images/myhome.png" alt="App Icon" onerror="this.src='assets/images/logo.png'">
            <div class="pwa-text">
                <strong>📲 Cài Mái Nhà Xanh lên máy</strong>
                <span>Truy cập nhanh hơn, dùng được offline!</span>
            </div>
            <div class="pwa-actions">
                <button id="pwa-btn-install">Cài đặt</button>
                <button id="pwa-btn-dismiss">Bỏ qua</button>
            </div>
        </div>
    </div>

    <!-- Mini Badge (hiện khi người dùng bỏ qua banner đầy đủ) -->
    <button id="pwa-mini-badge" title="Cài Mái Nhà Xanh">
        <img class="pwa-mini-icon" src="assets/images/myhome.png" alt="" onerror="this.src='assets/images/logo.png'">
        📲 Cài đặt app
    </button>

    <script>
    (function() {
        let deferredPrompt = null;
        const banner      = document.getElementById('pwa-install-banner');
        const miniBadge   = document.getElementById('pwa-mini-badge');
        const btnInstall  = document.getElementById('pwa-btn-install');
        const btnDismiss  = document.getElementById('pwa-btn-dismiss');

        // Helper localStorage an toàn
        const store = {
            get: function(key) {
                try { return localStorage.getItem(key); } catch(e) { return null; }
            },
            set: function(key, val) {
                try { localStorage.setItem(key, val); } catch(e) {}
            }
        };

        // Nếu đang chạy standalone (đã cài) → ẩn hết và thoát
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                             || window.navigator.standalone === true;
        if (isStandalone) return;

        // Nếu đã đánh dấu installed (localStorage) → thoát
        if (store.get('pwa-installed') === '1') return;

        // ── Hiện full banner sau 1.5 giây ──
        // KHÔNG lưu trạng thái dismissed → mỗi lần load lại trang đều hiện lại
        setTimeout(function() {
            if (banner) banner.style.display = 'block';
        }, 1500);

        // ── Khi Chrome sẵn sàng kích hoạt cài đặt ──
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            if (banner) banner.style.display = 'block';
        });

        // ── Hàm kích hoạt cài đặt native ──
        async function triggerInstall() {
            if (!deferredPrompt) {
                showManualGuide();
                return;
            }
            try {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                if (banner)    banner.style.display    = 'none';
                if (miniBadge) miniBadge.style.display = 'none';
                if (outcome === 'accepted') {
                    store.set('pwa-installed', '1');
                    // Hiện thông báo thành công
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '🎉 Cài đặt thành công!',
                            text: 'Mái Nhà Xanh đã được thêm vào màn hình của bạn.',
                            confirmButtonColor: '#10b981',
                            confirmButtonText: 'Tuyệt vời!',
                            timer: 3000,
                            timerProgressBar: true
                        });
                    }
                }
            } catch(e) {}
        }

        // Hướng dẫn cài thủ công (khi chưa có prompt native)
        function showManualGuide() {
            const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
            const msg = isIOS
                ? '📱 Để cài app:\n1. Nhấn nút Chia sẻ (⬆) ở Safari\n2. Chọn "Thêm vào Màn hình chính"'
                : '📲 Để cài app:\n1. Nhấn vào biểu tượng ⊕ hoặc ⋮ trên thanh địa chỉ Chrome\n2. Chọn "Cài đặt ứng dụng"';
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'info', title: 'Cài đặt App', text: msg,
                    confirmButtonColor: '#10b981', confirmButtonText: 'OK' });
            } else {
                alert(msg);
            }
        }

        // Nút Cài đặt
        if (btnInstall) btnInstall.addEventListener('click', triggerInstall);

        // Nút Bỏ qua → chỉ ẩn banner NGAY LÚC NÀY, KHÔNG lưu gì vào storage
        // → Load lại trang: JS chạy lại từ đầu, banner sẽ hiện lại sau 1.5s
        if (btnDismiss) {
            btnDismiss.addEventListener('click', function() {
                if (banner) {
                    banner.style.transition = 'opacity 0.3s, transform 0.3s';
                    banner.style.opacity = '0';
                    banner.style.transform = 'translateY(100%)';
                    setTimeout(function() { banner.style.display = 'none'; }, 300);
                }
                if (miniBadge) miniBadge.style.display = 'none';
            });
        }

        // Sau khi cài xong → ẩn tất cả
        window.addEventListener('appinstalled', function() {
            if (banner)    banner.style.display    = 'none';
            if (miniBadge) miniBadge.style.display = 'none';
            store.set('pwa-installed', '1');
        });
    })();
    </script>
    <?php endif; ?>

<?php if (isset($_SESSION['user_id'])): ?>
    <!-- Real-time communication dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/socket.io-client@4.7.2/dist/socket.io.min.js"></script>
    <script src="https://unpkg.com/@zegocloud/zego-uikit-prebuilt/zego-uikit-prebuilt.js"></script>
    <!-- Hidden inputs for active user profile -->
    <input type="hidden" id="current-user-id" value="<?php echo $_SESSION['user_id']; ?>">
    <input type="hidden" id="current-user-name" value="<?php echo htmlspecialchars($_SESSION['hoten'] ?? $_SESSION['username'] ?? ''); ?>">
    <script>
        // Cấu hình URL của Socket.io Server từ file .env
        window.REALTIME_SERVER_URL = "<?= $_ENV['REALTIME_SERVER_URL'] ?? getenv('REALTIME_SERVER_URL') ?? '' ?>";
    </script>
    <!-- Custom realtime scripts -->
    <script src="assets/js/tin-nhan-dong-bo.js?v=<?= time() ?>"></script>
    <script src="assets/js/call-webrtc.js?v=<?= time() ?>"></script>
<?php endif; ?>

