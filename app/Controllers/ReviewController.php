<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Ride;
use App\Models\Review;
use App\Models\Booking;
use App\Db\Sql;
use PDO;

final class ReviewController extends BaseController
{
    /* GET /reviews/new?token=... — Affiche le formulaire d'avis (public) */
    public function new(): void
    {
        if (session_status() === \PHP_SESSION_NONE) session_start();

        $token  = (string)($_GET['token'] ?? '');
        $claims = Security::verifyReviewToken($token);
        if (!$claims) {
            http_response_code(400);
            echo 'Lien d’avis invalide ou expiré.'; exit;
        }

        $rideId      = (int)$claims['rid'];
        $passengerId = (int)$claims['pid'];

        $ride = Ride::findById($rideId);
        if (!$ride) {
            http_response_code(404);
            echo 'Trajet introuvable.'; exit;
        }

        // ✅ Vérifie que ce passager a bien une réservation CONFIRMED sur ce trajet
        $booking = Booking::findByRideAndUser($rideId, $passengerId);
        if (!$booking || strtoupper($booking['status'] ?? '') !== 'CONFIRMED') {
            http_response_code(403);
            echo 'Ce lien ne correspond pas à une réservation confirmée.'; exit;
        }

        // CSRF pour le formulaire
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        $this->render('reviews/review_new', [
            'title' => 'Laisser un avis',
            'token' => $token,
            'ride'  => $ride,
            'csrf'  => $_SESSION['csrf'],
        ]);
    }

    /* POST /reviews — Enregistre l’avis dans Mongo (status = PENDING) */
    public function create(): void
    {
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée. Merci de réessayer.';
            header('Location: ' . BASE_URL); exit;
        }

        $token  = (string)($_POST['token'] ?? '');
        $claims = Security::verifyReviewToken($token);
        if (!$claims) {
            $_SESSION['flash_error'] = 'Lien invalide.';
            header('Location: ' . BASE_URL); exit;
        }

        $rideId = (int)$claims['rid'];
        $passId = (int)$claims['pid'];

        $ride = Ride::findById($rideId);
        if (!$ride) {
            $_SESSION['flash_error'] = 'Trajet introuvable.';
            header('Location: ' . BASE_URL); exit;
        }

        // ✅ Re-vérifie la réservation confirmée
        $booking = Booking::findByRideAndUser($rideId, $passId);
        if (!$booking || strtoupper($booking['status'] ?? '') !== 'CONFIRMED') {
            $_SESSION['flash_error'] = 'Réservation introuvable ou non confirmée.';
            header('Location: ' . BASE_URL); exit;
        }

        $note    = max(1, min(5, (int)($_POST['note'] ?? 0)));
        $comment = trim((string)($_POST['comment'] ?? ''));

        $reviews = new Review();

        // Anti-doublon (ride,passenger)
        if ($reviews->existsByRidePassenger($rideId, $passId)) {
            $_SESSION['flash_error'] = 'Vous avez déjà laissé un avis sur ce trajet.';
            header('Location: ' . BASE_URL); exit;
        }

        $ok = $reviews->create([
            'ride_id'      => $rideId,
            'driver_id'    => (int)$ride['driver_id'],
            'passenger_id' => $passId,
            'note'         => $note,
            'comment'      => $comment, // commentaire optionnel
            'token_id'     => substr(hash('sha1', $token), 0, 20),
            'meta'         => [
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR']      ?? '',
            ],
        ]);

        if ($ok) {
            $_SESSION['flash_success'] = 'Merci ! Votre avis sera visible après validation.';
        } else {
            $_SESSION['flash_error'] = 'Impossible d’enregistrer votre avis.';
        }

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

        // Infos publiques du conducteur (SQL)
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

        // Données avis (Mongo)
        $avg          = null;
        $count        = 0;
        $distribution = [1=>0,2=>0,3=>0,4=>0,5=>0];
        $reviews      = [];

        try {
            $rm = new Review();

            // moyenne
            if (method_exists($rm, 'avgForDriver')) {
                $avg = $rm->avgForDriver($driverId);
            }

            // compteur (si dispo via map agrégée)
            if (method_exists($rm, 'avgForDrivers')) {
                $map = $rm->avgForDrivers([$driverId]);
                if (isset($map[$driverId]['count'])) {
                    $count = (int)$map[$driverId]['count'];
                }
            }

            // liste d'avis approuvés (on essaye plusieurs méthodes selon ton modèle)
            if (method_exists($rm, 'allApprovedForDriver')) {
                $reviews = $rm->allApprovedForDriver($driverId);
            } elseif (method_exists($rm, 'findByDriverApproved')) {
                // large limite pour la distrib
                $reviews = $rm->findByDriverApproved($driverId, 500);
            } elseif (method_exists($rm, 'approvedForDriver')) {
                $reviews = $rm->approvedForDriver($driverId);
            } elseif (method_exists($rm, 'getApprovedForDriver')) {
                $reviews = $rm->getApprovedForDriver($driverId);
            } else {
                $reviews = [];
            }

            // si le compteur n’a pas été obtenu via la map, on fallback sur la liste
            if ($count === 0 && !empty($reviews)) {
                $count = count($reviews);
            }

            // distribution 1..5
            foreach ($reviews as $rv) {
                $n = (int)($rv['note'] ?? $rv['rating'] ?? 0);
                if ($n >= 1 && $n <= 5) { $distribution[$n]++; }
            }
        } catch (\Throwable $e) {
            // on n'éclate pas la page si Mongo est KO
            error_log('[driverRatings] '.$e->getMessage());
        }

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
