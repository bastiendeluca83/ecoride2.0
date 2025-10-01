<?php
/**
 * app/Views/email/_helpers.php
 * ----------------------------
 * Helpers d’échappement utilisés dans les templates d’e-mails.
 * Objectif : avoir un point central pour éviter de répéter la même fonction
 * dans chaque template et garantir que tout ce qui est injecté dans le HTML
 * est correctement échappé (anti-XSS).
 *
 * NB :
 * - J’utilise ENT_QUOTES + UTF-8 systematiquement.
 * - Je fournis deux alias (e et esc) parce que selon les templates, j’ai parfois
 *   utilisé l’un ou l’autre. Comme ça, pas de "undefined function".
 * - J’encadre chaque définition par function_exists pour éviter les redeclarations
 *   si le fichier est inclus plusieurs fois.
 */

// Alias principal : e()
if (!function_exists('e')) {
  /**
   * Échappe une valeur pour une sortie HTML.
   * Usage : <?= e($variable) ?>
   */
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

// Alias secondaire : esc()
if (!function_exists('esc')) {
  /**
   * Même chose que e(), juste un alias (habitude sur d’autres projets).
   * Usage : <?= esc($variable) ?>
   */
  function esc($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}
