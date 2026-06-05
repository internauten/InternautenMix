# InternautenMix

Sammlung von PrestaShop-Modulen und Hilfsskripten fuer Entwicklung, Test und Paketierung.

## Inhalte

- [internautench](/home/dmo/internauten/InternautenMix/internautench): PrestaShop-Modul fuer Schweizer Zahlen- und Preisformatierung mit konfigurierbaren Locales.
- [internautengraph](/home/dmo/internauten/InternautenMix/internautengraph): PrestaShop-Modul, das den E-Mail-Versand via Microsoft Graph API (Office 365) ermoeglicht.
- [build-module-zip.sh](/home/dmo/internauten/InternautenMix/build-module-zip.sh): Erstellt aus einem Modulordner ein installierbares ZIP-Archiv.
- [dist](/home/dmo/internauten/InternautenMix/dist): Zielverzeichnis fuer erzeugte ZIP-Dateien.

## Modul-Dokumentation

Die modulbezogene Dokumentation liegt jeweils im Modulordner. Fuer das vorhandene Modul siehe:

- [internautench/README.md](/home/dmo/internauten/InternautenMix/internautench/README.md)

## ZIP-Build

Standardmaessig wird das Modul `internautench` gepackt:

```bash
./build-module-zip.sh
```

Optional kann ein anderer Modulordnername uebergeben werden:

```bash
./build-module-zip.sh modulname
```

Das erzeugte Archiv landet unter `dist/`.

## Entwicklung

- Modulcode liegt jeweils in einem eigenen Verzeichnis im Projekt-Root.
- Build-Artefakte wie ZIP-Dateien werden nicht versioniert.
- Die Root-[.gitignore](/home/dmo/internauten/InternautenMix/.gitignore) ignoriert lokale Build-, Test- und Editor-Artefakte.
