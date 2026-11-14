<?php

namespace Tests\Unit;

use App\Support\AppVersion;
use Tests\TestCase;

/** Quellcode der laufenden Version in der Fusszeile (AGPL-3.0 §13, #336). */
final class QuellcodeLinkTest extends TestCase
{
    public function test_release_verweist_auf_tag(): void
    {
        config(['glossary.version' => '0.15.0', 'glossary.build' => null, 'glossary.build_describe' => 'v0.15.0', 'glossary.source_url' => 'https://github.com/beispiel/glossary/']);
        $this->assertSame('https://github.com/beispiel/glossary/tree/v0.15.0', AppVersion::sourceUrl());
    }

    public function test_developer_release_verweist_auf_commit(): void
    {
        config(['glossary.version' => '0.15.0', 'glossary.build' => 'main-1a2b3c4', 'glossary.build_describe' => 'v0.14.0-3-g1a2b3c4', 'glossary.source_url' => 'https://github.com/beispiel/glossary']);
        $this->assertSame('https://github.com/beispiel/glossary/tree/1a2b3c4', AppVersion::sourceUrl());
    }

    public function test_link_fuer_alle_sichtbar(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('links.quellcode', AppVersion::sourceUrl()));
    }
}
