<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Models\Ride;

final class CronController extends BaseController
{
    /** Duplique pour le lendemain les trajets qui viennent de se terminer (1 fois) */
    public function run(): void
    {
        $pdo = Sql::pdo();

        // fenêtre: trajets terminés entre hier 00:00 et maintenant
        $st = $pdo->prepare("
            SELECT id, driver_id, vehicle_id, from_city, to_city, date_start, date_end, price
            FROM rides
            WHERE date_end IS NOT NULL
              AND date_end < NOW()
              AND date_end >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $created = 0;
        foreach ($rows as $r) {
            try {
                $ds = (new \DateTimeImmutable($r['date_start']))->modify('+1 day');
                $de = (new \DateTimeImmutable($r['date_end']))->modify('+1 day');

                // évite doublons (ensureRide)
                $id = Ride::ensureRide(
                    (int)$r['driver_id'],
                    (int)$r['vehicle_id'],
                    (string)$r['from_city'],
                    (string)$r['to_city'],
                    $ds->format('Y-m-d H:i:s'),
                    $de->format('Y-m-d H:i:s'),
                    (int)$r['price'],
                    null // seats -> on reprend les sièges du véhicule
                );
                if ($id) { $created++; }
            } catch (\Throwable $e) { /* on ignore */ }
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo "OK - trajets créés: $created";
    }
}
