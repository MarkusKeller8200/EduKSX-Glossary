# Synchronisationsvertrag – Entwurf

**Pflicht:** Das Intranet synchronisiert veröffentlichte Daten periodisch über HTTPS/JSON REST `/api/v1`.

- Vollsnapshot und inkrementelle Änderungen (`since`-Cursor) inklusive Tombstones.
- Stabile IDs, Revisionen, Publikationszeitpunkte, atomare Cursor und reproduzierbare Reihenfolge.
- ETag und `304 Not Modified`, Pagination und klar definierte Fehlermeldungen.
- Bei Fehlern bleibt letzter gültiger lokaler Snapshot erhalten; später idempotent nachziehen.
- Keine unpublizierten Entwürfe, Anträge oder redaktionellen personenbezogenen Daten ausliefern.
- Implementierungsdetails zu Intervall, Auth und Retention sind offene Entscheide.
