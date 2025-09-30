<?php
/* app/Views/rides/show.php */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

if (!$ride) {
    echo '<div class="alert alert-danger">Trajet introuvable.</div>';
    return;
}

$title = 'Trajet ' . h($ride['from_city']) . ' → ' . h($ride['to_city']) . ' • EcoRide';

$eco   = (int)($ride['is_eco'] ?? 0) === 1;
$seats = (int)($ride['seats_left'] ?? 0);
$price = (int)($ride['price'] ?? 0);

$driverName = trim((string)($ride['driver_display_name'] ?? 'Conducteur'));
$driverId   = (int)($ride['driver_id'] ?? 0);
$profileUrl = $driverId > 0 ? (BASE_URL ?? '').'users/profile?id='.$driverId : '#';
$ratingsUrl = (BASE_URL ?? '').'drivers/ratings?id='.$driverId;

$avatar = $ride['driver_avatar'] ?? '';
if ($avatar && $avatar[0] !== '/') { $avatar = '/'.$avatar; }
$avatarUrl = $avatar ?: 'https://api.dicebear.com/9.x/initials/svg?seed='.urlencode($driverName);

/* helper libellés prefs */
$prefLabel = function(string $type, $v): string {
    $v = (string)($v ?? '0');
    $labels = [
        'smoker'  => ['0'=>'N/A',        '1'=>'Non',           '2'=>'Oui'],
        'animals' => ['0'=>'N/A',        '1'=>'Non',           '2'=>'Oui'],
        'music'   => ['0'=>'N/A',        '1'=>'Plutôt non',    '2'=>'Avec plaisir'],
        'chatty'  => ['0'=>'N/A',        '1'=>'Discret',       '2'=>'Bavard'],
        'ac'      => ['0'=>'N/A',        '1'=>'Oui',           '2'=>'Peu/éteinte'],
    ];
    return $labels[$type][$v] ?? 'N/A';
};

/* includes (rating + liste avis) */
$ratingInclude  = __DIR__ . '/../partials/_rating_badge.php';
$reviewsInclude = __DIR__ . '/../partials/_reviews_list.php';
?>

<a href="/rides" class="btn btn-outline-secondary mb-4">← Retour aux covoiturages</a>

