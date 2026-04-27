-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2026 at 12:13 PM
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
-- Database: `musanada_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `opportunity_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','accepted','rejected','completed') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `user_id`, `opportunity_id`, `status`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 8, 1, 'accepted', NULL, '2026-04-25 18:33:06', '2026-04-25 18:33:39'),
(2, 8, 2, 'pending', NULL, '2026-04-25 18:34:25', '2026-04-25 18:34:25');

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attachable_type` varchar(255) NOT NULL,
  `attachable_id` bigint(20) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(255) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `opportunity_id` bigint(20) UNSIGNED NOT NULL,
  `certificate_code` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hour_logs`
--

CREATE TABLE `hour_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `opportunity_id` bigint(20) UNSIGNED NOT NULL,
  `hours` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `date_logged` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2026_04_03_161924_create_musanada_database_schema', 1),
(2, '2026_04_03_162708_create_personal_access_tokens_table', 1),
(3, '2026_04_04_230827_create_notifications_table', 1),
(4, '2026_04_09_170920_update_status_enum_in_opportunities_table', 1),
(5, '2026_04_09_221628_add_start_date_to_opportunities_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
('be1787b4-d106-470f-b9c7-5bae3804bf7d', 'App\\Notifications\\ApplicationStatusChanged', 'App\\Models\\User', 8, '{\"application_id\":1,\"title\":\"\\u062a\\u062d\\u062f\\u064a\\u062b \\u0641\\u064a \\u0637\\u0644\\u0628\\u0643 \\u0644\\u0641\\u0631\\u0635\\u0629: \\u0625\\u062f\\u062e\\u0627\\u0644 \\u0628\\u064a\\u0627\\u0646\\u0627\\u062a \\u0627\\u0644\\u0645\\u0633\\u062a\\u0641\\u064a\\u062f\\u064a\\u0646\",\"message\":\"\\u062a\\u0647\\u0627\\u0646\\u064a\\u0646\\u0627! \\u062a\\u0645 \\u0642\\u0628\\u0648\\u0644 \\u0627\\u0646\\u0636\\u0645\\u0627\\u0645\\u0643 \\u0644\\u0644\\u0641\\u0631\\u0635\\u0629\\u060c \\u064a\\u0645\\u0643\\u0646\\u0643 \\u0627\\u0644\\u0628\\u062f\\u0621 \\u0627\\u0644\\u0622\\u0646. \\ud83c\\udf89\",\"opportunity_title\":\"\\u0625\\u062f\\u062e\\u0627\\u0644 \\u0628\\u064a\\u0627\\u0646\\u0627\\u062a \\u0627\\u0644\\u0645\\u0633\\u062a\\u0641\\u064a\\u062f\\u064a\\u0646\",\"status\":\"accepted\",\"type\":\"success\",\"sender_name\":\"\\u0627\\u0644\\u0647\\u0644\\u0627\\u0644 \\u0627\\u0644\\u0627\\u062d\\u0645\\u0631 \\u0627\\u0644\\u0633\\u0639\\u0648\\u062f\\u064a\",\"action_url\":\"\\/applications\\/1\"}', NULL, '2026-04-25 18:33:39', '2026-04-25 18:33:39'),
('c7441be0-3c3f-414f-9648-674c54e864fa', 'App\\Notifications\\AdminSystemNotification', 'App\\Models\\User', 7, '{\"title\":\"\\u062a\\u0645 \\u0642\\u0628\\u0648\\u0644 \\u0627\\u0644\\u062a\\u0633\\u062c\\u064a\\u0644\",\"message\":\"\\u0646\\u0647\\u0646\\u0626\\u0643\\u0645\\u060c \\u062a\\u0645 \\u0642\\u0628\\u0648\\u0644 \\u0637\\u0644\\u0628 \\u0627\\u0646\\u0636\\u0645\\u0627\\u0645 \\u0645\\u0624\\u0633\\u0633\\u062a\\u0643\\u0645 (\\u0627\\u0644\\u0647\\u0644\\u0627\\u0644 \\u0627\\u0644\\u0627\\u062d\\u0645\\u0631 \\u0627\\u0644\\u0633\\u0639\\u0648\\u062f\\u064a) \\u0628\\u0646\\u062c\\u0627\\u062d.\",\"type\":\"success\",\"sender\":\"\\u0625\\u062f\\u0627\\u0631\\u0629 \\u0645\\u0633\\u0627\\u0646\\u062f\\u0629\",\"created_at\":\"2026-04-25 20:32:47\"}', NULL, '2026-04-25 17:32:47', '2026-04-25 17:32:47');

-- --------------------------------------------------------

--
-- Table structure for table `opportunities`
--

CREATE TABLE `opportunities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `duration` varchar(255) NOT NULL,
  `requirements` text DEFAULT NULL,
  `required_volunteers` int(11) NOT NULL DEFAULT 1,
  `deadline` date NOT NULL,
  `status` enum('open','closed','completed','hidden') DEFAULT 'open',
  `type` enum('voluntary','training','course') NOT NULL DEFAULT 'voluntary',
  `gender` enum('male','female','both') NOT NULL DEFAULT 'both',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `opportunities`
