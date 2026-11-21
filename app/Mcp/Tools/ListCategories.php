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

#[Name('glossary_list_categories')]
#[Title('Kategorien und Herkunft')]
#[Description('Listet die Kategorien (Themen) und die Herkunft (herausgebende Stellen) des Glossary mit Schlüssel, Label, Bezeichnung, Beschreibung und Adresse – für Filter in glossary_search_terms.')]
#[IsReadOnly]
#[IsIdempotent]
class ListCategories extends Tool
{
    public function handle(Request $request, PublicLookup $lookup): ResponseFactory
    {
        $lists = $lookup->lists();
        $line = fn (array $i): string => '- '.$i['label'].($i['name'] !== $i['label'] ? ' – '.$i['name'] : '').' (`'.$i['key'].'`)';
        $text = "Kategorien:\n".implode("\n", array_map($line, $lists['categories']))."\n\nHerkunft:\n".implode("\n", array_map($line, $lists['sources']));

        return Response::make(Response::text($text))->withStructuredContent($lists);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
