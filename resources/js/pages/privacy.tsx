import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

/** Datenschutzerklärung (#42); mit «kenntnisnahme» nach dem Login bei neuer Version. */
export default function Privacy({
    html,
    version,
    entwurf,
    kenntnisnahme = false,
}: {
    html: string;
    version: string;
    entwurf: boolean;
    kenntnisnahme?: boolean;
}) {
    const form = useForm({});
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/datenschutz/kenntnisnahme');
    };

    return (
        <>
            <Head title="Datenschutz" />
            {entwurf ? (
                <Alert className="mb-6" role="note">
                    <AlertTitle>Entwurf</AlertTitle>
                    <AlertDescription>
                        Diese Datenschutzerklärung ist noch nicht rechtlich
                        geprüft und gilt als Entwurf (Version {version}).
                    </AlertDescription>
                </Alert>
            ) : null}
            {kenntnisnahme ? (
                <Alert className="mb-6" role="status">
                    <AlertTitle>Bitte zur Kenntnis nehmen</AlertTitle>
                    <AlertDescription>
                        Die Datenschutzerklärung hat eine neue Version (
                        {version}). Bitte lesen und unten bestätigen.
                    </AlertDescription>
                </Alert>
            ) : null}
            <article
                className="markdown max-w-3xl"
                // eigener Inhalt aus docs/DATENSCHUTZ-ENTWURF.md, serverseitig ohne HTML-Eingaben gerendert
                dangerouslySetInnerHTML={{ __html: html }}
            />
            {kenntnisnahme ? (
                <form onSubmit={submit} className="mt-8">
                    <Button type="submit" disabled={form.processing}>
                        Zur Kenntnis genommen (Version {version})
                    </Button>
                </form>
            ) : null}
        </>
    );
}