--

INSERT INTO `opportunities` (`id`, `user_id`, `title`, `description`, `cover_image`, `start_date`, `location`, `lat`, `lng`, `duration`, `requirements`, `required_volunteers`, `deadline`, `status`, `type`, `gender`, `created_at`, `updated_at`) VALUES
(1, 7, 'إدخال بيانات المستفيدين', 'هذه الفرصة تتيح لك الانخراط في تجربة حقيقية لخدمة المجتمع، نسعى لاستقطاب المتميزين للمشاركة في إدخال بيانات المستفيدين.', 'opportunities/qq6g6XhRBCbl5MKauEEdWrMMnGrUT0bnTnhlhYef.jpg', '2026-04-29', 'الرياض - حي العليا', 20.41845481, 47.46799565, '20 يوم', 'حاسوب', 3, '2026-05-08', 'open', 'training', 'both', '2026-04-25 17:50:41', '2026-04-25 17:50:41'),
(2, 7, 'كبارنا بركتنا', 'خلف كل تجعيدة على وجوههم قصة، وفي عيونهم شوق لحديثٍ دافئ. هم ليسوا مجرد أرقام في دار للعجزة، بل هم بركة الحياة وأصل الحكاية. التطوع هنا ليس مجرد وقت تقضيه، بل هو إحياء لروح، وجبر لقلب، ورسم ابتسامة قد تكون الأخيرة في يوم أحدهم. كن أنت العائلة التي يحتاجونها، وشاركنا بلمسة حنان تضيء أيامهم. تطوع.. لتزرع العطاء وتحصد المحبة.', 'opportunities/BWn5jkltAULDhblsPkASs9VDTqKg8xXvdIF3WXqv.jpg', '2026-04-27', 'الرياض', 24.59949424, 46.60075183, '10 ايام', NULL, 6, '2026-05-09', 'open', 'voluntary', 'both', '2026-04-25 18:31:09', '2026-04-25 18:31:09');

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_skill`
--

CREATE TABLE `opportunity_skill` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `opportunity_id` bigint(20) UNSIGNED NOT NULL,
  `skill_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `opportunity_skill`
--

INSERT INTO `opportunity_skill` (`id`, `opportunity_id`, `skill_id`) VALUES
(1, 1, 10),
(2, 1, 2),
(3, 1, 12),
(4, 1, 3),
(5, 2, 3),
(6, 2, 1),
(7, 2, 10);

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `org_name` varchar(255) NOT NULL,
  `org_type` varchar(255) NOT NULL,
  `contact_person` varchar(255) NOT NULL,
  `license_file` varchar(255) DEFAULT NULL,
  `digital_signature` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `user_id`, `org_name`, `org_type`, `contact_person`, `license_file`, `digital_signature`, `description`, `website`, `is_verified`, `created_at`, `updated_at`) VALUES
