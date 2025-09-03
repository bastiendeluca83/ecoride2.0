<?php if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } } ?>
<div class="container py-4">
  <h1 class="h3 mb-3">Avis en attente</h1>
  <?php if (empty($items)): ?>
    <div class="alert alert-info">Aucun avis à modérer.</div>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead><tr><th>Date</th><th>Trajet</th><th>Passager</th><th>Note</th><th>Commentaire</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $r): ?>
        <tr>
          <td><?= e($r['created_at']) ?></td>
          <td>#<?= e($r['ride_id']) ?></td>
          <td><?= e($r['passenger_id']) ?></td>
          <td><?= e($r['note']) ?></td>
          <td><?= e($r['comment']) ?></td>
          <td class="text-nowrap">
            <form method="post" action="<?= e(BASE_URL.'employee/reviews/approve') ?>" class="d-inline">
              <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf'] ?? '') ?>">
              <input type="hidden" name="id" value="<?= e($r['id']) ?>">
              <button class="btn btn-sm btn-success">Approuver</button>
            </form>
            <form method="post" action="<?= e(BASE_URL.'employee/reviews/reject') ?>" class="d-inline ms-2">
              <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf'] ?? '') ?>">
              <input type="hidden" name="id" value="<?= e($r['id']) ?>">
              <input type="hidden" name="reason" value="">
              <button class="btn btn-sm btn-outline-danger">Rejeter</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
