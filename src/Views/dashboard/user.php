<?php
// /php/Views/dashboard/user.php
$root = dirname(__DIR__, 2);
include_once $root . '/includes/header.php';

$user = $_SESSION['user'] ?? ['nom' => 'Utilisateur', 'credits' => 0];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Bienvenue, <?= e($user['nom']) ?></h1>
    <a class="btn btn-outline-secondary" href="/logout">Déconnexion</a>
  </div>

  <!-- Crédits -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h5 class="card-title mb-2">Mes crédits</h5>
          <p class="display-6 mb-0"><?= (int)($user['credits'] ?? 0) ?></p>
        </div>
      </div>
    </div>
  </div>
  <!-- Profil -->
  <div class="container my-4 d-flex justify-content-center">
  <div class="card border-0 shadow rounded-4" style="max-width: 540px; width: 100%;">
    <div class="card-header bg-white border-0 pt-4 pb-0">
      <h5 class="fw-bold mb-1">Mon Profil</h5>
      <p class="text-muted mb-0">Bienvenue sur EcoRide</p>
    </div>

    <div class="card-body px-4 pb-4">
      <!-- Lignes d'infos, design épuré comme le modal -->
      <div class="py-3 border-bottom">
        <div class="text-muted small mb-1">Nom</div>
        <div class="fs-5 fw-semibold">
          <!-- PHP dynamique : -->
          <?= htmlspecialchars($user['nom'] ?? '—') ?>
        </div>
      </div>

      <div class="py-3 border-bottom">
        <div class="text-muted small mb-1">Prénom</div>
        <div class="fs-5 fw-semibold">
          <?= e($user['prenom'] ?? '—') ?>
        </div>
      </div>

      <div class="py-3 border-bottom">
        <div class="text-muted small mb-1">Adresse</div>
        <div class="fs-5 fw-semibold">
          <?= htmlspecialchars($user['adresse'] ?? '—') ?>
        </div>
      </div>

      <div class="py-3 border-bottom">
        <div class="text-muted small mb-1">Téléphone</div>
        <div class="fs-5 fw-semibold">
          <?= htmlspecialchars($user['telephone'] ?? '—') ?>
        </div>
      </div>

      <div class="py-3">
        <div class="text-muted small mb-1">Email</div>
        <div class="fs-5 fw-semibold">
          <?= htmlspecialchars($user['email'] ?? '—') ?>
        </div>
      </div>

      <!-- Bouton style "Se connecter" (vert) pour rappeler l'image 1, optionnel -->
      <div class="mt-3">
        <a href="/profil/edit" class="btn btn-success w-100 py-2 fw-semibold rounded-3">
          Modifier mes informations
        </a>
      </div>
    </div>
  </div>
</div>
    
      

  <!-- Réservations à venir -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h5 class="card-title">Mes réservations à venir</h5>
      <?php if (!empty($reservations)): ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>Départ</th><th>Arrivée</th><th>Date</th><th>Crédits</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($reservations as $res): ?>
                <tr>
                  <td><?= e($res['from_city']) ?></td>
                  <td><?= e($res['to_city']) ?></td>
                  <td><?= e($res['date_start']) ?></td>
                  <td><?= (int)$res['credits_spent'] ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-danger"
                       href="/booking/cancel?id=<?= (int)$res['id'] ?>">
                      Annuler
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted">Aucune réservation à venir.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Trajets publiés -->
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title">Mes trajets en tant que conducteur</h5>
      <?php if (!empty($rides)): ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>Départ</th><th>Arrivée</th><th>Départ prévu</th><th>Places restantes</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($rides as $ride): ?>
                <tr>
                  <td><?= e($ride['from_city']) ?></td>
                  <td><?= e($ride['to_city']) ?></td>
                  <td><?= e($ride['date_start']) ?></td>
                  <td><?= (int)$ride['seats_left'] ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="/driver/ride/start?id=<?= (int)$ride['id'] ?>">Démarrer</a>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="/driver/ride/stop?id=<?= (int)$ride['id'] ?>">Arrivée</a>
                    <a class="btn btn-sm btn-outline-danger"
                       href="/driver/ride/cancel?id=<?= (int)$ride['id'] ?>">Annuler</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted">Aucun trajet publié.</p>
      <?php endif; ?>
      <a class="btn btn-outline-success mt-3" href="/driver/ride/new">Publier un trajet</a>
    </div>
  </div>
</div>

<?php include_once $root . '/includes/footer.php'; ?>
