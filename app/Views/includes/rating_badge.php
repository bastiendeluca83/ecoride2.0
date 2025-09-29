<?php
/**
 * Variables attendues :
 * - float $avg   (ex: 4.3)
 * - int   $count (ex: 12)  (facultatif)
 * - bool  $small (facultatif)
 */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$avg   = isset($avg) ? (float)$avg : null;
$count = isset($count) ? (int)$count : null;
$small = !empty($small);

if ($avg === null) return;

$full  = (int) floor($avg);
$half  = ($avg - $full) >= 0.5 ? 1 : 0;
$empty = 5 - $full - $half;

$cls = $small ? 'badge text-bg-warning' : 'badge text-bg-warning';
$fs  = $small ? 'small' : '';
?>
<span class="<?= $cls ?> <?= $fs ?>" title="Note moyenne du conducteur">
  <?php for ($i=0;$i<$full;$i++): ?>★<?php endfor; ?>
  <?php if ($half): ?>☆<?php endif; ?>
  <?php for ($i=0;$i<$empty;$i++): ?>☆<?php endfor; ?>
  <span class="ms-1"><?= e(number_format($avg, 1)) ?>/5<?= $count!==null ? ' · '.(int)$count.' avis' : '' ?></span>
</span>
