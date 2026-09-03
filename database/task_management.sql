-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2026 at 09:29 PM
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
-- Database: `task_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `sync_checkpoints`
--

CREATE TABLE `sync_checkpoints` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'github',
  `repository` varchar(255) NOT NULL,
  `direction` enum('github_to_app','app_to_github') NOT NULL,
  `cursors` varchar(500) DEFAULT NULL,
  `page` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `last_provider_updated_at` datetime DEFAULT NULL,
  `status` enum('running','completed','failed') NOT NULL DEFAULT 'running',
  `last_error` text DEFAULT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sync_checkpoints`
--

INSERT INTO `sync_checkpoints` (`id`, `provider`, `repository`, `direction`, `cursors`, `page`, `last_provider_updated_at`, `status`, `last_error`, `updated_at`) VALUES
(1, 'github', 'nilamholkar/task-sync-demo', 'github_to_app', NULL, 2, '2026-09-02 12:31:38', 'completed', NULL, '2026-09-03 06:05:42');

-- --------------------------------------------------------

--
-- Table structure for table `sync_conflicts`
--

CREATE TABLE `sync_conflicts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'github',
  `local_version` int(10) UNSIGNED NOT NULL,
  `local_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`local_snapshot`)),
  `provider_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`provider_snapshot`)),
  `conflict_type` varchar(100) NOT NULL DEFAULT 'concurrent_update',
  `status` enum('open','resolved','ignored') NOT NULL DEFAULT 'open',
  `resolution` enum('keep_local','keep_provider','manual_merge') DEFAULT NULL,
  `resolved_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resolved_snapshot`)),
  `created_at` datetime NOT NULL,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sync_conflicts`
--

INSERT INTO `sync_conflicts` (`id`, `task_id`, `provider`, `local_version`, `local_snapshot`, `provider_snapshot`, `conflict_type`, `status`, `resolution`, `resolved_snapshot`, `created_at`, `resolved_at`) VALUES
(1, 4, 'github', 3, '{\"id\":4,\"title\":\"Local Version - Important Change\",\"description\":\"Testing GitHub webhook synchronization\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":null,\"version\":3,\"local_updated_at\":\"2026-09-03 07:22:01\"}', '{\"id\":5333160810,\"number\":4,\"title\":\"GitHub Version - Different Change\",\"description\":\"This was changed directly in GitHub.\",\"state\":\"open\",\"html_url\":\"https:\\/\\/github.com\\/nilamholkar\\/task-sync-demo\\/issues\\/4\",\"updated_at\":\"2026-09-03T07:05:00Z\"}', 'concurrent_update', 'resolved', 'keep_provider', '{\"title\":\"GitHub Version - Different Change\",\"description\":\"This was changed directly in GitHub.\",\"status\":\"pending\",\"provider_snapshot\":{\"id\":5333160810,\"number\":4,\"title\":\"GitHub Version - Different Change\",\"description\":\"This was changed directly in GitHub.\",\"state\":\"open\",\"html_url\":\"https:\\/\\/github.com\\/nilamholkar\\/task-sync-demo\\/issues\\/4\",\"updated_at\":\"2026-09-03T07:05:00Z\"},\"previous_local_snapshot\":{\"id\":4,\"title\":\"Local Version - Important Change\",\"description\":\"Testing GitHub webhook synchronization\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":null,\"version\":3,\"local_updated_at\":\"2026-09-03 07:22:01\"}}', '2026-09-03 07:28:05', '2026-09-03 08:30:27'),
(2, 6, 'github', 4, '{\"id\": 6, \"title\": \"GitHub Version - LOCAL Conflict Test\", \"description\": \"testing\", \"status\": \"pending\", \"priority\": \"high\", \"due_date\": \"2026-09-25\", \"version\": 4}', '{\"id\": \"5340030945\", \"number\": 6, \"title\": \"GitHub Conflict Version\", \"description\": \"Changed directly in GitHub\", \"state\": \"open\"}', 'concurrent_update', 'resolved', 'keep_local', '{\"title\":\"GitHub Version - LOCAL Conflict Test\",\"description\":\"testing\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":\"2026-09-25\"}', '2026-09-04 00:23:05', '2026-09-03 18:56:39');

