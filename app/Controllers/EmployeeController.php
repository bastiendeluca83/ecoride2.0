<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Booking;
use App\Models\Review;

final class EmployeeController extends BaseController
{
    public function index(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);

        // Incidents (annulations récentes)
        $incidents = Booking::cancelledLast(20);

        // ✅ Avis en attente (Mongo)
        $rm       = new Review();
        $pending  = $rm->findPending(10);        // on en montre un aperçu (10) sur le dashboard
        $pendingCount = count($pending);         // utile pour un badge/compteur

        $role       = Security::role();
        $crossLabel = ($role === 'ADMIN') ? 'Espace administrateur' : 'Espace utilisateur';
        $crossHref  = ($role === 'ADMIN') ? (BASE_URL . 'admin/dashboard') : (BASE_URL . 'user/dashboard');

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf       = $_SESSION['csrf'];
        $currentUrl = $_SERVER['REQUEST_URI'] ?? (BASE_URL . 'employee');

        $this->render('dashboard/employee', [
            'title'        => 'Espace Employé',
            'incidents'    => $incidents,
            'pending'      => $pending,       // ✅ passé à la vue
            'pendingCount' => $pendingCount,  // ✅ optionnel
            'crossLabel'   => $crossLabel,
            'crossHref'    => $crossHref,
            'csrf'         => $csrf,
            'currentUrl'   => $currentUrl,
        ]);
    }

    /** GET /employee/reviews — liste des avis en attente (Mongo) */
    public function reviews(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);

        $rm    = new Review();
        $items = $rm->findPending(100);

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf = $_SESSION['csrf'];

        $this->render('reviews/reviews_pending', [
            'title' => 'Avis en attente de validation',
            'items' => $items,
            'csrf'  => $csrf,
        ]);
    }

    /** POST /employee/reviews — approve/reject */
    public function moderate(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Méthode non autorisée';
            exit;
        }

        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            if (session_status() === \PHP_SESSION_NONE) { session_start(); }
            $_SESSION['flash_error'] = 'Session expirée.';
            header('Location: ' . BASE_URL . 'employee/reviews'); exit;
        }

        $id     = (string)($_POST['id'] ?? '');
        $action = strtolower((string)($_POST['action'] ?? ''));
        $uid    = (int)($_SESSION['user']['id'] ?? 0);

        $rm = new Review();
        $ok = false;

        if ($action === 'approve') {
            $ok = $rm->approve($id, $uid);
        } elseif ($action === 'reject') {
            $reason = trim((string)($_POST['reason'] ?? ''));
            $ok = $rm->reject($id, $uid, $reason);
        }

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        $_SESSION['flash_' . ($ok ? 'success' : 'error')] = $ok ? 'Avis mis à jour.' : 'Action impossible.';
        header('Location: ' . BASE_URL . 'employee/reviews'); exit;
    }
}
