<?php

require_once("db/DBReadWriteHandler.class.php");

function getDBReadWriteHandler() : DBReadWriteHandler
{
	try
	{
		require("config.php");
		$dbpwd = $config["dbpwd"];
		return new DBReadWriteHandler($dbpwd);
	}
	catch(DBException $e)
	{
		die("Datenbankverbindung nicht m&ouml;glich!\n<!-- ".$e->getLongMessage()." -->");
	}
}
