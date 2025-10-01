-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : db:3306
-- Généré le : mer. 01 oct. 2025 à 16:43
-- Version du serveur : 8.0.43
-- Version de PHP : 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `ecoride`
--

-- --------------------------------------------------------

--
-- Structure de la table `bookings`
--
-- Création : jeu. 21 août 2025 à 15:00
--

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `ride_id` int NOT NULL,
  `passenger_id` int NOT NULL,
  `status` enum('CONFIRMED','CANCELLED') NOT NULL DEFAULT 'CONFIRMED',
  `credits_spent` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `bookings`
--

INSERT INTO `bookings` (`id`, `ride_id`, `passenger_id`, `status`, `credits_spent`, `created_at`) VALUES
(1, 1, 1, 'CONFIRMED', 10, '2025-08-21 17:16:26'),
(2, 1, 1, 'CONFIRMED', 10, '2025-08-21 17:20:46'),
(3, 1, 1, 'CONFIRMED', 10, '2025-08-22 08:35:25'),
(4, 3, 4, 'CONFIRMED', 10, '2025-08-27 16:30:30'),
(5, 4, 9, 'CONFIRMED', 10, '2025-08-27 16:30:30'),
(6, 5, 4, 'CONFIRMED', 10, '2025-08-27 16:30:30'),
(16, 7, 9, 'CONFIRMED', 10, '2025-08-29 10:55:18'),
(17, 7, 4, 'CONFIRMED', 10, '2025-08-29 10:59:17'),
(18, 8, 9, 'CONFIRMED', 10, '2025-09-02 10:12:43'),
(19, 8, 4, 'CONFIRMED', 10, '2025-09-02 10:16:16'),
(22, 19, 8, 'CONFIRMED', 8, '2025-09-26 07:37:15'),
(23, 21, 8, 'CONFIRMED', 8, '2025-09-26 08:33:00'),
(24, 23, 8, 'CONFIRMED', 10, '2025-09-26 09:07:34'),
(25, 24, 8, 'CONFIRMED', 8, '2025-09-26 10:43:42'),
(26, 25, 8, 'CONFIRMED', 10, '2025-09-26 13:16:07'),
(27, 26, 14, 'CONFIRMED', 0, '2025-09-26 14:19:59'),
(28, 31, 14, 'CONFIRMED', 10, '2025-09-30 12:36:12');

-- --------------------------------------------------------

--
-- Structure de la table `cron_state`
--
-- Création : mer. 27 août 2025 à 16:15
--

