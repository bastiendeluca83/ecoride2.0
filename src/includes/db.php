<?php
// includes/db.php
require_once __DIR__ . '/../config/app.php'; // ton tableau $config

function pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = $GLOBALS['config']['db'];
        $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
