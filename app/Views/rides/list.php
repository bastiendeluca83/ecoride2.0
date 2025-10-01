<?php
/* app/Views/rides/list.php
   Vue MVC — Résultats de recherche de covoiturages.
   Objectif : afficher les trajets trouvés + un petit jeu de filtres côté GET.
*/

/* Helper d’échappement : je protège toutes les sorties HTML. */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* Générateur de badges pour les préférences conducteur.
   Je garde ça en closure pour rester local à la vue, simple et lisible.
   - $type : smoker/animals/music/chatty/ac
   - $raw  : valeur brute (0/1/2 ou null)
   - $icon : classe Font Awesome (ex: 'fa-smoking')
   Je mappe la valeur → libellé + une classe Bootstrap par défaut.
*/
$prefBadge = function(string $type, $raw, string $icon) {
    $v = (string)($raw ?? '0');

    $labels = [
        'smoker'  => ['0'=>'N/A',        '1'=>'Non',           '2'=>'Oui'],
        'animals' => ['0'=>'N/A',        '1'=>'Non',           '2'=>'Oui'],
        'music'   => ['0'=>'N/A',        '1'=>'Plutôt non',    '2'=>'Avec plaisir'],
        'chatty'  => ['0'=>'N/A',        '1'=>'Discret',       '2'=>'Bavard'],
        'ac'      => ['0'=>'N/A',        '1'=>'Oui',           '2'=>'Peu/éteinte'],
    ];
    $label = $labels[$type][$v] ?? 'N/A';

    /* Côté couleur : je reste minimaliste (N/A en gris, le reste en vert).
       Si je veux affiner par type plus tard, je pourrai étendre cette logique. */
    $cls = ($v === '0') ? 'bg-secondary' : 'bg-success';

    return '<span class="badge '.$cls.'"><i class="fas '.$icon.' me-1"></i>'.$label.'</span>';
};

/* Titre de page (utilisé par le layout si besoin) */
$title = 'Liste des covoiturages • EcoRide';

/* Partial d’affichage compact de la note moyenne.
   Le partial lit $avg et $count depuis le scope local de la vue, donc je veille à les définir avant l’include. */
$ratingInclude = __DIR__ . '/../partials/_rating_badge.php';
?>

<h1 class="h4 mb-4">Résultats de votre recherche</h1>

<?php if (empty($rides)): ?>
  <!-- État vide : je propose un lien direct pour relancer la recherche -->
  <div class="alert alert-info">
    Aucun covoiturage trouvé pour vos critères.
    <br>
    <a href="/rides" class="btn btn-outline-secondary mt-2">← Nouvelle recherche</a>
  </div>
<?php else: ?>
  <!-- Filtres rapides (client-side via GET).
       Je laisse la validation/logique de filtrage côté contrôleur/Model. -->
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
      <input type="number" step="0.1" id="min_note" name="min_note" class="form-control"
             value="<?= h($_GET['min_note'] ?? '') ?>">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-success w-100">Filtrer</button>
    </div>
  </form>

  <!-- Liste des trajets : cartes responsives Bootstrap -->
  <div class="row g-3">
    <?php foreach ($rides as $r): ?>
      <?php
        /* Je prépare les infos d’entête de carte : nom, avatar, badges de note. */
        $driverName = trim((string)($r['driver_display_name'] ?? 'Conducteur'));

        /* Avatar : si je reçois un chemin relatif, je force un slash devant.
           Sinon je tombe sur un avatar initials DiceBear pour un fallback propre. */
        $avatar = $r['driver_avatar'] ?? '';
        if ($avatar && $avatar[0] !== '/') { $avatar = '/'.$avatar; }
        $avatarUrl = $avatar ?: 'https://api.dicebear.com/9.x/initials/svg?seed='.urlencode($driverName);

        /* Détails de l’offre */
        $eco   = (int)($r['is_eco'] ?? 0) === 1;   // Badge "Éco" si alimenté par l’algorithme backend
        $seats = (int)($r['seats_left'] ?? 0);
        $price = (int)($r['price'] ?? 0);

        /* Données de notation : le partial _rating_badge.php consomme $avg et $count */
        $avg   = $r['rating_avg']   ?? null;
        $count = $r['rating_count'] ?? 0;
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <!-- En-tête conducteur : avatar + nom + (éventuellement) badge note -->
            <div class="d-flex align-items-center mb-2 justify-content-between">
              <div class="d-flex align-items-center">
                <img src="<?= h($avatarUrl) ?>" class="rounded-circle border me-2" width="48" height="48" alt="Avatar">
                <div class="fw-bold"><?= h($driverName) ?></div>
              </div>
              <?php if ($avg !== null && file_exists($ratingInclude)): ?>
                <div class="ms-2">
                  <?php /* Le partial affichera la moyenne et le nombre d’avis. */ ?>
                  <?php include $ratingInclude; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Itinéraire + horaires (je laisse le formatage date au serveur via date()) -->
            <p class="mb-1"><strong><?= h($r['from_city']) ?></strong> → <strong><?= h($r['to_city']) ?></strong></p>
            <p class="mb-1">
              <?= h(date('d/m/Y H\hi', strtotime($r['date_start']))) ?>
              <?php if (!empty($r['date_end'])): ?> - <?= h(date('H\hi', strtotime($r['date_end']))) ?><?php endif; ?>
            </p>

            <!-- Prix / places / éco -->
            <div class="d-flex align-items-center mb-2 gap-2">
              <span class="fw-bold"><i class="fas fa-coins text-warning me-1"></i><?= $price ?> crédits</span>
              <span class="badge <?= $seats > 0 ? 'text-bg-success' : 'text-bg-danger' ?>">
                <?= $seats ?> place(s)
              </span>
              <?php if ($eco): ?><span class="badge text-bg-success">Éco</span><?php endif; ?>
            </div>

            <!-- Préférences conducteur : je génère les badges à partir des valeurs 0/1/2 -->
            <div class="d-flex flex-wrap gap-1 mb-3">
              <?= $prefBadge('smoker',  $r['smoker']  ?? null, 'fa-smoking') ?>
              <?= $prefBadge('animals', $r['animals'] ?? null, 'fa-paw') ?>
              <?= $prefBadge('music',   $r['music']   ?? null, 'fa-music') ?>
              <?= $prefBadge('chatty',  $r['chatty']  ?? null, 'fa-comments') ?>
              <?= $prefBadge('ac',      $r['ac']      ?? null, 'fa-snowflake') ?>
            </div>

            <!-- CTA détail : je passe l’ID de trajet en GET (le contrôleur sécurise derrière). -->
            <a href="/rides/show?id=<?= (int)$r['id'] ?>" class="btn btn-outline-primary w-100">Détail</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
