<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\UserPreferences;
use App\Models\Review;
use PDO;

/**
 * PublicProfileController
 * - Affiche le profil PUBLIC d'un conducteur (accessible aux visiteurs).
 * - Je récupère l'identité SQL, ses véhicules, ses préférences, puis sa note/avis (Mongo).
 * - J'envoie un paquet de données propre à la vue, sans logique de présentation ici.
 */
final class PublicProfileController extends BaseController
{
    /**
     * GET /users/profile?id=<driver_id>
     * - Je charge la fiche conducteur + sa note moyenne + quelques avis approuvés.
     * - Si id invalide ou conducteur introuvable, j'affiche la vue avec état "vide".
     */
    public function show(): void
    {
        $driverId = (int)($_GET['id'] ?? 0);

        // id manquant → je renvoie une page "vide" mais avec un 404 correct
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
            // conducteur non trouvé → même principe: 404 + vue propre
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
        // Je privilégie la méthode du modèle si elle existe, sinon je retombe sur un SQL simple.
        $vehicles = [];
        if (class_exists(Vehicle::class) && method_exists(Vehicle::class, 'forUser')) {
            $vehicles = Vehicle::forUser($driverId) ?: [];
        } else {
            try {
                $pdo = Sql::pdo();
                $st = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = :u ORDER BY id DESC");
                $st->execute([':u' => $driverId]);
                $vehicles = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                // si la table n'existe pas encore ou autre souci, je reste silencieux ici
                $vehicles = [];
            }
        }

        // --- Préférences conducteur (je teste plusieurs noms de méthodes pour rester tolérant)
        $prefs = [];
        foreach (['get','findByUserId','forUser'] as $m) {
            if (class_exists(UserPreferences::class) && method_exists(UserPreferences::class, $m)) {
                $prefs = UserPreferences::$m($driverId) ?? [];
                break;
            }
        }

        // --- Notes & avis (Mongo)
        $avg = null;                                // moyenne arrondie à 0.1 (géré côté modèle)
        $count = 0;                                 // total d'avis approuvés
        $distribution = [5=>0,4=>0,3=>0,2=>0,1=>0]; // répartition 1..5 pour l'affichage
        $recent = [];                               // derniers avis (ex: 5 plus récents)

        try {
            if (class_exists(Review::class)) {
                $rm = new Review();

                // moyenne globale du conducteur
                $avg = $rm->avgForDriver($driverId);

                // 5 avis approuvés les plus récents (pour un aperçu rapide)
                $recent = $rm->recentApprovedForDriver($driverId, 5);

                // Si le modèle expose une distribution directe, je l'utilise.
                // Sinon je recompose à partir d'une liste raisonnable d'avis approuvés.
                if (method_exists($rm, 'distributionForDriver') && is_callable([$rm, 'distributionForDriver'])) {
                    // je m'assure que toutes les clés 1..5 existent
                    $distributionResult = $rm->distributionForDriver($driverId);
                    if (is_array($distributionResult)) {
                        $distribution = $distributionResult + [5=>0,4=>0,3=>0,2=>0,1=>0];
                        $count = array_sum($distribution);
                    } else {
                        $distribution = [5=>0,4=>0,3=>0,2=>0,1=>0];
                        $count = 0;
                    }
                } else {
                    $allApproved = $rm->findByDriverApproved($driverId, 500); // limite "safe"
                    foreach ($allApproved as $rv) {
                        $n = (int)($rv['note'] ?? $rv['rating'] ?? 0);
                        if ($n >= 1 && $n <= 5) { $distribution[$n]++; $count++; }
                    }
                }
            }
        } catch (\Throwable $e) {
            // si Mongo ou le modèle n'est pas dispo, je n'affiche simplement pas la partie "notes"
            // l'objectif: ne jamais casser la page publique.
        }

        // Je passe toutes les infos à la vue "Profil public"
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
