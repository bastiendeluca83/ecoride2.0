<?php
/** @var array $passenger */
/** @var array $ride */
/** @var array $driver */
/** @var string $link */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:auto;line-height:1.5;">
  <h2 style="margin:0 0 12px;">Votre avis nous intéresse ⭐</h2>

  <p style="margin:0 0 8px;">Bonjour <?= e($passenger['pseudo'] ?? $passenger['nom'] ?? '') ?>,</p>

  <p style="margin:0 0 8px;">
    Le trajet <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong> avec
    <strong><?= e($driver['display_name'] ?? $driver['nom'] ?? 'le conducteur') ?></strong> est terminé.
  </p>

  <ul style="padding-left:20px;margin:8px 0 16px;">
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
  </ul>

  <p style="margin:0 0 16px;">Merci de laisser votre avis (note + commentaire). Il sera vérifié avant publication.</p>

  <!-- Bouton compatible Outlook -->
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;">
    <tr>
      <td bgcolor="#16a34a" style="border-radius:8px;">
        <a href="<?= e($link) ?>"
           style="display:inline-block;padding:12px 18px;font-weight:bold;text-decoration:none;
                  color:#ffffff;background:#16a34a;border-radius:8px;">
          Déposer mon avis
        </a>
      </td>
    </tr>
  </table>

  <!-- Lien texte (fallback) -->
  <p style="font-size:12px;color:#666;margin:0 0 4px;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>
  <p style="font-size:12px;color:#0066cc;word-break:break-all;margin:0 0 16px;"><?= e($link) ?></p>

  <p style="color:#888;margin:0;">EcoRide</p>
</div>
