/**
 * PDF-Export des Glossars (#112) als Druckansicht des Browsers («Als PDF speichern»), ohne zusätzliches Paket.
 * Übernommen aus dem Intranet (`resources/js/lib/glossary.ts` → `printGlossary`, dort #58, #65, #70, #73, #79) und
 * angepasst: Zeilen aus dem Glossary (veröffentlicht bzw. Arbeitsstand), Lizenzhinweis CC BY 4.0.
 */
import { isColorName, printPalette } from '@/lib/colors';

/** Zeile vom Server (`/export?format=json`, `/admin/export?format=json`, App\Glossary\PrintRow). */
export type PrintRow = {
    id: string;
    slug: string;
    abk: string;
    bez: string;
    aliase: string[];
    kurz: string;
    beschreibung: string;
    url: string;
    kategorien: string[];
    herkunft: string[];
    status: string;
    gueltig_ab: string;
    gueltig_bis: string;
    bedeutungen: number;
    notiz?: string;
};

export type PdfScope = 'kompakt' | 'erweitert' | 'vollstaendig';
export type PdfGroup = 'keine' | 'kategorie' | 'herkunft' | 'status';
export type PdfOptions = {
    format: 'hoch' | 'quer';
    umfang: PdfScope;
    gruppe: PdfGroup;
    umbruch: 'keiner' | 'gruppe';
};

export const PDF_DEFAULT: PdfOptions = {
    format: 'hoch',
    umfang: 'kompakt',
    gruppe: 'kategorie',
    umbruch: 'keiner',
};

/** Farben der Labels je Liste (Label → Palettenname), wie auf der Website. */
export type TagColors = {
    kategorien: Record<string, string | null>;
    herkunft: Record<string, string | null>;
};

/** Lizenzhinweis der Daten (DEC-016). */
export const LICENSE_NOTE =
    'Daten: EduKSX-Glossary (glossary.eduksx.ch), Lizenz CC BY 4.0 – https://creativecommons.org/licenses/by/4.0/deed.de';

const esc = (s: string) =>
    s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

const sortKey = (r: PrintRow) => r.abk || r.bez;
const byAbbreviation = (list: PrintRow[]) =>
    [...list].sort((a, b) => sortKey(a).localeCompare(sortKey(b), 'de-CH'));

/** Hochgestellte Zahl bei Mehrfachbedeutung. */
const sup = (r: PrintRow) =>
    r.bedeutungen > 1 ? `<sup>${r.bedeutungen}</sup>` : '';

const tagStyle = (color: string | null | undefined) => {
    const c = color && isColorName(color) ? printPalette[color] : null;

    return c
        ? ` style="background:${c.bg};border-color:${c.border};color:${c.text}"`
        : '';
};

const tags = (list: string[], colors: Record<string, string | null> = {}) =>
    list
        .map((t) => `<span class="tag"${tagStyle(colors[t])}>${esc(t)}</span>`)
        .join(' ');

/** HTML-Element mit Inhalt. */
const tag = (name: string, inner: string) => `<${name}>${inner}</${name}>`;
/** Begriff ohne Abkürzung: «–» in der Abkürzungsspalte, Begriff kursiv. */
const abkHtml = (r: PrintRow) =>
    r.abk ? tag('b', esc(r.abk)) : tag('small', '–');
const bezHtml = (r: PrintRow) => (r.abk ? esc(r.bez) : tag('i', esc(r.bez)));

/** Link nur für http(s)-Adressen (keine javascript:- oder data:-Ziele). */
const anchor = (href: string, text: string) =>
    /^https?:\/\//i.test(href) ? `<a href="${esc(href)}">${text}</a>` : '';

