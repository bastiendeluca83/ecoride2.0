<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\Sql;
use PDO;

final class Review
{
    private static function pdo(): PDO { return Sql::pdo(); }

    public static function summaryForUser(int $userId): array
    {
        $st = self::pdo()->prepare(
            "SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS cnt
               FROM reviews WHERE target_user_id=:u AND status='APPROVED'"
        );
        $st->execute([':u'=>$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['avg_rating'=>null,'cnt'=>0];
        return ['avg'=> (float)($row['avg_rating'] ?? 0), 'count'=>(int)$row['cnt']];
    }

    public static function recentForUser(int $userId, int $limit=3): array
    {
        $st = self::pdo()->prepare(
          "SELECT r.id, r.rating, r.comment, r.role, r.created_at,
                  u.nom AS reviewer_name
             FROM reviews r
             JOIN users u ON u.id = r.reviewer_user_id
            WHERE r.target_user_id=:u AND r.status='APPROVED'
            ORDER BY r.created_at DESC
            LIMIT :lim"
        );
        $st->bindValue(':u', $userId, PDO::PARAM_INT);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
