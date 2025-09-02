<?php if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } } ?>
<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.5">
  <h2>Bonjour <?= e($driver['pseudo'] ?? $driver['nom'] ?? '') ?>,</h2>
  <p>Vous avez une <strong>nouvelle réservation</strong> sur votre trajet.</p>
  <h3 style="margin-top:16px">Détails</h3>
  <ul>
    <li><strong>Passager :</strong> <?= e($passenger['pseudo'] ?? $passenger['nom'] ?? '') ?> (<?= e($passenger['email'] ?? '') ?>)</li>
    <li><strong>Départ :</strong> <?= e($ride['start_city'] ?? $ride['start_address'] ?? '') ?></li>
    <li><strong>Arrivée :</strong> <?= e($ride['end_city'] ?? $ride['end_address'] ?? '') ?></li>
    <li><strong>Date/Heure :</strong> <?= e($ride['departure_at'] ?? '') ?></li>
    <li><strong>Places restantes :</strong> <?= e($ride['seats_left'] ?? ($ride['seats'] ?? '')) ?></li>
  </ul>
  <p>Pensez à démarrer le trajet au moment du départ.</p>
  <p>— L’équipe EcoRide</p>
</div>
