<?php
	require_once("../include/global.inc.php");

	if(!isset($_GET["datasetId"]) || !is_numeric($_GET["datasetId"]))
	{
		die("Falsche ID");
	}

	$db = getDBReadWriteHandler();
	$path = $db->getLastLogPath($_GET["datasetId"]);

	if($path === null || !file_exists($path))
	{
		die("Kein Log gefunden");
	}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Log <?= basename($path); ?></title>
		<link rel="stylesheet" type="text/css" href="../style.css" />
		<meta http-equiv="refresh" content="10" />
		<style type="text/css">
			body, html {
				margin: 0;
				padding: 0;
			}
			pre {
				width: calc(100% - 20px);
				padding: 10px;
				white-space: pre-wrap;
				overflow: auto;
			}
		</style>
	</head>
	<body onload="document.querySelector('body').scrollIntoView(false);">
		<pre><?php readfile($path); ?></pre>
	</body>
</html>
