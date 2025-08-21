<?php
// /php/Views/dashboard/user.php
$root = dirname(__DIR__, 2);
include_once $root . '/includes/header.php';

$user = $_SESSION['user'] ?? ['nom' => 'Utilisateur', 'credits' => 0];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<div class="container my-4">

  <!-- Topbar -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h4 mb-0">Bienvenue, <?= e($user['nom']) ?></h1>
    <a class="btn btn-outline-secondary" href="/logout">Déconnexion</a>
  </div>

  <!-- Ligne 1 : Crédits + Profil + Historique -->
  <div class="row g-4 mb-4">

    <!-- Mes crédits -->
    <div class="col-12 col-md-4 col-lg-3">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-white text-center">
          <h5 class="mb-0">Mes crédits</h5>
          <small class="text-muted">Solde disponible</small>
        </div>
        <div class="card-body d-flex justify-content-center align-items-center">
          <h2 class="display-5 mb-0"><?= (int)($user['credits'] ?? 0) ?></h2>
        </div>
      </div>
    </div>

    <!-- Mon Profil -->
    <div class="col-12 col-md-4 col-lg-4">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-white text-center">
          <h5 class="mb-0">Mon Profil</h5>
          <small class="text-muted">Bienvenue sur EcoRide</small>
        </div>
        <div class="card-body">

          <!-- Nom + Prénom -->
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="text-muted small">Nom</div>
              <div class="fw-semibold"><?= e($user['nom'] ?? '—') ?></div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Prénom</div>
              <div class="fw-semibold"><?= e($user['prenom'] ?? '—') ?></div>
            </div>
          </div>

          <!-- Adresse + Téléphone -->
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="text-muted small">Adresse</div>
              <div class="fw-semibold"><?= e($user['adresse'] ?? '—') ?></div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Téléphone</div>
              <div class="fw-semibold"><?= e($user['telephone'] ?? '—') ?></div>
            </div>
          </div>

          <!-- Email -->
          <div class="mb-3">
            <div class="text-muted small">Email</div>
            <div class="fw-semibold"><?= e($user['email'] ?? '—') ?></div>
          </div>

          <!-- Bouton -->
          <a href="/profil/edit" class="btn btn-success w-100">Modifier mes informations</a>
        </div>
      </div>
    </div>

    <!-- Historique des mouvements -->
    <div class="col-12 col-md-4 col-lg-5">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-white text-center">
          <h5 class="mb-0">Historique des mouvements</h5>
          <small class="text-muted">Crédits dépensés et gagnés</small>
        </div>
        <div class="card-body">
          <?php if (!empty($transactions)): ?>
            <div class="row g-3">
              <?php foreach ($transactions as $t): ?>
                <div class="col-12">
                  <div class="card shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="mb-1"><?= e($t['description']) ?></h6>
                        <small class="text-muted"><?= e($t['date']) ?></small>
                      </div>
                      <?php if ($t['type'] === 'gain'): ?>
                        <span class="badge bg-success">+<?= (int)$t['montant'] ?> cr.</span>
                      <?php else: ?>
                        <span class="badge bg-danger">-<?= (int)$t['montant'] ?> cr.</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted mb-0 text-center">Aucun mouvement pour le moment.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- Réservations à venir -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white text-center">
      <h5 class="mb-0">Mes réservations à venir</h5>
      <small class="text-muted">Vos prochains trajets en tant que passager</small>
    </div>
    <div class="card-body">
      <?php if (!empty($reservations)): ?>
        <div class="row g-3">
          <?php foreach ($reservations as $res): ?>
            <div class="col-12">
              <div class="card shadow-sm">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h6 class="mb-1"><?= e($res['from_city']) ?> &rarr; <?= e($res['to_city']) ?></h6>
                      <div class="text-muted small">Date : <?= e($res['date_start']) ?></div>
                    </div>
                    <span class="badge bg-success align-self-center">
                      <?= (int)$res['credits_spent'] ?> cr.
                    </span>
                  </div>
                  <div class="mt-3 text-end">
                    <a class="btn btn-outline-danger btn-sm"
                       href="/booking/cancel?id=<?= (int)$res['id'] ?>">
                      Annuler
                    </a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-muted mb-0 text-center">Aucune réservation à venir.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Trajets publiés -->
  <div class="card shadow-sm">
    <div class="card-header bg-white text-center">
      <h5 class="mb-0">Mes trajets en tant que conducteur</h5>
      <small class="text-muted">Publiez, démarrez, terminez ou annulez vos trajets</small>
    </div>
    <div class="card-body">
      <?php if (!empty($rides)): ?>
        <div class="row g-3">
          <?php foreach ($rides as $ride): ?>
            <div class="col-12">
              <div class="card shadow-sm">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h6 class="mb-1"><?= e($ride['from_city']) ?> &rarr; <?= e($ride['to_city']) ?></h6>
                      <div class="text-muted small">Départ prévu : <?= e($ride['date_start']) ?></div>
                    </div>
                    <span class="badge bg-success align-self-center">
                      <?= (int)$ride['seats_left'] ?> place(s)
                    </span>
                  </div>
                  <div class="mt-3 text-end">
                    <a class="btn btn-outline-secondary btn-sm me-1"
                       href="/driver/ride/start?id=<?= (int)$ride['id'] ?>">Démarrer</a>
                    <a class="btn btn-outline-secondary btn-sm me-1"
                       href="/driver/ride/stop?id=<?= (int)$ride['id'] ?>">Arrivée</a>
                    <a class="btn btn-outline-danger btn-sm"
                       href="/driver/ride/cancel?id=<?= (int)$ride['id'] ?>">Annuler</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-muted mb-3 text-center">Aucun trajet publié.</p>
        <div class="text-center">
          <a class="btn btn-outline-success" href="/driver/ride/new">Publier un trajet</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php include_once $root . '/includes/footer.php'; ?>
