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
    <?php $isSelf = ($m['id'] == $currentUser['id']); ?>
    <div class="member-swipe<?= $isSelf ? ' is-self' : '' ?>" data-id="<?= (int)$m['id'] ?>">
      <?php if (!$isSelf): ?>
      <div class="member-swipe-action">
        <button type="button" class="member-delete-btn" data-name="<?= \App\Core\View::e($m['name']) ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          <span>Borrar</span>
        </button>
      </div>
      <?php endif; ?>
      <div class="member-card">
        <div class="avatar-lg" style="background:<?= \App\Core\View::e($m['color']) ?>">
          <?= \App\Core\View::e($m['avatar'] ?? mb_strtoupper(mb_substr($m['name'],0,1))) ?>
        </div>
        <div class="member-info">
          <span class="member-name"><?= \App\Core\View::e($m['name']) ?></span>
          <span class="member-email"><?= \App\Core\View::e($m['email']) ?></span>
        </div>
        <?php if ($isSelf): ?>
          <span class="badge badge-primary">Tú</span>
        <?php endif; ?>
      </div>
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

/* ── Swipe-to-delete family members ───────────────── */
(function(){
  const ACTION_W = 96;            // width revealed behind the card
  const THRESHOLD = ACTION_W / 2; // past this, snap open
  let openRow = null;

  function closeOpen(except) {
    if (openRow && openRow !== except) openRow.classList.remove('open');
    if (openRow === except) return;
    openRow = null;
  }

  document.querySelectorAll('.member-swipe:not(.is-self)').forEach(row => {
    const card = row.querySelector('.member-card');
    let startX = 0, startY = 0, dx = 0, dragging = false, decided = false;

    card.addEventListener('touchstart', e => {
      if (e.touches.length !== 1) return;
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
      dx = 0; dragging = true; decided = false;
      card.style.transition = 'none';
    }, { passive: true });

    card.addEventListener('touchmove', e => {
      if (!dragging) return;
      const mx = e.touches[0].clientX - startX;
      const my = e.touches[0].clientY - startY;
      if (!decided) {
        // Ignore mostly-vertical gestures (let the page scroll)
        if (Math.abs(my) > Math.abs(mx)) { dragging = false; return; }
        decided = true;
        if (openRow && openRow !== row) closeOpen(row);
      }
      const base = row.classList.contains('open') ? -ACTION_W : 0;
      dx = Math.max(-ACTION_W, Math.min(0, base + mx));
      card.style.transform = `translateX(${dx}px)`;
    }, { passive: true });

    function settle() {
      if (!dragging) return;
      dragging = false;
      card.style.transition = '';
      card.style.transform = '';
      const shouldOpen = dx <= -THRESHOLD;
      row.classList.toggle('open', shouldOpen);
      openRow = shouldOpen ? row : null;
    }
    card.addEventListener('touchend', settle, { passive: true });
    card.addEventListener('touchcancel', settle, { passive: true });

    // Tapping an open card (not the button) closes it
    card.addEventListener('click', () => {
      if (row.classList.contains('open')) { row.classList.remove('open'); openRow = null; }
    });

    // Desktop fallback: the delete button is always reachable; a hover
    // reveals it slightly so the affordance isn't mobile-only.
    row.querySelector('.member-delete-btn')?.addEventListener('click', async (e) => {
      e.stopPropagation();
      const btn  = e.currentTarget;
      const name = btn.dataset.name || 'este integrante';
      if (!confirm(`¿Quitar a ${name} de la familia?`)) return;
      btn.disabled = true;
      try {
        await fc_api('DELETE', APP_URL + '/group/members/' + row.dataset.id);
        row.style.transition = 'opacity .2s, transform .2s, max-height .25s';
        row.style.maxHeight = row.offsetHeight + 'px';
        requestAnimationFrame(() => {
          row.style.opacity = '0';
          row.style.transform = 'translateX(-30px)';
          row.style.maxHeight = '0';
          row.style.margin = '0';
          row.style.padding = '0';
        });
        setTimeout(() => row.remove(), 260);
        window.showToast('Integrante quitado de la familia', 'success');
      } catch(err) {
        btn.disabled = false;
        window.showToast(err.message || 'No se pudo quitar al integrante', 'error');
      }
    });
  });

  // Tap outside any open row closes it
  document.addEventListener('click', e => {
    if (openRow && !openRow.contains(e.target)) closeOpen(null);
  });
})();
</script>
