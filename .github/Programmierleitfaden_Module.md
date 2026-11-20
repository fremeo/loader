# Programmierleitfaden für Module

Regeln für den Code in Modulen (`system/vendor/fremeo/<modul>`). Neue Erkenntnisse werden fortlaufend hier dokumentiert.

Fuer Sprachvariablen gilt das [Konzept_Sprachvariablen.md](../system/vendor/fremeo/core/docs/Konzept_Sprachvariablen.md) als Zielkonzept. Jede Aenderung an den Sprachvariablen-Regeln oder ihrer Umsetzung muss die Konzeptdatei im selben Arbeitsschritt aktualisieren; keine automatische Dokumentgenerierung zur Laufzeit.

Fuer das Shop-Modul gilt [Konzept_Shop.md](../system/vendor/fremeo/shop/docs/Konzept_Shop.md). Bei jeder Anpassung oder Erweiterung des Konzepts sowie konzeptrelevanten Aenderungen seiner Umsetzung muss diese Markdown-Datei im selben Arbeitsschritt aktualisiert werden; ueberholte Aussagen ersetzen und Umsetzungsstand sowie zurueckgestellte Funktionen korrekt halten.

Neue Projekte, Module und Seiten legen ihre Oberflaechentexte von Anfang an als Sprachvariablen im jeweiligen Modul unter `system/template/language/de.php` an und verwenden im Template `{i18n key="..." values=[...]}` statt fest eingebauter Texte. Waehrend der Entwicklung genuegt Deutsch. Vor jeder Veroeffentlichung muessen die mitgelieferten Texte zusaetzlich sinngemaess ins Englische uebersetzt und unter denselben Schluesseln in `system/template/language/en.php` hinterlegt sein; ICU-Platzhalter und deren Bedeutung erhalten. Bei jeder erneuten Ueberarbeitung einer bestehenden Seite deren Oberflaechentexte mitpruefen und noch fest eingebaute Texte in die Sprachdatei des zustaendigen Moduls portieren. Keine pauschale Migration unberuehrter Seiten.

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

Wichtige Grundregel: Das Modul hat immer einen eigenen Bereich unter `system/`, wobei Templates in `system/template/`, Datenbank-Migrationen in `system/migration/` und die PHP-Seiten direkt im `system/`-Ordner liegen.

### Gemeinsames Admin-Fenster

`include/window.tpl` stellt einen verschiebbaren modalen Dialog bereit. Mit
`{include file="include/window.tpl" windowId="..." windowTitle=$title windowClose=$close windowContent=$content windowOk=$ok windowCancel=$cancel windowApply=$apply}`
werden eindeutige ID, Titel, Schliessen-Beschriftung und zuvor mit `{capture}`
erzeugte Inhalts- und Aktionsbereiche uebergeben. Die Standardaktionen stehen
in der Reihenfolge OK (Speichern und schliessen), Abbrechen, Uebernehmen
(Speichern ohne Schliessen); nicht benoetigte Aktionen koennen entfallen.
Fuer abweichende Aktionsleisten bleibt `windowActions` verfuegbar. Das Fenster stellt Rahmen,
Fokus, Escape, Schliessen und Pointer-Drag bereit; Speichern, Fehler und
fachliche Aktionen implementiert die aufrufende Seite. Dynamische Inhalte
vor dem Capture escapen. Das bestehende Builder-Fenster bleibt eigenstaendig.

### Datenbank-Patterns (verbindlich fuer alle Module)

- Moduleigene Pattern-Definitionen liegen in `system/default/default.pattern.php` als PHP-Datei mit `return [...]`, nicht direkt in `init.php`.
- `init.php` laedt die Datei in einer isolierten Closure und registriert das zurueckgegebene Array ausschliesslich beim eigenen `$C['<vendor/modul>']['CData']`. Keine gemeinsame `$Pattern`-Variable ueberschreiben: Modulinitialisierungen teilen sich den aufrufenden Scope.
- Das vom Core bereitgestellte `default.global.pattern.php` bleibt davon getrennt und wird weiterhin zentral fuer jedes Modul registriert. Gemeinsame Definitionen nicht in Moduldateien duplizieren.
- Beim Auslagern bestehender Patterns alle Pfade, Felder, Typen und Optionen unveraendert erhalten; eine Strukturmigration ist ein eigener Arbeitsschritt.

