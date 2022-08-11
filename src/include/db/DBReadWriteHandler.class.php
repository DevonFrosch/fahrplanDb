<?php

require_once("DBReadHandler.class.php");

class DBReadWriteHandler extends DBReadHandler
{
	const EXCLUDED_COLUMN_NAME = "importExcluded";
	const EXCLUSION_COLUMN_NAME = "importExclusion";
	const DATASET_COLUMNS = ["dataset_name", "import_time", "reference_date", "desc", "license", "start_date", "end_date", "import_state"];

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

	public function duplicateDataset(int $oldDatasetId, string $name) : int
	{
		$columns = array_filter(self::DATASET_COLUMNS, function($column) {
			return $column !== "dataset_name";
		});

		$params = [
			":oldDatasetId" => $oldDatasetId,
			":name" => $name,
		];
		$sql = "INSERT INTO `datasets` (dataset_name, `".join("`, `", $columns)."`)
				SELECT :name, `".join("`, `", $columns)."`
				FROM `datasets`
				WHERE `dataset_id` = :oldDatasetId";
		$this->logQuery($sql, $params);
		$this->execute($sql, $params);

		return $this->lastInsertId();
	}

	public function copyDatasetData(int $oldDatasetId, int $newDatasetId, string $tableName, array $columns) : int
	{
		$params = [];
		$params[":oldDatasetId"] = $oldDatasetId;
		$params[":newDatasetId"] = $newDatasetId;
		$sql = "INSERT INTO `$tableName` (`dataset_id`, `".join("`, `", $columns)."`)
				SELECT :newDatasetId, `".join("`, `", $columns)."`
				FROM `$tableName`
				WHERE `dataset_id` = :oldDatasetId";

		$this->logQuery($sql, $params);
		$rowCount = $this->execute($sql, $params);
		return $rowCount;
	}

	public function setImportState(int $datasetId, string $importState) : void
	{
		$sql = "UPDATE `datasets`
				SET `import_state` = :import_state
				WHERE `dataset_id` = :dataset_id";
		$rowCount = $this->execute($sql, [
			":import_state" => $importState,
			":dataset_id" => $datasetId,
		]);
	}

	public function setLastLogFile(int $datasetId, string $path) : void
	{
		$sql = "UPDATE `datasets`
				SET `last_logfile` = :last_logfile
				WHERE `dataset_id` = :dataset_id";
		$rowCount = $this->execute($sql, [
			":last_logfile" => $path,
			":dataset_id" => $datasetId,
		]);
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

	public function cleanupTable(string $tableName, int $datasetId, string $reason, array $references, bool $useOR = false) : int
	{
		if(empty($references))
		{
			throw new DBException("Datenbankfehler: cleanupTable braucht Referenzen.");
		}

		$conditionParts = [];

		foreach($references as $ref)
		{
			$conditionParts[] = "NOT EXISTS (
					SELECT 1 FROM `".$this->getImportTableName($ref[1])."` a
					WHERE a.dataset_id = :datasetId
					AND a.`".self::EXCLUDED_COLUMN_NAME."` IS FALSE
					AND a.`".$ref[2]."` = `".$this->getImportTableName($tableName)."`.`".$ref[0]."`
					LIMIT 1
				)";
		}

		$glue = " AND ";
		if($useOR)
		{
			$glue = " OR ";
		}
		$condition = join($glue, $conditionParts);

		return $this->exclude($datasetId, $tableName, $reason, $condition, []);
	}

	public function cleanupDataWithoutReference(string $tableName) : int
	{
		return $this->execute("
			DELETE FROM `$tableName`
			WHERE dataset_id NOT IN (SELECT dataset_id FROM datasets);
		");
	}

	public function deleteData(string $tableName, int $datasetId, string $condition = "1=1", array $params = [], int $chunkedDelete = 0) : int
	{
		if(empty($condition))
		{
			throw new DBException("Datenbankfehler: Kann nicht ohne Bedingung löschen.");
		}
		if(!$this->tableExists($tableName))
		{
			return 0;
		}

		$rowCount = 0;
		$params[":datasetId"] = $datasetId;
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

		return $rowCount;
	}

	public function getImportTableName(string $tableName) : string
	{
		return $tableName."_import";
	}
	public function createImportTable(string $tableName) : string
	{
		$importTableName = $this->getImportTableName($tableName);
		$this->execute("CREATE TABLE IF NOT EXISTS `$importTableName` LIKE `$tableName`");
		$this->execute("ALTER TABLE `$importTableName`
						ADD COLUMN IF NOT EXISTS `".self::EXCLUDED_COLUMN_NAME."` BOOLEAN NOT NULL DEFAULT 0,
						ADD COLUMN IF NOT EXISTS `".self::EXCLUSION_COLUMN_NAME."` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
						ADD INDEX IF NOT EXISTS `".self::EXCLUDED_COLUMN_NAME."` (`".self::EXCLUDED_COLUMN_NAME."`)");
		return $importTableName;
	}
	public function tableExists(string $tableName) : bool
	{
		$rowCount = $this->execute("SHOW TABLE STATUS WHERE NAME LIKE :tableName", [":tableName" => $tableName]);
		return $rowCount > 0;
	}

	public function exclude(int $datasetId, string $tableName, string $reason, string $condition = "1=1", array $params = []) : int
	{
		$importTableName = $this->getImportTableName($tableName);

		$params[":datasetId"] = $datasetId;
		$params[":reason"] = $reason;
		$sql = "UPDATE `$importTableName`
				SET `".self::EXCLUDED_COLUMN_NAME."` = TRUE,
					`".self::EXCLUSION_COLUMN_NAME."` = :reason
				WHERE `dataset_id` = :datasetId
				AND `".self::EXCLUDED_COLUMN_NAME."` IS FALSE
				AND (
					$condition
				)";

		$this->logQuery($sql, $params);
		$rowCount = $this->execute($sql, $params);
		return $rowCount;
	}

	public function copyFromImportTable(int $datasetId, string $tableName, array $columns = []) : int
	{
		$importTableName = $this->getImportTableName($tableName);
		$columns[] = "dataset_id";
		$sql = "INSERT INTO `$tableName` (`".join("`, `", $columns)."`)
				SELECT `".join("`, `", $columns)."`
				FROM `$importTableName`
				WHERE dataset_id = :dataset_id
				AND `".self::EXCLUDED_COLUMN_NAME."` IS FALSE";
		$this->logQuery($sql, [":dataset_id" => $datasetId]);
		$rowCount = $this->execute($sql);
		return $rowCount;
	}
}
