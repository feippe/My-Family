<div class="auth-card">
  <div class="auth-card-header">
    <div class="auth-logo">🗓</div>
    <h1 class="auth-title">FamilyCal</h1>
    <p class="auth-subtitle">Iniciá sesión en tu calendario familiar</p>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e): ?>
      <span><?= \App\Core\View::e($e) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form class="auth-form" method="POST" action="<?= $appUrl ?>/login" novalidate>
    <div class="form-group">
      <label class="form-label" for="email">Email</label>
      <input class="form-input" type="email" id="email" name="email"
             value="<?= \App\Core\View::e($email ?? '') ?>"
             placeholder="tu@email.com" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label" for="password">Contraseña</label>
      <div class="input-with-toggle">
        <input class="form-input" type="password" id="password" name="password"
               placeholder="Tu contraseña" required>
        <button type="button" class="input-toggle-vis" aria-label="Mostrar contraseña" tabindex="-1">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-full">
      <span>Entrar</span>
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    </button>
  </form>

  <p class="auth-switch">
    ¿No tenés cuenta?
    <a href="<?= $appUrl ?>/register">Crear cuenta</a>
  </p>
</div>
