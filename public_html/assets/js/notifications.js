/* FamilyCal — Notifications (in-app + push) */
'use strict';

/* ══════════════════════════════════════════════════
   In-app notification bell
══════════════════════════════════════════════════ */
(function initNotifBell() {
  const bell    = document.getElementById('notifBell');
  const badge   = document.getElementById('notifBadge');
  const panel   = document.getElementById('notifPanel');
  const list    = document.getElementById('notifList');
  const markAll = document.getElementById('markAllRead');
  if (!bell) return;

  let open = false;

  function togglePanel() {
    open = !open;
    panel.style.display = open ? 'flex' : 'none';
    if (open) { loadNotifications(); }
  }

  bell.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(); });
  document.addEventListener('click', (e) => {
    if (open && !document.getElementById('notifBellWrap').contains(e.target)) {
      open = false;
      panel.style.display = 'none';
    }
  });

  markAll?.addEventListener('click', async () => {
    await fc_api('POST', APP_URL + '/api/notifications/read-all').catch(() => {});
    updateBadge(0);
    list.querySelectorAll('.notif-item').forEach(i => i.classList.remove('unread'));
  });

  async function loadNotifications() {
    try {
      const data = await fc_api('GET', APP_URL + '/api/notifications');
      renderNotifications(data.notifications || []);
      updateBadge(data.unread_count || 0);
    } catch(e) {}
  }

  function renderNotifications(notifs) {
    if (!notifs.length) {
      list.innerHTML = '<div class="notif-empty">Sin notificaciones nuevas</div>';
      return;
    }
    const icons = {
      event_created: '🗓',
      event_updated: '✏️',
      event_deleted: '🗑️',
      invitation:    '📨',
    };
    list.innerHTML = notifs.map(n => `
      <div class="notif-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}" data-url="${n.action_url||''}">
        <div class="notif-icon">${icons[n.type] || '🔔'}</div>
        <div class="notif-body">
          <div class="notif-title-text">${escHtml(n.title)}</div>
          ${n.body ? `<div class="notif-body-text">${escHtml(n.body)}</div>` : ''}
          <div class="notif-time">${relativeTime(n.created_at)}</div>
        </div>
      </div>`).join('');

    list.querySelectorAll('.notif-item').forEach(item => {
      item.addEventListener('click', async () => {
        const id  = item.dataset.id;
        const url = item.dataset.url;
        item.classList.remove('unread');
        await fc_api('POST', APP_URL + `/api/notifications/${id}/read`).catch(() => {});
        const cnt = parseInt(badge.textContent || '0', 10) - 1;
        updateBadge(Math.max(0, cnt));
        if (url) { window.location.href = url; }
      });
    });
  }

  function updateBadge(count) {
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  }

  function escHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function relativeTime(dtStr) {
    const diff = (Date.now() - new Date(dtStr).getTime()) / 1000;
    if (diff < 60)    return 'Ahora';
    if (diff < 3600)  return `Hace ${Math.floor(diff/60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff/3600)} h`;
    return `Hace ${Math.floor(diff/86400)} d`;
  }

  // Initial load + poll every 60s
  loadNotifications();
  setInterval(loadNotifications, 60000);
})();

/* ══════════════════════════════════════════════════
   Push notification subscription
   requestPermission() MUST be called from a user gesture.
   We show a banner button inside the notification panel.
══════════════════════════════════════════════════ */
(async function initPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

  const banner  = document.getElementById('pushEnableBanner');
  const btnEnable = document.getElementById('btnEnablePush');
  if (!banner || !btnEnable) return;

  let vapidKey = null;

  try {
    const vapid = await fc_api('GET', APP_URL + '/api/push/vapid-key');
    if (!vapid.enabled || !vapid.public_key) return;
    vapidKey = vapid.public_key;

    const reg      = await navigator.serviceWorker.ready;
    const existing = await reg.pushManager.getSubscription();

    // Already subscribed — nothing to do
    if (existing) return;

    // Permission already denied — don't bother showing the button
    if (Notification.permission === 'denied') return;

    // Already granted but no subscription yet — subscribe silently
    if (Notification.permission === 'granted') {
      await doSubscribe(reg, vapidKey);
      return;
    }

    // Default: show the banner button so user can grant from a gesture
    banner.style.display = 'flex';
  } catch(e) {}

  btnEnable.addEventListener('click', async () => {
    try {
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') { banner.style.display = 'none'; return; }
      const reg = await navigator.serviceWorker.ready;
      await doSubscribe(reg, vapidKey);
      banner.style.display = 'none';
    } catch(e) {}
  });

  async function doSubscribe(reg, publicKey) {
    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlB64ToUint8(publicKey),
    });
    const subJson = sub.toJSON();
    await fc_api('POST', APP_URL + '/api/push/subscribe', {
      endpoint: subJson.endpoint,
      keys:     subJson.keys,
    });
  }

  function urlB64ToUint8(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
  }
})();
