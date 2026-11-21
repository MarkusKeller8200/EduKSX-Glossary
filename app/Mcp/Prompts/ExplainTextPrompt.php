<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/** Prompt «Text mit Glossar erklären» (DEC-029 M4). */
#[Name('glossary_explain_text')]
#[Title('Text mit Glossar erklären')]
#[Description('Erklärt Abkürzungen und Fachbegriffe eines Texts (z. B. Elternbrief, Weisung) mit dem EduKSX-Glossary – verständlich und mit Quellen.')]
class ExplainTextPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $text = (string) $request->get('text', '');

        return Response::text(<<<PROMPT
            Erkläre die Abkürzungen und Fachbegriffe im folgenden Text für Eltern bzw. Lehrpersonen in einfacher Sprache.
            Rufe dafür zuerst das Werkzeug `glossary_explain_text` mit dem Text auf. Verwende nur Erklärungen aus dem
            EduKSX-Glossary und nenne je Begriff den Permalink. Hat ein Begriff mehrere Bedeutungen, wähle sie nach dem
            Zusammenhang und sage das. Kürzel, die im Glossary fehlen, nenne am Schluss separat, ohne sie zu erfinden.
            Begriffe mit dem Hinweis «KI-unterstützt, noch nicht geprüft» als solche kennzeichnen.
            Quelle: EduKSX-Glossary (glossary.eduksx.ch), CC BY 4.0.

            Text:
            {$text}
            PROMPT);
    }

    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [new Argument('text', 'Text mit Abkürzungen, z. B. ein Elternbrief', true)];
    }
}
