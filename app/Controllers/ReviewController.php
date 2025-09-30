<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Ride;
use App\Models\Review;
use App\Models\Booking;
use App\Db\Sql;
use PDO;

/**
 * ReviewController
 * - Je gère tout le flux des avis :
 *   1) Afficher le formulaire via un lien signé (token)
 *   2) Enregistrer un avis (en PENDING) dans Mongo
 *   3) Afficher la page publique des avis d’un conducteur
 *
 * Je protège au maximum :
 * - Token signé + expiration
 * - Vérification que le passager a bien une réservation CONFIRMED
 * - CSRF sur le POST
 * - Tolerance aux méthodes du modèle Review (selon ce qui est dispo)
 */
final class ReviewController extends BaseController
{
    /* GET /reviews/new?token=... — Affiche le formulaire d'avis (public) */
    public function new(): void
    {
        // j'ouvre la session si besoin (CSRF + flash)
        if (session_status() === \PHP_SESSION_NONE) session_start();

        // je récupère le token signé dans l'URL
        $token  = (string)($_GET['token'] ?? '');
        $claims = Security::verifyReviewToken($token); // rid = id trajet, pid = id passager, exp = expiration
        if (!$claims) {
            http_response_code(400);
            echo 'Lien d’avis invalide ou expiré.'; exit;
        }

        $rideId      = (int)$claims['rid'];
        $passengerId = (int)$claims['pid'];

        // je vérifie que le trajet existe
        $ride = Ride::findById($rideId);
        if (!$ride) {
            http_response_code(404);
            echo 'Trajet introuvable.'; exit;
        }

        // ✅ je m'assure que ce passager avait bien une réservation CONFIRMED pour ce trajet
        $booking = Booking::findByRideAndUser($rideId, $passengerId);
        if (!$booking || strtoupper($booking['status'] ?? '') !== 'CONFIRMED') {
            http_response_code(403);
            echo 'Ce lien ne correspond pas à une réservation confirmée.'; exit;
        }

        // je génère un token CSRF pour sécuriser la soumission du formulaire
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        // je rends la vue du formulaire (aucune logique de présentation ici)
        $this->render('reviews/review_new', [
            'title' => 'Laisser un avis',
            'token' => $token,          // je renvoie le token pour le POST
            'ride'  => $ride,           // quelques infos du trajet (affichage)
            'csrf'  => $_SESSION['csrf']
        ]);
    }

    /* POST /reviews — Enregistre l’avis dans Mongo (status = PENDING) */
    public function create(): void
    {
        // protection CSRF pour éviter des soumissions frauduleuses
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée. Merci de réessayer.';
            header('Location: ' . BASE_URL); exit;
        }

        // je relis et vérifie le token signé
        $token  = (string)($_POST['token'] ?? '');
        $claims = Security::verifyReviewToken($token);
        if (!$claims) {
            $_SESSION['flash_error'] = 'Lien invalide.';
            header('Location: ' . BASE_URL); exit;
        }

        $rideId = (int)$claims['rid'];
        $passId = (int)$claims['pid'];

        // le trajet doit exister (sinon je coupe court)
        $ride = Ride::findById($rideId);
        if (!$ride) {
            $_SESSION['flash_error'] = 'Trajet introuvable.';
            header('Location: ' . BASE_URL); exit;
        }

        // ✅ je re-vérifie que la réservation est CONFIRMED (cohérence serveur)
        $booking = Booking::findByRideAndUser($rideId, $passId);
        if (!$booking || strtoupper($booking['status'] ?? '') !== 'CONFIRMED') {
            $_SESSION['flash_error'] = 'Réservation introuvable ou non confirmée.';
            header('Location: ' . BASE_URL); exit;
        }

        // je normalise la note et je nettoie le commentaire
        $note    = max(1, min(5, (int)($_POST['note'] ?? 0)));
        $comment = trim((string)($_POST['comment'] ?? ''));

        $reviews = new Review();

        // je bloque les doublons (un seul avis par (trajet, passager))
        if ($reviews->existsByRidePassenger($rideId, $passId)) {
            $_SESSION['flash_error'] = 'Vous avez déjà laissé un avis sur ce trajet.';
            header('Location: ' . BASE_URL); exit;
        }

