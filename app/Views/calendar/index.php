<?php $pageTitle = 'Calendario'; $pageScripts = ['calendar.js']; ?>
<div class="calendar-page">
  <!-- Toolbar -->
  <div class="cal-toolbar">
    <div class="cal-nav">
      <button class="btn-icon" id="calPrev" aria-label="Anterior">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <button class="btn-text" id="calToday">Hoy</button>
      <button class="btn-icon" id="calNext" aria-label="Siguiente">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
      <h2 class="cal-title" id="calTitle"></h2>
    </div>
    <div class="cal-views">
      <button class="view-btn active" data-view="dayGridMonth">Mes</button>
      <button class="view-btn" data-view="timeGridWeek">Semana</button>
      <button class="view-btn" data-view="timeGridDay">Día</button>
      <button class="view-btn" data-view="listWeek">Lista</button>
    </div>
    <button class="btn btn-primary btn-sm" id="addEventBtn">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <span class="hide-xs">Nuevo evento</span>
    </button>
  </div>

  <!-- FullCalendar mount -->
  <div id="calendar"></div>
</div>

<!-- ===== EVENT MODAL ===== -->
<div class="modal-overlay" id="eventModalOverlay" role="dialog" aria-modal="true" aria-labelledby="eventModalTitle">
  <div class="modal modal-lg" id="eventModal">
    <div class="modal-header">
      <h3 class="modal-title" id="eventModalTitle">Nuevo evento</h3>
      <button class="btn-icon modal-close" id="eventModalClose" aria-label="Cerrar">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <form id="eventForm" novalidate>
        <input type="hidden" id="eventId" name="event_id">

        <!-- Title -->
        <div class="form-group">
          <input class="form-input form-input-lg" type="text" id="evTitle" name="title"
                 placeholder="Título del evento" required>
        </div>

        <!-- Dates -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Inicio</label>
            <div class="datetime-group">
              <input class="form-input" type="date" id="evStartDate" name="start_date" required>
              <input class="form-input time-input" type="text" id="evStartTime" name="start_time"
                     placeholder="09:00" autocomplete="off">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Fin</label>
            <div class="datetime-group">
              <input class="form-input" type="date" id="evEndDate" name="end_date" required>
              <input class="form-input time-input" type="text" id="evEndTime" name="end_time"
                     placeholder="10:00" autocomplete="off">
            </div>
          </div>
        </div>

        <!-- All day toggle -->
        <div class="form-check-row">
          <label class="toggle-label">
            <input type="checkbox" id="evAllDay" name="all_day" class="toggle-input">
            <span class="toggle-track"><span class="toggle-thumb"></span></span>
            <span>Todo el día</span>
          </label>
        </div>

        <!-- Location -->
        <div class="form-group">
          <div class="input-icon-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <input class="form-input" type="text" id="evLocation" name="location" placeholder="Ubicación (opcional)">
          </div>
        </div>

        <!-- Description -->
        <div class="form-group">
          <textarea class="form-input form-textarea" id="evDescription" name="description"
                    placeholder="Descripción (opcional)" rows="2"></textarea>
        </div>

        <!-- Category + Visibility row -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Categoría</label>
            <select class="form-select" id="evCategory" name="category_id">
              <option value="">Sin categoría</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" data-color="<?= \App\Core\View::e($cat['color']) ?>">
                <?= \App\Core\View::e($cat['icon'] . ' ' . $cat['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Visibilidad</label>
            <div class="visibility-group" id="visibilityGroup">
              <button type="button" class="vis-btn active" data-value="public" title="Público: todos lo ven">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <span>Público</span>
              </button>
              <button type="button" class="vis-btn" data-value="hybrid" title="Híbrido: muestra ocupado pero no detalles">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                <span>Híbrido</span>
              </button>
              <button type="button" class="vis-btn" data-value="private" title="Privado: solo vos lo ves">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>Privado</span>
              </button>
            </div>
            <input type="hidden" id="evVisibility" name="visibility" value="public">
          </div>
        </div>

        <!-- Participants -->
        <div class="form-group" id="participantsSection">
          <label class="form-label">Participantes</label>
          <div class="participants-grid" id="participantsList">
            <?php foreach ($members as $m): ?>
            <label class="participant-chip" data-id="<?= $m['id'] ?>">
              <input type="checkbox" name="participants[]" value="<?= $m['id'] ?>"
                     class="participant-check"
                     <?= $m['id'] == ($currentUser['id'] ?? 0) ? 'checked disabled' : '' ?>>
              <span class="avatar-xs" style="background:<?= \App\Core\View::e($m['color']) ?>">
                <?= \App\Core\View::e($m['avatar'] ?? mb_strtoupper(mb_substr($m['name'],0,1))) ?>
              </span>
              <span class="participant-name"><?= \App\Core\View::e($m['name']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Recurrence toggle -->
        <div class="form-check-row">
          <label class="toggle-label">
            <input type="checkbox" id="evRecurring" name="is_recurring" class="toggle-input">
            <span class="toggle-track"><span class="toggle-thumb"></span></span>
            <span>Evento recurrente</span>
          </label>
        </div>

        <!-- Recurrence panel -->
        <div class="recurrence-panel" id="recurrencePanel" style="display:none">
          <div class="form-group">
            <label class="form-label">Repetir</label>
            <div class="recurrence-types" id="recurrenceTypes">
              <button type="button" class="rec-type-btn active" data-type="weekly">Semanal</button>
              <button type="button" class="rec-type-btn" data-type="monthly">Mensual</button>
              <button type="button" class="rec-type-btn" data-type="annual">Anual</button>
            </div>
            <input type="hidden" id="evRecurrenceType" name="recurrence_type" value="weekly">
          </div>

          <!-- Weekly options -->
          <div class="rec-options" id="recWeekly">
            <label class="form-label">Días de la semana</label>
            <div class="weekdays-grid">
              <label class="weekday-chip"><input type="checkbox" value="monday" class="weekday-cb"> Lun</label>
              <label class="weekday-chip"><input type="checkbox" value="tuesday" class="weekday-cb"> Mar</label>
              <label class="weekday-chip"><input type="checkbox" value="wednesday" class="weekday-cb"> Mié</label>
              <label class="weekday-chip"><input type="checkbox" value="thursday" class="weekday-cb"> Jue</label>
              <label class="weekday-chip"><input type="checkbox" value="friday" class="weekday-cb"> Vie</label>
              <label class="weekday-chip"><input type="checkbox" value="saturday" class="weekday-cb"> Sáb</label>
              <label class="weekday-chip"><input type="checkbox" value="sunday" class="weekday-cb"> Dom</label>
            </div>
          </div>

          <!-- Monthly options -->
          <div class="rec-options" id="recMonthly" style="display:none">
            <div class="rec-mode-row">
              <label class="radio-label">
                <input type="radio" name="rec_monthly_mode" value="day_of_month" checked> Día del mes
              </label>
              <label class="radio-label">
                <input type="radio" name="rec_monthly_mode" value="day_of_week"> Día de semana
              </label>
            </div>
            <div id="recMonthDayOption">
              <label class="form-label">Día</label>
              <input class="form-input form-input-sm" type="number" id="recMonthDay" min="1" max="31" placeholder="29">
            </div>
            <div id="recMonthWeekOption" style="display:none">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Ocurrencia</label>
                  <select class="form-select" id="recMonthOccurrence">
                    <option value="1">Primero/a</option>
                    <option value="2">Segundo/a</option>
                    <option value="3">Tercero/a</option>
                    <option value="4">Cuarto/a</option>
                    <option value="-1">Último/a</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Día</label>
                  <select class="form-select" id="recMonthWeekday">
                    <option value="monday">Lunes</option>
                    <option value="tuesday">Martes</option>
                    <option value="wednesday">Miércoles</option>
                    <option value="thursday">Jueves</option>
                    <option value="friday">Viernes</option>
                    <option value="saturday">Sábado</option>
                    <option value="sunday">Domingo</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Annual options -->
          <div class="rec-options" id="recAnnual" style="display:none">
            <div class="rec-mode-row">
              <label class="radio-label">
                <input type="radio" name="rec_annual_mode" value="fixed_date" checked> Fecha fija
              </label>
              <label class="radio-label">
                <input type="radio" name="rec_annual_mode" value="day_of_week"> Día de semana
              </label>
            </div>
            <div id="recAnnualFixedOption">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Mes</label>
                  <select class="form-select" id="recAnnualMonth">
                    <option value="1">Enero</option><option value="2">Febrero</option>
                    <option value="3">Marzo</option><option value="4">Abril</option>
                    <option value="5">Mayo</option><option value="6">Junio</option>
                    <option value="7">Julio</option><option value="8">Agosto</option>
                    <option value="9">Septiembre</option><option value="10">Octubre</option>
                    <option value="11">Noviembre</option><option value="12">Diciembre</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Día</label>
                  <input class="form-input form-input-sm" type="number" id="recAnnualDay" min="1" max="31" placeholder="29">
                </div>
              </div>
            </div>
            <div id="recAnnualWeekOption" style="display:none">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Mes</label>
                  <select class="form-select" id="recAnnualWeekMonth">
                    <option value="1">Enero</option><option value="2">Febrero</option>
                    <option value="3">Marzo</option><option value="4">Abril</option>
                    <option value="5">Mayo</option><option value="6">Junio</option>
                    <option value="7">Julio</option><option value="8">Agosto</option>
                    <option value="9">Septiembre</option><option value="10">Octubre</option>
                    <option value="11">Noviembre</option><option value="12">Diciembre</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Ocurrencia</label>
                  <select class="form-select" id="recAnnualOccurrence">
                    <option value="1">Primero/a</option>
                    <option value="2">Segundo/a</option>
                    <option value="3">Tercero/a</option>
                    <option value="4">Cuarto/a</option>
                    <option value="-1">Último/a</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Día</label>
                  <select class="form-select" id="recAnnualWeekday">
                    <option value="monday">Lunes</option>
                    <option value="tuesday">Martes</option>
                    <option value="wednesday">Miércoles</option>
                    <option value="thursday">Jueves</option>
                    <option value="friday">Viernes</option>
                    <option value="saturday">Sábado</option>
                    <option value="sunday">Domingo</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <div class="form-group mt-sm">
            <label class="form-label">Fin de la recurrencia</label>
            <input class="form-input" type="date" id="evRecurrenceEnd" name="recurrence_end">
            <span class="form-hint">Dejá vacío para que no tenga fin.</span>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" id="eventModalCancel">Cancelar</button>
      <button type="button" class="btn btn-danger" id="eventDeleteBtn" style="display:none">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        Eliminar
      </button>
      <button type="submit" form="eventForm" class="btn btn-primary" id="eventSaveBtn">
        <span id="eventSaveTxt">Crear evento</span>
      </button>
    </div>
  </div>
</div>

<!-- ===== RECURRING SCOPE DIALOG ===== -->
<div class="modal-overlay" id="recurScopeOverlay">
  <div class="modal" style="max-width:380px">
    <div class="modal-header">
      <h3 class="modal-title" id="recurScopeTitle">Editar evento recurrente</h3>
    </div>
    <div class="modal-body" style="gap:10px">
      <p class="text-secondary" style="font-size:.88rem;margin-bottom:4px">¿Cuáles eventos querés modificar?</p>
      <label class="radio-option-row">
        <input type="radio" name="rec_scope" value="this" checked>
        <div><strong>Solo este evento</strong><span class="radio-desc">Solo se modifica esta ocurrencia.</span></div>
      </label>
      <label class="radio-option-row">
        <input type="radio" name="rec_scope" value="following">
        <div><strong>Este y los siguientes</strong><span class="radio-desc">Se modifica desde esta fecha en adelante.</span></div>
      </label>
      <label class="radio-option-row">
        <input type="radio" name="rec_scope" value="all">
        <div><strong>Todos los eventos</strong><span class="radio-desc">Se modifican todas las ocurrencias.</span></div>
      </label>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="recurScopeCancel">Cancelar</button>
      <button class="btn btn-primary" id="recurScopeConfirm">Continuar</button>
    </div>
  </div>
</div>

<!-- Time picker dropdown -->
<div class="time-picker-dropdown" id="timePicker" style="display:none">
  <div class="time-picker-inner" id="timePickerInner"></div>
</div>

<script>
window.APP_CATEGORIES = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
window.APP_MEMBERS    = <?= json_encode($members, JSON_UNESCAPED_UNICODE) ?>;
</script>
