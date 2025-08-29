<?php
namespace App\Models;

use App\Db\Sql;
use PDO;

final class Stats
{
    private static function pdo(): \PDO { return Sql::pdo(); }

    public static function kpis(): array
    {
        $pdo = self::pdo();

        $users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        $ridesUpcoming = (int)$pdo->query("
            SELECT COUNT(*) FROM rides WHERE date_start >= NOW()
        ")->fetchColumn();

        $bookings = (int)$pdo->query("
            SELECT COUNT(*) FROM bookings WHERE status='CONFIRMED'
        ")->fetchColumn();

        $seatsLeft = (int)$pdo->query("
            SELECT COALESCE(SUM(seats_left),0) FROM rides WHERE date_start >= NOW()
        ")->fetchColumn();

        $platformCredits = (int)$pdo->query("
            SELECT COALESCE(SUM(montant),0)
            FROM transactions
            WHERE type IN ('PLATFORM','PLATFORM_FEE')
        ")->fetchColumn();

        return [
            'users'           => $users,
            'rides_upcoming'  => $ridesUpcoming,
            'bookings'        => $bookings,
            'seats_left'      => $seatsLeft,
            'platform_credits'=> $platformCredits,
        ];
    }

    public static function ridesPerDay(string $from, string $to): array
    {
        $sql = "SELECT DATE(date_start) AS jour, COUNT(*) AS nombre
                FROM rides
                WHERE DATE(date_start) BETWEEN :f AND :t
                GROUP BY DATE(date_start)
                ORDER BY jour ASC";
        $st = self::pdo()->prepare($sql);
        $st->execute([':f'=>$from, ':t'=>$to]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function platformCreditsPerDay(string $from, string $to): array
    {
        $sql = "SELECT DATE(created_at) AS jour, COALESCE(SUM(montant),0) AS credits
                FROM transactions
                WHERE type IN ('PLATFORM','PLATFORM_FEE')
                  AND DATE(created_at) BETWEEN :f AND :t
                GROUP BY DATE(created_at)
                ORDER BY jour ASC";
        $st = self::pdo()->prepare($sql);
        $st->execute([':f'=>$from, ':t'=>$to]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