/** Links eines Eintrags: URL, dann Links aus Beschreibung und Notiz (Markdown), ohne Doppelte. */
function rowLinks(r: PrintRow): string[] {
    const found = [
        r.url,
        ...[
            ...`${r.beschreibung}\n${r.notiz ?? ''}`.matchAll(
                /\]\((https?:\/\/[^\s)]+)\)/gi,
            ),
        ].map((m) => m[1]),
    ];

    return [...new Set(found.filter((u) => /^https?:\/\//i.test(u)))];
}

/** Gliedern nach Kategorie, Herkunft oder Status (Mehrfachwerte: in jeder ihrer Gruppen). */
function groupRows(rows: PrintRow[], by: PdfGroup): [string, PrintRow[]][] {
    if (by === 'keine') {
        return [['', byAbbreviation(rows)]];
    }
    const groups = new Map<string, PrintRow[]>();
    for (const r of rows) {
        const keys =
            by === 'status'
                ? [r.status]
                : by === 'kategorie'
                  ? r.kategorien
                  : r.herkunft;
        for (const k of keys.length ? keys : ['(ohne Angabe)']) {
            groups.set(k, [...(groups.get(k) ?? []), r]);
        }
    }

    return [...groups.entries()]
        .sort(([a], [b]) => a.localeCompare(b, 'de-CH'))
        .map(([k, list]) => [k, byAbbreviation(list)]);
}

type Column = {
    title: string;
    width: number;
    cell: (r: PrintRow) => string;
    cls?: string;
};

/** Spalten je Umfang; die Spalte der Gruppierung entfällt. */
function columns(
    o: PdfOptions,
    colors: TagColors,
    withNote: boolean,
): Column[] {
    // Schmale Spalte «Link»: je Zeile 1, 2, 3 …, der Link liegt dahinter
    const link: Column = {
        title: 'Link',
        width: 4.5,
        cell: (r) =>
            rowLinks(r)
                .map((u, i) => anchor(u, String(i + 1)))
                .join(' '),
        cls: 'link',
    };
    const abk = (full: boolean): Column => ({
        title: 'Abkürzung',
        width: full ? 9 : 12,
        cell: (r) =>
            abkHtml(r) +
            sup(r) +
            (full && r.aliase.length
                ? `<br><small>Alias: ${esc(r.aliase.join('; '))}</small>`
                : ''),
    });
    const bez = (full: boolean): Column => ({
        title: 'Bezeichnung',
        width: full ? 20 : 40,
        cell: bezHtml,
    });
    const kurz: Column = {
        title: 'Kurzbeschreibung',
        width: 26,
        cell: (r) => esc(r.kurz),
    };
    const kat: Column = {
        title: 'Kategorie',
        width: 11,
        cell: (r) => tags(r.kategorien, colors.kategorien),
    };
    const her: Column = {
        title: 'Herkunft',
        width: 10,
        cell: (r) => tags(r.herkunft, colors.herkunft),
    };
    const sta: Column = {
        title: 'Status',
        width: 8,
        cell: (r) => {
            const v = [
                r.gueltig_ab && `ab ${esc(r.gueltig_ab)}`,
                r.gueltig_bis && `bis ${esc(r.gueltig_bis)}`,
            ]
                .filter(Boolean)
                .join('<br>');

            return `${esc(r.status)}${v ? `<br><small>${v}</small>` : ''}`;
        },
    };
    const without = (list: Column[]) =>
        list.filter(
            (c) =>
                !(o.gruppe === 'kategorie' && c === kat) &&
                !(o.gruppe === 'herkunft' && c === her) &&
                !(o.gruppe === 'status' && c === sta),
        );

    if (o.umfang === 'kompakt') {
        return without([abk(false), bez(false), kurz, kat, her, link]);
    }
    if (o.umfang === 'erweitert') {
        return without([
            abk(true),
            bez(true),
            kurz,
            {
                title: 'Beschreibung',
                width: 24,
                cell: (r) => esc(r.beschreibung),
            },
            kat,
            her,
            link,
        ]);
    }

    return without([
        abk(true),
        bez(true),
        kurz,
        {
            title: 'Beschreibung',
            width: 18,
            cell: (r) => esc(r.beschreibung),
            cls: 'pre',
        },
        kat,
        her,
        sta,
        // Notiz nur im Arbeitsstand der Redaktion (öffentlich nicht vorhanden)
        ...(withNote
            ? [
                  {
                      title: 'Notiz',
                      width: 12,
                      cell: (r: PrintRow) => esc(r.notiz ?? ''),
                      cls: 'pre',
                  },
              ]
            : []),
        link,
    ]);
}

function table(list: PrintRow[], cols: Column[], compact: boolean): string {
    const total = cols.reduce((sum, c) => sum + c.width, 0);

    return `<table class="${compact ? 'compact' : 'full'}"><colgroup>${cols.map((c) => `<col style="width:${((c.width / total) * 100).toFixed(1)}%">`).join('')}</colgroup><thead><tr>${cols.map((c) => `<th>${c.title}</th>`).join('')}</tr></thead><tbody>${list
        .map(
            (r) =>
                `<tr>${cols.map((c) => `<td${c.cls ? ` class="${c.cls}"` : ''}>${c.cell(r)}</td>`).join('')}</tr>`,
        )
        .join('')}</tbody></table>`;
}

const UMFANG = {
    kompakt: 'kompakt',
    erweitert: 'erweitert',
    vollstaendig: 'vollständig',
} as const;
const GRUPPE = {
    keine: '',
    kategorie: 'nach Kategorie',
    herkunft: 'nach Herkunft',
    status: 'nach Status',
} as const;

/** HTML der Druckansicht (eigene Funktion für Tests und Vorschau). */
export function printHtml(
    rows: PrintRow[],
    options: PdfOptions = PDF_DEFAULT,
    colors: TagColors = { kategorien: {}, herkunft: {} },
    titel = 'Glossary',
): string {
    const compact = options.umfang === 'kompakt';
    const cols = columns(
        options,
        colors,
        rows.some((r) => r.notiz !== undefined),
    );
    const body = groupRows(rows, options.gruppe)
        .map(
            ([title, list]) =>
                `<section>${title ? `<h2>${esc(title)} <small>(${list.length})</small></h2>` : ''}${table(list, cols, compact)}</section>`,
        )
        .join('');
    const page = options.format === 'hoch' ? 'portrait' : 'landscape';
    const meta = [
        `${rows.length} Einträge`,
        UMFANG[options.umfang],
        GRUPPE[options.gruppe],
        `Stand ${new Date().toLocaleDateString('de-CH')}`,
    ]
        .filter(Boolean)
        .join(' · ');

    return `<!doctype html><html lang="de-CH"><head><meta charset="utf-8"><title>${esc(titel)} (${UMFANG[options.umfang]})</title>
<style>@page{size:A4 ${page};margin:12mm}*{box-sizing:border-box}body{font:9pt/1.35 Inter,system-ui,sans-serif;margin:24px;color:#111}h1{font-size:15pt;margin:0 0 2px}h2{font-size:11pt;margin:14px 0 4px;break-after:avoid}h2 small{font-weight:normal;color:#666}
table{border-collapse:collapse;width:100%;table-layout:fixed}th,td{border-bottom:1px solid #ccc;padding:2px 5px;text-align:left;vertical-align:top}th{background:#f2f4f6;font-size:8pt}
.compact td{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:8.5pt}td a{color:#0272ad;font-weight:600;text-decoration:none}td.link,th:last-child{text-align:center}
.full td{font-size:8pt;overflow-wrap:anywhere}.full .pre{white-space:pre-wrap}
small{color:#555}.tag{-webkit-print-color-adjust:exact;print-color-adjust:exact;display:inline-block;border:1px solid #bbb;border-radius:3px;padding:0 4px;margin:0 2px 1px 0;font-size:7.5pt;line-height:1.4;background:#f6f7f8}
${options.umbruch === 'gruppe' ? 'section+section{break-before:page}' : ''}
p.meta{margin:0 0 6px;color:#555}p.license{margin-top:10px}@media print{body{margin:0}tr{break-inside:avoid}th{-webkit-print-color-adjust:exact;print-color-adjust:exact}}</style></head><body>
<h1>${esc(titel)}</h1><p class="meta">${meta}${rows.some((r) => r.bedeutungen > 1) ? ' · hochgestellte Zahl = Anzahl verschiedener Bedeutungen der Abkürzung' : ''}</p>${body}<p class="meta license">${esc(LICENSE_NOTE)}</p>
<script>window.onload=()=>window.print()</script></body></html>`;
}

/**
 * Druckansicht in einem Fenster öffnen; false, wenn der Browser das Fenster blockiert. `target`: schon geöffnetes
 * Fenster (vor dem Laden der Daten geöffnet, sonst blockieren Browser es).
 */
export function printGlossary(
    rows: PrintRow[],
    options: PdfOptions = PDF_DEFAULT,
    colors: TagColors = { kategorien: {}, herkunft: {} },
    titel = 'Glossary',
    target: Window | null = null,
): boolean {
    const w = target ?? window.open('', '_blank');
    if (!w) {
        return false;
    }
    w.document.open();
    w.document.write(printHtml(rows, options, colors, titel));
    w.document.close();

    return true;
}
