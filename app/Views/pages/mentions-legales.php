<?php
/* app/Views/pages/legal.php
   Vue injectée dans layouts/base.php (MVC)
   Mention légales complètes – EcoRide (infos fictives)
*/
$title = $title ?? 'Mentions légales – EcoRide';
$meta  = $meta  ?? [
  'description' => "Mentions légales de la plateforme de covoiturage écologique EcoRide (informations fictives)."
];

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
?>

<div class="container py-4">
  <h1 class="h3 mb-4">Mentions légales</h1>

  <section class="mb-4">
    <h2 class="h5">1. Informations légales</h2>
    <ul class="mb-0">
      <li><strong>Nom du site :</strong> EcoRide – Plateforme de covoiturage écologique</li>
      <li><strong>Responsable de la publication :</strong> José MARTIN (Directeur technique)</li>
      <li><strong>Contact :</strong> <a href="mailto:ecoride.demo@gmail.com">ecoride.demo@gmail.com</a></li>
      <li><strong>Siège social :</strong> 25 rue des Horizons Verts, 75012 Paris, France</li>
      <li><strong>Téléphone :</strong> +33 (0)1 23 45 67 89</li>
    </ul>
  </section>

  <section class="mb-4">
    <h2 class="h5">2. Hébergeur</h2>
    <p class="mb-0">
      <strong>OVH SAS</strong><br>
      2 rue Kellermann, 59100 Roubaix, France<br>
      Tél. : +33 (0)2 03 04 05 06<br>
      Site : <a href="https://www.ovhcloud.com/fr/" target="_blank" rel="noopener">ovhcloud.com</a>
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h5">3. Propriété intellectuelle</h2>
    <p class="mb-0">
      L’ensemble du contenu de ce site (textes, images, graphismes, logo, icônes, sons, logiciels, etc.)
      est la propriété exclusive d’EcoRide, sauf mentions contraires. Toute reproduction, représentation,
      modification, publication ou adaptation, totale ou partielle, est interdite sans autorisation écrite
      préalable.
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h5">4. Données personnelles (RGPD)</h2>
    <p>
      Les données personnelles collectées (identité, email, informations relatives aux trajets et préférences de
      covoiturage) sont nécessaires à la création et à la gestion du compte utilisateur, à la mise en relation
      entre conducteurs et passagers, et au fonctionnement du service.
    </p>
    <ul>
      <li><strong>Base légale&nbsp;:</strong> exécution du contrat (CGU) et intérêt légitime (sécurité, prévention de la fraude).</li>
      <li><strong>Durées de conservation&nbsp;:</strong> pendant la relation contractuelle puis archivage légal.</li>
      <li><strong>Destinataires&nbsp;:</strong> uniquement EcoRide et prestataires techniques habilités.</li>
      <li><strong>Transferts hors UE&nbsp;:</strong> aucun transfert prévu.</li>
      <li><strong>Vos droits&nbsp;:</strong> accès, rectification, suppression, limitation, opposition, portabilité.</li>
    </ul>
    <p class="mb-0">
      Pour exercer vos droits&nbsp;: <a href="mailto:ecoride.demo@gmail.com">ecoride.demo@gmail.com</a> ou par courrier au siège social.
      En cas de difficulté, vous pouvez saisir la CNIL (cnil.fr).
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h5">5. Cookies</h2>
    <p class="mb-2">
      Ce site utilise des cookies techniques nécessaires à son fonctionnement (session, sécurité) et, le cas échéant,
      des cookies de mesure d’audience anonymisés. Vous pouvez paramétrer votre navigateur pour refuser tout ou partie
      des cookies.
    </p>
    <ul class="mb-0">
      <li><strong>Cookies nécessaires :</strong> indispensables au service (ex. <code>PHPSESSID</code>).</li>
      <li><strong>Mesure d’audience :</strong> statistiques agrégées, sans traçage individualisé.</li>
    </ul>
  </section>

  <section class="mb-4">
    <h2 class="h5">6. Responsabilité</h2>
    <p class="mb-0">
      EcoRide s’efforce d’assurer l’exactitude et la mise à jour des informations diffusées, sans garantie d’absence
      d’erreurs ou d’omissions. L’utilisateur demeure seul responsable de l’usage qu’il fait du site et des informations
      qui y sont fournies. EcoRide ne pourra être tenue responsable des dommages directs ou indirects résultant de
      l’utilisation du site.
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h5">7. Loi applicable & juridiction compétente</h2>
    <p class="mb-0">
      Les présentes mentions légales sont régies par le droit français. À défaut d’accord amiable, les tribunaux français
      seront seuls compétents.
    </p>
  </section>

  <hr>

  <p class="text-muted mb-0 small">
    Document à caractère fictif destiné à un projet pédagogique.
  </p>
</div>
