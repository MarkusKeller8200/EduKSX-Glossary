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
    browser_specific_settings: {
        gecko: {
            id: string;
            strict_min_version: string;
            data_collection_permissions?: { required: string[] };
        };
    };
};

describe('Manifest der Browser-Erweiterung (#153)', () => {
    it('ist Manifest V3 mit minimalen Berechtigungen', () => {
        expect(manifest.manifest_version).toBe(3);
        // contextMenus (#480): «An EduKSX-Glossary senden» für markierten Text
        expect(manifest.permissions.sort()).toEqual([
            'activeTab',
            'contextMenus',
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

    it('erklärt für Firefox, dass keine Daten erhoben werden (#386)', () => {
        const gecko = manifest.browser_specific_settings.gecko;
        expect(gecko.id).toBe('glossary@eduksx.ch');
        // Pflicht für neue Add-ons auf addons.mozilla.org, wirkt ab Firefox 140
        expect(gecko.data_collection_permissions).toEqual({
            required: ['none'],
        });
        expect(gecko.strict_min_version).toBe('140.0');
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
