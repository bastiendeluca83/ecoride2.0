<?php
/**
 * app/Views/email/signup_welcome.php
 * ----------------------------------
 * Email envoyé à un utilisateur après création de son compte.
 *
 * Variables injectées par le Mailer :
 * - array $user → infos du nouvel utilisateur (pseudo, nom…)
 *
 * Particularités :
 * - Message simple et positif ("Bienvenue sur EcoRide").
 * - Call-to-action clair : bouton pour se connecter.
 * - Fallback BASE_URL si la variable d’env n’est pas définie.
 * - Tout passe par e() pour éviter les failles XSS.
 */

/** Helper d’échappement */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}

/** Base URL de l’application (fallback localhost si rien défini) */
$base = getenv('BASE_URL') ?: 'http://localhost:8080';
?>
<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:auto;">
  <h2>Bienvenue sur EcoRide 👋</h2>

  <!-- Salutation personnalisée -->
  <p>Bonjour <?= e($user['pseudo'] ?? $user['nom'] ?? '') ?>,</p>

  <!-- Message principal -->
  <p>
    Votre compte a bien été créé.  
    Vous pouvez maintenant publier des trajets ou réserver une place.
  </p>

  <!-- Bouton "Me connecter" -->
  <p style="margin:24px 0;">
    <a href="<?= e(rtrim($base,'/')) ?>/login" 
       style="background:#16a34a;color:#fff;text-decoration:none;
              padding:12px 18px;border-radius:8px;display:inline-block;">
      Me connecter
    </a>
  </p>

  <!-- Signature technique -->
  <p style="color:#888;font-size:12px;">E-mail automatique • Merci de ne pas répondre.</p>
</div>
