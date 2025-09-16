<?php
/* app/Views/rides/list.php */

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* Badge  */
$prefBadge = function(string $type, $raw, string $icon) {
    $v = (string)($raw ?? '0');

    $labels = [
        'smoker'  => ['0'=>'N/A',        '1'=>'Non',           '2'=>'Oui'],
        'animals' => ['0'=>'N/A',        '1'=>'Non',           '2'=>'Oui'],
        'music'   => ['0'=>'N/A',        '1'=>'Plutôt non',    '2'=>'Avec plaisir'],
        'chatty'  => ['0'=>'N/A',        '1'=>'Discret',       '2'=>'Bavard'],
        'ac'      => ['0'=>'N/A',        '1'=>'Oui',           '2'=>'Peu/éteinte'],
    ];
    $label = $labels[$type][$v] ?? 'N/A';
    $cls = ($v === '0') ? 'bg-secondary' : 'bg-success';

    return '<span class="badge '.$cls.'"><i class="fas '.$icon.' me-1"></i>'.$label.'</span>';
};

$title = 'Liste des covoiturages • EcoRide';
?>

<h1 class="h4 mb-4">Résultats de votre recherche</h1>

<?php if (empty($rides)): ?>
  <div class="alert alert-info">
    Aucun covoiturage trouvé pour vos critères.
    <br>
    <a href="/rides" class="btn btn-outline-secondary mt-2">← Nouvelle recherche</a>
  </div>
<?php else: ?>
  <!-- Filtres -->
  <form action="/rides" method="get" class="row g-3 mb-4">
    <div class="col-md-3">
      <label for="price_max" class="form-label">Prix max</label>
      <input type="number" id="price_max" name="price_max" class="form-control"
             value="<?= h($_GET['price_max'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label for="duration_max" class="form-label">Durée max (heures)</label>
      <input type="number" id="duration_max" name="duration_max" class="form-control"
             value="<?= h($_GET['duration_max'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label for="min_note" class="form-label">Note minimum</label>
      <input type="number" step="0.1" id="min_note" name="min_note" class="form-control"
             value="<?= h($_GET['min_note'] ?? '') ?>">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-success w-100">Filtrer</button>
    </div>
  </form>

  <!-- Liste -->
  <div class="row g-3">
    <?php foreach ($rides as $r): ?>
      <?php
        $driverName = trim((string)($r['driver_display_name'] ?? 'Conducteur'));
        $avatar = $r['driver_avatar'] ?? '';
        if ($avatar && $avatar[0] !== '/') { $avatar = '/'.$avatar; }
        $avatarUrl = $avatar ?: 'https://api.dicebear.com/9.x/initials/svg?seed='.urlencode($driverName);
        $eco   = (int)($r['is_eco'] ?? 0) === 1;
        $seats = (int)($r['seats_left'] ?? 0);
        $price = (int)($r['price'] ?? 0);
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <!-- Conducteur -->
            <div class="d-flex align-items-center mb-3">
              <img src="<?= h($avatarUrl) ?>" class="rounded-circle border me-2" width="48" height="48" alt="Avatar">
              <div class="fw-bold"><?= h($driverName) ?></div>
            </div>

            <!-- Trajet -->
            <p class="mb-1"><strong><?= h($r['from_city']) ?></strong> → <strong><?= h($r['to_city']) ?></strong></p>
            <p class="mb-1">
              <?= h(date('d/m/Y H\hi', strtotime($r['date_start']))) ?>
              <?php if (!empty($r['date_end'])): ?> - <?= h(date('H\hi', strtotime($r['date_end']))) ?><?php endif; ?>
            </p>

            <div class="d-flex align-items-center mb-2 gap-2">
              <span class="fw-bold"><i class="fas fa-coins text-warning me-1"></i><?= $price ?> crédits</span>
              <span class="badge <?= $seats > 0 ? 'text-bg-success' : 'text-bg-danger' ?>">
                <?= $seats ?> place(s)
              </span>
              <?php if ($eco): ?><span class="badge text-bg-success">Éco</span><?php endif; ?>
            </div>

            <!-- Préférences du conducteur -->
            <div class="d-flex flex-wrap gap-1 mb-3">
              <?= $prefBadge('smoker',  $r['smoker']  ?? null, 'fa-smoking') ?>
              <?= $prefBadge('animals', $r['animals'] ?? null, 'fa-paw') ?>
              <?= $prefBadge('music',   $r['music']   ?? null, 'fa-music') ?>
              <?= $prefBadge('chatty',  $r['chatty']  ?? null, 'fa-comments') ?>
              <?= $prefBadge('ac',      $r['ac']      ?? null, 'fa-snowflake') ?>
            </div>

            <a href="/rides/show?id=<?= (int)$r['id'] ?>" class="btn btn-outline-primary w-100">Détail</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
