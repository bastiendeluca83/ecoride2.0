<?php
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div class="container py-4">
  <h1 class="h3 mb-3">Laisser un avis</h1>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="<?= e(BASE_URL.'reviews') ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf ?? ($_SESSION['csrf'] ?? '')) ?>">
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

        <div class="mb-3">
          <label class="form-label">Note (1 à 5)</label>
          <input type="number" class="form-control" name="note" min="1" max="5" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Commentaire</label>
          <textarea class="form-control" name="comment" rows="4" placeholder="Votre ressenti..."></textarea>
        </div>

        <button class="btn btn-success">Envoyer mon avis</button>
      </form>
    </div>
  </div>
</div>
