<?php $pageTitle = 'Crear grupo'; ?>
<div class="page-container page-narrow">
  <div class="empty-state">
    <div class="empty-icon">🏠</div>
    <h2>Creá tu grupo familiar</h2>
    <p>Dale un nombre a tu grupo para comenzar a compartir el calendario con tu familia.</p>
    <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= \App\Core\View::e($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="<?= $appUrl ?>/group" class="create-group-form">
      <div class="form-group">
        <input class="form-input form-input-lg" type="text" name="name"
               placeholder="Ej: Los García" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-full">
        Crear grupo familiar
      </button>
    </form>
  </div>
</div>
