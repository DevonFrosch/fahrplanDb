<?php

class GTFSConstants
{
	public const ROUTE_TYPE_CLASS_RAIL = "rail";
	public const ROUTE_TYPE_CLASS_LIGHTRAIL = "lightrail";
	public const ROUTE_TYPE_CLASS_BUS = "bus";
	public const ROUTE_TYPE_CLASS_OTHER = "other";
	public const ROUTE_TYPE_CLASS_UNKNOWN = "unknown";
	public const ROUTE_TYPE_CLASSES = [
		self::ROUTE_TYPE_CLASS_RAIL,
		self::ROUTE_TYPE_CLASS_LIGHTRAIL,
		self::ROUTE_TYPE_CLASS_BUS,
		self::ROUTE_TYPE_CLASS_OTHER,
		self::ROUTE_TYPE_CLASS_UNKNOWN,
	];

	public const ROUTE_TYPES = [
		0 => ["Straßenbahn", self::ROUTE_TYPE_CLASS_LIGHTRAIL],
		1 => ["U-Bahn", self::ROUTE_TYPE_CLASS_LIGHTRAIL],
		2 => ["Eisenbahn", self::ROUTE_TYPE_CLASS_RAIL],
		3 => ["Bus", self::ROUTE_TYPE_CLASS_BUS],
		4 => ["Fähre", self::ROUTE_TYPE_CLASS_OTHER],
		5 => ["Standseilbahn", self::ROUTE_TYPE_CLASS_OTHER],
		101 => ["Fernverkehr (Express)", self::ROUTE_TYPE_CLASS_RAIL],
		102 => ["InterCity", self::ROUTE_TYPE_CLASS_RAIL],
		103 => ["InterRegio", self::ROUTE_TYPE_CLASS_RAIL],
		104 => ["Autozug", self::ROUTE_TYPE_CLASS_RAIL],
		105 => ["Schlafzug", self::ROUTE_TYPE_CLASS_RAIL],
		106 => ["Regionalzug", self::ROUTE_TYPE_CLASS_RAIL],
		107 => ["Touristenverkehr", self::ROUTE_TYPE_CLASS_RAIL],
		109 => ["S-Bahn", self::ROUTE_TYPE_CLASS_RAIL],
		110 => ["Ersatzverkehr", self::ROUTE_TYPE_CLASS_RAIL],
		117 => ["Zusatz-Bahnverkehr", self::ROUTE_TYPE_CLASS_RAIL],
		202 => ["Fernbus", self::ROUTE_TYPE_CLASS_BUS],
		401 => ["Metro", self::ROUTE_TYPE_CLASS_LIGHTRAIL],
		700 => ["Bus", self::ROUTE_TYPE_CLASS_BUS],
		702 => ["Expressbuss", self::ROUTE_TYPE_CLASS_BUS],
		705 => ["Nachtbus", self::ROUTE_TYPE_CLASS_BUS],
		710 => ["Sightseeing-Bus", self::ROUTE_TYPE_CLASS_BUS],
		715 => ["Anrufbus", self::ROUTE_TYPE_CLASS_BUS],
		900 => ["Straßenbahn", self::ROUTE_TYPE_CLASS_LIGHTRAIL],
		1000 => ["Fähre", self::ROUTE_TYPE_CLASS_OTHER],
		1300 => ["Seilbahn", self::ROUTE_TYPE_CLASS_OTHER],
		1400 => ["Seilbahn", self::ROUTE_TYPE_CLASS_OTHER],
		1500 => ["Taxi", self::ROUTE_TYPE_CLASS_OTHER],
		1700 => ["Sonstiges", self::ROUTE_TYPE_CLASS_UNKNOWN],
	];

	const LOCATION_TYPES = [
		0 => "Halt / Bahnsteig",
		1 => "Bahnhof",
		2 => "Eingang",
		3 => "Allgemeiner Ort",
		4 => "Einstiegsbereich",
	];
	
	public const IMPORT_STATE_INIT = "INIT";
	public const IMPORT_STATE_FILES_READ = "FILES_READ";
	public const IMPORT_STATE_FILTERED = "FILTERED";
	public const IMPORT_STATE_REFINED = "REFINED";
	public const IMPORT_STATE_COMPLETE = "COMPLETE";
	public const IMPORT_STATES = [
		self::IMPORT_STATE_INIT => "Initialisierung",
		self::IMPORT_STATE_FILES_READ => "Dateien eingelesen",
		self::IMPORT_STATE_FILTERED => "Daten gefiltert",
		self::IMPORT_STATE_REFINED => "Daten verarbeitet",
		self::IMPORT_STATE_COMPLETE => "Abgeschlossen",
	];
	public const IMPORT_STATE_COMPARE = [
		self::IMPORT_STATE_INIT => 0,
		self::IMPORT_STATE_FILES_READ => 1,
		self::IMPORT_STATE_FILTERED => 2,
		self::IMPORT_STATE_REFINED => 3,
		self::IMPORT_STATE_COMPLETE => 4,
	];

	public static function getRouteTypeName(int $type): string
	{
		if(isset(self::ROUTE_TYPES[$type]))
		{
			return self::ROUTE_TYPES[$type][0];
		}
		return "Unbekannt ($type)";
	}
	public static function getRouteTypeValue(string $name) : int
	{
		$names = array_column($name, 0);
		if(in_array($name, $names))
		{
			return array_search($name, $names);
		}
		return 1700;
	}
	public static function isRouteTypeClass(string $routeTypeClass) : bool
	{
		return in_array($routeTypeClass, self::ROUTE_TYPE_CLASSES);
	}
	public static function getRouteTypeClass(int $type) : string
	{
		if(isset(self::ROUTE_TYPES[$type]))
		{
			return self::ROUTE_TYPES[$type][1];
		}
		return self::ROUTE_TYPE_CLASS_UNKNOWN;
	}
	public static function getRouteTypesByClass(string $routeTypeClass) : array
	{
		return array_filter(self::ROUTE_TYPES, function($rt) use($routeTypeClass) { return $rt[1] == $routeTypeClass; });
	}

	public static function getLocationTypeName(int $locationType) : string
	{
		if(isset(self::LOCATION_TYPES[$locationType]))
		{
			return self::LOCATION_TYPES[$locationType];
		}
		return "Unbekannt ($locationType)";
	}
	
	public static function isImportState(string $importState) : bool
	{
		return in_array($importState, array_keys(self::IMPORT_STATES));
	}
	public static function hasReachedState(string $currentState, string $untilState) : bool
	{
		$current = GTFSConstants::IMPORT_STATE_COMPARE[$currentState];
		$until = GTFSConstants::IMPORT_STATE_COMPARE[$untilState];

		return $current >= $until;
	}
}
