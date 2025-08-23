<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Security;

final class UserDashboardController extends BaseController
{
    public function index(): void
    {
        Security::ensure(['USER']);

        // TODO: remplace par tes vraies requêtes
        $user = $_SESSION['user'] ?? ['nom'=>'Utilisateur','credits'=>0,'total_rides'=>0];
        $reservations = []; // SELECT ...
        $rides = [];        // SELECT ...

        $this->render('dashboard/user', [
            'title'        => 'Espace utilisateur',
            'user'         => $user,
            'reservations' => $reservations,
            'rides'        => $rides,
        ]);
    }

    /* ===== Profil ===== */
    public function profile(): void            { Security::ensure(['USER']); $this->render('dashboard/profile',['title'=>'Mon profil']); }
    public function updateProfile(): void      { Security::ensure(['USER']); /* Traitement + redirect */ header('Location: /user/profile'); }
    public function addVehicle(): void         { Security::ensure(['USER']); /* Traitement */ header('Location: /user/profile'); }
    public function editVehicle(): void        { Security::ensure(['USER']); /* Traitement */ header('Location: /user/profile'); }
    public function deleteVehicle(): void      { Security::ensure(['USER']); /* Traitement */ header('Location: /user/profile'); }

    /* ===== Trajets ===== */
    public function createRide(): void         { Security::ensure(['USER']); /* Form + save */ $this->render('dashboard/create_ride',['title'=>'Publier un trajet']); }
    public function history(): void            { Security::ensure(['USER']); $this->render('dashboard/history',['title'=>'Historique']); }
    public function startRide(): void          { Security::ensure(['USER']); /* start */ header('Location: /user/dashboard'); }
    public function endRide(): void            { Security::ensure(['USER']); /* end */ header('Location: /user/dashboard'); }
    public function cancelRide(): void         { Security::ensure(['USER']); /* cancel */ header('Location: /user/dashboard'); }
}
