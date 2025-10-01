<?php
/**
 * app/Views/email/ride_published.php
 * ----------------------------------
 * Email envoyé au CONDUCTEUR quand il publie un trajet avec succès.
 *
 * Variables injectées par le Mailer :
 * - array $driver → infos du conducteur (pseudo, nom…)
 * - array $ride   → infos du trajet (départ, arrivée, dates, prix, places…)
 *
 * Particularités :
 * - Mise en page basique mais lisible (inlines CSS pour compatibilité).
 * - Toutes les variables sont échappées avec e() (anti-XSS).
 */

/** Helper d’échappement (sécurité XSS) */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}
?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:640px;margin:auto;">
  <h2>Trajet publié 🎉</h2>

  <!-- Salutation -->
  <p>Bonjour <?= e($driver['pseudo'] ?? $driver['nom'] ?? ''); ?>,</p>

  <!-- Corps principal -->
  <p>
    Votre trajet 
    <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong> 
    a été publié.
  </p>

  <!-- Détails du trajet -->
  <ul>
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
    <li>Prix : <strong><?= (int)($ride['price'] ?? 0) ?> crédits</strong></li>
    <li>Places : <strong><?= (int)($ride['seats_left'] ?? $ride['seats'] ?? 0) ?></strong></li>
  </ul>

  <!-- Message de clôture -->
  <p>Bon covoiturage !</p>

  <!-- Signature -->
  <hr>
  <p style="color:#888">EcoRide</p>
</div>
