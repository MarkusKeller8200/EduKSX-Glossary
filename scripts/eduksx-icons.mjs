// EduKSX-Icons aus @eduksx/ui (Repo EduKSX-UI) übernehmen – dieses Repo hält keine eigenen Kopien (22.11.2026).
// Erzeugt die Dateien, die als feste Pfade gebraucht werden (Favicon, Manifest); sie sind in .gitignore.
// Aufruf: node scripts/eduksx-icons.mjs  (läuft vor «build» und «dev»)
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const quelle = resolve(
    root,
    'node_modules/@eduksx/ui/icons/svg/eduksx-glossary-tile-dark.svg',
);
const ziele = ['public/icon.svg', 'public/favicon.svg']; // site/produkt.html verweist direkt auf EduKSX-UI

for (const ziel of ziele) {
    const pfad = resolve(root, ziel);
    mkdirSync(dirname(pfad), { recursive: true });
    copyFileSync(quelle, pfad);
}
console.log(`EduKSX-Icon aus @eduksx/ui → ${ziele.join(', ')}`);
