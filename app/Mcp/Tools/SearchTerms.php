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

#[Name('glossary_search_terms')]
#[Title('Begriffe suchen')]
#[Description('Sucht veröffentlichte Begriffe im EduKSX-Glossary nach Text (Abkürzung, Bezeichnung, Aliase, Kurzbeschreibung), optional gefiltert nach Kategorie bzw. Herkunft (Schlüssel oder Label aus glossary_list_categories). Liefert eine Kurzform je Begriff mit Code und Permalink.')]
#[IsReadOnly]
#[IsIdempotent]
class SearchTerms extends Tool
{
    public function handle(Request $request, PublicLookup $lookup): ResponseFactory
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $terms = array_map([PublicLookup::class, 'compact'], $lookup->search(
            (string) ($data['query'] ?? ''), (string) ($data['category'] ?? ''), (string) ($data['source'] ?? ''), (int) ($data['limit'] ?? 10),
        ));
        $text = $terms === [] ? 'Kein Begriff gefunden.' : implode("\n", array_map(fn (array $t): string => Format::line($t), $terms));

        return Response::make(Response::text($text))->withStructuredContent(['terms' => $terms, 'license' => $lookup->license()]);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Suchtext, z. B. «Schulleitung» oder «SL»'),
            'category' => $schema->string()->description('Kategorie (Schlüssel oder Label), optional'),
            'source' => $schema->string()->description('Herkunft (Schlüssel oder Label), optional'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstens so viele Treffer (Vorgabe 10)'),
        ];
    }
}
