<?php
/** @var string $title */
/** @var array $kpis */
/** @var array $ridesPerDay */
/** @var array $creditsPerDay */
/** @var array $users */
/** @var string $csrf */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0"><?= e($title ?? 'Espace Administrateur') ?></h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary" href="/employee/dashboard">Espace employé</a>
      <a class="btn btn-outline-secondary" href="/user/dashboard">Espace utilisateur</a>
      <a class="btn btn-outline-secondary" href="/logout">Déconnexion</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Utilisateurs actifs</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['users_active'] ?? 0) ?></strong></p>
    </div></div></div>
    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Trajets à venir</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['rides_upcoming'] ?? 0) ?></strong></p>
    </div></div></div>
    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Réservations</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['bookings_total'] ?? 0) ?></strong></p>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Places restantes (total)</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['seats_left_sum'] ?? 0) ?></strong></p>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Crédits plateforme (total)</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['platform_total'] ?? 0) ?></strong></p>
    </div></div></div>
  </div>

  <hr class="my-4">

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Covoiturages par jour (14j)</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>Jour</th><th>Nombre</th></tr></thead>
            <tbody>
            <?php foreach (($ridesPerDay ?? []) as $r): ?>
              <tr><td><?= e($r['day']) ?></td><td><?= e($r['rides_count']) ?></td></tr>
            <?php endforeach; if (empty($ridesPerDay)): ?>
              <tr><td colspan="2" class="text-muted">Aucune donnée.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Crédits gagnés / jour (14j)</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>Jour</th><th>Crédits</th></tr></thead>
            <tbody>
            <?php foreach (($creditsPerDay ?? []) as $r): ?>
              <tr><td><?= e($r['day']) ?></td><td><?= e($r['credits']) ?></td></tr>
            <?php endforeach; if (empty($creditsPerDay)): ?>
              <tr><td colspan="2" class="text-muted">Aucune donnée.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>

  <hr class="my-4">

  <div class="row g-3">
    <!-- Création d'un employé -->
    <div class="col-lg-4">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Créer un compte employé</h5>
        <form method="post" action="/admin/employees/create" class="vstack gap-2">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input class="form-control" name="nom" placeholder="Nom (optionnel)">
          <input class="form-control" type="email" name="email" placeholder="Email" required>
          <input class="form-control" type="password" name="password" placeholder="Mot de passe (min 8)" minlength="8" required>
          <button class="btn btn-primary">Créer</button>
        </form>
        <small class="text-muted d-block mt-2">Rôle assigné automatiquement : <code>EMPLOYEE</code>.</small>
      </div></div>
    </div>

    <!-- Gestion comptes -->
    <div class="col-lg-8">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Gérer les comptes</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>#</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Rôle</th><th>Crédits</th><th>Statut</th><th>Action</th></tr>
            </thead>
            <tbody>
              <?php foreach (($users ?? []) as $u): ?>
              <tr>
                <td><?= e($u['id']) ?></td>
                <td><?= e($u['nom'] ?? '') ?></td>
                <td><?= e($u['prenom'] ?? '') ?></td>
                <td><?= e($u['email'] ?? '') ?></td>
                <td><?= e($u['role'] ?? '') ?></td>
                <td><?= e($u['credits'] ?? 0) ?></td>
                <td>
                  <?php if (!empty($u['is_suspended'])): ?>
                    <span class="badge bg-danger">Suspendu</span>
                  <?php else: ?>
                    <span class="badge bg-success">Actif</span>
                  <?php endif; ?>
                </td>
                <td class="d-flex gap-1">
                  <?php if (!empty($u['is_suspended'])): ?>
                    <form method="post" action="/admin/users/unsuspend">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                      <button class="btn btn-sm btn-success">Réactiver</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="/admin/users/suspend">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger">Suspendre</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; if (empty($users)): ?>
                <tr><td colspan="8" class="text-muted">Aucun utilisateur.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>
</div>
