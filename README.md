# BB Map Consent 0.1.1

Installierbare erste Testversion für WordPress ab 6.2 / PHP ab 7.4. GPL-2.0-or-later.

## Installation

1. ZIP unter Plugins → Installieren → Plugin hochladen installieren und aktivieren.
2. Unter Einstellungen → Datenschutz die richtige Datenschutzerklärung hinterlegen. Der Platzhalter verlinkt diese automatisch.
3. Seiten-, Divi-, Optimierungs-, CDN- und Browser-Cache vollständig leeren.
4. Zunächst auf einer Testinstallation prüfen. Borlabs erst nach erfolgreicher Prüfung auf der jeweiligen Installation entfernen. Vorher prüfen, welche weiteren Dienste Borlabs dort blockiert.

Es gibt kein zusätzliches Cookie-Banner und keine externe Bibliothek. Der Klick erlaubt sämtliche vom Plugin verwalteten Google-Karten auf der aktuellen Seite. Ohne gesetztes Häkchen gilt die Freigabe nur für die aktuelle Seitenansicht. Optional wird sie für 30 Tage in localStorage gespeichert. Die gespeicherte Freigabe wird bei jedem Seitenaufruf clientseitig ausgewertet; das vom Server erzeugte HTML bleibt immer gesperrt und kann deshalb auch gecacht werden.

## Unterstützt

- Statisch im HTML ausgelieferte Google-Maps-iframes mit src oder data-src, einschließlich der üblichen google.com/maps/embed- und maps.google.de/maps-Adressen.
- Klassische Divi-Shortcodes et_pb_map und et_pb_fullwidth_map einschließlich et_pb_map_pin mit gespeicherten Koordinaten. Das Plugin ersetzt die Divi-Ausgabe durch eine eigene Google-Maps-Darstellung. Kartenzentrum, Zoom, Pin-Koordinaten, Titel und reiner Beschreibungstext werden übernommen. Es benötigt den vorhandenen Divi-Google-API-Schlüssel mit für die Domain freigeschalteter Maps JavaScript API.
- Ein Widerrufsbutton direkt unter jeder geladenen Karte. Optional kann [bb_maps_revoke] zusätzlich in einem Shortcode- oder Textbereich der Datenschutzerklärung stehen. Nicht direkt als Text in ein WordPress-Navigationsmenü einfügen.

## Grenzen dieser Version

- Noch nicht in einer echten WordPress-/Divi-Installation getestet. Divi 5 mit nativem Block-/JSON-Rendering ist nicht als unterstützt bestätigt. Der klassische Shortcode-Pfad wird verarbeitet, die iframe-Sperre ist unabhängig vom Builder.
- Eigene Divi-Kartenfarben, individuelle Modul-CSS-Klassen/IDs, Animationen, exakte Modulhöhe und HTML in Pin-Beschreibungen werden nicht übernommen. Iframes werden responsiv mit der numerischen Originalhöhe (200–1000 px, Standard 400 px) dargestellt.
- Keine Unterstützung für Karten, die Drittanbieter-JavaScript nachträglich einfügt, srcdoc, Google-My-Maps auf anderen URL-Pfaden, eigene Proxy-URLs oder beliebige andere Google-Dienste. Ein MutationObserver wäre keine zuverlässige Netzwerksperre und wird deshalb nicht als solche eingesetzt.
- Direkt ausgegebene Maps-API-script-Tags werden vor der Auslieferung entfernt; eigene Inline-Skripte, die Google Maps dynamisch nachladen, werden nicht umgeschrieben. Solche Integrationen müssen gesondert geprüft werden.
- Maps-API-Skripte werden im Frontend global entfernt, auch wenn andere Plugins dieselbe API nutzen. Nicht parallel mit weiteren Karten-/Places-Integrationen einsetzen, ohne deren Verhalten zu prüfen.
- Kein vollständiger Borlabs-Ersatz: YouTube, Tracking, Google Fonts, reCAPTCHA und andere Dienste werden nicht gesperrt. Der Hinweistext ersetzt keine abgestimmte Datenschutzerklärung.
- Der Widerruf entfernt die lokale Freigabe und lädt die Seite neu, damit bereits laufende Maps-Skripte beendet werden. Bereits übermittelte Daten und Cookies bei Google kann das Plugin nicht zurückholen.
- Bei deaktiviertem JavaScript bleibt die Karte gesperrt. Eine strenge Content Security Policy muss die lokalen Plugin-Dateien erlauben sowie nach Einwilligung die für Maps benötigten Google-Ressourcen.
- HTML-Minifier und Optimierungsplugins dürfen die Plugin-Dateien nicht bis zur ersten Interaktion verzögern oder entfernte Maps-Skripte eigenständig erneut einfügen. Nach Installation und Updates Caches leeren.

## Abnahme auf jeder Installation

