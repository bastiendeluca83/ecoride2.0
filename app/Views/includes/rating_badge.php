<?php
/**
 * app/Views/partials/_rating_badge.php
 * ------------------------------------
 * Petit composant réutilisable pour afficher une note moyenne (étoiles).
 *
 * Variables attendues :
 * - float $avg   : note moyenne (ex: 4.3)
 * - int   $count : nombre d’avis associés (optionnel, ex: 12)
 * - bool  $small : affichage compact (optionnel)
 *
 * Usage typique dans une vue :
 *   $avg = 4.3; $count = 12; $small = true;
 *   include __DIR__.'/_rating_badge.php';
 */

/** Helper XSS */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}

/* Sécurisation + valeurs par défaut */
$avg   = isset($avg) ? (float)$avg : null;
$count = isset($count) ? (int)$count : null;
$small = !empty($small);

/* Si aucune note, on ne rend rien */
if ($avg === null) return;

/* Calcul du rendu étoiles :
   - $full  : nombre d’étoiles pleines
   - $half  : 1 si une demi-étoile (≥ 0.5)
   - $empty : reste d’étoiles vides jusqu’à 5 */
$full  = (int) floor($avg);
$half  = ($avg - $full) >= 0.5 ? 1 : 0;
$empty = 5 - $full - $half;

/* Style CSS : badge Bootstrap + version compacte ou non */
$cls = $small ? 'badge text-bg-warning' : 'badge text-bg-warning';
$fs  = $small ? 'small' : '';
?>

<!-- Badge note -->
<span class="<?= $cls ?> <?= $fs ?>" title="Note moyenne du conducteur">
  <!-- Pleines -->
  <?php for ($i=0;$i<$full;$i++): ?>★<?php endfor; ?>
  <!-- Demi -->
  <?php if ($half): ?>☆<?php endif; ?>
  <!-- Vides -->
  <?php for ($i=0;$i<$empty;$i++): ?>☆<?php endfor; ?>

  <!-- Texte : note /5 + nombre d’avis -->
  <span class="ms-1">
    <?= e(number_format($avg, 1)) ?>/5<?= $count!==null ? ' · '.(int)$count.' avis' : '' ?>
  </span>
</span>
