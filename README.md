# BB Consent 0.5.1

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
- Ein Widerrufsbutton oben rechts auf jeder geladenen Karte (bei Divi-Karten als Google-Maps-Bedienelement, auf schmalen Bildschirmen unten links). Die geladene Karte übernimmt mindestens die Höhe des Platzhalters, damit sich das Layout beim Freigeben nicht verschiebt. Optional kann [bb_maps_revoke] zusätzlich in einem Shortcode- oder Textbereich der Datenschutzerklärung stehen. Nicht direkt als Text in ein WordPress-Navigationsmenü einfügen.

## Grenzen dieser Version

- Noch nicht in einer echten WordPress-/Divi-Installation getestet. Divi 5 mit nativem Block-/JSON-Rendering ist nicht als unterstützt bestätigt. Der klassische Shortcode-Pfad wird verarbeitet, die iframe-Sperre ist unabhängig vom Builder.
- Eigene Divi-Kartenfarben, individuelle Modul-CSS-Klassen/IDs, Animationen, exakte Modulhöhe und HTML in Pin-Beschreibungen werden nicht übernommen. Iframes werden responsiv mit der numerischen Originalhöhe (150–1000 px, Standard 400 px; bis 0.5.0: 200–1000 px) dargestellt.
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


## Planity-Terminbuchung ab 0.5.0

Das Planity-Buchungswidget (Inline-Konfiguration mit `window.planity = …` und die Skripte von `d2skjte8udjqxw.cloudfront.net/widget/…`) wird serverseitig zurückgehalten. An der Stelle der Widget-Skripte erscheint ein Platzhalter „Online-Terminbuchung“. Der Widget-Container und das Widget-CSS bleiben im HTML. Nach Einwilligung werden Konfiguration und Skripte in der Originalreihenfolge eingefügt, das Widget rendert dann wie gewohnt in seinen Container. Eigene Freigabe, eigener optionaler 30-Tage-Eintrag, eigener Widerruf; optionaler Shortcode `[bb_planity_revoke]`. Die Datenschutzerklärung muss Planity (Planity SAS) und deren Dienstleister nennen. Laut Widget-Code kommen dabei unter anderem Amazon Web Services, Cloudinary, Google, Stripe und Algolia in Frage.

Geprüft auf villa-haartrend-speyer.de/termin/ (28.10.2026): Der von Borlabs gesperrte Widget-Code wurde in eine lokale Testseite mit Plugin-Filter und -Skript übernommen. Vor dem Klick gab es keine externen Anfragen. Nach dem Klick luden CloudFront und Planity, und das Widget zeigte die Dienstleistungen. Mit gemerkter Freigabe lud es nach dem Neuladen automatisch.

Nicht vom Plugin abgedeckt: Divi bettet dort Google Fonts als `@font-face` mit Adressen auf fonts.gstatic.com direkt ins CSS ein. Die Schriften laden auf jeder Seite ohne Einwilligung. Das muss über lokal gehostete Schriften gelöst werden, nicht über eine Einwilligung.


## Kompakter Platzhalter ab 0.5.1

Für schmale Spalten wie eine Footer-Karte. Ist die Box höchstens 360 px breit oder das eingebettete Element niedriger als 260 px, zeigt der Platzhalter einen Kurzhinweis mit dem Link zur Datenschutzerklärung („Beim Laden werden Daten an Google übermittelt.“), „30 Tage merken“ und einen kleineren Button. Die Höhe wird schon serverseitig gesetzt, damit nichts springt. Ab 240 px Breite und schmaler entfällt die Überschrift, und der Widerruf unter einer geladenen Karte erscheint als kleiner Textlink statt als Button über der Karte. Die Breitenerkennung nutzt CSS-Container-Queries; ältere Browser zeigen dort die normale Variante. Geprüft im Browser mit einer 195 px breiten, 170 px hohen Karte wie im Footer von wagner-containerservice.de.

