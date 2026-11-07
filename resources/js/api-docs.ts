/**
 * Interaktive API-Doku (#26) mit Scalar, selbst ausgeliefert über Vite (#44): keine Abrufe bei jsDelivr oder
 * fonts.scalar.com (withDefaultFonts: false).
 */
import { createApiReference } from '@scalar/api-reference';
import '@scalar/api-reference/style.css';

const config = {
    url: '/api/openapi.json',
    withDefaultFonts: false,
    hideDownloadButton: false,
    // Entwickler-Leiste von Scalar (nur auf localhost sichtbar, Teilen über die Scalar Registry) aus
    showDeveloperTools: 'never',
    defaultHttpClient: { targetKey: 'shell', clientKey: 'curl' },
    metaData: { title: 'API – EduKSX-Glossary' },
};

createApiReference(
    '#api-reference',
    config as Parameters<typeof createApiReference>[1],
);
