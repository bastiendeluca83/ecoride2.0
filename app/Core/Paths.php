<?php
// app/Core/Paths.php

// Racine du projet (…/ton-projet)
define('BASE_PATH', dirname(__DIR__, 2));    // <-- 2 niveaux depuis app/Core

// Dossiers clés
define('APP_PATH',    BASE_PATH . '/app');
define('VIEW_PATH',   APP_PATH  . '/Views');
define('CONFIG_PATH', BASE_PATH . '/config');
define('DB_PATH',     BASE_PATH . '/db');
define('PUBLIC_PATH', BASE_PATH . '/public');

// URL de base (adapter si sous-dossier)
if (!defined('BASE_URL')) {
  define('BASE_URL', '/');
}
