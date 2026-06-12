/* FamilyCal — Calendar + Event Form */
'use strict';

/* ══════════════════════════════════════════════════
   Time Picker (5-minute intervals)
══════════════════════════════════════════════════ */
(function initTimePicker() {
  const picker    = document.getElementById('timePicker');
  const inner     = document.getElementById('timePickerInner');
  if (!picker) return;

  let activeInput = null;

  function buildOptions() {
    inner.innerHTML = '';
    for (let h = 0; h < 24; h++) {
      for (let m = 0; m < 60; m += 5) {
        const label = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
        const opt   = document.createElement('div');
        opt.className = 'time-option';
        opt.textContent = label;
        opt.dataset.value = label;
        opt.addEventListener('click', () => selectTime(label));
        inner.appendChild(opt);
      }
    }
  }

  function selectTime(val) {
    if (activeInput) {
      activeInput.value = val;
      activeInput.dispatchEvent(new Event('change'));
      syncEndTime();
    }
    hidePicker();
  }

  function showPicker(input) {
    activeInput = input;
    const rect = input.getBoundingClientRect();
    picker.style.display = 'block';
    picker.style.left    = rect.left + 'px';
    picker.style.top     = (rect.bottom + 4) + 'px';

    // Highlight current
    inner.querySelectorAll('.time-option').forEach(o => {
      o.classList.toggle('selected', o.dataset.value === input.value);
    });
    // Scroll to current
    const sel = inner.querySelector('.selected');
    if (sel) sel.scrollIntoView({ block: 'center' });
    else inner.scrollTop = 0;
  }

  function hidePicker() {
    picker.style.display = 'none';
    activeInput = null;
  }

  buildOptions();

  document.querySelectorAll('.time-input').forEach(input => {
    input.addEventListener('click', (e) => { e.stopPropagation(); showPicker(input); });
    input.addEventListener('keydown', e => {
      if (e.key === 'Escape') hidePicker();
    });
  });

  document.addEventListener('click', e => {
    if (!picker.contains(e.target)) hidePicker();
  });

  function syncEndTime() {
    const startDate = document.getElementById('evStartDate')?.value;
    const startTime = document.getElementById('evStartTime')?.value;
    const endDate   = document.getElementById('evEndDate');
    const endTime   = document.getElementById('evEndTime');
    if (!startDate || !startTime || !endDate || !endTime) return;

    // If end date is empty, sync it
    if (!endDate.value) endDate.value = startDate;

    // Auto-advance end time by 1h if same date and end <= start
    const [sh, sm] = startTime.split(':').map(Number);
    const [eh, em] = (endTime.value || '00:00').split(':').map(Number);
    const startMins = sh * 60 + sm;
    const endMins   = eh * 60 + em;

    if (endDate.value === startDate && endMins <= startMins) {
      const newMins = startMins + 60;
      const nh = Math.floor(newMins / 60) % 24;
      const nm = newMins % 60;
      endTime.value = `${String(nh).padStart(2,'0')}:${String(nm - nm%5).padStart(2,'0')}`;
    }
  }

  window._syncEndTime = syncEndTime;
})();

