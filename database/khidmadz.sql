-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2026 at 09:52 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `khidmadz1`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(20) NOT NULL,
  `id_user` int(20) DEFAULT NULL,
  `niveau_acces` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id_admin`, `id_user`, `niveau_acces`) VALUES
(0, 0, 'super_admin');

-- --------------------------------------------------------

--
-- Table structure for table `avis`
--

CREATE TABLE `avis` (
  `id_avis` int(20) NOT NULL,
  `id_client` int(20) DEFAULT NULL,
  `id_technicien` int(20) DEFAULT NULL,
  `id_service` int(11) DEFAULT NULL,
  `note` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `photo` varchar(200) DEFAULT NULL,
  `date_avis` date DEFAULT NULL,
  `visible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `avis`
--

INSERT INTO `avis` (`id_avis`, `id_client`, `id_technicien`, `id_service`, `note`, `commentaire`, `photo`, `date_avis`, `visible`) VALUES
(8, 10, 18, 88, 5, '', NULL, '2026-05-05', 1),
(9, 10, 18, 98, 5, '', NULL, '2026-05-17', 1),
(10, 10, 23, 106, 5, '', NULL, '2026-05-22', 1);

-- --------------------------------------------------------

--
-- Table structure for table `client`
--

CREATE TABLE `client` (
  `id_client` int(11) NOT NULL,
  `id_user` int(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client`
--

INSERT INTO `client` (`id_client`, `id_user`) VALUES
(10, 42),
(11, 45);

-- --------------------------------------------------------

--
-- Table structure for table `commission`
--

CREATE TABLE `commission` (
  `id_commission` int(20) NOT NULL,
  `id_service` int(11) NOT NULL,
  `id_paiment` int(20) NOT NULL,
  `id_technicien` int(11) NOT NULL,
  `montant_service` float NOT NULL DEFAULT 0 COMMENT 'Montant HT encaissé par le tech',
  `taux_commission` decimal(5,2) NOT NULL DEFAULT 8.00,
  `montant_commission` float NOT NULL DEFAULT 0 COMMENT 'Ce que le tech doit à Khidma DZ',
  `statut` enum('due','payée') NOT NULL DEFAULT 'due',
  `date_commission` datetime NOT NULL DEFAULT current_timestamp(),
  `date_paiement` datetime DEFAULT NULL COMMENT 'Quand le tech a payé la plateforme'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission`
--

INSERT INTO `commission` (`id_commission`, `id_service`, `id_paiment`, `id_technicien`, `montant_service`, `taux_commission`, `montant_commission`, `statut`, `date_commission`, `date_paiement`) VALUES
(1, 88, 36, 18, 2500, 8.00, 200, 'payée', '2026-05-05 00:00:00', '2026-06-06 16:01:41'),
(2, 89, 37, 18, 800, 8.00, 64, 'payée', '2026-05-05 00:00:00', '2026-06-06 16:01:41'),
(3, 90, 38, 18, 2500, 8.00, 200, 'payée', '2026-05-05 00:00:00', '2026-06-06 16:01:41'),
(4, 91, 39, 18, 6307, 8.00, 504.56, 'payée', '2026-05-05 00:00:00', '2026-06-06 16:01:41'),
(5, 93, 40, 18, 5950, 8.00, 476, 'payée', '2026-05-05 00:00:00', '2026-06-06 16:01:41'),
(6, 94, 41, 18, 13447, 8.00, 1075.76, 'payée', '2026-05-17 00:00:00', '2026-06-06 16:01:41'),
(7, 95, 42, 18, 2500, 8.00, 200, 'payée', '2026-05-17 00:00:00', '2026-06-06 16:01:41'),
(8, 96, 44, 18, 1500, 8.00, 120, 'payée', '2026-05-17 00:00:00', '2026-06-06 16:01:41'),
(9, 97, 48, 18, 6000, 8.00, 480, 'payée', '2026-05-17 00:00:00', '2026-06-06 16:01:41'),
(10, 98, 49, 18, 5800, 8.00, 464, 'payée', '2026-05-17 00:00:00', '2026-06-06 16:01:41'),
(11, 99, 50, 18, 800, 8.00, 64, 'payée', '2026-05-17 00:00:00', '2026-06-06 16:01:41'),
(12, 101, 53, 18, 3300, 8.00, 264, 'payée', '2026-05-17 00:00:00', '2026-06-06 16:01:41'),
(13, 102, 55, 18, 1500, 8.00, 120, 'payée', '2026-05-17 00:00:00', '2026-06-06 16:01:41'),
(14, 103, 59, 23, 5500, 8.00, 440, 'payée', '2026-05-22 00:00:00', '2026-06-06 16:16:51'),
(15, 104, 60, 18, 7500, 8.00, 600, 'payée', '2026-05-22 00:00:00', '2026-06-06 16:01:41'),
(16, 106, 61, 23, 1500, 8.00, 120, 'payée', '2026-05-22 00:00:00', '2026-06-06 16:16:51'),
(17, 107, 62, 23, 6500, 8.00, 520, 'payée', '2026-05-22 00:00:00', '2026-06-06 16:16:51'),
(18, 108, 63, 23, 6500, 8.00, 520, 'payée', '2026-05-22 00:00:00', '2026-06-06 16:16:51'),
(19, 114, 64, 18, 6500, 8.00, 520, 'payée', '2026-05-23 00:00:00', '2026-06-06 16:01:41'),
(20, 115, 65, 18, 5000, 8.00, 400, 'payée', '2026-06-05 00:00:00', '2026-06-06 16:16:54'),
(21, 116, 66, 18, 7500, 8.00, 600, 'payée', '2026-06-05 00:00:00', '2026-06-06 16:16:54'),
(22, 117, 67, 18, 6000, 8.00, 480, 'payée', '2026-06-05 00:00:00', '2026-06-06 16:16:54'),
(23, 118, 68, 18, 7000, 8.00, 560, 'payée', '2026-06-05 00:00:00', '2026-06-06 16:16:54');

-- --------------------------------------------------------

--
-- Table structure for table `disponibilite`
--

CREATE TABLE `disponibilite` (
  `id_disponibilite` int(20) NOT NULL,
  `id_technicien` int(20) DEFAULT NULL,
  `jour` varchar(20) DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `statut_tech` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disponibilite`
--

INSERT INTO `disponibilite` (`id_disponibilite`, `id_technicien`, `jour`, `heure_debut`, `heure_fin`, `statut_tech`) VALUES
(57, 23, 'Lundi', '00:00:00', '23:00:00', 'disponible'),
(58, 23, 'Mardi', '00:00:00', '23:00:00', 'disponible'),
(59, 23, 'Mercredi', '00:00:00', '23:00:00', 'disponible'),
(60, 23, 'Jeudi', '00:00:00', '23:00:00', 'disponible'),
(61, 23, 'Vendredi', '00:00:00', '23:00:00', 'disponible'),
(62, 23, 'Samedi', '00:00:00', '23:00:00', 'disponible'),
(63, 23, 'Dimanche', '00:00:00', '23:00:00', 'disponible'),
(64, 22, 'Lundi', '00:00:00', '23:02:00', 'disponible'),
(65, 22, 'Mardi', '00:00:00', '23:00:00', 'disponible'),
(66, 22, 'Mercredi', '00:00:00', '23:00:00', 'disponible'),
(67, 22, 'Jeudi', '00:00:00', '23:00:00', 'disponible'),
(68, 22, 'Vendredi', '00:00:00', '23:00:00', 'disponible'),
(69, 22, 'Samedi', '00:00:00', '23:00:00', 'disponible'),
(70, 22, 'Dimanche', '00:00:00', '23:00:00', 'disponible'),
(71, 18, 'Lundi', '00:00:00', '23:00:00', 'disponible'),
(72, 18, 'Mardi', '00:00:00', '23:00:00', 'disponible'),
(73, 18, 'Mercredi', '00:00:00', '23:00:00', 'disponible'),
(74, 18, 'Jeudi', '00:00:00', '23:00:00', 'disponible'),
(75, 18, 'Vendredi', '00:00:00', '23:00:00', 'disponible'),
(76, 18, 'Samedi', '00:00:00', '23:00:00', 'disponible'),
(77, 18, 'Dimanche', '00:00:00', '23:00:00', 'disponible');

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL,
  `demande_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `tech_id` int(11) NOT NULL,
  `service_nom` varchar(255) NOT NULL,
  `note` tinyint(4) NOT NULL CHECK (`note` between 1 and 5),
  `commentaire` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facture`
--

CREATE TABLE `facture` (
  `id_facture` int(20) NOT NULL,
  `id_service` int(20) DEFAULT NULL,
  `date_facture` date DEFAULT NULL,
  `montant_total` float DEFAULT NULL,
  `fichier_facture` varchar(200) DEFAULT NULL,
  `statut_facture` enum('émise','payée','annulée') NOT NULL DEFAULT 'émise'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facture`
--

INSERT INTO `facture` (`id_facture`, `id_service`, `date_facture`, `montant_total`, `fichier_facture`, `statut_facture`) VALUES
(1, 8, '2026-03-07', 3500, NULL, 'émise'),
(2, 11, '2026-03-08', 2100, NULL, 'émise'),
(3, 12, '2026-03-08', 1600, 'factures/facture_12.html', 'émise'),
(4, 13, '2026-03-08', 1800, 'factures/facture_13.html', 'émise'),
(5, 14, '2026-03-08', 100, 'factures/facture_14.html', 'émise'),
(6, 16, '2026-03-08', 800, 'factures/facture_16.html', 'émise'),
(7, 19, '2026-03-09', 8000, 'factures/facture_19.html', 'émise'),
(8, 17, '2026-03-09', 7500, NULL, 'émise'),
(9, 21, '2026-03-09', 6000, 'factures/facture_21.html', 'émise'),
(10, 22, '2026-03-12', 7500, 'factures/facture_22.html', 'émise'),
(11, 23, '2026-03-12', 2000, 'factures/facture_23.html', 'émise'),
(12, 24, '2026-03-12', 3000, 'factures/facture_24.html', 'émise'),
(13, 25, '2026-03-13', 4000, 'factures/facture_25.html', 'émise'),
(14, 26, '2026-03-19', 2500, NULL, 'émise'),
(15, 28, '2026-03-19', 4000, 'factures/facture_28.html', 'émise'),
(16, 29, '2026-03-19', 7000, NULL, 'émise'),
(17, 30, '2026-03-19', 3000, NULL, 'payée'),
(18, 31, '2026-03-19', 4000, NULL, 'payée'),
(19, 33, '2026-03-19', 5000, NULL, 'payée'),
(20, 40, '2026-03-28', 10500, NULL, 'payée'),
(21, 45, '2026-04-06', 4500, NULL, 'payée'),
(22, 46, '2026-04-07', 3000, NULL, 'payée'),
(23, 47, '2026-04-08', 8000, NULL, 'payée'),
(24, 48, '2026-04-08', 2000, NULL, 'payée'),
(25, 50, '2026-04-08', 1000, NULL, 'payée'),
(26, 51, '2026-04-10', 2000, NULL, 'payée'),
(27, 52, '2026-04-10', 1500, NULL, 'payée'),
(28, 54, '2026-04-10', 2500, NULL, 'payée'),
(29, 55, '2026-04-13', 1000, NULL, 'payée'),
(30, 56, '2026-04-13', 1000, NULL, 'payée'),
(31, 57, '2026-04-13', 2000, NULL, 'payée'),
(32, 58, '2026-04-20', 1500, NULL, 'payée'),
(33, 59, '2026-04-20', 2000, NULL, 'payée'),
(34, 75, '2026-04-29', 2000, NULL, 'payée'),
(35, 76, '2026-04-29', 4500, NULL, 'payée'),
(36, 78, '2026-04-29', 2000, NULL, 'payée'),
(37, 79, '2026-04-29', 1500, NULL, 'payée'),
(38, 53, '2026-05-04', 2000, NULL, 'émise'),
(39, 49, '2026-05-04', 1500, NULL, 'émise'),
(40, 88, '2026-05-05', 2500, NULL, 'payée'),
(41, 91, '2026-05-05', 6307, NULL, 'payée'),
(42, 93, '2026-05-05', 5950, NULL, 'payée'),
(46, 94, '2026-05-17', 13447, NULL, 'payée'),
(47, 95, '2026-05-17', 2500, NULL, 'payée'),
(48, 95, '2026-05-17', 8925, NULL, 'émise'),
(49, 96, '2026-05-17', 8330, NULL, 'émise'),
(50, 96, '2026-05-17', 10115, NULL, 'émise'),
(51, 97, '2026-05-17', 6000, NULL, 'payée'),
(52, 98, '2026-05-17', 5800, NULL, 'payée'),
(53, 99, '2026-05-17', 6902, NULL, 'émise'),
(54, 101, '2026-05-17', 9877, NULL, 'émise'),
(55, 102, '2026-05-17', 7735, NULL, 'émise'),
(56, 100, '2026-05-17', 5800, NULL, 'émise'),
(57, 102, '2026-05-21', 9520, NULL, 'émise'),
(58, 104, '2026-05-22', 7500, NULL, 'payée'),
(59, 103, '2026-05-22', 5500, NULL, 'payée'),
(60, 107, '2026-05-22', 6500, NULL, 'payée'),
(61, 108, '2026-05-22', 6500, NULL, 'payée'),
(62, 114, '2026-05-23', 6500, NULL, 'payée'),
(63, 115, '2026-06-05', 5000, NULL, 'payée'),
(64, 116, '2026-06-05', 7500, NULL, 'payée'),
(65, 117, '2026-06-05', 6000, NULL, 'payée'),
(66, 118, '2026-06-05', 7000, NULL, 'payée');

-- --------------------------------------------------------

--
-- Table structure for table `litige`
--

CREATE TABLE `litige` (
  `id_litige` int(20) NOT NULL,
  `id_service` int(20) DEFAULT NULL,
  `id_admin` int(20) DEFAULT NULL,
  `id_client` int(20) DEFAULT NULL,
  `id_technicien` int(20) DEFAULT NULL,
  `description_litige` text DEFAULT NULL,
  `statut_litige` varchar(20) DEFAULT NULL,
  `date_overture` date DEFAULT NULL,
  `date_resolution` date DEFAULT NULL,
  `resolution` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_action`
--

CREATE TABLE `log_action` (
  `id_log` int(20) NOT NULL,
  `id_user` int(20) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `date_action` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_action`
--

INSERT INTO `log_action` (`id_log`, `id_user`, `action`, `details`, `ip`, `date_action`) VALUES
(1, 12, 'gen_facture', 'Service #8 montant 3500', '::1', '2026-03-07 00:26:34'),
(2, 12, 'view_facture', 'Facture #1', '::1', '2026-03-07 00:26:42'),
(6, 12, 'resolve_litige', 'Litige #1 → resolu', '::1', '2026-03-07 00:31:48'),
(12, 12, 'view_facture', 'Facture #1', '::1', '2026-03-07 01:18:56'),
(13, 12, 'view_facture', 'Facture #1', '::1', '2026-03-07 01:19:14'),
(14, 12, 'view_facture', 'Facture #1', '::1', '2026-03-07 01:20:01'),
(17, 12, 'handle_signal', 'Signalement #1 → traité', '::1', '2026-03-07 01:49:02'),
(18, 12, 'handle_signal', 'Signalement #1 → traité', '::1', '2026-03-07 01:49:06'),
(24, 12, 'gen_facture', 'Service #11 montant 2100', '::1', '2026-03-08 01:14:53'),
(25, 12, 'reject_tech', 'Tech #1', '::1', '2026-03-08 01:23:58'),
(26, 12, 'suspend_user', 'User #9 raison: ', '::1', '2026-03-08 01:24:07'),
(27, 12, 'reject_tech', 'Tech #1', '::1', '2026-03-08 01:24:12'),
(28, 12, 'reject_tech', 'Tech #1', '::1', '2026-03-08 01:24:14'),
(29, 12, 'reject_tech', 'Tech #3', '::1', '2026-03-08 01:24:21'),
(30, 12, 'reject_tech', 'Tech #1', '::1', '2026-03-08 01:24:23'),
(31, 12, 'reject_tech', 'Tech #5', '::1', '2026-03-08 01:24:59'),
(32, 12, 'suspend_user', 'User #13 raison: ', '::1', '2026-03-08 01:25:04'),
(33, 12, 'suspend_user', 'User #9 raison: ', '::1', '2026-03-08 01:25:15'),
(53, 12, 'reject_tech', 'Tech #6', '::1', '2026-03-08 17:23:32'),
(54, 12, 'suspend_user', 'User #1 raison: ', '::1', '2026-03-08 17:26:03'),
(55, 12, 'suspend_user', 'User #17 raison: ', '::1', '2026-03-08 17:31:03'),
(56, 12, 'suspend_user', 'User #18 raison: ', '::1', '2026-03-08 17:31:12'),
(59, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-08 17:45:24'),
(60, 12, 'reject_tech', 'Tech #5', '::1', '2026-03-08 17:45:34'),
(61, 12, 'reject_tech', 'Tech #1', '::1', '2026-03-08 17:45:41'),
(62, 12, 'reject_tech', 'Tech #3', '::1', '2026-03-08 17:45:43'),
(63, 12, 'activate_user', 'User #18', '::1', '2026-03-08 17:46:10'),
(65, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-08 17:47:20'),
(66, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-08 18:02:49'),
(69, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-08 18:07:02'),
(70, 12, 'approve_tech', 'Tech #8', '::1', '2026-03-08 18:07:32'),
(72, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-08 18:08:21'),
(73, 12, 'reject_tech', 'Tech #6', '::1', '2026-03-08 18:08:25'),
(74, 12, 'suspend_user', 'User #19 raison: ', '::1', '2026-03-08 18:08:38'),
(80, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-08 23:53:45'),
(92, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 00:14:27'),
(93, 12, 'reject_tech', 'Tech #6', '::1', '2026-03-09 00:16:04'),
(95, 12, 'reject_tech', 'Tech #9', '::1', '2026-03-09 00:20:00'),
(96, 12, 'suspend_user', 'User #19 raison: ', '::1', '2026-03-09 00:25:37'),
(97, 12, 'activate_user', 'User #20', '::1', '2026-03-09 00:25:49'),
(98, 12, 'suspend_user', 'User #9 raison: ', '::1', '2026-03-09 00:26:17'),
(99, 12, 'reject_tech', 'Tech #9 supprimé', '::1', '2026-03-09 00:26:39'),
(100, 12, 'reject_tech', 'Tech #9', '::1', '2026-03-09 00:26:39'),
(103, 12, 'approve_tech', 'Tech #10', '::1', '2026-03-09 00:30:53'),
(104, 12, 'suspend_user', 'User #21 raison: ', '::1', '2026-03-09 00:34:40'),
(105, 12, 'suspend_user', 'User #21 raison: ', '::1', '2026-03-09 00:35:26'),
(106, 12, 'activate_user', 'User #21', '::1', '2026-03-09 00:40:17'),
(107, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 01:30:04'),
(111, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 01:31:49'),
(112, 12, 'suspend_user', 'User #18 raison: ', '::1', '2026-03-09 01:32:00'),
(113, 12, 'handle_signal', 'Signalement #2 → traité', '::1', '2026-03-09 01:32:04'),
(114, 12, 'handle_signal', 'Signalement #2 → traité', '::1', '2026-03-09 01:32:07'),
(115, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 01:57:57'),
(116, 12, 'activate_user', 'User #18', '::1', '2026-03-09 01:58:09'),
(121, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 17:53:31'),
(122, 12, 'delete_user', 'User #13', '::1', '2026-03-09 17:59:37'),
(123, 12, 'delete_user', 'User #9', '::1', '2026-03-09 17:59:43'),
(124, 12, 'approve_tech', 'Tech #11', '::1', '2026-03-09 17:59:57'),
(132, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 18:11:52'),
(144, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 18:20:56'),
(147, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 18:28:13'),
(148, 12, 'gen_facture', 'Service #17 montant 7500', '::1', '2026-03-09 18:28:50'),
(151, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 18:45:45'),
(155, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 18:58:12'),
(161, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-09 19:11:29'),
(162, 12, 'suspend_user', 'User #18 raison: ', '::1', '2026-03-10 23:57:56'),
(163, 12, 'delete_avis', 'Avis #2', '::1', '2026-03-10 23:58:09'),
(164, 12, 'delete_user', 'User #23', '::1', '2026-03-10 23:59:22'),
(165, 12, 'handle_signal', 'Signalement #2 → traité', '::1', '2026-03-11 00:00:14'),
(166, 12, 'send_message', 'To user #18', '::1', '2026-03-11 00:10:08'),
(167, 12, 'send_message', 'To user #18', '::1', '2026-03-11 00:10:24'),
(170, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 00:27:31'),
(171, 12, 'activate_user', 'User #18', '::1', '2026-03-11 00:27:44'),
(173, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 00:47:38'),
(177, 12, 'send_message', 'To user #25', '::1', '2026-03-11 00:54:31'),
(180, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 01:18:04'),
(182, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 01:19:30'),
(185, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 01:20:47'),
(186, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 04:04:58'),
(187, 12, 'suspend_user', 'User #25 raison: ', '::1', '2026-03-11 04:11:31'),
(188, 12, 'activate_user', 'User #25', '::1', '2026-03-11 04:18:08'),
(189, 12, 'handle_signal', 'Signalement #3 → traité', '::1', '2026-03-11 04:18:26'),
(191, 12, 'send_message', 'To user #25', '::1', '2026-03-11 04:19:23'),
(193, 12, 'send_message', 'To user #18', '::1', '2026-03-11 04:20:33'),
(196, 12, 'send_message', 'To user #21', '::1', '2026-03-11 04:25:10'),
(198, 12, 'send_message', 'To user #21', '::1', '2026-03-11 04:29:42'),
(199, 12, 'create_admin', 'Nouvel admin créé : admino@khidma.dz (id=27)', '::1', '2026-03-11 04:39:22'),
(200, 27, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 04:39:44'),
(201, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 04:42:43'),
(202, 27, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 04:48:17'),
(203, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 04:56:01'),
(204, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-11 05:02:01'),
(205, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-12 01:54:20'),
(217, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-12 03:20:06'),
(252, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-18 23:51:13'),
(259, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-19 00:28:33'),
(260, 12, 'send_message', 'To user #21', '::1', '2026-03-19 00:28:48'),
(263, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-19 01:22:33'),
(271, 12, 'login', 'Connexion depuis 192.168.1.5', '192.168.1.5', '2026-03-19 02:20:16'),
(279, 12, 'login', 'Connexion depuis 192.168.1.5', '192.168.1.5', '2026-03-19 02:34:31'),
(280, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-19 02:35:12'),
(281, 12, 'send_message', 'To user #18', '::1', '2026-03-19 02:40:11'),
(282, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-19 02:43:58'),
(283, 12, 'send_message', 'To user #18', '::1', '2026-03-19 02:44:28'),
(287, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-19 03:02:18'),
(288, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-19 03:03:03'),
(289, 12, 'activate_user', 'User #17', '::1', '2026-03-19 03:03:12'),
(305, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-19 17:47:30'),
(306, 12, 'delete_user', 'User #1', '::1', '2026-03-19 17:48:14'),
(373, 32, 'login', 'Connexion depuis ::1', '::1', '2026-03-28 15:47:47'),
(374, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-28 15:48:15'),
(375, 12, 'approve_tech', 'Tech #12', '::1', '2026-03-28 15:48:44'),
(376, 32, 'login', 'Connexion depuis ::1', '::1', '2026-03-28 15:49:01'),
(377, 32, 'update_profile', '', '::1', '2026-03-28 15:49:56'),
(378, 32, 'add_prestation', 'chambre', '::1', '2026-03-28 15:51:11'),
(379, 32, 'add_prestation', 'cuisine', '::1', '2026-03-28 15:51:24'),
(381, 32, 'accept_service', 'Service #44', '::1', '2026-03-28 15:52:00'),
(382, 32, 'refuse_service', 'Service #44', '::1', '2026-03-28 15:52:08'),
(383, 32, 'add_prestation', 'service complet', '::1', '2026-03-28 15:53:07'),
(384, 32, 'add_prestation', 'sallon', '::1', '2026-03-28 15:53:53'),
(385, 32, 'signalement', 'Signalement de user #31 motif: paiement', '::1', '2026-03-28 16:02:47'),
(386, 12, 'login', 'Connexion depuis ::1', '::1', '2026-03-28 16:02:59'),
(387, 12, 'suspend_user', 'User #31 raison: ', '::1', '2026-03-28 16:03:15'),
(388, 12, 'activate_user', 'User #31', '::1', '2026-03-28 16:03:52'),
(389, 12, 'handle_signal', 'Signalement #4 → traité', '::1', '2026-03-28 16:03:59'),
(390, 12, 'hide_avis', 'Avis #5', '::1', '2026-03-28 16:04:45'),
(391, 12, 'restore_avis', 'Avis #5', '::1', '2026-03-28 16:04:48'),
(392, 12, 'delete_avis', 'Avis #5', '::1', '2026-03-28 16:05:00'),
(396, 12, 'resolve_litige', 'Litige #2 → ferme', '::1', '2026-03-28 16:07:59'),
(397, 12, 'resolve_litige', 'Litige #2 → resolu_tech', '::1', '2026-03-28 16:08:31'),
(400, 32, 'login', 'Connexion depuis ::1', '::1', '2026-04-06 21:42:05'),
(401, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-06 21:43:58'),
(402, 12, 'resolve_litige', 'Litige #2 → resolu', '127.0.0.1', '2026-04-06 21:44:22'),
(404, 32, 'login', 'Connexion depuis ::1', '::1', '2026-04-06 22:13:08'),
(406, 32, 'accept_service', 'Service #45', '::1', '2026-04-06 22:14:37'),
(407, 32, 'complete_service', 'Service #45 total 4500 DA', '::1', '2026-04-06 22:14:58'),
(408, 32, 'login', 'Connexion depuis ::1', '::1', '2026-04-07 23:46:07'),
(410, 32, 'accept_service', 'Service #46', '::1', '2026-04-07 23:55:10'),
(411, 32, 'complete_service', 'Service #46 total 3000 DA', '::1', '2026-04-07 23:55:27'),
(412, 32, 'login', 'Connexion depuis ::1', '::1', '2026-04-08 11:00:45'),
(415, 32, 'accept_service', 'Service #47', '::1', '2026-04-08 11:02:28'),
(416, 32, 'complete_service', 'Service #47 total 8000 DA', '::1', '2026-04-08 11:02:43'),
(418, 32, 'accept_service', 'Service #48', '::1', '2026-04-08 11:12:42'),
(419, 32, 'complete_service', 'Service #48 total 2000 DA', '::1', '2026-04-08 11:12:47'),
(422, 32, 'accept_service', 'Service #49', '::1', '2026-04-08 11:21:51'),
(423, 32, 'complete_service', 'Service #49 total 1500 DA', '::1', '2026-04-08 11:22:05'),
(426, 32, 'accept_service', 'Service #50', '::1', '2026-04-08 11:24:47'),
(427, 32, 'complete_service', 'Service #50 total 1000 DA', '::1', '2026-04-08 11:24:58'),
(430, 32, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 16:39:14'),
(431, 32, 'add_prestation', 'Nettoyage bureau', '::1', '2026-04-09 16:55:47'),
(432, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:04:29'),
(433, 12, 'delete_user', 'User #21', '::1', '2026-04-09 17:05:43'),
(434, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:08:45'),
(435, 12, 'approve_tech', 'Tech #13', '::1', '2026-04-09 17:08:53'),
(436, 33, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:09:11'),
(437, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:12:10'),
(438, 12, 'delete_user', 'User #25', '::1', '2026-04-09 17:14:35'),
(439, 12, 'delete_user', 'User #19', '::1', '2026-04-09 17:14:40'),
(440, 12, 'delete_user', 'User #16', '::1', '2026-04-09 17:14:45'),
(441, 12, 'delete_user', 'User #29', '::1', '2026-04-09 17:14:55'),
(442, 12, 'delete_user', 'User #30', '::1', '2026-04-09 17:15:04'),
(443, 12, 'delete_user', 'User #22', '::1', '2026-04-09 17:15:22'),
(444, 12, 'delete_user', 'User #17', '::1', '2026-04-09 17:15:30'),
(445, 12, 'delete_user', 'User #18', '::1', '2026-04-09 17:16:22'),
(446, 12, 'delete_user', 'User #28', '::1', '2026-04-09 17:16:27'),
(447, 12, 'delete_user', 'User #24', '::1', '2026-04-09 17:16:31'),
(448, 12, 'delete_user', 'User #26', '::1', '2026-04-09 17:16:36'),
(449, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:18:08'),
(450, 12, 'approve_tech', 'Tech #14', '::1', '2026-04-09 17:18:20'),
(451, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:19:23'),
(452, 12, 'approve_tech', 'Tech #15', '::1', '2026-04-09 17:19:50'),
(453, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:22:43'),
(454, 12, 'approve_tech', 'Tech #16', '::1', '2026-04-09 17:22:48'),
(455, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:24:06'),
(456, 12, 'approve_tech', 'Tech #17', '::1', '2026-04-09 17:24:21'),
(457, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:26:14'),
(458, 35, 'add_prestation', 'Fuite eau', '::1', '2026-04-09 17:26:35'),
(459, 35, 'add_prestation', 'Chauffe-eau', '::1', '2026-04-09 17:29:23'),
(460, 35, 'add_prestation', 'Détection fuite', '::1', '2026-04-09 17:29:35'),
(461, 35, 'add_prestation', 'Installer douche', '::1', '2026-04-09 17:29:45'),
(462, 35, 'add_prestation', 'Remplacer robinet', '::1', '2026-04-09 17:29:59'),
(463, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 17:30:16'),
(464, 36, 'add_prestation', 'Fuite eau', '::1', '2026-04-09 17:30:26'),
(465, 36, 'add_prestation', 'Installer douche', '::1', '2026-04-09 17:30:42'),
(466, 36, 'add_prestation', 'Réparer tuyaux', '::1', '2026-04-09 17:30:58'),
(467, 36, 'add_prestation', 'Réparer WC', '::1', '2026-04-09 17:31:10'),
(468, 36, 'add_prestation', 'Détection fuite', '::1', '2026-04-09 17:34:18'),
(469, 36, 'add_custom_prestation', 'reparation urgente', '::1', '2026-04-09 17:54:11'),
(473, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-09 20:39:07'),
(478, 34, 'login', 'Connexion depuis ::1', '::1', '2026-04-10 20:27:17'),
(479, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-10 20:29:24'),
(481, 36, 'accept_service', 'Service #51', '::1', '2026-04-10 20:30:44'),
(482, 36, 'complete_service', 'Service #51 total 2000 DA', '::1', '2026-04-10 20:30:56'),
(485, 36, 'accept_service', 'Service #52', '::1', '2026-04-10 20:33:03'),
(486, 36, 'complete_service', 'Service #52 total 1500 DA', '::1', '2026-04-10 20:33:16'),
(487, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-10 20:42:10'),
(489, 36, 'accept_service', 'Service #53', '::1', '2026-04-10 20:44:16'),
(490, 36, 'complete_service', 'Service #53 total 2000 DA', '::1', '2026-04-10 20:44:35'),
(492, 36, 'accept_service', 'Service #54', '::1', '2026-04-10 20:51:46'),
(493, 36, 'complete_service', 'Service #54 total 2500 DA', '::1', '2026-04-10 20:52:02'),
(496, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-13 23:34:44'),
(498, 36, 'accept_service', 'Service #55', '::1', '2026-04-13 23:36:25'),
(499, 36, 'complete_service', 'Service #55 total 1000 DA', '::1', '2026-04-13 23:36:42'),
(500, 36, 'confirm_payment', 'Service #55 — espèces — 1000 DA', '::1', '2026-04-13 23:37:36'),
(502, 36, 'accept_service', 'Service #56', '::1', '2026-04-13 23:39:27'),
(503, 36, 'complete_service', 'Service #56 total 1000 DA', '::1', '2026-04-13 23:39:37'),
(505, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-13 23:45:40'),
(508, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-13 23:47:57'),
(509, 36, 'accept_service', 'Service #57', '::1', '2026-04-13 23:48:22'),
(510, 36, 'accept_service', 'Service #57', '::1', '2026-04-13 23:48:25'),
(511, 36, 'complete_service', 'Service #57 total 2000 DA', '::1', '2026-04-13 23:48:38'),
(515, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-14 00:45:43'),
(516, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-20 16:46:59'),
(521, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-20 18:03:02'),
(523, 36, 'accept_service', 'Service #58', '::1', '2026-04-20 18:04:32'),
(524, 36, 'complete_service', 'Service #58 total 1500 DA', '::1', '2026-04-20 18:04:52'),
(527, 36, 'accept_service', 'Service #59', '::1', '2026-04-20 18:10:47'),
(528, 36, 'complete_service', 'Service #59 total 2000 DA', '::1', '2026-04-20 18:10:52'),
(541, 36, 'refuse_service', 'Service #65', '::1', '2026-04-20 18:38:16'),
(545, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-27 17:02:12'),
(548, 36, 'refuse_service', 'Service #67', '::1', '2026-04-27 17:03:48'),
(553, 36, 'refuse_service', 'Service #70', '::1', '2026-04-27 17:21:28'),
(555, 36, 'login', 'Connexion depuis ::1', '::1', '2026-04-27 17:22:55'),
(556, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-27 17:24:36'),
(557, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-27 17:25:06'),
(560, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-27 17:26:30'),
(561, 35, 'refuse_service', 'Service #71', '::1', '2026-04-27 17:26:38'),
(568, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-27 17:33:25'),
(569, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-29 16:35:14'),
(574, 35, 'accept_service', 'Service #75', '::1', '2026-04-29 16:37:04'),
(575, 35, 'complete_service', 'Service #75 total 2000 DA', '::1', '2026-04-29 16:37:17'),
(576, 35, 'set_price', 'Service #75 — Prix: 2000 DA (auto)', '::1', '2026-04-29 16:38:48'),
(578, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-29 16:39:55'),
(580, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-29 16:42:37'),
(582, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-29 16:46:20'),
(583, 35, 'accept_service', 'Service #76', '::1', '2026-04-29 16:47:16'),
(584, 35, 'complete_service', 'Service #76 total 4500 DA', '::1', '2026-04-29 16:47:23'),
(587, 35, 'accept_service', 'Service #77', '::1', '2026-04-29 17:01:45'),
(588, 35, 'accept_service', 'Service #77', '::1', '2026-04-29 17:02:00'),
(589, 35, 'refuse_service', 'Service #77', '::1', '2026-04-29 17:02:00'),
(591, 35, 'accept_service', 'Service #78', '::1', '2026-04-29 17:04:15'),
(592, 35, 'complete_service', 'Service #78 total 2000 DA', '::1', '2026-04-29 17:04:19'),
(595, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-29 17:10:19'),
(598, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-29 17:14:16'),
(599, 35, 'accept_service', 'Service #79', '::1', '2026-04-29 17:14:22'),
(600, 35, 'complete_service', 'Service #79 total 1500 DA', '::1', '2026-04-29 17:14:27'),
(602, 12, 'login', 'Connexion depuis ::1', '::1', '2026-04-29 17:39:58'),
(604, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-30 22:51:20'),
(606, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-30 23:03:29'),
(607, 35, 'login', 'Connexion depuis ::1', '::1', '2026-04-30 23:22:57'),
(608, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-01 21:22:55'),
(609, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-01 21:24:31'),
(613, 35, 'accept_service', 'Service #80', '::1', '2026-05-01 21:33:16'),
(614, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-01 21:47:44'),
(622, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-01 23:32:42'),
(623, 35, 'update_location', 'lat=34.832741086639 lng=0.15283262538482', '::1', '2026-05-01 23:32:50'),
(624, 35, 'update_location', 'lat=34.832741086639 lng=0.15283262538482', '::1', '2026-05-01 23:32:50'),
(637, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 18:03:08'),
(639, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 18:12:54'),
(640, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 18:14:39'),
(641, 12, 'approve_tech', 'Tech #18', '::1', '2026-05-02 18:15:16'),
(642, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 19:46:31'),
(643, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 21:06:03'),
(644, 41, 'add_prestation', 'Installer interrupteur', '::1', '2026-05-02 21:06:35'),
(645, 41, 'add_prestation', 'Installer prise', '::1', '2026-05-02 21:59:16'),
(646, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 22:05:01'),
(648, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 22:12:15'),
(651, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 22:15:31'),
(653, 35, 'refuse_service', 'Service #82', '::1', '2026-05-02 22:22:59'),
(657, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 22:50:00'),
(658, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 23:10:24'),
(659, 42, 'book_service', 'Service #84 tech #15', '::1', '2026-05-02 23:10:34'),
(660, 42, 'cancel_service', 'Service #84', '::1', '2026-05-02 23:10:37'),
(662, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-02 23:47:38'),
(663, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-03 00:12:15'),
(664, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-03 00:17:36'),
(665, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-03 00:18:25'),
(666, 41, 'add_prestation', 'Réparer circuit', '::1', '2026-05-03 00:19:14'),
(667, 42, 'book_service', 'Service #85 tech #18', '::1', '2026-05-03 00:20:40'),
(668, 41, 'refuse_service', 'Service #86', '::1', '2026-05-03 00:20:47'),
(669, 42, 'book_service', 'Service #86 tech #18', '::1', '2026-05-03 00:21:02'),
(670, 41, 'refuse_service', 'Service #85', '::1', '2026-05-03 00:21:16'),
(672, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-03 00:34:53'),
(673, 42, 'update_profile', '', '::1', '2026-05-03 00:35:45'),
(674, 42, 'update_profile', '', '::1', '2026-05-03 00:50:37'),
(675, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 21:50:12'),
(676, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 21:57:25'),
(677, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 22:41:49'),
(678, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 22:42:14'),
(679, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 22:42:38'),
(680, 42, 'book_service', 'Service #87 tech #18', '::1', '2026-05-04 22:43:36'),
(681, 42, 'cancel_service', 'Service #87', '::1', '2026-05-04 22:43:40'),
(682, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 22:45:00'),
(683, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 22:47:24'),
(684, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 22:52:47'),
(685, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 22:53:14'),
(686, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 23:08:07'),
(687, 12, 'gen_facture', 'Service #53 montant 2000 DA (auto)', '::1', '2026-05-04 23:15:37'),
(688, 12, 'gen_facture', 'Service #49 montant 1500 DA (auto)', '::1', '2026-05-04 23:15:38'),
(689, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-04 23:16:16'),
(690, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-05 09:37:08'),
(691, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-05 20:33:10'),
(692, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-05 20:38:37'),
(693, 42, 'book_service', 'Service #88 tech #18', '::1', '2026-05-05 20:43:47'),
(694, 41, 'accept_service', 'Service #88', '::1', '2026-05-05 20:43:58'),
(695, 41, 'complete_service', 'Service #88 total 2500 DA', '::1', '2026-05-05 20:44:02'),
(696, 42, 'confirm_payment', 'Service #88 — espèces — 2500 DA', '::1', '2026-05-05 20:44:33'),
(697, 42, 'rate_tech', 'Tech #18 note 5', '::1', '2026-05-05 20:47:08'),
(698, 42, 'book_service', 'Service #89 tech #18', '::1', '2026-05-05 20:47:39'),
(699, 41, 'accept_service', 'Service #89', '::1', '2026-05-05 20:47:49'),
(700, 41, 'complete_service', 'Service #89 total 800 DA', '::1', '2026-05-05 20:47:54'),
(701, 42, 'confirm_payment', 'Service #89 — espèces — 800 DA', '::1', '2026-05-05 20:48:07'),
(702, 42, 'book_service', 'Service #90 tech #18', '::1', '2026-05-05 21:00:43'),
(703, 41, 'accept_service', 'Service #90', '::1', '2026-05-05 21:00:48'),
(704, 41, 'complete_service', 'Service #90 total 2500 DA', '::1', '2026-05-05 21:00:50'),
(705, 42, 'confirm_payment', 'Service #90 — espèces — 2500 DA', '::1', '2026-05-05 21:01:07'),
(706, 41, 'add_prestation', 'Installer interrupteur', '::1', '2026-05-05 21:11:37'),
(707, 42, 'book_service', 'Service #91 tech #18', '::1', '2026-05-05 21:12:11'),
(708, 41, 'accept_service', 'Service #91', '::1', '2026-05-05 21:12:56'),
(709, 41, 'complete_service', 'Service #91 total 5300 DA', '::1', '2026-05-05 21:13:03'),
(710, 42, 'confirm_payment', 'Service #91 — espèces — 5300 DA', '::1', '2026-05-05 21:14:08'),
(711, 42, 'book_service', 'Service #92 tech #18', '::1', '2026-05-05 21:28:20'),
(712, 42, 'cancel_service', 'Service #92', '::1', '2026-05-05 21:28:23'),
(713, 42, 'book_service', 'Service #93 tech #18', '::1', '2026-05-05 21:29:01'),
(714, 41, 'accept_service', 'Service #93', '::1', '2026-05-05 21:29:14'),
(715, 41, 'complete_service', 'Service #93 total 5000 DA', '::1', '2026-05-05 21:29:29'),
(716, 42, 'confirm_payment', 'Service #93 — espèces — 5000 DA', '::1', '2026-05-05 21:29:52'),
(717, 42, 'book_service', 'Service #94 tech #18', '::1', '2026-05-05 21:42:44'),
(718, 41, 'accept_service', 'Service #94', '::1', '2026-05-05 21:43:01'),
(719, 41, 'complete_service', 'Service #94 total 6500 DA', '::1', '2026-05-05 21:43:09'),
(720, 42, 'confirm_payment', 'Service #94 — espèces — 6500 DA', '::1', '2026-05-05 21:43:39'),
(721, 42, 'confirm_payment', 'Service #94 — espèces — 8000 DA', '::1', '2026-05-05 21:44:20'),
(723, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 19:10:52'),
(724, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 19:13:05'),
(725, 42, 'confirm_payment', 'Service #94 — espèces — 3000 DA', '::1', '2026-05-17 19:13:35'),
(726, 42, 'confirm_payment', 'Service #94 — espèces — 11300 DA', '::1', '2026-05-17 19:15:58'),
(727, 42, 'book_service', 'Service #95 tech #18', '::1', '2026-05-17 19:40:29'),
(728, 41, 'accept_service', 'Service #95', '::1', '2026-05-17 19:41:42'),
(729, 41, 'complete_service', 'Service #95 total 2500 DA', '::1', '2026-05-17 19:41:49'),
(730, 42, 'confirm_payment_partial', 'Service #95 paiment #42 — espèces — 2500 DA', '::1', '2026-05-17 19:42:21'),
(731, 42, 'book_service', 'Service #96 tech #18', '::1', '2026-05-17 19:52:01'),
(732, 41, 'accept_service', 'Service #96', '::1', '2026-05-17 19:52:09'),
(733, 41, 'complete_service', 'Service #96 total 1500 DA', '::1', '2026-05-17 19:52:13'),
(734, 42, 'confirm_payment_partial', 'Service #96 paiment #44 — espèces — 1500 DA', '::1', '2026-05-17 19:52:41'),
(735, 42, 'book_service', 'Service #97 tech #18', '::1', '2026-05-17 20:16:45'),
(736, 41, 'accept_service', 'Service #97', '::1', '2026-05-17 20:16:59'),
(737, 41, 'complete_service', 'Service #97 total 6000 DA', '::1', '2026-05-17 20:17:02'),
(738, 42, 'confirm_payment_partial', 'Service #97 paiment #48 — espèces — 6000 DA', '::1', '2026-05-17 20:17:25'),
(739, 42, 'book_service', 'Service #98 tech #18', '::1', '2026-05-17 20:19:56'),
(740, 41, 'accept_service', 'Service #98', '::1', '2026-05-17 20:20:00'),
(741, 41, 'complete_service', 'Service #98 total 5800 DA', '::1', '2026-05-17 20:20:12'),
(742, 42, 'confirm_payment_partial', 'Service #98 paiment #49 — espèces — 5800 DA', '::1', '2026-05-17 20:20:35'),
(743, 42, 'rate_tech', 'Tech #18 note 5', '::1', '2026-05-17 20:26:06'),
(744, 42, 'book_service', 'Service #99 tech #18', '::1', '2026-05-17 20:26:35'),
(745, 41, 'accept_service', 'Service #99', '::1', '2026-05-17 20:26:45'),
(746, 41, 'complete_service', 'Service #99 total 800 DA', '::1', '2026-05-17 20:26:50'),
(747, 42, 'confirm_payment_partial', 'Service #99 paiment #50 — espèces — 800 DA', '::1', '2026-05-17 20:29:04'),
(748, 42, 'book_service', 'Service #100 tech #18', '::1', '2026-05-17 20:34:28'),
(749, 41, 'accept_service', 'Service #100', '::1', '2026-05-17 20:34:39'),
(750, 41, 'complete_service', 'Service #100 total 5800 DA', '::1', '2026-05-17 20:34:50'),
(751, 42, 'book_service', 'Service #101 tech #18', '::1', '2026-05-17 20:36:33'),
(752, 41, 'accept_service', 'Service #101', '::1', '2026-05-17 20:36:54'),
(753, 41, 'complete_service', 'Service #101 total 3300 DA', '::1', '2026-05-17 20:36:58'),
(754, 42, 'confirm_payment_partial', 'Service #101 paiment #53 — espèces — 3300 DA', '::1', '2026-05-17 20:37:09'),
(755, 42, 'book_service', 'Service #102 tech #18', '::1', '2026-05-17 20:43:04'),
(756, 41, 'accept_service', 'Service #102', '::1', '2026-05-17 20:43:42'),
(757, 41, 'complete_service', 'Service #102 total 1500 DA', '::1', '2026-05-17 20:43:47'),
(758, 42, 'confirm_payment_partial', 'Service #102 paiment #55 — espèces — 1500 DA', '::1', '2026-05-17 20:44:01'),
(759, 42, 'update_profile', '', '::1', '2026-05-17 21:11:18'),
(760, 42, 'update_profile', '', '::1', '2026-05-17 21:11:27'),
(761, 41, 'update_profile', '', '::1', '2026-05-17 21:18:39'),
(762, 41, 'update_profile', '', '::1', '2026-05-17 21:27:05'),
(763, 41, 'update_profile', '', '::1', '2026-05-17 21:29:26'),
(764, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 21:30:31'),
(765, 12, 'gen_facture', 'Service #100 montant 5800 DA (auto)', '::1', '2026-05-17 21:37:18'),
(766, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 21:52:18'),
(767, 12, 'create_admin', 'Nouvel admin créé : moha@gmail.dz (id=43)', '::1', '2026-05-17 21:54:38'),
(768, 43, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 21:55:01'),
(769, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 21:57:24'),
(770, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 22:18:23'),
(771, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 22:36:14'),
(772, 42, 'update_profile', '', '::1', '2026-05-17 22:37:38'),
(773, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 22:41:31'),
(774, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-17 22:46:15'),
(776, 12, 'reject_tech', 'Tech #19 supprimé', '::1', '2026-05-17 22:51:26'),
(777, 12, 'reject_tech', 'Tech #19', '::1', '2026-05-17 22:51:26'),
(778, 45, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 21:28:42'),
(779, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 21:29:10'),
(780, 12, 'suspend_user', 'User #35 raison: ', '::1', '2026-05-20 21:30:10'),
(781, 12, 'suspend_user', 'User #32 raison: ', '::1', '2026-05-20 21:31:03'),
(782, 12, 'suspend_user', 'User #37 raison: ', '::1', '2026-05-20 21:31:05'),
(783, 12, 'suspend_user', 'User #34 raison: ', '::1', '2026-05-20 21:31:06'),
(784, 12, 'suspend_user', 'User #36 raison: ', '::1', '2026-05-20 21:31:08'),
(785, 12, 'suspend_user', 'User #33 raison: ', '::1', '2026-05-20 21:31:09'),
(786, 12, 'approve_tech', 'Tech #20', '::1', '2026-05-20 21:50:56'),
(787, 12, 'approve_tech', 'Tech #21', '::1', '2026-05-20 21:52:24'),
(788, 12, 'approve_tech', 'Tech #31', '::1', '2026-05-20 22:18:31'),
(789, 12, 'approve_tech', 'Tech #27', '::1', '2026-05-20 22:18:36'),
(790, 12, 'approve_tech', 'Tech #28', '::1', '2026-05-20 22:18:52'),
(791, 12, 'approve_tech', 'Tech #29', '::1', '2026-05-20 22:18:57'),
(792, 12, 'approve_tech', 'Tech #30', '::1', '2026-05-20 22:19:00'),
(793, 12, 'approve_tech', 'Tech #32', '::1', '2026-05-20 22:19:04'),
(794, 12, 'approve_tech', 'Tech #33', '::1', '2026-05-20 22:19:08'),
(795, 12, 'approve_tech', 'Tech #34', '::1', '2026-05-20 22:19:12'),
(796, 12, 'approve_tech', 'Tech #35', '::1', '2026-05-20 22:19:15'),
(797, 12, 'approve_tech', 'Tech #23', '::1', '2026-05-20 22:19:19'),
(798, 12, 'approve_tech', 'Tech #25', '::1', '2026-05-20 22:19:22'),
(799, 12, 'approve_tech', 'Tech #26', '::1', '2026-05-20 22:19:26'),
(800, 12, 'approve_tech', 'Tech #24', '::1', '2026-05-20 22:19:30'),
(801, 12, 'approve_tech', 'Tech #22', '::1', '2026-05-20 22:19:34'),
(802, 47, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 22:20:16'),
(803, 47, 'add_prestation', 'Installer évier', '::1', '2026-05-20 22:23:48'),
(804, 47, 'add_prestation', 'Installer douche', '::1', '2026-05-20 22:24:02'),
(805, 60, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 22:26:52'),
(806, 60, 'add_prestation', 'Construire mur', '::1', '2026-05-20 22:40:21'),
(807, 60, 'add_prestation', 'Dalle béton', '::1', '2026-05-20 22:40:56'),
(808, 60, 'add_prestation', 'Démolition cloison', '::1', '2026-05-20 22:41:13'),
(809, 60, 'add_prestation', 'Fissure mur', '::1', '2026-05-20 22:41:30'),
(810, 60, 'add_prestation', 'Réparation fissure / lézarde', '::1', '2026-05-20 22:41:46'),
(811, 60, 'add_prestation', 'Réparer mur', '::1', '2026-05-20 22:42:15'),
(812, 60, 'add_prestation', 'Travaux rénovation', '::1', '2026-05-20 22:44:03'),
(813, 48, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 22:46:12'),
(814, 48, 'add_prestation', 'Court-circuit', '::1', '2026-05-20 22:46:46'),
(815, 48, 'add_prestation', 'Installer interrupteur', '::1', '2026-05-20 22:47:37'),
(816, 48, 'add_prestation', 'Installer lumière', '::1', '2026-05-20 22:48:41'),
(817, 48, 'add_prestation', 'Installer prise', '::1', '2026-05-20 22:48:57'),
(818, 48, 'add_prestation', 'Mise en conformité tableau', '::1', '2026-05-20 22:49:18'),
(819, 48, 'add_prestation', 'Panne courant', '::1', '2026-05-20 22:49:30'),
(820, 48, 'add_prestation', 'Remplacer câblage', '::1', '2026-05-20 22:54:54'),
(821, 48, 'add_prestation', 'Réparer circuit', '::1', '2026-05-20 22:55:35'),
(822, 48, 'add_prestation', 'Tableau électrique', '::1', '2026-05-20 22:55:53'),
(823, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 22:57:01'),
(824, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 22:57:31'),
(825, 42, 'update_profile', '', '::1', '2026-05-20 22:58:25'),
(826, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 22:58:46'),
(827, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-20 23:02:56'),
(828, 42, 'update_profile', '', '::1', '2026-05-20 23:09:20'),
(829, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 01:22:15'),
(830, 41, 'update_profile', '', '::1', '2026-05-21 01:22:51'),
(831, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 01:23:26'),
(832, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 01:56:06'),
(833, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 02:04:32'),
(834, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 13:19:38'),
(835, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 13:23:44'),
(836, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 13:25:22'),
(837, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 13:38:29'),
(838, 12, 'activate_user', 'User #35', '::1', '2026-05-21 13:38:42'),
(839, 12, 'activate_user', 'User #34', '::1', '2026-05-21 13:40:24'),
(840, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 13:41:12'),
(841, 35, 'update_location', 'lat=35.35 lng=1.34', '::1', '2026-05-21 13:41:30'),
(842, 35, 'update_location', 'lat=35.35 lng=1.34', '::1', '2026-05-21 13:41:36'),
(843, 35, 'update_location', 'lat=35.35 lng=1.34', '::1', '2026-05-21 13:41:40'),
(844, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 13:42:02'),
(845, 35, 'update_location', 'lat=35.35 lng=1.34', '::1', '2026-05-21 13:42:04'),
(846, 35, 'update_location', 'lat=35.35 lng=1.34', '::1', '2026-05-21 13:42:11'),
(847, 34, 'login', 'Connexion depuis ::1', '::1', '2026-05-21 13:42:30'),
(848, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 20:57:48'),
(849, 12, 'suspend_user', 'User #41 raison: ', '::1', '2026-05-22 20:58:17'),
(850, 12, 'activate_user', 'User #41', '::1', '2026-05-22 20:59:33'),
(851, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 20:59:48'),
(852, 12, 'suspend_user', 'User #41 raison: ', '::1', '2026-05-22 21:00:15'),
(853, 12, 'activate_user', 'User #41', '::1', '2026-05-22 21:00:37'),
(854, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:00:49'),
(855, 41, 'add_prestation', 'Tableau électrique', '::1', '2026-05-22 21:02:14'),
(856, 41, 'add_prestation', 'Court-circuit', '::1', '2026-05-22 21:02:37'),
(857, 41, 'add_prestation', 'Installer lumière', '::1', '2026-05-22 21:03:14'),
(858, 41, 'add_prestation', 'Mise en conformité tableau', '::1', '2026-05-22 21:03:37'),
(859, 41, 'add_prestation', 'Panne courant', '::1', '2026-05-22 21:03:53'),
(860, 41, 'add_prestation', 'Remplacer câblage', '::1', '2026-05-22 21:04:04'),
(861, 56, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:07:09'),
(862, 49, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:13:52'),
(863, 49, 'add_prestation', 'Installer interrupteur', '::1', '2026-05-22 21:19:55'),
(864, 49, 'add_prestation', 'Installer lumière', '::1', '2026-05-22 21:20:09'),
(865, 49, 'add_prestation', 'Installer prise', '::1', '2026-05-22 21:20:41'),
(866, 49, 'add_prestation', 'Panne courant', '::1', '2026-05-22 21:20:57'),
(867, 49, 'add_prestation', 'Réparer circuit', '::1', '2026-05-22 21:21:22'),
(868, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:21:41'),
(869, 35, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:22:06'),
(870, 35, 'update_location', 'lat=34.88 lng=-1.32', '::1', '2026-05-22 21:22:25'),
(871, 35, 'update_location', 'lat=34.88 lng=-1.32', '::1', '2026-05-22 21:22:25'),
(872, 35, 'update_location', 'lat=34.88 lng=-1.32', '::1', '2026-05-22 21:22:27'),
(873, 35, 'update_location', 'lat=34.88 lng=-1.32', '::1', '2026-05-22 21:22:30'),
(874, 35, 'update_profile', '', '::1', '2026-05-22 21:23:04'),
(875, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:24:00'),
(876, 49, 'update_profile', '', '::1', '2026-05-22 21:24:29'),
(877, 42, 'book_service', 'Service #103 tech #23', '::1', '2026-05-22 21:26:37'),
(878, 49, 'accept_service', 'Service #103', '::1', '2026-05-22 21:26:55'),
(879, 49, 'complete_service', 'Service #103 total 5500 DA', '::1', '2026-05-22 21:27:25'),
(880, 42, 'book_service', 'Service #104 tech #18', '::1', '2026-05-22 21:37:29'),
(881, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:37:41'),
(882, 41, 'accept_service', 'Service #104', '::1', '2026-05-22 21:37:46'),
(883, 41, 'complete_service', 'Service #104 total 7500 DA', '::1', '2026-05-22 21:37:55'),
(884, 42, 'confirm_payment_partial', 'Service #104 paiment #60 — espèces — 7500 DA', '::1', '2026-05-22 21:42:03'),
(885, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:43:45'),
(886, 49, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:44:22'),
(887, 42, 'confirm_payment_partial', 'Service #103 paiment #59 — espèces — 5500 DA', '::1', '2026-05-22 21:44:44'),
(888, 42, 'book_service', 'Service #105 tech #23', '::1', '2026-05-22 21:45:37'),
(889, 42, 'cancel_service', 'Service #105', '::1', '2026-05-22 21:45:47'),
(890, 42, 'book_service', 'Service #106 tech #23', '::1', '2026-05-22 21:46:37'),
(891, 49, 'accept_service', 'Service #106', '::1', '2026-05-22 21:46:45'),
(892, 49, 'complete_service', 'Service #106 total 1500 DA', '::1', '2026-05-22 21:46:48'),
(893, 42, 'confirm_payment_partial', 'Service #106 paiment #61 — espèces — 1500 DA', '::1', '2026-05-22 21:47:05'),
(894, 42, 'rate_tech', 'Tech #23 note 5', '::1', '2026-05-22 21:47:25'),
(895, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 21:49:04'),
(896, 12, 'delete_user', 'User #31', '::1', '2026-05-22 22:00:07'),
(897, 12, 'delete_user', 'User #38', '::1', '2026-05-22 22:00:11'),
(898, 12, 'delete_user', 'User #39', '::1', '2026-05-22 22:00:15'),
(899, 12, 'delete_user', 'User #40', '::1', '2026-05-22 22:00:18'),
(900, 42, 'book_service', 'Service #107 tech #23', '::1', '2026-05-22 22:07:01'),
(901, 49, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 22:07:23'),
(902, 49, 'accept_service', 'Service #107', '::1', '2026-05-22 22:07:31'),
(903, 49, 'complete_service', 'Service #107 total 6500 DA', '::1', '2026-05-22 22:07:36'),
(904, 42, 'confirm_payment_partial', 'Service #107 paiment #62 — espèces — 6500 DA', '::1', '2026-05-22 22:07:44'),
(905, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-22 22:42:44'),
(906, 42, 'book_service', 'Service #108 tech #23', '::1', '2026-05-22 22:46:38'),
(907, 49, 'accept_service', 'Service #108', '::1', '2026-05-22 22:48:13'),
(908, 49, 'complete_service', 'Service #108 total 6500 DA', '::1', '2026-05-22 22:49:05'),
(909, 42, 'confirm_payment_partial', 'Service #108 paiment #63 — espèces — 6500 DA', '::1', '2026-05-22 22:50:44'),
(910, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 01:50:23'),
(911, 41, 'save_dispo', '', '::1', '2026-05-23 01:54:43'),
(912, 41, 'save_dispo', '', '::1', '2026-05-23 01:54:51'),
(913, 41, 'save_dispo', '', '::1', '2026-05-23 01:55:02'),
(914, 41, 'save_dispo', '', '::1', '2026-05-23 01:57:07'),
(915, 42, 'book_service', 'Service #109 tech #18', '::1', '2026-05-23 01:57:59'),
(916, 41, 'refuse_service', 'Service #109', '::1', '2026-05-23 01:58:22'),
(917, 41, 'save_dispo', '', '::1', '2026-05-23 02:04:46'),
(918, 41, 'save_dispo', '', '::1', '2026-05-23 02:05:25'),
(919, 42, 'book_service', 'Service #110 tech #18', '::1', '2026-05-23 02:05:39'),
(920, 49, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 02:09:13'),
(921, 49, 'save_dispo', '', '::1', '2026-05-23 02:09:29'),
(922, 42, 'book_service', 'Service #111 tech #23', '::1', '2026-05-23 02:10:18'),
(923, 49, 'refuse_service', 'Service #111', '::1', '2026-05-23 02:13:21'),
(924, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 02:18:34'),
(925, 41, 'refuse_service', 'Service #110', '::1', '2026-05-23 02:18:38'),
(926, 41, 'save_dispo', '', '::1', '2026-05-23 02:19:10'),
(927, 41, 'save_dispo', '', '::1', '2026-05-23 02:19:24'),
(928, 42, 'book_service', 'Service #112 tech #18', '::1', '2026-05-23 02:20:58'),
(929, 41, 'accept_service', 'Service #112', '::1', '2026-05-23 02:21:07'),
(930, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 02:22:42'),
(931, 45, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 02:23:17'),
(932, 45, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 02:23:54'),
(933, 41, 'refuse_service', 'Service #112', '::1', '2026-05-23 02:25:27'),
(934, 45, 'book_service', 'Service #113 tech #18', '::1', '2026-05-23 02:28:26'),
(935, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 02:28:57'),
(936, 41, 'accept_service', 'Service #113', '::1', '2026-05-23 02:29:14'),
(937, 41, 'refuse_service', 'Service #113', '::1', '2026-05-23 02:29:57'),
(938, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 16:25:46'),
(939, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 16:53:22'),
(940, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 16:57:54'),
(941, 41, 'login', 'Connexion depuis 192.168.1.4', '192.168.1.4', '2026-05-23 17:04:16'),
(942, 49, 'login', 'Connexion depuis 192.168.1.4', '192.168.1.4', '2026-05-23 17:11:38'),
(943, 49, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 17:33:07'),
(944, 49, 'save_dispo', '', '::1', '2026-05-23 17:33:44'),
(945, 48, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 17:34:21'),
(946, 48, 'save_dispo', '', '::1', '2026-05-23 17:35:00'),
(947, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 17:35:47'),
(948, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:47:00'),
(949, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:49:52'),
(950, 41, 'save_dispo', '', '::1', '2026-05-23 20:50:20'),
(951, 49, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:50:43'),
(952, 49, 'save_dispo', '', '::1', '2026-05-23 20:51:13'),
(953, 48, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:51:40'),
(954, 48, 'save_dispo', '', '::1', '2026-05-23 20:52:05'),
(955, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:52:19'),
(956, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:53:49'),
(957, 41, 'save_dispo', '', '::1', '2026-05-23 20:54:15'),
(958, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:54:37'),
(959, 49, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:55:06'),
(960, 49, 'save_dispo', '', '::1', '2026-05-23 20:55:28'),
(961, 48, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:55:45'),
(962, 48, 'save_dispo', '', '::1', '2026-05-23 20:56:07'),
(963, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:56:18'),
(964, 42, 'book_service', 'Service #114 tech #18', '::1', '2026-05-23 20:59:24'),
(965, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 20:59:52'),
(966, 41, 'accept_service', 'Service #114', '::1', '2026-05-23 21:01:59'),
(967, 41, 'complete_service', 'Service #114 total 6500 DA', '::1', '2026-05-23 21:03:20'),
(968, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 21:04:53'),
(969, 42, 'confirm_payment_partial', 'Service #114 paiment #64 — espèces — 6500 DA', '::1', '2026-05-23 21:07:59'),
(970, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 21:09:01'),
(971, 12, 'login', 'Connexion depuis ::1', '::1', '2026-05-23 21:10:30'),
(972, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-24 00:18:54'),
(973, 41, 'login', 'Connexion depuis ::1', '::1', '2026-05-24 00:19:40'),
(974, 42, 'login', 'Connexion depuis ::1', '::1', '2026-05-24 00:24:36'),
(975, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 19:10:00'),
(976, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 19:12:34'),
(977, 42, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 19:13:49'),
(978, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 19:14:18'),
(979, 41, 'save_dispo', '', '::1', '2026-06-05 19:14:35'),
(980, 42, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 19:14:52'),
(981, 42, 'book_service', 'Service #115 tech #18', '::1', '2026-06-05 19:15:03'),
(982, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 19:16:52'),
(983, 41, 'accept_service', 'Service #115', '::1', '2026-06-05 19:17:03'),
(984, 41, 'complete_service', 'Service #115 total 5000 DA', '::1', '2026-06-05 19:17:13'),
(985, 42, 'confirm_payment_partial', 'Service #115 paiment #65 — espèces — 5000 DA', '::1', '2026-06-05 19:17:29'),
(986, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 19:35:39'),
(987, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 19:50:20'),
(988, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 20:13:17'),
(989, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 20:13:44'),
(990, 12, 'commission_payee', 'Commission #20 marked paid via AJAX', '::1', '2026-06-05 20:14:10'),
(991, 12, 'commission_month_reminder', 'Tech #18 - 2026-05 - 6690.3999633789 DA', '::1', '2026-06-05 21:03:41'),
(992, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:04:06'),
(993, 49, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:07:01'),
(994, 12, 'commission_month_settled', 'Tech #23 - 2026-05 - 2000 DA', '::1', '2026-06-05 21:07:16'),
(995, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:10:55'),
(996, 12, 'reject_tech', 'Tech #3 supprimé', '::1', '2026-06-05 21:16:11'),
(997, 12, 'reject_tech', 'Tech #3', '::1', '2026-06-05 21:16:11'),
(998, 12, 'reject_tech', 'Tech #1 supprimé', '::1', '2026-06-05 21:16:15'),
(999, 12, 'reject_tech', 'Tech #1', '::1', '2026-06-05 21:16:15'),
(1000, 12, 'reject_tech', 'Tech #5 supprimé', '::1', '2026-06-05 21:16:28'),
(1001, 12, 'reject_tech', 'Tech #5', '::1', '2026-06-05 21:16:29'),
(1002, 12, 'reject_tech', 'Tech #6 supprimé', '::1', '2026-06-05 21:16:34'),
(1003, 12, 'reject_tech', 'Tech #6', '::1', '2026-06-05 21:16:34'),
(1004, 12, 'commission_month_settled', 'Tech #18 - 2026-05 - 6690.3999633789 DA', '::1', '2026-06-05 21:22:02'),
(1005, 42, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:38:01'),
(1006, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:38:33'),
(1007, 42, 'book_service', 'Service #116 tech #18', '::1', '2026-06-05 21:38:53'),
(1008, 41, 'accept_service', 'Service #116', '::1', '2026-06-05 21:39:22'),
(1009, 41, 'complete_service', 'Service #116 total 7500 DA', '::1', '2026-06-05 21:39:27'),
(1010, 42, 'confirm_payment_partial', 'Service #116 paiment #66 — espèces — 7500 DA', '::1', '2026-06-05 21:39:36'),
(1011, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:40:40'),
(1012, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:45:08'),
(1013, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:46:36'),
(1014, 42, 'login', 'Connexion depuis ::1', '::1', '2026-06-05 21:47:28'),
(1015, 42, 'book_service', 'Service #117 tech #18', '::1', '2026-06-05 21:48:00'),
(1016, 41, 'accept_service', 'Service #117', '::1', '2026-06-05 21:48:12'),
(1017, 41, 'complete_service', 'Service #117 total 6000 DA', '::1', '2026-06-05 21:48:23'),
(1018, 42, 'confirm_payment_partial', 'Service #117 paiment #67 — espèces — 6000 DA', '::1', '2026-06-05 21:48:38'),
(1019, 42, 'book_service', 'Service #118 tech #18', '::1', '2026-06-05 23:02:41'),
(1020, 41, 'accept_service', 'Service #118', '::1', '2026-06-05 23:02:50'),
(1021, 41, 'complete_service', 'Service #118 total 7000 DA', '::1', '2026-06-05 23:02:53'),
(1022, 42, 'confirm_payment_partial', 'Service #118 paiment #68 — espèces — 7000 DA', '::1', '2026-06-05 23:03:06'),
(1023, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 15:56:13'),
(1024, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 16:01:12'),
(1025, 12, 'commission_month_settled', 'Tech #18 - 2026-05 - 5352.3200073242 DA', '::1', '2026-06-06 16:01:41'),
(1026, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 16:02:33'),
(1027, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 16:05:00'),
(1028, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 16:10:26'),
(1029, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 16:11:23'),
(1030, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 16:12:09'),
(1031, 12, 'commission_month_settled', 'Tech #23 - 2026-05 - 1600 DA', '::1', '2026-06-06 16:16:51'),
(1032, 12, 'commission_month_settled', 'Tech #18 - 2026-06 - 2040 DA', '::1', '2026-06-06 16:16:54'),
(1033, 12, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 16:26:04'),
(1034, 41, 'login', 'Connexion depuis ::1', '::1', '2026-06-06 16:55:03');

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE `message` (
  `id_message` int(11) NOT NULL,
  `id_expediteur` int(20) DEFAULT NULL,
  `id_destinataire` int(20) DEFAULT NULL,
  `contenu` text DEFAULT NULL,
  `data_envoi` datetime DEFAULT current_timestamp(),
  `lu` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message`
--

INSERT INTO `message` (`id_message`, `id_expediteur`, `id_destinataire`, `contenu`, `data_envoi`, `lu`) VALUES
(1, 49, 42, 'Heyy', '2026-05-23 17:16:24', 1),
(2, 41, 42, 'salam alaikom', '2026-05-23 21:01:36', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `id_notif` int(20) NOT NULL,
  `id_user` int(20) NOT NULL,
  `titre` varchar(200) NOT NULL,
  `contenu` text NOT NULL,
  `lien` varchar(200) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `date_notif` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`id_notif`, `id_user`, `titre`, `contenu`, `lien`, `lu`, `date_notif`) VALUES
(67, 12, 'Nouveau signalement', 'Un utilisateur a été signalé (ID:18) — Motif: paiement', '../dashboard-admin.php?tab=signalements', 0, '2026-03-09 01:31:34'),
(92, 12, 'Nouveau signalement', 'Un utilisateur a été signalé (ID:25) — Motif: qualite', '../dashboard-admin.php?tab=signalements', 0, '2026-03-11 01:20:17'),
(177, 32, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 1, '2026-03-28 15:48:41'),
(178, 32, 'Nouvelle réservation', 'Un client a réservé votre service pour le 29/03/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-03-28 15:51:47'),
(181, 12, 'Nouveau signalement', 'Un utilisateur a été signalé (ID:31) — Motif: paiement', '../dashboard-admin.php?tab=signalements', 0, '2026-03-28 16:02:47'),
(182, 27, 'Nouveau signalement', 'Un utilisateur a été signalé (ID:31) — Motif: paiement', '../dashboard-admin.php?tab=signalements', 0, '2026-03-28 16:02:47'),
(185, 12, 'Nouveau litige #2', 'Un client a ouvert un litige pour le service #26', '../dashboard-admin.php?tab=litiges', 0, '2026-03-28 16:07:21'),
(186, 27, 'Nouveau litige #2', 'Un client a ouvert un litige pour le service #26', '../dashboard-admin.php?tab=litiges', 0, '2026-03-28 16:07:21'),
(194, 32, 'Nouvelle réservation', 'Un client a réservé votre service pour le 07/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-06 22:14:20'),
(197, 32, 'Nouvelle réservation', 'Un client a réservé votre service pour le 08/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-07 23:54:45'),
(200, 32, 'Nouvelle réservation', 'Un client a réservé votre service pour le 09/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-08 11:02:02'),
(203, 32, 'Nouvelle réservation', 'Un client a réservé votre service pour le 09/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-08 11:12:27'),
(206, 32, '💰 Paiement reçu', 'Le client a confirmé le paiement de 2,000 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-08 11:13:13'),
(207, 32, 'Nouvelle réservation', 'Un client a réservé votre service pour le 09/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-08 11:21:34'),
(210, 32, '💰 Paiement reçu', 'Le client a confirmé le paiement de 1,500 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-08 11:22:21'),
(211, 32, 'Nouvelle réservation', 'Un client a réservé votre service pour le 09/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-08 11:24:34'),
(214, 32, '💰 Paiement reçu', 'Le client a confirmé le paiement de 1,000 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-08 11:25:12'),
(215, 33, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-04-09 17:08:50'),
(216, 34, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-04-09 17:18:14'),
(217, 35, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-04-09 17:19:25'),
(218, 36, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 1, '2026-04-09 17:22:45'),
(219, 37, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-04-09 17:24:10'),
(220, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 11/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-10 20:30:30'),
(223, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 11/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-10 20:32:49'),
(226, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 11/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-10 20:44:01'),
(229, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 11/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-10 20:51:32'),
(232, 36, '💰 Paiement reçu', 'Le client a confirmé le paiement de 2,500 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-04-10 20:52:40'),
(233, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 14/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-13 23:35:59'),
(237, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 14/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-13 23:39:07'),
(240, 36, '💰 Paiement reçu', 'Le client a confirmé le paiement de 1,000 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-04-13 23:39:56'),
(241, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 14/04/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-04-13 23:47:30'),
(245, 36, '💰 Paiement reçu', 'Le client a confirmé le paiement de 2,000 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-04-13 23:49:11'),
(246, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 21/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:03:41'),
(249, 36, '💰 Paiement reçu', 'Le client a confirmé le paiement de 1,500 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:09:49'),
(250, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 21/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:10:34'),
(253, 36, '💰 Paiement reçu', 'Le client a confirmé le paiement de 2,000 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:11:10'),
(254, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 21/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:15:46'),
(255, 36, 'Réservation annulée', 'Le client a annulé la réservation #60.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:15:52'),
(256, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 21/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:17:08'),
(257, 36, 'Réservation annulée', 'Le client a annulé la réservation #61.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:17:16'),
(258, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 21/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:18:49'),
(259, 36, 'Réservation annulée', 'Le client a annulé la réservation #62.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:18:54'),
(260, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 21/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:19:53'),
(261, 36, 'Réservation annulée', 'Le client a annulé la réservation #63.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:19:58'),
(262, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 21/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:30:37'),
(263, 36, 'Réservation annulée', 'Le client a annulé la réservation #64.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:30:44'),
(264, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 21/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-20 18:37:59'),
(266, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 28/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 16:31:49'),
(267, 36, 'Réservation annulée', 'Le client a annulé la réservation #66.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 16:32:07'),
(268, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 28/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:03:14'),
(270, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 28/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:04:14'),
(271, 36, 'Réservation annulée', 'Le client a annulé la réservation #68.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:04:19'),
(272, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 28/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:20:43'),
(273, 36, 'Réservation annulée', 'Le client a annulé la réservation #69.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:20:50'),
(274, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 28/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:21:14'),
(276, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 28/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:26:16'),
(278, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 28/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:27:32'),
(279, 35, 'Réservation annulée', 'Le client a annulé la réservation #72.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:27:39'),
(280, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 28/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:31:10'),
(281, 35, 'Réservation annulée', 'Le client a annulé la réservation #73.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-27 17:31:22'),
(282, 36, 'Nouvelle réservation', 'Un client a réservé votre service pour le 30/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 16:36:06'),
(283, 36, 'Réservation annulée', 'Le client a annulé la réservation #74.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 16:36:12'),
(284, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 30/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 16:36:49'),
(288, 35, '💰 Paiement reçu', 'Le client a confirmé le paiement de 2,000 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 16:39:20'),
(289, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 30/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 16:45:49'),
(292, 35, '💰 Paiement reçu', 'Le client a confirmé le paiement de 4,500 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 16:47:38'),
(293, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 30/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 17:01:14'),
(297, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 30/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 17:03:59'),
(300, 35, '💰 Paiement reçu', 'Le client a confirmé le paiement de 2,000 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 17:06:13'),
(301, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 30/04/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 17:13:46'),
(304, 35, '💰 Paiement reçu', 'Le client a confirmé le paiement de 1,500 DA.', 'dashboard-tech.php?tab=demandes', 0, '2026-04-29 17:24:01'),
(305, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 02/05/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-05-01 21:32:12'),
(307, 35, 'Réservation annulée', 'Le client a annulé la réservation #80.', 'dashboard-tech.php?tab=demandes', 0, '2026-05-01 23:38:27'),
(308, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 02/05/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-05-01 23:39:57'),
(309, 35, 'Réservation annulée', 'Le client a annulé la réservation #81.', 'dashboard-tech.php?tab=demandes', 0, '2026-05-01 23:40:19'),
(310, 41, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 1, '2026-05-02 18:15:05'),
(311, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 03/05/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-05-02 22:13:31'),
(313, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 03/05/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-05-02 22:48:21'),
(314, 35, 'Réservation annulée', 'Le client a annulé la réservation #83.', 'dashboard-tech.php?tab=demandes', 0, '2026-05-02 22:48:32'),
(315, 35, 'Nouvelle réservation', 'Un client a réservé votre service pour le 03/05/2026', 'dashboard-tech.php?tab=demandes', 0, '2026-05-02 23:10:34'),
(316, 35, 'Réservation annulée', 'Le client a annulé la réservation #84.', 'dashboard-tech.php?tab=demandes', 0, '2026-05-02 23:10:37'),
(317, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 04/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-03 00:20:06'),
(318, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 04/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-03 00:20:40'),
(319, 42, 'Service annulé', 'Le technicien a annulé votre service #86.', 'dashboard-client.php?tab=reservations', 1, '2026-05-03 00:20:47'),
(320, 42, 'Service annulé', 'Le technicien a annulé votre service #85.', 'dashboard-client.php?tab=reservations', 1, '2026-05-03 00:21:16'),
(321, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 04/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-04 22:43:33'),
(322, 41, 'Réservation annulée', 'Le client a annulé la réservation #87.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-04 22:43:40'),
(323, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 06/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 20:43:42'),
(324, 42, 'Réservation acceptée ✓', 'Votre demande #88 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 20:43:55'),
(325, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 2,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 20:44:02'),
(326, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 2,500 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 20:44:33'),
(327, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 06/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 20:47:36'),
(328, 42, 'Réservation acceptée ✓', 'Votre demande #89 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 20:47:46'),
(329, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 800 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 20:47:54'),
(330, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 800 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 20:48:07'),
(331, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 06/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:00:31'),
(332, 42, 'Réservation acceptée ✓', 'Votre demande #90 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 21:00:45'),
(333, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 2,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 21:00:50'),
(334, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 2,500 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:01:07'),
(335, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 06/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:12:06'),
(336, 42, 'Réservation acceptée ✓', 'Votre demande #91 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 21:12:20'),
(337, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 5,300 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 21:13:03'),
(338, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 6,307 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:14:08'),
(339, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 06/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:28:07'),
(340, 41, 'Réservation annulée', 'Le client a annulé la réservation #92.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:28:23'),
(341, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 06/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:28:55'),
(342, 42, 'Réservation acceptée ✓', 'Votre demande #93 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 21:29:12'),
(343, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 5,000 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 21:29:29'),
(344, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 5,950 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:29:52'),
(345, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 06/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:42:33'),
(346, 42, 'Réservation acceptée ✓', 'Votre demande #94 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 21:42:49'),
(347, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 6,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-05 21:43:09'),
(348, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 7,735 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:43:39'),
(349, 42, 'Montant du service mis à jour', 'Le montant du service #94 a été mis à jour à 9,520 DA. Merci de confirmer le paiement. Une nouvelle facture a été générée.', 'paiment.php?id_service=94', 1, '2026-05-05 21:43:56'),
(350, 41, 'Montant mis à jour', 'Nouveau montant calculé: 9,520 DA avec une nouvelle facture.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:43:56'),
(351, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 9,520 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-05 21:44:20'),
(352, 42, 'Montant du service mis à jour', 'Le montant du service #94 a été mis à jour à 9,520 DA. Merci de confirmer le paiement. Une nouvelle facture a été générée.', 'paiment.php?id_service=94', 1, '2026-05-17 19:11:22'),
(353, 41, 'Montant mis à jour', 'Nouveau montant calculé: 9,520 DA avec une nouvelle facture.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:11:22'),
(354, 42, 'Montant du service mis à jour', 'Le montant du service #94 a été mis à jour à 3,000 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=94', 1, '2026-05-17 19:12:15'),
(355, 41, 'Montant mis à jour', 'Nouveau montant calculé: 3,000 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:12:15'),
(356, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 3,000 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:13:35'),
(357, 42, 'Montant du service mis à jour', 'Le montant du service #94 a été mis à jour à 13,447 DA. Merci de confirmer le paiement. Une nouvelle facture a été générée.', 'paiment.php?id_service=94', 1, '2026-05-17 19:15:28'),
(358, 41, 'Montant mis à jour', 'Nouveau montant calculé: 13,447 DA avec une nouvelle facture.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:15:28'),
(359, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 13,447 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:15:58'),
(360, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 18/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:40:25'),
(361, 42, 'Réservation acceptée ✓', 'Votre demande #95 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 19:41:39'),
(362, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 2,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 19:41:49'),
(363, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 2,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:42:21'),
(364, 42, 'Montant du service mis à jour', 'Le montant du service #95 a été mis à jour à 0 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=95', 1, '2026-05-17 19:44:41'),
(365, 41, 'Montant mis à jour', 'Nouveau montant calculé: 0 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:44:41'),
(366, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 18/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:51:59'),
(367, 42, 'Réservation acceptée ✓', 'Votre demande #96 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 19:52:06'),
(368, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 1,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 19:52:13'),
(369, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 1,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:52:41'),
(370, 42, 'Montant du service mis à jour', 'Le montant du service #96 a été mis à jour à 0 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=96', 1, '2026-05-17 19:53:07'),
(371, 41, 'Montant mis à jour', 'Nouveau montant calculé: 0 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:53:07'),
(372, 42, 'Montant du service mis à jour', 'Le montant du service #96 a été mis à jour à 0 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=96', 1, '2026-05-17 19:53:37'),
(373, 41, 'Montant mis à jour', 'Nouveau montant calculé: 0 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 19:53:37'),
(374, 42, 'Montant du service mis à jour', 'Le montant du service #96 a été mis à jour à 0 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=96', 1, '2026-05-17 20:15:13'),
(375, 41, 'Montant mis à jour', 'Nouveau montant calculé: 0 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:15:13'),
(376, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 18/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:16:33'),
(377, 42, 'Réservation acceptée ✓', 'Votre demande #97 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:16:57'),
(378, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 6,000 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:17:02'),
(379, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 6,000 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:17:25'),
(380, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 18/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:19:35'),
(381, 42, 'Réservation acceptée ✓', 'Votre demande #98 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:19:58'),
(382, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 5,800 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:20:12'),
(383, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 5,800 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:20:35'),
(384, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 18/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:26:32'),
(385, 42, 'Réservation acceptée ✓', 'Votre demande #99 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:26:43'),
(386, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 800 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:26:50'),
(387, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 800 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:29:04'),
(388, 42, 'Montant du service mis à jour', 'Le montant du service #99 a été mis à jour à 0 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=99', 1, '2026-05-17 20:29:25'),
(389, 41, 'Montant mis à jour', 'Nouveau montant calculé: 0 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:29:26'),
(390, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 18/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:34:26'),
(391, 42, 'Réservation acceptée ✓', 'Votre demande #100 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:34:37'),
(392, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 5,800 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:34:50'),
(393, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 18/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:36:31'),
(394, 42, 'Réservation acceptée ✓', 'Votre demande #101 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:36:49'),
(395, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 3,300 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:36:58'),
(396, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 3,300 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:37:09'),
(397, 42, 'Montant du service mis à jour', 'Le montant du service #101 a été mis à jour à 0 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=101', 1, '2026-05-17 20:37:26'),
(398, 41, 'Montant mis à jour', 'Nouveau montant calculé: 0 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:37:26'),
(399, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 18/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:42:33'),
(400, 42, 'Réservation acceptée ✓', 'Votre demande #102 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:43:18'),
(401, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 1,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-17 20:43:47'),
(402, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 1,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:44:01'),
(403, 42, 'تحديث مبلغ الخدمة', 'تم تحديث قيمة الخدمة #102 إلى 0 دج. يرجى تأكيد الدفع.', 'paiment.php?id_service=102', 1, '2026-05-17 20:48:55'),
(404, 41, 'Montant mis à jour', 'Nouveau montant calculé: 0 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-17 20:48:55'),
(405, 35, 'Compte suspendu', 'Votre compte a été suspendu.', '', 0, '2026-05-20 21:30:10'),
(406, 32, 'Compte suspendu', 'Votre compte a été suspendu.', '', 0, '2026-05-20 21:31:03'),
(407, 37, 'Compte suspendu', 'Votre compte a été suspendu.', '', 0, '2026-05-20 21:31:05'),
(408, 34, 'Compte suspendu', 'Votre compte a été suspendu.', '', 0, '2026-05-20 21:31:06'),
(409, 36, 'Compte suspendu', 'Votre compte a été suspendu.', '', 0, '2026-05-20 21:31:08'),
(410, 33, 'Compte suspendu', 'Votre compte a été suspendu.', '', 0, '2026-05-20 21:31:09'),
(411, 46, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 21:50:53'),
(412, 47, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 1, '2026-05-20 21:52:21'),
(413, 57, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:18:29'),
(414, 53, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:18:33'),
(415, 54, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:18:49'),
(416, 55, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:18:54'),
(417, 56, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:18:58'),
(418, 58, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:19:01'),
(419, 59, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:19:05'),
(420, 60, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:19:09'),
(421, 61, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:19:13'),
(422, 49, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 1, '2026-05-20 22:19:16'),
(423, 51, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:19:20'),
(424, 52, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:19:23'),
(425, 50, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 0, '2026-05-20 22:19:27'),
(426, 48, 'Profil approuvé ✓', 'Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.', 'dashboard-tech.php', 1, '2026-05-20 22:19:31'),
(427, 42, 'Montant du service mis à jour', 'Le montant du service #102 a été mis à jour à 0 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=102', 1, '2026-05-21 13:20:08'),
(428, 41, 'Montant mis à jour', 'Nouveau montant calculé: 0 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-21 13:20:08'),
(429, 42, 'Montant du service mis à jour', 'Le montant du service #102 a été mis à jour à 11,020 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=102', 1, '2026-05-21 13:35:27'),
(430, 41, 'Montant mis à jour', 'Nouveau montant calculé: 11,020 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-21 13:35:27'),
(431, 35, 'Compte réactivé ✓', 'Votre compte est de nouveau actif.', '', 0, '2026-05-21 13:38:42'),
(432, 34, 'Compte réactivé ✓', 'Votre compte est de nouveau actif.', '', 0, '2026-05-21 13:40:24'),
(433, 41, 'Compte suspendu', 'Votre compte a été suspendu.', '', 1, '2026-05-22 20:58:17'),
(434, 41, 'Compte réactivé ✓', 'Votre compte est de nouveau actif.', '', 1, '2026-05-22 20:59:33'),
(435, 41, 'Compte suspendu', 'Votre compte a été suspendu.', '', 1, '2026-05-22 21:00:15'),
(436, 41, 'Compte réactivé ✓', 'Votre compte est de nouveau actif.', '', 1, '2026-05-22 21:00:37'),
(437, 49, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 21:26:33'),
(438, 42, 'Réservation acceptée ✓', 'Votre demande #103 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 21:26:46'),
(439, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 5,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 21:27:25'),
(440, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 21:37:26'),
(441, 42, 'Réservation acceptée ✓', 'Votre demande #104 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 21:37:44'),
(442, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 7,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 21:37:55'),
(443, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 7,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 21:42:03'),
(444, 49, '💰 تم استلام الدفع', 'تأكيد العميل دفع 5,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 21:44:44'),
(445, 49, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 21:45:34'),
(446, 49, 'Réservation annulée', 'Le client a annulé la réservation #105.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 21:45:47'),
(447, 49, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 21:46:28'),
(448, 42, 'Réservation acceptée ✓', 'Votre demande #106 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 21:46:43'),
(449, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 1,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 21:46:48'),
(450, 49, '💰 تم استلام الدفع', 'تأكيد العميل دفع 1,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 21:47:05'),
(451, 49, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 22:06:58'),
(452, 42, 'Réservation acceptée ✓', 'Votre demande #107 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 22:07:27'),
(453, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 6,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 22:07:36'),
(454, 49, '💰 تم استلام الدفع', 'تأكيد العميل دفع 6,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 22:07:44'),
(455, 49, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 22:46:38'),
(456, 42, 'Réservation acceptée ✓', 'Votre demande #108 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 22:48:09'),
(457, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 6,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-22 22:49:05'),
(458, 49, '💰 تم استلام الدفع', 'تأكيد العميل دفع 6,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-22 22:50:44'),
(459, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 24/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-23 01:57:56'),
(460, 42, 'Service annulé', 'Le technicien a annulé votre service #109.', 'dashboard-client.php?tab=reservations', 1, '2026-05-23 01:58:22'),
(461, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 24/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-23 02:05:37'),
(462, 49, 'Nouvelle réservation', 'Un client a réservé votre service pour le 25/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-23 02:10:14'),
(463, 42, 'Service annulé', 'Le technicien a annulé votre service #111.', 'dashboard-client.php?tab=reservations', 1, '2026-05-23 02:13:21'),
(464, 42, 'Service annulé', 'Le technicien a annulé votre service #110.', 'dashboard-client.php?tab=reservations', 1, '2026-05-23 02:18:38'),
(465, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-23 02:20:55'),
(466, 42, 'Réservation acceptée ✓', 'Votre demande #112 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-23 02:21:04'),
(467, 42, 'Service annulé', 'Le technicien a annulé votre service #112.', 'dashboard-client.php?tab=reservations', 1, '2026-05-23 02:25:27'),
(468, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-23 02:28:22'),
(469, 45, 'Réservation acceptée ✓', 'Votre demande #113 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 0, '2026-05-23 02:29:11'),
(470, 45, 'Service annulé', 'Le technicien a annulé votre service #113.', 'dashboard-client.php?tab=reservations', 0, '2026-05-23 02:29:57'),
(471, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 23/05/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-05-23 20:59:21'),
(472, 42, 'Réservation acceptée ✓', 'Votre demande #114 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 1, '2026-05-23 21:01:57'),
(473, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 6,500 DA disponible.', 'dashboard-client.php?tab=reservations', 1, '2026-05-23 21:03:20'),
(474, 42, 'Montant du service mis à jour', 'Le montant du service #114 a été mis à jour à 6,500 DA. Merci de confirmer le paiement.', 'paiment.php?id_service=114', 1, '2026-05-23 21:04:31'),
(475, 41, 'Montant mis à jour', 'Nouveau montant calculé: 6,500 DA', 'dashboard-tech.php?tab=demandes', 1, '2026-05-23 21:04:31'),
(476, 41, '💰 تم استلام الدفع', 'تأكيد العميل دفع 6,500 دج.', 'dashboard-tech.php?tab=demandes', 1, '2026-05-23 21:07:59'),
(477, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 05/06/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-06-05 19:15:00'),
(478, 42, 'Réservation acceptée ✓', 'Votre demande #115 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 0, '2026-06-05 19:16:57'),
(479, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 5,000 DA disponible.', 'dashboard-client.php?tab=reservations', 0, '2026-06-05 19:17:13'),
(480, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 5,000 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-06-05 19:17:29'),
(481, 41, 'Rappel commission 05/2026', 'Votre commission de 6 690 DA pour 05/2026 est en attente. 15 intervention(s) dans le mois.', 'dashboard-admin.php?tab=commissions', 1, '2026-06-05 21:03:30'),
(482, 49, 'Commission 05/2026 réglée', 'Votre commission mensuelle de 2 000 DA pour 05/2026 a été enregistrée comme payée.', 'dashboard-admin.php?tab=commissions', 1, '2026-06-05 21:07:16'),
(483, 41, 'Commission 05/2026 réglée', 'Votre commission mensuelle de 6 690 DA pour 05/2026 a été enregistrée comme payée.', 'dashboard-admin.php?tab=commissions', 1, '2026-06-05 21:22:02'),
(484, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 05/06/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-06-05 21:38:51'),
(485, 42, 'Réservation acceptée ✓', 'Votre demande #116 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 0, '2026-06-05 21:39:04'),
(486, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 7,500 DA disponible.', 'dashboard-client.php?tab=reservations', 0, '2026-06-05 21:39:27'),
(487, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 7,500 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-06-05 21:39:36'),
(488, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 05/06/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-06-05 21:47:48'),
(489, 42, 'Réservation acceptée ✓', 'Votre demande #117 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 0, '2026-06-05 21:48:09'),
(490, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 6,000 DA disponible.', 'dashboard-client.php?tab=reservations', 0, '2026-06-05 21:48:23'),
(491, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 6,000 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-06-05 21:48:38'),
(492, 41, 'Nouvelle réservation', 'Un client a réservé votre service pour le 06/06/2026', 'dashboard-tech.php?tab=demandes', 1, '2026-06-05 23:02:37'),
(493, 42, 'Réservation acceptée ✓', 'Votre demande #118 a été acceptée par le technicien.', 'dashboard-client.php?tab=reservations', 0, '2026-06-05 23:02:48'),
(494, 42, 'Service terminé ✓', 'Votre service est terminé. Facture de 7,000 DA disponible.', 'dashboard-client.php?tab=reservations', 0, '2026-06-05 23:02:53'),
(495, 41, '💰 Paiement reçu', 'Le client a confirmé le paiement de 7,000 DA.', 'dashboard-tech.php?tab=demandes', 1, '2026-06-05 23:03:06'),
(496, 41, 'Commission 05/2026 réglée', 'Votre commission mensuelle de 5 352 DA pour 05/2026 a été enregistrée comme payée.', 'dashboard-admin.php?tab=commissions', 0, '2026-06-06 16:01:41'),
(497, 49, 'Commission 05/2026 réglée', 'Votre commission mensuelle de 1 600 DA pour 05/2026 a été enregistrée comme payée.', 'dashboard-admin.php?tab=commissions', 0, '2026-06-06 16:16:51'),
(498, 41, 'Commission 06/2026 réglée', 'Votre commission mensuelle de 2 040 DA pour 06/2026 a été enregistrée comme payée.', 'dashboard-admin.php?tab=commissions', 0, '2026-06-06 16:16:54');

-- --------------------------------------------------------

--
-- Table structure for table `paiment`
--

CREATE TABLE `paiment` (
  `id_paiment` int(20) NOT NULL,
  `id_service` int(20) DEFAULT NULL,
  `mode_paiment` varchar(20) DEFAULT NULL,
  `montant` float DEFAULT NULL,
  `date_paiment` date DEFAULT NULL,
  `statut_paiment` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `paiment`
--

INSERT INTO `paiment` (`id_paiment`, `id_service`, `mode_paiment`, `montant`, `date_paiment`, `statut_paiment`) VALUES
(1, 12, 'espèces', 1600, '2026-03-08', 'payé'),
(2, 13, 'espèces', 1800, '2026-03-08', 'payé'),
(3, 14, 'espèces', 100, '2026-03-08', 'payé'),
(4, 16, 'espèces', 800, '2026-03-08', 'payé'),
(5, 19, 'espèces', 8000, '2026-03-09', 'payé'),
(6, 21, 'espèces', 6000, '2026-03-09', 'payé'),
(7, 22, 'espèces', 7500, '2026-03-12', 'payé'),
(8, 23, 'espèces', 2000, '2026-03-12', 'payé'),
(9, 24, 'espèces', 3000, '2026-03-12', 'payé'),
(10, 25, 'espèces', 4000, '2026-03-13', 'payé'),
(11, 28, 'espèces', 4000, '2026-03-19', 'payé'),
(12, 29, 'espèces', 7000, '2026-03-19', 'en_attente_tech'),
(13, 30, 'espèces', 3000, '2026-03-19', 'payé'),
(14, 31, 'espèces', 4000, '2026-03-19', 'payé'),
(15, 33, 'espèces', 5000, '2026-03-19', 'payé'),
(16, 40, 'espèces', 10500, '2026-03-28', 'payé'),
(17, 45, 'espèces', 4500, '2026-04-06', 'payé'),
(18, 46, 'espèces', 3000, '2026-04-07', 'payé'),
(19, 47, 'espèces', 8000, '2026-04-08', 'payé'),
(20, 48, 'espèces', 2000, '2026-04-08', 'payé'),
(21, 49, 'espèces', 1500, '2026-04-08', 'payé'),
(22, 50, 'espèces', 1000, '2026-04-08', 'payé'),
(23, 51, 'espèces', 2000, '2026-04-10', 'payé'),
(24, 52, 'espèces', 1500, '2026-04-10', 'payé'),
(25, 53, 'espèces', 2000, '2026-04-10', 'payé'),
(26, 54, 'espèces', 2500, '2026-04-10', 'payé'),
(27, 55, 'espèces', 1000, '2026-04-13', 'payé'),
(28, 56, 'espèces', 1000, '2026-04-13', 'payé'),
(29, 57, 'espèces', 2000, '2026-04-13', 'payé'),
(30, 58, 'espèces', 1500, '2026-04-20', 'payé'),
(31, 59, 'espèces', 2000, '2026-04-20', 'payé'),
(32, 75, 'espèces', 2000, '2026-04-29', 'payé'),
(33, 76, 'espèces', 4500, '2026-04-29', 'payé'),
(34, 78, 'espèces', 2000, '2026-04-29', 'payé'),
(35, 79, 'espèces', 1500, '2026-04-29', 'payé'),
(36, 88, 'espèces', 2500, '2026-05-05', 'payé'),
(37, 89, 'espèces', 800, '2026-05-05', 'payé'),
(38, 90, 'espèces', 2500, '2026-05-05', 'payé'),
(39, 91, 'espèces', 6307, '2026-05-05', 'payé'),
(40, 93, 'espèces', 5950, '2026-05-05', 'payé'),
(41, 94, 'espèces', 13447, '2026-05-17', 'payé'),
(42, 95, 'espèces', 2500, '2026-05-17', 'payé'),
(43, 95, 'espèces', 8925, NULL, 'en_attente'),
(44, 96, 'espèces', 1500, '2026-05-17', 'payé'),
(45, 96, 'espèces', 4500, NULL, 'en_attente'),
(46, 96, 'espèces', 8330, NULL, 'en_attente'),
(47, 96, 'espèces', 10115, NULL, 'en_attente'),
(48, 97, 'espèces', 6000, '2026-05-17', 'payé'),
(49, 98, 'espèces', 5800, '2026-05-17', 'payé'),
(50, 99, 'espèces', 800, '2026-05-17', 'payé'),
(51, 99, 'espèces', 6902, NULL, 'en_attente'),
(52, 100, 'espèces', 5800, NULL, 'en_attente'),
(53, 101, 'espèces', 3300, '2026-05-17', 'payé'),
(54, 101, 'espèces', 9877, NULL, 'en_attente'),
(55, 102, 'espèces', 1500, '2026-05-17', 'payé'),
(56, 102, 'espèces', 7735, NULL, 'en_attente'),
(57, 102, 'espèces', 9520, NULL, 'en_attente'),
(58, 102, 'espèces', 1500, NULL, 'en_attente'),
(59, 103, 'espèces', 5500, '2026-05-22', 'payé'),
(60, 104, 'espèces', 7500, '2026-05-22', 'payé'),
(61, 106, 'espèces', 1500, '2026-05-22', 'payé'),
(62, 107, 'espèces', 6500, '2026-05-22', 'payé'),
(63, 108, 'espèces', 6500, '2026-05-22', 'payé'),
(64, 114, 'espèces', 6500, '2026-05-23', 'payé'),
(65, 115, 'espèces', 5000, '2026-06-05', 'payé'),
(66, 116, 'espèces', 7500, '2026-06-05', 'payé'),
(67, 117, 'espèces', 6000, '2026-06-05', 'payé'),
(68, 118, 'espèces', 7000, '2026-06-05', 'payé');

-- --------------------------------------------------------

--
-- Table structure for table `prestation`
--

CREATE TABLE `prestation` (
  `id_prestation` int(20) NOT NULL,
  `id_technicien` int(20) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `prix_min` float DEFAULT NULL,
  `prix_max` float DEFAULT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unite` enum('heure','jour','unité','intervention') NOT NULL DEFAULT 'intervention',
  `actif` tinyint(1) DEFAULT 1,
  `id_catalogue` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prestation`
--

INSERT INTO `prestation` (`id_prestation`, `id_technicien`, `nom`, `description`, `prix_min`, `prix_max`, `prix_unitaire`, `unite`, `actif`, `id_catalogue`) VALUES
(7, 12, 'chambre', '', NULL, NULL, 1000.00, 'heure', 1, NULL),
(8, 12, 'cuisine', '', NULL, NULL, 1500.00, 'heure', 1, NULL),
(9, 12, 'service complet', '', NULL, NULL, 8000.00, 'jour', 1, NULL),
(10, 12, 'sallon', '', NULL, NULL, 3500.00, 'intervention', 1, NULL),
(11, 12, 'Nettoyage bureau', '', NULL, NULL, 2500.00, 'heure', 1, NULL),
(13, 15, 'Chauffe-eau', '', NULL, NULL, 1500.00, 'intervention', 1, 8),
(14, 15, 'Détection fuite', '', NULL, NULL, 500.00, 'heure', 1, 116),
(15, 15, 'Installer douche', '', NULL, NULL, 1000.00, 'intervention', 0, 5),
(17, 16, 'Fuite eau', '', NULL, NULL, 2000.00, 'intervention', 1, NULL),
(18, 16, 'Installer douche', '', NULL, NULL, 1000.00, 'intervention', 1, NULL),
(19, 16, 'Réparer tuyaux', '', NULL, NULL, 1000.00, 'heure', 1, NULL),
(20, 16, 'Réparer WC', '', NULL, NULL, 2500.00, 'unité', 1, NULL),
(21, 16, 'Détection fuite', '', NULL, NULL, 1500.00, 'intervention', 1, NULL),
(22, 16, 'reparation urgente', '', NULL, NULL, 1500.00, 'heure', 1, NULL),
(24, 18, 'Installer prise', '', NULL, NULL, 2500.00, 'unité', 1, 12),
(25, 18, 'Réparer circuit', '', NULL, NULL, 800.00, 'intervention', 1, 123),
(26, 18, 'Installer interrupteur', '', NULL, NULL, 1500.00, 'unité', 1, 14),
(27, 21, 'Installer évier', '', NULL, NULL, 1000.00, 'unité', 1, 109),
(28, 21, 'Installer douche', '', NULL, NULL, 1500.00, 'unité', 1, 5),
(29, 34, 'Construire mur', '', NULL, NULL, 2500.00, 'intervention', 1, 155),
(30, 34, 'Dalle béton', '', NULL, NULL, 3500.00, 'intervention', 1, 158),
(31, 34, 'Démolition cloison', '', NULL, NULL, 1200.00, 'intervention', 1, 72),
(32, 34, 'Fissure mur', '', NULL, NULL, 2500.00, 'intervention', 1, 157),
(33, 34, 'Réparation fissure / lézarde', '', NULL, NULL, 1500.00, 'intervention', 1, 70),
(34, 34, 'Réparer mur', '', NULL, NULL, 3500.00, 'intervention', 1, 156),
(35, 34, 'Travaux rénovation', '', NULL, NULL, 5000.00, 'intervention', 1, 52),
(36, 22, 'Court-circuit', '', NULL, NULL, 2000.00, 'intervention', 1, 122),
(37, 22, 'Installer interrupteur', '', NULL, NULL, 1000.00, 'unité', 1, 14),
(38, 22, 'Installer lumière', '', NULL, NULL, 2000.00, 'unité', 1, 10),
(39, 22, 'Installer prise', '', NULL, NULL, 1500.00, 'unité', 1, 12),
(40, 22, 'Mise en conformité tableau', '', NULL, NULL, 6000.00, 'intervention', 1, 13),
(41, 22, 'Panne courant', '', NULL, NULL, 3000.00, 'intervention', 1, 11),
(42, 22, 'Remplacer câblage', '', NULL, NULL, 1000.00, 'intervention', 1, 120),
(43, 22, 'Réparer circuit', '', NULL, NULL, 2500.00, 'unité', 1, 123),
(44, 22, 'Tableau électrique', '', NULL, NULL, 10000.00, 'intervention', 1, 17),
(46, 18, 'Court-circuit', '', NULL, NULL, 2000.00, 'intervention', 1, 122),
(47, 18, 'Installer lumière', '', NULL, NULL, 1500.00, 'unité', 1, 10),
(48, 18, 'Mise en conformité tableau', '', NULL, NULL, 6000.00, 'intervention', 1, 13),
(49, 18, 'Panne courant', '', NULL, NULL, 3000.00, 'intervention', 1, 11),
(50, 18, 'Remplacer câblage', '', NULL, NULL, 1000.00, 'intervention', 1, 120),
(51, 23, 'Installer interrupteur', '', NULL, NULL, 1000.00, 'intervention', 1, 14),
(52, 23, 'Installer lumière', '', NULL, NULL, 750.00, 'unité', 1, 10),
(53, 23, 'Installer prise', '', NULL, NULL, 1500.00, 'unité', 1, 12),
(54, 23, 'Panne courant', '', NULL, NULL, 2500.00, 'intervention', 1, 11),
(55, 23, 'Réparer circuit', '', NULL, NULL, 4000.00, 'intervention', 1, 123);

-- --------------------------------------------------------

--
-- Table structure for table `prestation_catalogue`
--

CREATE TABLE `prestation_catalogue` (
  `id_catalogue` int(11) NOT NULL,
  `specialite` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `nom_ar` varchar(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prestation_catalogue`
--

INSERT INTO `prestation_catalogue` (`id_catalogue`, `specialite`, `nom`, `nom_ar`) VALUES
(1, 'Plomberie', 'Fuite eau', 'تسرب مياه '),
(3, 'Plomberie', 'Débouchage canalisation', 'إزالة انسداد المجاري'),
(4, 'Plomberie', 'Remplacement chauffe-eau', 'استبدال سخان المياه'),
(5, 'Plomberie', 'Installer douche', 'تركيب دش'),
(6, 'Plomberie', 'Remplacer robinet', 'استبدال صنبور'),
(10, 'Électricité', 'Installer lumière', 'تركيب إضاءة'),
(11, 'Électricité', 'Panne courant', 'انقطاع الكهرباء'),
(12, 'Électricité', 'Installer prise', 'تركيب مقبس كهربائي '),
(13, 'Électricité', 'Mise en conformité tableau', 'تحديث لوحة الكهرباء'),
(14, 'Électricité', 'Installer interrupteur', 'تركيب مفتاح كهربائي'),
(17, 'Électricité', 'Tableau électrique', 'لوحة كهربائية'),
(18, 'Menuiserie', 'Réparer porte', 'إصلاح باب'),
(19, 'Menuiserie', 'Installer porte', 'تركيب باب'),
(20, 'Menuiserie', 'Installer fenêtre', 'تركيب نافذة'),
(21, 'Menuiserie', 'Installation placard', 'تركيب خزانة'),
(22, 'Menuiserie', 'Fabrication meuble sur mesure', 'صنع أثاث مخصص'),
(23, 'Menuiserie', 'Pose parquet', 'تركيب باركيه'),
(24, 'Menuiserie', 'Bois sur mesure', 'خشب حسب الطلب'),
(27, 'Peinture', 'Peinture plafond', 'طلاء السقف'),
(30, 'Peinture', 'Peinture intérieure (m²)', 'دهان داخلي (م²)'),
(31, 'Peinture', 'Enduit mur', 'تلبيس الجدار'),
(32, 'Climatisation', 'Installer clim', 'تركيب مكيف'),
(34, 'Climatisation', 'Recharge gaz', 'شحن غاز'),
(35, 'Climatisation', 'Nettoyer clim', 'تنظيف المكيف'),
(36, 'Climatisation', 'Entretien clim', 'صيانة المكيف'),
(38, 'Électroménager', 'Réparer frigo', 'إصلاح الثلاجة'),
(40, 'Électroménager', 'Réparer four', 'إصلاح الفرن'),
(44, 'Carrelage', 'Réparer carrelage', 'إصلاح البلاط'),
(45, 'Carrelage', 'Revêtement sol', 'تغطية الأرضية'),
(46, 'Carrelage', 'Revêtement mur', 'تغطية الجدار'),
(47, 'Carrelage', 'Joint carrelage', 'فواصل البلاط'),
(50, 'Électroménager', 'Réparation machine à laver', 'إصلاح الغسالة'),
(52, 'Maçonnerie', 'Travaux rénovation', 'أعمال الترميم'),
(55, 'Serrurerie', 'Installer serrure', 'تركيب قفل'),
(58, 'Vitrerie', 'Installer vitre', 'تركيب زجاج'),
(59, 'Vitrerie', 'Remplacer vitre', 'استبدال زجاج'),
(60, 'Carrelage', 'Pose carrelage sol (m²)', 'تركيب بلاط الأرضية (م²)'),
(61, 'Carrelage', 'Pose carrelage mural (m²)', 'تركيب بلاط الجدار (م²)'),
(62, 'Vitrerie', 'Vitre cassée', 'زجاج مكسور'),
(63, 'Toiture', 'Réparer toit', 'إصلاح السقف'),
(66, 'Toiture', 'Nettoyer toiture', 'تنظيف السطح'),
(67, 'Toiture', 'Installer gouttière', 'تركيب مزراب'),
(68, 'Jardinage', 'Tondre pelouse', 'قص العشب'),
(70, 'Maçonnerie', 'Réparation fissure / lézarde', 'إصلاح شقوق الجدران'),
(71, 'Jardinage', 'Entretien jardin', 'صيانة الحديقة'),
(72, 'Maçonnerie', 'Démolition cloison', 'هدم حاجز'),
(75, 'Informatique', 'Virus suppression', 'إزالة الفيروسات'),
(76, 'Informatique', 'Problème réseau', 'مشكلة في الشبكة'),
(79, 'Télévision', 'Régler signal', 'ضبط الإشارة'),
(80, 'Télévision', 'Réparer antenne', 'إصلاح الهوائي'),
(81, 'Télévision', 'Installer parabole', 'تركيب صحن فضائي'),
(82, 'Télévision', 'Problème signal', 'مشكلة في الإشارة'),
(83, 'Déménagement', 'Déménagement complet', 'نقل كامل'),
(86, 'Déménagement', 'Décharger camion', 'تفريغ الشاحنة'),
(89, 'Nettoyage', 'Nettoyage bureau', 'تنظيف مكتب'),
(91, 'Nettoyage', 'Nettoyage canapé', 'تنظيف الأريكة'),
(92, 'Nettoyage', 'Nettoyage profond', 'تنظيف عميق'),
(93, 'Électronique', 'Réparer téléphone', 'إصلاح الهاتف'),
(96, 'Électronique', 'Problème batterie', 'مشكلة البطارية'),
(97, 'Électronique', 'Diagnostic appareil', 'تشخيص الجهاز'),
(98, 'Marbre', 'Poser marbre', 'تركيب الرخام'),
(100, 'Marbre', 'Polir marbre', 'تلميع الرخام'),
(101, 'Toiture', 'Étanchéité terrasse', 'عزل السطح'),
(105, 'Soudure', 'Portail fer', 'بوابة حديد'),
(107, 'Soudure', 'Fabrication fer', 'تصنيع الحديد'),
(109, 'Plomberie', 'Installer évier', 'تركيب حنفيات '),
(110, 'Plomberie', 'Réparer WC', 'اصلاح محاض '),
(114, 'Plomberie', 'Réparer tuyaux', 'اصلاح انابيب '),
(116, 'Plomberie', 'Détection fuite', 'كشف تسرب المياه '),
(120, 'Électricité', 'Remplacer câblage', 'تبديل الاسلاك '),
(123, 'Électricité', 'Réparer circuit', 'اصلاح تماس كهربائي '),
(128, 'Menuiserie', 'Réparer fenêtre', 'اصلاح نافذة '),
(129, 'Menuiserie', 'Fabriquer meuble', 'صنع الاثاث '),
(130, 'Menuiserie', 'Réparer placard', 'اصلاح خزانة '),
(131, 'Télévision', 'Réglage décodeur satellite', 'ضبط فضائي وريسيفر'),
(132, 'Menuiserie', 'Installer meuble', 'تركيب اثاث '),
(133, 'Peinture', 'Peinture mur', 'طلاء الجدران '),
(135, 'Peinture', 'Peinture façade', 'طلاء واجهة '),
(136, 'Peinture', 'Retouche peinture', 'ترميم طلاء '),
(137, 'Peinture', 'Préparation mur', 'اعداد جدار '),
(140, 'Climatisation', 'Réparer clim', 'اصلاح مكيف'),
(141, 'Déménagement', 'Transport de meubles', 'نقل أثاث'),
(144, 'Climatisation', 'Réparer chauffage', 'اصلاح مسخن'),
(148, 'Électroménager', 'Réparer lave-vaisselle', 'اصلاح غسالة الاطباق'),
(150, 'Nettoyage', 'Nettoyage appartement', 'تنظيف شقة'),
(151, 'Nettoyage', 'Nettoyage post-travaux', 'تنظيف ما بعد البناء'),
(155, 'Maçonnerie', 'Construire mur', 'بناء جدار '),
(156, 'Maçonnerie', 'Réparer mur', 'اصلاح جدار '),
(158, 'Maçonnerie', 'Dalle béton', 'بلاطة خرسانية'),
(160, 'Serrurerie', 'Ouvrir porte', 'فتح باب مقفل '),
(161, 'Serrurerie', 'Changer serrure', 'تبديل قفل '),
(163, 'Serrurerie', 'Sécurité porte', 'تامين باب '),
(164, 'Serrurerie', 'Clé cassée', 'اصلاح مفتاح مكسور'),
(167, 'Vitrerie', 'Réparer fenêtre', 'اصلاح نافذة'),
(168, 'Vitrerie', 'Cadre aluminium', 'تركيب نافذة اليمينيوم'),
(171, 'Toiture', 'Fuite toit', 'تسرب سقف '),
(172, 'Toiture', 'Étanchéité toit', 'عزل سقف من الماء'),
(176, 'Jardinage', 'Tailler arbre', 'تلقيم اشجار '),
(177, 'Jardinage', 'Planter jardin', 'زرع حديقة'),
(179, 'Jardinage', 'Nettoyer jardin', 'تنظيف حديقة'),
(180, 'Informatique', 'Réparer PC', 'اصلاح حاسوب '),
(181, 'Informatique', 'Installer logiciel', 'تثبيت برامج'),
(184, 'Informatique', 'Installer système', 'تثبيت نظام '),
(185, 'Télévision', 'Installer antenne', 'تركيب هوائي للتلفاز '),
(190, 'Autre', 'Autre prestation', 'خدمة أخرى'),
(192, 'Déménagement', 'Charger camion', 'تحميل شاحنة '),
(194, 'Déménagement', 'Emballage meubles', 'تغليف الاثاث '),
(195, 'Nettoyage', 'Nettoyage maison', 'تنظيف منزل '),
(197, 'Nettoyage', 'Nettoyage vitres', 'تنظيف النوافذ '),
(201, 'Électronique', 'Changer écran', 'تغيير شاشة جهاز'),
(202, 'Électronique', 'Réparer tablette', 'اصلاح لوحة رقمية'),
(206, 'Marbre', 'Réparer marbre', 'اصلاح رخام'),
(208, 'Marbre', 'Poser granit', 'تركيب غرانيت'),
(209, 'Marbre', 'Découpe pierre', 'قطع حجر '),
(210, 'Soudure', 'Travaux soudure', 'اعمال اللحام '),
(211, 'Soudure', 'Réparer métal', 'اصلاح معدن '),
(213, 'Soudure', 'Structure métal', 'هيكل معدني ');

-- --------------------------------------------------------

--
-- Table structure for table `reset_token`
--

CREATE TABLE `reset_token` (
  `id_token` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service`
--

CREATE TABLE `service` (
  `id_service` int(11) NOT NULL,
  `id_client` int(20) DEFAULT NULL,
  `id_technicien` int(20) DEFAULT NULL,
  `date_service` date DEFAULT NULL,
  `statut_service` varchar(20) DEFAULT NULL,
  `prix_estime` float DEFAULT NULL,
  `prix_final` float DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type_service` varchar(100) NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service`
--

INSERT INTO `service` (`id_service`, `id_client`, `id_technicien`, `date_service`, `statut_service`, `prix_estime`, `prix_final`, `description`, `type_service`, `nom`) VALUES
(1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Plomberie'),
(2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Électricité'),
(3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Menuiserie'),
(4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Peinture'),
(5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Électroménager'),
(84, 10, 15, '2026-05-03', 'annulé', 0, NULL, '', '', ''),
(85, 10, 18, '2026-05-04', 'annulé', 0, NULL, 'ahhhhhhhhhhhhhhhhhhhhhhhhhhhh', '', ''),
(86, 10, 18, '2026-05-04', 'annulé', 0, NULL, 'ahhhhhhhhhhhhhhhhhhhhhhhhhhhh', '', ''),
(87, 10, 18, '2026-05-04', 'annulé', 0, NULL, '', '', ''),
(88, 10, 18, '2026-05-06', 'terminé', 0, 2500, '', '', ''),
(89, 10, 18, '2026-05-06', 'terminé', 0, 800, '', '', ''),
(90, 10, 18, '2026-05-06', 'terminé', 0, 2500, '', '', ''),
(91, 10, 18, '2026-05-06', 'terminé', 0, 6307, '', '', ''),
(92, 10, 18, '2026-05-06', 'annulé', 0, NULL, '', '', ''),
(93, 10, 18, '2026-05-06', 'terminé', 0, 5950, '', '', ''),
(94, 10, 18, '2026-05-06', 'terminé', 0, 13447, '', '', ''),
(95, 10, 18, '2026-05-18', 'terminé', 0, 8925, '', '', ''),
(96, 10, 18, '2026-05-18', 'terminé', 0, 10115, '', '', ''),
(97, 10, 18, '2026-05-18', 'terminé', 0, 6000, '', '', ''),
(98, 10, 18, '2026-05-18', 'terminé', 0, 5800, '', '', ''),
(99, 10, 18, '2026-05-18', 'terminé', 0, 6902, '', '', ''),
(100, 10, 18, '2026-05-18', 'terminé', 0, 5800, '', '', ''),
(101, 10, 18, '2026-05-18', 'terminé', 0, 9877, '', '', ''),
(102, 10, 18, '2026-05-18', 'terminé', 0, 11020, '', '', ''),
(103, 10, 23, '2026-05-23', 'terminé', 0, 5500, '', '', ''),
(104, 10, 18, '2026-05-23', 'terminé', 0, 7500, '', '', ''),
(105, 10, 23, '2026-05-23', 'annulé', 0, NULL, '', '', ''),
(106, 10, 23, '2026-05-23', 'terminé', 0, 1500, '', '', ''),
(107, 10, 23, '2026-05-23', 'terminé', 0, 6500, '', '', ''),
(108, 10, 23, '2026-05-23', 'terminé', 0, 6500, '', '', ''),
(109, 10, 18, '2026-05-24', 'annulé', 0, NULL, '', '', ''),
(110, 10, 18, '2026-05-24', 'annulé', 0, NULL, '', '', ''),
(111, 10, 23, '2026-05-25', 'annulé', 0, NULL, '', '', ''),
(112, 10, 18, '2026-05-23', 'annulé', 0, NULL, '', '', ''),
(113, 11, 18, '2026-05-23', 'annulé', 0, NULL, '', '', ''),
(114, 10, 18, '2026-05-23', 'terminé', 0, 6500, '', '', ''),
(115, 10, 18, '2026-06-05', 'terminé', 0, 5000, '', '', ''),
(116, 10, 18, '2026-06-05', 'terminé', 0, 7500, '', '', ''),
(117, 10, 18, '2026-06-05', 'terminé', 0, 6000, '', '', ''),
(118, 10, 18, '2026-06-06', 'terminé', 0, 7000, '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `service_prestation`
--

CREATE TABLE `service_prestation` (
  `id_service` int(11) NOT NULL,
  `id_prestation` int(11) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_prestation`
--

INSERT INTO `service_prestation` (`id_service`, `id_prestation`, `quantite`) VALUES
(84, 14, 1),
(85, 25, 1),
(86, 25, 1),
(87, 25, 1),
(88, 24, 1),
(89, 25, 1),
(90, 24, 1),
(91, 25, 1),
(91, 26, 3),
(92, 24, 1),
(93, 24, 2),
(94, 24, 3),
(94, 25, 1),
(94, 26, 2),
(95, 24, 3),
(96, 24, 1),
(96, 26, 4),
(97, 26, 4),
(98, 24, 2),
(98, 25, 1),
(99, 24, 2),
(99, 25, 1),
(100, 24, 2),
(100, 25, 1),
(101, 24, 3),
(101, 25, 1),
(102, 24, 2),
(102, 26, 3),
(103, 52, 2),
(103, 53, 1),
(103, 54, 1),
(104, 24, 3),
(105, 52, 1),
(106, 52, 2),
(107, 54, 1),
(107, 55, 1),
(108, 51, 1),
(108, 52, 2),
(108, 55, 1),
(109, 24, 1),
(110, 26, 1),
(111, 53, 1),
(112, 24, 1),
(112, 25, 1),
(113, 24, 1),
(114, 24, 2),
(114, 47, 1),
(115, 24, 2),
(116, 24, 3),
(117, 47, 4),
(118, 24, 2),
(118, 46, 1);

-- --------------------------------------------------------

--
-- Table structure for table `signalement`
--

CREATE TABLE `signalement` (
  `id_signalement` int(20) NOT NULL,
  `id_user` int(20) DEFAULT NULL,
  `motif` tinytext DEFAULT NULL,
  `date_signalement` date DEFAULT NULL,
  `statut_signal` varchar(20) DEFAULT NULL,
  `id_signaleur` int(20) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `technicien`
--

CREATE TABLE `technicien` (
  `id_technicien` int(11) NOT NULL,
  `id_user` int(20) DEFAULT NULL,
  `specialité` varchar(100) DEFAULT NULL,
  `experience` int(11) DEFAULT NULL,
  `tarif` float DEFAULT NULL,
  `ville_tech` varchar(100) DEFAULT NULL,
  `cv` varchar(200) DEFAULT NULL,
  `diplôme` varchar(200) DEFAULT NULL,
  `verifie` tinyint(1) DEFAULT NULL,
  `date_validation` date DEFAULT NULL,
  `description_profil` varchar(500) DEFAULT NULL,
  `id_service` int(11) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `localisation_active` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technicien`
--

INSERT INTO `technicien` (`id_technicien`, `id_user`, `specialité`, `experience`, `tarif`, `ville_tech`, `cv`, `diplôme`, `verifie`, `date_validation`, `description_profil`, `id_service`, `latitude`, `longitude`, `localisation_active`) VALUES
(12, 32, 'Nettoyage', 5, 0, NULL, 'cv_32_1774709239.pdf', NULL, 1, '2026-03-28', '', 0, NULL, NULL, 0),
(13, 33, 'Peinture', 2, 0, NULL, 'cv_33_1775747307.pdf', NULL, 1, '2026-04-09', '', 0, NULL, NULL, 0),
(14, 34, 'Électricité', 0, 0, NULL, 'cv_34_1775747875.pdf', NULL, 1, '2026-04-09', '', 0, NULL, NULL, 0),
(15, 35, 'Plomberie', 0, 0, NULL, 'cv_35_1775747956.pdf', NULL, 1, '2026-04-09', '', 0, 34.8800000, -1.3200000, 0),
(16, 36, 'Plomberie', 0, 0, NULL, NULL, NULL, 1, '2026-04-09', '', 0, NULL, NULL, 0),
(17, 37, 'Nettoyage', 0, 0, NULL, NULL, NULL, 1, '2026-04-09', '', 0, NULL, NULL, 0),
(18, 41, 'Électricité', 2, 0, NULL, 'cv_41_1777738359.pdf', NULL, 1, '2026-05-02', '', 0, NULL, NULL, 0),
(20, 46, 'Plomberie', 3, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(21, 47, 'Plomberie', 5, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(22, 48, 'Électricité', 1, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(23, 49, 'Électricité', 2, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(24, 50, 'Menuiserie', 0, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(25, 51, 'Menuiserie', 5, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(26, 52, 'Peinture', 0, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(27, 53, 'Peinture', 2, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(28, 54, 'Climatisation', 0, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(29, 55, 'Climatisation', 1, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(30, 56, 'Électroménager', 0, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(31, 57, 'Électroménager', 0, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(32, 58, 'Carrelage', 0, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(33, 59, 'Carrelage', 0, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(34, 60, 'Maçonnerie', 5, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(35, 61, 'Maçonnerie', 0, 0, NULL, NULL, NULL, 1, '2026-05-20', '', 0, NULL, NULL, 0),
(36, 62, 'Informatique', 1, 0, NULL, 'cv_62_1779547089.pdf', NULL, 0, NULL, '', 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tva`
--

CREATE TABLE `tva` (
  `id_tva` bigint(20) UNSIGNED NOT NULL,
  `id_service` bigint(20) UNSIGNED DEFAULT NULL,
  `id_paiment` bigint(20) UNSIGNED DEFAULT NULL,
  `id_technicien` bigint(20) UNSIGNED DEFAULT NULL,
  `montant_service` double DEFAULT 0,
  `taux_tva` double DEFAULT 0,
  `montant_tva` double DEFAULT 0,
  `statut` varchar(32) DEFAULT 'due',
  `date_tva` datetime DEFAULT NULL,
  `date_paiement` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id_user` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(100) NOT NULL,
  `date_inscription` date NOT NULL DEFAULT curdate(),
  `statut_compte` varchar(20) DEFAULT 'actif',
  `role` varchar(20) NOT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `raison_suspension` text DEFAULT NULL,
  `langue` varchar(5) DEFAULT 'fr'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utilisateur`
--

INSERT INTO `utilisateur` (`id_user`, `nom`, `prenom`, `email`, `telephone`, `ville`, `mot_de_passe`, `date_inscription`, `statut_compte`, `role`, `latitude`, `longitude`, `raison_suspension`, `langue`) VALUES
(2, 'moha', 'zadam', 'moha25@gmail.com', '0788888888', 'saida', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '0000-00-00', '', 'technicien', NULL, NULL, NULL, 'fr'),
(3, 'moha', 'zadam', 'moha@gmail.com', '0788888888', 'saida', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '0000-00-00', '', 'technicien', NULL, NULL, NULL, 'fr'),
(5, 'dj', 'kada', 'djk@gmail.com', '02555252156525', 'saida', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '0000-00-00', '', 'technicien', NULL, NULL, NULL, 'fr'),
(6, 'dj', 'daka', 'djdaka@gmail.com', '05555555555', 'saida', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '0000-00-00', '', 'technicien', NULL, NULL, NULL, 'fr'),
(7, 'dj', 'daka', 'dj100@gmail.com', '05555555555', 'saida', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '0000-00-00', '', 'technicien', NULL, NULL, NULL, 'fr'),
(8, 'dj', 'daka', 'dj101@gmail.com', '05555555555', 'saida', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '0000-00-00', '', 'technicien', NULL, NULL, NULL, 'fr'),
(12, 'Admin', 'Khidma', 'admin@khidma.dz', '0555000000', 'Alger', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-03-05', 'actif', 'admin', NULL, NULL, NULL, 'fr'),
(27, 'dz', 'mohamed', 'admino@khidma.dz', '', NULL, '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-03-11', 'actif', 'admin', NULL, NULL, NULL, 'fr'),
(32, 'mira', 'ocean', 'ocean@gmail.com', '0698754145', 'Alger', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-03-28', 'suspendu', 'technicien', NULL, NULL, '', 'fr'),
(33, 'girl', 'super', 'supergirl@gmail.com', '0596325875', 'Alger', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-04-09', 'suspendu', 'technicien', NULL, NULL, '', 'fr'),
(34, 'man', 'super', 'superman@gmail.com', '0788552211', 'Alger', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-04-09', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(35, 'dahman', 'adem', 'batman@gmail.com', '0699332214', 'Alger', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-04-09', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(36, 'boy', 'aqua', 'aquaboy@gmail.com', '0788521478', 'Alger', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-04-09', 'suspendu', 'technicien', NULL, NULL, '', 'fr'),
(37, 'girl', 'wonder', 'wondergirl@gmail.com', '0596547825', 'Alger', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-04-09', 'suspendu', 'technicien', NULL, NULL, '', 'fr'),
(41, 'benaili', 'karim', 'karim20@gmail.com', '0654785214', 'Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-02', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(42, 'djelloul', 'mehdi', 'mehdidj@gmail.com', '0596471235', 'Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-02', 'actif', 'client', NULL, NULL, NULL, 'fr'),
(43, 'dz', 'moha', 'moha@gmail.dz', '0789654258', NULL, '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-17', 'actif', 'admin', NULL, NULL, NULL, 'fr'),
(45, 'mehdi', 'djelloul', 'mehdi20@gmail.com', '0766663252', 'Alger', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'client', NULL, NULL, NULL, 'fr'),
(46, 'youcef', 'khelifi', 'youcef20@gmail.com', '0596414785', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(47, 'rachid', 'boudiaf', 'rachid20@gmail.com', '0754851423', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'ar'),
(48, 'karim', 'meziane', 'karim16@gmail.com', '0589632415', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(49, 'hamdi', 'nassim', 'nassim20@gmail.com', '0654896254', 'Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(50, 'omar', 'boudali', 'omar20@gmail.com', '0789569322', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(51, 'zerrouki', 'bilal', 'bilal20@gmail.com', '0612585325', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(52, 'bensalem', 'sofiane', 'sofiane20@gmail.com', '2599663311', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(53, 'cherif', 'amine', 'cherif20@gmail.com', '0789652152', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(54, 'toumi', 'walid', 'walid20@gmail.com', '06987525', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(55, 'ouali', 'riad', 'riad20@gmail.com', '0699887744', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(56, 'saidani', 'hichem', 'hichem20@gmail.com', '0587863214', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(57, 'djaber', 'tarek', 'tarek20@gmail.com', '0623521452', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(58, 'boukhalef', 'samir', 'samir20@gmail.com', '0756325889', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(59, 'mekki', 'adel', 'adel20@gmail.com', '0696321588', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(60, 'ferhat', 'zakaria', 'zakaria20@gmail.com', '0789632532', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(61, 'berrahma', 'lotfi', 'lotfi20@gmail.com', '0699335577', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-20', 'actif', 'technicien', NULL, NULL, NULL, 'fr'),
(62, 'djaber', 'hamdan', 'hamdan@gmail.com', '06987552211', '20-Saïda', '$2y$10$/KHmFlvZmBzkA2jhHjBMDulHUn6qpSD6gwK8G1HSZ3jUWfdiBQFbC', '2026-05-23', 'actif', 'technicien', NULL, NULL, NULL, 'fr');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`);

--
-- Indexes for table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`id_avis`),
  ADD UNIQUE KEY `unique_client_service` (`id_client`,`id_service`),
  ADD KEY `fk_avis_service` (`id_service`);

--
-- Indexes for table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`id_client`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `commission`
--
ALTER TABLE `commission`
  ADD PRIMARY KEY (`id_commission`),
  ADD UNIQUE KEY `uniq_paiment` (`id_paiment`),
  ADD KEY `idx_technicien` (`id_technicien`),
  ADD KEY `idx_service` (`id_service`),
  ADD KEY `idx_statut` (`statut`);

--
-- Indexes for table `disponibilite`
--
ALTER TABLE `disponibilite`
  ADD PRIMARY KEY (`id_disponibilite`),
  ADD KEY `id_technicien` (`id_technicien`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_demande_eval` (`demande_id`),
  ADD KEY `idx_tech_id` (`tech_id`),
  ADD KEY `idx_client_id` (`client_id`);

--
-- Indexes for table `facture`
--
ALTER TABLE `facture`
  ADD PRIMARY KEY (`id_facture`);

--
-- Indexes for table `litige`
--
ALTER TABLE `litige`
  ADD PRIMARY KEY (`id_litige`);

--
-- Indexes for table `log_action`
--
ALTER TABLE `log_action`
  ADD PRIMARY KEY (`id_log`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id_message`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`id_notif`);

--
-- Indexes for table `paiment`
--
ALTER TABLE `paiment`
  ADD PRIMARY KEY (`id_paiment`);

--
-- Indexes for table `prestation`
--
ALTER TABLE `prestation`
  ADD PRIMARY KEY (`id_prestation`),
  ADD KEY `id_technicien` (`id_technicien`);

--
-- Indexes for table `prestation_catalogue`
--
ALTER TABLE `prestation_catalogue`
  ADD PRIMARY KEY (`id_catalogue`),
  ADD KEY `idx_specialite` (`specialite`);

--
-- Indexes for table `reset_token`
--
ALTER TABLE `reset_token`
  ADD PRIMARY KEY (`id_token`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user` (`id_user`);

--
-- Indexes for table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`id_service`),
  ADD KEY `id_client` (`id_client`),
  ADD KEY `id_technicien` (`id_technicien`);

--
-- Indexes for table `service_prestation`
--
ALTER TABLE `service_prestation`
  ADD PRIMARY KEY (`id_service`,`id_prestation`),
  ADD KEY `id_prestation` (`id_prestation`);

--
-- Indexes for table `signalement`
--
ALTER TABLE `signalement`
  ADD PRIMARY KEY (`id_signalement`);

--
-- Indexes for table `technicien`
--
ALTER TABLE `technicien`
  ADD PRIMARY KEY (`id_technicien`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `tva`
--
ALTER TABLE `tva`
  ADD PRIMARY KEY (`id_tva`),
  ADD UNIQUE KEY `unique_service_tva` (`id_service`);

--
-- Indexes for table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `avis`
--
ALTER TABLE `avis`
  MODIFY `id_avis` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `client`
--
ALTER TABLE `client`
  MODIFY `id_client` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `commission`
--
ALTER TABLE `commission`
  MODIFY `id_commission` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facture`
--
ALTER TABLE `facture`
  MODIFY `id_facture` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `litige`
--
ALTER TABLE `litige`
  MODIFY `id_litige` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `log_action`
--
ALTER TABLE `log_action`
  MODIFY `id_log` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1035;

--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
  MODIFY `id_message` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `id_notif` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=499;

--
-- AUTO_INCREMENT for table `paiment`
--
ALTER TABLE `paiment`
  MODIFY `id_paiment` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `prestation`
--
ALTER TABLE `prestation`
  MODIFY `id_prestation` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `prestation_catalogue`
--
ALTER TABLE `prestation_catalogue`
  MODIFY `id_catalogue` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=215;

--
-- AUTO_INCREMENT for table `reset_token`
--
ALTER TABLE `reset_token`
  MODIFY `id_token` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `service`
--
ALTER TABLE `service`
  MODIFY `id_service` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `signalement`
--
ALTER TABLE `signalement`
  MODIFY `id_signalement` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `technicien`
--
ALTER TABLE `technicien`
  MODIFY `id_technicien` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `tva`
--
ALTER TABLE `tva`
  MODIFY `id_tva` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `fk_avis_service` FOREIGN KEY (`id_service`) REFERENCES `service` (`id_service`) ON DELETE SET NULL;

--
-- Constraints for table `client`
--
ALTER TABLE `client`
  ADD CONSTRAINT `client_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`);

--
-- Constraints for table `disponibilite`
--
ALTER TABLE `disponibilite`
  ADD CONSTRAINT `disponibilite_ibfk_1` FOREIGN KEY (`id_technicien`) REFERENCES `technicien` (`id_technicien`);

--
-- Constraints for table `prestation`
--
ALTER TABLE `prestation`
  ADD CONSTRAINT `prestation_ibfk_1` FOREIGN KEY (`id_technicien`) REFERENCES `technicien` (`id_technicien`);

--
-- Constraints for table `reset_token`
--
ALTER TABLE `reset_token`
  ADD CONSTRAINT `reset_token_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `service_prestation`
--
ALTER TABLE `service_prestation`
  ADD CONSTRAINT `service_prestation_ibfk_1` FOREIGN KEY (`id_service`) REFERENCES `service` (`id_service`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_prestation_ibfk_2` FOREIGN KEY (`id_prestation`) REFERENCES `prestation` (`id_prestation`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
