<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Services\Mailer;
use App\Models\Review; // ✅ je charge le modèle qui lit les notes (Mongo)
use PDO;

/**
 * RideController
 * - Liste/filtre des trajets (list)
 * - Vue catalogue /covoiturage (prochains + 30 derniers jours)
 * - Détail d’un trajet (show)
 * - Réservation (book) avec gestion des crédits + transactions + mails
 *
 * Je reste strict MVC : ici je prépare les données, la vue s’occupe du rendu.
 */
class RideController extends BaseController
{
    /** Page d’accueil très simple (SEO + cohérence MVC) */
    public function home(): void
    {
        $this->render('home/index', ['title' => 'EcoRide – Covoiturage écoresponsable']);
    }

    /**
     * Liste avec filtres (US 3 + US 4)
     * - Filtres: from_city, to_city, date_start, eco_only, price_max, duration_max, min_note
     * - Je filtre d’abord côté SQL (places dispo, ville, date, prix, durée, électricité)
     * - Puis j’injecte les notes (Mongo) et j’applique min_note côté PHP
     */
    public function list(): void
    {
        $pdo = Sql::pdo();

        // je récupère les filtres depuis GET ou POST (tolérant)
        $from    = trim($_GET['from_city']  ?? $_POST['from_city']  ?? '');
        $to      = trim($_GET['to_city']    ?? $_POST['to_city']    ?? '');
        $date    = trim($_GET['date_start'] ?? $_POST['date_start'] ?? '');
        $ecoOnly = !empty($_GET['eco_only'] ?? $_POST['eco_only']   ?? '');

        $priceMax    = isset($_GET['price_max'])    ? (int)$_GET['price_max']    : null;
        $durationMax = isset($_GET['duration_max']) ? (int)$_GET['duration_max'] : null;
        $minNote     = isset($_GET['min_note'])     ? (float)$_GET['min_note']   : null;

        // je construis ma clause WHERE progressivement
        $where  = ["r.seats_left > 0"]; // je ne propose que des trajets avec au moins une place
        $params = [];

        if ($from !== '') { $where[] = "r.from_city LIKE :from"; $params[':from'] = "%$from%"; }
        if ($to   !== '') { $where[] = "r.to_city   LIKE :to";   $params[':to']   = "%$to%"; }
        if ($date !== '') { $where[] = "DATE(r.date_start) = :d"; $params[':d']   = $date; }

        // filtre éco: j’utilise le cache is_electric_cached quand dispo, sinon je déduis via vehicles.energy
        if ($ecoOnly) {
            $where[] = "(COALESCE(r.is_electric_cached, CASE WHEN UPPER(v.energy)='ELECTRIC' THEN 1 ELSE 0 END)) = 1";
        }
        if ($priceMax !== null && $priceMax > 0) {
            $where[] = "r.price <= :pmax"; $params[':pmax'] = $priceMax;
        }
        if ($durationMax !== null && $durationMax > 0) {
            $where[] = "TIMESTAMPDIFF(HOUR, r.date_start, r.date_end) <= :dmax";
            $params[':dmax'] = $durationMax;
        }

        // je sélectionne toutes les infos utiles pour l’affichage (conducteur, avatar, préférences…)
        $sql = "
        SELECT
          r.id, r.from_city, r.to_city, r.date_start, r.date_end,
          r.price, r.seats_left, r.driver_id,
          COALESCE(r.is_electric_cached, CASE WHEN UPPER(v.energy)='ELECTRIC' THEN 1 ELSE 0 END) AS is_eco,

          TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS driver_display_name,
          u.avatar_path AS driver_avatar,

          up.smoker, up.animals, up.music, up.chatty, up.ac
        FROM rides r
        JOIN users u                  ON u.id = r.driver_id
        LEFT JOIN vehicles v          ON v.id = r.vehicle_id
        LEFT JOIN user_preferences up ON up.user_id = r.driver_id
        " . (count($where) ? "WHERE " . implode(' AND ', $where) : "") . "
        ORDER BY r.date_start ASC
        LIMIT 50
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rides = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        /* ==== J’injecte les notes depuis Mongo (APPROVED only) et j’applique min_note côté PHP ==== */
        try {
            $rm = new Review();
            $driverIds = array_values(array_unique(array_map(fn($r)=>(int)$r['driver_id'], $rides)));
            $ratingsMap = $rm->avgForDrivers($driverIds); // format: [driver_id => ['avg'=>x.x,'count'=>n]]

            foreach ($rides as &$r) {
                $did = (int)$r['driver_id'];
                $r['rating_avg']   = isset($ratingsMap[$did]) ? (float)$ratingsMap[$did]['avg']   : null;
                $r['rating_count'] = isset($ratingsMap[$did]) ? (int)$ratingsMap[$did]['count']   : 0;
            }
            unset($r);

            // si min_note est demandé, je filtre ici (post-traitement des données SQL)
            if ($minNote !== null) {
                $rides = array_values(array_filter($rides, function($r) use ($minNote) {
                    if (!isset($r['rating_avg'])) return false; // j’exclus ceux sans note
                    return (float)$r['rating_avg'] >= (float)$minNote;
                }));
            }
        } catch (\Throwable $e) {
            // Pas bloquant si Mongo est indisponible ou si le modèle n’expose pas les méthodes attendues
        }

        // si pas de résultats, je propose la date la + proche (US 3)
        $suggestion = null;
        if (!$rides && $from !== '' && $to !== '') {
            $st2 = $pdo->prepare("
                SELECT id, from_city, to_city, date_start
                FROM rides
                WHERE from_city LIKE :from AND to_city LIKE :to AND date_start > NOW()
                ORDER BY date_start ASC
                LIMIT 1
            ");
            $st2->execute([':from'=>"%$from%", ':to'=>"%$to%"]);
            $suggestion = $st2->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // j’envoie tout à la vue
        $this->render('rides/list', [
            'title'      => 'Covoiturages – Résultats',
            'rides'      => $rides,
            'filters'    => compact('from','to','date','ecoOnly','priceMax','durationMax','minNote'),
            'suggestion' => $suggestion,
        ]);
    }

    /**
     * /covoiturage
     * - Prochains trajets (places > 0)
     * - Trajets terminés sur les 30 derniers jours (peut servir d’historique public)
     * - Injection des notes (moyenne + count) pour chaque conducteur
     */
    public function covoiturage(): void
    {
        $pdo = Sql::pdo();

        // Prochains trajets (affichage catalogue)
        $st = $pdo->prepare("
            SELECT
              r.*,
              TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS driver_display_name,
              u.avatar_path AS driver_avatar,
              v.brand, v.model, v.energy,
              p.smoker, p.animals, p.music, p.chatty, p.ac
            FROM rides r
            JOIN users u              ON u.id = r.driver_id
            LEFT JOIN vehicles v      ON v.id = r.vehicle_id
            LEFT JOIN user_preferences p ON p.user_id = u.id
            WHERE r.date_start >= NOW() AND r.seats_left > 0
            ORDER BY r.date_start ASC
        ");
        $st->execute();
        $ridesUpcoming = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Trajets terminés dans les 30 derniers jours (pour visibilité + SEO)
        $st2 = $pdo->prepare("
            SELECT
              r.*,
              TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS driver_display_name,
              u.avatar_path AS driver_avatar,
              v.brand, v.model, v.energy
            FROM rides r
            JOIN users u         ON u.id = r.driver_id
            LEFT JOIN vehicles v ON v.id = r.vehicle_id
            WHERE r.date_end < NOW()
              AND r.date_end >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY r.date_end DESC
        ");
        $st2->execute();
        $ridesPast30 = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

        /* ✅ J’ajoute les moyennes de notes (Mongo) pour les deux listes */
        try {
            $rm = new Review();
            $ids = array_unique(array_merge(
                array_map(fn($r)=>(int)$r['driver_id'], $ridesUpcoming),
                array_map(fn($r)=>(int)$r['driver_id'], $ridesPast30)
            ));
            $map = $rm->avgForDrivers($ids);

            foreach ($ridesUpcoming as &$r) {
                $did = (int)$r['driver_id'];
                $r['rating_avg']   = isset($map[$did]) ? (float)$map[$did]['avg']   : null;
                $r['rating_count'] = isset($map[$did]) ? (int)$map[$did]['count']   : 0;
            }
            unset($r);
            foreach ($ridesPast30 as &$r) {
                $did = (int)$r['driver_id'];
                $r['rating_avg']   = isset($map[$did]) ? (float)$map[$did]['avg']   : null;
                $r['rating_count'] = isset($map[$did]) ? (int)$map[$did]['count']   : 0;
            }
            unset($r);
        } catch (\Throwable $e) {
            // silencieux: si Mongo tombe, on garde quand même la page
        }

        $this->render('pages/covoiturage', [
            'title'          => 'Covoiturage',
            'rides_upcoming' => $ridesUpcoming,
            'rides_past_30d' => $ridesPast30,
        ]);
    }

    /**
     * Détail d’un trajet (US 5)
     * - J’affiche toutes les infos du trajet + préférences + véhicule + note moyenne du conducteur
     * - Je remonte aussi quelques avis récents pour la crédibilité
     */
    public function show(): void
    {
        $pdo = Sql::pdo();

        // id de trajet requis
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(404);
            $this->render('rides/show', ['ride' => null, 'reviews' => [], 'avgNote' => null, 'reviewsRecent'=>[]]);
            return;
        }

        // je récupère tout ce qu’il faut pour la fiche
        $sql = "
            SELECT
              r.id, r.from_city, r.to_city, r.date_start, r.date_end,
              r.price, r.seats_left, r.driver_id,
              COALESCE(r.is_electric_cached, CASE WHEN UPPER(v.energy)='ELECTRIC' THEN 1 ELSE 0 END) AS is_eco,

              TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS driver_display_name,
              u.avatar_path AS driver_avatar,

              v.brand, v.model, v.color, v.energy,

              up.smoker, up.animals, up.music, up.chatty, up.ac
            FROM rides r
            JOIN users u                  ON u.id = r.driver_id
            LEFT JOIN vehicles v          ON v.id = r.vehicle_id
            LEFT JOIN user_preferences up ON up.user_id = r.driver_id
            WHERE r.id = :id
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':id'=>$id]);
        $ride = $st->fetch(PDO::FETCH_ASSOC);

        if (!$ride) {
            http_response_code(404);
            $this->render('rides/show', ['ride' => null, 'reviews' => [], 'avgNote' => null, 'reviewsRecent'=>[]]);
            return;
        }

        // bloc avis (je reste tolérant aux méthodes dispo dans Review)
        $reviews = [];
        $avgNote = null;
        $reviewsRecent = [];
        try {
            $rm = new Review(); // ✅ (correct: plus de ReviewModel)
            $driverId = (int)$ride['driver_id'];
            $reviews = $rm->findByDriverApproved($driverId, 10);
            $avgNote = $rm->avgForDriver($driverId);
            $reviewsRecent = $rm->recentApprovedForDriver($driverId, 3);
        } catch (\Throwable $e) {
            // pas bloquant si Mongo tombe
        }

        $this->render('rides/show', compact('ride','reviews','avgNote','reviewsRecent'));
    }

    /* ... book() inchangé ... */

    /**
     * Réservation d’un trajet (US 6)
     * - Transactions atomiques (FOR UPDATE + COMMIT/ROLLBACK)
     * - Décrément des places, débit passager, crédit conducteur, commission plateforme
     * - Trois écritures dans la table transactions (passager, conducteur, plateforme)
     * - E-mails de confirmation
     */
    public function book(): void
    {
        // j’accepte uniquement POST avec un ride_id
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_POST['ride_id'])) {
            header('Location: /rides');
            return;
        }

