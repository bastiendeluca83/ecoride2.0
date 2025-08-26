<?php 
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;
use App\Models\User;

final class UserDashboardController extends BaseController
{
    public function index(): void
    {
        Security::ensure(['USER']);
        $user = $_SESSION['user'] ?? ['nom'=>'Utilisateur','credits'=>0,'total_rides'=>0];
        $reservations = [];
        $rides = [];

        $this->render('dashboard/user', [
            'title'        => 'Espace utilisateur',
            'user'         => $user,
            'reservations' => $reservations,
            'rides'        => $rides,
        ]);
    }

    public function profile(): void
    {
        Security::ensure(['USER']);
        $id   = (int)($_SESSION['user']['id'] ?? 0);
        $user = User::findById($id) ?? ($_SESSION['user'] ?? null);

        $this->render('dashboard/profile', [
            'title' => 'Mon profil',
            'user'  => $user,
        ]);
    }

    /** GET /profil/edit */
    public function editForm(): void
    {
        Security::ensure(['USER']);
        $id = (int)($_SESSION['user']['id'] ?? 0);

        // Essai BDD, sinon fallback session (on affiche quand même le form)
        $user = null;
        try { if ($id > 0) $user = User::findById($id); } catch (\Throwable $e) { error_log('[profil/edit] '.$e->getMessage()); }
        if (!$user) {
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                $_SESSION['flash'][] = ['type'=>'danger','text'=>"Utilisateur introuvable."];
                header('Location: ' . BASE_URL . 'user/dashboard'); exit;
            }
        }

        $this->render('dashboard/profile_edit', [
            'title' => 'Modifier mon profil',
            'user'  => $user,
        ]);
    }

    /** POST /profil/edit */
    public function update(): void
    {
        Security::ensure(['USER']);

        if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['flash'][] = ['type'=>'danger','text'=>'Session expirée, veuillez réessayer.'];
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
            'last_name'   => $_POST['last_name']  ?? null,
            'first_name'  => $_POST['first_name'] ?? null,
            'phone'       => $_POST['phone']      ?? null,
            'address'     => $_POST['address']    ?? null,
        ];
        $data = [];
        foreach ($payload as $k=>$v) if ($v !== null && $v !== '') $data[$k] = is_string($v) ? trim($v) : $v;

        $ok = false;
        try { $ok = $data ? User::updateProfile($id, $data) : false; }
        catch (\Throwable $e) { error_log('[profil/update] ' . $e->getMessage()); }

        // Rafraîchir la session avec la BDD si possible
        $fresh = null;
        try { if ($id > 0) $fresh = User::findById($id); } catch (\Throwable $e) { error_log('[profil/update find] '.$e->getMessage()); }
        if ($fresh) $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $fresh);

        $_SESSION['flash'][] = ['type' => $ok ? 'success' : 'warning', 'text' => $ok ? 'Profil mis à jour.' : 'Aucun changement.'];
        header('Location: ' . BASE_URL . 'profil/edit'); exit;
    }

    /** Alias /profile/edit -> /profil/edit (301) */
    public function redirectToProfilEdit(): void
    {
        header('Location: ' . BASE_URL . 'profil/edit', true, 301); exit;
    }

    // Legacy
    public function updateProfile(): void { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/profile'); }
    public function addVehicle(): void    { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/profile'); }
    public function editVehicle(): void   { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/profile'); }
    public function deleteVehicle(): void { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/profile'); }

    public function createRide(): void    { Security::ensure(['USER']); $this->render('dashboard/create_ride',['title'=>'Publier un trajet']); }
    public function history(): void       { Security::ensure(['USER']); $this->render('dashboard/history',['title'=>'Historique']); }
    public function startRide(): void     { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
    public function endRide(): void       { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
    public function cancelRide(): void    { Security::ensure(['USER']); header('Location: ' . BASE_URL . 'user/dashboard'); }
}
