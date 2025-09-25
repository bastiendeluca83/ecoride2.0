<?php
/* app/Views/auth/login.php
 Vue injectée dans layouts/base.php via BaseController::render()
 */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$title = 'Connexion — EcoRide';

/* $error peut être fourni par AuthController::login() via render() */
$error = $error ?? null;
?>

<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-4 text-center">Connexion</h1>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
          <?php endif; ?>

          <form method="post" action="/login" novalidate>
            <input type="hidden" name="redirect" value="<?= h($_GET['redirect'] ?? '/') ?>">
            <?php if (session_status() === PHP_SESSION_NONE) session_start(); $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16)); ?>
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="text" name="email" id="email" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Mot de passe</label>
              <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success w-100">Se connecter</button>
          </form>

          <p class="mt-3 text-center mb-0">
            Pas encore de compte ? <a href="/signup">Créer un compte</a>
          </p>
          <p class="text-center mt-1">
            <a href="/">← Retour à l'accueil</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
