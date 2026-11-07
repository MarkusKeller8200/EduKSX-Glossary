#!/usr/bin/env node
/**
 * Lizenzbericht der Pakete (#73, docs/LEGAL.md): direkte und transitive Composer- und npm-Pakete mit Lizenz.
 * Nur Node-Standardbibliothek. Aufruf (nach composer install und npm ci):
 *
 *   composer licenses --no-dev --format=json > composer-licenses.json
 *   npm ls --omit=dev --all --json --long > npm-tree.json
 *   node scripts/license-report.mjs composer-licenses.json npm-tree.json > license-report.md
 *
 * Bedenkliche Lizenzen (starkes Copyleft, nicht kommerziell, ohne Angabe) erscheinen als Warnung im Bericht und als
 * «::warning::» in GitHub Actions. Der Bericht bricht die Prüfung nicht ab; er ist keine rechtliche Freigabe.
 */
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const [composerFile, npmTreeFile] = process.argv.slice(2);
const actions = process.env.GITHUB_ACTIONS === 'true';

/** Lizenzen, die eine Prüfung brauchen (SPDX, Teilstring). */
const REVIEW = [
    /AGPL/i,
    /(^|[^L])GPL/i,
    /LGPL/i,
    /SSPL/i,
    /EUPL/i,
    /\bNC\b|NonCommercial/i,
    /BUSL|Business Source/i,
    /UNLICENSED/i,
    /^unbekannt$/,
];

const readJson = (file) => {
    if (!file || !existsSync(file)) {
        return null;
    }
    try {
        return JSON.parse(readFileSync(file, 'utf8'));
    } catch {
        return null;
    }
};

/** @type {{ source: string, name: string, version: string, license: string }[]} */
const packages = [];

const composer = readJson(composerFile);
for (const [name, info] of Object.entries(composer?.dependencies ?? {})) {
    packages.push({
        source: 'Composer',
        name,
        version: String(info.version ?? ''),
        license: (info.license ?? []).join(' OR ') || 'unbekannt',
    });
}

const licenseOf = (name) =>
    licenseAt(join('node_modules', name, 'package.json'));

const licenseAt = (file) => {
    if (!existsSync(file)) {
        return 'unbekannt';
    }
    const pkg = readJson(file) ?? {};
    if (typeof pkg.license === 'string') {
        return pkg.license;
    }
    if (pkg.license && typeof pkg.license.type === 'string') {
        return pkg.license.type;
    }
    if (Array.isArray(pkg.licenses)) {
        return pkg.licenses.map((l) => l.type ?? l).join(' OR ') || 'unbekannt';
    }

    return 'unbekannt';
};

const seen = new Set();
const walk = (deps) => {
    for (const [name, node] of Object.entries(deps ?? {})) {
        // nicht installierte optionale bzw. Peer-Abhängigkeiten (ohne Version) überspringen
        if (!node.version || node.missing) {
            continue;
        }
        const key = `${name}@${node.version}`;
        if (!seen.has(key)) {
            seen.add(key);
            const license =
                typeof node.license === 'string'
                    ? node.license
                    : node.path
                      ? licenseAt(join(node.path, 'package.json'))
                      : licenseOf(name);
            packages.push({
                source: 'npm',
                name,
                version: String(node.version),
                license,
            });
        }
        walk(node.dependencies);
    }
};
walk(readJson(npmTreeFile)?.dependencies);

packages.sort((a, b) =>
    `${a.source} ${a.name}`.localeCompare(`${b.source} ${b.name}`),
);
/** Bei Wahl-Lizenzen («A OR B») genügt eine unbedenkliche Alternative. */
const needsReview = (license) =>
    license
        .replace(/[()]/g, '')
        .split(/\s+OR\s+/i)
        .every((part) => REVIEW.some((r) => r.test(part.trim())));
const flagged = packages.filter((p) => needsReview(p.license));
const count = {};
for (const p of packages) {
    count[p.license] = (count[p.license] ?? 0) + 1;
}

const version = existsSync('VERSION')
    ? readFileSync('VERSION', 'utf8').trim()
    : '';
const out = [];
out.push(`# Lizenzbericht EduKSX-Glossary ${version}`);
out.push('');
out.push(
    'Automatisch erzeugt (#73). Pakete der Laufzeit: Composer ohne Entwicklungspakete, npm ohne devDependencies, ' +
        'direkt und transitiv. Keine rechtliche Freigabe – Prüfliste in docs/LEGAL.md.',
);
out.push('');
out.push('## Übersicht');
out.push('');
out.push('| Lizenz | Pakete |');
out.push('| --- | --- |');
for (const [license, n] of Object.entries(count).sort((a, b) => b[1] - a[1])) {
    out.push(`| ${license} | ${n} |`);
}
out.push('');
out.push('## Zu prüfen');
out.push('');
if (flagged.length === 0) {
    out.push(
        'Keine Pakete mit Copyleft-, nicht-kommerzieller oder fehlender Lizenzangabe.',
    );
} else {
    for (const p of flagged) {
        out.push(`- ${p.source}: \`${p.name}\` ${p.version} – ${p.license}`);
    }
}
out.push('');
out.push('## Schrift');
out.push('');
out.push(
    '- Inter (rsms/inter), beim Build über Bunny Fonts eingebunden und selbst ausgeliefert: SIL Open Font License 1.1 – ' +
        'Copyright-Hinweis und Lizenztext beilegen (docs/LEGAL.md).',
);
out.push('');
out.push('## Alle Pakete');
out.push('');
out.push('| Quelle | Paket | Version | Lizenz |');
out.push('| --- | --- | --- | --- |');
for (const p of packages) {
    out.push(`| ${p.source} | \`${p.name}\` | ${p.version} | ${p.license} |`);
}
process.stdout.write(out.join('\n') + '\n');

if (actions) {
    for (const p of flagged) {
        process.stderr.write(
            `::warning title=Lizenz prüfen::${p.source} ${p.name} ${p.version}: ${p.license}\n`,
        );
    }
}
process.stderr.write(
    `${packages.length} Pakete, ${flagged.length} zu prüfen.\n`,
);
