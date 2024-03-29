<?php

require_once("DBReadWriteHandler.class.php");

class GTFSDBHandler extends DBReadWriteHandler
{
	public function updateParentStops(int $datasetId) : int
	{
		if($datasetId < 0)
		{
			throw new DBException("Datenbankfehler: datasetId negativ.");
		}
		$sql = "
			UPDATE ".$this->getImportTableName("stops")." s
			SET s.is_parent = '1'
			WHERE s.dataset_id = :datasetId
			AND EXISTS (
				SELECT s2.stop_id
				FROM ".$this->getImportTableName("stops")." s2
				WHERE s2.dataset_id = s.dataset_id
				AND s2.parent_station = s.stop_id
			)";
		$params = [":datasetId" => $datasetId];
		$this->logQuery($sql, $params);
		return $this->execute($sql, $params);
	}

	public function firstLastStopForTrips(int $datasetId) : int
	{
		if($datasetId < 0)
		{
			throw new DBException("Datenbankfehler: datasetId negativ.");
		}
		$sql = "
			UPDATE ".$this->getImportTableName("trips")." t
			SET t.first_stop = (
					SELECT st1.stop_id
					FROM ".$this->getImportTableName("stop_times")." st1
					WHERE st1.dataset_id = :datasetId
					AND st1.trip_id = t.trip_id
					AND st1.stop_sequence = (
						SELECT MIN(sts1.stop_sequence)
						FROM ".$this->getImportTableName("stop_times")." sts1
						WHERE sts1.dataset_id = :datasetId
						AND sts1.trip_id = t.trip_id
					)
					LIMIT 1
				),
				t.last_stop = (
					SELECT st2.stop_id
					FROM ".$this->getImportTableName("stop_times")." st2
					WHERE st2.dataset_id = :datasetId
					AND st2.trip_id = t.trip_id
					AND st2.stop_sequence = (
						SELECT MAX(sts2.stop_sequence)
						FROM ".$this->getImportTableName("stop_times")." sts2
						WHERE sts2.dataset_id = :datasetId
						AND sts2.trip_id = t.trip_id
					)
					LIMIT 1
				)
			WHERE t.dataset_id = :datasetId";
		$params = [":datasetId" => $datasetId];
		$this->logQuery($sql, $params);
		return $this->execute($sql, $params);
	}

	public function setDatasetDates(int $datasetId) : int
	{
		if($datasetId < 0)
		{
			throw new DBException("Datenbankfehler: datasetId negativ.");
		}

		$sql = "
			UPDATE datasets s
			SET s.start_date = LEAST(
				IFNULL((
					SELECT MIN(start_date) FROM ".$this->getImportTableName("calendar")." c WHERE c.dataset_id = :datasetId
				), :maxDate),
				IFNULL((
					SELECT MIN(date) FROM ".$this->getImportTableName("calendar_dates")." c WHERE c.dataset_id = :datasetId
				), :maxDate)
			),
			s.end_date = GREATEST(
				IFNULL((
					SELECT MAX(end_date) FROM ".$this->getImportTableName("calendar")." c WHERE c.dataset_id = :datasetId
				), :minDate),
				IFNULL((
					SELECT MAX(date) FROM ".$this->getImportTableName("calendar_dates")." c WHERE c.dataset_id = :datasetId
				), :minDate)
			)
			WHERE s.dataset_id = :datasetId";
		$params = [
			":datasetId" => $datasetId,

			// Definition nach https://mariadb.com/kb/en/date/
			// Ansonsten nimmt least() immer den NULL-Wert, wenn einer von beiden nicht vorhanden ist
			":minDate" => "1000-00-00",
			":maxDate" => "9999-12-31",
		];
		$this->logQuery($sql, $params);
		return $this->execute($sql, $params);
	}

