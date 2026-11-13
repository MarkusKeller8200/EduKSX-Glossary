import type { AppVersion } from '@/types';

/**
 * Release «0.13.0», Beta «0.13.0-beta.1», Developer «0.13.0-dev.5» (geplante Version, Commits seit dem letzten
 * Release; docs/KONZEPT-VERSIONIERUNG.md, #308).
 */
export function versionLabel(v: AppVersion): string {
    return v.dev ? v.dev.label : v.version;
}

/** Kurzform für das Handy (#233): dieselbe Bezeichnung, sie ist bereits kurz. */
export function versionLabelShort(v: AppVersion): string {
    return versionLabel(v);
}
