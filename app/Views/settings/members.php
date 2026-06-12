<?php $pageTitle = 'Integrantes'; ?>
<div class="page-container page-narrow">
  <h1 class="page-title">Integrantes</h1>
  <div class="members-grid">
    <?php foreach ($members as $m): ?>
    <div class="member-card">
      <div class="avatar-lg" style="background:<?= \App\Core\View::e($m['color']) ?>">
        <?= \App\Core\View::e($m['avatar'] ?? mb_strtoupper(mb_substr($m['name'],0,1))) ?>
      </div>
      <div class="member-info">
        <span class="member-name"><?= \App\Core\View::e($m['name']) ?></span>
        <span class="member-email"><?= \App\Core\View::e($m['email']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
