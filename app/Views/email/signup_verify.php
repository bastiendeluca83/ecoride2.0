<?php
/** @var array $user */
/** @var string $link */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:auto;">
  <h2>Confirmez votre adresse e-mail</h2>
  <p>Bonjour <?= e($user['pseudo'] ?? $user['nom'] ?? '') ?>,</p>
  <p>Pour sécuriser votre compte, merci de confirmer votre adresse e-mail :</p>

  <p style="margin:24px 0;">
    <a href="<?= e($link) ?>" style="background:#2563eb;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;display:inline-block;">
      Confirmer mon e-mail
    </a>
  </p>

  <p style="color:#666;font-size:12px;">
    Si le bouton ne fonctionne pas, copiez ce lien :<br>
    <?= e($link) ?>
  </p>

  <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
  <p style="color:#888;font-size:12px;">E-mail automatique • Merci de ne pas répondre.</p>
</div>
