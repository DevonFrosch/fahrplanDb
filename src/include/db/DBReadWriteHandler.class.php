<?php

require_once("DBReadHandler.class.php");

class DBReadWriteHandler extends DBReadHandler
{
	const EXCLUSION_COLUMN_NAME = "importExclusion";
	
	public function addDataset(string $name, string $license, ?string $referenceDate = null, string $desc = "") : bool
	{
		$sql = "INSERT INTO `datasets`
				SET `dataset_name` = :name,
					`license` = :license,
					`reference_date` = :referenceDate,
					`desc` = :desc";
		$rowCount = $this->execute($sql, [
			":name" => $name,
			":license" => $license,
			":referenceDate" => $referenceDate,
			":desc" => $desc,
		]);
		return $rowCount == 1;
	}
	
	public function addToTable(string $name, array $data) : array
	{
		$sqlParams = [];
		$params = [];
		$errors = [];
		
		if(empty($data) || !is_array($data[0]))
		{
			throw new DBException("Fehler: Falsche Parameter für addToTable.");
		}
		
		foreach($data[0] as $key => $value)
		{
			$sqlParams[] = "`$key` = :$key";
			$params[":$key"] = $value;
		}
		
		$insertSql = "INSERT INTO `$name` SET ".join(",", $sqlParams).";";
		
		try
		{
			$stmt = $this->prepare($insertSql);
			
			foreach($data as $index => $line)
			{
				foreach($line as $key => $value)
				{
					$stmt->bindValue(":$key", $value);
				}
				$success = $stmt->execute();
				if(!$success)
				{
					$errors[$index] = [$stmt->errorCode(), $stmt->errorInfo(), $line];
				}
			}
		}
		catch(PDOException $e)
		{
			throw new DBException("Datenbankfehler beim Schreiben.", $e, $insertSql);
		}
		
		return $errors;
	}
	
	public function loadData(string $fileName, string $eol, string $tableName, array $columns, array $sets, array $params = null) : void
	{
		if(!$this->pdo)
		{
			throw new DBException("Keine Datenbankverbindung.");
		}
		
		if(empty($columns))
		{
			throw new DBException("Datenbankfehler: Format für columns falsch.");
		}
		
		$sql = "LOAD DATA INFILE ".$this->pdo->quote($fileName)."
				INTO TABLE `$tableName`
				FIELDS TERMINATED BY ','
					OPTIONALLY ENCLOSED BY '\"'
				LINES TERMINATED BY '$eol'
				IGNORE 1 LINES
				(".join(", ", $columns).") ";
		
		if(!empty($sets))
		{
			$sql .= "
				SET ".$this->joinKeyValuePairs($sets);
		}
		
		$this->logQuery($sql, $params);
		$this->execute($sql, $params);
	}
	
	public function cleanupTable(string $tableName, int $datasetId, array $references, int $chunkedDelete = 0, bool $useOR = false, bool $bigTable = false) : int
	{
		if(empty($references))
		{
			throw new DBException("Datenbankfehler: cleanupTable braucht Referenzen.");
		}
		
		$conditionParts = [];
		
		foreach($references as $ref)
		{
			$conditionParts[] = "NOT EXISTS (SELECT 1 FROM `".$ref[1]."` a WHERE a.dataset_id = :datasetId AND a.`".$ref[2]."` = `".$tableName."`.`".$ref[0]."` LIMIT 1)";
		}
		
		$glue = " AND ";
		if($useOR)
		{
			$glue = " OR ";
		}
		$condition = join($glue, $conditionParts);
		
		if($bigTable)
		{
			return $this->deleteBigData($tableName, $datasetId, $condition);
		}
		return $this->deleteData($tableName, $datasetId, $condition, [], $chunkedDelete);
	}
	
