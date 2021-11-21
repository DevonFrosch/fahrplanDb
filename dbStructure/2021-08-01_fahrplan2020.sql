-- phpMyAdmin SQL Dump
-- version 4.6.6deb4
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 01. Aug 2021 um 17:11
-- Server-Version: 10.3.29-MariaDB-0+deb10u1
-- PHP-Version: 7.3.29-1+0~20210701.86+debian10~1.gbp7ad6eb

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `fahrplan2020`
--
CREATE DATABASE IF NOT EXISTS `fahrplan2020` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fahrplan2020`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `agency`
--

CREATE TABLE IF NOT EXISTS `agency` (
  `dataset_id` int(11) NOT NULL,
  `agency_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_timezone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`dataset_id`,`agency_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `calendar`
--

CREATE TABLE IF NOT EXISTS `calendar` (
  `dataset_id` int(11) NOT NULL,
  `service_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `tuesday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `wednesday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `thursday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `friday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `saturday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `sunday` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  PRIMARY KEY (`dataset_id`,`service_id`) USING BTREE,
  KEY `start_date` (`start_date`,`dataset_id`,`end_date`) USING BTREE,
  KEY `end_date` (`end_date`,`dataset_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `calendar_dates`
--

CREATE TABLE IF NOT EXISTS `calendar_dates` (
  `dataset_id` int(11) NOT NULL,
  `service_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `exception_type` enum('1','2') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '1 - Service has been added for the specified date.\r\n2 - Service has been removed for the specified date.',
  PRIMARY KEY (`dataset_id`,`service_id`,`date`) USING BTREE,
  KEY `date` (`date`,`dataset_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `datasets`
--

CREATE TABLE IF NOT EXISTS `datasets` (
  `dataset_id` int(11) NOT NULL AUTO_INCREMENT,
  `dataset_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `import_time` datetime NOT NULL DEFAULT current_timestamp(),
  `reference_date` date DEFAULT NULL,
  `desc` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `license` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  PRIMARY KEY (`dataset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `routes`
--

CREATE TABLE IF NOT EXISTS `routes` (
  `dataset_id` int(11) NOT NULL,
  `route_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agency_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_short_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_long_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_type` int(6) NOT NULL,
  `route_color` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FFFFFF',
  `route_text_color` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '000000',
  `route_desc` text COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `continuous_pickup` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping pickup.\r\n1 or empty - No continuous stopping pickup.\r\n2 - Must phone agency to arrange continuous stopping pickup.\r\n3 - Must coordinate with driver to arrange continuous stopping pickup. ',
  `continuous_drop_off` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping drop off.\r\n1 or empty - No continuous stopping drop off.\r\n2 - Must phone agency to arrange continuous stopping drop off.\r\n3 - Must coordinate with driver to arrange continuous stopping drop off. ',
  PRIMARY KEY (`dataset_id`,`route_id`) USING BTREE,
  KEY `route_type` (`route_type`,`dataset_id`) USING BTREE,
  KEY `FK_routes_agency` (`dataset_id`,`agency_id`),
  KEY `agency` (`agency_id`,`dataset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `stops`
--

CREATE TABLE IF NOT EXISTS `stops` (
  `dataset_id` int(11) NOT NULL,
  `stop_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stop_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stop_name` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stop_desc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stop_lat` float DEFAULT NULL,
  `stop_lon` float DEFAULT NULL,
  `location_type` enum('0','1','2','3','4') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '• 0 (or blank): Stop (or Platform). A location where passengers board or disembark from a transit vehicle. Is called a platform when defined within a parent_station.\r\n• 1: Station. A physical structure or area that contains one or more platform.\r\n• 2: Entrance/Exit. A location where passengers can enter or exit a station from the street. If an entrance/exit belongs to multiple stations, it can be linked by pathways to both, but the data provider must pick one of them as parent.\r\n• 3: Generic Node. A location within a station, not matching any other location_type, which can be used to link together pathways define in pathways.txt.\r\n• 4: Boarding Area. A specific location on a platform, where passengers can board and/or alight vehicles.',
  `parent_station` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_parent` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`dataset_id`,`stop_id`) USING BTREE,
  KEY `stop_name` (`stop_name`,`dataset_id`) USING BTREE,
  KEY `stop_code` (`stop_code`,`dataset_id`) USING BTREE,
  KEY `location_type` (`location_type`,`dataset_id`) USING BTREE,
  KEY `parent_station` (`parent_station`,`dataset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `stop_times`
--

CREATE TABLE IF NOT EXISTS `stop_times` (
  `dataset_id` int(11) NOT NULL,
  `trip_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stop_sequence` int(6) NOT NULL,
  `stop_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arrival_time` time DEFAULT NULL,
  `departure_time` time DEFAULT NULL,
  `stop_headsign` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_type` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '0 or empty - Regularly scheduled pickup.\r\n1 - No pickup available.\r\n2 - Must phone agency to arrange pickup.\r\n3 - Must coordinate with driver to arrange pickup.',
  `drop_off_type` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '0 or empty - Regularly scheduled drop off.\r\n1 - No drop off available.\r\n2 - Must phone agency to arrange drop off.\r\n3 - Must coordinate with driver to arrange drop off.',
  `continuous_pickup` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping pickup.\r\n1 or empty - No continuous stopping pickup.\r\n2 - Must phone agency to arrange continuous stopping pickup.\r\n3 - Must coordinate with driver to arrange continuous stopping pickup. ',
  `continuous_drop_off` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping drop off.\r\n1 or empty - No continuous stopping drop off.\r\n2 - Must phone agency to arrange continuous stopping drop off.\r\n3 - Must coordinate with driver to arrange continuous stopping drop off. ',
  `timepoint` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 - Times are considered approximate.\r\n1 or empty - Times are considered exact.',
  PRIMARY KEY (`dataset_id`,`trip_id`,`stop_sequence`) USING BTREE,
  KEY `FK_stopTimes_stops` (`dataset_id`,`stop_id`),
  KEY `stop_and_time` (`stop_id`,`dataset_id`,`departure_time`) USING BTREE,
  KEY `departure_time` (`departure_time`,`dataset_id`) USING BTREE,
  KEY `arrival_time` (`arrival_time`,`dataset_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `trips`
--

CREATE TABLE IF NOT EXISTS `trips` (
  `dataset_id` int(11) NOT NULL DEFAULT 0,
  `trip_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction_id` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_id` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trip_headsign` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trip_short_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `block_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`dataset_id`,`trip_id`) USING BTREE,
  KEY `FK_trips_routes` (`dataset_id`,`route_id`) USING BTREE,
  KEY `routes` (`route_id`,`dataset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `agency`
--
ALTER TABLE `agency`
  ADD CONSTRAINT `FK_agency_datasets` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`dataset_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `calendar`
--
ALTER TABLE `calendar`
  ADD CONSTRAINT `FK_calendar_datasets` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`dataset_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `calendar_dates`
--
ALTER TABLE `calendar_dates`
  ADD CONSTRAINT `FK_calendarDates_datasets` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`dataset_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `routes`
--
ALTER TABLE `routes`
  ADD CONSTRAINT `FK_routes_agency` FOREIGN KEY (`dataset_id`,`agency_id`) REFERENCES `agency` (`dataset_id`, `agency_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_routes_datasets` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`dataset_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `stops`
--
ALTER TABLE `stops`
  ADD CONSTRAINT `FK_stops_datasets` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`dataset_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `stop_times`
--
ALTER TABLE `stop_times`
  ADD CONSTRAINT `FK_stopTimes_datasets` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`dataset_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_stopTimes_stops` FOREIGN KEY (`dataset_id`,`stop_id`) REFERENCES `stops` (`dataset_id`, `stop_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_stopTimes_trips` FOREIGN KEY (`dataset_id`,`trip_id`) REFERENCES `trips` (`dataset_id`, `trip_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `FK_trips_datasets` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`dataset_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_trips_routes` FOREIGN KEY (`dataset_id`,`route_id`) REFERENCES `routes` (`dataset_id`, `route_id`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