-- --------------------------------------------------------

--
-- Table structure for table `sync_logs`
--

CREATE TABLE `sync_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED DEFAULT NULL,
  `direction` enum('app_to_github','github_to_app') NOT NULL,
  `operation` varchar(50) NOT NULL,
  `status` enum('success','failed','retry','conflict','ignored') NOT NULL,
  `message` text DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `duration_ms` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sync_logs`
--

INSERT INTO `sync_logs` (`id`, `task_id`, `direction`, `operation`, `status`, `message`, `request_data`, `response_data`, `duration_ms`, `created_at`) VALUES
(1, NULL, 'github_to_app', 'initial_sync_page', 'success', 'Processed GitHub page 1 containing 3 records.', '{\"page\":1,\"per_page\":100}', '{\"count\":3}', 1857, '2026-09-03 06:05:42'),
(2, 4, 'app_to_github', 'create', 'success', 'GitHub issue created successfully.', '{\"title\":\"Test local task\",\"description\":\"Created from local application\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":null}', '{\"id\":5333160810,\"number\":4,\"url\":\"https:\\/\\/github.com\\/nilamholkar\\/task-sync-demo\\/issues\\/4\"}', 1692, '2026-09-03 06:33:20'),
(3, 4, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"Local Version - Important Change\"}', '{\"number\":4,\"updated_at\":\"2026-09-03T10:40:32Z\"}', 1431, '2026-09-03 10:40:33'),
(4, 4, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"Local Version - Important Change\"}', '{\"number\":4,\"updated_at\":\"2026-09-03T10:40:32Z\"}', 1245, '2026-09-03 10:40:34'),
(5, 4, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"GitHub Version - Different Change 1\",\"body\":\"This was changed directly in GitHub.\",\"state\":\"open\"}', '{\"number\":4,\"updated_at\":\"2026-09-03T10:40:35Z\"}', 1203, '2026-09-03 10:40:35'),
(6, 4, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"GitHub Version - Different Change 1\",\"body\":\"This was changed directly in GitHub.\",\"state\":\"closed\"}', '{\"number\":4,\"updated_at\":\"2026-09-03T10:40:36Z\"}', 1371, '2026-09-03 10:40:36'),
(7, 5, 'app_to_github', 'create', 'success', 'GitHub issue created successfully.', '{\"title\":\"Database issue\",\"description\":\"For Migration database file not working\",\"status\":\"pending\",\"priority\":\"low\",\"due_date\":\"2026-09-04\"}', '{\"id\":5335511427,\"number\":5,\"url\":\"https:\\/\\/github.com\\/nilamholkar\\/task-sync-demo\\/issues\\/5\"}', 971, '2026-09-03 10:40:37'),
(8, 5, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"Database issue\",\"body\":\"For Migration database file not working\",\"state\":\"open\"}', '{\"number\":5,\"updated_at\":\"2026-09-03T10:40:37Z\"}', 1354, '2026-09-03 18:02:53'),
(9, 5, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"Database issue\",\"body\":\"For Migration database file not working 1\",\"state\":\"open\"}', '{\"number\":5,\"updated_at\":\"2026-09-03T18:16:56Z\"}', 1452, '2026-09-03 18:16:56'),
(10, 5, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"Database issue 1\",\"body\":\"For Migration database file not working 1\",\"state\":\"open\"}', '{\"number\":5,\"updated_at\":\"2026-09-03T18:16:57Z\"}', 1203, '2026-09-03 18:16:57'),
(11, 5, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"Database issue 2\",\"body\":\"For Migration database file not working 1\",\"state\":\"open\"}', '{\"number\":5,\"updated_at\":\"2026-09-03T18:16:58Z\"}', 1067, '2026-09-03 18:16:58'),
(12, 6, 'app_to_github', 'create', 'success', 'GitHub issue created successfully.', '{\"title\":\"Conflict Test Task - LOCAL CHANGE\",\"description\":\"testing\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":\"2026-09-25\"}', '{\"id\":5340030945,\"number\":6,\"url\":\"https:\\/\\/github.com\\/nilamholkar\\/task-sync-demo\\/issues\\/6\"}', 1980, '2026-09-03 18:23:00'),
(13, 6, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"Conflict Test Task - Github CHANGE\",\"body\":\"testing\",\"state\":\"open\"}', '{\"number\":6,\"updated_at\":\"2026-09-03T18:23:03Z\"}', 2648, '2026-09-03 18:23:03'),
(14, 6, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"Conflict Test Task - Local CHANGE\",\"body\":\"testing\",\"state\":\"open\"}', '{\"number\":6,\"updated_at\":\"2026-09-03T18:23:06Z\"}', 2444, '2026-09-03 18:23:05'),
(15, 6, 'app_to_github', 'update', 'success', 'GitHub issue updated successfully.', '{\"title\":\"GitHub Version - LOCAL Conflict Test\",\"body\":\"testing\",\"state\":\"open\"}', '{\"number\":6,\"updated_at\":\"2026-09-03T18:56:53Z\"}', 1833, '2026-09-03 18:56:53'),
(16, 1, 'app_to_github', 'delete', 'success', 'Local task deleted and GitHub issue closed.', '{\"issue_number\":\"3\"}', '{\"state\":\"closed\"}', 2237, '2026-09-03 19:04:32');

-- --------------------------------------------------------

--
-- Table structure for table `sync_queue`
--

CREATE TABLE `sync_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'github',
  `operation` enum('create','update','delete') NOT NULL,
  `status` enum('pending','processing','succeeded','failed','quarantined') NOT NULL DEFAULT 'pending',
  `idempotency_key` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `next_attempt_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `locked_by` varchar(100) DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sync_queue`
--

INSERT INTO `sync_queue` (`id`, `task_id`, `provider`, `operation`, `status`, `idempotency_key`, `payload`, `attempts`, `max_attempts`, `next_attempt_at`, `locked_at`, `locked_by`, `last_error`, `created_at`, `updated_at`) VALUES
(1, 4, 'github', 'create', 'succeeded', 'task-4-v1-create', '{\"title\":\"Test local task\",\"description\":\"Created from local application\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":null}', 0, 5, '2026-09-03 06:28:05', NULL, NULL, NULL, '2026-09-03 06:28:05', '2026-09-03 06:33:20'),
(2, 4, 'github', 'update', 'succeeded', 'task-4-v2-update', '{\"version\":2,\"changes\":{\"title\":\"Local Version - Important Change\",\"version\":2,\"local_updated_at\":\"2026-09-03 07:06:29\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 07:06:29', NULL, NULL, NULL, '2026-09-03 07:06:29', '2026-09-03 10:40:33'),
(3, 4, 'github', 'update', 'succeeded', 'task-4-v3-update', '{\"version\":3,\"changes\":{\"title\":\"Local Version - Important Change\",\"version\":3,\"local_updated_at\":\"2026-09-03 07:22:01\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 07:22:01', NULL, NULL, NULL, '2026-09-03 07:22:01', '2026-09-03 10:40:34'),
(4, 4, 'github', 'update', 'succeeded', 'task-4-v5-update', '{\"version\":5,\"changes\":{\"title\":\"GitHub Version - Different Change 1\",\"description\":\"This was changed directly in GitHub.\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":null,\"version\":5,\"local_updated_at\":\"2026-09-03 10:34:24\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 10:34:24', NULL, NULL, NULL, '2026-09-03 10:34:24', '2026-09-03 10:40:35'),
(5, 4, 'github', 'update', 'succeeded', 'task-4-v6-update', '{\"version\":6,\"changes\":{\"title\":\"GitHub Version - Different Change 1\",\"description\":\"This was changed directly in GitHub.\",\"status\":\"completed\",\"priority\":\"high\",\"due_date\":null,\"version\":6,\"local_updated_at\":\"2026-09-03 10:35:50\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 10:35:50', NULL, NULL, NULL, '2026-09-03 10:35:50', '2026-09-03 10:40:36'),
(6, 5, 'github', 'create', 'succeeded', 'task-5-v1-create', '{\"title\":\"Database issue\",\"description\":\"For Migration database file not working\",\"status\":\"pending\",\"priority\":\"low\",\"due_date\":\"2026-09-04\"}', 0, 5, '2026-09-03 10:37:10', NULL, NULL, NULL, '2026-09-03 10:37:10', '2026-09-03 10:40:37'),
(7, 5, 'github', 'update', 'succeeded', 'task-5-v2-update', '{\"version\":2,\"changes\":{\"title\":\"Database issue\",\"description\":\"For Migration database file not working\",\"status\":\"in_progress\",\"priority\":\"low\",\"due_date\":\"2026-09-04\",\"version\":2,\"local_updated_at\":\"2026-09-03 17:58:29\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 17:58:29', NULL, NULL, NULL, '2026-09-03 17:58:29', '2026-09-03 18:02:53'),
(8, 5, 'github', 'update', 'succeeded', 'task-5-v3-update', '{\"version\":3,\"changes\":{\"title\":\"Database issue\",\"description\":\"For Migration database file not working 1\",\"status\":\"in_progress\",\"priority\":\"low\",\"due_date\":\"2026-09-04\",\"version\":3,\"local_updated_at\":\"2026-09-03 18:13:35\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 18:13:35', NULL, NULL, NULL, '2026-09-03 18:13:35', '2026-09-03 18:16:56'),
(9, 5, 'github', 'update', 'succeeded', 'task-5-v4-update', '{\"version\":4,\"changes\":{\"title\":\"Database issue 1\",\"description\":\"For Migration database file not working 1\",\"status\":\"in_progress\",\"priority\":\"low\",\"due_date\":\"2026-09-04\",\"version\":4,\"local_updated_at\":\"2026-09-03 18:13:42\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 18:13:42', NULL, NULL, NULL, '2026-09-03 18:13:42', '2026-09-03 18:16:57'),
(10, 5, 'github', 'update', 'succeeded', 'task-5-v5-update', '{\"version\":5,\"changes\":{\"title\":\"Database issue 2\",\"description\":\"For Migration database file not working 1\",\"status\":\"in_progress\",\"priority\":\"low\",\"due_date\":\"2026-09-04\",\"version\":5,\"local_updated_at\":\"2026-09-03 18:16:32\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 18:16:32', NULL, NULL, NULL, '2026-09-03 18:16:32', '2026-09-03 18:16:58'),
(11, 6, 'github', 'create', 'succeeded', 'task-6-v1-create', '{\"title\":\"Conflict Test Task - LOCAL CHANGE\",\"description\":\"testing\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":\"2026-09-25\"}', 0, 5, '2026-09-03 18:20:10', NULL, NULL, NULL, '2026-09-03 18:20:10', '2026-09-03 18:23:00'),
(12, 6, 'github', 'update', 'succeeded', 'task-6-v2-update', '{\"version\":2,\"changes\":{\"title\":\"Conflict Test Task - Github CHANGE\",\"description\":\"testing\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":\"2026-09-25\",\"version\":2,\"local_updated_at\":\"2026-09-03 18:20:35\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 18:20:35', NULL, NULL, NULL, '2026-09-03 18:20:35', '2026-09-03 18:23:03'),
(13, 6, 'github', 'update', 'succeeded', 'task-6-v3-update', '{\"version\":3,\"changes\":{\"title\":\"Conflict Test Task - Local CHANGE\",\"description\":\"testing\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":\"2026-09-25\",\"version\":3,\"local_updated_at\":\"2026-09-03 18:22:52\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 18:22:52', NULL, NULL, NULL, '2026-09-03 18:22:52', '2026-09-03 18:23:05'),
(14, 6, 'github', 'update', 'succeeded', 'task-6-v4-update', '{\"version\":4,\"changes\":{\"title\":\"GitHub Version - LOCAL Conflict Test\",\"description\":\"testing\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":\"2026-09-25\",\"version\":4,\"local_updated_at\":\"2026-09-03 18:28:10\",\"sync_status\":\"pending\"}}', 0, 5, '2026-09-03 18:28:10', NULL, NULL, NULL, '2026-09-03 18:28:10', '2026-09-03 18:56:53'),
(15, 6, 'github', 'update', 'succeeded', 'task-6-v5-conflict-local', '{\"title\":\"GitHub Version - LOCAL Conflict Test\",\"description\":\"testing\",\"status\":\"pending\",\"priority\":\"high\",\"due_date\":\"2026-09-25\"}', 0, 5, NULL, NULL, NULL, NULL, '2026-09-03 18:56:39', '2026-09-03 18:56:53'),
(16, 1, 'github', 'delete', 'succeeded', 'task-1-v2-delete', '{\"version\":2}', 0, 5, '2026-09-03 19:03:58', NULL, NULL, NULL, '2026-09-03 19:03:58', '2026-09-03 19:04:32');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `provider_task_id` varchar(100) DEFAULT NULL,
  `provider_issue_number` int(11) DEFAULT NULL,
  `provider_url` varchar(500) DEFAULT NULL,
  `sync_status` enum('synced','pending','conflict','error') NOT NULL DEFAULT 'pending',
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `local_updated_at` datetime NOT NULL,
  `provider_updated_at` datetime DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `status`, `priority`, `due_date`, `provider`, `provider_task_id`, `provider_issue_number`, `provider_url`, `sync_status`, `version`, `local_updated_at`, `provider_updated_at`, `last_synced_at`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'Fix user validation', 'Fix user validation errors.', 'pending', 'medium', NULL, 'github', '5323924879', 3, 'https://github.com/nilamholkar/task-sync-demo/issues/3', 'synced', 2, '2026-09-03 19:03:58', '2026-09-03 19:04:32', '2026-09-03 19:04:32', '2026-09-03 19:03:58', '2026-09-03 06:05:42', '2026-09-03 19:04:32'),
(2, 'Add dashboard', 'Create the task management dashboard.', 'pending', 'medium', NULL, 'github', '5323919643', 2, 'https://github.com/nilamholkar/task-sync-demo/issues/2', 'synced', 1, '2026-09-03 06:05:42', '2026-09-02 12:32:00', '2026-09-03 06:05:42', NULL, '2026-09-03 06:05:42', '2026-09-03 06:05:42'),
(3, 'Fix login page', 'Fix validation issue on the login page.', 'pending', 'medium', NULL, 'github', '5323915716', 1, 'https://github.com/nilamholkar/task-sync-demo/issues/1', 'synced', 1, '2026-09-03 06:05:42', '2026-09-02 12:31:38', '2026-09-03 06:05:42', NULL, '2026-09-03 06:05:42', '2026-09-03 06:05:42'),
(4, 'GitHub Version - Different Change 1', 'This was changed directly in GitHub.', 'completed', 'high', NULL, 'github', '5333160810', 4, 'https://github.com/nilamholkar/task-sync-demo/issues/4', 'synced', 6, '2026-09-03 10:35:50', '2026-09-03 10:40:36', '2026-09-03 10:40:36', NULL, '2026-09-03 06:28:05', '2026-09-03 10:40:36'),
(5, 'Database issue 2', 'For Migration database file not working 1', 'in_progress', 'low', '2026-09-04', 'github', '5335511427', 5, 'https://github.com/nilamholkar/task-sync-demo/issues/5', 'synced', 5, '2026-09-03 18:16:32', '2026-09-03 18:16:58', '2026-09-03 18:16:58', NULL, '2026-09-03 10:37:10', '2026-09-03 18:16:58'),
(6, 'GitHub Version - LOCAL Conflict Test', 'testing', 'pending', 'high', '2026-09-25', 'github', '5340030945', 6, 'https://github.com/nilamholkar/task-sync-demo/issues/6', 'synced', 5, '2026-09-03 18:56:39', '2026-09-03 18:56:53', '2026-09-03 18:56:53', NULL, '2026-09-03 18:20:10', '2026-09-03 18:56:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'admin@example.com', '$2y$10$EDqWhesQmfRSUUYiDLW8FeICU.xqzpmnUTLwAXAmHbCWF2uYo5Viy', 'admin', 1, '2026-09-02 13:06:41', '2026-09-02 13:06:41'),
(2, 'Test User 1', 'user@example.com', '$2y$10$bPT0CmbPGO0AokNjhnxS5e7SZEEuuMPWr0wk9aIPhMCeeae33MFFu', 'user', 1, '2026-09-02 13:06:41', '2026-09-02 10:18:59');

-- --------------------------------------------------------

--
-- Table structure for table `webhook_events`
--

CREATE TABLE `webhook_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'github',
  `event_id` varchar(255) NOT NULL,
  `event_name` varchar(100) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `delivery_status` enum('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `error_message` text DEFAULT NULL,
  `received_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `webhook_events`
--

INSERT INTO `webhook_events` (`id`, `provider`, `event_id`, `event_name`, `action`, `delivery_status`, `payload`, `error_message`, `received_at`, `processed_at`) VALUES
(1, 'github', 'test-delivery-001', 'issues', 'edited', 'processed', '{\r\n  \"action\": \"edited\",\r\n  \"issue\": {\r\n    \"id\": 123456789,\r\n    \"number\": 4,\r\n    \"title\": \"Webhook Test Task\",\r\n    \"body\": \"Testing GitHub webhook synchronization\",\r\n    \"state\": \"open\",\r\n    \"html_url\": \"https://github.com/nilamholkar/task-sync-demo/issues/4\",\r\n    \"updated_at\": \"2026-09-03T06:30:00Z\"\r\n  }\r\n}', NULL, '2026-09-03 06:49:27', '2026-09-03 06:49:27'),
(2, 'github', 'test-conflict-003', 'issues', 'edited', 'failed', '{\n    \"action\": \"edited\",\n    \"issue\": {\n        \"id\": 5333160810,\n        \"number\": 4,\n        \"title\": \"GitHub Version - Different Change\",\n        \"body\": \"This was changed directly in GitHub.\",\n        \"state\": \"open\",\n        \"html_url\": \"https://github.com/nilamholkar/task-sync-demo/issues/4\",\n        \"updated_at\": \"2026-09-03T07:05:00Z\"\n    }\n}', 'Class \"App\\Services\\SyncConflictModel\" not found', '2026-09-03 07:24:10', '2026-09-03 07:24:10'),
(3, 'github', 'test-conflict-004', 'issues', 'edited', 'processed', '{\r\n    \"action\": \"edited\",\r\n    \"issue\": {\r\n        \"id\": 5333160810,\r\n        \"number\": 4,\r\n        \"title\": \"GitHub Version - Different Change\",\r\n        \"body\": \"This was changed directly in GitHub.\",\r\n        \"state\": \"open\",\r\n        \"html_url\": \"https://github.com/nilamholkar/task-sync-demo/issues/4\",\r\n        \"updated_at\": \"2026-09-03T07:05:00Z\"\r\n    }\r\n}', NULL, '2026-09-03 07:28:05', '2026-09-03 07:28:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `sync_checkpoints`
--
ALTER TABLE `sync_checkpoints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checkpoint` (`provider`,`repository`,`direction`);

--
-- Indexes for table `sync_conflicts`
--
ALTER TABLE `sync_conflicts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conflict_task` (`task_id`),
  ADD KEY `idx_conflict_status` (`status`);

--
-- Indexes for table `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_task` (`task_id`),
  ADD KEY `idx_log_status` (`status`),
  ADD KEY `idx_log_created` (`created_at`);

--
-- Indexes for table `sync_queue`
--
ALTER TABLE `sync_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sync_idempotency` (`idempotency_key`),
  ADD KEY `idx_queue_status` (`status`,`next_attempt_at`),
  ADD KEY `idx_queue_task` (`task_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_provider_task` (`provider`,`provider_task_id`),
  ADD KEY `idx_sync_status` (`sync_status`),
  ADD KEY `idx_provider_issue` (`provider`,`provider_issue_number`),
  ADD KEY `idx_provider_updated` (`provider_updated_at`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- Indexes for table `webhook_events`
--
ALTER TABLE `webhook_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_provider_event` (`provider`,`event_id`),
  ADD KEY `idx_event_status` (`delivery_status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `sync_checkpoints`
--
ALTER TABLE `sync_checkpoints`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sync_conflicts`
--
ALTER TABLE `sync_conflicts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sync_logs`
--
ALTER TABLE `sync_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sync_queue`
--
ALTER TABLE `sync_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `webhook_events`
--
ALTER TABLE `webhook_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sync_conflicts`
--
ALTER TABLE `sync_conflicts`
  ADD CONSTRAINT `fk_sync_conflict_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD CONSTRAINT `fk_sync_log_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sync_queue`
--
ALTER TABLE `sync_queue`
  ADD CONSTRAINT `fk_sync_queue_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
