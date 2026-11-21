/**
 * «Begriff melden» (#480, DEC-042): Dialog für «Fehlt im Glossary» bzw. «Erklärung falsch» in Erweiterung und
 * Einbindung. Übertragen werden nur das Wort, ein freiwilliger Kommentar und die Adresse der Seite ohne Abfrage und
 * Anker – nie Seitentext. Shadow DOM, Inhalte nur über textContent, Escape schliesst.
 */

export type ReportKind = 'fehlt' | 'falsch';

export type ReportPayload = {
    wort: string;
    art: ReportKind;
    code?: string;
    kommentar?: string;
    adresse?: string;
};

export type ReportResult = { ok: boolean; text: string };

export type ReportOptions = {
    word: string;
    kind: ReportKind;
    code?: string | null;
    /** Name, wenn angemeldet erkannt; null = anonym */
    account?: string | null;
    /** anonymes Melden offen (Killswitch) */
    anonymous?: boolean;
    /** Adresse zum Anmelden, wenn anonym gesperrt */
    loginUrl?: string;
    send: (payload: ReportPayload) => Promise<ReportResult>;
};

export const MAX_WORD = 80;

export const MAX_COMMENT = 500;

/** Adresse ohne Abfrageparameter und Anker (nur http/https); sonst leer. */
export function cleanAddress(href: string): string {
    try {
        const u = new URL(href);
        if (u.protocol !== 'https:' && u.protocol !== 'http:') {
            return '';
        }

        return `${u.origin}${u.pathname}`;
    } catch {
        return '';
    }
}

/** Markierten Text zu einem Wort kürzen (Leerraum zusammenfassen, höchstens 80 Zeichen). */
export function cleanWord(text: string): string {
    return text.replace(/\s+/g, ' ').trim().slice(0, MAX_WORD);
}

/** Inhalt der Meldung (für Vorschau und Senden). */
export function payloadOf(
    options: Pick<ReportOptions, 'word' | 'kind' | 'code'>,
    comment: string,
    href: string,
): ReportPayload {
    const payload: ReportPayload = {
        wort: cleanWord(options.word),
        art: options.kind,
    };
    if (options.kind === 'falsch' && options.code) {
        payload.code = options.code;
    }
    const c = comment.trim().slice(0, MAX_COMMENT);
    if (c !== '') {
        payload.kommentar = c;
    }
    const adresse = cleanAddress(href);
    if (adresse !== '') {
        payload.adresse = adresse;
    }

    return payload;
}

const STYLE = `
:host{all:initial}
.back{position:fixed;inset:0;z-index:2147483647;background:rgba(15,23,42,.35);display:flex;align-items:center;justify-content:center;font:14px/1.45 Inter,system-ui,sans-serif}
.dlg{width:min(26rem,calc(100vw - 2rem));background:#fff;color:#1f2933;border-radius:.6rem;padding:1rem 1.1rem;box-shadow:0 10px 40px rgba(15,23,42,.3)}
h2{margin:0 0 .5rem;font-size:16px}.word{font-weight:600;color:#0272ad;word-break:break-word}
label{display:block;margin-top:.6rem;font-size:13px}textarea{box-sizing:border-box;width:100%;min-height:4.5rem;margin-top:.2rem;font:inherit;padding:.35rem;border:1px solid #d5dbe3;border-radius:.35rem}
.note{margin:.6rem 0 0;font-size:12px;color:#5f6b7a}.who{margin:.4rem 0 0;font-size:12px}
.row{display:flex;gap:.5rem;justify-content:flex-end;margin-top:.8rem}
button{font:inherit;padding:.35rem .8rem;border-radius:.35rem;border:1px solid #d5dbe3;background:#fff;color:inherit;cursor:pointer}
button.primary{background:#0272ad;border-color:#0272ad;color:#fff}button:disabled{opacity:.6;cursor:default}
.msg{margin-top:.6rem;font-size:13px}a{color:#0272ad}
@media (prefers-color-scheme:dark){.dlg{background:#1c2128;color:#e6edf3}textarea,button{background:#0d1117;color:#e6edf3;border-color:#30363d}.note{color:#9aa4af}.word,a{color:#58a6ff}}
`;

