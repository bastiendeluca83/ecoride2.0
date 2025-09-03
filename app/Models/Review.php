<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\Mongo;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

final class Review
{
    private ?Collection $col;

    public function __construct()
    {
        $db = Mongo::db();
        $this->col = $db ? $db->selectCollection('reviews') : null;
    }

    private function c(): ?Collection { return $this->col; }

    /** Crée un avis en statut PENDING (modération employé). */
    public function create(array $data): bool
    {
        if (!$this->c()) return false;

        $doc = [
            'ride_id'      => (int)($data['ride_id'] ?? 0),
            'driver_id'    => (int)($data['driver_id'] ?? 0),
            'passenger_id' => (int)($data['passenger_id'] ?? 0),
            'note'         => max(1, min(5, (int)($data['note'] ?? 0))),
            'comment'      => (string)($data['comment'] ?? ''),
            'status'       => 'PENDING',
            'token_id'     => (string)($data['token_id'] ?? ''),
            'meta'         => (array)($data['meta'] ?? []),
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
            'moderated_by' => null,
            'moderated_at' => null,
            'reject_reason'=> null,
        ];

        $res = $this->c()->insertOne($doc);
        return (bool)$res->getInsertedId();
    }

    /** Empêche les doublons pour un couple (ride,passenger). */
    public function existsByRidePassenger(int $rideId, int $passengerId): bool
    {
        if (!$this->c()) return false;
        return $this->c()->countDocuments([
            'ride_id'      => $rideId,
            'passenger_id' => $passengerId,
        ]) > 0;
    }

    /** Avis APPROVED d’un conducteur (pour affichage public). */
    public function findByDriverApproved(int $driverId, int $limit = 10): array
    {
        if (!$this->c()) return [];
        $cursor = $this->c()->find(
            ['driver_id' => $driverId, 'status' => 'APPROVED'],
            ['sort' => ['created_at' => -1], 'limit' => $limit]
        );
        $out = [];
        foreach ($cursor as $doc) {
            $out[] = [
                'note'       => (int)($doc['note'] ?? 0),
                'comment'    => (string)($doc['comment'] ?? ''),
                'created_at' => (string)($doc['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    /** Moyenne des notes APPROVED pour un conducteur. */
    public function avgForDriver(int $driverId): ?float
    {
        if (!$this->c()) return null;
        $pipeline = [
            ['$match' => ['driver_id' => $driverId, 'status' => 'APPROVED']],
            ['$group' => ['_id' => '$driver_id', 'avg' => ['$avg' => '$note']]],
        ];
        $res = iterator_to_array($this->c()->aggregate($pipeline), false);
        if (!$res) return null;
        $avg = (float)($res[0]['avg'] ?? 0);
        return $avg > 0 ? round($avg, 1) : null;
    }

    /** Avis en attente pour l’employé. */
    public function findPending(int $limit = 100): array
    {
        if (!$this->c()) return [];
        $cursor = $this->c()->find(
            ['status' => 'PENDING'],
            ['sort' => ['created_at' => 1], 'limit' => $limit]
        );
        $out = [];
        foreach ($cursor as $doc) {
            $out[] = [
                'id'           => isset($doc['_id']) ? (string)$doc['_id'] : '',
                'ride_id'      => (int)($doc['ride_id'] ?? 0),
                'driver_id'    => (int)($doc['driver_id'] ?? 0),
                'passenger_id' => (int)($doc['passenger_id'] ?? 0),
                'note'         => (int)($doc['note'] ?? 0),
                'comment'      => (string)($doc['comment'] ?? ''),
                'created_at'   => (string)($doc['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    public function approve(string $id, int $moderatorId): bool
    {
        if (!$this->c() || !$this->isValidObjectId($id)) return false;
        $res = $this->c()->updateOne(
            ['_id' => new ObjectId($id), 'status' => 'PENDING'],
            ['$set' => [
                'status'       => 'APPROVED',
                'moderated_by' => $moderatorId,
                'moderated_at' => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]]
        );
        return $res->getModifiedCount() > 0;
    }

    public function reject(string $id, int $moderatorId, string $reason = ''): bool
    {
        if (!$this->c() || !$this->isValidObjectId($id)) return false;
        $res = $this->c()->updateOne(
            ['_id' => new ObjectId($id), 'status' => 'PENDING'],
            ['$set' => [
                'status'        => 'REJECTED',
                'reject_reason' => $reason,
                'moderated_by'  => $moderatorId,
                'moderated_at'  => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]]
        );
        return $res->getModifiedCount() > 0;
    }

    private function isValidObjectId(string $id): bool
    {
        try { new ObjectId($id); return true; }
        catch (\Throwable $e) { return false; }
    }
}
