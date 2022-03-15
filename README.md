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

Es muss der Ordner "var/import" angelegt werden. In diesen Ordner werden folgende drei Ordner angelegt:
- cache/
- files/
- logs/

Achte in der duplizierten Config-Datei, dass die drei Ordnerpfade dort korrekt hinterlegt sind!

Um Dateien importieren zu können, muss von den open data sources eine ZIP-Datei runtergeladen und diese im Ordner files/ abgelegt werden. Die ZIP-Datei musst nicht entpackt werden!

Nach dem Import wird der Import erfolgreich sein, jedoch wird in der Übersicht keine Daten zu sehen sein. Dieses Verhalten ist aktuell gewollt und wird demnächst noch gelöst. Wichtig ist beim Einrichten erstmal, dass der Import als "erfolgreich" zurück gemeldet wurde.

## Further reading

Specifications:
* [GTFS Specification](https://gtfs.org/reference/static/)
* [Extended route types for GTFS](https://developers.google.com/transit/gtfs/reference/extended-route-types)

Open data sources (Read their licenses before usage):
* [DELFI](https://www.opendata-oepnv.de/ht/de/organisation/delfi/startseite) (Germany)
* [GTFS.de](https://gtfs.de/) (Germany)
* [OVapi](https://gtfs.ovapi.nl/nl/) (Nederlands)
