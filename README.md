# BB Consent 0.5.0

Installierbare erste Testversion für WordPress ab 6.2 / PHP ab 7.4. GPL-2.0-or-later.

## Installation

1. ZIP unter Plugins → Installieren → Plugin hochladen installieren und aktivieren.
2. Unter Einstellungen → Datenschutz die richtige Datenschutzerklärung hinterlegen. Der Platzhalter verlinkt diese automatisch.
3. Seiten-, Divi-, Optimierungs-, CDN- und Browser-Cache vollständig leeren.
4. Zunächst auf einer Testinstallation prüfen. Borlabs erst nach erfolgreicher Prüfung auf der jeweiligen Installation entfernen. Vorher prüfen, welche weiteren Dienste Borlabs dort blockiert.

Es gibt kein zusätzliches Cookie-Banner und keine externe Bibliothek. Der Klick erlaubt sämtliche vom Plugin verwalteten Inhalte desselben Dienstes auf der aktuellen Seite. Google Maps und Instagram haben getrennte Freigaben. Ohne gesetztes Häkchen gilt die Freigabe nur für die aktuelle Seitenansicht. Optional wird sie für 30 Tage in localStorage gespeichert. Die gespeicherte Freigabe wird bei jedem Seitenaufruf clientseitig ausgewertet; das vom Server erzeugte HTML bleibt immer gesperrt und kann deshalb auch gecacht werden.

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


## Instagram ab 0.3.0

Unterstützt werden klassische Instagram-Embed-Codes mit `blockquote.instagram-media` und `data-instgrm-permalink`, einem entsprechenden Beitragslink im Embed oder direkte Instagram-iframes. Beiträge, Reels und ältere /tv/-Beiträge werden erkannt. Die gesamte Vorschau einschließlich etwaiger externer Bilder wird serverseitig ersetzt; das Instagram-embed.js-Skript und Instagram-Resource-Hints werden entfernt. Nach Einwilligung wird ein Instagram-Embed-iframe mit der normalisierten Beitragsadresse geladen. Es wird kein Embed-HTML als ausführbarer Code wieder eingefügt. Das iframe hat zunächst 620 px Höhe; es gibt keine automatische Anpassung durch das Instagram-SDK.

Instagram hat eine eigene Freigabe, einen eigenen optionalen 30-Tage-Eintrag in localStorage und einen separaten Widerruf. Bereits gespeicherte Maps-Freigaben gelten weiterhin ausschließlich für Karten. Optionaler Shortcode für die Datenschutzerklärung: `[bb_instagram_revoke]`. Beim Widerruf lädt die Seite neu; die gespeicherte Einwilligung des jeweils anderen Dienstes bleibt erhalten.

Nicht unterstützte Instagram-URLs in erkannten Embed-Elementen bleiben gesperrt und zeigen nach dem Freigabeversuch einen Hinweis. Profile, Stories und andere dynamisch eingefügte Feed-Plugins, Social-Walls oder Proxy-Dienste werden nicht unterstützt. Normale Instagram-Links bleiben unverändert. Das Plugin beschafft keine Beiträge und ersetzt keine oEmbed-Anbindung: Der Embed-Code muss bereits in der WordPress-Ausgabe vorhanden sein. Private oder vom Betreiber für Einbettungen gesperrte Inhalte kann Instagram nicht anzeigen. Die Datenschutzerklärung um Instagram/Meta ergänzen.

Zusätzliche Abnahme: Seite mit Karte und Instagram gemeinsam öffnen, vor Freigabe Netzwerk prüfen, Instagram freigeben (Karte bleibt gesperrt), optional speichern, neu laden und Instagram widerrufen. Danach umgekehrt mit Kartenfreigabe testen. Getrennte Freigaben, Speichern, Ablauf und Widerruf sind mit JavaScript-Test-Doubles geprüft; ein echter Browser-/WordPress-Test auf einer Instagram-Zielseite steht noch aus.


## Umbenennung ab 0.3.1

Der sichtbare Plugin-Name lautet BB Consent. Plugin-Ordner, Hauptdatei, Repository und Update-Adresse behalten aus Kompatibilitätsgründen ihre bisherigen Namen. Bestehende Installationen bekommen die Umbenennung als normales Update.


