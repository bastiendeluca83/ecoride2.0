<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Db\Sql;
use PDO;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        try {
            // 1) Auth + rôle
            if (!Security::check()) {
                header('Location: /login?redirect=/dashboard');
                exit;
            }

            $role = Security::role(); // 'USER' | 'ADMIN' | 'EMPLOYEE' ...
            if ($role !== 'USER') {
                // Redirige proprement selon le rôle pour éviter toute boucle
                if ($role === 'ADMIN') { header('Location: /admin'); exit; }
                if ($role === 'EMPLOYEE') { header('Location: /employee'); exit; }
                header('Location: /'); exit;
            }

            // 2) Données (tout en try/catch global pour éviter 500 bruts)
            $pdo = Sql::pdo(); // assure-toi que Sql::pdo() met ERRMODE_EXCEPTION
            $userId = (int)($_SESSION['user']['id'] ?? 0);
            if ($userId <= 0) {
                // session corrompue → retour login
                header('Location: /login?redirect=/dashboard'); exit;
            }

            // a) Crédits (source de vérité BDD)
            $stmt = $pdo->prepare("SELECT credits, pseudo, email FROM users WHERE id = :uid");
            $stmt->execute([':uid' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['credits'=>0,'pseudo'=>'','email'=>''];
            $_SESSION['user']['credits'] = (int)$user['credits'];

            // b) Réservations à venir (exemple — adapte aux vrais noms de tables/colonnes)
            $stmt2 = $pdo->prepare("
                SELECT r.id, r.depart_city, r.arrivee_city, r.depart_at, r.price_credits
                FROM reservations r
                WHERE r.user_id = :uid AND r.status IN ('CONFIRMED','PENDING')
                ORDER BY r.depart_at ASC
                LIMIT 10
            ");
            $stmt2->execute([':uid' => $userId]);
            $reservations = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // c) Trajets créés par l’utilisateur (si il est chauffeur)
            $stmt3 = $pdo->prepare("
                SELECT t.id, t.depart_city, t.arrivee_city, t.depart_at, t.places_left
                FROM trajets t
                WHERE t.driver_id = :uid
                ORDER BY t.depart_at DESC
                LIMIT 10
            ");
            $stmt3->execute([':uid' => $userId]);
            $trajets = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            // 3) Render
            $this->render('dashboard/index', [
                'title'        => 'Mon espace',
                'user'         => $user,
                'reservations' => $reservations,
                'trajets'      => $trajets,
            ]);
        } catch (\Throwable $e) {
            // Eviter l’écran blanc/500 : message dev + log
            error_log('[DASHBOARD] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo '<h1>Erreur sur /dashboard</h1><pre>'
               . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
               . "</pre><p>Consulte /tmp/php-error.log pour le détail.</p>";
        }
    }
}
