<?php
	require_once("include/global.inc.php");
	require_once("include/HtmlHelper.class.php");
	require_once("include/GTFSConstants.class.php");

	$datasetId = HtmlHelper::getChosenDatasetId();
	$marginDates = [];
	$result = [];
	$trip = [];
	$stopTimes = [];

	$tripId = HtmlHelper::getStringParameter("trip");
	$filters = [
		"trip" => $tripId,
		"date" => HtmlHelper::getStringParameter("date"),
	];

	$db = getGTFSDBHandler();

	if($tripId !== null)
	{
		try
		{
			$trip = $db->getTrip($datasetId, $tripId);
		}
		catch(DBException $e)
		{
			$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Fahrt", "exception" => $e];
		}

		try
		{
			$stopTimes = $db->getStopTimesTrip($datasetId, $tripId);

			if(sizeof($stopTimes) > $db::MAX_ROWS)
			{
				$result[] = ["type" => "info", "msg" => "Zu viele Datensätze, zeige die ersten ".$db::MAX_ROWS];
			}
		}
		catch(DBException $e)
		{
			$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Halte", "exception" => $e];
		}
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>
			Fahrplan Zug
			<?= $trip ? $trip["trip_short_name"] + 0 : "" ?>
			<?= $trip ? "nach ".$trip["trip_headsign"] : "" ?>
		</title>
		<link rel="stylesheet" type="text/css" href="./style.css" />
	</head>
	<body>
		<h1>
			Fahrplan Zug
			<?= $trip ? $trip["trip_short_name"] + 0 : "" ?>
			<?= $trip ? "nach ".$trip["trip_headsign"] : "" ?>
		</h1>
		<div class="addBottomMargin">
			<a href="index.php">Datensätze</a>&nbsp;/
			<?= HtmlHelper::getLink("halte", "Halte") ?>&nbsp;/
			<?= HtmlHelper::getLink("linien", "Linien") ?>
		</div>
		<?= HtmlHelper::resultBlock($result); ?>

		<?= HtmlHelper::dateSelect($marginDates, $filters) ?>

		<?php if($trip) { ?>
		<table class="data addBottomMargin">
			<tbody>
				<tr>
					<th>Fahrt-ID</th>
					<td><?= $trip["trip_id"] ?></td>
				</tr>
				<tr>
					<th>Zugnummer</th>
					<td><?= $trip["trip_short_name"] + 0 ?></td>
				</tr>
				<tr>
					<th>Ziel</th>
					<td><?= $trip["trip_headsign"] ?></td>
				</tr>
				<tr>
					<th>Linie</th>
					<td><?= $trip["route_short_name"] ?></td>
				</tr>
				<tr>
					<th>Verkehrsmittel</th>
					<td><?= GTFSConstants::getRouteTypeName($trip["route_type"]) ?></td>
				</tr>
				<tr>
					<th>Betreiber</th>
					<td><?= $trip["agency_name"] ?></td>
				</tr>
				<tr>
					<th>Betreiber-ID</th>
					<td><?= $trip["agency_id"] ?></td>
				</tr>
			</tbody>
		</table>

		<table class="data">
			<thead>
				<tr>
					<th>Nr</th>
					<th>Haltestelle / Bahnhof</th>
					<th>Gleis</th>
					<th>an</th>
					<th>ab</th>
					<th>Hinweis</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($stopTimes as $stopTime) { ?>
				<tr>
					<td><?= $stopTime["stop_sequence"] ?></td>
					<td><?= HtmlHelper::getLink("abfahrten", $stopTime["stop_name"], ["stop" => $stopTime["stop_id"]]) ?></td>
					<td><?= isset($stopTime["platform_code"]) ? $stopTime["platform_code"] : "" ?></td>
					<td><?= $stopTime["arrival_time"] ?></td>
					<td><?= $stopTime["departure_time"] ?></td>
					<td>
						<?php if($stopTime["pickup_type"]) { ?><span class="entryType" title="Kein Einstieg">E</span><?php } ?>
						<?php if($stopTime["drop_off_type"]) { ?><span class="entryType" title="Kein Ausstieg">A</span><?php } ?>
					</td>
				</tr>
				<?php } if(empty($stopTimes)) { ?>
					<tr><td colspan="6" class="nodata">Keine Halte</td></tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } else { ?>
		<p>Fahrt nicht gefunden.</p>
		<?php } ?>
	</body>
</html>
