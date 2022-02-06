<?php

require_once("ImportException.class.php");

class GTFSFiles
{
	public static function getFileOptions(string $fileName) : GTFSFileOptions
	{
		switch($fileName)
		{
			case "agency":
				return new GTFSFileOptions("agency", [
					"agency_id",
					"agency_name",
				], [
					"agency_timezone" => null,
				]);
			case "calendar":
				return new GTFSFileOptions("calendar", [
					"service_id",
					"start_date",
					"end_date",
				], [
					"monday" => "1",
					"tuesday" => "1",
					"wednesday" => "1",
					"thursday" => "1",
					"friday" => "1",
					"saturday" => "1",
					"sunday" => "1",
				], true);
			case "calendar_dates":
				return new GTFSFileOptions("calendar_dates", [
					"service_id",
					"date",
					"exception_type",
				], [], true);
			case "stops":
				return new GTFSFileOptions("stops", [
					"stop_id",
				], [
					"stop_code" => null,
					"stop_name" => null,
					"stop_desc" => null,
					"stop_lat" => null,
					"stop_lon" => null,
					"location_type" => "0",
					"parent_station" => null,
					"platform_code" => null,
				]);
			case "routes":
				return new GTFSFileOptions("routes", [
					"route_id",
					"agency_id",
					"route_type",
				], [
					"route_short_name" => null,
					"route_long_name" => null,
					"route_color" => "FFFFFF",
					"route_text_color" => "000000",
					"route_desc" => "",
					"continuous_pickup" => "1",
					"continuous_drop_off" => "1",
				]);
			case "trips":
				return new GTFSFileOptions("trips", [
					"trip_id",
					"route_id",
				], [
					"direction_id" => "0",
					"service_id" => null,
					"trip_headsign" => null,
					"trip_short_name" => null,
					"block_id" => null,
				]);
			case "stop_times":
				return new GTFSFileOptions("stop_times", [
					"trip_id",
					"stop_sequence",
					"stop_id",
				], [
					"arrival_time" => null,
					"departure_time" => null,
					"stop_headsign" => null,
					"pickup_type" => "0",
					"drop_off_type" => "0",
					"continuous_pickup" => "1",
					"continuous_drop_off" => "1",
					"timepoint" => "1",
				]);
		}
		throw new ImportException("GTFSFiles: Datei $fileName unbekannt");
	}
}

class GTFSFileOptions
{
	protected $fileName;
	protected $tableName;
	protected $mandatoryFields = [];
	protected $optionalFields = [];
	protected $optional = false;

	protected $mandatoryFieldsFound = [];
	protected $optionalFieldsFound = [];

	function __construct(string $fileName, array $mandatoryFields, array $optionalFields, bool $optional = false, string $tableName = null)
	{
		$this->fileName = $fileName;
		$this->mandatoryFields = $mandatoryFields;
		$this->optionalFields = $optionalFields;
		$this->optional = $optional;
		$this->tableName = $fileName;
		if($tableName !== null)
		{
			$this->tableName = $tableName;
		}

		foreach($this->optionalFields as $field => $default)
		{
			if(is_numeric($field))
			{
				throw new ImportException("GTFSFileOptions: optionalFields enthält numerische Keys! Falsche Verwendung!");
			}
		}
	}

	public function getFileName() : string
	{
		return $this->fileName;
	}

	public function getTableName() : string
	{
		return $this->tableName;
	}

	// ---------------------
	public function getMandatoryFields() : array
	{
		return $this->mandatoryFields;
	}
	public function isMandatoryField(string $field) : bool
	{
		return in_array($field, $this->getMandatoryFields());
	}
	public function markMandatoryFieldAsFound(string $field) : bool
	{
		if(in_array($field, $this->mandatoryFieldsFound))
		{
			return false;
		}
		$this->mandatoryFieldsFound[] = $field;
		return true;
	}
	public function getMissingMandatoryFields() : array
	{
		return array_diff($this->mandatoryFields, $this->mandatoryFieldsFound);
	}

	// ---------------------
	public function getOptionalFields() : array
	{
		return array_keys($this->optionalFields);
	}
	public function isOptionalField(string $field) : bool
	{
		return in_array($field, $this->getOptionalFields());
	}
	public function markOptionalFieldAsFound(string $field) : bool
	{
		if(in_array($field, $this->optionalFieldsFound))
		{
			return false;
		}
		$this->optionalFieldsFound[] = $field;
		return true;
	}

	public function getDefaultForField(string $field)
	{
		if(!$this->isOptionalField($field))
		{
			throw new ImportException("GTFSFileOptions: '$field' ist kein optionales Feld!");
		}
		return $this->optionalFields[$field];
	}
	
	public function getFields() : array
	{
		return array_merge($this->getMandatoryFields(), $this->getOptionalFields());
	}

	public function isOptional() : bool
	{
		return $this->optional;
	}
}