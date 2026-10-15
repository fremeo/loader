# Programmierleitfaden für Module

Regeln für den Code in Modulen (`system/vendor/fremeo/<modul>`). Neue Erkenntnisse werden fortlaufend hier dokumentiert.

## Standardstruktur eines Moduls

Ein Modul folgt in der Regel dieser Struktur. Die Struktur dient als Referenz für KI und Entwickler, damit die Dateien nicht erst im Code gesucht werden müssen:

```text
system/vendor/<hersteller>/<modul>/
├── composer.json
├── README.md
├── init.php
├── start.php
├── system/
│   ├── migration/
│   │   ├── 00000000000000_install.php
│   │   ├── <DateTime>_update.php
│   │   └── ...
│   ├── template/
│   │   ├── <seite>.tpl
│   │   └── ...
│   ├── <seite>.php
│   └── ...
├── tests/
│   └── ...
└── docs/
    └── ...
```

Kurzform:

```text
<modul>/
├── init.php
├── start.php
├── system/
│   ├── migration/
│   ├── template/
│   ├── *.php
│   └── ...
├── tests/
└── docs/
```

Wichtige Grundregel: Das Modul hat immer einen eigenen Bereich unter `system/`, wobei Templates in `system/template/`, Datenbank-Migrationen in `system/migration/` und die PHP-Seiten direkt im `system/`-Ordner liegen.

## 1. Grundprinzip: `$D` ist der Datenbaum

- `$D` ist ein mehrdimensionaler Baum, der **von Anfang bis Ende** der Anfrage mit Daten angereichert wird und 1:1 an Smarty geht (`$D.PAGE.D.<Id>.LANGUAGE.D.DE.Title`).
- `$D` bildet die Struktur des **Patterns** ab (siehe `init.php` des Moduls). Dieselbe Struktur steckt in den Namen der Formularfelder: `D[PAGE][D][<Id>][LANGUAGE][D][DE][Title]`.
- `CData::get_object($D, $f)` und `set_object($D)` bauen die übergebene Variable auf. Sie **löschen nie fremde Daten** aus `$D`, sie ergänzen nur. Mehrere Abfragen (z.B. PAGE, danach LINK) füllen denselben Baum.
- Deshalb gilt: Daten direkt in `$D` laden und im Template darauf zugreifen, **keine Kopien in Hilfsarrays** (`$R['PageEdit'] = [...]`) bauen. Ein neues Feld im Pattern ist dann ohne Änderung an der PHP-Seite im Template verfügbar (z.B. `DateTimeOriginal`).
- Filtern, was angezeigt wird, macht das **Template** (z.B. nur aktive Bilder per `{if $img.Active|default:1}`), nicht die PHP-Seite. Die PHP-Seite reicht `Active` & Co. unverändert durch.
- Abfragen in einer eigenen Variable anlegen (`$f`), nicht in der globalen `$F`: `start.php` fragt am Ende mit `$F` die Core-Datenbank ab.

## 2. `$R` für alles außerhalb der Datenstruktur

- Hilfswerte, die nicht zum Pattern gehören, laufen über `$R`: Seiten-/Modul-Id (`R[Id]`, `R[Page]`, `R[ModuleId]`), Passwort-Eingabe, Meldungen (`$R['Msg']`, `$R['Info']`), Zustand der Seite (`$R['Gallery']['Locked']`), Filter, Upload-Zusatzinfos.
- Im Template: `$D.R.<Name>`. `$R` und `$D` sind getrennt und können sich nicht überschneiden. In `$D` stehen nur Daten aus der Datenbank.

## 3. Eingaben aus dem Formular

