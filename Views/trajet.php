<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: /rides/show' . ($qs ? ('?' . $qs) : ''));
exit;

// public/trajet.php

// ---------- Connexion PDO ----------
$dsn  = getenv("DB_DSN") ?: "mysql:host=db;dbname=ecoride;charset=utf8mb4";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "root";

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

try {
    $pdo = new PDO($dsn, $user, $pass, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo "<div class='alert alert-danger'>Erreur DB : ".h($e->getMessage())."</div>";
    exit;
}

// ---------- Récup de l'id ----------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    echo "<h1>404</h1><p>Trajet introuvable.</p>";
    exit;
}

// ---------- Requête trajet ----------
$sql = "
SELECT
  r.id, r.from_city, r.to_city, r.date_start, r.date_end,
  r.price, r.seats_left, r.driver_id,
  COALESCE(r.is_electric_cached, CASE WHEN UPPER(v.energy) = 'ELECTRIC' THEN 1 ELSE 0 END) AS is_eco,
  u.pseudo, u.avatar_url,
  v.brand, v.model, v.color, v.energy
FROM rides r
JOIN users u    ON u.id = r.driver_id
LEFT JOIN vehicles v ON v.id = r.vehicle_id
WHERE r.id = :id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$ride = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ride) {
    http_response_code(404);
    echo "<h1>404</h1><p>Trajet introuvable.</p>";
    exit;
}

$eco = ((int)$ride['is_eco'] === 1);

// (Optionnel) Avis du conducteur si tu as une table reviews
$reviews = [];
$avgNote = null;
try {
    $q = $pdo->prepare("
        SELECT note, comment, created_at
        FROM reviews
        WHERE driver_id = :uid
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $q->execute([':uid' => $ride['driver_id']]);
    $reviews = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($reviews) {
        $avg = $pdo->prepare("SELECT ROUND(AVG(note),1) AS avgNote FROM reviews WHERE driver_id = :uid");
        $avg->execute([':uid' => $ride['driver_id']]);
        $avgNote = $avg->fetchColumn();
    }
} catch (Throwable $ignored) {
    // Si la table n'existe pas encore, on ignore.
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Trajet <?=h($ride['from_city'])?> → <?=h($ride['to_city'])?> • EcoRide</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<main class="container my-5">

  <a href="covoiturages.php" class="btn btn-outline-secondary mb-4">← Retour aux covoiturages</a>

  <div class="d-flex align-items-center gap-3 mb-3">
    <h1 class="mb-0"><?=h($ride['from_city'])?> → <?=h($ride['to_city'])?></h1>
    <?php if ($eco): ?><span class="badge text-bg-success">Éco</span><?php endif; ?>
  </div>

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <p class="mb-1"><strong>Départ : </strong><?= date("d/m/Y H\hi", strtotime($ride['date_start'])) ?></p>
          <?php if (!empty($ride['date_end'])): ?>
          <p class="mb-1"><strong>Arrivée : </strong><?= date("d/m/Y H\hi", strtotime($ride['date_end'])) ?></p>
          <?php endif; ?>

          <p class="mb-1"><strong>Prix : </strong><?= (int)$ride['price'] ?> crédits</p>
          <p class="mb-3">
            <span class="badge <?=((int)$ride['seats_left']>0?'text-bg-success':'text-bg-danger')?>">
              <?= (int)$ride['seats_left'] ?> place(s) dispo
            </span>
          </p>

          <h5>Véhicule</h5>
          <p class="mb-0">
            <?php if ($ride['brand'] || $ride['model']): ?>
              <?= h(trim(($ride['brand'] ?? '').' '.($ride['model'] ?? ''))) ?>
              <?php if ($ride['color']): ?> • Couleur : <?= h($ride['color']) ?><?php endif; ?>
              <?php if ($ride['energy']): ?> • Énergie : <?= h($ride['energy']) ?><?php endif; ?>
            <?php else: ?>
              Non renseigné
            <?php endif; ?>
          </p>
        </div>
      </div>

      <?php if ($reviews): ?>
        <div class="card shadow-sm mt-4">
          <div class="card-body">
            <h5 class="card-title mb-3">Avis du conducteur <?php if ($avgNote): ?><small class="text-muted">(moyenne : <?=$avgNote?>/5)</small><?php endif; ?></h5>
            <ul class="list-unstyled mb-0">
              <?php foreach ($reviews as $rv): ?>
              <li class="mb-2">
                <strong><?=$rv['note']?>/5</strong> — <?=h($rv['comment'])?>
                <div class="text-muted small"><?= date('d/m/Y', strtotime($rv['created_at'])) ?></div>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Chauffeur</h5>
          <div class="d-flex align-items-center gap-3">
            <img src="<?= h($ride['avatar_url'] ?: 'https://via.placeholder.com/64') ?>" alt="Avatar" width="64" height="64" class="rounded-circle border">
            <div>
              <div class="fw-bold"><?= h($ride['pseudo']) ?></div>
              <?php if ($avgNote): ?><div class="text-muted"><?=$avgNote?>/5</div><?php endif; ?>
            </div>
          </div>

          <!-- Bouton participer (tu brancheras plus tard la logique de crédits + login) -->
          <hr>
          <a href="participer.php?ride_id=<?= (int)$ride['id'] ?>" class="btn btn-success w-100"
             <?= ((int)$ride['seats_left']<=0 ? 'aria-disabled="true" tabindex="-1" onclick="return false;"' : '') ?>>
            Participer
          </a>
          <?php if ((int)$ride['seats_left']<=0): ?>
            <div class="small text-muted mt-2">Plus de places disponibles.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
