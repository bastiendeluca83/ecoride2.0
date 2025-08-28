<?php
/**
 * Layout global EcoRide (MVC)
 * Emplacement : app/Views/layouts/base.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$title       = $title       ?? 'EcoRide – Covoiturage écologique';
$meta        = $meta        ?? [];
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';
$bodyClass   = $bodyClass   ?? '';

$user    = $_SESSION['user'] ?? null;
$role    = $user['role'] ?? null;
$credits = isset($user['credits']) ? (int)$user['credits'] : null;

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$currentUrl = $_SERVER['REQUEST_URI'] ?? '/';

/* Avatar */
$avatarPath = $user['avatar_path'] ?? '';
if ($avatarPath && $avatarPath[0] !== '/') {
    $avatarPath = '/'.$avatarPath;
}
$avatarUrl = $avatarPath ?: ("https://api.dicebear.com/9.x/initials/svg?seed=" . urlencode($user['nom'] ?? 'guest'));

/* Modal auth error */
$errorMessage = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'badcreds': $errorMessage = "Identifiants invalides. Vérifiez votre email et votre mot de passe."; break;
        case 'suspended': $errorMessage = "Votre compte est suspendu. Contactez l'administrateur."; break;
        case 'csrf': $errorMessage = "Session expirée. Merci de réessayer."; break;
    }
}

/* Flash messages globaux */
$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

/* Bannière 'profil incomplet' */
$profileBannerHtml = '';
try {
    if ($user && !empty($user['id'])) {
        $fresh = \App\Models\User::findById((int)$user['id']);
        if ($fresh) {
            $_SESSION['user'] = $user = array_merge($user, $fresh);
        }
        $check = \App\Models\User::isProfileComplete((int)$user['id']);
        if (!$check['complete']) {
            $labels = [
                'nom'=>'nom','prenom'=>'prénom','email'=>'email','telephone'=>'téléphone',
                'adresse'=>'adresse','avatar'=>'photo de profil',
                'preferences'=>'préférences (fumeur, animaux, musique, discussion, clim)'
            ];
            $missing = array_map(fn($k)=>$labels[$k] ?? $k, $check['missing']);
            $txt = "Complétez votre profil : ".htmlspecialchars(implode(', ', $missing)).".";
            $profileBannerHtml = '
            <div class="alert alert-warning border-0 rounded-0 mb-0 alert-dismissible fade show" role="alert">
              <div class="container d-flex flex-wrap align-items-center gap-2">
                <i class="fas fa-user-edit me-1"></i>
                <strong>Profil incomplet.</strong>
                <span class="me-2">'.$txt.'</span>
                <a class="btn btn-sm btn-outline-dark" href="/profil/edit">Compléter maintenant</a>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>';
        }
    }
} catch (\Throwable $e) { /* ignore */ }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<?php if (!empty($meta)): ?>
  <?php foreach ($meta as $name => $contentMeta): ?>
    <meta name="<?= htmlspecialchars((string)$name) ?>" content="<?= htmlspecialchars((string)$contentMeta) ?>">
  <?php endforeach; ?>
<?php endif; ?>
<meta name="description" content="EcoRide, plateforme de covoiturage écoresponsable.">
<link rel="icon" type="image/png" href="/assets/img/favicon-emp.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/app.css">
<style>.navbar .dropdown-menu { z-index: 2000; } .avatar{width:32px;height:32px;object-fit:cover;border-radius:50%;}</style>
<?= $pageStyles ?>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?> bg-light d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-light" style="background-color:#18a558;">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center text-white" href="/">
      <img src="/assets/img/logo-emp-light.png" alt="EcoRide" style="height:36px" class="me-2">
      <span class="fw-semibold d-none d-sm-inline">EcoRide</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item"><a class="nav-link text-white" href="/">Accueil</a></li>
       <li class="nav-item">
  <a class="nav-link text-white<?= (strpos($currentUrl ?? '/', '/covoiturage') === 0 ? ' active fw-semibold' : '') ?>"
     href="<?= BASE_URL ?>covoiturage">
    <i class="fas fa-users me-1"></i> Covoiturage
  </a>
