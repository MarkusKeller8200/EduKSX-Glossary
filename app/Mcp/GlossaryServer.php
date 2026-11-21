<?php

namespace App\Mcp;

use App\Mcp\Prompts\ExplainTextPrompt;
use App\Mcp\Resources\TermResource;
use App\Mcp\Tools\ExplainText;
use App\Mcp\Tools\GetTerm;
use App\Mcp\Tools\ListCategories;
use App\Mcp\Tools\ResolveAbbreviation;
use App\Mcp\Tools\SearchTerms;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * MCP-Server des Glossary (DEC-029, #261), Phase 1: öffentlich lesen, ohne Anmeldung, mit Begrenzer. Werkzeuge,
 * Ressource und Prompt nutzen nur PublicLookup bzw. ApiPresenter (DEC-011, M5) – dieselben veröffentlichten Daten wie
 * die API v1. Eingaben der Assistenten werden nicht protokolliert (M9).
 */
#[Name('EduKSX-Glossary')]
#[Instructions(<<<'MARKDOWN'
    Geprüfte Begriffe und Abkürzungen für Schweizer Volksschulen, Schulgemeinden und Gemeinden (Kanton Zürich zuerst).
    Nutze `glossary_resolve_abbreviation` für «Was heisst …?», `glossary_explain_text` für Abkürzungen in einem Text
    (z. B. Elternbrief), `glossary_search_terms` zum Suchen und `glossary_get_term` für alle Angaben eines Begriffs.
    Nenne den Permalink als Quelle. Daten: EduKSX-Glossary, CC BY 4.0. Begriffe mit `research_basis` = `ai_assisted`
    sind noch nicht von einem Menschen geprüft – weise darauf hin.
    MARKDOWN)]
class GlossaryServer extends Server
{
    protected string $version = '1.0.0';

    protected array $tools = [
        SearchTerms::class,
        GetTerm::class,
        ResolveAbbreviation::class,
        ExplainText::class,
        ListCategories::class,
    ];

    protected array $resources = [
        TermResource::class,
    ];

    protected array $prompts = [
        ExplainTextPrompt::class,
    ];
}
