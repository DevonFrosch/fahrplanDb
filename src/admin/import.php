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
	$importer = getGTFSImporter($db, "import");
	$files = $importer->getImportFiles();
	$result = [];

	$chosenFile = null;
	if(isset($_POST["action"]) && $_POST["action"] === "new"
		&& isset($_POST["name"]) && isset($_POST["file"]))
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
			elseif(!isset($_POST["runUntil"]) || !GTFSConstants::isImportState($_POST["runUntil"]))
			{
				$result[] = ["type" => "error", "msg" => "Laufoption nicht gültig."];
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

				$importer->startImport(trim($_POST["name"]), $license, $referenceDate, $desc, $chosenFile, $_POST["runUntil"]);
			}
		}
		catch(ImportException $e)
		{
			$result[] = ["type" => "error", "msg" => "Fehler beim Import, Log beachten", "exception" => $e];
		}
	}

	if(isset($_POST["action"]) && $_POST["action"] === "resume"
		&& isset($_POST["datasetId"]) && is_numeric($_POST["datasetId"]))
	{
		try
		{
			if(!isset($_POST["runUntil"]) || !GTFSConstants::isImportState($_POST["runUntil"]))
			{
				$result[] = ["type" => "error", "msg" => "Laufoption nicht gültig."];
			}
			else
			{
				$importer->resumeImport($_POST["datasetId"], $_POST["runUntil"]);
			}
		}
		catch(ImportException $e)
		{
			$result[] = ["type" => "error", "msg" => "Fehler beim Import, Log beachten", "exception" => $e];
		}
	}

	if(isset($_POST["action"]) && $_POST["action"] === "copyDataset"
		&& isset($_POST["datasetId"]) && is_numeric($_POST["datasetId"]) && isset($_POST["name"]))
	{
		try
		{
			if(empty(trim($_POST["name"])))
			{
				$result[] = ["type" => "error", "msg" => "Kein Name angegeben"];
			}
			elseif(!$importer->isDatasetNameAvailable(trim($_POST["name"])))
			{
				$result[] = ["type" => "error", "msg" => "Name bereits vorhanden"];
			}
			else
			{
				$importer->setDatasetId($_POST["datasetId"]);
				$importer->copyDataset($_POST["name"], isset($_POST["includeImportTables"]));
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
		$datasets = $db->getDatasets();
	}
	catch(DBException $e)
	{
		$result[] = ["type" => "error", "msg" => "Fehler beim Holen der Datasets", "exception" => $e];
	}

	$resumableDatasets = array_filter($datasets, function($dataset) {
		return $dataset["import_state"] !== GTFSConstants::IMPORT_STATE_COMPLETE;
	});
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
				getAjax(self, "deleteDataset", { datasetId });
			}

			const clearCache = async (self) => {
				getAjax(self, "clearCache");
			}

			const getAjax = async (button, action, data) => {
				setButtonProgress(button, "in Arbeit...");

				const body = new FormData();
				body.append("action", action);
				if(data !== undefined) {
					for(let key of Object.keys(data)) {
						body.append(key, data[key]);
					}
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

			const openLog = (datasetId) => {
				window.open("logs.php?datasetId="+datasetId, "logs", "popup");
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
				<th>Import-Status</th>
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
					<td><?= GTFSConstants::IMPORT_STATES[$dataset["import_state"]] ?></td>
					<td>
						<button onclick="deleteDataset(this, <?= $dataset["dataset_id"] ?>)">löschen</button>
						<button onclick="openLog(<?= $dataset["dataset_id"] ?>)">Log</button>
					</td>
				</tr>
			<?php }} else { ?>
				<tr><td colspan="15" style="text-align: center">Keine Datasets</td></tr>
			<?php } ?>
		</table>

		<div class="actions">
			<div class="import-new">
				<h3>Neuer Import (Dataset)</h3>
				<p>
					Wähle eine ZIP-Datei aus. In der ZIP-Datei müssen direkt die txt-Dateien enthalten sein, kein Unterordner.
				</p>
				<form method="POST" action="" name="new" class="import-form">
					<input type="hidden" name="action" value="new">
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

					<p>Laufoptionen:</p>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_FILES_READ ?>" />Daten einlesen</label>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_FILTERED ?>" />Daten einlesen + filtern</label>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_REFINED ?>" />Daten einlesen + filtern + verarbeiten</label>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_APPLIED ?>" checked />Daten einlesen + filtern + verarbeiten + übernehmen</label>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_COMPLETE ?>" checked />Daten einlesen + filtern + verarbeiten + übernehmen + aufräumen</label>

					<div class="button">
						<button type="submit" onclick="setButtonProgress(this, 'wird ausgeführt...')">Import starten</button>
					</div>
				</form>
			</div>
			<div>
				<h3>Datenübernahme</h3>
				<?php if(!empty($resumableDatasets)) { ?>
				<form method="POST" action="" name="resume" class="import-form">
					<input type="hidden" name="action" value="resume">

					<select name="datasetId">
						<?php foreach($resumableDatasets as $dataset) { ?>
						<option value="<?= $dataset["dataset_id"] ?>">
							<?= $dataset["dataset_name"] ?> (<?= $dataset["dataset_id"] ?>)
							(Status: <?= GTFSConstants::IMPORT_STATES[$dataset["import_state"]] ?>)
						</option>
						<?php } ?>
					</select>

					<p>Laufoptionen:</p>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_FILTERED ?>" />Daten filtern</label>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_REFINED ?>" />Daten filtern + verarbeiten</label>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_APPLIED ?>" checked />Daten filtern + verarbeiten + übernehmen</label>
					<label><input type="radio" name="runUntil" value="<?= GTFSConstants::IMPORT_STATE_COMPLETE ?>" checked />Daten filtern + verarbeiten + übernehmen + aufräumen</label>

					<div class="button">
						<button type="submit" onclick="setButtonProgress(this, 'wird ausgeführt...')">Import wiederaufnehmen</button>
					</div>
				</form>
				<?php } else { ?>
				<p>Keine angefangenen Importe vorhanden</p>
				<?php } ?>

				<hr>

				<h3>Datensatz kopieren</h3>
				<?php if(!empty($datasets)) { ?>

				<form method="POST" action="" name="copy-dataset" class="import-form">
					<input type="hidden" name="action" value="copyDataset">
					<label>
						Alter Datensatz:
						<select name="datasetId">
							<?php foreach($datasets as $dataset) { ?>
							<option value="<?= $dataset["dataset_id"] ?>">
								<?= $dataset["dataset_name"] ?> (<?= $dataset["dataset_id"] ?>)
							</option>
							<?php } ?>
						</select>
					</label>
					<label>
						Neuer Name:
						<input type="text" name="name" />
					</label>
					<label>
						<input type="checkbox" name="includeImportTables" />
						Auch mit temporären Import-Tabellen
					</label>

					<div class="button">
						<button type="submit" onclick="setButtonProgress(this, 'wird ausgeführt...')">Kopieren</button>
					</div>
				</form>
				<?php } else { ?>
				<p>Keine Datensätze vorhanden</p>
				<?php } ?>

				<hr>

				<h3>Wartung</h3>
				<div class="button">
					<button onclick="clearCache(this)">Cache aufräumen</button>
				</div>
			</div>
		</div>
	</body>
</html>
