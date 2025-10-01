<?php
/**
 * Vue MVC — Page "Mes avis" (uniquement avis approuvés)
 * Contexte : injectée depuis mon contrôleur (ex : GeneralController::ratings).
 * Objectif : afficher la note moyenne, la distribution par étoiles et la liste paginée/brute des avis.
 *
 * Données attendues :
 * - float|null $avg           Note moyenne (ou null si aucune)
 * - int        $count         Nombre total d’avis approuvés
 * - array      $reviews       Tableau d’avis (déjà filtrés/approuvés côté contrôleur)
 * - array      $distribution  Tableau associatif [5=>n,4=>n,...,1=>n]
 */

if (!function_exists('e')) {
    /** Helper d'échappement :
     *  Je force en string et j’échappe les caractères dangereux pour éviter le XSS. */
    function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* Je sécurise/normalise mes entrées pour ne pas casser l’affichage si une clé manque. */
$avg          = $avg ?? null;
$count        = (int)($count ?? 0);
$reviews      = is_array($reviews ?? null) ? $reviews : [];
$distribution = $distribution ?? [1=>0,2=>0,3=>0,4=>0,5=>0];

/* Partial réutilisable pour le badge de note globale.
   Je teste sa présence pour éviter un include fatal si le fichier manque. */
$ratingInclude = __DIR__ . '/../partials/_rating_badge.php';
?>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <!-- Lien retour dashboard (UX simple) -->
    <a href="<?= BASE_URL ?>user/dashboard" class="btn btn-outline-secondary btn-sm mb-3">← Retour</a>

    <h1 class="h4 mb-3">Mes avis</h1>

    <!-- Bloc "note moyenne" :
         - Si j’ai le partial, je l’inclus en lui passant $avg, $count et $small=false.
         - Sinon, je fais un fallback propre avec un badge Bootstrap.
         - Si pas de note, je l’indique clairement. -->
    <div class="d-flex align-items-center gap-3 mb-4">
      <?php if ($avg !== null && file_exists($ratingInclude)): ?>
        <?php
          // Le partial attend $avg, $count et $small : je garde une version "non petite".
          $small = false;
          include $ratingInclude;
        ?>
      <?php elseif ($avg !== null): ?>
        <span class="badge text-bg-primary"><?= number_format((float)$avg, 1, ',', ' ') ?>/5 (<?= (int)$count ?>)</span>
      <?php else: ?>
        <span class="badge text-bg-secondary">Pas encore de note</span>
      <?php endif; ?>
    </div>

    <!-- Distribution des notes par étoiles :
         Je parcours 5→1 pour garder l’ordre visuel classique. -->
    <div class="row g-3 mb-4">
      <?php foreach ([5,4,3,2,1] as $n): ?>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="border rounded-3 p-2 text-center bg-light">
            <div class="fw-bold"><?= $n ?> ★</div>
            <div class="display-6 fw-semibold"><?= (int)($distribution[$n] ?? 0) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Liste des avis :
         Chaque avis affiche la note, la date (si dispo) et le commentaire avec retours à la ligne.
         J’accepte des clés alternatives (note/rating, created_at/date, commentaire/comment). -->
    <h2 class="h5">Tous les avis</h2>
    <?php if (!empty($reviews)): ?>
      <ul class="list-unstyled mb-0">
        <?php foreach ($reviews as $rv): ?>
          <?php
            // Je récupère la note (int) en tolérant plusieurs noms de champs.
            $note = (int)($rv['note'] ?? $rv['rating'] ?? 0);

            // Je récupère la date (timestamp ou string parsable) et je la formate en jj/mm/AAAA.
            $date = $rv['created_at'] ?? $rv['date'] ?? null;
            $dateLabel = '';
            if ($date) {
              $ts = is_numeric($date) ? (int)$date : strtotime((string)$date);
              if ($ts) { $dateLabel = date('d/m/Y', $ts); }
            }

            // Je récupère le commentaire (tolérance nom de champ) et je l’échappe.
            $comm = $rv['commentaire'] ?? $rv['comment'] ?? '';
          ?>
          <li class="mb-3 border-bottom pb-2">
            <div class="mb-1">
              <strong><?= $note ?>/5</strong>
              <?php if ($dateLabel): ?>
                <span class="text-muted small ms-2"><?= e($dateLabel) ?></span>
              <?php endif; ?>
            </div>
            <!-- nl2br pour conserver les retours à la ligne saisis par l’utilisateur -->
            <div><?= nl2br(e($comm)) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <!-- État vide épuré -->
      <div class="text-muted">Aucun avis pour le moment.</div>
    <?php endif; ?>
  </div>
</div>
