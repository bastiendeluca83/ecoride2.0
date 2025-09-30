<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\UserPreferences;
use App\Models\Review;
use PDO;

final class PublicProfileController extends BaseController
{
    /**
     * GET /users/profile?id=<driver_id>
     * Profil public d'un conducteur avec note + avis (APPROVED) depuis Mongo.
     */
    public function show(): void
    {
        $driverId = (int)($_GET['id'] ?? 0);
        if ($driverId <= 0) {
            http_response_code(404);
            $this->render('pages/public_profile', [
                'title'          => 'Profil conducteur',
                'driver'         => null,
                'vehicles'       => [],
                'prefs'          => [],
                'avg'            => null,
                'count'          => 0,
                'distribution'   => [5=>0,4=>0,3=>0,2=>0,1=>0],
                'reviews_recent' => [],
            ]);
            return;
        }

        // --- Identité conducteur (SQL)
        $driver = User::findById($driverId);
        if (!$driver) {
            http_response_code(404);
            $this->render('pages/public_profile', [
                'title'          => 'Profil conducteur',
                'driver'         => null,
                'vehicles'       => [],
                'prefs'          => [],
                'avg'            => null,
                'count'          => 0,
                'distribution'   => [5=>0,4=>0,3=>0,2=>0,1=>0],
                'reviews_recent' => [],
            ]);
            return;
        }

        // --- Véhicules du conducteur
        $vehicles = [];
        if (class_exists(Vehicle::class) && method_exists(Vehicle::class, 'forUser')) {
            $vehicles = Vehicle::forUser($driverId) ?: [];
        } else {
            // Fallback SQL simple si besoin
            try {
                $pdo = Sql::pdo();
                $st = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = :u ORDER BY id DESC");
                $st->execute([':u' => $driverId]);
                $vehicles = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $vehicles = [];
            }
        }

        // --- Préférences (tolérant aux différents noms de méthodes)
        $prefs = [];
        foreach (['get','findByUserId','forUser'] as $m) {
            if (class_exists(UserPreferences::class) && method_exists(UserPreferences::class, $m)) {
                $prefs = UserPreferences::$m($driverId) ?? [];
                break;
            }
        }

        // --- Notes & avis (Mongo)
        $avg = null;
        $count = 0;
        $distribution = [5=>0,4=>0,3=>0,2=>0,1=>0];
        $recent = [];

        try {
            if (class_exists(Review::class)) {
                $rm = new Review();

                // Moyenne arrondie à 0.1 (implémentation côté modèle)
                $avg = $rm->avgForDriver($driverId);

                // Derniers avis approuvés
                $recent = $rm->recentApprovedForDriver($driverId, 5);

                // Distribution + count : si le modèle n'a pas de méthode dédiée,
                // on la recompose depuis la liste complète approuvée (limite raisonnable).
                if (method_exists($rm, 'distributionForDriver')) {
                    $distribution = $rm->distributionForDriver($driverId) + [5=>0,4=>0,3=>0,2=>0,1=>0];
                    $count = array_sum($distribution);
                } else {
                    $allApproved = $rm->findByDriverApproved($driverId, 500);
                    foreach ($allApproved as $rv) {
                        $n = (int)($rv['note'] ?? $rv['rating'] ?? 0);
                        if ($n >= 1 && $n <= 5) { $distribution[$n]++; $count++; }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Si Mongo indisponible, on affiche juste le profil sans note.
        }

        $this->render('pages/public_profile', [
            'title'          => 'Profil conducteur',
            'driver'         => $driver,
            'vehicles'       => $vehicles,
            'prefs'          => $prefs,
            'avg'            => $avg,
            'count'          => $count,
            'distribution'   => $distribution,
            'reviews_recent' => $recent,
        ]);
    }
}
