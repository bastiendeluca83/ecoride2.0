<?php
/** @var array $user */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
$base = getenv('BASE_URL') ?: 'http://localhost:8080';
?>
<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:auto;">
  <h2>Bienvenue sur EcoRide 👋</h2>
  <p>Bonjour <?= e($user['pseudo'] ?? $user['nom'] ?? '') ?>,</p>
  <p>Votre compte a bien été créé. Vous pouvez maintenant publier des trajets ou réserver une place.</p>
  <p style="margin:24px 0;">
    <a href="<?= e(rtrim($base,'/')) ?>/login" style="background:#16a34a;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;display:inline-block;">
      Me connecter
    </a>
  </p>
  <p style="color:#888;font-size:12px;">E-mail automatique • Merci de ne pas répondre.</p>
</div>
