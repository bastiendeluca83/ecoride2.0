<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Services\Mailer;
use App\Models\Review; // ✅ ajoute l'import
use PDO;

class RideController extends BaseController
{
    public function home(): void
    {
        $this->render('home/index', ['title' => 'EcoRide – Covoiturage écoresponsable']);
    }

    public function list(): void
    {
        $pdo = Sql::pdo();

        $from    = trim($_GET['from_city']  ?? $_POST['from_city']  ?? '');
        $to      = trim($_GET['to_city']    ?? $_POST['to_city']    ?? '');
        $date    = trim($_GET['date_start'] ?? $_POST['date_start'] ?? '');
        $ecoOnly = !empty($_GET['eco_only'] ?? $_POST['eco_only']   ?? '');

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

        /* ==== Injection des notes depuis Mongo (APPROVED only) ==== */
        try {
            $rm = new Review();
            $driverIds = array_values(array_unique(array_map(fn($r)=>(int)$r['driver_id'], $rides)));
            $ratingsMap = $rm->avgForDrivers($driverIds); // [driver_id => ['avg'=>x.x,'count'=>n]]

            foreach ($rides as &$r) {
                $did = (int)$r['driver_id'];
                $r['rating_avg']   = isset($ratingsMap[$did]) ? (float)$ratingsMap[$did]['avg']   : null;
                $r['rating_count'] = isset($ratingsMap[$did]) ? (int)$ratingsMap[$did]['count']   : 0;
            }
            unset($r);

            // Filtre min_note côté PHP, sur la moyenne issue de Mongo
            if ($minNote !== null) {
                $rides = array_values(array_filter($rides, function($r) use ($minNote) {
                    if (!isset($r['rating_avg'])) return false; // exclure ceux sans note
                    return (float)$r['rating_avg'] >= (float)$minNote;
                }));
            }
        } catch (\Throwable $e) {
            // pas bloquant
        }

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

    public function covoiturage(): void
    {
        $pdo = Sql::pdo();

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

        /* ✅ AJOUT : notes étoilées sur /covoiturage */
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
            // silencieux
        }

        $this->render('pages/covoiturage', [
            'title'          => 'Covoiturage',
            'rides_upcoming' => $ridesUpcoming,
            'rides_past_30d' => $ridesPast30,
        ]);
    }

    public function show(): void
    {
        $pdo = Sql::pdo();

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(404);
            $this->render('rides/show', ['ride' => null, 'reviews' => [], 'avgNote' => null, 'reviewsRecent'=>[]]);
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
            $this->render('rides/show', ['ride' => null, 'reviews' => [], 'avgNote' => null, 'reviewsRecent'=>[]]);
            return;
        }

        $reviews = [];
        $avgNote = null;
        $reviewsRecent = [];
        try {
            $rm = new Review(); // ✅ fix: plus ReviewModel
            $driverId = (int)$ride['driver_id'];
            $reviews = $rm->findByDriverApproved($driverId, 10);
            $avgNote = $rm->avgForDriver($driverId);
            $reviewsRecent = $rm->recentApprovedForDriver($driverId, 3);
        } catch (\Throwable $e) {
        }

        $this->render('rides/show', compact('ride','reviews','avgNote','reviewsRecent'));
    }

    /* ... book() inchangé ... */

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

        $platformFee = 2;

        try {
            $pdo->beginTransaction();

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

            $st = $pdo->prepare("SELECT 1 FROM bookings WHERE ride_id=:r AND passenger_id=:u AND status='CONFIRMED' LIMIT 1");
            $st->execute([':r'=>$rideId, ':u'=>$userId]);
            if ($st->fetchColumn()) {
                throw new \RuntimeException('Vous avez déjà réservé ce trajet.');
            }

            $price = (int)$ride['price'];
            $driverAmount = max(0, $price - $platformFee);

            $st = $pdo->prepare("SELECT credits FROM users WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$userId]);
            $creditsPassenger = (int)($st->fetchColumn() ?: 0);
            if ($creditsPassenger < $price) {
                throw new \RuntimeException('Crédits insuffisants.');
            }

            $platformUserId = (int)($pdo->query("SELECT id FROM users WHERE role='ADMIN' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
            if ($platformUserId === 0) { $platformUserId = (int)$ride['driver_id']; }

            $tx = $this->pickTxLabels($pdo);

            $bst = $pdo->prepare("
                INSERT INTO bookings(ride_id,passenger_id,status,credits_spent,created_at)
                VALUES(:r,:u,'CONFIRMED',:c,NOW())
            ");
            $bst->execute([':r'=>$rideId, ':u'=>$userId, ':c'=>$price]);
            $bookingId = (int)$pdo->lastInsertId();

            $ust = $pdo->prepare("
                UPDATE rides SET seats_left = seats_left - 1
                WHERE id = :id AND seats_left >= 1
            ");
            $ust->execute([':id'=>$rideId]);
            if ($ust->rowCount() === 0) {
                throw new \RuntimeException('Plus de places disponibles.');
            }

            $pdo->prepare("UPDATE users SET credits = credits - :c WHERE id = :id")
                ->execute([':c'=>$price, ':id'=>$userId]);

            $pdo->prepare("UPDATE users SET credits = credits + :c WHERE id = :id")
                ->execute([':c'=>$driverAmount, ':id'=>(int)$ride['driver_id']]);

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

            // envois e-mails : confirmation (passager) + nouvelle réservation (conducteur)
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

    private function pickTxLabels(PDO $pdo): array
    {
        $col = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'type'")->fetch(PDO::FETCH_ASSOC) ?: [];
        $type = (string)($col['Type'] ?? '');

        $candBooking = ['BOOKING_DEBIT','BOOKING','RESERVATION','DEBIT','BOOK','PAYMENT'];
        $candEarn    = ['EARN_DRIVER','EARN','GAIN','CREDIT','DRIVER_EARN'];
        $candFee     = ['PLATFORM_FEE','FEE','COMMISSION','PLATFORM','PLFEE'];

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

        return ['booking'=>'BOOK','earn'=>'EARN','fee'=>'FEE'];
    }
}
