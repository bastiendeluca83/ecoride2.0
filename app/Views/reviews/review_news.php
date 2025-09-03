<?php if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } } ?>
<div class="container py-4">
  <h1 class="h3 mb-3">Laisser un avis</h1>
  <form method="post" action="<?= e(BASE_URL.'reviews/new') ?>">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf'] ?? '') ?>">
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

    <div class="mb-3">
      <label class="form-label">Note (1 à 5)</label>
      <input type="number" name="note" min="1" max="5" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Commentaire</label>
      <textarea name="comment" class="form-control" rows="4" placeholder="Votre ressenti..."></textarea>
    </div>

    <button class="btn btn-success">Envoyer</button>
  </form>
</div>
