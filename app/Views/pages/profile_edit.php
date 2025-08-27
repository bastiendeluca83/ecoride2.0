<?php
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/** @var array|null $user */
/** @var array|null $prefs */
/** @var string $title */

$pref = function($k, $default = 0) use ($prefs) {
  $v = $prefs[$k] ?? $default;
  return (string)$v;
};

/** Calcule l'âge (années pleines) à partir d'une date YYYY-MM-DD */
function age_years(?string $dateNaissance): ?int {
  $d = $dateNaissance ? trim($dateNaissance) : '';
  if ($d === '') return null;
  try {
    $dob = new \DateTime($d);
    $now = new \DateTime('today');
    return $dob->diff($now)->y;
  } catch (\Throwable $e) {
    return null;
  }
}

$dob = $user['date_naissance'] ?? null;
$age = age_years($dob);
?>

<div class="container my-4">
  <h1 class="h3 mb-3"><?= e($title ?? 'Modifier mon profil') ?></h1>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>profile/edit" class="row g-3" enctype="multipart/form-data">
    <?= \App\Security\Security::csrfField(); ?>

    <!-- Avatar -->
    <div class="col-12">
      <label class="form-label">Photo de profil (avatar)</label>
      <div class="d-flex align-items-center gap-3">
        <?php
        $avatar = $user['avatar_path'] ?? ($_SESSION['user']['avatar_path'] ?? null);
        $avatarUrl = $avatar ? (BASE_URL . $avatar) : (BASE_URL . 'assets/img/avatar-placeholder.png');
        ?>
        <img src="<?= e($avatarUrl) ?>" alt="Avatar" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
        <input type="file" name="avatar" class="form-control" accept="image/*">
      </div>
      <small class="text-muted">Formats acceptés: JPG/PNG/WEBP/GIF — 2 Mo max.</small>
    </div>

    <div class="col-md-6">
      <label class="form-label">Nom</label>
      <input type="text" name="last_name" class="form-control" required value="<?= e($user['last_name'] ?? $user['nom'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Prénom</label>
      <input type="text" name="first_name" class="form-control" required value="<?= e($user['first_name'] ?? $user['prenom'] ?? '') ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required value="<?= e($user['email'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Téléphone</label>
      <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? $user['telephone'] ?? '') ?>">
    </div>

    <div class="col-12">
      <label class="form-label">Adresse</label>
      <input type="text" name="address" class="form-control" value="<?= e($user['address'] ?? $user['adresse'] ?? '') ?>">
    </div>

    <!-- Date de naissance + âge -->
    <div class="col-md-6">
      <label class="form-label">Date de naissance</label>
      <input type="date" name="date_naissance" class="form-control" value="<?= e($dob ?? '') ?>">
      <div class="form-text">Utilisée pour afficher votre âge.</div>
    </div>
    <div class="col-md-6 d-flex align-items-end">
      <div>
        <label class="form-label d-block">Âge</label>
        <div class="fw-semibold">
          <?= $age !== null ? e($age).' ans' : '<span class="text-muted">—</span>' ?>
        </div>
      </div>
    </div>

    <div class="col-12">
      <label class="form-label">Bio</label>
      <textarea name="bio" class="form-control" rows="3"><?= e($user['bio'] ?? '') ?></textarea>
    </div>

    <!-- Préférences de trajet -->
    <div class="col-12">
      <hr>
      <h6 class="mb-2">Mes préférences pendant le trajet</h6>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Fumeur</label>
          <select name="pref_smoking" class="form-select">
            <option value="0" <?= $pref('smoker','0')==='0'?'selected':''; ?>>N/A</option>
            <option value="1" <?= $pref('smoker','0')==='1'?'selected':''; ?>>Non</option>
            <option value="2" <?= $pref('smoker','0')==='2'?'selected':''; ?>>Oui</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Animaux acceptés</label>
          <select name="pref_pets" class="form-select">
            <option value="0" <?= $pref('animals','0')==='0'?'selected':''; ?>>N/A</option>
            <option value="1" <?= $pref('animals','0')==='1'?'selected':''; ?>>Non</option>
            <option value="2" <?= $pref('animals','0')==='2'?'selected':''; ?>>Oui</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Musique</label>
          <select name="pref_music" class="form-select">
            <option value="0" <?= $pref('music','0')==='0'?'selected':''; ?>>N/A</option>
            <option value="1" <?= $pref('music','1')==='1'?'selected':''; ?>>Plutôt non</option>
            <option value="2" <?= $pref('music','1')==='2'?'selected':''; ?>>Avec plaisir</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Discussion</label>
          <select name="pref_chat" class="form-select">
            <option value="0" <?= $pref('chatty','0')==='0'?'selected':''; ?>>N/A</option>
            <option value="1" <?= $pref('chatty','1')==='1'?'selected':''; ?>>Discret</option>
            <option value="2" <?= $pref('chatty','1')==='2'?'selected':''; ?>>Bavard</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Climatisation</label>
          <select name="pref_ac" class="form-select">
            <option value="0" <?= $pref('ac','0')==='0'?'selected':''; ?>>N/A</option>
            <option value="1" <?= $pref('ac','1')==='1'?'selected':''; ?>>Oui</option>
            <option value="2" <?= $pref('ac','1')==='2'?'selected':''; ?>>Peu/éteinte</option>
          </select>
        </div>
      </div>
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