/* ══════════════════════════════════════════════════
   Event Modal
══════════════════════════════════════════════════ */
(function initEventModal() {
  const overlay  = document.getElementById('eventModalOverlay');
  const form     = document.getElementById('eventForm');
  const title    = document.getElementById('eventModalTitle');
  const saveBtn  = document.getElementById('eventSaveBtn');
  const saveTxt  = document.getElementById('eventSaveTxt');
  const delBtn   = document.getElementById('eventDeleteBtn');
  const closeBtn = document.getElementById('eventModalClose');
  const cancelBtn= document.getElementById('eventModalCancel');
  if (!overlay) return;

  let editingId = null;

  // Visibility
  document.querySelectorAll('.vis-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.vis-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('evVisibility').value = btn.dataset.value;
    });
  });

  // Recurrence toggle
  const recurringChk  = document.getElementById('evRecurring');
  const recurrencePanel = document.getElementById('recurrencePanel');
  recurringChk?.addEventListener('change', () => {
    recurrencePanel.style.display = recurringChk.checked ? 'flex' : 'none';
  });

  // Recurrence type tabs
  document.querySelectorAll('.rec-type-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.rec-type-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('evRecurrenceType').value = btn.dataset.type;
      document.getElementById('recWeekly').style.display  = btn.dataset.type === 'weekly'  ? '' : 'none';
      document.getElementById('recMonthly').style.display = btn.dataset.type === 'monthly' ? '' : 'none';
      document.getElementById('recAnnual').style.display  = btn.dataset.type === 'annual'  ? '' : 'none';
    });
  });

  // Monthly mode
  document.querySelectorAll('[name="rec_monthly_mode"]').forEach(r => {
    r.addEventListener('change', () => {
      const dom = r.value === 'day_of_month';
      document.getElementById('recMonthDayOption').style.display  = dom ? '' : 'none';
      document.getElementById('recMonthWeekOption').style.display = dom ? 'none' : '';
    });
  });

  // Annual mode
  document.querySelectorAll('[name="rec_annual_mode"]').forEach(r => {
    r.addEventListener('change', () => {
      const fixed = r.value === 'fixed_date';
      document.getElementById('recAnnualFixedOption').style.display = fixed ? '' : 'none';
      document.getElementById('recAnnualWeekOption').style.display  = fixed ? 'none' : '';
    });
  });

  // All day toggle
  const allDayChk = document.getElementById('evAllDay');
  allDayChk?.addEventListener('change', () => {
    const timeInputs = document.querySelectorAll('.time-input');
    timeInputs.forEach(i => { i.disabled = allDayChk.checked; i.style.opacity = allDayChk.checked ? '0.4' : '1'; });
  });

  function buildRecurrenceRule() {
    if (!recurringChk.checked) return null;
    const type = document.getElementById('evRecurrenceType').value;
    const rule = { type };

    if (type === 'weekly') {
      rule.days = Array.from(document.querySelectorAll('.weekday-cb:checked')).map(c => c.value);
      if (!rule.days.length) {
        const today = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][new Date().getDay()];
        rule.days = [today];
      }
    } else if (type === 'monthly') {
      const mode = document.querySelector('[name="rec_monthly_mode"]:checked')?.value || 'day_of_month';
      rule.mode  = mode;
      if (mode === 'day_of_month') {
        rule.day = parseInt(document.getElementById('recMonthDay').value) || 1;
      } else {
        rule.occurrence = parseInt(document.getElementById('recMonthOccurrence').value) || 1;
        rule.weekday    = document.getElementById('recMonthWeekday').value;
      }
    } else if (type === 'annual') {
      const mode = document.querySelector('[name="rec_annual_mode"]:checked')?.value || 'fixed_date';
      rule.mode  = mode;
      if (mode === 'fixed_date') {
        rule.month = parseInt(document.getElementById('recAnnualMonth').value) || 1;
        rule.day   = parseInt(document.getElementById('recAnnualDay').value) || 1;
      } else {
        rule.month      = parseInt(document.getElementById('recAnnualWeekMonth').value) || 1;
        rule.occurrence = parseInt(document.getElementById('recAnnualOccurrence').value) || 1;
        rule.weekday    = document.getElementById('recAnnualWeekday').value;
      }
    }
    return rule;
  }

  function collectForm() {
    const startDate = document.getElementById('evStartDate').value;
    const startTime = document.getElementById('evStartTime').value || '00:00';
    const endDate   = document.getElementById('evEndDate').value || startDate;
    const endTime   = document.getElementById('evEndTime').value || startTime;
    const allDay    = document.getElementById('evAllDay').checked;

    const participants = Array.from(document.querySelectorAll('.participant-check:checked')).map(c => parseInt(c.value));

    return {
      title:            document.getElementById('evTitle').value.trim(),
      description:      document.getElementById('evDescription').value.trim() || null,
      location:         document.getElementById('evLocation').value.trim() || null,
      category_id:      document.getElementById('evCategory').value || null,
      visibility:       document.getElementById('evVisibility').value,
      start_datetime:   `${startDate} ${startTime}:00`,
      end_datetime:     `${endDate} ${endTime}:00`,
      all_day:          allDay ? 1 : 0,
      is_recurring:     recurringChk.checked ? 1 : 0,
      recurrence_type:  recurringChk.checked ? document.getElementById('evRecurrenceType').value : null,
      recurrence_rule:  buildRecurrenceRule(),
      recurrence_end:   document.getElementById('evRecurrenceEnd').value || null,
      participants,
    };
  }

  function resetForm() {
    form.reset();
    document.getElementById('eventId').value = '';
    document.getElementById('evVisibility').value = 'public';
    document.querySelectorAll('.vis-btn').forEach(b => b.classList.toggle('active', b.dataset.value === 'public'));
    recurrencePanel.style.display = 'none';
    delBtn.style.display = 'none';
    editingId = null;
  }

  window.openEventModal = function(dateStr = null, eventData = null) {
    resetForm();
    if (eventData) {
      // Edit mode
      editingId = eventData.event_id;
      title.textContent  = 'Editar evento';
      saveTxt.textContent = 'Guardar cambios';
      delBtn.style.display = '';

      document.getElementById('evTitle').value = eventData.title;
      if (eventData.extendedProps) {
        const p = eventData.extendedProps;
        document.getElementById('evDescription').value = p.description || '';
        document.getElementById('evLocation').value    = p.location    || '';
        document.getElementById('evCategory').value    = p.category_id || '';
        document.getElementById('evVisibility').value  = p.visibility  || 'public';
        document.querySelectorAll('.vis-btn').forEach(b => b.classList.toggle('active', b.dataset.value === p.visibility));

        if (p.participants?.length) {
          document.querySelectorAll('.participant-check').forEach(c => {
            c.checked = p.participants.some(m => m.id == c.value);
          });
        }
        if (p.is_recurring) {
          recurringChk.checked = true;
          recurrencePanel.style.display = 'flex';
          document.getElementById('evRecurrenceType').value = p.recurrence_type || 'weekly';
          const typeBtn = document.querySelector(`.rec-type-btn[data-type="${p.recurrence_type}"]`);
          if (typeBtn) {
            document.querySelectorAll('.rec-type-btn').forEach(b => b.classList.remove('active'));
            typeBtn.classList.add('active');
            document.getElementById('recWeekly').style.display  = p.recurrence_type === 'weekly'  ? '' : 'none';
            document.getElementById('recMonthly').style.display = p.recurrence_type === 'monthly' ? '' : 'none';
            document.getElementById('recAnnual').style.display  = p.recurrence_type === 'annual'  ? '' : 'none';
          }
        }
      }

      const start = new Date(eventData.start);
      const end   = eventData.end ? new Date(eventData.end) : start;
      document.getElementById('evStartDate').value = fmtDate(start);
      document.getElementById('evStartTime').value = fmtTime(start);
      document.getElementById('evEndDate').value   = fmtDate(end);
      document.getElementById('evEndTime').value   = fmtTime(end);
    } else {
      title.textContent   = 'Nuevo evento';
      saveTxt.textContent = 'Crear evento';
      if (dateStr) {
        const d = new Date(dateStr);
        document.getElementById('evStartDate').value = fmtDate(d);
        document.getElementById('evEndDate').value   = fmtDate(d);
        document.getElementById('evStartTime').value = '09:00';
        document.getElementById('evEndTime').value   = '10:00';
      }
      // Check current user by default
      document.querySelectorAll('.participant-check').forEach(c => {
        if (c.hasAttribute('disabled')) c.checked = true;
      });
    }
    overlay.classList.add('open');
    document.getElementById('evTitle').focus();
  };

  [closeBtn, cancelBtn].forEach(b => b?.addEventListener('click', () => overlay.classList.remove('open')));
  overlay.addEventListener('click', e => { if (!document.getElementById('eventModal').contains(e.target)) overlay.classList.remove('open'); });

  delBtn?.addEventListener('click', async () => {
    if (!editingId || !confirm('¿Eliminar este evento?')) return;
    try {
      await fc_api('DELETE', APP_URL + '/api/events/' + editingId);
      overlay.classList.remove('open');
      window._calendar?.getEventById(editingId)?.remove();
      window._calendar?.refetchEvents();
      showToast('Evento eliminado', 'success');
    } catch(e) { showToast(e.message, 'error'); }
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const data = collectForm();
    if (!data.title) { showToast('El título es requerido', 'error'); return; }
    if (!document.getElementById('evStartDate').value) { showToast('Seleccioná una fecha', 'error'); return; }

    saveBtn.disabled = true;
    try {
      let res;
      if (editingId) {
        res = await fc_api('PUT', APP_URL + '/api/events/' + editingId, data);
      } else {
        res = await fc_api('POST', APP_URL + '/api/events', data);
      }
      overlay.classList.remove('open');
      window._calendar?.refetchEvents();
      showToast(editingId ? 'Evento actualizado' : '¡Evento creado!', 'success');
    } catch(e) {
      showToast(e.message, 'error');
    } finally {
      saveBtn.disabled = false;
    }
  });
})();

