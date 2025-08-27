<?php
declare(strict_types=1);

/**
 * Bootstrap EcoRide (MVC)
 * - Définit les chemins
 * - Charge l'env et l'autoload (Composer si dispo, fallback PSR-4 App\)
 * - Paramètre le fuseau / erreurs
 * - Instancie le Router et charge config/routes.php
 * - Retourne l'instance $router au front controller
 */

require __DIR__ . '/Paths.php'; // définit APP_PATH, CONFIG_PATH, etc.

// ---------------------------------------------------------------------
// ENV
// ---------------------------------------------------------------------
if (is_file(CONFIG_PATH . '/env.php')) {
    /** @var array $ENV optionnel */
    $ENV = require CONFIG_PATH . '/env.php';

    // Injecte aussi dans $_ENV et variables de process (utile sous Apache + Docker)
    if (is_array($ENV)) {
        foreach ($ENV as $k => $v) {
            if (!is_string($k)) continue;
            $val = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_SLASHES);
            $_ENV[$k] = $val;
            putenv($k . '=' . $val);
        }
    }

    // Fuseau horaire depuis env sinon Europe/Paris
    $tz = isset($ENV['TIMEZONE']) && is_string($ENV['TIMEZONE']) ? $ENV['TIMEZONE'] : 'Europe/Paris';
    @date_default_timezone_set($tz);

    // Mode debug (affichage erreurs)
    $debug = !empty($ENV['DEBUG']);
    if ($debug) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    }
} else {
    // Valeurs par défaut si pas d'env.php
    @date_default_timezone_set('Europe/Paris');
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// ---------------------------------------------------------------------
// AUTOLOAD
// ---------------------------------------------------------------------
// 1) Composer si présent (préféré)
$composer = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
}

// 2) Fallback PSR-4 pour App\ si Composer n’est pas configuré là-dessus
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) return;
    $relative = str_replace('\\', '/', substr($class, $len));
    $file = APP_PATH . '/' . $relative . '.php';
    if (is_file($file)) require $file;
});

// ---------------------------------------------------------------------
// ROUTER
// ---------------------------------------------------------------------
use App\Core\Router;

if (!class_exists(Router::class)) {
    http_response_code(500);
    echo 'Classe Router introuvable (App\Core\Router).';
    exit;
}

// Instancie le routeur applicatif
$router = new Router();

// Charge les routes applicatives
$routesFile = CONFIG_PATH . '/routes.php';
if (!is_file($routesFile)) {
    http_response_code(500);
    echo 'Fichier routes introuvable : config/routes.php';
    exit;
}
require $routesFile;

// Retourne l’instance au front controller (public/index.php)
return $router;
// ex: public/index.php ou app/bootstrap.php
require_once __DIR__.'/../app/Config/cron.php';

