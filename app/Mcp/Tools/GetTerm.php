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

#[Name('glossary_get_term')]
#[Title('Begriff abrufen')]
#[Description('Alle veröffentlichten Angaben eines Begriffs (Felder wie API v1): nach Code des Permalinks (6 Zeichen, auch als Adresse https://glossary.eduksx.ch/g/{code}), Slug oder ID.')]
#[IsReadOnly]
#[IsIdempotent]
class GetTerm extends Tool
{
    public function handle(Request $request, PublicLookup $lookup): Response|ResponseFactory
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:200']]);
        $term = $lookup->find((string) $data['reference']);
        if ($term === null) {
            return Response::error('Begriff «'.$data['reference'].'» ist nicht veröffentlicht oder existiert nicht.');
        }

        return Response::make(Response::text(Format::detail($term)))->withStructuredContent(['term' => $term, 'license' => $lookup->license()]);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->description('Code (z. B. 7KQ2MX), Permalink, Slug oder ID')->required(),
        ];
    }
}
