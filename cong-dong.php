<?php
require_once 'config/bootstrap.php';
requireLogin(); // User must be logged in

// Hide floating post button on community page (has dedicated composer)
?>
<style>
.floating-post-btn { display: none !important; }
</style>
<?php
$page_title = "Cộng đồng";
include 'includes/header.php';
?>

<!-- ── Community Hero Banner ───────────────────────────── -->
<section class="comm-hero-section">
    <div class="hero-bg-layer"></div>
    <div class="hero-glow-circle hero-glow-1"></div>
    <div class="hero-glow-circle hero-glow-2"></div>
    <div class="container" style="position:relative;z-index:2;">
        <div class="comm-hero-badge"><i class="fas fa-users"></i> Cộng đồng</div>
        <h2 class="typing-effect comm-hero-title">Cộng đồng Mái Nhà Xanh</h2>
        <p class="comm-hero-sub">Chia sẻ thông tin, đánh giá phòng trọ và kết nối với mọi người.</p>
        <div class="comm-hero-stats">
            <div class="comm-stat-pill"><i class="fas fa-fire-alt"></i> Đang hoạt động</div>
            <div class="comm-stat-pill"><i class="fas fa-comments"></i> Thảo luận mọi lúc</div>
            <div class="comm-stat-pill"><i class="fas fa-shield-alt"></i> An toàn & Văn minh</div>
        </div>
    </div>
</section>

<style>
/* Community Hero Overrides (if any) */
.comm-hero-section {
    padding: 100px 0 36px;
}
.comm-hero-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.4);
    color: #6ee7b7; font-size: .78rem; font-weight: 700;
    padding: 5px 14px; border-radius: 30px; margin-bottom: 14px;
    backdrop-filter: blur(8px); letter-spacing: .5px; text-transform: uppercase;
}
.comm-hero-title {
    font-size: 2rem; color: #fff !important; font-weight: 800; margin: 0 0 10px;
    text-shadow:
        0 0 30px rgba(110,231,183,0.6),
        0 4px 20px rgba(0,0,0,0.4),
        0 0 60px rgba(16,185,129,0.3);
    display: block !important;
}
.comm-hero-sub {
    font-size: .95rem; color: rgba(255,255,255,0.75);
    margin: 0 0 20px; line-height: 1.6;
}
.comm-hero-stats {
    display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;
}
.comm-stat-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.8); font-size: .8rem; font-weight: 600;
    padding: 6px 14px; border-radius: 30px; backdrop-filter: blur(8px);
    transition: all .3s;
}
.comm-stat-pill:hover {
    background: rgba(255,255,255,0.16); border-color: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}
</style>



<style>
/* ── Community Styles ─────────────────────────────────────── */
.community-section {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 10% 8%, rgba(16,185,129,0.22), transparent 34%),
        radial-gradient(circle at 88% 14%, rgba(20,184,166,0.18), transparent 30%),
        linear-gradient(180deg, #effaf3 0%, #e9f6ef 52%, #deeee6 100%);
    min-height: 100vh;
}
.comm-post-card {
    background:
        linear-gradient(150deg, rgba(255,255,255,0.24), transparent 62%),
        linear-gradient(155deg, rgba(255,255,255,0.62) 0%, rgba(233,251,242,0.45) 42%, rgba(207,242,225,0.38) 100%);
    border-radius: 20px;
    border: 1px solid rgba(21,199,136,0.2);
    padding: 22px;
    box-shadow:
        0 24px 44px rgba(6,60,45,0.14),
        0 10px 20px rgba(12,90,66,0.1),
        0 1px 0 rgba(255,255,255,0.5) inset,
        0 -1px 0 rgba(21,199,136,0.1) inset;
    margin-bottom: 20px;
    transition: transform .25s ease, box-shadow .25s ease;
    position: relative;
    overflow: hidden;
    animation: floatCard 7s ease-in-out infinite;
    backdrop-filter: blur(18px) saturate(155%);
    -webkit-backdrop-filter: blur(18px) saturate(155%);
}
.comm-post-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #10b981, #3b82f6, #8b5cf6);
    border-radius: 20px 20px 0 0;
}
.comm-post-card:hover {
    transform: translateY(-4px);
    box-shadow:
        0 28px 48px rgba(6,60,45,0.16),
        0 0 24px rgba(34,197,94,0.2);
}

@media (min-width: 992px) {
    .community-section > .container,
    .community-section .container {
        max-width: 1280px !important;
        width: min(1280px, calc(100% - 40px)) !important;
    }

    .comm-create-card,
    .comm-post-card {
        border-radius: 18px;
        animation: floatCard 7s ease-in-out infinite !important;
    }


    .comm-create-card {
        display: block;
        overflow: hidden;
    }

    .comm-create-body {
        padding: 22px 24px 16px;
        gap: 16px;
    }

    .comm-create-card textarea {
        min-height: 96px !important;
    }

    .comm-create-footer {
        width: 100%;
        border-top: 1px solid #f1f5f9;
        border-left: 0;
        background: linear-gradient(150deg, rgba(255,255,255,0.52), rgba(224,246,236,0.35));
        display: flex;
        flex-direction: row;
        justify-content: center;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
    }

    .comm-create-footer > div {
        display: flex;
        gap: 8px;
        justify-content: flex-start;
        align-items: center;
        flex: 1 1 auto;
    }

    .comm-create-footer .comm-media-btn {
        flex: 0 0 auto;
    }

    #postSubmitBtn {
        width: auto !important;
        padding: 0 18px !important;
        margin-left: auto;
        height: 40px !important;
        border-radius: 8px !important;
        box-shadow: none !important;
        transform: none !important;
        align-self: center;
    }
}

.comm-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #06b5f0);
    color: #fff; display: flex; align-items: center;
    justify-content: center; font-size: 1.1rem; font-weight: 700;
    flex-shrink: 0;
}
.comm-avatar-sm {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff; display: flex; align-items: center;
    justify-content: center; font-size: .8rem; font-weight: 700;
    flex-shrink: 0;
}
.comm-avatar-xs {
    width: 26px; height: 26px; border-radius: 50%;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    color: #fff; display: flex; align-items: center;
    justify-content: center; font-size: .65rem; font-weight: 700;
    flex-shrink: 0;
}

