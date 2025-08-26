<?php
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/** @var array|null $user */
/** @var string $title */
?>
<div class="container my-4">
  <h1 class="h3 mb-3"><?= e($title ?? 'Modifier mon profil') ?></h1>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>profile/edit" class="row g-3">
    <?= \App\Security\Security::csrfField(); ?>

    <div class="col-md-6">
      <label class="form-label">Nom</label>
      <input type="text" name="last_name" class="form-control" required
             value="<?= e($user['last_name'] ?? $user['nom'] ?? '') ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Prénom</label>
      <input type="text" name="first_name" class="form-control" required
             value="<?= e($user['first_name'] ?? $user['prenom'] ?? '') ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required
             value="<?= e($user['email'] ?? '') ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Téléphone</label>
      <input type="text" name="phone" class="form-control"
             value="<?= e($user['phone'] ?? $user['telephone'] ?? '') ?>">
    </div>

    <div class="col-12">
      <label class="form-label">Adresse</label>
      <input type="text" name="address" class="form-control"
             value="<?= e($user['address'] ?? $user['adresse'] ?? '') ?>">
    </div>

    <div class="col-12">
      <label class="form-label">Bio</label>
      <textarea name="bio" class="form-control" rows="3"><?= e($user['bio'] ?? '') ?></textarea>
    </div>

    <hr class="mt-4">

    <div class="col-md-6">
      <label class="form-label">Nouveau mot de passe (optionnel)</label>
      <input type="password" name="new_password" class="form-control" minlength="8">
    </div>
    <div class="col-md-6">
      <label class="form-label">Confirmer</label>
      <input type="password" name="confirm_password" class="form-control" minlength="8">
    </div>

    <div class="col-12 d-flex gap-2 mt-3">
      <button class="btn btn-success" type="submit">Enregistrer</button>
      <a href="<?= BASE_URL ?>user/dashboard" class="btn btn-outline-secondary">Annuler</a>
    </div>
  </form>
</div>
