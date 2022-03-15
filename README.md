# Fahrplan-DB

Webbasierte Fahrplan-Datenbank für Fahrplandaten aus GTFS-Daten.

## Install

### Voraussetzungen
* PHP + MariaDB
	* PHP-Pakete: mysql, zip
* Rechte auf der MariaDB:
	* Globale Permission FILE (für LOAD DATA INFILE)

### Konfiguration
`include/config.example.php` als `include/config.php` duplizieren und anpassen.

Neue Datenbank mit dem neuesten SQL-Script aus `dbStructure` anlegen.

Bei öffentlich zugänglichen Systemen sollten die Ordner `admin` und `include` zugangsbeschränkt werden (z.B. mit `.htaccess`).

Standardmäßig arbeitet die Seite mit Ordnern unter `/var/import`. In diesem Ordner werden folgende drei Ordner angelegt:
- `cache/`
- `files/`
- `logs/`

Diese Pfade sind in der Config-Datei hinterlegt.

Um Dateien importieren zu können, muss von eine der OpenData-Quellen eine ZIP-Datei runtergeladen und diese im Ordner `files/` abgelegt werden. Die ZIP-Datei darf nicht entpackt werden! Im Verzeichnis `logs/` wird bei jedem Import eine Logdatei angelegt, die Webseite gibt unter Umständen nur beschränkte Infos aus.

## Further reading

Specifications:
* [GTFS Specification](https://gtfs.org/reference/static/)
* [Extended route types for GTFS](https://developers.google.com/transit/gtfs/reference/extended-route-types)

OpenData Sources (Read their licenses before usage):
* [DELFI](https://www.opendata-oepnv.de/ht/de/organisation/delfi/startseite) (Germany)
* [GTFS.de](https://gtfs.de/) (Germany)
* [OVapi](https://gtfs.ovapi.nl/nl/) (Nederlands)
