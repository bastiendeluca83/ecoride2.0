<?php
/* ===== Imports contrôleurs ===== */
use App\Controllers\HomeController;
use App\Controllers\RideController;
use App\Controllers\AuthController;
use App\Controllers\DashboardGatewayController;
use App\Controllers\UserDashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\AdminController;
use App\Controllers\StaticController;
use App\Controllers\TrajetController;
use App\Controllers\CronController;

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

/* Dashboard – passerelle + page covoiturage publique */
$router->get('/dashboard',                 [DashboardGatewayController::class, 'route']);
$router->get('/covoiturage',               [\App\Controllers\RideController::class, 'covoiturage']);

/* Espace user */
$router->get('/user/dashboard',            [UserDashboardController::class, 'index']);
$router->get('/profil/edit',               [UserDashboardController::class, 'editForm']);
$router->post('/profil/edit',              [UserDashboardController::class, 'update']);
$router->get('/profile/edit',              [UserDashboardController::class, 'redirectToProfilEdit']);
$router->post('/profile/edit',             [UserDashboardController::class, 'update']);
$router->get('/user/profile',              [UserDashboardController::class, 'profile']);
$router->post('/user/profile/update',      [UserDashboardController::class, 'updateProfile']);
$router->get('/profile',                   [UserDashboardController::class, 'profile']);
$router->post('/profile/update',           [UserDashboardController::class, 'updateProfile']);

$router->get('/user/vehicle',              [UserDashboardController::class, 'vehicleForm']);
$router->get('/user/vehicle/edit',         [UserDashboardController::class, 'vehicleForm']);
$router->post('/user/vehicle/add',         [UserDashboardController::class, 'addVehicle']);
$router->post('/user/vehicle/edit',        [UserDashboardController::class, 'editVehicle']);
$router->post('/user/vehicle/delete',      [UserDashboardController::class, 'deleteVehicle']);
$router->get('/vehicle',                   [UserDashboardController::class, 'vehicleForm']);
$router->get('/vehicle/edit',              [UserDashboardController::class, 'vehicleForm']);
$router->post('/vehicle/add',              [UserDashboardController::class, 'addVehicle']);
$router->post('/vehicle/edit',             [UserDashboardController::class, 'editVehicle']);
$router->post('/vehicle/delete',           [UserDashboardController::class, 'deleteVehicle']);

$router->get('/user/ride/create',          [UserDashboardController::class, 'createRide']);
$router->post('/user/ride/create',         [UserDashboardController::class, 'createRide']);
$router->get('/user/ride/cancel',          [UserDashboardController::class, 'cancelRide']);
$router->get('/ride/create',               [UserDashboardController::class, 'createRide']);
$router->post('/ride/create',              [UserDashboardController::class, 'createRide']);
$router->get('/ride/cancel',               [UserDashboardController::class, 'cancelRide']);

$router->get('/user/history',              [UserDashboardController::class, 'history']);
$router->get('/user/ride/start',           [UserDashboardController::class, 'startRide']);
$router->post('/user/ride/start',          [UserDashboardController::class, 'startRide']);
$router->get('/user/ride/end',             [UserDashboardController::class, 'endRide']);
$router->post('/user/ride/end',            [UserDashboardController::class, 'endRide']);
$router->get('/history',                   [UserDashboardController::class, 'history']);
$router->get('/ride/start',                [UserDashboardController::class, 'startRide']);
$router->post('/ride/start',               [UserDashboardController::class, 'startRide']);
$router->get('/ride/end',                  [UserDashboardController::class, 'endRide']);
$router->post('/ride/end',                 [UserDashboardController::class, 'endRide']);

/* Employé */
$router->get('/employee/dashboard',        [EmployeeController::class, 'index']);
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

/* Cron (tâches planifiées) */
$router->get('/cron/run',                  [CronController::class, 'run']);
$router->post('/cron/run',                 [CronController::class, 'run']);
