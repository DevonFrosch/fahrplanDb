<?php

// Quelle: https://www.php.net/manual/en/function.fgetcsv.php#68213
class CsvReader
{
	private $fp;
	private $parse_header;
	private $header;
	private $length;

	public function __construct(string $file_name, bool $parse_header=false, int $length=8000)
	{
		$this->fp = fopen($file_name, "r");
		$this->parse_header = $parse_header;
		$this->length = $length;

		// Quelle: https://www.php.net/manual/en/function.fgetcsv.php#122696
		// Progress file pointer and get first 3 characters to compare to the BOM string.
		if(fgets($this->fp, 4) !== "\xef\xbb\xbf")
		{
			// BOM not found - rewind pointer to start of file.
			rewind($this->fp);
		}

		if ($this->parse_header)
		{
		   $this->header = fgetcsv($this->fp, $this->length);
		}
	}

	public function __destruct()
	{
		if ($this->fp)
		{
			fclose($this->fp);
		}
	}

	public function get(int $max_lines=0) : array
	{
		$data = [];

		$line_count = -1;
		if ($max_lines > 0)
		{
			$line_count = 0;
		}

		while ($line_count < $max_lines && ($row = fgetcsv($this->fp, $this->length)) !== FALSE)
		{
			if ($this->parse_header)
			{
				$row_new = [];
				foreach ($this->header as $i => $heading_i)
				{
					$row_new[$heading_i] = $row[$i];
				}
				$data[] = $row_new;
			}
			else
			{
				$data[] = $row;
			}

			if ($max_lines > 0)
			{
				$line_count++;
			}
		}
		return $data;
	}
}
