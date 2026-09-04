## Arten von Modul Typen
- module: Ein Modul sind Erweiterungen der Anwendung, die zusätzliche Funktionalitäten bereitstellen. Diese Module können eigenständige Features oder Dienste enthalten, die in die Hauptanwendung integriert werden.
- core: Das Core-Modul ist das Herzstück der Anwendung. Es enthält die grundlegenden Funktionen, die von anderen Modulen genutzt werden.
- template: Ein Template-Modul stellt die visuelle Darstellung der Anwendung bereit. Es enthält Layouts, Stylesheets, Skripte und andere Ressourcen, die das Erscheinungsbild der Anwendung definieren.
- liberary: Ein Library-Modul bietet wiederverwendbare Funktionen oder Klassen, die von anderen Modulen genutzt werden können. Es kann Hilfsfunktionen, Dienstprogramme oder andere wiederverwendbare Komponenten enthalten.

## Keywords von Modulen
Keywords werden in der `composer.json` Datei eines Moduls definiert und dienen dazu, das Modul zu kategorisieren und zu identifizieren. Sie helfen Entwicklern, Module basierend auf ihren Funktionen oder Typen zu finden.
Der Module Store benutzt keywords, um Module zu filtern und zu durchsuchen.
- 1. Entwickler sollten "fremeo" als Keyword für alle Module verwenden, um die Zugehörigkeit zu der fremeo Plattform zu kennzeichnen.
- 2. Entwickler sollen "fremeo-[Type]" als Keyword für alle Module verwenden, um den Typ des Moduls zu kennzeichnen.
- 3. Entwickler sollen als dritten Keyword den Namen des Moduls verwenden, um das spezifische Modul zu identifizieren.
- 4. Optional können Entwickler weitere Keywords hinzufügen, die die Funktionalität oder den Zweck des Moduls beschreiben.