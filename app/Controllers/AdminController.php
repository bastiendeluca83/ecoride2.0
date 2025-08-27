<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Stats;
use App\Models\User;
// >>> Ajout pour l’API historique (on réutilise ton modèle existant)
use App\Models\AdminStats;

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

    // =========================================================
    // NOUVEAU — API JSON pour l’historique des crédits
    // GET /admin/api/credits-history?days=90
    // Réservé ADMIN
    // =========================================================
    public function apiCreditsHistory(): void
    {
        Security::ensure(['ADMIN']);

        $days = max(1, (int)($_GET['days'] ?? 90));
        $to   = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable("today -$days days"))->format('Y-m-d');

        // Récupère les lignes agrégées (peut être creux certains jours)
        $rows = AdminStats::platformCreditsHistoryDetailed($from, $to, 2);

        // Index par jour
        $byDay = [];
        foreach ($rows as $r) {
            // Compat des noms renvoyés par la requête
            $day = $r['jour'] ?? $r['day'] ?? null;
            if ($day === null) continue;
            $byDay[$day] = [
                'credits'  => (int)($r['credits'] ?? 0),
                'ride_ids' => (string)($r['ride_ids'] ?? ''),
            ];
        }

        // Normalise pour avoir tous les jours de from..to
        $out = [];
        $cursor = new \DateTimeImmutable($from);
        $limit  = new \DateTimeImmutable($to);
        while ($cursor <= $limit) {
            $d = $cursor->format('Y-m-d');
            $out[] = [
                'day'      => $d,
                'credits'  => $byDay[$d]['credits']  ?? 0,
                'ride_ids' => $byDay[$d]['ride_ids'] ?? '',
            ];
            $cursor = $cursor->modify('+1 day');
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'from' => $from,
            'to'   => $to,
            'data' => $out,
        ], JSON_UNESCAPED_UNICODE);
    }
}
