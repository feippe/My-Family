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
      <div class="cat-color-dot" style="background:<?= \App\Core\View::e($cat['color']) ?>"></div>
      <span class="cat-icon"><?= \App\Core\View::e($cat['icon']) ?></span>
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
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Icono</label>
          <input class="form-input form-input-sm text-center" type="text" id="catIcon" maxlength="4" placeholder="📅">
        </div>
        <div class="form-group flex-1">
          <label class="form-label">Nombre</label>
          <input class="form-input" type="text" id="catName" placeholder="Ej: Vacaciones">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Color</label>
        <div class="color-palette" id="colorPalette">
          <?php
          $palette = ['#7c3aed','#2563eb','#16a34a','#ea580c','#ca8a04',
                      '#0891b2','#db2777','#dc2626','#65a30d','#0d9488'];
          foreach ($palette as $c): ?>
          <button type="button" class="color-swatch" data-color="<?= $c ?>"
                  style="background:<?= $c ?>"></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="catColor" value="#7c3aed">
        <div class="color-custom-row">
          <label class="form-label-sm">Personalizado:</label>
          <input type="color" id="catColorPicker" value="#7c3aed" class="color-picker-input">
        </div>
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
  const iconInput  = document.getElementById('catIcon');
  const colorInput = document.getElementById('catColor');
  const colorPicker= document.getElementById('catColorPicker');
  const editIdInput= document.getElementById('catEditId');
  const modalTitle = document.getElementById('catModalTitle');
  const list       = document.getElementById('categoriesList');

  function openModal(cat = null) {
    editIdInput.value  = cat ? cat.id   : '';
    nameInput.value    = cat ? cat.name : '';
    iconInput.value    = cat ? cat.icon : '📅';
    colorInput.value   = cat ? cat.color: '#7c3aed';
    colorPicker.value  = cat ? cat.color: '#7c3aed';
    modalTitle.textContent = cat ? 'Editar categoría' : 'Nueva categoría';
    document.querySelectorAll('.color-swatch').forEach(s => {
      s.classList.toggle('selected', s.dataset.color === colorInput.value);
    });
    overlay.classList.add('open');
    nameInput.focus();
  }

  addBtn.addEventListener('click', () => openModal());
  [closeBtn, cancelBtn].forEach(b => b.addEventListener('click', () => overlay.classList.remove('open')));

  document.querySelectorAll('.color-swatch').forEach(s => {
    s.addEventListener('click', () => {
      colorInput.value  = s.dataset.color;
      colorPicker.value = s.dataset.color;
      document.querySelectorAll('.color-swatch').forEach(x => x.classList.remove('selected'));
      s.classList.add('selected');
    });
  });
  colorPicker.addEventListener('input', () => {
    colorInput.value = colorPicker.value;
    document.querySelectorAll('.color-swatch').forEach(x => x.classList.remove('selected'));
  });

  saveBtn.addEventListener('click', async () => {
    const id   = editIdInput.value;
    const name = nameInput.value.trim();
    const icon = iconInput.value.trim() || '📅';
    const color= colorInput.value;
    if (!name) { window.showToast('Ingresá un nombre', 'error'); return; }

    try {
      if (id) {
        await fc_api('PUT', APP_URL + '/settings/categories/' + id, { name, icon, color });
        const row = list.querySelector('[data-id="'+id+'"]');
        if (row) {
          row.querySelector('.cat-color-dot').style.background = color;
          row.querySelector('.cat-icon').textContent  = icon;
          row.querySelector('.cat-name').textContent  = name;
        }
      } else {
        const res = await fc_api('POST', APP_URL + '/settings/categories', { name, icon, color });
        const row = document.createElement('div');
        row.className = 'category-row';
        row.dataset.id = res.category.id;
        row.innerHTML = `
          <div class="cat-color-dot" style="background:${color}"></div>
          <span class="cat-icon">${icon}</span>
          <span class="cat-name">${name}</span>
          <div class="cat-actions">
            <button class="btn-icon cat-edit-btn" title="Editar">✏️</button>
            <button class="btn-icon cat-delete-btn" title="Eliminar">🗑️</button>
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
      openModal({
        id:    row.dataset.id,
        name:  row.querySelector('.cat-name').textContent,
        icon:  row.querySelector('.cat-icon').textContent,
        color: row.querySelector('.cat-color-dot').style.background,
      });
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
