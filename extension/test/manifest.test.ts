import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { originOf } from '../src/settings';

const manifest = JSON.parse(
    readFileSync(resolve(import.meta.dirname, '../manifest.json'), 'utf8'),
) as {
    manifest_version: number;
    permissions: string[];
    host_permissions: string[];
    optional_host_permissions: string[];
    content_scripts?: unknown;
};

describe('Manifest der Browser-Erweiterung (#153)', () => {
    it('ist Manifest V3 mit minimalen Berechtigungen', () => {
        expect(manifest.manifest_version).toBe(3);
        expect(manifest.permissions.sort()).toEqual([
            'activeTab',
            'scripting',
            'storage',
        ]);
        // dauernd nur das Glossary; Websites nur auf Wunsch einzeln
        expect(manifest.host_permissions).toEqual([
            'https://glossary.eduksx.ch/*',
        ]);
        expect(manifest.permissions).not.toContain('<all_urls>');
        expect(manifest.content_scripts).toBeUndefined();
    });

    it('nimmt nur http(s)-Ursprünge', () => {
        expect(originOf('https://www.schule.ch/seite?x=1')).toBe(
            'https://www.schule.ch',
        );
        expect(originOf('chrome://extensions')).toBeNull();
        expect(originOf('file:///C:/x.html')).toBeNull();
        expect(originOf(undefined)).toBeNull();
    });
});
