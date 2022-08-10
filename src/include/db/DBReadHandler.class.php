<?php

require_once("DBHandler.class.php");

class DBReadHandler extends DBHandler
{
	public const DATE_REGEX = "/[1-9][0-9]{3}-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])/";

	public const MAX_ROWS = 1500;

	public function getDatasets() : array
	{
		$sql = "SELECT * FROM datasets";
		return $this->query($sql);
	}
	public function getDataset(int $datasetId) : ?array
	{
		$sql = "SELECT * FROM datasets WHERE dataset_id = :datasetId";
		$result = $this->query($sql, [":datasetId" => $datasetId]);

		if(!empty($result))
		{
			return $result[0];
		}
		return null;
	}

	public function getLastLogPath(int $datasetId) : ?string
	{
		try
		{
			$path = $this->queryValue("
				SELECT last_logfile
				FROM datasets
				WHERE dataset_id = :datasetId
				LIMIT 1", [
				":datasetId" => $datasetId,
			]);
			return $path;
		}
		catch(DBException $e)
		{
			return null;
		}
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

	public function getImportState(int $datasetId) : ?string
	{
		try
		{
			$importState = $this->queryValue("
				SELECT import_state
				FROM `datasets`
				WHERE `dataset_id` = :dataset_id", [
				":dataset_id" => $datasetId,
			]);
			return $importState;
		}
		catch(DBException $e)
		{
			return null;
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
	public function getTableCount(string $tableName, int $datasetId) : ?int
	{
		$sql = "SELECT COUNT(*) count FROM `$tableName` WHERE dataset_id = :dataset_id";
		return $this->queryValue($sql, [":dataset_id" => $datasetId]);
	}
}
