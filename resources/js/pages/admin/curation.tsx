import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardCheck, FileSearch, SearchX, Sparkles } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Bekannt = {
    abbreviation: string;
    count: number;
    term_id: string;
    title: string;
};
type Kandidat = {
    abbreviation: string;
    count: number;
    long_form: string | null;
    context: string;
};
type Regel = {
    key: string;
    label: string;
    hint: string;
    count: number;
    items: { id: string; title: string; detail: string | null }[];
};

/** Link zum vorausgefüllten Begriffsformular (Entwurf). */
function entwurf(abk: string, name: string | null, notiz: string): string {
    const p = new URLSearchParams({ official_abbreviation: abk, note: notiz });
    if (name) {
        p.set('official_name', name);
    }

    return `/admin/begriffe/neu?${p.toString()}`;
}

/**
 * Einsammeln und Prüfen (Konzept docs/KONZEPT-EINSAMMELN.md, Stufe 1 – Prototyp): Text prüfen, Gesucht, Prüfbedarf.
 * Der eingefügte Text wird nicht gespeichert.
 */
export default function Curation({
    text,
    ergebnis,
    gesucht,
    pruefbedarf,
    maxLaenge,
}: {
    text: string;
    ergebnis: { known: Bekannt[]; candidates: Kandidat[] } | null;
    gesucht: { suche: string; anzahl: number; zuletzt: string }[];
    pruefbedarf: Regel[];
    maxLaenge: number;
}) {
    const form = useForm({ text });
    const [tab, setTab] = useState<'text' | 'gesucht' | 'pruefen'>('text');
    const [offen, setOffen] = useState<string | null>(null);
    const pruefen = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/einsammeln/text', { preserveScroll: true });
    };
    const tabs = [
        { key: 'text' as const, label: 'Text prüfen', icon: FileSearch },
        {
            key: 'gesucht' as const,
            label: `Gesucht (${gesucht.length})`,
            icon: SearchX,
        },
        {
            key: 'pruefen' as const,
            label: `Prüfbedarf (${pruefbedarf.reduce((n, r) => n + r.count, 0)})`,
            icon: ClipboardCheck,
        },
    ];

    return (
        <>
            <Head title="Einsammeln" />
            <h1 className="flex items-center gap-2 text-3xl font-semibold tracking-tight">
                <Sparkles className="size-7" aria-hidden /> Einsammeln
            </h1>
            <p className="mt-2 max-w-3xl text-muted-foreground">
                Fehlende Begriffe finden und bestehende prüfen.{' '}
                <span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
                    Prototyp
                </span>
            </p>

            <div
                role="tablist"
                aria-label="Bereiche"
                className="mt-6 flex flex-wrap gap-2 border-b"
            >
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.key}
                        onClick={() => setTab(t.key)}
                        className={`-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium ${
                            tab === t.key
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <t.icon className="size-4" aria-hidden /> {t.label}
                    </button>
                ))}
            </div>

            {tab === 'text' ? (
                <section className="mt-6 max-w-4xl">
                    <form onSubmit={pruefen} className="grid gap-3">
                        <Label htmlFor="text">
                            Text einfügen (z. B. Elternbrief, Weisung, Seite des
                            Volksschulamts)
                        </Label>
                        <textarea
                            id="text"
                            rows={10}
                            maxLength={maxLaenge}
                            className="w-full rounded-md border bg-transparent p-3 text-sm"
                            value={form.data.text}
                            onChange={(e) =>
                                form.setData('text', e.target.value)
                            }
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            Der Text wird nur für die Prüfung verwendet und
                            nicht gespeichert. Höchstens{' '}
                            {maxLaenge.toLocaleString('de-CH')} Zeichen.
                        </p>
                        <InputError message={form.errors.text} />
                        <div>
                            <Button type="submit" disabled={form.processing}>
                                <FileSearch aria-hidden />
                                {form.processing
                                    ? 'Wird geprüft …'
                                    : 'Text prüfen'}
                            </Button>
                        </div>
                    </form>

                    {ergebnis !== null ? (
                        <div className="mt-8 grid gap-8">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Nicht im Glossary (
                                    {ergebnis.candidates.length})
                                </h2>
                                {ergebnis.candidates.length === 0 ? (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Keine unbekannten Kürzel gefunden.
                                    </p>
                                ) : (
                                    <ul className="mt-3 divide-y rounded-md border">
                                        {ergebnis.candidates.map((k) => (
                                            <li
                                                key={k.abbreviation}
                                                className="flex flex-wrap items-start justify-between gap-3 p-3"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="font-medium">
                                                        {k.abbreviation}
                                                        {k.long_form ? (
                                                            <span className="font-normal text-muted-foreground">
                                                                {' '}
                                                                – vermutlich «
                                                                {k.long_form}»
                                                            </span>
                                                        ) : null}
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            {k.count}×
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 text-sm break-words text-muted-foreground">
                                                        {k.context}
                                                    </div>
                                                </div>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={entwurf(
                                                            k.abbreviation,
                                                            k.long_form,
                                                            'Aus «Einsammeln – Text prüfen»',
                                                        )}
                                                    >
                                                        Als Entwurf anlegen
                                                    </Link>
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Schon im Glossary ({ergebnis.known.length})
                                </h2>
                                <ul className="mt-3 flex flex-wrap gap-2">
                                    {ergebnis.known.map((b) => (
                                        <li key={b.abbreviation}>
                                            <Link
                                                href={`/admin/begriffe/${b.term_id}`}
                                                className="inline-block rounded-full border px-3 py-1 text-sm hover:bg-muted"
                                                title={b.title}
                                            >
                                                {b.abbreviation}{' '}
                                                <span className="text-muted-foreground">
                                                    {b.count}×
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    ) : null}
                </section>
            ) : null}

            {tab === 'gesucht' ? (
                <section className="mt-6 max-w-3xl">
                    <p className="text-sm text-muted-foreground">
                        Häufigste Suchen ohne Treffer der letzten 90 Tage (ohne
                        Personenbezug). Alle Einträge unter{' '}
                        <Link
                            href="/admin/suchen-ohne-treffer"
                            className="underline"
                        >
                            Ohne Treffer
                        </Link>
                        .
                    </p>
                    {gesucht.length === 0 ? (
                        <p className="mt-4 text-sm text-muted-foreground">
                            Keine Suchen ohne Treffer.
                        </p>
                    ) : (
                        <ul className="mt-4 divide-y rounded-md border">
                            {gesucht.map((g) => (
                                <li
                                    key={g.suche}
                                    className="flex items-center justify-between gap-3 p-3"
                                >
                                    <div>
                                        <span className="font-medium">
                                            {g.suche}
                                        </span>
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {g.anzahl}× · zuletzt{' '}
                                            {g.zuletzt
                                                .split('-')
                                                .reverse()
                                                .join('.')}
                                        </span>
                                    </div>
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={entwurf(
                                                g.suche,
                                                null,
                                                'Aus «Einsammeln – Gesucht»',
                                            )}
                                        >
                                            Als Entwurf anlegen
                                        </Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ) : null}

            {tab === 'pruefen' ? (
                <section className="mt-6 max-w-4xl">
                    <p className="text-sm text-muted-foreground">
                        Veröffentlichte Begriffe nach festen Regeln. Je Regel
                        höchstens 50 Begriffe in der Liste.
                    </p>
                    <ul className="mt-4 grid gap-3">
                        {pruefbedarf.map((r) => (
                            <li key={r.key} className="rounded-md border">
                                <button
                                    type="button"
                                    className="flex w-full items-center justify-between gap-3 p-3 text-left"
                                    aria-expanded={offen === r.key}
                                    onClick={() =>
                                        setOffen(offen === r.key ? null : r.key)
                                    }
                                    disabled={r.count === 0}
                                >
                                    <span>
                                        <span className="font-medium">
                                            {r.label}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {r.hint}
                                        </span>
                                    </span>
                                    <span
                                        className={`rounded-full px-2.5 py-0.5 text-sm font-semibold ${
                                            r.count === 0
                                                ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100'
                                                : 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100'
                                        }`}
                                    >
                                        {r.count}
                                    </span>
                                </button>
                                {offen === r.key ? (
                                    <ul className="divide-y border-t">
                                        {r.items.map((t) => (
                                            <li
                                                key={t.id}
                                                className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                            >
                                                <Link
                                                    href={`/admin/begriffe/${t.id}`}
                                                    className="underline-offset-2 hover:underline"
                                                >
                                                    {t.title}
                                                </Link>
                                                <span className="flex items-center gap-3">
                                                    {t.detail ? (
                                                        <span className="text-muted-foreground">
                                                            {t.detail
                                                                .split('-')
                                                                .reverse()
                                                                .join('.')}
                                                        </span>
                                                    ) : null}
                                                    <Link
                                                        href={`/admin/begriffe/${t.id}/bearbeiten`}
                                                        className="underline"
                                                    >
                                                        Bearbeiten
                                                    </Link>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </>
    );
}
