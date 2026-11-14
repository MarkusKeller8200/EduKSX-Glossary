<?php

namespace App\Console\Commands;

use App\Data\Purposes;
use Illuminate\Console\Command;

/**
 * Verzeichnis der Bearbeitungen aus dem Code erzeugen (DB-Standard B9, #314): docs/DATENSCHUTZ-VERZEICHNIS.md nach
 * der Vorlage der Plattform (EduKSX-Architektur docs/vorlagen/DATENSCHUTZ-VERZEICHNIS.md). Zwecke, Tabellen,
 * Datenklassen und Operationen kommen aus app/Data/Purposes.php und app/Data/DataClasses.php.
 */
class ProcessingRecord extends Command
{
    protected $signature = 'glossary:verzeichnis {--check : Fehler, wenn die Datei nicht dem Code entspricht}';

    protected $description = 'Verzeichnis der Bearbeitungen (docs/DATENSCHUTZ-VERZEICHNIS.md) erzeugen oder prüfen';

    public const PATH = 'docs/DATENSCHUTZ-VERZEICHNIS.md';

    public function handle(): int
    {
        $path = base_path(self::PATH);
        $content = self::render();
        if ($this->option('check')) {
            if ((string) @file_get_contents($path) !== $content) {
                $this->error(self::PATH.' ist veraltet: php artisan glossary:verzeichnis');

                return self::FAILURE;
            }
            $this->info(self::PATH.' ist aktuell.');

            return self::SUCCESS;
        }
        file_put_contents($path, $content);
        $this->info(self::PATH.' geschrieben.');

        return self::SUCCESS;
    }

    public static function render(): string
    {
        $lines = [
            '# Verzeichnis der Bearbeitungen – Modul `glossary`',
            '',
            'Erzeugt aus `app/Data/Purposes.php` und `app/Data/DataClasses.php` mit `php artisan glossary:verzeichnis` – nicht',
            'von Hand ändern. Vorlage: EduKSX-Architektur `docs/vorlagen/DATENSCHUTZ-VERZEICHNIS.md`. Grundlage: DB-Standard',
            'B7–B9, API-Standard C9. Arbeitshilfe, keine Rechtsberatung; rechtliche Prüfung offen (#278, #66). Fristen in',
            'eckigen Klammern sind noch nicht rechtlich geprüft.',
            '',
            '## 1. Angaben zum Modul',
            '',
            '| Feld | Inhalt |',
            '| --- | --- |',
            '| Modul (Schlüssel, Marketingname) | `glossary`, EduKSX-Glossary |',
            '| Betriebsart | zentral; selbst gehostet möglich (docs/SELBST-HOSTEN.md) |',
            '| Verantwortliche (Datenherrschaft) | Betreiber des Glossary; bei Organisationen die Schule bzw. Gemeinde für ihre Darstellung |',
            '| Anwendbares Recht | IDG ZH / DSG / DSGVO (strengstes gilt, DB B7) |',
            '| Höchste Datenklasse | personenbezogen (Konten); keine besonders schützenswerten Daten |',
            '| Folgenabschätzung nötig? | Kurz-Folgenabschätzung vor dem öffentlichen Betrieb (DB B10, #278) |',
            '| Datenort | Hosting, Sicherungen und Protokolle in der Schweiz (docs/KONZEPT-HOSTING.md) |',
            '',
            '## 2. Bearbeitungen',
            '',
            '| Zweck | Rechtsgrundlage / Auftrag | Geltungsbereich | Betroffene | Datenkategorien (höchste Klasse je Tabelle) | Tabellen | Operationen (`operationId`) | Empfänger | Löschfrist |',
            '| --- | --- | --- | --- | --- | --- | --- | --- | --- |',
        ];
        foreach (Purposes::MAP as $purpose) {
            $classes = [];
            foreach ($purpose['tables'] as $table) {
                $classes[Purposes::highestClass($table)->label()][] = $table;
            }
            $categories = implode('; ', array_map(fn (string $label, array $tables): string => $label.' ('.count($tables).')', array_keys($classes), $classes));
            $lines[] = '| '.implode(' | ', [
                $purpose['purpose'],
                $purpose['basis'],
                $purpose['scope'],
                $purpose['subjects'],
                $categories,
                implode(', ', array_map(fn (string $t): string => '`'.$t.'`', $purpose['tables'])),
                $purpose['operations'] === [] ? '–' : implode(', ', array_map(fn (string $o): string => '`'.$o.'`', $purpose['operations'])),
                $purpose['recipients'],
                $purpose['retention'],
            ]).' |';
        }

        return implode("\n", array_merge($lines, [
            '',
            '## 3. Bekanntgaben und Unterauftragnehmer',
            '',
            '| Empfänger | Zweck | Daten | Ort | Grundlage / Vertrag |',
            '| --- | --- | --- | --- | --- |',
            '| Hosting (Infomaniak bzw. eigener Server) | Betrieb | alle | Schweiz | Auftragsbearbeitung |',
            '| Mail-Versand | Bestätigung, Hinweise | E-Mail-Adresse, Name | Schweiz | Auftragsbearbeitung |',
            '| Microsoft Entra ID | Anmeldung (nur nach Verknüpfung) | Kennung, Mandant | gemäss Vertrag der Schule | Einwilligung bei der Verknüpfung |',
            '| EduKSX-Ident (sobald angeschlossen) | Anmeldung, Ereignisse | Kennung, Rollen | Schweiz | Plattform S1, S4 |',
            '',
            '## 4. Technische und organisatorische Massnahmen',
            '',
            '- Geltungsbereiche getrennt (DB B1), Datenklassen je Spalte (DB B2, docs/DATENKLASSEN.md), Löschregister (DB B3)',
            '- Zweiter Faktor für privilegierte Rechte; Servicekonten mit Scopes und befristeten Tokens',
            '- Historie ohne personenbezogene Werte (DB A7); Sicherheitsprotokoll [5 Jahre]',
            '- Sicherung vor jedem Update und ausser Haus (docs/LOESCHKONZEPT.md); Kopien für dev und test nur anonymisiert (DB B12)',
            '',
            '## 5. Rechte der Betroffenen',
            '',
            '| Recht | Umsetzung im Glossary |',
            '| --- | --- |',
            '| Auskunft, Datenherausgabe | «Mein Konto» → «Meine Daten» (JSON) und Export in der Verwaltung (DB B8) |',
            '| Berichtigung | «Mein Konto»; Verwaltung |',
            '| Löschung | «Mein Konto» → Konto löschen; `ident.user.deleted` (S4); Löschkonzept |',
            '| Einschränkung | `users.is_processing_restricted` (DB B13) |',
            '',
        ]));
    }
}
