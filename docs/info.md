# Was ist der Loader?

Der Loader ist im Grunde eine Hülle. Erst durch das Zusammenfügen verschiedener Module wird er zu der Software, für die er bestimmt ist. Aus diesem Grund enthält der Loader so wenig Logik wie möglich – gerade genug, um den Kern (Core) zu laden.

## Core
Der Core, der sich logischerweise auch direkt im Loader befinden könnte, wurde als eigenes Modul ausgelagert.

- Vorteil: Die Update-Logik für den Core muss nicht separat umgesetzt werden. Er kann stattdessen einfach zusammen mit den restlichen Modulen aktualisiert werden.

Weitere Besonderheiten des Cores können in der dedizierten Core-Dokumentation (beim core-Modul selbst) nachgelesen werden.


## Setup
Der Ordner `setup` im Root-Verzeichnis beinhaltet eine unabhängige Software zur Installation und Aktualisierung des Projekts.

### Verzeichnisschutz & Sicherheit
* **Erster Aufruf:** Aus Sicherheitsgründen wird beim ersten Ausführen automatisch ein Verzeichnisschutz eingerichtet. Das generierte Passwort wird **nur einmalig angezeigt**.
* **Passwort ändern:** Das Passwort kann nachträglich manuell in der `.htpasswd`-Datei geändert werden.
* **Windows vs. Linux:** In einer lokalen Windows-Umgebung wird das Passwort unverschlüsselt gespeichert. Bei einem Umzug auf einen Linux-Server sollte der Verzeichnisschutz daher aus Sicherheitsgründen neu generiert werden.

### Installation & Admin-Zugang
Nach der Ersteinrichtung erfolgt eine automatische Weiterleitung zur Composer-Konsole. Über den Befehl `install` wird die Software installiert. Dabei wird direkt ein Administrator-Konto angelegt. Die Zugangsdaten werden **nur einmalig in der Konsole ausgegeben**.

### Funktionen der Konsole
Die Konsole steht auch in Zukunft für folgende Aufgaben bereit:
* Nachinstallation von Modulen
* Einspielen von Updates
* Deinstallation von Modulen
* **Notfall-System:** Sollte die Hauptsoftware durch einen *Fatal Error* nicht mehr erreichbar sein, können über die Setup-Umgebung Reparaturen durchgeführt werden.
* **Voller Funktionsumfang:** Es wird der komplette Composer-Umfang unterstützt.

### Wichtiger Hinweis zu Nutzdaten
Nutzdaten im Ordner `data` bleiben von Deinstallationen (z. B. von Modulen) unberührt. Wenn Sie ein Modul deinstallieren und dessen gespeicherte Daten ebenfalls löschen möchten, müssen Sie den entsprechenden Ordner manuell via FTP aus dem `data`-Verzeichnis entfernen.
