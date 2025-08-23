<?php
// app/Views/rides/list.php
// Injecté via BaseController::render()
// Variables attendues : $rides (array), éventuellement $filters (array)

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$title = 'Liste des covoiturages • EcoRide';
?>

<h1 class="h4 mb-4">Résultats de votre recherche</h1>

<?php if (empty($rides)): ?>
  <div class="alert alert-info">
    Aucun covoiturage trouvé pour vos critères.
    <br>
    <a href="/rides" class="btn btn-outline-secondary mt-2">← Nouvelle recherche</a>
  </div>
<?php else: ?>
  <!-- Filtres -->
  <form action="/rides" method="get" class="row g-3 mb-4">
    <div class="col-md-3">
      <label for="price_max" class="form-label">Prix max</label>
      <input type="number" id="price_max" name="price_max" class="form-control"
             value="<?= h($_GET['price_max'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label for="duration_max" class="form-label">Durée max (heures)</label>
      <input type="number" id="duration_max" name="duration_max" class="form-control"
             value="<?= h($_GET['duration_max'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label for="min_note" class="form-label">Note minimum</label>
      <input type="number" id="min_note" name="min_note" step="0.1" class="form-control"
             value="<?= h($_GET['min_note'] ?? '') ?>">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-success w-100">Filtrer</button>
    </div>
  </form>

  <!-- Liste des trajets -->
  <div class="row g-3">
    <?php foreach ($rides as $r): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center mb-3">
              <img src="<?= h($r['avatar_url'] ?? 'https://via.placeholder.com/48') ?>"
                   class="rounded-circle border me-2" width="48" height="48"
                   alt="Avatar conducteur">
              <div>
                <div class="fw-bold"><?= h($r['pseudo'] ?? 'Conducteur') ?></div>
                <?php if (!empty($r['note'])): ?>
                  <div class="small text-muted"><?= h($r['note']) ?>/5</div>
                <?php endif; ?>
              </div>
            </div>

            <p class="mb-1"><strong><?= h($r['from_city']) ?></strong> → <strong><?= h($r['to_city']) ?></strong></p>
            <p class="mb-1">
              <?= date('d/m/Y H\hi', strtotime($r['date_start'])) ?>
              <?php if (!empty($r['date_end'])): ?> - <?= date('H\hi', strtotime($r['date_end'])) ?><?php endif; ?>
            </p>
            <p class="mb-1"><strong><?= (int)$r['price'] ?> crédits</strong></p>
            <p class="mb-2">
              <span class="badge <?= ($r['seats_left'] > 0 ? 'text-bg-success' : 'text-bg-danger') ?>">
                <?= (int)$r['seats_left'] ?> place(s)
              </span>
              <?php if (!empty($r['is_eco'])): ?>
                <span class="badge text-bg-success">Éco</span>
              <?php endif; ?>
            </p>

            <a href="/rides/show?id=<?= (int)$r['id'] ?>" class="btn btn-outline-primary w-100">
              Détail
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
