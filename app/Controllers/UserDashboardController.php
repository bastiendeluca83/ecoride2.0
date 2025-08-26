<?php  
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\User;
use App\Models\Ride;
use App\Models\Booking;

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

        $this->render('dashboard/user', [
            'title'        => 'Espace utilisateur',
            'user'         => $user,
            'reservations' => $reservations,
            'rides'        => $rides,
        ]);
    }

    /** GET /profil/edit */
    public function editForm(): void
    {
        Security::ensure(['USER']);
        $id   = (int)($_SESSION['user']['id'] ?? 0);
        $user = $id ? (User::findById($id) ?? ($_SESSION['user'] ?? null)) : ($_SESSION['user'] ?? null);

        $this->render('dashboard/profile_edit', [
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

        // Accepte FR ou EN
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
        foreach ($payload as $k=>$v) if ($v !== null && $v !== '') $data[$k] = is_string($v) ? trim($v) : $v;

        $ok = $id>0 && $data ? User::updateProfile($id, $data) : false;

        // RAF session
        $fresh = $id ? User::findById($id) : null;
        if ($fresh) $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $fresh);

        $_SESSION['flash_success'] = $ok ? 'Profil mis à jour.' : 'Aucun changement.';
        header('Location: ' . BASE_URL . 'profil/edit'); exit;
    }

    /** Alias /profile/edit -> /profil/edit (301) */
    public function redirectToProfilEdit(): void
    {
        header('Location: ' . BASE_URL . 'profil/edit', true, 301); exit;
    }

    /* =========================
       ALIAS/ADAPTATEURS AJOUTÉS
       (pour ne rien casser dans les routes/lien existants)
       ========================= */

    /** Certains anciens liens appellent "editProfile" (GET) */
    public function editProfile(): void { $this->editForm(); }

    /** Certains appellent "updateProfile" (POST) → on délègue à update() */
    public function updateProfile(): void { $this->update(); }

    /** Si un lien appelle "profile" (erreur vue dans ton screenshot) → montre le formulaire */
    public function profile(): void { $this->editForm(); }

    /** Tes routes utilisaient parfois legacyProfileRedirect() */
    public function legacyProfileRedirect(): void { $this->redirectToProfilEdit(); }

    /* Legacy alias — conservés mais non utilisés dans le flux actuel */
    public function addVehicle(): void    { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/profile'); }
    public function editVehicle(): void   { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/profile'); }
    public function deleteVehicle(): void { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/profile'); }

    public function createRide(): void    { Security::ensure(['USER']); $this->render('dashboard/create_ride',['title'=>'Publier un trajet']); }
    public function history(): void       { Security::ensure(['USER']); $this->render('dashboard/history',['title'=>'Historique']); }
    public function startRide(): void     { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
    public function endRide(): void       { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
    public function cancelRide(): void    { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
}
