<?php
namespace App\Controllers;

use App\Db\Sql;
use App\Security\Security;
use PDO;
use Throwable;

class RideController {

  private function pdo(): PDO { return Sql::pdo(); }

  public function home() { header('Location: /'); return ''; }

  public function list() {
    $title = 'Covoiturages';
    ob_start();
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container my-4"><p>Utilisez le formulaire d\'accueil pour lancer une recherche.</p></div>';
    include __DIR__ . '/../includes/footer.php';
    return ob_get_clean();
  }

  public function search() {
    $pdo = $this->pdo();
    $from = trim($_POST['from_city'] ?? '');
    $to   = trim($_POST['to_city'] ?? '');
    $date = trim($_POST['date_start'] ?? '');
    $ecoOnly = isset($_POST['eco_only']);

    $sql = "SELECT id, from_city, to_city, date_start, price, is_electric_cached FROM rides WHERE 1=1";
    $params = [];
    if ($from) { $sql .= " AND from_city LIKE :from"; $params['from'] = "%$from%"; }
    if ($to)   { $sql .= " AND to_city LIKE :to";     $params['to']   = "%$to%"; }
    if ($date) { $sql .= " AND DATE(date_start) = :ds"; $params['ds'] = $date; }
    if ($ecoOnly) { $sql .= " AND is_electric_cached = 1"; }
    $sql .= " ORDER BY date_start ASC LIMIT 100";

    $stmt = $pdo->prepare($sql); $stmt->execute($params); $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $title = 'Résultats';
    ob_start();
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container my-4"><h1 class="h4 mb-3">Résultats</h1>';
    if ($results) {
      echo '<div class="list-group">';
      foreach ($results as $r) {
        $id = (int)$r['id'];
        echo '<a class="list-group-item list-group-item-action" href="/rides/show?id='.$id.'">'
           . htmlspecialchars($r['from_city']).' → '.htmlspecialchars($r['to_city'])
           . ' — '.htmlspecialchars($r['date_start']).' — '.(int)$r['price'].' cr.'
           . ($r['is_electric_cached'] ? ' ⚡' : '')
           . '</a>';
      }
      echo '</div>';
    } else { echo '<p class="text-muted">Aucun résultat.</p>'; }
    echo '</div>';
    include __DIR__ . '/../includes/footer.php';
    return ob_get_clean();
  }

  public function show() {
    $pdo = $this->pdo();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { header('Location:/rides'); return ''; }
    $stmt = $pdo->prepare("SELECT * FROM rides WHERE id=:id");
    $stmt->execute(['id'=>$id]);
    $ride = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ride) { header('Location:/rides'); return ''; }

    $title = 'Trajet';
    ob_start();
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container my-4">';
    echo '<h1 class="h4 mb-3">'.htmlspecialchars($ride['from_city']).' → '.htmlspecialchars($ride['to_city']).'</h1>';
    echo '<p>Date : '.htmlspecialchars($ride['date_start']).'</p>';
    echo '<p>Prix : '.(int)$ride['price'].' crédits</p>';
    echo '<form method="post" action="/rides/book" class="mt-3">';
    echo '<input type="hidden" name="ride_id" value="'.$id.'">';
    echo '<input type="hidden" name="credits_spent" value="'.(int)$ride['price'].'">';
    echo '<button class="btn btn-success">Réserver</button>';
    echo '</form></div>';
    include __DIR__ . '/../includes/footer.php';
    return ob_get_clean();
  }

  // --- Actions ---
  public function book() {
    Security::ensure(['USER','EMPLOYEE','ADMIN']);
    $pdo = $this->pdo();
    $rideId = (int)($_POST['ride_id'] ?? 0);
    $uid    = (int)($_SESSION['user']['id'] ?? 0);
    $cost   = (int)($_POST['credits_spent'] ?? 0);
    if ($rideId <= 0 || $uid <= 0) { header('Location: /rides'); return ''; }

    try {
      $pdo->beginTransaction();
      $r = $pdo->prepare("SELECT seats_left FROM rides WHERE id = :id FOR UPDATE"); $r->execute(['id'=>$rideId]);
      $ride = $r->fetch(PDO::FETCH_ASSOC);
      if (!$ride || (int)$ride['seats_left'] <= 0) { $pdo->rollBack(); header('Location:/rides/show?id='.$rideId); return ''; }

      $u = $pdo->prepare("SELECT credits, is_suspended FROM users WHERE id=:id FOR UPDATE"); $u->execute(['id'=>$uid]);
      $user = $u->fetch(PDO::FETCH_ASSOC);
      if (!$user || (int)$user['is_suspended'] === 1 || (int)$user['credits'] < $cost) { $pdo->rollBack(); header('Location:/rides/show?id='.$rideId); return ''; }

      $pdo->prepare("INSERT INTO bookings(ride_id, passenger_id, credits_spent) VALUES(:r,:p,:c)")
          ->execute(['r'=>$rideId,'p'=>$uid,'c'=>$cost]);
      $pdo->prepare("UPDATE users SET credits = credits - :c WHERE id=:id")
          ->execute(['c'=>$cost,'id'=>$uid]);
      $pdo->prepare("UPDATE rides SET seats_left = seats_left - 1 WHERE id=:id")
          ->execute(['id'=>$rideId]);
      $pdo->commit();

      // Mise à jour les crédits de l'utilisateur dans la session
      $_SESSION['user']['credits'] = (int)$user['credits'] - $cost;

      header('Location: /dashboard');
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      http_response_code(500); echo 'Erreur booking.';
    }
    return '';
  }

}
