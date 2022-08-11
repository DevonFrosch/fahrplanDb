<?php

require_once("ZipImporter.class.php");
require_once("GTFSFiles.class.php");

class GTFSImporter extends ZipImporter
{
	var $runUntil = null;

	public function __construct(GTFSDBHandler $db, string $importPath, string $cachePath, string $logPath)
	{
		parent::__construct($db, $importPath, $cachePath, $logPath);
	}

	private function setRunUntil(string $runUntil) : void
	{
		if($runUntil === null)
		{
			$this->runUntil = null;
			return;
		}
		if(!GTFSConstants::isImportState($runUntil))
		{
			$this->abort("shouldStop: Invalider runUntil '".$runUntil."'");
			return;
		}
		$this->runUntil = $runUntil;
	}

	public function startImport(string $datasetName, string $license, ?string $referenceDate, string $desc,
	SplFileInfo $file, ?string $runUntil) : GTFSImporter
	{
		$this->log("Starte Import für neuen Datensatz $datasetName");
		$this->addDataset(trim($_POST["name"]), $license, $referenceDate, $desc);
		$this->setRunUntil($runUntil);
		return $this->stepReadFiles($file);
	}
	public function resumeImport(int $datasetId, ?string $runUntil) : GTFSImporter
	{
		$this->setDatasetId($datasetId);
		$this->setRunUntil($runUntil);
		$importState = $this->getImportState();

		$this->log("Nehme Import für Datensatz $datasetId wieder auf, Status ist $importState");

		switch($importState)
		{
			case GTFSConstants::IMPORT_STATE_INIT:
				return $this->stepReadFiles();
			case GTFSConstants::IMPORT_STATE_FILES_READ:
				return $this->stepFilter();
			case GTFSConstants::IMPORT_STATE_FILTERED:
				return $this->stepRefine();
			case GTFSConstants::IMPORT_STATE_REFINED:
				return $this->stepApply();
			case GTFSConstants::IMPORT_STATE_APPLIED:
				return $this->stepFinish();
		}
		$this->finish();
		return $this;
	}
	private function shouldStop(string $currentState) : bool
	{
		if(!GTFSConstants::isImportState($currentState))
		{
			$this->abort("shouldStop: Invalider currentState '$currentState'");
			return true;
		}
		if($this->runUntil === null)
		{
			$this->log("shouldStop: null");
			return false;
		}

		return GTFSConstants::hasReachedState($currentState, $this->runUntil);
	}

	private function stepReadFiles(SplFileInfo $file) : GTFSImporter
	{
		if($this->shouldStop(GTFSConstants::IMPORT_STATE_INIT))
		{
			$this->finish(false);
			return $this;
		}

		$this->extractZipFile($file);
		$this->importFiles();
		$this->setImportState(GTFSConstants::IMPORT_STATE_FILES_READ);

		return $this->stepFilter();
	}
	private function stepFilter() : GTFSImporter
	{
		if($this->shouldStop(GTFSConstants::IMPORT_STATE_FILES_READ))
		{
			$this->finish(false);
			return $this;
		}

		$this->removeExtractedFiles();
		$this->removeRouteTypeClasses([GTFSConstants::ROUTE_TYPE_CLASS_BUS, GTFSConstants::ROUTE_TYPE_CLASS_OTHER]);
		$this->removeUnusedData();
		$this->setImportState(GTFSConstants::IMPORT_STATE_FILTERED);

		return $this->stepRefine();
	}
	private function stepRefine() : GTFSImporter
	{
		if($this->shouldStop(GTFSConstants::IMPORT_STATE_FILTERED))
		{
			$this->finish();
			return $this;
		}

		$this->markParentStops();
		$this->setDatasetDates();
		$this->setImportState(GTFSConstants::IMPORT_STATE_REFINED);

		return $this->stepFinish();
	}
	private function stepApply() : GTFSImporter
	{
		if($this->shouldStop(GTFSConstants::IMPORT_STATE_REFINED))
		{
			$this->finish();
			return $this;
		}

		$this->copyImportData();
		$this->setImportState(GTFSConstants::IMPORT_STATE_APPLIED);
		return $this;
	}
	private function stepFinish() : GTFSImporter
	{
		if($this->shouldStop(GTFSConstants::IMPORT_STATE_APPLIED))
		{
			$this->finish();
			return $this;
		}

		$this->removeTempData();
		$this->setImportState(GTFSConstants::IMPORT_STATE_COMPLETE);
		$this->finish();
		return $this;
	}

