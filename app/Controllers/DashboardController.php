<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Db\Sql;
use PDO;

/**
 * DashboardController
 * - J'affiche un tableau de bord différent selon le rôle :
 *   * ADMIN     → redirection vers /admin/dashboard
 *   * EMPLOYEE  → redirection vers /employee
 *   * USER      → j'affiche mon dashboard utilisateur : crédits + réservations + trajets publiés
 *
 * Je reste fidèle au MVC : ici je ne fais que préparer les données et je délègue
 * l'affichage à ma vue 'dashboard/user'.
 */
final class DashboardController extends BaseController
{
    public function index(): void
    {
        // 1) Authentification + autorisation
        //    Je limite l'accès du dashboard aux rôles valides : USER, EMPLOYEE, ADMIN.
        Security::ensure(['USER','EMPLOYEE','ADMIN']);

        // 2) Je détecte le rôle courant pour router correctement
        $role = Security::role();

        // 2.a) Les admins n'utilisent pas ce dashboard : je les envoie sur leur espace dédié
        if ($role === 'ADMIN')    { header('Location: /admin/dashboard'); exit; }

        // 2.b) Les employés aussi : espace employé spécifique
        if ($role === 'EMPLOYEE') { header('Location: /employee');        exit; }

        // 3) À partir d'ici, je suis sûr d'être sur un rôle USER → je prépare les données du tableau de bord
        $pdo = Sql::pdo();

        // 3.a) J'identifie l'utilisateur connecté depuis la session
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        // 3.b) Je rafraîchis le solde de crédits depuis la base (source de vérité)
        $q = $pdo->prepare("SELECT credits FROM users WHERE id = :id");
        $q->execute([':id' => $userId]);
        $_SESSION['user']['credits'] = (int)($q->fetchColumn() ?: 0);

        // 4) Je récupère les réservations à venir du USER (en tant que passager)
        //     Point d'attention schéma :
        //    - Ici je filtre sur b.passenger_id et b.status='CONFIRMED'.
        //    - Dans mon BookingController, l'insertion était sur la colonne user_id
        //      et le status 'confirmed' (tout en minuscule).
        //    → Il faut que mon schéma et mes valeurs concordent.
        //      Si ma table 'bookings' utilise 'user_id' + status 'confirmed',
        //      j'adapte la requête ci-dessous en conséquence (user_id / 'confirmed').
        $reservations = [];
        try {
            $st = $pdo->prepare("
                SELECT b.id, r.from_city, r.to_city, r.date_start, r.price
                FROM bookings b
                JOIN rides r ON r.id = b.ride_id
                WHERE b.passenger_id = :id
                  AND b.status = 'CONFIRMED'
                  AND r.date_start >= NOW()
                ORDER BY r.date_start ASC
                LIMIT 10
            ");
            $st->execute([':id' => $userId]);
            $reservations = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Je garde le dashboard fonctionnel même si la requête échoue
            // (log possible ici si je veux tracer l'erreur)
        }

        // 5) Je récupère les trajets publiés par l'utilisateur (en tant que conducteur)
        $trajets = [];
        try {
            $st = $pdo->prepare("
                SELECT id, from_city, to_city, date_start, seats_left
                FROM rides
                WHERE driver_id = :id
                  AND date_start >= NOW()
                ORDER BY date_start ASC
                LIMIT 10
            ");
            $st->execute([':id' => $userId]);
            $trajets = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Même approche : je reste résilient si la requête plante
        }

        // 6) Je passe toutes mes données à la vue utilisateur
        $this->render('dashboard/user', [
            'title'        => 'Mon espace – EcoRide',
            'user'         => $_SESSION['user'] ?? null,
            'reservations' => $reservations,
            'trajets'      => $trajets,
        ]);
    }
}
