<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Booking;
use App\Models\ReviewModel;

final class EmployeeController extends BaseController
{
    public function index(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);

        $incidents = Booking::cancelledLast(20);

        $role       = Security::role();
        $crossLabel = ($role === 'ADMIN') ? 'Espace administrateur' : 'Espace utilisateur';
        $crossHref  = ($role === 'ADMIN') ? '/admin/dashboard'     : '/user/dashboard';

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf       = $_SESSION['csrf'];
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/employee';

        $this->render('dashboard/employee', [
            'title'      => 'Espace Employé',
            'incidents'  => $incidents,
            'crossLabel' => $crossLabel,
            'crossHref'  => $crossHref,
            'csrf'       => $csrf,
            'currentUrl' => $currentUrl,
        ]);
    }

    /** Liste des avis en attente (Mongo) */
    public function reviews(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);
        $rm = new ReviewModel();
        $pending = $rm->findPending(100);

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf = $_SESSION['csrf'];

        $this->render('employee/reviews_pending', [
            'title'   => 'Avis en attente de validation',
            'pending' => $pending,
            'csrf'    => $csrf,
        ]);
    }

    /** POST /employee/reviews — approve/reject */
    public function moderate(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo 'Méthode non autorisée'; return; }

        if (!\App\Security\Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée.';
            header('Location: /employee/reviews'); return;
        }

        $id     = (string)($_POST['id'] ?? '');
        $action = strtolower((string)($_POST['action'] ?? ''));
        $uid    = (int)($_SESSION['user']['id'] ?? 0);

        $rm = new ReviewModel();
        $ok = false;
        if ($action === 'approve') {
            $ok = $rm->approve($id, $uid);
        } elseif ($action === 'reject') {
            $reason = trim((string)($_POST['reason'] ?? ''));
            $ok = $rm->reject($id, $uid, $reason);
        }

        $_SESSION['flash_' . ($ok ? 'success' : 'error')] = $ok ? 'Avis mis à jour.' : 'Action impossible.';
        header('Location: /employee/reviews');
    }
}
