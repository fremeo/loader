# Hauptprojekt: fremeo/loader

Dieses Projekt ist das zentrale Hauptprojekt und dient als modularer Loader/Installer für zusätzliche core-Projekte.

## Architektur

- Das Hauptprojekt ist als Composer-Projekt (`composer.json`) angelegt.
- Die Composer-Abhängigkeiten werden in `system/vendor` installiert.
- Dort liegen auch die geladenen Module/Pakete wie `fremeo/core`, `fremeo/shop`, `fremeo/blog` und `fremeo/page`.
- `system/vendor` kann neben PHP-Code auch Dokumentation oder weitere Dateien der installierten Pakete enthalten.

## Start und Boot

Die Datei `index.php` ist der Einstiegspunkt:

- `system/core/Packagist.php` und `system/core/ComposerManager.php` werden geladen.
- Eine Composer-Autoload-Datei aus `system/vendor/autoload.php` wird eingebunden.
- Das Framework `fremeo/core` wird über:
  - `system/vendor/fremeo/core/init.php`
  - `system/vendor/fremeo/core/start.php`
  geladen.

Damit ist klar: Das Hauptprojekt orchestriert die Installation und den Start, während die eigentliche Funktionalität modular über die Pakete aus `system/vendor` bereitgestellt wird.

## Composer-Konfiguration

Die `composer.json` definiert das Projekt `fremeo/loader` und setzt:

- `type: project`
- `config.vendor-dir: system/vendor`
- `require`:
  - `php: ^8.0`
  - `fremeo/core`
  

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

## Installation
# 1. Lade das Hauptprojekt herunter und entpacke es in einen Webserver-Ordner.
# 2. Rufe `/setup/` im Browser auf, um die Installation zu starten.
# 3. Als erstes wird ein Verzeichnisschutz für setup/ eingerichtet, um unbefugten Zugriff zu verhindern. Zugangsdaten werden im Browser einmal angezeigt und sollten notiert werden.
# 4. Danach wird console.php aufgerufen.
# 5. gib install ein, um die Installation zu starten. Composer wird automatisch heruntergeladen und die Abhängigkeiten installiert.
# 6. nach erfoglreichen installtion wird Admin Zugang angezeigt. Benutzer und Passwort dies notieren, da es nur einmal angezeigt wird.
# 7. durch den Aufruf "/admin/" im Browser kann man sich mit den Zugangsdaten anmelden und die Admin-Oberfläche nutzen.
# 8. Änderen Sie das Passwort nach der ersten Anmeldung, um die Sicherheit zu gewährleisten.