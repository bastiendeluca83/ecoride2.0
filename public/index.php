<?php
ini_set('session.save_path', '/tmp');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/../vendor/autoload.php';

define('ROOT', dirname(__DIR__));
define('VIEW_PATH', ROOT . '/src/Views');
define('INC_PATH',  ROOT . '/src/includes');

$routes = require ROOT . '/config/routes.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($path !== '/' && substr($path, -1) === '/') { $path = rtrim($path, '/'); }

// match route
$matched = null;
foreach ($routes as $r) {
  if ($r[0] === $method && $r[1] === $path) { $matched = $r; break; }
}

// home rendue ici
if ($matched && $matched[1] === '/') { $matched = null; }
if (!$matched && $path === '/') {
  $title = 'Accueil – EcoRide';
  include INC_PATH . '/header.php'; ?>
  <div class="container my-5">
    <section class="mb-5">
      <div class="row align-items-center g-4">
        <div class="col-lg-6">
          <h2 class="mb-3">Présentation d’EcoRide</h2>
          <p class="lead mb-0">EcoRide est la plateforme de covoiturage écoresponsable.</p>
        </div>
        <div class="col-lg-6">
          <img src="/assets/img/photo-home-page-1.jpg" class="img-fluid rounded" alt="EcoRide">
        </div>
      </div>
    </section>

    <section class="mb-4">
      <h3 class="h5">Rechercher un covoiturage</h3>
      <form action="/search" method="post" class="row g-3 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label">Départ</label><input name="from_city" class="form-control" required></div>
        <div class="col-12 col-md-4"><label class="form-label">Arrivée</label><input name="to_city" class="form-control" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Date</label><input type="date" name="date_start" class="form-control" required></div>
        <div class="col-12 col-md-1 d-grid"><button class="btn btn-success">Go</button></div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="eco_only" name="eco_only"><label class="form-check-label" for="eco_only">Uniquement véhicules électriques</label></div></div>
      </form>
    </section>

    <?php include ROOT . '/src/includes/trajets-phares.php'; ?>
  </div>
  <?php include INC_PATH . '/footer.php'; exit;
}

// dispatch
if (!$matched) { http_response_code(404); echo '<h1>404</h1>'; exit; }
[$class, $action] = $matched[2];
$controller = new $class();
$response = $controller->$action();
if (is_string($response)) echo $response;
