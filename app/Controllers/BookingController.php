<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Security\Security;
use PDO;

final class BookingController extends BaseController
{
    /** POST /rides/book  (attend ride_id) */
    public function confirm(): void
    {
        // Sécurité: login requis
        if (!Security::check()) { header('Location: /login?redirect=/rides'); exit; }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo 'Méthode non autorisée'; return; }

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (isset($_POST['csrf'], $_SESSION['csrf']) && !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
            http_response_code(400);
            $this->render('rides/detail', ['title'=>'Trajet', 'error'=>'Session expirée (CSRF).']);
            return;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $rideId = (int)($_POST['ride_id'] ?? 0);
        if ($userId <= 0 || $rideId <= 0) {
            http_response_code(400);
            $this->render('rides/detail', ['title'=>'Trajet', 'error'=>'Requête invalide (ride_id manquant).']);
            return;
        }

        $pdo = Sql::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->beginTransaction();

            // 1) Lock trajet
            $q = $pdo->prepare("SELECT id, price, seats_left FROM rides WHERE id=? FOR UPDATE");
            $q->execute([$rideId]);
            $ride = $q->fetch(PDO::FETCH_ASSOC);
            if (!$ride) { throw new \RuntimeException("Trajet introuvable."); }
            if ((int)$ride['seats_left'] <= 0) { throw new \RuntimeException("Plus de place."); }

            // 2) Lock user + crédits
            $q = $pdo->prepare("SELECT credits FROM users WHERE id=? FOR UPDATE");
            $q->execute([$userId]);
            $credits = (int)$q->fetchColumn();
            $price   = (int)$ride['price'];
            if ($credits < $price) { throw new \RuntimeException("Crédits insuffisants."); }

            // 3) Réservation
            $stmt = $pdo->prepare("
                INSERT INTO bookings (user_id, ride_id, credits_spent, status, created_at)
                VALUES (?, ?, ?, 'confirmed', NOW())
            ");
            $stmt->execute([$userId, $rideId, $price]);

            // 4) MAJ trajets + crédits
            $pdo->prepare("UPDATE rides SET seats_left = seats_left - 1 WHERE id=?")->execute([$rideId]);
            $pdo->prepare("UPDATE users SET credits    = credits    - ? WHERE id=?")->execute([$price, $userId]);

            $pdo->commit();

            $_SESSION['flash_success'] = "Réservation confirmée !";
            header('Location: /user/dashboard');
            exit;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $this->render('rides/detail', ['title'=>'Trajet', 'error'=>$e->getMessage()]);
        }
    }
}
