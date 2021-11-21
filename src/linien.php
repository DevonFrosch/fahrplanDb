<?php
	require_once("include/HtmlHelper.class.php");
	require_once("include/db/DBReadHandler.class.php");
	include_once("include/GTFSConstants.class.php");
	
	$datasetId = HtmlHelper::getChosenDatasetId();
	$marginDates = [];
	$result = [];
	$agencies = [];
	$routes = [];
	
	$agencyId = HtmlHelper::getStringParameter("agency");
	$date = HtmlHelper::getStringParameter("date");
	
	$db = null;
	try
	{
		$db = new DBReadHandler();
	}
	catch(DBException $e)
	{
		die("Datenbankverbindung nicht m&ouml;glich!\n<!-- ".$e->getLongMessage()." -->");
	}
	
	try
	{
		$agencies = $db->getAgencies($datasetId);
	}
	catch(DBException $e)
	{
		$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Verkehrsunternehmen", "exception" => $e];
	}
	
	array_unshift($agencies, ["agency_id" => "", "agency_name" => "-- Keine --"]);
	
	try
	{
		$routes = $db->getRoutes($datasetId, $agencyId, $date);
		
		if(sizeof($routes) > $db::MAX_ROWS)
		{
			$result[] = ["type" => "info", "msg" => "Zu viele Datensätze, zeige die ersten ".$db::MAX_ROWS];
		}
	}
	catch(DBException $e)
	{
		$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Linien", "exception" => $e];
	}
	
	try
	{
		$marginDates = $db->getMarginDates($datasetId);
	}
	catch(DBException $e)
	{
		$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Daten für das Dataset", "exception" => $e];
	}
	
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Linien</title>
		<link rel="stylesheet" type="text/css" href="./style.css" />
	</head>
	<body>
		<h1>Linien</h1>
		<div class="addBottomMargin">
			<a href="index.php">Datensätze</a>&nbsp;/
			<?= HtmlHelper::getLink("halte", "Halte") ?>&nbsp;/
			<?= HtmlHelper::getLink("linien", "Linien") ?>
		</div>
		<?= HtmlHelper::resultBlock($result); ?>
		
		<?= HtmlHelper::dateSelect($marginDates, ["agency" => $agencyId]) ?>
		
		<div class="addBottomMargin">
			<form action="" method="GET">
				<?= HtmlHelper::getHiddenParams() ?>
				<select name="agency">
					<?php foreach($agencies as $agency) { ?>
					<option value="<?= $agency["agency_id"] ?>" <?= ($agencyId == $agency["agency_id"]) ? "selected" : "" ?>>
						<?= $agency["agency_name"] ?>
					</option>
					<?php } ?>
				</select>
				<button type="submit">filtern</button>
			</form>
		</div>
		<table class="data">
			<thead>
				<tr>
					<th>ID</th>
					<th>Name</th>
					<th>Langname</th>
					<th>Verkehrsmittel</th>
					<th>Betreiber</th>
					<th>Nur Richtung</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($routes as $route) { ?>
				<tr>
					<td><?= HtmlHelper::getLink("fahrten", $route["route_id"], ["route" => $route["route_id"]]) ?></td>
					<td><?= $route["route_short_name"] ? $route["route_short_name"] : "" ?></td>
					<td><?= $route["route_long_name"] ? $route["route_long_name"] : "" ?></td>
					<td><?= GTFSConstants::getRouteTypeName($route["route_type"]) ?></td>
					<td><?= $route["agency_name"] ?></td>
					<td>
						<?= HtmlHelper::getLink("fahrten", "hin", ["route" => $route["route_id"], "direction" => "0"]) ?> /
						<?= HtmlHelper::getLink("fahrten", "rück", ["route" => $route["route_id"], "direction" => "1"]) ?>
					</td>
				</tr>
				<?php } if(empty($routes)) { ?>
					<tr><td colspan="6" class="nodata">Keine Linien</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</body>
</html>
