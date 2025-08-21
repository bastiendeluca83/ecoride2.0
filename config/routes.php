<?php
use App\Controllers\TrajetController;
use App\Controllers\RideController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\AdminController;

return [
  // Accueil + Rides
  ['GET','/',            [RideController::class,'home']],
  ['GET','/rides',       [RideController::class,'list']],
  ['POST','/search',     [RideController::class,'search']],
  ['GET','/rides/show',  [RideController::class,'show']],
  ['GET','/trajet',      [TrajetController::class,'show']], // alias

  // Auth
  ['GET','/signup',      [AuthController::class,'signupForm']],
  ['POST','/signup',     [AuthController::class,'signup']],
  ['GET','/login',       [AuthController::class,'loginForm']],
  ['POST','/login',      [AuthController::class,'login']],
  ['POST','/logout',     [AuthController::class,'logout']], // POST (nav)
  ['GET','/logout',      [AuthController::class,'logout']], // compat GET

  // Espace utilisateur
  ['GET','/dashboard',   [DashboardController::class,'index']],

  // Réservations / trajets
  ['POST','/rides/book', [RideController::class,'book']],

  // Employé (Mongo)
  ['GET','/employee',          [EmployeeController::class,'index']],
  ['POST','/employee/reviews', [EmployeeController::class,'moderate']],

  // Admin
  ['GET','/admin',             [AdminController::class,'index']],
  ['POST','/admin/suspend',    [AdminController::class,'suspendAccount']],
];
