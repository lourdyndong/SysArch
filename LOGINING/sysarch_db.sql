-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 24, 2026 at 04:44 AM
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
-- Database: `sysarch_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `course_level` int(11) DEFAULT 1,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `course` varchar(50) DEFAULT 'BSIT',
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_photo` varchar(255) DEFAULT 'register.png',
  `role` varchar(20) DEFAULT 'student',
  `remaining_sessions` int(11) DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `id_number`, `last_name`, `first_name`, `middle_name`, `course_level`, `email`, `password`, `course`, `address`, `created_at`, `profile_photo`, `role`, `remaining_sessions`) VALUES
(3, '21464755', 'verdida', 'lourdyn', 'mangyao', 1, 'po0tamo2@gmail.com', '$2y$10$avDs6HX8K7Xschd/K1p/cuzunTBw4YweaAVfM41gK8I/qE0SJAXPW', 'BSIT', 'tpadilla', '2026-03-13 00:52:12', '21464755_1773370779.jpg', 'student', 26),
(4, 'admin', 'Admin', 'Site', '', 0, 'admin', '$2y$10$x7GSC9oKiBi8md1AGgq6g.lX5UPkHvqrYNeH1lezZN.gx30cXR3de', '', '', '2026-03-13 03:14:48', 'register.png', 'admin', 30),
(5, '123456789', 'Burgadol', 'mechudas', 'chadchad', 4, 'chadchad@gmail.com', '$2y$10$RY40htLgBvk/LjthcY/TdeHzf5pxmbOEMf/YE64Z6WsnXRiCsa0aq', 'BSIT', 'Emile resides in Atlanta, Georgia', '2026-03-27 01:07:47', 'register.png', 'student', 29);

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `admin_name` varchar(100) DEFAULT 'CCS Admin',
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `admin_name`, `content`, `created_at`) VALUES
(1, 'CCS Admin', 'jeff pogi', '2026-03-13 04:14:58');

-- --------------------------------------------------------

--
-- Table structure for table `feedbacks`
--

CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL,
  `sit_in_id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `lab` varchar(100) NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `feedbacks`
--

INSERT INTO `feedbacks` (`id`, `sit_in_id`, `id_number`, `student_name`, `lab`, `rating`, `message`, `created_at`) VALUES
(2, 3, '21464755', 'lourdyn verdida', '544', 2, 'wtwrwrgssdg', '2026-04-24 09:26:17'),
(3, 4, '21464755', 'lourdyn verdida', '530 PC 13', 5, 'nasuko ang working', '2026-04-24 10:19:10');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `student_name` varchar(150) DEFAULT NULL,
  `lab` varchar(50) DEFAULT NULL,
  `purpose` varchar(200) DEFAULT NULL,
  `res_date` date DEFAULT NULL,
  `res_time` time DEFAULT NULL,
  `status` enum('pending','approved','declined') DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  `assigned_pc` varchar(50) DEFAULT NULL,
  `reservation_date` date DEFAULT NULL,
  `reservation_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `id_number`, `student_name`, `lab`, `purpose`, `res_date`, `res_time`, `status`, `created_at`, `assigned_pc`, `reservation_date`, `reservation_time`) VALUES
(1, '21464755', 'lourdyn verdida', '524', 'java', '2026-03-27', '11:30:00', 'declined', '2026-03-27 09:05:00', NULL, NULL, NULL),
(2, '123456789', 'mechudas Burgadol', '524', 'C', '2026-03-27', '10:30:00', 'approved', '2026-03-27 09:08:28', NULL, NULL, NULL),
(3, '123456789', 'mechudas Burgadol', '524', 'C', '2026-03-27', '09:22:00', 'approved', '2026-03-27 09:23:14', NULL, NULL, NULL),
(4, '21464755', 'lourdyn verdida', '544', 'java', '2026-03-28', '10:47:00', '', '2026-03-27 10:47:36', NULL, NULL, NULL),
(5, '21464755', 'lourdyn verdida', '530', 'java', NULL, NULL, '', '2026-04-24 09:01:50', '530 PC 13', '2026-04-24', '09:00:00'),
(6, '21464755', 'lourdyn verdida', '524', 'C', NULL, NULL, '', '2026-04-24 10:19:42', '524 PC 24', '2026-04-24', '10:19:00');

-- --------------------------------------------------------

--
-- Table structure for table `sit_in_records`
--

CREATE TABLE `sit_in_records` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `student_name` varchar(200) NOT NULL,
  `purpose` varchar(100) DEFAULT '',
  `lab` varchar(50) DEFAULT '',
  `session` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `timeout_at` datetime DEFAULT NULL,
  `reward_points` int(11) DEFAULT 0,
  `task_completed_points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `sit_in_records`
--

INSERT INTO `sit_in_records` (`id`, `id_number`, `student_name`, `purpose`, `lab`, `session`, `status`, `created_at`, `timeout_at`, `reward_points`, `task_completed_points`) VALUES
(1, '21464755', 'lourdyn m. verdida', 'java', '524', 30, 'done', '2026-03-27 00:46:00', '2026-03-27 10:47:05', 1, 0),
(2, '123456789', 'mechudas Burgadol', 'C', '524', 30, 'done', '2026-03-27 01:23:28', '2026-03-27 10:47:00', 1, 0),
(3, '21464755', 'lourdyn verdida', 'java', '544', 29, 'done', '2026-03-27 02:47:50', '2026-04-24 08:12:47', 1, 0),
(4, '21464755', 'lourdyn verdida', 'java', '530 PC 13', 28, 'done', '2026-04-24 01:02:26', '2026-04-24 09:02:52', 1, 2),
(5, '21464755', 'lourdyn verdida', 'C', '524 PC 24', 27, 'done', '2026-04-24 02:20:04', '2026-04-24 10:20:41', 1, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sit_in_records`
--
ALTER TABLE `sit_in_records`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedbacks`
--
ALTER TABLE `feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sit_in_records`
--
ALTER TABLE `sit_in_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
