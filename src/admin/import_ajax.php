<?php
	header("Content-Type: application/json");
	
	require_once("../include/global.inc.php");
	require_once("../include/import.inc.php");
	require_once("../include/import/ImportException.class.php");
	
	function action() : array
	{
		if(!isset($_REQUEST["action"]))
		{
			return ["error" => "Keine Aktion gewählt."];
		}
		
		$db = getDBReadWriteHandler();
		$importer = getGTFSImporter($db);
		
		switch($_REQUEST["action"])
		{
			case "deleteDataset":
				if(!isset($_REQUEST["dataset"]) || !is_numeric($_REQUEST["dataset"]))
				{
					return ["error" => "Aktion nicht erkannt."];
				}
				try
				{
					$importer->setDatasetId($_REQUEST["dataset"]);
					$importer->deleteDataset();
					return ["result" => "Dataset gelöscht."];
				}
				catch(DBException $e)
				{
					return ["error" => "Fehler beim Löschen.", "exception" => $e->getLongMessage()];
				}
				catch(ImportException $e)
				{
					return ["error" => "Fehler beim Löschen.", "exception" => $e->getLongMessage()];
				}
			case "cleanupDataset":
				if(!isset($_REQUEST["dataset"]) || !is_numeric($_REQUEST["dataset"]))
				{
					return ["error" => "Aktion nicht erkannt."];
				}
				try
				{
					$importer->setDatasetId($_REQUEST["dataset"]);
					runDataCleanup($importer);
					
					return ["result" => "Aufräumen ausgeführt."];
				}
				catch(DBException $e)
				{
					return ["error" => "Fehler beim Aufräumen.", "exception" => $e->getLongMessage()];
				}
				catch(ImportException $e)
				{
					return ["error" => "Fehler beim Löschen.", "exception" => $e->getLongMessage()];
				}
				
			case "clearCache":
				try
				{
					$importer->clearAll();
					return ["result" => "Cache gelöscht."];
				}
				catch(DBException $e)
				{
					return ["error" => "Fehler beim Löschen des Cache.", "exception" => $e->getLongMessage()];
				}
				catch(ImportException $e)
				{
					return ["error" => "Fehler beim Löschen.", "exception" => $e->getLongMessage()];
				}
			default:
				return ["error" => "Aktion nicht erkannt."];
		}
	}
	
	echo json_encode(action());
	