	// --------------- Arbeit ---------------
	public function setDatasetId(int $datasetId) : GTFSImporter
	{
		parent::setDatasetId($datasetId);
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
			parent::setDatasetId($this->db->duplicateDataset($oldDatasetId, $name));

			if($this->datasetId === null)
			{
				$this->abort("Dataset $name wurde nicht angelegt.");
			}
			$this->log("Neues Dataset $name angelegt, id ".$this->datasetId.".");

			foreach(GTFSFiles::FILES as $file)
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
					$fields[] = "importExclusion";
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
			$files = GTFSFiles::FILES;
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
					$count = $this->db->exclude($this->datasetId, "routes", "route type class ".$class, $condition, $params);
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

	protected function markParentStops() : GTFSImporter
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

	protected function setDatasetDates() : GTFSImporter
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

	protected function removeUnusedData(int $runCount = 0) : GTFSImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		$totalCount = 0;
		try
		{
			$this->log("Lösche ungültige routes...");
			$count = $this->db->cleanupTable("routes", $this->datasetId, "routes cleanup $runCount", [["agency_id", "agency", "agency_id"]]);
			$this->log("$count ungültige routes markiert.");
			$totalCount += $count;

			$this->log("Lösche ungültige trips...");
			$count = $this->db->cleanupTable("trips", $this->datasetId, "trips cleanup $runCount", [["route_id", "routes", "route_id"]]);
			$this->log("$count ungültige trips markiert.");
			$totalCount += $count;

			$this->log("Lösche ungültige stop_times...");
			$count = $this->db->cleanupTable("stop_times", $this->datasetId, "stop_times cleanup $runCount", [["trip_id", "trips", "trip_id"], ["stop_id", "stops", "stop_id"]], true);
			$this->log("$count ungültige stop_times markiert.");
			$totalCount += $count;

			$this->log("Lösche unbenutzte stops...");
			$count = $this->db->cleanupTable("stops", $this->datasetId, "stops cleanup reverse $runCount", [["stop_id", "stop_times", "stop_id"], ["stop_id", "stops", "parent_station"]]);
			$this->log("$count unbenutzte stops markiert.");
			$totalCount += $count;

			$this->log("Lösche unbenutzte trips...");
			$count = $this->db->cleanupTable("trips", $this->datasetId, "trips cleanup reverse $runCount", [["trip_id", "stop_times", "trip_id"]]);
			$this->log("$count unbenutzte trips markiert.");
			$totalCount += $count;

			$this->log("Lösche unbenutzte routes...");
			$count = $this->db->cleanupTable("routes", $this->datasetId, "routes cleanup reverse $runCount", [["route_id", "trips", "route_id"]]);
			$this->log("$count unbenutzte routes markiert.");
			$totalCount += $count;

			$this->log("Lösche unbenutzte Kalenderdaten...");
			$count = $this->db->cleanupTable("calendar", $this->datasetId, "calendar cleanup reverse $runCount", [["service_id", "trips", "service_id"]]);
			$this->log("$count unbenutzte agencies markiert.");
			$totalCount += $count;

			$this->log("Lösche unbenutzte Kalenderausnahmen...");
			$count = $this->db->cleanupTable("calendar_dates", $this->datasetId, "calendar_dates cleanup reverse $runCount", [["service_id", "trips", "service_id"]]);
			$this->log("$count unbenutzte Kalenderausnahmen markiert.");
			$totalCount += $count;

			$this->log("Lösche unbenutzte agencies...");
			$count = $this->db->cleanupTable("agency", $this->datasetId, "agencies cleanup reverse $runCount", [["agency_id", "routes", "agency_id"]]);
			$this->log("$count unbenutzte agencies markiert.");
			$totalCount += $count;
		}
		catch(DBException $e)
		{
			$this->abort("Datenbankfehler beim Löschen unbenutzter Daten.", $e);
		}

		if($totalCount <= 0)
		{
			$this->log("Keine Änderung nach Aufräumen, fertig.");
			return $this;
		}

		$runCount++;
		$maxCleanupRuns= 10;
		if($runCount > $maxCleanupRuns)
		{
			$this->log("Aufräumen von Daten: Maximale Rekursionstiefe erreicht ($maxCleanupRuns)");
		}
		else
		{
			$this->removeUnusedData($runCount);
		}
		return $this;
	}

