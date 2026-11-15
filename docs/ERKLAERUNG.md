# Der Code erklärt

Diese Datei erklärt, wie Stadt Services aufgebaut ist und warum.
Sie hilft auch, das Projekt in einem Bewerbungsgespräch zu erklären.

## Ablauf aus Sicht des Nutzers

1. Der Nutzer tippt einen Ort ins Suchfeld. Unter dem Feld erscheinen passende Vorschläge.
2. Er wählt einen Ort. Die Suche verschwindet, sechs Karten mit Diensten erscheinen.
3. Er klickt auf eine Karte. Ein Fenster (Overlay) öffnet sich und zeigt erst „Daten werden geladen…“.
4. Die Anwendung fragt die passende API ab und schreibt das Ergebnis in das Fenster.

## Die wichtigsten Teile in index.html

**Suche (`searchInput`, Ereignis `input`)**
Bei jedem Tastendruck wird die Liste `germanStates` gefiltert: `filter(...)` behält alle Einträge, die den getippten Text enthalten (`includes`).
Die Liste selbst kommt aus `landkreise.json` und wird beim Start mit `fetch` geladen, ergänzt um die 16 Bundesländer.

**`selectState(state)`**
Merkt sich den gewählten Ort in der Variable `currentState` und blendet die Dienste ein. Alle späteren Abfragen benutzen `currentState`.

**`openServiceOverlay(serviceType)`**
Öffnet das Fenster, setzt den Titel und ruft `loadServiceData` auf.

**`loadServiceData(serviceType)`**
Ein `switch` mit einem `case` pro Dienst. Jeder `case` folgt demselben Muster:

```js
fetch(adresse)                 // 1. Anfrage schicken
  .then(res => res.json())     // 2. Antwort von Text in ein Objekt umwandeln
  .then(data => { ... })       // 3. HTML bauen und in overlayBody schreiben
  .catch(err => { ... });      // 4. Bei einem Fehler eine Meldung zeigen
```

## Die Dienste im Einzelnen

**Bilder (Unsplash):** Eine Abfrage mit dem Ortsnamen als Suchwort. Aus der Liste `data.results` wird mit `map` für jedes Bild ein `<img>` erzeugt und mit `join('')` zu einem Text verbunden.

**Nachrichten (GNews):** Gleiches Muster. `slice(0, 10)` begrenzt auf zehn Artikel.

**Wetter (OpenWeatherMap):** Hier laufen zwei Abfragen gleichzeitig, eine für das aktuelle Wetter und eine für die Vorhersage. `Promise.all([...])` wartet, bis beide fertig sind.
Die Vorhersage kommt in Schritten von drei Stunden. Der Code nimmt pro Tag nur den Wert von 12 Uhr.

**Feiertage (Nager.Date):** Liefert alle Feiertage eines Jahres. Das Jahr steht nicht fest im Code: `const JAHR = new Date().getFullYear()` nimmt immer das laufende Jahr. `renderFeiertagsKalender` baut daraus eine Tabelle mit 31 Zeilen (Tage) und 12 Spalten (Monate) und färbt die Feiertage ein.

**Schulferien (ferien-api):** Ferien hängen vom Bundesland ab. Deshalb gibt es zwei Schritte: Erst fragt die Anwendung bei OpenPLZ, in welchem Bundesland der Ort liegt. Dann holt sie mit dem Kürzel des Bundeslands (zum Beispiel `HB` für Bremen) die Ferien.

**Statistiken (GENESIS über PHP):** Ein kleiner Assistent in zwei Schritten (Zeitraum, Thema). Danach geht die Abfrage an das eigene PHP-Skript, und Chart.js zeichnet die Diagramme.
Chart.js wird erst geladen, wenn der Nutzer die Statistik öffnet. Das spart Ladezeit beim Start.

## genesis-statistics.php

1. **Parameter lesen und prüfen:** `category`, `state`, `startYear`, `endYear` kommen aus der Adresse (`$_GET`). Fehlt etwas oder ist die Kategorie unbekannt, antwortet das Skript mit einer Fehlermeldung.
2. **Kategorie in Tabellennummer übersetzen:** Das Statistische Bundesamt nummeriert seine Tabellen. `population` wird zu `12521-0020`. Weil nur Werte aus dieser festen Liste erlaubt sind, kann niemand beliebige Tabellen abfragen.
3. **Abfrage mit cURL:** Das Skript ruft die GENESIS-API auf und bekommt Text mit Semikolons zurück.
4. **Text zerlegen (`parseGenesisFlexible`):** Zeile für Zeile mit `explode(';', ...)`. Die Funktion erkennt die Zeile mit den Jahren und ob es eine Spalte für das Geschlecht gibt.
5. **Filtern und ausgeben:** Nur das gewählte Bundesland und der gewählte Zeitraum bleiben übrig. `json_encode` macht daraus JSON für den Browser.

**Warum PHP und nicht direkt aus dem Browser?**
Erstens bleibt der Zugangs-Token auf dem Server. Zweitens bekommt der Browser fertiges JSON und muss das Textformat nicht kennen.

## Die API-Schlüssel

In der ersten Version standen vier Schlüssel direkt im Code und damit öffentlich auf GitHub. Jeder konnte sie kopieren und benutzen.

Jetzt gilt:

- Die Schlüssel für den Browser stehen in `config.js`, der Token für PHP in `config.php` (oder in der Umgebungsvariable `GENESIS_TOKEN`).
- Beide Dateien stehen in `.gitignore`. Git lädt sie nicht hoch.
- Im Repository liegen nur die Vorlagen `config.example.js` und `config.example.php` ohne echte Werte.

Zwei weitere Änderungen an der Sicherheit im PHP-Skript:

- `display_errors` ist aus. Fehlermeldungen von PHP verraten sonst Dateipfade und zerstören die JSON-Antwort.
- Die Prüfung des TLS-Zertifikats ist wieder an (`CURLOPT_SSL_VERIFYPEER` war auf `false`). Ohne sie könnte sich ein fremder Server als Statistisches Bundesamt ausgeben.

## Mögliche Fragen im Gespräch

- **Was war die größte Schwierigkeit?** Jede API hat ein anderes Format und andere Regeln: Schlüssel in der Adresse, verschiedene Fehlercodes, bei GENESIS Text statt JSON. Dazu kommt die Abhängigkeit zwischen Abfragen, zum Beispiel erst Bundesland, dann Ferien.
- **Was passiert, wenn eine API ausfällt?** Jeder Dienst hat ein eigenes `catch`. Fällt einer aus, zeigt nur dieses Fenster eine Fehlermeldung, die anderen Dienste laufen weiter.
- **Was würdest du heute anders machen?** Das JavaScript in Dateien pro Dienst aufteilen, alle Abfragen mit Schlüssel über den Server leiten, und Texte aus fremden APIs mit `textContent` statt `innerHTML` einsetzen, damit kein fremdes HTML in die Seite gelangt.
