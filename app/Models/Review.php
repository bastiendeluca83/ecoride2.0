<?php
namespace App\Models;


use PDO;


class Review extends BaseModels
{
public static function create(int $chauffeurId,int $passagerId,int $note,string $comment,string $status='EN_ATTENTE'): int {
$pdo = self::pdo();
$pdo->prepare("INSERT INTO avis(chauffeur_id,passager_id,note,commentaire,status,created_at) VALUES(:c,:p,:n,:m,:s,NOW())")
->execute([':c'=>$chauffeurId, ':p'=>$passagerId, ':n'=>$note, ':m'=>$comment, ':s'=>$status]);
return (int)$pdo->lastInsertId();
}


public static function validate(int $id): bool { return self::pdo()->prepare("UPDATE avis SET status='VALIDE' WHERE id=:id")->execute([':id'=>$id]); }


public static function refuse(int $id): bool { return self::pdo()->prepare("UPDATE avis SET status='REFUSE' WHERE id=:id")->execute([':id'=>$id]); }


public static function listByDriver(int $chauffeurId,string $status='VALIDE'): array { return self::all("SELECT * FROM avis WHERE chauffeur_id=:c AND status=:s ORDER BY created_at DESC", [':c'=>$chauffeurId, ':s'=>$status]); }
}



