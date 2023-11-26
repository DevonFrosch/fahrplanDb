<?php

class ImportTable
{
	const TABLES = [
		"agency" => [
			"createTable" => "(
				`dataset_id` int(11) NOT NULL,
				`agency_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`agency_name` varchar(100) NOT NULL,
				`agency_timezone` varchar(20) DEFAULT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT",
			"createIndex" => [
				"ADD PRIMARY KEY (`dataset_id`,`agency_id`) USING BTREE"
			]
		],
		"calendar" => [
			"createTable" => "(
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
				`end_date` date NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"ADD PRIMARY KEY (`dataset_id`,`service_id`) USING BTREE",
				"ADD KEY `start_date` (`dataset_id`,`start_date`,`end_date`) USING BTREE",
				"ADD KEY `end_date` (`dataset_id`,`end_date`) USING BTREE"
			]
		],
		"calendar_dates" => [
			"createTable" => "(
				`dataset_id` int(11) NOT NULL,
				`service_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`date` date NOT NULL,
				`exception_type` enum('1','2') NOT NULL COMMENT '1 - Service has been added for the specified date.\r\n2 - Service has been removed for the specified date.'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"ADD PRIMARY KEY (`dataset_id`,`service_id`,`date`) USING BTREE",
				"ADD KEY `date` (`dataset_id`,`date`) USING BTREE"
			]
		],
		"datasets" => [
			"createTable" => "(
				`dataset_id` int(11) NOT NULL,
				`dataset_name` varchar(150) NOT NULL,
				`import_time` datetime NOT NULL DEFAULT current_timestamp(),
				`reference_date` date DEFAULT NULL,
				`desc` mediumtext NOT NULL DEFAULT '',
				`license` mediumtext DEFAULT NULL,
				`start_date` date DEFAULT NULL,
				`end_date` date DEFAULT NULL,
				`import_state` varchar(50) NOT NULL DEFAULT 'INIT',
				`last_logfile` varchar(250) DEFAULT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT",
			"createIndex" => [
				"ADD PRIMARY KEY (`dataset_id`)"
			]
		],
		"routes" => [
			"createTable" => "(
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
				`continuous_drop_off` enum('0','1','2','3') NOT NULL DEFAULT '1' COMMENT '0 - Continuous stopping drop off.\r\n1 or empty - No continuous stopping drop off.\r\n2 - Must phone agency to arrange continuous stopping drop off.\r\n3 - Must coordinate with driver to arrange continuous stopping drop off. '
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"ADD PRIMARY KEY (`dataset_id`,`route_id`) USING BTREE",
				"ADD KEY `agency` (`dataset_id`,`agency_id`) USING BTREE",
				"ADD KEY `route_type` (`dataset_id`,`route_type`) USING BTREE"
			]
		],
		"stops" => [
			"createTable" => "(
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
				`is_parent` enum('0','1') NOT NULL DEFAULT '0'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"ADD PRIMARY KEY (`dataset_id`,`stop_id`) USING BTREE",
				"ADD KEY `stop_name` (`dataset_id`,`stop_name`) USING BTREE",
				"ADD KEY `stop_code` (`dataset_id`,`stop_code`) USING BTREE",
				"ADD KEY `location_type` (`dataset_id`,`location_type`) USING BTREE",
				"ADD KEY `parent_station` (`dataset_id`,`parent_station`) USING BTREE"
			]
		],
		"stop_times" => [
			"createTable" => "(
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
				`timepoint` enum('0','1') NOT NULL DEFAULT '1' COMMENT '0 - Times are considered approximate.\r\n1 or empty - Times are considered exact.'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"ADD PRIMARY KEY (`dataset_id`,`trip_id`,`stop_sequence`) USING BTREE",
				"ADD KEY `stops` (`dataset_id`,`stop_id`) USING BTREE",
			],
			"createIndexNonImport" => [
				"ADD KEY `stop_and_time` (`dataset_id`,`stop_id`,`departure_time`) USING BTREE",
				"ADD KEY `departure_time` (`dataset_id`,`departure_time`) USING BTREE",
				"ADD KEY `arrival_time` (`dataset_id`,`arrival_time`) USING BTREE"
			]
		],
		"trips" => [
			"createTable" => "(
				`dataset_id` int(11) NOT NULL DEFAULT 0,
				`trip_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`route_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`direction_id` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`service_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
				`trip_headsign` varchar(150) DEFAULT NULL,
				`trip_short_name` varchar(50) DEFAULT NULL,
				`block_id` varchar(50) DEFAULT NULL,
				`first_stop` varchar(50) DEFAULT NULL,
				`last_stop` varchar(50) DEFAULT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"createIndex" => [
				"ADD PRIMARY KEY (`dataset_id`,`trip_id`) USING BTREE",
				"ADD KEY `routes` (`dataset_id`,`route_id`) USING BTREE",
				"ADD KEY `service` (`dataset_id`,`service_id`)"
			]
		],
	];
	
	public static function getCreateTable(string $tableName, string $alias, bool $ifNotExists = true) : string
	{
		if(!isset(self::TABLES[$tableName]))
		{
			return null;
		}
		
		return "CREATE TABLE ".($ifNotExists ? "IF NOT EXISTS " : "")."`$alias` "
			.self::TABLES[$tableName]["createTable"];
	}
	
	public static function getCreateIndex(string $tableName, string $alias, bool $isImport) : array
	{
		if(!isset(self::TABLES[$tableName]))
		{
			return null;
		}
		
		$sqls = [];
		$category = $isImport ? "createIndex" : "createIndexNonImport";
		foreach(self::TABLES[$tableName][$category] as $sql)
		{
			$sqls[] = "ALTER TABLE `$alias` ".$sql;
		}
		
		return $sqls;
	}
}