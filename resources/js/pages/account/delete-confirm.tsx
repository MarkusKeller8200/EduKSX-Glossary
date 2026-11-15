import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

/** Konto löschen per Link (#445): Seite aus der Mail; erst der Klick löscht. */
export default function DeleteConfirm({
    aktion,
    email,
}: {
    aktion: string;
    email: string;
}) {
    const form = useForm({});
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(aktion);
    };

    return (
        <>
            <Head title="Konto löschen" />
            <div className="mx-auto max-w-md">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Konto löschen
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Das Konto <strong className="break-all">{email}</strong>{' '}
                    wird gelöscht und alle Sitzungen werden beendet. Das lässt
                    sich nicht rückgängig machen.
                </p>
                <form onSubmit={submit} className="mt-6">
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={form.processing}
                    >
                        Konto endgültig löschen
                    </Button>
                </form>
            </div>
        </>
    );
}
