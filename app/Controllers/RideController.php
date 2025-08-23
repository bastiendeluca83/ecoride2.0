<?php
namespace App\Controllers;

use App\Db\Sql;       // Ton wrapper PDO (adaptable). Sinon: new \PDO(...)
use PDO;

class RideController extends BaseController
{
    /** Page d’accueil (barre de recherche) */
    public function home(): void
    {
        $this->render('home/index');
    }

    /** Résultats + filtres */
    public function list(): void
    {
        $pdo = Sql::pdo();

        // Critères de recherche (GET ou POST selon ton formulaire)
        $from = trim($_GET['from_city'] ?? $_POST['from_city'] ?? '');
        $to   = trim($_GET['to_city'] ?? $_POST['to_city'] ?? '');
        $date = trim($_GET['date_start'] ?? $_POST['date_start'] ?? '');
        $eco  = !empty($_GET['eco_only'] ?? $_POST['eco_only'] ?? '');

        // Filtres additionnels (US4)
        $priceMax    = isset($_GET['price_max'])    ? (int)$_GET['price_max']    : null;
        $durationMax = isset($_GET['duration_max']) ? (int)$_GET['duration_max'] : null;
        $minNote     = isset($_GET['min_note'])     ? (float)$_GET['min_note']   : null;

        // Construction SQL
        $sql = "
        SELECT
          r.id, r.from_city, r.to_city, r.date_start, r.date_end,
          r.price, r.seats_left, r.driver_id,
          COALESCE(r.is_electric_cached, CASE WHEN UPPER(v.energy)='ELECTRIC' THEN 1 ELSE 0 END) AS is_eco,
          u.pseudo, u.avatar_url,
          -- moyenne des notes si table reviews (optionnel)
          (SELECT ROUND(AVG(note),1) FROM reviews rv WHERE rv.driver_id = r.driver_id) AS note
        FROM rides r
        JOIN users u     ON u.id = r.driver_id
        LEFT JOIN vehicles v ON v.id = r.vehicle_id
        WHERE r.seats_left > 0
        ";

        $p = [];

        if ($from !== '') { $sql .= " AND r.from_city LIKE :from_city"; $p[':from_city'] = "%$from%"; }
        if ($to   !== '') { $sql .= " AND r.to_city   LIKE :to_city";   $p[':to_city']   = "%$to%";   }
        if ($date !== '') { $sql .= " AND DATE(r.date_start) = :d";      $p[':d']        = $date;     }
        if ($eco)         { $sql .= " AND (COALESCE(r.is_electric_cached, CASE WHEN UPPER(v.energy)='ELECTRIC' THEN 1 ELSE 0 END) = 1)"; }

        if ($priceMax !== null)    { $sql .= " AND r.price <= :pmax";     $p[':pmax']     = $priceMax; }
        if ($durationMax !== null) { $sql .= " AND TIMESTAMPDIFF(HOUR, r.date_start, r.date_end) <= :dmax"; $p[':dmax'] = $durationMax; }
        if ($minNote !== null)     { $sql .= " HAVING (note IS NULL OR note >= :nmin)"; $p[':nmin'] = $minNote; }

        $sql .= " ORDER BY r.date_start ASC LIMIT 100";

        $stmt  = $pdo->prepare($sql);
        $stmt->execute($p);
        $rides = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si aucun résultat mais on a from/to/date => proposer la date la plus proche (US3)
        $suggestion = null;
        if (!$rides && $from !== '' && $to !== '' && $date !== '') {
            $q = $pdo->prepare("
                SELECT DATE(r.date_start) AS date_sugg
                FROM rides r
                WHERE r.seats_left > 0
                  AND r.from_city LIKE :from_city
                  AND r.to_city   LIKE :to_city
                  AND r.date_start > :after
                ORDER BY r.date_start ASC
                LIMIT 1
            ");
            $q->execute([
                ':from_city' => "%$from%",
                ':to_city'   => "%$to%",
                ':after'     => $date . ' 00:00:00',
            ]);
            $suggestion = $q->fetchColumn() ?: null;
        }

        $this->render('rides/list', [
            'rides'       => $rides,
            'filters'     => compact('from','to','date','eco','priceMax','durationMax','minNote'),
            'suggestion'  => $suggestion,
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
          u.pseudo, u.avatar_url,
          v.brand, v.model, v.color, v.energy
        FROM rides r
        JOIN users u     ON u.id = r.driver_id
        LEFT JOIN vehicles v ON v.id = r.vehicle_id
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

        // Avis (optionnel si table reviews)
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
        } catch (\Throwable $e) {
            // table reviews pas encore créée -> on ignore
        }

        $this->render('rides/show', compact('ride','reviews','avgNote'));
    }

    /** Action de réservation (US6) */
    public function book(): void
    {
        // TODO: vérifier login + crédits + stock places, double confirmation etc.
        // Pour l’instant, stub pour connecter ton bouton Participer.
        if (empty($_POST['ride_id'])) {
            header('Location: /rides');
            return;
        }
        $rideId = (int)$_POST['ride_id'];
        header('Location: /rides/show?id='.$rideId);
    }
}
