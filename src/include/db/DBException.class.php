<?php

class DBException extends Exception
{
	protected $query = "";
	
	public function __construct($message = '', Throwable $previous = null, string $query = "")
	{
		parent::__construct($message, 0, $previous);
		$this->query = $query;
	}
	
	public function getLongMessage() : string
	{
		$str = $this->__toString();
		$str .= PHP_EOL.PHP_EOL."Query: ".$this->query.PHP_EOL;
		if($this->getPrevious() !== null)
		{
			$str .= $this->getPrevious()->__toString();
		}
		return $str;
	}
	
	public function getQuery() : string
	{
		return $this->query;
	}
}
