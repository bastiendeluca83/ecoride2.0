<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\Stats;
use App\Models\User;
use App\Models\AdminStats;

/**
 * Contrôleur Admin – respecte mon MVC :
 * - Récupère les données via mes Models (Stats, User, AdminStats)
 * - Applique mes règles d’accès (Security::ensure)
 * - Passe les données à la vue (render 'dashboard/admin')
 *
 * NB : aucune logique de présentation ici (pas d’HTML), uniquement orchestration.
 */
final class AdminController extends BaseController
{
    /**
     * Page d’accueil de l’espace admin (tableau de bord).
     * - Vérifie que l’utilisateur a le rôle ADMIN.
     * - Calcule une fenêtre glissante de 14 jours pour mes courbes.
     * - Récupère KPIs, stats journalières et liste des utilisateurs.
     * - Prépare un token CSRF à injecter dans mes formulaires.
     * - Envoie le tout à la vue 'dashboard/admin'.
     */
    public function index(): void
    {
        // Je verrouille l’accès : seuls les ADMIN peuvent entrer ici
        Security::ensure(['ADMIN']);

        // Fenêtre glissante = aujourd’hui exclus -> tomorrow (bord supérieur)
        // et 13 jours en arrière (inclu) => total 14 jours affichés
        $to   = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $from = (new \DateTimeImmutable('-13 days'))->format('Y-m-d');

        // Mes agrégats/indicateurs pour les cartes KPI et graphiques
        $kpis                = Stats::kpis();
        $totalPlatformPlace  = Stats::totalPlatformPlace();
        $ridesPerDay         = Stats::ridesPerDay($from, $to);
        $creditsPerDay       = Stats::platformCreditsPerDay($from, $to);
        $users               = User::listAll();

        // Je m’assure que la session est ouverte et qu’un CSRF est disponible
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        $csrf = $_SESSION['csrf'];

        // Je délègue l’affichage à la vue (MVC)
        $this->render('dashboard/admin', [
            'title'               => 'Espace Administrateur',
            'kpis'                => $kpis,
            'totalPlatformPlace'  => $totalPlatformPlace,
            'ridesPerDay'         => $ridesPerDay,
            'creditsPerDay'       => $creditsPerDay,
            'users'               => $users,
            'csrf'                => $csrf,
        ]);
    }

    // Aliases “par lisibilité” depuis mes routes (je mappe des URLs simples vers mes vraies actions)
    public function addEmployee(): void     { $this->createEmployee(); }
    public function suspendEmployee(): void { $this->suspend(); }
    public function suspendAccount(): void  { $this->suspend(); }

    /**
     * Création d’un compte Employé par l’Administrateur.
     * - Accès ADMIN, méthode POST obligatoire
     * - CSRF obligatoire
     * - Vérifs minimales (email non vide, mot de passe >= 8 chars)
     * - Appel au Model User::createEmployee
     * - Redirections avec querystring (feedback UI)
     *
     * Je garde volontairement la logique simple ici, le détail (messages) est géré côté vue.
     */
    public function createEmployee(): void
    {
        Security::ensure(['ADMIN']);

        // Je n’accepte que du POST sur une action de création
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405); // Méthode non autorisée
            return;
        }

        // Je valide le CSRF pour éviter les soumissions cross-site
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
            header('Location: /admin?error=csrf');
            return;
        }

        // Je récupère proprement les champs
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        $nom   = trim((string)($_POST['nom'] ?? ''));

        // Petits garde-fous (je reste minimaliste ici, j’ai potentiellement plus fort côté Model)
        if ($email === '' || strlen($pass) < 8) {
            header('Location: /admin?error=invalid');
            return;
        }

        // Je tente la création. S’il y a un doublon ou une contrainte, je renvoie un feedback standardisé
        try {
            User::createEmployee($email, $pass, $nom ?: null, null);
            header('Location: /admin?created=1');
        } catch (\Throwable $e) {
            header('Location: /admin?error=duplicate');
        }
    }

    // Petits wrappers pratiques pour piloter la suspension
    public function suspend(): void   { $this->setSuspended(true); }
    public function unsuspend(): void { $this->setSuspended(false); }

    // Alias dédiés “user”, pour plus de clarté dans le routing si besoin
    public function suspendUser(): void   { $this->setSuspended(true); }
    public function unsuspendUser(): void { $this->setSuspended(false); }

    /**
     * Change l’état “suspendu” d’un compte (user/employé).
     * - ADMIN uniquement
     * - POST uniquement
     * - CSRF obligatoire
     * - Je bloque l’auto-suspension (sécurité/ergonomie)
     */
    private function setSuspended(bool $suspend): void
    {
        Security::ensure(['ADMIN']);

        // Action sensible → uniquement POST
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            return;
        }

        // Vérification CSRF
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
            header('Location: /admin?error=csrf');
            return;
        }

        // Je récupère la cible et j’empêche de se suspendre soi-même
        $targetId = (int)($_POST['id'] ?? 0);
        $selfId   = (int)($_SESSION['user']['id'] ?? 0);
        if ($targetId <= 0 || $targetId === $selfId) {
            header('Location: /admin?error=badtarget');
            return;
        }

        // Passage de l’état via le Model
        User::setSuspended($targetId, $suspend);

        // Feedback générique (la vue affiche un message en fonction du querystring)
        header('Location: /admin?suspended=1');
    }

    /**
     * API JSON – Historique des crédits plateforme par jour.
     * - ADMIN uniquement
     * - Paramètre ?days=… (par défaut 90). Je borne à min 1.
     * - Construit une série continue jour par jour (remplit les jours vides à 0)
     * - Retourne un JSON propre (UTF-8, sans échappement inutile)
     *
     * Ces données servent à alimenter mon graphique côté front (admin).
     */
    public function apiCreditsHistory(): void
    {
        Security::ensure(['ADMIN']);

        // Je récupère le nombre de jours à couvrir (je sécurise un minimum)
        $days = max(1, (int)($_GET['days'] ?? 90));

        // Période : today inclus, et je remonte “$days” jours
        $to   = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $from = (new \DateTimeImmutable("today -$days days"))->format('Y-m-d');

        // Je demande au Model un détail par jour
        // Le “2” ici est mon “coût plateforme” par transaction (crédits), cohérent avec mon métier
        $rows = AdminStats::platformCreditsHistoryDetailed($from, $to, 2);

        // Je mets en forme sous un index jour → { credits, ride_ids }
        $byDay = [];
        foreach ($rows as $r) {
            $day = $r['jour'] ?? $r['day'] ?? null; // suivant le SQL, j’accepte les deux alias
            if ($day === null) continue;
            $byDay[$day] = [
                'credits'  => (int)($r['credits'] ?? 0),
                'ride_ids' => (string)($r['ride_ids'] ?? ''),
            ];
        }

        // Je reconstitue une série continue jour par jour (pour un graphe propre)
        $out    = [];
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

        // Réponse JSON propre (UTF-8), consommable direct par mon front
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'from' => $from,
            'to'   => $to,
            'data' => $out,
        ], JSON_UNESCAPED_UNICODE);
    }
}
