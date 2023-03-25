-- phpMyAdmin SQL Dump
-- version 4.6.6deb4
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 21. Nov 2021 um 20:04
-- Server-Version: 10.3.31-MariaDB-0+deb10u1
-- PHP-Version: 7.3.33-1+0~20211119.91+debian10~1.gbp618351

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `fahrplan2020`
--
CREATE DATABASE IF NOT EXISTS `fahrplan2020` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
USE `fahrplan2020`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `agency`
--

CREATE TABLE `agency` (
  `dataset_id` int(11) NOT NULL,
  `agency_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `agency_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_timezone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `calendar`
--

CREATE TABLE `calendar` (
  `dataset_id` int(11) NOT NULL,
  `service_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `monday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `tuesday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `wednesday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `thursday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `friday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `saturday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `sunday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `calendar_dates`
--

CREATE TABLE `calendar_dates` (
  `dataset_id` int(11) NOT NULL,
  `service_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `date` date NOT NULL,
  `exception_type` enum('1','2') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '1 - Service has been added for the specified date.\r\n2 - Service has been removed for the specified date.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `datasets`
--

CREATE TABLE `datasets` (
  `dataset_id` int(11) NOT NULL,
  `dataset_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `import_time` datetime NOT NULL DEFAULT current_timestamp(),
  `reference_date` date DEFAULT NULL,
  `desc` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `license` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `import_state` VARCHAR(50) NOT NULL DEFAULT 'INIT' COLLATE 'utf8mb4_unicode_ci',
  `last_logfile` VARCHAR(250) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `routes`
--

CREATE TABLE `routes` (
  `dataset_id` int(11) NOT NULL,
  `route_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `agency_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `route_short_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_long_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_type` int(6) NOT NULL,
  `route_color` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FFFFFF',
  `route_text_color` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '000000',
  `route_desc` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `continuous_pickup` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping pickup.\r\n1 or empty - No continuous stopping pickup.\r\n2 - Must phone agency to arrange continuous stopping pickup.\r\n3 - Must coordinate with driver to arrange continuous stopping pickup. ',
  `continuous_drop_off` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping drop off.\r\n1 or empty - No continuous stopping drop off.\r\n2 - Must phone agency to arrange continuous stopping drop off.\r\n3 - Must coordinate with driver to arrange continuous stopping drop off. '
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `stops`
--

CREATE TABLE `stops` (
  `dataset_id` int(11) NOT NULL,
  `stop_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `stop_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stop_name` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stop_desc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stop_lat` float DEFAULT NULL,
  `stop_lon` float DEFAULT NULL,
  `location_type` enum('0','1','2','3','4') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '• 0 (or blank): Stop (or Platform). A location where passengers board or disembark from a transit vehicle. Is called a platform when defined within a parent_station.\r\n• 1: Station. A physical structure or area that contains one or more platform.\r\n• 2: Entrance/Exit. A location where passengers can enter or exit a station from the street. If an entrance/exit belongs to multiple stations, it can be linked by pathways to both, but the data provider must pick one of them as parent.\r\n• 3: Generic Node. A location within a station, not matching any other location_type, which can be used to link together pathways define in pathways.txt.\r\n• 4: Boarding Area. A specific location on a platform, where passengers can board and/or alight vehicles.',
  `parent_station` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_parent` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `stop_times`
--

CREATE TABLE `stop_times` (
  `dataset_id` int(11) NOT NULL,
  `trip_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `stop_sequence` int(6) NOT NULL,
  `stop_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `arrival_time` time DEFAULT NULL,
  `departure_time` time DEFAULT NULL,
  `stop_headsign` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_type` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '0 or empty - Regularly scheduled pickup.\r\n1 - No pickup available.\r\n2 - Must phone agency to arrange pickup.\r\n3 - Must coordinate with driver to arrange pickup.',
  `drop_off_type` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '0 or empty - Regularly scheduled drop off.\r\n1 - No drop off available.\r\n2 - Must phone agency to arrange drop off.\r\n3 - Must coordinate with driver to arrange drop off.',
  `continuous_pickup` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping pickup.\r\n1 or empty - No continuous stopping pickup.\r\n2 - Must phone agency to arrange continuous stopping pickup.\r\n3 - Must coordinate with driver to arrange continuous stopping pickup. ',
  `continuous_drop_off` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping drop off.\r\n1 or empty - No continuous stopping drop off.\r\n2 - Must phone agency to arrange continuous stopping drop off.\r\n3 - Must coordinate with driver to arrange continuous stopping drop off. ',
  `timepoint` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Times are considered approximate.\r\n1 or empty - Times are considered exact.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `trips`
--

CREATE TABLE `trips` (
  `dataset_id` int(11) NOT NULL DEFAULT 0,
  `trip_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `route_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `direction_id` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `service_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `trip_headsign` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trip_short_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `block_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_stop` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_stop` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `agency`
--
ALTER TABLE `agency`
  ADD PRIMARY KEY (`dataset_id`,`agency_id`) USING BTREE;

--
-- Indizes für die Tabelle `calendar`
--
ALTER TABLE `calendar`
  ADD PRIMARY KEY (`dataset_id`,`service_id`) USING BTREE,
  ADD KEY `start_date` (`dataset_id`,`start_date`,`end_date`) USING BTREE,
  ADD KEY `end_date` (`dataset_id`,`end_date`) USING BTREE;

--
-- Indizes für die Tabelle `calendar_dates`
--
ALTER TABLE `calendar_dates`
  ADD PRIMARY KEY (`dataset_id`,`service_id`,`date`) USING BTREE,
  ADD KEY `date` (`dataset_id`,`date`) USING BTREE;

--
-- Indizes für die Tabelle `datasets`
--
ALTER TABLE `datasets`
  ADD PRIMARY KEY (`dataset_id`);

--
-- Indizes für die Tabelle `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`dataset_id`,`route_id`) USING BTREE,
  ADD KEY `agency` (`dataset_id`,`agency_id`) USING BTREE,
  ADD KEY `route_type` (`dataset_id`,`route_type`) USING BTREE;

--
-- Indizes für die Tabelle `stops`
--
ALTER TABLE `stops`
  ADD PRIMARY KEY (`dataset_id`,`stop_id`) USING BTREE,
  ADD KEY `stop_name` (`dataset_id`,`stop_name`) USING BTREE,
  ADD KEY `stop_code` (`dataset_id`,`stop_code`) USING BTREE,
  ADD KEY `location_type` (`dataset_id`,`location_type`) USING BTREE,
  ADD KEY `parent_station` (`dataset_id`,`parent_station`) USING BTREE;

--
-- Indizes für die Tabelle `stop_times`
--
ALTER TABLE `stop_times`
  ADD PRIMARY KEY (`dataset_id`,`trip_id`,`stop_sequence`) USING BTREE,
  ADD KEY `stops` (`dataset_id`,`stop_id`) USING BTREE,
  ADD KEY `stop_and_time` (`dataset_id`,`stop_id`,`departure_time`) USING BTREE,
  ADD KEY `departure_time` (`dataset_id`,`departure_time`) USING BTREE,
  ADD KEY `arrival_time` (`dataset_id`,`arrival_time`) USING BTREE;

--
-- Indizes für die Tabelle `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`dataset_id`,`trip_id`) USING BTREE,
  ADD KEY `routes` (`dataset_id`,`route_id`) USING BTREE,
  ADD KEY `service` (`dataset_id`,`service_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `datasets`
--
ALTER TABLE `datasets`
  MODIFY `dataset_id` int(11) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