Korrektur für DealerWeb-Banner in 0.5.1: Prozentbreiten wie Divis Tablet-/Handy-Variante `width="100%"` wurden in 0.5.0 als 100 px gelesen. Der Banner war nach der Freigabe dort nur 100 px breit. Nur feste Pixelbreiten begrenzen jetzt die Breite. Banner mit fester Breite werden mitsamt Platzhalter mittig gesetzt wie das ursprüngliche iframe. Geprüft mit Headless-Chrome an einem Nachbau der Startseite von fahrzeugtechnik-sanfilippo.de: Desktop 800 px mittig, Tablet und Handy in voller Breite mit Divis Höhen 200 bzw. 150 px, keine externen Anfragen vor dem Klick.



## Fehlerkorrektur 0.5.2

Divi-Karten lesen den Google-Maps-API-Schlüssel über Divis eigene Funktion et_pb_get_google_api_key und als Fallback aus der globalen Einstellung et_google_api_settings. Die bisherige Suche nur unter divi_google_api_key konnte vorhandene Schlüssel übersehen. Nach dem Update Seiten-, Divi- und CDN-Caches leeren. Die Sperre bis zur Einwilligung bleibt bestehen.


## Fehlerkorrektur 0.5.3

Klassische Divi-Karten werden nach dem Rendern über do_shortcode_tag verarbeitet. Zentrum, Zoom und Pins werden aus Divis tatsächlich erzeugten data-Attributen übernommen, statt unbekannte Shortcode-Feldnamen mit 0,0 zu ersetzen. Beide Kartenarten und die beiden Heidelberger Kartenpositionen sind durch Regressionstests abgedeckt. Fehlende Koordinaten führen zu einem Hinweis statt zu einer Karte bei 0,0. Der äußere Modul-Container mit Klassen und ID bleibt erhalten. Ein Browser-Test mit simulierter Google-Maps-API bestätigt Einwilligungssperre, Kartenoptionen, Pin und Widerrufsbutton. Live-Test mit echter Google-API auf der WordPress-Installation weiterhin erforderlich.


## Update-Ordner ab 0.5.4

Der Updater erhält bei zukünftigen Updates den tatsächlich installierten Plugin-Ordner, auch bei Installationen aus GitHubs Quellcode-ZIP mit dem Suffix -main. Dadurch bleibt WordPress' gespeicherter Aktivierungspfad erhalten. Ordnerkonflikte oder fehlgeschlagenes Umbenennen brechen das Update ab, statt einen anderen Installationspfad zu verwenden.

Wichtig zur erstmaligen Umstellung: Der Update-Prozess führt den bisher installierten Updater aus. Versionen vor 0.5.4 können deshalb beim ersten Update aus einem abweichenden Ordner noch einmal deaktiviert werden. Danach das Plugin in der Plugin-Liste einmal aktivieren. Empfohlene Erstinstallation: die angehängte ZIP unter Releases verwenden, nicht Code → Download ZIP. Eine bereits erfolgte Deaktivierung wird nicht automatisch rückgängig gemacht.


## Fester Installationsordner ab 0.5.5

Die Installationsdatei heißt bb-consent-VERSION.zip und enthält immer den Ordner bb-consent. Die Hauptdatei bb-map-consent.php und die GitHub-Repository-Adresse behalten ihre technischen Namen. Ein zusätzlicher gleichwertiger Release-Anhang mit dem bisherigen ZIP-Namen bleibt für ältere Updater verfügbar.

Zur einmaligen Umstellung eine bestehende alte Installation deaktivieren und löschen, dann die ZIP bb-consent-VERSION.zip aus Releases installieren und aktivieren. Dieses Plugin hat keine uninstall-Routine, die Einstellungen oder Seiteninhalte löscht; lokale Browserfreigaben bleiben erhalten. Nicht zwei Kopien gleichzeitig aktivieren. Für bisherige Installationen in anderen Ordnern bewahrt der Updater weiterhin den bestehenden Ordner, um keine erneute Deaktivierung durch einen Pfadwechsel zu verursachen. Der neue feste Ordner wird durch die einmalige Neuinstallation übernommen.


