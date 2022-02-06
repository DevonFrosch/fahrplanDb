<!DOCTYPE html>
<html>
	<head>
		<title>Fahrplan-DB - Admin</title>
		<link rel="stylesheet" type="text/css" href="../style.css" />
	</head>
	<body>
		<h1>Fahrplan-DB - Admin</h1>
		<ul>
			<li><a href="../">Startseite</a></li>
			<li><a href="import.php">Import</a></li>
		</ul>
		<p>
			Git-Commit:
			<pre><?php
				exec('git show HEAD -s --format="%h%d%n%ci%n%s"', $output);
				echo htmlspecialchars(join("\n", $output));
			?></pre>
		</p>
	</body>
</html>
