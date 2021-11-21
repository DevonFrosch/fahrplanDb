<?php
	require_once("include/global.inc.php");
	require_once("include/HtmlHelper.class.php");
	require_once("include/db/DBReadHandler.class.php");
	require_once("include/GTFSConstants.class.php");

	$datasetId = HtmlHelper::getChosenDatasetId();
	$marginDates = [];
	$result = [];
	$route = [];
	$trips = [];

	$routeId = HtmlHelper::getStringParameter("route");
	$date = HtmlHelper::getStringParameter("date");
	$direction = HtmlHelper::getStringParameter("direction", "");

	$db = getDBReadWriteHandler();

	if($routeId !== null)
	{
		try
		{
			$route = $db->getRoute($datasetId, $routeId);
		}
		catch(DBException $e)
		{
			$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Route", "exception" => $e];
		}

		try
		{
			$trips = $db->getTrips($datasetId, $routeId, $date, $direction);

			if(sizeof($trips) > $db::MAX_ROWS)
			{
				$result[] = ["type" => "info", "msg" => "Zu viele Datensätze, zeige die ersten ".$db::MAX_ROWS];
			}
		}
		catch(DBException $e)
		{
			$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Fahrten", "exception" => $e];
		}
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
		<title>Linie <?= $route ? $route["route_name"] : "" ?></title>
		<link rel="stylesheet" type="text/css" href="./style.css" />
	</head>
	<body>
		<h1>Linie <?= $route ? $route["route_name"] : "" ?></h1>
		<div class="addBottomMargin">
			<a href="index.php">Datensätze</a>&nbsp;/
			<?= HtmlHelper::getLink("halte", "Halte") ?>&nbsp;/
			<?= HtmlHelper::getLink("linien", "Linien") ?>
		</div>
		<?= HtmlHelper::resultBlock($result) ?>

		<?= HtmlHelper::dateSelect($marginDates, ["route" => $routeId]) ?>

		<?php if($route) { ?>
		<table class="data addBottomMargin">
			<tbody>
				<tr>
					<th>Linien-ID</th>
					<td><?= $route["route_id"] ?></td>
				</tr>
				<?php if(isset($route["route_short_name"])) { ?>
				<tr>
					<th>Name</th>
					<td><?= $route["route_short_name"] ?></td>
				</tr>
				<?php } ?>
				<?php if(isset($route["route_long_name"])) { ?>
				<tr>
					<th>Langname</th>
					<td><?= $route["route_long_name"] ?></td>
				</tr>
				<?php } ?>
				<tr>
					<th>Verkehrsmittel</th>
					<td><?= GTFSConstants::getRouteTypeName($route["route_type"]) ?></td>
				</tr>
				<tr>
					<th>Betreiber</th>
					<td><?= $route["agency_name"] ?></td>
				</tr>
				<tr>
					<th>Betreiber-ID</th>
					<td><?= $route["agency_id"] ?></td>
				</tr>
			</tbody>
		</table>

		<table class="data">
			<thead>
				<tr>
					<th>ID</th>
					<th>Ziel</th>
					<th>Zugnummer</th>
					<th>von</th>
					<th>ab</th>
					<th>nach</th>
					<th>an</th>
				</tr>
			</thead>

			<tbody>
				<?php foreach($trips as $trip) { ?>
				<tr>
					<td><?= HtmlHelper::getLink("fahrt", $trip["trip_id"], ["trip" => $trip["trip_id"]]) ?></td>
					<td><?= $trip["trip_headsign"] ?></td>
					<td><?= $trip["trip_short_name"] ?></td>
					<td><?= $trip["start"] . (isset($trip["start_platform_code"]) ? " ".$trip["start_platform_code"] : "") ?></td>
					<td><?= $trip["departure_time"] ?></td>
					<td><?= $trip["dest"] . (isset($trip["dest_platform_code"]) ? " ".$trip["dest_platform_code"] : "") ?></td>
					<td><?= $trip["arrival_time"] ?></td>
				</tr>
				<?php } if(empty($trips)) { ?>
					<tr><td colspan="8" class="nodata">Keine Fahrten</td></tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } else { ?>
		<p>Fahrt nicht gefunden.</p>
		<?php } ?>
	</body>
</html>