## Datenschutz-Link ab 0.5.6

Wenn keine veröffentlichte Datenschutzseite in WordPress ausgewählt ist, verlinken die Platzhalter auf home_url('/datenschutz'). Eine konfigurierte Datenschutzseite hat weiterhin Vorrang.


## Elfsight ab 0.6.0

Der gemeinsame Elfsight-Lader https://static.elfsight.com/platform/platform.js wird bis zur Elfsight-Freigabe gesperrt. Container mit Klasse elfsight-app-ID werden einschließlich vorhandener Bilder durch Platzhalter ersetzt. Nach Freigabe werden die Container wieder eingesetzt und das originale SDK mit data-use-service-core geladen. Mehrfach eingebundene Lader werden einmal ausgeführt. Die Elfsight-Freigabe umfasst alle so eingebundenen Elfsight-Widgets der Seite, nicht nur Twitter/X: Der gemeinsame Lader kann verschiedene Widget-Arten laden. Andere Dienste bleiben unabhängig gesperrt. Optional 30 Tage merken und Widerruf über [bb_elfsight_revoke].

Wird nur der Lader ohne Widget-Container gefunden, erscheint ein allgemeiner Elfsight-Platzhalter am Seitenende, damit kein sichtbarer Inhalt in den head eingefügt wird. Der Lader alleine enthält keine Feed-ID und erstellt keinen konkreten Feed. Für einen Platzhalter direkt am Feed den vollständigen Elfsight-Code einschließlich div.elfsight-app-ID einfügen. Inline-Skripte, die Elfsight eigenständig dynamisch laden, sowie andere Twitter-Feed-Plugins und direkte Twitter-Embeds sind nicht Teil dieser Integration. Ein vorhandener Borlabs-Blocker muss beim Wechsel gesondert entfernt werden, sonst bleibt dessen kodierter Inhalt unverändert gesperrt.

Geprüft: PHP-Regressionen und Browser-Lebenszyklus mit simuliertem Elfsight-SDK, einschließlich freigegebenem Neuladen und Widerruf. Live-Test mit der tatsächlichen Widget-ID noch erforderlich.


## YouTube und Tramino ab 0.7.0

Direkte iframe-Einbettungen von youtube.com/embed und youtube-nocookie.com/embed sowie iframe-Widgets von tramino.de und dessen Subdomains werden serverseitig durch Platzhalter ersetzt. YouTube und Tramino haben jeweils eigene Freigaben, optionales Speichern für 30 Tage und Widerruf. Shortcodes: [bb_youtube_revoke] und [bb_tramino_revoke]. Die originale iframe-Adresse, Höhe und Titel bleiben erhalten; die Breite wird responsiv. Der YouTube-Player erhält die üblichen Player-Berechtigungen einschließlich Vollbild und Picture-in-Picture.

Auf fewo-almwiese.de wurden ein Tramino-Bewertungswidget (/api/widgets/rating/) und ein YouTube-nocookie-Video festgestellt und deren Sperre am aktuellen HTML geprüft. Die Tramino-Integration umfasst iframe-Widgets; eigenständige JavaScript-Widgets oder nachträglich dynamisch eingefügte Inhalte sind nicht pauschal abgedeckt. Externe Video-Vorschaubilder außerhalb des iframe werden nicht automatisch blockiert. Alte Borlabs-Platzhalter dürfen beim Wechsel keine zusätzliche Sperre um die Inhalte behalten. Bunny Fonts und Bilder von anderen Domains werden durch diese Erweiterung nicht lokalisiert.

Automatisierte Prüfungen decken Host-Erkennung, Protokolle, Trennung der Dienste und Ressourcenvorladungen ab. Browser-Lebenszyklus mit simulierten iframe-Antworten geprüft; abschließende Prüfung auf der Live-Installation nach Update erforderlich.


## Microsoft Bookings ab 0.8.0