1. Abgemeldet im privaten Browserfenster öffnen, DevTools → Netzwerk → Protokoll beibehalten, Cache deaktivieren.
2. Seite neu laden. Vor dem Klick dürfen keine Maps-Anfragen an Google/Googleapis/Gstatic erscheinen. Auch die gesamte Seite auf andere externe Dienste prüfen.
3. Button per Maus und Tastatur testen: iframe oder Divi-Karte muss laden. Bei Divi Zentrum, Zoom und alle Pins vergleichen.
4. Ohne Merken-Häkchen neu laden: wieder gesperrt. Mit Häkchen freigeben und neu laden: Karte lädt automatisch.
5. Widerrufen: Seite lädt neu, Karte bleibt gesperrt. Neuen Seitenaufruf prüfen.
6. Mehrere Karten, Mobilansicht, Cache aktiviert und Divi-Builder separat prüfen.

## Technischer Stand

PHP-Syntax und JavaScript-Syntax geprüft. Elf PHP-Regressionstests prüfen iframe-Erkennung, Lazy-Load-Attribute, API-Sperre, Resource-Hints sowie unveränderte Fremd-iframes, Inline-JavaScript und Kommentare. Keine Browser-Netzwerkprüfung oder End-to-End-Prüfung mit WordPress/Divi durchgeführt.

Die Sperre verarbeitet die fertige HTML-Antwort vor der Auslieferung. WordPress muss die Seite selbst rendern bzw. ein Cache muss die bereits gefilterte Antwort speichern. Alte statische Cache-Kopien werden nicht rückwirkend geändert. Die Erkennung ist gezielt auf übliche HTML-Einbettungen begrenzt und kein allgemeiner HTML-Sicherheitsfilter.


## Änderung in 0.1.1

Snazzy-Maps-iframes auf snazzymaps.com/embed/NUMMER werden jetzt ebenfalls serverseitig gesperrt. Die ursprüngliche iframe-URL und somit die Kartengestaltung bleiben erhalten. Vor Freigabe wird auch Snazzy Maps nicht geladen. Der Hinweistext benennt diesen zusätzlichen Anbieter. Die Datenschutzerklärung muss bei Nutzung von Snazzy Maps beide Anbieter berücksichtigen.

Die Kontaktseite rhotertundpartner.de verwendet genau diese Einbettungsart. Ihr öffentlich ausgeliefertes HTML wurde als Regressionstest geprüft. Keine Änderung auf der Live-Seite vorgenommen, kein Browser-Netzwerktest durchgeführt.


## GitHub-Updates ab 0.2.0

Version 0.2.0 einmal manuell installieren. Ab dann fragt WordPress stabile Releases des öffentlichen Repositorys bueroblanko/bb-map-consent ab und zeigt neue Versionen in der normalen Plugin-Verwaltung an. Dort lassen sich auch die WordPress-Auto-Updates aktivieren. Das Plugin schaltet automatische Installationen nicht ungefragt ein. Keine Tokens oder GitHub-Zugangsdaten auf Kundenseiten nötig.

Updates werden im normalen WordPress-Prüfintervall gefunden, mit einem zusätzlichen API-Cache von einer Stunde. Bei API-Fehlern wird fünf Minuten zwischengespeichert. WP-Cron bzw. ein Server-Cron und ausgehende HTTPS-Verbindungen zu GitHub müssen funktionieren. GitHub kann anonyme API-Aufrufe begrenzen, besonders bei vielen Websites mit derselben Server-IP. Die Abfragen gehen vom WordPress-Server aus, nicht vom Browser des Besuchers.

### Neue Version veröffentlichen

1. Änderungen vornehmen, Versionsnummer im PHP-Plugin-Header und Asset-Versionen aktualisieren.
2. `php tests/updater.php` und `node --check assets/maps.js` ausführen.
3. Commit pushen, danach passenden Tag setzen und pushen, zum Beispiel `git tag v0.2.1` und `git push origin v0.2.1`.
4. GitHub Actions prüft den Code, baut eine ZIP mit stabilem Plugin-Ordner bb-map-consent und veröffentlicht ein Release mit der ZIP. Erst das veröffentlichte Release macht das Update verfügbar; ein normaler Commit reicht nicht.

Der Updater akzeptiert nur stabile Versions-Tags und die passend benannte ZIP aus genau diesem Repository. Entwürfe und Vorabversionen werden nicht ausgeliefert. GitHub-Releases sollten unveränderlich behandelt werden: Korrekturen mit neuer Versionsnummer veröffentlichen. Zugang zum GitHub-Konto und Schreibrechte für das Repository schützen, da veröffentlichte Releases Code auf verbundenen Websites aktualisieren können.

Validierung: automatisierte Tests für Release-Auswahl, fremde Plugins, ungültige Download-URLs, Cache, API-Fehler und Timeouts. Ein vollständiger WordPress-Upgrade-Durchlauf ist noch ausstehend.
