<?php

require_once("db/DBReadWriteHandler.class.php");
require_once("import/GTFSImporter.class.php");
require_once("GTFSConstants.class.php");

function getGTFSImporter(DBReadWriteHandler $db) : GTFSImporter
{
	require("config.php");
	$importPaths = $config["importPaths"];

	$importer = new GTFSImporter($db, $importPaths["IMPORT_DIR"], $importPaths["EXTRACT_DIR"], $importPaths["LOG_DIR"]);
	$importer->enableQueryLogger(true);
	return $importer;
}

function import(GTFSImporter $importer, string $datasetName, string $license, ?string $referenceDate, string $desc,
	SplFileInfo $file, ?string $runUntil) : void
{
	$importer->addDataset(trim($_POST["name"]), $license, $referenceDate, $desc);
	$importer->extractZipFile($file);
	$importer->importFiles();
	
	if($runUntil === "filesRead")
	{
		$importer->finish(false);
		return;
	}
	
	$importer->removeExtractedFiles();
	$importer->removeRouteTypeClasses([GTFSConstants::ROUTE_TYPE_CLASS_BUS, GTFSConstants::ROUTE_TYPE_CLASS_OTHER]);
	$importer->removeUnusedData();
	$importer->markParentStops();
	$importer->setDatasetDates();
	
	if($runUntil === "filtered")
	{
		$importer->finish();
		return;
	}

	$importer->copyImportData();
	$importer->finish();
}
