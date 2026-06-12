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
