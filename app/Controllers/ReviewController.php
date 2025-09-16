<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Ride;
use App\Models\Review;

final class ReviewController extends BaseController
{
    /* GET /reviews/new?token=... — Affiche le formulaire d'avis */
    public function new(): void
    {
        if (session_status() === \PHP_SESSION_NONE) session_start();

        $token  = (string)($_GET['token'] ?? '');
        $claims = Security::verifyReviewToken($token);
        if (!$claims) {
            http_response_code(400);
            echo 'Lien d’avis invalide ou expiré.'; exit;
        }

        $rideId = (int)$claims['rid'];
        $ride   = Ride::findById($rideId);
        if (!$ride) {
            http_response_code(404);
            echo 'Trajet introuvable.'; exit;
        }

        /*  CSRF pour le formulaire */
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

    /* POST /reviews — Enregistre l’avis dans Mongo (status=PENDING) */
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
        $note   = max(1, min(5, (int)($_POST['note'] ?? 0)));
        $comment= trim((string)($_POST['comment'] ?? ''));

        $ride = Ride::findById($rideId);
        if (!$ride) {
            $_SESSION['flash_error'] = 'Trajet introuvable.';
            header('Location: ' . BASE_URL); exit;
        }

        $reviews = new Review();

        /* Anti-doublon par (ride,passenger) */
        if ($reviews->existsByRidePassenger($rideId, $passId)) {
            $_SESSION['flash_error'] = 'Vous avez déjà laissé un avis sur ce trajet.';
            header('Location: ' . BASE_URL); exit;
        }

        $ok = $reviews->create([
            'ride_id'      => $rideId,
            'driver_id'    => (int)$ride['driver_id'],
            'passenger_id' => $passId,
            'note'         => $note,
            'comment'      => $comment,
            'token_id'     => substr(hash('sha1', $token), 0, 20),
            'meta'         => [
                'ua'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip'    => $_SERVER['REMOTE_ADDR']      ?? '',
            ],
        ]);

        if ($ok) {
            $_SESSION['flash_success'] = 'Merci ! Votre avis sera visible après validation.';
        } else {
            $_SESSION['flash_error'] = 'Impossible d’enregistrer votre avis.';
        }
        header('Location: ' . BASE_URL); exit;
    }
}
