# Projektrichtlinien

Vor Aenderungen den [Programmierleitfaden fuer Module](Programmierleitfaden_Module.md)
und die [Entwicklungsrichtlinien](../system/vendor/fremeo/core/docs/Entwicklungsrichtlinien_KI.md) lesen.
Fachkonzepte der betroffenen Module ebenfalls beruecksichtigen.

- Vorhandenen Code und die kleinste passende Loesung bevorzugen; keine einmalig
  verwendeten Aliase, Kopien oder Hilfsfunktionen ohne sachlichen Grund.
- Nur ausdruecklich Beauftragtes umsetzen; fehlende Anforderungen nicht als
  Auftrag interpretieren. Keine ungefragten Validierungsschleifen, Feld-Whitelists,
  `throw new`-Pruefungen, Uploadwege oder Layout-Umbauten ergaenzen.
  Bestehende Masken nur an den betroffenen Datenpfaden minimal anpassen.
- Bestehende Funktionen und Schutzmechanismen niemals fuer eine neue Loesung
  beschaedigen, abschwaechen oder umgehen. Bei Konflikten vor der Umsetzung
  auf die Einschraenkung hinweisen und das weitere Vorgehen abstimmen;
  insbesondere registrierte Security-Feldnamen nicht per JavaScript umgehen.
- Bibliotheken wie CData bei Arbeiten an anderen Modulen niemals automatisch
  erweitern. Bei einem triftigen Grund zuerst begruenden, nachfragen und die
  ausdrueckliche Zustimmung abwarten. Genehmigte Erweiterungen muessen allgemein
  wiederverwendbar sein und duerfen niemals auf ein einzelnes Modul zugeschnitten sein.
- CData-Formulare senden geaenderte Pattern-Teilbaeume direkt in `$D`; fehlende
  Felder und Unterdaten bleiben unveraendert. Standardmaessig beim Speichern
  direkt `set_object($D)` aufrufen, ohne den Bestand vorher nur fuer eine
  vollstaendige Zusammenfuehrung zu laden. Filter in `$F` schrittweise aufbauen
  und Modulfilter danach entfernen. Schutz, fachlich notwendige Bestandspruefungen
  und korrektes Neuladen weiterhin beruecksichtigen.
- Eine einfache CData-Speicheraktion besteht aus Aktionspruefung und
  `set_object($D)`. Keine vorsorgliche rekursive Vorverarbeitung des
  Pattern-Baums und keine eigene Aenderungsfilterung neben `input.tpl`.
  Konkrete zusaetzliche Fachlogik zuerst klaeren, nicht hineininterpretieren.
- Nach einem Redirect den Request beenden; nicht im selben Request Daten erneut
  laden oder fuer die Anzeige bereinigen. Ohne Redirect beim Neuladen beachten,
  dass `get_object` vorhandene Zweige ergaenzt und geloeschte Werte stehen bleiben koennen.
- Formularfelder bevorzugt mit `{input}` und vollstaendigem `D[...]`-Patternpfad
  erstellen. Dynamische Felder und Security-Namen mitpruefen. Fehlende
  Eingabetypen oder Ersatzloesungen vor einer Erweiterung mit dem Nutzer klaeren.
- Keine individuellen seitenbezogenen CSRF-/Security-Token einfuehren; ein
  spaeteres Security-Konzept soll solche Schutzmechanismen zentral behandeln.
  Vorhandene Berechtigungspruefungen und zweckgebundene Upload-Token beibehalten.
- CRC32-Datensatz-IDs aus einem Timestamp erzeugen und Kollisionen pruefen;
  fuer Sicherheits-Token keine CRC32-IDs verwenden.
- Keine ungefragten Funktionen oder Meldungen fuer fehlende Eingaben hinzufuegen.
  Die geforderten Tests ausfuehren und Einschraenkungen offen benennen.
