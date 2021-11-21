<?php

require_once("DBException.class.php");

class DBHandler
{
	protected $pdo = null;
	protected $queryLogger = null;

	const TABLES = [
		"agency",
		"calendar",
		"calendar_dates",
		"datasets",
		"routes",
		"stops",
		"stop_times",
		"trips",
	];

	public function __construct(array $dbConfig)
	{
		try
		{
			$this->pdo = new PDO('mysql:host='.$dbConfig['host'].';dbname='.$dbConfig['db'], $dbConfig['user'], $dbConfig['passw'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		}
		catch(PDOException $e)
		{
			throw new DBException("Fehler beim Verbinden mit der Datenbank", $e);
		}
	}
	public function setQueryLogger(?callable $queryLogger) : void
	{
		$this->queryLogger = $queryLogger;
	}
	protected function logQuery(string $query, ?array $params = null, ?string $additionalInfo = null) : void
	{
		if($this->queryLogger == null)
		{
			return;
		}
		$logger = $this->queryLogger;
		$logger($query, $params, $additionalInfo);
	}

	protected function query(string $query, ?array $params = null) : array
	{
		if(!$this->pdo)
		{
			throw new DBException("Keine Datenbankverbindung.");
		}

		try
		{
			$stmt = $this->pdo->prepare($query);
			$stmt->execute($params);
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
		catch(PDOException $e)
		{
			throw new DBException("Datenbankfehler beim Lesen.", $e, $query);
		}
	}
	protected function queryValue(string $query, ?array $params = null) : ?string
	{
		if(!$this->pdo)
		{
			throw new DBException("Keine Datenbankverbindung.");
		}

		try
		{
			$stmt = $this->pdo->prepare($query);
			$stmt->execute($params);
			$result = $stmt->fetch(PDO::FETCH_NUM);
			if(isset($result[0]))
			{
				return $result[0];
			}
		}
		catch(PDOException $e)
		{
			throw new DBException("Datenbankfehler beim Lesen.", $e, $query);
		}
		return null;
	}
	protected function execute(string $query, ?array $args = null) : int
	{
		if(!$this->pdo)
		{
			throw new DBException("Keine Datenbankverbindung.");
		}

		try
		{
			$stmt = $this->pdo->prepare($query);
			$stmt->execute($args);
			return $stmt->rowCount();
		}
		catch(PDOException $e)
		{
			throw new DBException("Datenbankfehler beim Schreiben.", $e, $query);
		}
	}
	protected function prepare(string $query) : PDOStatement
	{
		if(!$this->pdo)
		{
			throw new DBException("Keine Datenbankverbindung.");
		}

		try
		{
			return $this->pdo->prepare($query);
		}
		catch(PDOException $e)
		{
			throw new DBException("Datenbankfehler beim Vorbereiten.", $e, $query);
		}
	}

	public function disableKeys(string $tableName) : void
	{
		$this->execute("ALTER TABLE `$tableName` DISABLE KEYS;");
	}
	public function enableKeys(string $tableName) : void
	{
		$this->execute("ALTER TABLE `$tableName` ENABLE KEYS;");
	}

	public function lastInsertId() : ?int
	{
		$id = $this->pdo->lastInsertId();
		if($id === false)
		{
			return null;
		}
		return $id;
	}

	protected function joinKeyValuePairs(array $pairs, string $glue = ", ") : string
	{
		$sets = [];
		foreach($pairs as $key => $value)
		{
			if(is_numeric($key))
			{
				$sets[] = $value;
			}

			if(empty($key) || $value === null || $value == "")
			{
				throw new DBException("joinKeyValuePairs: Falsches Format für pairs.", $exception);
			}
			$sets[] = "`$key` = $value";
		}
		return join(", ", $sets);
	}
}
