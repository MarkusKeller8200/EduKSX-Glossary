<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Browser-Erweiterung und Einbindung markieren im Glossary selbst nichts (#505: removeChild-Absturz von React). */
final class EigeneSeitenNichtMarkierenTest extends TestCase
{
    public function test_seiten_des_glossary_sind_fuer_die_markierung_gesperrt(): void
    {
        $this->get('/')->assertOk()->assertSee('<body class="font-sans antialiased" data-glossary-ignore>', false);
    }
}
