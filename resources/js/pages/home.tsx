import { Head, usePage } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

/** Startseite: Suche und Begriffe folgen mit dem Datenmodell (0.2) und der öffentlichen Website (0.3). */
export default function Home() {
    const { links } = usePage().props;

    return (
        <>
            <Head title="Begriffe und Abkürzungen" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Begriffe und Abkürzungen
            </h1>
            <p className="mt-2 max-w-2xl text-muted-foreground">
                Ein öffentliches Glossar für Schweizer Volksschulen,
                Schulgemeinden und Gemeinden: Abkürzungen und Begriffe mit
                Erklärung, Quelle und Gültigkeit.
            </p>

            <div className="mt-6 max-w-xl">
                <label htmlFor="suche" className="sr-only">
                    Begriff suchen
                </label>
                <Input
                    id="suche"
                    type="search"
                    placeholder="Begriff suchen …"
                    disabled
                    aria-describedby="suche-hinweis"
                />
                <p
                    id="suche-hinweis"
                    className="mt-2 text-sm text-muted-foreground"
                >
                    Die Suche folgt, sobald die ersten Begriffe übernommen sind.
                </p>
            </div>

            <Card className="mt-8">
                <CardHeader>
                    <CardTitle>Mitmachen</CardTitle>
                    <CardDescription>
                        Fehlt ein Begriff oder stimmt eine Erklärung nicht?
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {links.githubRepo ? (
                        <a
                            href={`https://github.com/${links.githubRepo}/issues/new/choose`}
                            className="text-link underline underline-offset-4"
                        >
                            Begriff vorschlagen oder Fehler melden
                        </a>
                    ) : null}
                </CardContent>
            </Card>
        </>
    );
}
