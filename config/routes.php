<?php
/* ===== Imports contrôleurs =====
   J’importe ici tous mes contrôleurs que je vais utiliser dans le routing. */
use App\Controllers\HomeController;
use App\Controllers\RideController;
use App\Controllers\AuthController;
use App\Controllers\DashboardGatewayController;
use App\Controllers\GeneralController;
use App\Controllers\EmployeeController;
use App\Controllers\AdminController;
use App\Controllers\StaticController;
use App\Controllers\TrajetController;
use App\Controllers\CronController;
use App\Controllers\ReviewController;
use App\Controllers\PublicProfileController; 

/* =========================
   Public
   ========================= */
/* Page d’accueil */
$router->get('/', [HomeController::class, 'index']);

/* =========================
   Rides (recherche / liste / détail / réservation)
   ========================= */
$router->get('/rides',      [RideController::class, 'list']);
$router->post('/rides',     [RideController::class, 'list']);   // POST si je viens d’un formulaire
$router->post('/search',    [RideController::class, 'list']);   // alias pratique
$router->get('/rides/show', [RideController::class, 'show']);   // page détail trajet
$router->post('/rides/book',[RideController::class, 'book']);   // réservation trajet

/* Alias “trajet” si le contrôleur existe */
if (class_exists(\App\Controllers\TrajetController::class)) {
    $router->get('/trajet', [TrajetController::class, 'show']);
}

/* =========================
   Pages statiques
   ========================= */
/* Mentions légales */
$router->get('/mentions-legales', [StaticController::class, 'mentions']);
/* Alias pratiques (j’évite les 404 si l’URL varie) */
$router->get('/legal',            [StaticController::class, 'mentions']);
$router->get('/mentions',         [StaticController::class, 'mentions']);
$router->get('/mentions_legales', [StaticController::class, 'mentions']);

/* Politique de confidentialité */
$router->get('/confidentialite',  [StaticController::class, 'confidentialite']);
$router->get('/confidentiality',  [StaticController::class, 'confidentialite']); // alias EN (au cas où)

/* Conditions Générales d’Utilisation (CGU) */
$router->get('/cgu',                  [StaticController::class, 'cgu']);
$router->get('/conditions-generales', [StaticController::class, 'cgu']); // alias FR long
$router->get('/conditions',           [StaticController::class, 'cgu']); // alias court

/* Contact (affiché uniquement dans le footer côté UI) */
$router->get('/contact',          [StaticController::class, 'contact']);
/* Envoi du formulaire de contact (POST) */
$router->post('/send-contact', [GeneralController::class, 'sendContact']);


/* =========================
   Auth
   ========================= */
$router->get('/signup',   [AuthController::class, 'signupForm']);
$router->post('/signup',  [AuthController::class, 'signup']);
$router->get('/login',    [AuthController::class, 'loginForm']);
$router->post('/login',   [AuthController::class, 'login']);
$router->get('/logout',   [AuthController::class, 'logout']);
$router->post('/logout',  [AuthController::class, 'logout']);
$router->get('/verify-email', [AuthController::class, 'verifyEmail']);

/* =========================
   Dashboard – passerelle + covoiturage public
   ========================= */
$router->get('/dashboard',   [DashboardGatewayController::class, 'route']);
$router->get('/covoiturage', [RideController::class, 'covoiturage']);

/* =========================
   Espace user
   ========================= */
/* Tableau de bord user */
$router->get('/user/dashboard', [GeneralController::class, 'index']);

/* Profil utilisateur (edit + update) */
$router->get('/profil/edit',   [GeneralController::class, 'editForm']);
$router->post('/profil/edit',  [GeneralController::class, 'update']);
$router->get('/profile/edit',  [GeneralController::class, 'redirectToProfilEdit']);
$router->post('/profile/edit', [GeneralController::class, 'update']);

/* Profil utilisateur (lecture) */
$router->get('/user/profile',        [GeneralController::class, 'profile']);
$router->post('/user/profile/update',[GeneralController::class, 'updateProfile']);
$router->get('/profile',             [GeneralController::class, 'profile']);
$router->post('/profile/update',     [GeneralController::class, 'updateProfile']);

