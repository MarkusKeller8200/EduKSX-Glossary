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

#[Name('glossary_resolve_abbreviation')]
#[Title('Abkürzung auflösen')]
#[Description('«Was heisst …?»: alle Bedeutungen einer Abkürzung, Bezeichnung oder Schreibweise (auch Aliase), ohne Gross-/Kleinschreibung. Mehrere Treffer = mehrdeutig; dann nach dem Zusammenhang fragen.')]
#[IsReadOnly]
#[IsIdempotent]
class ResolveAbbreviation extends Tool
{
    public function handle(Request $request, PublicLookup $lookup): ResponseFactory
    {
        $data = $request->validate(['abbreviation' => ['required', 'string', 'max:120']]);
        $terms = array_map([PublicLookup::class, 'compact'], $lookup->resolve((string) $data['abbreviation']));
        $text = $terms === []
            ? '«'.$data['abbreviation'].'» ist im Glossary nicht erfasst.'
            : implode("\n", array_map(fn (array $t): string => Format::line($t), $terms));

        return Response::make(Response::text($text))->withStructuredContent(['terms' => $terms, 'ambiguous' => count($terms) > 1, 'license' => $lookup->license()]);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'abbreviation' => $schema->string()->description('Abkürzung oder Begriff, z. B. «KLP»')->required(),
        ];
    }
}
