<?php
/** @var array $driver */
/** @var array $ride */
/** @var array $passenger */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:640px;margin:auto;">
  <h2>Nouvelle réservation ✉️</h2>
  <p>Bonjour <?= e($driver['pseudo'] ?? $driver['nom'] ?? '') ?>,</p>
  <p><?= e($passenger['pseudo'] ?? $passenger['nom'] ?? 'Un passager') ?> a réservé une place sur votre trajet
     <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong>.</p>
  <ul>
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
    <li>Prix : <strong><?= (int)($ride['price'] ?? 0) ?> crédits</strong></li>
  </ul>
  <p>Rendez-vous dans votre tableau de bord pour voir la liste des participants.</p>
  <hr>
  <p style="color:#888">EcoRide</p>
</div>