	protected function tripCountQuery(?string $date = null) : string
	{
		$additionalJoin = "";
		$additionalWhere = "";

		if($date !== null)
		{
			$additionalJoin .= "
				LEFT JOIN trips t
					ON t.dataset_id = st.dataset_id
					AND t.trip_id = st.trip_id
				LEFT JOIN calendar c
					ON c.dataset_id = t.dataset_id
					AND c.service_id = t.service_id
					AND :date BETWEEN c.start_date AND c.end_date
					AND ".self::getWeekdayCondition($date)."
				LEFT JOIN calendar_dates cd
					ON cd.dataset_id = t.dataset_id
					AND cd.service_id = t.service_id
					AND cd.date = :date";
			$additionalWhere .= "
				AND (
					(c.service_id IS NOT NULL AND IFNULL(cd.exception_type, 0) <> '2')
					OR IFNULL(cd.exception_type, 0) = '1'
				)";
		}

		return "SELECT COUNT(*)
				FROM stop_times st
				$additionalJoin
				WHERE st.dataset_id = s.dataset_id
				AND st.stop_id = s.stop_id
				$additionalWhere";
	}
	public function getStops(int $datasetId, ?string $parentStopId = null, array $filters = []) : array
	{
		$params = [":datasetId" => $datasetId];
		$sql = "
			SELECT s.*,
				(
					".$this->tripCountQuery($filters["date"])."
				) stop_time_count,
				(
					SELECT COUNT(*)
					FROM stops s2
					WHERE s2.dataset_id = s.dataset_id
					AND s2.parent_station = s.stop_id
				) children_count
			FROM stops s
			WHERE dataset_id = :datasetId";

		if(isset($filters["date"]) && $filters["date"] !== null)
		{
			$params[":date"] = $filters["date"];
		}

		if(isset($filters["name"]) && $filters["name"] !== null)
		{
			$sql .= "
				AND stop_name LIKE :nameFilter";
			$params[":nameFilter"] = str_replace(["%", "*"], ["\\%", "%"], $filters["name"])."%";
		}

		if(isset($filters["code"]) && $filters["code"] !== null)
		{
			$sql .= "
				AND stop_code LIKE :codeFilter";
			$params[":codeFilter"] = str_replace(["%", "*"], ["\\%", "%"], $filters["code"])."%";
		}

		if($parentStopId !== null)
		{
			$sql .= "
				AND parent_station = :parentStopId";
			$params[":parentStopId"] = $parentStopId;
		}
		else
		{
			$sql .= "
				AND (parent_station IS NULL OR parent_station = '')";
		}

		if(isset($filters["filterEmpty"]) && $filters["filterEmpty"])
		{
			$sql .= "
				HAVING (stop_time_count > 0 OR children_count > 0)";
		}

		$sql .= "
			ORDER BY s.stop_name ASC, s.platform_code ASC, s.stop_id ASC
			LIMIT ".(self::MAX_ROWS+1);

		return $this->query($sql, $params);
	}

