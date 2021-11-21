<?php
	require_once("include/global.inc.php");
	require_once("include/HtmlHelper.class.php");
	require_once("include/db/DBReadHandler.class.php");

	$db = getDBReadWriteHandler();

	$result = [];
	$datasets = [];
	try
	{
		$datasets = $db->getDatasets();
	}
	catch(DBException $e)
	{
		$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Datasets", "exception" => $e];
	}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Fahrplan-Datenbank</title>
		<script src="https://kit.fontawesome.com/d3175d0b40.js" crossorigin="anonymous"></script>
		<link rel="stylesheet" type="text/css" href="./style.css" />
	</head>
	<body>
		<h1>Fahrplan-Datenbank</h1>
		<?= HtmlHelper::resultBlock($result); ?>

		<p>
			Hier werden Fahrplandaten für den StellwerkSim gesammelt. Die Daten sind in verschiedene Datensätze aufgeteilt - zum Beispiel pro Land. Soweit möglich ist dem Datensatz die Lizenz der Rohdaten beigelegt.
		</p>
		<h3>Datensätze</h3>
		<table class="data datasets">
			<tr>
				<th>Name</th>
				<th>Datum Export</th>
				<th>Lizenz</th>
				<th>Beschreibung</th>
				<th>Erste Fahrt</th>
				<th>Letzte Fahrt</th>
				<th></th>
			</tr>
			<?php foreach($datasets as $dataset) { ?>
				<tr>
					<td><?= $dataset["dataset_name"] ?></td>
					<td><?= $dataset["reference_date"] ?></td>
					<td><?= $dataset["license"] ?></td>
					<td class="pre"><?= $dataset["desc"] ?></td>
					<td><?= $dataset["start_date"] ? $dataset["start_date"] : "" ?></td>
					<td><?= $dataset["end_date"] ? $dataset["end_date"] : "" ?></td>
					<td>
						<?= HtmlHelper::getLink("halte", "Halte", ["dataset" => $dataset["dataset_id"]]) ?>&nbsp;/
						<?= HtmlHelper::getLink("linien",  "Linien", ["dataset" => $dataset["dataset_id"]]) ?>
					</td>
				</tr>
			<?php } if(empty($datasets)) { ?>
				<tr><td colspan="6" class="nodata">Keine Datensätze</td></tr>
			<?php } ?>
		</table>
	</body>
</html>
