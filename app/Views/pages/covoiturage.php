<?php
/** @var array $rides_upcoming */
/** @var array $rides_past_30d */

/* Helpers généraux  */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('initials_from_name')) {
  function initials_from_name(string $name): string {
    $name = trim($name);
    if ($name === '') return 'U';
    $parts = preg_split('/\s+/', $name);
    $first = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
    $second = mb_strtoupper(mb_substr($parts[1] ?? '', 0, 1));
    return $first . ($second ?: '');
  }
}
if (!function_exists('fmt_date_short')) {
  function fmt_date_short(?string $dt): string {
    if (!$dt) return '';
    $t = strtotime($dt);
    return $t ? date('d/m à H:i', $t) : '';
  }
}
if (!function_exists('fmt_duration')) {
  function fmt_duration(?string $start, ?string $end): string {
    if (!$start || !$end) return '—';
    $s = strtotime($start); $e = strtotime($end);
    if (!$s || !$e || $e <= $s) return '—';
    $m = (int) round(($e - $s) / 60);
    $h = intdiv($m, 60); $mn = $m % 60;
    if ($h > 0 && $mn > 0) return "{$h} h {$mn} min";
    if ($h > 0) return "{$h} h";
    return "{$mn} min";
  }
}
if (!function_exists('ride_driver_name')) {
  function ride_driver_name(array $r): string {
    $candidates = [
      $r['driver_display_name'] ?? null,
      $r['driver_name'] ?? null,
      trim(($r['driver_prenom'] ?? '') . ' ' . ($r['driver_nom'] ?? '')),
      $r['display_name'] ?? null,
    ];
    foreach ($candidates as $n) {
      $n = trim((string)$n);
      if ($n !== '') return $n;
    }
    return 'Conducteur';
  }
}
if (!function_exists('ride_driver_avatar')) {
  function ride_driver_avatar(array $r): ?string {
    foreach (['driver_avatar','driver_avatar_path','avatar_path','user_avatar','photo'] as $k) {
      if (!empty($r[$k])) return (string)$r[$k];
    }
    return null;
  }
}
if (!function_exists('ride_prefs_from_row')) {
  function ride_prefs_from_row(array $r): array {
    if (!empty($r['prefs']) && is_array($r['prefs'])) return $r['prefs'];
    /* champs ramenés par la requête du contrôleur*/
    return [
      'smoker'  => isset($r['smoker'])  ? (int)$r['smoker']  : (isset($r['pref_smoking']) ? (int)$r['pref_smoking'] : 0),
      'animals' => isset($r['animals']) ? (int)$r['animals'] : (isset($r['pref_pets'])    ? (int)$r['pref_pets']    : 0),
      'music'   => isset($r['music'])   ? (int)$r['music']   : (isset($r['pref_music'])   ? (int)$r['pref_music']   : 0),
      'chatty'  => isset($r['chatty'])  ? (int)$r['chatty']  : (isset($r['pref_chat'])    ? (int)$r['pref_chat']    : 0),
      'ac'      => isset($r['ac'])      ? (int)$r['ac']      : (isset($r['pref_ac'])      ? (int)$r['pref_ac']      : 0),
    ];
  }
}

/* MAPPINGS de préférences (0/1/2) */
function pref_txt(string $k, int $v): string {
  $map = [
    'smoker'  => [0=>'N/A', 1=>'Non',         2=>'Oui'],
    'animals' => [0=>'N/A', 1=>'Non',         2=>'Oui'],
    'music'   => [0=>'N/A', 1=>'Plutôt non',  2=>'Avec plaisir'],
    'chatty'  => [0=>'N/A', 1=>'Discret',     2=>'Bavard'],
    'ac'      => [0=>'N/A', 1=>'Oui',         2=>'Peu/éteinte'],
  ];
  return $map[$k][$v] ?? 'N/A';
}
function pref_badge(string $k, int $v): string {
  if ($v === 0) return 'bg-secondary';
  if ($k === 'smoker')  return $v===1 ? 'bg-success' : 'bg-warning';
  if ($k === 'animals') return $v===2 ? 'bg-success' : 'bg-secondary';
  if ($k === 'ac')      return $v===1 ? 'bg-success' : 'bg-secondary';
  if ($k === 'music')   return $v===2 ? 'bg-success' : 'bg-secondary';
  if ($k === 'chatty')  return $v===2 ? 'bg-info'    : 'bg-secondary';
  return 'bg-secondary';
}