## 1. Grundprinzip: `$D` ist der Datenbaum

- `$D` ist ein mehrdimensionaler Baum, der **von Anfang bis Ende** der Anfrage mit Daten angereichert wird und 1:1 an Smarty geht (`$D.PAGE.D.<Id>.LANGUAGE.D.DE.Title`).
- `$D` bildet die Struktur des **Patterns** ab (siehe `system/default/default.pattern.php` des Moduls). Dieselbe Struktur steckt in den Namen der Formularfelder: `D[PAGE][D][<Id>][LANGUAGE][D][DE][Title]`.
- `CData::get_object($D, $F)` ergänzt den Datenbaum und überschreibt vorhandene Schlüssel mit geladenen Werten; weitere vorhandene Zweige bleiben erhalten. Mehrere Abfragen (z.B. PAGE, danach LINK) können denselben Baum füllen. `set_object($D)` verarbeitet alle übergebenen Pattern-Zweige; Löschmarker und leere Werte können Daten entfernen.
- `get_object()` und `set_object()` niemals innerhalb einer Schleife aufrufen. Auch bei Pagination zuerst einmal laden und nur bei einer nötigen Seitenkorrektur einmal gezielt nachladen.
- Deshalb gilt: Daten direkt in `$D` laden und im Template darauf zugreifen, **keine Kopien in Hilfsarrays** (`$R['PageEdit'] = [...]`) bauen. Ein neues Feld im Pattern ist dann ohne Änderung an der PHP-Seite im Template verfügbar (z.B. `DateTimeOriginal`).
- Filtern, was angezeigt wird, macht das **Template** (z.B. nur aktive Bilder per `{if $img.Active|default:1}`), nicht die PHP-Seite. Die PHP-Seite reicht `Active` & Co. unverändert durch.
- Bestehende Filterstruktur beibehalten: `$F` wie im vorhandenen Code schrittweise ueber Array-Pfade aufbauen, nicht fuer jeden Filter eine eigene Variable oder ein tief verschachteltes Array einfuehren. Da `start.php` `$F` anschliessend fuer die Core-Datenbank nutzt, die verwendeten Modulfilter danach wieder entfernen.
- Beispiele fuer direkte Verarbeitung und Filteraufbau: [admin__article.list.php](../system/vendor/fremeo/shop/system/admin__article.list.php) und [admin__attribute_set.list.php](../system/vendor/fremeo/shop/system/admin__attribute_set.list.php). Berechtigungen und Schutzregeln sind je nach Seite weiterhin zu beruecksichtigen.

## 2. `$R` für alles außerhalb der Datenstruktur

- Hilfswerte, die nicht zum Pattern gehören, laufen über `$R`: Seiten-/Modul-Id (`R[Id]`, `R[Page]`, `R[ModuleId]`), Passwort-Eingabe, Meldungen (`$R['Msg']`, `$R['Info']`), Zustand der Seite (`$R['Gallery']['Locked']`), Filter, Upload-Zusatzinfos.
- Im Template: `$D.R.<Name>`. `$R` und `$D` sind getrennt und können sich nicht überschneiden. In `$D` stehen nur Daten aus der Datenbank.

## 3. Eingaben aus dem Formular