- Dem Formular nie vertrauen: **nie die ganze Anfrage** in die Datenbank schreiben (`set_object($D)` mit `$D` aus dem POST ist verboten). Einen Update-Baum `$U` aus erlaubten Feldern bauen und `set_object($U)` aufrufen.
- IDs prüfen (`/^[A-Za-z0-9_-]+$/`), Sprach-Codes prüfen (`/^[A-Za-z]{2,8}$/`). Werte, die in Kommandozeilen oder Pfade gelangen, immer validieren.
- Was `CData::set_object` selbst übernimmt: nur Typen und Felder aus dem Pattern (Unbekanntes wird ignoriert) und **IDs werden escaped** (auch beim Löschen und im Cache). Der `Type` im Pattern (`number`, `checkbox`, `id`) wird von `set_object` **noch nicht** geprüft, ein Wert wie `'abc'` in einem `number`-Feld wird gespeichert. Das ist offen und bis zur Entscheidung Aufgabe der Seite (`(int)`, Regex).
- Was `set_object` **nicht** übernimmt, bleibt Aufgabe der Seite: Werte gegen den Typ prüfen (siehe oben), welche Felder ein Formular schreiben darf (Felder, die der Server vergibt wie `LINK`, `FileId`, `Password`), Löschbefehle (`__DELETE__`, `Active = -2`) aus dem Formular, Berechtigungen und inhaltliche Regeln.
- Gepostete Daten nach dem Speichern aus `$D` entfernen (`unset($D['PAGE'])`) und aus der Datenbank neu laden, damit Eingabe und gespeicherte Daten nie vermischt werden. Das gilt auch im Frontend (`unset($D['ALBUM'])` vor `get_object`), sonst kann ein Besucher Daten einschleusen.
- Das Formular sendet **nur geänderte Felder** (`ischanged`, siehe `input.tpl`). Fehlt ein Feld im POST, heißt das "unverändert", nicht "leer". Ein gesendetes leeres Feld ist eine bewusste Änderung.
- Ändernde Aktionen (speichern, löschen, installieren) nur per **POST** und nur bei `$D['ACTION'] == 'save'`, nie über Links (GET).
- Geheimnisse (Passwort) vor dem Template aus `$D` entfernen (`unset($A['Password'])`). Bei gesperrten Inhalten auch die Unterdaten (`IMAGE`) entfernen.

## 4. Löschen

- Löschen-Button je Zeile blendet die Zeile nur aus und aktiviert ein verstecktes Feld `D[<TYP>][D][<Id>][__DELETE__]=1`. Entfernt wird erst beim Speichern ("Löschen erfolgt erst beim Speichern"), vorher fragt `confirm()` nach.
- Der Server löscht mit `'__DELETE__'` im Update-Baum und räumt Abhängiges mit auf: SEO-Links (`$C['Link']->deleteById`), Dateien (`$File->delete`, nur wenn nirgends sonst verwendet).

## 5. Datenbank, Patterns, Dateien, Links

- Jedes Modul mit eigener Datenbank legt in `init.php` `$C['<vendor/modul>']['CData']` an und registriert sein Pattern. Das Core ergänzt automatisch `LINK`, `FILE`, `MIGRATION` und das Objekt `$C['<vendor/modul>']['File']`.
- **Dateien** nur über den File-Dienst des eigenen Moduls (`$C['fremeo/gallery']['File']->add(...)`): schreibt nur in den eigenen Ordner und lehnt ausführbare Endungen ab.
- **SEO-Links** nur über `$C['Link']` (`create`, `rename`, `deleteById`, `getById`, `normalize`). Ist eine URL vergeben, hängt `Link` selbst `_1`, `_2` ... an, auch bei der leeren URL (Startseite). Geprüft wird über alle Module.
- Der Vorschlag der SEO-URL aus dem Titel entsteht per **JS im Template** (Feld als geändert markieren mit `input_onmouseout(el)`), der Server erzeugt sie nicht aus dem Titel. Leeres, gesendetes Feld = Startseite.
- Zeitangaben als **Unix-Timestamp** (Zahl) speichern, Format erst im Template (`{date('d.m.Y H:i:s', (int)$x)}`). Ein Datum nie durch den Upload-Zeitpunkt ersetzen, wenn es aus den Daten gelesen werden soll (EXIF `DateTimeOriginal`, sonst Dateidatum des Browsers, sonst kein Wert).
- Datenbanken wachsen per Pattern, Änderungen an bestehenden Daten laufen über Migrationen (`system/migration/*.php`).

