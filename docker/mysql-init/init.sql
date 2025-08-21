-- docker/mysql-init/init.sql - schéma minimal SQL (rapide)
CREATE DATABASE IF NOT EXISTS ecoride CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecoride;

-- Users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pseudo VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('USER','EMPLOYEE','ADMIN') NOT NULL DEFAULT 'USER',
  credits INT NOT NULL DEFAULT 20,
  is_suspended TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Vehicles
CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  brand VARCHAR(60) NOT NULL,
  model VARCHAR(60) NOT NULL,
  color VARCHAR(40),
  energy ENUM('ESSENCE','DIESEL','ELECTRIC','HYBRID') NOT NULL,
  plate VARCHAR(20) NOT NULL,
  first_reg_date DATE,
  seats INT NOT NULL DEFAULT 4,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY (plate),
  INDEX (user_id)
) ENGINE=InnoDB;

-- Rides
CREATE TABLE IF NOT EXISTS rides (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_id INT NOT NULL,
  vehicle_id INT NOT NULL,
  from_city VARCHAR(80) NOT NULL,
  to_city VARCHAR(80) NOT NULL,
  date_start DATETIME NOT NULL,
  date_end DATETIME NULL,
  price INT NOT NULL,
  seats_left INT NOT NULL,
  is_electric_cached TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
  INDEX idx_search (from_city, to_city, date_start)
) ENGINE=InnoDB;

-- Bookings
CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ride_id INT NOT NULL,
  passenger_id INT NOT NULL,
  status ENUM('CONFIRMED','CANCELLED') NOT NULL DEFAULT 'CONFIRMED',
  credits_spent INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
  FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (ride_id), INDEX (passenger_id)
) ENGINE=InnoDB;

-- Plateforme (crédits prélevés)
CREATE TABLE IF NOT EXISTS penalties_platform (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ride_id INT NOT NULL,
  credits_taken INT NOT NULL DEFAULT 2,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seeds (démo)
INSERT IGNORE INTO users(pseudo, email, password_hash, role, credits) VALUES
('driver1','driver1@example.com', '$2y$10$4mG9GQW7b0u0G4n9QH7WzOjzYzQZpGJ5vXoY3vJkVYJg5l8kHqW8G', 'USER', 50), -- mdp: test1234
('user1','user1@example.com', '$2y$10$4mG9GQW7b0u0G4n9QH7WzOjzYzQZpGJ5vXoY3vJkVYJg5l8kHqW8G', 'USER', 20),
('admin','admin@example.com', '$2y$10$4mG9GQW7b0u0G4n9QH7WzOjzYzQZpGJ5vXoY3vJkVYJg5l8kHqW8G', 'ADMIN', 999);

INSERT IGNORE INTO vehicles(user_id, brand, model, color, energy, plate, first_reg_date, seats) VALUES
(1, 'Tesla', 'Model 3', 'black', 'ELECTRIC', 'AA-123-AA', '2021-06-01', 4);

INSERT IGNORE INTO rides(driver_id, vehicle_id, from_city, to_city, date_start, date_end, price, seats_left, is_electric_cached) VALUES
(1, 1, 'Paris', 'Lyon', '2025-08-15 08:00:00', '2025-08-15 12:00:00', 10, 3, 1),
(1, 1, 'Paris', 'Lille', '2025-08-16 09:00:00', '2025-08-16 11:30:00', 8, 2, 1);