        // je crée l’avis en statut "PENDING" (validation côté employé)
        $ok = $reviews->create([
            'ride_id'      => $rideId,
            'driver_id'    => (int)$ride['driver_id'],
            'passenger_id' => $passId,
            'note'         => $note,
            'comment'      => $comment, // optionnel
            'token_id'     => substr(hash('sha1', $token), 0, 20), // j'évite de stocker le token brut
            'meta'         => [
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR']      ?? '',
            ],
        ]);

        // je renvoie un feedback utilisateur propre
        if ($ok) {
            $_SESSION['flash_success'] = 'Merci ! Votre avis sera visible après validation.';
        } else {
            $_SESSION['flash_error'] = 'Impossible d’enregistrer votre avis.';
        }

        // retour à l'accueil (pas de double POST)
        header('Location: ' . BASE_URL); exit;
    }

    /* ✅ GET /drivers/ratings?id=<driver_id>
       Page publique “Avis du conducteur” (Mongo APPROVED) */
    public function driverRatings(): void
    {
        $driverId = (int)($_GET['id'] ?? 0);
        if ($driverId <= 0) {
            http_response_code(404);
            echo 'Conducteur introuvable.'; exit;
        }

        // je récupère un sous-ensemble d'infos publiques du conducteur (via SQL simple)
        $pdo = Sql::pdo();
        $st  = $pdo->prepare("
            SELECT id, prenom, nom, avatar_path 
            FROM users 
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([':id' => $driverId]);
        $driver = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$driver) {
            http_response_code(404);
            echo 'Conducteur introuvable.'; exit;
        }

        // bloc avis (Mongo) — je reste tolérant aux méthodes disponibles dans Review
        $avg          = null;                          // moyenne (arrondie côté modèle si prévu)
        $count        = 0;                             // nombre total d'avis approuvés
        $distribution = [1=>0,2=>0,3=>0,4=>0,5=>0];    // pour un histogramme propre en vue
        $reviews      = [];                            // liste d'avis approuvés

        try {
            $rm = new Review();

            // moyenne si méthode dispo
            if (method_exists($rm, 'avgForDriver')) {
                $avg = $rm->avgForDriver($driverId);
            }

            // compteur via map agrégée si dispo
            if (method_exists($rm, 'avgForDrivers')) {
                $map = $rm->avgForDrivers([$driverId]);
                if (isset($map[$driverId]['count'])) {
                    $count = (int)$map[$driverId]['count'];
                }
            }

            // je récupère la liste "approved" selon ce que le modèle expose
            if (method_exists($rm, 'allApprovedForDriver')) {
                $reviews = $rm->allApprovedForDriver($driverId);
            } elseif (method_exists($rm, 'findByDriverApproved')) {
                $reviews = $rm->findByDriverApproved($driverId, 500); // limite large pour une belle distrib
            } elseif (method_exists($rm, 'approvedForDriver')) {
                $reviews = $rm->approvedForDriver($driverId);
            } elseif (method_exists($rm, 'getApprovedForDriver')) {
                $reviews = $rm->getApprovedForDriver($driverId);
            } else {
                $reviews = [];
            }

            // si je n’ai pas de count via l’agrégat, je retombe sur un simple count($reviews)
            if ($count === 0 && !empty($reviews)) {
                $count = count($reviews);
            }

            // je calcule la distribution 1..5 pour l'affichage (barres/étoiles)
            foreach ($reviews as $rv) {
                $n = (int)($rv['note'] ?? $rv['rating'] ?? 0);
                if ($n >= 1 && $n <= 5) { $distribution[$n]++; }
            }
        } catch (\Throwable $e) {
            // si Mongo tombe, je log et j'affiche quand même la fiche conducteur (sans casser la page)
            error_log('[driverRatings] '.$e->getMessage());
        }

        // j’envoie les données à la vue publique (pages/driver_ratings.php)
        $this->render('pages/driver_ratings', [
            'title'        => 'Avis du conducteur',
            'driver'       => $driver,
            'avg'          => $avg,
            'count'        => $count,
            'reviews'      => $reviews,
            'distribution' => $distribution,
        ]);
    }
}
