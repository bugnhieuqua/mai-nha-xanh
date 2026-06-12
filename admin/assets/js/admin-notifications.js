(function () {
  const apiUrl = '../api/admin-get-notifications.php';
  const state = {
    items: [],
    unread: 0,
    loaded: false,
    loading: false,
  };

  const els = {};

  function esc(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function getEls() {
    els.wrap = document.getElementById('admin-notif-wrap');
    els.button = document.getElementById('admin-bell');
    els.badge = document.getElementById('admin-notif-badge');
    els.dropdown = document.getElementById('admin-notif-dropdown');
    els.list = document.getElementById('admin-notif-list');
    return els.wrap && els.button && els.badge && els.dropdown && els.list;
  }

  function iconFor(type) {
    const map = {
      post: 'fa-clock',
      contact: 'fa-envelope',
      support: 'fa-headset',
      booking: 'fa-calendar-check',
      comment: 'fa-comment-dots',
      report: 'fa-triangle-exclamation',
      default: 'fa-bell',
    };
    return map[type] || map.default;
  }

  function colorFor(type) {
    const map = {
      post: 'linear-gradient(135deg,#f59e0b,#d97706)',
      contact: 'linear-gradient(135deg,#3b82f6,#2563eb)',
      support: 'linear-gradient(135deg,#10b981,#059669)',
      booking: 'linear-gradient(135deg,#8b5cf6,#6d28d9)',
      comment: 'linear-gradient(135deg,#06b6d4,#0ea5e9)',
      report: 'linear-gradient(135deg,#ef4444,#dc2626)',
      default: 'linear-gradient(135deg,#64748b,#475569)',
    };
    return map[type] || map.default;
  }

  function navigateTo(link) {
    if (!link) return;
    window.location.href = link;
  }

  function updateBadge(count) {
    if (!els.badge) return;
    els.badge.textContent = count > 99 ? '99+' : String(count);
    els.badge.style.display = count > 0 ? 'flex' : 'none';
    els.button?.setAttribute('data-count', String(count));
    
    // Show/hide top notification bar
    const notifBar = document.getElementById('admin-notif-bar');
    if (notifBar) {
      if (count > 0) {
        notifBar.classList.add('visible');
      } else {
        notifBar.classList.remove('visible');
      }
    }
  }

  function renderEmpty() {
    if (!els.list) return;
    els.list.innerHTML = `
      <div class="admin-notif-empty">
        <div class="admin-notif-empty-icon"><i class="fas fa-bell-slash"></i></div>
        <div class="admin-notif-empty-title">Không có thông báo mới</div>
        <div class="admin-notif-empty-sub">Mọi thứ đang ổn. Hệ thống chưa ghi nhận mục cần xử lý.</div>
      </div>
    `;
  }

  function renderList(items) {
    if (!els.list) return;
    if (!items || !items.length) {
      renderEmpty();
      return;
    }

    els.list.innerHTML = '';
    const frag = document.createDocumentFragment();

    items.forEach((item) => {
      const row = document.createElement('div');
      row.className = 'admin-notif-item';
      row.dataset.id = item.id;
      row.dataset.read = item.admin_seen == 1 ? '1' : '0';

      const isUnread = item.admin_seen == 0;
      row.innerHTML = `
        <button type="button" class="admin-notif-main" aria-label="Mở thông báo">
          <span class="admin-notif-icon" style="background:${colorFor(item.type)}">
            <i class="fas ${iconFor(item.type)}"></i>
          </span>
          <span class="admin-notif-copy">
            <span class="admin-notif-title">${esc(item.title)}</span>
            <span class="admin-notif-content">${esc(item.content)}</span>
            <span class="admin-notif-meta">${new Date(item.created_at).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>
          </span>
          ${isUnread ? '<span class="admin-notif-dot"></span>' : ''}
        </button>
        <button type="button" class="admin-notif-delete" title="Xoá">
          <i class="fas fa-trash-alt"></i>
        </button>
      `;

      row.querySelector('.admin-notif-main').addEventListener('click', () => {
        if (item.link) navigateTo(item.link);
      });

      row.querySelector('.admin-notif-delete').addEventListener('click', async (e) => {
        e.stopPropagation();
        await deleteNotif(item.id);
      });

      frag.appendChild(row);
    });

    els.list.appendChild(frag);
  }

  function syncPayload(payload) {
    if (!payload || payload.success === false) return;
    state.items = Array.isArray(payload.data) ? payload.data : [];
    state.unread = Number(payload.unread_count || 0);
    state.loaded = true;
    updateBadge(state.unread);
    if (els.list) renderList(state.items);
  }

  async function loadNotifications() {
    if (state.loading) return;
    state.loading = true;
    try {
      const res = await fetch(`${apiUrl}?full=1`, { cache: 'no-store' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      syncPayload(data);
    } catch (e) {
      if (!state.loaded) renderEmpty();
    } finally {
      state.loading = false;
    }
  }

  function openDropdown() {
    if (!getEls()) return;
    els.dropdown.style.display = 'block';
    els.dropdown.classList.add('is-open');
    els.button?.setAttribute('aria-expanded', 'true');
    loadNotifications();
  }

  function closeDropdown() {
    if (!getEls()) return;
    els.dropdown.style.display = 'none';
    els.dropdown.classList.remove('is-open');
    els.button?.setAttribute('aria-expanded', 'false');
  }

  function toggleDropdown() {
    if (!getEls()) return;
    const isOpen = els.dropdown.style.display === 'block';
    if (isOpen) closeDropdown();
    else openDropdown();
  }

  async function markAllRead() {
    if (!getEls()) return;
    try {
      await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mark_all_read: true }),
      });
      await loadNotifications();
    } catch (e) {}
  }

  async function deleteNotif(id) {
    if (!getEls()) return;
    try {
      await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delete_id: id }),
      });
      await loadNotifications();
    } catch (e) {}
  }

  async function deleteAllNotifs() {
    if (!getEls()) return;
    const confirmed = typeof Swal !== 'undefined'
      ? await Swal.fire({
          title: 'Xoá tất cả thông báo?',
          text: 'Toàn bộ thông báo admin sẽ bị xoá vĩnh viễn.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Xoá hết',
          cancelButtonText: 'Huỷ',
          confirmButtonColor: '#ef4444',
        }).then((r) => r.isConfirmed)
      : confirm('Xoá tất cả thông báo admin?');

    if (!confirmed) return;

    try {
      await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delete_all: true }),
      });
      await loadNotifications();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Đã xoá',
          icon: 'success',
          timer: 1200,
          showConfirmButton: false,
        });
      }
    } catch (e) {}
  }

  function wireEvents() {
    if (!getEls()) return;
    els.button.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleDropdown();
    });

    els.dropdown.addEventListener('click', (e) => e.stopPropagation());

    document.addEventListener('click', (e) => {
      if (!els.wrap.contains(e.target)) {
        closeDropdown();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeDropdown();
    });

    const markAllBtn = document.querySelector('#admin-notif-dropdown .admin-notif-mark-all');
    const deleteAllBtn = document.querySelector('#admin-notif-dropdown .admin-notif-delete-all');
    if (markAllBtn) markAllBtn.addEventListener('click', markAllRead);
    if (deleteAllBtn) deleteAllBtn.addEventListener('click', deleteAllNotifs);
  }

  window.toggleAdminNotif = toggleDropdown;
  window.adminMarkAllRead = markAllRead;
  window.adminDeleteAllNotifs = deleteAllNotifs;

  window.addEventListener('adminNotifUpdate', (event) => {
    syncPayload(event.detail);
  });

  document.addEventListener('DOMContentLoaded', () => {
    if (!getEls()) return;
    wireEvents();
    loadNotifications();
  });
})();
