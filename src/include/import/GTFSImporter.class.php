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
	
	public function removeRouteTypeClasses(array $routeTypeClass) : GTFSImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Falsche Reihenfolge, kein Dataset angelegt.");
		}
		
		foreach($routeTypeClass as $class)
		{
			if(!GTFSConstants::isRouteTypeClass($class, GTFSConstants::ROUTE_TYPE_CLASSES))
			{
				$this->abort("Falsche Klasse angegeben, nichts gelöscht");
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
					$count = $this->db->deleteData("routes", $this->datasetId, $condition, $params);
					$this->log("$count Routen gelöscht.");
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
			$batchSize = 1e4;
			
			$this->log("Lösche ungültige routes...");
			$count = $this->db->cleanupTable("routes", $this->datasetId, [["agency_id", "agency", "agency_id"]], $batchSize);
			$this->log("$count ungültige routes gelöscht.");
			
			$this->log("Lösche ungültige trips...");
			$count = $this->db->cleanupTable("trips", $this->datasetId, [["route_id", "routes", "route_id"]], $batchSize);
			$this->log("$count ungültige trips gelöscht.");
			
			$this->log("Lösche ungültige stop_times...");
			$count = $this->db->cleanupTable("stop_times", $this->datasetId, [["trip_id", "trips", "trip_id"], ["stop_id", "stops", "stop_id"]], $batchSize, true);
			$this->log("$count gültige stop_times behalten.");
			
			$this->log("Lösche unbenutzte stops...");
			$count = $this->db->cleanupTable("stops", $this->datasetId, [["stop_id", "stop_times", "stop_id"], ["stop_id", "stops", "parent_station"]], $batchSize);
			$this->log("$count unbenutzte stops gelöscht.");
			
			$this->log("Lösche unbenutzte trips...");
			$count = $this->db->cleanupTable("trips", $this->datasetId, [["trip_id", "stop_times", "trip_id"]], $batchSize);
			$this->log("$count unbenutzte trips gelöscht.");
			
			$this->log("Lösche unbenutzte routes...");
			$count = $this->db->cleanupTable("routes", $this->datasetId, [["route_id", "trips", "route_id"]], $batchSize);
			$this->log("$count unbenutzte routes gelöscht.");
			
			$this->log("Lösche unbenutzte Kalenderdaten...");
			$count = $this->db->cleanupTable("calendar", $this->datasetId, [["service_id", "trips", "service_id"]], $batchSize);
			$this->log("$count unbenutzte agencies gelöscht.");
			
			$this->log("Lösche unbenutzte Kalenderausnahmen...");
			$count = $this->db->cleanupTable("calendar_dates", $this->datasetId, [["service_id", "trips", "service_id"]], $batchSize);
			$this->log("$count unbenutzte Kalenderausnahmen gelöscht.");
			
			$this->log("Lösche unbenutzte agencies...");
			$count = $this->db->cleanupTable("agency", $this->datasetId, [["agency_id", "routes", "agency_id"]]);
			$this->log("$count unbenutzte agencies gelöscht.");
		}
		catch(DBException $e)
		{
			$this->abort("Datenbankfehler beim Löschen unbenutzter Daten.", $e);
		}
		
		return $this;
	}
	
	public function setDatasetId(int $datasetId)
	{
		$this->datasetId = $datasetId;
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
			
			$importTableName = $this->db->createImportTable($fileOptions->getTableName());
			$this->db->loadData($filePath, $eol, $importTableName, $columns, $sets, $params);
		}
		catch(DBException $e)
		{
			$this->abort("Datenbankfehler beim Lesen von $fileName", $e);
		}
		
		$this->log("Importieren von $fileName abgeschlossen.");
	}
}