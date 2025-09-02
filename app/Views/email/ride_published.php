<?php if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } } ?>
<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.5">
  <h2>Bonjour <?= e($driver['pseudo'] ?? $driver['nom'] ?? '') ?>,</h2>
  <p>Votre trajet vient d’être <strong>publié</strong> sur EcoRide.</p>
  <ul>
    <li><strong>Départ :</strong> <?= e($ride['start_city'] ?? $ride['start_address'] ?? '') ?></li>
    <li><strong>Arrivée :</strong> <?= e($ride['end_city'] ?? $ride['end_address'] ?? '') ?></li>
    <li><strong>Date/Heure :</strong> <?= e($ride['departure_at'] ?? '') ?></li>
    <li><strong>Prix :</strong> <?= e($ride['price'] ?? '') ?> crédits</li>
    <li><strong>Places :</strong> <?= e($ride['seats'] ?? '') ?></li>
  </ul>
  <p>Bon covoiturage !</p>
  <p>— L’équipe EcoRide</p>
</div>
