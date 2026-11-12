<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Support\ConfigCheck;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Prüfung der Konfiguration (#196): Befehl glossary:pruefen und Systemseite, ohne Werte von Geheimnissen. */
class ConfigCheckTest extends TestCase
{
    /** @param  list<array{bereich: string, pruefung: string, status: string, hinweis: string}>  $checks */
    private function status(array $checks, string $pruefung): string
    {
        foreach ($checks as $c) {
            if (str_starts_with($c['pruefung'], $pruefung)) {
                return $c['status'];
            }
        }
        $this->fail("Prüfung «{$pruefung}» fehlt.");
    }

    public function test_betrieb_mit_unsicheren_werten_meldet_fehler(): void
    {
        config(['app.env' => 'production', 'app.debug' => true, 'app.url' => 'http://glossary.example.ch', 'session.secure' => false]);
        $checks = ConfigCheck::run();

        $this->assertSame(ConfigCheck::FEHLER, $this->status($checks, 'Fehleranzeige'));
        $this->assertSame(ConfigCheck::FEHLER, $this->status($checks, 'Adresse'));
        $this->assertSame(ConfigCheck::FEHLER, $this->status($checks, 'Sichere Cookies'));
        $this->assertTrue(ConfigCheck::hasErrors($checks));
        $this->artisan('glossary:pruefen')->assertExitCode(1);
    }

    public function test_fehlender_mailversand_ist_eine_warnung(): void
    {
        config(['app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://glossary.example.ch', 'session.secure' => true, 'mail.default' => 'log']);
        $checks = ConfigCheck::run();

        $this->assertSame(ConfigCheck::WARNUNG, $this->status($checks, 'Versand eingerichtet'));
        $this->assertSame(ConfigCheck::OK, $this->status($checks, 'Adresse'));
        $this->assertSame(ConfigCheck::OK, $this->status($checks, 'Datenbank erreichbar'));
    }

    public function test_halb_eingerichteter_dienst_ist_eine_warnung_ohne_geheimnis(): void
    {
        config(['glossary.turnstile.site_key' => 'site', 'glossary.turnstile.secret' => '', 'services.microsoft.client_secret' => 'sehr-geheim']);
        $checks = ConfigCheck::run();

        $this->assertSame(ConfigCheck::WARNUNG, $this->status($checks, 'Spam-Schutz'));
        $this->assertStringNotContainsString('sehr-geheim', json_encode($checks, JSON_THROW_ON_ERROR));
    }

    public function test_systemseite_zeigt_pruefung(): void
    {
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
        $this->get('/admin/system')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/system')
            ->has('pruefung')
            ->where('pruefung.0.bereich', 'Anwendung'));
    }
}
