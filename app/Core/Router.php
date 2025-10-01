<?php
namespace App\Core;

/**
 * Router minimal maison
 * - Je gère des routes GET/POST.
 * - Je supporte les paramètres de chemin type {slug} → regex nommée (?P<slug>...).
 * - Je normalise les URLs en supprimant le / final (redirection 301).
 * - Je renvoie 404 si la route n’existe pas, 405 si la méthode HTTP n’est pas autorisée.
 * - J’injecte les paramètres dans la méthode du contrôleur par nom via Reflection.
 *
 * Exemples:
 *   $router->get('/rides', [RideController::class, 'list']);
 *   $router->get('/rides/{id}', [RideController::class, 'show']); // id récupéré depuis l’URL
 *   $router->map(['GET','POST'], '/search', [SearchController::class, 'index']);
 */
class Router
{
    /** 
     * Tableau des routes indexées par méthode HTTP.
     * - Chaque entrée: ['path'=>string, 'regex'=>string, 'action'=>[class, method]]
     * @var array<string,array<int,array{path:string,regex:string,action:array}>>
     */
    private array $routes = ['GET' => [], 'POST' => []];

    /** Raccourci pour déclarer une route GET */
    public function get(string $path, array $action): void { $this->add('GET', $path, $action); }

    /** Raccourci pour déclarer une route POST */
    public function post(string $path, array $action): void { $this->add('POST', $path, $action); }

    /**
     * Déclare la même route pour plusieurs méthodes.
     * Permet: $router->map(['GET','POST'], '/rides', [Ctrl::class,'list'])
     */
    public function map(array $methods, string $path, array $action): void {
        foreach ($methods as $m) {
            $m = strtoupper($m);
            if ($m === 'GET' || $m === 'POST') $this->add($m, $path, $action);
        }
    }

    /**
     * Enregistre une route dans la table interne.
     * - Je supprime le slash final pour la cohérence (sauf pour "/").
     * - Je remplace {param} par un groupe nommé regex (?P<param>...).
     */
    private function add(string $method, string $path, array $action): void
    {
        // normalisation: /chemin/ → /chemin
        if ($path !== '/' && substr($path, -1) === '/') $path = rtrim($path, '/');

        // paramètres de route: {name} → (?P<name>[A-Za-z0-9_-]+)
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#','(?P<$1>[A-Za-z0-9_-]+)',$path);

        $this->routes[$method][] = [
            'path'   => $path,
            'regex'  => '#^' . $regex . '$#',
            'action' => $action,
        ];
    }

    /**
     * Résout la requête courante et appelle le contrôleur ciblé.
     * - Redirection 301 si l’URL finit par un / (sauf la racine).
     * - Matching exact, puis matching regex (avec extraction des paramètres nommés).
     * - 405 si le chemin existe pour une autre méthode ; sinon 404.
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // redirection propre si trailing slash
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $target = rtrim($uri, '/');
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            if ($qs !== '') $target .= '?' . $qs;
            header('Location: ' . $target, true, 301); return;
        }

        // 1) tentative de match exact
        $action = null;
        foreach ($this->routes[$method] as $r) if ($r['path'] === $uri) { $action = $r; break; }

        // 2) sinon je tente le match regex (paramètres nommés)
        $params = [];
        if (!$action) {
            foreach ($this->routes[$method] as $r) {
                if (preg_match($r['regex'], $uri, $m)) {
                    $action = $r;
                    foreach ($m as $k=>$v) if (!is_int($k)) $params[$k]=$v; // je ne garde que les noms
                    break;
                }
            }
        }

        // 3) si rien trouvé: je vérifie s’il existe des routes pour le même chemin sur d’autres méthodes
        if (!$action) {
            $allowed = $this->allowedMethodsFor($uri);
            if (!empty($allowed)) { header('Allow: '.implode(', ',$allowed), true, 405); $this->httpError(405,'Méthode non autorisée.'); return; }
            $this->httpError(404,'Page non trouvée.'); return;
        }

        // 4) j’instancie le contrôleur ciblé et je vérifie la méthode
        [$class, $fn] = $action['action'];
        if (!class_exists($class))        { $this->httpError(500,"Contrôleur introuvable: $class"); return; }
        $controller = new $class();
        if (!method_exists($controller,$fn)){ $this->httpError(500,"Méthode introuvable: $fn"); return; }

        // 5) j’invoque en injectant les paramètres par nom de variable (Reflection)
        $this->invoke($controller, $fn, $params);
    }

    /**
     * Retourne la liste des méthodes HTTP autorisées pour un chemin donné.
     * - Utile pour construire l’en-tête Allow en 405.
     */
    private function allowedMethodsFor(string $uri): array {
        $allowed = [];
        foreach ($this->routes as $method => $list) foreach ($list as $r)
            if ($r['path'] === $uri || preg_match($r['regex'],$uri)) { $allowed[]=$method; break; }
        return array_values(array_unique($allowed));
    }

    /**
     * Appelle la méthode du contrôleur avec les paramètres de route injectés par nom.
     * - Je mappe chaque param attendu ($ref->getParameters()) avec $params.
     * - Si un param n’est pas fourni mais a une valeur par défaut, je l’utilise.
     */
    private function invoke(object $controller, string $fn, array $params): void
    {
        if (!$params) { $controller->$fn(); return; } // pas de params → j’appelle directement

        $ref = new \ReflectionMethod($controller, $fn);
        $args = [];
        foreach ($ref->getParameters() as $p) {
            $name = $p->getName();
            $args[] = array_key_exists($name,$params) ? $params[$name] : ($p->isDefaultValueAvailable() ? $p->getDefaultValue() : null);
        }
        $ref->invokeArgs($controller, $args);
    }

    /**
     * Petit helper pour renvoyer une page d’erreur simple.
     * - Je fixe le code HTTP et je sors un HTML minimaliste (suffisant pour debug).
     * - (Pour la prod, on pourra brancher ici un rendu de vue d’erreur dédiée.)
     */
    private function httpError(int $code, string $message): void {
        http_response_code($code);
        echo "<h1>$code</h1><p>$message</p>";
    }
}
