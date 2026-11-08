import { useEffect, useRef, useState } from 'react';

/**
 * ER-Diagramm aus Mermaid-Text (#170). Mermaid wird erst hier nachgeladen (gross) und läuft mit securityLevel
 * «strict»; der Text stammt vom Server (Struktur der eigenen Datenbank).
 */
export default function ErDiagram({ code }: { code: string }) {
    const box = useRef<HTMLDivElement>(null);
    const [fehler, setFehler] = useState<string | null>(null);

    useEffect(() => {
        let aktiv = true;
        void (async () => {
            try {
                const { default: mermaid } = await import('mermaid');
                const dunkel =
                    document.documentElement.classList.contains('dark');
                mermaid.initialize({
                    startOnLoad: false,
                    securityLevel: 'strict',
                    theme: dunkel ? 'dark' : 'neutral',
                    er: { useMaxWidth: false },
                });
                const { svg } = await mermaid.render('datenmodell-er', code);
                if (aktiv && box.current) {
                    box.current.innerHTML = svg;
                }
            } catch (e) {
                if (aktiv) {
                    setFehler(String(e));
                }
            }
        })();

        return () => {
            aktiv = false;
        };
    }, [code]);

    return (
        <div className="mt-3 max-h-[80vh] overflow-auto rounded-lg border border-border bg-background p-3">
            {fehler ? (
                <p className="text-sm text-red-600">
                    Diagramm nicht verfügbar: {fehler}
                </p>
            ) : null}
            <div ref={box} role="img" aria-label="ER-Diagramm des Datenmodells">
                <p className="text-sm text-muted-foreground">
                    Diagramm wird geladen …
                </p>
            </div>
        </div>
    );
}
