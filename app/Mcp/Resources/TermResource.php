<?php

namespace App\Mcp\Resources;

use App\Glossary\PublicLookup;
use App\Mcp\Tools\Format;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

/** Ein Begriff unter seinem Permalink (DEC-029 M4), Text mit allen veröffentlichten Angaben. */
#[Name('glossary_term')]
#[Title('Begriff')]
#[Description('Ein veröffentlichter Begriff des EduKSX-Glossary unter seinem Permalink /g/{code}.')]
#[MimeType('text/plain')]
class TermResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate(url('/g').'/{code}');
    }

    public function handle(Request $request, PublicLookup $lookup): Response
    {
        $term = $lookup->find((string) $request->get('code', ''));

        return $term === null
            ? Response::error('Begriff nicht gefunden oder nicht veröffentlicht.')
            : Response::text(Format::detail($term));
    }
}