- **Minimaler Speicherweg ist verbindlich:** Bei einfachen Pattern-Formularen genuegen die Aktionspruefung und `$C['<modul>']['CData']->set_object($D)`. Keine vorsorglichen rekursiven Validierungsschleifen, Feld-Whitelists, Typkopien oder `throw new`-Pruefungen davor setzen. Eine konkret benoetigte Fachregel ist gesondert zu klaeren; bestehende Berechtigungspruefungen und ausdruecklich vorhandene Schutzregeln bleiben erhalten.
- **Keine Interpretation als Zusatzauftrag:** Nicht verlangte Funktionen, Uploadwege, Meldungen oder Fehlerfaelle nicht eigenmaechtig ergaenzen. Bei einer Pattern-Anpassung bestehende Anordnung und Bedienung erhalten; keine neuen Karten, Item-Rahmen oder Unterdialoge erfinden. `input.tpl` uebernimmt die Aenderungserkennung und unterdrueckt unveraenderte Felder; keine zweite allgemeine Filter-/Speicherlogik dafuer bauen.
- **Standard fuer neue und ueberarbeitete CData-Formulare:** Geaenderte Felder und Unterdaten direkt als Pattern-Teilbaum `D[...]` senden und in der Speicheraktion `set_object($D)` aufrufen. Keinen vollstaendigen Datenbaum aufbauen oder den bisherigen Bestand nur zur Zusammenfuehrung vor dem Speichern laden. Ein eigener Update-Baum oder eine Bestandsabfrage nur bei fachlicher Notwendigkeit, z.B. fuer Kollisionspruefungen.
- Ein Teilbaum genügt: `D[ATTRIBUTE][D][XX][Active]=1` ändert nur dieses Feld; nicht übergebene Felder und Unterdaten bleiben erhalten. Leere Feldwerte und Löschmarker wirken ausdrücklich als Löschung; Ausnahmen wie `ForeignKey=1` beachten.
- Formularpfade bleiben beim vorgegebenen Pattern mit `<TYP>[D][<Id>]`. Keine neuen Transportzweige oder CData-Erweiterungen fuer eine Seitenanpassung einfuehren. Zusammengehoerige dynamische Felder erhalten ihre gemeinsame ID bei Bedarf per JavaScript; vorhandene IDs beibehalten.
- Bibliotheken wie CData bei Arbeiten an anderen Modulen niemals automatisch erweitern. Bei einem triftigen Grund diesen zuerst erlaeutern, nachfragen und die ausdrueckliche Zustimmung abwarten. Auch genehmigte Erweiterungen muessen allgemein wiederverwendbar sein und duerfen niemals speziell auf ein einzelnes Modul zugeschnitten werden.
- Bestehende Funktionen und Schutzmechanismen nicht beschaedigen, abschwaechen oder umgehen, um neue Anforderungen zu erfuellen. Konflikte und technische Grenzen vor der Umsetzung offenlegen und das Vorgehen abstimmen. Bei aktiver Feldnamen-Security vollstaendige Feldpfade registrieren; keine lesbaren Restpfade an gehashte Praefixe anhaengen.
- Loeschungen nach Moeglichkeit bereits als Datensatzwert `__DELETE__` im Pattern-Pfad senden, statt in PHP einen separaten Entwurfsbaum umzuwandeln. Das Frontend muss dabei nur geaenderte Zweige uebertragen, nicht den vollstaendigen Bestand.
- IDs prüfen (`/^[A-Za-z0-9_-]+$/`), Sprach-Codes prüfen (`/^[A-Za-z]{2,8}$/`). Werte, die in Kommandozeilen oder Pfade gelangen, immer validieren.
- Was `CData::set_object` selbst übernimmt: nur Typen und Felder aus dem Pattern (Unbekanntes wird ignoriert) und **IDs werden escaped** (auch beim Löschen und im Cache). `checkbox` und `bool` normalisieren `1`, `'1'` und `true` zu `1`, `0`, `'0'`, `false` und andere Werte außer `null`, `''`, `'__DELETE__'` zu `0`. Diese drei Ausnahmen löschen normalerweise das Feld; bei `ForeignKey=1` werden `''` und `'__DELETE__'` gespeichert. `Active=true` kann wegen der Datensatz-Löschprüfung vor der Normalisierung den Datensatz löschen; Formulare senden hier `1`/`0`. Die übrigen Pattern-Typen wie `number` und `id` werden noch nicht auf ihren Wert geprüft.
- Pflichtfelder im Formular werden vom Browser geprueft. `CData::set_object` erzwingt derzeit keine Pflichtfeldregeln; ein direkter Request kann die Browserpruefung umgehen. Unbekannte Felder filtert CData anhand des Patterns, aber im Pattern vorhandene geschuetzte Felder und Loeschmarker muessen weiterhin durch die Seite begrenzt werden.
- Keine individuellen seitenbezogenen CSRF-/Security-Token einbauen; ein spaeteres Security-Konzept soll diese Aufgaben zentral behandeln. Die Feldnamen-Security ist derzeit abschaltbar; das globale Konzept und eine dauerhafte Aktivierung sind noch nicht umgesetzt. Bestehende Berechtigungspruefungen und zweckgebundene Upload-Token bleiben davon unberuehrt.
- Was `set_object` **nicht** übernimmt, bleibt soweit erforderlich Aufgabe der Seite: Typpruefung ausser `checkbox` und `bool`, welche geschuetzten Felder ein Formular schreiben darf (z.B. `LINK`, `FileId`, `Password`), Loeschbefehle (`__DELETE__`, `Active = -2`), Berechtigungen und inhaltliche Regeln.
- Bei einem Redirect nach dem Speichern den Request direkt beenden; der folgende GET laedt die Daten neu. Ohne Redirect im selben Request neu laden: `get_object` ergaenzt `$D`, deshalb bei Bedarf den betroffenen Zweig vorher entfernen, damit insbesondere geloeschte Daten nicht stehen bleiben. Fuer AJAX-Antworten den benoetigten Datenbaum im selben Request korrekt neu laden.
- Beim regulären Formular-Submit deaktiviert `input.tpl` Felder mit unverändertem `ischanged`-Status. Fehlt ein Feld im Request, bedeutet das "unverändert"; ein gesendetes leeres Feld ist eine bewusste Änderung. Eigene AJAX-Speicherwege müssen ihr Sendeverhalten ausdrücklich berücksichtigen.
- Fuer einfache Speicheraktionen genuegt die vorhandene Aktionspruefung, z.B. `($D['ACTION'] ?? null) === 'save'`; keine zusaetzliche Pruefung der HTTP-Methode nur aus Gewohnheit. GET ist damit ebenfalls moeglich und kann etwa durch Vorabrufe ausgeloest werden; wo Schutz vor unbeabsichtigten Aktionen erforderlich ist, die Methode bewusst einschraenken.
- Geheimnisse (z.B. Passwörter) vor der Template-Ausgabe aus dem jeweiligen Datenzweig entfernen. Bei gesperrten Inhalten auch die Unterdaten (`IMAGE`) entfernen.

