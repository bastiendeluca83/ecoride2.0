<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Db\Sql;
use App\Security\Security;
use App\Models\User;
use App\Models\Ride;
use App\Models\Booking;   // ✅ bon namespace
use App\Models\Vehicle;   // ✅ bon namespace
use App\Models\UserPreferences;
use App\Services\Mailer;
use App\Models\Review;     // ✅ Pour récupérer la note depuis Mongo
use PDO;

/**
 * Controller "général" pour l'espace utilisateur.
 * - Je charge le dashboard (réservations, trajets, stats, avis)
 * - Je gère le profil (édition + upload avatar + préférences + mot de passe)
 * - Je gère les véhicules (CRUD léger)
 * - Je gère le cycle de vie d’un trajet (créer / démarrer / terminer / annuler)
 * - Je fournis la page "Ma note" (avis + moyenne depuis Mongo)
 *
 * NB: je reste strict MVC -> aucune logique de vue ici, j’envoie seulement des données aux templates.
 */
final class GeneralController extends BaseController
{
    public function index(): void
    {
        Security::ensure(['USER']); // je force l'accès aux utilisateurs connectés
        $uid  = (int)($_SESSION['user']['id'] ?? 0);

        /* Je rafraîchis l'utilisateur en session (crédits, total, etc.) pour éviter les données périmées */
        $fresh = $uid ? User::findById($uid) : null;
        if ($fresh) {
            $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $fresh);
        }
        $user = $_SESSION['user'] ?? ['nom'=>'Utilisateur','credits'=>0,'total_rides'=>0];

        $pdo = Sql::pdo(); // j'ai besoin de PDO pour quelques fallbacks

