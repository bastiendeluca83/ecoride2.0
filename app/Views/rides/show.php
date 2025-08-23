<?php
// app/Views/rides/show.php
// Cette vue est injectée dans le layout via BaseController::render() / View::render().
// Variables attendues : $ride (array), $reviews (array), $avgNote (float|null)

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$title = 'Trajet ' . h($ride['from_city']) . ' → ' . h($ride['to_city']) . ' • EcoRide';
$eco   = (int)($ride['is_eco'] ?? 0) === 1;
$seats = (int)($ride['seats_left'] ?? 0);
$price = (int)($ride['price'] ?? 0);
?>

<a href="/rides" class="btn btn-outline-secondary mb-4">← Retour aux covoiturages</a>

<div class="d-flex align-items-center gap-3 mb-3">
  <h1 class="mb-0"><?= h($ride['from_city']) ?> → <?= h($ride['to_city']) ?></h1>
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

        <p class="mb-1"><strong>Prix : </strong><?= $price ?> crédits</p>
        <p class="mb-3">
          <span class="badge <?= ($seats > 0 ? 'text-bg-success' : 'text-bg-danger') ?>">
            <?= $seats ?> place(s) dispo
          </span>
        </p>

        <h5>Véhicule</h5>
        <p class="mb-0">
          <?php if (!empty($ride['brand']) || !empty($ride['model'])): ?>
            <?= h(trim(($ride['brand'] ?? '').' '.($ride['model'] ?? ''))) ?>
            <?php if (!empty($ride['color'])): ?> • Couleur : <?= h($ride['color']) ?><?php endif; ?>
            <?php if (!empty($ride['energy'])): ?> • Énergie : <?= h($ride['energy']) ?><?php endif; ?>
          <?php else: ?>
            Non renseigné
          <?php endif; ?>
        </p>
      </div>
    </div>

    <?php if (!empty($reviews)): ?>
      <div class="card shadow-sm mt-4">
        <div class="card-body">
          <h5 class="card-title mb-3">
            Avis du conducteur
            <?php if (!empty($avgNote)): ?>
              <small class="text-muted">(moyenne : <?= h($avgNote) ?>/5)</small>
            <?php endif; ?>
          </h5>
          <ul class="list-unstyled mb-0">
            <?php foreach ($reviews as $rv): ?>
              <li class="mb-2">
                <strong><?= (int)$rv['note'] ?>/5</strong> — <?= h($rv['comment'] ?? '') ?>
                <div class="text-muted small"><?= !empty($rv['created_at']) ? date('d/m/Y', strtotime($rv['created_at'])) : '' ?></div>
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
            <?php if (!empty($avgNote)): ?><div class="text-muted"><?= h($avgNote) ?>/5</div><?php endif; ?>
          </div>
        </div>

        <hr>
        <!-- Participation : POST vers /rides/book pour respecter ta route existante -->
        <form action="/rides/book" method="post" class="d-grid">
          <input type="hidden" name="ride_id" value="<?= (int)$ride['id'] ?>">
          <button class="btn btn-success w-100" type="submit" <?= $seats <= 0 ? 'disabled' : '' ?>>
            Participer
          </button>
        </form>
        <?php if ($seats <= 0): ?>
          <div class="small text-muted mt-2">Plus de places disponibles.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
