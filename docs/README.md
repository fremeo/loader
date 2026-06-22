# Dokumentation des Hauptprojekts

## Überblick

Dieses Hauptprojekt ist ein modularer Composer-Loader und bezeichnet sich selbst als `papp/loader`.
Es lädt die eigentliche Anwendung über Composer-Pakete, die in `system/vendor` abgelegt sind.

## Hauptfunktionalität

- `index.php` ist der Einstiegspunkt.
- `setup.php` sorgt für die Composer-Umgebung und bietet eine Installationsoberfläche.
- `system/core/ComposerManager.php` steuert Composer-Befehle wie `install`, `update`, `remove` und `dump-autoload`.
- Die Paketkonfiguration (`composer.json`) legt den Installationspfad auf `system/vendor` fest.

## Modularer Aufbau

Das Projekt ist so aufgebaut, dass zusätzliche Funktionen nicht direkt im Kern liegen, sondern als Composer-Pakete geladen werden.
Diese Pakete sind:

- `papp/phpapp` – das Framework selbst
- `papp/shop` – Shop-Funktionalität
- `papp/blog` – Blog-Funktionalität
- `papp/page` – Seiten-/Content-Funktionalität

## Boot-Prozess

1. `index.php` lädt die Composer-Kernklassen.
2. `system/vendor/autoload.php` wird eingebunden.
3. `system/vendor/papp/phpapp/init.php` und `system/vendor/papp/phpapp/start.php` werden ausgeführt.

Das heißt: Der Hauptprojekt-Code initialisiert die Umgebung, und das Laden der eigentlichen Anwendungslogik erfolgt über die installierten Module.

## Composer und `system/vendor`

In `composer.json` ist der `vendor-dir` explizit auf `system/vendor` gesetzt.
Damit wird Composer angewiesen, alle Abhängigkeiten nicht im Standardordner `vendor`, sondern im Projektordner `system/vendor` zu installieren.

Das erlaubt:

- klare Trennung zwischen dem Hauptprojekt und installierten Modulen
- einfachere Verwaltung der Abhängigkeiten durch die integrierte Setup-Oberfläche
- Nutzung von Dokumentation oder zusätzlichen Dateien, die von den Paketen mitgeliefert werden

## Hinweise für die Arbeit am Projekt

- Das Hauptprojekt ist `papp/loader`.
- `system/vendor` enthält die installierten Pakete, die während der Laufzeit genutzt werden.
- Änderungen an der Paketkonfiguration und am Loader-Code erfolgen im Hauptprojekt.
- Wenn du nur an den installierten Projektteilen arbeiten willst, sind das die jeweiligen Paketordner unter `system/vendor`.

## Fazit

Dieses Projekt ist ein übergeordnetes Loader-Projekt, das modular durch Composer-Pakete erweitert wird.
Die eigentliche Software entsteht durch die Pakete, die in `system/vendor` geladen werden.

