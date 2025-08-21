<?php
$dsn  = getenv('DB_DSN') ?: 'mysql:host=db;dbname=ecoride;charset=utf8mb4';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

try { $pdo = new PDO($dsn, $user, $pass, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]); }
catch (Throwable $e) { echo '<div class="alert alert-danger">Erreur connexion DB : ' . htmlspecialchars($e->getMessage()) . '</div>'; return; }

$sql = "
SELECT r.id, r.from_city, r.to_city, r.date_start, r.price, r.is_electric_cached, u.nom
FROM rides r
JOIN users u ON u.id = r.driver_id
ORDER BY r.created_at DESC
LIMIT 3";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="my-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Trajets phares</h3>
    <a href="/rides" class="btn btn-outline-success btn-sm">Voir tous les covoiturages</a>
  </div>
  <?php if ($rows): ?>
    <div class="list-group">
      <?php foreach ($rows as $r): $id = (int)$r['id']; ?>
        <a class="list-group-item list-group-item-action" href="/rides/show?id=<?= $id ?>">
          <strong><?= htmlspecialchars($r['from_city']) ?> → <?= htmlspecialchars($r['to_city']) ?></strong>
          <span class="text-muted"> — <?= htmlspecialchars($r['date_start']) ?></span>
          <span class="ms-2"><?= (int)$r['price'] ?> cr.</span>
          <?php if ($r['is_electric_cached']): ?><span class="ms-2">⚡</span><?php endif; ?>
          <span class="ms-2 text-muted">par <?= htmlspecialchars($r['nom']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?><p class="text-muted mb-0">Aucun trajet pour le moment.</p><?php endif; ?>
</section>
