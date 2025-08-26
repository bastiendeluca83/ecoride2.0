<?php
/** @var array $vehicles */
/** @var string $title */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$firstVehicleId = isset($vehicles[0]['id']) ? (int)$vehicles[0]['id'] : 0;
?>
<div class="container my-4">
  <h1 class="h3 mb-3"><?= e($title ?? 'Publier un trajet') ?></h1>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>user/ride/create" class="row g-3">
    <?= \App\Security\Security::csrfField(); ?>

    <div class="col-md-6">
      <label class="form-label">Véhicule *</label>
      <select name="vehicle_id" class="form-select" required>
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= (int)$v['id'] ?>"
                  <?= ($firstVehicleId === (int)$v['id']) ? 'selected' : '' ?>>
            <?= e(($v['brand'] ?? '').' '.($v['model'] ?? '').' • '.($v['plate'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">Choisissez le véhicule utilisé pour ce trajet.</small>
    </div>

    <div class="col-md-6">
      <label class="form-label">Places proposées *</label>
      <input type="number" name="seats" class="form-control" min="1" required value="3">
    </div>

    <div class="col-md-6">
      <label class="form-label">Ville de départ *</label>
      <input type="text" name="from_city" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Ville d'arrivée *</label>
      <input type="text" name="to_city" class="form-control" required>
    </div>

    <div class="col-md-6">
      <label class="form-label">Date & heure de départ *</label>
      <input type="datetime-local" name="date_start" class="form-control" required>
    </div>

    <div class="col-md-6">
      <label class="form-label">Prix (crédits) / place (optionnel)</label>
      <input type="number" name="price" class="form-control" min="0" step="1" placeholder="ex : 5">
    </div>

    <div class="col-12">
      <label class="form-label">Notes (optionnel)</label>
      <textarea name="notes" class="form-control" rows="3" placeholder="Infos utiles pour les passagers"></textarea>
    </div>

    <div class="col-12 d-flex gap-2 mt-3">
      <button class="btn btn-success" type="submit">Publier</button>
      <a href="<?= BASE_URL ?>user/dashboard" class="btn btn-outline-secondary">Annuler</a>
    </div>
  </form>
</div>
