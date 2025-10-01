<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Security\Security;
use PDO;

/**
 * Contrôleur de réservation (Booking).
 * Rôle : confirmer une réservation de trajet en débitant des crédits
 *        et en décrémentant le nombre de places restantes.
 * Important : je gère tout en transaction pour garantir l'atomicité.
 */
final class BookingController extends BaseController
{
    /**
     * Action POST /rides/book
     * Attend un champ form 'ride_id' (identifiant du trajet).
     */
    public function confirm(): void
    {
        // 1) Sécurité de base : utilisateur connecté obligatoire.
        //    Si non connecté, je le renvoie vers /login avec un redirect vers /rides.
        if (!Security::check()) { header('Location: /login?redirect=/rides'); exit; }

        // 2) Je n'accepte QUE la méthode POST (anti-CSRF / respect REST).
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Méthode non autorisée';
            return;
        }

        // 3) Ouverture de session + vérification CSRF
        if (session_status() === PHP_SESSION_NONE) session_start();
        //    Ici : si le token est présent et ne matche pas, je bloque (session expirée).
        //    (NB : si le token est manquant, on laisse passer dans cette version ; je garde le comportement existant)
        if (isset($_POST['csrf'], $_SESSION['csrf']) && !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
            http_response_code(400);
            $this->render('rides/detail', ['title'=>'Trajet', 'error'=>'Session expirée (CSRF).']);
            return;
        }

        // 4) Récupération des identifiants nécessaires
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $rideId = (int)($_POST['ride_id'] ?? 0);

        //    Je valide que tout est bien fourni
        if ($userId <= 0 || $rideId <= 0) {
            http_response_code(400);
            $this->render('rides/detail', ['title'=>'Trajet', 'error'=>'Requête invalide (ride_id manquant).']);
            return;
        }

        // 5) Accès PDO + mode Exception explicite (pratique pour le rollback)
        $pdo = Sql::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            // ---------------------------------------
            // 6) Début de transaction pour garantir :
            //    - cohérence des crédits
            //    - cohérence du nombre de places
            //    - insertion de la réservation
            //    => tout ou rien
            // ---------------------------------------
            $pdo->beginTransaction();

            // 6.1) Verrouillage pessimiste du trajet (FOR UPDATE)
            //      Objectif : éviter les surventes si plusieurs users réservent en même temps.
            $q = $pdo->prepare("SELECT id, price, seats_left FROM rides WHERE id=? FOR UPDATE");
            $q->execute([$rideId]);
            $ride = $q->fetch(PDO::FETCH_ASSOC);

            //      Je valide l’existence et la disponibilité du trajet
            if (!$ride) { throw new \RuntimeException("Trajet introuvable."); }
            if ((int)$ride['seats_left'] <= 0) { throw new \RuntimeException("Plus de place."); }

            // 6.2) Verrouillage pessimiste de l'utilisateur (FOR UPDATE) pour ses crédits
            $q = $pdo->prepare("SELECT credits FROM users WHERE id=? FOR UPDATE");
            $q->execute([$userId]);
            $credits = (int)$q->fetchColumn();

            //      Prix du trajet (en crédits)
            $price   = (int)$ride['price'];

            //      Je vérifie que l'utilisateur a assez de crédits
            if ($credits < $price) { throw new \RuntimeException("Crédits insuffisants."); }

            // 6.3) Insertion de la réservation (status confirmé d’emblée)
            $stmt = $pdo->prepare("
                INSERT INTO bookings (user_id, ride_id, credits_spent, status, created_at)
                VALUES (?, ?, ?, 'confirmed', NOW())
            ");
            $stmt->execute([$userId, $rideId, $price]);

            // 6.4) Mise à jour du nombre de places du trajet
            $pdo->prepare("UPDATE rides SET seats_left = seats_left - 1 WHERE id=?")->execute([$rideId]);

            //      Mise à jour des crédits de l’utilisateur (débit)
            $pdo->prepare("UPDATE users SET credits    = credits    - ? WHERE id=?")->execute([$price, $userId]);

            // 6.5) Tout s’est bien passé → je valide la transaction
            $pdo->commit();

            // 7) Petit message de succès en session + redirection vers l'espace utilisateur
            $_SESSION['flash_success'] = "Réservation confirmée !";
            header('Location: /user/dashboard');
            exit;

        } catch (\Throwable $e) {
            // En cas d'exception : je reviens à l'état initial
            if ($pdo->inTransaction()) { $pdo->rollBack(); }

            // J’affiche l’erreur sur la vue de détail du trajet (message contrôlé côté vue)
            $this->render('rides/detail', ['title'=>'Trajet', 'error'=>$e->getMessage()]);
        }
    }
}