(1, 7, 'الهلال الاحمر السعودي', 'خيرية', 'احمد علي', 'licenses/lJC0rSPWzPABDPqupKECvNe6vS6vncVlyYzQNc8b.pdf', NULL, NULL, 'http://localhost:5173/register', 1, '2026-04-25 17:29:59', '2026-04-25 17:32:43');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'musanada_token', '9ea19c4f6cdb8120144f88eb00edcd9e503762845e8893615d463e25c1efea66', '[\"*\"]', '2026-04-25 16:58:45', NULL, '2026-04-25 16:58:32', '2026-04-25 16:58:45'),
(2, 'App\\Models\\User', 2, 'musanada_token', '747bb8c5f9660497754db19942a8e5dc6e3f317c56a9560d66e3c8f36a82ed33', '[\"*\"]', NULL, NULL, '2026-04-25 17:01:37', '2026-04-25 17:01:37'),
(3, 'App\\Models\\User', 7, 'musanada_token', '02952e943b66595a45e1bb13627acad437c87a7af2638e753feb3ea4b2a965af', '[\"*\"]', NULL, NULL, '2026-04-25 17:29:59', '2026-04-25 17:29:59'),
(4, 'App\\Models\\User', 1, 'musanada_token', '258dbd978fbc09e750983df5d2a3e3045f20f4a1ab9d5d08b819118fef4d993c', '[\"*\"]', '2026-04-25 17:50:58', NULL, '2026-04-25 17:31:42', '2026-04-25 17:50:58'),
(5, 'App\\Models\\User', 7, 'musanada_token', '0e4c0fe100e1b402934d853bba7f1449f6c646fcece5637300badbacb624bab5', '[\"*\"]', '2026-04-25 17:50:44', NULL, '2026-04-25 17:33:20', '2026-04-25 17:50:44'),
(6, 'App\\Models\\User', 8, 'musanada_token', 'f58765f3e5b31edf7605199968db7fd4ffd9246c0910c031e059818cdb69150f', '[\"*\"]', NULL, NULL, '2026-04-25 17:57:44', '2026-04-25 17:57:44'),
(7, 'App\\Models\\User', 8, 'musanada_token', '879c41e69e49e1a1c6bd5857be544324149a534a4c4c4511ba60672ac14a6376', '[\"*\"]', '2026-04-25 18:34:25', NULL, '2026-04-25 17:58:09', '2026-04-25 18:34:25'),
(8, 'App\\Models\\User', 7, 'musanada_token', '1d7c4a2c8863930fdb8a367733d78b76445400f6cd9318dad5324b660817b501', '[\"*\"]', '2026-04-25 18:33:45', NULL, '2026-04-25 18:16:15', '2026-04-25 18:33:45'),
(9, 'App\\Models\\User', 1, 'musanada_token', 'c16e6ea6720bc849ef8b153c1e2f1c52c063d9843df3711e9929bc1cd8d9e05d', '[\"*\"]', '2026-04-27 06:10:42', NULL, '2026-04-27 06:05:23', '2026-04-27 06:10:42'),
(10, 'App\\Models\\User', 7, 'musanada_token', '1510c5cde1e574c07586a46a418f4ddbc327e94ff2487a4c6109cc277a5c76ba', '[\"*\"]', '2026-04-27 06:12:28', NULL, '2026-04-27 06:11:50', '2026-04-27 06:12:28'),
(11, 'App\\Models\\User', 8, 'musanada_token', 'ff956911ab971288a4e277af3380f54d2abaaae9977e187d19a4a196a8aea8ce', '[\"*\"]', '2026-04-27 06:21:47', NULL, '2026-04-27 06:13:05', '2026-04-27 06:21:47'),
(12, 'App\\Models\\User', 8, 'musanada_token', 'a2bfa42ea499034f4b2c75a124583b3c9a32974fa41670a7fa5b282d3e288965', '[\"*\"]', '2026-04-27 06:23:00', NULL, '2026-04-27 06:22:18', '2026-04-27 06:23:00'),
(14, 'App\\Models\\User', 8, 'musanada_token', '0196da3e591bfef08eb8b94e90e24a8fcf9567483e415369e8475a1906e1d383', '[\"*\"]', '2026-04-27 07:12:13', NULL, '2026-04-27 06:43:12', '2026-04-27 07:12:13');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `opportunity_id` bigint(20) UNSIGNED NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'الاسعات الاولية', '2026-04-25 17:36:26', '2026-04-25 17:36:26'),
(2, 'الفصاحة', '2026-04-25 17:38:42', '2026-04-25 17:38:42'),
(3, 'اجتماعي', '2026-04-25 17:40:09', '2026-04-25 17:40:09'),
(4, 'تصميم تجربة المستخدم (UI/UX)', '2026-04-25 17:41:06', '2026-04-25 17:41:06'),
(5, 'قيادة الفرق', '2026-04-25 17:42:33', '2026-04-25 17:42:33'),
(6, 'التصميم المعماري', '2026-04-25 17:42:57', '2026-04-25 17:42:57'),
(7, 'كتابة المحتوى', '2026-04-25 17:43:11', '2026-04-25 17:43:11'),
(8, 'إدارة المشاريع', '2026-04-25 17:43:22', '2026-04-25 17:43:22'),
(9, 'الدعم الفني التقني', '2026-04-25 17:43:37', '2026-04-25 17:43:37'),
(10, 'مهارات التواصل', '2026-04-25 17:43:51', '2026-04-25 17:43:51'),
(11, 'التسويق الرقمي', '2026-04-25 17:44:08', '2026-04-25 17:44:08'),
(12, 'التحليل الإحصائي', '2026-04-25 17:44:16', '2026-04-25 17:44:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','organization','admin') NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `phone`, `profile_image`, `location`, `lat`, `lng`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'sarah', 'sarah@admin.com', '$2y$12$nvsW1Ca/w4dywIhdGEWEa.yGn.YXuEtBEklOkmiW7.x4OM2E4dUAe', 'admin', '0555265476', NULL, NULL, NULL, NULL, 1, NULL, NULL),
(2, 'ساره', 'sarah@gmail.com', '$2y$12$MRn0/6nRpWJDZ9tZTIVJFu0im4GBeQTucafI6iHRdeZeayH84Ishi', 'student', NULL, NULL, NULL, NULL, NULL, 1, '2026-04-25 17:01:37', '2026-04-25 17:01:37'),
(7, 'أحمد محمد علي', 'srh@org.com', '$2y$12$/.pC6wGfW2BCQZJZfuNlHuopDgFrTPE2WrGzP9Cww8rW4jixu5i.m', 'organization', '05504773', 'profiles/970J0I3e10MZoMMNVrb9kas45Rq7lAd53RsWPsFv.jpg', 'الرياض', NULL, NULL, 1, '2026-04-25 17:29:59', '2026-04-27 06:12:26'),
(8, 'لين امين', 'leen@gmail.com', '$2y$12$u7.gTIlP0hB5OE.BpihiVuiXmdf8Bw1UcQC.s6KstBWBzIfJ52wze', 'student', '500203763', 'profiles/OX5iXNBGnwktNoufiui0uTvooS924exQY7HY04CF.jpg', 'المجاردة', NULL, NULL, 1, '2026-04-25 17:57:44', '2026-04-27 06:22:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `university` varchar(255) DEFAULT NULL,
  `major` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `total_volunteer_hours` int(11) NOT NULL DEFAULT 0,
  `total_training_hours` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `university`, `major`, `bio`, `birth_date`, `gender`, `total_volunteer_hours`, `total_training_hours`, `created_at`, `updated_at`) VALUES
