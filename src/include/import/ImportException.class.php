<?php

class ImportException extends Exception
{
	public function __construct($message = '', Throwable $previous = null)
	{
		parent::__construct($message, 0, $previous);
	}
	
	public function getLongMessage() : string
	{
		$str = $this->__toString();
		if($this->getPrevious() !== null)
		{
			if(method_exists($this->getPrevious(), "getLongMessage"))
			{
				$str .= $this->getPrevious()->getLongMessage();
			}
			else
			{
				$str .= $this->getPrevious()->__toString();
			}
		}
		return $str;
	}
}
