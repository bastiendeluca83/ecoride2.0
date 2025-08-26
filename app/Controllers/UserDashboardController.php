<?php  
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\User;
use App\Models\Ride;
use App\Models\Booking;
use App\Models\Vehicle; // <-- AJOUT

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
        $vehicles    = $uid ? Vehicle::forUser($uid) : []; // <-- AJOUT

        $this->render('dashboard/user', [
            'title'        => 'Espace utilisateur',
            'user'         => $user,
            'reservations' => $reservations,
            'rides'        => $rides,
            'vehicles'     => $vehicles, // <-- AJOUT
        ]);
    }

    /** GET /profil/edit */
    public function editForm(): void
    {
        Security::ensure(['USER']);
        $id   = (int)($_SESSION['user']['id'] ?? 0);
        $user = $id ? (User::findById($id) ?? ($_SESSION['user'] ?? null)) : ($_SESSION['user'] ?? null);

        // <- chemin de vue déplacé vers pages/
        $this->render('pages/profile_edit', [
            'title' => 'Modifier mon profil',
            'user'  => $user,
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
        ];
        $data = [];
        foreach ($payload as $k=>$v) {
            if ($v !== null && $v !== '') { $data[$k] = is_string($v) ? trim($v) : $v; }
        }

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
            // Hash côté modèle
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

        if ($pwChanged && $profileUpdated) {
            $_SESSION['flash_success'] = 'Profil et mot de passe mis à jour.';
        } elseif ($pwChanged) {
            $_SESSION['flash_success'] = 'Mot de passe mis à jour.';
        } elseif ($profileUpdated) {
            $_SESSION['flash_success'] = 'Profil mis à jour.';
        } else {
            $_SESSION['flash_success'] = 'Aucun changement.';
        }

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
       VÉHICULES (on remplace les redirections par du vrai code)
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

        // <- chemin de vue déplacé vers pages/
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

    /* Legacy alias — conservés mais non utilisés dans le flux actuel */
    public function createRide(): void    { Security::ensure(['USER']); $this->render('dashboard/create_ride',['title'=>'Publier un trajet']); }
    public function history(): void       { Security::ensure(['USER']); $this->render('dashboard/history',['title'=>'Historique']); }
    public function startRide(): void     { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
    public function endRide(): void       { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
    public function cancelRide(): void    { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
}
