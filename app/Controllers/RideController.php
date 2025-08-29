<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use PDO;

class RideController extends BaseController
{
    /** Accueil (form de recherche) */
    public function home(): void
    {
        $this->render('home/index', ['title' => 'EcoRide – Covoiturage écoresponsable']);
    }

    /**
     * Résultats + filtres
     */
    public function list(): void
    {
        $pdo = Sql::pdo();

        // Recherche de base
        $from    = trim($_GET['from_city']  ?? $_POST['from_city']  ?? '');
        $to      = trim($_GET['to_city']    ?? $_POST['to_city']    ?? '');
        $date    = trim($_GET['date_start'] ?? $_POST['date_start'] ?? '');
        $ecoOnly = !empty($_GET['eco_only'] ?? $_POST['eco_only']   ?? '');

        // Filtres
        $priceMax    = isset($_GET['price_max'])    ? (int)$_GET['price_max']    : null;
        $durationMax = isset($_GET['duration_max']) ? (int)$_GET['duration_max'] : null;
        $minNote     = isset($_GET['min_note'])     ? (float)$_GET['min_note']   : null;

        $where  = ["r.seats_left > 0"];
        $params = [];

        if ($from !== '') { $where[] = "r.from_city LIKE :from"; $params[':from'] = "%$from%"; }
        if ($to   !== '') { $where[] = "r.to_city   LIKE :to";   $params[':to']   = "%$to%"; }
        if ($date !== '') { $where[] = "DATE(r.date_start) = :d"; $params[':d']   = $date; }

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
        if ($minNote !== null && $minNote > 0) {
            $where[] = "(SELECT ROUND(AVG(rv.note),1) FROM reviews rv WHERE rv.driver_id = r.driver_id) >= :minn";
            $params[':minn'] = $minNote;
        }

        $sql = "
        SELECT
          r.id, r.from_city, r.to_city, r.date_start, r.date_end,
          r.price, r.seats_left,
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

        // Suggestion si pas de résultat
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

        $this->render('rides/list', [
            'title'      => 'Covoiturages – Résultats',
            'rides'      => $rides,
            'filters'    => compact('from','to','date','ecoOnly','priceMax','durationMax','minNote'),
            'suggestion' => $suggestion,
        ]);
    }

    /** Page publique “/covoiturage” */
    public function covoiturage(): void
    {
        $pdo = Sql::pdo();

        // À venir
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

        // Passés 30 jours
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

        $this->render('pages/covoiturage', [
            'title'          => 'Covoiturage',
            'rides_upcoming' => $ridesUpcoming,
            'rides_past_30d' => $ridesPast30,
        ]);
    }

