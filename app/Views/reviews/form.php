<?php
/** @var string|null $error */
/** @var string|null $token */
/** @var array|null  $ride */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div class="container my-4">
  <h1 class="h3 mb-3">Laisser un avis</h1>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php return; ?>
  <?php endif; ?>

  <?php if ($ride): ?>
    <div class="card mb-3"><div class="card-body">
      <div><strong>Trajet :</strong> <?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></div>
      <div><strong>Date :</strong> <?= !empty($ride['date_end']) ? e(date('d/m/Y H:i', strtotime($ride['date_end']))) : e(date('d/m/Y H:i', strtotime($ride['date_start'] ?? 'now'))) ?></div>
    </div></div>
  <?php endif; ?>

  <form method="post" action="/reviews" class="card">
    <div class="card-body">
      <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

      <div class="mb-3">
        <label class="form-label">Note</label>
        <select name="note" class="form-select" required>
          <?php for ($i=5; $i>=1; $i--): ?>
            <option value="<?= $i ?>"><?= $i ?> ★</option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Commentaire (optionnel)</label>
        <textarea name="comment" rows="4" class="form-control" placeholder="Votre retour aide les autres passagers."></textarea>
      </div>

      <button class="btn btn-success">Envoyer mon avis</button>
    </div>
  </form>
</div>
