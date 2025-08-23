<?php
// app/Views/pages/legal.php
// Vue injectée dans layouts/base.php (NE PAS mettre <html>/<head>/<body>)
$title = $title ?? 'Mentions légales – EcoRide';

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
?>

<h1 class="h4 mb-3">Mentions légales</h1>

<section class="mb-4">
  <h2 class="h6">Éditeur du site</h2>
  <p class="mb-1"><strong>EcoRide</strong></p>
  <p class="mb-0">Contact : <a href="mailto:contact@ecoride.com">contact@ecoride.com</a></p>
</section>

<section class="mb-4">
  <h2 class="h6">Hébergeur</h2>
  <p class="mb-0">Renseigner ici le nom, l’adresse et les coordonnées de l’hébergeur.</p>
</section>

<section class="mb-4">
  <h2 class="h6">Données personnelles</h2>
  <p class="mb-0">Les données collectées sont utilisées uniquement pour la gestion des comptes et des covoiturages. Vous pouvez exercer vos droits (accès, rectification, suppression) en nous écrivant à <a href="mailto:contact@ecoride.com">contact@ecoride.com</a>.</p>
</section>

<section class="mb-4">
  <h2 class="h6">Propriété intellectuelle</h2>
  <p class="mb-0">Tout contenu (textes, logos, visuels) présent sur ce site est la propriété d’EcoRide, sauf mention contraire.</p>
</section>
