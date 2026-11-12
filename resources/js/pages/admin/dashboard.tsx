import { Head, Link, usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { versionLabel } from '@/components/version-label';
import { can } from '@/lib/access';

/** Startseite der Administration. Redaktion, Import und Moderation folgen ab 0.2. */
type Stats = {
    terms: number;
    published: number;
    categories: number;
    sources: number;
    release: number | null;
};

export default function Dashboard({
    admins,
    stats,
    update,
}: {
    admins: number;
    stats: Stats;
    /** neue Version verfügbar (#176), nur mit Recht «system» */
    update: { neueste: string; art: string | null; url: string | null } | null;
}) {
    const { appVersion, auth } = usePage().props;

    return (
        <>
            <Head title="Administration" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Willkommen, {auth.user?.name}
            </h1>
            {update ? (
                <p
                    role="status"
                    className="mt-4 rounded-md border border-info/30 bg-info-soft px-3 py-2 text-sm text-info"
                >
                    Neue Version {update.neueste} verfügbar
                    {update.art === 'korrektur'
                        ? ' (Korrektur)'
                        : update.art === 'funktion'
                          ? ' (neue Funktionen)'
                          : update.art === 'hauptversion'
                            ? ' (Hauptversion)'
                            : ''}
                    .{' '}
                    <Link
                        href="/admin/system"
                        className="underline underline-offset-4"
                    >
                        Zu den Updates
                    </Link>
                </p>
            ) : null}
            <div className="mt-6 grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Version</CardTitle>
                    </CardHeader>
                    <CardContent>{versionLabel(appVersion)}</CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Admin-Konten</CardTitle>
                    </CardHeader>
                    <CardContent>{admins}</CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Begriffe</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {stats.terms} gesamt, davon {stats.published}{' '}
                        veröffentlicht
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Listen und Datenstand</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {stats.categories} Kategorien, {stats.sources} Herkünfte
                        · Datenstand {stats.release ?? '–'}
                    </CardContent>
                </Card>
            </div>
            <div className="mt-6 flex flex-wrap gap-3">
                {can(auth, 'import') ? (
                    <Button asChild>
                        <Link href="/admin/import">Excel-Import</Link>
                    </Button>
                ) : null}
                <Button asChild variant="outline">
                    <Link href="/admin/begriffe">Begriffe anzeigen</Link>
                </Button>
                {can(auth, 'export') && can(auth, 'edit-terms') ? (
                    <Button asChild variant="outline">
                        <a href="/admin/export">Excel-Export</a>
                    </Button>
                ) : null}
            </div>
            <Alert className="mt-6">
                <AlertTitle>Nächste Schritte</AlertTitle>
                <AlertDescription>
                    Zweiter Faktor für Admins, Redaktion mit Tabelle und
                    Excel-Import aus dem Intranet (Meilenstein 0.2).
                </AlertDescription>
            </Alert>
        </>
    );
}
