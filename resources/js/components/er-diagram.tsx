import { Maximize, Minimize, Minus, Plus, Scan } from 'lucide-react';
import {
    type PointerEvent as ReactPointerEvent,
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';
import { Button } from '@/components/ui/button';

type Ansicht = { scale: number; x: number; y: number };

const MIN = 0.1;
const MAX = 4;
const SCHRITT = 1.25;

const begrenzt = (scale: number) => Math.min(MAX, Math.max(MIN, scale));

/**
 * ER-Diagramm aus Mermaid-Text (#170). Mermaid wird erst hier nachgeladen (gross) und läuft mit securityLevel
 * «strict»; der Text stammt vom Server (Struktur der eigenen Datenbank).
 *
 * Bedienung (#371): Einpassen und zentrieren, vergrössern, verkleinern, verschieben mit Maus bzw. Finger, Zoom mit
 * Ctrl/⌘ + Mausrad bzw. Zwei-Finger-Geste (im Vollbild auch mit dem Mausrad allein), Vollbild (Esc beendet es).
 */
export default function ErDiagram({ code }: { code: string }) {
    const rahmen = useRef<HTMLDivElement>(null);
    const flaeche = useRef<HTMLDivElement>(null);
    const box = useRef<HTMLDivElement>(null);
    const groesse = useRef<{ w: number; h: number } | null>(null);
    const zeiger = useRef(new Map<number, { x: number; y: number }>());
    const geste = useRef<{
        abstand: number;
        scale: number;
        x: number;
        y: number;
        mx: number;
        my: number;
    } | null>(null);
    const [fehler, setFehler] = useState<string | null>(null);
    const [geladen, setGeladen] = useState(false);
    const [ansicht, setAnsicht] = useState<Ansicht>({ scale: 1, x: 0, y: 0 });
    const [vollbild, setVollbild] = useState(false);

    /** Diagramm einpassen und in der Fläche zentrieren (nie grösser als 100 %). */
    const einpassen = useCallback(() => {
        const f = flaeche.current;
        const g = groesse.current;
        if (!f || !g || g.w === 0 || g.h === 0) {
            return;
        }
        const scale = begrenzt(
            Math.min(
                1,
                (f.clientWidth - 24) / g.w,
                (f.clientHeight - 24) / g.h,
            ),
        );
        setAnsicht({
            scale,
            x: (f.clientWidth - g.w * scale) / 2,
            y: (f.clientHeight - g.h * scale) / 2,
        });
    }, []);

    /** Zoomen um einen Punkt der Fläche (Mitte, Mauszeiger bzw. Mitte der Finger). */
    const zoomUm = useCallback((faktor: number, px?: number, py?: number) => {
        const f = flaeche.current;
        setAnsicht((a) => {
            const scale = begrenzt(a.scale * faktor);
            const mx = px ?? (f ? f.clientWidth / 2 : 0);
            const my = py ?? (f ? f.clientHeight / 2 : 0);
            const k = scale / a.scale;

            return { scale, x: mx - (mx - a.x) * k, y: my - (my - a.y) * k };
        });
    }, []);

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
                    const el = box.current.querySelector('svg');
                    if (el) {
                        const vb = el.viewBox.baseVal;
                        const rect = el.getBoundingClientRect();
                        const w = vb?.width || rect.width;
                        const h = vb?.height || rect.height;
                        el.setAttribute('width', String(w));
                        el.setAttribute('height', String(h));
                        el.style.maxWidth = 'none';
                        groesse.current = { w, h };
                    }
                    setGeladen(true);
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

    // nach dem Laden und beim Wechsel ins bzw. aus dem Vollbild einpassen
    useEffect(() => {
        if (geladen) {
            einpassen();
        }
    }, [geladen, vollbild, einpassen]);

    useEffect(() => {
        const wechsel = () =>
            setVollbild(document.fullscreenElement === rahmen.current);
        document.addEventListener('fullscreenchange', wechsel);

        return () => document.removeEventListener('fullscreenchange', wechsel);
    }, []);

    // Mausrad: nicht passiv, damit Ctrl/⌘ + Rad bzw. die Zwei-Finger-Geste nicht die Seite zoomt
    useEffect(() => {
        const f = flaeche.current;
        if (!f) {
            return;
        }
        const rad = (e: WheelEvent) => {
            if (!e.ctrlKey && !e.metaKey && !vollbild) {
                return;
            }
            e.preventDefault();
            const r = f.getBoundingClientRect();
            zoomUm(
                Math.exp(-e.deltaY * 0.0015),
                e.clientX - r.left,
                e.clientY - r.top,
            );
        };
        f.addEventListener('wheel', rad, { passive: false });

        return () => f.removeEventListener('wheel', rad);
    }, [vollbild, zoomUm]);

    const unten = (e: ReactPointerEvent<HTMLDivElement>) => {
        e.currentTarget.setPointerCapture(e.pointerId);
        zeiger.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (zeiger.current.size === 2) {
            const [a, b] = [...zeiger.current.values()];
            const r = e.currentTarget.getBoundingClientRect();
            geste.current = {
                abstand: Math.hypot(a.x - b.x, a.y - b.y),
                ...ansicht,
                mx: (a.x + b.x) / 2 - r.left,
                my: (a.y + b.y) / 2 - r.top,
            };
        }
    };

    const bewegen = (e: ReactPointerEvent<HTMLDivElement>) => {
        const vorher = zeiger.current.get(e.pointerId);
        if (!vorher) {
            return;
        }
        zeiger.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (zeiger.current.size === 2 && geste.current) {
            const [a, b] = [...zeiger.current.values()];
            const g = geste.current;
            const scale = begrenzt(
                (g.scale * Math.hypot(a.x - b.x, a.y - b.y)) / g.abstand,
            );
            const k = scale / g.scale;
            setAnsicht({
                scale,
                x: g.mx - (g.mx - g.x) * k,
                y: g.my - (g.my - g.y) * k,
            });
        } else if (zeiger.current.size === 1) {
            setAnsicht((s) => ({
                ...s,
                x: s.x + e.clientX - vorher.x,
                y: s.y + e.clientY - vorher.y,
            }));
        }
    };

    const los = (e: ReactPointerEvent<HTMLDivElement>) => {
        zeiger.current.delete(e.pointerId);
        if (zeiger.current.size < 2) {
            geste.current = null;
        }
    };

    const vollbildUmschalten = () => {
        if (document.fullscreenElement) {
            void document.exitFullscreen();
        } else {
            void rahmen.current?.requestFullscreen?.();
        }
    };

    return (
        <div
            ref={rahmen}
            className={
                vollbild
                    ? 'flex h-screen flex-col bg-background p-3'
                    : 'mt-3 flex flex-col rounded-lg border border-border bg-background'
            }
        >
            <div
                role="toolbar"
                aria-label="Diagramm bedienen"
                className="flex flex-wrap items-center gap-1 border-b border-border p-2"
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={einpassen}
                    disabled={!geladen}
                >
                    <Scan aria-hidden="true" />
                    Einpassen
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Vergrössern"
                    title="Vergrössern"
                    onClick={() => zoomUm(SCHRITT)}
                    disabled={!geladen || ansicht.scale >= MAX}
                >
                    <Plus aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Verkleinern"
                    title="Verkleinern"
                    onClick={() => zoomUm(1 / SCHRITT)}
                    disabled={!geladen || ansicht.scale <= MIN}
                >
                    <Minus aria-hidden="true" />
                </Button>
                <span
                    className="min-w-14 text-center text-sm text-muted-foreground tabular-nums"
                    aria-live="polite"
                >
                    {Math.round(ansicht.scale * 100)} %
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="ml-auto"
                    onClick={vollbildUmschalten}
                    disabled={!geladen}
                >
                    {vollbild ? (
                        <Minimize aria-hidden="true" />
                    ) : (
                        <Maximize aria-hidden="true" />
                    )}
                    {vollbild ? 'Vollbild beenden' : 'Vollbild'}
                </Button>
            </div>
            <div
                ref={flaeche}
                className={
                    'relative touch-none overflow-hidden select-none ' +
                    (vollbild ? 'flex-1' : 'h-[70vh]') +
                    (geladen ? ' cursor-grab active:cursor-grabbing' : '')
                }
                onPointerDown={unten}
                onPointerMove={bewegen}
                onPointerUp={los}
                onPointerCancel={los}
                onDoubleClick={einpassen}
            >
                {fehler ? (
                    <p className="p-3 text-sm text-red-600">
                        Diagramm nicht verfügbar: {fehler}
                    </p>
                ) : null}
                <div
                    className="absolute top-0 left-0 origin-top-left"
                    style={{
                        transform: `translate(${ansicht.x}px, ${ansicht.y}px) scale(${ansicht.scale})`,
                    }}
                >
                    <div
                        ref={box}
                        role="img"
                        aria-label="ER-Diagramm des Datenmodells"
                    >
                        <p className="p-3 text-sm text-muted-foreground">
                            Diagramm wird geladen …
                        </p>
                    </div>
                </div>
            </div>
            <p className="px-3 py-2 text-xs text-muted-foreground">
                Verschieben mit gedrückter Maustaste bzw. dem Finger; zoomen mit
                Ctrl/⌘ + Mausrad oder zwei Fingern, im Vollbild auch mit dem
                Mausrad; Doppelklick passt ein.
            </p>
        </div>
    );
}