## 4. Löschen

- Standard: Löschaktion bestätigen, im Formular markieren und erst beim Speichern ausführen. Für CData den Datensatzwert auf `'__DELETE__'` setzen; bei einem separaten `__DELETE__=1`-Flag muss die Seite dieses vor `set_object` umwandeln. Abweichende sofortige Aktionen nur bei entsprechender Anforderung.
- Zugehörige SEO-Links oder Dateien nur nach fachlicher Regel bereinigen; Dateien nur löschen, wenn sie nicht anderweitig verwendet werden.

## 5. Datenbank, Patterns, Dateien, Links

- Jedes Modul mit eigener Datenbank legt in `init.php` `$C['<vendor/modul>']['CData']` an und registriert sein Pattern. Das Core ergänzt automatisch `LINK`, `FILE`, `MIGRATION` und das Objekt `$C['<vendor/modul>']['File']`.
- **Dateien** nur über den File-Dienst des eigenen Moduls (`$C['fremeo/gallery']['File']->add(...)`): schreibt nur in den eigenen Ordner und lehnt ausführbare Endungen ab.
- **SEO-Links** nur über `$C['Link']` (`create`, `rename`, `deleteById`, `getById`, `normalize`). Ist eine URL vergeben, hängt `Link` selbst `_1`, `_2` ... an, auch bei der leeren URL (Startseite). Geprüft wird über alle Module.
- Der Vorschlag der SEO-URL aus dem Titel entsteht per **JS im Template** (Feld als geändert markieren mit `input_onmouseout(el)`), der Server erzeugt sie nicht aus dem Titel. Leeres, gesendetes Feld = Startseite.
- Zeitangaben als **Unix-Timestamp** (Zahl) speichern, Format erst im Template (`{date('d.m.Y H:i:s', (int)$x)}`). Ein Datum nie durch den Upload-Zeitpunkt ersetzen, wenn es aus den Daten gelesen werden soll (EXIF `DateTimeOriginal`, sonst Dateidatum des Browsers, sonst kein Wert).
- Werden neue Datensatz-IDs mit CRC32 erzeugt, dafuer einen Timestamp statt eines Zufallswerts verwenden und Kollisionen im jeweiligen ID-Bereich pruefen. CRC32 ist kein Sicherheits-Token; bestehende Hashes fuer andere Zwecke bleiben unveraendert.
- Datenbanken wachsen per Pattern, Änderungen an bestehenden Daten laufen über Migrationen (`system/migration/*.php`).

