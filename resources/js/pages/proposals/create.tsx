import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Werte = {
    official_abbreviation: string;
    official_name: string;
    short_description: string;
    description: string;
    url: string;
    alternative_aliases: string[];
    categories: string[];
};

type Form = {
    begriff: string;
    werte: Omit<Werte, 'alternative_aliases'> & { alternative_aliases: string };
    begruendung: string;
};

/** Vorschlag einreichen (#113, DEC-024): Korrektur zu einem veröffentlichten Begriff oder neuer Begriff. */
export default function ProposalCreate({
    begriff,
    werte,
    felder,
    kategorien,
}: {
    begriff: { slug: string; titel: string; revision: number | null } | null;
    werte: Werte;
    felder: Record<keyof Werte, string>;
    kategorien: { key: string; label: string }[];
}) {
    const form = useForm<Form>({
        begriff: begriff?.slug ?? '',
        // robust gegen fehlende Werte älterer Abbilder (#133)
        werte: {
            official_abbreviation: werte.official_abbreviation ?? '',
            official_name: werte.official_name ?? '',
            short_description: werte.short_description ?? '',
            description: werte.description ?? '',
            url: werte.url ?? '',
            alternative_aliases: Array.isArray(werte.alternative_aliases)
                ? werte.alternative_aliases.join('; ')
                : '',
            categories: Array.isArray(werte.categories) ? werte.categories : [],
        },
        begruendung: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const setWert = <K extends keyof Form['werte']>(
        key: K,
        value: Form['werte'][K],
    ) => form.setData('werte', { ...form.data.werte, [key]: value });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/vorschlaege');
    };
    const text = (
        key: Exclude<keyof Form['werte'], 'categories'>,
        hint?: string,
        max?: number,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`v-${key}`}>{felder[key]}</Label>
            <Input
                id={`v-${key}`}
                value={form.data.werte[key]}
                maxLength={max}
                onChange={(e) => setWert(key, e.target.value)}
            />
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
        </div>
    );

    return (
        <>
            <Head
                title={
                    begriff ? 'Korrektur vorschlagen' : 'Begriff vorschlagen'
                }
            />
            <p className="text-sm">
                <Link
                    href={begriff ? `/begriffe/${begriff.slug}` : '/'}
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← {begriff ? begriff.titel : 'Alle Begriffe'}
                </Link>
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                {begriff ? 'Korrektur vorschlagen' : 'Begriff vorschlagen'}
            </h1>
            <p className="mt-2 max-w-prose text-sm text-muted-foreground">
                {begriff
                    ? `Ändern Sie die Felder, die nicht stimmen (Stand V${begriff.revision ?? ''}). Übermittelt werden nur die Änderungen.`
                    : 'Abkürzung oder Bezeichnung genügt; je mehr Angaben und Quellen, desto schneller die Prüfung.'}{' '}
                Die Redaktion prüft jeden Vorschlag; veröffentlicht wird erst
                nach Freigabe. Ihr Vorschlag ist nicht öffentlich – den Stand
                sehen Sie unter{' '}
                <Link
                    href="/vorschlaege"
                    className="text-link underline-offset-4 hover:underline"
                >
                    «Meine Vorschläge»
                </Link>
                .
            </p>

            <form onSubmit={submit} className="mt-6 grid max-w-2xl gap-4">
                <div className="grid gap-4 sm:grid-cols-[12rem_1fr]">
                    {text('official_abbreviation', undefined, 60)}
                    {text('official_name', undefined, 255)}
                </div>
                {text(
                    'short_description',
                    `${form.data.werte.short_description.length} / 160 Zeichen`,
                    160,
                )}
                <div className="grid gap-2">
                    <Label htmlFor="v-description">{felder.description}</Label>
                    <Textarea
                        id="v-description"
                        rows={5}
                        value={form.data.werte.description}
                        maxLength={5000}
                        onChange={(e) => setWert('description', e.target.value)}
                    />
                </div>
                {text('url', 'Mit https:// – z. B. amtliche Quelle.', 255)}
                {text(
                    'alternative_aliases',
                    'Mehrere mit Strichpunkt trennen.',
                    1000,
                )}
                {kategorien.length > 0 ? (
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">
                            {felder.categories}
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {kategorien.map((k) => (
                                <label
                                    key={k.key}
                                    className="flex items-center gap-3 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.werte.categories.includes(
                                            k.key,
                                        )}
                                        onCheckedChange={(v) =>
                                            setWert(
                                                'categories',
                                                v === true
                                                    ? [
                                                          ...form.data.werte
                                                              .categories,
                                                          k.key,
                                                      ]
                                                    : form.data.werte.categories.filter(
                                                          (c) => c !== k.key,
                                                      ),
                                            )
                                        }
                                    />
                                    {k.label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                ) : null}
                <div className="grid gap-2">
                    <Label htmlFor="v-begruendung">Begründung und Quelle</Label>
                    <Textarea
                        id="v-begruendung"
                        rows={3}
                        maxLength={2000}
                        placeholder="Was ist falsch oder fehlt? Woher stammt die Angabe?"
                        value={form.data.begruendung}
                        onChange={(e) =>
                            form.setData('begruendung', e.target.value)
                        }
                    />
                    <InputError message={errors.begruendung} />
                </div>
                <InputError message={errors.werte} />
                <div>
                    <Button type="submit" disabled={form.processing}>
                        Vorschlag einreichen
                    </Button>
                </div>
            </form>
        </>
    );
}
