<?php
/** @var array $driver */
/** @var array $ride */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:640px;margin:auto;">
  <h2>Trajet publié 🎉</h2>
  <p>Bonjour <?= e($driver['pseudo'] ?? $driver['nom'] ?? ''); ?>,</p>
  <p>Votre trajet <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong> a été publié.</p>
  <ul>
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
    <li>Prix : <strong><?= (int)($ride['price'] ?? 0) ?> crédits</strong></li>
    <li>Places : <strong><?= (int)($ride['seats_left'] ?? $ride['seats'] ?? 0) ?></strong></li>
  </ul>
  <p>Bon covoiturage !</p>
  <hr>
  <p style="color:#888">EcoRide</p>
</div>
