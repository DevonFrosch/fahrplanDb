<?php

require_once("Importer.class.php");
require_once("ImportException.class.php");
require_once("CSVReader.class.php");

abstract class ZipImporter extends Importer
{
	protected $importPath = null;
	protected $cachePath = null;

	protected $extractPath = null;

	function __construct(DBHandler $db, string $importPath, string $cachePath, string $logPath)
	{
		parent::__construct($db, $logPath);

		$this->importPath = $importPath;
		$this->cachePath = $cachePath;
	}

	public function getImportFiles() : array
	{
		$files = [];
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->importPath)) as $file)
		{
			if(!$file->isFile())
			{
				continue;
			}

			$files[$file->getFilename()] = $file;
		}
		return $files;
	}

	public function extractZipFile(SplFileInfo $file) : ZipImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		$this->log("Entpacke ZIP-Archiv...");

		$zip = new ZipArchive;
		$res = $zip->open($file->getRealPath());
		if($res !== TRUE)
		{
			$this->abort("Fehler beim Öffnen der ZIP-Datei, Fehlercode $res.");
		}

		$this->extractPath = $this->cachePath."/".date("Y-m-d_His_").$file->getFilename();
		mkdir($this->extractPath);
		$res = $zip->extractTo($this->extractPath);
		$zip->close();

		if($res)
		{
			$this->log("ZIP-Archiv entpackt, Pfad: ".$this->extractPath);
			return $this;
		}

		$this->abort("Fehler beim Entpacken, Fehlercode $res.");
	}

	public function importFiles(?array $files = null) : ZipImporter
	{
		if(!$this->isRunning())
		{
			return $this;
		}
		$this->doImportFiles($files);
		return $this;
	}

	public abstract function doImportFiles(?array $files) : ZipImporter;

	public function removeExtractedFiles() : void
	{
		if($this->extractPath)
		{
			$this->log("Lösche Daten aus ".$this->extractPath);
			self::delTree($this->extractPath);
		}
	}
	public function clear() : void
	{
		$this->removeExtractedFiles();
	}
	public function clearAll() : void
	{
		$this->log("Lösche Daten aus ".$this->cachePath);
		self::delTree($this->cachePath, false);
		$this->log("Lösche Daten aus ".$this->logPath);
		self::delTree($this->logPath, false);
	}
	public function finish($clearImportData = true) : void
	{
		if($clearImportData)
		{
			$this->clear();
		}
		parent::finish();
	}

	// File handling
	protected function getFile(string $fileName) : CSVReader
	{
		$path = $this->getFilePath($fileName);
		if(!is_file($path))
		{
			$this->abort("Datei $fileName fehlt im Import!");
		}

		return new CSVReader($path, true);
	}
	protected function getCSVHeader(string $fileName) : array
	{
		$path = $this->getFilePath($fileName);
		if(!is_file($path))
		{
			$this->abort("Datei $fileName fehlt im Import!");
		}

		$csvFile = new CSVReader($path, false);
		$lines = $csvFile->get(1);
		if(empty($lines))
		{
			return [];
		}
		return $lines[0];
	}
	protected function doesFileExist(string $fileName) : bool
	{
		return is_file($this->getFilePath($fileName));
	}
	protected function getFilePath(string $fileName) : string
	{
		return $this->extractPath."/".$fileName.".txt";
	}

	// Quelle: https://stackoverflow.com/a/45290342
	protected function detectEOL(string $filePath) : string
	{
		// open the file and read a single line from it
		$file = fopen($filePath, 'r');
		fgets($file);

		// fgets() moves the pointer, so get the current position
		$position = ftell($file);

		// File too short -> just guess
		if($position < 2)
		{
			$this->log("detectEOL(): Zeile zu kurz, nehme \\n");
			return "\n";
		}

		// now get a couple bytes before that position
		fseek($file, $position - 2);
		$data = fread($file, 2);

		// we no longer need the file
		fclose($file);

		foreach(["\\r\\n" => "\r\n", "\\n" => "\n", "\\r" => "\r"] as $display => $ending)
		{
			if(strpos($data, $ending) !== false)
			{
				$this->log("detectEOL(): Gefunden: $display");
				return $ending;
			}
		}
		// Just guess
		$this->log("detectEOL(): Kein Zeilenende gefunden, nehme \\n");
		return "\n";
	}

	protected static function delTree(string $dir, bool $deleteFolder = true) : void
	{
		if(!is_dir($dir))
		{
			return;
		}

		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file)
		{
			if(is_dir("$dir/$file"))
			{
				self::delTree("$dir/$file");
			}
			else
			{
				unlink("$dir/$file");
			}
		}
		if($deleteFolder)
		{
			rmdir($dir);
		}
	}
}
