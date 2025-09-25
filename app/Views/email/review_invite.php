<?php
/** @var array $passenger */
/** @var array $ride */
/** @var array $driver */
/** @var string $link */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:640px;margin:auto;">
  <h2>Votre avis nous intéresse ⭐</h2>
  <p>Bonjour <?= e($passenger['pseudo'] ?? $passenger['nom'] ?? '') ?>,</p>
  <p>Le trajet <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong> avec
     <strong><?= e($driver['display_name'] ?? $driver['nom'] ?? 'le conducteur') ?></strong> est terminé.</p>
  <ul>
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
  </ul>
  <p>Merci de laisser votre avis (note + commentaire). Il sera vérifié avant publication.</p>
  <p style="margin:24px 0;">
    <a href="<?= e($link) ?>" style="display:inline-block;padding:12px 18px;background:#16a34a;color:#fff;text-decoration:none;border-radius:8px;">
      Déposer mon avis
    </a>
  </p>
  <p style="color:#888">EcoRide</p>
</div>
