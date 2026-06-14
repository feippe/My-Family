<?php $pageTitle = 'Perfil'; ?>
<div class="page-container page-narrow">
  <div class="settings-section">
    <h2 class="settings-section-title">Mi perfil</h2>
    <div class="profile-avatar-row">
      <div class="avatar-xl" style="background:<?= \App\Core\View::e($user['color']) ?>">
        <?= \App\Core\View::e($user['avatar'] ?? mb_strtoupper(mb_substr($user['name'],0,1))) ?>
      </div>
      <div>
        <p class="text-lg font-medium"><?= \App\Core\View::e($user['name']) ?></p>
        <p class="text-secondary"><?= \App\Core\View::e($user['email']) ?></p>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Nombre</label>
      <input class="form-input" type="text" id="profileName" value="<?= \App\Core\View::e($user['name']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Email</label>
      <input class="form-input" type="email" value="<?= \App\Core\View::e($user['email']) ?>" disabled>
      <span class="form-hint">El email no se puede cambiar.</span>
    </div>
    <button class="btn btn-primary" id="saveProfile">Guardar cambios</button>
  </div>

  <div class="settings-section mt-xl">
    <h2 class="settings-section-title">Aplicación</h2>
    <div class="app-version-row">
      <div>
        <p class="form-label" style="margin-bottom:2px">Versión instalada</p>
        <p class="app-version-value" id="swVersion">—</p>
      </div>
      <button class="btn btn-outline" id="btnUpdateApp">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="btnUpdateIcon"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        Actualizar aplicación
      </button>
    </div>
  </div>

  <div class="settings-section mt-xl">
    <h2 class="settings-section-title">Cambiar contraseña</h2>
    <div class="form-group">
      <label class="form-label">Contraseña actual</label>
      <input class="form-input" type="password" id="currentPassword" placeholder="Tu contraseña actual">
    </div>
    <div class="form-group">
      <label class="form-label">Nueva contraseña</label>
      <input class="form-input" type="password" id="newPassword" placeholder="Mínimo 8 caracteres">
    </div>
    <div class="form-group">
      <label class="form-label">Confirmar nueva contraseña</label>
      <input class="form-input" type="password" id="confirmPassword" placeholder="Repetí la nueva contraseña">
    </div>
    <button class="btn btn-outline" id="savePassword">Cambiar contraseña</button>
  </div>
</div>

<script>
(function(){
  /* ── App version + update ── */
  const versionEl  = document.getElementById('swVersion');
  const updateBtn  = document.getElementById('btnUpdateApp');
  const updateIcon = document.getElementById('btnUpdateIcon');

  async function querySwVersion() {
    if (!('serviceWorker' in navigator)) return null;
    try {
      const reg = await navigator.serviceWorker.ready;
      const worker = reg.active;
      if (!worker) return null;
      return await new Promise((resolve) => {
        const channel = new MessageChannel();
        channel.port1.onmessage = (e) => resolve(e.data?.version ?? null);
        worker.postMessage({ type: 'GET_VERSION' }, [channel.port2]);
        setTimeout(() => resolve(null), 1500);
      });
    } catch { return null; }
  }

  querySwVersion().then(v => {
    versionEl.textContent = v ? `v${v}` : 'Sin service worker';
  });

  updateBtn.addEventListener('click', async () => {
    updateBtn.disabled = true;
    updateIcon.style.animation = 'spin 0.8s linear infinite';

    try {
      // 1. Ask SW to check for update
      if ('serviceWorker' in navigator) {
        const reg = await navigator.serviceWorker.getRegistration('/');
        if (reg) {
          try { await reg.update(); } catch {}
          // Tell any waiting SW to take over immediately
          if (reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
      }

      // 2. Clear all caches
      if ('caches' in window) {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => caches.delete(k)));
      }

      // 3. Hard reload from server
      window.location.reload(true);
    } catch {
      window.showToast('No se pudo actualizar. Recargá la página manualmente.', 'error');
      updateBtn.disabled = false;
      updateIcon.style.animation = '';
    }
  });

  document.getElementById('saveProfile').addEventListener('click', async () => {
    const name = document.getElementById('profileName').value.trim();
    if (!name) return;
    try {
      await fc_api('POST', APP_URL + '/settings/profile', { name });
      window.showToast('Perfil actualizado', 'success');
    } catch(e) { window.showToast(e.message, 'error'); }
  });

  document.getElementById('savePassword').addEventListener('click', async () => {
    const cur  = document.getElementById('currentPassword').value;
    const np   = document.getElementById('newPassword').value;
    const conf = document.getElementById('confirmPassword').value;
    if (np !== conf) { window.showToast('Las contraseñas no coinciden', 'error'); return; }
    if (np.length < 8) { window.showToast('Mínimo 8 caracteres', 'error'); return; }
    try {
      await fc_api('POST', APP_URL + '/settings/profile', { current_password: cur, new_password: np });
      window.showToast('Contraseña cambiada', 'success');
      ['currentPassword','newPassword','confirmPassword'].forEach(id => document.getElementById(id).value = '');
    } catch(e) { window.showToast(e.message, 'error'); }
  });
})();
</script>
