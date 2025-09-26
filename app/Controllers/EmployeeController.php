<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Ride;   // ⬅️ ajout
use App\Models\User;   // ⬅️ ajout

final class EmployeeController extends BaseController
{
    public function index(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);

        // Incidents "annulations" (MySQL)
        $cancelIncidents = Booking::cancelledLast(20);

        // Avis PENDING avec note ≤ 3 (Mongo) -> considérés comme incidents
        $rm = new Review();
        $lowReviews = $rm->findPendingLowScore(3, 20);

        // On enrichit chaque avis avec infos ride + passager pour remplir le tableau incidents
        $reviewIncidents = [];
        foreach ($lowReviews as $r) {
            $ride = $r['ride_id'] ? (Ride::findById((int)$r['ride_id']) ?? []) : [];
            $usr  = $r['passenger_id'] ? (User::findById((int)$r['passenger_id']) ?? []) : [];

            $reviewIncidents[] = [
                // On garde le champ 'id' attendu par la vue; on préfixe pour ne pas confondre avec un booking id
                'id'             => 'R-' . ($r['id'] ?? ''),
                'passenger_email'=> (string)($usr['email'] ?? ('#'.$r['passenger_id'])),
                'from_city'      => (string)($ride['from_city'] ?? ''),
                'to_city'        => (string)($ride['to_city'] ?? ''),
                'date_start'     => (string)($ride['date_start'] ?? ''),
                // Pour la colonne "Crédits / Note", on met "Note X★"
                'credits_spent'  => 'Note ' . (int)($r['note'] ?? 0) . '★',
                'created_at'     => (string)($r['created_at'] ?? ''),
                'is_review'      => true,   // indicateur interne, si besoin plus tard
            ];
        }

        // Fusion incidents et tri par date décroissante
        $incidents = array_merge($reviewIncidents, $cancelIncidents);
        usort($incidents, function(array $a, array $b){
            $da = strtotime((string)($a['created_at'] ?? '')) ?: 0;
            $db = strtotime((string)($b['created_at'] ?? '')) ?: 0;
            return $db <=> $da;
        });

        // Pour afficher aussi le tableau "Avis en attente" sur la même page
        $pendingReviews = $rm->findPending(100);

        $role       = Security::role();
        $crossLabel = ($role === 'ADMIN') ? 'Espace administrateur' : 'Espace utilisateur';
        $crossHref  = ($role === 'ADMIN') ? (BASE_URL . 'admin/dashboard') : (BASE_URL . 'user/dashboard');

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf       = $_SESSION['csrf'];
        $currentUrl = $_SERVER['REQUEST_URI'] ?? (BASE_URL . 'employee');

        $this->render('dashboard/employee', [
            'title'      => 'Espace Employé',
            'incidents'  => $incidents,
            'pending'    => $pendingReviews,   // ⬅️ ajouté
            'crossLabel' => $crossLabel,
            'crossHref'  => $crossHref,
            'csrf'       => $csrf,
            'currentUrl' => $currentUrl,
        ]);
    }

    /** GET /employee/reviews — liste des avis en attente (Mongo) */
    public function reviews(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);

        $rm    = new Review();              // <- modèle Mongo
        $items = $rm->findPending(100);     // tableau d'avis en attente

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf = $_SESSION['csrf'];

        // ✅ nom de vue corrigé (avec "s")
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
