<?php
/** Vue MVC — Formulaire véhicule (ajout ou édition)
 *  Objectif : permettre à l’utilisateur d’ajouter un véhicule ou de modifier un véhicule existant.
 *
 *  Variables attendues :
 *  @var array|null $vehicle  Données du véhicule si édition, sinon null
 *  @var string     $title    Titre personnalisé (optionnel)
 */

/* Helper d’échappement : je protège toutes les sorties HTML. */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Flag d’état : si $vehicle est fourni, je suis en mode édition. */
$editing = !empty($vehicle);
?>

<div class="container my-4">
  <!-- Titre : je prends $title si dispo, sinon un libellé en fonction du mode (ajout/édition). -->
  <h1 class="h3 mb-3"><?= e($title ?? ($editing ? 'Modifier mon véhicule' : 'Ajouter un véhicule')) ?></h1>

  <!-- Messages flash (feedback utilisateur), puis je les purge pour éviter la redondance au refresh. -->
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>

  <!-- Formulaire : POST vers l’action qui correspond au mode (edit/add).
       Je garde la grille Bootstrap pour un layout propre et responsive. -->
  <form method="post"
        action="<?= BASE_URL . ($editing ? 'user/vehicle/edit' : 'user/vehicle/add') ?>"
        class="row g-3">
    <!-- Protection CSRF systématique -->
    <?= \App\Security\Security::csrfField(); ?>

    <!-- En mode édition, je passe l’identifiant du véhicule -->
    <?php if ($editing): ?>
      <input type="hidden" name="id" value="<?= (int)$vehicle['id'] ?>">
    <?php endif; ?>

    <!-- Marque / Modèle -->
    <div class="col-md-6">
      <label class="form-label">Marque *</label>
      <input type="text" name="brand" class="form-control" required
             value="<?= e($vehicle['brand'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Modèle *</label>
      <input type="text" name="model" class="form-control" required
             value="<?= e($vehicle['model'] ?? '') ?>">
    </div>

    <!-- Couleur / Énergie / Places -->
    <div class="col-md-4">
      <label class="form-label">Couleur</label>
      <input type="text" name="color" class="form-control"
             value="<?= e($vehicle['color'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Énergie</label>
      <select name="energy" class="form-select">
        <?php
          /* Je définis mes valeurs de référence côté vue, c’est simple et lisible.
             Le contrôleur/Model fera la validation stricte derrière (enum, etc.). */
          $energies = [
            'GASOLINE' => 'Essence',
            'DIESEL'   => 'Diesel',
            'ELECTRIC' => 'Électrique',
            'HYBRID'   => 'Hybride'
          ];
          $selected = (string)($vehicle['energy'] ?? '');
          foreach ($energies as $val => $label){
            $sel = ($selected === $val) ? 'selected' : '';
            echo "<option value=\"".e($val)."\" $sel>".e($label)."</option>";
          }
        ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Places *</label>
      <!-- Je force min=1 côté client ; la contrainte réelle (max selon carte grise) sera revalidée côté serveur. -->
      <input type="number" name="seats" class="form-control" min="1" required
             value="<?= e($vehicle['seats'] ?? 4) ?>">
    </div>

    <!-- Plaque / 1ère immatriculation -->
    <div class="col-md-6">
      <label class="form-label">Plaque *</label>
      <input type="text" name="plate" class="form-control" required
             value="<?= e($vehicle['plate'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">1ère immatriculation</label>
      <!-- Champ optionnel : je laisse le formatage date au navigateur, le parsing au backend. -->
      <input type="date" name="first_reg_date" class="form-control"
             value="<?= e($vehicle['first_reg_date'] ?? '') ?>">
    </div>

    <!-- Actions -->
    <div class="col-12 d-flex gap-2 mt-3">
      <button class="btn btn-success" type="submit">
        <?= $editing ? 'Enregistrer' : 'Ajouter' ?>
      </button>
      <a href="<?= BASE_URL ?>user/dashboard" class="btn btn-outline-secondary">Annuler</a>
    </div>
  </form>
</div>
