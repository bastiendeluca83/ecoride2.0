<?php
/** @var string $title */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0"><?= e($title ?? 'Espace Administrateur') ?></h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary" href="/employee/dashboard">Espace employé</a>
      <a class="btn btn-outline-secondary" href="/user/dashboard">Espace administrateur</a>
      <a class="btn btn-outline-secondary" href="/logout">Déconnexion</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Covoiturages / jour</h6>
      <div class="border rounded p-4 text-center">[Graphique A]</div>
    </div></div></div>
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Crédits gagnés / jour</h6>
      <div class="border rounded p-4 text-center">[Graphique B]</div>
    </div></div></div>
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Total crédits gagnés plateforme</h6>
      <p class="display-6 mb-0"><strong><!-- somme injectée --></strong></p>
    </div></div></div>
  </div>

  <hr class="my-4">

  <div class="row g-3">
    <!-- Création d'un employé -->
    <div class="col-lg-4">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Créer un compte employé</h5>
        <form method="post" action="/admin/employees/create" class="vstack gap-2">
          <input class="form-control" name="nom" placeholder="Nom" required>
          <input class="form-control" type="email" name="email" placeholder="Email" required>
          <input class="form-control" type="password" name="password" placeholder="Mot de passe sécurisé" required>
          <button class="btn btn-primary">Créer</button>
        </form>
        <small class="text-muted d-block mt-2">Le rôle sera automatiquement “EMPLOYEE”.</small>
      </div></div>
    </div>

    <!-- Gestion comptes -->
    <div class="col-lg-8">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Gérer les comptes</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>#</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Crédits</th><th>Suspension</th><th>Action</th></tr>
            </thead>
            <tbody>
              <tr>
                <td>1</td><td>driver1</td><td>driver1@example.com</td><td>USER</td><td>50</td>
                <td><span class="badge bg-success">Actif</span></td>
                <td>
                  <form method="post" action="/admin/users/suspend" class="d-inline">
                    <input type="hidden" name="id" value="1">
                    <input type="hidden" name="suspend" value="1">
                    <button class="btn btn-sm btn-outline-danger">Suspendre</button>
                  </form>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>
</div>