	public function getStop(int $datasetId, string $stopId) : ?array
	{
		$stops = $this->query("
			SELECT s.*
			FROM stops s
			WHERE s.dataset_id = :datasetId
			AND stop_id = :stopId
			LIMIT 1", [
			":datasetId" => $datasetId,
			":stopId" => $stopId,
		]);
		if(isset($stops[0]))
		{
			return $stops[0];
		}
		return null;
	}

	public function getAgencies(int $datasetId) : array
	{
		return $this->query("
			SELECT *
			FROM agency
			WHERE dataset_id = :datasetId
			ORDER BY agency_name
			LIMIT ".(self::MAX_ROWS+1), [
			":datasetId" => $datasetId
		]);
	}

	public function getRoutes(int $datasetId, ?string $agencyId = null, array $filters = []) : array
	{
		self::checkDate($filters);

		$additionalJoin = "";
		$additionalWhere = "";
		$params = [
			":datasetId" => $datasetId,
		];

		if(isset($filters["date"]) && $filters["date"] !== null)
		{
			$additionalWhere .= "
				AND EXISTS (
					SELECT t.trip_id
					FROM trips t
						LEFT JOIN calendar c
						ON c.dataset_id = t.dataset_id
						AND c.service_id = t.service_id
						AND :date BETWEEN c.start_date AND c.end_date
						AND ".self::getWeekdayCondition($date)."
					LEFT JOIN calendar_dates cd
						ON cd.dataset_id = t.dataset_id
						AND cd.service_id = t.service_id
						AND cd.date = :date
					WHERE t.route_id = r.route_id
					AND (
						(c.service_id IS NOT NULL AND IFNULL(cd.exception_type, 0) <> '2')
						OR IFNULL(cd.exception_type, 0) = '1'
					)
				)";
			$params[":date"] = $filters["date"];
		}

		if($agencyId !== null)
		{
			$additionalWhere .= "
				AND a.agency_id = :agencyId";
			$params[":agencyId"] = $agencyId;
		}

		$sql = "
			SELECT r.*, COALESCE(r.route_short_name, r.route_long_name, '') AS route_name, a.agency_name
			FROM routes r
			LEFT JOIN agency a
				ON a.agency_id = r.agency_id
				AND a.dataset_id = r.dataset_id
			$additionalJoin
			WHERE r.dataset_id = :datasetId
				$additionalWhere
			ORDER BY a.agency_name ASC, a.agency_id ASC, r.route_short_name ASC, r.route_id ASC
			LIMIT ".(self::MAX_ROWS+1);
		return $this->query($sql, $params);
	}

	public function getRoute(int $datasetId, string $routeId) : ?array
	{
		$routes = $this->query("
			SELECT r.*, COALESCE(r.route_short_name, r.route_long_name, '') AS route_name, a.agency_name
			FROM routes r
			LEFT JOIN agency a
				ON a.agency_id = r.agency_id
				AND a.dataset_id = r.dataset_id
			WHERE r.dataset_id = :datasetId
			AND route_id = :routeId
			LIMIT 1", [
			":datasetId" => $datasetId,
			":routeId" => $routeId,
		]);
		if(isset($routes[0]))
		{
			return $routes[0];
		}
		return null;
	}

	public function getTrips(int $datasetId, string $routeId, array $filters = []) : array
	{
		self::checkDate($filters);

		$additionalJoin = "";
		$additionalWhere = "";
		$params = [
			":datasetId" => $datasetId,
			":routeId" => $routeId,
		];

		if(isset($filters["date"]) && $filters["date"] !== null)
		{
			$additionalJoin .= "
				LEFT JOIN calendar c
					ON c.dataset_id = t.dataset_id
					AND c.service_id = t.service_id
					AND :date BETWEEN c.start_date AND c.end_date
					AND ".self::getWeekdayCondition($filters["date"])."
				LEFT JOIN calendar_dates cd
					ON cd.dataset_id = t.dataset_id
					AND cd.service_id = t.service_id
					AND cd.date = :date";
			$additionalWhere .= "
				AND (
					(c.service_id IS NOT NULL AND IFNULL(cd.exception_type, 0) <> '2')
					OR IFNULL(cd.exception_type, 0) = '1'
				)";
			$params[":date"] = $filters["date"];
		}

		if(isset($filters["direction"]) && ($filters["direction"] === "0" || $filters["direction"] === "1"))
		{
			$additionalWhere .= "
				AND t.direction_id = :direction";
			$params[":direction"] = $filters["direction"];
		}

		if(isset($filters["short_name"]) && $filters["short_name"] !== null)
		{
			$additionalWhere .= "
				AND t.trip_short_name LIKE :shortNameFilter";
			$params[":shortNameFilter"] = str_replace(["%", "*"], ["\\%", "%"], $filters["short_name"])."%";
		}

		$sql = "
			SELECT t.*,
				s1.stop_name AS start, s1.platform_code AS start_platform_code, st1.departure_time,
				s2.stop_name AS dest, s2.platform_code AS dest_platform_code, st2.arrival_time
			FROM trips t
			LEFT JOIN stop_times st1
				ON st1.dataset_id = t.dataset_id
				AND st1.trip_id = t.trip_id
				AND st1.stop_sequence = (
					SELECT MIN(stop_sequence) FROM stop_times WHERE dataset_id = st1.dataset_id AND trip_id = st1.trip_id
				)
			LEFT JOIN stops s1
				ON s1.dataset_id = t.dataset_id
				AND s1.stop_id = st1.stop_id
			LEFT JOIN stop_times st2
				ON st2.dataset_id = t.dataset_id
				AND st2.trip_id = t.trip_id
				AND st2.stop_sequence = (
					SELECT MAX(stop_sequence) FROM stop_times WHERE dataset_id = st2.dataset_id AND trip_id = st2.trip_id
				)
			LEFT JOIN stops s2
				ON s2.dataset_id = t.dataset_id
				AND s2.stop_id = st2.stop_id
			$additionalJoin
			WHERE t.dataset_id = :datasetId
				AND t.route_id = :routeId
				$additionalWhere
			ORDER BY st1.arrival_time ASC, st1.departure_time ASC, t.trip_short_name ASC, t.trip_id ASC
			LIMIT ".(self::MAX_ROWS+1);

		return $this->query($sql, $params);
	}

	public function getTrip(int $datasetId, string $tripId) : ?array
	{
		$trips = $this->query("
			SELECT t.*, r.*, a.agency_name, a.agency_id
			FROM trips t
			LEFT JOIN routes r
				ON r.dataset_id = t.dataset_id
				AND r.route_id = t.route_id
			LEFT JOIN agency a
				ON a.dataset_id = r.dataset_id
				AND a.agency_id = r.agency_id
			WHERE t.dataset_id = :datasetId
			AND t.trip_id = :tripId
			LIMIT 1", [
			":datasetId" => $datasetId,
			":tripId" => $tripId,
		]);
		if(isset($trips[0]))
		{
			return $trips[0];
		}
		return null;
	}

	public function getStopTimesTrip(int $datasetId, string $tripId) : array
	{
		return $this->query("
			SELECT st.*, s.*
			FROM stop_times st
			LEFT JOIN stops s
				ON s.dataset_id = st.dataset_id
				AND s.stop_id = st.stop_id
			WHERE st.dataset_id = :datasetId
			AND st.trip_id = :tripId
			ORDER BY st.stop_sequence ASC, st.arrival_time ASC, st.departure_time ASC, s.stop_name ASC
			LIMIT ".(self::MAX_ROWS+1), [
			":datasetId" => $datasetId,
			":tripId" => $tripId,
		]);
	}

	public function getStopTimesStop(int $datasetId, string $stopId, array $filters = []) : array
	{
		self::checkDate($filters);

		$additionalJoin = "";
		$additionalWhere = "";
		$params = [
			":datasetId" => $datasetId,
			":stopId" => $stopId,
		];

		if(isset($filters["date"]) && $filters["date"] !== null)
		{
			$additionalJoin .= "
				LEFT JOIN calendar c
					ON c.dataset_id = t.dataset_id
					AND c.service_id = t.service_id
					AND :date BETWEEN c.start_date AND c.end_date
					AND ".self::getWeekdayCondition($filters["date"])."
				LEFT JOIN calendar_dates cd
					ON cd.dataset_id = t.dataset_id
					AND cd.service_id = t.service_id
					AND cd.date = :date";
			$additionalWhere .= "
				AND (
					(c.service_id IS NOT NULL AND IFNULL(cd.exception_type, 0) <> '2')
					OR IFNULL(cd.exception_type, 0) = '1'
				)";
			$params[":date"] = $filters["date"];
		}

		if(isset($filters["headsign"]) && $filters["headsign"] !== null)
		{
			$additionalWhere .= "
				AND trip_headsign LIKE :headsignFilter";
			$params[":headsignFilter"] = str_replace(["%", "*"], ["\\%", "%"], $filters["headsign"])."%";
		}

		if(isset($filters["agency_name"]) && $filters["agency_name"] !== null)
		{
			$additionalWhere .= "
				AND agency_name LIKE :agencyNameFilter";
			$params[":agencyNameFilter"] = str_replace(["%", "*"], ["\\%", "%"], $filters["agency_name"])."%";
		}

		if(isset($filters["route_short_name"]) && $filters["route_short_name"] !== null)
		{
			$additionalWhere .= "
				AND route_short_name LIKE :routeShortNameFilter";
			$params[":routeShortNameFilter"] = str_replace(["%", "*"], ["\\%", "%"], $filters["route_short_name"])."%";
		}

		if(isset($filters["short_name"]) && $filters["short_name"] !== null)
		{
			$additionalWhere .= "
				AND trip_short_name LIKE :shortNameFilter";
			$params[":shortNameFilter"] = str_replace(["%", "*"], ["\\%", "%"], $filters["short_name"])."%";
		}

		$sql = "
			SELECT st.*,
				t.trip_id, t.trip_headsign, t.trip_short_name,
				r.route_short_name, a.agency_name, a.agency_id
			FROM stop_times st
			LEFT JOIN trips t
				ON t.dataset_id = st.dataset_id
				AND t.trip_id = st.trip_id
			LEFT JOIN routes r
				ON r.dataset_id = t.dataset_id
				AND r.route_id = t.route_id
			LEFT JOIN agency a
				ON a.dataset_id = r.dataset_id
				AND a.agency_id = r.agency_id
			$additionalJoin
			WHERE st.dataset_id = :datasetId
				AND st.stop_id = :stopId
				$additionalWhere
			ORDER BY st.arrival_time ASC, st.departure_time ASC, t.trip_short_name ASC
			LIMIT ".(self::MAX_ROWS+1);

		return $this->query($sql, $params);
	}

	protected static function checkDate(array $filters) : void
	{
		$date = isset($filters["date"]) ? $filters["date"] : null;

		if($date !== null && !preg_match(self::DATE_REGEX, $date))
		{
			throw new DBException("Fehlerhafte Datumsangabe.");
		}
	}
	protected static function getWeekdayCondition(?string $date) : string
	{
		if($date === null)
		{
			return "1=1";
		}
		$days = [
			0 => "sunday",
			1 => "monday",
			2 => "tuesday",
			3 => "wednesday",
			4 => "thursday",
			5 => "friday",
			6 => "saturday",
		];
		$dayOfWeek = date("w", strtotime($date));
		if(isset($days[$dayOfWeek]))
		{
			return $days[$dayOfWeek]." = '1'";
		}
		return "1=1";
	}
}
