<?php
/* ===== Imports contrôleurs ===== */
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

/* Public */
$router->get('/',                          [HomeController::class, 'index']);

/* Rides */
$router->get('/rides',                     [RideController::class, 'list']);
$router->post('/rides',                    [RideController::class, 'list']);
$router->post('/search',                   [RideController::class, 'list']);
$router->get('/rides/show',                [RideController::class, 'show']);
$router->post('/rides/book',               [RideController::class, 'book']);

/* Alias trajet (si présent) */
if (class_exists(\App\Controllers\TrajetController::class)) {
    $router->get('/trajet',                [TrajetController::class, 'show']);
}

/* Statiques */
$router->get('/mentions-legales',          [StaticController::class, 'mentions']);

/* Auth */
$router->get('/signup',                    [AuthController::class, 'signupForm']);
$router->post('/signup',                   [AuthController::class, 'signup']);
$router->get('/login',                     [AuthController::class, 'loginForm']);
$router->post('/login',                    [AuthController::class, 'login']);
$router->get('/logout',                    [AuthController::class, 'logout']);
$router->post('/logout',                   [AuthController::class, 'logout']);
$router->get('/verify-email',              [AuthController::class, 'verifyEmail']);

/* Dashboard – passerelle + page covoiturage publique */
$router->get('/dashboard',                 [DashboardGatewayController::class, 'route']);
$router->get('/covoiturage',               [RideController::class, 'covoiturage']);

/* Espace user */
$router->get('/user/dashboard',            [GeneralController::class, 'index']);
$router->get('/profil/edit',               [GeneralController::class, 'editForm']);
$router->post('/profil/edit',              [GeneralController::class, 'update']);
$router->get('/profile/edit',              [GeneralController::class, 'redirectToProfilEdit']);
$router->post('/profile/edit',             [GeneralController::class, 'update']);
$router->get('/user/profile',              [GeneralController::class, 'profile']);
$router->post('/user/profile/update',      [GeneralController::class, 'updateProfile']);
$router->get('/profile',                   [GeneralController::class, 'profile']);
$router->post('/profile/update',           [GeneralController::class, 'updateProfile']);

$router->get('/user/vehicle',              [GeneralController::class, 'vehicleForm']);
$router->get('/user/vehicle/edit',         [GeneralController::class, 'vehicleForm']);
$router->post('/user/vehicle/add',         [GeneralController::class, 'addVehicle']);
$router->post('/user/vehicle/edit',        [GeneralController::class, 'editVehicle']);
$router->post('/user/vehicle/delete',      [GeneralController::class, 'deleteVehicle']);
$router->get('/vehicle',                   [GeneralController::class, 'vehicleForm']);
$router->get('/vehicle/edit',              [GeneralController::class, 'vehicleForm']);
$router->post('/vehicle/add',              [GeneralController::class, 'addVehicle']);
$router->post('/vehicle/edit',             [GeneralController::class, 'editVehicle']);
$router->post('/vehicle/delete',           [GeneralController::class, 'deleteVehicle']);

$router->get('/user/ride/create',          [GeneralController::class, 'createRide']);
$router->post('/user/ride/create',         [GeneralController::class, 'createRide']);
$router->get('/user/ride/cancel',          [GeneralController::class, 'cancelRide']);
$router->get('/ride/create',               [GeneralController::class, 'createRide']);
$router->post('/ride/create',              [GeneralController::class, 'createRide']);
$router->get('/ride/cancel',               [GeneralController::class, 'cancelRide']);

/* Démarrer / terminer trajet */
$router->get('/user/ride/start',           [GeneralController::class, 'startRide']);
$router->post('/user/ride/start',          [GeneralController::class, 'startRide']);
$router->get('/ride/start',                [GeneralController::class, 'startRide']);
$router->post('/ride/start',               [GeneralController::class, 'startRide']);

$router->get('/user/ride/end',             [GeneralController::class, 'endRide']);
$router->post('/user/ride/end',            [GeneralController::class, 'endRide']);

/* ✅ Page “Ma note” (accessible depuis la carte du dashboard) */
$router->get('/user/ratings',              [GeneralController::class, 'ratings']);
$router->get('/ratings',                   [GeneralController::class, 'ratings']);

/* Employé */
$router->get('/employee/dashboard',        [EmployeeController::class, 'index']);
$router->get('/employee/reviews',          [EmployeeController::class, 'reviews']);
$router->post('/employee/reviews',         [EmployeeController::class, 'moderate']);
$router->get('/employee',                  [EmployeeController::class, 'index']);

/* Admin */
$router->get('/admin/dashboard',           [AdminController::class, 'index']);
$router->post('/admin/suspend',            [AdminController::class, 'suspendAccount']);
$router->post('/admin/employee/suspend',   [AdminController::class, 'suspendEmployee']);
$router->post('/admin/users/suspend',      [AdminController::class, 'suspendUser']);
$router->post('/admin/employees/create',   [AdminController::class, 'createEmployee']);
$router->get('/admin',                     [AdminController::class, 'index']);
$router->get('/admin/api/credits-history', [AdminController::class, 'apiCreditsHistory']);

/* Avis (NoSQL) */
$router->get('/reviews/new',               [ReviewController::class, 'new']);
$router->post('/reviews',                  [ReviewController::class, 'create']);

/* Cron */
$router->get('/cron/run',                  [CronController::class, 'run']);
$router->post('/cron/run',                 [CronController::class, 'run']);