/* ══════════════════════════════════════════════════
   FullCalendar
══════════════════════════════════════════════════ */
(function initCalendar() {
  const el = document.getElementById('calendar');
  if (!el || typeof FullCalendar === 'undefined') return;

  const calendar = new FullCalendar.Calendar(el, {
    locale:         'es',
    initialView:    window.innerWidth < 640 ? 'listWeek' : 'dayGridMonth',
    firstDay:       1, // Monday
    headerToolbar:  false,
    height:         '100%',
    nowIndicator:   true,
    editable:       false,
    selectable:     true,
    dayMaxEvents:   4,
    eventDisplay:   'block',

    events: function(info, successCb, failureCb) {
      const url = APP_URL + `/api/events?start=${info.startStr}&end=${info.endStr}`;
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => successCb(data))
        .catch(failureCb);
    },

    datesSet(info) {
      const months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
      const start  = info.view.currentStart;
      let label;
      if (info.view.type === 'dayGridMonth') {
        label = months[start.getMonth()] + ' ' + start.getFullYear();
      } else if (info.view.type === 'timeGridWeek') {
        const end = new Date(info.view.currentEnd - 1);
        label = `${start.getDate()} – ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear()}`;
      } else if (info.view.type === 'timeGridDay') {
        const days = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
        label = `${days[start.getDay()]} ${start.getDate()} ${months[start.getMonth()]}`;
      } else {
        label = months[start.getMonth()] + ' ' + start.getFullYear();
      }
      document.getElementById('calTitle').textContent  = label;
      document.getElementById('topBarTitle').textContent = label;
    },

    dateClick(info) {
      window.openEventModal(info.dateStr);
    },

    eventClick(info) {
      const ev = info.event;
      if (ev.extendedProps.is_hybrid) {
        showToast('Este evento es privado', 'info');
        return;
      }
      window.openEventModal(null, {
        event_id: ev.extendedProps.event_id,
        title:    ev.title,
        start:    ev.startStr,
        end:      ev.endStr,
        extendedProps: ev.extendedProps,
      });
    },

    eventDidMount(info) {
      const p = info.event.extendedProps;
      if (p.is_recurring) {
        const dot = document.createElement('span');
        dot.style.cssText = 'display:inline-block;width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.7);margin-right:3px;vertical-align:middle;';
        info.el.querySelector('.fc-event-title')?.prepend(dot);
      }
    },
  });

  calendar.render();
  window._calendar = calendar;

  // Toolbar controls
  document.getElementById('calPrev')?.addEventListener('click', () => calendar.prev());
  document.getElementById('calNext')?.addEventListener('click', () => calendar.next());
  document.getElementById('calToday')?.addEventListener('click', () => calendar.today());
  document.getElementById('addEventBtn')?.addEventListener('click', () => window.openEventModal());

  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      calendar.changeView(btn.dataset.view);
    });
  });
})();

/* ── Helpers ─────────────────────────────────────── */
function fmtDate(d) {
  return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}
function fmtTime(d) {
  const m = d.getMinutes();
  return String(d.getHours()).padStart(2,'0') + ':' + String(m - m%5).padStart(2,'0');
}
