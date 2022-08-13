<?php
	require_once("include/global.inc.php");
	require_once("include/HtmlHelper.class.php");
	require_once("include/GTFSConstants.class.php");

	$datasetId = HtmlHelper::getChosenDatasetId();
	$marginDates = [];
	$result = [];
	$stop = [];
	$stopTimes = [];

	$stopId = HtmlHelper::getStringParameter("stop");
	$filters = [
		"stop" => $stopId,
		"date" => HtmlHelper::getStringParameter("date"),
		"short_name" => HtmlHelper::getStringParameter("short_name"),
		"headsign" => HtmlHelper::getStringParameter("headsign"),
		"agency_name" => HtmlHelper::getStringParameter("agency_name"),
		"route_short_name" => HtmlHelper::getStringParameter("route_short_name"),
	];

	$db = getGTFSDBHandler();

	if($stopId !== null)
	{
		try
		{
			$stop = $db->getStop($datasetId, $stopId);
		}
		catch(DBException $e)
		{
			$result[] = ["type" => "error", "msg" => "Fehler beim Holen des Haltes", "exception" => $e];
		}

		try
		{
			$stopTimes = $db->getStopTimesStop($datasetId, $stopId, $filters);

			if(sizeof($stopTimes) > $db::MAX_ROWS)
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
		<title>
			Abfahrten
			<?= $stop ? " in ".$stop["stop_name"] : "" ?>
		</title>
		<link rel="stylesheet" type="text/css" href="./style.css" />
	</head>
	<body>
		<h1>
			Abfahrten
			<?= $stop ? " in ".$stop["stop_name"] : "" ?>
		</h1>
		<div class="addBottomMargin">
			<a href="index.php">Datensätze</a>&nbsp;/
			<?= HtmlHelper::getLink("halte", "Halte") ?>&nbsp;/
			<?= HtmlHelper::getLink("linien", "Linien") ?>
		</div>
		<?= HtmlHelper::resultBlock($result); ?>

		<?= HtmlHelper::dateSelect($marginDates, $filters) ?>
		<?= HtmlHelper::textFilter("short_name", "Zugnummer", $filters) ?>
		<?= HtmlHelper::textFilter("headsign", "Ziel", $filters) ?>
		<?= HtmlHelper::textFilter("agency_name", "Betreiber", $filters) ?>
		<?= HtmlHelper::textFilter("route_short_name", "Linie", $filters) ?>

		<?php if($stop) { ?>
		<?= HtmlHelper::stopHtml($stop) ?>

		<table class="data">
			<thead>
				<tr>
					<th>Fahrt-ID</th>
					<th>Zugnummer</th>
					<th>Linie</th>
					<th>Ziel</th>
					<th>an</th>
					<th>ab</th>
					<th>Betreiber</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($stopTimes as $stopTime) { ?>
				<tr>
					<td><?= HtmlHelper::getLink("fahrt", $stopTime["trip_id"], ["trip" => $stopTime["trip_id"]]) ?></td>
					<td><?= $stopTime["trip_short_name"] ?></td>
					<td><?= $stopTime["route_short_name"] ?></td>
					<td><?= $stopTime["trip_headsign"] ?></td>
					<td><?= $stopTime["arrival_time"] ?></td>
					<td><?= $stopTime["departure_time"] ?></td>
					<td><?= $stopTime["agency_name"] ?></td>
				</tr>
				<?php } if(empty($stopTimes)) { ?>
					<tr><td colspan="8" class="nodata">Keine Halte</td></tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } else { ?>
		<p>Halt nicht gefunden.</p>
		<?php } ?>
	</body>
</html>