<div class="d-flex align-items-center gap-3 mb-3">
  <h1 class="mb-0"><?= h($ride['from_city']) ?> → <?= h($ride['to_city']) ?></h1>
  <?php if ($eco): ?><span class="badge text-bg-success">Éco</span><?php endif; ?>
  <?php
    // Affiche la moyenne si disponible (cliquable vers avis conducteur)
    if (isset($avgNote) && $avgNote !== null && file_exists($ratingInclude)) {
        $avg = (float)$avgNote;
        $count = isset($reviews) ? count($reviews) : null; // fallback si non injecté
        echo '<a href="'.h($ratingsUrl).'" class="text-decoration-none">';
        include $ratingInclude;
        echo '</a>';
    }
  ?>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <p class="mb-1"><strong>Départ : </strong><?= h(date("d/m/Y H\hi", strtotime($ride['date_start']))) ?></p>
        <?php if (!empty($ride['date_end'])): ?>
          <p class="mb-1"><strong>Arrivée : </strong><?= h(date("d/m/Y H\hi", strtotime($ride['date_end']))) ?></p>
        <?php endif; ?>

        <p class="mb-1"><strong>Prix : </strong><?= $price ?> crédits</p>
        <p class="mb-3">
          <span class="badge <?= ($seats > 0 ? 'text-bg-success' : 'text-bg-danger') ?>">
            <?= $seats ?> place(s) dispo
          </span>
        </p>

        <h5>Véhicule</h5>
        <p class="mb-0">
          <?php if (!empty($ride['brand']) || !empty($ride['model'])): ?>
            <?= h(trim(($ride['brand'] ?? '').' '.($ride['model'] ?? ''))) ?>
            <?php if (!empty($ride['color'])): ?> • Couleur : <?= h($ride['color']) ?><?php endif; ?>
            <?php if (!empty($ride['energy'])): ?> • Énergie : <?= h($ride['energy']) ?><?php endif; ?>
          <?php else: ?>
            Non renseigné
          <?php endif; ?>
        </p>

        <?php if (isset($ride['smoker'])): ?>
          <hr>
          <h5 class="mb-2">Préférences du conducteur</h5>
          <ul class="list-unstyled mb-0">
            <li>Fumeur : <strong><?= h($prefLabel('smoker',  $ride['smoker'])) ?></strong></li>
            <li>Animaux : <strong><?= h($prefLabel('animals', $ride['animals'])) ?></strong></li>
            <li>Musique : <strong><?= h($prefLabel('music',   $ride['music'])) ?></strong></li>
            <li>Discussion : <strong><?= h($prefLabel('chatty',  $ride['chatty'])) ?></strong></li>
            <li>Climatisation : <strong><?= h($prefLabel('ac',      $ride['ac'])) ?></strong></li>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <?php
      // Bloc "Derniers avis" compact
      if (!empty($reviewsRecent ?? []) && file_exists($reviewsInclude)) {
          $items = $reviewsRecent;
          echo '<div class="card shadow-sm mt-4"><div class="card-body">';
          echo '<h5 class="card-title mb-3">Derniers avis validés</h5>';
          include $reviewsInclude;
          echo '</div></div>';
      }
    ?>

    <?php if (!empty($reviews)): ?>
      <div class="card shadow-sm mt-4">
        <div class="card-body">
          <h5 class="card-title mb-3">
            Tous les avis du conducteur
            <?php if (!empty($avgNote)): ?>
              <small class="text-muted">(moyenne : <?= h($avgNote) ?>/5)</small>
            <?php endif; ?>
          </h5>
          <ul class="list-unstyled mb-0">
            <?php foreach ($reviews as $rv): ?>
              <li class="mb-2">
                <strong><?= (int)$rv['note'] ?>/5</strong> — <?= h($rv['comment'] ?? '') ?>
                <div class="text-muted small"><?= !empty($rv['created_at']) ? h(date('d/m/Y', strtotime($rv['created_at']))) : '' ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title">Chauffeur</h5>
        <div class="d-flex align-items-center gap-3">
          <a href="<?= h($profileUrl) ?>" title="Voir le profil">
            <img src="<?= h($avatarUrl) ?>" alt="Avatar" width="64" height="64" class="rounded-circle border">
          </a>
          <div class="fw-bold">
            <a class="text-decoration-none text-dark" href="<?= h($profileUrl) ?>" title="Voir le profil">
              <?= h($driverName) ?>
            </a>
            <?php
              if (isset($avgNote) && $avgNote !== null && file_exists($ratingInclude)) {
                  $avg = (float)$avgNote;
                  $count = isset($reviews) ? count($reviews) : null;
                  $small = true;
                  echo '<div class="mt-1"><a href="'.h($ratingsUrl).'" class="text-decoration-none" title="Voir les avis">';
                  include $ratingInclude;
                  echo '</a></div>';
              }
            ?>
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <a class="btn btn-outline-secondary btn-sm flex-fill" href="<?= h($profileUrl) ?>">
            Voir le profil
          </a>
          <a class="btn btn-outline-primary btn-sm flex-fill" href="<?= h($ratingsUrl) ?>">
            Voir les avis
          </a>
        </div>

        <hr>
        <form action="/rides/book" method="post" class="d-grid">
          <input type="hidden" name="ride_id" value="<?= (int)$ride['id'] ?>">
          <button class="btn btn-success w-100" type="submit" <?= $seats <= 0 ? 'disabled' : '' ?>>
            Participer
          </button>
        </form>
        <?php if ($seats <= 0): ?>
          <div class="small text-muted mt-2">Plus de places disponibles.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
