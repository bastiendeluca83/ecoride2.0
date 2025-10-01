<?php
/* Vue MVC — Laisser un avis
   Objectif : permettre à l’utilisateur de déposer une note (1 à 5) + un commentaire libre.
   Sécurité : j’inclus CSRF et un token spécifique pour lier l’avis à un trajet ou un conducteur.
*/

/* Helper d’échappement : je protège toutes les sorties HTML. */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}
?>

<div class="container py-4">
  <h1 class="h3 mb-3">Laisser un avis</h1>

  <!-- Messages flash : feedback utilisateur après tentative de soumission -->
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>

  <!-- Formulaire principal -->
  <div class="card shadow-sm">
    <div class="card-body">
      <!-- Je poste vers l’URL /reviews (contrôleur ReviewsController::create par ex.) -->
      <form method="post" action="<?= e(BASE_URL.'reviews') ?>">
        <!-- Protection CSRF : champ caché -->
        <input type="hidden" name="csrf" value="<?= e($csrf ?? ($_SESSION['csrf'] ?? '')) ?>">
        <!-- Token caché pour identifier la ressource ciblée (trajet ou conducteur) -->
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

        <!-- Champ note : obligatoire, entre 1 et 5 -->
        <div class="mb-3">
          <label class="form-label">Note (1 à 5)</label>
          <input type="number" class="form-control" name="note" min="1" max="5" required>
        </div>

        <!-- Champ commentaire : libre, non obligatoire -->
        <div class="mb-3">
          <label class="form-label">Commentaire</label>
          <textarea class="form-control" name="comment" rows="4" placeholder="Votre ressenti..."></textarea>
        </div>

        <!-- Bouton d’envoi -->
        <button class="btn btn-success">Envoyer mon avis</button>
      </form>
    </div>
  </div>
</div>