Microsoft-Bookings-iframes auf outlook.office365.com und outlook.office.com werden bei klassischen /owa/calendar/…/bookings/-Adressen und /book/…-Adressen erkannt. Bookings besitzt eine eigene Einwilligung mit optionalem 30-Tage-Speichern und Widerruf. Shortcode: [bb_bookings_revoke]. Andere Outlook-Seiten werden nicht umgeschrieben. Die ursprüngliche Buchungsadresse einschließlich Parametern und die numerische Höhe bleiben erhalten; ohne Höhenangabe gilt 800 px.

Geprüft mit dem originalen Microsoft-Bookings-iframe von rn-systems.de/termin-vereinbaren/. Auf dieser Website liegt es derzeit in einer Borlabs-kodierten Sperre. Beim Wechsel zuerst die originale Einbettung wieder ausgeben lassen; BB Consent entfernt nicht automatisch den Schutz anderer Consent-Plugins. Die Bookings-Freigabe steuert kein Matomo und keine sonstigen Statistik-Skripte. Browser-Prüfung mit simuliertem iframe bestätigt Trennung von Maps, Freigabe, Speichern, Neuladen und Widerruf. Ein tatsächlicher Buchungsvorgang wurde nicht durchgeführt.


## Korrektur 0.8.1: Platzhalter in Divi-Videomodulen

Divi setzt in `.et_pb_video_box` `line-height:0`. Über `font:inherit` hat der Platzhalter das geerbt, alle Textzeilen lagen übereinander (gesehen bei fewo-almwiese.de). Der Platzhalter hat jetzt eine eigene Zeilenhöhe von 1.5. YouTube-Platzhalter und geladene Videos behalten außerdem das Seitenverhältnis aus `width`/`height` des Einbettungscodes, ohne Pixelbreite 16:9, statt der festen Höhe. Auf dem Handy war das Video sonst 312 × 608 px hoch. Geprüft mit Headless-Chrome an der Startseite von fewo-almwiese.de mit ausgetauschtem Plugin-Stand: Desktop 605 × 340, Tablet 656 × 369, Handy 312 × 176, vor und nach der Freigabe.



## Eigenes CSS ab 0.9.0

Unter Einstellungen → BB Consent steht ein CSS-Feld für die gesamte WordPress-Installation zur Verfügung. Ein Link „Einstellungen“ erscheint auch in der Plugin-Zeile. Das Feld ist für Benutzer mit edit_theme_options zugänglich. Es wird die normale WordPress Settings API verwendet.

Beim Bearbeiten einer WordPress-Seite gibt es zusätzlich die Box „BB Consent – Seiten-CSS“. Dort gespeichertes CSS wird ausschließlich beim Aufruf dieser Seite ausgegeben, nach dem Website-CSS. Die normalen CSS-Spezifitätsregeln gelten weiter. Im Divi-Frontend-Builder ist diese WordPress-Metabox nicht sichtbar; dafür die normale Seitenbearbeitung öffnen. Ein leeres Feld entfernt die seitenbezogene Anpassung. Änderungen an anderen Seiten oder Saves ohne diese Felder überschreiben nichts.

Beispiel für die Website:

```css
.bbmc { --bbmc-accent: #113262; }
.bbmc-placeholder { background: #f5f8fd; border-radius: 12px; }
```

Gezielt einen Dienst gestalten:

```css
.bbmc[data-service="youtube"] .bbmc-allow { background: #b00; }
```

Nur CSS eingeben, keine style-Tags. HTML-Markup wird entfernt. Das Feld ist kein Editor für PHP oder JavaScript. Die Regeln sollten mit .bbmc beginnen, damit sie nur Content-Blocker betreffen. Globale Regeln sind technisch möglich und beeinflussen dann entsprechend die übrige Website. Die CSS-Daten bleiben bei Plugin-Deaktivierung und normalen Updates erhalten. Nach Änderungen ggf. den Seiten-/CDN-Cache leeren.

