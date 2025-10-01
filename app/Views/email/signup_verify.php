<?php
/**
 * app/Views/email/signup_verify.php
 * ---------------------------------
 * Email envoyé à l’utilisateur nouvellement inscrit,
 * afin qu’il confirme son adresse e-mail.
 *
 * Variables injectées par le Mailer :
 * - array  $user → infos du nouvel utilisateur (pseudo, nom…)
 * - string $link → lien unique et signé de confirmation
 *
 * Particularités :
 * - Layout volontairement simple pour compatibilité e-mails.
 * - Bouton stylé via <a> inline (fonctionne sur webmails).
 * - Fallback : lien en clair pour copier-coller si le bouton ne marche pas.
 * - On échappe toujours les sorties avec e() (sécurité XSS).
 */

/** Helper d’échappement */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}
?>
<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:auto;">
  <h2>Confirmez votre adresse e-mail</h2>

  <!-- Salutation personnalisée -->
  <p>Bonjour <?= e($user['pseudo'] ?? $user['nom'] ?? '') ?>,</p>

  <!-- Message principal -->
  <p>Pour sécuriser votre compte, merci de confirmer votre adresse e-mail :</p>

  <!-- Bouton de confirmation -->
  <p style="margin:24px 0;">
    <a href="<?= e($link) ?>" 
       style="background:#2563eb;color:#fff;text-decoration:none;
              padding:12px 18px;border-radius:8px;display:inline-block;">
      Confirmer mon e-mail
    </a>
  </p>

  <!-- Fallback en cas de problème avec le bouton -->
  <p style="color:#666;font-size:12px;">
    Si le bouton ne fonctionne pas, copiez ce lien :<br>
    <?= e($link) ?>
  </p>

  <!-- Signature technique -->
  <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
  <p style="color:#888;font-size:12px;">E-mail automatique • Merci de ne pas répondre.</p>
</div>
