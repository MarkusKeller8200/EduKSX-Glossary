#!/usr/bin/env node
/**
 * Browser-Erweiterung bauen (#153): extension/src → extension/dist (Manifest V3, Chrome, Edge, Firefox) und
 * extension/eduksx-glossary-<Version>.zip zum Hochladen in die Stores bzw. zur Verteilung über die
 * Geräteverwaltung. Kein Code aus dem Netz (MV3), alles gebündelt.
 *
 *   node scripts/build-extension.mjs
 */
import { execFileSync } from 'node:child_process';
import {
    cpSync,
    mkdirSync,
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
for (const file of ['manifest.json', 'popup.html', 'popup.css']) {
    cpSync(resolve(src, file), resolve(dist, file));
}
cpSync(resolve(src, 'icons'), resolve(dist, 'icons'), { recursive: true });
cpSync(resolve(root, 'LICENSE'), resolve(dist, 'LICENSE'));

const zip = `eduksx-glossary-${manifest.version}.zip`;
rmSync(resolve(src, zip), { force: true });
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
    `✓ extension/dist und extension/${zip} (Version ${manifest.version})`,
);