Validierung: automatisierte Tests für Berechtigungen, Nonce, unveränderte Daten bei fehlenden Feldern, Löschen leerer Overrides und Ausgabe-Reihenfolge von Website- und Seiten-CSS. Kein vollständiger Admin-Speicherdurchlauf in einer echten WordPress-Installation durchgeführt. Die Divi-Video-Darstellungskorrektur aus 0.8.1 ist enthalten und wurde auf fewo-almwiese.de mit nicht überlappenden Texten beobachtet.

## Rechtstext-Modul (in Arbeit, noch in keinem Release)

Anbindung an die Rechtstexte-Zentrale legal.bbkd.de. Das Modul liegt in `includes/legal/` und ist technisch vom Content-Blocker getrennt. Es ist **standardmäßig ausgeschaltet**, ein Update allein ändert auf keiner Website etwas.

- **Einstellungen → Rechtstexte:** Modul einschalten, Zentrale (Standard `https://legal.bbkd.de`), Projekt-ID und Seitenschlüssel eintragen. Der Schlüssel wird nicht wieder angezeigt. Hier nie Admin- oder Freigabe-Token der Zentrale eintragen.
- **Ausgabe:** übernimmt die eRecht24-Shortcodes `[erecht24 type="imprint|privacy_policy|social_media|terms|withdrawal" lang="de|en" strip_title="true"]` (`terms` = AGB, `withdrawal` = Widerrufsbelehrung; beide optional, nur bei Shops), `[erecht24_widget]`, `[impressum]`, `[datenschutz]`, die Blöcke `erecht24/erecht24` und `erecht24/legal-text` sowie ein Elementor-Widget. Solange ein anderer Anbieter (z. B. das eRecht24-Plugin) diese Shortcodes belegt, bleibt er zuständig und nichts wird überschrieben.
- **Synchronisation:** Texte werden lokal gespeichert (ohne Autoload) und ohne Abruf der Zentrale ausgeliefert. Die Zentrale meldet Neues signiert an `POST /wp-json/bb-legal/v1/push` (mit Schutz gegen Wiederholung), zusätzlich fragt WP-Cron alle 15 Minuten nach (`bb_legal_poll`). Fällt die Zentrale aus, bleibt die letzte gültige Fassung. Eine Anzeige-Bestätigung (`ack`) geht nur für Texte raus, die tatsächlich öffentlich ausgegeben wurden.
- **Rechtsinhalte bearbeiten (wie in bb-cms):** Einstellungen → Rechtstexte, Reiter „Rechtsinhalte“: Liste der Texte mit „Bearbeiten | Ansehen“. Im Editor ist jeder Baustein ein eigenes Feld, dazu eigener Text am Anfang, nach jedem Baustein und am Ende. Ein geänderter Baustein wird ab dann nicht mehr automatisch aktualisiert (markiert, zurücksetzbar), unveränderte bleiben aktuell. Gespeichert wird per signiertem `PUT /api/v1/sites/{id}/segments/{typ}/{sprache}`; ohne Verbindung bleibt alles als lokaler Entwurf. Verbindung, Seitenschlüssel und Aktivierung stehen im Reiter „Einstellungen“.
- **Eingaben:** Der Fragebogen ist im Backend bearbeitbar. Ohne Verbindung wird ein lokaler Entwurf gespeichert. Antworten der Zentrale (zusammengeführt, Konflikt, wartet auf Freigabe) werden angezeigt.
- **Spätere Übergabe an BB Basic:** Beide Plugins melden sich über `bb_legal_register_provider()` an. Ein aktiviertes, kompatibles Modul in BB Basic übernimmt mit unveränderten Optionen, Seitenschlüssel und lokalen Texten; ist es aus, bleibt BB Consent zuständig. Ein inkompatibles Schema wird nicht zurückmigriert.
- **Öffentliches Repository:** keine Seitenschlüssel, Tokens oder echten Rechtstexte in Code, Tests oder Issues.

Prüfungen: `php tests/legal.php` (Modul) und `bash tests/wordpress/run.sh` (lädt ein Wegwerf-WordPress und prüft Shortcodes, Blöcke, Push, Ausfall der Zentrale, Fremdanbieter und Übergabe an BB Basic). Beide laufen auch in GitHub Actions. Vor einem Release: Pilot auf dev.bueroblanko.de.


