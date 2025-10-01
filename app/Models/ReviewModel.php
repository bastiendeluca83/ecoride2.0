<?php
namespace App\Models;

use App\Db\Mongo;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;

/**
 * ReviewModel
 * -----------
 * Modèle MongoDB qui gère les avis laissés par les passagers sur les conducteurs.
 * - Tous les avis sont stockés dans la collection "reviews".
 * - La création passe d'abord par un statut "PENDING", puis un employé doit modérer.
 */
final class ReviewModel
{
    /** Instance de la collection MongoDB (ou null si indisponible) */
    private ?Collection $col;

    public function __construct()
    {
        $db = Mongo::db();
        $this->col = $db ? $db->selectCollection('reviews') : null;
    }

    /** Accès interne à la collection */
    private function c(): ?Collection
    {
        return $this->col;
    }

    /**
     * Crée un avis en statut PENDING.
     * Les champs principaux sont normalisés et complétés avec des dates.
     */
    public function create(array $data): bool
    {
        if (!$this->c()) return false;

        $doc = [
            'ride_id'       => (int)($data['ride_id'] ?? 0),
            'driver_id'     => (int)($data['driver_id'] ?? 0),
            'passenger_id'  => (int)($data['passenger_id'] ?? 0),
            'note'          => max(1, min(5, (int)($data['note'] ?? 0))),
            'comment'       => (string)($data['comment'] ?? ''),
            'status'        => 'PENDING',
            'token_id'      => (string)($data['token_id'] ?? ''),
            'meta'          => (array)($data['meta'] ?? []),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
            'moderated_by'  => null,
            'moderated_at'  => null,
            'reject_reason' => null,
        ];

        $res = $this->c()->insertOne($doc);
        return (bool)$res->getInsertedId();
    }

    /**
     * Vérifie si un passager a déjà déposé un avis pour un trajet donné.
     * Permet d’éviter les doublons.
     */
    public function existsByRidePassenger(int $rideId, int $passengerId): bool
    {
        if (!$this->c()) return false;

        $count = $this->c()->countDocuments([
            'ride_id'      => $rideId,
            'passenger_id' => $passengerId,
        ]);
        return $count > 0;
    }

    /**
     * Récupère une liste d’avis APPROVED d’un conducteur.
     * Triés du plus récent au plus ancien, avec un nombre maximum.
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
     * Calcule la moyenne des notes APPROVED d’un conducteur.
     * Retourne null si aucune note n’est disponible.
     */
    public function avgForDriver(int $driverId): ?float
    {
        if (!$this->c()) return null;

        $pipeline = [
            ['$match' => ['driver_id' => $driverId, 'status' => 'APPROVED']],
            ['$group' => ['_id' => '$driver_id', 'avg' => ['$avg' => '$note']]],
        ];

        $cursor = $this->c()->aggregate($pipeline);
        $res = iterator_to_array($cursor, false);

        if (!$res) return null;

        $avg = (float)($res[0]['avg'] ?? 0);
        return $avg > 0 ? round($avg, 1) : null;
    }

    /**
     * Récupère les avis en attente (PENDING).
     * Utilisé par l’employé pour la modération.
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
                'id'           => (string)($doc['_id'] ?? ''),
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
     * Approuve un avis en attente.
     * Met à jour le statut et renseigne l’employé modérateur.
     */
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

    /**
     * Rejette un avis en attente.
     * Permet de stocker un motif de rejet.
     */
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

    /**
     * Vérifie que l’ID donné est bien un ObjectId MongoDB valide.
     */
    private function isValidObjectId(string $id): bool
    {
        try {
            new ObjectId($id);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