</li>

        <?php if (!$user): ?>
          <li class="nav-item ms-lg-2">
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#authModal">Connexion</button>
          </li>
        <?php else: ?>
          <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item"><a class="nav-link text-white" href="/admin">Admin</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="/employee">Modération</a></li>
          <?php elseif ($role === 'EMPLOYEE'): ?>
            <li class="nav-item"><a class="nav-link text-white" href="/employee">Employé</a></li>
          <?php endif; ?>
          <li class="nav-item dropdown ms-lg-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center text-white" href="#" id="userMenu" role="button" data-bs-toggle="dropdown">
              <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="avatar" class="avatar me-2">
              <span class="fw-semibold"><?= htmlspecialchars($user['nom'] ?? 'Profil') ?></span>
              <?php if ($credits !== null): ?>
                <span class="badge bg-light text-dark ms-2"><?= $credits ?> cr.</span>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <li><a class="dropdown-item" href="/dashboard">Mon profil</a></li>
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
              <li>
                <form action="/logout" method="post" class="px-3">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
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
            <button class="nav-link active" id="tab-login" data-bs-toggle="tab" data-bs-target="#pane-login" type="button" role="tab">Connexion</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-signup" data-bs-toggle="tab" data-bs-target="#pane-signup" type="button" role="tab">Inscription</button>
          </li>
        </ul>
      </div>

      <div class="tab-content px-3 pb-3">
        <?php if (!empty($errorMessage)): ?>
          <div class="alert alert-danger mb-3"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="tab-pane fade show active" id="pane-login" role="tabpanel">
          <form method="post" action="/login" class="needs-validation" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
            <div class="mb-3">
              <label for="loginId" class="form-label">Email ou nom</label>
              <input type="text" class="form-control" id="loginId" name="email" required>
              <div class="invalid-feedback">Veuillez saisir votre email ou votre nom.</div>
            </div>
            <div class="mb-3">
              <label for="loginPass" class="form-label">Mot de passe</label>
              <input type="password" class="form-control" id="loginPass" name="password" required>
              <div class="invalid-feedback">Mot de passe requis.</div>
            </div>
            <button type="submit" class="btn btn-success w-100">Se connecter</button>
          </form>
        </div>

        <div class="tab-pane fade" id="pane-signup" role="tabpanel">
          <form method="post" action="/signup" class="needs-validation" novalidate id="signupForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">

            <div class="mb-3">
              <label class="form-label" for="snNom">Nom</label>
              <input type="text" class="form-control" id="snNom" name="nom" required>
              <div class="invalid-feedback">Nom requis.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="snPrenom">Prénom</label>
              <input type="text" class="form-control" id="snPrenom" name="prenom">
            </div>
            <div class="mb-3">
              <label class="form-label" for="snAdresse">Adresse</label>
              <input type="text" class="form-control" id="snAdresse" name="adresse">
            </div>
            <div class="mb-3">
              <label class="form-label" for="snTel">Téléphone</label>
              <input type="tel" class="form-control" id="snTel" name="telephone" placeholder="06 12 34 56 78">
              <div class="form-text">Optionnel. Chiffres et espaces uniquement.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="snEmail">Adresse email</label>
              <input type="email" class="form-control" id="snEmail" name="email" required>
              <div class="invalid-feedback">Merci de saisir un email valide.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="snPass">Mot de passe</label>
              <input type="password" class="form-control" id="snPass" name="password" minlength="8" required>
              <div class="invalid-feedback">Minimum 8 caractères.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="snPass2">Confirmer le mot de passe</label>
              <input type="password" class="form-control" id="snPass2" name="password_confirm" minlength="8" required>
              <div class="invalid-feedback">Les mots de passe doivent correspondre.</div>
            </div>
            <button type="submit" class="btn btn-primary w-100">S'inscrire</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="bg-success-subtle border-bottom border-success py-2">
  <div class="container d-flex align-items-center gap-3">
    <i class="fas fa-leaf text-success"></i>
    <strong class="text-success">EcoRide</strong>
    <span class="text-muted">— Covoiturage écologique</span>
  </div>
</div>

<?= $profileBannerHtml ?>

<div class="container mt-3">
<?php if (!empty($flashes)): ?>
  <div class="mb-3">
    <?php foreach ($flashes as $f): ?>
      <?php
        $type = $f['type'] ?? 'info';
        $text = $f['text'] ?? '';
        $bs = [
          'success' => 'alert-success',
          'danger' => 'alert-danger',
          'warning' => 'alert-warning',
          'info' => 'alert-info',
        ][$type] ?? 'alert-secondary';
      ?>
      <div class="alert <?= $bs ?> alert-dismissible fade show" role="alert">
        <?= $text ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<main class="flex-grow-1 py-4">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-10 col-xl-8">
        <?= $content ?? '' ?>
      </div>
    </div>
  </div>
</main>

<footer class="mt-auto border-top bg-white">
  <div class="container py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center">
    <div class="small text-muted">
      © <?= date('Y') ?> EcoRide — Tous droits réservés
    </div>
    <div class="small">
      <a href="mailto:contact@ecoride.com" class="text-decoration-none">
        <i class="fas fa-envelope me-1"></i>contact@ecoride.com
      </a>
      <span class="text-muted mx-2">|</span>
      <a href="/mentions-legales" class="text-decoration-none">Mentions légales</a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
(() => {
  'use strict';
  document.querySelectorAll('.needs-validation').forEach(form => {
    form.addEventListener('submit', e => {
      if (!form.checkValidity()) {
        e.preventDefault(); e.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
  const f = document.getElementById('signupForm');
  if (f) {
    const p1 = document.getElementById('snPass');
    const p2 = document.getElementById('snPass2');
    const check = () => { p2.setCustomValidity(p1.value && p2.value && p1.value !== p2.value ? 'Mismatch' : ''); };
    p1 && p1.addEventListener('input', check);
    p2 && p2.addEventListener('input', check);
  }
})();
</script>
<script src="/assets/js/app.js"></script>
<?= $pageScripts ?>
</body>
</html>
