<?php

require_once("ZipImporter.class.php");
require_once("GTFSFiles.class.php");

class GTFSImporter extends ZipImporter
{
	public const GTFS_FILES = ["agency", "calendar", "calendar_dates", "stops", "routes", "trips", "stop_times"];


	function __construct(DBHandler $db, string $importPath, string $cachePath, string $logPath)
	{
		parent::__construct($db, $importPath, $cachePath, $logPath);
	}

	public function setDatasetId(int $datasetId) : GTFSImporter
	{
		$this->datasetId = $datasetId;
		return $this;
	}

	public function copyDataset(string $name, bool $includeImportTables = false) : ZipImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Keine datasetId gesetzt.");
		}
		$oldDatasetId = $this->datasetId;

		try
		{
			$this->datasetId = $this->db->duplicateDataset($oldDatasetId, $name);

			if($this->datasetId === null)
			{
				$this->abort("Dataset $name wurde nicht angelegt.");
			}
			$this->log("Neues Dataset $name angelegt, id ".$this->datasetId.".");

			foreach(self::GTFS_FILES as $file)
			{
				$fileOptions = GTFSFiles::getFileOptions($file);
				$tableName = $fileOptions->getTableName();
				$importTableName = $this->db->getImportTableName($tableName);
				$fields = $fileOptions->getFields();

				$this->log("Kopiere $tableName...");
				$count = $this->db->copyDatasetData($oldDatasetId, $this->datasetId, $tableName, $fields);
				$this->log("Fertig, $count Einträge.");

				if($includeImportTables && $importTableName)
				{
					$this->log("Kopiere $importTableName...");
					$count = $this->db->copyDatasetData($oldDatasetId, $this->datasetId, $importTableName, $fields);
					$this->log("Fertig, $count Einträge.");
				}
			}

			$this->log("Dataset $name kopiert, id ".$this->datasetId.".");
			return $this;
		}
		catch(DBException $e)
		{
			$this->abort("Fehler beim Kopieren von datasetId $oldDatasetId.", $e);
		}
	}

	public static function getImportType() : string
	{
		return "GTFS";
	}

	public function doImportFiles(?array $files) : ZipImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		if($files === null)
		{
			$files = self::GTFS_FILES;
		}

		$this->log("Importiere folgende Dateien: [".join(", ", $files)."]");

		set_time_limit(120);

		foreach($files as $file)
		{
			$fileOptions = GTFSFiles::getFileOptions($file);
			$this->db->createImportTable($fileOptions->getTableName());

			if(!$this->doesFileExist($file))
			{
				if(!$fileOptions->isOptional())
				{
					throw new ImportException("Datei $file fehlt im Export!");
				}
				$this->log("Datei $file übersprungen, Datei nicht vorhanden.");
				continue;
			}

			$this->importLoadFile($fileOptions);
		}

		return $this;
	}

	public function removeRouteTypeClasses(array $routeTypeClasses) : GTFSImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		foreach($routeTypeClasses as $class)
		{
			if(!GTFSConstants::isRouteTypeClass($class, GTFSConstants::ROUTE_TYPE_CLASSES))
			{
				$this->abort("Falsche Klasse angegeben, nichts markiert");
			}
			$routeTypes = GTFSConstants::getRouteTypesByClass($class);

			try
			{
				foreach($routeTypes as $routeTypeId => $routeType)
				{
					$this->log("Lösche route type ".$routeType[0]." ($routeTypeId)");
					$condition = "route_type = :routeType";
					$params = [
						":routeType" => $routeTypeId,
					];
					$count = $this->db->exclude("routes", $this->datasetId, "route type class ".$class, $condition, $params);
					$this->log("$count Routen markiert.");
				}
			}
			catch(DBException $e)
			{
				$this->abort("Datenbankfehler, breche ab.", $e);
			}
		}
		return $this;
	}

	public function markParentStops() : GTFSImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		$this->log("Markiere Stationen, die Unterstationen haben...");
		$count = $this->db->updateParentStops($this->datasetId);
		$this->log("$count stops markiert.");
		return $this;
	}

	public function setDatasetDates() : GTFSImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		$this->log("Schreibe Startdatum und Enddatum für Dataset ...");
		try
		{
			$count = $this->db->setDatasetDates($this->datasetId);
		}
		catch(DBException $e)
		{
			$this->abort("Datenbankfehler beim Schreiben des Start/Enddatum des Datasets.", $e);
		}
		$this->log("$count Dataset geändert.");
		return $this;
	}

	public function removeUnusedData() : GTFSImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		try
		{
			$this->log("Lösche ungültige routes...");
			$count = $this->db->cleanupTable("routes", $this->datasetId, "routes cleanup", [["agency_id", "agency", "agency_id"]]);
			$this->log("$count ungültige routes markiert.");

			$this->log("Lösche ungültige trips...");
			$count = $this->db->cleanupTable("trips", $this->datasetId, "trips cleanup", [["route_id", "routes", "route_id"]]);
			$this->log("$count ungültige trips markiert.");

			$this->log("Lösche ungültige stop_times...");
			$count = $this->db->cleanupTable("stop_times", $this->datasetId, "stop_times cleanup", [["trip_id", "trips", "trip_id"], ["stop_id", "stops", "stop_id"]], true);
			$this->log("$count gültige stop_times behalten.");

			$this->log("Lösche unbenutzte stops...");
			$count = $this->db->cleanupTable("stops", $this->datasetId, "stops cleanup reverse", [["stop_id", "stop_times", "stop_id"], ["stop_id", "stops", "parent_station"]]);
			$this->log("$count unbenutzte stops markiert.");

			$this->log("Lösche unbenutzte trips...");
			$count = $this->db->cleanupTable("trips", $this->datasetId, "trips cleanup reverse", [["trip_id", "stop_times", "trip_id"]]);
			$this->log("$count unbenutzte trips markiert.");

			$this->log("Lösche unbenutzte routes...");
			$count = $this->db->cleanupTable("routes", $this->datasetId, "routes cleanup reverse", [["route_id", "trips", "route_id"]]);
			$this->log("$count unbenutzte routes markiert.");

			$this->log("Lösche unbenutzte Kalenderdaten...");
			$count = $this->db->cleanupTable("calendar", $this->datasetId, "calendar cleanup reverse", [["service_id", "trips", "service_id"]]);
			$this->log("$count unbenutzte agencies markiert.");

			$this->log("Lösche unbenutzte Kalenderausnahmen...");
			$count = $this->db->cleanupTable("calendar_dates", $this->datasetId, "calendar_dates cleanup reverse", [["service_id", "trips", "service_id"]]);
			$this->log("$count unbenutzte Kalenderausnahmen markiert.");

			$this->log("Lösche unbenutzte agencies...");
			$count = $this->db->cleanupTable("agency", $this->datasetId, "agencies cleanup reverse", [["agency_id", "routes", "agency_id"]]);
			$this->log("$count unbenutzte agencies markiert.");
		}
		catch(DBException $e)
		{
			$this->abort("Datenbankfehler beim Löschen unbenutzter Daten.", $e);
		}

		return $this;
	}

	public function copyImportData(?array $files = null) : GTFSImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		if($files === null)
		{
			$files = self::GTFS_FILES;
		}

		foreach($files as $file)
		{
			$fileOptions = GTFSFiles::getFileOptions($file);
			if(!$this->doesFileExist($file))
			{
				continue;
			}

			try
			{
				$tableName = $fileOptions->getTableName();
				$columns = array_merge($fileOptions->getMandatoryFields(), $fileOptions->getOptionalFields());

				$importCount = $this->db->getTableCount($this->db->getImportTableName($tableName), $this->datasetId);
				if($importCount === null || $importCount == 0)
				{
					$this->log("Keine vorhandenen Einträge für $tableName gefunden, wird übersprungen.");
				}
				else
				{
					$this->log("Übernehme $tableName...");
					$count = $this->db->copyFromImportTable($tableName, $columns);
					$this->log("$count von $importCount Einträgen aus $tableName übernommen.");
				}
			}
			catch(DBException $e)
			{
				$this->abort("Datenbankfehler beim Übernehmen der Daten.", $e);
			}
		}

		return $this;
	}

	protected function importLoadFile(GTFSFileOptions $fileOptions) : void
	{
		$fileName = $fileOptions->getFileName();
		$this->log("Importiere $fileName als lokale Datei...");

		$header = $this->getCSVHeader($fileName);
		$columns = [];
		$sets = ["dataset_id" => ":datasetId"];
		$params = [":datasetId" => $this->datasetId];
		$i = 0;

		foreach($header as $field)
		{
			$i++;
			if($fileOptions->isMandatoryField($field))
			{
				if($fileOptions->markMandatoryFieldAsFound($field))
				{
					$columns[] = $field;
				}
				else
				{
					$this->log("$fileName enthält Spalte $field doppelt, wird ignoriert.");
					$columns[] = "@ignore".$i;
				}
			}
			elseif($fileOptions->isOptionalField($field))
			{
				if($fileOptions->markOptionalFieldAsFound($field))
				{
					$columns[] = "@".$field;
					$sets[$field] = "IF(@$field <> '', @$field, :$field)";
					$params[":$field"] = $fileOptions->getDefaultForField($field);
				}
				else
				{
					$this->log("$fileName enthält Spalte $field doppelt, wird ignoriert.");
					$columns[] = "@ignore".$i;
				}
			}
			else
			{
				$this->log("$fileName enthält unbekannte Spalte $field, wird ignoriert.");
				$columns[] = "@ignore".$i;
			}
		}

		$missing = $fileOptions->getMissingMandatoryFields();
		if(!empty($missing))
		{
		$this->abort("$fileName enthält Spalten [".join(", ", $missing)."] nicht, breche ab.");
		}

		try
		{
			$filePath = realpath($this->getFilePath($fileName));
			$eol = $this->detectEOL($filePath);
			$importTableName = $this->db->getImportTableName($fileOptions->getTableName());
			$this->db->loadData($filePath, $eol, $importTableName, $columns, $sets, $params);
		}
		catch(DBException $e)
		{
			$this->abort("Datenbankfehler beim Lesen von $fileName", $e);
		}

		$this->log("Importieren von $fileName abgeschlossen.");
	}
}