<?php
declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\RideController;
use App\Controllers\AuthController;

// Dashboards
use App\Controllers\DashboardGatewayController; // passerelle /dashboard
use App\Controllers\UserDashboardController;    // USER
use App\Controllers\EmployeeController;         // EMPLOYEE
use App\Controllers\AdminController;            // ADMIN

use App\Controllers\StaticController;

/**
 * Routes EcoRide (MVC)
 * Toutes les vues passent par app/Views/layouts/base.php via BaseController::render()
 */

/* =======================
   Public / Visiteur
   ======================= */
$router->get('/', [HomeController::class, 'index']);

/* Rides (recherche + résultats + détail + réserver) */
$router->map(['GET','POST'], '/rides',     [RideController::class, 'list']);   // remplace /search
$router->get('/rides/show',                [RideController::class, 'show']);
$router->post('/rides/book',               [RideController::class, 'book']);

/* Pages statiques */
$router->get('/mentions-legales',          [StaticController::class, 'mentions']);

/* =======================
   Auth
   ======================= */
$router->get('/signup',                    [AuthController::class, 'signupForm']);
$router->post('/signup',                   [AuthController::class, 'signup']);
$router->get('/login',                     [AuthController::class, 'loginForm']);
$router->post('/login',                    [AuthController::class, 'login']);
$router->post('/logout',                   [AuthController::class, 'logout']);
$router->get('/logout',                    [AuthController::class, 'logout']); // toléré

/* =======================
   Dashboard – Passerelle
   ======================= */
$router->get('/dashboard',                 [DashboardGatewayController::class, 'route']);

/* =======================
   Espace UTILISATEUR (USER)
   ======================= */
$router->get('/user/dashboard',            [UserDashboardController::class, 'index']);

/* Profil + véhicules (USER) */
$router->get('/user/profile',              [UserDashboardController::class, 'profile']);
$router->post('/user/profile/update',      [UserDashboardController::class, 'updateProfile']);
$router->get('/user/vehicle',              [UserDashboardController::class, 'vehicleForm']);
$router->get('/user/vehicle/edit',         [UserDashboardController::class, 'vehicleForm']);


/* ===== Profil EDIT – URL canonique ===== */
$router->get('/profil/edit',               [UserDashboardController::class, 'editForm']);
$router->post('/profil/edit',              [UserDashboardController::class, 'update']);
$router->post('/profile/edit',             [UserDashboardController::class, 'update']);
$router->get('/profile/edit',              [UserDashboardController::class, 'redirectToProfilEdit']);

/* --------- Véhicules (ajout/édition/suppression) --------- */
/* GET form create/edit (NOUVEAU) */
$router->get('/user/vehicle',              [UserDashboardController::class, 'vehicleForm']);
$router->get('/user/vehicle/edit',         [UserDashboardController::class, 'vehicleForm']);
/* POST actions (conservées) */
$router->post('/user/vehicle/add',         [UserDashboardController::class, 'addVehicle']);
$router->post('/user/vehicle/edit',        [UserDashboardController::class, 'editVehicle']);
$router->post('/user/vehicle/delete',      [UserDashboardController::class, 'deleteVehicle']);
/* Alias rétro-compat GET (si anciens liens) */
$router->get('/vehicle',                   [UserDashboardController::class, 'vehicleForm']);
$router->get('/vehicle/edit',              [UserDashboardController::class, 'vehicleForm']);

/* Saisir un trajet (chauffeur) */
$router->get('/user/ride/create',          [UserDashboardController::class, 'createRide']);
$router->post('/user/ride/create',         [UserDashboardController::class, 'createRide']);

/* Historique + démarrer/arrêter (chauffeur) */
$router->get('/user/history',              [UserDashboardController::class, 'history']);
$router->post('/user/ride/start',          [UserDashboardController::class, 'startRide']);
$router->post('/user/ride/end',            [UserDashboardController::class, 'endRide']);

/* --- Alias rétro-compat (à retirer plus tard) --- */
$router->get('/profile',                   [UserDashboardController::class, 'profile']);
$router->post('/profile/update',           [UserDashboardController::class, 'updateProfile']);
$router->post('/vehicle/add',              [UserDashboardController::class, 'addVehicle']);
$router->post('/vehicle/edit',             [UserDashboardController::class, 'editVehicle']);
$router->post('/vehicle/delete',           [UserDashboardController::class, 'deleteVehicle']);
$router->get('/ride/create',               [UserDashboardController::class, 'createRide']);
$router->post('/ride/create',              [UserDashboardController::class, 'createRide']);
$router->get('/history',                   [UserDashboardController::class, 'history']);
$router->post('/ride/start',               [UserDashboardController::class, 'startRide']);
$router->post('/ride/end',                 [UserDashboardController::class, 'endRide']);

/* =======================
   Espace EMPLOYÉ (EMPLOYEE)
   ======================= */
$router->get('/employee/dashboard',        [EmployeeController::class, 'index']);
$router->post('/employee/reviews',         [EmployeeController::class, 'moderate']);

/* --- Alias rétro-compat --- */
$router->get('/employee',                  [EmployeeController::class, 'index']);

/* =======================
   Espace ADMIN (ADMIN)
   ======================= */
$router->get('/admin/dashboard',           [AdminController::class, 'index']);
$router->post('/admin/suspend',            [AdminController::class, 'suspendAccount']);
$router->post('/admin/employee/suspend',   [AdminController::class, 'suspendEmployee']);
$router->post('/admin/users/suspend',      [AdminController::class, 'suspendUser']);
$router->post('/admin/employees/create',   [AdminController::class, 'createEmployee']);

/* --- Alias rétro-compat --- */
$router->get('/admin',                     [AdminController::class, 'index']);
