<?php

require_once("DBHandler.class.php");

class DBReadHandler extends DBHandler
{
	public const DATE_REGEX = "/[1-9][0-9]{3}-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])/";

	public const MAX_ROWS = 1500;

	public function getDatasets(bool $withCounts = false) : array
	{
		$sql = "SELECT * FROM datasets";
		$datasets = $this->query($sql);

		if($withCounts)
		{
			foreach($datasets as $i => $dataset)
			{
				$datasets[$i]["counts"] = [];
				$datasetId = $dataset["dataset_id"];
				foreach(self::TABLES as $tableName)
				{
					$datasets[$i]["counts"][$tableName] = $this->getTableCount($tableName, $datasetId);
				}
			}
		}

		return $datasets;
	}

	public function getMarginDates(int $datasetId) : array
	{
		$result = $this->query("
			SELECT start_date, end_date
			FROM datasets
			WHERE dataset_id = :datasetId", [
			":datasetId" => $datasetId,
		]);
		if(sizeof($result) < 1)
		{
			throw new DBException("Dataset nicht gefunden.");
		}
		return [
			$result[0]["start_date"],
			$result[0]["end_date"],
		];
	}

	public function isValidDataset(int $datasetId) : bool
	{
		try
		{
			$count = $this->queryValue("
				SELECT COUNT(*)
				FROM datasets
				WHERE dataset_id = :datasetId
				LIMIT 1", [
				":datasetId" => $datasetId,
			]);
			return $count == 1;
		}
		catch(DBException $e)
		{
			return false;
		}
	}

	public function getTableCounts(string $tableName) : array
	{
		$sql = "SELECT dataset_id, COUNT(*) count FROM `$tableName` GROUP BY dataset_id";
		$result = $this->query($sql);

		$counts = [];
		foreach($result as $count)
		{
			$counts[$count["dataset_id"]] = $count["count"];
		}
		return $counts;
	}
	public function getTableCount(string $tableName, ?int $datasetId) : int
	{
		$counts = $this->getTableCounts($tableName);
		if($datasetId !== null)
		{
			if(isset($counts[$datasetId]))
			{
				return $counts[$datasetId];
			}
			return 0;
		}
		$sum = 0;
		foreach($counts as $count)
		{
			$sum += $count;
		}
		return $sum;
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
	public function getStops(int $datasetId, ?string $parentStopId = null, ?string $nameFilter = null, ?string $date = null) : array
	{
		$params = [":datasetId" => $datasetId];
		$sql = "
			SELECT s.*,
				(
					".$this->tripCountQuery($date)."
				) stop_time_count,
				(
					SELECT COUNT(*)
					FROM stops s2
					WHERE s2.dataset_id = s.dataset_id
					AND s2.parent_station = s.stop_id
				) children_count
			FROM stops s
			WHERE dataset_id = :datasetId";

		if($date !== null)
		{
			$params[":date"] = $date;
		}

		if($nameFilter !== null)
		{
			$sql .= "
				AND stop_name LIKE :nameFilter";
			$params[":nameFilter"] = str_replace(["%", "*"], ["\\%", "%"], $nameFilter)."%";
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

	public function getRoutes(int $datasetId, ?string $agencyId = null, ?string $date = null) : array
	{
		self::checkDate($date);

		$additionalJoin = "";
		$additionalWhere = "";
		$params = [
			":datasetId" => $datasetId,
		];

		if($date !== null)
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
			$params[":date"] = $date;
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

	public function getTrips(int $datasetId, string $routeId, ?string $date = null, string $direction = "") : array
	{
		self::checkDate($date);

		$additionalJoin = "";
		$additionalWhere = "";
		$params = [
			":datasetId" => $datasetId,
			":routeId" => $routeId,
		];

		if($date !== null)
		{
			$additionalJoin .= "
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
			$params[":date"] = $date;
		}

		if($direction === "0" || $direction === "1")
		{
			$additionalWhere .= "
				AND t.direction_id = :direction";
			$params[":direction"] = $direction;
		}

		$sql = "
			SELECT t.*,
				s1.stop_name AS start, s1.platform_code start_platform_code, st1.departure_time,
				s2.stop_name AS dest, s2.platform_code dest_platform_code, st2.arrival_time
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

	public function getStopTimesStop(int $datasetId, string $stopId, ?string $date = null) : array
	{
		self::checkDate($date);

		$additionalJoin = "";
		$additionalWhere = "";
		$params = [
			":datasetId" => $datasetId,
			":stopId" => $stopId,
		];

		if($date !== null)
		{
			$additionalJoin .= "
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
			$params[":date"] = $date;
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

	protected static function checkDate(?string $date) : void
	{
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
