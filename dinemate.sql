-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 16, 2026 at 02:33 PM
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
-- Database: `dinemate`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `number_of_guests` int(11) NOT NULL,
  `special_request` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `table_id`, `booking_date`, `booking_time`, `number_of_guests`, `special_request`, `status`, `created_at`) VALUES
(1, 6, 4, '2026-03-10', '17:53:00', 4, 'I want this table very clean and with all the amenities okay.\r\nThank you!', 'confirmed', '2026-03-07 09:24:30'),
(2, 6, 6, '2026-03-15', '21:53:00', 8, 'I want this table very clean.\r\nThank you!', 'confirmed', '2026-03-07 09:25:15'),
(3, 7, 6, '2026-03-14', '08:00:00', 8, 'I want this table clean and alone.', 'confirmed', '2026-03-07 09:35:08'),
(4, 8, 8, '2026-03-15', '02:22:00', 5, 'window seat required', 'confirmed', '2026-03-08 05:52:27'),
(5, 8, 1, '2026-03-20', '03:27:00', 1, 'NA', 'confirmed', '2026-03-08 05:55:07'),
(6, 9, 6, '2026-03-18', '03:30:00', 8, 'Window seat.', 'confirmed', '2026-03-08 06:01:13'),
(7, 9, 7, '2026-03-27', '02:35:00', 4, '', 'confirmed', '2026-03-08 06:06:05'),
(8, 9, 3, '2026-03-25', '02:49:00', 3, '', 'confirmed', '2026-03-08 06:19:43'),
(9, 10, 7, '2026-03-08', '18:00:00', 4, 'Window seat required.', 'confirmed', '2026-03-08 07:08:56');

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_tables`
--

CREATE TABLE `restaurant_tables` (
  `table_id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('available','unavailable') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restaurant_tables`
--

INSERT INTO `restaurant_tables` (`table_id`, `table_number`, `capacity`, `status`, `created_at`) VALUES
(1, 1, 2, 'available', '2026-03-03 09:29:40'),
(2, 2, 2, 'available', '2026-03-03 09:29:40'),
(3, 3, 4, 'available', '2026-03-03 09:29:40'),
(4, 4, 4, 'available', '2026-03-03 09:29:40'),
(5, 5, 6, 'available', '2026-03-03 09:29:40'),
(6, 6, 8, 'available', '2026-03-03 09:29:40'),
(7, 7, 4, 'available', '2026-03-07 07:56:21'),
(8, 8, 5, 'available', '2026-03-07 08:20:33'),
(9, 9, 10, 'available', '2026-03-08 06:32:53'),
(10, 10, 15, 'available', '2026-03-08 07:11:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `password`, `role`, `created_at`) VALUES
(5, 'Admin', 'admin@dinemate.com', '0023009801', 'admin123', 'admin', '2026-03-07 05:51:54'),
(6, 'John Doe', 'john@test.com', '1234567890', '123456', 'customer', '2026-03-07 09:22:08'),
(7, 'test', 'test123@gmail.com', '4145672738', 'test123@', 'customer', '2026-03-07 09:33:31'),
(8, 'meera', 'meera@test.com', '0542366848', 'Merra123', 'customer', '2026-03-08 05:11:12'),
(9, 'saini', 'saini@12.com', '6235472682', 'Saini123', 'customer', '2026-03-08 06:00:12'),
(10, 'new user', 'user123@23.com', '3646487323', 'User123', 'customer', '2026-03-08 07:07:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `idx_booking_datetime` (`booking_date`,`booking_time`),
  ADD KEY `idx_booking_table` (`table_id`),
  ADD KEY `idx_booking_user` (`user_id`);

--
-- Indexes for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  ADD PRIMARY KEY (`table_id`),
  ADD UNIQUE KEY `table_number` (`table_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  MODIFY `table_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_table` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`table_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
