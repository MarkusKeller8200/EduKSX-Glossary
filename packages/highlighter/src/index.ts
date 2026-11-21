/**
 * Kern «glossary-highlighter» (DEC-026): gemeinsam für die Einbindung auf Webseiten (#155) und die
 * Browser-Erweiterung (#153). Sucht lokal, markiert fein, erklärt per Hover und Tastatur, verlinkt per Permalink.
 */
export { Highlighter, MARK_CLASS } from './highlighter';
export { loadIndex, localStore } from './loader';
export type { IndexStore } from './loader';
export { Matcher } from './matcher';
export {
    cleanAddress,
    cleanWord,
    openReportDialog,
    payloadOf,
    sendReport,
} from './report';
export type {
    ReportKind,
    ReportOptions,
    ReportPayload,
    ReportResult,
} from './report';
export { CODE_PATTERN, permalink, Tooltip } from './tooltip';
export type {
    HighlightOptions,
    IndexEntry,
    IndexResponse,
    Match,
    ScanOptions,
} from './types';
