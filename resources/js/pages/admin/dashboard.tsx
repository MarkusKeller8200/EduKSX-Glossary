import { Head, usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { versionLabel } from '@/components/version-label';

/** Startseite der Administration. Redaktion, Import und Moderation folgen ab 0.2. */
export default function Dashboard({ admins }: { admins: number }) {
    const { appVersion, auth } = usePage().props;

    return (
        <>
            <Head title="Administration" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Willkommen, {auth.user?.name}
            </h1>
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
            </div>
            <Alert className="mt-6">
                <AlertTitle>Nächste Schritte</AlertTitle>
                <AlertDescription>
                    Zweiter Faktor für Admins, Datenmodell und Übernahme der
                    Begriffe aus dem Intranet (Meilenstein 0.2).
                </AlertDescription>
            </Alert>
        </>
    );
}
