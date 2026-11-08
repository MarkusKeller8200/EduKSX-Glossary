#!/usr/bin/env python3
"""Postman-Sammlung (Collection v2.1) aus der OpenAPI-Beschreibung erzeugen (#158).

Aufruf:  python3 scripts/postman.py          → schreibt openapi/glossary-v1.postman.json
         python3 scripts/postman.py --check  → Fehler, wenn die Datei nicht dem aktuellen Stand entspricht

Variablen der Sammlung: baseUrl (Vorgabe Produktion) und token (Servicekonto, leer). Ordner je Tag der
OpenAPI-Beschreibung; Beispielwerte aus den Parametern bzw. dem Beispiel des Anfrage-Inhalts.
"""

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SPEC = ROOT / "openapi" / "glossary-v1.json"
OUT = ROOT / "openapi" / "glossary-v1.postman.json"
METHODS = ["get", "post", "put", "patch", "delete"]


def example(param: dict) -> str:
    if "example" in param:
        return str(param["example"]).lower() if isinstance(param["example"], bool) else str(param["example"])
    schema = param.get("schema", {})
    if "default" in schema:
        value = schema["default"]
        return str(value).lower() if isinstance(value, bool) else str(value)
    if "enum" in schema:
        return str(schema["enum"][0])
    return ""


def request(path: str, method: str, op: dict, spec: dict) -> dict:
    segments = [s for s in path.strip("/").split("/") if s]
    raw_segments = []
    variables = []
    query = []
    headers = [{"key": "Accept", "value": "application/json"}]
    for param in op.get("parameters", []):
        if "$ref" in param:
            continue
        where = param.get("in")
        if where == "path":
            variables.append({"key": param["name"], "value": example(param) or "…", "description": param.get("description", "")})
        elif where == "query":
            query.append({
                "key": param["name"],
                "value": example(param),
                "description": param.get("description", ""),
                "disabled": not param.get("required", False),
            })
        elif where == "header" and param["name"].lower() != "if-none-match":
            headers.append({"key": param["name"], "value": example(param), "disabled": not param.get("required", False)})
    for s in segments:
        raw_segments.append(":" + s[1:-1] if s.startswith("{") and s.endswith("}") else s)

    url = {
        "raw": "{{baseUrl}}/" + "/".join(raw_segments),
        "host": ["{{baseUrl}}"],
        "path": raw_segments,
    }
    if query:
        url["query"] = query
        enabled = [q for q in query if not q["disabled"]]
        if enabled:
            url["raw"] += "?" + "&".join(f"{q['key']}={q['value']}" for q in enabled)
    if variables:
        url["variable"] = variables

    req = {
        "method": method.upper(),
        "header": headers,
        "url": url,
        "description": op.get("description", op.get("summary", "")),
    }
    security = op.get("security", spec.get("security"))
    if security:
        req["auth"] = {"type": "bearer", "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}]}
    else:
        req["auth"] = {"type": "noauth"}

    body = op.get("requestBody", {}).get("content", {}).get("application/json")
    if body is not None:
        headers.append({"key": "Content-Type", "value": "application/json"})
        sample = body.get("example", {})
        req["body"] = {"mode": "raw", "raw": json.dumps(sample, ensure_ascii=False, indent=2), "options": {"raw": {"language": "json"}}}

    return {"name": op.get("summary", f"{method.upper()} {path}"), "request": req, "response": []}


def build() -> dict:
    spec = json.loads(SPEC.read_text(encoding="utf-8"))
    folders: dict[str, list] = {}
    tag_order = [t["name"] for t in spec.get("tags", [])]
    for path, item in spec["paths"].items():
        for method in METHODS:
            op = item.get(method)
            if op is None:
                continue
            tag = (op.get("tags") or ["Allgemein"])[0]
            folders.setdefault(tag, []).append(request(path, method, op, spec))
    names = [t for t in tag_order if t in folders] + [t for t in folders if t not in tag_order]
    descriptions = {t["name"]: t.get("description", "") for t in spec.get("tags", [])}
    servers = spec.get("servers", [])
    return {
        "info": {
            "name": spec["info"]["title"],
            "description": "Aus openapi/glossary-v1.json erzeugt (scripts/postman.py). Variablen: baseUrl, token "
            "(Servicekonto, nur für geschützte Endpunkte). Doku: /api/docs, ausprobieren: /api/swagger.",
            "version": spec["info"]["version"],
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "variable": [
            {"key": "baseUrl", "value": servers[0]["url"] if servers else "http://localhost:8000/api", "type": "string"},
            {"key": "token", "value": "", "type": "string"},
        ],
        "item": [{"name": n, "description": descriptions.get(n, ""), "item": folders[n]} for n in names],
    }


def main() -> int:
    text = json.dumps(build(), ensure_ascii=False, indent=2) + "\n"
    if "--check" in sys.argv:
        if not OUT.exists() or OUT.read_text(encoding="utf-8") != text:
            print("openapi/glossary-v1.postman.json ist veraltet: python3 scripts/postman.py", file=sys.stderr)
            return 1
        return 0
    OUT.write_text(text, encoding="utf-8")
    print(f"✓ {OUT.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
