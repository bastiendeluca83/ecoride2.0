<?php
/** @var string $title */
/** @var array  $incidents */
/** @var array  $pending */
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

  <!-- ====== Tableau 1 : Incidents (annulations + avis <= 3 en PENDING) ====== -->
  <div class="card shadow-sm mb-4"><div class="card-body">
    <h5 class="card-title">Incidents récents</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Passager</th>
            <th>Trajet</th>
            <th>Date trajet</th>
            <th>Crédits / Note</th>
            <th>Quand</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (($incidents ?? []) as $b): ?>
          <tr>
            <td>#<?= e($b['id']) ?></td>
            <td><?= e($b['passenger_email'] ?? '') ?></td>
            <td><?= e($b['from_city'] ?? '') ?> → <?= e($b['to_city'] ?? '') ?></td>
            <td><?= e($b['date_start'] ?? '') ?></td>
            <td><?= e($b['credits_spent'] ?? '') ?></td>
            <td><?= e($b['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; if (empty($incidents)): ?>
          <tr><td colspan="6" class="text-muted">Aucun incident.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div></div>

  <!-- ====== Tableau 2 : Avis en attente (même page) ====== -->
  <div class="card shadow-sm"><div class="card-body">
    <h5 class="card-title">Avis en attente</h5>

    <?php if (empty($pending)): ?>
      <div class="alert alert-info mb-0">Aucun avis à modérer.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>Date</th>
              <th>Trajet</th>
              <th>Passager</th>
              <th>Note</th>
              <th>Commentaire</th>
              <th style="width:220px;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pending as $r): ?>
            <tr>
              <td><?= e($r['created_at'] ?? '') ?></td>
              <td>#<?= e($r['ride_id'] ?? '') ?></td>
              <td><?= e($r['passenger_id'] ?? '') ?></td>
              <td><?= e($r['note'] ?? '') ?></td>
              <td><?= nl2br(e($r['comment'] ?? '')) ?></td>
              <td>
                <form method="post" action="/employee/reviews" class="d-inline">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id"   value="<?= e($r['id'] ?? '') ?>">
                  <input type="hidden" name="action" value="approve">
                  <button class="btn btn-success btn-sm">Valider</button>
                </form>

                <form method="post" action="/employee/reviews" class="d-inline ms-2">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id"   value="<?= e($r['id'] ?? '') ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="reason" value="">
                  <button class="btn btn-danger btn-sm">Refuser</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div></div>
</div>
