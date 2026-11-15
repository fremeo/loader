# Changelog

## 1.0.5 - 2026-11-09 (unveroeffentlicht)

- Composer ausschliesslich im Loader-Setup bereitstellen; Core nutzt eine
  optionale Schnittstelle statt eigener Klasse/PHAR oder Laufzeitkopien.
- Setup-Wrapper wertet Fehler und Skript-Exitcodes aus, erfasst Prozessausgaben
  und sperrt parallele Paketaktionen ohne eigenen 300s-Prozessabbruch.
- Versionsregel korrigiert: genau eine naechste Modulversion nach dem letzten
  Release/Tag; bis zum zugehoerigen Tag keine weiteren Versionsanhebungen.
- Entwicklungsleitfaden um die verbindliche Changelog-Pflege bei jeder
  Modulaenderung ergaenzt; fehlende Modul-Changelogs werden angelegt.
- Laufende Pflege des Page-Builder-Konzepts im Core bei jeder
  Builder-Aenderung oder -Erweiterung vorgeschrieben.

Fruehere Versionen werden hier nicht nachtraeglich rekonstruiert.
