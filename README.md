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

Bei öffentlich zugänglichen Systemen sollten die Ordner admin und include zugangsbeschränkt werden (z.B. mit .htaccess).

## Further reading

Specifications:
* [GTFS Specification](https://gtfs.org/reference/static/)
* [Extended route types for GTFS](https://developers.google.com/transit/gtfs/reference/extended-route-types)

Open data sources (Read their licenses before usage):
* [DELFI](https://www.opendata-oepnv.de/ht/de/organisation/delfi/startseite) (Germany)
* [GTFS.de](https://gtfs.de/) (Germany)
* [OVapi](https://gtfs.ovapi.nl/nl/) (Nederlands)
