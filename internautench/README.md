# internautench

PrestaShop-Modul zur Formatierung von Zahlen und Preisen nach Schweizer Standard fuer konfigurierbare Locales.

## Verhalten

- Betrifft standardmaessig `de-CH`, `fr-CH`, `it-CH` und `rm-CH`.
- Setzt standardmaessig den Dezimaltrenner auf `.`.
- Setzt standardmaessig den Tausendertrenner auf `'`.
- Setzt dieselben Trennzeichen auch fuer Preisformatierungen.
- Kann im Back Office ein- oder ausgeschaltet werden.
- Erlaubt eine frei konfigurierbare Locale-Liste und eigene Trennzeichen.

## Installation

1. Den Ordner `internautench` in `modules/` kopieren.
2. Modul im Back Office installieren.
3. Unter der Modulseite die Konfiguration oeffnen und bei Bedarf anpassen.
4. Sicherstellen, dass die Shop-Sprache ein passendes Locale wie `de-CH` oder `fr-CH` verwendet.

## Hinweis

Das Modul dekoriert die CLDR-Locale-Datenquelle von PrestaShop. Dadurch werden Formatierungen zentral angepasst, ohne Template-Dateien zu ueberschreiben.

Fuer die Frontoffice-Ausgabe ist zusaetzlich die Service-Datei `config/front/services.yml` notwendig, weil PrestaShop fuer Frontend und Backoffice getrennte Service-Container verwendet.

## Test mit Docker

```bash
ln -s /internauten/InternautenMix/internautench /var/www/html/modules/internautench
chown -h www-data:www-data /var/www/html/modules/internautench
```