        // je vérifie l’authentification (session requise)
        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) { header('Location: /login'); return; }

        $rideId = (int)$_POST['ride_id'];
        $pdo = Sql::pdo();

        $platformFee = 2; // US 9 : commission fixe de 2 crédits

        try {
            $pdo->beginTransaction();

            // je verrouille le trajet (places, driver_id, price…)
            $st = $pdo->prepare("SELECT * FROM rides WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$rideId]);
            $ride = $st->fetch(PDO::FETCH_ASSOC);
            if (!$ride) { throw new \RuntimeException('Trajet introuvable'); }

            // je bloque la réservation de son propre trajet
            if ((int)$ride['driver_id'] === $userId) {
                throw new \RuntimeException('Vous ne pouvez pas réserver votre propre trajet.');
            }

            // je vérifie les places restantes
            if ((int)$ride['seats_left'] <= 0) {
                throw new \RuntimeException('Plus de places disponibles.');
            }

            // pas de double réservation confirmée pour ce passager/ce trajet
            $st = $pdo->prepare("SELECT 1 FROM bookings WHERE ride_id=:r AND passenger_id=:u AND status='CONFIRMED' LIMIT 1");
            $st->execute([':r'=>$rideId, ':u'=>$userId]);
            if ($st->fetchColumn()) {
                throw new \RuntimeException('Vous avez déjà réservé ce trajet.');
            }

            // je calcule la part conducteur
            $price = (int)$ride['price'];
            $driverAmount = max(0, $price - $platformFee);

            // je verrouille le solde du passager (crédits)
            $st = $pdo->prepare("SELECT credits FROM users WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$userId]);
            $creditsPassenger = (int)($st->fetchColumn() ?: 0);
            if ($creditsPassenger < $price) {
                throw new \RuntimeException('Crédits insuffisants.');
            }

            // je récupère un "compte plate-forme" (ADMIN le plus ancien)
            $platformUserId = (int)($pdo->query("SELECT id FROM users WHERE role='ADMIN' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
            if ($platformUserId === 0) { $platformUserId = (int)$ride['driver_id']; } // fallback minimal

            // labels de transactions compatibles avec le schéma (ENUM/VARCHAR)
            $tx = $this->pickTxLabels($pdo);

            // 1) création de la réservation
            $bst = $pdo->prepare("
                INSERT INTO bookings(ride_id,passenger_id,status,credits_spent,created_at)
                VALUES(:r,:u,'CONFIRMED',:c,NOW())
            ");
            $bst->execute([':r'=>$rideId, ':u'=>$userId, ':c'=>$price]);
            $bookingId = (int)$pdo->lastInsertId();

            // 2) décrément des places (avec garde-fou)
            $ust = $pdo->prepare("
                UPDATE rides SET seats_left = seats_left - 1
                WHERE id = :id AND seats_left >= 1
            ");
            $ust->execute([':id'=>$rideId]);
            if ($ust->rowCount() === 0) {
                throw new \RuntimeException('Plus de places disponibles.');
            }

            // 3) débit passager
            $pdo->prepare("UPDATE users SET credits = credits - :c WHERE id = :id")
                ->execute([':c'=>$price, ':id'=>$userId]);

            // 4) crédit conducteur
            $pdo->prepare("UPDATE users SET credits = credits + :c WHERE id = :id")
                ->execute([':c'=>$driverAmount, ':id'=>(int)$ride['driver_id']]);

            // 5) journal des transactions (passager, conducteur, plateforme)
            $insTx = $pdo->prepare("
                INSERT INTO transactions(user_id, booking_id, ride_id, type, montant, description, created_at)
                VALUES(:uid,:bid,:rid,:type,:amount,:descr,NOW())
            ");
            $insTx->execute([
                ':uid'=>$userId, ':bid'=>$bookingId, ':rid'=>$rideId,
                ':type'=>$tx['booking'], ':amount'=>-$price,
                ':descr'=>'Réservation covoiturage #'.$rideId
            ]);
            $insTx->execute([
                ':uid'=>(int)$ride['driver_id'], ':bid'=>$bookingId, ':rid'=>$rideId,
                ':type'=>$tx['earn'], ':amount'=>$driverAmount,
                ':descr'=>'Gain conducteur ride #'.$rideId
            ]);
            $insTx->execute([
                ':uid'=>$platformUserId, ':bid'=>$bookingId, ':rid'=>$rideId,
                ':type'=>$tx['fee'], ':amount'=>$platformFee,
                ':descr'=>'Commission plate-forme ride #'.$rideId
            ]);

            $pdo->commit();

            // Envois e-mails: confirmation au passager + notification au conducteur
            $m = new Mailer();

            $passenger = $pdo->prepare("SELECT id, email, prenom, nom FROM users WHERE id = ? LIMIT 1");
            $passenger->execute([$userId]);
            $p = $passenger->fetch(PDO::FETCH_ASSOC) ?: [];

            $driver = $pdo->prepare("SELECT id, email, prenom, nom FROM users WHERE id = ? LIMIT 1");
            $driver->execute([(int)$ride['driver_id']]);
            $d = $driver->fetch(PDO::FETCH_ASSOC) ?: [];

            $passengerArr = [
                'id'     => (int)($p['id'] ?? 0),
                'email'  => (string)($p['email'] ?? ''),
                'pseudo' => (string)($p['prenom'] ?? $p['nom'] ?? 'Passager'),
                'nom'    => (string)($p['nom'] ?? ''),
            ];
            $driverArr = [
                'id'           => (int)($d['id'] ?? 0),
                'email'        => (string)($d['email'] ?? ''),
                'pseudo'       => (string)($d['prenom'] ?? $d['nom'] ?? 'Chauffeur'),
                'nom'          => (string)($d['nom'] ?? ''),
                'display_name' => trim((string)($d['prenom'] ?? '') . ' ' . (string)($d['nom'] ?? '')),
            ];

            if (!empty($passengerArr['email']) && !empty($driverArr['email'])) {
                $m->sendBookingConfirmation($passengerArr, $ride, $driverArr);
                $m->sendDriverNewReservation($driverArr, $ride, $passengerArr);
            }

            $_SESSION['flash'][] = ['type'=>'success','text'=>'Réservation confirmée 👍'];
            header('Location: /rides/show?id='.$rideId);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['flash'][] = ['type'=>'danger','text'=>'Réservation impossible : '.$e->getMessage()];
            header('Location: /rides/show?id='.$rideId);
        }
    }

    /**
     * Choisit des libellés compatibles avec le schéma de la colonne transactions.type
     * - Support ENUM (je pioche parmi une liste de candidats)
     * - Support VARCHAR(n) (je choisis un candidat qui rentre)
     * - Fallback génériques
     */
    private function pickTxLabels(PDO $pdo): array
    {
        $col = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'type'")->fetch(PDO::FETCH_ASSOC) ?: [];
        $type = (string)($col['Type'] ?? '');

        $candBooking = ['BOOKING_DEBIT','BOOKING','RESERVATION','DEBIT','BOOK','PAYMENT'];
        $candEarn    = ['EARN_DRIVER','EARN','GAIN','CREDIT','DRIVER_EARN'];
        $candFee     = ['PLATFORM_FEE','FEE','COMMISSION','PLATFORM','PLFEE'];

        // ENUM('...','...') → je choisis la 1ère valeur qui colle
        if (stripos($type, 'enum(') === 0) {
            if (preg_match('/enum\((.*)\)/i', $type, $m)) {
                $vals = array_map(fn($s)=>trim($s, " '\""), explode(',', $m[1]));
                $pick = function(array $cands) use ($vals) {
                    foreach ($cands as $c) {
                        if (in_array($c, $vals, true)) return $c;
                        if (in_array(strtoupper($c), $vals, true)) return strtoupper($c);
                    }
                    return $vals[0] ?? 'TX';
                };
                return [
                    'booking' => $pick($candBooking),
                    'earn'    => $pick($candEarn),
                    'fee'     => $pick($candFee),
                ];
            }
        }

        // VARCHAR(n) → je prends le 1er candidat qui tient dans n
        if (preg_match('/varchar\((\d+)\)/i', $type, $m)) {
            $n = (int)$m[1];
            $fit = function(array $cands) use ($n) {
                foreach ($cands as $c) {
                    if (mb_strlen($c) <= $n) return $c;
                }
                return mb_substr('TX', 0, max(1,$n));
            };
            return [
                'booking' => $fit($candBooking),
                'earn'    => $fit($candEarn),
                'fee'     => $fit($candFee),
            ];
        }

        // fallback générique si je n’ai pas réussi à introspecter
        return ['booking'=>'BOOK','earn'=>'EARN','fee'=>'FEE'];
    }
}