/* Véhicules */
$router->get('/user/vehicle',        [GeneralController::class, 'vehicleForm']);
$router->get('/user/vehicle/edit',   [GeneralController::class, 'vehicleForm']);
$router->post('/user/vehicle/add',   [GeneralController::class, 'addVehicle']);
$router->post('/user/vehicle/edit',  [GeneralController::class, 'editVehicle']);
$router->post('/user/vehicle/delete',[GeneralController::class, 'deleteVehicle']);

/* Alias courts */
$router->get('/vehicle',        [GeneralController::class, 'vehicleForm']);
$router->get('/vehicle/edit',   [GeneralController::class, 'vehicleForm']);
$router->post('/vehicle/add',   [GeneralController::class, 'addVehicle']);
$router->post('/vehicle/edit',  [GeneralController::class, 'editVehicle']);
$router->post('/vehicle/delete',[GeneralController::class, 'deleteVehicle']);

/* Trajets côté user */
$router->get('/user/ride/create', [GeneralController::class, 'createRide']);
$router->post('/user/ride/create',[GeneralController::class, 'createRide']);
$router->get('/user/ride/cancel', [GeneralController::class, 'cancelRide']);
$router->get('/ride/create',      [GeneralController::class, 'createRide']);
$router->post('/ride/create',     [GeneralController::class, 'createRide']);
$router->get('/ride/cancel',      [GeneralController::class, 'cancelRide']);

/* Démarrage / fin trajet */
$router->get('/user/ride/start',  [GeneralController::class, 'startRide']);
$router->post('/user/ride/start', [GeneralController::class, 'startRide']);
$router->get('/ride/start',       [GeneralController::class, 'startRide']);
$router->post('/ride/start',      [GeneralController::class, 'startRide']);
$router->get('/user/ride/end',    [GeneralController::class, 'endRide']);
$router->post('/user/ride/end',   [GeneralController::class, 'endRide']);

/* Page “Ma note” (espace utilisateur) */
$router->get('/user/ratings', [GeneralController::class, 'ratings']);
$router->get('/ratings',      [GeneralController::class, 'ratings']);

/* Profil public conducteur (clic sur avatar/nom depuis covoiturage) */
$router->get('/users/profile',[PublicProfileController::class, 'show']);

/* =========================
   Espace employé
   ========================= */
$router->get('/employee/dashboard', [EmployeeController::class, 'index']);
$router->get('/employee/reviews',   [EmployeeController::class, 'reviews']);
$router->post('/employee/reviews',  [EmployeeController::class, 'moderate']);
$router->get('/employee',           [EmployeeController::class, 'index']);

/* =========================
   Espace admin
   ========================= */
$router->get('/admin/dashboard',           [AdminController::class, 'index']);
$router->post('/admin/suspend',            [AdminController::class, 'suspendAccount']);
$router->post('/admin/employee/suspend',   [AdminController::class, 'suspendEmployee']);
$router->post('/admin/users/suspend',      [AdminController::class, 'suspendUser']);
$router->post('/admin/employees/create',   [AdminController::class, 'createEmployee']);
$router->get('/admin',                     [AdminController::class, 'index']);
$router->get('/admin/api/credits-history', [AdminController::class, 'apiCreditsHistory']);

/* =========================
   Avis (stockés côté NoSQL Mongo)
   ========================= */
$router->get('/reviews/new',    [ReviewController::class, 'new']);     // formulaire laisser un avis
$router->post('/reviews',       [ReviewController::class, 'create']);  // traitement création avis

/* Page publique : avis d’un conducteur (par id) */
$router->get('/drivers/ratings',[ReviewController::class, 'driverRatings']);
$router->get('/driver/ratings', [ReviewController::class, 'driverRatings']); // alias

/* =========================
   Cron
   ========================= */
$router->get('/cron/run',  [CronController::class, 'run']);
$router->post('/cron/run', [CronController::class, 'run']);
