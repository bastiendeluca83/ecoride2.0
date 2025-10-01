<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Booking;
use App\Models\Review;

/**
 * EmployeeController
 * - Contrôleur réservé aux employés (et admins).
 * - Permet de consulter les incidents, gérer les avis en attente, et les modérer.
 * - Je garde mon MVC : récupération des données par les Models (Booking, Review),
 *   passage des variables aux vues correspondantes.
 */
final class EmployeeController extends BaseController
{
    /**
     * Tableau de bord employé (ou admin).
     * - Récupère incidents (annulations récentes)
     * - Récupère avis en attente (MongoDB)
     * - Prépare un token CSRF
     * - Affiche la vue 'dashboard/employee'
     */
    public function index(): void
    {
        // 1) Accès : seulement EMPLOYEE ou ADMIN
        Security::ensure(['EMPLOYEE','ADMIN']);

        // 2) Incidents : j’utilise mon Model Booking pour récupérer les 20 dernières annulations
        $incidents = Booking::cancelledLast(20);

        // 3) Avis en attente (MongoDB)
        $rm          = new Review();
        $pending     = $rm->findPending(10);   // j’affiche juste un aperçu (10)
        $pendingCount = count($pending);       // compteur pratique (badge notification)

        // 4) Cross-link (bouton pour basculer sur un autre espace selon le rôle)
        $role       = Security::role();
        $crossLabel = ($role === 'ADMIN') ? 'Espace administrateur' : 'Espace utilisateur';
        $crossHref  = ($role === 'ADMIN') ? (BASE_URL . 'admin/dashboard') : (BASE_URL . 'user/dashboard');

        // 5) CSRF token
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf       = $_SESSION['csrf'];
        $currentUrl = $_SERVER['REQUEST_URI'] ?? (BASE_URL . 'employee');

        // 6) Rendu de la vue
        $this->render('dashboard/employee', [
            'title'        => 'Espace Employé',
            'incidents'    => $incidents,
            'pending'      => $pending,       // la liste des avis en attente
            'pendingCount' => $pendingCount,  // compteur
            'crossLabel'   => $crossLabel,    // libellé bouton cross
            'crossHref'    => $crossHref,     // lien bouton cross
            'csrf'         => $csrf,
            'currentUrl'   => $currentUrl,
        ]);
    }

    /**
     * GET /employee/reviews
     * Liste des avis en attente (MongoDB)
     * - Récupère jusqu’à 100 avis
     * - Passe les données à la vue 'reviews/reviews_pending'
     */
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

    /**
     * POST /employee/reviews
     * Action de modération (approve / reject un avis)
     * - Méthode POST obligatoire
     * - CSRF obligatoire
     * - Actions possibles : approve / reject (avec raison optionnelle)
     * - Retourne vers la liste avec un flash message
     */
    public function moderate(): void
    {
        Security::ensure(['EMPLOYEE','ADMIN']);

        // 1) Je force l’usage de POST
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Méthode non autorisée';
            exit;
        }

        // 2) Vérification CSRF
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            if (session_status() === \PHP_SESSION_NONE) { session_start(); }
            $_SESSION['flash_error'] = 'Session expirée.';
            header('Location: ' . BASE_URL . 'employee/reviews'); exit;
        }

        // 3) Je récupère les infos du formulaire
        $id     = (string)($_POST['id'] ?? '');            // id de l’avis
        $action = strtolower((string)($_POST['action'] ?? '')); // approve ou reject
        $uid    = (int)($_SESSION['user']['id'] ?? 0);     // id de l’employé

        $rm = new Review();
        $ok = false;

        // 4) Selon l’action choisie, j’appelle la bonne méthode du Model Review
        if ($action === 'approve') {
            $ok = $rm->approve($id, $uid);
        } elseif ($action === 'reject') {
            $reason = trim((string)($_POST['reason'] ?? '')); // motif du rejet
            $ok = $rm->reject($id, $uid, $reason);
        }

        // 5) Message flash + redirection vers la liste
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        $_SESSION['flash_' . ($ok ? 'success' : 'error')] = $ok
            ? 'Avis mis à jour.'
            : 'Action impossible.';
        header('Location: ' . BASE_URL . 'employee/reviews'); exit;
    }
}
