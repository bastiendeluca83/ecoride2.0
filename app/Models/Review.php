<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\Mongo;
use MongoDB\Collection;

/**
 * Modèle Review (MongoDB)
 * -----------------------
 * Gère les avis passagers sur les conducteurs.
 * - Stockage NoSQL (collection "reviews").
 * - Modération par un employé (PENDING → APPROVED/REJECTED).
 */
final class Review
{
    /** Collection MongoDB (null si indisponible) */
    private ?Collection $col;

    public function __construct()
    {
        // Récupère la connexion et pointe sur la collection "reviews".
        $db = Mongo::db();
        $this->col = $db ? $db->selectCollection('reviews') : null;
    }

    /** Raccourci interne vers la collection (évite de répéter la null-check). */
    private function c(): ?Collection
    {
        return $this->col;
    }

    /**
     * Crée un avis en statut PENDING (en attente de modération).
     * Champs attendus: ride_id, driver_id, passenger_id, note(1..5), comment, token_id, meta[]
     */
    public function create(array $data): bool
    {
        if (!$this->c()) return false;

        $now = date('Y-m-d H:i:s');
        $doc = [
            'ride_id'       => (int)($data['ride_id'] ?? 0),
            'driver_id'     => (int)($data['driver_id'] ?? 0),
            'passenger_id'  => (int)($data['passenger_id'] ?? 0),
            'note'          => max(1, min(5, (int)($data['note'] ?? 0))),
            'comment'       => (string)($data['comment'] ?? ''),
            'status'        => 'PENDING',
            'token_id'      => (string)($data['token_id'] ?? ''),
            'meta'          => (array)($data['meta'] ?? []),
            'created_at'    => $now,
            'updated_at'    => $now,
            'moderated_by'  => null,
            'moderated_at'  => null,
            'reject_reason' => null,
        ];

        $res = $this->c()->insertOne($doc);
        return (bool) $res->getInsertedId();
    }

    /**
     * Vérifie l'existence d'un avis pour un couple (trajet, passager).
     * Évite les doublons: un passager ne dépose pas 2 avis sur le même trajet.
     */
    public function existsByRidePassenger(int $rideId, int $passengerId): bool
    {
        if (!$this->c()) return false;

        return $this->c()->countDocuments([
            'ride_id'      => $rideId,
            'passenger_id' => $passengerId,
        ]) > 0;
    }

    /**
     * Récupère des avis APPROVED d’un conducteur (affichage public).
     * Tri du plus récent au plus ancien, limité (par défaut 10).
     */
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

    /**
     * Moyenne des notes APPROVED pour un conducteur.
     * Retourne null si aucune note.
     */
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

    /* ======= Helpers de listing ======= */

    /**
     * Retourne un tableau indexé par driver_id avec:
     *   ['avg' => moyenne arrondie à 0.1, 'count' => nombre d'avis]
     * pour un ensemble d'IDs.
     */
    public function avgForDrivers(array $driverIds): array
    {
        if (!$this->c() || empty($driverIds)) return [];

        $driverIds = array_values(array_unique(array_map('intval', $driverIds)));

        $pipeline = [
            ['$match' => ['status' => 'APPROVED', 'driver_id' => ['$in' => $driverIds]]],
            ['$group' => [
                '_id'   => '$driver_id',
                'avg'   => ['$avg' => '$note'],
                'count' => ['$sum' => 1],
            ]],
        ];

        $out = [];
        foreach ($this->c()->aggregate($pipeline) as $row) {
            $did = (int)($row['_id'] ?? 0);
            $avg = isset($row['avg']) ? round((float)$row['avg'], 1) : null;
            $cnt = (int)($row['count'] ?? 0);
            if ($did > 0 && $avg !== null) {
                $out[$did] = ['avg' => $avg, 'count' => $cnt];
            }
        }
        return $out;
    }

    /**
     * Les N derniers avis APPROVED d’un conducteur (pour encart trajet).
     */
    public function recentApprovedForDriver(int $driverId, int $limit = 3): array
    {
        return $this->findByDriverApproved($driverId, $limit);
    }

    /**
     * Liste des avis en attente (PENDING) pour l’interface employé.
     * Tri du plus ancien au plus récent (facilite la file de modération).
     */
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

    /**
     * Approuve un avis PENDING.
     * Renseigne le modérateur et les timestamps.
     */
    public function approve(string $id, int $moderatorId): bool
    {
        if (!$this->c() || !$this->isValidObjectId($id)) return false;

        $now = date('Y-m-d H:i:s');
        $res = $this->c()->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id), 'status' => 'PENDING'],
            ['$set' => [
                'status'       => 'APPROVED',
                'moderated_by' => $moderatorId,
                'moderated_at' => $now,
                'updated_at'   => $now,
            ]]
        );

        return $res->getModifiedCount() > 0;
    }

    /**
     * Rejette un avis PENDING avec un motif optionnel.
     */
    public function reject(string $id, int $moderatorId, string $reason = ''): bool
    {
        if (!$this->c() || !$this->isValidObjectId($id)) return false;

        $now = date('Y-m-d H:i:s');
        $res = $this->c()->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id), 'status' => 'PENDING'],
            ['$set' => [
                'status'        => 'REJECTED',
                'reject_reason' => $reason,
                'moderated_by'  => $moderatorId,
                'moderated_at'  => $now,
                'updated_at'    => $now,
            ]]
        );

        return $res->getModifiedCount() > 0;
    }

    /** Valide visuellement un ObjectId (évite les exceptions au new ObjectId). */
    private function isValidObjectId(string $id): bool
    {
        try {
            new \MongoDB\BSON\ObjectId($id);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
