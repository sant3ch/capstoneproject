-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 14, 2026 at 07:02 AM
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
-- Database: `jorishlaundry_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `title`, `message`, `type`, `related_id`, `status`, `created_at`, `read_at`) VALUES
(2, 'New GCASH Payment Request', 'New GCASH payment request #2 from Mark Angelo for ₱97.00', 'gcash_request', 2, 'read', '2026-02-03 11:25:51', '2026-02-09 14:21:34'),
(3, 'New GCASH Payment Request', 'New GCASH payment request #3 from Mark Angelo for ₱81.00', 'gcash_request', 3, 'read', '2026-03-17 12:06:20', '2026-03-23 03:03:29'),
(4, 'GCASH Request Approved', '{\"request_id\":3,\"customer_name\":\"Mark Angelo\",\"amount\":\"81.00\",\"reference_number\":\"GCASH-20260317-000192\",\"type\":\"gcash_approved\",\"message\":\"GCASH request #3 from Mark Angelo has been approved\"}', 'gcash_approved', 3, 'read', '2026-03-23 03:03:42', '2026-03-23 03:37:03'),
(5, 'New GCASH Payment Request', 'New GCASH payment request #4 from Mark Angelo for ₱97.00', 'gcash_request', 4, 'read', '2026-03-23 03:13:25', '2026-03-23 03:29:48'),
(6, 'New GCASH Payment Request', 'New GCASH payment request #5 from Mark Angelo for ₱81.00', 'gcash_request', 5, 'read', '2026-03-23 13:14:57', '2026-04-03 07:02:45'),
(7, 'New GCASH Payment Request', 'New GCASH payment request #6 from Mark Angelo for ₱97.00', 'gcash_request', 6, 'read', '2026-03-23 13:40:38', '2026-04-03 07:02:45'),
(8, 'New GCASH Payment Request', 'New GCASH payment request #7 from Albert DeLeon for ₱92.00', 'gcash_request', 7, 'read', '2026-03-24 06:17:41', '2026-04-03 07:02:45'),
(9, 'GCASH Request Approved', '{\"request_id\":7,\"customer_name\":\"Albert DeLeon\",\"amount\":\"92.00\",\"formatted_amount\":\"\\u20b192.00\",\"reference_number\":\"GCASH-20260324-000200\",\"booking_id\":200,\"type\":\"gcash_approved\",\"message\":\"GCASH request #7 from Albert DeLeon for \\u20b192.00 has been approved. Reference: GCASH-20260324-000200\"}', 'gcash_approved', 7, 'read', '2026-03-24 06:58:42', '2026-03-24 06:58:47'),
(10, 'New GCASH Payment Request', 'New GCASH payment request #8 from Albert DeLeon for ₱97.00', 'gcash_request', 8, 'read', '2026-03-25 04:22:50', '2026-04-03 07:02:45'),
(11, 'New GCASH Payment Request', 'New GCASH payment request #9 from Albert DeLeon for ₱167.00', 'gcash_request', 9, 'read', '2026-03-25 04:49:13', '2026-04-03 07:02:45'),
(12, 'GCASH Request Approved', '{\"request_id\":9,\"customer_name\":\"Albert DeLeon\",\"amount\":\"167.00\",\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260325-000202\",\"booking_id\":202,\"type\":\"gcash_approved\",\"message\":\"GCASH request #9 from Albert DeLeon for \\u20b1167.00 has been approved. Reference: GCASH-20260325-000202\"}', 'gcash_approved', 9, 'read', '2026-03-25 04:49:51', '2026-04-03 07:02:45'),
(13, 'New GCASH Payment Request', 'New GCASH payment request #10 from Albert DeLeon for ₱167.00', 'gcash_request', 10, 'read', '2026-03-25 06:27:21', '2026-03-25 06:27:59'),
(14, 'GCASH Request Approved', '{\"request_id\":10,\"customer_name\":\"Albert DeLeon\",\"amount\":\"167.00\",\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260325-000203\",\"booking_id\":203,\"type\":\"gcash_approved\",\"message\":\"GCASH request #10 from Albert DeLeon for \\u20b1167.00 has been approved. Reference: GCASH-20260325-000203\"}', 'gcash_approved', 10, 'read', '2026-03-25 06:28:10', '2026-04-03 07:02:45'),
(15, 'New GCASH Payment Request', 'New GCASH payment request #11 from Albert DeLeon for ₱167.00', 'gcash_request', 11, 'read', '2026-03-26 05:39:13', '2026-04-03 07:02:45'),
(16, 'GCASH Request Approved', '{\"request_id\":11,\"customer_name\":\"Albert DeLeon\",\"amount\":\"167.00\",\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260326-000204\",\"booking_id\":204,\"type\":\"gcash_approved\",\"message\":\"GCASH request #11 from Albert DeLeon for \\u20b1167.00 has been approved. Reference: GCASH-20260326-000204\"}', 'gcash_approved', 11, 'read', '2026-03-26 05:41:57', '2026-04-03 07:02:45'),
(17, 'User Marked Payment as Completed', 'User Albert DeLeon has marked GCASH payment completed for booking #204 (₱167.00). Please verify and approve/reject.', 'gcash_request', 11, 'read', '2026-03-26 05:42:35', '2026-04-03 07:02:45'),
(18, 'GCASH Payment Completed', '{\"request_id\":11,\"customer_name\":\"Albert DeLeon\",\"amount\":167,\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260326-000204\",\"booking_id\":204,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #11 from Albert DeLeon of \\u20b1167.00 has been completed. Reference: GCASH-20260326-000204\"}', 'gcash_completed', 11, 'read', '2026-03-26 06:09:09', '2026-04-03 07:02:45'),
(19, 'User Marked Payment as Completed', 'User Mark Angelo has marked GCASH payment completed for booking #192 (₱81.00). Please verify and approve/reject.', 'gcash_request', 3, 'read', '2026-03-29 12:37:54', '2026-03-29 12:39:32'),
(20, 'New GCASH Payment Request', 'New GCASH payment request #12 from Albert DeLeon for ₱167.00', 'gcash_request', 12, 'read', '2026-03-31 07:17:14', '2026-04-03 07:02:45'),
(21, 'GCASH Payment Completed', '{\"request_id\":12,\"customer_name\":\"Albert DeLeon\",\"amount\":167,\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260331-000001\",\"booking_id\":1,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #12 from Albert DeLeon of \\u20b1167.00 has been completed. Reference: GCASH-20260331-000001\"}', 'gcash_completed', 12, 'read', '2026-03-31 07:18:20', '2026-04-03 07:02:45'),
(22, 'New GCASH Payment Request', 'New GCASH payment request #13 from Albert DeLeon for ₱167.00', 'gcash_request', 13, 'read', '2026-03-31 07:48:15', '2026-04-03 07:02:45'),
(23, 'GCASH Request Approved', '{\"request_id\":13,\"customer_name\":\"Albert DeLeon\",\"amount\":\"167.00\",\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260331-000002\",\"booking_id\":2,\"type\":\"gcash_approved\",\"message\":\"GCASH request #13 from Albert DeLeon for \\u20b1167.00 has been approved. Reference: GCASH-20260331-000002\"}', 'gcash_approved', 13, 'read', '2026-03-31 07:48:58', '2026-04-03 07:02:45'),
(24, 'User Marked Payment as Completed', 'User Albert DeLeon has marked GCASH payment completed for booking #2 (₱167.00). Please verify and approve/reject.', 'gcash_request', 13, 'read', '2026-03-31 07:49:41', '2026-04-03 07:02:45'),
(25, 'GCASH Payment Completed', '{\"request_id\":13,\"customer_name\":\"Albert DeLeon\",\"amount\":167,\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260331-000002\",\"booking_id\":2,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #13 from Albert DeLeon of \\u20b1167.00 has been completed. Reference: GCASH-20260331-000002\"}', 'gcash_completed', 13, 'read', '2026-03-31 07:50:07', '2026-04-03 07:02:45'),
(26, 'New GCASH Payment Request', 'New GCASH payment request #14 from Albert DeLeon for ₱167.00', 'gcash_request', 14, 'read', '2026-03-31 08:05:28', '2026-04-03 07:02:45'),
(27, 'GCASH Request Approved', '{\"request_id\":14,\"customer_name\":\"Albert DeLeon\",\"amount\":\"167.00\",\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260331-000003\",\"booking_id\":3,\"type\":\"gcash_approved\",\"message\":\"GCASH request #14 from Albert DeLeon for \\u20b1167.00 has been approved. Reference: GCASH-20260331-000003\"}', 'gcash_approved', 14, 'read', '2026-03-31 08:06:15', '2026-04-03 07:02:45'),
(28, 'User Marked Payment as Completed', 'User Albert DeLeon has marked GCASH payment completed for booking #3 (₱167.00). Please verify and approve/reject.', 'gcash_request', 14, 'read', '2026-03-31 08:06:26', '2026-04-03 07:02:45'),
(29, 'GCASH Payment Completed', '{\"request_id\":14,\"customer_name\":\"Albert DeLeon\",\"amount\":167,\"formatted_amount\":\"\\u20b1167.00\",\"reference_number\":\"GCASH-20260331-000003\",\"booking_id\":3,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #14 from Albert DeLeon of \\u20b1167.00 has been completed. Reference: GCASH-20260331-000003\"}', 'gcash_completed', 14, 'read', '2026-03-31 08:06:42', '2026-04-03 07:02:45'),
(30, 'New GCASH Payment Request', 'New GCASH payment request #15 from Mark Angelo for ₱172.00', 'gcash_request', 15, 'read', '2026-04-03 12:58:44', '2026-04-09 11:32:48'),
(31, 'New GCASH Payment Request', 'New GCASH payment request #16 from Mark Angelo for ₱194.00', 'gcash_request', 16, 'read', '2026-04-03 13:22:19', '2026-04-09 11:32:48'),
(32, 'GCASH Request Approved', '{\"request_id\":16,\"customer_name\":\"Mark Angelo\",\"amount\":\"194.00\",\"formatted_amount\":\"\\u20b1194.00\",\"reference_number\":\"GCASH-20260403-000006\",\"booking_id\":6,\"type\":\"gcash_approved\",\"message\":\"GCASH request #16 from Mark Angelo for \\u20b1194.00 has been approved. Reference: GCASH-20260403-000006\"}', 'gcash_approved', 16, 'read', '2026-04-03 14:03:40', '2026-04-09 11:32:48'),
(33, 'User Marked Payment as Completed', 'User Mark Angelo has marked GCASH payment completed for booking #6 (₱194.00). Please verify and approve/reject.', 'gcash_request', 16, 'read', '2026-04-03 14:04:49', '2026-04-09 11:32:48'),
(34, 'GCASH Payment Completed', '{\"request_id\":16,\"customer_name\":\"Mark Angelo\",\"amount\":194,\"formatted_amount\":\"\\u20b1194.00\",\"reference_number\":\"GCASH-20260403-000006\",\"booking_id\":6,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #16 from Mark Angelo of \\u20b1194.00 has been completed. Reference: GCASH-20260403-000006\"}', 'gcash_completed', 16, 'read', '2026-04-03 14:08:33', '2026-04-09 11:32:48'),
(35, 'New GCASH Payment Request', 'New GCASH payment request #17 from Mark Angelo for ₱194.00', 'gcash_request', 17, 'read', '2026-04-07 09:17:50', '2026-04-09 11:32:48'),
(36, 'New GCASH Payment Request', 'New GCASH payment request #18 from Mark Angelo for ₱194.00', 'gcash_request', 18, 'read', '2026-04-07 10:03:22', '2026-04-09 11:32:48'),
(37, 'New GCASH Payment Request', 'New GCASH payment request #19 from Mark Angelo for ₱224.00', 'gcash_request', 19, 'read', '2026-04-08 09:50:06', '2026-04-09 11:32:48'),
(38, 'New GCASH Payment Request', 'New GCASH payment request #1 from Mark Angelo for ₱199.00', 'gcash_request', 1, 'read', '2026-04-09 11:32:14', '2026-04-09 11:32:48'),
(39, 'GCASH Request Approved', '{\"request_id\":1,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"GCASH-20260409-000001\",\"booking_id\":1,\"type\":\"gcash_approved\",\"message\":\"GCASH request #1 from Mark Angelo for \\u20b1199.00 has been approved. Reference: GCASH-20260409-000001\"}', 'gcash_approved', 1, 'unread', '2026-04-09 11:32:58', NULL),
(40, 'User Marked Payment as Completed', 'User Mark Angelo has marked GCASH payment completed for booking #1 (₱199.00). Please verify and approve/reject.', 'gcash_request', 1, 'unread', '2026-04-09 11:33:17', NULL),
(41, 'GCASH Payment Completed', '{\"request_id\":1,\"customer_name\":\"Mark Angelo\",\"amount\":199,\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"GCASH-20260409-000001\",\"booking_id\":1,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #1 from Mark Angelo of \\u20b1199.00 has been completed. Reference: GCASH-20260409-000001\"}', 'gcash_completed', 1, 'unread', '2026-04-09 12:04:28', NULL),
(42, 'New Cash on Delivery Request', 'New cash on delivery request #2 from Mark Angelo for ₱199.00', 'cash_request', 2, 'unread', '2026-04-10 06:36:38', NULL),
(43, 'GCASH Request Approved', '{\"request_id\":2,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260410-000002\",\"booking_id\":2,\"type\":\"gcash_approved\",\"message\":\"GCASH request #2 from Mark Angelo for \\u20b1199.00 has been approved. Reference: COD-20260410-000002\"}', 'gcash_approved', 2, 'unread', '2026-04-10 06:51:19', NULL),
(44, 'New Cash on Delivery Request', 'New cash on delivery request #3 from Mark Angelo for ₱199.00', 'cash_request', 3, 'unread', '2026-04-10 07:21:25', NULL),
(45, 'GCASH Request Approved', '{\"request_id\":3,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260410-000003\",\"booking_id\":3,\"type\":\"gcash_approved\",\"message\":\"GCASH request #3 from Mark Angelo for \\u20b1199.00 has been approved. Reference: COD-20260410-000003\"}', 'gcash_approved', 3, 'unread', '2026-04-10 07:21:49', NULL),
(46, 'User Confirmed Cash on Delivery Payment', 'User Mark Angelo has confirmed Cash on Delivery payment for booking #3 (₱199.00). Please verify and approve/reject.', 'gcash_request', 3, 'unread', '2026-04-10 07:24:23', NULL),
(47, 'GCASH Payment Completed', '{\"request_id\":3,\"customer_name\":\"Mark Angelo\",\"amount\":199,\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260410-000003\",\"booking_id\":3,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #3 from Mark Angelo of \\u20b1199.00 has been completed. Reference: COD-20260410-000003\"}', 'gcash_completed', 3, 'unread', '2026-04-10 07:24:43', NULL),
(48, 'New Cash on Delivery Request', 'New cash on delivery request #1 from Mark Angelo for ₱199.00', 'cash_request', 1, 'unread', '2026-04-10 07:36:45', NULL),
(49, 'GCASH Request Approved', '{\"request_id\":1,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260410-000001\",\"booking_id\":1,\"type\":\"gcash_approved\",\"message\":\"GCASH request #1 from Mark Angelo for \\u20b1199.00 has been approved. Reference: COD-20260410-000001\"}', 'gcash_approved', 1, 'unread', '2026-04-10 07:37:05', NULL),
(50, 'User Confirmed Cash on Delivery Payment', 'User Mark Angelo has confirmed Cash on Delivery payment for booking #1 (₱199.00). Please verify and approve/reject.', 'gcash_request', 1, 'unread', '2026-04-10 07:38:01', NULL),
(51, 'GCASH Payment Completed', '{\"request_id\":1,\"customer_name\":\"Mark Angelo\",\"amount\":199,\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260410-000001\",\"booking_id\":1,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #1 from Mark Angelo of \\u20b1199.00 has been completed. Reference: COD-20260410-000001\"}', 'gcash_completed', 1, 'unread', '2026-04-10 07:38:13', NULL),
(52, 'New Cash on Delivery Request', 'New cash on delivery request #2 from Mark Angelo for ₱199.00', 'cash_request', 2, 'unread', '2026-04-10 14:54:40', NULL),
(53, 'GCASH Request Approved', '{\"request_id\":2,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260410-000002\",\"booking_id\":2,\"type\":\"gcash_approved\",\"message\":\"GCASH request #2 from Mark Angelo for \\u20b1199.00 has been approved. Reference: COD-20260410-000002\"}', 'gcash_approved', 2, 'unread', '2026-04-10 14:55:10', NULL),
(54, 'New Cash on Delivery Request', 'New cash on delivery request #3 from Mark Angelo for ₱199.00', 'cash_request', 3, 'unread', '2026-04-10 15:09:44', NULL),
(55, 'GCASH Request Approved', '{\"request_id\":3,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260410-000003\",\"booking_id\":3,\"type\":\"gcash_approved\",\"message\":\"GCASH request #3 from Mark Angelo for \\u20b1199.00 has been approved. Reference: COD-20260410-000003\"}', 'gcash_approved', 3, 'unread', '2026-04-10 15:09:57', NULL),
(56, 'New GCASH Payment Request', 'New GCASH payment request #4 from Mark Angelo for ₱199.00', 'gcash_request', 4, 'unread', '2026-04-10 15:34:54', NULL),
(57, 'GCASH Request Approved', '{\"request_id\":4,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"GCASH-20260410-000004\",\"booking_id\":4,\"type\":\"gcash_approved\",\"message\":\"GCASH request #4 from Mark Angelo for \\u20b1199.00 has been approved. Reference: GCASH-20260410-000004\"}', 'gcash_approved', 4, 'unread', '2026-04-10 15:35:51', NULL),
(58, 'New GCASH Payment Request', 'New GCASH payment request #1 from Mark Angelo for ₱199.00', 'gcash_request', 1, 'unread', '2026-04-10 16:05:22', NULL),
(59, 'GCASH Request Approved', '{\"request_id\":1,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"GCASH-20260410-000001\",\"booking_id\":1,\"type\":\"gcash_approved\",\"message\":\"GCASH request #1 from Mark Angelo for \\u20b1199.00 has been approved. Reference: GCASH-20260410-000001\"}', 'gcash_approved', 1, 'unread', '2026-04-10 16:05:50', NULL),
(60, 'User Marked Payment as Completed', 'User Mark Angelo has marked GCASH payment completed for booking #1 (₱199.00). Please verify and approve/reject.', 'gcash_request', 1, 'unread', '2026-04-10 16:06:19', NULL),
(61, 'GCASH Payment Completed', '{\"request_id\":1,\"customer_name\":\"Mark Angelo\",\"amount\":199,\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"GCASH-20260410-000001\",\"booking_id\":1,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #1 from Mark Angelo of \\u20b1199.00 has been completed. Reference: GCASH-20260410-000001\"}', 'gcash_completed', 1, 'unread', '2026-04-10 16:06:44', NULL),
(62, 'New Cash on Delivery Request', 'New cash on delivery request #2 from Mark Angelo for ₱229.00', 'cash_request', 2, 'unread', '2026-04-10 16:10:44', NULL),
(63, 'GCASH Request Approved', '{\"request_id\":2,\"customer_name\":\"Mark Angelo\",\"amount\":\"229.00\",\"formatted_amount\":\"\\u20b1229.00\",\"reference_number\":\"COD-20260410-000002\",\"booking_id\":2,\"type\":\"gcash_approved\",\"message\":\"GCASH request #2 from Mark Angelo for \\u20b1229.00 has been approved. Reference: COD-20260410-000002\"}', 'gcash_approved', 2, 'unread', '2026-04-10 16:11:22', NULL),
(64, 'User Confirmed Cash on Delivery Payment', 'User Mark Angelo has confirmed Cash on Delivery payment for booking #2 (₱229.00). Please verify and approve/reject.', 'gcash_request', 2, 'unread', '2026-04-10 16:11:40', NULL),
(65, 'GCASH Payment Completed', '{\"request_id\":2,\"customer_name\":\"Mark Angelo\",\"amount\":229,\"formatted_amount\":\"\\u20b1229.00\",\"reference_number\":\"COD-20260410-000002\",\"booking_id\":2,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #2 from Mark Angelo of \\u20b1229.00 has been completed. Reference: COD-20260410-000002\"}', 'gcash_completed', 2, 'unread', '2026-04-10 16:12:00', NULL),
(66, 'New GCASH Payment Request', 'New GCASH payment request #3 from Mark Angelo for ₱199.00', 'gcash_request', 3, 'unread', '2026-04-13 06:30:42', NULL),
(67, 'GCASH Request Approved', '{\"request_id\":3,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"GCASH-20260413-000003\",\"booking_id\":3,\"type\":\"gcash_approved\",\"message\":\"GCASH request #3 from Mark Angelo for \\u20b1199.00 has been approved. Reference: GCASH-20260413-000003\"}', 'gcash_approved', 3, 'unread', '2026-04-13 06:31:54', NULL),
(68, 'User Marked Payment as Completed', 'User Mark Angelo has marked GCASH payment completed for booking #3 (₱199.00). Please verify and approve/reject.', 'gcash_request', 3, 'unread', '2026-04-13 06:40:08', NULL),
(69, 'GCASH Payment Completed', '{\"request_id\":3,\"customer_name\":\"Mark Angelo\",\"amount\":199,\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"GCASH-20260413-000003\",\"booking_id\":3,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #3 from Mark Angelo of \\u20b1199.00 has been completed. Reference: GCASH-20260413-000003\"}', 'gcash_completed', 3, 'unread', '2026-04-13 06:41:02', NULL),
(70, 'New Cash on Delivery Request', 'New cash on delivery request #4 from Mark Angelo for ₱199.00', 'cash_request', 4, 'unread', '2026-04-13 10:26:01', NULL),
(71, 'GCASH Request Approved', '{\"request_id\":4,\"customer_name\":\"Mark Angelo\",\"amount\":\"199.00\",\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260413-000004\",\"booking_id\":4,\"type\":\"gcash_approved\",\"message\":\"GCASH request #4 from Mark Angelo for \\u20b1199.00 has been approved. Reference: COD-20260413-000004\"}', 'gcash_approved', 4, 'unread', '2026-04-13 10:27:04', NULL),
(72, 'User Confirmed Cash on Delivery Payment', 'User Mark Angelo has confirmed Cash on Delivery payment for booking #4 (₱199.00). Please verify and approve/reject.', 'gcash_request', 4, 'unread', '2026-04-13 10:28:37', NULL),
(73, 'GCASH Payment Completed', '{\"request_id\":4,\"customer_name\":\"Mark Angelo\",\"amount\":199,\"formatted_amount\":\"\\u20b1199.00\",\"reference_number\":\"COD-20260413-000004\",\"booking_id\":4,\"type\":\"gcash_completed\",\"message\":\"GCASH payment for request #4 from Mark Angelo of \\u20b1199.00 has been completed. Reference: COD-20260413-000004\"}', 'gcash_completed', 4, 'unread', '2026-04-13 10:29:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `booking_date` date DEFAULT NULL,
  `service_type` varchar(255) DEFAULT NULL,
  `detergent` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Completed','Cancelled','Pending Payment') NOT NULL DEFAULT 'Pending',
  `points_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `machine_count` int(11) NOT NULL DEFAULT 1,
  `time_slot` varchar(50) NOT NULL,
  `request_service` varchar(50) DEFAULT NULL,
  `queue_number` int(11) DEFAULT NULL,
  `queue_code` varchar(20) DEFAULT NULL,
  `pickup_status` enum('Waiting for Pick Up','Already Picked') DEFAULT 'Waiting for Pick Up',
  `order_stage` enum('Pending / Booked','Queued (Waiting for Machine)','Queuing (Assigning Machines)','In Process','Ready for Pickup','Completed / Picked Up','Missed Pickup') NOT NULL DEFAULT 'Pending / Booked',
  `machine_names` text DEFAULT NULL,
  `estimated_start_time` datetime DEFAULT NULL,
  `estimated_completion_time` datetime DEFAULT NULL,
  `process_started_at` datetime DEFAULT NULL,
  `process_completed_at` datetime DEFAULT NULL,
  `picked_up_at` datetime DEFAULT NULL,
  `overdue_notified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `booking_date`, `service_type`, `detergent`, `status`, `points_claimed`, `machine_count`, `time_slot`, `request_service`, `queue_number`, `queue_code`, `pickup_status`, `order_stage`, `machine_names`, `estimated_start_time`, `estimated_completion_time`, `process_started_at`, `process_completed_at`, `picked_up_at`, `overdue_notified`, `created_at`) VALUES
(1, 3, '2026-04-11', '1:Full-Service - Wash & Dry', '2x Ariel, 2x Del', 'Completed', 0, 2, '7:00 AM - 8:30 AM', 'Pickup, Delivery', 1, 'Q-001', 'Waiting for Pick Up', 'Missed Pickup', 'Washer1, Dryer1', '2026-04-11 07:00:00', '2026-04-11 08:30:00', '2026-04-10 18:07:27', '2026-04-13 08:41:14', NULL, 1, '2026-04-10 16:05:01'),
(2, 3, '2026-04-11', '1:Full-Service - Wash & Dry, 5:Fold', '2x Champion, 2x Downy AntiBac', 'Completed', 0, 2, '8:30 AM - 10:00 AM', 'Delivery', 2, 'Q-002', 'Waiting for Pick Up', 'Missed Pickup', 'Washer2, Dryer2', '2026-04-11 08:30:00', '2026-04-11 10:00:00', '2026-04-10 18:12:26', '2026-04-13 08:41:14', NULL, 1, '2026-04-10 16:10:27'),
(3, 3, '2026-04-13', '1:Full-Service - Wash & Dry', '2x Ariel, 2x Del', 'Completed', 0, 2, '2:30 PM - 4:00 PM', 'Pickup, Delivery', 1, 'Q-001', 'Waiting for Pick Up', 'Missed Pickup', 'Washer3, Dryer3', '2026-04-13 14:30:00', '2026-04-13 16:00:00', '2026-04-13 08:42:44', '2026-04-13 18:51:42', NULL, 1, '2026-04-13 06:24:51'),
(4, 3, '2026-04-13', '1:Full-Service - Wash & Dry', '2x Ariel, 2x Del', 'Completed', 0, 2, '5:30 PM - 7:00 PM', 'Pickup, Delivery', 2, 'Q-002', 'Waiting for Pick Up', 'Missed Pickup', 'Washer1, Dryer1', '2026-04-13 17:30:00', '2026-04-13 19:00:00', '2026-04-13 12:30:27', '2026-04-14 03:53:08', NULL, 1, '2026-04-13 10:24:19');

-- --------------------------------------------------------

--
-- Table structure for table `bookings_backup`
--

CREATE TABLE `bookings_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `booking_date` date DEFAULT NULL,
  `service_type` varchar(255) DEFAULT NULL,
  `detergent` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Completed','Cancelled','Pending Payment') NOT NULL DEFAULT 'Pending',
  `points_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `machine_count` int(11) NOT NULL DEFAULT 1,
  `time_slot` varchar(50) NOT NULL,
  `request_service` varchar(50) DEFAULT NULL,
  `queue_number` int(11) DEFAULT NULL,
  `queue_code` varchar(20) DEFAULT NULL,
  `pickup_status` enum('Waiting for Pick Up','Already Picked') DEFAULT 'Waiting for Pick Up',
  `order_stage` enum('Pending / Booked','Queued (Waiting for Machine)','In Process','Ready for Pickup','Completed / Picked Up','Missed Pickup') NOT NULL DEFAULT 'Pending / Booked',
  `machine_names` text DEFAULT NULL,
  `estimated_start_time` datetime DEFAULT NULL,
  `estimated_completion_time` datetime DEFAULT NULL,
  `process_started_at` datetime DEFAULT NULL,
  `process_completed_at` datetime DEFAULT NULL,
  `picked_up_at` datetime DEFAULT NULL,
  `overdue_notified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings_backup`
--

INSERT INTO `bookings_backup` (`id`, `user_id`, `booking_date`, `service_type`, `detergent`, `status`, `points_claimed`, `machine_count`, `time_slot`, `request_service`, `queue_number`, `queue_code`, `pickup_status`, `order_stage`, `machine_names`, `estimated_start_time`, `estimated_completion_time`, `process_started_at`, `process_completed_at`, `picked_up_at`, `overdue_notified`, `created_at`) VALUES
(169, 5, '2025-06-30', 'Full-Service - Wash & Dry', NULL, 'Completed', 0, 1, '7:00 AM - 8:30 AM', 'Delivery', NULL, NULL, 'Already Picked', 'Completed / Picked Up', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-26 17:23:42'),
(170, 10, '2025-07-01', 'Full-Service - Wash & Dry', NULL, 'Completed', 0, 1, '7:00 AM - 8:30 AM', 'Delivery', NULL, NULL, 'Already Picked', 'Completed / Picked Up', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-28 07:13:09'),
(171, 9, '2025-07-01', 'Full-Service - Wash & Dry', 'Surf, Champion, Breeze, Ariel, Surf Downy, Downy anti-kulob, Safe Guard', 'Pending', 0, 1, '7:00 AM - 8:30 AM', 'Delivery', NULL, NULL, 'Waiting for Pick Up', 'Missed Pickup', 'Dryer1', '2026-03-31 06:56:54', '2026-03-31 08:26:54', '2026-03-31 06:56:54', NULL, NULL, 1, '2025-06-30 14:30:40'),
(172, 9, '2025-07-01', 'Self-Service - Washer', 'Surf, Champion, Breeze, Ariel, Surf Downy, Downy anti-kulob, Safe Guard', 'Pending', 0, 1, '7:00 AM - 8:30 AM', 'Delivery', NULL, NULL, 'Waiting for Pick Up', 'In Process', 'Dryer4', '2026-03-31 07:00:22', '2026-03-31 08:33:51', '2026-03-31 07:00:22', NULL, NULL, 0, '2025-06-24 21:32:04'),
(173, 10, '2025-07-01', 'Full-Service - Wash & Dry, Self-Service - Washer, Self-Service - Dryer, Fold', 'Surf, Champion, Breeze, Ariel, Surf Downy, Downy anti-kulob, Safe Guard', 'Pending', 0, 3, '8:30 AM - 10:00 AM', 'Delivery', NULL, NULL, 'Waiting for Pick Up', 'In Process', 'Dryer3', '2026-03-31 07:03:40', '2026-03-31 08:33:40', '2026-03-31 07:03:40', NULL, NULL, 0, '2025-06-25 02:55:21'),
(174, 13, '2025-07-31', 'Full-Service - Wash & Dry', NULL, 'Completed', 0, 1, '5:30 PM - 7:00 PM', 'Delivery', NULL, NULL, 'Already Picked', 'Completed / Picked Up', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-07-29 18:27:57'),
(175, 13, '2025-07-01', 'Full-Service - Wash & Dry', 'Surf', 'Pending', 0, 1, '7:00 AM - 8:30 AM', 'Delivery', NULL, NULL, 'Waiting for Pick Up', 'Pending / Booked', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-27 20:43:01'),
(176, 13, '2025-07-01', 'Full-Service - Wash & Dry', 'Surf, Champion', 'Pending', 0, 1, '7:00 AM - 8:30 AM', 'Delivery', NULL, NULL, 'Waiting for Pick Up', 'Pending / Booked', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-24 07:29:27'),
(177, 12, '2025-07-01', 'Full-Service - Wash & Dry', 'Surf', 'Pending', 0, 1, '7:00 AM - 8:30 AM', 'Pickup', NULL, NULL, 'Waiting for Pick Up', 'Pending / Booked', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-25 09:16:06'),
(178, 12, '2025-07-02', 'Full-Service - Wash & Dry', NULL, 'Completed', 0, 1, '7:00 AM - 8:30 AM', 'Delivery', NULL, NULL, 'Already Picked', 'Completed / Picked Up', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-26 21:00:19'),
(179, 12, '2025-07-02', 'Full-Service - Wash & Dry', NULL, 'Completed', 0, 1, '8:30 AM - 10:00 AM', 'Delivery', NULL, NULL, 'Waiting for Pick Up', 'In Process', 'Dryer2', '2026-03-31 06:56:58', '2026-03-31 08:26:58', '2026-03-31 06:56:58', NULL, NULL, 0, '2025-06-26 22:26:20'),
(180, 3, '2025-12-09', 'Full-Service - Wash & Dry', 'Surf, Champion', 'Pending', 0, 2, '7:00 AM - 8:30 AM', 'Pickup', 786, 'Q-786', 'Waiting for Pick Up', 'Pending / Booked', 'M1', NULL, NULL, NULL, NULL, NULL, 0, '2025-12-06 15:44:42'),
(181, 3, '2025-12-09', 'Self-Service - Washer', 'Surf', 'Pending', 0, 1, '10:00 AM - 11:30 AM', 'Pickup', 411, 'Q-411', 'Waiting for Pick Up', 'Pending / Booked', 'M3', NULL, NULL, NULL, NULL, NULL, 0, '2025-12-06 04:38:36'),
(182, 3, '2025-12-09', 'Full-Service - Wash & Dry', 'Surf', 'Cancelled', 0, 1, '10:00 AM - 11:30 AM', 'Pickup', 722, 'Q-722', 'Waiting for Pick Up', 'Missed Pickup', 'M2', NULL, NULL, NULL, NULL, NULL, 0, '2025-12-08 12:54:55'),
(183, 3, '2025-12-09', 'Full-Service - Wash & Dry', 'Surf', 'Cancelled', 0, 1, '10:00 AM - 11:30 AM', 'Delivery', 282, 'Q-282', 'Waiting for Pick Up', 'Missed Pickup', 'M4', NULL, NULL, NULL, NULL, NULL, 0, '2025-12-02 10:02:57'),
(184, 3, '2025-12-10', 'Self-Service - Washer', 'Surf', 'Cancelled', 0, 1, '10:00 AM - 11:30 AM', 'Pickup, Delivery', 328, 'Q-328', 'Waiting for Pick Up', 'Missed Pickup', 'M6', NULL, NULL, NULL, NULL, NULL, 0, '2025-12-05 06:50:26'),
(185, 3, '2025-12-19', 'Full-Service - Wash & Dry', NULL, 'Completed', 0, 1, '8:30 AM - 10:00 AM', 'Delivery', 539, 'Q-539', '', 'Pending / Booked', 'M5', NULL, NULL, NULL, NULL, NULL, 0, '2025-12-13 17:17:17'),
(186, 3, '2025-12-20', 'Full-Service - Wash & Dry', 'Surf', 'Cancelled', 0, 2, '7:00 AM - 8:30 AM', 'Pickup, Delivery', 810, 'Q-810', '', 'Missed Pickup', 'M2', NULL, NULL, NULL, NULL, NULL, 0, '2025-12-16 15:08:46'),
(187, 3, '2026-01-09', 'Fold', NULL, 'Completed', 0, 1, '7:00 AM - 8:30 AM', 'Delivery', 294, 'Q-294', '', 'Pending / Booked', 'M1', NULL, NULL, NULL, NULL, NULL, 0, '2026-01-05 15:29:30'),
(188, 3, '2026-01-10', 'Self-Service - Washer', 'Champion', 'Pending', 0, 2, '7:00 AM - 8:30 AM', 'Delivery', 714, 'Q-714', '', 'Pending / Booked', 'M2', NULL, NULL, NULL, NULL, NULL, 0, '2026-01-09 12:58:23'),
(189, 3, '2026-01-08', 'Self-Service - Dryer', NULL, 'Completed', 0, 1, '4:00 PM - 5:30 PM', 'Pickup', 653, 'Q-653', '', 'Pending / Booked', 'M3', NULL, NULL, NULL, NULL, NULL, 0, '2026-01-08 07:13:22'),
(190, 3, '2026-03-01', 'Full-Service - Wash & Dry', 'Surf, Ariel', 'Pending', 0, 2, '7:00 AM - 8:30 AM', 'Pickup, Delivery', 992, 'Q-992', '', 'Pending / Booked', 'M1, M2', NULL, NULL, NULL, NULL, NULL, 0, '2026-01-31 10:25:27'),
(191, 3, '2026-02-02', 'Full-Service - Wash & Dry', 'Surf, Breeze', 'Pending', 0, 2, '7:00 AM - 8:30 AM', 'Pickup, Delivery', 864, 'Q-864', '', 'Pending / Booked', 'M3, M4', NULL, NULL, NULL, NULL, NULL, 0, '2026-01-31 18:32:32'),
(192, 3, '2026-03-18', 'Full-Service - Wash & Dry', 'Ariel', 'Pending', 0, 1, '7:00 AM - 8:30 AM', '', 927, 'Q-927', '', 'Pending / Booked', 'Washer1', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-17 11:53:44'),
(193, 3, '2026-03-18', 'Full-Service - Wash & Dry', 'Ariel, Champion AntiBacterial', 'Cancelled', 0, 2, '7:00 AM - 8:30 AM', 'Pickup, Delivery', 847, 'Q-847', '', 'Missed Pickup', 'Washer2, Dryer2', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-17 12:38:10'),
(194, 3, '2026-03-18', 'Full-Service - Wash & Dry', 'Ariel, Champion AntiBacterial', 'Pending', 0, 2, '7:00 AM - 8:30 AM', 'Pickup, Delivery', 890, 'Q-890', '', 'Pending / Booked', 'Washer1, Dryer1', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-17 14:09:16'),
(195, 3, '2026-03-23', 'Full-Service - Wash & Dry', 'Ariel, Champion AntiBacterial', 'Pending', 0, 2, '10:00 AM - 11:30 AM', 'Pickup, Delivery', 838, 'Q-838', '', 'Pending / Booked', 'Washer2, Dryer2', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-23 01:45:10'),
(196, 3, '2026-03-24', '1:Full-Service - Wash & Dry', 'Breeze, Del', 'Completed', 0, 2, '7:00 AM - 8:30 AM', '', 973, 'Q-973', '', 'Pending / Booked', 'Washer3, Dryer4', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-23 02:00:32'),
(197, 3, '2026-03-25', '1:Full-Service - Wash & Dry', 'Breeze, Champion AntiBacterial', 'Pending', 0, 2, '7:00 AM - 8:30 AM', 'Pickup, Delivery', 700, 'Q-700', '', 'Pending / Booked', 'Washer4, Dryer3', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-23 03:13:00'),
(198, 3, '2026-03-26', '1:Full-Service - Wash & Dry', 'Breeze', 'Pending', 0, 2, '10:00 AM - 11:30 AM', 'Delivery', 901, 'Q-901', '', 'Pending / Booked', 'Washer5, Washer6', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-23 13:14:35'),
(199, 3, '2026-03-28', '3:Self-Service - Washer', 'Ariel', 'Pending', 0, 1, '1:00 PM - 2:30 PM', 'Delivery', 479, 'Q-479', '', 'Pending / Booked', 'Dryer5', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-23 14:10:32'),
(200, 5, '2026-03-27', '1:Full-Service - Wash & Dry', 'Breeze, Downy Sunrise Fresh', 'Pending', 0, 2, '7:00 AM - 8:30 AM', 'Pickup, Delivery', 293, 'Q-293', '', 'Pending / Booked', 'Washer1, Dryer1', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-24 06:16:33'),
(201, 5, '2026-03-25', '1:Full-Service - Wash & Dry', 'Ariel, Champion AntiBacterial', 'Pending', 0, 2, '8:30 AM - 10:00 AM', 'Pickup, Delivery', 787, 'Q-787', '', 'Pending / Booked', 'Washer2, Dryer2', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-25 04:22:17'),
(202, 5, '2026-03-29', '1:Full-Service - Wash & Dry', 'Breeze, Del', 'Pending', 0, 2, '7:00 AM - 8:30 AM', 'Delivery', 837, 'Q-837', '', 'Pending / Booked', 'Washer1, Dryer1', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-25 04:48:47'),
(203, 5, '2026-03-29', '1:Full-Service - Wash & Dry', 'Ariel, Champion AntiBacterial', 'Completed', 0, 2, '8:30 AM - 10:00 AM', 'Pickup', 540, 'Q-540', '', 'Pending / Booked', 'Washer2, Dryer2', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-25 06:27:09'),
(204, 5, '2026-03-26', '1:Full-Service - Wash & Dry', 'Ariel, Champion AntiBacterial', 'Completed', 0, 2, '1:00 PM - 2:30 PM', 'Pickup, Delivery', 741, 'Q-741', '', 'Pending / Booked', 'Washer3, Dryer3', NULL, NULL, NULL, NULL, NULL, 0, '2026-03-26 05:38:37');

-- --------------------------------------------------------

--
-- Table structure for table `booking_services`
--

CREATE TABLE `booking_services` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `claimed_rewards`
--

CREATE TABLE `claimed_rewards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reward_name` varchar(100) NOT NULL,
  `status` enum('Pending','Claimed','Rejected') DEFAULT 'Pending',
  `claimed_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claimed_rewards`
--

INSERT INTO `claimed_rewards` (`id`, `user_id`, `reward_name`, `status`, `claimed_at`, `approved_at`) VALUES
(1, 3, 'Free Wash Load', 'Claimed', '2025-06-04 14:08:35', '2025-06-04 15:37:13'),
(2, 3, 'Free Dry Load', 'Claimed', '2026-01-08 15:36:26', '2026-01-08 15:37:04'),
(3, 5, 'Free Wash Load', 'Pending', '2026-03-31 16:21:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `gcash_requests`
--

CREATE TABLE `gcash_requests` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'GCASH',
  `amount` decimal(10,2) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `qr_code_url` varchar(500) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `is_notified` tinyint(1) DEFAULT 0,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gcash_requests`
