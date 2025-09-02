<?php if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } } ?>
<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.5">
  <h2>Bonjour <?= e($passenger['pseudo'] ?? $passenger['nom'] ?? '') ?>,</h2>
  <p>Votre <strong>réservation</strong> a bien été enregistrée ✅</p>
  <h3 style="margin-top:16px">Détails du trajet</h3>
  <ul>
    <li><strong>Chauffeur :</strong> <?= e($driver['pseudo'] ?? $driver['nom'] ?? '') ?></li>
    <li><strong>Départ :</strong> <?= e($ride['start_city'] ?? $ride['start_address'] ?? '') ?></li>
    <li><strong>Arrivée :</strong> <?= e($ride['end_city'] ?? $ride['end_address'] ?? '') ?></li>
    <li><strong>Date/Heure :</strong> <?= e($ride['departure_at'] ?? '') ?></li>
    <li><strong>Prix payé :</strong> <?= e($ride['price'] ?? '') ?> crédits</li>
  </ul>
  <p>Merci d’utiliser EcoRide 💚</p>
</div>
