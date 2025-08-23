<?php
// app/Views/auth/signup.php
// Vue injectée dans layouts/base.php via BaseController::render()
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$title = 'Créer un compte — EcoRide';

// $error peut être fourni par AuthController::signup() via render()
$error = $error ?? null;
?>

<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-4 text-center">Créer un compte</h1>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
          <?php endif; ?>

          <form method="post" action="/signup" novalidate>
            <input type="hidden" name="redirect" value="<?= h($_GET['redirect'] ?? '/dashboard') ?>">
            <?php if (session_status() === PHP_SESSION_NONE) session_start(); $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16)); ?>
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

            <div class="row g-3">
              <div class="col-md-6">
                <label for="nom" class="form-label">Nom *</label>
                <input type="text" name="nom" id="nom" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label for="prenom" class="form-label">Prénom</label>
                <input type="text" name="prenom" id="prenom" class="form-control">
              </div>

              <div class="col-12">
                <label for="adresse" class="form-label">Adresse</label>
                <input type="text" name="adresse" id="adresse" class="form-control">
              </div>

              <div class="col-md-6">
                <label for="telephone" class="form-label">Téléphone</label>
                <input type="text" name="telephone" id="telephone" class="form-control" placeholder="ex: 06 12 34 56 78">
              </div>
              <div class="col-md-6">
                <label for="email" class="form-label">Email *</label>
                <input type="email" name="email" id="email" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label for="password" class="form-label">Mot de passe *</label>
                <input type="password" name="password" id="password" class="form-control" minlength="8" required>
              </div>
              <div class="col-md-6">
                <label for="password_confirm" class="form-label">Confirmer *</label>
                <input type="password" name="password_confirm" id="password_confirm" class="form-control" minlength="8" required>
              </div>
            </div>

            <button type="submit" class="btn btn-success w-100 mt-3">Créer mon compte</button>
          </form>

          <p class="mt-3 text-center mb-0">
            Déjà inscrit ? <a href="/login">Se connecter</a>
          </p>
          <p class="text-center mt-1">
            <a href="/">← Retour à l'accueil</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