        /* --- Réservations passager à venir (je tente via le modèle, sinon SQL fallback) --- */
        if (class_exists(Booking::class) && method_exists(Booking::class, 'forPassengerUpcoming')) {
            $reservations = $uid ? Booking::forPassengerUpcoming($uid) : [];
        } else {
            // fallback SQL si la méthode du modèle n’existe pas encore
            $reservations = [];
            if ($uid) {
                $st = $pdo->prepare("
                    SELECT b.id, b.ride_id, b.credits_spent,
                           r.from_city, r.to_city, r.date_start, r.date_end
                    FROM bookings b
                    JOIN rides r ON r.id = b.ride_id
                    WHERE b.passenger_id = :u
                      AND UPPER(b.status) = 'CONFIRMED'
                      AND r.date_start >= NOW()
                    ORDER BY r.date_start ASC
                ");
                $st->execute([':u'=>$uid]);
                $reservations = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        /* --- Trajets à venir où je suis conducteur + mes véhicules --- */
        $rides    = $uid ? Ride::forDriverUpcoming($uid) : [];
        $vehicles = $uid ? Vehicle::forUser($uid) : [];

        /* J’enrichis les réservations avec les infos conducteur (nom, avatar...) pour l’affichage */
        if (!empty($reservations)) {
            foreach ($reservations as &$res) {
                $rideId = (int)($res['ride_id'] ?? $res['id'] ?? 0);
                $res['driver'] = $rideId ? Ride::driverInfo($rideId) : null;
            }
            unset($res);
        }

        /* J’enrichis mes trajets conducteur avec la liste des participants */
        if (!empty($rides)) {
            foreach ($rides as &$r) {
                $r['participants'] = Ride::passengersForRide((int)($r['id'] ?? 0));
            }
            unset($r);
        }

        /* --- Stats: combien de trajets terminés (conducteur + passager) --- */
        $driverDone = $uid ? Ride::countCompletedByDriver($uid) : 0;

        if (class_exists(Booking::class) && method_exists(Booking::class, 'countCompletedByPassenger')) {
            $passengerDone = $uid ? Booking::countCompletedByPassenger($uid) : 0;
        } else {
            // fallback SQL si la méthode n’existe pas
            $passengerDone = 0;
            if ($uid) {
                $st = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM bookings b
                    JOIN rides r ON r.id = b.ride_id
                    WHERE b.passenger_id = :u
                      AND UPPER(b.status) = 'CONFIRMED'
                      AND r.date_end IS NOT NULL
                      AND r.date_end < NOW()
                ");
                $st->execute([':u'=>$uid]);
                $passengerDone = (int)$st->fetchColumn();
            }
        }

        // un petit indicateur CO2 fictif (je reste cohérent avec le thème "écolo")
        $totalDone = (int)$driverDone + (int)$passengerDone;
        $co2PerTrip = 2.5;             // je pose une valeur simple pour illustrer
        $co2Total   = $totalDone * $co2PerTrip;

        $stats = [
            'completed_total' => $totalDone,
            'driver_total'    => (int)$driverDone,
            'passenger_total' => (int)$passengerDone,
            'co2_per_trip'    => $co2PerTrip,
            'co2_total'       => $co2Total,
        ];

        /* ✅ Je récupère ma note moyenne et quelques avis récents depuis Mongo (via le modèle Review si dispo) */
        $driver_rating_avg = null;
        $driver_rating_count = 0;
        $driver_reviews_recent = [];
        try {
            if ($uid > 0) {
                $rm = new Review();
                // moyenne arrondie à 0.1 si la méthode existe
                if (method_exists($rm, 'avgForDriver')) {
                    $driver_rating_avg = $rm->avgForDriver($uid);
                }
                // nombre total d'avis (si la méthode groupée existe)
                if (method_exists($rm, 'avgForDrivers')) {
                    $map = $rm->avgForDrivers([$uid]);
                    if (isset($map[$uid])) {
                        $driver_rating_count = (int)$map[$uid]['count'];
                    }
                }
                // pour alimenter une modale ou un bloc "derniers avis"
                if (method_exists($rm, 'recentApprovedForDriver')) {
                    $driver_reviews_recent = $rm->recentApprovedForDriver($uid, 5);
                }
            }
        } catch (\Throwable $e) {
            // je reste silencieux ici: si Mongo tombe, je ne casse pas le dashboard
        }

        // J’envoie toutes les données à la vue du dashboard (aucune logique de présentation ici)
        $this->render('dashboard/user', [
            'title'        => 'Espace utilisateur',
            'user'         => $user,
            'reservations' => $reservations,
            'rides'        => $rides,
            'vehicles'     => $vehicles,
            'stats'        => $stats,
            'driver_rating_avg'    => $driver_rating_avg,
            'driver_rating_count'  => $driver_rating_count,
            'driver_reviews_recent'=> $driver_reviews_recent,
        ]);
    }

    /* ====== PROFIL ====== */

    public function editForm(): void
    {
        Security::ensure(['USER']); // je sécurise la route
        $id   = (int)($_SESSION['user']['id'] ?? 0);
        // je tente un findById sinon je retombe sur la session telle quelle
        $user = $id ? (User::findById($id) ?? ($_SESSION['user'] ?? null)) : ($_SESSION['user'] ?? null);

        // je récupère les préférences via la 1ère méthode dispo du modèle
        $prefs = [];
        foreach (['get','findByUserId','forUser'] as $m) {
            if (method_exists(UserPreferences::class, $m)) {
                $prefs = UserPreferences::$m($id) ?? [];
                break;
            }
        }

        $this->render('pages/profile_edit', [
            'title' => 'Modifier mon profil',
            'user'  => $user,
            'prefs' => $prefs,
        ]);
    }

    public function update(): void
    {
        Security::ensure(['USER']); // protection

        // je vérifie le CSRF pour éviter les soumissions frauduleuses
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
            header('Location: ' . BASE_URL . 'profil/edit'); exit;
        }

        $id = (int)($_SESSION['user']['id'] ?? 0);

        // je récupère proprement le payload (j’accepte les clés legacy et nouvelles)
        $payload = [
            'nom'            => $_POST['nom']            ?? null,
            'prenom'         => $_POST['prenom']         ?? null,
            'email'          => $_POST['email']          ?? null,
            'telephone'      => $_POST['telephone']      ?? null,
            'adresse'        => $_POST['adresse']        ?? null,
            'bio'            => $_POST['bio']            ?? null,
            'last_name'      => $_POST['last_name']      ?? null,
            'first_name'     => $_POST['first_name']     ?? null,
            'phone'          => $_POST['phone']          ?? null,
            'address'        => $_POST['address']        ?? null,
            'date_naissance' => $_POST['date_naissance'] ?? null,
        ];
        $data = [];
        foreach ($payload as $k=>$v) if ($v !== null && $v !== '') $data[$k] = is_string($v) ? trim($v) : $v;

        /* Upload avatar (optionnel, taille max 2Mo, formats classiques) */
        $avatarUpdated = false;
        if (!empty($_FILES['avatar']) && is_array($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['avatar'];
            if ($f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
                $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                $mime = @mime_content_type($f['tmp_name']) ?: '';
                $sizeOk = (int)$f['size'] <= 2 * 1024 * 1024;
                if (in_array($mime, $allowed, true) && $sizeOk) {
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg');
                    $baseDir = dirname(__DIR__, 2) . '/public/uploads/avatars';
                    if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);
                    $filename = 'u'.$id.'_'.time().'.'.$ext;
                    $dest = $baseDir . '/' . $filename;
                    if (@move_uploaded_file($f['tmp_name'], $dest)) {
                        $relPath = 'uploads/avatars/' . $filename;
                        // je préfère updateAvatar si dispo, sinon je passe par updateProfile
                        if (method_exists(User::class, 'updateAvatar')) {
                            $avatarUpdated = (bool)User::updateAvatar($id, $relPath);
                        } else {
                            $avatarUpdated = (bool)User::updateProfile($id, ['avatar_path'=>$relPath]);
                        }
                        if (!empty($_SESSION['user'])) $_SESSION['user']['avatar_path'] = $relPath;
                    } else {
                        $_SESSION['flash_error'] = "Échec lors de l'enregistrement de l'avatar.";
                        header('Location: ' . BASE_URL . 'profil/edit'); exit;
                    }
                } else {
                    $_SESSION['flash_error'] = "Avatar invalide (JPG/PNG/WEBP/GIF, 2 Mo max).";
                    header('Location: ' . BASE_URL . 'profil/edit'); exit;
                }
            }
        }

        /* Préférences utilisateur (je prends la 1ère méthode dispo du modèle) */
        $prefsUpdated = false;
        $prefs = [
            'smoker'  => isset($_POST['pref_smoking']) ? (int)$_POST['pref_smoking'] : null,
            'animals' => isset($_POST['pref_pets'])    ? (int)$_POST['pref_pets']    : null,
            'music'   => isset($_POST['pref_music'])   ? (int)$_POST['pref_music']   : null,
            'chatty'  => isset($_POST['pref_chat'])    ? (int)$_POST['pref_chat']    : null,
            'ac'      => isset($_POST['pref_ac'])      ? (int)$_POST['pref_ac']      : null,
        ];
        $toSave = [];
        foreach ($prefs as $k=>$v) if ($v !== null) $toSave[$k] = $v;

        if ($id > 0 && $toSave) {
            foreach (['upsert','save','set','updateForUser','saveForUser'] as $m) {
                if (method_exists(UserPreferences::class, $m)) {
                    $prefsUpdated = (bool)UserPreferences::$m($id, $toSave);
                    break;
                }
            }
        }

        /* Changement de mot de passe (facultatif, avec un minimum de sécurité) */
        $pwChanged = false;
        $newPw  = trim((string)($_POST['new_password']     ?? ''));
        $confPw = trim((string)($_POST['confirm_password'] ?? ''));
        if ($newPw !== '') {
            if (mb_strlen($newPw) < 8) {
                $_SESSION['flash_error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
                header('Location: ' . BASE_URL . 'profil/edit'); exit;
            }
            if ($newPw !== $confPw) {
                $_SESSION['flash_error'] = 'Les mots de passe ne correspondent pas.';
                header('Location: ' . BASE_URL . 'profil/edit'); exit;
            }
            if (!User::updatePassword($id, $newPw)) {
                $_SESSION['flash_error'] = 'Échec de la mise à jour du mot de passe.';
                header('Location: ' . BASE_URL . 'profil/edit'); exit;
            }
            $pwChanged = true;
        }

        // update profil si j’ai des infos à persister
        $profileUpdated = $id>0 && $data ? User::updateProfile($id, $data) : false;

        // je rafraîchis la session après maj
        $fresh = $id ? User::findById($id) : null;
        if ($fresh) $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $fresh);

        // je construis un message propre (profil, mot de passe, avatar, préférences)
        $parts = [];
        if ($profileUpdated) $parts[] = 'profil';
        if ($pwChanged)      $parts[] = 'mot de passe';
        if ($avatarUpdated)  $parts[] = 'avatar';
        if ($prefsUpdated)   $parts[] = 'préférences';
        $_SESSION['flash_success'] = $parts ? (ucfirst(implode(', ', $parts)) . ' mis à jour.') : 'Aucun changement.';

        header('Location: ' . BASE_URL . 'profil/edit'); exit;
    }

    /** Petit alias /profile/edit -> /profil/edit (SEO/UX) */
    public function redirectToProfilEdit(): void
    {
        header('Location: ' . BASE_URL . 'profil/edit', true, 301); exit;
    }

    /* ====== VÉHICULES ====== */

    public function vehicleForm(): void
    {
        Security::ensure(['USER']);
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $id  = (int)($_GET['id'] ?? 0);

        // si id présent, je vérifie que le véhicule m’appartient
        $vehicle = null;
        if ($id > 0) {
            $vehicle = Vehicle::findOwned($id, $uid);
            if (!$vehicle) {
                $_SESSION['flash_error'] = "Véhicule introuvable.";
                header('Location: ' . BASE_URL . 'user/dashboard'); exit;
            }
        }

        $this->render('pages/vehicle_form', [
            'title'   => $id ? 'Modifier mon véhicule' : 'Ajouter un véhicule',
            'vehicle' => $vehicle
        ]);
    }

    public function addVehicle(): void
    {
        Security::ensure(['USER']);
        // CSRF obligatoire
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
            header('Location: ' . BASE_URL . 'user/vehicle'); exit;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        // je récupère les champs essentiels
        $data = [
            'brand'          => trim((string)($_POST['brand'] ?? '')),
            'model'          => trim((string)($_POST['model'] ?? '')),
            'color'          => trim((string)($_POST['color'] ?? '')),
            'energy'         => trim((string)($_POST['energy'] ?? '')),
            'plate'          => trim((string)($_POST['plate'] ?? '')),
            'first_reg_date' => trim((string)($_POST['first_reg_date'] ?? '')),
            'seats'          => (int)($_POST['seats'] ?? 0),
        ];

        // validation minimum côté serveur
        if ($data['brand']==='' || $data['model']==='' || $data['plate']==='' || $data['seats']<=0) {
            $_SESSION['flash_error'] = 'Marque, modèle, plaque et places sont obligatoires.';
            header('Location: ' . BASE_URL . 'user/vehicle'); exit;
        }

        $ok = Vehicle::create($uid, $data);
        $_SESSION['flash_success'] = $ok ? 'Véhicule ajouté.' : 'Échec de l’ajout.';
        header('Location: ' . BASE_URL . 'user/dashboard'); exit;
    }

    public function editVehicle(): void
    {
        Security::ensure(['USER']);
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $id  = (int)($_POST['id'] ?? 0);

        // je protège l’accès: je ne modifie que mes véhicules
        if (!$id || !Vehicle::findOwned($id, $uid)) {
            $_SESSION['flash_error'] = "Véhicule introuvable.";
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        $data = [
            'brand'          => trim((string)($_POST['brand'] ?? '')),
            'model'          => trim((string)($_POST['model'] ?? '')),
            'color'          => trim((string)($_POST['color'] ?? '')),
            'energy'         => trim((string)($_POST['energy'] ?? '')),
            'plate'          => trim((string)($_POST['plate'] ?? '')),
            'first_reg_date' => trim((string)($_POST['first_reg_date'] ?? '')),
            'seats'          => (int)($_POST['seats'] ?? 0),
        ];

        $ok = Vehicle::update($id, $uid, $data);
        $_SESSION['flash_success'] = $ok ? 'Véhicule mis à jour.' : 'Aucun changement.';
        header('Location: ' . BASE_URL . 'user/dashboard'); exit;
    }

    public function deleteVehicle(): void
    {
        Security::ensure(['USER']);
        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $id  = (int)($_POST['id'] ?? 0);

        $ok = ($id>0) ? Vehicle::delete($id, $uid) : false;
        $_SESSION['flash_success'] = $ok ? 'Véhicule supprimé.' : 'Suppression impossible.';
        header('Location: ' . BASE_URL . 'user/dashboard'); exit;
    }

    /* ===== TRAJETS : créer/démarrer/terminer/annuler ===== */

    public function createRide(): void
    {
        Security::ensure(['USER']);
        $uid = (int)($_SESSION['user']['id'] ?? 0);

        // je force l’ajout d’un véhicule avant de pouvoir publier un trajet
        $vehicles = $uid ? Vehicle::forUser($uid) : [];
        if (empty($vehicles)) {
            $_SESSION['flash_error'] = "Ajoutez d'abord un véhicule pour publier un trajet.";
            header('Location: ' . BASE_URL . 'user/vehicle'); exit;
        }

        // je gère la soumission POST
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
                $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }

            // je vérifie que le véhicule m’appartient
            $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
            if ($vehicleId <= 0 || !Vehicle::findOwned($vehicleId, $uid)) {
                $_SESSION['flash_error'] = "Véhicule invalide.";
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }

            // payload minimal pour créer un trajet
            $payload = [
                'from_city'  => trim((string)($_POST['from_city']  ?? '')),
                'to_city'    => trim((string)($_POST['to_city']    ?? '')),
                'date_start' => trim((string)($_POST['date_start'] ?? '')),
                'date_end'   => trim((string)($_POST['date_end']   ?? '')),
                'seats'      => (int)($_POST['seats'] ?? 0),
                'price'      => (int)($_POST['price'] ?? 0),
                'notes'      => trim((string)($_POST['notes'] ?? '')),
            ];

            // validation de base
            if ($payload['from_city']==='' || $payload['to_city']==='' || $payload['date_start']==='' || $payload['date_end']==='' || $payload['seats']<=0) {
                $_SESSION['flash_error'] = 'Ville départ, arrivée, dates et places sont obligatoires.';
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }

            // cohérence des dates (arrivée > départ)
            try {
                $ds = new \DateTime($payload['date_start']);
                $de = new \DateTime($payload['date_end']);
                if ($de <= $ds) {
                    $_SESSION['flash_error'] = 'La date/heure d’arrivée doit être postérieure au départ.';
                    header('Location: ' . BASE_URL . 'user/ride/create'); exit;
                }
            } catch (\Throwable $e) {
                $_SESSION['flash_error'] = 'Format de date invalide.';
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }

            // je tente d’utiliser createForDriver si dispo, sinon je retombe sur create(...)
            $ok = false;
            if (method_exists(Ride::class, 'createForDriver')) {
                $ok = (bool)Ride::createForDriver($uid, $vehicleId, $payload);
            } else {
                try {
                    $newId = Ride::create(
                        $uid, $vehicleId,
                        $payload['from_city'], $payload['to_city'],
                        $payload['date_start'], $payload['date_end'],
                        (int)$payload['price'], (int)$payload['seats']
                    );
                    $ok = $newId > 0;
                } catch (\ArgumentCountError|\TypeError $e) {
                    $ok = false;
                }
            }

            /* === J’envoie un e-mail au conducteur pour confirmer la publication (synchrone, mais non bloquant si erreur) === */
            if ($ok) {
                try {
                    $driverUser = User::findById($uid) ?: [];
                    $email = (string)($driverUser['email'] ?? '');
                    if ($email !== '') {
                        $driver = [
                            'email'  => $email,
                            'pseudo' => (string)($driverUser['prenom'] ?? $driverUser['nom'] ?? 'Chauffeur'),
                            'nom'    => (string)($driverUser['nom'] ?? ''),
                        ];
                        $rideForMail = [
                            'from_city'  => (string)($payload['from_city']  ?? ''),
                            'to_city'    => (string)($payload['to_city']    ?? ''),
                            'date_start' => (string)($payload['date_start'] ?? ''),
                            'date_end'   => (string)($payload['date_end']   ?? ''),
                            'price'      => (int)($payload['price']         ?? 0),
                            'seats'      => (int)($payload['seats']         ?? 0),
                            'seats_left' => (int)($payload['seats']         ?? 0),
                        ];
                        $sent = (new Mailer())->sendRidePublished($driver, $rideForMail);
                        if (!$sent) {
                            // je log au cas où la conf SMTP n’est pas bonne
                            error_log('[MAIL createRide] sendRidePublished=false (vérifier config SMTP / logs PHPMailer)');
                            $_SESSION['flash_warning'] = "Trajet publié (⚠️ e-mail de confirmation non envoyé).";
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[MAIL createRide] '.$e->getMessage());
                    $_SESSION['flash_warning'] = "Trajet publié (⚠️ e-mail non envoyé).";
                }
            }
            /* === fin notification e-mail === */

            if ($ok) {
                $_SESSION['flash_success'] = 'Trajet publié.';
                header('Location: ' . BASE_URL . 'user/dashboard'); exit;
            } else {
                $_SESSION['flash_error'] = "Impossible d’enregistrer le trajet.";
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }
        }

        // GET -> j’affiche le formulaire de création de trajet
        $this->render('pages/create_ride', [
            'title'    => 'Publier un trajet',
            'vehicles' => $vehicles
        ]);
    }

    public function startRide(): void
    {
        Security::ensure(['USER']);

        // je récupère l’ID du trajet depuis GET ou POST
        $rideId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($rideId <= 0) { $_SESSION['flash_error']='Trajet invalide.'; header('Location: ' . BASE_URL . 'user/dashboard'); exit; }

        $ride = Ride::findById($rideId);
        $uid  = (int)($_SESSION['user']['id'] ?? 0);

        // je m’assure que le trajet m’appartient (je suis le conducteur)
        if (!$ride || (int)$ride['driver_id'] !== $uid) {
            $_SESSION['flash_error'] = "Trajet introuvable ou non autorisé.";
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        $pdo = Sql::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->beginTransaction();

            // je verrouille la ligne pour éviter un double démarrage
            $st = $pdo->prepare("SELECT status, date_start FROM rides WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$rideId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $status = strtoupper((string)($row['status'] ?? ''));

            if (in_array($status, ['FINISHED','CANCELLED'], true)) {
                $pdo->commit();
                $_SESSION['flash_info'] = 'Ce trajet est déjà clôturé.';
                header('Location: ' . BASE_URL . 'user/dashboard'); exit;
            }

            // je passe le trajet en STARTED (si pas déjà démarré)
            $pdo->prepare("
                UPDATE rides 
                   SET status='STARTED', 
                       date_start = COALESCE(date_start, NOW())
                 WHERE id=:id
            ")->execute([':id'=>$rideId]);

            $pdo->commit();

            $_SESSION['flash_success'] = "Trajet démarré 👍";
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['flash_error'] = "Impossible de démarrer : ".$e->getMessage();
        }

        header('Location: ' . BASE_URL . 'user/dashboard'); exit;
    }

    public function endRide(): void
    {
        Security::ensure(['USER']);

        $rideId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($rideId <= 0) { $_SESSION['flash_error']='Trajet invalide.'; header('Location: ' . BASE_URL . 'user/dashboard'); exit; }

        $ride = Ride::findById($rideId);
        $uid  = (int)($_SESSION['user']['id'] ?? 0);
        if (!$ride || (int)$ride['driver_id'] !== $uid) {
            $_SESSION['flash_error'] = "Trajet introuvable ou non autorisé.";
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        $pdo = Sql::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->beginTransaction();

            // je verrouille pour éviter un double "fin de trajet"
            $st = $pdo->prepare("SELECT status FROM rides WHERE id = :id FOR UPDATE");
            $st->execute([':id'=>$rideId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $status = strtoupper((string)($row['status'] ?? ''));

            if ($status === 'CANCELLED') {
                $pdo->commit();
                $_SESSION['flash_info'] = "Ce trajet a été annulé auparavant.";
                header('Location: ' . BASE_URL . 'user/dashboard'); exit;
            }

            // je marque comme FINISHED et j’enregistre une date de fin si manquante
            $pdo->prepare("UPDATE rides SET status='FINISHED', date_end = COALESCE(date_end, NOW()) WHERE id=:id")
                ->execute([':id'=>$rideId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['flash_error'] = "Impossible de clôturer : ".$e->getMessage();
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        /* J’envoie les invitations d’avis aux passagers (robuste + liens absolus) */
        $passengers = Ride::passengersWithEmailForRide($rideId);
        $mailer = new Mailer();

        // je construis l’URL de base (env ou auto depuis HTTP_HOST)
        $base = rtrim(
            getenv('APP_URL')
            ?: (
                isset($_SERVER['HTTP_HOST'])
                    ? (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . $_SERVER['HTTP_HOST'])
                    : 'http://localhost:8080'
              ),
            '/'
        );

        $sent = 0; $failed = 0;
        foreach ($passengers as $p) {
            $toEmail = (string)($p['email'] ?? '');
            if ($toEmail === '') { continue; }

            // je signe un jeton limité 7 jours pour écrire un avis
            $token = Security::signReviewToken($rideId, (int)$p['id'], time() + 7 * 86400);
            $link  = $base . '/reviews/new?token=' . rawurlencode($token);

            try {
                $mailer->sendReviewInvite(
                    [
                        'email'  => $toEmail,
                        'pseudo' => (string)($p['display_name'] ?? $toEmail),
                    ],
                    $ride,
                    [
                        'email'        => (string)($ride['driver_email'] ?? ''),
                        'display_name' => trim((string)($_SESSION['user']['prenom'] ?? '').' '.(string)($_SESSION['user']['nom'] ?? 'Chauffeur')),
                    ],
                    $link
                );
                // je log le lien pour debug si besoin
                error_log('[REVIEW_INVITE_LINK] ' . $link);

                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                error_log('[EcoRide][mail avis] ride='.$rideId.' to='.$toEmail.' error='.$e->getMessage());
            }
        }

        $_SESSION['flash_'.($failed? 'warning':'success')] =
            "Trajet marqué terminé. {$sent} e-mail(s) envoyé(s)".($failed? ", {$failed} échec(s).":'.');

        header('Location: ' . BASE_URL . 'user/dashboard'); exit;
    }

    public function cancelRide(): void
    {
        Security::ensure(['USER']);

        $rideId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($rideId <= 0) { $_SESSION['flash_error']='Trajet invalide.'; header('Location: ' . BASE_URL . 'user/dashboard'); exit; }

        $ride = Ride::findById($rideId);
        $uid  = (int)($_SESSION['user']['id'] ?? 0);
        if (!$ride || (int)$ride['driver_id'] !== $uid) {
            $_SESSION['flash_error'] = "Trajet introuvable ou non autorisé.";
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        $pdo = Sql::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->beginTransaction();

            // je verrouille d’abord les réservations confirmées de ce trajet
            $pdo->prepare("SELECT id FROM bookings WHERE ride_id=:r AND UPPER(status)='CONFIRMED' FOR UPDATE")
                ->execute([':r'=>$rideId]);

            // je récupère les bookings concernés + emails pour informer et rembourser
            $bs = $pdo->prepare("
                SELECT b.id, b.passenger_id, b.credits_spent, u.email, u.prenom, u.nom
                FROM bookings b
                JOIN users u ON u.id = b.passenger_id
                WHERE b.ride_id = :r AND UPPER(b.status)='CONFIRMED'
            ");
            $bs->execute([':r'=>$rideId]);
            $bookings = $bs->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // pour chaque réservation: je rembourse les crédits et je mets le statut à CANCELLED
            foreach ($bookings as $b) {
                $credits = (int)($b['credits_spent'] ?? 0);
                if ($credits > 0) {
                    $pdo->prepare("UPDATE users SET credits = credits + :c WHERE id = :uid")
                        ->execute([':c'=>$credits, ':uid'=>(int)$b['passenger_id']]);
                }
                $pdo->prepare("UPDATE bookings SET status='CANCELLED' WHERE id=:id")
                    ->execute([':id'=>(int)$b['id']]);
            }

            // je passe le trajet en CANCELLED
            $pdo->prepare("UPDATE rides SET status='CANCELLED' WHERE id=:id")->execute([':id'=>$rideId]);

            $pdo->commit();

            // Après commit, j’envoie les e-mails d’info (je n’échoue pas la page si SMTP HS)
            $mailer = new Mailer();
            foreach ($bookings as $b) {
                $to = (string)($b['email'] ?? '');
                if ($to === '') continue;
                $name = trim((string)($b['prenom'] ?? '').' '.(string)($b['nom'] ?? 'Passager'));
                $subject = "Trajet annulé – remboursement effectué";
                $html = "<p>Bonjour {$name},</p>
                         <p>Le trajet <strong>".htmlspecialchars((string)$ride['from_city'])." → ".htmlspecialchars((string)$ride['to_city'])."</strong> a été annulé par le conducteur.</p>
                         <p>Vos crédits ont été <strong>remboursés</strong> sur votre compte.</p>
                         <p>Merci de votre compréhension.<br>EcoRide</p>";
                try { $mailer->send($to, $name, $subject, $html); } catch (\Throwable $e) {
                    error_log('[EcoRide][mail annulation] ride='.$rideId.' to='.$to.' error='.$e->getMessage());
                }
            }

            $_SESSION['flash_success'] = "Trajet annulé. Tous les passagers ont été remboursés et informés.";
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['flash_error'] = "Annulation impossible : ".$e->getMessage();
        }

        header('Location: ' . BASE_URL . 'user/dashboard'); exit;
    }

    /* ====== MA NOTE / AVIS ====== */
    /**
     * Page “Ma note” (moyenne + distribution + listes d’avis approuvés/en attente).
     * Vue: app/Views/pages/driver_ratings.php
     */
    public function ratings(): void
    {
        Security::ensure(['USER']);
        $uid = (int)($_SESSION['user']['id'] ?? 0);

        $avg = null;
        $count = 0;
        $approved = [];
        $pending  = [];
        $distribution = [1=>0,2=>0,3=>0,4=>0,5=>0];

        /* 1) Je tente via le modèle Review si les méthodes existent */
        try {
            $rm = new Review();

            if (method_exists($rm, 'avgForDriver')) {
                $avg = $rm->avgForDriver($uid); // moyenne arrondie à 0.1 côté modèle
            }
            if (method_exists($rm, 'avgForDrivers')) {
                $map = $rm->avgForDrivers([$uid]);
                if (isset($map[$uid]['count'])) {
                    $count = (int)$map[$uid]['count'];
                }
            }

            // je prends les 100 derniers approuvés si possible
            if (method_exists($rm, 'approvedForDriver')) {
                $approved = $rm->recentApprovedForDriver($uid, 100);
            } elseif (method_exists($rm, 'recentApprovedForDriver')) {
                $approved = $rm->recentApprovedForDriver($uid, 100);
            }

            // et les en attente
            if (method_exists($rm, 'pendingForDriver')) {
                $pending = $rm->pendingForDriver($uid, 100);
            }

            // je construis une distribution simple [1..5]
            foreach ($approved as $a) {
                $n = (int)($a['note'] ?? $a['rating'] ?? 0);
                if ($n >=1 && $n <= 5) $distribution[$n]++;
            }
        } catch (\Throwable $e) {
            // si le modèle n’est pas prêt, j’essaie le fallback Mongo direct
        }

        /* 2) Fallback MongoDB direct si je n’ai rien (ou presque) */
        if ($avg === null || ($count === 0 && empty($approved) && empty($pending))) {
            try {
                $fb = $this->mongoFallbackRatings($uid);
                $avg          = $fb['avg'];
                $count        = $fb['count'];
                $approved     = $fb['approved'];
                $pending      = $fb['pending'];
                $distribution = $fb['distribution'];
            } catch (\Throwable $e) {
                // si Mongo est totalement indispo, je n’affiche simplement rien
            }
        }

        $this->render('pages/driver_ratings', [
            'title'        => 'Ma note',
            'avg'          => $avg,
            'count'        => $count,
            'reviews'      => $approved,  // avis approuvés
            'pending'      => $pending,   // avis en attente
            'distribution' => $distribution
        ]);
    }

    /* ====== Helpers privés ====== */

    /**
     * Fallback MongoDB quand le modèle Review n’est pas utilisable.
     * - Je cherche dans la base "ecoride", collection "avis" (overrides via env).
     * - J’accepte plusieurs noms de champs possibles (selon tests/captures).
     */
    private function mongoFallbackRatings(int $uid): array
    {
        if (!class_exists(\MongoDB\Client::class)) {
            // si l’extension MongoDB n’est pas installée, je remonte des valeurs neutres
            return [
                'avg'=>null,'count'=>0,'approved'=>[],'pending'=>[],'distribution'=>[1=>0,2=>0,3=>0,4=>0,5=>0]
            ];
        }

        // je lis DSN/DB/collection depuis l’environnement (avec valeurs par défaut)
        $dsn     = getenv('MONGO_DSN') ?: 'mongodb://127.0.0.1:27017';
        $dbName  = getenv('MONGO_DB')  ?: 'ecoride';
        $collName= getenv('MONGO_COLLECTION_REVIEWS') ?: 'avis';

        $client = new \MongoDB\Client($dsn);
        $coll   = $client->selectCollection($dbName, $collName);

        // je tolère différentes clés possibles rencontrées dans les données
        $driverMatch = [
            '$or' => [
                ['identifiant_du_conducteur' => $uid],
                ['driver_id'                 => $uid],
                ['identifiant_conducteur'    => $uid],
                ['conducteur_id'             => $uid],
                ['identifiant'               => $uid],
            ]
        ];

        // statuts : APPROUVÉ/APPROUVE/APPROVED / PENDING...
        $approvedMatch = [
            '$or' => [
                ['statut' => 'APPROUVÉ'],
                ['statut' => 'APPROUVE'],
                ['status' => 'APPROVED'],
            ]
        ];
        $pendingMatch = [
            '$or' => [
                ['statut' => 'EN ATTENTE'],
                ['status' => 'PENDING'],
            ]
        ];

        $opts = ['sort'=>['_id'=>-1], 'limit'=>100];

        $approvedDocs = $coll->find(['$and'=>[$driverMatch, $approvedMatch]], $opts);
        $pendingDocs  = $coll->find(['$and'=>[$driverMatch, $pendingMatch]],  $opts);

        $approved = [];
        $pending  = [];
        $sum = 0; $count = 0;
        $distribution = [1=>0,2=>0,3=>0,4=>0,5=>0];

        // je normalise les docs et je calcule la moyenne + distribution
        foreach ($approvedDocs as $d) {
            $row = $this->normalizeReviewDoc($d);
            $approved[] = $row;
            $n = (int)($row['note'] ?? 0);
            if ($n>=1 && $n<=5) { $sum += $n; $count++; $distribution[$n]++; }
        }
        foreach ($pendingDocs as $d) {
            $pending[] = $this->normalizeReviewDoc($d);
        }

        $avg = $count ? round($sum / $count, 1) : null;

        return [
            'avg' => $avg,
            'count' => $count,
            'approved' => $approved,
            'pending'  => $pending,
            'distribution' => $distribution
        ];
    }

    /** Je transforme un document Mongo (BSON) en tableau simple, avec des clés stables. */
    private function normalizeReviewDoc($doc): array
    {
        // je convertis en array (safe)
        $arr = json_decode(json_encode($doc, JSON_PARTIAL_OUTPUT_ON_ERROR), true) ?: [];

        return [
            'id'             => (string)($arr['_id']['$oid'] ?? $arr['_id'] ?? ''),
            'ride_id'        => (int)($arr['identifiant_de_trajet'] ?? $arr['identifiant_trajet'] ?? $arr['ride_id'] ?? 0),
            'driver_id'      => (int)($arr['identifiant_du_conducteur'] ?? $arr['driver_id'] ?? 0),
            'passenger_id'   => (int)($arr['identifiant_passager'] ?? $arr['passenger_id'] ?? 0),
            'note'           => (int)($arr['note'] ?? $arr['rating'] ?? 0),
            'commentaire'    => (string)($arr['commentaire'] ?? $arr['comment'] ?? ''),
            'statut'         => (string)($arr['statut'] ?? $arr['status'] ?? ''),
            'jeton_id'       => (string)($arr['jeton_id'] ?? $arr['token_id'] ?? ''),
            'created_at'     => (string)($arr['created_at'] ?? $arr['date'] ?? ''),
        ];
    }
}
