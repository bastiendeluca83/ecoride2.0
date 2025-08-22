<?php
header('Location: /rides');
include __DIR__ . '/includes/header.php';
// covoiturages.php

// ---------- Connexion PDO ----------
$dsn  = getenv("DB_DSN") ?: "mysql:host=db;dbname=ecoride;charset=utf8mb4";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "root";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo "<div class='alert alert-danger'>Erreur DB : " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// ---------- Lecture des filtres ----------
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to   = isset($_GET['to'])   ? trim($_GET['to'])   : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : ''; // format jj/mm/aaaa accepté

// Normalisation date (jj/mm/aaaa -> aaaa-mm-jj)
$normalizedDate = null;
if ($date !== '') {
    $parts = preg_split('#[/\-]#', $date);
    if (count($parts) === 3) {
        // On tolère jj/mm/aaaa ou aaaa-mm-jj
        if (strlen($parts[0]) === 2) {
            [$jj,$mm,$aaaa] = $parts;
        } else {
            [$aaaa,$mm,$jj] = $parts;
        }
        if (checkdate((int)$mm,(int)$jj,(int)$aaaa)) {
            $normalizedDate = sprintf('%04d-%02d-%02d', $aaaa, $mm, $jj);
        }
    }
}

// ---------- Construction requête ----------
$sql = "
SELECT
  r.id,
  r.from_city, r.to_city,
  r.date_start, r.date_end,
  r.price, r.seats_left,
  COALESCE(r.is_electric_cached, CASE WHEN UPPER(v.energy) = 'ELECTRIC' THEN 1 ELSE 0 END) AS is_eco,
  u.pseudo,
  -- si tu as une table d'avis, tu pourras ajouter AVG(...) ici
  v.brand, v.model, v.energy
FROM rides r
JOIN users u    ON u.id = r.driver_id
LEFT JOIN vehicles v ON v.id = r.vehicle_id
WHERE r.seats_left > 0
";
$params = [];

if ($from !== '') {
    $sql .= " AND r.from_city LIKE :from";
    $params[':from'] = "%$from%";
}
if ($to !== '') {
    $sql .= " AND r.to_city LIKE :to";
    $params[':to'] = "%$to%";
}
if ($normalizedDate !== null) {
    // on filtre sur la date de départ (jour)
    $sql .= " AND DATE(r.date_start) = :d";
    $params[':d'] = $normalizedDate;
}

$sql .= " ORDER BY r.date_start ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Petite fonction util ----------
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Tous les covoiturages • EcoRide</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap (si déjà inclus ailleurs, tu peux enlever) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <main class="container my-5">

    <h1 class="mb-4">Tous les covoiturages</h1>

    <!-- Formulaire de recherche (US 3) -->
    <form class="row g-3 mb-4" method="get" action="/covoiturages.php">
      <div class="col-12 col-md-4">
        <label class="form-label">Départ</label>
        <input type="text" name="from" class="form-control" placeholder="Ville de départ" value="<?=h($from)?>">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Arrivée</label>
        <input type="text" name="to" class="form-control" placeholder="Ville d'arrivée" value="<?=h($to)?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Date</label>
        <input type="text" name="date" class="form-control" placeholder="jj/mm/aaaa" value="<?=h($date)?>">
      </div>
      <div class="col-12 col-md-1 d-flex align-items-end">
        <button class="btn btn-success w-100">Rechercher</button>
      </div>
      <div class="col-12">
        <small class="text-muted">Astuce : tu peux saisir seulement la ville de départ, ou départ + arrivée, ou ajouter la date.</small>
      </div>
    </form>

    <!-- Résultats -->
    <?php if (!$rides): ?>
      <div class="alert alert-info">
        Aucun covoiturage ne correspond à ta recherche.
        <div class="small text-muted">Essaye avec une autre date ou d'autres villes.</div>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($rides as $r): ?>
          <?php
            $eco = ((int)$r['is_eco'] === 1);
          ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h5 class="card-title mb-0"><?=h($r['from_city'])?> → <?=h($r['to_city'])?></h5>
                  <?php if ($eco): ?><span class="badge text-bg-success">Éco</span><?php endif; ?>
                </div>

                <p class="mb-1"><small>
                  Chauffeur : <strong><?=h($r['pseudo'])?></strong>
                  <?php if ($r["brand"] && $r["model"]): ?>
                    • Véhicule : <?=h($r["brand"]." ".$r["model"])?>
                  <?php endif; ?>
                </small></p>

                <p class="mb-1"><small>
                  Départ : <?=date("d/m H\hi", strtotime($r["date_start"]))?>
                  <?php if ($r["date_end"]): ?> • Arrivée : <?=date("d/m H\hi", strtotime($r["date_end"]))?><?php endif; ?>
                </small></p>

                <p class="mb-2"><strong>Prix : <?= (int)$r["price"] ?> crédits</strong></p>

                <div class="mt-auto d-flex justify-content-between align-items-center">
                  <span class="badge <?=((int)$r["seats_left"]>0?'text-bg-success':'text-bg-danger')?>"><?= (int)$r["seats_left"] ?> place(s) dispo</span>
                  <a class="btn btn-success btn-sm" href="/trajet.php?id=<?= (int)$r["id"] ?>">Détail</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="mt-5">
      <a class="btn btn-outline-secondary" href="/">← Retour à l’accueil</a>
    </div>
  </main>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <!-- Bootstrap JS (optionnel) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
