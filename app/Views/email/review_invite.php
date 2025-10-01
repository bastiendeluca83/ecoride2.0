<?php
/**
 * app/Views/email/review_invite.php
 * ---------------------------------
 * Email envoyé à un PASSAGER après la fin d’un trajet,
 * pour l’inviter à déposer un avis sur le conducteur.
 *
 * Variables injectées par le Mailer :
 * - array  $passenger → infos du passager (pseudo, nom…)
 * - array  $ride      → infos du trajet (villes, dates…)
 * - array  $driver    → infos du conducteur (nom, display_name…)
 * - string $link      → lien unique signé pour déposer l’avis
 *
 * Particularités :
 * - Layout très simple pour compatibilité Outlook et webmails.
 * - Styles inline obligatoires pour e-mails.
 * - Bouton construit en <table> (hack classique pour Outlook).
 * - Toujours échapper les variables avec e().
 */

/** Helper d’échappement */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}
?>
<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:auto;line-height:1.5;">
  <h2 style="margin:0 0 12px;">Votre avis nous intéresse ⭐</h2>

  <!-- Salutation personnalisée -->
  <p style="margin:0 0 8px;">Bonjour <?= e($passenger['pseudo'] ?? $passenger['nom'] ?? '') ?>,</p>

  <!-- Contexte du trajet terminé -->
  <p style="margin:0 0 8px;">
    Le trajet 
    <strong><?= e($ride['from_city'] ?? '') ?> → <?= e($ride['to_city'] ?? '') ?></strong> 
    avec <strong><?= e($driver['display_name'] ?? $driver['nom'] ?? 'le conducteur') ?></strong> est terminé.
  </p>

  <!-- Petit récapitulatif du trajet -->
  <ul style="padding-left:20px;margin:8px 0 16px;">
    <li>Départ : <strong><?= e($ride['date_start'] ?? '') ?></strong></li>
    <?php if (!empty($ride['date_end'])): ?>
      <li>Arrivée : <strong><?= e($ride['date_end']) ?></strong></li>
    <?php endif; ?>
  </ul>

  <!-- Message principal -->
  <p style="margin:0 0 16px;">
    Merci de laisser votre avis (note + commentaire).  
    Il sera vérifié avant publication.
  </p>

  <!-- Bouton "Déposer mon avis" (table = compat Outlook) -->
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

  <!-- Fallback lien texte en cas de problème avec le bouton -->
  <p style="font-size:12px;color:#666;margin:0 0 4px;">
    Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :
  </p>
  <p style="font-size:12px;color:#0066cc;word-break:break-all;margin:0 0 16px;">
    <?= e($link) ?>
  </p>

  <!-- Signature -->
  <p style="color:#888;margin:0;">EcoRide</p>
</div>
