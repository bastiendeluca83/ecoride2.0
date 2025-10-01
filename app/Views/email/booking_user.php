<?php
/**
 * app/Views/email/booking_user.php
 * --------------------------------
 * Email envoyé au PASSAGER lorsqu’il a réservé un trajet avec succès.
 *
 * Variables injectées par le Mailer :
 * - array $passenger → infos du passager (pseudo, nom…)
 * - array $ride      → infos du trajet (départ, arrivée, date, prix…)
 * - array $driver    → infos du conducteur (nom, display_name…)
 *
 * Particularités :
 * - Mise en page très simple (inlines CSS) pour compatibilité e-mails.
 * - Toutes les sorties passent par e() pour éviter XSS.
 */

/** Helper échappement */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}
?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:640px;margin:auto;">
  <h2>Réservation confirmée ✅</h2>

  <!-- Salutation personnalisée -->
  <p>Bonjour <?= e($passenger['pseudo'] ?? $passenger['nom'] ?? '') ?>,</p>

  <!-- Corps du message -->
  <p>
    Votre place est réservée sur le trajet 
    <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong>.
  </p>

  <!-- Récapitulatif du trajet -->
  <ul>
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
    <li>Conducteur : <strong><?= e($driver['display_name'] ?? $driver['nom'] ?? '') ?></strong></li>
    <li>Prix payé : <strong><?= (int)($ride['price'] ?? 0) ?> crédits</strong></li>
  </ul>

  <!-- Signature courte -->
  <p>Bon trajet !</p>
  <hr>
  <p style="color:#888">EcoRide</p>
</div>
