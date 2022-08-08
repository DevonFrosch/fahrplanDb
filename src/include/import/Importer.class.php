<?php

require_once("ImportException.class.php");
require_once("CSVReader.class.php");

abstract class Importer
{
	protected $db = null;
	protected $logPath = null;

	protected $logFile = null;

	protected $isAborted = false;
	protected $isFinished = false;

	protected $datasetId = null;

	function __construct(DBHandler $db, string $logPath)
	{
		$this->db = $db;
		$this->logPath = $logPath;

		$this->logFile = $logPath."/".date("Y-m-d_His_").$this->getImportType().".log";
	}
	public abstract static function getImportType() : string;

	public function enableQueryLogger(bool $withParams = false) : void
	{
		$this->db->setQueryLogger(function(string $query, ?array $params = null, ?string $additionalInfo = null) use($withParams) {
			$this->log("  SQL: $query");

			if($withParams && $params !== null)
			{
				$this->log("  SQL-Params: ".json_encode($params));
			}
			if($additionalInfo !== null)
			{
				$this->log("  ".$additionalInfo);
			}
		});
	}
	public function getLogPath() :  string
	{
		return $this->logFile;
	}
	// Programmfluss
	public function finish() : void
	{
		if($this->isFinished)
		{
			return;
		}
		$this->isFinished = true;
		$this->log("Import beendet.");
	}

	public function hasLog() : bool
	{
		return is_file($this->logFile);
	}
	public function printLog() : void
	{
		if(is_file($this->logFile))
		{
			readfile($this->logFile);
		}
	}

	protected function log(string $message) : void
	{
		$message = date("[Y-m-d H:i:s] ") . $message;
		file_put_contents($this->logFile, $message.PHP_EOL, FILE_APPEND);
	}
	protected function abort(?string $message = null, ?DBException $exception = null) : void
	{
		$this->isAborted = true;
		if($message !== null)
		{
			$this->log($message);
		}
		if($exception !== null)
		{
			if(method_exists($exception, "getLongMessage"))
			{
				$this->log($exception->getLongMessage());
			}
			else
			{
				$this->log($exception);
			}
		}
		throw new ImportException($message, $exception);
	}
	protected function isRunning() : bool
	{
		return !$this->isAborted && !$this->isFinished;
	}

	public function isDatasetNameAvailable(string $name) : bool
	{
		$datasets = $this->db->getDatasets();
		foreach($datasets as $dataset)
		{
			if($dataset["dataset_name"] == $name)
			{
				return false;
			}
		}
		return true;
	}

	public function addDataset(string $name, string $license, ?string $referenceDate = null, string $desc = "") : ZipImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		try
		{
			$this->db->addDataset($name, $license, $referenceDate, $desc);
			$this->setDatasetId($this->db->lastInsertId());

			if($this->datasetId === null)
			{
				$this->abort("Dataset $name wurde nicht angelegt.");
			}

			$this->log("Dataset $name angelegt, id ".$this->datasetId.".");
			return $this;
		}
		catch(DBException $e)
		{
			$this->abort("Fehler beim Anlegen von Dataset $name.", $e);
		}
	}

	function deleteDependendData() : void
	{
		// Wird von Unterklassen überschrieben
		return;
	}

	public function deleteDataset() : ZipImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		if($this->datasetId === null)
		{
			$this->abort("Kein Dataset gewählt");
		}
		try
		{
			$this->log("Lösche Dataset ".$this->datasetId."...");

			$this->deleteDependendData();

			$this->log("Lösche datasets...");
			$this->db->deleteData("datasets", $this->datasetId);
			$this->log("Dataset ".$this->datasetId." gelöscht.");
			$this->finish();
			return $this;
		}
		catch(DBException $e)
		{
			$this->abort("Fehler beim Löschen von Dataset ".$this->datasetId.".", $e);
		}
	}

	public function getDatasetId() : ?int
	{
		return $this->datasetId;
	}
	public function setDatasetId(int $datasetId)
	{
		$this->datasetId = $datasetId;
		try
		{
			$this->db->setLastLogFile($this->datasetId, $this->logFile);
		}
		catch(PDOException $e)
		{
			// Fehler ist nicht kritisch
			$this->log("Datenbankfehler beim Setzten des Pfads der Logdatei");
		}
	}

	public function getImportState() : string
	{
		return $this->db->getImportState($this->datasetId);
	}
	public function setImportState(string $importState) : void
	{
		$this->db->setImportState($this->datasetId, $importState);
	}
}
