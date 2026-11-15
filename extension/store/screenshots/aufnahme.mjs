/**
 * Screenshot 1280×800 für die Stores (#386): Beispielseite mit geöffneter Erklärbox.
 * Vorher beispielseite.html mit /embed/v1/glossary.js und /api/v1/index unter http://127.0.0.1:8765/ ausliefern.
 *
 *   npm i --no-save puppeteer-core && node extension/store/screenshots/aufnahme.mjs <ziel.png> [chrome-pfad]
 */
import puppeteer from 'puppeteer-core';

const out = process.argv[2];
const chrome = process.argv[3] ?? '/usr/bin/google-chrome';
const browser = await puppeteer.launch({
    executablePath: chrome,
    args: ['--no-sandbox', '--no-proxy-server'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 800 });
await page.goto('http://127.0.0.1:8765/beispielseite.html', {
    waitUntil: 'networkidle0',
});
await new Promise((r) => setTimeout(r, 1500));
const term = await page.evaluateHandle(() =>
    [...document.querySelectorAll('#inhalt *')].find(
        (e) => e.textContent === 'SHP' && e.children.length === 0,
    ),
);
await term.hover();
await new Promise((r) => setTimeout(r, 800));
await page.screenshot({ path: out });
await browser.close();