/* Media tools in comment box */
.comm-media-btn {
    background: none; border: none; cursor: pointer;
    width: 34px; height: 34px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #64748b; font-size: 1rem; transition: background .2s, color .2s;
}
.comm-media-btn:hover {
    background: linear-gradient(150deg, rgba(255,255,255,0.62), rgba(224,246,236,0.35));
    color: #10b981;
}

/* Image grid */
.comm-img-grid {
    display: grid; gap: 4px; margin-top: 8px; border-radius: 10px; overflow: hidden;
}
.comm-img-grid.n1 { grid-template-columns: 1fr; }
.comm-img-grid.n2 { grid-template-columns: 1fr 1fr; }
.comm-img-grid.n3 { grid-template-columns: 1fr 1fr 1fr; }
.comm-img-grid img {
    width: 100%; aspect-ratio: 1/1; object-fit: cover;
    cursor: pointer; transition: opacity .2s;
}
.comm-img-grid img:hover { opacity: .88; }
.n1 img { aspect-ratio: 16/9; border-radius: 10px; }

/* Lightbox */
#comm-lightbox {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.92); z-index: 99999;
    align-items: center; justify-content: center;
}
#comm-lightbox.open { display: flex; }
#comm-lightbox img {
    max-width: 92vw; max-height: 88vh; border-radius: 10px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
}
#comm-lightbox-close {
    position: absolute; top: 18px; right: 22px;
    background: rgba(255,255,255,.15); border: none; color: #fff;
    font-size: 1.5rem; width: 42px; height: 42px; border-radius: 50%;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background .2s;
}
#comm-lightbox-close:hover { background: rgba(255,255,255,.3); }

/* Media preview strip */
.comm-preview-strip {
    display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;
}
.comm-preview-item {
    position: relative; width: 72px; height: 72px;
    border-radius: 8px; overflow: hidden;
    background: linear-gradient(150deg, rgba(255,255,255,0.62), rgba(224,246,236,0.35));
    border: 1px solid rgba(21,199,136,0.15);
}
.comm-preview-item img, .comm-preview-item video {
    width: 100%; height: 100%; object-fit: cover;
}
.comm-preview-remove {
    position: absolute; top: 2px; right: 2px;
    background: rgba(0,0,0,.55); border: none; color: #fff;
    font-size: .65rem; width: 18px; height: 18px; border-radius: 50%;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}

/* Reply badge */
.comm-reply-badge {
    background: linear-gradient(150deg, rgba(255,255,255,0.55), rgba(225,247,236,0.35));
    border: 1px solid rgba(21,199,136,0.2);
    border-radius: 8px; padding: 5px 10px;
    font-size: .82rem; color: #059669; font-weight: 600;
    display: flex; align-items: center; gap: 6px; margin-bottom: 6px;
    flex-wrap: wrap; /* Cho phép xuống dòng nếu tên dài */
}
.comm-reply-badge button {
    background: none; border: none; color: #94a3b8; cursor: pointer;
    font-size: .9rem; line-height: 1; padding: 0; margin-left: auto;
}

/* Reply indent */
.comm-reply-indent {
    border-left: 2px solid rgba(16,185,129,0.2); 
    margin-left: 14px; 
    padding-left: 12px;
    margin-bottom: 12px;
}

/* Comment Item Layout */
.comm-comment-wrapper {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
    animation: fadeIn 0.3s ease;
}

.comm-comment-bubble {
    background: linear-gradient(155deg, rgba(255,255,255,0.7), rgba(225,247,236,0.4));
    backdrop-filter: blur(10px);
    border: 1px solid rgba(21,199,136,0.15);
    padding: 8px 14px;
    border-radius: 18px; /* Bo tròn mượt mà */
    display: inline-block;
    max-width: 100%;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.comm-comment-user {
    font-weight: 700;
    font-size: 0.82rem;
    color: #1e293b;
    margin-bottom: 2px;
}

.comm-comment-text {
    font-size: 0.88rem;
    color: #334155;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}

/* Inline reply label */
.comm-reply-label {
    font-size: .78rem; color: #8b5cf6; font-weight: 600; margin-bottom: 3px;
    display: block;
}

/* Action bar under each comment */
.comm-comment-action {
    background: none; border: none; font-size: .78rem; color: #94a3b8;
    cursor: pointer; padding: 2px 4px; border-radius: 4px; transition: color .2s, background .2s;
}
.comm-comment-action:hover {
    color: #10b981;
    background: linear-gradient(150deg, rgba(255,255,255,0.56), rgba(225,247,236,0.35));
}

/* Comment box */
.comm-input-wrap {
    display: flex; flex-direction: column; gap: 4px;
    flex: 1;
}
.comm-input-row {
    display: flex; gap: 8px; align-items: flex-end;
}
.comm-text-input {
    flex: 1; border: 1.5px solid #e2e8f0; border-radius: 22px;
    padding: 9px 14px; font-size: .92rem; outline: none;
    transition: border-color .2s;
    background: linear-gradient(148deg, rgba(255,255,255,0.74), rgba(221,247,234,0.42));
    border-color: rgba(21,199,136,0.2);
    resize: none; min-height: 40px; max-height: 120px;
    font-family: inherit;
}
.comm-text-input:focus {
    border-color: #10b981;
    background: linear-gradient(148deg, rgba(255,255,255,0.82), rgba(223,247,236,0.45));
}

.comm-send-btn {
    background: linear-gradient(135deg, #10b981, #06b5f0);
    border: none; color: #fff; width: 38px; height: 38px;
    border-radius: 50%; cursor: pointer; display: flex;
    align-items: center; justify-content: center; font-size: .95rem;
    transition: transform .15s, box-shadow .15s; flex-shrink: 0;
    position: relative; z-index: 2;
}
.comm-send-btn:hover { transform: scale(1.1); box-shadow: 0 4px 12px rgba(16,185,129,.4); }

/* Keep the post submit button flat and embedded in the composer */
#postSubmitBtn,
#postSubmitBtn:hover,
#postSubmitBtn:active,
#postSubmitBtn:focus {
    transform: none !important;
    box-shadow: none !important;
}

#postSubmitBtn:hover {
    filter: brightness(0.98);
}

/* Desktop post composer: keep the button flat, not floating */
@media (min-width: 992px) {
    .comm-create-footer {
        align-items: center;
    }

    .comm-create-footer .comm-media-btn {
        background: #fff;
        border: 1px solid #e2e8f0;
    }

    #postSubmitBtn {
        background: linear-gradient(135deg, #10b981, #06b5f0) !important;
        border: 0 !important;
        color: #fff !important;
        box-shadow: none !important;
    }

    #postSubmitBtn:hover {
        transform: none !important;
        box-shadow: none !important;
    }
}

