<?php $pageTitle = 'Categorías'; ?>
<div class="page-container page-narrow">
  <div class="page-header">
    <h1 class="page-title">Categorías</h1>
    <button class="btn btn-primary btn-sm" id="addCatBtn">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nueva
    </button>
  </div>

  <div class="categories-list" id="categoriesList">
    <?php foreach ($categories as $cat): ?>
    <div class="category-row" data-id="<?= $cat['id'] ?>">
      <span class="cat-name"><?= \App\Core\View::e($cat['name']) ?></span>
      <div class="cat-actions">
        <button class="btn-icon cat-edit-btn" title="Editar">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="btn-icon cat-delete-btn" title="Eliminar">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Category form modal -->
<div class="modal-overlay" id="catModalOverlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="catModalTitle">Nueva categoría</h3>
      <button class="btn-icon modal-close" id="catModalClose">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="catEditId">
      <div class="form-group">
        <label class="form-label">Nombre</label>
        <input class="form-input" type="text" id="catName" placeholder="Ej: Vacaciones">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="catModalCancel">Cancelar</button>
      <button class="btn btn-primary" id="catSaveBtn">Guardar</button>
    </div>
  </div>
</div>

<script>
(function(){
  const overlay    = document.getElementById('catModalOverlay');
  const addBtn     = document.getElementById('addCatBtn');
  const closeBtn   = document.getElementById('catModalClose');
  const cancelBtn  = document.getElementById('catModalCancel');
  const saveBtn    = document.getElementById('catSaveBtn');
  const nameInput  = document.getElementById('catName');
  const editIdInput= document.getElementById('catEditId');
  const modalTitle = document.getElementById('catModalTitle');
  const list       = document.getElementById('categoriesList');

  function openModal(cat = null) {
    editIdInput.value      = cat ? cat.id   : '';
    nameInput.value        = cat ? cat.name : '';
    modalTitle.textContent = cat ? 'Editar categoría' : 'Nueva categoría';
    overlay.classList.add('open');
  }

  addBtn.addEventListener('click', () => openModal());
  [closeBtn, cancelBtn].forEach(b => b.addEventListener('click', () => overlay.classList.remove('open')));

  saveBtn.addEventListener('click', async () => {
    const id   = editIdInput.value;
    const name = nameInput.value.trim();
    if (!name) { window.showToast('Ingresá un nombre', 'error'); return; }

    try {
      if (id) {
        await fc_api('PUT', APP_URL + '/settings/categories/' + id, { name });
        const row = list.querySelector('[data-id="'+id+'"]');
        if (row) row.querySelector('.cat-name').textContent = name;
      } else {
        const res = await fc_api('POST', APP_URL + '/settings/categories', { name });
        const row = document.createElement('div');
        row.className  = 'category-row';
        row.dataset.id = res.category.id;
        row.innerHTML  = `
          <span class="cat-name">${name}</span>
          <div class="cat-actions">
            <button class="btn-icon cat-edit-btn" title="Editar">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn-icon cat-delete-btn" title="Eliminar">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>`;
        list.appendChild(row);
        bindRowEvents(row);
      }
      overlay.classList.remove('open');
      window.showToast('Categoría guardada', 'success');
    } catch(e) { window.showToast(e.message, 'error'); }
  });

  function bindRowEvents(row) {
    row.querySelector('.cat-edit-btn').addEventListener('click', () => {
      openModal({ id: row.dataset.id, name: row.querySelector('.cat-name').textContent });
    });
    row.querySelector('.cat-delete-btn').addEventListener('click', async () => {
      if (!confirm('¿Eliminar esta categoría?')) return;
      try {
        await fc_api('DELETE', APP_URL + '/settings/categories/' + row.dataset.id);
        row.remove();
        window.showToast('Categoría eliminada', 'success');
      } catch(e) { window.showToast(e.message, 'error'); }
    });
  }

  list.querySelectorAll('.category-row').forEach(bindRowEvents);
})();
</script>
