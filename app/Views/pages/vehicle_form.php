<?php
/** @var array|null $vehicle */
/** @var string $title */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$editing = !empty($vehicle);
?>
<div class="container my-4">
  <h1 class="h3 mb-3"><?= e($title ?? ($editing ? 'Modifier mon véhicule' : 'Ajouter un véhicule')) ?></h1>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL . ($editing ? 'user/vehicle/edit' : 'user/vehicle/add') ?>" class="row g-3">
    <?= \App\Security\Security::csrfField(); ?>
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$vehicle['id'] ?>"><?php endif; ?>

    <div class="col-md-6">
      <label class="form-label">Marque *</label>
      <input type="text" name="brand" class="form-control" required value="<?= e($vehicle['brand'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Modèle *</label>
      <input type="text" name="model" class="form-control" required value="<?= e($vehicle['model'] ?? '') ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Couleur</label>
      <input type="text" name="color" class="form-control" value="<?= e($vehicle['color'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Énergie</label>
      <select name="energy" class="form-select">
        <?php
          $energies = ['GASOLINE'=>'Essence','DIESEL'=>'Diesel','ELECTRIC'=>'Électrique','HYBRID'=>'Hybride'];
          $selected = (string)($vehicle['energy'] ?? '');
          foreach ($energies as $val=>$label){
            $sel = ($selected === $val) ? 'selected' : '';
            echo "<option value=\"".e($val)."\" $sel>".e($label)."</option>";
          }
        ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Places *</label>
      <input type="number" name="seats" class="form-control" min="1" required value="<?= e($vehicle['seats'] ?? 4) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Plaque *</label>
      <input type="text" name="plate" class="form-control" required value="<?= e($vehicle['plate'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">1ère immatriculation</label>
      <input type="date" name="first_reg_date" class="form-control" value="<?= e($vehicle['first_reg_date'] ?? '') ?>">
    </div>

    <div class="col-12 d-flex gap-2 mt-3">
      <button class="btn btn-success" type="submit"><?= $editing ? 'Enregistrer' : 'Ajouter' ?></button>
      <a href="<?= BASE_URL ?>user/dashboard" class="btn btn-outline-secondary">Annuler</a>
    </div>
  </form>
</div>
