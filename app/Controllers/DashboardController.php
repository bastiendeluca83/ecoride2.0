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
        // Auth & rôle
        Security::ensure(['USER','EMPLOYEE','ADMIN']);
        $role = Security::role();

        if ($role === 'ADMIN')    { header('Location: /admin/dashboard'); exit; }
        if ($role === 'EMPLOYEE') { header('Location: /employee');        exit; }

        // Rôle USER : tableau de bord minimal
        $pdo = Sql::pdo();
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        // Crédits à jour
        $q = $pdo->prepare("SELECT credits FROM users WHERE id = :id");
        $q->execute([':id'=>$userId]);
        $_SESSION['user']['credits'] = (int)($q->fetchColumn() ?: 0);

        // Réservations à venir (>>> colonne correcte passenger_id + status)
        $reservations = [];
        try {
            $st = $pdo->prepare("
                SELECT b.id, r.from_city, r.to_city, r.date_start, r.price
                FROM bookings b
                JOIN rides r ON r.id = b.ride_id
                WHERE b.passenger_id = :id AND b.status='CONFIRMED' AND r.date_start >= NOW()
                ORDER BY r.date_start ASC
                LIMIT 10
            ");
            $st->execute([':id'=>$userId]);
            $reservations = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        // Trajets publiés (driver)
        $trajets = [];
        try {
            $st = $pdo->prepare("
                SELECT id, from_city, to_city, date_start, seats_left
                FROM rides
                WHERE driver_id = :id AND date_start >= NOW()
                ORDER BY date_start ASC
                LIMIT 10
            ");
            $st->execute([':id'=>$userId]);
            $trajets = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        $this->render('dashboard/user', [
            'title'        => 'Mon espace – EcoRide',
            'user'         => $_SESSION['user'] ?? null,
            'reservations' => $reservations,
            'trajets'      => $trajets,
        ]);
    }
}
