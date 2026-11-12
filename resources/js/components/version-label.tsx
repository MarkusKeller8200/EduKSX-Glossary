import type { AppVersion } from '@/types';

/** «0.1.0» bzw. «0.1.0 · Developer Release (+5)» wie im Intranet (docs/KONZEPT-VERSIONIERUNG.md). */
export function versionLabel(v: AppVersion): string {
    return v.dev
        ? `${v.dev.base} · Developer Release (+${v.dev.commits})`
        : v.version;
}

/** Kurzform für das Handy (#233): «0.1.0» bzw. «0.1.0+5». */
export function versionLabelShort(v: AppVersion): string {
    return v.dev ? `${v.dev.base}+${v.dev.commits}` : v.version;
}
