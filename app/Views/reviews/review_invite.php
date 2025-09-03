<?php
/** @var array  $passenger */
/** @var array  $ride */
/** @var array  $driver */
/** @var string $link */
if (!function_exists('esc')) { function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:auto;">
  <h2>Votre trajet est terminé 🎉</h2>
  <p>Merci d’avoir voyagé avec <strong>EcoRide</strong> !</p>

  <p>
    Trajet : <strong><?= esc($ride['from_city'] ?? '') ?> → <?= esc($ride['to_city'] ?? '') ?></strong><br>
    Conducteur : <strong><?= esc($driver['pseudo'] ?? $driver['email'] ?? 'Conducteur') ?></strong>
  </p>

  <p>Pour aider la communauté, laissez un avis (note + commentaire) sur votre conducteur :</p>

  <p style="margin:24px 0;">
    <a href="<?= esc($link) ?>" style="background:#16a34a;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;display:inline-block;">
      Laisser mon avis ⭐
    </a>
  </p>

  <p style="color:#666;font-size:12px;">
    Ce lien est valable 7 jours. Si le bouton ne fonctionne pas, copiez/collez ce lien :<br>
    <?= esc($link) ?>
  </p>

  <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
  <p style="color:#888;font-size:12px;">Email automatique • Merci de ne pas répondre.</p>
</div>
