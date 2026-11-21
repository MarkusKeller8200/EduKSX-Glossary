<?php

namespace App\Mcp\Tools;

use App\Glossary\PublicLookup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('glossary_explain_text')]
#[Title('Abkürzungen in einem Text erklären')]
#[Description('Findet die Abkürzungen (genaue Schreibweise, an Wortgrenzen) und optional die ausgeschriebenen Begriffe des Glossary in einem Text, z. B. einem Elternbrief, mit Erklärung und Permalink. Nennt zusätzlich Kürzel, die im Glossary fehlen. Der Text wird nicht gespeichert und nicht protokolliert (höchstens 20 000 Zeichen).')]
#[IsReadOnly]
#[IsIdempotent]
class ExplainText extends Tool
{
    public function handle(Request $request, PublicLookup $lookup): ResponseFactory
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.PublicLookup::MAX_TEXT],
            'names' => ['nullable', 'boolean'],
        ]);
        $result = $lookup->explain((string) $data['text'], (bool) ($data['names'] ?? false));
        $lines = [];
        foreach ($result['found'] as $hit) {
            foreach ($hit['terms'] as $t) {
                $lines[] = '«'.$hit['text'].'» ('.$hit['count'].'×): '.Format::line($t);
            }
        }
        if ($result['unknown'] !== []) {
            $lines[] = 'Nicht im Glossary: '.implode(', ', array_column($result['unknown'], 'text'));
        }

        return Response::make(Response::text($lines === [] ? 'Keine Begriffe des Glossary gefunden.' : implode("\n", $lines)))
            ->withStructuredContent($result + ['license' => $lookup->license()]);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()->description('Text mit Abkürzungen (höchstens 20 000 Zeichen)')->required(),
            'names' => $schema->boolean()->description('Auch ausgeschriebene Bezeichnungen erkennen (Vorgabe nein)'),
        ];
    }
}
