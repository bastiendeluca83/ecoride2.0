<?php
/**
 * Vue MVC — Formulaire de publication d’un trajet
 * Contexte : app/Views/user/ride_create.php (exemple), injectée dans mon layout.
 * Objectif : permettre à l’utilisateur (conducteur) de créer un trajet.
 *
 * Variables attendues :
 * @var array  $vehicles  Liste des véhicules de l’utilisateur (id, brand, model, plate, …)
 * @var string $title     Titre de la page (optionnel)
 */

 /** Petite fonction d’échappement :
  *  Je caste en string puis j’échappe les quotes pour éviter le XSS.
  *  Je l’utilise pour tout ce qui s’affiche à l’écran.
  */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Je pré-sélectionne le premier véhicule de la liste pour améliorer l’UX.
 *  Si la liste est vide, $firstVehicleId vaut 0 et rien ne sera "selected".
 */
$firstVehicleId = isset($vehicles[0]['id']) ? (int)$vehicles[0]['id'] : 0;
?>

<div class="container my-4">
  <!-- Titre de la page : je garde une valeur de repli si $title n’est pas fourni -->
  <h1 class="h3 mb-3"><?= e($title ?? 'Publier un trajet') ?></h1>

  <!-- Flash messages : j’affiche d’abord l’erreur puis le succès (s’ils existent).
       Je nettoie la session juste après affichage pour éviter la répétition au refresh. -->
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>

  <!-- Mon formulaire : POST vers l’action de création de trajet côté contrôleur.
       Je reste en Bootstrap (grille .row / .col-*) pour un layout propre. -->
  <form method="post" action="<?= BASE_URL ?>user/ride/create" class="row g-3">
    <!-- Protection CSRF : je génère un champ caché sécurisé.
         Le contrôleur vérifiera le token côté serveur. -->
    <?= \App\Security\Security::csrfField(); ?>

    <!-- Sélecteur du véhicule : je rends ce champ obligatoire.
         Je montre brand + model + plate pour aider à choisir. -->
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

    <!-- Nombre de places proposées : je mets un min à 1.
         Le contrôleur devra revalider côté serveur (min/max cohérents avec le véhicule). -->
    <div class="col-md-6">
      <label class="form-label">Places proposées *</label>
      <input type="number" name="seats" class="form-control" min="1" required value="3">
    </div>

    <!-- Villes de départ et d’arrivée : champs texte simples.
         Je laisse le contrôleur normaliser (trim, capitale, etc.). -->
    <div class="col-md-6">
      <label class="form-label">Ville de départ *</label>
      <input type="text" name="from_city" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Ville d'arrivée *</label>
      <input type="text" name="to_city" class="form-control" required>
    </div>

    <!-- Dates/horaires : je pars sur des inputs HTML5 datetime-local.
         Le contrôleur vérifiera que date_end > date_start et appliquera le fuseau si besoin. -->
    <div class="col-md-6">
      <label class="form-label">Date & heure de départ *</label>
      <input type="datetime-local" name="date_start" class="form-control" required>
    </div>

    <!--  Date & heure d'arrivée  -->
    <div class="col-md-6">
      <label class="form-label">Date & heure d'arrivée *</label>
      <input type="datetime-local" name="date_end" class="form-control" required>
    </div>

    <!-- Prix : je parle en “crédits / place”.
         C’est optionnel (placeholder pour guider l’utilisateur). -->
    <div class="col-md-6">
      <label class="form-label">Prix (crédits) / place (optionnel)</label>
      <input type="number" name="price" class="form-control" min="0" step="1" placeholder="ex : 5">
    </div>

    <!-- Notes libres : informations pour les passagers (point de rdv, bagages, etc.). -->
    <div class="col-12">
      <label class="form-label">Notes (optionnel)</label>
      <textarea name="notes" class="form-control" rows="3" placeholder="Infos utiles pour les passagers"></textarea>
    </div>

    <!-- Actions : je propose Publier + Annuler (retour dashboard).
         Le contrôleur fera les validations finales et renverra des flash messages. -->
    <div class="col-12 d-flex gap-2 mt-3">
      <button class="btn btn-success" type="submit">Publier</button>
      <a href="<?= BASE_URL ?>user/dashboard" class="btn btn-outline-secondary">Annuler</a>
    </div>
  </form>
</div>