	public function copyImportData(?array $files = null) : GTFSImporter
	{
		if(!$this->isRunning())
		{
			$this->log("not running");
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		if($files === null)
		{
			$files = GTFSFiles::FILES;
		}

		$this->log("files: ".json_encode($files));

		foreach($files as $file)
		{
			$fileOptions = GTFSFiles::getFileOptions($file);

			try
			{
				$tableName = $fileOptions->getTableName();
				$importTableName = $this->db->getImportTableName($tableName);
				$columns = array_merge($fileOptions->getMandatoryFields(), $fileOptions->getOptionalFields());

				$importCount = $this->db->getTableCount($importTableName, $this->datasetId);
				if($importCount === null || $importCount == 0)
				{
					$this->log("Keine vorhandenen Einträge für $tableName gefunden, wird übersprungen.");
				}
				else
				{
					$this->log("Übernehme $tableName...");
					$count = $this->db->copyFromImportTable($tableName, $columns);
					$percent = round($importCount !== 0 ? $count / $importCount : 0, 2);
					$this->log("$count von $importCount Einträgen aus $tableName übernommen ($percent%).");
				}
			}
			catch(DBException $e)
			{
				$this->abort("Datenbankfehler beim Übernehmen der Daten.", $e);
			}
		}

		return $this;
	}

	public function clearDataWithoutDataset() : GTFSImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}

		try
		{
			$this->log("Lösche Daten ohne Datensatz");

			foreach(GTFSFiles::FILES as $file)
			{
				$fileOptions = GTFSFiles::getFileOptions($file);
				$tableName = $fileOptions->getTableName();
				$importTableName = $this->db->getImportTableName($tableName);

				$this->log("Räume $tableName auf...");
				$count = $this->db->cleanupDataWithoutReference($tableName);
				$this->log("Fertig, $count Einträge.");

				if($importTableName)
				{
					$this->log("Räume $importTableName auf...");
					$count = $this->db->cleanupDataWithoutReference($importTableName);
					$this->log("Fertig, $count Einträge.");
				}
			}

			return $this;
		}
		catch(DBException $e)
		{
			$this->abort("Fehler beim Kopieren von datasetId $oldDatasetId.", $e);
		}
	}

	protected function removeTempData() : GTFSImporter
	{
		if(!$this->isRunning())
		{
			$this->log("not running");
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}

		$this->log("files: ".json_encode($files));

		foreach(GTFSFiles::FILES as $file)
		{
			$fileOptions = GTFSFiles::getFileOptions($file);

			try
			{
				$tableName = $fileOptions->getTableName();
				$importTableName = $this->db->getImportTableName($tableName);

				$this->log("Lösche temporäre Daten aus $importTableName...");
				$this->db->deleteData($importTableName, $this->datasetId);
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

	function deleteDependendData() : void
	{
		foreach(GTFSFiles::getTables() as $tableName)
		{
			$this->log("Lösche $tableName...");
			$this->db->deleteData($tableName, $this->datasetId);

			$importTableName = $this->db->getImportTableName($tableName);
			$this->log("Lösche $importTableName...");
			$this->db->deleteData($importTableName, $this->datasetId);
		}
	}
}