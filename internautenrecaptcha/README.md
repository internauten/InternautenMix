# Internauten reCAPTCHA Module

Dieses PrestaShop-Modul schuetzt das Kontaktformular mit Google reCAPTCHA.

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
5. PrestaShop-Cache leeren.

## Funktionsweise

- Das Modul laedt die Google reCAPTCHA API auf der Kontaktseite.
- Vor dem Absenden muss der reCAPTCHA-Check erfolgreich sein.
- Serverseitig wird das Token bei Google validiert.
- Bei Fehlern wird auf die Kontaktseite mit Meldung zurueckgeleitet.

## Test

1. Kontaktformular ohne reCAPTCHA absenden -> sollte blockiert werden.
2. Kontaktformular mit geloestem reCAPTCHA absenden -> sollte erfolgreich gesendet werden.

## Fehlerbehebung

- Wenn kein Widget sichtbar ist:
  - Site key pruefen.
  - Domain in der Google reCAPTCHA-Konfiguration pruefen.
  - Cache leeren.
- Wenn Absenden trotz Haken fehlschlaegt:
  - Secret key pruefen.
  - Sicherstellen, dass Site key und Secret key vom selben reCAPTCHA-Eintrag stammen.
