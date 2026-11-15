#!/usr/bin/env node
/**
 * Browser-Erweiterung bauen (#153): extension/src → extension/dist (Manifest V3, Chrome, Edge, Firefox) und
 * extension/eduksx-glossary-extension-<Version>.zip zum Hochladen in die Stores, zum Herunterladen am Release (#385)
 * bzw. zur Verteilung über die Geräteverwaltung. Kein Code aus dem Netz (MV3), alles gebündelt.
 *
 * Version = App-Version aus VERSION (#385). Die Stores nehmen nur Zahlen (X.Y.Z), deshalb fällt der Zusatz einer Beta
 * bzw. eines Developer-Builds weg; er steht in «version_name» (Chrome/Edge zeigen ihn an).
 *
 *   node scripts/build-extension.mjs
 */
import { execFileSync } from 'node:child_process';
import {
    cpSync,
    mkdirSync,
    readdirSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'rolldown';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const src = resolve(root, 'extension');
const dist = resolve(src, 'dist');
const manifest = JSON.parse(
    readFileSync(resolve(src, 'manifest.json'), 'utf8'),
);
const appVersion = readFileSync(resolve(root, 'VERSION'), 'utf8').trim();
const numeric = /^(\d+\.\d+\.\d+)/.exec(appVersion)?.[1];
if (!numeric) {
    throw new Error(`VERSION «${appVersion}» hat nicht die Form X.Y.Z`);
}
manifest.version = numeric;
if (appVersion !== numeric) {
    manifest.version_name = appVersion;
} else {
    delete manifest.version_name;
}

rmSync(dist, { recursive: true, force: true });
mkdirSync(dist, { recursive: true });

for (const name of ['background', 'content', 'popup']) {
    const result = await build({
        input: resolve(src, `src/${name}.ts`),
        write: false,
        output: { format: 'iife', minify: true },
    });
    writeFileSync(resolve(dist, `${name}.js`), result.output[0].code);
}
writeFileSync(
    resolve(dist, 'manifest.json'),
    `${JSON.stringify(manifest, null, 4)}\n`,
);
for (const file of ['popup.html', 'popup.css']) {
    cpSync(resolve(src, file), resolve(dist, file));
}
cpSync(resolve(src, 'icons'), resolve(dist, 'icons'), { recursive: true });
cpSync(resolve(root, 'LICENSE'), resolve(dist, 'LICENSE'));

const zip = `eduksx-glossary-extension-${manifest.version}.zip`;
for (const old of readdirSync(src).filter((f) => f.endsWith('.zip'))) {
    rmSync(resolve(src, old), { force: true });
}
execFileSync(
    'python3',
    [
        '-m',
        'zipfile',
        '-c',
        `../${zip}`,
        'manifest.json',
        'background.js',
        'content.js',
        'popup.js',
        'popup.html',
        'popup.css',
        'icons',
        'LICENSE',
    ],
    { cwd: dist },
);
console.log(
    `✓ extension/dist und extension/${zip} (Version ${manifest.version_name ?? manifest.version})`,
);
