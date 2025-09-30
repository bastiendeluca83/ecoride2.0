<?php 
/** @var array $user */
/** @var array $reservations */
/** @var array $rides */
/** @var array $vehicles */
/** @var array $stats */
/** @var float|null $driver_rating_avg */
/** @var int $driver_rating_count */

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* Calcule l'âge (années pleines) */
if (!function_exists('age_years')) {
  function age_years(?string $dateNaissance): ?int {
    $d = $dateNaissance ? trim($dateNaissance) : '';
    if ($d === '') return null;
    try {
      $dob = new \DateTime($d);
      $now = new \DateTime('today');
      return $dob->diff($now)->y;
    } catch (\Throwable $e) {
      return null;
    }
  }
}

$dobRaw = $user['date_naissance'] ?? null;
$dobTxt = $dobRaw ? date('d/m/Y', strtotime($dobRaw)) : null;
$age = age_years($dobRaw);

/* helper initials si pas d'avatar */
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

$stats = $stats ?? ['completed_total'=>0,'co2_total'=>0,'co2_per_trip'=>2.5];

/* helper badge statut trajet conducteur */
if (!function_exists('ride_status_badge')) {
  function ride_status_badge(?string $status): string {
    $s = strtoupper(trim((string)$status));
    switch ($s) {
      case 'STARTED':   return '<span class="badge bg-info text-white">En cours</span>';
      case 'FINISHED':  return '<span class="badge bg-success text-white">Terminé</span>';
      case 'CANCELLED': return '<span class="badge bg-secondary text-white">Annulé</span>';
      case 'PREVU':
      default:          return '<span class="badge bg-warning text-dark">Prévu</span>';
    }
  }
}

/* include du badge de note réutilisable */
$ratingInclude = __DIR__ . '/../partials/_rating_badge.php';

/* lien sécurisé vers la page Mes avis (token CSRF en query) */
$csrf = \App\Security\Security::csrfToken();
$ratingsUrl = BASE_URL . 'user/ratings?t=' . urlencode($csrf);
?>

