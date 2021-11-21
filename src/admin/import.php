<?php
	require_once("../include/global.inc.php");
	require_once("../include/import.inc.php");
	require_once("../include/HtmlHelper.class.php");

	function printResult(array $result) : string
	{
		if(!$result)
		{
			return "";
		}
		switch($result["type"])
		{
			case "log":
				return "<pre>".$result["msg"]."</pre>";
			case "error":
				$str = "<p class='error'>".$result["msg"]."</p>";
				if(isset($result["exception"]))
				{
					$str .= "<pre>".$result["exception"]->getLongMessage()."</pre>";
				}
				return $str;
			default:
				return "<p>".$result["msg"]."</p>";
		}
	}

	$db = getDBReadWriteHandler();
	$importer = getGTFSImporter($db);
	$files = $importer->getImportFiles();
	$result = [];

	$chosenFile = null;
	if(isset($_POST["name"]) && isset($_POST["file"]))
	{
		try
		{
			if(isset($files[$_POST["file"]]))
			{
				$chosenFile = $files[$_POST["file"]];
			}
			else
			{
				$result[] = ["type" => "error", "msg" => "Datei nicht gefunden"];
			}

			if(empty(trim($_POST["name"])))
			{
				$result[] = ["type" => "error", "msg" => "Kein Name angegeben"];
			}
			elseif(!$importer->isDatasetNameAvailable(trim($_POST["name"])))
			{
				$result[] = ["type" => "error", "msg" => "Name bereits vorhanden"];
			}
			elseif(isset($_POST["reference_date"]) && !empty($_POST["reference_date"]) && !preg_match($db::DATE_REGEX, $_POST["reference_date"]))
			{
				$result[] = ["type" => "error", "msg" => "Datum nicht gültig, ggf. löschen"];
			}
			elseif($chosenFile !== null)
			{
				$license = "";
				if(isset($_POST["license"]))
				{
					$license = trim($_POST["license"]);
				}
				$desc = "";
				if(isset($_POST["desc"]))
				{
					$desc = trim($_POST["desc"]);
				}
				$referenceDate = null;
				if(isset($_POST["reference_date"]) && !empty($_POST["reference_date"]))
				{
					$referenceDate = $_POST["reference_date"];
				}

				import($importer, trim($_POST["name"]), $license, $referenceDate, $desc, $chosenFile);
			}
		}
		catch(ImportException $e)
		{
			$result[] = ["type" => "error", "msg" => "Fehler beim Import, Log beachten", "exception" => $e];
		}
	}

	$datasets = [];
	try
	{
		$datasets = $db->getDatasets(true);
	}
	catch(DBException $e)
	{
		$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Datasets", "exception" => $e];
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Fahrplan-DB - Import</title>
		<script src="https://kit.fontawesome.com/d3175d0b40.js" crossorigin="anonymous"></script>
		<link rel="stylesheet" type="text/css" href="../style.css" />
		<script type="text/javascript" src="../script.js"></script>
		<script type="text/javascript">
			const deleteDataset = async (self, datasetId) => {
				getAjax(self, "deleteDataset", datasetId);
			}

			const clearCache = async (self) => {
				getAjax(self, "clearCache");
			}

			const getAjax = async (button, action, datasetId) => {
				setButtonProgress(button, "in Arbeit...");

				const body = new FormData();
				body.append("action", action);
				if(datasetId !== undefined) {
					body.append("dataset", datasetId);
				}

				const response = await fetch("import_ajax.php", {
					method: "POST",
					body,
				});

				try {
					const result = await response.clone().json();

					if(result.result) {
						setButtonDone(button, result.result);
						return;
					}

					setButtonError(button, result.error, result.exception);
				}
				catch(error) {
					setButtonError(button, "Interner Fehler (vermutlich der Datenbank).");
					console.log("Fehler beim fetch", await response.text());
					throw error;
				}
			}
		</script>
	</head>
	<body>
		<h1>Fahrplan-DB - Import</h1>
		<?= HtmlHelper::resultBlock($result); ?>

		<h3>Vorhandene Datasets (<a href="">aktualisieren</a>)</h3>
		<table class="data datasets">
			<tr>
				<th>ID</th>
				<th>Name</th>
				<th>Datum Export</th>
				<th>Datum Import</th>
				<th>Beschreibung</th>
				<th>Erste Fahrt</th>
				<th>Letzte Fahrt</th>
				<th>Anzahl<br>VUs</th>
				<th>Anzahl<br>Verkehrstage</th>
				<th>Anzahl<br>Tagesausnahmen</th>
				<th>Anzahl<br>Routen</th>
				<th>Anzahl<br>Halte</th>
				<th>Anzahl<br>Haltezeiten</th>
				<th>Anzahl<br>Fahrten</th>
				<th></th>
			</tr>
			<?php if($datasets) { foreach($datasets as $dataset) { ?>
				<tr>
					<td><?= $dataset["dataset_id"] ?></td>
					<td><a href="../halte.php?dataset=<?= $dataset["dataset_id"] ?>"><?= $dataset["dataset_name"] ?></a></td>
					<td><?= $dataset["reference_date"] ?></td>
					<td><?= $dataset["import_time"] ?></td>
					<td class="pre"><?= $dataset["desc"] ?></td>
					<td><?= $dataset["start_date"] ? $dataset["start_date"] : "" ?></td>
					<td><?= $dataset["end_date"] ? $dataset["end_date"] : "" ?></td>
					<td><?= $dataset["counts"]["agency"] ?></td>
					<td><?= $dataset["counts"]["calendar"] ?></td>
					<td><?= $dataset["counts"]["calendar_dates"] ?></td>
					<td><?= $dataset["counts"]["routes"] ?></td>
					<td><?= $dataset["counts"]["stops"] ?></td>
					<td><?= $dataset["counts"]["stop_times"] ?></td>
					<td><?= $dataset["counts"]["trips"] ?></td>
					<td>
						<button onclick="deleteDataset(this, <?= $dataset["dataset_id"] ?>)">löschen</button>
					</td>
				</tr>
			<?php }} else { ?>
				<tr><td colspan="15" style="text-align: center">Keine Datasets</td></tr>
			<?php } ?>
		</table>

		<div class="import-new">
			<h3>Neuer Import (Dataset)</h3>
			<p>
				Wähle eine ZIP-Datei aus. In der ZIP-Datei müssen direkt die txt-Dateien enthalten sein, kein Unterordner.
			</p>
			<form method="POST" action="">
				<label>Name:
					<input type="text" name="name" value="<?= isset($_POST["name"]) ? $_POST["name"] : "" ?>">
				</label>
				<label>Datei:
					<select name="file">
						<?php foreach($files as $fileName => $file) { ?>
						<option <?= ($chosenFile && $chosenFile->getFilename() == $fileName) ? "selected" : "" ?>
							value='<?= $fileName ?>'><?= $fileName ?></option>
						<?php } ?>
					</select>
				</label>
				<label>Lizenz:
					<textarea name="license" rows="1" cols="30"><?= isset($_POST["license"]) ? $_POST["license"] : "" ?></textarea>
				</label>
				<label>Datum des Exports (optional):
					<input type="date" name="reference_date" value="<?= isset($_POST["reference_date"]) ? $_POST["reference_date"] : "" ?>">
				</label>
				<label>Beschreibung (optional):
					<textarea name="desc" rows="1" cols="30"><?= isset($_POST["desc"]) ? $_POST["desc"] : "" ?></textarea>
				</label>
				<div class="button"><button type="submit">Import</button></div>
			</form>
			<div class="button">
				<button onclick="clearCache(this)">Cache aufräumen</button>
			</div>
		</div>
	</body>
</html>