	public function deleteData(string $tableName, int $datasetId, string $condition = "1=1", array $params = [], int $chunkedDelete = 0) : int
	{
		if(empty($condition))
		{
			throw new DBException("Datenbankfehler: Kann nicht ohne Bedingung löschen.");
		}

		$this->disableKeys($tableName);
		$rowCount = 0;
		$params[":datasetId"] = $datasetId;
		try
		{
			$sql = "DELETE FROM `$tableName` WHERE dataset_id = :datasetId AND $condition";
			if($chunkedDelete > 0)
			{
				$sql .= " LIMIT $chunkedDelete";
			}
			do
			{
				$info = null;
				if($chunkedDelete > 0)
				{
					$info = "Bereits $rowCount gelöscht.";
				}
				$this->logQuery($sql, $params, $info);
				$count = $this->execute($sql, $params);
				$rowCount += $count;
			}
			while($chunkedDelete > 0 && $count > 0);
		}
		finally
		{
			$this->enableKeys($tableName);
		}
		return $rowCount;
	}
	
	public function getImportTableName(string $tableName) : string
	{
		return $tableName."_import";
	}
	public function createImportTable(string $tableName) : string
	{
		$importTableName = $this->getImportTableName($tableName);
		$this->execute("DROP TABLE IF EXISTS `$importTableName`");
		$this->execute("CREATE TEMPORARY TABLE `$importTableName` LIKE `$tableName`");
		$this->execute("ALTER TABLE `$importTableName`
						ADD COLUMN IF NOT EXISTS `".self::EXCLUSION_COLUMN_NAME."` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci'");
		$this->execute("ALTER TABLE `$importTableName`
						ADD INDEX IF NOT EXISTS `".self::EXCLUSION_COLUMN_NAME."` (`".self::EXCLUSION_COLUMN_NAME."`)");
		return $importTableName;
	}
	
	public function exclude(string $tableName, int $datasetId, string $reason, string $condition = "1=1", array $params = []) : int
	{
		$importTableName = $this->getImportTableName($tableName);
		
		$params[":datasetId"] = $datasetId;
		$params[":reason"] = $reason;
		$sql = "UPDATE TABLE `$importTableName`
				SET `".self::EXCLUSION_COLUMN_NAME."` = :reason
				WHERE dataset_id = :datasetId
				AND $condition";
		
		$this->logQuery($sql, $params);
		$rowCount = $this->execute($sql, $params);
		return $rowCount;
	}
	
	public function copyFromImportTable(string $tableName, array $columns = []) : int
	{
		$importTableName = $this->getImportTableName($tableName);
		$sql = "INSERT INTO TABLE `$tableName` SELECT `".join("`, `", $columns)."` FROM `$importTableName` WHERE `".self::EXCLUSION_COLUMN_NAME."` IS NULL";
		$this->logQuery($sql, $params);
		$rowCount = $this->execute($sql, $params);
		return $rowCount;
	}
	
	public function updateParentStops(int $datasetId) : int
	{
		if($datasetId < 0)
		{
			throw new DBException("Datenbankfehler: datasetId negativ.");
		}
		$sql = "
			UPDATE stops s
			SET s.is_parent = '1'
			WHERE s.dataset_id = :datasetId
			AND EXISTS (
				SELECT s2.stop_id
				FROM stops s2
				WHERE s2.dataset_id = s.dataset_id
				AND s2.parent_station = s.stop_id
			)";
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
					SELECT MIN(start_date) FROM calendar c WHERE c.dataset_id = :datasetId
				), :maxDate),
				IFNULL((
					SELECT MIN(date) FROM calendar_dates c WHERE c.dataset_id = :datasetId
				), :maxDate)
			),
			s.end_date = GREATEST(
				IFNULL((
					SELECT MAX(end_date) FROM calendar c WHERE c.dataset_id = :datasetId
				), :minDate),
				IFNULL((
					SELECT MAX(date) FROM calendar_dates c WHERE c.dataset_id = :datasetId
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
}
