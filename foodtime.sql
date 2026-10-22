-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 12, 2026 at 05:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `foodtime`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `categoryid` varchar(10) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`categoryid`, `category_name`) VALUES
('CAT001', 'Appetizers'),
('CAT002', 'Soups'),
('CAT003', 'Chicken Dishes'),
('CAT004', 'Beef & Lamb'),
('CAT005', 'Seafood'),
('CAT006', 'Rice Dishes'),
('CAT007', 'Noodles'),
('CAT008', 'Vegetable Dishes'),
('CAT009', 'Special '),
('CAT010', 'Western '),
('CAT011', 'Desserts'),
('CAT012', 'Beverages');

-- --------------------------------------------------------

--
-- Table structure for table `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `conversation_id` varchar(10) NOT NULL,
  `customer_id` varchar(10) NOT NULL,
  `admin_id` varchar(10) DEFAULT NULL,
  `related_orderid` varchar(20) DEFAULT NULL,
  `subject` varchar(150) DEFAULT NULL,
  `status` enum('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_conversations`
--

INSERT INTO `chat_conversations` (`conversation_id`, `customer_id`, `admin_id`, `related_orderid`, `subject`, `status`, `created_at`, `updated_at`, `category`) VALUES
('CH000001', 'UR001', 'UR004', NULL, 'Hello World', 'pending', '2026-03-20 16:49:13', '2026-03-24 08:49:22', NULL),
('CH000002', 'UR001', 'UR004', 'ORD00006', 'Hello World', 'closed', '2026-03-20 16:49:15', '2026-04-11 10:29:50', NULL),
('CH000003', 'UR001', NULL, 'ORD00006', 'Hello World', 'closed', '2026-03-20 16:49:16', '2026-04-11 10:29:50', NULL),
('CH000004', 'UR001', 'UR004', 'ORD00006', 'Hello World', 'closed', '2026-03-20 16:49:16', '2026-04-11 10:29:50', NULL),
('CH000005', 'UR001', 'UR004', NULL, 'Account Blocked', 'pending', '2026-03-21 18:43:27', '2026-03-24 08:30:50', 'Account Blocked'),
('CH000006', 'UR001', 'UR004', 'ORD00044', 'Order Issue', 'open', '2026-03-22 05:25:08', '2026-03-22 05:25:08', 'Order Issue'),
('CH000007', 'UR001', 'UR003', NULL, 'Order Issue', 'resolved', '2026-03-31 12:46:34', '2026-04-11 06:26:53', 'Order Issue'),
('CH000008', 'UR001', 'UR003', NULL, 'Order Issue', 'resolved', '2026-04-06 14:37:03', '2026-04-11 06:27:00', 'Order Issue'),
('CH000009', 'UR001', 'UR003', 'ORD00016', 'Other', 'closed', '2026-04-06 14:37:45', '2026-04-11 06:27:13', 'Other'),
('CH000010', 'UR001', 'UR003', NULL, 'Account Blocked', 'pending', '2026-04-07 08:17:38', '2026-04-09 07:29:33', 'Account Blocked'),
('CH000011', 'UR001', 'UR003', NULL, 'Order Issue', 'open', '2026-04-09 10:27:06', '2026-04-11 06:26:05', 'Order Issue'),
('CH000012', 'UR002', 'UR003', NULL, 'Account Blocked', 'open', '2026-04-11 06:23:05', '2026-04-11 06:23:35', 'Account Blocked'),
('CH000013', 'UR002', 'UR003', NULL, 'Order Issue', 'open', '2026-04-11 06:23:09', '2026-04-11 06:23:09', 'Order Issue'),
('CH000014', 'UR002', 'UR003', NULL, 'CCC', 'open', '2026-04-11 06:23:23', '2026-04-11 06:23:23', 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` varchar(10) NOT NULL,
  `conversation_id` varchar(10) NOT NULL,
  `sender_id` varchar(20) DEFAULT NULL,
  `receiver_id` varchar(10) DEFAULT NULL,
  `message_type` enum('text','image','audio','system','auto_reply') NOT NULL DEFAULT 'text',
  `message_text` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `conversation_id`, `sender_id`, `receiver_id`, `message_type`, `message_text`, `attachment_path`, `is_read`, `created_at`) VALUES
('MSG000001', 'CH000003', 'UR001', NULL, 'text', '11111', NULL, 0, '2026-03-20 16:49:36'),
('MSG000002', 'CH000003', 'UR005', 'UR001', 'text', 'hello xiao yan', NULL, 1, '2026-03-20 16:53:43'),
('MSG000003', 'CH000005', 'UR004', 'UR001', 'system', '👋 Hi! Thank you for contacting FOODTIME Support.\nTopic: Account Blocked (Ref: CH000005)\nA support agent will respond shortly. Please describe your issue in detail below.', NULL, 1, '2026-03-21 18:43:27'),
('MSG000004', 'CH000001', 'UR001', 'UR004', 'text', 'hello bro', NULL, 1, '2026-03-22 05:15:13'),
('MSG000005', 'CH000001', 'UR001', 'UR004', 'text', 'hello yy', NULL, 1, '2026-03-22 05:24:24'),
('MSG000006', 'CH000001', 'AUTOBOT', 'UR001', 'auto_reply', '👋 Hello! Welcome to FOODTIME Support. How can we help you today? Feel free to describe your issue and our team will assist you shortly.', NULL, 1, '2026-03-22 05:24:24'),
('MSG000007', 'CH000006', 'UR004', 'UR001', 'system', '👋 Hi! Thank you for contacting FOODTIME Support.\nTopic: Order Issue (Ref: CH000006)\nA support agent will respond shortly. Please describe your issue in detail below.', NULL, 1, '2026-03-22 05:25:08'),
('MSG000008', 'CH000001', 'UR001', 'UR004', 'text', 'hello admin why i cannot order', NULL, 1, '2026-03-23 07:35:42'),
('MSG000009', 'CH000005', 'UR001', 'UR004', 'text', 'hello how can i unblock my account?', NULL, 1, '2026-03-23 14:19:47'),
('MSG000010', 'CH000005', 'UR004', 'UR001', 'text', 'hello', NULL, 1, '2026-03-24 08:30:50'),
('MSG000011', 'CH000001', 'UR004', 'UR001', 'text', 'hello im here', NULL, 1, '2026-03-24 08:49:22'),
('MSG000012', 'CH000007', NULL, 'UR001', 'auto_reply', 'Hi! Thank you for contacting FOODTIME Support. We received your order issue message. Our support team will review it shortly. Please provide your order ID and briefly describe the problem.', NULL, 1, '2026-03-31 12:46:34'),
('MSG000013', 'CH000007', 'UR001', 'UR003', 'text', 'got issue', NULL, 0, '2026-04-06 14:36:45'),
('MSG000014', 'CH000007', 'UR001', 'UR003', 'text', 'ORD001', NULL, 0, '2026-04-06 14:36:57'),
('MSG000015', 'CH000008', NULL, 'UR001', 'auto_reply', '👋 Good evening! Thank you for reaching out to FOODTIME Support.\n\nWe\'ve received your Order Issue report at 10:37 PM on 06 Apr 2026.\n\n⏱ A support agent will be with you within 5–15 minutes.\n\nTo help us resolve this faster, please share:\n  • Your Order ID (e.g. ORD001234)\n  • What went wrong — missing item, wrong order, late delivery, etc.\n  • Any photos if relevant\n\nWe\'re on it! 🍽️', NULL, 1, '2026-04-06 14:37:03'),
('MSG000016', 'CH000009', NULL, 'UR001', 'auto_reply', '👋 Good evening! Thank you for contacting FOODTIME Support.\n\nWe\'ve received your enquiry at 10:37 PM on 06 Apr 2026.\n\n⏱ A support agent will be with you within 5–15 minutes.\n\nPlease go ahead and describe your issue in as much detail as you can — the more context you give us, the faster we can help! 💬', NULL, 1, '2026-04-06 14:37:45'),
('MSG000017', 'CH000010', NULL, 'UR001', 'auto_reply', '👋 Good afternoon! Thank you for contacting FOODTIME Support.\n\nWe\'ve received your Account Blocked request at 04:17 PM on 07 Apr 2026.\n\n⏱ A support agent will review your case within 5–15 minutes.\n\nTo speed up your appeal, please share:\n  • Your username or registered email\n  • When you noticed the restriction\n  • Any error message you received\n\nWe\'ll do our best to get you back on track! 🔓', NULL, 1, '2026-04-07 08:17:38'),
('MSG000018', 'CH000010', 'UR001', 'UR003', 'text', 'hello', NULL, 0, '2026-04-07 08:17:44'),
('MSG000019', 'CH000010', 'UR001', 'UR003', 'text', 'xiao yan', NULL, 0, '2026-04-07 08:17:47'),
('MSG000020', 'CH000010', 'UR004', 'UR001', 'text', 'hello kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', NULL, 0, '2026-04-09 07:29:33'),
('MSG000021', 'CH000009', 'UR004', 'UR001', 'text', 'aaaaaaaaaaaaaaaaaaaaaaaaaaa', NULL, 0, '2026-04-09 07:30:04'),
('MSG000022', 'CH000008', 'UR004', 'UR001', 'text', 'aaaaaaaaaaaaaaaaaaa', NULL, 0, '2026-04-09 07:30:16'),
('MSG000023', 'CH000007', 'UR004', 'UR001', 'text', 'aaaaaaaaaaaaaaaaaaaaaa', NULL, 1, '2026-04-09 07:31:44'),
('MSG000024', 'CH000011', NULL, 'UR001', 'auto_reply', '👋 Good evening! Thank you for reaching out to FOODTIME Support.\n\nWe\'ve received your Order Issue report at 06:27 PM on 09 Apr 2026.\n\n⏱ A support agent will be with you within 15–30 minutes.\n\nTo help us resolve this faster, please share:\n  • Your Order ID (e.g. ORD001234)\n  • What went wrong — missing item, wrong order, late delivery, etc.\n  • Any photos if relevant\n\nWe\'re on it! 🍽️', NULL, 1, '2026-04-09 10:27:06'),
('MSG000025', 'CH000011', 'UR001', 'UR003', 'text', 'heeeeee', NULL, 0, '2026-04-09 10:27:12'),
('MSG000026', 'CH000011', 'UR004', 'UR001', 'text', 'hellllll', NULL, 1, '2026-04-09 10:27:46'),
('MSG000027', 'CH000011', 'UR004', 'UR001', 'text', 'kkkkkk', NULL, 1, '2026-04-09 10:27:59'),
('MSG000028', 'CH000012', NULL, 'UR002', 'auto_reply', '👋 Good afternoon! Thank you for contacting FOODTIME Support.\n\nWe\'ve received your Account Blocked request at 02:23 PM on 11 Apr 2026.\n\n⏱ A support agent will review your case within 15–30 minutes.\n\nTo speed up your appeal, please share:\n  • Your username or registered email\n  • When you noticed the restriction\n  • Any error message you received\n\nWe\'ll do our best to get you back on track! 🔓', NULL, 1, '2026-04-11 06:23:05'),
('MSG000029', 'CH000013', NULL, 'UR002', 'auto_reply', '👋 Good afternoon! Thank you for reaching out to FOODTIME Support.\n\nWe\'ve received your Order Issue report at 02:23 PM on 11 Apr 2026.\n\n⏱ A support agent will be with you within 15–30 minutes.\n\nTo help us resolve this faster, please share:\n  • Your Order ID (e.g. ORD001234)\n  • What went wrong — missing item, wrong order, late delivery, etc.\n  • Any photos if relevant\n\nWe\'re on it! 🍽️', NULL, 1, '2026-04-11 06:23:09'),
('MSG000030', 'CH000014', NULL, 'UR002', 'auto_reply', '👋 Good afternoon! Thank you for contacting FOODTIME Support.\n\nWe\'ve received your enquiry at 02:23 PM on 11 Apr 2026.\n\n⏱ A support agent will be with you within 15–30 minutes.\n\nPlease go ahead and describe your issue in as much detail as you can — the more context you give us, the faster we can help! 💬', NULL, 1, '2026-04-11 06:23:23'),
('MSG000031', 'CH000012', 'UR002', NULL, 'text', 'hello', NULL, 0, '2026-04-11 06:23:35'),
('MSG000032', 'CH000011', 'UR001', NULL, 'text', 'hai', NULL, 0, '2026-04-11 06:25:23'),
('MSG000033', 'CH000011', 'UR001', NULL, 'text', 'hai', NULL, 0, '2026-04-11 06:25:25'),
('MSG000034', 'CH000011', 'UR001', NULL, 'text', 'hai\'', NULL, 0, '2026-04-11 06:25:28'),
('MSG000035', 'CH000011', 'UR004', 'UR001', 'text', 'hai', NULL, 1, '2026-04-11 06:26:05'),
('MSG000036', 'CH000004', NULL, 'UR001', 'system', 'This issue has been resolved. Thank you for contacting FOODTIME Support.', NULL, 1, '2026-04-11 06:26:43'),
('MSG000037', 'CH000007', NULL, 'UR001', 'system', 'This issue has been resolved. Thank you for contacting FOODTIME Support.', NULL, 0, '2026-04-11 06:26:53'),
('MSG000038', 'CH000008', NULL, 'UR001', 'system', 'This issue has been resolved. Thank you for contacting FOODTIME Support.', NULL, 0, '2026-04-11 06:27:00'),
('MSG000039', 'CH000009', NULL, 'UR001', 'system', 'This issue has been resolved. Thank you for contacting FOODTIME Support.', NULL, 0, '2026-04-11 06:27:06');

-- --------------------------------------------------------

--
-- Table structure for table `chat_message_reads`
--

CREATE TABLE `chat_message_reads` (
  `id` int(10) UNSIGNED NOT NULL,
  `message_id` varchar(10) NOT NULL,
  `admin_id` varchar(10) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_message_reads`
--

INSERT INTO `chat_message_reads` (`id`, `message_id`, `admin_id`, `read_at`) VALUES
(1, 'MSG000001', 'UR003', '2026-04-10 17:17:36'),
(2, 'MSG000001', 'UR004', '2026-04-10 17:17:36'),
(3, 'MSG000001', 'UR006', '2026-04-10 17:17:36'),
(4, 'MSG000004', 'UR003', '2026-04-10 17:17:36'),
(5, 'MSG000004', 'UR004', '2026-04-10 17:17:36'),
(6, 'MSG000004', 'UR006', '2026-04-10 17:17:36'),
(7, 'MSG000005', 'UR003', '2026-04-10 17:17:36'),
(8, 'MSG000005', 'UR004', '2026-04-10 17:17:36'),
(9, 'MSG000005', 'UR006', '2026-04-10 17:17:36'),
(10, 'MSG000008', 'UR003', '2026-04-10 17:17:36'),
(11, 'MSG000008', 'UR004', '2026-04-10 17:17:36'),
(12, 'MSG000008', 'UR006', '2026-04-10 17:17:36'),
(13, 'MSG000009', 'UR003', '2026-04-10 17:17:36'),
(14, 'MSG000009', 'UR004', '2026-04-10 17:17:36'),
(15, 'MSG000009', 'UR006', '2026-04-10 17:17:36'),
(16, 'MSG000013', 'UR003', '2026-04-10 17:17:36'),
(17, 'MSG000013', 'UR004', '2026-04-10 17:17:36'),
(18, 'MSG000013', 'UR006', '2026-04-10 17:17:36'),
(19, 'MSG000014', 'UR003', '2026-04-10 17:17:36'),
(20, 'MSG000014', 'UR004', '2026-04-10 17:17:36'),
(21, 'MSG000014', 'UR006', '2026-04-10 17:17:36'),
(22, 'MSG000018', 'UR003', '2026-04-10 17:17:36'),
(23, 'MSG000018', 'UR004', '2026-04-10 17:17:36'),
(24, 'MSG000018', 'UR006', '2026-04-10 17:17:36'),
(25, 'MSG000019', 'UR003', '2026-04-10 17:17:36'),
(26, 'MSG000019', 'UR004', '2026-04-10 17:17:36'),
(27, 'MSG000019', 'UR006', '2026-04-10 17:17:36'),
(28, 'MSG000025', 'UR003', '2026-04-10 17:17:36'),
(29, 'MSG000025', 'UR004', '2026-04-10 17:17:36'),
(30, 'MSG000025', 'UR006', '2026-04-10 17:17:36'),
(31, 'MSG000032', 'UR004', '2026-04-11 06:25:54'),
(32, 'MSG000033', 'UR004', '2026-04-11 06:25:54'),
(33, 'MSG000034', 'UR004', '2026-04-11 06:25:54'),
(34, 'MSG000031', 'UR004', '2026-04-11 06:25:56');

-- --------------------------------------------------------

--
-- Table structure for table `id_counters`
--

CREATE TABLE `id_counters` (
  `counter_name` varchar(50) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `id_counters`
--

INSERT INTO `id_counters` (`counter_name`, `last_number`) VALUES
('user', 14);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `orderid` varchar(20) NOT NULL,
  `userid` varchar(10) NOT NULL,
  `order_type` enum('dine_in','takeaway') NOT NULL DEFAULT 'dine_in',
  `tableid` varchar(20) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_rm` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','preparing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `special_notes` text DEFAULT NULL,
  `points_earned` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`orderid`, `userid`, `order_type`, `tableid`, `customer_name`, `customer_phone`, `customer_email`, `subtotal`, `tax`, `discount_rm`, `status`, `total_price`, `payment_method`, `special_notes`, `points_earned`, `created_at`, `updated_at`) VALUES
('ORD00001', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'cash', '', 50, '2026-03-31 13:04:43', '2026-03-31 13:06:31'),
('ORD00002', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'card', 'Card: ****2342', 50, '2026-03-31 13:05:14', '2026-03-31 13:05:14'),
('ORD00003', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'online_banking', 'Bank: maybank', 50, '2026-03-31 13:05:37', '2026-03-31 13:05:37'),
('ORD00004', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'tng', 'E-Wallet: TNG', 55, '2026-03-31 13:06:02', '2026-03-31 13:06:09'),
('ORD00005', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 13:48:29', '2026-03-31 13:51:37'),
('ORD00006', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 13:52:22', '2026-03-31 13:52:30'),
('ORD00007', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 13:58:38', '2026-03-31 13:58:42'),
('ORD00008', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 14:07:28', '2026-03-31 14:07:36'),
('ORD00009', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 14:07:58', '2026-03-31 14:08:00'),
('ORD00010', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 14:10:42', '2026-03-31 14:10:49'),
('ORD00011', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'tng', 'E-Wallet: TNG', 55, '2026-03-31 14:11:13', '2026-03-31 14:11:20'),
('ORD00012', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 14:13:33', '2026-03-31 14:13:40'),
('ORD00013', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 22.00, 1.32, 0.00, 'paid', 23.32, 'tng', 'E-Wallet: TNG', 115, '2026-03-31 14:16:19', '2026-03-31 14:16:25'),
('ORD00014', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'card', 'Card: ****3434', 50, '2026-03-31 14:20:11', '2026-03-31 14:20:11'),
('ORD00015', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'online_banking', 'Bank: maybank', 55, '2026-03-31 14:20:54', '2026-03-31 14:20:54'),
('ORD00016', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'pending', 10.60, 'tng', 'E-Wallet: TNG', 0, '2026-03-31 16:26:19', '2026-03-31 16:26:19'),
('ORD00017', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 16:33:12', '2026-03-31 16:37:35'),
('ORD00018', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'cash', '', 50, '2026-03-31 20:33:47', '2026-03-31 20:34:20'),
('ORD00019', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 21:15:04', '2026-03-31 21:15:09'),
('ORD00020', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-03-31 21:15:36', '2026-03-31 21:15:42'),
('ORD00021', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 200.00, 12.00, 0.00, 'paid', 212.00, 'cash', '', 1060, '2026-04-01 00:31:06', '2026-04-01 00:32:56'),
('ORD00022', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 200.00, 12.00, 0.00, 'paid', 212.00, 'cash', '', 1060, '2026-04-01 00:31:26', '2026-04-01 00:32:57'),
('ORD00023', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 200.00, 12.00, 0.00, 'paid', 212.00, 'cash', '', 1060, '2026-04-01 00:31:52', '2026-04-01 00:33:00'),
('ORD00024', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 200.00, 12.00, 0.00, 'paid', 212.00, 'cash', '', 1060, '2026-04-01 00:32:09', '2026-04-01 00:33:02'),
('ORD00025', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 150.00, 9.00, 0.00, 'paid', 159.00, 'cash', '', 795, '2026-04-01 00:32:36', '2026-04-01 00:33:03'),
('ORD00026', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 60.00, 1.80, 0.00, 'paid', 31.80, 'cash', 'Voucher: FT0D00FE67 (RM 30.00 off)', 155, '2026-04-01 00:42:18', '2026-04-01 00:43:07'),
('ORD00027', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 240.00, 14.40, 0.00, 'paid', 254.40, 'card', 'Card: ****2323', 1270, '2026-04-03 09:51:40', '2026-04-03 09:51:40'),
('ORD00028', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-04-04 13:02:49', '2026-04-04 13:03:35'),
('ORD00029', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 20.00, 1.20, 0.00, 'paid', 21.20, 'cash', '', 105, '2026-04-05 20:31:43', '2026-04-06 13:07:19'),
('ORD00030', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'cancelled', 10.60, 'card', 'Payment: Stripe Card', 0, '2026-04-05 21:41:25', '2026-04-05 21:44:52'),
('ORD00031', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'card', 'Payment: Stripe Card', 55, '2026-04-05 22:25:06', '2026-04-05 22:25:32'),
('ORD00032', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'card', 'Payment: Stripe Card', 50, '2026-04-06 12:39:03', '2026-04-06 12:39:46'),
('ORD00033', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-04-06 22:43:40', '2026-04-06 22:43:51'),
('ORD00034', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'cash', '', 55, '2026-04-07 13:07:29', '2026-04-07 13:07:49'),
('ORD00035', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'cash', '', 55, '2026-04-07 13:22:10', '2026-04-07 13:23:02'),
('ORD00036', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 13.00, 0.78, 0.00, 'paid', 13.78, 'cash', '', 65, '2026-04-07 13:22:27', '2026-04-07 13:23:00'),
('ORD00037', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'pending', 11.66, 'tng', 'E-Wallet: TNG', 0, '2026-04-07 15:07:20', '2026-04-07 15:07:20'),
('ORD00038', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 22.00, 1.32, 0.00, 'paid', 23.32, 'cash', '', 115, '2026-04-07 15:30:18', '2026-04-07 15:30:39'),
('ORD00039', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 27.00, 1.62, 0.00, 'paid', 28.62, 'online_banking', 'Bank: publicbank', 140, '2026-04-07 15:34:58', '2026-04-07 15:34:58'),
('ORD00040', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 13.00, 0.78, 0.00, 'paid', 13.78, 'tng', 'E-Wallet: TNG', 65, '2026-04-07 15:41:18', '2026-04-07 15:41:43'),
('ORD00041', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 12.00, 0.72, 0.00, 'paid', 12.72, 'card', 'Payment: Stripe Card', 60, '2026-04-07 15:49:01', '2026-04-07 15:49:54'),
('ORD00042', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'card', 'Payment: Stripe Card', 55, '2026-04-07 16:00:38', '2026-04-07 16:01:05'),
('ORD00043', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'grabpay', 'E-Wallet: GRABPAY', 55, '2026-04-07 16:20:07', '2026-04-07 16:20:26'),
('ORD00044', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 14.00, 0.84, 0.00, 'paid', 14.84, 'tng', 'E-Wallet: TNG', 70, '2026-04-07 16:31:58', '2026-04-07 16:32:05'),
('ORD00045', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'pending', 11.66, 'cash', '', 0, '2026-04-07 23:38:50', '2026-04-07 23:38:50'),
('ORD00046', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'online_banking', 'Bank: maybank', 55, '2026-04-07 23:40:02', '2026-04-07 23:40:02'),
('ORD00047', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 13.00, 0.78, 0.00, 'paid', 13.78, 'online_banking', 'Bank: publicbank', 65, '2026-04-07 23:41:26', '2026-04-07 23:41:26'),
('ORD00048', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'tng', 'E-Wallet: TNG', 55, '2026-04-07 23:44:08', '2026-04-07 23:44:19'),
('ORD00049', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'online_banking', 'Bank: hongleong', 55, '2026-04-07 23:55:22', '2026-04-07 23:55:22'),
('ORD00050', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 30.00, 1.80, 0.00, 'paid', 31.80, 'cash', '', 155, '2026-04-07 23:57:23', '2026-04-08 11:41:59'),
('ORD00051', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'cash', '', 55, '2026-04-08 11:24:20', '2026-04-08 11:41:56'),
('ORD00052', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 22.00, 1.32, 0.00, 'paid', 23.32, 'online_banking', 'Bank: maybank', 115, '2026-04-08 11:24:38', '2026-04-08 11:24:38'),
('ORD00053', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 20.00, 0.90, 0.00, 'cancelled', 15.90, 'card', 'Voucher: FT78EF98FB (RM 5.00 off) | Payment: Stripe Card', 0, '2026-04-08 11:25:25', '2026-04-08 11:25:35'),
('ORD00054', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 20.00, 0.90, 0.00, 'cancelled', 15.90, 'card', 'Voucher: FT78EF98FB (RM 5.00 off) | Payment: Stripe Card', 0, '2026-04-08 11:25:45', '2026-04-08 11:25:46'),
('ORD00055', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 20.00, 1.20, 0.00, 'paid', 21.20, 'card', 'Payment: Stripe Card', 105, '2026-04-08 11:26:19', '2026-04-08 11:27:04'),
('ORD00056', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 22.00, 1.02, 0.00, 'paid', 18.02, 'online_banking', 'Bank: maybank | Voucher: FT78EF98FB (RM 5.00 off)', 90, '2026-04-08 11:27:53', '2026-04-08 11:27:53'),
('ORD00057', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.30, 0.00, 'paid', 5.30, 'card', 'Voucher: FT37EEE5C0 (RM 5.00 off) | Payment: Stripe Card', 25, '2026-04-08 11:34:38', '2026-04-08 11:34:58'),
('ORD00058', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 15.00, 0.60, 5.00, 'paid', 10.60, 'card', 'Voucher: FT215A953A (RM 5.00 off) | Payment: Stripe Card', 50, '2026-04-08 11:52:20', '2026-04-08 11:52:39'),
('ORD00059', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 12.00, 0.42, 5.00, 'paid', 7.42, 'tng', 'E-Wallet: TNG | Voucher: FT215A953A (RM 5.00 off)', 35, '2026-04-08 12:05:28', '2026-04-08 12:06:17'),
('ORD00060', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 15.00, 0.60, 5.00, 'paid', 10.60, 'online_banking', 'Bank: maybank | Voucher: FT37EEE5C0 (RM 5.00 off)', 50, '2026-04-08 12:08:09', '2026-04-08 12:08:09'),
('ORD00061', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 14.00, 0.54, 5.00, 'paid', 9.54, 'card', 'Voucher: FT730BDC00 (RM 5.00 off) | Payment: Stripe Card', 45, '2026-04-08 12:09:09', '2026-04-08 12:09:28'),
('ORD00062', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 15.00, 0.60, 5.00, 'paid', 10.60, 'cash', 'Voucher: FT730BDC00 (RM 5.00 off)', 50, '2026-04-08 12:10:16', '2026-04-08 12:10:49'),
('ORD00063', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 14.00, 0.84, 0.00, 'paid', 14.84, 'card', 'Payment: Stripe Card', 70, '2026-04-08 17:22:21', '2026-04-08 17:22:38'),
('ORD00064', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.30, 5.00, 'paid', 5.30, 'card', 'Voucher: FT096953A5 (RM 5.00 off) | Payment: Stripe Card', 25, '2026-04-08 17:54:18', '2026-04-08 17:54:35'),
('ORD00065', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'online_banking', 'Bank: publicbank', 50, '2026-04-09 18:36:15', '2026-04-09 18:36:15'),
('ORD00066', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.30, 5.00, 'paid', 5.30, 'cash', 'Voucher: FT3F2E7C18 (RM 5.00 off)', 25, '2026-04-09 18:39:09', '2026-04-09 18:39:28'),
('ORD00067', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 27.00, 1.62, 0.00, 'pending', 28.62, 'cash', '', 0, '2026-04-09 18:41:45', '2026-04-09 18:41:45'),
('ORD00068', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'card', 'Payment: Stripe Card', 50, '2026-04-09 18:46:43', '2026-04-09 18:47:02'),
('ORD00069', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'cash', '', 50, '2026-04-10 14:23:41', '2026-04-11 02:10:14'),
('ORD00070', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-04-10 19:25:15', '2026-04-10 19:25:54'),
('ORD00071', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'pending', 11.66, 'tng', 'E-Wallet: TNG', 0, '2026-04-10 20:40:56', '2026-04-10 20:40:56'),
('ORD00072', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 11.00, 0.66, 0.00, 'paid', 11.66, 'tng', 'E-Wallet: TNG', 55, '2026-04-10 21:16:38', '2026-04-10 21:16:54'),
('ORD00073', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 506.00, 30.36, 0.00, 'paid', 536.36, 'cash', '', 2680, '2026-04-11 02:09:41', '2026-04-11 02:10:12'),
('ORD00074', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.00, 10.00, 'paid', 0.00, 'online_banking', 'Bank: maybank | Voucher: FT74ED3D77 (RM 10.00 off)', 0, '2026-04-12 04:00:05', '2026-04-12 04:00:05'),
('ORD00075', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'online_banking', 'Bank: publicbank', 50, '2026-04-12 04:02:33', '2026-04-12 04:02:33'),
('ORD00076', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 20.00, 1.20, 5.00, 'pending', 16.20, 'cash', 'Voucher: FT5E8426D6 (RM 5.00 off)', 0, '2026-04-12 11:37:41', '2026-04-12 11:37:41'),
('ORD00077', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'online_banking', 'Bank: publicbank', 50, '2026-04-12 11:38:30', '2026-04-12 11:38:30'),
('ORD00078', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 5.00, 'paid', 5.60, 'online_banking', 'Bank: maybank | Voucher: FT5D7F914D (RM 5.00 off)', 25, '2026-04-12 11:39:05', '2026-04-12 11:39:05'),
('ORD00079', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 5.00, 'paid', 5.60, 'tng', 'E-Wallet: TNG | Voucher: FT5507827E (RM 5.00 off)', 25, '2026-04-12 11:46:35', '2026-04-12 11:47:08'),
('ORD00080', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 0.00, 'paid', 10.60, 'tng', 'E-Wallet: TNG', 50, '2026-04-12 11:51:25', '2026-04-12 11:52:15'),
('ORD00081', 'UR001', 'dine_in', NULL, 'xiaoyan1', '', '', 10.00, 0.60, 5.00, 'paid', 5.60, 'tng', 'E-Wallet: TNG | Voucher: FTAA257F46 (RM 5.00 off)', 25, '2026-04-12 11:52:36', '2026-04-12 11:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `orderitemid` varchar(10) NOT NULL,
  `orderid` varchar(20) DEFAULT NULL,
  `productid` varchar(10) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `custom_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_options`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`orderitemid`, `orderid`, `productid`, `product_name`, `quantity`, `price`, `subtotal`, `created_at`, `custom_options`) VALUES
('OI00001', 'ORD00001', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, NULL),
('OI00002', 'ORD00002', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00003', 'ORD00003', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00004', 'ORD00004', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, NULL),
('OI00005', 'ORD00005', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, NULL),
('OI00006', 'ORD00006', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00007', 'ORD00007', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, NULL),
('OI00008', 'ORD00008', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, NULL),
('OI00009', 'ORD00009', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00010', 'ORD00010', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00011', 'ORD00011', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, NULL),
('OI00012', 'ORD00012', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, NULL),
('OI00013', 'ORD00013', 'PRD006', 'Hot & Sour Soup', 2, 11.00, 22.00, NULL, NULL),
('OI00014', 'ORD00014', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00015', 'ORD00015', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, NULL),
('OI00016', 'ORD00016', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00017', 'ORD00017', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00018', 'ORD00018', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, NULL),
('OI00019', 'ORD00019', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, NULL),
('OI00020', 'ORD00020', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00021', 'ORD00021', 'PRD002', 'Dumplings', 20, 10.00, 200.00, NULL, NULL),
('OI00022', 'ORD00022', 'PRD002', 'Dumplings', 20, 10.00, 200.00, NULL, NULL),
('OI00023', 'ORD00023', 'PRD002', 'Dumplings', 20, 10.00, 200.00, NULL, NULL),
('OI00024', 'ORD00024', 'PRD002', 'Dumplings', 20, 10.00, 200.00, NULL, NULL),
('OI00025', 'ORD00025', 'PRD002', 'Dumplings', 15, 10.00, 150.00, NULL, NULL),
('OI00026', 'ORD00026', 'PRD003', 'Fried Wontons', 6, 10.00, 60.00, NULL, NULL),
('OI00027', 'ORD00027', 'PRD003', 'Fried Wontons', 24, 10.00, 240.00, NULL, NULL),
('OI00028', 'ORD00028', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00029', 'ORD00029', 'PRD002', 'Dumplings', 2, 10.00, 20.00, NULL, NULL),
('OI00030', 'ORD00030', 'PRD003', NULL, 1, 10.00, 10.00, NULL, NULL),
('OI00031', 'ORD00031', 'PRD006', NULL, 1, 11.00, 11.00, NULL, NULL),
('OI00032', 'ORD00032', 'PRD003', NULL, 1, 10.00, 10.00, NULL, NULL),
('OI00033', 'ORD00033', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, NULL),
('OI00034', 'ORD00034', 'PRD003', 'Fried Wontons', 1, 11.00, 11.00, NULL, NULL),
('OI00035', 'ORD00035', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, NULL),
('OI00036', 'ORD00036', 'PRD006', 'Hot & Sour Soup', 1, 13.00, 13.00, NULL, NULL),
('OI00037', 'ORD00037', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, NULL),
('OI00038', 'ORD00038', 'PRD006', 'Hot & Sour Soup', 2, 11.00, 22.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00039', 'ORD00039', 'PRD001', 'Spring Rolls', 1, 15.00, 15.00, NULL, '{\"removes\":{\"no_sauce\":true,\"no_chili\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":true}}'),
('OI00040', 'ORD00039', 'PRD001', 'Spring Rolls', 1, 12.00, 12.00, NULL, '{\"removes\":{\"no_sauce\":false,\"no_chili\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00041', 'ORD00040', 'PRD006', 'Hot & Sour Soup', 1, 13.00, 13.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":true,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":true,\"add_meat\":false}}'),
('OI00042', 'ORD00041', 'PRD001', NULL, 1, 12.00, 12.00, NULL, NULL),
('OI00043', 'ORD00042', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00044', 'ORD00043', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00045', 'ORD00044', 'PRD052', 'Red Bean Soup', 1, 14.00, 14.00, NULL, '{\"serve_time\":\"after_meal\"}'),
('OI00046', 'ORD00045', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00047', 'ORD00046', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00048', 'ORD00047', 'PRD006', 'Hot & Sour Soup', 1, 13.00, 13.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":true},\"addons\":{\"add_tofu\":true,\"add_meat\":false}}'),
('OI00049', 'ORD00048', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00050', 'ORD00049', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00051', 'ORD00050', 'PRD003', 'Fried Wontons', 3, 10.00, 30.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00052', 'ORD00051', 'PRD006', 'Hot & Sour Soup', 1, 11.00, 11.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00053', 'ORD00052', 'PRD006', 'Hot & Sour Soup', 2, 11.00, 22.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00054', 'ORD00053', 'PRD003', 'Fried Wontons', 2, 10.00, 20.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00055', 'ORD00054', 'PRD003', 'Fried Wontons', 2, 10.00, 20.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00056', 'ORD00055', 'PRD003', 'Fried Wontons', 2, 10.00, 20.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00057', 'ORD00056', 'PRD006', 'Hot & Sour Soup', 2, 11.00, 22.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_chili\":false,\"no_vinegar\":false,\"no_egg\":false},\"addons\":{\"add_tofu\":false,\"add_meat\":false}}'),
('OI00058', 'ORD00057', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false,\"no_ginger\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00059', 'ORD00058', 'PRD050', 'Mango Pudding', 1, 15.00, 15.00, NULL, '{\"serve_time\":\"before_meal\"}'),
('OI00060', 'ORD00059', 'PRD001', 'Spring Rolls', 1, 12.00, 12.00, NULL, '{\"removes\":{\"no_sauce\":false,\"no_chili\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00061', 'ORD00060', 'PRD050', 'Mango Pudding', 1, 15.00, 15.00, NULL, '{\"serve_time\":\"after_meal\"}'),
('OI00062', 'ORD00061', 'PRD052', 'Red Bean Soup', 1, 14.00, 14.00, NULL, '{\"serve_time\":\"after_meal\"}'),
('OI00063', 'ORD00062', 'PRD050', 'Mango Pudding', 1, 15.00, 15.00, NULL, '{\"serve_time\":\"after_meal\"}'),
('OI00064', 'ORD00063', 'PRD052', 'Red Bean Soup', 1, 14.00, 14.00, NULL, '{\"serve_time\":\"after_meal\"}'),
('OI00065', 'ORD00064', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00066', 'ORD00065', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false,\"no_ginger\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00067', 'ORD00066', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00068', 'ORD00067', 'PRD043', 'Butter Prawns', 1, 27.00, 27.00, NULL, '{\"removes\":{\"no_curry_leaf\":false,\"no_chili\":true,\"no_egg\":false},\"addons\":{\"add_prawn\":true,\"add_rice\":false}}'),
('OI00069', 'ORD00068', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00070', 'ORD00069', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00071', 'ORD00070', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00072', 'ORD00071', 'PRD003', 'Fried Wontons', 1, 11.00, 11.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":true,\"add_extra\":false}}'),
('OI00073', 'ORD00072', 'PRD003', 'Fried Wontons', 1, 11.00, 11.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":true,\"add_extra\":false}}'),
('OI00074', 'ORD00073', 'PRD002', 'Dumplings', 48, 10.00, 480.00, NULL, '{\"removes\":{\"no_sauce\":true,\"no_ginger\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00075', 'ORD00073', 'PRD002', 'Dumplings', 1, 15.00, 15.00, NULL, '{\"removes\":{\"no_sauce\":false,\"no_ginger\":false},\"addons\":{\"add_sauce\":true,\"add_extra\":true}}'),
('OI00076', 'ORD00073', 'PRD002', 'Dumplings', 1, 11.00, 11.00, NULL, '{\"removes\":{\"no_sauce\":true,\"no_ginger\":false},\"addons\":{\"add_sauce\":true,\"add_extra\":false}}'),
('OI00077', 'ORD00074', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00078', 'ORD00075', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00079', 'ORD00076', 'PRD003', 'Fried Wontons', 2, 10.00, 20.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00080', 'ORD00077', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00081', 'ORD00078', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00082', 'ORD00079', 'PRD002', 'Dumplings', 1, 10.00, 10.00, NULL, '{\"spice_level\":\"original\",\"removes\":{\"no_onion\":false,\"no_garlic\":false},\"addons\":{\"add_sauce\":false,\"add_onion\":false}}'),
('OI00083', 'ORD00080', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}'),
('OI00084', 'ORD00081', 'PRD003', 'Fried Wontons', 1, 10.00, 10.00, NULL, '{\"removes\":{\"no_sauce\":false},\"addons\":{\"add_sauce\":false,\"add_extra\":false}}');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `paymentid` varchar(10) NOT NULL,
  `orderid` varchar(20) NOT NULL,
  `user_id` varchar(10) DEFAULT NULL,
  `method` enum('cash','card','ewallet','online_banking') DEFAULT NULL,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `amount` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `special_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`paymentid`, `orderid`, `user_id`, `method`, `status`, `amount`, `created_at`, `special_note`) VALUES
('PAY00001', 'ORD00001', 'UR001', 'cash', 'paid', 10.60, '2026-03-31 13:04:43', ''),
('PAY00002', 'ORD00002', 'UR001', 'card', 'paid', 10.60, '2026-03-31 13:05:14', 'Card: ****2342'),
('PAY00003', 'ORD00003', 'UR001', 'online_banking', 'paid', 10.60, '2026-03-31 13:05:37', 'Bank: maybank'),
('PAY00004', 'ORD00004', 'UR001', 'ewallet', 'paid', 11.66, '2026-03-31 13:06:09', 'E-Wallet: TNG'),
('PAY00005', 'ORD00005', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 13:51:37', 'E-Wallet: TNG'),
('PAY00006', 'ORD00006', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 13:52:30', 'E-Wallet: TNG'),
('PAY00007', 'ORD00007', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 13:58:42', 'E-Wallet: TNG'),
('PAY00008', 'ORD00008', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 14:07:36', 'E-Wallet: TNG'),
('PAY00009', 'ORD00009', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 14:08:00', 'E-Wallet: TNG'),
('PAY00010', 'ORD00010', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 14:10:49', 'E-Wallet: TNG'),
('PAY00011', 'ORD00011', 'UR001', 'ewallet', 'paid', 11.66, '2026-03-31 14:11:20', 'E-Wallet: TNG'),
('PAY00012', 'ORD00012', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 14:13:40', 'E-Wallet: TNG'),
('PAY00013', 'ORD00013', 'UR001', 'ewallet', 'paid', 23.32, '2026-03-31 14:16:25', 'E-Wallet: TNG'),
('PAY00014', 'ORD00014', 'UR001', 'card', 'paid', 10.60, '2026-03-31 14:20:11', 'Card: ****3434'),
('PAY00015', 'ORD00015', 'UR001', 'online_banking', 'paid', 11.66, '2026-03-31 14:20:54', 'Bank: maybank'),
('PAY00016', 'ORD00017', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 16:37:35', 'E-Wallet: TNG'),
('PAY00017', 'ORD00018', 'UR001', 'cash', 'paid', 10.60, '2026-03-31 20:33:47', ''),
('PAY00018', 'ORD00019', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 21:15:09', 'E-Wallet: TNG'),
('PAY00019', 'ORD00020', 'UR001', 'ewallet', 'paid', 10.60, '2026-03-31 21:15:42', 'E-Wallet: TNG'),
('PAY00020', 'ORD00021', 'UR001', 'cash', 'paid', 212.00, '2026-04-01 00:31:06', ''),
('PAY00021', 'ORD00022', 'UR001', 'cash', 'paid', 212.00, '2026-04-01 00:31:26', ''),
('PAY00022', 'ORD00023', 'UR001', 'cash', 'paid', 212.00, '2026-04-01 00:31:52', ''),
('PAY00023', 'ORD00024', 'UR001', 'cash', 'paid', 212.00, '2026-04-01 00:32:09', ''),
('PAY00024', 'ORD00025', 'UR001', 'cash', 'paid', 159.00, '2026-04-01 00:32:36', ''),
('PAY00025', 'ORD00026', 'UR001', 'cash', 'paid', 31.80, '2026-04-01 00:42:18', 'Voucher: FT0D00FE67 (RM 30.00 off)'),
('PAY00026', 'ORD00027', 'UR001', 'card', 'paid', 254.40, '2026-04-03 09:51:40', 'Card: ****2323'),
('PAY00027', 'ORD00028', 'UR001', 'ewallet', 'paid', 10.60, '2026-04-04 13:03:35', NULL),
('PAY00028', 'ORD00029', 'UR001', 'cash', 'paid', 21.20, '2026-04-05 20:31:43', NULL),
('PAY00029', 'ORD00030', 'UR001', 'card', '', 10.60, '2026-04-05 21:41:25', NULL),
('PAY00030', 'ORD00031', 'UR001', 'card', 'pending', 11.66, '2026-04-05 22:25:06', NULL),
('PAY00031', 'ORD00032', 'UR001', 'card', 'pending', 10.60, '2026-04-06 12:39:03', NULL),
('PAY00032', 'ORD00033', 'UR001', 'ewallet', 'paid', 10.60, '2026-04-06 22:43:51', NULL),
('PAY00033', 'ORD00034', 'UR001', 'cash', 'paid', 11.66, '2026-04-07 13:07:29', NULL),
('PAY00034', 'ORD00035', 'UR001', 'cash', 'paid', 11.66, '2026-04-07 13:22:10', NULL),
('PAY00035', 'ORD00036', 'UR001', 'cash', 'paid', 13.78, '2026-04-07 13:22:27', NULL),
('PAY00036', 'ORD00038', 'UR001', 'cash', 'paid', 23.32, '2026-04-07 15:30:18', NULL),
('PAY00037', 'ORD00039', 'UR001', 'online_banking', 'paid', 28.62, '2026-04-07 15:34:58', NULL),
('PAY00038', 'ORD00040', 'UR001', 'ewallet', 'paid', 13.78, '2026-04-07 15:41:43', NULL),
('PAY00039', 'ORD00041', 'UR001', 'card', 'pending', 12.72, '2026-04-07 15:49:01', NULL),
('PAY00040', 'ORD00042', 'UR001', 'card', 'pending', 11.66, '2026-04-07 16:00:38', NULL),
('PAY00041', 'ORD00043', 'UR001', 'ewallet', 'paid', 11.66, '2026-04-07 16:20:26', NULL),
('PAY00042', 'ORD00044', 'UR001', 'ewallet', 'paid', 14.84, '2026-04-07 16:32:05', NULL),
('PAY00043', 'ORD00045', 'UR001', 'cash', 'pending', 11.66, '2026-04-07 23:38:50', NULL),
('PAY00044', 'ORD00046', 'UR001', 'online_banking', 'paid', 11.66, '2026-04-07 23:40:02', NULL),
('PAY00045', 'ORD00047', 'UR001', 'online_banking', 'paid', 13.78, '2026-04-07 23:41:26', NULL),
('PAY00046', 'ORD00048', 'UR001', 'ewallet', 'paid', 11.66, '2026-04-07 23:44:19', NULL),
('PAY00047', 'ORD00049', 'UR001', 'online_banking', 'paid', 11.66, '2026-04-07 23:55:22', NULL),
('PAY00048', 'ORD00050', 'UR001', 'cash', 'paid', 31.80, '2026-04-07 23:57:23', NULL),
('PAY00049', 'ORD00051', 'UR001', 'cash', 'paid', 11.66, '2026-04-08 11:24:20', NULL),
('PAY00050', 'ORD00052', 'UR001', 'online_banking', 'paid', 23.32, '2026-04-08 11:24:38', NULL),
('PAY00051', 'ORD00053', 'UR001', 'card', '', 15.90, '2026-04-08 11:25:25', NULL),
('PAY00052', 'ORD00054', 'UR001', 'card', '', 15.90, '2026-04-08 11:25:45', NULL),
('PAY00053', 'ORD00055', 'UR001', 'card', 'pending', 21.20, '2026-04-08 11:26:19', NULL),
('PAY00054', 'ORD00056', 'UR001', 'online_banking', 'paid', 18.02, '2026-04-08 11:27:53', NULL),
('PAY00055', 'ORD00057', 'UR001', 'card', 'paid', 5.30, '2026-04-08 11:34:38', NULL),
('PAY00056', 'ORD00058', 'UR001', 'card', 'pending', 10.60, '2026-04-08 11:52:20', NULL),
('PAY00057', 'ORD00059', 'UR001', 'ewallet', 'paid', 7.42, '2026-04-08 12:06:17', NULL),
('PAY00058', 'ORD00060', 'UR001', 'online_banking', 'paid', 10.60, '2026-04-08 12:08:09', NULL),
('PAY00059', 'ORD00061', 'UR001', 'card', 'paid', 9.54, '2026-04-08 12:09:09', NULL),
('PAY00060', 'ORD00062', 'UR001', 'cash', 'paid', 10.60, '2026-04-08 12:10:16', NULL),
('PAY00061', 'ORD00063', 'UR001', 'card', 'paid', 14.84, '2026-04-08 17:22:21', NULL),
('PAY00062', 'ORD00064', 'UR001', 'card', 'paid', 5.30, '2026-04-08 17:54:18', NULL),
('PAY00063', 'ORD00065', 'UR001', 'online_banking', 'paid', 10.60, '2026-04-09 18:36:15', NULL),
('PAY00064', 'ORD00066', 'UR001', 'cash', 'paid', 5.30, '2026-04-09 18:39:09', NULL),
('PAY00065', 'ORD00067', 'UR001', 'cash', 'pending', 28.62, '2026-04-09 18:41:45', NULL),
('PAY00066', 'ORD00068', 'UR001', 'card', 'paid', 10.60, '2026-04-09 18:46:43', NULL),
('PAY00067', 'ORD00069', 'UR001', 'cash', 'paid', 10.60, '2026-04-10 14:23:41', NULL),
('PAY00068', 'ORD00070', 'UR001', 'ewallet', 'paid', 10.60, '2026-04-10 19:25:54', NULL),
('PAY00069', 'ORD00072', 'UR001', 'ewallet', 'paid', 11.66, '2026-04-10 21:16:54', NULL),
('PAY00070', 'ORD00073', 'UR001', 'cash', 'paid', 536.36, '2026-04-11 02:09:41', NULL),
('PAY00071', 'ORD00074', 'UR001', 'online_banking', 'paid', 0.00, '2026-04-12 04:00:05', NULL),
('PAY00072', 'ORD00075', 'UR001', 'online_banking', 'paid', 10.60, '2026-04-12 04:02:33', NULL),
('PAY00073', 'ORD00076', 'UR001', 'cash', 'pending', 16.20, '2026-04-12 11:37:41', NULL),
('PAY00074', 'ORD00077', 'UR001', 'online_banking', 'paid', 10.60, '2026-04-12 11:38:30', NULL),
('PAY00075', 'ORD00078', 'UR001', 'online_banking', 'paid', 5.60, '2026-04-12 11:39:05', NULL),
('PAY00076', 'ORD00079', 'UR001', 'ewallet', 'paid', 5.60, '2026-04-12 11:47:08', NULL),
('PAY00077', 'ORD00080', 'UR001', 'ewallet', 'paid', 10.60, '2026-04-12 11:52:15', NULL),
('PAY00078', 'ORD00081', 'UR001', 'ewallet', 'paid', 5.60, '2026-04-12 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `productid` varchar(10) NOT NULL,
  `categoryid` varchar(10) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `stock` int(11) NOT NULL DEFAULT 100,
  `custom_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_options`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`productid`, `categoryid`, `name`, `price`, `image`, `description`, `is_available`, `stock`, `custom_options`) VALUES
('PRD001', 'CAT001', 'Spring Rolls', 12.00, 'PRD001_1774279842.jpg', '', 1, 998, NULL),
('PRD002', 'CAT001', 'Dumplings', 10.00, 'PRD002_1774280274.jpg', '', 1, 48, '{\"spice\":true,\"beverage\":false,\"dessert\":false,\"remove\":[{\"key\":\"no_onion\",\"label\":\"🧅 No Onion\"},{\"key\":\"no_garlic\",\"label\":\"🧄 No Garlic\"}],\"addons\":[{\"key\":\"add_sauce\",\"label\":\"🥫 Extra Sauce\",\"price\":2},{\"key\":\"add_onion\",\"label\":\"🧅 Extra Onion\",\"price\":1}]}'),
('PRD003', 'CAT001', 'Fried Wontons', 10.00, 'PRD003_1774280297.jpg', '', 1, 50, NULL),
('PRD004', 'CAT001', 'Prawn Crackers', 15.00, 'PRD004_1774280316.jpg', '', 1, 100, NULL),
('PRD005', 'CAT001', 'Garlic Bread', 8.00, 'PRD005_1774280334.jpg', '', 1, 100, NULL),
('PRD006', 'CAT002', 'Hot & Sour Soup', 11.00, 'PRD006_1774280428.jpg', '', 1, 86, '{\"spice\":true,\"beverage\":false,\"dessert\":false,\"remove\":[{\"key\":\"no_onion\",\"label\":\"🧅 No Onion\"},{\"key\":\"no_chili\",\"label\":\"🌶 No Chili\"},{\"key\":\"no_sauce\",\"label\":\"🥫 No Sauce\"},{\"key\":\"no_coriander\",\"label\":\"🌿 No Coriander\"}],\"addons\":[{\"key\":\"add_rice\",\"label\":\"🍚 Add Rice\",\"price\":2},{\"key\":\"add_meat\",\"label\":\"🥩 Extra Meat\",\"price\":5},{\"key\":\"add_tofu\",\"label\":\"🟨 Extra Tofu\",\"price\":2}]}'),
('PRD007', 'CAT002', 'Sweer Corn Chicken Soup', 10.00, 'PRD007_1774280506.jpg', '', 1, 100, NULL),
('PRD008', 'CAT002', 'Tom Yum Seafood', 12.00, 'PRD008_1774280526.jpg', '', 1, 100, NULL),
('PRD009', 'CAT002', 'Wonton Soup', 12.00, 'PRD009_1774280546.jpg', '', 1, 100, NULL),
('PRD010', 'CAT002', 'Mushroom Soup', 10.00, 'PRD010_1774280561.jpg', '', 1, 100, NULL),
('PRD011', 'CAT003', 'Kung Pao Chicken', 25.00, 'PRD011_1774280595.jpg', '', 1, 100, NULL),
('PRD012', 'CAT003', 'Sweet & Sour Chicken', 26.00, 'PRD012_1774280641.jpg', '', 1, 100, NULL),
('PRD013', 'CAT003', 'Lemon Chicken', 22.00, 'PRD013_1774280662.jpg', '', 1, 100, NULL),
('PRD014', 'CAT003', 'Black Pepper Chicken', 25.00, 'PRD014_1774280755.jpg', '', 1, 100, NULL),
('PRD015', 'CAT003', 'Crispy Fried Chicken', 26.00, 'PRD015_1774280788.jpg', '', 1, 100, NULL),
('PRD016', 'CAT004', 'Beef with Broccoli', 22.00, 'PRD016_1774280839.jpg', '', 1, 100, NULL),
('PRD017', 'CAT004', 'Black Pepper Beef', 25.00, 'PRD017_1774280859.jpg', '', 1, 100, NULL),
('PRD018', 'CAT004', 'Mongolian Beef', 28.00, 'PRD018_1774280876.jpg', '', 1, 100, NULL),
('PRD019', 'CAT004', 'Lamb with Ginger & Spring Onion', 27.00, 'PRD019_1774280913.jpg', '', 1, 100, NULL),
('PRD020', 'CAT004', 'Grilled Beef Steak', 27.00, 'PRD020_1774280936.jpg', '', 1, 100, NULL),
('PRD021', 'CAT005', 'Chili Prawns', 26.00, 'PRD021_1774280976.jpg', '', 1, 100, NULL),
('PRD022', 'CAT005', 'Salted Egg Shrimp', 25.00, 'PRD022_1774280991.jpg', '', 1, 100, NULL),
('PRD023', 'CAT005', 'Steamed Fish with Soy Sauce', 26.00, 'PRD023_1774281023.jpg', '', 1, 100, NULL),
('PRD024', 'CAT005', 'Sweet & Sour Fish', 25.00, 'PRD024_1774281043.jpg', '', 1, 100, NULL),
('PRD025', 'CAT005', 'Grilled Salmon', 25.00, 'PRD025_1774281061.jpg', '', 1, 100, NULL),
('PRD026', 'CAT006', 'Yangzhou Fried Rice', 20.00, 'PRD026_1774281096.jpg', '', 1, 100, NULL),
('PRD027', 'CAT006', 'Seafood Fried Rice', 22.00, 'PRD027_1774281113.jpg', '', 1, 100, NULL),
('PRD028', 'CAT006', 'Chicken Fried Rice', 23.00, 'PRD028_1774281129.jpg', '', 1, 100, NULL),
('PRD029', 'CAT006', 'Claypot Rice', 25.00, 'PRD029_1774281311.jpg', '', 1, 100, NULL),
('PRD030', 'CAT006', 'Chicken Biryani', 23.00, 'PRD030_1774281348.jpg', '', 1, 100, NULL),
('PRD031', 'CAT007', 'Chow Mein', 19.00, 'PRD031_1774281427.jpg', '', 1, 100, NULL),
('PRD032', 'CAT007', 'Singapore Fried Noodles', 21.00, 'PRD032_1774281443.jpg', '', 1, 100, NULL),
('PRD033', 'CAT007', 'Beef Noodles', 23.00, 'PRD033_1774281461.jpg', '', 1, 100, NULL),
('PRD034', 'CAT007', 'Seafood Noodles', 22.00, 'PRD034_1774281477.jpg', '', 1, 100, NULL),
('PRD035', 'CAT007', 'Spaghetti Bolognese', 21.00, 'PRD035_1774280823.jpg', '', 1, 100, NULL),
('PRD036', 'CAT008', 'Stir-Fried Bok Choy', 16.00, 'PRD036_1774281330.jpg', '', 1, 100, NULL),
('PRD037', 'CAT008', 'Garlic Chinese Cabbage', 17.00, 'PRD037_1774281405.jpg', '', 1, 100, NULL),
('PRD038', 'CAT008', 'Mixed Vegetables', 19.00, 'PRD038_1774281492.jpg', '', 1, 100, NULL),
('PRD039', 'CAT008', 'Mapo Tofu', 15.00, 'PRD039_1774281508.jpg', '', 1, 100, NULL),
('PRD040', 'CAT009', 'Peking Duck', 21.00, 'PRD040_1774281525.jpg', '', 1, 100, NULL),
('PRD041', 'CAT009', 'Honey BBQ Spare Ribs', 23.00, 'PRD041_1774281541.jpg', '', 1, 100, NULL),
('PRD042', 'CAT009', 'Szechuan Spicy Chicken', 23.00, 'PRD042_1774281559.jpg', '', 1, 100, NULL),
('PRD043', 'CAT009', 'Butter Prawns', 21.00, 'PRD043_1774280352.jpg', '', 1, 99, NULL),
('PRD044', 'CAT009', 'Chicken Chop with Black Pepper Sauce', 23.00, 'PRD044_1774281795.jpg', '', 1, 100, NULL),
('PRD045', 'CAT010', 'Margherita Pizza', 18.00, 'PRD045_1774281601.jpg', '', 1, 100, NULL),
('PRD046', 'CAT010', 'Pepperoni Pizza', 18.00, 'PRD046_1774281617.jpg', '', 1, 100, NULL),
('PRD047', 'CAT010', 'Beef Burger', 20.00, 'PRD047_1774281633.jpg', '', 1, 100, NULL),
('PRD048', 'CAT010', 'Chicken Burger', 19.00, 'PRD048_1774281659.jpg', '', 1, 100, NULL),
('PRD049', 'CAT010', 'Fish & Chips', 19.00, 'PRD049_1774281675.jpg', '', 1, 100, NULL),
('PRD050', 'CAT011', 'Mango Pudding', 15.00, 'PRD050_1774281692.jpg', '', 1, 98, NULL),
('PRD051', 'CAT011', 'Sesame Balls', 17.00, 'PRD051_1774281711.jpg', '', 1, 100, NULL),
('PRD052', 'CAT011', 'Red Bean Soup', 14.00, 'PRD052_1774281385.jpg', '', 1, 99, NULL),
('PRD053', 'CAT011', 'Fried Ice Cream', 14.00, 'PRD053_1774281292.jpg', '', 1, 100, NULL),
('PRD054', 'CAT011', 'Chocolate Cake', 15.00, 'PRD054_1774281263.jpg', '', 1, 100, NULL),
('PRD055', 'CAT011', 'Cheesecake', 15.00, 'PRD055_1774281009.jpg', '', 1, 100, NULL),
('PRD056', 'CAT012', 'Chinese Tea (Jasmine / Oolong)', 7.00, 'PRD056_1774280806.jpg', '', 1, 100, NULL),
('PRD057', 'CAT012', 'Green Tea', 7.00, 'PRD057_1774280772.jpg', '', 1, 100, NULL),
('PRD058', 'CAT012', 'Coffee', 9.00, 'PRD058_1774280612.jpg', '', 1, 100, NULL),
('PRD059', 'CAT012', 'Latte', 11.00, 'PRD059_1774280580.jpg', '', 1, 100, NULL),
('PRD060', 'CAT012', 'Iced Lemon Tea', 14.00, 'PRD060_1774280488.jpg', '', 1, 100, NULL),
('PRD061', 'CAT012', 'Fresh Fruit Juice', 14.00, 'PRD061_1774280472.jpg', '', 1, 100, NULL),
('PRD062', 'CAT012', 'Soft Drinks', 16.00, 'PRD062_1774280447.jpg', '', 1, 100, NULL),
('PRD063', 'CAT012', 'Milkshakes', 16.00, 'PRD063_1774280371.jpg', '', 1, 100, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userid` varchar(10) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(12) NOT NULL,
  `address` text NOT NULL,
  `gender` enum('male','female','','') NOT NULL,
  `password` varchar(255) NOT NULL,
  `position` enum('admin','customer') NOT NULL DEFAULT 'customer',
  `avatar` varchar(255) DEFAULT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `profile_picture` varchar(255) DEFAULT NULL,
  `membership_points` int(11) NOT NULL DEFAULT 0,
  `total_points_earned` int(11) NOT NULL DEFAULT 0,
  `ewallet_pin_hash` varchar(255) DEFAULT NULL,
  `ewallet_pin_set_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userid`, `username`, `email`, `phone`, `address`, `gender`, `password`, `position`, `avatar`, `is_blocked`, `status`, `profile_picture`, `membership_points`, `total_points_earned`, `ewallet_pin_hash`, `ewallet_pin_set_at`) VALUES
('UR001', 'xiaoyan1', 'yan931515@gmail.com', '0184679907', 'Balik Pulau, George Town, Penang, 11000, Malaysia', 'female', '$2y$10$i8RG9kplfPdHVETbR.2loOeWOh1CoSSuf0T0UYC6f2Udt2Y5n5HSe', 'customer', NULL, 0, 'active', 'profile_UR001_1774578275.jpg', 3820, 12820, '$2y$10$vmeRyozz9WibEO82QdzMtuanzcdh80r4.IW3QL4Tfm24U9pcl3sMy', '2026-04-10 21:16:38'),
('UR002', 'xiaoyan2', 'yanzilaile5@gmail.com', '0123456789', 'Tanjung Bungah, Mukim 18, North-East, George Town, Penang, 11200, Malaysia', 'male', '$2y$10$A7DtvSsQBNaM1th7pux/Xu1zvbSKJgT2DH4cayRQXSSsBygPUqmUm', 'customer', NULL, 0, 'active', NULL, 0, 0, NULL, NULL),
('UR003', 'xiaoyan3', 'xiaoyanyan574@gmail.com', '0134251234', 'Tanjung Bungah, Mukim 18, North-East, George Town, Penang, 11200, Malaysia', 'male', '$2y$10$nMV0nuh01cQCt7S2q69T1.AYK4Arcya498OD9u5nhbDrrN4960zxi', 'admin', NULL, 0, 'active', NULL, 0, 0, NULL, NULL),
('UR004', 'Wu Tong', 'twu37712@gmail.com', '0125610177', 'Lintang Lembah Permai 1, 2 Permai, Mukim 18, North-East, George Town, Penang, 11200, Malaysia', 'male', '$2y$10$jkaEMJR/w1i86dodOM9J0uVFARr8pZlWpy6tASdP.XPpbgEOmiVxO', 'admin', NULL, 0, 'active', 'profile_UR004_1774260726.jpg', 0, 0, NULL, NULL),
('UR006', 'yuyang3', 'xiaolilianglaile@gmail.com', '0198765432', 'Ng Kee Cake Shop, 61, Cintra Street, Little India, Central George Town, North-East, George Town, Penang, 10100, Malaysia', 'male', '$2y$10$Bdvv8XqLtFXk/mGRh1wTMudC2zA6KljPVtOuVMY9alOqng6H7wQ2G', 'admin', NULL, 0, 'active', NULL, 0, 0, NULL, NULL),
('UR007', 'yuyang', 'yylim7012@gmail.com', '0125673214', '38, Kampong Malabar, Little India, Central George Town, North-East, George Town, Penang, 10200, Malaysia', 'male', '$2y$10$Tk0jpITD5H6O/zL40N1yd.D2PVrZfJ3Mxgbz0ior/uya.J9oXsSwe', 'customer', NULL, 0, 'active', NULL, 0, 0, NULL, NULL),
('UR014', 'xiaoyan4', 'tongw2699@gmail.com', '0184662196', 'Burger Society, Leith Street, Little India, Central George Town, North-East, George Town, Penang, 10200, Malaysia', 'male', '$2y$10$8J7HaL2mF9YRZUmbg5009Ope8.yeKhr.muCGsp8B7zmRcSPL2aagm', 'customer', NULL, 0, 'active', NULL, 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_vouchers`
--

CREATE TABLE `user_vouchers` (
  `voucherid` int(11) NOT NULL,
  `userid` varchar(10) NOT NULL,
  `code` varchar(20) NOT NULL,
  `discount_rm` decimal(8,2) NOT NULL,
  `points_spent` int(11) NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  `used_order_id` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_vouchers`
--

INSERT INTO `user_vouchers` (`voucherid`, `userid`, `code`, `discount_rm`, `points_spent`, `is_used`, `created_at`, `used_at`, `used_order_id`) VALUES
(1, 'UR001', 'FT0D00FE67', 30.00, 3000, 1, '2026-04-01 00:40:52', '2026-04-01 00:42:18', 'ORD00026'),
(2, 'UR001', 'FT78EF98FB', 5.00, 500, 1, '2026-04-08 11:25:08', '2026-04-08 11:27:53', 'ORD00056'),
(3, 'UR001', 'FT37EEE5C0', 5.00, 500, 1, '2026-04-08 11:28:45', '2026-04-08 12:08:09', 'ORD00060'),
(4, 'UR001', 'FT215A953A', 5.00, 500, 1, '2026-04-08 11:51:46', '2026-04-08 12:05:28', NULL),
(5, 'UR001', 'FT730BDC00', 5.00, 500, 1, '2026-04-08 12:08:29', '2026-04-08 12:10:16', 'ORD00062'),
(6, 'UR001', 'FT096953A5', 5.00, 500, 1, '2026-04-08 17:54:05', '2026-04-08 17:54:35', 'ORD00064'),
(7, 'UR001', 'FT74ED3D77', 10.00, 1000, 1, '2026-04-09 16:22:48', '2026-04-12 04:00:05', 'ORD00074'),
(8, 'UR001', 'FT3F2E7C18', 5.00, 500, 1, '2026-04-09 16:42:31', '2026-04-09 18:39:09', 'ORD00066'),
(9, 'UR001', 'FT5D7F914D', 5.00, 500, 1, '2026-04-09 22:25:36', '2026-04-12 11:39:05', 'ORD00078'),
(10, 'UR001', 'FT5E8426D6', 5.00, 500, 1, '2026-04-12 03:58:40', '2026-04-12 11:37:41', 'ORD00076'),
(11, 'UR001', 'FTAA257F46', 5.00, 500, 1, '2026-04-12 11:43:59', '2026-04-12 11:52:36', NULL),
(12, 'UR001', 'FT5507827E', 5.00, 500, 1, '2026-04-12 11:44:05', '2026-04-12 11:46:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `voucher_tiers`
--

CREATE TABLE `voucher_tiers` (
  `tierid` int(11) NOT NULL,
  `points_cost` int(11) NOT NULL,
  `discount_rm` decimal(8,2) NOT NULL,
  `label` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voucher_tiers`
--

INSERT INTO `voucher_tiers` (`tierid`, `points_cost`, `discount_rm`, `label`, `is_active`) VALUES
(1, 500, 5.00, 'RM5 Off Voucher', 1),
(2, 1000, 10.00, 'RM10 Off Voucher', 1),
(3, 1500, 15.00, 'RM15 Off Voucher', 1),
(4, 2000, 20.00, 'RM20 Off Voucher', 1),
(5, 2500, 25.00, 'RM25 Off Voucher', 1),
(6, 3000, 30.00, 'RM30 Off Voucher', 1);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `userid` varchar(10) NOT NULL,
  `productid` varchar(10) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `userid`, `productid`, `created_at`) VALUES
(11, 'UR001', 'PRD010', '2026-03-29 00:50:38'),
(15, 'UR001', 'PRD003', '2026-04-06 12:49:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`categoryid`);

--
-- Indexes for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD KEY `idx_chatconv_customer` (`customer_id`),
  ADD KEY `idx_chatconv_admin` (`admin_id`),
  ADD KEY `idx_chatconv_order` (`related_orderid`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_chatmsg_conversation` (`conversation_id`),
  ADD KEY `idx_chatmsg_sender` (`sender_id`),
  ADD KEY `idx_chatmsg_receiver` (`receiver_id`);

--
-- Indexes for table `chat_message_reads`
--
ALTER TABLE `chat_message_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_msg_admin` (`message_id`,`admin_id`),
  ADD KEY `idx_cmr_admin` (`admin_id`),
  ADD KEY `idx_cmr_message` (`message_id`);

--
-- Indexes for table `id_counters`
--
ALTER TABLE `id_counters`
  ADD PRIMARY KEY (`counter_name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`orderid`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `fk_orders_table` (`tableid`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`orderitemid`),
  ADD KEY `idx_order_items_orderid` (`orderid`),
  ADD KEY `idx_order_items_productid` (`productid`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`paymentid`),
  ADD KEY `idx_payments_orderid` (`orderid`),
  ADD KEY `idx_payments_user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`productid`),
  ADD KEY `idx_products_categoryid` (`categoryid`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userid`);

--
-- Indexes for table `user_vouchers`
--
ALTER TABLE `user_vouchers`
  ADD PRIMARY KEY (`voucherid`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_user_vouchers_used_order_id` (`used_order_id`);

--
-- Indexes for table `voucher_tiers`
--
ALTER TABLE `voucher_tiers`
  ADD PRIMARY KEY (`tierid`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_product` (`userid`,`productid`),
  ADD KEY `idx_wishlist_userid` (`userid`),
  ADD KEY `idx_wishlist_productid` (`productid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_message_reads`
--
ALTER TABLE `chat_message_reads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `user_vouchers`
--
ALTER TABLE `user_vouchers`
  MODIFY `voucherid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `voucher_tiers`
--
ALTER TABLE `voucher_tiers`
  MODIFY `tierid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD CONSTRAINT `fk_chat_conversations_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`userid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_conversations_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_conversations_order` FOREIGN KEY (`related_orderid`) REFERENCES `orders` (`orderid`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`conversation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`userid`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `chat_message_reads`
--
ALTER TABLE `chat_message_reads`
  ADD CONSTRAINT `fk_chat_message_reads_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_message_reads_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`message_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`userid`) REFERENCES `users` (`userid`) ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`orderid`) REFERENCES `orders` (`orderid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`productid`) REFERENCES `products` (`productid`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`orderid`) REFERENCES `orders` (`orderid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`userid`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`categoryid`) REFERENCES `categories` (`categoryid`) ON UPDATE CASCADE;

--
-- Constraints for table `user_vouchers`
--
ALTER TABLE `user_vouchers`
  ADD CONSTRAINT `fk_user_vouchers_used_order` FOREIGN KEY (`used_order_id`) REFERENCES `orders` (`orderid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_vouchers_user` FOREIGN KEY (`userid`) REFERENCES `users` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`productid`) REFERENCES `products` (`productid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`userid`) REFERENCES `users` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