    /** Détail d’un covoiturage */
    public function show(): void
    {
        $pdo = Sql::pdo();

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(404);
            $this->render('rides/show', ['ride' => null, 'reviews' => [], 'avgNote' => null]);
            return;
        }

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
            $this->render('rides/show', ['ride' => null, 'reviews' => [], 'avgNote' => null]);
            return;
        }

        // Avis (optionnel)
        $reviews = [];
        $avgNote = null;
        try {
            $q = $pdo->prepare("
                SELECT note, comment, created_at
                FROM reviews
                WHERE driver_id = :uid
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $q->execute([':uid' => $ride['driver_id']]);
            $reviews = $q->fetchAll(PDO::FETCH_ASSOC);

            $avg = $pdo->prepare("SELECT ROUND(AVG(note),1) FROM reviews WHERE driver_id = :uid");
            $avg->execute([':uid' => $ride['driver_id']]);
            $avgNote = $avg->fetchColumn() ?: null;
        } catch (\Throwable $e) { /* table absente => on ignore */ }

        $this->render('rides/show', compact('ride','reviews','avgNote'));
    }

    /**
     * Choisit des libellés de transaction compatibles avec la colonne `transactions.type`
     * - si ENUM: on prend les valeurs présentes (BOOK/DEBIT/EARN/FEE/COMMISSION…)
     * - si VARCHAR(n): on tronque/choisit un libellé qui tient dans n
     */
    private function pickTxLabels(PDO $pdo): array
    {
        $col = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'type'")->fetch(PDO::FETCH_ASSOC) ?: [];
        $type = (string)($col['Type'] ?? '');

        // Candidats par sémantique
        $candBooking = ['BOOKING_DEBIT','BOOKING','RESERVATION','DEBIT','BOOK','PAYMENT'];
        $candEarn    = ['EARN_DRIVER','EARN','GAIN','CREDIT','DRIVER_EARN'];
        $candFee     = ['PLATFORM_FEE','FEE','COMMISSION','PLATFORM','PLFEE'];

        // ENUM ?
        if (stripos($type, 'enum(') === 0) {
            if (preg_match('/enum\((.*)\)/i', $type, $m)) {
                $vals = array_map(fn($s)=>trim($s, " '\""), explode(',', $m[1]));
                $pick = function(array $cands) use ($vals) {
                    foreach ($cands as $c) {
                        if (in_array($c, $vals, true)) return $c;
                        // aussi tenter versions courtes
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

        // VARCHAR(n) ?
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

        // Autre type: valeurs courtes par défaut
        return ['booking'=>'BOOK','earn'=>'EARN','fee'=>'FEE'];
    }

    /** Bouton Participer : réservation + répartition 8/2 + transactions */
    public function book(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_POST['ride_id'])) {
            header('Location: /rides');
            return;
        }

        if (session_status() === \PHP_SESSION_NONE) { session_start(); }
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) { header('Location: /login'); return; }

        $rideId = (int)$_POST['ride_id'];
        $pdo = Sql::pdo();

        $platformFee = 2; // crédits plate-forme

        try {
            $pdo->beginTransaction();

            // 1) Verrouille le trajet
            $st = $pdo->prepare("SELECT * FROM rides WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$rideId]);
            $ride = $st->fetch(PDO::FETCH_ASSOC);
            if (!$ride) { throw new \RuntimeException('Trajet introuvable'); }

            if ((int)$ride['driver_id'] === $userId) {
                throw new \RuntimeException('Vous ne pouvez pas réserver votre propre trajet.');
            }

            if ((int)$ride['seats_left'] <= 0) {
                throw new \RuntimeException('Plus de places disponibles.');
            }

            // 2) Déjà réservé ?
            $st = $pdo->prepare("SELECT 1 FROM bookings WHERE ride_id=:r AND passenger_id=:u AND status='CONFIRMED' LIMIT 1");
            $st->execute([':r'=>$rideId, ':u'=>$userId]);
            if ($st->fetchColumn()) {
                throw new \RuntimeException('Vous avez déjà réservé ce trajet.');
            }

            $price = (int)$ride['price'];
            $driverAmount = max(0, $price - $platformFee);

            // 3) Vérifie crédits passager (lock)
            $st = $pdo->prepare("SELECT credits FROM users WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$userId]);
            $creditsPassenger = (int)($st->fetchColumn() ?: 0);
            if ($creditsPassenger < $price) {
                throw new \RuntimeException('Crédits insuffisants.');
            }

            // Détermine l'utilisateur "plateforme" (ADMIN), fallback conducteur
            $platformUserId = (int)($pdo->query("SELECT id FROM users WHERE role='ADMIN' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
            if ($platformUserId === 0) { $platformUserId = (int)$ride['driver_id']; }

            // Libellés compatibles pour transactions.type
            $tx = $this->pickTxLabels($pdo);

            // 4) Crée la réservation
            $bst = $pdo->prepare("
                INSERT INTO bookings(ride_id,passenger_id,status,credits_spent,created_at)
                VALUES(:r,:u,'CONFIRMED',:c,NOW())
            ");
            $bst->execute([':r'=>$rideId, ':u'=>$userId, ':c'=>$price]);
            $bookingId = (int)$pdo->lastInsertId();

            // 5) Décrémente places
            $ust = $pdo->prepare("
                UPDATE rides SET seats_left = seats_left - 1
                WHERE id = :id AND seats_left >= 1
            ");
            $ust->execute([':id'=>$rideId]);
            if ($ust->rowCount() === 0) {
                throw new \RuntimeException('Plus de places disponibles.');
            }

            // 6) Mouvements crédits
            // débit passager
            $pdo->prepare("UPDATE users SET credits = credits - :c WHERE id = :id")
                ->execute([':c'=>$price, ':id'=>$userId]);

            // crédit conducteur
            $pdo->prepare("UPDATE users SET credits = credits + :c WHERE id = :id")
                ->execute([':c'=>$driverAmount, ':id'=>(int)$ride['driver_id']]);

            // 7) Transactions
            $insTx = $pdo->prepare("
                INSERT INTO transactions(user_id, booking_id, ride_id, type, montant, description, created_at)
                VALUES(:uid,:bid,:rid,:type,:amount,:descr,NOW())
            ");
            // débit passager
            $insTx->execute([
                ':uid'=>$userId, ':bid'=>$bookingId, ':rid'=>$rideId,
                ':type'=>$tx['booking'], ':amount'=>-$price,
                ':descr'=>'Réservation covoiturage #'.$rideId
            ]);
            // revenu conducteur
            $insTx->execute([
                ':uid'=>(int)$ride['driver_id'], ':bid'=>$bookingId, ':rid'=>$rideId,
                ':type'=>$tx['earn'], ':amount'=>$driverAmount,
                ':descr'=>'Gain conducteur ride #'.$rideId
            ]);
            // commission plateforme
            $insTx->execute([
                ':uid'=>$platformUserId, ':bid'=>$bookingId, ':rid'=>$rideId,
                ':type'=>$tx['fee'], ':amount'=>$platformFee,
                ':descr'=>'Commission plate-forme ride #'.$rideId
            ]);

            $pdo->commit();

            $_SESSION['flash'][] = ['type'=>'success','text'=>'Réservation confirmée 👍'];
            header('Location: /rides/show?id='.$rideId);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['flash'][] = ['type'=>'danger','text'=>'Réservation impossible : '.$e->getMessage()];
            header('Location: /rides/show?id='.$rideId);
        }
    }
}