<div class="container-fluid px-4 py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); min-height: 100vh;">
  <div class="container">

    <div class="d-flex align-items-center justify-content-between mb-5">
      <div>
        <h1 class="h2 mb-1 fw-bold text-dark">Bienvenue, <?= e($user['prenom'] ?? '') ?> <?= e($user['nom'] ?? 'Utilisateur') ?></h1>
        <p class="text-muted mb-0">Gérez vos trajets et votre profil EcoRide</p>

        <!-- Petit rappel compact (optionnel) -->
        <?php if (isset($driver_rating_avg) && $driver_rating_avg !== null): ?>
          <div class="mt-2">
            <span class="me-2 text-muted small">Ma note conducteur :</span>
            <?php if (file_exists($ratingInclude)): ?>
              <?php
                $avg = (float)$driver_rating_avg;
                $count = (int)($driver_rating_count ?? 0);
                $small = true;
                include $ratingInclude;
              ?>
            <?php else: ?>
              <span class="badge text-bg-primary"><?= number_format((float)$driver_rating_avg, 1, ',', ' ') ?>/5 (<?= (int)($driver_rating_count ?? 0) ?>)</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <a class="btn btn-outline-danger px-4 py-2 rounded-pill" href="<?= BASE_URL ?>logout">
        <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
      </a>
    </div>

    <!-- Statistiques (4 encarts) -->
    <div class="row justify-content-center g-3 mb-5">
      <div class="col-md-3">
        <div class="card border-0 shadow-lg text-white h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
          <div class="card-body text-center p-3">
            <div class="mb-2"><i class="fas fa-coins fa-2x text-white"></i></div>
            <h6 class="card-title mb-2 fw-bold text-white">Mes crédits</h6>
            <div class="display-5 fw-bold mb-1 text-white"><?= (int)($user['credits'] ?? 0) ?></div>
            <small class="text-white-50 fw-medium">disponibles</small>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border-0 shadow-lg bg-white h-100">
          <div class="card-body text-center p-3">
            <div class="mb-2"><i class="fas fa-route fa-2x text-success"></i></div>
            <h6 class="card-title mb-2 fw-bold text-dark">Trajets effectués</h6>
            <div class="display-5 fw-bold mb-1 text-success"><?= (int)($stats['completed_total'] ?? 0) ?></div>
            <small class="text-muted fw-medium">voyages</small>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border-0 shadow-lg bg-white h-100">
          <div class="card-body text-center p-3">
            <div class="mb-2"><i class="fas fa-leaf fa-2x text-warning"></i></div>
            <h6 class="card-title mb-2 fw-bold text-dark">Impact CO₂</h6>
            <div class="display-6 fw-bold mb-1 text-warning"><?= number_format((float)($stats['co2_total'] ?? 0), 1) ?> kg</div>
            <small class="text-muted fw-medium">économisés</small>
          </div>
        </div>
      </div>

      <!-- Encarts : MA NOTE -->
      <div class="col-md-3">
        <a href="<?= e($ratingsUrl) ?>" class="text-decoration-none">
          <div class="card border-0 shadow-lg bg-white h-100">
            <div class="card-body text-center p-3">
              <div class="mb-2"><i class="fas fa-star fa-2x text-warning"></i></div>
              <h6 class="card-title mb-2 fw-bold text-dark">Ma note</h6>
              <div class="mb-1">
                <?php if (isset($driver_rating_avg) && $driver_rating_avg !== null): ?>
                  <?php if (file_exists($ratingInclude)): ?>
                    <?php
                      $avg = (float)$driver_rating_avg;
                      $count = (int)($driver_rating_count ?? 0);
                      $small = false;
                      include $ratingInclude;
                    ?>
                  <?php else: ?>
                    <span class="badge text-bg-primary"><?= number_format((float)$driver_rating_avg, 1, ',', ' ') ?>/5 (<?= (int)($driver_rating_count ?? 0) ?>)</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge text-bg-secondary">—</span>
                <?php endif; ?>
              </div>
              <small class="text-muted fw-medium d-block">Cliquez pour voir mes avis</small>
            </div>
          </div>
        </a>
      </div>
      <!-- /encart Ma note -->
    </div>

    <!-- Profil + Véhicule -->
    <div class="row justify-content-center mb-4">
      <div class="col-lg-6">
        <div class="card border-0 shadow rounded-3 overflow-hidden">
          <div class="card-header text-white position-relative" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1.5rem;">
            <div class="position-absolute top-0 end-0 p-3"><i class="fas fa-user-circle fa-2x opacity-30"></i></div>
            <h5 class="fw-bold mb-1">Mon Profil</h5>
            <small class="opacity-85">Informations personnelles</small>
          </div>

          <div class="card-body p-3">
            <div class="row g-2">
              <div class="col-6">
                <div class="p-2 rounded-3 border bg-white">
                  <div class="text-primary small mb-1 fw-semibold"><i class="fas fa-user me-1"></i>Nom</div>
                  <div class="fw-bold text-dark"><?= e($user['nom'] ?? '—') ?></div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 rounded-3 border bg-white">
                  <div class="text-info small mb-1 fw-semibold"><i class="fas fa-id-badge me-1"></i>Prénom</div>
                  <div class="fw-bold text-dark"><?= e($user['prenom'] ?? '—') ?></div>
                </div>
              </div>
              <div class="col-12">
                <div class="p-2 rounded-3 border bg-white">
                  <div class="text-warning small mb-1 fw-semibold"><i class="fas fa-envelope me-1"></i>Email</div>
                  <div class="fw-bold text-dark"><?= e($user['email'] ?? '—') ?></div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 rounded-3 border bg-white">
                  <div class="text-success small mb-1 fw-semibold"><i class="fas fa-phone me-1"></i>Téléphone</div>
                  <div class="fw-bold text-dark"><?= e($user['telephone'] ?? '—') ?></div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 rounded-3 border bg-white">
                  <div class="text-danger small mb-1 fw-semibold"><i class="fas fa-home me-1"></i>Adresse</div>
                  <div class="fw-bold text-dark"><?= e($user['adresse'] ?? '—') ?></div>
                </div>
              </div>

              <!-- Date de naissance + Âge -->
              <div class="col-6">
                <div class="p-2 rounded-3 border bg-white">
                  <div class="text-secondary small mb-1 fw-semibold"><i class="fas fa-birthday-cake me-1"></i>Date de naissance</div>
                  <div class="fw-bold text-dark"><?= $dobTxt ? e($dobTxt) : '—' ?></div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 rounded-3 border bg-white">
                  <div class="text-secondary small mb-1 fw-semibold"><i class="fas fa-hourglass-half me-1"></i>Âge</div>
                  <div class="fw-bold text-dark"><?= $age !== null ? e((string)$age).' ans' : '—' ?></div>
                </div>
              </div>
            </div>

            <div class="mt-3">
              <a href="<?= BASE_URL ?>profil/edit" class="btn btn-success w-100 py-2 fw-semibold rounded-3 d-flex align-items-center justify-content-center">
                <i class="fas fa-edit me-2"></i>Modifier
              </a>
            </div>
          </div>
        </div>
      </div>

      <?php if (!empty($vehicles)): ?>
      <div class="col-lg-6">
        <div class="card border-0 shadow rounded-3 overflow-hidden">
          <div class="card-header text-white" style="background: linear-gradient(135deg, #20bf6b 0%, #0fb9b1 100%); padding: 1.5rem;">
            <h5 class="fw-bold mb-1"><i class="fas fa-car me-2"></i>Mon véhicule</h5>
            <small class="opacity-85">Gestion de mes véhicules</small>
          </div>
          <div class="card-body p-3">
            <?php foreach ($vehicles as $v): ?>
              <div class="border rounded-3 p-3 mb-2 bg-white">
                <div>
                  <div class="fw-bold mb-1"><?= e($v['brand'] ?? '') ?> <?= e($v['model'] ?? '') ?> • <?= e($v['color'] ?? '') ?></div>
                  <div class="small text-muted">
                    <i class="fas fa-bolt me-1"></i><?= e($v['energy'] ?? '') ?> ·
                    <i class="fas fa-id-card me-1 ms-2"></i><?= e($v['plate'] ?? '') ?> ·
                    <i class="fas fa-chair me-1 ms-2"></i><?= (int)($v['seats'] ?? 0) ?> place<?= ((int)($v['seats'] ?? 0) > 1 ? 's' : '') ?>
                  </div>
                </div>
                <div class="d-flex justify-content-end gap-2 pt-2 mt-3 border-top">
                  <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>user/vehicle/edit?id=<?= (int)$v['id'] ?>">
                    <i class="fas fa-edit me-1"></i>Modifier
                  </a>
                  <form method="post" action="<?= BASE_URL ?>user/vehicle/delete"
                        onsubmit="return confirm('Supprimer ce véhicule ?');" class="m-0">
                    <?= \App\Security\Security::csrfField(); ?>
                    <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                    <button class="btn btn-outline-danger btn-sm">
                      <i class="fas fa-trash me-1"></i>Supprimer
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
            <a href="<?= BASE_URL ?>user/vehicle" class="btn btn-success w-100 mt-2">
              <i class="fas fa-plus me-2"></i>Ajouter un véhicule
            </a>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Réservations -->
    <div class="card border-0 shadow mb-4 rounded-3 overflow-hidden">
      <div class="card-header text-white" style="background: linear-gradient(135deg, #20bf6b 0%, #0fb9b1 100%); padding: 1rem;">
        <h5 class="fw-bold mb-1"><i class="fas fa-calendar-check me-2"></i>Mes réservations</h5>
        <small class="opacity-85">Voyages programmés</small>
      </div>
      <div class="card-body p-3">
        <?php if (!empty($reservations)): ?>
          <div class="row g-2">
            <?php foreach ($reservations as $res): ?>
              <div class="col-md-6">
                <div class="card h-100 border shadow-sm rounded-3 bg-white">
                  <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <small class="fw-bold text-success mb-0"><i class="fas fa-route me-1"></i>Trajet</small>
                      <div class="d-flex align-items-center gap-1">
                        <span class="badge bg-success text-white px-2 py-1 rounded-pill small">
                          <?= (int)($res['credits_spent'] ?? 0) ?> cr.
                        </span>
                        <span class="badge bg-light text-dark border rounded-pill small">
                          ≈ <?= number_format((float)($stats['co2_per_trip'] ?? 2.5), 1) ?> kg
                        </span>
                      </div>
                    </div>

                    <!-- conducteur -->
                    <?php $d = $res['driver'] ?? null; ?>
                    <?php if ($d): ?>
                      <div class="d-flex align-items-center gap-2 mb-2">
                        <?php if (!empty($d['avatar_path'])): ?>
                          <img src="<?= BASE_URL . e($d['avatar_path']) ?>"
                               alt="Conducteur"
                               class="rounded-circle border"
                               width="28" height="28"
                               style="object-fit:cover;">
                        <?php else: ?>
                          <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center border"
                               style="width:28px;height:28px;font-size:12px;">
                            <?= e(initials_from_name((string)($d['display_name'] ?? 'Conducteur'))) ?>
                          </div>
                        <?php endif; ?>
                        <small class="fw-semibold"><?= e($d['display_name'] ?? 'Conducteur') ?></small>
                        <span class="badge bg-light text-dark border">Conducteur</span>
                      </div>
                    <?php endif; ?>
                    <!-- /conducteur -->  

                    <div class="mb-2">
                      <div class="d-flex align-items-center mb-1">
                        <i class="fas fa-map-marker-alt text-primary me-1"></i>
                        <small class="fw-semibold"><?= e($res['from_city'] ?? '') ?></small>
                      </div>
                      <div class="text-center"><i class="fas fa-arrow-down text-muted small"></i></div>
                      <div class="d-flex align-items-center">
                        <i class="fas fa-flag-checkered text-danger me-1"></i>
                        <small class="fw-semibold"><?= e($res['to_city'] ?? '') ?></small>
                      </div>
                    </div>

                    <div class="mb-2">
                      <small class="text-muted">DÉPART</small>
                      <div class="small fw-bold text-dark">
                        <i class="fas fa-clock me-1"></i>
                        <?= e(isset($res['date_start']) ? date('d/m à H:i', strtotime($res['date_start'])) : '') ?>
                      </div>
                    </div>

                    <?php if (!empty($res['date_end'])): ?>
                    <div class="mb-2">
                      <small class="text-muted">ARRIVÉE</small>
                      <div class="small fw-bold text-dark">
                        <i class="fas fa-flag-checkered me-1"></i>
                        <?= e(date('d/m à H:i', strtotime($res['date_end']))) ?>
                      </div>
                    </div>
                    <?php endif; ?>

                    <a class="btn btn-outline-danger btn-sm w-100 rounded-3 fw-semibold"
                       href="<?= BASE_URL ?>user/ride/cancel?id=<?= (int)($res['id'] ?? 0) ?>">
                      <i class="fas fa-times me-1"></i>Annuler
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="fas fa-calendar-times fa-3x text-muted mb-2"></i>
            <h6 class="text-muted mb-2">Aucune réservation à venir</h6>
            <small class="text-muted mb-3 d-block">Explorez nos trajets disponibles</small>
            <a href="<?= BASE_URL ?>rides" class="btn btn-success px-3 py-2 rounded-pill btn-sm">
              <i class="fas fa-search me-1"></i>Rechercher
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Trajets conducteur -->
    <div class="card border-0 shadow rounded-3 overflow-hidden">
      <div class="card-header text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 1rem;">
        <h5 class="fw-bold mb-1"><i class="fas fa-car me-2"></i>Mes trajets conducteur</h5>
        <small class="opacity-85">Offres de covoiturage</small>
      </div>
      <div class="card-body p-3">
        <?php if (!empty($rides)): ?>
          <div class="row g-2">
            <?php foreach ($rides as $ride): ?>
              <?php
                $participants = $ride['participants'] ?? [];
                $maxShown = 4;
                $shown = 0;
                $more = max(0, count($participants) - $maxShown);

                /* Statut et badges */
                $status = strtoupper((string)($ride['status'] ?? 'PREVU'));
              ?>
              <div class="col-lg-6">
                <div class="card h-100 border shadow-sm rounded-3 bg-white">
                  <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div class="d-flex align-items-center gap-2">
                        <small class="fw-bold text-danger mb-0">
                          <i class="fas fa-steering-wheel me-1"></i>Mon trajet
                        </small>
                        <!-- Badge de statut -->
                        <?= ride_status_badge($status); ?>
                      </div>
                      <div class="d-flex align-items-center gap-1">
                        <span class="badge bg-primary text-white px-2 py-1 rounded-pill small">
                          <?= (int)($ride['seats_left'] ?? 0) ?> place<?= ((int)($ride['seats_left'] ?? 0) > 1 ? 's' : '') ?>
                        </span>
                        <span class="badge bg-light text-dark border rounded-pill small">
                          ≈ <?= number_format((float)($stats['co2_per_trip'] ?? 2.5), 1) ?> kg
                        </span>
                      </div>
                    </div>

                    <div class="mb-2">
                      <div class="d-flex align-items-center mb-1">
                        <i class="fas fa-map-marker-alt text-primary me-1"></i>
                        <small class="fw-semibold"><?= e($ride['from_city'] ?? '') ?></small>
                      </div>
                      <div class="text-center"><i class="fas fa-arrow-right text-muted small"></i></div>
                      <div class="d-flex align-items-center">
                        <i class="fas fa-flag-checkered text-danger me-1"></i>
                        <small class="fw-semibold"><?= e($ride['to_city'] ?? '') ?></small>
                      </div>
                    </div>

                    <div class="mb-2">
                      <small class="text-muted">DÉPART</small>
                      <div class="small fw-bold text-dark">
                        <i class="fas fa-clock me-1"></i>
                        <?= e(isset($ride['date_start']) ? date('d/m à H:i', strtotime($ride['date_start'])) : '') ?>
                      </div>
                    </div>

                    <?php if (!empty($ride['date_end'])): ?>
                    <div class="mb-2">
                      <small class="text-muted">ARRIVÉE</small>
                      <div class="small fw-bold text-dark">
                        <i class="fas fa-flag-checkered me-1"></i>
                        <?= e(date('d/m à H:i', strtotime($ride['date_end']))) ?>
                      </div>
                    </div>
                    <?php endif; ?>

                    <div class="mb-2">
                      <small class="text-muted d-block">Participants</small>
                      <?php if (!empty($participants)): ?>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                          <?php foreach ($participants as $p): ?>
                            <?php if ($shown >= $maxShown) break; $shown++; ?>
                            <div class="d-flex align-items-center me-2">
                              <?php if (!empty($p['avatar_path'])): ?>
                                <img
                                  src="<?= BASE_URL . e($p['avatar_path']) ?>"
                                  alt="avatar"
                                  class="rounded-circle border"
                                  width="28" height="28"
                                  style="object-fit: cover;"
                                />
                              <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center border"
                                     style="width:28px;height:28px;font-size:12px;">
                                  <?= e(initials_from_name((string)($p['display_name'] ?? 'Utilisateur'))) ?>
                                </div>
                              <?php endif; ?>
                              <small class="ms-2 fw-semibold"><?= e($p['display_name'] ?? 'Utilisateur') ?></small>
                            </div>
                          <?php endforeach; ?>
                          <?php if ($more > 0): ?>
                            <span class="badge bg-secondary">+<?= (int)$more ?></span>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <small class="text-muted">Aucun pour le moment</small>
                      <?php endif; ?>
                    </div>

                    <!-- Actions selon statut -->
                    <div class="d-grid gap-1">
                      <?php if ($status === 'PREVU'): ?>
                        <div class="btn-group" role="group">
                          <form method="post" action="<?= BASE_URL ?>user/ride/start" class="m-0 me-1">
                            <?= \App\Security\Security::csrfField(); ?>
                            <input type="hidden" name="id" value="<?= (int)($ride['id'] ?? 0) ?>">
                            <button class="btn btn-outline-success btn-sm">
                              <i class="fas fa-play me-1"></i>Démarrer
                            </button>
                          </form>
                          <form method="post" action="<?= BASE_URL ?>user/ride/cancel" class="m-0">
                            <?= \App\Security\Security::csrfField(); ?>
                            <input type="hidden" name="id" value="<?= (int)($ride['id'] ?? 0) ?>">
                            <button class="btn btn-outline-danger btn-sm">
                              <i class="fas fa-times me-1"></i>Annuler
                            </button>
                          </form>
                        </div>
                      <?php elseif ($status === 'STARTED'): ?>
                        <div class="btn-group" role="group">
                          <form method="post" action="<?= BASE_URL ?>user/ride/end" class="m-0 me-1">
                            <?= \App\Security\Security::csrfField(); ?>
                            <input type="hidden" name="id" value="<?= (int)($ride['id'] ?? 0) ?>">
                            <button class="btn btn-outline-info btn-sm">
                              <i class="fas fa-stop me-1"></i>Terminer
                            </button>
                          </form>
                          <form method="post" action="<?= BASE_URL ?>user/ride/cancel" class="m-0">
                            <?= \App\Security\Security::csrfField(); ?>
                            <input type="hidden" name="id" value="<?= (int)($ride['id'] ?? 0) ?>">
                            <button class="btn btn-outline-danger btn-sm">
                              <i class="fas fa-times me-1"></i>Annuler
                            </button>
                          </form>
                        </div>
                      <?php elseif ($status === 'FINISHED'): ?>
                        <div class="d-grid">
                          <form method="post" action="<?= BASE_URL ?>user/ride/end" class="m-0">
                            <?= \App\Security\Security::csrfField(); ?>
                            <input type="hidden" name="id" value="<?= (int)($ride['id'] ?? 0) ?>">
                            <button class="btn btn-outline-info btn-sm">
                              <i class="fas fa-paper-plane me-1"></i>Terminer (renvoyer invitations)
                            </button>
                          </form>
                        </div>
                      <?php else: ?>
                        <div class="text-muted small">Aucune action disponible.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="fas fa-car fa-3x text-muted mb-2"></i>
            <h6 class="text-muted mb-2">Aucun trajet publié</h6>
            <small class="text-muted mb-3 d-block">Partagez vos trajets et gagnez des crédits</small>
          </div>
        <?php endif; ?>

        <div class="text-center mt-3">
          <a class="btn btn-success px-3 py-2 rounded-pill fw-semibold btn-sm" href="<?= BASE_URL ?>user/ride/create">
            <i class="fas fa-plus me-1"></i>Publier un trajet
          </a>
        </div>
      </div>
    </div>

  </div>
</div>