## Korrektur 0.10.1: Platzhalter in Raster-Plugins

Divi FilterGrid bricht Klicks in seinen Video-Kacheln ab. Dadurch ließen sich „30 Tage merken“ und der Datenschutz-Link im Platzhalter nicht anklicken. Klicks auf Label und Links bleiben jetzt im Platzhalter. „Einwilligung widerrufen“ unter einem geladenen Inhalt hat seitlichen Abstand und wird in Kacheln mit negativem Rand nicht mehr abgeschnitten. Gefunden auf ausbildung-bergstrasse.de.

## Cookie-Banner für Tracking ab 0.10.0 (Modul `includes/cmp/`, Standard aus)

Einschalten unter Einstellungen → BB Consent → „Cookie-Banner für Tracking“ (nur Administratoren). Ist Borlabs, Complianz, Real Cookie Banner, CookieYes, Cookiebot, Cookie Notice oder ein ähnliches Plugin aktiv, bleibt das Banner aus, damit nie zwei Banner parallel laufen.

Das Modul erkennt im ausgelieferten HTML Google Analytics, Google Tag Manager, Google Ads, Meta-Pixel, LinkedIn Insight Tag, TikTok-Pixel, Matomo, Hotjar, Mouseflow und Microsoft Clarity (Skript-Adresse oder Inline-Code). Diese Skripte werden zu `<script type="text/plain" data-category="analytics|marketing">` und laufen erst nach Zustimmung. Tracking-Pixel in `<noscript>` und Resource-Hints zu diesen Diensten werden entfernt. Tag Manager und Google Ads zählen zu Marketing, weil darüber beliebige Tags laufen können.

Einmal erkannte Dienste merkt sich die Website (Option `bbmc_cmp_seen`). Das Banner erscheint dann auf allen Seiten, nicht nur dort, wo der Tracker steht. Kommt ein neuer Dienst dazu, ändert sich die Einwilligungsversion und Besucher werden erneut gefragt. Nach dem Entfernen eines Trackers „Erkannte Dienste zurücksetzen“ klicken. Seiten ohne erkanntes Tracking bekommen kein Banner, nur die bisherigen Platzhalter.

Technik: CookieConsent 3.1.0 von Orest Bida (MIT, `includes/cmp/vendor/`), lokal ausgeliefert, kein CDN. Banner als Box in der Bildschirmmitte, die Seite dahinter ist gesperrt, bis entschieden ist. Drei gleich gewichtete Buttons (Alle akzeptieren, nur essenzielle, individuelle Einstellungen mit Essenziell, Statistik, Marketing), darunter Links zu Datenschutzerklärung und Impressum (Seite `impressum` oder `imprint`, sonst `/impressum`). Die Entscheidung liegt 30 Tage in localStorage (`bbmc_cmp`), Zustimmung wie Ablehnung. Beim Widerruf werden die bekannten Cookies der Dienste gelöscht (auch auf der Hauptdomain, wenn die Website auf einer Subdomain läuft) und die Seite neu geladen. Externe Inhalte (Karten, Videos, Feeds) stehen nicht im Banner und behalten ihre eigenen Freigaben.

Anrede: Sie oder Du (Option `bbmc_cmp_address`, Standard Sie). Die Standardtexte sind eigene Formulierungen und nennen, was übliche Banner (z. B. Borlabs) nennen: Freiwilligkeit, IP-Adresse, USA-Übermittlung nach Art. 49 DSGVO, Hinweis unter 16 Jahren, Widerruf, 30 Tage. Wer die Texte eines bestimmten Anbieters will, trägt sie pro Website unter „Eigene Texte“ ein; fremde Texte gehören nicht ins Repository.

