<?php
/** @var string $title */
/** @var array $kpis */
/** @var array $ridesPerDay */
/** @var array $creditsPerDay */
/** @var array $users */
/** @var string $csrf */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0"><?= e($title ?? 'Espace Administrateur') ?></h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary" href="/employee/dashboard">Espace employé</a>
      <a class="btn btn-outline-secondary" href="/user/dashboard">Espace administrateur</a>
      <a class="btn btn-outline-secondary" href="/logout">Déconnexion</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Utilisateurs actifs</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['users_active'] ?? 0) ?></strong></p>
    </div></div></div>
    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Trajets à venir</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['rides_upcoming'] ?? 0) ?></strong></p>
    </div></div></div>
    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Réservations</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['bookings_total'] ?? 0) ?></strong></p>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Places restantes (total)</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['seats_left_sum'] ?? 0) ?></strong></p>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Crédits plateforme (total)</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['platform_total'] ?? 0) ?></strong></p>
    </div></div></div>
  </div>

  <hr class="my-4">

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Covoiturages par jour (14j)</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>Jour</th><th>Nombre</th></tr></thead>
            <tbody>
            <?php foreach (($ridesPerDay ?? []) as $r): ?>
              <tr><td><?= e($r['day']) ?></td><td><?= e($r['rides_count']) ?></td></tr>
            <?php endforeach; if (empty($ridesPerDay)): ?>
              <tr><td colspan="2" class="text-muted">Aucune donnée.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Crédits gagnés / jour (14j)</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>Jour</th><th>Crédits</th></tr></thead>
            <tbody>
            <?php foreach (($creditsPerDay ?? []) as $r): ?>
              <tr><td><?= e($r['day']) ?></td><td><?= e($r['credits']) ?></td></tr>
            <?php endforeach; if (empty($creditsPerDay)): ?>
              <tr><td colspan="2" class="text-muted">Aucune donnée.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>

  <!-- ======================= -->
  <!-- NOUVEAU : Historique    -->
  <!-- ======================= -->
  <hr class="my-4">
  <section class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0">Historique des crédits (plateforme)</h5>
        <div class="d-flex gap-2 align-items-center">
          <label for="histDays" class="form-label mb-0 me-2 small text-muted">Plage</label>
          <select id="histDays" class="form-select form-select-sm">
            <option value="14">14 jours</option>
            <option value="30">30 jours</option>
            <option value="90" selected>90 jours</option>
            <option value="180">180 jours</option>
          </select>
        </div>
      </div>
      <canvas id="creditsHistoryChart" height="120"></canvas>
      <small class="text-muted d-block mt-2">
        Données en temps réel, calculées depuis vos réservations confirmées.
      </small>
    </div>
  </section>

  <hr class="my-4">

  <div class="row g-3">
    <!-- Création d'un employé -->
    <div class="col-lg-4">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Créer un compte employé</h5>
        <form method="post" action="/admin/employees/create" class="vstack gap-2">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input class="form-control" name="nom" placeholder="Nom (optionnel)">
          <input class="form-control" type="email" name="email" placeholder="Email" required>
          <input class="form-control" type="password" name="password" placeholder="Mot de passe (min 8)" minlength="8" required>
          <button class="btn btn-primary">Créer</button>
        </form>
        <small class="text-muted d-block mt-2">Rôle assigné automatiquement : <code>EMPLOYEE</code>.</small>
      </div></div>
    </div>

    <!-- Gestion comptes -->
    <div class="col-lg-8">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Gérer les comptes</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>#</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Rôle</th><th>Crédits</th><th>Statut</th><th>Action</th></tr>
            </thead>
            <tbody>
              <?php foreach (($users ?? []) as $u): ?>
              <tr>
                <td><?= e($u['id']) ?></td>
                <td><?= e($u['nom'] ?? '') ?></td>
                <td><?= e($u['prenom'] ?? '') ?></td>
                <td><?= e($u['email'] ?? '') ?></td>
                <td><?= e($u['role'] ?? '') ?></td>
                <td><?= e($u['credits'] ?? 0) ?></td>
                <td>
                  <?php if (!empty($u['is_suspended'])): ?>
                    <span class="badge bg-danger">Suspendu</span>
                  <?php else: ?>
                    <span class="badge bg-success">Actif</span>
                  <?php endif; ?>
                </td>
                <td class="d-flex gap-1">
                  <?php if (!empty($u['is_suspended'])): ?>
                    <form method="post" action="/admin/users/unsuspend">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                      <button class="btn btn-sm btn-success">Réactiver</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="/admin/users/suspend">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger">Suspendre</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; if (empty($users)): ?>
                <tr><td colspan="8" class="text-muted">Aucun utilisateur.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>
</div>

<!-- Chart.js en CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const $sel    = document.getElementById('histDays');
  const $canvas = document.getElementById('creditsHistoryChart');
  let chart;

  // Formateur FR: 'YYYY-MM-DD' -> 'DD/MM/YYYY'
  const fmtFR = (ymd) => {
    if (!ymd) return '';
    const [y, m, d] = String(ymd).split('-');
    return `${d}/${m}/${y}`;
  };

  async function loadData(days){
    const res  = await fetch(`/admin/api/credits-history?days=${encodeURIComponent(days)}`, { credentials: 'same-origin' });
    const json = await res.json();
    // labels & série
    const labels  = json.data.map(r => r.day);
    const credits = json.data.map(r => r.credits);

    // Tooltips incluent les ride_ids
    const rideIds = json.data.map(r => r.ride_ids || '');

    if (chart) chart.destroy();
    chart = new Chart($canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Crédits gagnés / jour',
          data: credits,
          tension: 0.3,
          pointRadius: 2,
          fill: true
        }]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: {
            title: { display: true, text: 'Jour' },
            ticks: {
              callback: function(value) {
                const raw = this.getLabelForValue(value); // récupère 'YYYY-MM-DD'
                return fmtFR(raw);
              }
            }
          },
          y: { title: { display: true, text: 'Crédits' }, beginAtZero: true, ticks: { precision: 0 } }
        },
        plugins: {
          tooltip: {
            callbacks: {
              // Titre du tooltip au format FR
              title: (items) => items.length ? fmtFR(items[0].label) : '',
              afterBody: (items) => {
                const i = items[0].dataIndex;
                const ids = rideIds[i];
                return ids ? `ride_id: ${ids}` : 'Aucun trajet ce jour';
              }
            }
          },
          legend: { display: false }
        }
      }
    });
  }

  $sel.addEventListener('change', () => loadData($sel.value));
  loadData($sel.value);
});
</script>
