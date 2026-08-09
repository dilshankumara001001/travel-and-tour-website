-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 09, 2026 at 07:29 AM
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
-- Database: `travel_tour_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `AdminID` int(11) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `DateCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`AdminID`, `FullName`, `Email`, `Password`, `PhoneNumber`, `DateCreated`) VALUES
(12, 'admin', 'admin@gmail.com', 'admin1234', '0701004533', '2026-07-11 07:02:20');

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip_address`, `created_at`) VALUES
(1, 1, 'Added expense for vehicle 20', '::1', '2026-07-17 16:58:09'),
(2, 1, 'Updated maintenance for vehicle 20', '::1', '2026-07-17 16:58:38'),
(3, 1, 'Updated vehicle 20 status to Available', '::1', '2026-07-17 18:19:52'),
(4, 1, 'Updated vehicle 20 status to Maintenance', '::1', '2026-07-17 18:20:53'),
(5, 1, 'Updated vehicle 20 status to Booked', '::1', '2026-07-17 18:44:57'),
(6, 1, 'Updated vehicle 20 status to Booked', '::1', '2026-07-17 18:45:09'),
(7, 1, 'Updated maintenance for vehicle 20', '::1', '2026-07-17 18:48:14'),
(8, 1, 'Updated vehicle 20 status to Booked', '::1', '2026-07-17 18:52:09'),
(9, 1, 'Updated vehicle 20 status to Booked', '::1', '2026-07-17 18:52:38'),
(10, 1, 'Updated vehicle 20 status to Booked', '::1', '2026-07-17 18:53:03'),
(11, 1, 'Updated vehicle 5 status to Booked', '::1', '2026-07-17 18:53:15'),
(12, 1, 'Updated vehicle 20 status to Available', '::1', '2026-07-17 18:53:31'),
(13, 1, 'Updated vehicle 5 status to Maintenance', '::1', '2026-07-17 18:53:44'),
(14, 1, 'Updated vehicle 20 status to Maintenance', '::1', '2026-07-17 18:54:04');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `package_name` varchar(255) DEFAULT NULL,
  `manual_package_name` varchar(255) DEFAULT NULL,
  `booking_date` datetime NOT NULL,
  `status` varchar(50) DEFAULT 'Approved',
  `pickup_location` varchar(255) DEFAULT NULL,
  `destination_location` varchar(255) DEFAULT NULL,
  `calculated_distance` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `number_of_days` int(11) NOT NULL DEFAULT 1,
  `vehicle_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `number_of_members` int(11) DEFAULT 1,
  `pickup_lat` decimal(10,8) DEFAULT NULL,
  `pickup_lng` decimal(11,8) DEFAULT NULL,
  `dest_lat` decimal(10,8) DEFAULT NULL,
  `dest_lng` decimal(11,8) DEFAULT NULL,
  `petrol_cost` decimal(12,2) DEFAULT 0.00,
  `vehicle_cost` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `package_id`, `package_name`, `manual_package_name`, `booking_date`, `status`, `pickup_location`, `destination_location`, `calculated_distance`, `total_cost`, `number_of_days`, `vehicle_id`, `start_date`, `end_date`, `number_of_members`, `pickup_lat`, `pickup_lng`, `dest_lat`, `dest_lng`, `petrol_cost`, `vehicle_cost`) VALUES
