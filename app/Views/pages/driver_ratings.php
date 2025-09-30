<?php
/**
 * Page : Mes avis (uniquement approuvés)
 * Variables attendues (depuis GeneralController::ratings) :
 * - float|null $avg
 * - int        $count
 * - array      $reviews       liste d’avis approuvés
 * - array      $distribution  [5=>n,4=>n,3=>n,2=>n,1=>n]
 */
if (!function_exists('e')) {
    function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$avg          = $avg ?? null;
$count        = (int)($count ?? 0);
$reviews      = is_array($reviews ?? null) ? $reviews : [];
$distribution = $distribution ?? [1=>0,2=>0,3=>0,4=>0,5=>0];

$ratingInclude = __DIR__ . '/../partials/_rating_badge.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <a href="<?= BASE_URL ?>user/dashboard" class="btn btn-outline-secondary btn-sm mb-3">← Retour</a>

    <h1 class="h4 mb-3">Mes avis</h1>

    <div class="d-flex align-items-center gap-3 mb-4">
      <?php if ($avg !== null && file_exists($ratingInclude)): ?>
        <?php
          // Le partial attend $avg, $count et $small
          $small = false;
          include $ratingInclude;
        ?>
      <?php elseif ($avg !== null): ?>
        <span class="badge text-bg-primary"><?= number_format((float)$avg, 1, ',', ' ') ?>/5 (<?= (int)$count ?>)</span>
      <?php else: ?>
        <span class="badge text-bg-secondary">Pas encore de note</span>
      <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
      <?php foreach ([5,4,3,2,1] as $n): ?>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="border rounded-3 p-2 text-center bg-light">
            <div class="fw-bold"><?= $n ?> ★</div>
            <div class="display-6 fw-semibold"><?= (int)($distribution[$n] ?? 0) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <h2 class="h5">Tous les avis</h2>
    <?php if (!empty($reviews)): ?>
      <ul class="list-unstyled mb-0">
        <?php foreach ($reviews as $rv): ?>
          <?php
            $note = (int)($rv['note'] ?? $rv['rating'] ?? 0);
            $date = $rv['created_at'] ?? $rv['date'] ?? null;
            $comm = $rv['commentaire'] ?? $rv['comment'] ?? '';
            $dateLabel = '';
            if ($date) {
              $ts = is_numeric($date) ? (int)$date : strtotime((string)$date);
              if ($ts) { $dateLabel = date('d/m/Y', $ts); }
            }
          ?>
          <li class="mb-3 border-bottom pb-2">
            <div class="mb-1">
              <strong><?= $note ?>/5</strong>
              <?php if ($dateLabel): ?>
                <span class="text-muted small ms-2"><?= e($dateLabel) ?></span>
              <?php endif; ?>
            </div>
            <div><?= nl2br(e($comm)) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="text-muted">Aucun avis pour le moment.</div>
    <?php endif; ?>
  </div>
</div>
