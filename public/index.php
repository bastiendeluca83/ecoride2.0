<?php
ini_set('session.save_path', '/tmp');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Point d’entrée unique : on charge le bootstrap MVC puis on dispatch
$router = require dirname(__DIR__) . '/app/Core/Bootstrap.php';
$router->dispatch();