(1, 2, 'جامعة الملك خالد', 'هندسة', NULL, NULL, NULL, 0, 0, '2026-04-25 17:01:37', '2026-04-25 17:01:37'),
(2, 8, 'جامعة الملك خالد', 'طب', 'ربي ارحمني..!', '2011-04-07', 'female', 0, 0, '2026-04-25 17:57:44', '2026-04-25 18:32:45');

-- --------------------------------------------------------

--
-- Table structure for table `user_skill`
--

CREATE TABLE `user_skill` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `skill_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_skill`
--

INSERT INTO `user_skill` (`id`, `user_id`, `skill_id`) VALUES
(6, 8, 12),
(7, 8, 4),
(8, 8, 11),
(9, 8, 9),
(10, 8, 7);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applications_user_id_foreign` (`user_id`),
  ADD KEY `applications_opportunity_id_foreign` (`opportunity_id`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attachments_attachable_type_attachable_id_index` (`attachable_type`,`attachable_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificates_certificate_code_unique` (`certificate_code`),
  ADD KEY `certificates_user_id_foreign` (`user_id`),
  ADD KEY `certificates_opportunity_id_foreign` (`opportunity_id`);

--
-- Indexes for table `hour_logs`
--
ALTER TABLE `hour_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hour_logs_user_id_foreign` (`user_id`),
  ADD KEY `hour_logs_opportunity_id_foreign` (`opportunity_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `opportunities`
--
ALTER TABLE `opportunities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `opportunities_user_id_foreign` (`user_id`);

--
-- Indexes for table `opportunity_skill`
--
ALTER TABLE `opportunity_skill`
  ADD PRIMARY KEY (`id`),
  ADD KEY `opportunity_skill_opportunity_id_foreign` (`opportunity_id`),
  ADD KEY `opportunity_skill_skill_id_foreign` (`skill_id`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizations_user_id_foreign` (`user_id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reviews_user_id_opportunity_id_unique` (`user_id`,`opportunity_id`),
  ADD KEY `reviews_opportunity_id_foreign` (`opportunity_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `skills_name_unique` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_profiles_user_id_foreign` (`user_id`);

--
-- Indexes for table `user_skill`
--
ALTER TABLE `user_skill`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_skill_user_id_foreign` (`user_id`),
  ADD KEY `user_skill_skill_id_foreign` (`skill_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hour_logs`
--
ALTER TABLE `hour_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `opportunities`
--
ALTER TABLE `opportunities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `opportunity_skill`
--
ALTER TABLE `opportunity_skill`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_skill`
--
ALTER TABLE `user_skill`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hour_logs`
--
ALTER TABLE `hour_logs`
  ADD CONSTRAINT `hour_logs_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hour_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `opportunities`
--
ALTER TABLE `opportunities`
  ADD CONSTRAINT `opportunities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `opportunity_skill`
--
ALTER TABLE `opportunity_skill`
  ADD CONSTRAINT `opportunity_skill_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `opportunity_skill_skill_id_foreign` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `organizations`
--
ALTER TABLE `organizations`
  ADD CONSTRAINT `organizations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_skill`
--
ALTER TABLE `user_skill`
  ADD CONSTRAINT `user_skill_skill_id_foreign` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_skill_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