Eigene Texte: Unter „Eigene Texte“ lassen sich Titel, Banner-Text, Buttons und die Beschreibungen je Kategorie pro Website ersetzen (Option `bbmc_cmp_texts`, Links und Fettschrift erlaubt). `{datenschutz}` setzt den Link zur Datenschutzerklärung ein, `{impressum}` den zum Impressum, `{einstellungen}` einen Link, der die Einstellungen öffnet, `{kategorien}` die Kategorien, `{dienste}` die erkannten Dienste. Leere Felder nutzen die Standardtexte. Texte aus eRecht24 gehören dorthin und nicht ins öffentliche Repository.

Footer: Shortcode `[bb_consent_settings]` oder ein Menü-Link auf `#bb-consent-settings` öffnet die Einstellungen. Gestaltung über das CSS-Feld oben mit `#cc-main` (z. B. `#cc-main { --cc-btn-primary-bg: #113262; }`).

Grenzen: Tracker, die ein anderes Plugin erst per JavaScript nachlädt, ohne dass eines der Muster im HTML steht, werden nicht erkannt. Optimierungsplugins dürfen die Skripte mit `data-bbmc-cmp` nicht verzögern. Cookielos konfiguriertes Matomo wird trotzdem gesperrt. Texte sind neutrale Standardtexte und keine rechtliche Prüfung. Bots (z. B. Suchmaschinen) sehen kein Banner.

Geprüft: `php tests/cmp.php` (13 Tracker-Varianten, unveränderte fremde Skripte, noscript, Resource-Hints, Versionswechsel, Schutz vor zweitem Cookie-Plugin) und Headless-Chrome mit gefiltertem Testseiten-HTML: vor der Entscheidung und nach „Nur notwendige“ keine Anfragen an Google oder Facebook, nach „Alle akzeptieren“ laden beide, nach Neuladen automatisch, nach Widerruf über den Footer-Link wieder keine.

## Vorschaubilder für YouTube (ab 0.10.2)

Unter Einstellungen → BB Consent → Gestaltung lässt sich „Vorschaubilder für YouTube anzeigen“ einschalten (Standard aus). Der Platzhalter zeigt dann das Vorschaubild des Videos hinter einem dunklen Schleier, Text weiß. Der Server lädt jedes Bild einmal von `i.ytimg.com` (höchstens 12 neue Bilder pro Seitenaufruf, Zeitlimit 3 s) und speichert es unter `wp-content/uploads/bb-consent/yt/`. Besucher haben vor ihrer Zustimmung keinen Kontakt zu YouTube. Schlägt ein Abruf fehl, folgt der nächste Versuch frühestens nach einem Tag. Nach dem Einschalten den Seiten-Cache leeren.

## Banner und Platzhalter verbunden (ab 0.10.2)

Ist das Cookie-Banner an, erscheinen die Dienste der Platzhalter (YouTube, Google Maps, Instagram usw.), sobald sie auf einer Seite vorkommen, im Banner als Kategorie „Externe Medien“ mit einem Schalter pro Dienst. Neue Dienste ändern die Revision, Besucher werden also neu gefragt.

- „Alle akzeptieren“ oder der Schalter in den Einstellungen lädt die Platzhalter des Dienstes sofort.
- „30 Tage merken“ im Platzhalter setzt den Dienst im Banner auf an. Ohne Haken gilt die Freigabe nur für den Seitenaufruf.
- Widerrufen im Platzhalter oder in den Einstellungen nimmt die Freigabe an beiden Stellen zurück und lädt die Seite neu.
- Ohne Banner bleibt alles wie bisher (Freigabe je Dienst im Browser).

Hinweis: Mit Banner und Platzhaltern, aber ohne Tracker erscheint das Banner jetzt auch nur für die externen Medien.

Eigenes CSS: `>` als Kind-Kombinator bleibt jetzt erhalten (vorher entfernt).

## 0.10.3: Jobmorgen, YouTube ohne Cookies, Matomo ohne Cookies

