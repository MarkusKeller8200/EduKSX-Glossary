import { useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Bestätigung als Dialog statt Browser-Fenster (#139): `await confirmAction('Frage?')` liefert true bzw. false.
 * Fokus im Dialog, Escape bricht ab, Text für Bildschirmleser. Der Host steckt in beiden Layouts.
 */
type Request = {
    message: string;
    confirmLabel: string;
    destructive: boolean;
    resolve: (ok: boolean) => void;
};

let current: Request | null = null;
const listeners = new Set<() => void>();
const emit = () => listeners.forEach((l) => l());

export function confirmAction(
    message: string,
    options: { confirmLabel?: string; destructive?: boolean } = {},
): Promise<boolean> {
    current?.resolve(false);

    return new Promise((resolve) => {
        current = {
            message,
            confirmLabel: options.confirmLabel ?? 'Bestätigen',
            destructive: options.destructive ?? false,
            resolve,
        };
        emit();
    });
}

function close(ok: boolean) {
    current?.resolve(ok);
    current = null;
    emit();
}

export default function ConfirmHost() {
    const request = useSyncExternalStore(
        (cb) => {
            listeners.add(cb);

            return () => listeners.delete(cb);
        },
        () => current,
        () => null,
    );

    return (
        <Dialog
            open={request !== null}
            onOpenChange={(open) => !open && close(false)}
        >
            <DialogContent role="alertdialog">
                <DialogHeader>
                    <DialogTitle>Bitte bestätigen</DialogTitle>
                    <DialogDescription>{request?.message}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => close(false)}>
                        Abbrechen
                    </Button>
                    <Button
                        variant={
                            request?.destructive ? 'destructive' : 'default'
                        }
                        onClick={() => close(true)}
                        autoFocus
                    >
                        {request?.confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
