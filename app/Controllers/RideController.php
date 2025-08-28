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
     * Branché sur:
     *  - rides (seats_left, dates, price…)
     *  - users (nom, prenom, avatar_path)
     *  - vehicles (energy -> badge Éco)
     *  - user_preferences (smoker, animals, music, chatty, ac)
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
            // si la table reviews n'existe pas, ce filtre ne gênera pas le reste
            $where[] = "(SELECT ROUND(AVG(rv.note),1) FROM reviews rv WHERE rv.driver_id = r.driver_id) >= :minn";
            $params[':minn'] = $minNote;
        }

        $sql = "
        SELECT
          r.id, r.from_city, r.to_city, r.date_start, r.date_end,
          r.price, r.seats_left,
          COALESCE(r.is_electric_cached, CASE WHEN UPPER(v.energy)='ELECTRIC' THEN 1 ELSE 0 END) AS is_eco,

          /* conducteur */
          TRIM(CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,''))) AS driver_display_name,
          u.avatar_path AS driver_avatar,

          /* préférences réelles du conducteur */
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

    /** Page publique “/covoiturage” (cartes à venir + historique 30j) */
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

    /** Détail d’un covoiturage (branché BDD + champs FR) */
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

    /** Bouton Participer (stub: redirige vers le détail) */
    public function book(): void
    {
        if (empty($_POST['ride_id'])) {
            header('Location: /rides');
            return;
        }
        $rideId = (int)$_POST['ride_id'];
        header('Location: /rides/show?id=' . $rideId);
    }
}
