/**
 * Swagger-Seite der API (#157) mit «Try it out», selbst ausgeliefert über Vite (keine CDNs, kein Validator-Dienst).
 * Die aktuelle Instanz steht als erster Server, damit Abrufe hier landen. Ein Token über «Authorize» bleibt nur im
 * Speicher dieser Seite (persistAuthorization: false).
 */
import SwaggerUIBundle from 'swagger-ui-dist/swagger-ui-bundle.js';
import 'swagger-ui-dist/swagger-ui.css';

type Spec = { servers?: { url: string; description?: string }[] };

async function start(): Promise<void> {
    const response = await fetch('/api/openapi.json', {
        headers: { Accept: 'application/json' },
    });
    const spec = (await response.json()) as Spec;
    const here = `${window.location.origin}/api`;
    spec.servers = [
        { url: here, description: 'Diese Instanz' },
        ...(spec.servers ?? []).filter((s) => s.url !== here),
    ];

    SwaggerUIBundle({
        spec,
        dom_id: '#swagger-ui',
        deepLinking: true,
        tryItOutEnabled: true,
        persistAuthorization: false,
        displayRequestDuration: true,
        docExpansion: 'list',
        filter: true,
        validatorUrl: null,
        defaultModelsExpandDepth: 0,
    });
}

void start();
