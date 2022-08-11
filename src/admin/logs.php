<?php
	require_once("../include/global.inc.php");

	if(!isset($_GET["datasetId"]) || !is_numeric($_GET["datasetId"]))
	{
		die("Falsche ID");
	}

	$db = getGTFSDBHandler();
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
		<script type="text/javascript">
			let timer = null;

			const onLoad = () => {
				document.querySelector('body').scrollIntoView(false);
				toggleTimer();
			}

			const toggleTimer = () => {
				const checkbox = document.getElementById("reload");
				if(timer !== null) {
					clearTimeout(timer);
					timer = null;
				}
				else {
					timer = setTimeout(() => window.location.reload(), 10 * 1000);
				}
				document.getElementById("reload").checked = (timer !== null);
				return false;
			}
		</script>
	</head>
	<body onload="onLoad()">
		<pre><?php readfile($path); ?></pre>
		<label><input type="checkbox" onclick="toggleTimer()" id="reload"> Automatischer Reload</label>
	</body>
</html>
