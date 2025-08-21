<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$title   = $title   ?? 'EcoRide – Covoiturage écologique';
$user    = $_SESSION['user'] ?? null;
$role    = $user['role'] ?? null;
$credits = isset($user['credits']) ? (int)$user['credits'] : null;

/* Messages d’erreur (pour réouvrir le modal si besoin) */
$errorMessage = '';
if (isset($_GET['error'])) {
  if    ($_GET['error'] === 'badcreds')   { $errorMessage = "Identifiants invalides. Vérifiez votre email et votre mot de passe."; }
  elseif($_GET['error'] === 'suspended')  { $errorMessage = "Votre compte est suspendu. Contactez l'administrateur."; }
  elseif($_GET['error'] === 'csrf')       { $errorMessage = "Session expirée. Merci de réessayer."; }
}

/* CSRF pour formulaires (signup/login + logout) */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="EcoRide, plateforme de covoiturage écoresponsable.">
  <link rel="icon" type="image/png" href="/assets/img/favicon-emp.png">
  <title><?= htmlspecialchars($title) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    /* s’assure que le menu passe au‑dessus de la navbar */
    .navbar .dropdown-menu { z-index: 2000; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light" style="background-color:#18a558;">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center text-white" href="/">
      <img src="/assets/img/logo-emp-light.png" alt="EcoRide" style="height:36px" class="me-2">
      <span class="fw-semibold d-none d-sm-inline">EcoRide</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item"><a class="nav-link text-white" href="/">Accueil</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/rides">Covoiturages</a></li>

        <?php if (!$user): ?>
          <!-- Bouton qui ouvre le modal -->
          <li class="nav-item ms-lg-2">
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#authModal">Connexion</button>
          </li>

        <?php else: ?>
          <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item"><a class="nav-link text-white" href="/admin">Employé</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/employee">Modération</a></li>
          <?php elseif ($role === 'EMPLOYEE'): ?>
            <li class="nav-item"><a class="nav-link text-white" href="/employee">Employé</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link text-white" href="/dashboard">Mon espace</a></li>

          <!-- DROPDOWN UTILISATEUR -->
          <li class="nav-item dropdown ms-lg-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center text-white"
               href="#" id="userMenu" role="button"
               data-bs-toggle="dropdown" aria-expanded="false">
              <img src="<?= htmlspecialchars($user['avatar_url'] ?? '/assets/img/avatar-placeholder.png') ?>"
                   class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover;" alt="avatar">
              <span class="fw-semibold"><?= htmlspecialchars($user['pseudo'] ?? 'Profil') ?></span>
              <?php if ($credits !== null): ?>
                <span class="badge bg-light text-dark ms-2"><?= $credits ?> cr.</span>
              <?php endif; ?>
            </a>

            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <!-- Mon profil -->
              <li>
                <a class="dropdown-item" href="/dashboard">
                  Mon profil
                </a>
              </li>

              <?php if ($role === 'ADMIN'): ?>
                <li><a class="dropdown-item" href="/admin">Espace admin</a></li>
                <li><a class="dropdown-item" href="/employee">Espace employé</a></li>
                <li><hr class="dropdown-divider"></li>
              <?php elseif ($role === 'EMPLOYEE'): ?>
                <li><a class="dropdown-item" href="/employee">Espace employé</a></li>
                <li><hr class="dropdown-divider"></li>
              <?php else: ?>
                <li><hr class="dropdown-divider"></li>
              <?php endif; ?>

              <!-- Déconnexion via POST (avec CSRF) -->
              <li>
                <form action="/logout" method="post" class="px-3">
                  <input type="hidden" name="csrf"     value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                  <button class="btn btn-link p-0 text-danger">Déconnexion</button>
                </form>
              </li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<?php if (!$user): ?>
<!-- ================= MODAL AUTH ================= -->
<div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Bienvenue sur EcoRide</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <div class="px-3">
        <ul class="nav nav-pills gap-2 mb-3" id="authTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-login" data-bs-toggle="tab"
                    data-bs-target="#pane-login" type="button" role="tab"
                    aria-controls="pane-login" aria-selected="true">
              Connexion
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-signup" data-bs-toggle="tab"
                    data-bs-target="#pane-signup" type="button" role="tab"
                    aria-controls="pane-signup" aria-selected="false">
              Inscription
            </button>
          </li>
        </ul>
      </div>

      <div class="tab-content px-3 pb-3">
        <?php if (!empty($errorMessage)): ?>
          <div class="alert alert-danger mb-3">
            <?= htmlspecialchars($errorMessage) ?>
          </div>
        <?php endif; ?>

        <!-- Connexion -->
        <div class="tab-pane fade show active" id="pane-login" role="tabpanel" aria-labelledby="tab-login">
          <form method="post" action="/login" class="needs-validation" novalidate>
            <input type="hidden" name="csrf"     value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
            <div class="mb-3">
              <label for="loginEmail" class="form-label">Adresse email</label>
              <input type="email" class="form-control" id="loginEmail" name="email" required>
              <div class="invalid-feedback">Merci de saisir un email valide.</div>
            </div>
            <div class="mb-3">
              <label for="loginPass" class="form-label">Mot de passe</label>
              <input type="password" class="form-control" id="loginPass" name="password" required>
              <div class="invalid-feedback">Mot de passe requis.</div>
            </div>
            <button type="submit" class="btn btn-success w-100">Se connecter</button>
          </form>
        </div>

        <!-- Inscription -->
        <div class="tab-pane fade" id="pane-signup" role="tabpanel" aria-labelledby="tab-signup">
          <form method="post" action="/signup" class="needs-validation" novalidate>
            <input type="hidden" name="csrf"     value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
            <div class="mb-3">
              <label for="suPseudo" class="form-label">Pseudo</label>
              <input type="text" class="form-control" id="suPseudo" name="pseudo" required>
              <div class="invalid-feedback">Le pseudo est requis.</div>
            </div>
            <div class="mb-3">
              <label for="suEmail" class="form-label">Email</label>
              <input type="email" class="form-control" id="suEmail" name="email" required>
              <div class="invalid-feedback">Merci de saisir un email valide.</div>
            </div>
            <div class="mb-3">
              <label for="suPass" class="form-label">Mot de passe</label>
              <input type="password" class="form-control" id="suPass" name="password" minlength="8" required>
              <div class="form-text">Au moins 8 caractères.</div>
              <div class="invalid-feedback">Mot de passe trop court.</div>
            </div>
            <button type="submit" class="btn btn-success w-100">Créer mon compte</button>
          </form>
        </div>
      </div>

      <div class="modal-footer border-0">
        <small class="text-muted">Besoin d’aide ? <a href="/contact" class="text-decoration-underline">Contact</a></small>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Bootstrap JS (bundle = Bootstrap + Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validation front Bootstrap
(() => {
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(f => {
    f.addEventListener('submit', e => {
      if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      f.classList.add('was-validated');
    }, false);
  });
})();

// Réouvrir le modal si ?error=...
<?php if (!empty($errorMessage) && !$user): ?>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('authModal');
  if (modalEl && window.bootstrap) new bootstrap.Modal(modalEl).show();
});
<?php endif; ?>
</script>

<main class="container my-4">
