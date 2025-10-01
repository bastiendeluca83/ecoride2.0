<?php
/**
 * app/Views/partials/_reviews_list.php
 * ------------------------------------
 * Affiche une liste d’avis utilisateur.
 *
 * Variables attendues :
 * - array $items : liste d'avis, chaque élément contenant :
 *     - 'note'       : note (int, ex: 4)
 *     - 'comment'    : commentaire (string)
 *     - 'created_at' : date de création (optionnelle)
 *
 * Rappel MVC :
 * - Ici je ne fais QUE de l’affichage (les données viennent du contrôleur).
 * - Chaque sortie est protégée par e() pour éviter XSS.
 */

/** Helper XSS */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* Valeur par défaut + gestion du cas vide */
$items = $items ?? [];
if (!$items) {
    echo '<div class="text-muted small">Aucun avis.</div>';
    return;
}
?>

<!-- Liste des avis -->
<ul class="list-unstyled mb-0">
  <?php foreach ($items as $it): ?>
    <li class="mb-2">
      <!-- Note -->
      <strong><?= (int)($it['note'] ?? 0) ?>/5</strong>
      — <?= e((string)($it['comment'] ?? '')) ?>

      <!-- Date si présente -->
      <?php if (!empty($it['created_at'])): ?>
        <div class="text-muted small">
          <?= e(date('d/m/Y', strtotime($it['created_at']))) ?>
        </div>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
