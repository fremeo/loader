# Hauptprojekt: papp/loader

Dieses Projekt ist das zentrale Hauptprojekt und dient als modularer Loader/Installer für zusätzliche phpApp-Projekte.

## Architektur

- Das Hauptprojekt ist als Composer-Projekt (`composer.json`) angelegt.
- Die Composer-Abhängigkeiten werden in `system/vendor` installiert.
- Dort liegen auch die geladenen Module/Pakete wie `papp/phpapp`, `papp/shop`, `papp/blog` und `papp/page`.
- `system/vendor` kann neben PHP-Code auch Dokumentation oder weitere Dateien der installierten Pakete enthalten.

## Start und Boot

Die Datei `index.php` ist der Einstiegspunkt:

- `system/core/Packagist.php` und `system/core/ComposerManager.php` werden geladen.
- Eine Composer-Autoload-Datei aus `system/vendor/autoload.php` wird eingebunden.
- Das Framework `papp/phpapp` wird über:
  - `system/vendor/papp/phpapp/init.php`
  - `system/vendor/papp/phpapp/start.php`
  geladen.

Damit ist klar: Das Hauptprojekt orchestriert die Installation und den Start, während die eigentliche Funktionalität modular über die Pakete aus `system/vendor` bereitgestellt wird.

## Composer-Konfiguration

Die `composer.json` definiert das Projekt `papp/loader` und setzt:

- `type: project`
- `config.vendor-dir: system/vendor`
- `require`:
  - `php: ^8.0`
  - `papp/phpapp`
  - `papp/shop`
  - `papp/blog`
  - `papp/page`

Das bedeutet: Alle Pakete werden in den Ordner `system/vendor` installiert und sind Teil der Laufzeitumgebung.

## Setup und Paketverwaltung

Die Datei `setup.php` sorgt dafür, dass die Umgebung existiert und Composer zur Verfügung steht:

- Erzeugt notwendige Ordner wie `system/vendor`, `system/core` und `data_c`
- Lädt `composer.phar` herunter, falls es noch nicht vorhanden ist
- Schreibt Log-Dateien in `data_c/composer_log.txt`
- Bietet AJAX-Aktionen für:
  - Suche
  - Paketinstallation
  - Updates
  - Entfernen
  - Anzeige installierter Pakete
  - Neuinstallation

## Bedeutung von `system/vendor`

`system/vendor` ist kein separater Hauptprojekt-Ordner, sondern der Composer-Installationspfad für Abhängigkeiten des Hauptprojekts.

- Hierher werden die Module geladen, die das Hauptprojekt verwenden.
- Beim Arbeiten am Hauptprojekt berücksichtige ich auch Dateien und Strukturen in `system/vendor`, weil sie Teil der installierten Module sind.
- Wenn du hingegen nur den Kern des Hauptprojekts ändern willst, dann konzentrieren wir uns auf die Dateien im Projektstamm statt auf die installierten Paketdaten.

## Empfehlung

- Bearbeite die Hauptlogik im Projektstamm (`index.php`, `setup.php`, `composer.json`, `system/core`).
- Nutze `system/vendor` als installierte Laufzeitumgebung für die geladenen Module.
- Wenn Pakete verändert werden sollen, ist es besser, die jeweiligen Paketquellen außerhalb des installierten `system/vendor` zu pflegen und dort neu zu installieren.
