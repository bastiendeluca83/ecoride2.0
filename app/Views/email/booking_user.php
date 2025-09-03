<?php
/** @var array $passenger */
/** @var array $ride */
/** @var array $driver */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:640px;margin:auto;">
  <h2>Réservation confirmée ✅</h2>
  <p>Bonjour <?= e($passenger['pseudo'] ?? $passenger['nom'] ?? '') ?>,</p>
  <p>Votre place est réservée sur le trajet <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong>.</p>
  <ul>
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
    <li>Conducteur : <strong><?= e($driver['display_name'] ?? $driver['nom'] ?? '') ?></strong></li>
    <li>Prix payé : <strong><?= (int)($ride['price'] ?? 0) ?> crédits</strong></li>
  </ul>
  <p>Bon trajet !</p>
  <hr>
  <p style="color:#888">EcoRide</p>
</div>
