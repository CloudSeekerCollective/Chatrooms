--
-- Chatrooms Database Structure for Satellite v0.7.20 - v0.7.23
-- 	* Have you ever wished to instantly create the Chatrooms database structure? 
-- 	* Don't want to follow a long list of stupid instructions?
-- 	* NOW YOU CAAAAAAAN! Just run this script to do just that.
--
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `chrms_universe`
--
CREATE DATABASE IF NOT EXISTS `chrms_universe` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `chrms_universe`;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `username` varchar(40) NOT NULL,
  `password` text NOT NULL,
  `email` varchar(100) NOT NULL,
  `id` text NOT NULL,
  `picture` text NOT NULL,
  `profilestatus` varchar(128) NOT NULL,
  `creationdate` bigint(20) NOT NULL,
  `status` text NOT NULL,
  `authentication` text NOT NULL,
  `badges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' CHECK (json_valid(`badges`)),
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' CHECK (json_valid(`roles`)),
  `latest2fa` varchar(6) NOT NULL,
  `2fa_admission` tinyint(1) NOT NULL DEFAULT 0,
  `mod_note` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `channels`
--

CREATE TABLE `channels` (
  `name` varchar(64) NOT NULL,
  `id` int(11) NOT NULL,
  `allowed_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' CHECK (json_valid(`allowed_roles`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `author` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `channel` varchar(100) NOT NULL,
  `date` bigint(20) NOT NULL,
  `number` bigint(4) NOT NULL,
  `attachment1` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `vchannels`
--

CREATE TABLE `vchannels` (
  `name` varchar(50) NOT NULL,
  `id` int(11) NOT NULL,
  `allowed_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`allowed_roles`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
