<?php
/**
 * app/Views/email/booking_driver.php
 * ----------------------------------
 * Email envoyé au conducteur quand un passager réserve une place sur son trajet.
 *
 * Variables injectées par le Mailer :
 * - array $driver    → infos du conducteur (pseudo, nom, email…)
 * - array $ride      → infos du trajet (ville départ/arrivée, dates, prix…)
 * - array $passenger → infos du passager (pseudo, nom…)
 *
 * Particularités :
 * - j’utilise un style inline minimaliste (compatible clients mail).
 * - j’échappe toujours les sorties avec e() (anti-XSS).
 * - la mise en page est volontairement simple pour maximiser la compatibilité.
 */

/** Helper d’échappement */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}
?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:640px;margin:auto;">
  <h2>Nouvelle réservation ✉️</h2>

  <!-- Premier message -->
  <p>Bonjour <?= e($driver['pseudo'] ?? $driver['nom'] ?? '') ?>,</p>

  <!-- Corps du message -->
  <p>
    <?= e($passenger['pseudo'] ?? $passenger['nom'] ?? 'Un passager') ?> 
    a réservé une place sur votre trajet
    <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong>.
  </p>

  <!-- Détails du trajet -->
  <ul>
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
    <li>Prix : <strong><?= (int)($ride['price'] ?? 0) ?> crédits</strong></li>
  </ul>

  <!-- Call to action implicite -->
  <p>Rendez-vous dans votre tableau de bord pour voir la liste des participants.</p>

  <!-- Signature -->
  <hr>
  <p style="color:#888">EcoRide</p>
</div>
