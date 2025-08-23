<?php
require __DIR__ . '/../vendor/autoload.php';


use App\Models\User;
use App\Models\Vehicle;
use App\Models\Ride;
use App\Models\Booking;


$uniq = time();
$driverId = User::firstOrCreateByEmail('driverTest', "driver+$uniq@test.local", 'Passw0rd!');
$passengerId = User::firstOrCreateByEmail('passTest', "pass+$uniq@test.local", 'Passw0rd!');
$vehId = Vehicle::create($driverId, 'AA-123-BB', 'Renault', 'Zoé', 'Vert', 'electrique', '2020-01-01', 3);
$rideId = Ride::create($driverId, $vehId, 'Paris', 'Lyon', date('Y-m-d 09:00:00', strtotime('+1 day')), date('Y-m-d 12:00:00', strtotime('+1 day')), 10, 3, 'PREVU');
$found = Ride::search('Paris', 'Lyon', date('Y-m-d', strtotime('+1 day')), true);
echo "Search found: ".count($found)." ride(s)\n";
$bookingId = Booking::create($rideId, $passengerId, 10, 'RESERVE');
Booking::confirm($bookingId);
$ride = Ride::findById($rideId);
echo "Ride seats_left: {$ride['seats_left']}\n";
