<?php
/**
 * Variables attendues :
 * - array $items : liste d'avis avec clés 'note','comment','created_at'
 */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$items = $items ?? [];
if (!$items) {
    echo '<div class="text-muted small">Aucun avis.</div>';
    return;
}
?>
<ul class="list-unstyled mb-0">
  <?php foreach ($items as $it): ?>
    <li class="mb-2">
      <strong><?= (int)($it['note'] ?? 0) ?>/5</strong>
      — <?= e((string)($it['comment'] ?? '')) ?>
      <?php if (!empty($it['created_at'])): ?>
        <div class="text-muted small"><?= e(date('d/m/Y', strtotime($it['created_at']))) ?></div>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
