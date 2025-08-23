<?php
namespace App\Models;


use PDO;


class Vehicle extends BaseModels
{
public static function create(int $userId, string $plaque, string $marque, string $modele, string $couleur, string $energie, string $dateImmat, int $places): int {
$sql = "INSERT INTO vehicles(user_id,plaque,marque,modele,couleur,energie,date_immat,places_disponibles,created_at) VALUES(:u,:p,:ma,:mo,:c,:e,:di,:pl,NOW())";
$pdo = self::pdo();
$pdo->prepare($sql)->execute([':u'=>$userId,':p'=>$plaque,':ma'=>$marque,':mo'=>$modele,':c'=>$couleur,':e'=>$energie,':di'=>$dateImmat,':pl'=>$places]);
return (int)$pdo->lastInsertId();
}


public static function findById(int $id): ?array { return self::one("SELECT * FROM vehicles WHERE id=:id", [':id'=>$id]); }


public static function listByUser(int $userId): array { return self::all("SELECT * FROM vehicles WHERE user_id=:u ORDER BY created_at DESC", [':u'=>$userId]); }


public static function update(int $id, array $fields): bool {
$allowed=['plaque','marque','modele','couleur','energie','date_immat','places_disponibles'];
$set=[];$params=[':id'=>$id];
foreach ($fields as $k=>$v) if (in_array($k,$allowed,true)) { $set[]="$k=:$k"; $params[":$k"]=$v; }
if (!$set) return false;
$sql='UPDATE vehicles SET '.implode(', ',$set).' WHERE id=:id';
return self::pdo()->prepare($sql)->execute($params);
}


public static function delete(int $id): bool { return self::pdo()->prepare("DELETE FROM vehicles WHERE id=:id")->execute([':id'=>$id]); }
}
