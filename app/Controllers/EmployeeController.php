<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Booking;

final class EmployeeController extends BaseController
{
    public function index(): void
    {
        // Autoriser EMPLOYEE et ADMIN
        Security::ensure(['EMPLOYEE','ADMIN']);

        // Incidents = réservations annulées récemment
        $incidents = Booking::cancelledLast(20);

        // Bouton cross-espace
        $role       = Security::role();
        $crossLabel = ($role === 'ADMIN') ? 'Espace administrateur' : 'Espace utilisateur';
        $crossHref  = ($role === 'ADMIN') ? '/admin/dashboard'     : '/user/dashboard';

        // CSRF + current URL
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
}
