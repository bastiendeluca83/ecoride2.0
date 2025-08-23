<?php
namespace App\Core;

class Router
{
    /** @var array<string,array<int,array{path:string,regex:string,action:array}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array $action): void { $this->add('GET', $path, $action); }
    public function post(string $path, array $action): void { $this->add('POST', $path, $action); }

    /** Permet: $router->map(['GET','POST'], '/rides', [Ctrl::class,'list']) */
    public function map(array $methods, string $path, array $action): void {
        foreach ($methods as $m) {
            $m = strtoupper($m);
            if ($m === 'GET' || $m === 'POST') $this->add($m, $path, $action);
        }
    }

    private function add(string $method, string $path, array $action): void
    {
        if ($path !== '/' && substr($path, -1) === '/') $path = rtrim($path, '/');

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#','(?P<$1>[A-Za-z0-9_-]+)',$path);

        $this->routes[$method][] = [
            'path'   => $path,
            'regex'  => '#^' . $regex . '$#',
            'action' => $action,
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if ($uri !== '/' && substr($uri, -1) === '/') {
            $target = rtrim($uri, '/');
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            if ($qs !== '') $target .= '?' . $qs;
            header('Location: ' . $target, true, 301); return;
        }

        $action = null;
        foreach ($this->routes[$method] as $r) if ($r['path'] === $uri) { $action = $r; break; }

        $params = [];
        if (!$action) {
            foreach ($this->routes[$method] as $r) {
                if (preg_match($r['regex'], $uri, $m)) {
                    $action = $r;
                    foreach ($m as $k=>$v) if (!is_int($k)) $params[$k]=$v;
                    break;
                }
            }
        }

        if (!$action) {
            $allowed = $this->allowedMethodsFor($uri);
            if (!empty($allowed)) { header('Allow: '.implode(', ',$allowed), true, 405); $this->httpError(405,'Méthode non autorisée.'); return; }
            $this->httpError(404,'Page non trouvée.'); return;
        }

        [$class, $fn] = $action['action'];
        if (!class_exists($class))        { $this->httpError(500,"Contrôleur introuvable: $class"); return; }
        $controller = new $class();
        if (!method_exists($controller,$fn)){ $this->httpError(500,"Méthode introuvable: $fn"); return; }

        $this->invoke($controller, $fn, $params);
    }

    private function allowedMethodsFor(string $uri): array {
        $allowed = [];
        foreach ($this->routes as $method => $list) foreach ($list as $r)
            if ($r['path'] === $uri || preg_match($r['regex'],$uri)) { $allowed[]=$method; break; }
        return array_values(array_unique($allowed));
    }

    private function invoke(object $controller, string $fn, array $params): void
    {
        if (!$params) { $controller->$fn(); return; }
        $ref = new \ReflectionMethod($controller, $fn);
        $args = [];
        foreach ($ref->getParameters() as $p) {
            $name = $p->getName();
            $args[] = array_key_exists($name,$params) ? $params[$name] : ($p->isDefaultValueAvailable() ? $p->getDefaultValue() : null);
        }
        $ref->invokeArgs($controller, $args);
    }

    private function httpError(int $code, string $message): void {
        http_response_code($code);
        echo "<h1>$code</h1><p>$message</p>";
    }
}