(4, 1, 4, NULL, NULL, '2026-05-20 01:14:55', 'Approved', NULL, NULL, NULL, NULL, 1, 0, '2026-05-20', '2026-05-21', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(5, 1, 10, NULL, NULL, '2026-05-20 01:14:59', 'Approved', NULL, NULL, NULL, NULL, 1, 0, '2026-05-20', '2026-05-21', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(13, 3, 4, NULL, NULL, '2026-05-23 00:00:00', 'Cancelled', NULL, NULL, NULL, NULL, 1, 0, '2026-05-23', '2026-05-24', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(14, 2, 1, NULL, NULL, '2026-05-23 00:00:00', 'Approved', NULL, NULL, NULL, NULL, 1, 0, '2026-05-23', '2026-05-24', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(15, 2, 1, NULL, NULL, '2026-05-23 00:00:00', 'Approved', NULL, NULL, NULL, NULL, 1, 0, '2026-05-23', '2026-05-24', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(20, 2, 1, NULL, NULL, '2026-05-24 00:00:00', 'Confirmed', NULL, NULL, NULL, NULL, 1, 0, '2026-05-24', '2026-05-25', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(21, 2, 1, NULL, NULL, '2026-05-24 00:00:00', 'Approved', NULL, NULL, NULL, NULL, 1, 0, '2026-05-24', '2026-05-25', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(22, 3, 3, NULL, NULL, '2026-05-24 00:00:00', 'Confirmed', NULL, NULL, NULL, NULL, 1, 0, '2026-05-24', '2026-05-25', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(23, 4, 7, NULL, NULL, '2026-05-24 00:00:00', 'Cancelled', NULL, NULL, NULL, NULL, 1, 0, '2026-05-24', '2026-05-25', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(38, 1, 0, NULL, NULL, '2026-06-17 19:25:38', 'Approved', 'Pickup: Lat: 7.6281, Lng: 80.3798', 'Destination: Lat: 6.7889, Lng: 80.7204', 100.60, 30678.92, 1, 7, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(42, 24, 1, NULL, 'kamal', '2026-06-17 19:40:07', 'Approved', 'Lat: 7.5394, Lng: 80.8456', 'Lat: 6.5363, Lng: 80.3402', 124.71, 96025.08, 1, 6, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(43, 24, 1, NULL, 'ranil', '2026-06-17 19:41:16', 'Approved', 'Lat: 8.7702, Lng: 80.3426', 'Lat: 7.0945, Lng: 80.7821', 192.51, 146874.25, 1, 6, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(44, 24, 1, NULL, 'ags', '2026-06-17 19:43:05', 'Approved', 'Lat: 7.8069, Lng: 80.6698', 'Lat: 8.4703, Lng: 80.6972', 73.83, 90093.04, 1, 9, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(45, 24, 1, NULL, 'ags', '2026-06-17 19:43:07', 'Approved', 'Lat: 7.8069, Lng: 80.6698', 'Lat: 8.4703, Lng: 80.6972', 73.83, 90093.04, 1, 9, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(46, 24, 1, NULL, 'ags', '2026-06-17 19:43:48', 'Approved', 'Lat: 7.8069, Lng: 80.6698', 'Lat: 8.4703, Lng: 80.6972', 73.83, 90093.04, 1, 9, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(47, 24, 1, 'ags', NULL, '2026-06-17 19:47:58', 'Approved', 'Lat: 8.1781, Lng: 80.4246', 'Lat: 7.0020, Lng: 80.6168', 132.48, 318956.65, 1, 10, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(48, 24, 1, 'aaaaaaaaaaaaa', NULL, '2026-06-17 19:48:41', 'Approved', 'Lat: 8.1278, Lng: 80.5187', 'Lat: 7.7034, Lng: 80.9197', 64.63, 26351.86, 1, 9, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(49, 24, 1, 'aaaaaaaaaaaaa', NULL, '2026-06-17 19:50:32', 'Approved', 'Lat: 8.0591, Lng: 80.2378', 'Lat: 7.2262, Lng: 81.1112', 133.58, 20536.31, 1, 6, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(50, 24, 1, 'aaaaaaaaaaaaa', NULL, '2026-06-17 19:53:13', 'Approved', 'Lat: 7.7770, Lng: 80.5906', 'Lat: 8.3264, Lng: 80.6510', 61.44, 25076.93, 1, 9, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(51, 24, 1, 'aaaaaaaaaaaaa', NULL, '2026-06-17 19:54:59', 'Approved', 'Lat: 7.8497, Lng: 80.6533', 'Lat: 7.1853, Lng: 81.0104', 83.71, 100950.48, 1, 10, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(52, 24, 1, 'kamal', NULL, '2026-06-17 19:55:28', 'Approved', 'Lat: 8.5891, Lng: 80.4940', 'Lat: 6.8963, Lng: 81.2136', 204.24, 29092.62, 1, 11, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(53, 24, 1, NULL, 'gas', '2026-06-17 19:59:40', 'Approved', 'Lat: 8.7411, Lng: 80.5160', 'Lat: 7.2779, Lng: 80.6094', 163.03, 73860.51, 1, 8, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(55, 24, 1, NULL, 'gggggggggggggg', '2026-06-17 20:02:31', 'Approved', 'Lat: 8.7977, Lng: 80.5160', 'Lat: 7.6180, Lng: 80.9060', 138.03, 187836.79, 1, 8, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(56, 24, 1, NULL, 'Mountain Adventure', '2026-06-17 20:04:25', 'Approved', 'Lat: 7.9904, Lng: 80.7851', 'Lat: 7.7727, Lng: 80.7082', 25.64, 31268.45, 1, 10, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(57, 24, 1, 'ggggggggggggggggggggggg', 'ggggggggggggggggggggggg', '2026-06-17 20:05:45', 'Approved', 'Lat: 8.5767, Lng: 80.4885', 'Lat: 7.6250, Lng: 80.9225', 116.11, 14431.89, 1, 1, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(58, 24, 1, 'g', 'g', '2026-06-17 20:07:17', 'Approved', 'Lat: 8.6636, Lng: 80.3951', 'Lat: 8.1745, Lng: 80.5215', 56.13, 7234.38, 1, 1, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(59, 24, 1, 'afd', 'afd', '2026-06-17 20:11:01', 'Approved', 'Lat: 8.8728, Lng: 80.5028', 'Lat: 8.4601, Lng: 80.6511', 48.70, 6343.19, 1, 1, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(60, 24, 1, 'Mountain Adventure', 'Mountain Adventure', '2026-06-17 20:57:38', 'Approved', 'Lat: 8.0652, Lng: 80.9093', 'Lat: 7.5319, Lng: 80.8983', 59.32, 7617.17, 1, 1, '2026-06-17', '2026-06-18', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(65, 20, 5, 'awdadadadad', 'awdadadadad', '2026-06-18 21:42:26', 'Approved', '7.9091, 81.0340', 'Lat: 7.5253, Lng: 80.7621', 52.20, 6720.00, 1, 1, '2026-06-18', '2026-06-19', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(67, 20, 5, 'sdd', 'sdd', '2026-06-18 21:50:40', 'Deleted', 'Lat: 8.3251, Lng: 80.6468', 'Lat: 7.6723, Lng: 80.6468', 72.58, 9009.07, 1, 1, '2026-06-18', '2026-06-19', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(88, 22, 6, 'paka', 'paka', '2026-06-22 18:50:07', 'Approved', 'Lat: 7.4030, Lng: 81.8288', 'Lat: 7.2876, Lng: 81.6770', 21.09, 3609.00, 1, 11, '2026-06-22', '2026-06-23', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(101, 29, 0, 'No Package Selected', 'No Package Selected', '2026-06-26 22:30:24', 'confirmed', '8.112475, 80.408466', 'Lat: 9.7964, Lng: 80.0308', 198.00, 32670.72, 1, 2, '2026-06-26', '2026-06-27', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(113, 20, 0, 'amal.', 'amal.', '2026-07-01 00:00:00', 'Approved', '7.8407, 80.5261', 'Lat: 7.8911, Lng: 80.5659', 7.12, 2212.00, 1, 1, '2026-07-01', '2026-07-02', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(114, 18, 0, 'Trip from Lat: 8.3343, Lng: 80... to Lat: 8.0135, Lng: 80...', 'Trip from Lat: 8.3343, Lng: 80... to Lat: 8.0135, Lng: 80...', '2026-07-05 00:00:00', 'Approved', 'Lat: 8.3343, Lng: 80.5878', 'Lat: 8.0135, Lng: 80.7966', 522.04, 86136.44, 1, 1, '2026-07-05', '2026-07-06', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(115, 20, 0, 'No Package Selected', 'No Package Selected', '2026-07-17 00:00:00', 'Approved', 'Lat: 8.1093, Lng: 80.4571', 'Lat: 8.4923, Lng: 80.4460', 8241.01, 13185622.05, 4, 5, '2026-07-17', '2026-07-21', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(116, 20, 0, 'Trip from Lat: 8.2964, Lng: 80... to Lat: 7.9211, Lng: 80...', 'Trip from Lat: 8.2964, Lng: 80... to Lat: 7.9211, Lng: 80...', '2026-07-07 00:00:00', 'Approved', 'Lat: 8.2964, Lng: 80.1806', 'Lat: 7.9211, Lng: 80.9002', 23755.03, 99999999.99, 19, 3, '2026-07-07', '2026-07-26', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(117, 20, 0, 'Trip from Lat: 8.2964, Lng: 80... to Lat: 8.0517, Lng: 80...', 'Trip from Lat: 8.2964, Lng: 80... to Lat: 8.0517, Lng: 80...', '2026-07-07 00:00:00', 'Approved', 'Lat: 8.2964, Lng: 80.4223', 'Lat: 8.0517, Lng: 80.5761', 998.38, 164732.20, 1, 1, '2026-07-07', '2026-07-08', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(118, 20, 8, 'Deep Sea Diving Adventure', 'Deep Sea Diving Adventure', '2026-07-07 00:00:00', 'Approved', '7.367118,80.739084', 'a,a', 0.00, 599.00, 1, 1, '2026-07-07', '2026-07-08', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(119, 20, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-07 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, '2026-07-07', '2026-07-08', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(120, 20, 0, 'Trip from Lat: 8.2394, Lng: 80... to Lat: 7.9838, Lng: 80...', 'Trip from Lat: 8.2394, Lng: 80... to Lat: 7.9838, Lng: 80...', '2026-07-07 00:00:00', 'Approved', 'Lat: 8.2394, Lng: 80.3508', 'Lat: 7.9838, Lng: 80.6090', 6626.64, 34193476.64, 4, 3, '2026-07-07', '2026-07-11', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(122, 32, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-12 00:00:00', 'Approved', 'Hunupola, Attanagalla, Gampaha District, Western Province, 11054, Sri Lanka', 'Govt. Seed Potato Farm, Meepilimana, Nuwara Eliya District, Central Province, 10000, Sri Lanka', 136.75, 19578.77, 1, 1, '2026-07-12', '2026-07-13', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(126, 32, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-12 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, '2026-07-12', '2026-07-13', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(127, 32, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-12 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, '2026-07-12', '2026-07-13', 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(128, 20, 24, 'ff tour', 'ff tour', '2026-07-13 00:00:00', 'Approved', '6.941706,80.807968', '7.2906,80.6337', 0.00, 3896.91, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(129, 12, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-13 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(130, 20, 24, 'ff tour', 'ff tour', '2026-07-14 00:00:00', 'Approved', '6.941706,80.807968', '7.2906,80.6337', 0.00, 3896.91, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(131, 12, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-15 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(132, 12, 8, 'Deep Sea Diving Adventure', 'Deep Sea Diving Adventure', '2026-07-15 00:00:00', 'Approved', '7.367118,80.739084', 'a,a', 0.00, 599.00, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(133, 12, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-17 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(134, 12, 0, 'amma', 'amma', '2026-07-17 00:00:00', 'Approved', 'Lat: 8.3008, Lng: 80.6373', 'Lat: 8.2029, Lng: 80.7856', 1012.69, 167093.14, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(135, 12, 8, 'Deep Sea Diving Adventure', 'Deep Sea Diving Adventure', '2026-07-17 00:00:00', 'Approved', '7.367118,80.739084', 'a,a', 0.00, 599.00, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(136, 12, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-17 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(137, 32, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-17 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(138, 32, 24, 'ff tour', 'ff tour', '2026-07-17 00:00:00', 'Approved', '6.941706,80.807968', '7.2906,80.6337', 0.00, 3896.91, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(139, 31, 25, 'ggggggggggg', 'ggggggggggg', '2026-07-17 00:00:00', 'Approved', '6.9271,79.8612', '6.821727,81.335311', 0.00, 19578.77, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(140, 31, 0, 'appa', 'appa', '2026-07-17 00:00:00', 'Approved', 'Lat: 8.3862, Lng: 80.5109', 'Lat: 8.1198, Lng: 80.6922', 1032.29, 170328.44, 1, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00),
(141, 31, 0, 'Custom Tour Plan', 'Custom Tour Plan', '2026-07-17 00:00:00', 'Approved', 'Lat: 7.5422, Lng: 80.4230', 'Lat: 7.3515, Lng: 80.7856', 688.80, 2412161.15, 1, 20, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `contact_feedbacks`
--

CREATE TABLE `contact_feedbacks` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_reply` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_feedbacks`
--

INSERT INTO `contact_feedbacks` (`id`, `name`, `email`, `subject`, `message`, `submitted_at`, `admin_reply`) VALUES
(1, 'dileepa', 'kulukulu@gmail.com', 'sa', 'sadasdasdasdadasdasdasd', '2026-05-19 18:46:32', 'adwdadadad'),
(2, 'dileepa', '1234@gmail.com', 'wad', 'wdadadawdawdawdawdwda', '2026-05-19 18:49:18', 'wawdawda'),
(4, 'dileepa dilshan', 'dileepadilshan46@gmail.com', 'game eka gana', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '2026-06-19 16:59:36', 'attatama niyamayi'),
(5, 'dileepa dilshan', 'gedara@gmail.com', 'game eka gana', 'a', '2026-06-19 17:08:39', 'niyamayi'),
(6, 'dileepa dilshan', 'gedara@gmail.com', 'game', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '2026-06-19 17:36:29', 'fffffffffffffffffffffff'),
(7, 'dileepa dilshan', 'dileepadilshan46@gmail.com', 'aaaaaaaaaaaaaa', 'aaaaaaaaaaaaaaaaa', '2026-06-20 05:32:26', 'aaaaaaaaaaaaaaaa'),
(8, 'bak', 'dileepadilshan46@gmail.com', 'nikam', 'aaaaaaaaaaaaaaaaaaaaaaaa', '2026-06-22 13:23:31', 'aaaaaaaaaa'),
(9, '', 'gedara@gmail.com', 'Re: game', 'aaaa', '2026-06-30 15:38:27', NULL),
(10, '', 'gedara@gmail.com', 'Re: Re: game', 'aaaa', '2026-06-30 15:38:37', NULL),
(11, '', 'gedara@gmail.com', 'Re: game eka gana', 'ok\\r\\n', '2026-06-30 15:38:56', ''),
(12, '', 'gedara@gmail.com', 'Re: game', 'b', '2026-07-07 20:13:27', NULL),
(13, 'dileepa dilshan', 'bak@gmail.com', 'game', 'jhgjhjhjhg', '2026-07-17 16:38:21', 'kjdkjbzdkjbszkd');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('available','on_duty','on_leave','inactive') DEFAULT 'available',
  `assigned_vehicle_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `phone`, `license_number`, `license_expiry`, `address`, `status`, `assigned_vehicle_id`, `created_at`, `updated_at`) VALUES
(1, 'Kamal Perera', '071-1234567', 'SL-12345', '2027-06-15', '123, Galle Road, Colombo 03', 'available', NULL, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(2, 'Nimal Fernando', '072-2345678', 'SL-23456', '2026-08-20', '45, Kandy Road, Kandy', 'on_duty', 1, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(3, 'Sunil Jayawardena', '073-3456789', 'SL-34567', '2028-01-10', '78, Negombo Road, Negombo', 'available', NULL, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(4, 'Chandana Silva', '074-4567890', 'SL-45678', '2025-12-05', '56, Matara Road, Galle', 'on_leave', NULL, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(5, 'Ranjith Kumara', '075-5678901', 'SL-56789', '2027-09-25', '23, Peradeniya Road, Peradeniya', 'available', NULL, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(6, 'Lalith Rathnayake', '076-6789012', 'SL-67890', '2026-11-18', '12, Anuradhapura Road, Anuradhapura', 'on_duty', 2, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(7, 'Priyantha Bandara', '077-7890123', 'SL-78901', '2025-07-30', '34, Badulla Road, Badulla', 'inactive', NULL, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(8, 'Saman Weerasinghe', '078-8901234', 'SL-89012', '2028-03-22', '67, Jaffna Road, Vavuniya', 'available', NULL, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(9, 'Mahesh Dissanayake', '079-9012345', 'SL-90123', '2026-10-12', '89, Kurunegala Road, Kurunegala', 'on_leave', NULL, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(10, 'Ruwan Wickramasinghe', '070-0123456', 'SL-01234', '2025-05-01', '101, Hambantota Road, Hambantota', 'available', 3, '2026-07-12 14:55:52', '2026-07-12 14:55:52'),
(11, 'Kamal Perera', '071-1234567', 'SL-12345', '2027-06-15', '123, Galle Road, Colombo 03', 'available', NULL, '2026-07-12 14:55:53', '2026-07-12 14:55:53'),
(12, 'Nimal Fernando', '072-2345678', 'SL-23456', '2026-08-20', '45, Kandy Road, Kandy', 'on_duty', 1, '2026-07-12 14:55:53', '2026-07-12 14:55:53'),
(13, 'Sunil Jayawardena', '073-3456789', 'SL-34567', '2028-01-10', '78, Negombo Road, Negombo', 'available', NULL, '2026-07-12 14:55:53', '2026-07-12 14:55:53'),
(14, 'Chandana Silva', '074-4567890', 'SL-45678', '2025-12-05', '56, Matara Road, Galle', 'on_leave', NULL, '2026-07-12 14:55:53', '2026-07-12 14:55:53'),
(15, 'Ranjith Kumara', '075-5678901', 'SL-56789', '2027-09-25', '23, Peradeniya Road, Peradeniya', 'available', NULL, '2026-07-12 14:55:53', '2026-07-12 14:55:53'),
(16, 'Lalith Rathnayake', '076-6789012', 'SL-67890', '2026-11-18', '12, Anuradhapura Road, Anuradhapura', 'on_duty', 2, '2026-07-12 14:55:53', '2026-07-12 14:55:53'),
(17, 'Priyantha Bandara', '077-7890123', 'SL-78901', '2025-07-30', '34, Badulla Road, Badulla', 'inactive', 20, '2026-07-12 14:55:53', '2026-07-17 19:25:57'),
(18, 'Saman Weerasinghe', '078-8901234', 'SL-89012', '2028-03-22', '67, Jaffna Road, Vavuniya', 'available', NULL, '2026-07-12 14:55:53', '2026-07-12 14:55:53'),
(19, 'Mahesh Dissanayake', '079-9012345', 'SL-90123', '2026-10-12', '89, Kurunegala Road, Kurunegala', 'on_leave', NULL, '2026-07-12 14:55:53', '2026-07-12 14:55:53'),
(20, 'Ruwan Wickramasinghe', '070-0123456', 'SL-01234', '2025-05-01', '101, Hambantota Road, Hambantota', 'available', 3, '2026-07-12 14:55:53', '2026-07-12 14:55:53');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(191) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `name`, `email`, `message`, `sent_at`) VALUES
(1, 'Dilan Perera', 'dilan@gmail.com', 'Hi, I would like to know more about the Sigiriya tour package pricing.', '2026-05-19 04:45:00'),
(2, 'Shani Silva', 'shani95@gmail.com', 'Do you provide hotel pickup and drop services for the Ella day tour?', '2026-05-19 07:00:45'),
(3, 'John Doe', 'johndoe@yahoo.com', 'Hello! Is it possible to customize a 3-day trip to Nuwara Eliya?', '2026-05-19 08:52:10'),
(4, 'Nirmala Fernando', 'nirmala.f@outlook.com', 'I tried to book a tour but the payment got failed. Please check.', '2026-05-19 10:35:18'),
(5, 'Alex Watson', 'alexw@gmail.com', 'Excellent service! Our family really enjoyed the Yala safari tour.', '2026-05-19 13:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operators`
--

CREATE TABLE `operators` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `operators`
--

INSERT INTO `operators` (`id`, `name`, `email`, `password`, `phone`, `status`, `created_at`) VALUES
(1, 'Kamal Perera', 'kamal@travel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0771234561', 'active', '2026-07-08 03:31:23'),
(2, 'Nimal Fernando', 'nimal@travel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0771234562', 'active', '2026-07-08 03:31:23'),
(3, 'Sunil Jayawardena', 'sunil@travel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0771234563', 'active', '2026-07-08 03:31:23');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `latitude2` varchar(50) DEFAULT NULL,
  `longitude2` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `icon` varchar(50) DEFAULT '?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `vehicle_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `image` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `title`, `description`, `latitude`, `longitude`, `latitude2`, `longitude2`, `price`, `icon`, `created_at`, `vehicle_id`, `status`, `image`) VALUES
(6, 'Wild Wildlife Safari', 'Get up close with leopards, wild elephants, and exotic birds in Yala National Park with an expert guide.', NULL, NULL, NULL, NULL, 449.00, '🐆', '2026-05-19 14:06:00', NULL, 'Deleted', NULL),
(8, 'Deep Sea Diving Adventure', 'Discover the breathtaking coral reefs, exotic marine life, and hidden shipwrecks in Trincomalee.', '7.367118', '80.739084', 'a', 'a', 599.00, '🤿', '2026-05-19 14:08:00', NULL, 'Deleted', 'uploads/packages/package_1784353514_6a5b12eaab484.jpg'),
(23, 'aaaaaaaaaaaaa', 'a', '6.9271', '79.8612', '7.2906', '80.6337', 11320.25, '🌍', '2026-06-20 04:17:16', NULL, 'Deleted', NULL),
(24, 'sigiriya tour', 'awdkmdoamwdo', '6.941706', '80.807968', '7.2906', '80.6337', 5195.88, '🌍', '2026-06-20 04:35:52', NULL, 'Deleted', 'uploads/packages/package_1784353530_6a5b12fa9c817.jpg'),
(25, 'colombo tour', 'meka maru..', '6.9271', '79.8612', '6.821727', '81.335311', 19578.77, '🌍', '2026-06-20 04:36:21', NULL, 'Deleted', 'uploads/packages/package_1784353547_6a5b130b35645.jpg'),
(26, 'aaaaaaaaaaaaa', 'aaaaaaaaaaaaaaaa', '6.9271', '79.8612', '7.2906', '80.6337', 11320.25, '🌍', '2026-06-20 05:29:29', NULL, 'Deleted', NULL),
(27, 'a', 'a', '10.427321', '77.918563', '7.2906', '80.6337', 55071.50, '🌍', '2026-06-22 14:08:26', NULL, 'Deleted', NULL),
(28, 'travl new ', 'supiri athdakimak ganna puluwan.', '8.375727', '80.581262', '7.2906', '80.6337', 14495.86, '🌍', '2026-07-18 04:52:25', NULL, 'Deleted', 'uploads/packages/package_1784353560_6a5b1318e0059.jpg'),
(29, 'colombo new', 'aaaaaaaaaaaaaaaaa', '6.789621', '80.518013', '7.236689', '80.935493', 8133.56, '🌍', '2026-07-18 05:35:57', NULL, 'Deleted', 'uploads/packages/package_1784353056_6a5b11204bffb.jpg'),
(30, 'colombo pakage 1', 'matara to colombo is amazing.', '6.9271', '79.8612', '5.924963', '80.539986', 16118.71, '🌍', '2026-07-18 05:57:51', NULL, 'Deleted', 'uploads/packages/package_1784369198_6a5b502e99c17.jpg'),
(31, 'colombo tour 2', 'amazing tour', '6.9271', '79.8612', '7.2906', '80.6337', 11320.25, '🌍', '2026-07-18 06:10:41', NULL, 'Active', 'uploads/packages/package_1784355041_6a5b18e17bd91.jpg'),
(32, 'aaaaaaaaaaaaaaa', 'qqqqqqq', '6.9271', '79.8612', '7.2906', '80.6337', 11320.25, '🌍', '2026-07-18 09:29:35', NULL, 'Active', 'uploads/packages/package_1784369967_6a5b532fc81a6.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(3, 'test@demo.com', 'valid_token_2026_3', '2027-07-05 12:06:59', '2026-07-05 06:36:59');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `package_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `card_name` varchar(255) NOT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL,
  `payment_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `package_id`, `amount`, `card_name`, `transaction_id`, `status`, `payment_date`) VALUES
(1, 0, 1, 0.00, 'Test', 'TXN123', 'Success', '2026-05-25 00:30:19'),
(2, 0, 1, 0.00, 'Test', 'TXN123', 'Success', '2026-05-25 00:30:28'),
(3, 0, 1, 499.00, 'Customer', 'TXN-6A134BA3469C5', 'Success', '2026-05-25 00:34:03'),
(4, 0, 1, 499.00, 'Client', 'TXN-6A134C64F2895', 'Success', '2026-05-25 00:37:16'),
(5, 0, 1, 499.00, 'Client', 'TXN-6A134C77244D3', 'Success', '2026-05-25 00:37:35');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` text NOT NULL,
  `log_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `log_date`) VALUES
(1, 24, 'User 24 successfully created booking #44 for package \'ags\'', '2026-06-17 19:43:05'),
(2, 24, 'User 24 successfully created booking #45 for package \'ags\'', '2026-06-17 19:43:07'),
(3, 24, 'User 24 successfully created booking #46 for package \'ags\'', '2026-06-17 19:43:48'),
(4, 24, 'User 24 successfully created booking #47 for package \'ags\'', '2026-06-17 19:47:58'),
(5, 24, 'User 24 successfully created booking #48 for package \'aaaaaaaaaaaaa\'', '2026-06-17 19:48:41'),
(6, 24, 'User 24 successfully created booking #49 for package \'aaaaaaaaaaaaa\'', '2026-06-17 19:50:32'),
(7, 24, 'User 24 successfully created booking #50 for package \'aaaaaaaaaaaaa\'', '2026-06-17 19:53:13'),
(8, 24, 'User 24 successfully created booking #51 for package \'aaaaaaaaaaaaa\'', '2026-06-17 19:54:59'),
(9, 24, 'User 24 successfully created booking #52 for package \'kamal\'', '2026-06-17 19:55:28'),
(10, 24, 'User 24 successfully created booking #53 for package \'gas\'', '2026-06-17 19:59:40'),
(11, 24, 'User 24 successfully created booking #54 for package \'gggggggggggg\'', '2026-06-17 20:00:05'),
(12, 24, 'User 24 booked package \'gggggggggggggg\'', '2026-06-17 20:02:31'),
(13, 24, 'User 24 booked package \'Mountain Adventure\'', '2026-06-17 20:04:25'),
(14, 24, 'User 24 booked package \'ggggggggggggggggggggggg\'', '2026-06-17 20:05:45'),
(15, 24, 'User 24 booked package \'g\'', '2026-06-17 20:07:17'),
(16, 24, 'User 24 booked package \'afd\'', '2026-06-17 20:11:01'),
(17, 24, 'User 24 booked package \'Mountain Adventure\'', '2026-06-17 20:57:38'),
(18, 20, 'User 20 booked package \'asad\'', '2026-06-18 21:24:01'),
(19, 20, 'User 20 booked package \'gggg\'', '2026-06-18 21:34:43'),
(20, 20, 'User 20 booked package \'fffff\'', '2026-06-18 21:39:01'),
(21, 20, 'User 20 booked package \'fffffffffffffffffffffffffffff\'', '2026-06-18 21:39:20'),
(22, 20, 'User 20 booked package \'awdadadadad\'', '2026-06-18 21:42:26'),
(23, 20, 'User 20 booked package \'Tropical Beach Escape\'', '2026-06-18 21:47:53'),
(24, 20, 'User 20 booked package \'sdd\'', '2026-06-18 21:50:40'),
(25, 20, 'User 20 booked package \'sdd\'', '2026-06-18 21:51:08'),
(26, 20, 'User 20 booked package \'Tropical Beach Escape\'', '2026-06-18 21:52:53'),
(27, 20, 'User 20 booked package \'fafawfaf\'', '2026-06-18 21:55:40'),
(28, 20, 'User 20 booked package \'dawadawd\'', '2026-06-18 21:58:45'),
(29, 20, 'User 20 booked package \'mkkk\'', '2026-06-18 22:02:27'),
(30, 20, 'User 20 booked package \'sadasdd\'', '2026-06-18 22:07:42'),
(31, 20, 'User 20 booked package \'amma\'', '2026-06-18 22:11:18'),
(32, 20, 'User 20 booked package \'Tropical Beach Escape\'', '2026-06-18 23:23:02'),
(33, 18, 'User 18 booked package \'Tropical Beach Escape\'', '2026-06-18 23:40:48'),
(34, 24, 'User 24 booked package \'Tropical Beach Escape\'', '2026-06-19 13:04:29'),
(35, 20, 'User 20 booked package \'Tropical Beach Escape\'', '2026-06-19 21:25:28'),
(36, 18, 'User 18 booked package \'Tropical Beach Escape\'', '2026-06-19 22:28:13'),
(37, 20, 'User 20 booked package \'Tropical Beach Escape\'', '2026-06-19 23:44:34'),
(38, 20, 'User 20 booked package \'Wild Wildlife Safari\'', '2026-06-20 10:20:30'),
(39, 20, 'User 20 booked package \'Wild Wildlife Safari\'', '2026-06-20 10:20:36'),
(40, 20, 'User 20 booked package \'aaaaaaaaaa\'', '2026-06-20 10:57:35'),
(41, 20, 'User 20 booked package \'Wild Wildlife Safari\'', '2026-06-20 11:04:10'),
(42, 20, 'User 20 booked package \'Wild Wildlife Safari\'', '2026-06-20 11:25:07'),
(43, 18, 'User 18 booked package \'Wild Wildlife Safariaaaaaaaaaaaaaaaaaaa\'', '2026-06-20 11:33:56'),
(44, 18, 'User 18 booked package \'Wild Wildlife Safari\'', '2026-06-20 11:49:36'),
(45, 22, 'User 22 booked package \'paka\'', '2026-06-22 18:50:07'),
(46, 20, 'User 20 booked package \'Deep Sea Diving Adventure\' (#89)', '2026-06-22 21:48:56'),
(47, 20, 'User 20 booked package \'Deep Sea Diving Adventure\' (#90)', '2026-06-23 10:28:58'),
(48, 20, 'User 20 booked package \'Deep Sea Diving Adventure\' for 1 days (4 members) (#91)', '2026-06-23 12:15:16'),
(49, 20, 'User 20 booked package \'ggggggggggg\' for 1 days (1 members) (#92)', '2026-06-24 00:31:15'),
(50, 20, 'User 20 booked package \'ggggggggggg\' for 1 days (1 members) (#93)', '2026-06-24 00:32:52'),
(51, 20, 'User 20 booked package \'ggggggggggg\' for 1 days (1 members) (#94)', '2026-06-24 09:17:59'),
(52, 20, 'User 20 booked package \'ggggggggggg\' for 1 days (1 members) (#95)', '2026-06-24 09:54:44'),
(53, 20, 'User 20 booked package \'No Package Selected\' for 1 days (1 members) (#96)', '2026-06-24 10:43:33'),
(54, 20, 'User 20 booked package \'No Package Selected\' for 1 days (1 members) (#97)', '2026-06-24 11:59:31'),
(55, 20, 'User 20 booked package \'No Package Selected\' for 1 days (1 members) (#98)', '2026-06-25 12:19:53'),
(56, 20, 'User 20 booked package \'No Package Selected\' for 4 days (4 members) (#99)', '2026-06-25 12:20:26'),
(57, 20, 'User 20 booked package \'No Package Selected\' for 1 days (1 members) (#100)', '2026-06-25 21:41:42'),
(58, 29, 'User 29 booked package \'No Package Selected\' for 1 days (1 members) (#101)', '2026-06-26 22:30:24'),
(59, 20, 'User 20 booked package \'Trip from Lat: 8.3114, Lng: 80... to Lat: 8.0178, Lng: 80...\' for 5 days (1 members) (#102)', '2026-06-28 19:43:51'),
(60, 20, 'User 20 booked package \'No Package Selected\' for 92 days (1 members) (#103)', '2026-06-28 20:07:14'),
(61, 20, 'User 20 booked package \'Trip from Lat: 8.5037, Lng: 77... to Lat: 8.1341, Lng: 80...\' for 2 days (1 members) (#104)', '2026-06-28 20:16:52'),
(62, 20, 'User 20 booked package \'ggggggggggg\' directly (#105)', '2026-06-28 20:17:14'),
(63, 20, 'User 20 booked package \'No Package Selected\' for 1 days (1 members) (#106)', '2026-06-28 20:23:36'),
(64, 20, 'User 20 booked package \'ggggggggggg\' directly (#107)', '2026-06-28 20:24:47'),
(65, 20, 'User 20 booked package \'No Package Selected\' for 1 days (1 members) (#108)', '2026-06-28 20:30:26'),
(66, 20, 'User 20 booked package \'Trip from Lat: 8.6042, Lng: 80... to Lat: 7.7669, Lng: 80...\' for 1 days (10 members) (#109)', '2026-06-29 20:34:01'),
(67, 20, 'User 20 booked package \'ggggggggggg\' directly (#110)', '2026-06-29 20:37:21'),
(68, 20, 'User 20 booked package \'Trip from Lat: 8.2203, Lng: 80... to Lat: 7.7906, Lng: 80...\' for 1 days on 2026-06-29 (1 members) (#111)', '2026-06-29 23:10:41'),
(69, 20, 'User 20 booked package \'Trip from Lat: 8.4249, Lng: 80... to Lat: 8.0335, Lng: 80...\' for 1 days on 2026-06-29 (1 members) (#112)', '2026-06-29 23:45:47'),
(70, 20, 'User 20 booked package \'amal.\' for 1 days on 2026-07-01 (4 members) (#113)', '2026-07-01 10:13:06'),
(71, 18, 'User 18 booked package \'Trip from Lat: 8.3343, Lng: 80... to Lat: 8.0135, Lng: 80...\' for 1 days on 2026-07-05 (1 members) (#114)', '2026-07-05 14:27:35'),
(72, 20, 'User 20 booked package \'No Package Selected\' for 4 days on 2026-07-17 (4 members) (#115)', '2026-07-07 22:29:36'),
(73, 20, 'User 20 booked package \'Trip from Lat: 8.2964, Lng: 80... to Lat: 7.9211, Lng: 80...\' for 19 days on 2026-07-07 (6 members) (#116)', '2026-07-07 22:44:37'),
(74, 20, 'User 20 booked package \'Trip from Lat: 8.2964, Lng: 80... to Lat: 8.0517, Lng: 80...\' for 1 days on 2026-07-07 (1 members) (#117)', '2026-07-07 23:31:56'),
(75, 20, 'User 20 booked package \'Deep Sea Diving Adventure\' directly (#118)', '2026-07-08 01:09:01'),
(76, 20, 'User 20 booked package \'ggggggggggg\' directly (#119)', '2026-07-08 01:19:39'),
(77, 20, 'User 20 booked package \'Trip from Lat: 8.2394, Lng: 80... to Lat: 7.9838, Lng: 80...\' for 4 days on 2026-07-07 (3 members) (#120)', '2026-07-08 01:20:33'),
(78, 32, 'User 32 booked package \'Trip from Lat: 8.2170, Lng: 80... to Lat: 7.8907, Lng: 80...\' for 2 days on 2026-07-15 (4 members) (#121)', '2026-07-12 12:34:52'),
(79, 32, 'User 32 booked package \'ggggggggggg\' directly (#122)', '2026-07-12 13:13:03'),
(80, 32, 'User 32 booked package \'aaaa\' for 1 days on 2026-07-12 (1 members) (#123)', '2026-07-12 14:55:12'),
(81, 32, 'User 32 booked package \'ff tour\' directly (#124)', '2026-07-12 15:13:07'),
(82, 32, 'User 32 booked package \'ff tour\' directly (#125)', '2026-07-12 15:13:35'),
(83, 32, 'User 32 booked package \'ggggggggggg\' directly (#126)', '2026-07-12 15:18:43'),
(84, 32, 'User 32 booked package \'ggggggggggg\' directly (#127)', '2026-07-12 15:26:56'),
(85, 20, 'User 20 booked package \'ff tour\' directly (#128)', '2026-07-13 14:50:07'),
(86, 12, 'User 12 booked package \'ggggggggggg\' directly (#129)', '2026-07-13 20:52:13'),
(87, 20, 'User 20 booked package \'ff tour\' directly (#130)', '2026-07-14 10:34:46'),
(88, 12, 'User 12 booked package \'ggggggggggg\' directly (#131)', '2026-07-15 15:16:47'),
(89, 12, 'User 12 booked package \'Deep Sea Diving Adventure\' directly (#132)', '2026-07-15 15:32:56'),
(90, 12, 'User 12 booked package \'ggggggggggg\' directly (#133)', '2026-07-17 20:44:59'),
(91, 12, 'User 12 booked package \'amma\' for 1 days on 2026-07-17 (1 members) (#134)', '2026-07-17 20:47:13'),
(92, 12, 'User 12 booked package \'Deep Sea Diving Adventure\' directly (#135)', '2026-07-17 20:56:35'),
(93, 12, 'User 12 booked package \'ggggggggggg\' directly (#136)', '2026-07-17 21:25:15'),
(94, 32, 'User 32 booked package \'ggggggggggg\' directly (#137)', '2026-07-17 21:31:46'),
(95, 32, 'User 32 booked package \'ff tour\' directly (#138)', '2026-07-17 21:32:48'),
(96, 31, 'User 31 booked package \'ggggggggggg\' directly (#139)', '2026-07-17 21:33:55'),
(97, 31, 'User 31 booked package \'appa\' for 1 days on 2026-07-17 (1 members) (#140)', '2026-07-17 21:35:51'),
(98, 31, 'User 31 booked package \'Custom Tour Plan\' for 1 days on 2026-07-17 (1 members) (#141)', '2026-07-18 00:28:38');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `address`, `password`, `role`, `created_at`) VALUES
(20, 'oafe', 'gedara@gmail.com', NULL, NULL, '$2y$10$MFmvojgGPnkA4B3AhcGmZuDfYhVWSBd9dHNaEEFlSDhRF4TFv3kTy', 'user', '2026-06-16 13:47:52'),
(25, 'kaka', 'kaka@gmail.com', NULL, NULL, '$2y$10$HTED5sQI4z6RRKGL92cofOAXcmO9AptRfyQf6/Yqk1YY7GMOCHLvG', 'user', '2026-06-20 03:34:54'),
(27, 'dileepa', 'dileepa@gmail.com', NULL, NULL, '$2y$10$KI5AM2IUKTOCG9j/58y3xeF8Du3PE0I3lLo5JCYlpvqrGqe/tkRjS', 'admin', '2026-06-22 13:44:38'),
(28, 'usernima', 'nimauu@gmail.com', NULL, NULL, '$2y$10$9U1qrChF3X2Lt1vzAWmj7.GwGZmBcFofAjOo/atNcdPMXI2/v3mTW', 'user', '2026-06-22 13:54:49'),
(29, 'pubudu sathsara', 'pubu@gmail.com', NULL, NULL, '$2y$10$qlUqBJNk7oMIm1fhs871iOcpLF4DYp2CA/O8a6/t/xnpio8r26bdy', 'user', '2026-06-26 16:56:09'),
(31, 'bak', 'bak@gmail.com', NULL, NULL, '$2y$10$9KqDtiWVU4CVywdBQkkyu.gxtIuij8trbuMz46WpDYrTr4378cOH6', 'user', '2026-07-11 06:40:33'),
(32, 'kuluni', 'kuluni@gmail.com', NULL, NULL, '$2y$10$v6Ied68/RtbB/itnH5cxIug701cwFL177OZixndBVTKQBrjqAEZUq', 'user', '2026-07-12 07:02:24'),
(33, 'ruwan', 'ruwan@gmail.com', NULL, NULL, '$2y$10$VxK1yps9NFJ7ZHFaUzzq2O0qqWpfYubi1DmE0nNgQuBNfza3JMSe2', 'user', '2026-07-17 19:33:23');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `vehicle_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT 'Sedan',
  `price_per_km` decimal(10,2) NOT NULL,
  `petrol_rate` decimal(10,2) NOT NULL DEFAULT 50.00,
  `capacity` int(11) NOT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `status` enum('Available','On Trip','Maintenance','Inactive') DEFAULT 'Available',
  `driver_name` varchar(100) DEFAULT NULL,
  `driver_phone` varchar(20) DEFAULT NULL,
  `last_lat` decimal(10,8) DEFAULT NULL,
  `last_lng` decimal(11,8) DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `registration_expiry` date DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `fuel_type` varchar(20) DEFAULT 'Petrol',
  `transmission` varchar(20) DEFAULT 'Manual',
  `driver_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `vehicle_name`, `category`, `price_per_km`, `petrol_rate`, `capacity`, `operator_id`, `status`, `driver_name`, `driver_phone`, `last_lat`, `last_lng`, `last_update`, `next_maintenance`, `insurance_expiry`, `registration_expiry`, `last_service_date`, `fuel_type`, `transmission`, `driver_id`, `created_at`) VALUES
(1, 'Sedan Car (Toyota/Axio)', 'Sedan', 120.00, 45.00, 4, 1, 'Available', 'Kamal Perera', '071-2345678', 6.92470000, 79.85480000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(2, 'Tourist Passenger Van (KDH)', 'Sedan', 180.00, 60.00, 10, 2, 'Available', 'Saman Kumara', '072-3456789', 6.92280000, 79.86310000, '2026-07-18 00:13:16', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', 1, '2026-07-12 17:18:44'),
(3, 'Luxury SUV (Prado/Montero)', 'Sedan', 350.00, 80.00, 6, 3, 'Available', 'Mahesh Gunasekara', '071-9012345', 6.92140000, 79.86460000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(4, 'Compact Car (Toyota Vitz)', 'Sedan', 90.00, 35.00, 4, 1, 'Available', 'Nimal Silva', '077-8901234', 6.93580000, 79.86850000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(5, 'Budget Hatchback (Suzuki Alto)', 'Sedan', 70.00, 30.00, 4, 2, 'Maintenance', 'Lalith Fernando', '078-9012345', 6.92270000, 79.86460000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(6, 'Minivan (Toyota Noah)', 'Sedan', 150.00, 55.00, 7, 3, 'Available', 'Anura Dissanayake', '077-0123456', 6.91770000, 79.86580000, '2026-07-13 00:10:44', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(7, 'Medium Bus (Toyota Coaster)', 'Sedan', 300.00, 100.00, 20, 1, '', 'Sunil Jayawardena', '075-4567890', 6.92980000, 79.86570000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(8, 'Premium Sedan (Mercedes Benz E-Class)', 'Sedan', 450.00, 50.00, 4, 2, '', 'Ruwan Wickramasinghe', '071-7890123', 6.91910000, 79.85590000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(9, 'Off-road 4x4 (Land Rover Defender)', 'Sedan', 400.00, 70.00, 5, 3, 'Available', 'Ranjith Bandara', '076-1234567', 6.91730000, 79.85420000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(10, 'Luxury Limousine', 'Sedan', 1200.00, 90.00, 8, 1, 'Available', 'Chaminda Rathnayake', '076-5678901', 6.92540000, 79.86250000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(11, 'Standard Van (Toyota HiAce)', 'Sedan', 140.00, 40.00, 10, 2, 'Available', 'Priyantha Perera', '072-8901234', 6.92250000, 79.85580000, '2026-07-06 14:34:05', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(14, 'Electric SUV (Tesla Model X)', 'Sedan', 380.00, 65.00, 5, 1, '', 'Tharanga Wijesinghe', '075-2345678', 6.92250000, 79.85490000, '2026-07-17 22:19:56', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(15, 'Large Coach Bus', 'Sedan', 500.00, 120.00, 45, 1, 'Maintenance', 'Bandula Weerasinghe', '071-6789012', 6.92110000, 79.86280000, '2026-07-08 14:33:51', '2026-07-22', NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(16, 'SUV (Toyota Fortuner)', 'Sedan', 250.00, 55.00, 7, NULL, 'Available', NULL, NULL, 6.91850000, 79.85670000, '2026-07-08 14:33:51', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(17, 'Luxury Van (Toyota Alphard)', 'Sedan', 350.00, 65.00, 8, NULL, 'Available', NULL, NULL, 6.92010000, 79.86030000, '2026-07-08 14:33:51', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(18, 'Mini Bus (Nissan Urvan)', 'Sedan', 200.00, 75.00, 15, NULL, 'Maintenance', 'Dinesh Perera', '071-3456789', 6.92340000, 79.86780000, '2026-07-08 14:33:51', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(19, 'Sedan (Honda Civic)', 'Sedan', 110.00, 38.00, 4, NULL, 'Available', NULL, NULL, 6.92670000, 79.85210000, '2026-07-08 14:33:51', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-12 17:18:44'),
(20, 'ammma', 'Sedan', 3500.00, 2.00, 31, 1, '', 'dawdad', '', 0.00000000, 0.00000000, '2026-07-18 00:25:09', '2026-07-15', NULL, NULL, NULL, 'Petrol', 'Manual', 17, '2026-07-17 16:54:26'),
(21, 'ttaaaa', 'Sedan', 220.00, 0.00, 5, 1, '', '', '', 6.71930000, 79.98770000, '2026-07-18 00:13:56', NULL, NULL, NULL, NULL, 'Petrol', 'Manual', NULL, '2026-07-17 18:08:45');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_audit_logs`
--

CREATE TABLE `vehicle_audit_logs` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `old_data` text DEFAULT NULL,
  `new_data` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_audit_logs`
--

INSERT INTO `vehicle_audit_logs` (`id`, `vehicle_id`, `user_id`, `action`, `old_data`, `new_data`, `ip_address`, `created_at`) VALUES
(1, 0, 1, 'Added expense for vehicle 5', NULL, NULL, '::1', '2026-07-13 16:22:36'),
(2, 0, 1, 'Updated maintenance for vehicle 15', NULL, NULL, '::1', '2026-07-13 16:22:55'),
(3, 14, 1, 'full_update', NULL, 'Name: Electric SUV (Tesla Model X), Price: 380, Capacity: 5, Operator: 1, Status: Booked, Driver: NULL', '::1', '2026-07-17 16:49:56'),
(4, 2, 1, 'full_update', NULL, 'Name: Tourist Passenger Van (KDH), Price: 180, Capacity: 10, Operator: 2, Status: Available, Driver: 1', '::1', '2026-07-17 18:43:16'),
(5, 20, 1, 'full_update', NULL, 'Name: ammma, Price: 3500, Capacity: 31, Operator: 1, Status: Available, Driver: NULL', '::1', '2026-07-17 18:43:36'),
(6, 21, 1, 'full_update', NULL, 'Name: ttaaaa, Price: 220, Capacity: 5, Operator: 1, Status: , Driver: NULL', '::1', '2026-07-17 18:43:56'),
(7, 20, 1, 'full_update', NULL, 'Name: ammma, Price: 3500, Capacity: 31, Operator: 1, Status: Booked, Driver: NULL', '::1', '2026-07-17 18:55:09');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_documents`
--

CREATE TABLE `vehicle_documents` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `doc_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_expenses`
--

CREATE TABLE `vehicle_expenses` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `type` enum('fuel','repair','insurance','tax','other') DEFAULT 'other',
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_expenses`
--

INSERT INTO `vehicle_expenses` (`id`, `vehicle_id`, `type`, `amount`, `expense_date`, `note`, `created_at`) VALUES
(1, 5, 'fuel', 566.00, '2026-07-13', 'dawdawdd', '2026-07-13 16:22:36'),
(2, 20, 'fuel', 10.00, '2026-07-17', 'jhbjhbj', '2026-07-17 16:58:09');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_manager`
--

CREATE TABLE `vehicle_manager` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager') DEFAULT 'manager',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_manager`
--

INSERT INTO `vehicle_manager` (`id`, `name`, `email`, `password`, `phone`, `role`, `status`, `last_login`, `created_at`) VALUES
(1, 'manager ranil', 'admin@vehicle.com', 'e180da5b6c2aeec0651715e329a79a539d27d2ea', '071-0000000', 'admin', 'active', '2026-07-08 14:57:05', '2026-07-08 09:13:57'),
(2, 'Manager Kumara', 'manager1@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-1111111', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(3, 'Manager Silva', 'manager2@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-2222222', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(4, 'Manager Perera', 'manager3@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-3333333', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(5, 'Manager Jayawardena', 'manager4@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-4444444', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(6, 'Manager Rathnayake', 'manager5@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-5555555', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(7, 'Manager Fernando', 'manager6@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-6666666', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(8, 'Manager Bandara', 'manager7@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-7777777', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(9, 'Manager Dissanayake', 'manager8@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-8888888', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(10, 'Manager Weerasinghe', 'manager9@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '071-9999999', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(11, 'Manager Gunawardena', 'manager10@vehicle.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', '070-1010101', 'manager', 'active', NULL, '2026-07-08 09:13:57'),
(12, 'vadmin', 'vadmin1@gmail.com', 'b1851733af3db3ea4f99b46fac4f4a18b706ed10', NULL, 'manager', 'active', NULL, '2026-07-11 07:20:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`AdminID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_feedbacks`
--
ALTER TABLE `contact_feedbacks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_vehicle_id` (`assigned_vehicle_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `operators`
--
ALTER TABLE `operators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_audit_logs`
--
ALTER TABLE `vehicle_audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_documents`
--
ALTER TABLE `vehicle_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_expenses`
--
ALTER TABLE `vehicle_expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_manager`
--
ALTER TABLE `vehicle_manager`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `AdminID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `contact_feedbacks`
--
ALTER TABLE `contact_feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `operators`
--
ALTER TABLE `operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `vehicle_audit_logs`
--
ALTER TABLE `vehicle_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `vehicle_documents`
--
ALTER TABLE `vehicle_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicle_expenses`
--
ALTER TABLE `vehicle_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vehicle_manager`
--
ALTER TABLE `vehicle_manager`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `drivers_ibfk_1` FOREIGN KEY (`assigned_vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `drivers_ibfk_2` FOREIGN KEY (`assigned_vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `drivers_ibfk_3` FOREIGN KEY (`assigned_vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
