<?php

require_once("db/DBReadWriteHandler.class.php");
require_once("import/GTFSImporter.class.php");
require_once("GTFSConstants.class.php");

define("LOG_DIR", "/var/import/logs");
define("IMPORT_DIR", "/var/import/files");
define("EXTRACT_DIR", "/var/import/cache");

function getDBReadWriteHandler() : DBReadWriteHandler
{
	try
	{
		return new DBReadWriteHandler();
	}
	catch(DBException $e)
	{
		die("Datenbankverbindung nicht m&ouml;glich!\n<!-- ".$e->getLongMessage()." -->");
	}
}
function getGTFSImporter(DBReadWriteHandler $db) : GTFSImporter
{
	$importer = new GTFSImporter($db, IMPORT_DIR, EXTRACT_DIR, LOG_DIR);
	$importer->enableQueryLogger(true);
	return $importer;
}

function import(GTFSImporter $importer, string $datasetName, string $license, ?string $referenceDate, string $desc, SplFileInfo $file) : void
{
	$importer->addDataset(trim($_POST["name"]), $license, $referenceDate, $desc);
	$importer->extractZipFile($file);
	$importer->importFiles();
	runDataCleanup($importer);
	$importer->finish(true);
}

function runDataCleanup(GTFSImporter $importer) : void
{
	set_time_limit(30);
	$importer->removeRouteTypeClasses([GTFSConstants::ROUTE_TYPE_CLASS_BUS, GTFSConstants::ROUTE_TYPE_CLASS_OTHER]);
	set_time_limit(30);
	$importer->removeUnusedData();
	set_time_limit(30);
	$importer->markParentStops();
	$importer->setDatasetDates();
	$importer->finish(true);
}
