/* Familia — Calendar + Event Form */
'use strict';

/* ══════════════════════════════════════════════════
   iOS-style Time Picker (scroll-wheel, bottom sheet)
══════════════════════════════════════════════════ */
(function initTimePicker() {
  const picker    = document.getElementById('timePicker');
  const hourCol   = document.getElementById('hourCol');
  const minCol    = document.getElementById('minuteCol');
  const doneBtn   = document.getElementById('timePickerDone');
  const cancelBtn = document.getElementById('timePickerCancel');
  if (!picker || !hourCol || !minCol) return;

  const ITEM_H = 44;
  const HOURS  = Array.from({length: 24}, (_, i) => i);
  const MINS   = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55];
  let activeInput = null;

  function buildCol(col, values) {
    col.innerHTML = '';
    // Ghost pads top/bottom so first/last items can snap to center
    const top = document.createElement('div');
    top.className = 'ios-picker-ghost';
    col.appendChild(top);
    values.forEach(v => {
      const item = document.createElement('div');
      item.className = 'ios-picker-item';
      item.textContent = String(v).padStart(2, '0');
      col.appendChild(item);
    });
    const bot = document.createElement('div');
    bot.className = 'ios-picker-ghost';
    col.appendChild(bot);
  }

  buildCol(hourCol, HOURS);
  buildCol(minCol,  MINS);

  function snapTo(col, index) {
    col.scrollTo({ top: index * ITEM_H, behavior: 'instant' });
  }

  function getIndex(col, len) {
    return Math.max(0, Math.min(Math.round(col.scrollTop / ITEM_H), len - 1));
  }

  function showPicker(input) {
    activeInput = input;
    const [hStr, mStr] = (input.value || '09:00').split(':');
    const h    = Math.max(0, Math.min(parseInt(hStr) || 0, 23));
    const mRaw = Math.round((parseInt(mStr) || 0) / 5) * 5;
    const mIdx = Math.max(0, MINS.indexOf(mRaw));
    picker.classList.add('open');
    // Double rAF: picker must be painted before scrollTop takes effect
    requestAnimationFrame(() => requestAnimationFrame(() => {
      snapTo(hourCol, h);
      snapTo(minCol,  mIdx);
    }));
  }

  function hidePicker() {
    picker.classList.remove('open');
    activeInput = null;
  }

  function syncEndTime() {
    const startDate = document.getElementById('evStartDate')?.value;
    const startTime = document.getElementById('evStartTime')?.value;
    const endDate   = document.getElementById('evEndDate');
    const endTime   = document.getElementById('evEndTime');
    if (!startDate || !startTime || !endDate || !endTime) return;
    if (!endDate.value) endDate.value = startDate;
    const [sh, sm] = startTime.split(':').map(Number);
    const [eh, em] = (endTime.value || '00:00').split(':').map(Number);
    if (endDate.value === startDate && (eh * 60 + em) <= (sh * 60 + sm)) {
      const nm = sh * 60 + sm + 60;
      const rnd = nm - nm % 5;
      endTime.value = `${String(Math.floor(rnd / 60) % 24).padStart(2,'0')}:${String(rnd % 60).padStart(2,'0')}`;
    }
  }

  doneBtn.addEventListener('click', () => {
    if (activeInput) {
      const h = HOURS[getIndex(hourCol, HOURS.length)];
      const m = MINS [getIndex(minCol,  MINS.length)];
      activeInput.value = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
      activeInput.dispatchEvent(new Event('change'));
      syncEndTime();
    }
    hidePicker();
  });

  cancelBtn.addEventListener('click', hidePicker);
  picker.addEventListener('click', e => { if (e.target === picker) hidePicker(); });

  document.querySelectorAll('.time-input').forEach(input => {
    input.readOnly = true;
    input.addEventListener('click', e => { e.stopPropagation(); showPicker(input); });
  });

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
  let currentInstanceDate = null;

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
      currentInstanceDate = eventData.extendedProps?.instance_date || null;
      title.textContent  = 'Editar evento';
      saveTxt.textContent = 'Guardar';
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
      saveTxt.textContent = 'Guardar';
      if (dateStr) {
        // A datetime (long-press in week/day) includes 'T'; a date-only
        // string (month) does not → keep the 09:00–10:00 default.
        const hasTime  = typeof dateStr === 'string' && dateStr.includes('T');
        // Date-only strings parse as UTC midnight, which shifts a day back in
        // negative-offset timezones. Force local parsing by pinning the time.
        const d        = hasTime ? new Date(dateStr) : new Date(dateStr + 'T00:00:00');
        document.getElementById('evStartDate').value = fmtDate(d);
        if (hasTime) {
          const end = new Date(d.getTime() + 60 * 60 * 1000);
          document.getElementById('evStartTime').value = fmtTime(d);
          document.getElementById('evEndDate').value   = fmtDate(end);
          document.getElementById('evEndTime').value   = fmtTime(end);
        } else {
          document.getElementById('evEndDate').value   = fmtDate(d);
          document.getElementById('evStartTime').value = '09:00';
          document.getElementById('evEndTime').value   = '10:00';
        }
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
    if (!editingId) return;
    const isRecurring = document.getElementById('evRecurring').checked;

    if (isRecurring) {
      // Show scope dialog for deletion
      window._recurScopeCallback = async (scope) => {
        try {
          const body = { scope };
          if (scope !== 'all') body.instance_date = currentInstanceDate;
          await fc_api('DELETE', APP_URL + '/api/events/' + editingId, body);
          overlay.classList.remove('open');
          window._calendar?.refetchEvents();
          showToast('Evento eliminado', 'success');
        } catch(e) { showToast(e.message, 'error'); }
      };
      document.getElementById('recurScopeTitle').textContent = 'Eliminar evento recurrente';
      document.getElementById('recurScopeConfirm').textContent = 'Eliminar';
      document.getElementById('recurScopeConfirm').className = 'btn btn-danger';
      document.getElementById('recurScopeOverlay').classList.add('open');
      return;
    }

    if (!confirm('¿Eliminar este evento?')) return;
    try {
      await fc_api('DELETE', APP_URL + '/api/events/' + editingId, { scope: 'all' });
      overlay.classList.remove('open');
      window._calendar?.refetchEvents();
      showToast('Evento eliminado', 'success');
    } catch(e) { showToast(e.message, 'error'); }
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const data = collectForm();
    if (!data.title) { showToast('El título es requerido', 'error'); return; }
    if (!document.getElementById('evStartDate').value) { showToast('Seleccioná una fecha', 'error'); return; }

    if (editingId && document.getElementById('evRecurring').checked) {
      // Show scope dialog for edits
      window._recurScopeCallback = async (scope) => {
        data.scope = scope;
        if (scope !== 'all') data.instance_date = currentInstanceDate;
        saveBtn.disabled = true;
        try {
          const res = await fc_api('PUT', APP_URL + '/api/events/' + editingId, data);
          overlay.classList.remove('open');
          window._calendar?.refetchEvents();
          showToast('Evento actualizado', 'success');
        } catch(e) { showToast(e.message, 'error'); }
        finally    { saveBtn.disabled = false; }
      };
      document.getElementById('recurScopeTitle').textContent    = 'Editar evento recurrente';
      document.getElementById('recurScopeConfirm').textContent  = 'Continuar';
      document.getElementById('recurScopeConfirm').className    = 'btn btn-primary';
      document.getElementById('recurScopeOverlay').classList.add('open');
      return;
    }

    saveBtn.disabled = true;
    try {
      if (editingId) {
        await fc_api('PUT', APP_URL + '/api/events/' + editingId, { ...data, scope: 'all' });
      } else {
        await fc_api('POST', APP_URL + '/api/events', data);
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

/* ── Recurring scope dialog ──────────────────────── */
(function initRecurDialog() {
  const overlay  = document.getElementById('recurScopeOverlay');
  const confirm  = document.getElementById('recurScopeConfirm');
  const cancel   = document.getElementById('recurScopeCancel');
  if (!overlay) return;

  confirm.addEventListener('click', () => {
    const scope = document.querySelector('[name="rec_scope"]:checked')?.value || 'this';
    overlay.classList.remove('open');
    window._recurScopeCallback?.(scope);
    window._recurScopeCallback = null;
  });
  cancel.addEventListener('click', () => { overlay.classList.remove('open'); window._recurScopeCallback = null; });
  overlay.addEventListener('click', e => {
    if (!overlay.querySelector('.modal').contains(e.target)) {
      overlay.classList.remove('open');
      window._recurScopeCallback = null;
    }
  });
})();

/* ══════════════════════════════════════════════════
   FullCalendar
══════════════════════════════════════════════════ */
(function initCalendar() {
  const el = document.getElementById('calendar');
  if (!el) return;

  /* ── Helpers ── */
  function calcHeight() {
    const topbar  = document.querySelector('.top-bar');
    const toolbar = document.querySelector('.cal-toolbar');
    const strip   = document.getElementById('dayWeekStrip');
    const bnav    = document.querySelector('.bottom-nav');
    const bnavH   = bnav && window.getComputedStyle(bnav).display !== 'none'
                    ? bnav.offsetHeight : 0;
    const stripH  = strip ? strip.offsetHeight : 0;
    const used = (topbar  ? topbar.offsetHeight  : 56)
               + (toolbar ? toolbar.offsetHeight : 60)
               + stripH + bnavH + 8;
    return Math.max(window.innerHeight - used, 300);
  }

  function updateDayStrip(currentDate) {
    const strip = document.getElementById('dayWeekStrip');
    if (!strip) return;
    const today = new Date(); today.setHours(0,0,0,0);
    const cur   = new Date(currentDate); cur.setHours(0,0,0,0);
    // Week starts Monday
    const dow   = cur.getDay();
    const mon   = new Date(cur);
    mon.setDate(cur.getDate() + (dow === 0 ? -6 : 1 - dow));
    const letters = ['L','M','X','J','V','S','D'];
    strip.innerHTML = '';
    for (let i = 0; i < 7; i++) {
      const day = new Date(mon);
      day.setDate(mon.getDate() + i);
      const isToday    = day.getTime() === today.getTime();
      const isSelected = day.getTime() === cur.getTime();
      const btn = document.createElement('button');
      btn.type      = 'button';
      btn.className = 'day-strip-item'
        + (isToday    ? ' is-today'    : '')
        + (isSelected ? ' is-selected' : '');
      btn.innerHTML = `<span class="day-strip-letter">${letters[i]}</span>`
                    + `<span class="day-strip-num">${day.getDate()}</span>`;
      btn.addEventListener('click', () => calendar.gotoDate(fmtDate(day)));
      strip.appendChild(btn);
    }
  }

  function updateTitle(view) {
    const months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio',
                    'Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const start = view.currentStart;
    let label;
    if (view.type === 'dayGridMonth') {
      label = months[start.getMonth()] + ' ' + start.getFullYear();
    } else if (view.type === 'timeGridWeek') {
      const end = new Date(+view.currentEnd - 1);
      label = `${start.getDate()} – ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear()}`;
    } else if (view.type === 'timeGridDay') {
      const days = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
      label = `${days[start.getDay()]} ${start.getDate()} ${months[start.getMonth()]}`;
    } else {
      label = months[start.getMonth()] + ' ' + start.getFullYear();
    }
    const t1 = document.getElementById('calTitle');
    const t2 = document.getElementById('topBarTitle');
    if (t1) t1.textContent = label;
    if (t2) t2.textContent = label;
  }

  /* ── Toolbar wiring — always runs, even if FullCalendar fails ── */
  function wireToolbar(cal) {
    document.getElementById('addEventBtn')
      ?.addEventListener('click', () => window.openEventModal?.());
    document.getElementById('calPrev')
      ?.addEventListener('click', () => { cal?.prev(); });
    document.getElementById('calNext')
      ?.addEventListener('click', () => { cal?.next(); });
    document.getElementById('calToday')
      ?.addEventListener('click', () => { cal?.today(); });
    document.querySelectorAll('.view-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        cal?.changeView(btn.dataset.view);
      });
    });
  }

  /* ── Abort with visible message if bundle missing ── */
  if (typeof FullCalendar === 'undefined') {
    wireToolbar(null);
    el.style.cssText = 'display:flex;align-items:center;justify-content:center;';
    el.innerHTML = '<p style="color:#a0a0c8;font-size:.9rem;text-align:center;padding:20px">'
      + '⚠️ No se pudo cargar el calendario.<br>Activá tu conexión y presioná<br>'
      + '"Actualizar aplicación" en Ajustes.</p>';
    return;
  }

  /* ── Initialize FullCalendar ── */
  let calendar;
  try {
    calendar = new FullCalendar.Calendar(el, {
      locale:          'es',
      initialView:     'timeGridWeek',
      firstDay:        1,
      headerToolbar:   false,
      height:          calcHeight(),
      nowIndicator:    true,
      editable:        false,
      selectable:      true,
      selectLongPressDelay: 500,   // touch long-press to create an event
      dayMaxEvents:    false,
      noEventsContent: 'Sin eventos',
      scrollTimeReset: false,
      allDayText:      'Día',
      slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

      eventContent(arg) {
        if (arg.view.type === 'dayGridMonth') {
          const c = arg.event.backgroundColor || 'var(--primary)';
          return { html: `<span class="fc-event-dot" style="background:${c}"></span>` };
        }
      },

      events(info, ok, fail) {
        fetch(APP_URL + `/api/events?start=${info.startStr}&end=${info.endStr}`,
              { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(r => r.json()).then(ok).catch(fail);
      },

      datesSet(info) {
        updateTitle(info.view);
        const strip = document.getElementById('dayWeekStrip');
        const isDay = info.view.type === 'timeGridDay';
        if (strip) {
          strip.style.display = isDay ? 'flex' : 'none';
          if (isDay) {
            updateDayStrip(info.view.currentStart);
            // Scroll to ~1h before now when viewing today
            const now = new Date();
            const viewStart = new Date(info.view.currentStart);
            viewStart.setHours(0,0,0,0);
            now.setSeconds(0,0);
            const isToday = viewStart.getTime() === (() => { const d=new Date(); d.setHours(0,0,0,0); return d.getTime(); })();
            if (isToday) {
              requestAnimationFrame(() =>
                calendar.scrollToTime({ hours: Math.max(0, now.getHours() - 1), minutes: now.getMinutes() })
              );
            }
            // Recalculate height after strip appears
            requestAnimationFrame(() => calendar.setOption('height', calcHeight()));
          }
        }
      },

      dateClick(info) {
        // Tap in month view → drill into that day
        if (info.view.type === 'dayGridMonth') {
          calendar.changeView('timeGridDay', info.date);
          document.querySelectorAll('.view-btn').forEach(b =>
            b.classList.toggle('active', b.dataset.view === 'timeGridDay')
          );
        }
        // In other views a simple tap does nothing; long-press creates events
      },

      // Long-press (touch) or drag (desktop) → create an event at the
      // selected day+time. info.startStr carries the precise time in
      // timeGrid views and a date-only string in month view.
      select(info) {
        navigator.vibrate?.(40);
        window.openEventModal?.(info.startStr);
        calendar.unselect();
      },

      eventClick(info) {
        const ev = info.event;
        if (ev.extendedProps.is_hybrid) {
          window.showToast?.('Este evento es privado', 'info');
          return;
        }
        window.openEventModal?.(null, {
          event_id: ev.extendedProps.event_id,
          title:    ev.title,
          start:    ev.startStr,
          end:      ev.endStr,
          extendedProps: ev.extendedProps,
        });
      },

      eventDidMount(info) {
        if (info.event.extendedProps.is_recurring && info.view.type !== 'dayGridMonth') {
          const dot = document.createElement('span');
          dot.style.cssText = 'display:inline-block;width:5px;height:5px;border-radius:50%;'
            + 'background:rgba(255,255,255,.7);margin-right:3px;vertical-align:middle;flex-shrink:0;';
          info.el.querySelector('.fc-event-title')?.prepend(dot);
        }
      },
    });

    // Wire toolbar BEFORE render so buttons work even if render errors
    wireToolbar(calendar);
    calendar.render();
    window._calendar = calendar;

    // Horizontal swipe → prev / next (only when horizontal movement dominates)
    let swipeX = null, swipeY = null;
    el.addEventListener('touchstart', e => {
      if (e.touches.length !== 1) { swipeX = swipeY = null; return; }
      swipeX = e.touches[0].clientX;
      swipeY = e.touches[0].clientY;
    }, { passive: true });
    el.addEventListener('touchend', e => {
      if (swipeX === null) return;
      const dx = e.changedTouches[0].clientX - swipeX;
      const dy = e.changedTouches[0].clientY - swipeY;
      swipeX = swipeY = null;
      if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy)) return;
      if (dx < 0) calendar.next(); else calendar.prev();
    }, { passive: true });

    window.addEventListener('resize', () => {
      window._calendar?.setOption('height', calcHeight());
    });

  } catch(err) {
    wireToolbar(null);
    el.style.cssText = 'display:flex;align-items:center;justify-content:center;';
    el.innerHTML = `<p style="color:#a0a0c8;font-size:.85rem;text-align:center;padding:20px">`
      + `⚠️ Error al inicializar el calendario:<br><code style="color:#f97;font-size:.8rem">`
      + err.message + `</code><br><br>Presioná "Actualizar aplicación" en Ajustes.</p>`;
    console.error('[Familia] FullCalendar init error:', err);
  }
})();

/* ── Helpers ─────────────────────────────────────── */
function fmtDate(d) {
  return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}
function fmtTime(d) {
  const m = d.getMinutes();
  return String(d.getHours()).padStart(2,'0') + ':' + String(m - m%5).padStart(2,'0');
}