## 6. Seiten und Templates

- Zu `system/<name>.php` gehört `system/template/<name>.tpl`. Die Seite läuft im Scope von `start.php`: Variablen (`$U`, `$old`, `$f` ...) am Anfang initialisieren, damit nichts aus anderen Dateien der Kette durchsickert.
- Smarty läuft mit Security-Policy: nur freigegebene PHP-Funktionen (`date`, `count`, `in_array`, `strtolower`, `implode` ...). Was fehlt, im Pfad der PHP-Seite vorbereiten oder in `start.php` freigeben.
- Ausgabe immer escapen (`|escape`), auch in `data-`-Attributen und JavaScript-Strings (`|escape:'javascript'`).
- Fehlende Felder im Template mit `|default:''` abfangen (nicht gesetzte Felder fehlen im `$D`).
- Formularelemente bevorzugt ueber `{input p=[...]}`; der Name spiegelt das Pattern (`D[PAGE][D][{$id}][Title]`). Fehlt ein Element oder muss `input.tpl` erweitert werden, vor einer Erweiterung oder eigenen Ersatzloesung den Benutzer nach dem gewuenschten Vorgehen fragen.
- Bei dynamischen Pattern-Feldern kann ein registrierter Security-Praefix wie `D[ATTRIBUTE]` beim Versand um die uebrigen Pfadteile erweitert werden. Die sichtbaren Feldnamen bleiben vollstaendige `D[...]`-Patternpfade; nach der Security-Aufloesung liegt der Datenbaum in `$D`. Dynamische IDs und schreibbare Felder weiterhin serverseitig pruefen.
- JavaScript in `{literal}...{/literal}`. Zähler (z.B. angezeigte Bilder) im Template mit Smarty-Variablen führen, wenn das Template selbst filtert.
- Bibliotheken (`type: library`) haben weder Tabs noch Templates und kommen in Admin-Listen nicht vor.
- Moduluebergreifende Verwaltungsseiten (z.B. Einstellungen und Sprachvariablen) greifen nur auf aktive Module zu: deaktivierte Module besitzen keine initialisierten Dienste. Ihre gespeicherten Werte bleiben erhalten und werden nach dem Aktivieren wieder angezeigt.

### Verbindliche Aktionsleiste im Backend

