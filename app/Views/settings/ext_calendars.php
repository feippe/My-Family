<?php $pageTitle = 'Calendarios externos'; ?>
<div class="page-container page-narrow">
  <div class="page-header">
    <h1 class="page-title">Calendarios externos</h1>
    <button class="btn btn-primary btn-sm" id="addCalBtn">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Agregar
    </button>
  </div>

  <p class="text-secondary mb-lg" style="font-size:.88rem">
    Cargá URLs de calendarios iCal externos (Google Calendar, Apple Calendar, etc). Sus eventos aparecerán en el calendario, actualizados en cada consulta. Son visibles para todos, pero no editables.
  </p>

  <div class="categories-list" id="extCalList">
    <?php foreach ($calendars as $cal): ?>
    <div class="category-row ext-cal-row" data-id="<?= (int)$cal['id'] ?>">
      <div class="cat-color-dot" style="background:<?= \App\Core\View::e($cal['color']) ?>"></div>
      <span class="cat-name" style="flex:1"><?= \App\Core\View::e($cal['name']) ?></span>
      <label class="toggle-switch" title="Activo/inactivo">
        <input type="checkbox" class="ext-cal-toggle" <?= $cal['is_active'] ? 'checked' : '' ?>>
        <span class="toggle-slider"></span>
      </label>
      <div class="cat-actions">
        <button class="btn-icon ext-cal-edit-btn" title="Editar">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="btn-icon ext-cal-delete-btn" title="Eliminar">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$calendars): ?>
    <p class="text-secondary" style="text-align:center;padding:32px 0;font-size:.88rem">No hay calendarios externos configurados.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="extCalOverlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="extCalModalTitle">Agregar calendario externo</h3>
      <button class="btn-icon modal-close" id="extCalClose">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="extCalEditId">
      <div class="form-group">
        <label class="form-label">Nombre</label>
        <input class="form-input" type="text" id="extCalName" placeholder="Ej: Feriados Uruguay">
      </div>
      <div class="form-group">
        <label class="form-label">URL iCal</label>
        <input class="form-input" type="url" id="extCalUrl" placeholder="https://...">
        <p class="form-hint">Debe ser una URL que apunte a un archivo .ics</p>
      </div>
      <div class="form-group">
        <label class="form-label">Color</label>
        <div class="color-palette" id="extColorPalette">
          <?php
          $palette = ['#0891b2','#2563eb','#7c3aed','#db2777','#dc2626',
                      '#ea580c','#ca8a04','#16a34a','#0d9488','#475569'];
          foreach ($palette as $c): ?>
          <button type="button" class="color-swatch" data-color="<?= $c ?>" style="background:<?= $c ?>"></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="extCalColor" value="#0891b2">
        <div class="color-custom-row">
          <label class="form-label-sm">Personalizado:</label>
          <input type="color" id="extCalColorPicker" value="#0891b2" class="color-picker-input">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="extCalCancel">Cancelar</button>
      <button class="btn btn-primary" id="extCalSave">
        <span id="extCalSaveTxt">Guardar</span>
        <span id="extCalSpinner" style="display:none">Verificando...</span>
      </button>
    </div>
  </div>
</div>

