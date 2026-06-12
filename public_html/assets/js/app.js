/* FamilyCal — Core JS */
'use strict';

/* ── API helper ──────────────────────────────────── */
window.fc_api = async function(method, url, body = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  };
  if (body) opts.body = JSON.stringify(body);

  const res  = await fetch(url, opts);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Error ${res.status}`);
  return data;
};

/* ── Toast ───────────────────────────────────────── */
window.showToast = function(msg, type = 'info', duration = 3500) {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  const icons = { success: '✓', error: '✕', info: 'ℹ' };
  toast.innerHTML = `<span style="font-size:1rem">${icons[type]||'ℹ'}</span><span>${msg}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('leaving');
    toast.addEventListener('animationend', () => toast.remove());
  }, duration);
};

/* ── Sidebar toggle ──────────────────────────────── */
(function initSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggle   = document.getElementById('sidebarToggle');
  const close    = document.getElementById('sidebarClose');
  if (!sidebar) return;

  function open()  { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
  function closeFn(){ sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }

  toggle?.addEventListener('click', open);
  close?.addEventListener('click', closeFn);
  overlay?.addEventListener('click', closeFn);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFn(); });
})();

/* ── Password visibility toggle ─────────────────── */
document.querySelectorAll('.input-toggle-vis').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = btn.previousElementSibling;
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    const icon = btn.querySelector('.eye-icon');
    if (icon) icon.style.opacity = show ? '0.5' : '1';
  });
});

/* ── Modal helpers ───────────────────────────────── */
window.openModal  = (id) => { const m = document.getElementById(id); if (m) m.classList.add('open'); };
window.closeModal = (id) => { const m = document.getElementById(id); if (m) m.classList.remove('open'); };

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  const modal = overlay.querySelector('.modal');
  overlay.addEventListener('click', e => {
    if (modal && !modal.contains(e.target)) overlay.classList.remove('open');
  });
});

/* ── Mobile FAB ──────────────────────────────────── */
document.getElementById('mobileAddEvent')?.addEventListener('click', () => {
  window.openEventModal?.();
});

/* ── PWA service worker ──────────────────────────── */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js').catch(() => {});
  });
}
