import { useEffect, useRef, useState } from 'react';
import type { PointerEvent, RefObject } from 'react';
import { cn } from '@/lib/utils';

/**
 * Breite Scroll-Leiste A–Z für das Handy (#254): rechts am Rand, beim Scrollen mit Anzeige des aktuellen Buchstabens.
 * Antippen oder Ziehen springt zum ersten Eintrag mit diesem Buchstaben. Einträge tragen `data-initial`.
 * `oben` ist der fixierte Bereich (Kopfzeile, Suchleiste), unter dem ein Eintrag als sichtbar gilt.
 */
export default function AlphabetLeiste({
    buchstaben,
    vorhanden,
    oben,
}: {
    buchstaben: string[];
    vorhanden: Set<string>;
    oben: RefObject<HTMLElement | null>;
}) {
    const [aktuell, setAktuell] = useState<string | null>(null);
    const [sichtbar, setSichtbar] = useState(false);
    const [ziehen, setZiehen] = useState(false);
    const leiste = useRef<HTMLDivElement>(null);
    const timer = useRef<number | undefined>(undefined);

    const grenze = () => oben.current?.getBoundingClientRect().bottom ?? 0;
    // Oberkante der Leiste unter dem fixierten Bereich; bei wenig Höhe (Handy quer) nur jeder n-te Buchstabe (#253)
    const [oberkante, setOberkante] = useState(0);
    const [schritt, setSchritt] = useState(1);
    const messen = () => {
        const top = Math.max(grenze(), 0) + 8;
        setOberkante(top);
        const hoehe = window.innerHeight - top - 64;
        setSchritt(
            Math.max(
                1,
                Math.ceil((buchstaben.length * 14) / Math.max(hoehe, 1)),
            ),
        );
    };

    const zeigen = () => {
        setSichtbar(true);
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => setSichtbar(false), 900);
    };

    useEffect(() => {
        const beimScrollen = () => {
            messen();
            const g = grenze();
            const eintraege =
                document.querySelectorAll<HTMLElement>('[data-initial]');
            for (const e of eintraege) {
                if (e.getBoundingClientRect().bottom > g + 8) {
                    setAktuell(e.dataset.initial ?? null);
                    break;
                }
            }
            zeigen();
        };
        messen();
        window.addEventListener('scroll', beimScrollen, { passive: true });
        window.addEventListener('resize', messen);

        return () => {
            window.removeEventListener('scroll', beimScrollen);
            window.removeEventListener('resize', messen);
            window.clearTimeout(timer.current);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const springen = (y: number) => {
        const r = leiste.current?.getBoundingClientRect();
        if (!r) {
            return;
        }
        const i = Math.min(
            buchstaben.length - 1,
            Math.max(
                0,
                Math.floor(((y - r.top) / r.height) * buchstaben.length),
            ),
        );
        // erster vorhandener Buchstabe ab der Position
        const ziel =
            buchstaben.slice(i).find((b) => vorhanden.has(b)) ??
            buchstaben
                .slice(0, i)
                .reverse()
                .find((b) => vorhanden.has(b));
        if (!ziel) {
            return;
        }
        const e = document.querySelector<HTMLElement>(
            `[data-initial="${ziel}"]`,
        );
        if (e) {
            window.scrollTo({
                top:
                    e.getBoundingClientRect().top +
                    window.scrollY -
                    grenze() -
                    8,
            });
        }
        setAktuell(ziel);
        zeigen();
    };

    const runter = (e: PointerEvent<HTMLDivElement>) => {
        e.currentTarget.setPointerCapture(e.pointerId);
        setZiehen(true);
        springen(e.clientY);
    };

    return (
        <>
            <div
                ref={leiste}
                role="navigation"
                aria-label="Springen zu Buchstabe"
                className={cn(
                    'fixed right-0 bottom-14 z-10 flex w-7 touch-none flex-col items-center justify-between rounded-l-lg py-1 text-[0.625rem] leading-none font-semibold select-none',
                    ziehen || sichtbar ? 'bg-muted/90' : 'bg-muted/50',
                )}
                style={{ top: oberkante }}
                onPointerDown={runter}
                onPointerMove={(e) => ziehen && springen(e.clientY)}
                onPointerUp={() => setZiehen(false)}
                onPointerCancel={() => setZiehen(false)}
            >
                {buchstaben.map((b, i) => (
                    <span
                        key={b}
                        hidden={
                            i % schritt !== 0 && i !== buchstaben.length - 1
                        }
                        className={cn(
                            b === aktuell
                                ? 'text-primary'
                                : vorhanden.has(b)
                                  ? 'text-muted-foreground'
                                  : 'text-muted-foreground/30',
                        )}
                    >
                        {b}
                    </span>
                ))}
            </div>
            {(sichtbar || ziehen) && aktuell ? (
                <div
                    aria-hidden
                    className="pointer-events-none fixed top-1/2 right-10 z-30 flex size-14 -translate-y-1/2 items-center justify-center rounded-xl bg-primary text-2xl font-semibold text-primary-foreground shadow-lg"
                >
                    {aktuell}
                </div>
            ) : null}
        </>
    );
}
