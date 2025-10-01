<?php
// app/Core/Paths.php

/**
 * Fichier de chemins (bootstrap)
 * - Je centralise ici tous les chemins importants du projet pour éviter les hardcodes.
 * - BASE_PATH pointe sur la racine du projet.
 * - Les autres chemins sont dérivés de BASE_PATH pour garder le code portable.
 */

// Racine du projet (…/ton-projet)
// Je pars de app/Core, donc je remonte de 2 niveaux pour retomber sur la racine.
define('BASE_PATH', dirname(__DIR__, 2));    // <-- 2 niveaux depuis app/Core

// Dossiers clés (je reste simple et lisible)
define('APP_PATH',    BASE_PATH . '/app');    // code applicatif (Controllers, Models, Views, etc.)
define('VIEW_PATH',   APP_PATH  . '/Views');  // vues MVC
define('CONFIG_PATH', BASE_PATH . '/config'); // configs (routes, env, etc.)
define('DB_PATH',     BASE_PATH . '/db');     // scripts SQL/migrations si besoin
define('PUBLIC_PATH', BASE_PATH . '/public'); // front controller (index.php), assets publics

// URL de base (utile pour générer des liens absolus propres)
//  Si l'appli est dans un sous-dossier (ex: /ecoride/), je remplace '/' par '/ecoride/'.
if (!defined('BASE_URL')) {
  define('BASE_URL', '/');
  // Exemple en sous-dossier:
  // define('BASE_URL', '/ecoride/');
}
