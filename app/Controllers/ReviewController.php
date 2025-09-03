<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Ride;
use App\Models\Review;

final class ReviewController extends BaseController
{
    /** GET /reviews/new?token=... — Affiche le formulaire */
    public function new(): void
    {
        $token = (string)($_GET['token'] ?? '');
        $claims = Security::verifyReviewToken($token);
        if (!$claims) {
            http_response_code(400);
            echo 'Lien invalide ou expiré.'; exit;
        }

        $ride = Ride::findById((int)$claims['ride_id']);
        if (!$ride) { http_response_code(404); echo 'Trajet introuvable.'; exit; }

        $this->render('pages/review_new', [
            'title'   => 'Laisser un avis',
            'token'   => $token,
            'ride'    => $ride,
            'csrf'    => $_SESSION['csrf'] ?? '',
        ]);
    }

    /** POST /reviews/new — Enregistre l’avis en Mongo */
    public function create(): void
    {
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée. Réessayez.'; header('Location: '.BASE_URL); exit;
        }

        $token = (string)($_POST['token'] ?? '');
        $claims = Security::verifyReviewToken($token);
        if (!$claims) { $_SESSION['flash_error']='Lien invalide.'; header('Location: '.BASE_URL); exit; }

        $rideId = (int)$claims['ride_id'];
        $passId = (int)$claims['passenger_id'];
        $note   = (int)($_POST['note'] ?? 0);
        $comment= trim((string)($_POST['comment'] ?? ''));

        $ride = Ride::findById($rideId);
        if (!$ride) { $_SESSION['flash_error']='Trajet introuvable.'; header('Location: '.BASE_URL); exit; }

        $reviews = new Review();
        if ($reviews->existsByRidePassenger($rideId, $passId)) {
            $_SESSION['flash_error'] = 'Vous avez déjà laissé un avis sur ce trajet.'; header('Location: '.BASE_URL); exit;
        }

        $ok = $reviews->create([
            'ride_id'      => $rideId,
            'driver_id'    => (int)$ride['driver_id'],
            'passenger_id' => $passId,
            'note'         => $note,
            'comment'      => $comment,
            'token_id'     => substr(hash('sha1', $token), 0, 20),
            'meta'         => ['ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''],
        ]);

        if ($ok) {
            $_SESSION['flash_success'] = 'Merci ! Votre avis sera visible après validation.';
        } else {
            $_SESSION['flash_error'] = 'Impossible d’enregistrer votre avis.';
        }
        header('Location: '.BASE_URL); exit;
    }

    /** GET /employee/reviews — Liste des avis en attente (employé) */
    public function pending(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);
        $reviews = new Review();
        $list = $reviews->findPending(100);
        $this->render('employee/reviews_pending', [
            'title' => 'Avis à modérer',
            'items' => $list,
        ]);
    }

    /** POST /employee/reviews/approve */
    public function approve(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) { header('Location: '.BASE_URL.'employee/reviews'); exit; }

        $id = (string)($_POST['id'] ?? '');
        $moderatorId = (int)($_SESSION['user']['id'] ?? 0);
        $ok = (new Review())->approve($id, $moderatorId);

        $_SESSION['flash_'.($ok?'success':'error')] = $ok ? 'Avis approuvé.' : 'Action impossible.';
        header('Location: '.BASE_URL.'employee/reviews'); exit;
    }

    /** POST /employee/reviews/reject */
    public function reject(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) { header('Location: '.BASE_URL.'employee/reviews'); exit; }

        $id = (string)($_POST['id'] ?? '');
        $reason = trim((string)($_POST['reason'] ?? ''));
        $moderatorId = (int)($_SESSION['user']['id'] ?? 0);
        $ok = (new Review())->reject($id, $moderatorId, $reason);

        $_SESSION['flash_'.($ok?'success':'error')] = $ok ? 'Avis rejeté.' : 'Action impossible.';
        header('Location: '.BASE_URL.'employee/reviews'); exit;
    }
}
