<?php
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
$items = $items ?? [];
?>
<div class="container py-4">
  <h1 class="h3 mb-3">Avis en attente</h1>

  <?php if (empty($items)): ?>
    <div class="alert alert-info">Aucun avis à modérer.</div>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Date</th>
          <th>Trajet</th>
          <th>Passager</th>
          <th>Note</th>
          <th>Commentaire</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $r): ?>
        <tr>
          <td><?= e($r['created_at'] ?? '') ?></td>
          <td>#<?= e($r['ride_id'] ?? '') ?></td>
          <td><?= e($r['passenger_id'] ?? '') ?></td>
          <td><?= e($r['note'] ?? '') ?></td>
          <td><?= e($r['comment'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
