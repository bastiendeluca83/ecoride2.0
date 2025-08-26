<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\Sql;
use PDO;

final class Stats
{
    public static function ridesPerDay(string $fromDate, string $toDate): array
    {
        $sql = "
            SELECT DATE(date_start) AS day, COUNT(*) AS rides_count
            FROM rides
            WHERE date_start >= :from AND date_start < :to
            GROUP BY DATE(date_start)
            ORDER BY day ASC
        ";
        $q = Sql::pdo()->prepare($sql);
        $q->execute([':from' => $fromDate, ':to' => $toDate]);
        return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function platformCreditsPerDay(string $fromDate, string $toDate): array
    {
        $sql = "
            SELECT DATE(p.created_at) AS day, SUM(p.credits_taken) AS credits
            FROM penalties_platform p
            WHERE p.created_at >= :from AND p.created_at < :to
            GROUP BY DATE(p.created_at)
            ORDER BY day ASC
        ";
        $q = Sql::pdo()->prepare($sql);
        $q->execute([':from' => $fromDate, ':to' => $toDate]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$rows) {
            $sql2 = "
                SELECT DATE(r.date_start) AS day, (COUNT(*) * 2) AS credits
                FROM rides r
                WHERE r.date_start >= :from AND r.date_start < :to
                GROUP BY DATE(r.date_start)
                ORDER BY day ASC
            ";
            $q2 = Sql::pdo()->prepare($sql2);
            $q2->execute([':from' => $fromDate, ':to' => $toDate]);
            $rows = $q2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return $rows;
    }

    public static function totalPlatformCredits(): int
    {
        $sql = "SELECT COALESCE(SUM(credits_taken), 0) AS total FROM penalties_platform";
        $q = Sql::pdo()->query($sql);
        $total = (int)($q->fetchColumn() ?: 0);
        if ($total > 0) return $total;

        $q2 = Sql::pdo()->query("SELECT COUNT(*) FROM rides");
        $rides = (int)($q2->fetchColumn() ?: 0);
        return $rides * 2;
    }

    public static function kpis(): array
    {
        $pdo = Sql::pdo();
        $users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_suspended = 0")->fetchColumn();
        $ridesUpcoming = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE date_start >= NOW()")->fetchColumn();
        $bookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
        $seatsLeft = (int)$pdo->query("SELECT COALESCE(SUM(seats_left),0) FROM rides")->fetchColumn();

        return [
            'users_active'   => $users,
            'rides_upcoming' => $ridesUpcoming,
            'bookings_total' => $bookings,
            'seats_left_sum' => $seatsLeft,
            'platform_total' => self::totalPlatformCredits(),
        ];
    }
}
