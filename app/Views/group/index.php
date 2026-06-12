<?php $pageTitle = 'Familia'; ?>
<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title"><?= \App\Core\View::e($group['name']) ?></h1>
      <p class="page-subtitle"><?= count($members) ?> integrante<?= count($members) !== 1 ? 's' : '' ?></p>
    </div>
    <button class="btn btn-primary" id="inviteBtn">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      Invitar integrante
    </button>
  </div>

  <div class="members-grid">
    <?php foreach ($members as $m): ?>
    <div class="member-card">
      <div class="avatar-lg" style="background:<?= \App\Core\View::e($m['color']) ?>">
        <?= \App\Core\View::e($m['avatar'] ?? mb_strtoupper(mb_substr($m['name'],0,1))) ?>
      </div>
      <div class="member-info">
        <span class="member-name"><?= \App\Core\View::e($m['name']) ?></span>
        <span class="member-email"><?= \App\Core\View::e($m['email']) ?></span>
      </div>
      <?php if ($m['id'] == $currentUser['id']): ?>
        <span class="badge badge-primary">Tú</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Invite modal -->
<div class="modal-overlay" id="inviteOverlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Invitar a la familia</h3>
      <button class="btn-icon modal-close" id="inviteClose">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p class="text-secondary mb-md">Ingresá el email de la persona que querés invitar. Se generará un link de invitación.</p>
      <div class="form-group">
        <label class="form-label" for="inviteEmail">Email</label>
        <input class="form-input" type="email" id="inviteEmail" placeholder="email@ejemplo.com">
      </div>
      <div id="inviteLinkBox" style="display:none">
        <label class="form-label">Link de invitación</label>
        <div class="copy-box">
          <input class="form-input" type="text" id="inviteLink" readonly>
          <button class="btn btn-ghost btn-sm" id="copyLink">Copiar</button>
        </div>
        <p class="form-hint">Compartí este link con tu familiar. Expira en 7 días.</p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="inviteCancel">Cancelar</button>
      <button class="btn btn-primary" id="inviteSubmit">Generar invitación</button>
    </div>
  </div>
</div>

<script>
(function(){
  const overlay    = document.getElementById('inviteOverlay');
  const openBtn    = document.getElementById('inviteBtn');
  const closeBtn   = document.getElementById('inviteClose');
  const cancelBtn  = document.getElementById('inviteCancel');
  const submitBtn  = document.getElementById('inviteSubmit');
  const emailInput = document.getElementById('inviteEmail');
  const linkBox    = document.getElementById('inviteLinkBox');
  const linkInput  = document.getElementById('inviteLink');
  const copyBtn    = document.getElementById('copyLink');

  openBtn.addEventListener('click', () => { overlay.classList.add('open'); emailInput.focus(); });
  [closeBtn, cancelBtn].forEach(b => b.addEventListener('click', () => overlay.classList.remove('open')));

  submitBtn.addEventListener('click', async () => {
    const email = emailInput.value.trim();
    if (!email) return;
    submitBtn.disabled = true;
    try {
      const res  = await fc_api('POST', APP_URL + '/group/invite', { email });
      linkInput.value = res.link;
      linkBox.style.display = 'block';
      window.showToast('Invitación generada', 'success');
    } catch(e) {
      window.showToast(e.message || 'Error al generar invitación', 'error');
    } finally {
      submitBtn.disabled = false;
    }
  });

  copyBtn.addEventListener('click', () => {
    navigator.clipboard.writeText(linkInput.value).then(() => window.showToast('¡Copiado!', 'success'));
  });
})();
</script>
