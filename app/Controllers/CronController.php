<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ride;
use App\Models\User;
use App\Models\Booking;

final class CronController extends BaseController
{
    /**
     * GET /cron/run?token=XXXX
     * À protéger via un token simple (voir $expected plus bas).
     * Tu peux appeler cette route via un cron `curl` quotidien.
     */
    public function run(): void
    {
        // 1) Sécurité basique (token)
        $expected = getenv('CRON_TOKEN') ?: 'changeme'; // mets ta vraie valeur en prod (ex: dans .env)
        $token = (string)($_GET['token'] ?? '');
        if ($token !== $expected) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        // === Paramétrage métier selon ton message ===
        // Conducteur A (1 trajet / quinzaine)
        $driverA  = 1;    // user_id
        $vehicleA = 1;    // vehicle_id

        // Conducteur B (2 trajets / quinzaine)
        $driverB  = 8;    // user_id
        $vehicleB = 7;    // vehicle_id

        // Passagers (crédités toutes les 2 semaines, et qu’on réserve automatiquement)
        $passengers = [4, 9];

        // Fenêtre de génération (prochaine “quinzaine”) :
        // on part du prochain samedi 09:00, puis 12:00, 15:00 (exemple clair),
        // et ce script peut tourner tous les jours sans doublons.
        $base = new \DateTimeImmutable('next saturday 09:00');

        // === 1) Génération des 3 trajets (anti-doublons) ===
        // 1 trajet pour A
        $rideIds = [];

        $rideIds[] = $this->ensureRideBlock(
            $driverA, $vehicleA, 'Paris', 'Lyon',
            $base,        // 09:00
            10            // prix
        );

        // 2 trajets pour B
        $rideIds[] = $this->ensureRideBlock(
            $driverB, $vehicleB, 'Paris', 'Lille',
            $base->modify('+3 hours'), // 12:00
            10
        );
        $rideIds[] = $this->ensureRideBlock(
            $driverB, $vehicleB, 'Paris', 'Reims',
            $base->modify('+6 hours'), // 15:00
            10
        );

        // === 2) Crédit auto passagers (toutes les 2 semaines) ===
        foreach ($passengers as $uid) {
            // +10 crédits si la dernière recharge date de ≥ 14 jours
            User::topUpCreditsIfDue($uid, 10, 'P14D');
        }

        // === 3) Auto-réservation des passagers sur les trajets générés ===
        // on alterne pour varier (4,9,4 par ex.)
        $rotate = [4, 9, 4];
        foreach ($rideIds as $i => $rid) {
            if (!$rid) continue;
            $pid = $rotate[$i % count($rotate)];

            $this->bookIfPossible($rid, $pid);
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo "OK\n";
    }

    /** Crée un trajet à la date donnée (anti-doublon) et retourne son id. */
    private function ensureRideBlock(
        int $driverId,
        int $vehicleId,
        string $fromCity,
        string $toCity,
        \DateTimeImmutable $start,
        int $price
    ): ?int {
        // Durée 3h par défaut
        $end = $start->modify('+3 hours');
        $id = \App\Models\Ride::ensureRide(
            $driverId,
            $vehicleId,
            $fromCity,
            $toCity,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $price,
            null     // seats_left = seats du véhicule
        );
        return $id ?: null;
    }

    /** Tente une réservation avec débit des crédits et décrément des places. */
    private function bookIfPossible(int $rideId, int $passengerId): void
    {
        // Déjà réservé ?
        if (Booking::findByRideAndUser($rideId, $passengerId)) return;

        // Récup info trajet (prix / seats_left)
        $ride = \App\Models\Ride::findById($rideId);
        if (!$ride) return;

        $price = (int)($ride['price'] ?? 0);
        $seats = (int)($ride['seats_left'] ?? 0);
        if ($seats <= 0) return;

        // Crédits passager
        $u = \App\Models\User::findById($passengerId);
        $credits = (int)($u['credits'] ?? 0);
        if ($credits < $price) {
            // Pas assez : on laisse tomber (le cron de J-1 remettra +10 si éligible)
            return;
        }

        // Réservation + débit + décrément
        $bid = \App\Models\Booking::create($rideId, $passengerId, $price, 'CONFIRMED');
        if ($bid > 0) {
            \App\Models\User::adjustCredits($passengerId, -$price);
            \App\Models\Ride::decrementSeats($rideId, 1);
        }
    }
}
