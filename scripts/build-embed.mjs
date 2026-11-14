#!/usr/bin/env node
/**
 * Script zur Einbindung (#155) und Web-Komponenten für Gastgeber (#338) bauen: packages/highlighter/src/embed.ts → public/embed/v1/glossary.js (immer die
 * neueste 1.x) und public/embed/v<Version>/glossary.js (unveränderlich), dazu public/embed/manifest.json mit
 * SRI-Hash. Die Dateien liegen im Repository, damit der Hash über Builds gleich bleibt.
 *
 *   node scripts/build-embed.mjs          bauen
 *   node scripts/build-embed.mjs --check  Fehler, wenn die Dateien nicht dem Quellcode entsprechen
 */
import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'rolldown';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const source = readFileSync(
    resolve(root, 'packages/highlighter/src/embed.ts'),
    'utf8',
);
const version = /EMBED_VERSION = '([0-9.]+)'/.exec(source)?.[1];
if (!version) {
    throw new Error('EMBED_VERSION fehlt');
}

const result = await build({
    input: resolve(root, 'packages/highlighter/src/embed.ts'),
    write: false,
    output: {
        format: 'iife',
        minify: true,
        banner: `/*! EduKSX-Glossary Einbindung v${version} – MIT – https://glossary.eduksx.ch/api/docs */`,
    },
});
const code = result.output[0].code;
const sri = `sha384-${createHash('sha384').update(code).digest('base64')}`;
const manifest = `${JSON.stringify({ version, file: `/embed/v${version}/glossary.js`, latest: '/embed/v1/glossary.js', integrity: sri }, null, 4)}\n`;
// Fremde Webseiten laden das Script mit crossorigin="anonymous" (SRI): CORS-Kopfzeile nötig; Versionen unveränderlich
const headers = (cache) => `# Erzeugt von scripts/build-embed.mjs (#155)
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Cross-Origin-Resource-Policy "cross-origin"
    Header set Cache-Control "${cache}"
</IfModule>
`;
// Web-Komponenten für Gastgeber (#338): packages/highlighter/src/ui.ts → public/ui/v1/glossary.js
const uiSource = readFileSync(
    resolve(root, 'packages/highlighter/src/ui.ts'),
    'utf8',
);
const uiVersion = /UI_VERSION = '([0-9.]+)'/.exec(uiSource)?.[1];
if (!uiVersion) {
    throw new Error('UI_VERSION fehlt');
}
const uiResult = await build({
    input: resolve(root, 'packages/highlighter/src/ui.ts'),
    write: false,
    output: {
        format: 'iife',
        minify: true,
        banner: `/*! EduKSX-Glossary Web-Komponenten v${uiVersion} – MIT – https://glossary.eduksx.ch/entwickler */`,
    },
});
const uiCode = uiResult.output[0].code;

const files = {
    'public/ui/v1/glossary.js': uiCode,
    'public/ui/v1/.htaccess': headers('public, max-age=3600'),
    'public/embed/v1/glossary.js': code,
    'public/embed/v1/.htaccess': headers('public, max-age=3600'),
    [`public/embed/v${version}/glossary.js`]: code,
    [`public/embed/v${version}/.htaccess`]: headers(
        'public, max-age=31536000, immutable',
    ),
    'public/embed/manifest.json': manifest,
};

if (process.argv.includes('--check')) {
    const stale = Object.entries(files).filter(
        ([path, content]) =>
            !existsSync(resolve(root, path)) ||
            readFileSync(resolve(root, path), 'utf8') !== content,
    );
    if (stale.length > 0) {
        console.error(
            `Einbindung veraltet: ${stale.map(([p]) => p).join(', ')} – node scripts/build-embed.mjs`,
        );
        process.exit(1);
    }
    process.exit(0);
}

for (const [path, content] of Object.entries(files)) {
    mkdirSync(dirname(resolve(root, path)), { recursive: true });
    writeFileSync(resolve(root, path), content);
}
console.log(
    `✓ Einbindung v${version} (${(code.length / 1024).toFixed(1)} KB), ${sri}`,
);
