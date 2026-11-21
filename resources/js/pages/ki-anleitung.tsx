import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/** Mit KI recherchieren (#481): Anleitung, Prompt zum Kopieren und Vorlage. */
export default function KiAnleitung({
    html,
    prompt,
}: {
    html: string;
    prompt: string;
}) {
    const [kopiert, setKopiert] = useState(false);
    const kopieren = async () => {
        try {
            await navigator.clipboard.writeText(prompt);
            setKopiert(true);
            window.setTimeout(() => setKopiert(false), 2500);
        } catch {
            setKopiert(false);
        }
    };

    return (
        <>
            <Head title="Mit KI recherchieren" />
            <div className="mb-6 flex flex-wrap gap-2">
                {prompt ? (
                    <Button type="button" onClick={kopieren}>
                        {kopiert ? 'Prompt kopiert' : 'Prompt kopieren'}
                    </Button>
                ) : null}
                <Button asChild variant="outline">
                    <a href="/mit-ki-recherchieren/vorlage.xlsx">
                        Vorlage herunterladen (.xlsx)
                    </a>
                </Button>
                <Button asChild variant="outline">
                    <a href="/mit-ki-recherchieren.md">Als Markdown</a>
                </Button>
            </div>
            <span className="sr-only" role="status" aria-live="polite">
                {kopiert ? 'Prompt in die Zwischenablage kopiert' : ''}
            </span>
            <article
                className="markdown"
                // eigener Inhalt aus docs/KI-ANLEITUNG.md, serverseitig ohne HTML-Eingaben gerendert
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </>
    );
}
