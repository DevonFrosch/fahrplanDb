<?php

require_once("import/Importer.class.php");
include_once("GTFSConstants.class.php");

class HtmlHelper
{
	public static function redirect(string $page = "") : void
	{
		$path = "https://".$_SERVER["SERVER_NAME"]."/fahrplan/".$url;
		header("Location: ".$path);
		die("<p><a href='$path'>Leite um...</a></p>");
	}

	public static function getNumericParameter(string $name, ?int $default = null) : ?int
	{
		if(isset($_REQUEST[$name]) && is_numeric($_REQUEST[$name]))
		{
			return $_REQUEST[$name];
		}
		return $default;
	}
	public static function getStringParameter(string $name, ?string $default = null) : ?string
	{
		if(isset($_REQUEST[$name]))
		{
			return $_REQUEST[$name];
		}
		return $default;
	}

	public static function getChosenDatasetId() : int
	{
		if(!isset($_REQUEST["dataset"]) || !is_numeric($_REQUEST["dataset"]) || $_REQUEST["dataset"] <= 0)
		{
			HtmlHelper::redirect();
		}
		return (int) $_REQUEST["dataset"];
	}

	public static function resultBlock(array $result, ?Importer $importer = null) : string
	{
		$html = [];
		if($result && ($importer == null || $importer->hasLog()))
		{
			$html[] = "<div class='result'>";
			$html[] = "<h3>Ergebnis</h3>";

			foreach($result as $line)
			{
				$html[] = self::resultToString($line);
			}

			if($importer != null && $importer->hasLog())
			{
				$html[] = "<pre>".$importer->printLog()."</pre>";
			}
			$html[] = "</div>";
		}
		return join(PHP_EOL, $html);
	}


	protected function resultToString(array $result) : string
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

	public static function dateSelect(array $marginDates, array $additionalParams = []) : string
	{
		$currentDate = self::getStringParameter("date", "");
		$startDate = (isset($marginDates[0]) && $marginDates[0] !== null) ? $marginDates[0] : "";
		$endDate = (isset($marginDates[1]) && $marginDates[1] !== null) ? $marginDates[1] : "";

		$html = [];
		$html[] = "<div class='dateSelect'>";
			$html[] = "<form action='' method='GET'>";
				$html[] = "<label>Datum ändern:";
					$html[] = "<input type='date' min='$startDate' max='$endDate' name='date' value='$currentDate'>";
				$html[] = "</label>";
				$html[] = "<button type='submit'>ändern</button>";
				$html[] = self::getHiddenParams($additionalParams, ["date"]);
			$html[] = "</form>";
		$html[] = "</div>";
		return join(PHP_EOL, $html);
	}
	public static function nameFilter(array $additionalParams = []) : string
	{
		$name = self::getStringParameter("name", "");

		$html = [];
		$html[] = "<div class='dateSelect'>";
			$html[] = "<form action='' method='GET'>";
				$html[] = "<label>Name:";
					$html[] = "<input type='text' name='name' value='$name' title='* für beliebige Zeichen'>";
				$html[] = "</label>";
				$html[] = "<button type='submit'>filtern</button>";
				$html[] = self::getHiddenParams($additionalParams, ["name"]);
			$html[] = "</form>";
		$html[] = "</div>";
		return join(PHP_EOL, $html);
	}

	public static function addDefaultParams(array $params, array $skip) : array
	{
		$default = [
			"dataset" => self::getNumericParameter("dataset"),
			"date" => self::getStringParameter("date"),
		];
		foreach($default as $key => $value)
		{
			if(!isset($params[$key]) && !in_array($key, $skip))
			{
				$params[$key] = $value;
			}
		}
		return $params;
	}
	public static function getLink(string $target, string $text, array $additionalParams = [], array $skipDefaultParams = []) : string
	{
		$additionalParams = self::addDefaultParams($additionalParams, $skipDefaultParams);
		$params = [];
		foreach($additionalParams as $name => $value)
		{
			if($value !== null && $value !== "")
			{
				$params[] = "$name=$value";
			}
		}
		$queryString = "";
		if(!empty($params))
		{
			$queryString .= "?".join("&", $params);
		}
		return "<a href='".$target.".php".$queryString."'>".$text."</a>";
	}
	public static function getHiddenParams(array $additionalParams = [], array $skipDefaultParams = []) : string
	{
		$additionalParams = self::addDefaultParams($additionalParams, $skipDefaultParams);
		$html = [];
		foreach($additionalParams as $name => $value)
		{
			if($value !== null && $value !== "")
			{
				$html[] = "<input type='hidden' name='$name' value='".htmlspecialchars($value)."'>";
			}
		}
		return join(PHP_EOL, $html);
	}



	public static function stopHtml(?array $stop = null)
	{
		$html = [];
		if($stop !== null && $stop !== [])
		{
			$html[] = "<table class='data addBottomMargin'>";
			$html[] = "<tbody>";

			$html[] = "<tr>";
			$html[] = "<th>Stop-ID</th>";
			$html[] = "<td>".$stop["stop_id"]."</td>";
			$html[] = "</tr>";

			if(isset($stop["stop_code"]))
			{
				$html[] = "<tr>";
				$html[] = "<th>Code</th>";
				$html[] = "<td>".$stop["stop_code"]."</td>";
				$html[] = "</tr>";
			}
			if(isset($stop["stop_name"]))
			{
				$html[] = "<tr>";
				$html[] = "<th>Name</th>";
				$html[] = "<td>".$stop["stop_name"]."</td>";
				$html[] = "</tr>";
			}
			if(isset($stop["platform_code"]))
			{
				$html[] = "<tr>";
				$html[] = "<th>Gleis / Bahnsteig</th>";
				$html[] = "<td>".$stop["platform_code"]."</td>";
				$html[] = "</tr>";
			}
			if(isset($stop["stop_desc"]))
			{
				$html[] = "<tr>";
				$html[] = "<th>Beschreibung</th>";
				$html[] = "<td>".$stop["stop_desc"]."</td>";
				$html[] = "</tr>";
			}
			if(isset($stop["stop_lat"]) && isset($stop["stop_lat"]))
			{
				$html[] = "<tr>";
				$html[] = "<th>Koordinaten</th>";
				$html[] = "<td><a href='https://openrailwaymap.org/?lat=".$stop["stop_lat"]."&lon=".$stop["stop_lon"]."&zoom=16'>OpenRailwayMap</a></td>";
				$html[] = "</tr>";
			}
			if(isset($stop["location_type"]))
			{
				$html[] = "<tr>";
				$html[] = "<th>Ortstyp</th>";
				$html[] = "<td>".GTFSConstants::getLocationTypeName($stop["location_type"])."</td>";
				$html[] = "</tr>";
			}
			if(isset($stop["parent_station"]))
			{
				$html[] = "<tr>";
				$html[] = "<th>Übergeordneter Ort</th>";
				$html[] = "<td>".HtmlHelper::getLink("abfahrten", $stop["parent_station"], ["stop" => $stop["parent_station"]])."</td>";
				$html[] = "</tr>";
			}
			$html[] = "</tbody>";
			$html[] = "</table>";
		}
		return join(PHP_EOL, $html);
	}
}
