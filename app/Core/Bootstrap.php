<?php
declare(strict_types=1);

/**
 * Bootstrap EcoRide (MVC)
 * - Je définis les chemins
 * - Je charge l'env et l'autoload (Composer si dispo, sinon fallback PSR-4 pour App\)
 * - Je paramètre le fuseau horaire et l'affichage des erreurs
 * - J'instancie le Router et je charge config/routes.php
 * - Je retourne l'instance $router au front controller
 */

require __DIR__ . '/Paths.php'; // je définis APP_PATH, CONFIG_PATH, etc. via un fichier central

// ---------------------------------------------------------------------
// ENV
// ---------------------------------------------------------------------
if (is_file(CONFIG_PATH . '/env.php')) {
    /** @var array $ENV optionnel */
    $ENV = require CONFIG_PATH . '/env.php'; // je charge mes variables d'environnement applicatives

    // J'injecte aussi ces variables dans $_ENV et dans les variables de process
    // → utile en local, Apache, Docker, etc.
    if (is_array($ENV)) {
        foreach ($ENV as $k => $v) {
            if (!is_string($k)) continue;
            $val = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_SLASHES);
            $_ENV[$k] = $val;
            putenv($k . '=' . $val);
        }
    }

    // Fuseau horaire : je prends TIMEZONE si présent, sinon Europe/Paris
    $tz = isset($ENV['TIMEZONE']) && is_string($ENV['TIMEZONE']) ? $ENV['TIMEZONE'] : 'Europe/Paris';
    @date_default_timezone_set($tz);

    // Mode debug: si DEBUG truthy → j'affiche tout (pratique en dev)
    $debug = !empty($ENV['DEBUG']);
    if ($debug) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    } else {
        // En prod, je masque les notices et deprecated pour éviter de polluer la sortie
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    }
} else {
    // Si pas d'env.php, je mets des valeurs par défaut "safe"
    @date_default_timezone_set('Europe/Paris');
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// ---------------------------------------------------------------------
// AUTOLOAD
// ---------------------------------------------------------------------
// 1) Composer si présent (préféré car gère toutes les dépendances et autoloads)
$composer = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
}

// 2) Fallback PSR-4 pour l'espace de noms App\ si Composer n’est pas configuré pour lui
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) return; // je ne traite que les classes App\*
    $relative = str_replace('\\', '/', substr($class, $len));
    $file = APP_PATH . '/' . $relative . '.php';
    if (is_file($file)) require $file; // je charge le fichier si je le trouve
});

// ---------------------------------------------------------------------
// ROUTER
// ---------------------------------------------------------------------
use App\Core\Router;

if (!class_exists(Router::class)) {
    // Si le Router n'est pas trouvable, je renvoie un 500 explicite
    http_response_code(500);
    echo 'Classe Router introuvable (App\Core\Router).';
    exit;
}

// J'instancie le routeur applicatif
$router = new Router();

// Je charge les routes applicatives depuis config/routes.php
$routesFile = CONFIG_PATH . '/routes.php';
if (!is_file($routesFile)) {
    http_response_code(500);
    echo 'Fichier routes introuvable : config/routes.php';
    exit;
}
require $routesFile;

// Je retourne l’instance au front controller (public/index.php)
return $router;

// NOTE: tout code après ce return ne sera jamais exécuté.
// La ligne ci-dessous est inatteignable telle quelle.
// Si besoin de charger "cron.php" ici, il faut le faire AVANT le return.
/*
require_once __DIR__.'/../app/Config/cron.php';
*/
