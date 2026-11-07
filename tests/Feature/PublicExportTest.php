<?php

namespace Tests\Feature;

use App\Glossary\ExcelFormat;
use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\Term;
use Tests\TestCase;

/** Export getrennt vom Import (#111): Benutzer exportieren veröffentlichte Daten, die Redaktion den Arbeitsstand. */
class PublicExportTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
    }

    /** Veröffentlichter Begriff mit Notiz und späterem Entwurf, dazu ein nie veröffentlichter Begriff. */
    private function daten(): Term
    {
        $term = $this->glossary->publish($this->glossary->save(null, [
            'official_abbreviation' => 'SL', 'official_name' => 'Schulleitung',
            'short_description' => 'Leitet die Schule', 'note' => 'Geheime Notiz',
        ], null, true), null);
        $this->glossary->save($term, ['short_description' => 'Entwurfstext'], null, true);
        $this->glossary->save(null, ['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson Entwurf'], null, true);

        return $term;
    }

    public function test_benutzer_exportiert_nur_veroeffentlichte_daten(): void
    {
        $this->daten();
        $this->actingAs($this->userWithRoles([Role::USER], 'b@example.org', twoFactor: false));

        $this->get('/export')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $csv = (string) $this->get('/export?format=csv')->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();

        $this->assertStringContainsString('Leitet die Schule', $csv);
        $this->assertStringNotContainsString('Entwurfstext', $csv);
        $this->assertStringNotContainsString('Lehrperson Entwurf', $csv);
        $this->assertStringNotContainsString('Geheime Notiz', $csv);
        $this->assertStringNotContainsString('"Notiz"', $csv);
        $this->assertNotContains('Notiz', ExcelFormat::PUBLIC_COLUMNS);
    }

    public function test_benutzer_darf_nicht_importieren(): void
    {
        $this->actingAs($this->userWithRoles([Role::USER], 'b@example.org', twoFactor: false));

        $this->get('/admin/import')->assertForbidden();
        $this->get('/admin/export')->assertForbidden();
    }

    public function test_gast_und_abgemeldet_ohne_export(): void
    {
        $this->get('/export')->assertRedirect('/anmelden');
        $this->actingAs($this->userWithRoles([Role::GUEST], 'g@example.org', twoFactor: false));
        $this->get('/export')->assertForbidden();
    }

    public function test_redaktion_exportiert_den_arbeitsstand(): void
    {
        $this->daten();
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'm@example.org'));

        $csv = (string) $this->get('/admin/export?format=csv')->assertOk()->getContent();
        $this->assertStringContainsString('Entwurfstext', $csv);
        $this->assertStringContainsString('Lehrperson Entwurf', $csv);
        $this->assertStringContainsString('Geheime Notiz', $csv);

        // Moderator hat kein Import-Recht
        $this->get('/admin/import')->assertForbidden();
    }
}