## 6. Seiten und Templates

- Zu `system/<name>.php` gehört `system/template/<name>.tpl`. Die Seite läuft im Scope von `start.php`: Variablen (`$U`, `$old`, `$f` ...) am Anfang initialisieren, damit nichts aus anderen Dateien der Kette durchsickert.
- Smarty läuft mit Security-Policy: nur freigegebene PHP-Funktionen (`date`, `count`, `in_array`, `strtolower`, `implode` ...). Was fehlt, im Pfad der PHP-Seite vorbereiten oder in `start.php` freigeben.
- Ausgabe immer escapen (`|escape`), auch in `data-`-Attributen und JavaScript-Strings (`|escape:'javascript'`).
- Fehlende Felder im Template mit `|default:''` abfangen (nicht gesetzte Felder fehlen im `$D`).
- Eingabefelder über `{input p=[...]}`; der Name spiegelt das Pattern (`D[PAGE][D][{$id}][Title]`).
- JavaScript in `{literal}...{/literal}`. Zähler (z.B. angezeigte Bilder) im Template mit Smarty-Variablen führen, wenn das Template selbst filtert.
- Bibliotheken (`type: library`) haben weder Tabs noch Templates und kommen in Admin-Listen nicht vor.

## 7. Composer und Module

- `Composer.php` im Core und `setup/Composer.php` sind **identisch** (das Setup kann den Core bei der Installation noch nicht laden). Änderungen immer in beide kopieren, `ComposerSyncTest` prüft das.
- Das Setup lädt nur seine Kopie, das Projekt nur die des Cores.
- Neue Core-Funktionen, die Module brauchen, erst im Core taggen. Module nennen die Mindestversion des Cores in der `composer.json` (z.B. `>=0.0.4 <1.0`).
- Bei `0.0.x` bedeutet `^0.0.4` genau diese Version, deshalb die Spanne verwenden.

## 8. Tests

- Jedes Modul hat einen `tests/`-Ordner mit `phpunit.xml` und `tests/bootstrap.php`. Neue Seiten und Fehlerkorrekturen bekommen einen Test.
- Integrationstests binden die echte Seite ein (`include __DIR__ . '/../system/<name>.php'`) und arbeiten mit temporären Datenbanken (`sys_get_temp_dir()`), die im `tearDown` entfernt werden.
- Gleichen Aufbau wie im Core verwenden: `$C`, `$D`, `$R` als Referenzen in einer Closure, `$D['R'] = &$R`.
- Hilfsmethoden in Testklassen nicht `run()`, `runTest()` o.ä. nennen (final in `TestCase`).
- Tests laufen mit `php phpunit.phar` im Modulordner.

## 9. Programmierstil

- Code **kurz** und lesbar halten, wie ein Mensch ihn schreibt: keine Abstraktionen für einmalige Dinge, keine Fehlerbehandlung für Fälle, die nicht vorkommen, nur an Systemgrenzen (Formular, Dateien, Netz) prüfen.
- Kommentare erklären das Warum, nicht das Wie. Deutsch, einzeilig.
- Bestehende Dateien bearbeiten statt neue anlegen. Nichts umbauen, was nicht verlangt ist.
- Änderungen an Dateien nicht gleichzeitig per Editor und Terminal ausführen (die zweite überschreibt die erste).

## 10. Informationen
- Weitere informationen könnten im docs/ Ordner des Haupt Projekts oder je Modul hinterlegt sein, diese detalierte Beschreibung ggf. zur Nutzung der Klassen liefert.