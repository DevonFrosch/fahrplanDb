<?php
	require_once("include/global.inc.php");
	require_once("include/HtmlHelper.class.php");
	require_once("include/db/DBReadHandler.class.php");

	$datasetId = HtmlHelper::getChosenDatasetId();
	$result = [];
	$stop = [];
	$stops = [];

	$stopId = HtmlHelper::getStringParameter("stop");
	$nameFilter = HtmlHelper::getStringParameter("name");
	$date = HtmlHelper::getStringParameter("date");
	$filterEmpty = HtmlHelper::getCheckboxParameter("filterEmpty");

	$db = getDBReadWriteHandler();

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
	}

	try
	{
		$stops = $db->getStops($datasetId, $stopId, $nameFilter, $date, $filterEmpty);

		if(sizeof($stops) > $db::MAX_ROWS)
		{
			$result[] = ["type" => "info", "msg" => "Zu viele Datensätze, zeige die ersten ".$db::MAX_ROWS];
		}
	}
	catch(DBException $e)
	{
		$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Halte", "exception" => $e];
	}

	try
	{
		$marginDates = $db->getMarginDates($datasetId);
	}
	catch(DBException $e)
	{
		$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Daten für das Dataset", "exception" => $e];
	}

	$additionalParameters = [
		"stop" => $stopId,
		"name" => $nameFilter,
		"filterEmpty" => $filterEmpty
	];
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Halte</title>
		<link rel="stylesheet" type="text/css" href="./style.css" />
	</head>
	<body>
		<h1>Halte</h1>
		<div class="addBottomMargin">
			<a href="index.php">Datensätze</a>&nbsp;/
			<?= HtmlHelper::getLink("halte", "Halte") ?>&nbsp;/
			<?= HtmlHelper::getLink("linien", "Linien") ?>
		</div>
		<?= HtmlHelper::resultBlock($result); ?>

		<?= HtmlHelper::stopHtml($stop) ?>
		<?= HtmlHelper::dateSelect($marginDates, $additionalParameters) ?>
		<?= HtmlHelper::nameFilter($additionalParameters) ?>
		<?= HtmlHelper::checkboxFilter("filterEmpty", "Filtere Halte ohne Daten", $additionalParameters) ?>

		<table class="data">
			<thead>
				<tr>
					<th>ID</th>
					<th>Code</th>
					<th>Name</th>
					<th>Gleis</th>
					<th>Typ</th>
					<th>Übergeordneter Halt</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($stops as $stop) { ?>
				<tr>
					<td><?= $stop["stop_id"] ?></td>
					<td><?= $stop["stop_code"] ?></td>
					<td><?= $stop["stop_name"] ?></td>
					<td><?= $stop["platform_code"] ?></td>
					<td><?= GTFSConstants::getLocationTypeName($stop["location_type"]) ?></td>
					<td><?= $stop["parent_station"] ?></td>
					<td>
						<?php
							if($stop["stop_time_count"] > 0)
							{
								echo HtmlHelper::getLink("abfahrten", $stop["stop_time_count"]." Abfahrten", ["stop" => $stop["stop_id"]]);
							}
						?>
						<?php
							if($stop["children_count"] > 0)
							{
								echo HtmlHelper::getLink("halte", $stop["children_count"]." Bahnsteige", ["stop" => $stop["stop_id"]]);
							}
						?>
					</td>
				</tr>
				<?php } if(empty($stops)) { ?>
				<tr><td colspan="6" class="nodata">Keine Halte</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</body>
</html>
