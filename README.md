# EduKSX-Glossary

Glossar mit Begriffen und Definitionen, veröffentlicht über GitHub Pages:
**https://markuskeller8200.github.io/EduKSX-Glossary/**

## Aufbau

| Pfad | Inhalt |
|---|---|
| `site/begriffe.json` | die Begriffe (`begriff`, `kategorie`, `definition`) |
| `site/index.html` | Website mit Suche; wird bei jedem Push auf `main` veröffentlicht (`.github/workflows/pages.yml`) |
| `VERSION` | aktuelle Version, erscheint im Fuss der Website |
| `CHANGELOG.md` | Änderungen pro Version |
| `docs/` | Ablauf auf GitHub, Unterstützen |

Lokal ansehen:

```bash
python3 -m http.server -d site 8000   # dann http://localhost:8000 öffnen
```

## Mitmachen

Begriffe vorschlagen, Fehler melden oder Verbesserungen anregen über die
[Issue-Formulare](https://github.com/MarkusKeller8200/EduKSX-Glossary/issues/new/choose).
Wie Meldungen freigegeben, umgesetzt und veröffentlicht werden, steht in [CONTRIBUTING.md](CONTRIBUTING.md) und
[docs/GITHUB-ABLAUF.md](docs/GITHUB-ABLAUF.md).

## Unterstützen

Ein ⭐, Rückmeldungen über die Issues oder ein Kaffee auf [Ko-fi](https://ko-fi.com/markussxch) helfen weiter –
mehr in [docs/UNTERSTUETZEN.md](docs/UNTERSTUETZEN.md).

---

Eingerichtet mit [my-github-blueprint](https://github.com/MarkusKeller8200/my-github-blueprint)
(Konfiguration: `.github/blueprint.toml`).
