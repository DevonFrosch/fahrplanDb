<?php

class ImportTable
{
	const TABLES = [
		"agency" => [
			"createTable" => "(
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`dataset_id` int(11) NOT NULL,
				`agency_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`agency_name` varchar(100) NOT NULL,
				`agency_timezone` varchar(20) DEFAULT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT",
			"createIndex" => [
				"unique" => "ADD UNIQUE KEY IF NOT EXISTS `unique` (`dataset_id`,`agency_id`)"
			]
		],
		"calendar" => [
			"createTable" => "(
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`dataset_id` int(11) NOT NULL,
				`service_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`monday` enum('0','1') NOT NULL DEFAULT '1',
				`tuesday` enum('0','1') NOT NULL DEFAULT '1',
				`wednesday` enum('0','1') NOT NULL DEFAULT '1',
				`thursday` enum('0','1') NOT NULL DEFAULT '1',
				`friday` enum('0','1') NOT NULL DEFAULT '1',
				`saturday` enum('0','1') NOT NULL DEFAULT '1',
				`sunday` enum('0','1') NOT NULL DEFAULT '1',
				`start_date` date NOT NULL,
				`end_date` date NOT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"unique" => "ADD UNIQUE KEY IF NOT EXISTS `unique` (`dataset_id`,`service_id`)",
				"start_date" => "ADD KEY IF NOT EXISTS `start_date` (`dataset_id`,`start_date`,`end_date`)",
				"end_date" => "ADD KEY IF NOT EXISTS `end_date` (`dataset_id`,`end_date`)"
			]
		],
		"calendar_dates" => [
			"createTable" => "(
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`dataset_id` int(11) NOT NULL,
				`service_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`date` date NOT NULL,
				`exception_type` enum('1','2') NOT NULL COMMENT '1 - Service has been added for the specified date.\r\n2 - Service has been removed for the specified date.',
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"unique" => "ADD UNIQUE KEY IF NOT EXISTS `unique` (`dataset_id`,`service_id`,`date`)",
				"date" => "ADD KEY IF NOT EXISTS `date` (`dataset_id`,`date`)"
			]
		],
		"datasets" => [
			"createTable" => "(
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`dataset_id` int(11) NOT NULL,
				`dataset_name` varchar(150) NOT NULL,
				`import_time` datetime NOT NULL DEFAULT current_timestamp(),
				`reference_date` date DEFAULT NULL,
				`desc` mediumtext NOT NULL DEFAULT '',
				`license` mediumtext DEFAULT NULL,
				`start_date` date DEFAULT NULL,
				`end_date` date DEFAULT NULL,
				`import_state` varchar(50) NOT NULL DEFAULT 'INIT',
				`last_logfile` varchar(250) DEFAULT NULL,
				PRIMARY KEY (`dataset_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT",
		],
		"routes" => [
			"createTable" => "(
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`dataset_id` int(11) NOT NULL,
				`route_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`agency_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`route_short_name` varchar(50) DEFAULT NULL,
				`route_long_name` varchar(200) DEFAULT NULL,
				`route_type` int(6) NOT NULL,
				`route_color` varchar(6) NOT NULL DEFAULT 'FFFFFF',
				`route_text_color` varchar(6) NOT NULL DEFAULT '000000',
				`route_desc` text NOT NULL DEFAULT '',
				`continuous_pickup` enum('0','1','2','3') NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping pickup.\r\n1 or empty - No continuous stopping pickup.\r\n2 - Must phone agency to arrange continuous stopping pickup.\r\n3 - Must coordinate with driver to arrange continuous stopping pickup. ',
				`continuous_drop_off` enum('0','1','2','3') NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping drop off.\r\n1 or empty - No continuous stopping drop off.\r\n2 - Must phone agency to arrange continuous stopping drop off.\r\n3 - Must coordinate with driver to arrange continuous stopping drop off. ',
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"unique" => "ADD UNIQUE KEY IF NOT EXISTS `unique` (`dataset_id`,`route_id`)",
				"agency" => "ADD KEY IF NOT EXISTS `agency` (`dataset_id`,`agency_id`)",
				"route_type" => "ADD KEY IF NOT EXISTS `route_type` (`dataset_id`,`route_type`)"
			]
		],
		"stops" => [
			"createTable" => "(
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`dataset_id` int(11) NOT NULL,
				`stop_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`stop_code` varchar(100) DEFAULT NULL,
				`stop_name` varchar(250) DEFAULT NULL,
				`stop_desc` text DEFAULT NULL,
				`stop_lat` float DEFAULT NULL,
				`stop_lon` float DEFAULT NULL,
				`location_type` enum('0','1','2','3','4') NOT NULL DEFAULT '0' COMMENT '• 0 (or blank): Stop (or Platform). A location where passengers board or disembark from a transit vehicle. Is called a platform when defined within a parent_station.\r\n• 1: Station. A physical structure or area that contains one or more platform.\r\n• 2: Entrance/Exit. A location where passengers can enter or exit a station from the street. If an entrance/exit belongs to multiple stations, it can be linked by pathways to both, but the data provider must pick one of them as parent.\r\n• 3: Generic Node. A location within a station, not matching any other location_type, which can be used to link together pathways define in pathways.txt.\r\n• 4: Boarding Area. A specific location on a platform, where passengers can board and/or alight vehicles.',
				`parent_station` varchar(50) DEFAULT NULL,
				`platform_code` varchar(50) DEFAULT NULL,
				`is_parent` enum('0','1') NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"unique" => "ADD UNIQUE KEY IF NOT EXISTS `unique` (`dataset_id`,`stop_id`)",
				"parent_station" => "ADD KEY IF NOT EXISTS `parent_station` (`dataset_id`,`parent_station`)"
			],
			"createIndexNonImport" => [
				"stop_name" => "ADD KEY IF NOT EXISTS `stop_name` (`dataset_id`,`stop_name`)",
				"stop_code" => "ADD KEY IF NOT EXISTS `stop_code` (`dataset_id`,`stop_code`)",
				"location_type" => "ADD KEY IF NOT EXISTS `location_type` (`dataset_id`,`location_type`)"
			]
		],
		"stop_times" => [
			"createTable" => "(
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`dataset_id` int(11) NOT NULL,
				`trip_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`stop_sequence` int(6) NOT NULL,
				`stop_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`arrival_time` time DEFAULT NULL,
				`departure_time` time DEFAULT NULL,
				`stop_headsign` varchar(150) DEFAULT NULL,
				`pickup_type` enum('0','1','2','3') NOT NULL DEFAULT '0' COMMENT '0 or empty - Regularly scheduled pickup.\r\n1 - No pickup available.\r\n2 - Must phone agency to arrange pickup.\r\n3 - Must coordinate with driver to arrange pickup.',
				`drop_off_type` enum('0','1','2','3') NOT NULL DEFAULT '0' COMMENT '0 or empty - Regularly scheduled drop off.\r\n1 - No drop off available.\r\n2 - Must phone agency to arrange drop off.\r\n3 - Must coordinate with driver to arrange drop off.',
				`continuous_pickup` enum('0','1','2','3') NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping pickup.\r\n1 or empty - No continuous stopping pickup.\r\n2 - Must phone agency to arrange continuous stopping pickup.\r\n3 - Must coordinate with driver to arrange continuous stopping pickup. ',
				`continuous_drop_off` enum('0','1','2','3') NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping drop off.\r\n1 or empty - No continuous stopping drop off.\r\n2 - Must phone agency to arrange continuous stopping drop off.\r\n3 - Must coordinate with driver to arrange continuous stopping drop off. ',
				`timepoint` enum('0','1') NOT NULL DEFAULT '1' COMMENT '0 - Times are considered approximate.\r\n1 or empty - Times are considered exact.',
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndexImport" => [
				"trips_import" => "ADD KEY IF NOT EXISTS `trips_import` (`dataset_id`,`importExcluded`,`trip_id`)",
				"stops_import" => "ADD KEY IF NOT EXISTS `stops_import` (`dataset_id`,`importExcluded`,`stop_id`)",
			],
			"createIndexNonImport" => [
				"unique" => "ADD UNIQUE KEY IF NOT EXISTS `unique` (`dataset_id`,`trip_id`,`stop_sequence`)",
				"stops" => "ADD KEY IF NOT EXISTS `stops` (`dataset_id`,`stop_id`)",
				"stop_and_time" => "ADD KEY IF NOT EXISTS `stop_and_time` (`dataset_id`,`stop_id`,`departure_time`)",
				"departure_time" => "ADD KEY IF NOT EXISTS `departure_time` (`dataset_id`,`departure_time`)",
				"arrival_time" => "ADD KEY IF NOT EXISTS `arrival_time` (`dataset_id`,`arrival_time`)"
			]
		],
		"trips" => [
			"createTable" => "(
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`dataset_id` int(11) NOT NULL DEFAULT 0,
				`trip_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`route_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`direction_id` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`service_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
				`trip_headsign` varchar(150) DEFAULT NULL,
				`trip_short_name` varchar(50) DEFAULT NULL,
				`block_id` varchar(50) DEFAULT NULL,
				`first_stop` varchar(50) DEFAULT NULL,
				`last_stop` varchar(50) DEFAULT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"unique" => "ADD UNIQUE KEY IF NOT EXISTS `unique` (`dataset_id`,`trip_id`)",
				"routes" => "ADD KEY IF NOT EXISTS `routes` (`dataset_id`,`route_id`)",
				"service" => "ADD KEY IF NOT EXISTS `service` (`dataset_id`,`service_id`)"
			]
		],
	];
	
	public static function getCreateTable(string $tableName, ?string $alias, bool $ifNotExists = true) : ?string
	{
		if(!isset(self::TABLES[$tableName]))
		{
			throw new DBException("Keine ImportTable-Definition für $tableName gefunden.");
		}
		
		return "CREATE TABLE ".($ifNotExists ? "IF NOT EXISTS " : "")."`$alias` "
			.self::TABLES[$tableName]["createTable"];
	}
	
	public static function getCreateIndex(string $tableName, ?string $alias, bool $isImport) : array
	{
		if(!isset(self::TABLES[$tableName]))
		{
			throw new DBException("Keine ImportTable-Definition für $tableName gefunden.");
		}
		
		$sqls = self::getCreateForIndex($tableName, "createIndex", $alias);
		
		if($isImport)
		{
			$sqls = array_merge($sqls, self::getCreateForIndex($tableName, "createIndexImport", $alias));
		}
		else
		{
			$sqls = array_merge($sqls, self::getCreateForIndex($tableName, "createIndexNonImport", $alias));
		}
		
		return $sqls;
	}
	private static function getCreateForIndex(string $tableName, string $category, string $alias) : array
	{
		if(!isset(self::TABLES[$tableName][$category]))
		{
			return [];
		}
		$sqls = [];
		foreach(self::TABLES[$tableName][$category] as $name => $create)
		{
			$sqls[] = "ALTER TABLE `$alias` ".$create;
		}
		return $sqls;
	}
	
	public static function getDeleteIndex(string $tableName, ?string $alias) : ?array
	{
		if(!isset(self::TABLES[$tableName]))
		{
			throw new DBException("Keine ImportTable-Definition für $tableName gefunden.");
		}
		
		$sqls = array_merge(
			self::getDeleteForIndex($tableName, "createIndex", $alias),
			self::getDeleteForIndex($tableName, "createIndexImport", $alias),
			self::getDeleteForIndex($tableName, "createIndexNonImport", $alias)
		);
		return $sqls;
	}
	private static function getDeleteForIndex(string $tableName, string $category, string $alias) : array
	{
		if(!isset(self::TABLES[$tableName][$category]))
		{
			return [];
		}
		$sqls = [];
		foreach(self::TABLES[$tableName][$category] as $name => $create)
		{
			$sqls[] = "ALTER TABLE `$alias` DROP INDEX IF EXISTS `$name`";
		}
		return $sqls;
	}
}