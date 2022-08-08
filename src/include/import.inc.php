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
