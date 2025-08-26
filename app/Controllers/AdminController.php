<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Stats;
use App\Models\User;

final class AdminController extends BaseController
{
    /** GET /admin ou /admin/dashboard */
    public function index(): void
    {
        Security::ensure(['ADMIN']);

        // Fenêtre glissante 14 jours pour les tableaux
        $to   = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('-13 days'))->format('Y-m-d');

        $kpis          = Stats::kpis();
        $ridesPerDay   = Stats::ridesPerDay($from, $to);
        $creditsPerDay = Stats::platformCreditsPerDay($from, $to);
        $users         = User::listAll();

        // CSRF pour les formulaires (suspension / création)
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf = $_SESSION['csrf'];

        $this->render('dashboard/admin', [
            'title'         => 'Espace Administrateur',
            'kpis'          => $kpis,
            'ridesPerDay'   => $ridesPerDay,
            'creditsPerDay' => $creditsPerDay,
            'users'         => $users,
            'csrf'          => $csrf,
        ]);
    }

    /* ------- Alias compat (routes anciennes) ------- */
    public function addEmployee(): void     { $this->createEmployee(); }
    public function suspendEmployee(): void { $this->suspend(); }
    public function suspendAccount(): void  { $this->suspend(); }

    /** POST /admin/employees/create */
    public function createEmployee(): void
    {
        Security::ensure(['ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); return; }

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
            header('Location: /admin?error=csrf'); return;
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        $nom   = trim((string)($_POST['nom'] ?? ''));
        if ($email === '' || strlen($pass) < 8) { header('Location: /admin?error=invalid'); return; }

        // Création via modèle User (rôle EMPLOYEE, crédits 0)
        try {
            User::createEmployee($email, $pass, $nom ?: null, null);
            header('Location: /admin?created=1');
        } catch (\Throwable $e) {
            header('Location: /admin?error=duplicate'); // email unique, etc.
        }
    }

    /** POST /admin/users/suspend */
    public function suspend(): void  { $this->setSuspended(true); }

    /** POST /admin/users/unsuspend */
    public function unsuspend(): void { $this->setSuspended(false); }

    /* --------- Implémentation commune --------- */
    private function setSuspended(bool $suspend): void
    {
        Security::ensure(['ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); return; }

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
            header('Location: /admin?error=csrf'); return;
        }

        $targetId = (int)($_POST['id'] ?? 0);
        $selfId   = (int)($_SESSION['user']['id'] ?? 0);
        if ($targetId <= 0 || $targetId === $selfId) { header('Location: /admin?error=badtarget'); return; }

        User::setSuspended($targetId, $suspend);
        header('Location: /admin?suspended=1');
    }
}