/* Post action bar */
.comm-post-actions {
    border-top: 1px solid #f1f5f9; padding-top: 12px;
    display: flex; gap: 10px;
}
.comm-post-act-btn {
    background: none; border: none; color: #64748b; font-size: .88rem;
    font-weight: 600; cursor: pointer; display: flex; align-items: center;
    gap: 6px; padding: 6px 10px; border-radius: 8px; transition: background .2s, color .2s;
}
.comm-post-act-btn:hover { background: #f0fdf4; color: #10b981; }

/* Video player in comment */
.comm-video { width: 100%; max-height: 220px; border-radius: 10px; margin-top: 8px; background: #000; }

/* Nội dung comment tránh tràn chữ */
.comment-body {
    word-break: break-word;
}

/* ── Responsive tweaks (small screens) ───────────────────────── */
@media (max-width: 520px) {
    .comm-post-card { padding: 14px; border-radius: 12px; }
    .comm-avatar    { width: 40px; height: 40px; font-size: 1rem; }
    .comm-avatar-sm { width: 30px; height: 30px; font-size: .75rem; }
    .comm-avatar-xs { width: 24px; height: 24px; font-size: .62rem; }

    .comm-post-actions { flex-wrap: wrap; gap: 6px; }
    .comm-post-act-btn { padding: 6px 8px; font-size: .85rem; }

    /* Comment composer: textarea full width, buttons wrap under */
    .comm-input-row { flex-wrap: wrap; gap: 6px; align-items: stretch; }
    .comm-text-input { flex: 0 0 100%; border-radius: 14px; min-height: 44px; }
    .comm-media-btn { width: 38px; height: 38px; border-radius: 10px; }
    .comm-send-btn  { width: 40px; height: 40px; margin-left: auto; position: relative; z-index: 2; }

    /* Post toolbar: icons + submit in one row (compact) */
    .comm-post-toolbar {
        flex-wrap: nowrap;
        gap: 10px;
        justify-content: space-between;
        margin-top: 8px !important;
        padding: 6px 8px;
        border: 1.5px solid #e2e8f0;
        border-radius: 999px;
        background: linear-gradient(150deg, rgba(255,255,255,0.62), rgba(224,246,236,0.35));
        width: 100%;
    }
    .comm-post-media { flex: 0 0 auto; gap: 4px !important; }
    .comm-post-media .comm-media-btn { width: 34px; height: 34px; border-radius: 999px; }
    .comm-post-media .comm-media-btn:hover { background: rgba(16,185,129,.10); }
    /* Unified Post Box */
    .comm-create-card {
        background:
            linear-gradient(150deg, rgba(255,255,255,0.24), transparent 62%),
            linear-gradient(155deg, rgba(255,255,255,0.62) 0%, rgba(233,251,242,0.45) 42%, rgba(207,242,225,0.38) 100%);
        border: 1px solid rgba(21,199,136,0.2);
        border-radius: 18px; 
        padding: 0; 
        box-shadow:
            0 24px 44px rgba(6,60,45,0.14),
            0 10px 20px rgba(12,90,66,0.1),
            0 1px 0 rgba(255,255,255,0.5) inset,
            0 -1px 0 rgba(21,199,136,0.1) inset;
        margin-bottom: 28px;
        overflow: hidden;
        transition: border-color .2s, box-shadow .2s;
        backdrop-filter: blur(18px) saturate(155%);
        -webkit-backdrop-filter: blur(18px) saturate(155%);
    }
    .comm-create-card:focus-within {
        border-color: #10b981;
        box-shadow: 0 4px 25px rgba(16, 185, 129, 0.08);
    }
    .comm-create-body { padding: 20px 20px 10px; display: flex; gap: 15px; }
    .comm-create-card textarea {
        border: none !important;
        padding: 5px 0 !important;
        font-size: 1.05rem !important;
        resize: none;
        background: transparent !important;
        box-shadow: none !important;
    }
    .comm-create-footer {
        background: linear-gradient(150deg, rgba(255,255,255,0.52), rgba(224,246,236,0.35));
        border-top: 1px solid #f1f5f9;
        padding: 10px 15px;
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        position: relative;
    }
    .comm-create-footer > div {
        display: flex;
        flex-wrap: nowrap;
        gap: 4px;
        align-items: center;
        flex-shrink: 0;
    }
    #postSubmitBtn {
        flex-shrink: 0;
        white-space: nowrap;
        width: auto !important;
        padding: 0 16px !important;
        height: 36px !important;
        border-radius: 20px !important;
        font-size: 0.88rem !important;
    }
    .comm-report-btn {
        background: none; border: none; color: #94a3b8; cursor: pointer;
        font-size: 0.85rem; padding: 4px 8px; border-radius: 6px;
        transition: all .2s;
    }
    .comm-report-btn:hover { background: #fee2e2; color: #ef4444; }

    #postsContainer { padding-bottom: 190px; }
}

/* Animations */
@keyframes comm-pop {
    0% { transform: scale(0.9); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes comm-pulse-green {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.comm-reply-badge { animation: comm-pop 0.3s ease-out; }
.comm-input-highlight { animation: comm-pulse-green 1.5s ease-out; }
</style>

<!-- Lightbox -->
<div id="comm-lightbox" onclick="closeLightbox()">
    <button id="comm-lightbox-close" onclick="closeLightbox()">✕</button>
    <img id="comm-lightbox-img" src="" alt="Ảnh phóng to">
</div>

<!-- ── Camera Modal ──────────────────────────────────────── -->
<div id="cam-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,.96); z-index: 99998; flex-direction: column; align-items: center; justify-content: flex-start;">
    <div id="cam-header" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: rgba(0,0,0,.4); position: absolute; top: 0; left: 0; z-index: 2;">
        <div id="cam-title" style="color: #fff; font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 8px;"><i class="fas fa-camera"></i> <span id="cam-modal-title">Máy ảnh</span></div>
        <button id="cam-close-btn" onclick="camClose()" style="background: rgba(255,255,255,.15); border: none; color: #fff; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;">✕</button>
    </div>
    <div id="cam-mode-tabs" style="position: absolute; top: 56px; left: 0; right: 0; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px;">
        <button class="cam-mode-tab active" id="tab-photo" onclick="camSetMode('photo')" style="background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; font-size: .82rem; font-weight: 700; padding: 5px 18px; border-radius: 20px; cursor: pointer;">📷 Chụp ảnh</button>
        <button class="cam-mode-tab" id="tab-video" onclick="camSetMode('video')" style="background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; font-size: .82rem; font-weight: 700; padding: 5px 18px; border-radius: 20px; cursor: pointer;">🎬 Quay video</button>
    </div>
    <div id="cam-timer" style="position: absolute; top: 58px; left: 50%; transform: translateX(-50%); display: none; background: #ef4444; color: #fff; font-size: .85rem; font-weight: 700; padding: 4px 14px; border-radius: 20px;">⏺ 00:00</div>
    <video id="cam-preview" autoplay muted playsinline style="width: 100%; height: 100vh; object-fit: cover; transform: scaleX(-1);"></video>
    <div id="cam-thumbnail-row" style="position: absolute; bottom: 130px; left: 16px; display: flex; gap: 8px; flex-wrap: wrap; max-width: 200px;"></div>
    <div id="cam-toolbar" style="position: absolute; bottom: 0; left: 0; right: 0; padding: 20px 20px 34px; background: linear-gradient(to top, rgba(0,0,0,.7) 0%, transparent 100%); display: flex; align-items: center; justify-content: space-around; gap: 20px;">
        <button id="cam-gallery-btn" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;width:48px;height:48px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-images"></i>
        </button>
        <button id="cam-shutter" onclick="camShutter()" style="width: 70px; height: 70px; border-radius: 50%; background: #fff; border: 5px solid rgba(255,255,255,.4); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #1e293b;">
            <i class="fas fa-camera" id="cam-shutter-icon"></i>
        </button>
        <button id="cam-flip-btn" onclick="camFlip()" style="background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3); color: #fff; width: 48px; height: 48px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
</div>

<section class="community-section" style="padding: 24px 0 60px 0; min-height: 60vh;">
    <div class="container community-wrap" style="max-width: 860px; margin: 0 auto; width: min(860px, calc(100% - 32px));">

        <!-- Composer Card -->
        <div class="comm-create-card" style="
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08), 0 0 0 1px rgba(16,185,129,0.08);
            margin-bottom: 28px;
            transition: box-shadow .25s;
            position: relative;
        ">
            <!-- Top gradient bar -->
            <div style="height:4px; background:linear-gradient(90deg,#10b981,#3b82f6,#8b5cf6);"></div>

            <div class="comm-create-body" style="padding:20px 20px 10px; display:flex; gap:14px; align-items:flex-start;">
                <?php if (!empty($_SESSION['avatar'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar" class="comm-avatar" style="flex-shrink:0; object-fit:cover; border:2px solid rgba(16,185,129,0.3); box-shadow:0 4px 12px rgba(16,185,129,0.2);">
                <?php else: ?>
                    <div class="comm-avatar" style="flex-shrink:0; display:flex; align-items:center; justify-content:center; border:2px solid rgba(16,185,129,0.3); box-shadow:0 4px 12px rgba(16,185,129,0.2);"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></div>
                <?php endif; ?>
                <div style="flex:1;">
                    <textarea id="postContent" class="comm-text-input" style="width:100%; min-height:72px; border-radius:14px; background:#f8fafc; border:1.5px solid #e2e8f0; padding:12px 16px; font-size:1rem;" placeholder="Bạn đang nghĩ gì, <?= htmlspecialchars($_SESSION['username'] ?? 'bạn') ?>? ✍️"></textarea>
                    <div id="preview-strip-post" class="comm-preview-strip"></div>
                </div>
            </div>

            <div class="comm-create-footer" style="display:flex !important; flex-direction:row !important; flex-wrap:nowrap !important; align-items:center !important; justify-content:space-between !important; background:linear-gradient(135deg,#f8fafc,#f0fdf4); border-top:1px solid #e2e8f0; padding:12px 20px; gap:8px;">
                <div style="display:flex; flex-direction:row; flex-wrap:nowrap; gap:6px; align-items:center; flex-shrink:0;">
                    <button class="comm-media-btn" title="Thêm ảnh" onclick="document.getElementById('img-input-post').click()" style="border-radius:10px; color:#10b981; border:1.5px solid rgba(16,185,129,0.2); background:rgba(16,185,129,0.06);"><i class="fas fa-image"></i></button>
                    <button class="comm-media-btn" title="Thêm video" onclick="document.getElementById('vid-input-post').click()" style="border-radius:10px; color:#3b82f6; border:1.5px solid rgba(59,130,246,0.2); background:rgba(59,130,246,0.06);"><i class="fas fa-video"></i></button>
                    <button class="comm-media-btn" title="Mở máy ảnh" onclick="camOpen('post')" style="border-radius:10px; color:#8b5cf6; border:1.5px solid rgba(139,92,246,0.2); background:rgba(139,92,246,0.06);"><i class="fas fa-camera"></i></button>
                    <input type="file" id="img-input-post" accept="image/*" multiple style="display:none" onchange="handleImageSelect(event, 'post')">
                    <input type="file" id="vid-input-post" accept="video/*" style="display:none" onchange="handleVideoSelect(event, 'post')">
                </div>
                <button type="button" id="postSubmitBtn" class="btn-3d-glow comm-send-btn" data-action="create_post"
                        style="flex-shrink:0; white-space:nowrap; width:auto; padding:0 22px; border-radius:25px; height:40px; font-size:.9rem; background:linear-gradient(135deg,#10b981,#06b5f0); border:none; color:#fff; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px; box-shadow:0 4px 14px rgba(16,185,129,0.35);">
                    <i class="fas fa-paper-plane" style="pointer-events:none;"></i> Đăng bài
                </button>
            </div>
        </div>

        <div id="postsContainer"></div>
    </div>
</section>


<script>
'use strict';
var replyingTo = {}; var selectedImages = {}; var selectedVideo = {};
document.addEventListener('DOMContentLoaded', function() { loadPosts(false); });

async function loadPosts(silent = false) {
    var cont = document.getElementById('postsContainer');
    if (!silent) {
        cont.innerHTML = '<div style="text-align:center;padding:40px;color:#64748b"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin-top:10px"> Đang tải...</p></div>';
    }
    try {
        var res  = await fetch('api/community.php?action=list_posts&_=' + Date.now(), { cache: 'no-store' });
        var data = await res.json();
        if (!data.success) { cont.innerHTML = '<div style="text-align:center;padding:30px;color:#ef4444">Lỗi: ' + esc(data.message) + '</div>'; return; }
        if (!data.data.length) { cont.innerHTML = '<div style="text-align:center;padding:30px;color:#64748b;background:#fff;border-radius:14px">Chưa có bài viết nào. Hãy là người đầu tiên chia sẻ!</div>'; return; }
        cont.innerHTML = data.data.map(buildPostHtml).join('');

        // Scroll đến bài viết cụ thể nếu URL có hash #post-card-{id}
        var hash = window.location.hash;
        if (hash && hash.startsWith('#post-card-')) {
            var targetEl = document.querySelector(hash);
            if (targetEl) {
                setTimeout(function() {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetEl.style.transition = 'box-shadow 0.5s';
                    targetEl.style.boxShadow = '0 0 0 3px #10b981, 0 8px 32px rgba(16,185,129,0.25)';
                    setTimeout(function() { targetEl.style.boxShadow = ''; }, 2500);
                    // Tự động mở comment section của bài viết đó
                    var postId = hash.replace('#post-card-', '');
                    var commSection = document.getElementById('comments-' + postId);
                    if (commSection) {
                        commSection.style.display = 'block';
                        loadComments(postId, true);
                    }
                }, 200);
            }
        }
    } catch(e) { cont.innerHTML = '<div style="text-align:center;padding:30px;color:#ef4444">Lỗi kết nối</div>'; console.error(e); }
}

function buildPostHtml(p) {
    var imgs = []; try { imgs = p.images ? JSON.parse(p.images) : []; } catch(e) {}
    var gridClass = imgs.length <= 1 ? 'n1' : imgs.length === 2 ? 'n2' : 'n3';
    var mediaHtml = '';
    if (imgs.length) {
        mediaHtml += '<div class="comm-img-grid ' + gridClass + '" style="margin-top:8px; margin-bottom:12px">'
            + imgs.map(function(src) { return '<img src="' + esc(src) + '" alt="Ảnh" loading="lazy" onclick="openLightbox(\'' + esc(src) + '\')">'; }).join('') + '</div>';
    }
    if (p.video) {
        mediaHtml += '<video class="comm-video" controls preload="metadata" style="margin-bottom:12px; max-width:100%; border-radius:10px;"><source src="' + esc(p.video) + '"></video>';
    }

    return '<div class="comm-post-card" id="post-card-' + p.id + '">'
        + '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">'
        +   '<div style="display:flex;gap:12px;align-items:center">'
        +     (p.avatar 
                ? '<img src="' + esc(p.avatar) + '" class="comm-avatar" style="object-fit:cover" alt="Avatar">'
                : '<div class="comm-avatar" style="display:flex;align-items:center;justify-content:center">' + esc(p.username.charAt(0).toUpperCase()) + '</div>')
        +     '<div><div style="font-weight:700;color:#1e293b">' + esc(p.username) + '</div><div style="font-size:.78rem;color:#94a3b8">' + fmtDate(p.created_at) + '</div></div>'
        +   '</div>'
        +   '<div style="display:flex;gap:5px">'
        +     '<button class="comm-report-btn" onclick="reportPost(' + p.id + ')" title="Báo cáo vi phạm"><i class="fas fa-flag"></i></button>'
        +     (p.is_owner ? '<button onclick="deletePost(' + p.id + ')" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:6px;"><i class="fas fa-trash"></i></button>' : '')
        +   '</div>'
        + '</div>'
        + '<div style="color:#334155;line-height:1.65;margin-bottom:16px;white-space:pre-wrap;word-break:break-word">' + esc(p.content) + '</div>'
        + mediaHtml
        + '<div class="comm-post-actions">'
        +   '<button class="comm-post-act-btn" onclick="toggleComments(' + p.id + ')"><i class="far fa-comment-dots"></i> <span id="comment-count-' + p.id + '">' + p.comment_count + '</span> Bình luận</button>'
        + '</div>'
        + '<div id="comments-' + p.id + '" style="display:none;margin-top:14px;border-top:1px dashed #e2e8f0;padding-top:14px">'
        +   '<div id="commentList-' + p.id + '" style="margin-bottom:12px"></div>'
        +   '<div style="display:flex;gap:10px;align-items:flex-start">'
        +     "<?php if (!empty($_SESSION['avatar'])): ?>"
        +     '<img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="comm-avatar-sm" style="object-fit:cover" alt="Avatar">'
        +     "<?php else: ?>"
        +     '<div class="comm-avatar-sm" style="display:flex;align-items:center;justify-content:center"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>'
        +     "<?php endif; ?>"
        +     '<div class="comm-input-wrap">'
        +       '<div id="reply-badge-' + p.id + '" class="comm-reply-badge" style="display:none"><i class="fas fa-reply"></i> <span id="reply-badge-text-' + p.id + '"></span><button onclick="cancelReply(' + p.id + ')">✕</button></div>'
        +       '<div id="preview-strip-' + p.id + '" class="comm-preview-strip"></div>'
        +       '<div class="comm-input-row">'
        +         '<textarea id="commentInput-' + p.id + '" class="comm-text-input" rows="1" placeholder="Viết bình luận..." onkeydown="handleCommentKey(event,' + p.id + ')" oninput="autoResize(this)"></textarea>'
        +         '<button class="comm-media-btn" onclick="document.getElementById(\'img-input-' + p.id + '\').click()"><i class="fas fa-image"></i></button>'
        +         '<button class="comm-media-btn" onclick="camOpen(' + p.id + ')"><i class="fas fa-camera"></i></button>'
        +         '<button type="button" class="comm-send-btn" data-action="create_comment" data-postid="' + p.id + '"><i class="fas fa-paper-plane" style="pointer-events: none;"></i></button>'
        +       '</div>'
        +       '<input type="file" id="img-input-' + p.id + '" accept="image/*" multiple style="display:none" onchange="handleImageSelect(event,' + p.id + ')">'
        +       '<input type="file" id="vid-input-' + p.id + '" accept="video/*" style="display:none" onchange="handleVideoSelect(event,' + p.id + ')">'
        +     '</div>'
        +   '</div>'
        + '</div></div>';
}

async function createPost() {
    var content = document.getElementById('postContent').value.trim();
    var images  = selectedImages['post'] || [];
    var video   = selectedVideo['post']  || null;
    if (!content && !images.length && !video) return;
    var btn = document.getElementById('postSubmitBtn'); 
    if (btn) {
        if (btn.classList.contains('sending')) return;
        btn.classList.add('sending');
        btn.style.opacity = '0.5';
        btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="pointer-events:none;"></i> Đang đăng...';
    }
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var fd = new FormData(); 
    fd.append('action', 'create_post'); 
    fd.append('content', content);
    if (csrfToken) fd.append('csrf_token', csrfToken.getAttribute('content'));
    images.forEach(function(f) { fd.append('comment_images[]', f); });
    if (video) fd.append('comment_video', video);
    try {
        var res = await fetch('api/community.php?action=create_post', { method: 'POST', body: fd });
        var data = await safeJson(res);
        if (data.success) { 
            var postInput = document.getElementById('postContent');
            postInput.value = ''; 
            postInput.blur();
            clearMediaPreview('post'); 
            loadPosts(true); 
        }
        else showErr(data.message);
    } catch(e) { showErr('Lỗi kết nối'); }
    if (btn) { btn.classList.remove('sending'); btn.style.opacity = '1'; btn.innerHTML = '<i class="fas fa-paper-plane" style="pointer-events:none;"></i> Đăng bài'; }
}

async function createComment(postId) {
    var input = document.getElementById('commentInput-' + postId);
    var content = input.value.trim();
    var images  = selectedImages[postId] || [];
    var video   = selectedVideo[postId]  || null;
    if (!content && !images.length && !video) return;
    
    // Find the button using document.querySelector
    var sendBtn = document.querySelector('.comm-send-btn[data-postid="' + postId + '"]'); 
    if (sendBtn) {
        if (sendBtn.classList.contains('sending')) return;
        sendBtn.classList.add('sending');
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="pointer-events: none;"></i>';
        sendBtn.disabled = true;
    }
    
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var fd = new FormData(); 
    fd.append('action', 'create_comment'); 
    fd.append('post_id', postId); 
    fd.append('content', content);
    if (csrfToken) fd.append('csrf_token', csrfToken.getAttribute('content'));
    if (replyingTo[postId]) fd.append('parent_id', replyingTo[postId].commentId);
    images.forEach(function(f) { fd.append('comment_images[]', f); });
    if (video) fd.append('comment_video', video);
    try {
        var res = await fetch('api/community.php?action=create_comment', { method: 'POST', body: fd });
        var data = await safeJson(res);
        if (data.success) {
            input.value = ''; input.style.height = 'auto'; 
            input.blur();
            cancelReply(postId); clearMediaPreview(postId);

            // Insert optimistic comment FIRST (no jank)
            if (data.comment) {
                insertCommentIntoDom(postId, data.comment);
                // Smooth scroll to new comment
                var newComment = document.getElementById('comment-' + data.comment.id);
                if (newComment) {
                    newComment.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }

            // Debounced refresh (300ms delay, no full re-render)
            setTimeout(() => {
                loadComments(postId, true).catch(() => {});
            }, 300);
        } else showErr(data.message);
    } catch(e) { showErr('Lỗi kết nối'); }
    if (sendBtn) { 
        sendBtn.classList.remove('sending');
        sendBtn.innerHTML = '<i class="fas fa-paper-plane" style="pointer-events: none;"></i>';
        sendBtn.disabled = false;
    }
}

function insertCommentIntoDom(postId, comment) {
    if (!comment) return;
    var list = document.getElementById('commentList-' + postId);
    if (!list) return;

    // Clear placeholder if present
    if ((list.textContent || '').toLowerCase().indexOf('chưa có') !== -1) list.innerHTML = '';

    var pid = comment.parent_id != null ? String(comment.parent_id) : '';
    var isReply = !!pid && pid !== '0';
    var html = buildCommentHtml(comment, postId, isReply);

    if (!isReply) {
        list.insertAdjacentHTML('beforeend', html);
    } else {
        var parentEl = document.getElementById('comment-' + pid);
        if (!parentEl) {
            list.insertAdjacentHTML('beforeend', '<div class="comm-reply-indent">' + html + '</div>');
        } else {
            var next = parentEl.nextElementSibling;
            if (next && next.classList && next.classList.contains('comm-reply-indent')) {
                next.insertAdjacentHTML('beforeend', html);
            } else {
                var wrap = document.createElement('div');
                wrap.className = 'comm-reply-indent';
                wrap.innerHTML = html;
                parentEl.insertAdjacentElement('afterend', wrap);
            }
        }
    }

    // Update count
    var cnt = document.getElementById('comment-count-' + postId);
    if (cnt) {
        var n = parseInt(cnt.textContent || '0', 10);
        if (!isNaN(n)) cnt.textContent = String(n + 1);
    }
}

async function toggleComments(postId) {
    var s = document.getElementById('comments-' + postId);
    if (s.style.display === 'none') { s.style.display = 'block'; await loadComments(postId); }
    else s.style.display = 'none';
}
async function loadComments(postId, silent = false) {
    var list = document.getElementById('commentList-' + postId);
    if (!silent) {
        list.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:8px">Đang tải...</div>';
    }
    try {
        var res = await fetch('api/community.php?action=list_comments&post_id=' + postId + '&_=' + Date.now(), { cache: 'no-store' });
        var data = await safeJson(res);
        if (data.success) {
            document.getElementById('comment-count-' + postId).textContent = data.data.length;
            var children = {}; data.data.forEach(function(c) {
                var key = (c.parent_id && c.parent_id != 0) ? String(c.parent_id) : 'root';
                if (!children[key]) children[key] = []; children[key].push(c);
            });
            function renderBranch(key, depth) {
                if (depth > 5) return '';
                return (children[key] || []).map(function(c) {
                    var html = buildCommentHtml(c, postId, depth > 0);
                    var kids = renderBranch(String(c.id), depth + 1);
                    return html + (kids ? '<div class="comm-reply-indent">' + kids + '</div>' : '');
                }).join('');
            }
            list.innerHTML = renderBranch('root', 0) || '<div style="text-align:center;color:#94a3b8;font-size:.85rem;padding:8px">Chưa có bình luận nào.</div>';
        }
    } catch(e) { list.innerHTML = 'Lỗi kết nối'; }
}

function buildCommentHtml(c, postId, isReply) {
    var imgs = []; try { imgs = c.images ? JSON.parse(c.images) : []; } catch(e) {}
    var mediaHtml = '';
    if (imgs.length) {
        mediaHtml += '<div class="comm-img-grid ' + (imgs.length<=1?'n1':imgs.length==2?'n2':'n3') + '" style="margin-top:8px">'
            + imgs.map(function(src){ return '<img src="'+esc(src)+'" onclick="openLightbox(\''+esc(src)+'\')">'; }).join('') + '</div>';
    }
    var username = c.username || 'Ẩn danh';
    var safeUserB64 = btoa(unescape(encodeURIComponent(username)));
    var avatarHtml = c.avatar
        ? '<img src="' + esc(c.avatar) + '" class="'+(isReply?'comm-avatar-xs':'comm-avatar-sm')+'" style="object-fit:cover" alt="Avatar">'
        : '<div class="'+(isReply?'comm-avatar-xs':'comm-avatar-sm')+'" style="display:flex;align-items:center;justify-content:center;font-weight:700;background:#e2e8f0;color:#64748b;font-size:0.7rem">'+esc(username.charAt(0).toUpperCase())+'</div>';

    return '<div class="comm-comment-wrapper" id="comment-' + c.id + '">'
        + avatarHtml
        + '<div style="flex:1">'
        +   '<div class="comm-comment-bubble">'
        +     '<div class="comm-comment-user">' + esc(username) + '</div>'
        +     (c.reply_to_username ? '<span class="comm-reply-label" style="font-size:0.72rem;opacity:0.8">↩ Trả lời ' + esc(c.reply_to_username) + '</span>' : '')
        +     '<div class="comm-comment-text">' + esc(c.content) + '</div>' + mediaHtml
        +   '</div>'
        +   '<div style="display:flex;gap:12px;font-size:.7rem;color:#94a3b8;margin:4px 0 0 8px">'
        +     '<span>' + fmtDate(c.created_at) + '</span>'
        +     '<button class="comm-comment-action" onclick="startReplyB64(' + postId + ',' + c.id + ',\'' + safeUserB64 + '\')">Trả lời</button>'
        +     '<button class="comm-comment-action" onclick="reportComment(' + c.id + ')">Báo cáo</button>'
        +     (c.is_owner ? '<button class="comm-comment-action" onclick="deleteComment(' + c.id + ',' + postId + ')" style="color:#ef4444">Xoá</button>' : '')
        +   '</div>'
        + '</div></div>';
}

function startReply(pid, cid, user) {
    replyingTo[pid] = { commentId: cid, username: user };
    var b = document.getElementById('reply-badge-' + pid), t = document.getElementById('reply-badge-text-' + pid);
    if (b) b.style.display = 'flex'; if (t) t.textContent = 'Đang trả lời ' + user;
    var i = document.getElementById('commentInput-' + pid); if (i) i.focus();
}
function startReplyB64(pid, cid, userB64) {
    var user = '?';
    try { user = decodeURIComponent(escape(atob(userB64))); } catch(e) {}
    startReply(pid, cid, user);
}
function cancelReply(pid) { delete replyingTo[pid]; var b = document.getElementById('reply-badge-' + pid); if (b) b.style.display = 'none'; }
function handleImageSelect(e, pid) { 
    var f = Array.from(e.target.files); selectedImages[pid] = (selectedImages[pid]||[]).concat(f).slice(0,5); 
    renderPreviewStrip(pid); e.target.value = ''; 
}
function handleVideoSelect(e, pid) { if (e.target.files[0]) selectedVideo[pid] = e.target.files[0]; renderPreviewStrip(pid); e.target.value = ''; }
function renderPreviewStrip(pid) {
    var s = document.getElementById('preview-strip-' + pid), h = '';
    (selectedImages[pid]||[]).forEach(function(f,i){ h += '<div class="comm-preview-item"><img src="'+URL.createObjectURL(f)+'"><button class="comm-preview-remove" onclick="removeImg(\''+pid+'\','+i+')">✕</button></div>'; });
    if (selectedVideo[pid]) h += '<div class="comm-preview-item"><video src="'+URL.createObjectURL(selectedVideo[pid])+'"></video><button class="comm-preview-remove" onclick="removeVid(\''+pid+'\')">✕</button></div>';
    s.innerHTML = h;
}
function removeImg(pid, i) { selectedImages[pid].splice(i,1); renderPreviewStrip(pid); }
function removeVid(pid) { delete selectedVideo[pid]; renderPreviewStrip(pid); }
function clearMediaPreview(pid) { selectedImages[pid] = []; delete selectedVideo[pid]; var s = document.getElementById('preview-strip-' + pid); if (s) s.innerHTML = ''; }

// Global paste listener for Community
window.addEventListener('paste', function(e) {
    if (e.clipboardData && e.clipboardData.files && e.clipboardData.files.length > 0) {
        var files = Array.from(e.clipboardData.files);
        var imageFiles = files.filter(function(f) { return f.type.startsWith('image/'); });
        
        if (imageFiles.length > 0) {
            var active = document.activeElement;
            var pid = null;
            
            if (active && active.id === 'postContent') {
                pid = 'post';
            } else if (active && active.id && active.id.startsWith('commentInput-')) {
                pid = active.id.replace('commentInput-', '');
            }
            
            // If no active textarea but post composer is visible/open, fallback to 'post'
            if (!pid && document.getElementById('postContent')) {
                pid = 'post';
            }
            
            if (pid) {
                e.preventDefault();
                selectedImages[pid] = (selectedImages[pid] || []).concat(imageFiles).slice(0, 5);
                renderPreviewStrip(pid);
            }
        }
    }
});

function openLightbox(s) { document.getElementById('comm-lightbox-img').src=s; document.getElementById('comm-lightbox').classList.add('open'); document.body.style.overflow='hidden'; }
function closeLightbox() { document.getElementById('comm-lightbox').classList.remove('open'); document.body.style.overflow=''; }

var CAM = {
    stream: null, facingMode: 'user', mode: 'photo', recorder: null, recordedChunks: [], timerInterval: null, timerSecs: 0, targetPostId: null, capturedFiles: [],
    open: function(pid) {
        this.targetPostId = pid; this.capturedFiles = []; this.recordedChunks = [];
        document.getElementById('cam-thumbnail-row').innerHTML = ''; this.setMode('photo');
        document.getElementById('cam-modal').style.display = 'flex'; document.body.style.overflow = 'hidden'; this.startStream();
    },
    startStream: async function() {
        try {
            if (this.stream) this.stream.getTracks().forEach(t => t.stop());
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: this.facingMode, width:1280, height:720 }, audio: this.mode === 'video' });
            document.getElementById('cam-preview').srcObject = this.stream;
        } catch(e) { alert('Lỗi camera: ' + e.message); this.close(); }
    },
    setMode: function(m) {
        this.mode = m; document.getElementById('tab-photo').classList.toggle('active', m === 'photo'); document.getElementById('tab-video').classList.toggle('active', m === 'video');
        document.getElementById('cam-shutter-icon').className = m === 'photo' ? 'fas fa-camera' : 'fas fa-video';
    },
    shutter: function() { if (this.mode === 'photo') this.capture(); else if (this.recorder && this.recorder.state === 'recording') this.stopRec(); else this.startRec(); },
    capture: function() {
        var v = document.getElementById('cam-preview'), c = document.createElement('canvas');
        c.width = v.videoWidth; c.height = v.videoHeight; c.getContext('2d').drawImage(v,0,0);
        c.toBlob(b => {
            var f = new File([b], 'cam_'+Date.now()+'.jpg', {type:'image/jpeg'}); this.capturedFiles.push(f);
            var img = document.createElement('img'); img.src = URL.createObjectURL(b); img.className = 'cam-thumb'; document.getElementById('cam-thumbnail-row').appendChild(img);
        }, 'image/jpeg');
    },
    startRec: function() {
        this.recordedChunks = []; this.recorder = new MediaRecorder(this.stream);
        this.recorder.ondataavailable = e => this.recordedChunks.push(e.data);
        this.recorder.onstop = () => this.finishRec();
        this.recorder.start(); document.getElementById('cam-shutter').style.background = '#ef4444';
    },
    stopRec: function() { this.recorder.stop(); document.getElementById('cam-shutter').style.background = '#fff'; },
    finishRec: function() {
        var b = new Blob(this.recordedChunks, {type:'video/webm'}), f = new File([b], 'cam_'+Date.now()+'.webm', {type:'video/webm'});
        selectedVideo[this.targetPostId] = f; renderPreviewStrip(this.targetPostId); this.close();
    },
    flip: function() { this.facingMode = this.facingMode === 'user' ? 'environment' : 'user'; this.startStream(); },
    close: function() {
        if (this.stream) this.stream.getTracks().forEach(t => t.stop()); this.stream = null;
        document.getElementById('cam-modal').style.display = 'none'; document.body.style.overflow = '';
        if (this.capturedFiles.length) {
            selectedImages[this.targetPostId] = (selectedImages[this.targetPostId]||[]).concat(this.capturedFiles).slice(0,5);
            renderPreviewStrip(this.targetPostId);
        }
    }
};
function camOpen(p) { CAM.open(p); } function camClose() { CAM.close(); } function camShutter() { CAM.shutter(); } function camFlip() { CAM.flip(); } function camSetMode(m) { CAM.setMode(m); }
function handleCommentKey(e, pid) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); createComment(pid); } }
function autoResize(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 120) + 'px'; }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtDate(s) {
    var d = new Date(s), diff = Math.floor((new Date() - d) / 1000);
    if (diff < 60) return 'Vừa xong'; if (diff < 3600) return Math.floor(diff/60) + ' phút trước';
    if (diff < 86400) return Math.floor(diff/3600) + ' giờ trước';
    return d.toLocaleDateString('vi-VN');
}
async function safeJson(r) { try { var j = await r.json(); return j; } catch(e) { return {success:false, message:'Lỗi dữ liệu'}; } }
function showErr(m) { alert(m || 'Lỗi không xác định'); }
async function reportPost(id) {
    var r = prompt('Lý do báo cáo?'); if (!r) return;
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var fd = new FormData(); fd.append('action','report_content'); fd.append('post_id',id); fd.append('reason',r);
    if (csrfToken) fd.append('csrf_token', csrfToken.getAttribute('content'));
    var res = await fetch('api/community.php', {method:'POST', body:fd}); var d = await safeJson(res); alert(d.message);
}
async function reportComment(id) {
    var r = prompt('Lý do báo cáo?'); if (!r) return;
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var fd = new FormData(); fd.append('action','report_content'); fd.append('comment_id',id); fd.append('reason',r);
    if (csrfToken) fd.append('csrf_token', csrfToken.getAttribute('content'));
    var res = await fetch('api/community.php', {method:'POST', body:fd}); var d = await safeJson(res); alert(d.message);
}
async function deletePost(id) { if (!confirm('Xoá bài?')) return; var csrfToken = document.querySelector('meta[name="csrf-token"]'); var fd = new FormData(); fd.append('id',id); fd.append('action','delete_post'); if (csrfToken) fd.append('csrf_token', csrfToken.getAttribute('content')); await fetch('api/community.php', {method:'POST',body:fd}); loadPosts(true); }
async function deleteComment(id, pid) { if (!confirm('Xoá bình luận?')) return; var csrfToken = document.querySelector('meta[name="csrf-token"]'); var fd = new FormData(); fd.append('id',id); fd.append('action','delete_comment'); if (csrfToken) fd.append('csrf_token', csrfToken.getAttribute('content')); await fetch('api/community.php', {method:'POST',body:fd}); loadComments(pid, true); }

// Global click handler to capture send buttons reliably without touch blocking
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.comm-send-btn');
    if (!btn) return;
    
    e.preventDefault();
    if (btn.dataset.action === 'create_post') {
        createPost();
    } else if (btn.dataset.action === 'create_comment') {
        var pid = btn.dataset.postid;
        if (pid) createComment(pid);
    }
});

</script>
<?php include 'includes/footer.php'; ?>
