<?php
namespace App\Controllers;

use App\Security\Security;
use App\Db\Sql;
use PDO;

class DashboardController
{
    public function index()
    {
        if (!Security::check()) { header('Location: /login'); exit; }
        if (Security::role() !== 'USER') { Security::redirectByRole(); }
        Security::ensure(['USER']);

        $pdo = Sql::pdo();
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        // 1) Récupère les crédits en BDD (source de vérité) et synchronise la session
        $q = $pdo->prepare("SELECT credits FROM users WHERE id = :uid");
        $q->execute(['uid' => $userId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $userCredits = (int)($row['credits'] ?? 0);
        $_SESSION['user']['credits'] = $userCredits;

        // 2) Réservations à venir (passager)
        $stmt = $pdo->prepare("
            SELECT r.id, r.from_city, r.to_city, r.date_start, b.credits_spent
            FROM bookings b
            JOIN rides r ON r.id = b.ride_id
            WHERE b.passenger_id = :uid
              AND b.status = 'CONFIRMED'
              AND r.date_start > NOW()
            ORDER BY r.date_start ASC
        ");
        $stmt->execute(['uid' => $userId]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3) Trajets du user en tant que conducteur
        $stmt = $pdo->prepare("
            SELECT id, from_city, to_city, date_start, seats_left
            FROM rides
            WHERE driver_id = :uid
            ORDER BY date_start ASC
        ");
        $stmt->execute(['uid' => $userId]);
        $rides = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $title = 'Espace utilisateur';

        // La vue `user.php` peut afficher $userCredits directement
        ob_start();
        include __DIR__ . '/../Views/dashboard/user.php';
        return ob_get_clean();
    }
}
