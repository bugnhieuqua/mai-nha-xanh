/**
 * notifications.js — Hệ thống thông báo thời gian thực
 * Dùng chung cho cả User (header.php) và Admin (admin pages)
 *
 * Cách dùng:
 *   NotifSystem.init({ apiUrl, role, pollInterval })
 */
const NotifSystem = (() => {
  let cfg = {
    apiUrl: "api/get-notifications.php",
    adminApiUrl: "api/admin-get-notifications.php",
    role: "user", // 'user' | 'admin'
    pollInterval: 4000, // ms — poll mỗi 4 giây
    badgeEl: null, // element hiển thị số
    listEl: null, // element danh sách dropdown
  };

  let _lastTime = null;
  let _timer = null;
  let _unread = 0;
  let _initialized = false;

  // ── Toast notification (popup góc phải) ──────────────────────────
  function showToast(title, body, type = "booking") {
    const colors = {
      booking: { bg: "#10b981", icon: "fa-calendar-check" },
      post_approved: { bg: "#3b82f6", icon: "fa-check-circle" },
      post_rejected: { bg: "#ef4444", icon: "fa-times-circle" },
      new_post: { bg: "#f59e0b", icon: "fa-home" },
      new_comment: { bg: "#8b5cf6", icon: "fa-comment-dots" },
      new_chat: { bg: "#8b5cf6", icon: "fa-comment-dots" },
      success: { bg: "#10b981", icon: "fa-check-circle" },
      error: { bg: "#ef4444", icon: "fa-exclamation-circle" },
      warning: { bg: "#f59e0b", icon: "fa-exclamation-triangle" },
    };
    const c = colors[type] || colors.booking;

    // Container
    let container = document.getElementById("notif-toast-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "notif-toast-container";
      container.style.cssText = `
                position:fixed; top:80px; right:20px; z-index:999999;
                display:flex; flex-direction:column; gap:10px;
                max-width:340px; width: calc(100vw - 40px);
                pointer-events:none;
            `;
      document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.style.cssText = `
            background: var(--toast-bg); border-radius:14px;
            box-shadow: 0 8px 32px rgba(0,0,0,.18);
            display:flex; align-items:flex-start; gap:12px;
            padding:14px 16px;
            border-left: 4px solid ${c.bg};
            pointer-events:all;
            animation: notifSlideIn .35s cubic-bezier(.21,1.02,.73,1) forwards;
            position:relative; overflow:hidden;
        `;
    toast.innerHTML = `
            <div style="width:38px;height:38px;border-radius:10px;background:${c.bg};
                        display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas ${c.icon}" style="color:#fff;font-size:1rem;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:.9rem;color: var(--toast-title);margin-bottom:3px;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(title)}</div>
                <div style="font-size:.82rem;color: var(--toast-body);line-height:1.4;">${escapeHtml(body)}</div>
            </div>
            <button onclick="this.closest('div[style]').remove()"
                    style="background:none;border:none;color:#94a3b8;cursor:pointer;
                           font-size:1.1rem;padding:0;line-height:1;flex-shrink:0;">×</button>
            <div class="notif-progress" style="position:absolute;bottom:0;left:0;height:3px;
                 background:${c.bg};width:100%;transform-origin:left;
                 animation:notifProgress 5s linear forwards;opacity:.5;"></div>
        `;

    // Thêm keyframes nếu chưa có
    if (!document.getElementById("notif-keyframes")) {
      const style = document.createElement("style");
      style.id = "notif-keyframes";
      style.textContent = `
                @keyframes notifSlideIn {
                    from { opacity:0; transform: translateX(100px) scale(.9); }
                    to   { opacity:1; transform: translateX(0)     scale(1);  }
                }
                @keyframes notifSlideOut {
                    from { opacity:1; transform: translateX(0)     scale(1);  max-height:200px; }
                    to   { opacity:0; transform: translateX(120px) scale(.8); max-height:0;     padding:0; }
                }
                @keyframes notifProgress {
                    from { width:100%; }
                    to   { width:0%;   }
                }
            `;
      document.head.appendChild(style);
    }

    container.appendChild(toast);

    // Tự đóng sau 6 giây
    let autoCloseTimer = setTimeout(() => {
      toast.style.animation = "notifSlideOut .3s ease forwards";
      setTimeout(() => toast.remove(), 300);
    }, 6000);

    // Huỷ tự đóng nếu người dùng di chuột vào để đọc
    toast.onmouseenter = () => {
      clearTimeout(autoCloseTimer);
      const pb = toast.querySelector(".notif-progress");
      if (pb) pb.style.animationPlayState = "paused";
    };

    // Khi người dùng đưa chuột ra ngoài, đóng chớp nhoáng (3 giây)
    toast.onmouseleave = () => {
      const pb = toast.querySelector(".notif-progress");
      if (pb) pb.style.animationPlayState = "running";
      autoCloseTimer = setTimeout(() => {
        toast.style.animation = "notifSlideOut .3s ease forwards";
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    };
    // Hỗ trợ chạm trên Mobile
    toast.ontouchstart = toast.onmouseenter;
    toast.ontouchend = toast.onmouseleave;
  }

  // ── Cập nhật badge số ────────────────────────────────────────────
  function updateBadge(count) {
    const badge = document.getElementById("notif-badge");
    if (!badge) return;
    _unread = count;
    if (count > 0) {
      badge.textContent = count > 99 ? "99+" : count;
      badge.style.display = "flex";
      // Rung chuông khi có mới
      const bell = document.getElementById("notif-bell");
      if (bell) {
        bell.style.transform = "rotate(-15deg)";
        setTimeout(() => {
          bell.style.transform = "rotate(10deg)";
        }, 150);
        setTimeout(() => {
          bell.style.transform = "";
        }, 300);
      }
    } else {
      badge.style.display = "none";
    }
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function closeDropdown() {
    const dd = document.getElementById("notif-dropdown");
    if (!dd) return;
    dd.classList.remove("show-mobile");
    dd.style.display = "none";
  }

  function isDropdownOpen() {
    const dd = document.getElementById("notif-dropdown");
    if (!dd) return false;
    return dd.classList.contains("show-mobile") || dd.style.display === "block";
  }

  function markListAsReadInDom() {
    const list = document.getElementById("notif-list");
    if (!list) return;

    list.querySelectorAll(".notif-item-wrap").forEach((item) => {
      item.dataset.read = "1";
      item.style.background = "var(--notif-bg-read)";

      const title = item.querySelector(".notif-title");
      if (title) title.style.fontWeight = "500";

      const dot = item.querySelector(".notif-unread-dot");
      if (dot) dot.remove();
    });
  }

  function markSingleAsReadInDom(id) {
    const item = document.querySelector(
      `.notif-item-wrap[data-id="${String(id)}"]`,
    );
    if (!item || item.dataset.read === "1") return false;

    item.dataset.read = "1";
    item.style.background = "var(--notif-bg-read)";

    const title = item.querySelector(".notif-title");
    if (title) title.style.fontWeight = "500";

    const dot = item.querySelector(".notif-unread-dot");
    if (dot) dot.remove();

    return true;
  }

  function navigateToLink(link) {
    if (!link) return;

    try {
      const url = new URL(link, window.location.href);
      const shouldOpenAdminChat =
        url.origin === window.location.origin &&
        url.searchParams.get("open_admin_chat") === "1";
      const isSamePage =
        url.origin === window.location.origin &&
        url.pathname === window.location.pathname;

      if (shouldOpenAdminChat && isSamePage) {
        window.dispatchEvent(new CustomEvent("mnx:open-admin-chat"));
        return;
      }

      window.location.href = url.toString();
    } catch (e) {
      window.location.href = link;
    }
  }

  async function markAllReadInternal(options = {}) {
    const { silent = false, keepList = false } = options;

    if (_unread > 0) {
      updateBadge(0);
      markListAsReadInDom();
    }

    try {
      await fetch(cfg.apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mark_all_read: true }),
      });

      if (!keepList) {
        await loadAll();
      }
    } catch (e) {
      if (!silent) {
        await loadAll();
      }
    }
  }

  async function markReadInternal(id, options = {}) {
    const { keepList = false } = options;
    const changed = markSingleAsReadInDom(id);
    if (changed) {
      updateBadge(Math.max(0, _unread - 1));
    }

    try {
      await fetch(cfg.apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mark_read: id }),
      });

      if (!keepList) {
        await loadAll();
      }
    } catch (e) {
      await loadAll();
    }
  }

  function handleItemClick(id, link, isRead) {
    closeDropdown();

    if (!isRead) {
      markReadInternal(id, { keepList: true });
    }

    navigateToLink(link);
  }

  // ── Render danh sách trong dropdown ─────────────────────────────
  function renderList(items) {
    const el = document.getElementById("notif-list");
    if (!el) return;
    if (!items || !items.length) {
      el.innerHTML =
        '<p style="text-align:center;color:#9ca3af;padding:24px 16px;font-size:.88rem;">Chưa có thông báo nào</p>';
      return;
    }
    const icons = {
      booking: "fa-calendar-check",
      new_post: "fa-home",
      post_approved: "fa-check-circle",
      post_rejected: "fa-times-circle",
      new_chat: "fa-comment-dots",
      new_comment: "fa-comment-dots",
      new_community_post: "fa-users",
    };
    const colors = {
      booking: "#10b981",
      new_post: "#f59e0b",
      post_approved: "#10b981",
      post_rejected: "#ef4444",
      new_chat: "#8b5cf6",
      new_comment: "#8b5cf6",
      new_community_post: "#06b5f0",
    };
    el.innerHTML = items
      .map(
        (n) => `
            <div style="display:flex;gap:12px;padding:13px 16px;cursor:pointer;
                        border-bottom:1px solid var(--notif-border);
                        background:${n.is_read == 1 ? "var(--notif-bg-read)" : "var(--notif-bg-unread)"};
                        transition:.15s; position:relative; overflow:hidden;"
                 class="notif-item-wrap"
                 data-id="${n.id}"
                 data-read="${n.is_read == 1 ? "1" : "0"}"
                 data-link="${escapeHtml(encodeURIComponent(n.link || ""))}"
                 onmouseover="this.style.background='var(--notif-hover)'"
                 onmouseout="this.style.background='${n.is_read == 1 ? "var(--notif-bg-read)" : "var(--notif-bg-unread)"}'">
                
                <div onclick="NotifSystem.handleItemClick(${n.id}, decodeURIComponent(this.closest('.notif-item-wrap').dataset.link || ''), ${n.is_read == 1 ? "true" : "false"})" style="flex:1; display:flex; gap:12px; align-items:flex-start;">
                    <div style="width:36px;height:36px;border-radius:50%;
                                background:${colors[n.type] || "#6b7280"};
                                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas ${icons[n.type] || "fa-bell"}" style="color:#fff;font-size:.85rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="notif-title" style="font-weight:${n.is_read == 1 ? "500" : "700"};font-size:.87rem;
                                    color: var(--notif-text-title);margin-bottom:2px;">${escapeHtml(n.title)}</div>
                        <div style="font-size:.78rem;color: var(--notif-text-content);
                                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(n.content)}</div>
                        <div style="font-size:.72rem;color:#94a3b8;margin-top:3px;">
                            ${new Date(n.created_at).toLocaleString("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" })}
                        </div>
                    </div>
                    ${n.is_read == 0 ? '<div class="notif-unread-dot" style="width:8px;height:8px;border-radius:50%;background:#10b981;flex-shrink:0;margin-top:6px;"></div>' : ""}
                </div>

                <!-- Nút Xoá riêng -->
                <button onclick="event.stopPropagation(); NotifSystem.deleteNotif(${n.id})" 
                        style="background:none; border:none; color:#cbd5e1; cursor:pointer; padding:8px 4px;
                               transition:color .2s; font-size:.85rem;"
                        onmouseover="this.style.color='#ef4444'"
                        onmouseout="this.style.color='#cbd5e1'"
                        title="Xoá">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `,
      )
      .join("");
  }

  // ── Poll API ──────────────────────────────────────────────────────
  async function poll() {
    try {
      const url =
        cfg.apiUrl +
        (_lastTime ? "?since=" + encodeURIComponent(_lastTime) : "?") + "&_t=" + Date.now();
      const res = await fetch(url, { cache: "no-store" });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.success) return;

      // Badge
      updateBadge(data.unread_count || 0);

      // Nếu có thông báo mới (không phải lần poll đầu tiên)
      if (_lastTime && data.data && data.data.length > 0) {
        data.data.forEach((n) => {
          showToast(n.title, n.content, n.type);
        });
        _lastTime = data.data[0].created_at;

        // Render lại nếu dropdown đang mở
        if (isDropdownOpen()) {
          await loadAll({ markViewed: true });
        }
      } else if (_lastTime === null) {
        // Lần đầu: chỉ set lastTime để đánh dấu mốc "bắt đầu phiên"
        // Không hiện Toast cho các thông báo cũ đã có từ trước
        if (data.data && data.data.length > 0) {
          _lastTime = data.data[0].created_at;
        } else {
          // Nếu chưa có thông báo nào, lấy thời điểm hiện tại làm mốc
          _lastTime = new Date().toISOString();
        }
      }
    } catch (e) {
      /* bỏ qua lỗi mạng */
    }
  }

  // ── Load toàn bộ (khi mở dropdown) ──────────────────────────────
  async function loadAll(options = {}) {
    const { markViewed = false } = options;
    try {
      const res = await fetch(cfg.apiUrl + "?full=1&_t=" + Date.now(), { cache: "no-store" });
      const data = await res.json();
      if (data.success) {
        updateBadge(data.unread_count || 0);
        renderList(data.data || []);
        if (data.data && data.data.length > 0)
          _lastTime = data.data[0].created_at;
        if (markViewed && (data.unread_count || 0) > 0) {
          await markAllReadInternal({ silent: true, keepList: true });
        }
      }
    } catch (e) {}
  }

  // ── Public API ───────────────────────────────────────────────────
  return {
    init(options = {}) {
      if (_initialized) return;
      Object.assign(cfg, options);
      _initialized = true;

      // Poll lần đầu ngay lập tức
      poll();
      _timer = setInterval(poll, cfg.pollInterval);
    },

    async markRead(id) {
      await markReadInternal(id);
    },

    async markAllRead(options = {}) {
      await markAllReadInternal(options);
    },

    async deleteNotif(id) {
      try {
        await fetch(cfg.apiUrl, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ delete_id: id }),
        });
        loadAll();
      } catch (e) {}
    },

    async deleteAllNotifs() {
      try {
        const res = await Swal.fire({
          title: "Xoá tất cả?",
          text: "Tất cả thông báo của bạn sẽ bị xoá vĩnh viễn!",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#10b981",
          cancelButtonColor: "#ef4444",
          confirmButtonText: "Đồng ý xoá",
          cancelButtonText: "Huỷ",
        });

        if (res.isConfirmed) {
          await fetch(cfg.apiUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ delete_all: true }),
          });
          updateBadge(0);
          renderList([]);
          Swal.fire({
            title: "Đã xoá!",
            icon: "success",
            timer: 1500,
            showConfirmButton: false,
          });
        }
      } catch (e) {}
    },

    handleItemClick,
    openDropdown() {
      const dd = document.getElementById("notif-dropdown");
      if (!dd) return;
      const isOpen = dd.style.display === "block";
      dd.style.display = isOpen ? "none" : "block";
      if (!isOpen) loadAll({ markViewed: true });
    },

    loadAll,
    showToast, // expose để dùng ngoài
  };
})();
