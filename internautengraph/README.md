# internautengraph

PrestaShop-Modul, das den Versand von `Mail::Send()` auf Microsoft Graph API (Office 365) umleitet.

## Features

- Installiert ein Override fuer die PrestaShop-Klasse `Mail`.
- Versendet E-Mails ueber Microsoft Graph `sendMail`.
- Liest die PrestaShop-Mailtemplates (`.html` / `.txt`) und ersetzt Template-Variablen.
- Fallback auf den nativen PrestaShop-Mailversand, falls Graph fehlschlaegt.

## Installation

1. Modulordner `internautengraph` nach `modules/` kopieren.
2. Modul im Back Office installieren.
3. In der Modulkonfiguration folgende Werte setzen:
   - Tenant ID
   - Client ID
   - Client Secret
   - Sender mailbox (Office 365 Benutzer)
4. Modul aktivieren und eine erste Testmail aus PrestaShop ausloesen.

## Azure und Office 365 Schritt fuer Schritt

### 1) App in Microsoft Entra ID registrieren

1. Azure Portal oeffnen und zu `Microsoft Entra ID` > `App registrations` > `New registration` wechseln.
2. Einen Namen vergeben, zum Beispiel `Prestashop Internauten Graph`.
3. `Accounts in this organizational directory only` auswaehlen.
4. Registrierung speichern.
5. In der Uebersicht die Werte notieren:
   - `Application (client) ID` (deutsch meist `Anwendungs-ID (Client)`)
   - `Directory (tenant) ID` (deutsch meist `Verzeichnis-ID (Mandant)` oder `Mandanten-ID`)

### 2) Client Secret erstellen

1. In der App zu `Certificates & secrets` wechseln.
2. Unter `Client secrets` auf `New client secret` klicken.
3. Beschreibung und Gueltigkeit waehlen, danach speichern.
4. Den Secret-`Value` sofort kopieren und sicher speichern (wird spaeter nicht mehr voll angezeigt).

### 3) Microsoft Graph Berechtigungen setzen

1. In der App zu `API permissions` wechseln.
2. `Add a permission` > `Microsoft Graph` > `Application permissions` waehlen.
3. Berechtigung `Mail.Send` hinzufuegen.
4. `Grant admin consent` fuer den Tenant ausfuehren.
5. Pruefen, dass der Status `Granted for <Tenant>` angezeigt wird.

### 4) Office 365 Mailbox als Absender vorbereiten

1. Eine bestehende Benutzer-Mailbox festlegen, zum Beispiel `shop@example.com`.
2. Sicherstellen, dass die Mailbox eine Exchange Online Lizenz hat und senden darf.
3. Falls Conditional Access aktiv ist, sicherstellen, dass App-only Zugriff auf Graph fuer diese Anwendung nicht blockiert wird.
4. Die Mailbox-Adresse exakt notieren. Diese Adresse wird in der Modulkonfiguration als `Sender mailbox` verwendet.

### 5) Werte im PrestaShop Modul eintragen

1. In PrestaShop `Module` > `internautengraph` > `Konfigurieren` oeffnen.
2. `Enable Graph sending` auf `Yes` setzen.
3. Felder fuellen:
   - `Tenant ID` = `Directory (tenant) ID` (deutsch: `Verzeichnis-ID (Mandant)` oder `Mandanten-ID`)
   - `Client ID` = `Application (client) ID` (deutsch: `Anwendungs-ID (Client)`)
   - `Client Secret` = Secret Value
   - `Sender mailbox` = Exchange Online Mailbox (z. B. `shop@example.com`)
4. Speichern.

### 6) Funktion testen

1. In PrestaShop eine Aktion mit E-Mail-Versand ausloesen (z. B. Passwort vergessen oder Kontaktformular).
2. In Exchange Online `Sent Items` der Absender-Mailbox pruefen.
3. Falls keine Mail ankommt, zuerst App-Berechtigungen, Admin-Consent und Secret-Gueltigkeit kontrollieren.
4. Falls Graph fehlschlaegt, verwendet das Modul automatisch den nativen PrestaShop-Mailversand als Fallback.

## Hinweise

- Das Modul verwendet den OAuth2 Client-Credentials-Flow.
- Bei aktivierter Graph-Konfiguration wird zuerst Graph versucht.
- Falls Graph nicht verfuegbar ist oder eine Anfrage fehlschlaegt, wird auf den Standardversand von PrestaShop zurueckgefallen.
- In der Modulkonfiguration gibt es ein eigenes Feld `Test recipient email` mit Button `Send test email`, um den Graph-Versand direkt zu pruefen.
- Bei Graph-Fehlern werden HTTP-Status und API-Fehlerdetails im Modul-Backend angezeigt (`Last Microsoft Graph error`).
- Optionaler Schalter `Debug template variables`: schreibt bei Bedarf Variable-Keys und nicht ersetzte Platzhalter in die PrestaShop-Logs (zum Troubleshooting, standardmaessig aus).
- Debug-Logs enthalten eine Request-ID (`rid=...`), damit zusammengehoerige Eintraege pro Versandversuch leicht gefiltert werden koennen.

## Test mit Docker

```bash
ln -s /internauten/InternautenMix/internautengraph /var/www/html/modules/internautengraph
chown -h www-data:www-data /var/www/html/modules/internautengraph
```
