<?php
$cfg     = require BASE_PATH . '/app/Config/app.php';
$appUrl  = rtrim($cfg['url'], '/');
$u       = $currentUser ?? null;
$pgTitle = $pageTitle ?? 'FamilyCal';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= \App\Core\View::e($pgTitle) ?> — FamilyCal</title>
<link rel="icon" href="<?= $appUrl ?>/assets/images/icon-192.png" type="image/png">
<link rel="manifest" href="<?= $appUrl ?>/manifest.json">
<meta name="theme-color" content="#08080f">
<link rel="apple-touch-icon" href="<?= $appUrl ?>/assets/images/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= $appUrl ?>/assets/css/app.css">
</head>
<body>

<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo">
        <span class="logo-icon">🗓</span>
        <span class="logo-text">FamilyCal</span>
      </div>
      <button class="sidebar-close btn-icon" id="sidebarClose" aria-label="Cerrar menú">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= $appUrl ?>/calendar" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'calendar') ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Calendario</span>
      </a>
      <a href="<?= $appUrl ?>/group" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'group') ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Familia</span>
      </a>
      <div class="nav-section-label">Ajustes</div>
      <a href="<?= $appUrl ?>/settings" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'settings') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'categori') ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Perfil</span>
      </a>
      <a href="<?= $appUrl ?>/settings/categories" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'categori') ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
        <span>Categorías</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <?php if ($u): ?>
      <div class="user-card">
        <div class="avatar-sm" style="background:<?= \App\Core\View::e($u['color']) ?>">
          <?= \App\Core\View::e($u['avatar'] ?? mb_strtoupper(mb_substr($u['name'],0,1))) ?>
        </div>
        <div class="user-card-info">
          <span class="user-card-name"><?= \App\Core\View::e($u['name']) ?></span>
          <span class="user-card-email"><?= \App\Core\View::e($u['email']) ?></span>
        </div>
      </div>
      <?php endif; ?>
      <a href="<?= $appUrl ?>/logout" class="btn-logout">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Salir
      </a>
    </div>
  </aside>

  <!-- Main content -->
  <div class="main-wrap">
    <header class="top-bar">
      <button class="btn-icon sidebar-toggle" id="sidebarToggle" aria-label="Menú">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="top-bar-title" id="topBarTitle"><?= \App\Core\View::e($pgTitle) ?></div>
      <div class="top-bar-actions" id="topBarActions">
        <!-- Notification bell -->
        <div class="notif-bell-wrap" id="notifBellWrap">
          <button class="btn-icon notif-bell" id="notifBell" aria-label="Notificaciones">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-badge" id="notifBadge" style="display:none">0</span>
          </button>
          <!-- Notification panel -->
          <div class="notif-panel" id="notifPanel" style="display:none">
            <div class="notif-panel-header">
              <span class="notif-panel-title">Notificaciones</span>
              <button class="btn-text btn-sm" id="markAllRead">Marcar todas</button>
            </div>
            <div class="notif-list" id="notifList">
              <div class="notif-empty">Sin notificaciones</div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="page-content">
      <?= $content ?>
    </main>
  </div>

  <!-- Bottom nav (mobile) -->
  <nav class="bottom-nav">
    <a href="<?= $appUrl ?>/calendar" class="bn-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'calendar') ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span>Inicio</span>
    </a>
    <button class="bn-item bn-fab" id="mobileAddEvent" aria-label="Agregar evento">
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </button>
    <a href="<?= $appUrl ?>/group" class="bn-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'group') ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Familia</span>
    </a>
    <a href="<?= $appUrl ?>/settings" class="bn-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'settings') ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span>Ajustes</span>
    </a>
  </nav>
</div>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<script>
  const APP_URL  = '<?= $appUrl ?>';
  const CSRF     = '<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES) ?>';
  const APP_USER = <?= json_encode(['id' => $u['id'] ?? null, 'name' => $u['name'] ?? '', 'color' => $u['color'] ?? '#7c3aed']) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="<?= $appUrl ?>/assets/js/app.js"></script>
<script src="<?= $appUrl ?>/assets/js/notifications.js"></script>
<?php if (!empty($pageScripts)): ?>
  <?php foreach ($pageScripts as $s): ?>
    <script src="<?= $appUrl ?>/assets/js/<?= \App\Core\View::e($s) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