--

INSERT INTO `gcash_requests` (`id`, `booking_id`, `user_id`, `customer_name`, `payment_method`, `amount`, `reference_number`, `qr_code_url`, `status`, `is_notified`, `requested_at`, `approved_at`, `payment_date`, `admin_notes`) VALUES
(1, 1, 3, 'Mark Angelo', 'GCASH', 199.00, 'GCASH-20260410-000001', NULL, 'completed', 0, '2026-04-10 16:05:22', '2026-04-10 16:06:44', '2026-04-10 16:06:44', NULL),
(2, 2, 3, 'Mark Angelo', 'Cash on Delivery', 229.00, 'COD-20260410-000002', NULL, 'completed', 0, '2026-04-10 16:10:44', '2026-04-10 16:12:00', '2026-04-10 16:12:00', NULL),
(3, 3, 3, 'Mark Angelo', 'GCASH', 199.00, 'GCASH-20260413-000003', NULL, 'completed', 0, '2026-04-13 06:30:42', '2026-04-13 06:41:02', '2026-04-13 06:41:02', NULL),
(4, 4, 3, 'Mark Angelo', 'Cash on Delivery', 199.00, 'COD-20260413-000004', NULL, 'completed', 0, '2026-04-13 10:26:01', '2026-04-13 10:29:18', '2026-04-13 10:29:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 16.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_type` enum('detergent','fabric_conditioner') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_name`, `stock_quantity`, `price`, `last_updated`, `item_type`) VALUES
(1, 'Surf', 38, 16.00, '2026-02-07 11:01:40', 'detergent'),
(2, 'Champion', 38, 16.00, '2026-04-10 16:10:27', 'detergent'),
(3, 'Breeze', 36, 16.00, '2026-04-10 15:34:39', 'detergent'),
(4, 'Ariel', 8, 16.00, '2026-04-13 10:24:19', 'detergent'),
(5, 'Surf Downy', 45, 11.00, '2026-03-24 07:11:21', 'fabric_conditioner'),
(6, 'Downy AntiBac', 38, 11.00, '2026-04-10 16:10:27', 'fabric_conditioner'),
(8, 'Del', 16, 11.00, '2026-04-13 10:24:19', 'fabric_conditioner'),
(9, 'Downy Sunrise Fresh', 40, 11.00, '2026-03-24 07:11:21', 'fabric_conditioner'),
(10, 'Champion AntiBacterial', 3, 11.00, '2026-04-10 14:54:34', 'fabric_conditioner'),
(11, 'Downy Kontra Kulob', 30, 11.00, '2026-03-24 07:11:21', 'fabric_conditioner');

--
-- Triggers `inventory`
--
DELIMITER $$
CREATE TRIGGER `before_insert_inventory` BEFORE INSERT ON `inventory` FOR EACH ROW BEGIN
    IF NEW.item_type = 'detergent' THEN
        SET NEW.price = 16.00;
    ELSEIF NEW.item_type = 'fabric_conditioner' THEN
        SET NEW.price = 11.00;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_inventory` BEFORE UPDATE ON `inventory` FOR EACH ROW BEGIN
    IF NEW.item_type != OLD.item_type THEN
        IF NEW.item_type = 'detergent' THEN
            SET NEW.price = 16.00;
        ELSEIF NEW.item_type = 'fabric_conditioner' THEN
            SET NEW.price = 11.00;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `machines`
--

CREATE TABLE `machines` (
  `id` int(11) NOT NULL,
  `machine_name` varchar(50) DEFAULT NULL,
  `machine_type` enum('washer','dryer') NOT NULL,
  `machine_model` varchar(255) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `last_maintenance_date` datetime DEFAULT NULL,
  `status` enum('Available','In Use','Needs Maintenance','Under Maintenance','Unavailable','Maintenance') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `machines`
--

INSERT INTO `machines` (`id`, `machine_name`, `machine_type`, `machine_model`, `usage_count`, `last_maintenance_date`, `status`) VALUES
(1, 'Washer1', 'washer', 'LG', 6, NULL, 'Available'),
(3, 'Washer2', 'washer', 'LG', 1, NULL, 'Available'),
(4, 'Washer3', 'washer', 'LG', 3, NULL, 'Available'),
(5, 'Washer4', 'washer', 'LG', 2, NULL, 'Available'),
(6, 'Washer5', 'washer', 'LG', 0, NULL, 'Available'),
(7, 'Washer6', 'washer', 'LG', 0, NULL, 'Available'),
(8, 'Dryer1', 'dryer', 'LG', 6, NULL, 'Available'),
(9, 'Dryer2', 'dryer', 'LG', 1, NULL, 'Available'),
(10, 'Dryer3', 'dryer', 'LG', 2, NULL, 'Available'),
(11, 'Dryer4', 'dryer', 'LG', 1, NULL, 'Available'),
(12, 'Dryer5', 'dryer', 'LG', 0, NULL, 'Available'),
(13, 'Dryer6', 'dryer', 'LG', 0, NULL, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `machine_count_backup`
--

CREATE TABLE `machine_count_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `date` date NOT NULL,
  `time_slot` varchar(20) NOT NULL,
  `available_machines` int(11) NOT NULL DEFAULT 12
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `machine_schedule`
--

CREATE TABLE `machine_schedule` (
  `id` int(11) NOT NULL,
  `machine_name` varchar(255) DEFAULT NULL,
  `restore_time` datetime DEFAULT NULL,
  `is_restored` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `machine_schedule`
--

INSERT INTO `machine_schedule` (`id`, `machine_name`, `restore_time`, `is_restored`) VALUES
(1, 'M1', '2025-07-01 04:43:18', 0),
(2, 'M2', '2025-07-01 04:43:19', 0),
(3, 'M3', '2025-07-01 05:36:26', 0),
(4, 'M3', '2025-07-01 06:08:28', 0),
(5, 'M4', '2025-07-01 06:08:31', 0),
(6, 'M5', '2025-07-01 06:08:32', 0),
(7, 'M4', '2025-07-01 06:08:34', 0),
(8, 'M3', '2025-07-01 06:08:34', 0),
(9, 'M6', '2025-07-01 06:08:35', 0),
(10, 'M4', '2025-07-01 06:10:29', 0),
(11, 'M1', '2025-07-01 06:10:51', 0),
(12, 'M1', '2025-07-01 07:15:13', 0),
(13, 'M1', '2025-07-02 08:09:58', 0),
(14, 'M2', '2025-07-02 10:14:51', 0),
(15, 'M3', '2025-07-02 10:19:41', 0),
(16, 'M5', '2025-07-10 06:37:15', 0),
(17, 'M4', '2025-12-04 11:59:29', 0),
(18, 'M4', '2025-12-04 11:59:34', 0),
(19, 'M1', '2025-12-04 12:12:32', 0);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_log`
--

CREATE TABLE `maintenance_log` (
  `id` int(11) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `machine_name` varchar(50) NOT NULL,
  `maintenance_type` enum('Usage-Based','Time-Based','Manual') DEFAULT 'Manual',
  `usage_count_before` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_by_name` varchar(255) DEFAULT NULL,
  `status_before` varchar(50) DEFAULT NULL,
  `status_after` varchar(50) DEFAULT NULL,
  `performed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedule`
--

CREATE TABLE `maintenance_schedule` (
  `id` int(11) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `machine_name` varchar(50) NOT NULL,
  `machine_type` enum('washer','dryer') NOT NULL,
  `trigger_type` enum('Usage-Based','Time-Based') NOT NULL,
  `trigger_value` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `status` enum('Pending','Acknowledged','Completed','Ignored') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `booking_id` int(11) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`, `booking_id`, `link`) VALUES
(1, 3, 'Booking Confirmed', 'Your booking #1 has been received. Queue Number: Q-001. Stage: Pending / Booked.', 1, '2026-04-10 16:05:01', 1, NULL),
(2, 3, 'GCASH Payment Request Submitted', 'Your GCASH payment request for ₱199.00 (Booking #1) has been submitted. Reference: GCASH-20260410-000001', 1, '2026-04-10 16:05:22', 1, '0'),
(3, 3, 'GCASH Payment Approved', 'Your GCASH payment request for ₱199.00 has been approved.\n\nReference Number: GCASH-20260410-000001\n\nClick here to view payment details.', 1, '2026-04-10 16:05:50', 1, 'user-profile.php?view_gcash_request=1'),
(4, 3, 'Payment Confirmation Sent', 'Your payment confirmation for booking #1 has been sent to admin for verification. You will be notified once it\'s approved.', 1, '2026-04-10 16:06:19', 1, 'user-profile.php'),
(5, 3, 'Payment Confirmed', 'Payment for booking #1 is complete.', 1, '2026-04-10 16:06:44', 1, 'user-profile.php'),
(6, 3, 'Laundry In Process', 'Your laundry (Q-001) is now in process.', 1, '2026-04-10 16:07:27', 1, NULL),
(7, 3, 'Booking Confirmed', 'Your booking #2 has been received. Queue Number: Q-002. Stage: Pending / Booked.', 1, '2026-04-10 16:10:27', 2, NULL),
(8, 3, 'Cash on Delivery Request Submitted', 'Your cash on delivery request for ₱229.00 (Booking #2) has been submitted. Reference: COD-20260410-000002', 1, '2026-04-10 16:10:44', 2, '0'),
(9, 3, 'GCASH Payment Approved', 'Your GCASH payment request for ₱229.00 has been approved.\n\nReference Number: COD-20260410-000002\n\nClick here to view payment details.', 1, '2026-04-10 16:11:22', 2, 'user-profile.php?view_gcash_request=2'),
(10, 3, 'Payment Confirmation Sent', 'Your Cash on Delivery payment confirmation for booking #2 has been sent to admin for verification. You will be notified once it\'s approved.', 1, '2026-04-10 16:11:40', 2, 'user-profile.php'),
(11, 3, 'Payment Confirmed', 'Payment for booking #2 is complete.', 1, '2026-04-10 16:12:00', 2, 'user-profile.php'),
(12, 3, 'Laundry In Process', 'Your laundry (Q-002) is now in process.', 1, '2026-04-10 16:12:26', 2, NULL),
(13, 3, 'Booking Confirmed', 'Your booking #3 has been received. Queue Number: Q-001. Stage: Pending / Booked.', 1, '2026-04-13 06:24:51', 3, NULL),
(14, 3, 'GCASH Payment Request Submitted', 'Your GCASH payment request for ₱199.00 (Booking #3) has been submitted. Reference: GCASH-20260413-000003', 1, '2026-04-13 06:30:42', 3, '0'),
(15, 3, 'GCASH Payment Approved', 'Your GCASH payment request for ₱199.00 has been approved.\n\nReference Number: GCASH-20260413-000003\n\nClick here to view payment details.', 1, '2026-04-13 06:31:54', 3, 'user-profile.php?view_gcash_request=3'),
(16, 3, 'Payment Confirmation Sent', 'Your payment confirmation for booking #3 has been sent to admin for verification. You will be notified once it\'s approved.', 1, '2026-04-13 06:40:08', 3, 'user-profile.php'),
(17, 3, 'Payment Confirmed', 'Payment for booking #3 is complete.', 1, '2026-04-13 06:41:02', 3, 'user-profile.php'),
(18, 3, 'Laundry Ready for Pickup', 'Your laundry (Q-001) is ready for pickup.', 1, '2026-04-13 06:41:14', 1, NULL),
(19, 3, 'Laundry Ready for Pickup', 'Your laundry (Q-002) is ready for pickup.', 1, '2026-04-13 06:41:14', 2, NULL),
(20, 3, 'Pickup Overdue', 'Your laundry (Q-001) pickup is overdue. Please claim it as soon as possible.', 1, '2026-04-13 06:41:14', 1, NULL),
(21, 3, 'Pickup Overdue', 'Your laundry (Q-002) pickup is overdue. Please claim it as soon as possible.', 1, '2026-04-13 06:41:14', 2, NULL),
(22, 3, 'Laundry In Process', 'Your laundry (Q-001) is now in process.', 1, '2026-04-13 06:42:44', 3, NULL),
(23, 3, 'Booking Confirmed', 'Your booking #4 has been received. Queue Number: Q-002. Stage: Pending / Booked.', 1, '2026-04-13 10:24:19', 4, NULL),
(24, 3, 'Cash on Delivery Request Submitted', 'Your cash on delivery request for ₱199.00 (Booking #4) has been submitted. Reference: COD-20260413-000004', 1, '2026-04-13 10:26:01', 4, '0'),
(25, 3, 'GCASH Payment Approved', 'Your GCASH payment request for ₱199.00 has been approved.\n\nReference Number: COD-20260413-000004\n\nClick here to view payment details.', 1, '2026-04-13 10:27:04', 4, 'user-profile.php?view_gcash_request=4'),
(26, 3, 'Payment Confirmation Sent', 'Your Cash on Delivery payment confirmation for booking #4 has been sent to admin for verification. You will be notified once it\'s approved.', 1, '2026-04-13 10:28:37', 4, 'user-profile.php'),
(27, 3, 'Payment Confirmed', 'Payment for booking #4 is complete.', 1, '2026-04-13 10:29:18', 4, 'user-profile.php'),
(28, 3, 'Laundry In Process', 'Your laundry (Q-002) is now in process.', 1, '2026-04-13 10:30:27', 4, NULL),
(29, 3, 'Laundry Ready for Pickup', 'Your laundry (Q-001) is ready for pickup.', 1, '2026-04-13 16:51:42', 3, NULL),
(30, 3, 'Pickup Overdue', 'Your laundry (Q-001) pickup is overdue. Please claim it as soon as possible.', 1, '2026-04-13 16:51:42', 3, NULL),
(31, 3, 'Laundry Ready for Pickup', 'Your laundry (Q-002) is ready for pickup.', 1, '2026-04-14 01:53:08', 4, NULL),
(32, 3, 'Pickup Overdue', 'Your laundry (Q-002) pickup is overdue. Please claim it as soon as possible.', 1, '2026-04-14 01:53:08', 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `service_type` varchar(255) NOT NULL DEFAULT '',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_time` datetime NOT NULL DEFAULT current_timestamp(),
  `is_holiday` tinyint(1) DEFAULT 0,
  `booking_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `amount`, `service_type`, `payment_date`, `date_time`, `is_holiday`, `booking_id`, `created_at`) VALUES
(1, 3, 194.00, 'full-service', '2026-04-03 14:08:33', '2026-04-03 22:08:33', 0, 6, '2026-04-03 22:08:33'),
(2, 3, 199.00, 'full-service', '2026-04-10 16:06:44', '2026-04-11 00:06:44', 0, 1, '2026-04-11 00:06:44'),
(3, 3, 199.00, 'full-service', '2026-04-13 06:41:02', '2026-04-13 14:41:02', 0, 3, '2026-04-13 14:41:02'),
(4, 3, 229.00, 'full-service,fold', '2026-04-10 16:12:00', '2026-04-11 00:12:00', 0, 2, '2026-04-11 00:12:00'),
(5, 3, 199.00, 'full-service', '2026-04-13 10:29:18', '2026-04-13 18:29:18', 0, 4, '2026-04-13 18:29:18');

-- --------------------------------------------------------

--
-- Table structure for table `paymongo_payments`
--

CREATE TABLE `paymongo_payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_intent_id` varchar(255) NOT NULL,
  `payment_method_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `qr_image_url` longtext DEFAULT NULL,
  `laundry_weight` decimal(10,2) DEFAULT 0.00,
  `selected_services` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `paymongo_payments`
--

INSERT INTO `paymongo_payments` (`id`, `booking_id`, `user_id`, `payment_intent_id`, `payment_method_id`, `amount`, `status`, `qr_image_url`, `laundry_weight`, `selected_services`, `created_at`, `updated_at`) VALUES
(1, 195, 3, 'pi_QZcFJTzvYEqEwes77yGrjsFm', 'pm_kFwpDxCit1BWG6XJo2tef4Jy', 172.00, 'pending', 'data:image/png;base64,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', 12.00, 'Full-Service - Wash & Dry', '2026-03-23 13:53:35', '2026-03-23 13:53:35'),
(2, 196, 3, 'pi_rrnLUoiZNtFWFjisG4VMEJWu', 'pm_TxC18pu5z5vSMcAowRUnctEg', 102.00, 'completed', 'data:image/png;base64,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', 12.00, 'Full-Service - Wash & Dry', '2026-03-23 14:24:53', '2026-03-23 14:42:05');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `price`, `description`) VALUES
(1, 'Full-Service - Wash & Dry', 145.00, 'Complete wash and dry service handled by our staff.'),
(3, 'Self-Service - Washer', 65.00, 'Use our self-service washing machines.'),
(4, 'Self-Service - Dryer', 80.00, 'Use our self-service drying machines.'),
(5, 'Fold', 30.00, 'Folding service for your laundry.');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `service_type` varchar(100) NOT NULL,
  `laundry_weight` decimal(5,2) DEFAULT NULL,
  `points_earned` int(11) DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Credit Card','E-Wallet','GCASH','Cash on Delivery') NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `customer_name`, `user_id`, `booking_id`, `service_type`, `laundry_weight`, `points_earned`, `total_amount`, `payment_method`, `transaction_date`) VALUES
(1, 'Mark Angelo', 3, 1, '1:Full-Service - Wash & Dry', NULL, 1, 199.00, '', '2026-04-10 16:06:44'),
(2, 'Mark Angelo', 3, 2, '1:Full-Service - Wash & Dry, 5:Fold', NULL, 1, 229.00, '', '2026-04-10 16:12:00'),
(3, 'Mark Angelo', 3, 3, '1:Full-Service - Wash & Dry', NULL, 1, 199.00, '', '2026-04-13 06:41:02'),
(4, 'Mark Angelo', 3, 4, '1:Full-Service - Wash & Dry', NULL, 1, 199.00, '', '2026-04-13 10:29:18');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','user','staff') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_points` int(11) DEFAULT 0,
  `user_status` tinyint(1) DEFAULT 1,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `phone`, `address`, `email`, `password`, `role`, `created_at`, `user_points`, `user_status`, `profile_picture`) VALUES
(1, 'Kyle', 'Santianez', '09612693586', 'Cavite', 'admin@gmail.com', '$2y$10$co61r6ham0jWAlKG5MyjrOqIFRTLIOE7CFi1AlGZdx583IxBHBuLi', 'admin', '2025-02-24 06:16:33', 0, 1, NULL),
(3, 'Mark', 'Angelo', '09152678901', 'Imus', 'markA@mail.com', '$2y$10$hL7.GLAFTtitJlWYv/eB2eUvSoOgrRzhzeX2hPbndQnJ40SDn99JO', 'user', '2025-03-06 04:00:51', 10, 1, 'profile_3_1775837590.png'),
(5, 'Albert', 'DeLeon', '01231314151', 'Bacoor Cavite', 'albertdeleon@gmail.com', '$2y$10$nGt0MlqbSIiRwAF4Wd.HA.BpRdwNcl28ckduedeMfYrCLThFeCvnW', 'user', '2025-04-16 04:15:22', 8, 1, NULL),
(6, 'testsubject', 'testting', '09677144840', 'Kawit', 'brycehartloyola27@gmail.com', '$2y$10$coY1H5tWZepo6efV/5B0zeYYf16WBngCx2DCX76rDz/qJij90F0W.', 'user', '2025-06-12 13:37:24', 3, 1, NULL),
(7, 'test', 'testuser', '0658777787', 'kawit,cavite', 'testsubject2@gmail.com', '$2y$10$mUy1.H9e0H/ejtqRULoovux2Z.22plVWlmHk3d6Y8IFgSizHV83v6', 'user', '2025-06-12 15:48:43', 1, 1, NULL),
(8, 'tester', 'testttetete', '0987878777', 'Kawit', 'testestestetee@gmail.com', '$2y$10$D7Xil2RKHdgfg/yF.jrY.OldASDWNMzbOH8RFUzI6yAGObXtwaWeG', 'user', '2025-06-12 15:51:35', 1, 1, NULL),
(9, 'test', 'test', '0314654654', 'testtt', 'albertdeleontee@gmail.com', '$2y$10$qEaLNaYyIdFQx1GtydqlQOgJ3d9j9euRB4HeyOGW1N7btK9BAZHsa', 'user', '2025-06-20 17:08:06', 3, 1, NULL),
(10, 'new', 'ew', '213212312312', 'testtt', 'new@gmail.com', '$2y$10$H.3XD7lVjBq50Zl1mRdJM.FeOSSiBfCCP52z9AyRqqKxad4Uf.JPe', 'user', '2025-06-20 17:37:08', 2, 1, NULL),
(11, 'User', 'one', '49684878978', 'Kawit', 'userone@gmail.com', '$2y$10$GqeGCw6DatqawtFfJ4F9KOFB..Li/Lpo8C8r7nnCRBYBe0olMllxy', 'user', '2025-06-21 05:19:47', 1, 1, NULL),
(12, 'user', 'two', '41657487', 'Cavite', 'usertwo@gmail.com', '$2y$10$XNHKGy3vLOpvQqCTxF6JieBMEHs4wOizUFKLQ51lFcP2JWlue/oFC', 'user', '2025-06-21 05:20:56', 4, 1, NULL),
(13, 'user', 'three', '478979879', 'Kawit', 'userthree@gmail.com', '$2y$10$yHuun.wtOnoDmggyyuUk1.m9aBkEJjEiLKHlkO4M6hmfhI6gvHbOG', 'user', '2025-06-21 05:21:40', 1, 1, NULL),
(14, 'Joyce', 'Ann', '09502147569', 'Las Piñas', 'staff@gmail.com', '$2y$10$my1TIiSUBlkNipRKTsUJXeVTixwkYq1Ri5ndmniOY5N.go4dQuVEy', 'staff', '2026-03-22 06:42:13', 0, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_bookings_queue_date_number` (`booking_date`,`queue_number`),
  ADD KEY `idx_bookings_order_stage` (`order_stage`),
  ADD KEY `idx_bookings_pickup_status` (`pickup_status`);

--
-- Indexes for table `booking_services`
--
ALTER TABLE `booking_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `claimed_rewards`
--
ALTER TABLE `claimed_rewards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_id` (`user_id`);

--
-- Indexes for table `gcash_requests`
--
ALTER TABLE `gcash_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_booking` (`booking_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_name` (`item_name`);

--
-- Indexes for table `machines`
--
ALTER TABLE `machines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `machine_name` (`machine_name`),
  ADD KEY `idx_machine_type_status` (`machine_type`,`status`);

--
-- Indexes for table `machine_schedule`
--
ALTER TABLE `machine_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance_log`
--
ALTER TABLE `maintenance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_machine_id` (`machine_id`),
  ADD KEY `idx_performed_at` (`performed_at`);

--
-- Indexes for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_machine_id` (`machine_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_notifications_booking` (`booking_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_payment_intent_id` (`payment_intent_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transactions_user_id` (`user_id`),
  ADD KEY `fk_transactions_booking_id` (`booking_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `booking_services`
--
ALTER TABLE `booking_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `claimed_rewards`
--
ALTER TABLE `claimed_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `gcash_requests`
--
ALTER TABLE `gcash_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `machines`
--
ALTER TABLE `machines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `machine_schedule`
--
ALTER TABLE `machine_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `maintenance_log`
--
ALTER TABLE `maintenance_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_services`
--
ALTER TABLE `booking_services`
  ADD CONSTRAINT `booking_services_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `claimed_rewards`
--
ALTER TABLE `claimed_rewards`
  ADD CONSTRAINT `claimed_rewards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `gcash_requests`
--
ALTER TABLE `gcash_requests`
  ADD CONSTRAINT `gcash_requests_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `gcash_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_booking_id` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `fk_transactions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