- Backend-Tabellen, Listen und Seitenformulare erhalten eine dauerhaft sichtbare untere Aktionsleiste wie in `admin__menu.list.tpl`. Sie liegt im Block `inner_footer` ausserhalb des scrollbaren Inhalts (`inner-frame`), reserviert ihren eigenen Platz und verdeckt auch am Scrollende keine Zeilen oder Eingaben. Kein schwebendes Overlay mit `position: fixed`. Dialogformulare verwenden ihre Aktionen im Fenster (siehe Gemeinsames Admin-Fenster).
- Dies gilt ebenso fuer Bearbeitungsformulare aller Module (z.B. Gallery-Album bearbeiten), nicht nur fuer Core-Seiten. Keine zusaetzlichen Speichern-/Abbrechen-Buttons am Ende des scrollbaren Formularinhalts. Externe Submit-Buttons muessen auch Formularvalidierung, Upload-Warteschlangen und deren Sperr-/Wiederholungsverhalten unveraendert ausloesen.
- **Links:** Speichern und Abbrechen. Speichern ist mit dem zugehoerigen Formular ueber `type="submit"` und `form="<formular-id>"` verbunden. Abbrechen verwirft ungespeicherte Eingaben ohne Speichern oder Loeschen.
- **Rechts:** je nach Seite Paging, Filter oder weitere formularbezogene Funktionen. Filter und andere Aktionen duerfen nicht versehentlich das Speicherformular absenden; ihren Aktionstyp bzw. ihre Formularzuordnung explizit setzen.
- Theme-faehige Bootstrap-Klassen verwenden, beispielsweise `bg-body-tertiary border-top p-2`; keine fest hellen Hintergrundfarben. Die beiden Bereiche mit Flexbox links/rechts ausrichten und auf schmalen Ansichten umbrechen lassen.

### Horizontales Scrollen im Backend

- Backend-Seiten aller Module muessen auf Desktop-Breiten ohne horizontalen Seiten- oder Inhalts-Scrollbalken auskommen. Formularfelder, Raster und Aktionsleisten an die verfuegbare Breite anpassen; lange Texte umbrechen und Flex-/Grid-Kindern bei Bedarf `min-width: 0` geben.
- Bootstrap-Raster brauchen ausreichend Platz fuer ihre negativen Aussenabstaende. In ungepolsterten Formularen diese z.B. mit `row g-3 mx-0` ausgleichen, ohne die Abstaende zwischen den Feldern zu entfernen.
- Ausnahme: Inhalte, die ihre Breite fachlich benoetigen (z.B. breite Datentabellen), duerfen gezielt innerhalb eines eigenen responsiven Scrollcontainers horizontal scrollen. Nicht die gesamte Seite verbreitern und Ueberlaeufe nicht pauschal mit `overflow-x: hidden` abschneiden.
- Desktop-Ansichten bei mehreren Breiten und mit sichtbarer vertikaler Scrollleiste pruefen: ohne notwendige Ausnahme muss `scrollWidth <= clientWidth` fuer Dokument und Inhaltsbereich gelten.

## 7. Composer und Module

- Modul-Eltern ausschliesslich mit `extra.module-parent` in `composer.json` deklarieren (z.B. `"extra": {"module-parent": "fremeo/frontend"}`). Das Elternpaket muss auch unter `require` stehen. Paketnamen besitzen keine Vererbungsbedeutung; `__` bleibt nur fuer TPL-Dateien und Theme-Ableitungen bestehen. Globale Patterns und Dienste bleiben unveraendert.

