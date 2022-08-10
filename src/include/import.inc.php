<?php

require_once("db/GTFSDBHandler.class.php");
require_once("import/GTFSImporter.class.php");
require_once("GTFSConstants.class.php");

function getGTFSImporter(GTFSDBHandler $db, ?string $importDesc = null) : GTFSImporter
{
	require("config.php");
	$importPaths = $config["importPaths"];

	$importer = new GTFSImporter($db, $importPaths["IMPORT_DIR"], $importPaths["EXTRACT_DIR"], $importPaths["LOG_DIR"], $importDesc);
	$importer->enableQueryLogger(true);
	return $importer;
}
