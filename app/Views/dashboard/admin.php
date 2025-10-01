<?php
/** 
 * app/Views/admin/dashboard.php (vue)
 * -----------------------------------
 * Vue du tableau de bord administrateur.
 * Elle est rendue par le layout global via BaseController::render().
 *
 * Variables attendues (injectées par le contrôleur) :
 * - string $title            : titre de la page
 * - array  $kpis             : chiffres clés (utilisateurs, trajets, crédits, etc.)
 * - array  $ridesPerDay      : séries "trajets par jour" (14 derniers jours)
 * - array  $creditsPerDay    : séries "crédits par jour" (14 derniers jours)
 * - array  $users            : liste d'utilisateurs à gérer (id, nom, email, rôle, crédits, statut)
 * - string $csrf             : token CSRF unique (généré côté contrôleur)
 *
 * Remarque MVC :
 * - Ici je ne fais **que** de l'affichage (pas de requête DB). Tout vient du contrôleur.
 */

/** Helper d’échappement (XSS) */
if (!function_exists('e')) { 
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } 
}
?>
<div class="container my-4">
  <!-- En-tête : titre + raccourcis -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0"><?= e($title ?? 'Espace Administrateur') ?></h1>
    <div class="btn-group">
      <!-- Raccourcis utiles : employé, admin, logout
           NB: le 2e lien "Espace administrateur" pointe vers /user/dashboard.
           Je laisse tel quel (peut-être voulu pour basculer sur l’espace user),
           mais à valider fonctionnellement. -->
      <a class="btn btn-outline-secondary" href="/employee/dashboard">Espace employé</a>
      <a class="btn btn-outline-secondary" href="/user/dashboard">Espace administrateur</a>
      <a class="btn btn-outline-secondary" href="/logout">Déconnexion</a>
    </div>
  </div>

  <!-- KPI rapides (cartes) -->
  <div class="row g-3">
    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Utilisateurs actifs</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['users_active'] ?? 0) ?></strong></p>
    </div></div></div>

    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Trajets disponibles (à venir)</h6>
      <p class="display-6 mb-0">
        <strong><?= (int)($kpis['rides_upcoming_available'] ?? $kpis['trajets_disponibles'] ?? 0) ?></strong>
      </p>
    </div></div></div>

    <div class="col-md-2"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Réservations (à venir)</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['bookings_upcoming'] ?? 0) ?></strong></p>
    </div></div></div>

    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Places restantes (à venir)</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['seats_left'] ?? $kpis['seats_left_upcoming'] ?? 0) ?></strong></p>
    </div></div></div>

    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
      <h6 class="text-muted mb-1">Crédits plateforme (total)</h6>
      <p class="display-6 mb-0"><strong><?= (int)($kpis['platform_credits'] ?? $kpis['platform_total'] ?? 0) ?></strong></p>
    </div></div></div>
  </div>

  <hr class="my-4">

  <!-- Tableaux récap 14 jours : trajets / crédits -->
  <div class="row g-3">
    <!-- Covoiturages par jour -->
    <div class="col-md-6">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Covoiturages par jour (14j)</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>Jour</th><th>Nombre</th></tr></thead>
            <tbody>
            <?php foreach (($ridesPerDay ?? []) as $r): ?>
              <tr>
                <td><?= e($r['day'] ?? $r['jour'] ?? '') ?></td>
                <td><?= e($r['rides_count'] ?? $r['nombre'] ?? $r['n'] ?? 0) ?></td>
              </tr>
            <?php endforeach; if (empty($ridesPerDay)): ?>
              <tr><td colspan="2" class="text-muted">Aucune donnée.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>

    <!-- Crédits par jour -->
    <div class="col-md-6">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Crédits gagnés / jour (14j)</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>Jour</th><th>Crédits</th></tr></thead>
            <tbody>
            <?php foreach (($creditsPerDay ?? []) as $r): ?>
              <tr><td><?= e($r['day'] ?? $r['jour'] ?? '') ?></td><td><?= e($r['credits'] ?? 0) ?></td></tr>
            <?php endforeach; if (empty($creditsPerDay)): ?>
              <tr><td colspan="2" class="text-muted">Aucune donnée.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>

  <!-- Graphique interactif (Chart.js) : historique des crédits plateforme -->
  <hr class="my-4">
  <section class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0">Historique des crédits (plateforme)</h5>
        <!-- Sélecteur de plage : j'appelle l'API côté admin pour recharger la série -->
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
    <!-- Formulaire : création d'un employé -->
    <div class="col-lg-4">
      <div class="card shadow-sm"><div class="card-body">
        <h5 class="card-title">Créer un compte employé</h5>
        <!-- POST sécurisé par CSRF (le token vient du contrôleur) -->
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

    <!-- Gestion des comptes (liste + actions suspendre/réactiver) -->
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
                    <!-- Réactivation : POST + CSRF -->
                    <form method="post" action="/admin/users/unsuspend">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                      <button class="btn btn-sm btn-success">Réactiver</button>
                    </form>
                  <?php else: ?>
                    <!-- Suspension : POST + CSRF -->
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

<!-- Dépendance Chart.js (CDN). Je charge en 'defer' et j'initialise une fois le DOM prêt. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const $sel    = document.getElementById('histDays');
  const $canvas = document.getElementById('creditsHistoryChart');
  let chart;

  /* Petit formateur FR : 'YYYY-MM-DD' -> 'DD/MM/YYYY' (affichage axes/tooltip) */
  const fmtFR = (ymd) => {
    if (!ymd) return '';
    const [y, m, d] = String(ymd).split('-');
    return `${d}/${m}/${y}`;
  };

  /* Récupère la série depuis l’API admin (JSON) et (re)dessine le graphique */
  async function loadData(days){
    const res  = await fetch(`/admin/api/credits-history?days=${encodeURIComponent(days)}`, { credentials: 'same-origin' });
    const json = await res.json();

    // labels = jours ; credits = série ; rideIds = info complémentaire pour tooltip
    const labels  = json.data.map(r => r.day || r.jour);
    const credits = json.data.map(r => r.credits);
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
                const raw = this.getLabelForValue(value); // ex: '2025-09-28'
                return fmtFR(raw);
              }
            }
          },
          y: { 
            title: { display: true, text: 'Crédits' }, 
            beginAtZero: true, 
            ticks: { precision: 0 } 
          }
        },
        plugins: {
          tooltip: {
            callbacks: {
              title: (items) => items.length ? fmtFR(items[0].label) : '',
              afterBody: (items) => {
                // J’affiche les ride_ids dans le tooltip pour traçabilité
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

  // Rafraîchissement à la volée quand je change la plage
  $sel.addEventListener('change', () => loadData($sel.value));
  // Chargement initial (valeur par défaut sélectionnée)
  loadData($sel.value);
});
</script>