/*  Données entrantes possibles */
$upcoming = $rides_upcoming ?? $ridesUpcoming ?? $rides ?? [];
$past30   = $rides_past_30d ?? $ridesPast30 ?? $rides_past ?? [];
$isLogged = !empty($_SESSION['user']['id']);

/* partial note */
$ratingPartial = __DIR__ . '/../partials/_rating_badge.php';
?>

<div class="container-fluid px-4 py-5" style="background:linear-gradient(135deg,#f8f9fa 0%,#eef1f4 100%);min-height:100vh;">
  <div class="container">

    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h1 class="h2 fw-bold mb-1 text-dark">Covoiturage</h1>
        <p class="text-muted mb-0">Réservez un trajet ou consultez l’historique récent</p>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>rides">
        <i class="fas fa-search me-1"></i> Rechercher
      </a>
    </div>

    <!-- À VENIR — SCROLLER HORIZONTAL -->
    <div class="card border-0 shadow rounded-3 overflow-hidden mb-4">
      <div class="card-header text-white" style="background:linear-gradient(135deg,#20bf6b 0%,#0fb9b1 100%);padding:1rem;">
        <h5 class="fw-bold mb-1"><i class="fas fa-calendar-plus me-2"></i>Covoiturages à venir</h5>
        <small class="opacity-85">Trajets ouverts à la participation</small>
      </div>
      <div class="card-body p-3">
        <?php if (!empty($upcoming)): ?>
          <!-- rangée non-wrap qui scroll -->
          <div class="row flex-nowrap overflow-auto g-3 pb-2" role="region" aria-label="Covoiturages à venir">
            <?php foreach ($upcoming as $ride): ?>
              <?php
                $driverName   = ride_driver_name($ride);
                $driverAvatar = ride_driver_avatar($ride);
                $brand  = trim((string)(($ride['brand'] ?? '') . ' ' . ($ride['model'] ?? '')));
                $energy = $ride['energy'] ?? null;
                $price  = (int)($ride['price'] ?? 0);
                $seats  = (int)($ride['seats_left'] ?? 0);
                $prefs  = ride_prefs_from_row($ride);

                $driverId   = (int)($ride['driver_id'] ?? 0);
                $profileUrl = $driverId > 0 ? (BASE_URL . 'users/profile?id=' . $driverId) : '#';
                $ratingsUrl = BASE_URL . 'drivers/ratings?id=' . $driverId;

                $avgBadge   = isset($ride['rating_avg'])   ? (float)$ride['rating_avg']   : null;
                $countBadge = isset($ride['rating_count']) ? (int)$ride['rating_count']   : 0;
              ?>
              <!-- largeurs responsives pour des cartes “larges” -->
              <div class="col-10 col-sm-8 col-md-6 col-lg-5 col-xl-4">
                <div class="card h-100 border rounded-3 shadow-sm bg-white">
                  <!-- En-tête conducteur -->
                  <div class="p-3 border-bottom d-flex align-items-center">
                    <?php if ($driverAvatar): ?>
                      <a href="<?= e($profileUrl) ?>" class="me-3" title="Voir le profil">
                        <img src="<?= BASE_URL . e($driverAvatar) ?>" alt="Conducteur"
                             class="rounded-circle border" width="72" height="72" style="object-fit:cover;">
                      </a>
                    <?php else: ?>
                      <a href="<?= e($profileUrl) ?>" class="me-3 text-decoration-none" title="Voir le profil">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center border"
                             style="width:72px;height:72px;font-size:24px;">
                          <?= e(initials_from_name($driverName)) ?>
                        </div>
                      </a>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                      <div class="fw-bold text-dark d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($driverId > 0): ?>
                          <a href="<?= e($profileUrl) ?>" class="text-decoration-none text-dark" title="Voir le profil">
                            <span><?= e($driverName) ?></span>
                          </a>
                        <?php else: ?>
                          <span><?= e($driverName) ?></span>
                        <?php endif; ?>
                        <!-- ⭐ Note cliquable -->
                        <span>
                          <?php if ($avgBadge !== null && file_exists($ratingPartial)): ?>
                            <a href="<?= e($ratingsUrl) ?>" class="text-decoration-none" title="Voir les avis">
                              <?php $avg = $avgBadge; $count = $countBadge; $small = true; include $ratingPartial; ?>
                            </a>
                          <?php elseif ($avgBadge !== null): ?>
                            <a href="<?= e($ratingsUrl) ?>" class="text-decoration-none" title="Voir les avis">
                              <span class="badge text-bg-warning"><?= number_format($avgBadge,1,',',' ') ?>/5 (<?= (int)$countBadge ?>)</span>
                            </a>
                          <?php else: ?>
                            <span class="badge text-bg-secondary">—</span>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="small text-muted">
                        <?php if ($brand !== ''): ?>
                          <i class="fas fa-car-side me-1"></i><?= e($brand) ?>
                          <?php if ($energy): ?> · <i class="fas fa-bolt ms-1 me-1"></i><?= e($energy) ?><?php endif; ?>
                        <?php elseif ($energy): ?>
                          <i class="fas fa-bolt me-1"></i><?= e($energy) ?>
                        <?php endif; ?>
                      </div>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= $seats ?> place<?= $seats>1?'s':'' ?></span>
                  </div>

                  <!-- Corps trajet -->
                  <div class="p-3">
                    <div class="mb-2">
                      <div class="d-flex align-items-center">
                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                        <span class="fw-semibold"><?= e($ride['from_city'] ?? '') ?></span>
                      </div>
                      <div class="text-center my-1"><i class="fas fa-arrow-right text-muted small"></i></div>
                      <div class="d-flex align-items-center">
                        <i class="fas fa-flag-checkered text-danger me-2"></i>
                        <span class="fw-semibold"><?= e($ride['to_city'] ?? '') ?></span>
                      </div>
                    </div>

                    <div class="row g-2 mb-2">
                      <div class="col-6">
                        <small class="text-muted d-block">Départ</small>
                        <div class="fw-bold">
                          <i class="fas fa-clock me-1"></i><?= e(fmt_date_short($ride['date_start'] ?? null)) ?>
                        </div>
                      </div>
                      <div class="col-6">
                        <small class="text-muted d-block">Durée</small>
                        <div class="fw-bold">
                          <i class="fas fa-hourglass-half me-1"></i><?= e(fmt_duration($ride['date_start'] ?? null, $ride['date_end'] ?? null)) ?>
                        </div>
                      </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="fw-bold">
                        <i class="fas fa-coins text-warning me-1"></i><?= $price ?> cr.
                      </div>
                      <div class="text-muted small">
                        <i class="fas fa-chair me-1"></i><?= $seats ?> dispo
                      </div>
                    </div>

                    <!-- Préférences CONDUCTEUR (connectées BDD) -->
                    <div class="mb-2">
                      <small class="text-muted d-block mb-1">Préférences du conducteur</small>
                      <?php
                        $smoker  = (int)($prefs['smoker']  ?? 0);
                        $animals = (int)($prefs['animals'] ?? 0);
                        $music   = (int)($prefs['music']   ?? 0);
                        $chatty  = (int)($prefs['chatty']  ?? 0);
                        $ac      = (int)($prefs['ac']      ?? 0);
                      ?>
                      <div class="d-flex flex-wrap gap-2">
                        <span class="badge <?= pref_badge('smoker',$smoker) ?>">
                          <i class="fa-solid fa-smoking me-1"></i>Fumeur: <?= e(pref_txt('smoker',$smoker)) ?>
                        </span>
                        <span class="badge <?= pref_badge('animals',$animals) ?>">
                          <i class="fa-solid fa-paw me-1"></i>Animaux: <?= e(pref_txt('animals',$animals)) ?>
                        </span>
                        <span class="badge <?= pref_badge('music',$music) ?>">
                          <i class="fa-solid fa-music me-1"></i>Musique: <?= e(pref_txt('music',$music)) ?>
                        </span>
                        <span class="badge <?= pref_badge('chatty',$chatty) ?>">
                          <i class="fa-solid fa-comments me-1"></i>Discussion: <?= e(pref_txt('chatty',$chatty)) ?>
                        </span>
                        <span class="badge <?= pref_badge('ac',$ac) ?>">
                          <i class="fa-solid fa-snowflake me-1"></i>Clim: <?= e(pref_txt('ac',$ac)) ?>
                        </span>
                      </div>
                    </div>

                    <!-- Participer -->
                    <div class="mt-3">
                      <?php if ($isLogged): ?>
                        <form method="post" action="<?= BASE_URL ?>rides/book" class="d-grid">
                          <?= \App\Security\Security::csrfField(); ?>
                          <input type="hidden" name="ride_id" value="<?= (int)($ride['id'] ?? 0) ?>">
                          <button class="btn btn-success fw-semibold">
                            <i class="fas fa-handshake me-1"></i> Participer
                          </button>
                        </form>
                      <?php else: ?>
                        <a href="<?= BASE_URL ?>login" class="btn btn-outline-secondary w-100">
                          <i class="fas fa-user-lock me-1"></i> Se connecter pour participer
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="fas fa-calendar-times fa-3x text-muted mb-2"></i>
            <h6 class="text-muted mb-2">Aucun covoiturage à venir</h6>
            <small class="text-muted">Revenez plus tard ou créez un trajet.</small>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PASSÉS (30 jours) -->
    <div class="card border-0 shadow rounded-3 overflow-hidden">
      <div class="card-header text-white" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:1rem;">
        <h5 class="fw-bold mb-1"><i class="fas fa-history me-2"></i>Covoiturages passés (30 jours)</h5>
        <small class="opacity-85">Historique récent</small>
      </div>
      <div class="card-body p-3">
        <?php if (!empty($past30)): ?>
          <div class="row g-3">
            <?php foreach ($past30 as $ride): ?>
              <?php
                $driverName   = ride_driver_name($ride);
                $driverAvatar = ride_driver_avatar($ride);

                $driverId   = (int)($ride['driver_id'] ?? 0);
                $profileUrl = $driverId > 0 ? (BASE_URL . 'users/profile?id=' . $driverId) : '#';
                $ratingsUrl = BASE_URL . 'drivers/ratings?id=' . $driverId;

                $avgBadge   = isset($ride['rating_avg'])   ? (float)$ride['rating_avg']   : null;
                $countBadge = isset($ride['rating_count']) ? (int)$ride['rating_count']   : 0;
              ?>
              <div class="col-md-6 col-lg-4">
                <div class="card h-100 border rounded-3 shadow-sm bg-white">
                  <div class="p-3 d-flex align-items-center">
                    <?php if ($driverAvatar): ?>
                      <a href="<?= e($profileUrl) ?>" class="me-3" title="Voir le profil">
                        <img src="<?= BASE_URL . e($driverAvatar) ?>" alt="Conducteur"
                             class="rounded-circle border" width="56" height="56" style="object-fit:cover;">
                      </a>
                    <?php else: ?>
                      <a href="<?= e($profileUrl) ?>" class="me-3 text-decoration-none" title="Voir le profil">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center border"
                             style="width:56px;height:56px;font-size:18px;">
                          <?= e(initials_from_name($driverName)) ?>
                        </div>
                      </a>
                    <?php endif; ?>
                    <div>
                      <div class="fw-bold text-dark d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($driverId > 0): ?>
                          <a href="<?= e($profileUrl) ?>" class="text-decoration-none text-dark" title="Voir le profil">
                            <span><?= e($driverName) ?></span>
                          </a>
                        <?php else: ?>
                          <span><?= e($driverName) ?></span>
                        <?php endif; ?>
                        <!-- ⭐ Note cliquable -->
                        <span>
                          <?php if ($avgBadge !== null && file_exists($ratingPartial)): ?>
                            <a href="<?= e($ratingsUrl) ?>" class="text-decoration-none" title="Voir les avis">
                              <?php $avg = $avgBadge; $count = $countBadge; $small = true; include $ratingPartial; ?>
                            </a>
                          <?php elseif ($avgBadge !== null): ?>
                            <a href="<?= e($ratingsUrl) ?>" class="text-decoration-none" title="Voir les avis">
                              <span class="badge text-bg-warning"><?= number_format($avgBadge,1,',',' ') ?>/5 (<?= (int)$countBadge ?>)</span>
                            </a>
                          <?php else: ?>
                            <span class="badge text-bg-secondary">—</span>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="small text-muted">
                        <?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?>
                      </div>
                      <div class="small">
                        <i class="fas fa-clock me-1 text-muted"></i><?= e(fmt_date_short($ride['date_start'] ?? null)) ?>
                        · <i class="fas fa-hourglass-half ms-1 me-1 text-muted"></i><?= e(fmt_duration($ride['date_start'] ?? null, $ride['date_end'] ?? null)) ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="fas fa-inbox fa-3x text-muted mb-2"></i>
            <h6 class="text-muted mb-2">Aucun trajet passé sur les 30 derniers jours</h6>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>
