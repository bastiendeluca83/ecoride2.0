<?php
declare(strict_types=1);
// TEMP: diagnostics
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);
ini_set('log_errors','1');
ini_set('error_log','/tmp/php-error.log');




// Session (répertoire temp pour éviter les permissions en conteneur)
ini_set('session.save_path', '/tmp');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// (Optionnel) fuseau par défaut
date_default_timezone_set('Europe/Paris');

// Charge le bootstrap qui instancie et configure le routeur
$bootstrap = dirname(__DIR__) . '/app/Core/Bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    echo 'Bootstrap introuvable : app/Core/Bootstrap.php';
    exit;
}

/** @var object $router */
$router = require $bootstrap;

// Dispatch de la requête courante
if (!method_exists($router, 'dispatch')) {
    http_response_code(500);
    echo 'Le routeur ne possède pas de méthode dispatch().';
    exit;
}

$router->dispatch();