DROP TABLE IF EXISTS `cron_state`;
CREATE TABLE `cron_state` (
  `k` varchar(64) NOT NULL,
  `v` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `penalties_platform`
--
-- Création : jeu. 21 août 2025 à 15:00
--

DROP TABLE IF EXISTS `penalties_platform`;
CREATE TABLE `penalties_platform` (
  `id` int NOT NULL,
  `ride_id` int NOT NULL,
  `credits_taken` int NOT NULL DEFAULT '2',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reviews`
--
-- Création : ven. 26 sep. 2025 à 08:46
--

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `ride_id` int NOT NULL,
  `driver_id` int NOT NULL,
  `passenger_id` int NOT NULL,
  `rating` tinyint NOT NULL,
  `comment` text,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rides`
--
-- Création : ven. 26 sep. 2025 à 07:47
--

DROP TABLE IF EXISTS `rides`;
CREATE TABLE `rides` (
  `id` int NOT NULL,
  `driver_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `from_city` varchar(80) NOT NULL,
  `to_city` varchar(80) NOT NULL,
  `date_start` datetime NOT NULL,
  `date_end` datetime DEFAULT NULL,
  `price` int NOT NULL,
  `seats_left` int NOT NULL,
  `is_electric_cached` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('PREVU','STARTED','FINISHED','CANCELLED') NOT NULL DEFAULT 'PREVU',
  `started_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `rides`
--

INSERT INTO `rides` (`id`, `driver_id`, `vehicle_id`, `from_city`, `to_city`, `date_start`, `date_end`, `price`, `seats_left`, `is_electric_cached`, `status`, `started_at`, `created_at`) VALUES
(1, 1, 1, 'Paris', 'Lyon', '2025-08-15 08:00:00', '2025-08-15 12:00:00', 10, 0, 1, 'PREVU', NULL, '2025-08-21 15:00:32'),
(2, 1, 1, 'Paris', 'Lille', '2025-08-16 09:00:00', '2025-08-16 11:30:00', 8, 2, 1, 'PREVU', NULL, '2025-08-21 15:00:32'),
(3, 1, 1, 'Paris', 'Lyon', '2025-08-30 09:00:00', '2025-08-30 12:00:00', 10, 3, 1, 'PREVU', NULL, '2025-08-27 16:30:30'),
(4, 8, 7, 'Paris', 'Lille', '2025-08-30 12:00:00', '2025-08-30 15:00:00', 10, 3, 1, 'PREVU', NULL, '2025-08-27 16:30:30'),
(5, 8, 7, 'Paris', 'Reims', '2025-08-30 15:00:00', '2025-08-30 18:00:00', 10, 3, 1, 'PREVU', NULL, '2025-08-27 16:30:30'),
(6, 1, 1, 'nans les pins', 'marseille', '2025-08-31 11:02:00', '2025-08-31 12:02:00', 10, 3, 1, 'PREVU', NULL, '2025-08-29 09:02:48'),
(7, 8, 7, 'aix', 'marseille', '2025-09-05 11:30:00', '2025-09-05 12:30:00', 10, 1, 1, 'PREVU', NULL, '2025-08-29 09:30:48'),
(8, 8, 7, 'orange', 'avignon', '2025-09-04 12:10:00', '2025-09-06 15:11:00', 10, 1, 1, 'PREVU', NULL, '2025-09-02 10:11:28'),
(12, 14, 8, 'marseille', 'marseille', '2025-09-25 14:00:00', '2025-09-25 15:00:00', 0, 4, 1, 'PREVU', NULL, '2025-09-25 12:00:19'),
(13, 14, 8, 'nans les pins', 'marseille', '2025-09-26 14:02:00', '2025-09-29 14:02:00', 5, 3, 1, 'FINISHED', NULL, '2025-09-25 12:02:55'),
(14, 14, 8, 'londre', 'marseille', '2025-09-25 14:21:00', '2025-09-25 20:21:00', 10, 3, 1, 'PREVU', NULL, '2025-09-25 12:21:26'),
(16, 14, 8, 'dernier test', 'pitier', '2025-09-25 14:46:00', '2025-09-26 14:46:00', 10, 3, 1, 'PREVU', NULL, '2025-09-25 12:47:00'),
(17, 14, 8, 'test', '...', '2025-09-25 15:26:00', '2025-09-25 20:26:00', 10, 2, 1, 'PREVU', NULL, '2025-09-25 13:26:20'),
(18, 14, 8, 'orange', 'avignon', '2025-09-25 17:17:00', '2025-09-25 17:19:00', 4, 4, 1, 'PREVU', NULL, '2025-09-25 15:18:15'),
(19, 14, 8, 'aix', 'paris', '2025-09-26 09:34:00', '2025-09-26 13:34:00', 8, 2, 1, 'FINISHED', NULL, '2025-09-26 07:34:51'),
(20, 14, 8, 'orange', 'avignon', '2025-09-26 09:56:00', '2025-09-26 13:56:00', 10, 4, 1, 'FINISHED', NULL, '2025-09-26 07:57:05'),
(21, 14, 8, 'marseille', 'paris', '2025-09-26 10:32:00', '2025-09-26 14:32:00', 8, 3, 1, 'FINISHED', NULL, '2025-09-26 08:32:28'),
(22, 14, 8, 'londre', 'avignon', '2025-09-26 10:54:00', '2025-09-27 10:54:00', 10, 4, 1, 'FINISHED', NULL, '2025-09-26 08:55:07'),
(23, 14, 8, 'londre', 'avignon', '2025-09-26 11:06:00', '2025-09-27 11:06:00', 10, 3, 1, 'FINISHED', NULL, '2025-09-26 09:06:43'),
(24, 14, 8, 'aix', 'marseille', '2025-09-26 12:43:00', '2025-09-26 14:43:00', 8, 2, 1, 'FINISHED', NULL, '2025-09-26 10:43:11'),
(25, 14, 8, 'nans les pins', 'pitier', '2025-09-26 15:14:00', '2025-09-26 18:14:00', 10, 2, 1, 'FINISHED', NULL, '2025-09-26 13:14:40'),
(26, 8, 7, 'orange', 'avignon', '2025-09-26 16:19:00', '2025-09-26 17:19:00', 0, 2, 1, 'FINISHED', NULL, '2025-09-26 14:19:13'),
(27, 8, 7, 'nans les pins', 'avignon', '2025-09-29 14:49:00', '2025-09-29 18:49:00', 10, 4, 1, 'PREVU', NULL, '2025-09-29 12:49:22'),
(28, 14, 8, 'orange', 'paris', '2025-09-30 11:05:00', '2025-09-30 13:05:00', 8, 3, 1, 'PREVU', NULL, '2025-09-30 09:06:06'),
(30, 14, 8, 'marseille', 'paris', '2025-09-30 12:08:00', '2025-09-30 14:08:00', 10, 3, 1, 'PREVU', NULL, '2025-09-30 10:08:51'),
(31, 8, 7, 'orange', 'avignon', '2025-09-30 14:35:00', '2025-09-30 16:35:00', 10, 3, 1, 'FINISHED', NULL, '2025-09-30 12:35:17');

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--
-- Création : jeu. 21 août 2025 à 17:25
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `ride_id` int DEFAULT NULL,
  `type` enum('gain','depense','remboursement') NOT NULL,
  `montant` int NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `booking_id`, `ride_id`, `type`, `montant`, `description`, `created_at`) VALUES
(1, 9, 16, 7, 'gain', -10, 'Réservation covoiturage #7', '2025-08-29 10:55:18'),
(2, 8, 16, 7, 'gain', 8, 'Gain conducteur ride #7', '2025-08-29 10:55:18'),
(3, 3, 16, 7, 'gain', 2, 'Commission plate-forme ride #7', '2025-08-29 10:55:18'),
(4, 4, 17, 7, 'gain', -10, 'Réservation covoiturage #7', '2025-08-29 10:59:17'),
(5, 8, 17, 7, 'gain', 8, 'Gain conducteur ride #7', '2025-08-29 10:59:17'),
(6, 3, 17, 7, 'gain', 2, 'Commission plate-forme ride #7', '2025-08-29 10:59:17'),
(7, 9, 18, 8, 'gain', -10, 'Réservation covoiturage #8', '2025-09-02 10:12:43'),
(8, 8, 18, 8, 'gain', 8, 'Gain conducteur ride #8', '2025-09-02 10:12:43'),
(9, 3, 18, 8, 'gain', 2, 'Commission plate-forme ride #8', '2025-09-02 10:12:43'),
(10, 4, 19, 8, 'gain', -10, 'Réservation covoiturage #8', '2025-09-02 10:16:16'),
(11, 8, 19, 8, 'gain', 8, 'Gain conducteur ride #8', '2025-09-02 10:16:16'),
(12, 3, 19, 8, 'gain', 2, 'Commission plate-forme ride #8', '2025-09-02 10:16:16'),
(14, 14, 20, 9, 'gain', 0, 'Gain conducteur ride #9', '2025-09-25 10:18:56'),
(17, 14, 21, 17, 'gain', 8, 'Gain conducteur ride #17', '2025-09-25 13:27:42'),
(18, 3, 21, 17, 'gain', 2, 'Commission plate-forme ride #17', '2025-09-25 13:27:42'),
(19, 8, 22, 19, 'gain', -8, 'Réservation covoiturage #19', '2025-09-26 07:37:15'),
(20, 14, 22, 19, 'gain', 6, 'Gain conducteur ride #19', '2025-09-26 07:37:15'),
(21, 3, 22, 19, 'gain', 2, 'Commission plate-forme ride #19', '2025-09-26 07:37:15'),
(22, 8, 23, 21, 'gain', -8, 'Réservation covoiturage #21', '2025-09-26 08:33:00'),
(23, 14, 23, 21, 'gain', 6, 'Gain conducteur ride #21', '2025-09-26 08:33:00'),
(24, 3, 23, 21, 'gain', 2, 'Commission plate-forme ride #21', '2025-09-26 08:33:00'),
(25, 8, 24, 23, 'gain', -10, 'Réservation covoiturage #23', '2025-09-26 09:07:34'),
(26, 14, 24, 23, 'gain', 8, 'Gain conducteur ride #23', '2025-09-26 09:07:34'),
(27, 3, 24, 23, 'gain', 2, 'Commission plate-forme ride #23', '2025-09-26 09:07:34'),
(28, 8, 25, 24, 'gain', -8, 'Réservation covoiturage #24', '2025-09-26 10:43:42'),
(29, 14, 25, 24, 'gain', 6, 'Gain conducteur ride #24', '2025-09-26 10:43:42'),
(30, 3, 25, 24, 'gain', 2, 'Commission plate-forme ride #24', '2025-09-26 10:43:42'),
(31, 8, 26, 25, 'gain', -10, 'Réservation covoiturage #25', '2025-09-26 13:16:07'),
(32, 14, 26, 25, 'gain', 8, 'Gain conducteur ride #25', '2025-09-26 13:16:07'),
(33, 3, 26, 25, 'gain', 2, 'Commission plate-forme ride #25', '2025-09-26 13:16:07'),
(34, 14, 27, 26, 'gain', 0, 'Réservation covoiturage #26', '2025-09-26 14:19:59'),
(35, 8, 27, 26, 'gain', 0, 'Gain conducteur ride #26', '2025-09-26 14:19:59'),
(36, 3, 27, 26, 'gain', 2, 'Commission plate-forme ride #26', '2025-09-26 14:19:59'),
(37, 14, 28, 31, 'gain', -10, 'Réservation covoiturage #31', '2025-09-30 12:36:12'),
(38, 8, 28, 31, 'gain', 8, 'Gain conducteur ride #31', '2025-09-30 12:36:12'),
(39, 3, 28, 31, 'gain', 2, 'Commission plate-forme ride #31', '2025-09-30 12:36:12');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--
-- Création : jeu. 25 sep. 2025 à 09:37
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL,
  `nom` varchar(60) DEFAULT NULL,
  `prenom` varchar(60) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(120) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('USER','EMPLOYEE','ADMIN') NOT NULL DEFAULT 'USER',
  `credits` int NOT NULL DEFAULT '20',
  `is_suspended` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_credit_topup` datetime DEFAULT NULL,
  `bio` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `adresse`, `telephone`, `email`, `email_verified_at`, `date_of_birth`, `avatar_path`, `password_hash`, `role`, `credits`, `is_suspended`, `created_at`, `updated_at`, `last_credit_topup`, `bio`) VALUES
(1, 'driver1', 'yannick', '36 Avenue de la Bastide Neuve', '0203040506', 'driver1@example.com', NULL, '1994-11-05', 'uploads/avatars/u1_1756386804.jpg', '$2y$10$DPJ9YyT1DwkYmCnPcWtwzOoklfHsW3nihwWXsEqRAsnBnLtP0I1Gi', 'USER', 30, 0, '2025-08-21 15:00:32', '2025-08-28 16:34:07', NULL, NULL),
(2, 'employer 1', NULL, NULL, NULL, 'employee@example.com', NULL, NULL, NULL, '$2y$10$PsW5BWZ6dOGkcZI6cIN88uwahU.Q/xeIYr25P6/.hUNr7SpyUEZG2', 'EMPLOYEE', 20, 0, '2025-08-21 15:00:32', '2025-09-30 13:03:20', NULL, NULL),
(3, 'admin', NULL, NULL, NULL, 'admin@example.com', NULL, NULL, NULL, '$2y$10$PsW5BWZ6dOGkcZI6cIN88uwahU.Q/xeIYr25P6/.hUNr7SpyUEZG2', 'ADMIN', 999, 0, '2025-08-21 15:00:32', '2025-08-21 15:04:35', NULL, NULL),
(4, 'sudan', 'eric', '1 rue diablique', '0102030405', 'sudan@ecoride.com', NULL, '1994-11-05', 'uploads/avatars/u4_1756386893.jpg', '$2y$10$IRv4A.fTremq54Nhyoplu..ay5QjIV4yT/5UdSLcofGegbMyCRbpG', 'USER', 40, 0, '2025-08-21 16:42:37', '2025-09-02 10:16:16', '2025-08-27 16:30:30', NULL),
(8, 'fichou', 'jesuis', '36 Avenue de la Bastide Neuve', '0629935796', 'bastienburghgraeve@hotmail.fr', NULL, '1994-11-05', 'uploads/avatars/u8_1756386940.jpg', '$2y$10$ocL4VbjaYas.OjWlNNLA/.Yfn1Avg68XqwJSsY6GK5ouVxdJy8plq', 'USER', 16, 0, '2025-08-27 14:08:39', '2025-09-30 12:36:12', NULL, NULL),
(9, 'volt', 'damien', '8 rue du vieux nans', '0629935778', 'volte@ecoride.com', NULL, '1994-11-05', 'uploads/avatars/u9_1756386989.jpg', '$2y$10$V4ctKzX7BG0QjCix8en05..x9.2Ie6uMtE7niEE3u8SuHJmdia.L6', 'USER', 50, 0, '2025-08-27 15:35:36', '2025-09-02 10:15:53', '2025-08-27 16:30:30', NULL),
(10, 'test', 'test', '8 rue du vieux nans', '0629935786', 'test@hotmail.fr', NULL, NULL, NULL, '$2y$10$vslvoNv8WbuefH0aL6.7du/7PcvCKYvS5GbsjT57fTrdjP8Af740G', 'USER', 20, 1, '2025-08-29 11:44:51', '2025-08-29 11:45:22', NULL, NULL),
(14, 'burghgraeve', 'elodie', '8 rue du vieux nans', '0629935787', 'bastienburghgraeve@gmail.com', NULL, '1994-11-05', 'uploads/avatars/u14_1758795039.jpg', '$2y$10$Im7C26z70P4EBm/ndSV0mOuYTLP3UgjOyJ5vWHrIEViMMlyn/drXS', 'USER', 52, 0, '2025-09-25 10:07:00', '2025-09-30 12:36:12', NULL, NULL),
(16, 'deluca', 'bastien', '8 rue du vieux nans', '0629935789', 'bastiendeluca@hotmail.com', NULL, NULL, NULL, '$2y$10$rbclLE7yNttYM.jYsCPiE.Aj3UqhGqqTYPUFTyVcS2dSyN1oUgo6a', 'USER', 20, 0, '2025-09-26 09:11:01', '2025-09-26 09:11:01', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `user_preferences`
--
-- Création : mar. 26 août 2025 à 16:13
--

DROP TABLE IF EXISTS `user_preferences`;
CREATE TABLE `user_preferences` (
  `user_id` int NOT NULL,
  `smoker` tinyint(1) NOT NULL DEFAULT '0',
  `animals` tinyint(1) NOT NULL DEFAULT '0',
  `music` tinyint(1) NOT NULL DEFAULT '1',
  `chatty` tinyint(1) NOT NULL DEFAULT '1',
  `ac` tinyint(1) NOT NULL DEFAULT '1',
  `custom_prefs` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `user_preferences`
--

INSERT INTO `user_preferences` (`user_id`, `smoker`, `animals`, `music`, `chatty`, `ac`, `custom_prefs`, `updated_at`) VALUES
(1, 1, 1, 2, 2, 1, NULL, '2025-08-28 16:34:07'),
(4, 1, 1, 1, 1, 1, NULL, '2025-08-27 13:44:28'),
(8, 2, 2, 1, 1, 1, NULL, '2025-08-29 10:21:12'),
(9, 1, 1, 1, 1, 1, NULL, '2025-08-28 13:16:29'),
(14, 1, 2, 2, 1, 1, NULL, '2025-09-25 10:10:39');

-- --------------------------------------------------------

--
-- Structure de la table `vehicles`
--
-- Création : jeu. 21 août 2025 à 15:00
--

DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `brand` varchar(60) NOT NULL,
  `model` varchar(60) NOT NULL,
  `color` varchar(40) DEFAULT NULL,
  `energy` enum('ESSENCE','DIESEL','ELECTRIC','HYBRID') NOT NULL,
  `plate` varchar(20) NOT NULL,
  `first_reg_date` date DEFAULT NULL,
  `seats` int NOT NULL DEFAULT '4'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `vehicles`
--

INSERT INTO `vehicles` (`id`, `user_id`, `brand`, `model`, `color`, `energy`, `plate`, `first_reg_date`, `seats`) VALUES
(1, 1, 'Tesla', 'Model 3', 'blanc', 'ELECTRIC', 'AA-123-AA', '2021-06-01', 4),
(3, 4, 'Tesla', 'Model 4', 'rouge', 'ELECTRIC', 'AA-123-BB', '2025-08-12', 4),
(7, 8, 'Tesla', 'Model 4', 'rouge', 'ELECTRIC', 'AA-123-CC', '2025-08-23', 4),
(8, 14, 'Tesla', 'Model 4', 'rouge', 'ELECTRIC', 'AA-123-EE', '2022-12-12', 4);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ride_id` (`ride_id`),
  ADD KEY `passenger_id` (`passenger_id`);

--
-- Index pour la table `cron_state`
--
ALTER TABLE `cron_state`
  ADD PRIMARY KEY (`k`);

--
-- Index pour la table `penalties_platform`
--
ALTER TABLE `penalties_platform`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ride_id` (`ride_id`);

--
-- Index pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rev_driver` (`driver_id`,`status`),
  ADD KEY `idx_rev_ride` (`ride_id`),
  ADD KEY `fk_rev_pass` (`passenger_id`);

--
-- Index pour la table `rides`
--
ALTER TABLE `rides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_driver_vehicle_date` (`driver_id`,`vehicle_id`,`date_start`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `idx_search` (`from_city`,`to_city`,`date_start`);

--
-- Index pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `telephone` (`telephone`);

--
-- Index pour la table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Index pour la table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vehicles_plate` (`plate`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `penalties_platform`
--
ALTER TABLE `penalties_platform`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rides`
--
ALTER TABLE `rides`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`passenger_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `penalties_platform`
--
ALTER TABLE `penalties_platform`
  ADD CONSTRAINT `penalties_platform_ibfk_1` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_rev_driv` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rev_pass` FOREIGN KEY (`passenger_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rev_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rides`
--
ALTER TABLE `rides`
  ADD CONSTRAINT `rides_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rides_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE RESTRICT;

--
-- Contraintes pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `fk_user_prefs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
