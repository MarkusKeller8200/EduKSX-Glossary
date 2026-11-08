import QRCode from 'qrcode';
import { useMemo } from 'react';

/**
 * QR-Code als SVG (#70), z. B. für die otpauth-Adresse beim Einrichten des zweiten Faktors. Erzeugt im Browser mit
 * dem Paket `qrcode` (MIT); nur die Matrix wird übernommen, gezeichnet wird ein eigenes SVG (keine HTML-Einfügung).
 */
export default function QrCode({
    value,
    label,
    size = 192,
}: {
    value: string;
    label: string;
    size?: number;
}) {
    const path = useMemo(() => {
        const { size: n, data } = QRCode.create(value, {
            errorCorrectionLevel: 'M',
        }).modules;
        let d = '';
        for (let y = 0; y < n; y++) {
            for (let x = 0; x < n; x++) {
                if (data[y * n + x]) {
                    d += `M${x + 4} ${y + 4}h1v1h-1z`;
                }
            }
        }

        return { d, n: n + 8 };
    }, [value]);

    return (
        <svg
            role="img"
            aria-label={label}
            width={size}
            height={size}
            viewBox={`0 0 ${path.n} ${path.n}`}
            shapeRendering="crispEdges"
            className="rounded-md border border-border bg-white"
        >
            <rect width={path.n} height={path.n} fill="#fff" />
            <path d={path.d} fill="#000" />
        </svg>
    );
}