<!-- Read-only event info modal (reused in calendar.js too) -->
<script>
(function(){
  const overlay   = document.getElementById('extCalOverlay');
  const addBtn    = document.getElementById('addCalBtn');
  const closeBtn  = document.getElementById('extCalClose');
  const cancelBtn = document.getElementById('extCalCancel');
  const saveBtn   = document.getElementById('extCalSave');
  const saveTxt   = document.getElementById('extCalSaveTxt');
  const spinner   = document.getElementById('extCalSpinner');
  const nameInput = document.getElementById('extCalName');
  const urlInput  = document.getElementById('extCalUrl');
  const colorInput= document.getElementById('extCalColor');
  const picker    = document.getElementById('extCalColorPicker');
  const editId    = document.getElementById('extCalEditId');
  const modalTitle= document.getElementById('extCalModalTitle');
  const list      = document.getElementById('extCalList');

  function openModal(cal = null) {
    editId.value      = cal ? cal.id    : '';
    nameInput.value   = cal ? cal.name  : '';
    urlInput.value    = cal ? cal.url   : '';
    colorInput.value  = cal ? cal.color : '#0891b2';
    picker.value      = cal ? cal.color : '#0891b2';
    modalTitle.textContent = cal ? 'Editar calendario' : 'Agregar calendario externo';
    document.querySelectorAll('#extColorPalette .color-swatch').forEach(s =>
      s.classList.toggle('selected', s.dataset.color === colorInput.value));
    overlay.classList.add('open');
    nameInput.focus();
  }

  addBtn.addEventListener('click', () => openModal());
  [closeBtn, cancelBtn].forEach(b => b.addEventListener('click', () => overlay.classList.remove('open')));

  document.querySelectorAll('#extColorPalette .color-swatch').forEach(s => {
    s.addEventListener('click', () => {
      colorInput.value = s.dataset.color;
      picker.value     = s.dataset.color;
      document.querySelectorAll('#extColorPalette .color-swatch').forEach(x => x.classList.remove('selected'));
      s.classList.add('selected');
    });
  });
  picker.addEventListener('input', () => {
    colorInput.value = picker.value;
    document.querySelectorAll('#extColorPalette .color-swatch').forEach(x => x.classList.remove('selected'));
  });

  saveBtn.addEventListener('click', async () => {
    const id    = editId.value;
    const name  = nameInput.value.trim();
    const url   = urlInput.value.trim();
    const color = colorInput.value;
    if (!name || !url) { window.showToast('Nombre y URL son requeridos', 'error'); return; }

    saveTxt.style.display = 'none';
    spinner.style.display = '';
    saveBtn.disabled = true;

    try {
      if (id) {
        await fc_api('PUT', APP_URL + '/settings/ext-calendars/' + id, { name, url, color });
        const row = list.querySelector('[data-id="'+id+'"]');
        if (row) {
          row.querySelector('.cat-color-dot').style.background = color;
          row.querySelector('.cat-name').textContent = name;
          row.dataset.url   = url;
          row.dataset.color = color;
        }
        window.showToast('Calendario actualizado', 'success');
      } else {
        const res = await fc_api('POST', APP_URL + '/settings/ext-calendars', { name, url, color });
        const cal = res.calendar;
        // Remove empty-state paragraph if present
        list.querySelector('p')?.remove();
        const row = document.createElement('div');
        row.className   = 'category-row ext-cal-row';
        row.dataset.id  = cal.id;
        row.dataset.url = url;
        row.innerHTML = `
          <div class="cat-color-dot" style="background:${color}"></div>
          <span class="cat-name" style="flex:1">${name}</span>
          <label class="toggle-switch" title="Activo/inactivo">
            <input type="checkbox" class="ext-cal-toggle" checked>
            <span class="toggle-slider"></span>
          </label>
          <div class="cat-actions">
            <button class="btn-icon ext-cal-edit-btn" title="Editar">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn-icon ext-cal-delete-btn" title="Eliminar">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>`;
        list.appendChild(row);
        bindRowEvents(row);
        window.showToast('Calendario agregado', 'success');
      }
      overlay.classList.remove('open');
    } catch(e) {
      window.showToast(e.message, 'error');
    } finally {
      saveTxt.style.display = '';
      spinner.style.display = 'none';
      saveBtn.disabled = false;
    }
  });

  function bindRowEvents(row) {
    row.querySelector('.ext-cal-toggle')?.addEventListener('change', async (e) => {
      try {
        await fc_api('PUT', APP_URL + '/settings/ext-calendars/' + row.dataset.id, { is_active: e.target.checked ? 1 : 0 });
      } catch(err) {
        e.target.checked = !e.target.checked; // revert on error
        window.showToast(err.message, 'error');
      }
    });

    row.querySelector('.ext-cal-edit-btn')?.addEventListener('click', () => {
      openModal({
        id:    row.dataset.id,
        name:  row.querySelector('.cat-name').textContent,
        url:   row.dataset.url || '',
        color: row.querySelector('.cat-color-dot').style.background,
      });
    });

    row.querySelector('.ext-cal-delete-btn')?.addEventListener('click', async () => {
      if (!confirm('¿Eliminar este calendario externo?')) return;
      try {
        await fc_api('DELETE', APP_URL + '/settings/ext-calendars/' + row.dataset.id);
        row.remove();
        if (!list.querySelector('.ext-cal-row')) {
          list.innerHTML = '<p class="text-secondary" style="text-align:center;padding:32px 0;font-size:.88rem">No hay calendarios externos configurados.</p>';
        }
        window.showToast('Calendario eliminado', 'success');
      } catch(e) { window.showToast(e.message, 'error'); }
    });
  }

  list.querySelectorAll('.ext-cal-row').forEach(row => {
    // Store URL in dataset for edit modal (not rendered in HTML directly to avoid leaking to page source)
    row.dataset.url = '<?php /* populated server-side below */ ?>';
    bindRowEvents(row);
  });
})();
</script>

<?php
// Inject the URLs into dataset attributes via PHP — keeps the URL out of visible HTML
// but accessible to the edit modal.
?>
<script>
(function(){
  const urls = <?= json_encode(array_column($calendars, 'url', 'id'), JSON_UNESCAPED_UNICODE) ?>;
  document.querySelectorAll('.ext-cal-row[data-id]').forEach(row => {
    const id = row.dataset.id;
    if (urls[id]) row.dataset.url = urls[id];
  });
})();
</script>
