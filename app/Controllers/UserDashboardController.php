<?php  
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\User;
use App\Models\Ride;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Models\UserPreferences;
use App\Models\Review;

final class UserDashboardController extends BaseController
{
    public function index(): void
    {
        Security::ensure(['USER']);
        $uid  = (int)($_SESSION['user']['id'] ?? 0);

        // Rafraîchit crédits & profil depuis la BDD
        $fresh = $uid ? User::findById($uid) : null;
        if ($fresh) {
            $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $fresh);
        }
        $user = $_SESSION['user'] ?? ['nom'=>'Utilisateur','credits'=>0,'total_rides'=>0];

        // Données SQL pour dashboard
        $reservations = $uid ? Booking::forPassengerUpcoming($uid) : [];
        $rides       = $uid ? Ride::forDriverUpcoming($uid) : [];
        $vehicles    = $uid ? Vehicle::forUser($uid) : [];

        $this->render('dashboard/user', [
            'title'        => 'Espace utilisateur',
            'user'         => $user,
            'reservations' => $reservations,
            'rides'        => $rides,
            'vehicles'     => $vehicles,
        ]);
    }

    /** GET /profil/edit */
    public function editForm(): void
    {
        Security::ensure(['USER']);
        $id   = (int)($_SESSION['user']['id'] ?? 0);
        $user = $id ? (User::findById($id) ?? ($_SESSION['user'] ?? null)) : ($_SESSION['user'] ?? null);

        // Préférences utilisateur (si le modèle existe)
        $prefs = [];
        foreach (['get','findByUserId','forUser'] as $m) {
            if (method_exists(UserPreferences::class, $m)) {
                $prefs = UserPreferences::$m($id) ?? [];
                break;
            }
        }

        // <- vues déplacées dans pages/
        $this->render('pages/profile_edit', [
            'title' => 'Modifier mon profil',
            'user'  => $user,
            'prefs' => $prefs,
        ]);
    }

    /** POST /profil/edit */
    public function update(): void
    {
        Security::ensure(['USER']);

        if (!\App\Security\Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
            header('Location: ' . BASE_URL . 'profil/edit'); exit;
        }

        $id = (int)($_SESSION['user']['id'] ?? 0);

        // Accepte FR ou EN (profil)
        $payload = [
            'nom'         => $_POST['nom']        ?? null,
            'prenom'      => $_POST['prenom']     ?? null,
            'email'       => $_POST['email']      ?? null,
            'telephone'   => $_POST['telephone']  ?? null,
            'adresse'     => $_POST['adresse']    ?? null,
            'bio'         => $_POST['bio']        ?? null,
            'last_name'   => $_POST['last_name']  ?? null,
            'first_name'  => $_POST['first_name'] ?? null,
            'phone'       => $_POST['phone']      ?? null,
            'address'     => $_POST['address']    ?? null,
            'date_naissance' => $_POST['date_naissance'] ?? null, // [ADD date_naissance]
        ];
        $data = [];
        foreach ($payload as $k=>$v) {
            if ($v !== null && $v !== '') { $data[$k] = is_string($v) ? trim($v) : $v; }
        }

        /* ---------- Upload avatar (optionnel) ---------- */
        $avatarUpdated = false;
        if (!empty($_FILES['avatar']) && is_array($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['avatar'];
            if ($f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
                $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                $mime = @mime_content_type($f['tmp_name']) ?: '';
                $sizeOk = (int)$f['size'] <= 2 * 1024 * 1024; // 2 Mo
                if (in_array($mime, $allowed, true) && $sizeOk) {
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg');
                    $baseDir = dirname(__DIR__, 2) . '/public/uploads/avatars';
                    if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);
                    $filename = 'u'.$id.'_'.time().'.'.$ext;
                    $dest = $baseDir . '/' . $filename;
                    if (@move_uploaded_file($f['tmp_name'], $dest)) {
                        $relPath = 'uploads/avatars/' . $filename;
                        // Tente la méthode dédiée si elle existe, sinon via updateProfile
                        if (method_exists(User::class, 'updateAvatar')) {
                            $avatarUpdated = (bool)User::updateAvatar($id, $relPath);
                        } else {
                            $avatarUpdated = (bool)User::updateProfile($id, ['avatar_path'=>$relPath]);
                        }
                        // Pour rafraîchir l'affichage éventuel
                        if (!empty($_SESSION['user'])) {
                            $_SESSION['user']['avatar_path'] = $relPath;
                        }
                    } else {
                        $_SESSION['flash_error'] = "Échec lors de l'enregistrement de l'avatar.";
                        header('Location: ' . BASE_URL . 'profil/edit'); exit;
                    }
                } else {
                    $_SESSION['flash_error'] = "Avatar invalide (formats acceptés: JPG/PNG/WEBP/GIF, max 2 Mo).";
                    header('Location: ' . BASE_URL . 'profil/edit'); exit;
                }
            }
        }
        /* ------------------------------------------------ */

        /* ---------- Préférences (fumeur/animaux/musique/discussion/clim) ---------- */
        $prefsUpdated = false;
        $prefs = [
            'smoking'  => isset($_POST['pref_smoking'])  ? (int)!!$_POST['pref_smoking']  : null,
            'pets'     => isset($_POST['pref_pets'])     ? (int)!!$_POST['pref_pets']     : null,
            'music'    => isset($_POST['pref_music'])    ? (int)!!$_POST['pref_music']    : null,
            'chat'     => isset($_POST['pref_chat'])     ? (int)!!$_POST['pref_chat']     : null,
            'ac'       => isset($_POST['pref_ac'])       ? (int)!!$_POST['pref_ac']       : null,
        ];
        // Nettoie les null (si le formulaire ne les a pas envoyés)
        $toSave = [];
        foreach ($prefs as $k=>$v) if ($v !== null) $toSave[$k] = $v;

        if ($id > 0 && $toSave) {
            // Cherche une méthode probable dans le modèle
            foreach (['save','upsertForUser','set','updateForUser','saveForUser'] as $m) {
                if (method_exists(UserPreferences::class, $m)) {
                    $prefsUpdated = (bool)UserPreferences::$m($id, $toSave);
                    break;
                }
            }
        }
        /* ------------------------------------------------------------------------- */

        /* ---------- Changement de mot de passe (optionnel) ---------- */
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
        /* ----------------------------------------------------------- */

        // Mise à jour du profil (si champs fournis)
        $profileUpdated = $id>0 && $data ? User::updateProfile($id, $data) : false;

        // RAF session (ex: email/pseudo)
        $fresh = $id ? User::findById($id) : null;
        if ($fresh) { $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $fresh); }

        // Messages combinés
        $parts = [];
        if ($profileUpdated) $parts[] = 'profil';
        if ($pwChanged)      $parts[] = 'mot de passe';
        if ($avatarUpdated)  $parts[] = 'avatar';
        if ($prefsUpdated)   $parts[] = 'préférences';
        $_SESSION['flash_success'] = $parts ? (ucfirst(implode(', ', $parts)) . ' mis à jour.') : 'Aucun changement.';

        header('Location: ' . BASE_URL . 'profil/edit'); exit;
    }

    /** Alias /profile/edit -> /profil/edit (301) */
    public function redirectToProfilEdit(): void
    {
        header('Location: ' . BASE_URL . 'profil/edit', true, 301); exit;
    }

    /* =========================
       ALIAS/ADAPTATEURS AJOUTÉS
       ========================= */
    public function editProfile(): void { $this->editForm(); }
    public function updateProfile(): void { $this->update(); }
    public function profile(): void { $this->editForm(); }
    public function legacyProfileRedirect(): void { $this->redirectToProfilEdit(); }

    /* =========================
       VÉHICULES
       ========================= */

    /** GET /user/vehicle (ajout) ou /user/vehicle/edit?id=... (édition) */
    public function vehicleForm(): void
    {
        Security::ensure(['USER']);
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $id  = (int)($_GET['id'] ?? 0);

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

    /** POST /user/vehicle/add */
    public function addVehicle(): void
    {
        Security::ensure(['USER']);
        if (!\App\Security\Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
            header('Location: ' . BASE_URL . 'user/vehicle'); exit;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $data = [
            'brand'          => trim((string)($_POST['brand'] ?? '')),
            'model'          => trim((string)($_POST['model'] ?? '')),
            'color'          => trim((string)($_POST['color'] ?? '')),
            'energy'         => trim((string)($_POST['energy'] ?? '')),
            'plate'          => trim((string)($_POST['plate'] ?? '')),
            'first_reg_date' => trim((string)($_POST['first_reg_date'] ?? '')),
            'seats'          => (int)($_POST['seats'] ?? 0),
        ];

        if ($data['brand']==='' || $data['model']==='' || $data['plate']==='' || $data['seats']<=0) {
            $_SESSION['flash_error'] = 'Marque, modèle, plaque et places sont obligatoires.';
            header('Location: ' . BASE_URL . 'user/vehicle'); exit;
        }

        $ok = Vehicle::create($uid, $data);
        $_SESSION['flash_success'] = $ok ? 'Véhicule ajouté.' : 'Échec de l’ajout.';
        header('Location: ' . BASE_URL . 'user/dashboard'); exit;
    }

    /** POST /user/vehicle/edit */
    public function editVehicle(): void
    {
        Security::ensure(['USER']);
        if (!\App\Security\Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $id  = (int)($_POST['id'] ?? 0);

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

    /** POST /user/vehicle/delete */
    public function deleteVehicle(): void
    {
        Security::ensure(['USER']);
        if (!\App\Security\Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
            header('Location: ' . BASE_URL . 'user/dashboard'); exit;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $id  = (int)($_POST['id'] ?? 0);

        $ok = ($id>0) ? Vehicle::delete($id, $uid) : false;
        $_SESSION['flash_success'] = $ok ? 'Véhicule supprimé.' : 'Suppression impossible.';
        header('Location: ' . BASE_URL . 'user/dashboard'); exit;
    }

    /* =========================
       Trajet : redirection conditionnelle + création
       ========================= */
    public function createRide(): void
    {
        Security::ensure(['USER']);
        $uid = (int)($_SESSION['user']['id'] ?? 0);

        // 1) Doit avoir au moins un véhicule, sinon → page ajout véhicule
        $vehicles = $uid ? Vehicle::forUser($uid) : [];
        if (empty($vehicles)) {
            $_SESSION['flash_error'] = "Ajoutez d'abord un véhicule pour publier un trajet.";
            header('Location: ' . BASE_URL . 'user/vehicle'); exit;
        }

        // 2) POST → création via modèle Ride
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!\App\Security\Security::checkCsrf($_POST['csrf'] ?? null)) {
                $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }

            $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
            if ($vehicleId <= 0 || !Vehicle::findOwned($vehicleId, $uid)) {
                $_SESSION['flash_error'] = "Véhicule invalide.";
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }

            $payload = [
                'from_city'  => trim((string)($_POST['from_city']  ?? '')),
                'to_city'    => trim((string)($_POST['to_city']    ?? '')),
                'date_start' => trim((string)($_POST['date_start'] ?? '')),
                'seats'      => (int)($_POST['seats'] ?? 0),
                'price'      => (int)($_POST['price'] ?? 0),
                'notes'      => trim((string)($_POST['notes'] ?? '')),
            ];

            if ($payload['from_city']==='' || $payload['to_city']==='' || $payload['date_start']==='' || $payload['seats']<=0) {
                $_SESSION['flash_error'] = 'Ville départ, arrivée, date et places sont obligatoires.';
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }

            $ok = false;
            if (method_exists(Ride::class, 'createForDriver')) {
                $ok = Ride::createForDriver($uid, $vehicleId, $payload);
            } elseif (method_exists(Ride::class, 'create')) {
                $ok = Ride::create($uid, $vehicleId, $payload);
            }

            if ($ok) {
                $_SESSION['flash_success'] = 'Trajet publié.';
                header('Location: ' . BASE_URL . 'user/dashboard'); exit;
            } else {
                $_SESSION['flash_error'] = "Impossible d’enregistrer le trajet (implémenter Ride::create*).";
                header('Location: ' . BASE_URL . 'user/ride/create'); exit;
            }
        }

        // 3) GET → affiche la page de création
        $this->render('pages/create_ride', [
            'title'    => 'Publier un trajet',
            'vehicles' => $vehicles
        ]);
    }

    /* Legacy alias — conservés mais non utilisés dans le flux actuel */
    public function history(): void       { Security::ensure(['USER']); $this->render('dashboard/history',['title'=>'Historique']); }
    public function startRide(): void     { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
    public function endRide(): void       { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
    public function cancelRide(): void    { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
}