- Composer-Klasse und PHAR liegen ausschliesslich im Loader unter `setup/Composer.php` und `setup/composer.phar`, nie im Core oder als Laufzeitkopien im Cache. Core delegiert Composer-Aktionen ueber `PackageManager`; ohne Setup bleiben Paketliste und Anwendung nutzbar, nur Composer-Aktionen werden mit sichtbarem Hinweis gesperrt. Setup nicht waehrend laufender Paketaktionen ersetzen.
- Das Setup lädt nur seine Kopie, das Projekt nur die des Cores.
- In `composer.json` kein eigenes Top-Level-Feld `version` eintragen, auch nicht bei Child-Templates. Composer ermittelt die Paketversion aus Git-Tags bzw. dem VCS-Stand; Versionsbedingungen unter `require` bleiben erhalten. Nach dem letzten GitHub-Tag die naechste fortlaufende Version im Changelog fuehren und alle weiteren Aenderungen bis zum naechsten Tag dort sammeln; beim Taggen muessen Changelog-Version und Tag uebereinstimmen (z.B. `0.1.5` und `v0.1.5`). Kompatibilitaetsgrenzen bei `0.x` pruefen. Bestehende Tags nicht nachtraeglich verschieben.
- Bei jeder Modulaenderung den Changelog im vorhandenen Format aktualisieren. Fuer Modul-Changelogs das Core-Format `CHANGELOG` mit Versionsabschnitt `#<Version>` ohne Zusatz und Legende verwenden; keine ungesicherte Historie erfinden.
- Benoetigt eine Aenderung eine neuere Funktion, Datenstruktur oder einen neuen Template-Block eines anderen Moduls oder des Cores, muss dessen Mindestversion unter `require` in der `composer.json` des abhaengigen Moduls angehoben werden. Zuerst die Abhaengigkeit mit der benoetigten Version veroeffentlichen, danach das abhaengige Modul. Keine Versionsanhebung ohne tatsaechlichen Bedarf; kompatible Obergrenzen beibehalten (z.B. `>=0.1.5 <1.0`).
- Vor einer Veroeffentlichung pruefen, dass Composer keine aeltere, funktional unzureichende Abhaengigkeit installieren kann. Ein vorhandenes `composer.lock` muss zum neuen Constraint passen; ein reines `composer install` aktualisiert einen veralteten Lock-Stand nicht automatisch.
- Bei `0.0.x` beschraenkt `^0.0.4` den Bereich auf `>=0.0.4 <0.0.5`; bei Bedarf eine passende explizite Spanne verwenden.

## 8. Tests

- Jedes Modul hat einen `tests/`-Ordner mit `phpunit.xml` und `tests/bootstrap.php`. Neue Seiten und Fehlerkorrekturen bekommen einen Test.
- Integrationstests binden die echte Seite ein (`include __DIR__ . '/../system/<name>.php'`) und arbeiten mit temporären Datenbanken (`sys_get_temp_dir()`), die im `tearDown` entfernt werden.
- Gleichen Aufbau wie im Core verwenden: `$C`, `$D`, `$R` als Referenzen in einer Closure, `$D['R'] = &$R`.
- Hilfsmethoden in Testklassen nicht `run()`, `runTest()` o.ä. nennen (final in `TestCase`).
- PHPUnit ist im Core als Entwicklungsabhaengigkeit definiert. Im Loader-Projekt kann die Shop-Suite mit `php system\vendor\fremeo\core\vendor\phpunit\phpunit\phpunit --configuration system\vendor\fremeo\shop\phpunit.xml` ausgefuehrt werden; der Shop-Bootstrap laedt dabei den Autoloader des Projekts. Fehlt der Runner, die Core-Entwicklungsabhaengigkeiten aus dessen Lockfile mit Composer installieren.

## 9. Programmierstil

- Code **kurz** und lesbar halten, wie ein Mensch ihn schreibt: keine Abstraktionen für einmalige Dinge, keine Fehlerbehandlung für Fälle, die nicht vorkommen, nur an Systemgrenzen (Formular, Dateien, Netz) prüfen.
- Kommentare erklären das Warum, nicht das Wie. Deutsch, einzeilig.
- Bestehende Dateien bearbeiten statt neue anlegen. Nichts umbauen, was nicht verlangt ist.
- Bei unklarer Umsetzung immer vor der Aenderung nachfragen; Anforderungen nicht eigenmaechtig interpretieren und umsetzen.
- Änderungen an Dateien nicht gleichzeitig per Editor und Terminal ausführen (die zweite überschreibt die erste).

## 10. Informationen
- Page-Builder-Konzept: `system/vendor/fremeo/core/docs/Konzept_Page_Builder.md`. Bei jeder Builder-Aenderung oder -Erweiterung im selben Arbeitsschritt aktualisieren; offene Entscheidungen vor Umsetzung klaeren.
- Verbindliche, laufend gepflegte Regeln für KI und Entwickler: `system/vendor/fremeo/core/docs/Entwicklungsrichtlinien_KI.md` (vor Änderungen lesen).
- Weitere informationen könnten im docs/ Ordner des Haupt Projekts oder je Modul hinterlegt sein, diese detalierte Beschreibung ggf. zur Nutzung der Klassen liefert.