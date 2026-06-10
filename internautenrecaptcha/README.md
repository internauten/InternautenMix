# Internauten reCAPTCHA Module

Dieses PrestaShop-Modul schuetzt das Kontaktformular und die Newsletter-Anmeldung mit Google reCAPTCHA.

## Modulname

- Technischer Name: `internautenrecaptcha`
- Hauptdatei: `internautenrecaptcha.php`

## Voraussetzungen

- PrestaShop 1.7+
- Google reCAPTCHA Schluessel

## Wichtiger Hinweis zum Schluesseltyp

Dieses Modul ist fuer **Google reCAPTCHA v2 Checkbox** gebaut.

Bitte bei Google genau diesen Typ erstellen:

https://www.google.com/recaptcha/admin/create

1. reCAPTCHA v2
2. Variante: "Ich bin kein Roboter"-Checkbox

Nicht passend fuer dieses Modul:

- reCAPTCHA v3
- reCAPTCHA v2 Invisible
- reCAPTCHA Enterprise

## Installation

1. Modulordner in den PrestaShop-Module-Pfad legen (oder als ZIP hochladen).
2. Im Backoffice unter Module nach `internautenrecaptcha` suchen.
3. Modul installieren.

## Konfiguration

1. Modul im Backoffice oeffnen.
2. `Site key` eintragen.
3. `Secret key` eintragen.
4. Speichern.
5. Die Vorschau im Backoffice pruefen.
6. Wenn dort `Invalid key type` erscheint, wurde nicht der richtige Schluesseltyp verwendet.
7. Auf den Secret-key-Hinweis im Backoffice achten.
8. Wenn dort ein Fehler erscheint, passen `Site key` und `Secret key` nicht zusammen oder der Secret key ist ungueltig.
9. PrestaShop-Cache leeren.

## Funktionsweise

- Das Modul laedt die Google reCAPTCHA API auf der Kontaktseite.
- Das Modul schuetzt auch die Newsletter-Anmeldung des Moduls `ps_emailsubscription`.
- Im Backoffice wird der gespeicherte `Site key` direkt als Vorschau geladen.
- Im Backoffice wird der gespeicherte `Secret key` serverseitig gegen Google reCAPTCHA geprueft.
- Vor dem Absenden muss der reCAPTCHA-Check erfolgreich sein.
- Serverseitig wird das Token bei Google validiert.
- Bei Fehlern wird auf die Kontaktseite mit Meldung zurueckgeleitet.

## Test

1. Kontaktformular ohne reCAPTCHA absenden -> sollte blockiert werden.
2. Kontaktformular mit geloestem reCAPTCHA absenden -> sollte erfolgreich gesendet werden.
3. Newsletter-Anmeldung ohne reCAPTCHA absenden -> sollte blockiert werden.
4. Newsletter-Anmeldung mit geloestem reCAPTCHA absenden -> sollte erfolgreich sein.

## Fehlerbehebung

- Wenn kein Widget sichtbar ist:
  - Site key pruefen.
  - Domain in der Google reCAPTCHA-Konfiguration pruefen.
  - Cache leeren.
- Wenn Absenden trotz Haken fehlschlaegt:
  - Secret key pruefen.
  - Sicherstellen, dass Site key und Secret key vom selben reCAPTCHA-Eintrag stammen.

## Test mit Docker

```bash
ln -s /internauten/InternautenMix/internautenrecaptcha /var/www/html/modules/internautenrecaptcha
chown -h www-data:www-data /var/www/html/modules/internautenrecaptcha
```
