import { Link } from '@inertiajs/react';
import { ExternalLink, Link2 } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    ListBadge,
    RevisionBadges,
    StateBadge,
    StatusBadge,
} from '@/components/tags';

export type TermCardData = {
    code: string;
    abkuerzung: string;
    bezeichnung: string;
    kurz: string;
    beschreibung: string;
    /** nur Administration */
    notiz?: string;
    url: string;
    aliase: string[];
    schreibweisen: string[];
    suchbegriffe: string;
    gueltigAb: string | null;
    gueltigBis: string | null;
    kategorien: string[];
    herkunft: string[];
    status: string;
    /** nur Administration */
    aktiv?: boolean;
    revision?: number;
    veroeffentlicht?: number | null;
    entwurf?: boolean;
    angelegt?: string | null;
    geaendert?: string | null;
};

const datum = (iso: string | null | undefined): string =>
    iso ? new Date(iso).toLocaleDateString('de-CH') : '';

/**
 * Kachel eines Begriffs mit allen Werten (#205), angelehnt an die Glossar-Kachel im Intranet: Kopf zweizeilig (#229)
 * mit Abkürzung | Herkunft und Zustand, Bezeichnung | Kategorien; dann Kurzbeschreibung, lange Texte gekürzt (ganzer
 * Text als Tooltip) und unten klein die übrigen Angaben. Leere Werte brauchen keinen Platz.
 */
export default function TermCard({
    begriff: b,
    href,
    oeffentlich = false,
}: {
    begriff: TermCardData;
    /** Ziel des Titels: Bearbeiten (Administration) bzw. Detailseite (Website, #213) */
    href: string;
    /** Website: ohne interne Angaben (Notiz, Veröffentlichung, Daten der Redaktion), Status nur wenn nicht aktuell */
    oeffentlich?: boolean;
}) {
    const titel = b.abkuerzung || b.bezeichnung || '(ohne Bezeichnung)';
    const zeigeBezeichnung = b.abkuerzung !== '' && b.bezeichnung !== '';
    const gueltig =
        b.gueltigAb || b.gueltigBis
            ? [
                  b.gueltigAb ? `ab ${datum(b.gueltigAb)}` : '',
                  b.gueltigBis ? `bis ${datum(b.gueltigBis)}` : '',
              ]
                  .filter(Boolean)
                  .join(' ')
            : '';
    const details: [string, ReactNode][] = (
        [
            ['Aliase', b.aliase.join(', ')],
            ['Schreibweisen', b.schreibweisen.join(', ')],
            ['Suchbegriffe', b.suchbegriffe],
            ['Gültig', gueltig],
            ['Angelegt', oeffentlich ? '' : datum(b.angelegt)],
            ['Geändert', oeffentlich ? '' : datum(b.geaendert)],
        ] as [string, string][]
    ).filter(([, v]) => v !== '');

    return (
        <article className="flex h-full min-w-0 flex-col gap-2 break-words">
            {/* Kopf zweizeilig (#229): Abkürzung | Herkunft, Status – Bezeichnung | Kategorien */}
            <header className="grid gap-1">
                <div className="flex flex-wrap items-start justify-between gap-x-2 gap-y-1">
                    <Link
                        href={href}
                        className="min-w-0 text-base font-semibold break-words text-link underline-offset-4 hover:underline"
                        translate="no"
                    >
                        {titel}
                    </Link>
                    <div className="ml-auto flex flex-wrap justify-end gap-1">
                        {b.herkunft.map((l) => (
                            <ListBadge
                                key={`h-${l}`}
                                list="herkunft"
                                label={l}
                            />
                        ))}
                        {!oeffentlich || b.status !== 'aktuell' ? (
                            <StatusBadge status={b.status} />
                        ) : null}
                        {!oeffentlich && b.revision !== undefined ? (
                            <RevisionBadges
                                published={b.veroeffentlicht ?? null}
                                revision={b.revision}
                                draft={b.entwurf}
                            />
                        ) : null}
                        {!oeffentlich && b.aktiv === false ? (
                            <StateBadge state="inactive">inaktiv</StateBadge>
                        ) : null}
                    </div>
                </div>
                {zeigeBezeichnung || b.kategorien.length > 0 ? (
                    <div className="flex flex-wrap items-start justify-between gap-x-2 gap-y-1">
                        {zeigeBezeichnung ? (
                            <p
                                className="min-w-0 text-sm break-words"
                                translate="no"
                            >
                                {b.bezeichnung}
                            </p>
                        ) : null}
                        {b.kategorien.length > 0 ? (
                            <div className="ml-auto flex flex-wrap justify-end gap-1">
                                {b.kategorien.map((l) => (
                                    <ListBadge
                                        key={`k-${l}`}
                                        list="kategorien"
                                        label={l}
                                    />
                                ))}
                            </div>
                        ) : null}
                    </div>
                ) : null}
            </header>

            {b.kurz ? (
                <p className="text-sm" translate="no">
                    {b.kurz}
                </p>
            ) : null}

            {b.beschreibung ? (
                <Text label="Beschreibung" text={b.beschreibung} />
            ) : null}
            {!oeffentlich && b.notiz ? (
                <Text label="Notiz" text={b.notiz} />
            ) : null}

            {details.length > 0 ? (
                <dl className="mt-auto flex flex-wrap gap-x-3 gap-y-0.5 border-t border-border pt-2 text-xs text-muted-foreground">
                    {details.map(([label, value]) => (
                        <div
                            key={label}
                            className="flex max-w-full min-w-0 gap-1"
                        >
                            <dt className="font-medium text-foreground/70">
                                {label}:
                            </dt>
                            <dd className="truncate" translate="no">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            ) : null}

            {b.code || b.url ? (
                <footer className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                    {b.code ? (
                        <a
                            href={`/g/${b.code}`}
                            className="inline-flex items-center gap-1 font-mono text-link hover:underline"
                            title="Permalink"
                        >
                            <Link2 className="size-3" aria-hidden />
                            /g/{b.code}
                        </a>
                    ) : null}
                    {b.url ? (
                        <a
                            href={b.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="ml-auto inline-flex min-w-0 items-center gap-1 text-link hover:underline"
                            title={b.url}
                        >
                            <span className="truncate">Quelle</span>
                            <ExternalLink className="size-3" aria-hidden />
                        </a>
                    ) : null}
                </footer>
            ) : null}
        </article>
    );
}

function Text({ label, text }: { label: string; text: string }) {
    return (
        <p
            className="line-clamp-3 text-xs whitespace-pre-line text-muted-foreground"
            title={text}
            translate="no"
        >
            <span className="font-medium text-foreground/70">{label}:</span>{' '}
            {text}
        </p>
    );
}
