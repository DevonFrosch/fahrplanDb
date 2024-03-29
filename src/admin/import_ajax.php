<?php
	header("Content-Type: application/json");

	require_once("../include/global.inc.php");
	require_once("../include/import/ImportException.class.php");

	function action() : array
	{
		if(!isset($_REQUEST["action"]))
		{
			return ["error" => "Keine Aktion gewählt."];
		}

		$db = getGTFSDBHandler();
		$importer = getGTFSImporter($db, "ajax");

		switch($_REQUEST["action"])
		{
			case "deleteDataset":
				if(!isset($_REQUEST["datasetId"]) || !is_numeric($_REQUEST["datasetId"]))
				{
					return ["error" => "datasetId nicht gesetzt."];
				}
				try
				{
					$importer->setDatasetId($_REQUEST["datasetId"]);
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
			case "clearDataWithoutDataset":
				try
				{
					$importer->clearDataWithoutDataset();
					return ["result" => "Daten ohne Datensatz gelöscht."];
				}
				catch(DBException $e)
				{
					return ["error" => "Fehler beim Löschen von Daten ohne Datensatz.", "exception" => $e->getLongMessage()];
				}
				catch(ImportException $e)
				{
					return ["error" => "Fehler beim Löschen von Daten ohne Datensatz.", "exception" => $e->getLongMessage()];
				}
			case "optimizeImportTables":
				try
				{
					$importer->optimizeImportTables();
					return ["result" => "Import-Tabellen optimiert."];
				}
				catch(DBException $e)
				{
					return ["error" => "Fehler beim Optimieren der Import-Tabellen.", "exception" => $e->getLongMessage()];
				}
				catch(ImportException $e)
				{
					return ["error" => "Fehler beim Optimieren der Import-Tabellen.", "exception" => $e->getLongMessage()];
				}
			default:
				return ["error" => "Aktion nicht erkannt."];
		}
	}

	echo json_encode(action());

