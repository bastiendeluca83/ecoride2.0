<?php
/* Vue MVC — Avis en attente (modération par employé/admin)
   Objectif : afficher la liste des avis laissés par les passagers
   et permettre à un employé de les valider ou de les refuser.
*/

/* Helper d’échappement pour sécuriser l’affichage. */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}

/* Je prépare mes variables pour éviter toute notice. */
$items = $items ?? [];            // Liste d’avis en attente
$csrf  = $_SESSION['csrf'] ?? ''; // Jeton CSRF pour sécuriser les actions POST
?>

<div class="container py-4">
  <h1 class="h3 mb-3">Avis en attente</h1>

  <!-- Si aucun avis à modérer, je montre un simple message. -->
  <?php if (empty($items)): ?>
    <div class="alert alert-info">Aucun avis à modérer.</div>
  <?php else: ?>
    <!-- Sinon, j’affiche un tableau Bootstrap -->
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
      <?php foreach ($items as $r): ?>
        <tr>
          <!-- J’affiche les infos essentielles de l’avis -->
          <td><?= e($r['created_at'] ?? '') ?></td>
          <td>#<?= e($r['ride_id'] ?? '') ?></td>
          <td><?= e($r['passenger_id'] ?? '') ?></td>
          <td><?= e($r['note'] ?? '') ?></td>
          <td><?= nl2br(e($r['comment'] ?? '')) ?></td>
          <td>
            <!-- Bouton “Valider” : je poste en précisant action=approve -->
            <form method="post" action="/employee/reviews" class="d-inline">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="id"   value="<?= e($r['id'] ?? '') ?>">
              <input type="hidden" name="action" value="approve">
              <button class="btn btn-success btn-sm">Valider</button>
            </form>

            <!-- Bouton “Refuser” : je poste en précisant action=reject -->
            <form method="post" action="/employee/reviews" class="d-inline ms-2">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="id"   value="<?= e($r['id'] ?? '') ?>">
              <input type="hidden" name="action" value="reject">
              <!-- Je pourrais ici stocker la raison (optionnelle) -->
              <input type="hidden" name="reason" value="">
              <button class="btn btn-danger btn-sm">Refuser</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
