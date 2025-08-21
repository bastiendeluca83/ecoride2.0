<?php
$root = dirname(__DIR__, 2);
include_once $root . '/includes/header.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* CSRF (au cas où) */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];
$currentUrl = $_SERVER['REQUEST_URI'] ?? '/employee';
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Espace Employé</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="/dashboard">Espace utilisateur</a>

      <!-- Déconnexion en POST, pas en GET -->
      <form method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="redirect" value="<?= e($currentUrl) ?>">
        <button class="btn btn-outline-secondary">Déconnexion</button>
      </form>
    </div>
  </div>

  <?php if (!empty($mongoError)): ?>
    <div class="alert alert-warning">
      ⚠️ <?= e($mongoError) ?> — La liste des avis restera vide tant que Mongo n’est pas configuré.
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <!-- Avis à valider -->
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Avis en attente de validation</h5>

          <?php if (!empty($reviews)): ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Chauffeur</th>
                    <th>Note</th>
                    <th>Commentaire</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $rev): ?>
                  <tr>
                    <td><code><?= e($rev['_id']) ?></code></td>
                    <td>
                      <?= e($rev['driver']) ?><br>
                      <small class="text-muted"><?= e($rev['driverMail']) ?></small>
                    </td>
                    <td><?= (int)$rev['note'] ?></td>
                    <td><?= e($rev['comment']) ?></td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <!-- Approve -->
                        <form method="post" action="/employee/reviews">
                          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                          <input type="hidden" name="id" value="<?= e($rev['_id']) ?>">
                          <input type="hidden" name="action" value="approve">
                          <button class="btn btn-sm btn-success">Valider</button>
                        </form>
                        <!-- Reject -->
                        <form method="post" action="/employee/reviews">
                          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                          <input type="hidden" name="id" value="<?= e($rev['_id']) ?>">
                          <input type="hidden" name="action" value="reject">
                          <button class="btn btn-sm btn-outline-danger">Refuser</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted mb-0">Aucun avis en attente.</p>
          <?php endif; ?>

          <small class="text-muted d-block mt-2">
            Les actions ci‑dessus envoient un POST vers <code>/employee/reviews</code> (CSRF inclus).
          </small>
        </div>
      </div>
    </div>

    <!-- Incidents (placeholder) -->
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Trajets “mal passés”</h5>
          <?php if (!empty($incidents)): ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>#Trajet</th>
                    <th>Chauffeur</th>
                    <th>Passager</th>
                    <th>Résumé</th>
                    <th>Suivi</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($incidents as $inc): ?>
                  <tr>
                    <td><?= (int)$inc['ride_id'] ?></td>
                    <td><?= e($inc['driver_nom']) ?> (<?= e($inc['driver_email']) ?>)</td>
                    <td><?= e($inc['passenger_nom']) ?> (<?= e($inc['passenger_email']) ?>)</td>
                    <td><?= e($inc['summary']) ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="/employee/incidents/<?= (int)$inc['ride_id'] ?>">Voir</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted mb-0">Aucun incident enregistré.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_once $root . '/includes/footer.php'; ?>