/** Dialog öffnen; gibt eine Funktion zum Schliessen zurück. */
export function openReportDialog(
    doc: Document,
    options: ReportOptions,
): () => void {
    const host = doc.createElement('div');
    host.setAttribute('data-glossary-ignore', '');
    const shadow = host.attachShadow({ mode: 'open' });
    const style = doc.createElement('style');
    style.textContent = STYLE;
    const back = doc.createElement('div');
    back.className = 'back';
    const dlg = doc.createElement('div');
    dlg.className = 'dlg';
    dlg.setAttribute('role', 'dialog');
    dlg.setAttribute('aria-modal', 'true');
    dlg.setAttribute('aria-labelledby', 'gls-report-title');

    const title = doc.createElement('h2');
    title.id = 'gls-report-title';
    title.textContent =
        options.kind === 'falsch'
            ? 'Erklärung falsch melden'
            : 'An EduKSX-Glossary senden';
    const word = doc.createElement('p');
    word.className = 'word';
    word.textContent = cleanWord(options.word);
    const label = doc.createElement('label');
    label.textContent =
        options.kind === 'falsch'
            ? 'Was ist falsch? (freiwillig)'
            : 'Kommentar (freiwillig, z. B. Bedeutung)';
    const comment = doc.createElement('textarea');
    comment.maxLength = MAX_COMMENT;
    label.append(comment);
    const note = doc.createElement('p');
    note.className = 'note';
    note.textContent =
        'Übertragen werden nur das Wort, der Kommentar und die Adresse dieser Seite ohne Abfrage. Die Redaktion prüft jede Meldung; nichts wird automatisch veröffentlicht.';
    const who = doc.createElement('p');
    who.className = 'who';
    const blocked = !options.account && options.anonymous === false;
    if (options.account) {
        who.textContent = `Angemeldet als ${options.account}`;
    } else if (blocked) {
        who.textContent = 'Zurzeit nur angemeldet möglich. ';
        if (options.loginUrl) {
            const a = doc.createElement('a');
            a.href = options.loginUrl;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = 'Anmelden';
            who.append(a);
        }
    } else {
        who.textContent = 'Ohne Anmeldung (anonym)';
    }
    const msg = doc.createElement('p');
    msg.className = 'msg';
    msg.setAttribute('role', 'status');
    const row = doc.createElement('div');
    row.className = 'row';
    const cancel = doc.createElement('button');
    cancel.type = 'button';
    cancel.textContent = 'Abbrechen';
    const send = doc.createElement('button');
    send.type = 'button';
    send.className = 'primary';
    send.textContent = 'Senden';
    send.disabled = blocked || word.textContent === '';
    row.append(cancel, send);
    dlg.append(title, word, label, note, who, msg, row);
    back.append(dlg);
    shadow.append(style, back);

    const close = (): void => {
        doc.removeEventListener('keydown', onKey, true);
        host.remove();
    };
    const onKey = (e: KeyboardEvent): void => {
        if (e.key === 'Escape') {
            close();
        }
    };
    doc.addEventListener('keydown', onKey, true);
    back.addEventListener('click', (e) => {
        if (e.target === back) {
            close();
        }
    });
    cancel.addEventListener('click', close);
    send.addEventListener('click', () => {
        send.disabled = true;
        msg.textContent = 'Wird gesendet …';
        void options
            .send(payloadOf(options, comment.value, doc.location.href))
            .then((r) => {
                msg.textContent = r.text;
                if (r.ok) {
                    cancel.textContent = 'Schliessen';
                    window.setTimeout(close, 2500);
                } else {
                    send.disabled = false;
                }
            })
            .catch(() => {
                msg.textContent =
                    'Senden nicht möglich – bitte später nochmals.';
                send.disabled = false;
            });
    });
    doc.body.append(host);
    comment.focus();

    return close;
}

/** Senden an das Glossary per fetch (Einbindung: ohne Sitzung; Erweiterung: mit Sitzung). */
export async function sendReport(
    origin: string,
    payload: ReportPayload,
    credentials: RequestCredentials = 'omit',
): Promise<ReportResult> {
    const res = await fetch(
        `${origin.replace(/\/+$/, '')}/erweiterung/kandidaten`,
        {
            method: 'POST',
            credentials,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        },
    );
    const json = (await res.json().catch(() => ({}))) as {
        text?: string;
        detail?: string;
    };
    if (res.ok) {
        return { ok: true, text: json.text ?? 'Danke!' };
    }
    if (res.status === 429) {
        return {
            ok: false,
            text: 'Zu viele Meldungen – bitte später nochmals.',
        };
    }

    return {
        ok: false,
        text: json.detail ?? `Senden nicht möglich (${res.status}).`,
    };
}
