<?php
/**
 * Vue : Profil public conducteur
 * Variables fournies par PublicProfileController::show()
 * - array|null $driver
 * - array      $vehicles
 * - array      $prefs
 * - float|null $avg
 * - int        $count
 * - array      $distribution  [5=>n,4=>n,3=>n,2=>n,1=>n]
 * - array      $reviews_recent (derniers avis approuvés)
 */

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
if (!function_exists('age_years')) {
  function age_years(?string $dateNaissance): ?int {
    $d = $dateNaissance ? trim($dateNaissance) : '';
    if ($d === '') return null;
    try {
      $dob = new \DateTime($d);
      $now = new \DateTime('today');
      return $dob->diff($now)->y;
    } catch (\Throwable $e) { return null; }
  }
}

/* Mappings préférences */
if (!function_exists('pref_txt')) {
  function pref_txt(string $k, int $v): string {
    $map = [
      'smoker'  => [0=>'—', 1=>'Non',         2=>'Oui'],
      'animals' => [0=>'—', 1=>'Non',         2=>'Oui'],
      'music'   => [0=>'—', 1=>'Plutôt non',  2=>'Avec plaisir'],
      'chatty'  => [0=>'—', 1=>'Discret',     2=>'Bavard'],
      'ac'      => [0=>'—', 1=>'Oui',         2=>'Peu/éteinte'],
    ];
    return $map[$k][$v] ?? '—';
  }
}
if (!function_exists('pref_badge')) {
  function pref_badge(string $k, int $v): string {
    if ($v === 0) return 'bg-secondary';
    if ($k === 'smoker')  return $v===1 ? 'bg-success' : 'bg-warning';
    if ($k === 'animals') return $v===2 ? 'bg-success' : 'bg-secondary';
    if ($k === 'ac')      return $v===1 ? 'bg-success' : 'bg-secondary';
    if ($k === 'music')   return $v===2 ? 'bg-success' : 'bg-secondary';
    if ($k === 'chatty')  return $v===2 ? 'bg-info'    : 'bg-secondary';
    return 'bg-secondary';
  }
}

/* Récup valeurs sûres */
$driver        = $driver ?? null;
$vehicles      = is_array($vehicles ?? null) ? $vehicles : [];
$prefs         = is_array($prefs ?? null) ? $prefs : [];
$avg           = $avg ?? null;
$count         = (int)($count ?? 0);
$distribution  = $distribution ?? [5=>0,4=>0,3=>0,2=>0,1=>0];
$reviewsRecent = is_array($reviews_recent ?? null) ? $reviews_recent : [];

$ratingInclude = __DIR__ . '/../partials/_rating_badge.php';

/* Identité */
$fullName = trim((string)(($driver['prenom'] ?? '') . ' ' . ($driver['nom'] ?? '')));
$display  = $fullName !== '' ? $fullName : (string)($driver['pseudo'] ?? 'Conducteur');
$avatar   = (string)($driver['avatar_path'] ?? '');
$age      = isset($driver['date_naissance']) ? age_years((string)$driver['date_naissance']) : null;
$email    = (string)($driver['email'] ?? '');
$phone    = (string)($driver['telephone'] ?? $driver['phone'] ?? '');
$bio      = trim((string)($driver['bio'] ?? ''));

/* Lien "voir tous les avis" (page publique par driver_id) */
$driverId   = (int)($driver['id'] ?? 0);
$allRatings = BASE_URL . 'drivers/ratings?id=' . $driverId;
?>

