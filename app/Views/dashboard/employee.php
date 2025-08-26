<?php
/** @var string $title */
/** @var array  $incidents */
/** @var string $crossLabel */
/** @var string $crossHref */
/** @var string $csrf */
/** @var string $currentUrl */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0"><?= e($title ?? 'Espace Employé') ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="<?= e($crossHref ?? '/user/dashboard') ?>">
        <?= e($crossLabel ?? 'Espace utilisateur') ?>
      </a>
      <form method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="redirect" value="<?= e($currentUrl) ?>">
        <button class="btn btn-outline-secondary">Déconnexion</button>
      </form>
    </div>
  </div>

  <div class="card shadow-sm"><div class="card-body">
    <h5 class="card-title">Annulations récentes</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>#</th><th>Passager</th><th>Trajet</th><th>Date trajet</th><th>Crédits</th><th>Quand</th></tr></thead>
        <tbody>
        <?php foreach (($incidents ?? []) as $b): ?>
          <tr>
            <td>#<?= e($b['id']) ?></td>
            <td><?= e($b['passenger_email'] ?? '') ?></td>
            <td><?= e($b['from_city'] ?? '') ?> → <?= e($b['to_city'] ?? '') ?></td>
            <td><?= e($b['date_start'] ?? '') ?></td>
            <td><?= e($b['credits_spent'] ?? 0) ?></td>
            <td><?= e($b['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; if (empty($incidents)): ?>
          <tr><td colspan="6" class="text-muted">Aucun incident.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div></div>
</div>