## Smash Balloon und manuelle Update-Prüfung ab 0.4.0

Smash Balloon Instagram Feed mit dem im HTML ausgegebenen Container `div#sb_instagram` wird unterstützt. Das komplette Feed-HTML wird vor Auslieferung in eine inaktive, kodierte Konfiguration verschoben. Die zugehörigen `sbi_`-Skript-Tags und Skripte aus instagram-feed/instagram-feed-pro werden zurückgehalten. Nach Instagram-Freigabe werden alle Feeds wieder eingesetzt und die Feed-Skripte in ursprünglicher Reihenfolge ausgeführt. Lokale Stylesheets bleiben aktiv. Die serverseitige Feed-Beschaffung durch Smash Balloon wird dadurch nicht abgeschaltet. Dessen Einstellungen und andere Consent-Integrationen können das spätere Laden beeinflussen. Getestete Ausgabe: Smash Balloon Free 6.14.0. Für Pro, individuell initialisierte und per AJAX nachträglich eingefügte Feeds ist keine vollständige Kompatibilität bestätigt.

In der Plugin-Zeile erscheint für Administratoren mit Update-Berechtigung „Nach Updates suchen“. Dieser Link prüft mit Nonce und Berechtigungsprüfung, leert den GitHub- und WordPress-Update-Cache und startet die WordPress-Update-Prüfung sofort. Anschließend wird gemeldet, ob die Version aktuell, ein Update verfügbar oder GitHub nicht erreichbar ist. Der Link installiert kein Update automatisch.

Browser-Test mit dem öffentlich ausgelieferten HTML von hiffelder-carneval.de und dessen echtem Smash-Balloon-Skript: vor Freigabe keine Feed-/Instagram-Medienanfragen, nach Freigabe Feed-Initialisierung und Wiederherstellung aller 16 Beiträge bestätigt. Sonstige Website-Ressourcen waren im Test blockiert; dies ersetzt keine vollständige Live-Abnahme nach Plugin-Installation.


## DealerWeb-Aktionsbanner ab 0.5.0

Iframes von dealerweb-comarketing.de (Aktionsbanner der COmarketing GmbH, ausgeliefert über Wix) werden serverseitig durch einen Platzhalter ersetzt. Erkannt werden src und data-src, auch protokollrelativ; http wird auf https umgestellt. Nach Einwilligung lädt das iframe mit der Originaladresse, der Originalhöhe (100–1000 px, Standard 300 px) und der Originalbreite als Maximalbreite. Der Banner hat eine eigene Freigabe, einen eigenen optionalen 30-Tage-Eintrag in localStorage und einen eigenen Widerruf unter dem Banner. Optionaler Shortcode für die Datenschutzerklärung: `[bb_dealerweb_revoke]`. Resource-Hints auf dealerweb-comarketing.de werden entfernt. Die Datenschutzerklärung muss COmarketing und Wix nennen.

Divi legt bei Modulen mit geräteabhängiger Sichtbarkeit (zum Beispiel „auf Tablet/Handy ausblenden“) den Modulinhalt zusätzlich als JSON im Attribut `data-et-multi-view` ab und setzt ihn per JavaScript wieder ein. Dieses JSON wird jetzt ebenfalls gefiltert, für alle Dienste des Plugins. Vorher hätte ein so abgelegter iframe die Sperre umgehen können.

Geprüft auf fahrzeugtechnik-sanfilippo.de (28.10.2026): Startseite (Banner in einem Divi-Code-Modul mit Multi-View-JSON) und Kontaktseite (Google-Maps-iframe) wurden aus dem öffentlich ausgelieferten HTML so nachgebaut, wie sie ohne Borlabs aussähen. Nach dem Filter ist kein externer iframe mehr im HTML oder im Divi-JSON. Das Kontaktformular hat kein reCAPTCHA aktiv, Schriften liegen lokal. Live mit Borlabs gab es vor Einwilligung keine externen Anfragen. Ein Browsertest mit installiertem Plugin steht noch aus.