<div class="container my-4">
  <?php if (!$driver): ?>
    <div class="alert alert-warning">Profil introuvable.</div>
    <a href="<?= BASE_URL ?>covoiturage" class="btn btn-outline-secondary">← Retour</a>
    <?php return; ?>
  <?php endif; ?>

  <a href="<?= BASE_URL ?>covoiturage" class="btn btn-outline-secondary btn-sm mb-3">← Retour</a>

  <!-- En-tête profil -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex align-items-center gap-3">
      <?php if ($avatar !== ''): ?>
        <img src="<?= BASE_URL . e($avatar) ?>" alt="Avatar"
             class="rounded-circle border" width="92" height="92" style="object-fit:cover;">
      <?php else: ?>
        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center border"
             style="width:92px;height:92px;font-size:30px;">
          <?= e(initials_from_name($display)) ?>
        </div>
      <?php endif; ?>

      <div class="flex-grow-1">
        <h1 class="h4 mb-1"><?= e($display) ?></h1>
        <div class="text-muted small">
          <?php if ($age !== null): ?>
            <span class="me-3"><i class="fas fa-birthday-cake me-1"></i><?= (int)$age ?> ans</span>
          <?php endif; ?>
          <?php if ($email !== ''): ?>
            <span class="me-3"><i class="fas fa-envelope me-1"></i><?= e($email) ?></span>
          <?php endif; ?>
          <?php if ($phone !== ''): ?>
            <span><i class="fas fa-phone me-1"></i><?= e($phone) ?></span>
          <?php endif; ?>
        </div>

        <!-- Note compacte à côté du nom -->
        <div class="mt-2">
          <?php if ($avg !== null && file_exists($ratingInclude)): ?>
            <?php $small = true; $countTmp = $count; $avgTmp = (float)$avg; ?>
            <a href="#avis" class="text-decoration-none">
              <?php $avg = $avgTmp; $count = $countTmp; include $ratingInclude; ?>
            </a>
          <?php elseif ($avg !== null): ?>
            <a href="#avis" class="badge text-bg-warning text-decoration-none">
              <?= number_format((float)$avg,1,',',' ') ?>/5 (<?= (int)$count ?>)
            </a>
          <?php else: ?>
            <span class="badge text-bg-secondary">Pas encore de note</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($bio !== ''): ?>
      <div class="card-footer bg-white">
        <div><strong>À propos :</strong></div>
        <div><?= nl2br(e($bio)) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="row g-3">
    <!-- Colonne gauche : préférences + véhicules -->
    <div class="col-lg-5">
      <!-- Préférences -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
          <strong><i class="fas fa-sliders-h me-2"></i>Préférences du conducteur</strong>
        </div>
        <div class="card-body">
          <?php
            $smoker  = (int)($prefs['smoker']  ?? 0);
            $animals = (int)($prefs['animals'] ?? 0);
            $music   = (int)($prefs['music']   ?? 0);
            $chatty  = (int)($prefs['chatty']  ?? 0);
            $ac      = (int)($prefs['ac']      ?? 0);
          ?>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge <?= pref_badge('smoker', $smoker) ?>">
              <i class="fa-solid fa-smoking me-1"></i>Fumeur : <?= e(pref_txt('smoker', $smoker)) ?>
            </span>
            <span class="badge <?= pref_badge('animals', $animals) ?>">
              <i class="fa-solid fa-paw me-1"></i>Animaux : <?= e(pref_txt('animals', $animals)) ?>
            </span>
            <span class="badge <?= pref_badge('music', $music) ?>">
              <i class="fa-solid fa-music me-1"></i>Musique : <?= e(pref_txt('music', $music)) ?>
            </span>
            <span class="badge <?= pref_badge('chatty', $chatty) ?>">
              <i class="fa-solid fa-comments me-1"></i>Discussion : <?= e(pref_txt('chatty', $chatty)) ?>
            </span>
            <span class="badge <?= pref_badge('ac', $ac) ?>">
              <i class="fa-solid fa-snowflake me-1"></i>Clim : <?= e(pref_txt('ac', $ac)) ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Véhicules -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
          <strong><i class="fas fa-car-side me-2"></i>Véhicule(s)</strong>
        </div>
        <div class="card-body">
          <?php if (!empty($vehicles)): ?>
            <div class="list-group list-group-flush">
              <?php foreach ($vehicles as $v): ?>
                <div class="list-group-item px-0">
                  <div class="fw-semibold">
                    <?= e(trim(($v['brand'] ?? '').' '.($v['model'] ?? ''))) ?>
                    <?php if (!empty($v['color'])): ?>
                      <span class="text-muted">• <?= e($v['color']) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted">
                    <?php if (!empty($v['energy'])): ?>
                      <i class="fas fa-bolt me-1"></i><?= e($v['energy']) ?>
                    <?php endif; ?>
                    <?php if (!empty($v['plate'])): ?>
                      <span class="ms-2"><i class="fas fa-id-card me-1"></i><?= e($v['plate']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($v['seats'])): ?>
                      <span class="ms-2"><i class="fas fa-chair me-1"></i><?= (int)$v['seats'] ?> places</span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-muted">Aucun véhicule renseigné.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Colonne droite : Note & avis -->
    <div class="col-lg-7" id="avis">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <div class="fw-bold"><i class="fas fa-star me-2 text-warning"></i>Note & avis</div>
          <a href="<?= e($allRatings) ?>" class="btn btn-outline-primary btn-sm">Voir tous les avis</a>
        </div>
        <div class="card-body">
          <!-- Badge de note -->
          <div class="mb-3">
            <?php if ($avg !== null && file_exists($ratingInclude)): ?>
              <?php $small=false; include $ratingInclude; ?>
            <?php elseif ($avg !== null): ?>
              <span class="badge text-bg-warning"><?= number_format((float)$avg,1,',',' ') ?>/5 (<?= (int)$count ?>)</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Pas encore de note</span>
            <?php endif; ?>
          </div>

          <!-- Distribution -->
          <div class="row g-2 mb-3">
            <?php foreach ([5,4,3,2,1] as $n): ?>
              <div class="col-6 col-md-4">
                <div class="border rounded-3 p-2 text-center bg-light">
                  <div class="fw-bold"><?= $n ?> ★</div>
                  <div class="display-6 fw-semibold"><?= (int)($distribution[$n] ?? 0) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Derniers avis -->
          <h6 class="mb-2">Derniers avis validés</h6>
          <?php if (!empty($reviewsRecent)): ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($reviewsRecent as $rv): ?>
                <?php
                  $note = (int)($rv['note'] ?? $rv['rating'] ?? 0);
                  $comm = (string)($rv['comment'] ?? $rv['commentaire'] ?? '');
                  $date = $rv['created_at'] ?? $rv['date'] ?? null;
                  $ts   = $date ? (is_numeric($date) ? (int)$date : strtotime((string)$date)) : null;
                ?>
                <li class="mb-3 border-bottom pb-2">
                  <div class="mb-1">
                    <strong><?= $note ?>/5</strong>
                    <?php if ($ts): ?>
                      <span class="text-muted small ms-2"><?= e(date('d/m/Y', $ts)) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($comm !== ''): ?>
                    <div><?= nl2br(e($comm)) ?></div>
                  <?php else: ?>
                    <div class="text-muted small fst-italic">Aucun commentaire.</div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="text-muted">Aucun avis validé pour le moment.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
