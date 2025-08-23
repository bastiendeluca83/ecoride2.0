// BookingController::confirm()
use App\Db\Sql;

public function confirm() {
  session_start();
  if (empty($_SESSION['user']['id'])) { header('Location:/login'); exit; }

  $pdo    = Sql::pdo();
  $userId = (int)$_SESSION['user']['id'];
  $rideId = (int)($_POST['ride_id'] ?? 0);

  $pdo->beginTransaction();
  try {
    // 1) Récupération du trajet (verrou)
    $q = $pdo->prepare("SELECT id, from_city, to_city, price, seats_left FROM rides WHERE id=? FOR UPDATE");
    $q->execute([$rideId]);
    $ride = $q->fetch(PDO::FETCH_ASSOC);
    if (!$ride || $ride['seats_left'] <= 0) throw new Exception("Plus de place.");

    // 2) Vérif crédits user
    $q = $pdo->prepare("SELECT credits FROM users WHERE id=? FOR UPDATE");
    $q->execute([$userId]);
    $credits = (int)$q->fetchColumn();
    $price   = (int)$ride['price'];
    if ($credits < $price) throw new Exception("Crédits insuffisants.");

    // 3) Créer la réservation
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, ride_id, credits_spent, status, created_at)
                           VALUES (?, ?, ?, 'confirmed', NOW())");
    $stmt->execute([$userId, $rideId, $price]);
    $bookingId = (int)$pdo->lastInsertId();

    // 4) Décrémenter les places
    $pdo->prepare("UPDATE rides SET seats_left = seats_left - 1 WHERE id = ?")->execute([$rideId]);

    // 5) Décrémenter crédits utilisateur
    $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?")->execute([$price, $userId]);

    // 6) MOUVEMENT : dépense
    $desc = "Réservation {$ride['from_city']} → {$ride['to_city']}";
    $tx = $pdo->prepare("INSERT INTO transactions (user_id, booking_id, ride_id, type, montant, description)
                         VALUES (?, ?, ?, 'depense', ?, ?)");
    $tx->execute([$userId, $bookingId, $rideId, $price, $desc]);

    $pdo->commit();
    header('Location: /dashboard'); // la carte Crédits + Historique s'actualisent
  } catch (Throwable $e) {
    $pdo->rollBack();
    header('Location: /rides?error=booking');
  }
}
