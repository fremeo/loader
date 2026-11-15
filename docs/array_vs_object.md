## Architektur-Entscheidung: Warum wir multidimensionale Arrays für dynamische Daten nutzen

In der modernen PHP-Entwicklung wird oft reflexartig gefordert, alles in Objekte (`Klassen`, `DTOs`, `Entities`) zu gießen. Wer in Projekten multidimensionale Arrays für Datenstrukturen nutzt, erntet nicht selten skeptische Blicke. 

Für das **fremeo-Framework** wurde sich jedoch **bewusst und aus Performance-Gründen** für den Einsatz von multidimensionalen Arrays bei dynamischen Datenflüssen entschieden. Hier ist die technische Begründung für diese Kern-Architektur.

---

### Der Trugschluss: „Objekte sind in PHP 8 immer schneller und speicherschonender“

Es stimmt, dass das PHP-Kernteam typisierte Klassen (`class User { public int $id; }`) massiv optimiert hat. Wenn eine Struktur **starr und zur Compile-Zeit bekannt** ist, verbrauchen Objekte weniger RAM als assoziative Arrays, da PHP die Schlüssel (Keys) nur einmal im Speicher ablegen muss.

**Das Problem:** Diese Optimierung greift bei unserer flexiblen Architektur nicht. 

Im fremeo-Framework bestimmen **Module und Pattern zur Laufzeit**, wie Datenstrukturen aussehen. Datenbank-Schemata und Konfigurationen sind dynamisch veränderbar. 

Würden wir hier auf Objekte setzen, müssten wir entweder:
1. Dynamische Eigenschaften nutzen (`#[AllowDynamicProperties]`) oder
2. Daten in generische "Wrapper-Klassen" (mit `__get()` und `__set()` Magic Methods) kapseln.

Sobald ein Objekt jedoch dynamisch befüllt wird, verliert PHP die Fähigkeit, feste Speicher-Offsets zu nutzen. PHP muss im Hintergrund für jedes einzelne Objekt wieder eine interne Hash-Tabelle anlegen. **Der Performance-Vorteil von Objekten ist damit vollständig dahin.**

---

### Warum Multidimensionale Arrays für uns die solidere Wahl sind

Bei tief verschachtelten, dynamischen Laufzeit-Strukturen übertreffen pure PHP-Arrays den Ansatz von dynamischen Objekten in jeglicher Hinsicht:

#### 1. Massiver RAM-Overhead bei tiefen Objekt-Strukturen (Rekursion)
Wenn Daten multidimensional verschachtelt sind (z. B. `$user->einstellungen->benachrichtigungen->email`), müsste jede Ebene ein eigenes, dynamisches Unter-Objekt instanziieren. 
Wenn du 1.000 Datensätze mit 3 Verschachtelungsebenen lädst, erzeugt die Objekt-Variante **4.000 PHP-Objektinstanzen** im Arbeitsspeicher. Jede Instanz bringt einen massiven Speicher-Overhead (Object Header) mit sich. Ein reines, multidimensionales Array speichert diese Hierarchie nativ in einer einzigen, hochoptimierten C-Struktur des PHP-Kerns.

#### 2. CPU-Verlust durch "Magische Methoden" (`__get()`)
Der Zugriff auf dynamische Objekte erfolgt unweigerlich über Magie: `__get()`. Jedes Mal, wenn im Code oder im Template (z. B. in Smarty 4) auf eine Eigenschaft zugegriffen wird, muss PHP einen Funktionsaufruf (`__get`) im Userland triggern, der intern das Array durchsucht. Bei komplexen Seitenaufrufen mit tausenden Datenpunkten führt das zu spürbaren CPU-Zyklen. Der direkte Zugriff auf ein Array (`$data['key']['sub_key']`) geschieht direkt auf Engine-Ebene ohne Funktions-Overhead.

#### 3. Nahtlose Integration in Smarty 4
Unser schlankes Framework basiert auf der mächtigen Vererbungsstruktur von Smarty 4 Templates. Smarty verarbeitet native PHP-Arrays extrem effizient. Die Übergabe von Arrays an Smarty erfordert null Vorverarbeitung oder Mapping – die Daten fließen direkt und ungebremst in das Rendering.

---

### Fazit

Wir nutzen Objekte dort, wo sie hingehören: Für die **Architektur des Frameworks** (Router, Request/Response-Handling, Loader, Core-Services). Hier profitieren wir von Typsicherheit und OOP.

Für die **Nutzdaten der Module** (Datenbank-Ergebnisse, flexible Pattern-Strukturen, dynamische Konfigurationen) nutzen wir **multimensionale Arrays**. Sie garantieren, dass das fremeo-Framework das bleibt, was es sein soll: **Kompromisslos schlank, extrem ressourcensparend und rasend schnell.**
