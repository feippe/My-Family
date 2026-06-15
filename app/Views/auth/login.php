<div class="auth-card">
  <div class="auth-card-header">
    <div class="auth-logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12.2039C2 9.91549 2 8.77128 2.5192 7.82274C3.0384 6.87421 3.98695 6.28551 5.88403 5.10813L7.88403 3.86687C9.88939 2.62229 10.8921 2 12 2C13.1079 2 14.1106 2.62229 16.116 3.86687L18.116 5.10812C20.0131 6.28551 20.9616 6.87421 21.4808 7.82274C22 8.77128 22 9.91549 22 12.2039V13.725C22 17.6258 22 19.5763 20.8284 20.7881C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.7881C2 19.5763 2 17.6258 2 13.725V12.2039Z"/><path d="M9 16C9.85038 16.6303 10.8846 17 12 17C13.1154 17 14.1496 16.6303 15 16"/></svg>
    </div>
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