- **Jobmorgen-Stellensuche** (MANNHEIMER MORGEN GmbH): Der Loader der Partnerbox (Inline-Skript mit `jobmorgen.de/partnerbox.js` bzw. `RegioPowerPartnerBox`) wird zurückgehalten. Vor dem Container `.regio-power-partner-box` steht ein Platzhalter. Seiten ohne Container halten nur das Skript zurück (vorher lief es dort ins Leere). Im Banner heißt der Dienst „Jobmorgen“.
- **YouTube**: `youtube.com/embed` (z. B. aus WordPress-oEmbed) wird beim Freigeben als `youtube-nocookie.com/embed` geladen.
- **Matomo ohne Cookies**: Ein Matomo-Code mit `_paq.push(['disableCookies'])` wird vom Banner nicht blockiert und aus den erkannten Diensten genommen.

## 0.10.4: Nachgeladene Inhalte

Inhalte, die eine Seite per admin-ajax nachlädt (z. B. Divi FilterGrid „Mehr laden“ oder Filter), werden wie die Seite selbst gefiltert. Das gilt für JSON-Antworten (Strings darin) und für HTML-Antworten. Es betrifft nur Anfragen von öffentlichen Seiten derselben Domain, nicht aus wp-admin oder dem Divi-Builder. Neu eingefügte Platzhalter richtet `maps.js` per MutationObserver ein, inklusive bereits erteilter Freigaben.

## 0.10.5: Kein Leerraum unter geladenen Videos

Lädt ein Video beim Seitenaufbau direkt (Einwilligung schon gespeichert), legt Divis FitVids einen Rahmen um das iframe und rechnet die Höhe nur aus dem height-Attribut. Im Raster entstand so ein großer Leerraum unter dem Video. Das iframe trägt jetzt die Klasse `fitvidsignore`, ein CSS-Rückfall setzt einen trotzdem gesetzten Rahmen zurück.

## 0.10.6: Neue Rechtstexte sofort statt per Abfrage

Viele Seiten liefern Besuchern nur Kopien aus dem Seiten-Cache aus. Dann läuft WP-Cron selten und die Abfrage alle 15 Minuten bleibt aus. Das Rechtstexte-Modul meldet der Zentrale deshalb bei jedem Abruf seine Push-Adresse. Die Zentrale speichert sie einmalig, nur für die eigene Domain, und schickt neue Fassungen danach direkt.

## 0.10.7: Footer-Link heißt „Cookies“

Der Shortcode `[bb_consent_settings]` zeigt jetzt „Cookies“ statt „Datenschutz-Einstellungen“. Ein eigener Text geht mit `[bb_consent_settings label="…"]`.

## 0.10.8: Tracking-Codes aus Borlabs übernehmen

Borlabs gibt Google Analytics, Tag Manager, Google Ads oder das Meta-Pixel oft selbst aus. Wird Borlabs entfernt, ist das Tracking weg. Unter Einstellungen › BB Consent › Cookie-Banner gibt es dafür die Karte „Tracking-Codes“ (GA4, GTM, Google Ads, Meta-Pixel, Mouseflow, Hotjar, Clarity). BB Consent schreibt den Standard-Code in den `<head>` und blockt ihn wie jeden anderen Tracker bis zur Zustimmung. Bei ausgeschaltetem Banner oder aktivem Borlabs wird nichts ausgegeben. Steht dieselbe ID schon im Theme oder einem Plugin, wird sie nicht doppelt eingebaut.

Ist Borlabs noch installiert, liest BB Consent die IDs aus dessen Einstellungen und bietet „In BB Consent übernehmen“ an. Ablauf beim Umstieg: BB Consent installieren, Banner einschalten, IDs übernehmen und prüfen, dann Borlabs deaktivieren.

## 0.10.9: Mit der Rechtstexte-Zentrale per Verbindungscode verbinden

Unter Einstellungen › BB Consent › Rechtstexte › Verbindung reicht jetzt ein einziger Verbindungscode (`BBL1-…`). Er kommt aus der Zentrale (Website › Anbindung › „Verbindungscode für WordPress“) und enthält Adresse, Projekt-ID und Seitenschlüssel. Nach „Verbinden“ werden Texte und Angaben sofort abgerufen. Die Einzelfelder stehen weiter unter „Manuell eintragen“.
