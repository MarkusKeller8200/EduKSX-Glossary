<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\TermCandidate;
use App\Support\Settings;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Begriff melden aus Browser-Erweiterung und Einbindung (#480, DEC-042). */
final class KandidatenTest extends TestCase
{
    private const EXT = 'chrome-extension://abcdefghijklmnopabcdefghijklmnop';

    private function melden(array $data, string $origin = self::EXT): TestResponse
    {
        return $this->postJson('/erweiterung/kandidaten', $data, ['Origin' => $origin]);
    }

    public function test_anonym_aus_der_erweiterung_nur_wort_kommentar_und_adresse_ohne_abfrage(): void
    {
        $this->melden(['wort' => '  SSA  ', 'kommentar' => 'Schulsozialarbeit?', 'adresse' => 'https://www.schule.ch/eltern/brief?id=123#teil'])
            ->assertStatus(202)->assertJsonPath('ok', true)
            ->assertHeader('Access-Control-Allow-Origin', self::EXT)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
        $c = TermCandidate::query()->sole();
        $this->assertSame(['SSA', 'missing', 'extension', 'https://www.schule.ch/eltern/brief', ['Schulsozialarbeit?'], null],
            [$c->word, $c->kind, $c->channel, $c->first_url, $c->comments, $c->reported_by_id]);

        // Duplikat zählt nur hoch
        $this->melden(['wort' => 'ssa'])->assertStatus(202);
        $this->assertSame(2, TermCandidate::query()->sole()->count);
    }

    public function test_angemeldet_mit_konto_verknuepft_und_erkannt(): void
    {
        $user = $this->userWithRoles([Role::USER], 'lea@schule.example', false);
        $this->actingAs($user);
        $this->getJson('/erweiterung/ich', ['Origin' => self::EXT])->assertOk()->assertJsonPath('angemeldet', true);
        $this->melden(['wort' => 'VSA'])->assertStatus(202)->assertJsonPath('angemeldet', true);
        $this->assertSame($user->id, TermCandidate::query()->sole()->reported_by_id);
    }

    public function test_cors_nur_erweiterungen_und_eingetragene_webseiten(): void
    {
        $this->melden(['wort' => 'XY'], 'https://boese.example')->assertForbidden();
        $this->call('OPTIONS', '/erweiterung/kandidaten', [], [], [], ['HTTP_ORIGIN' => self::EXT, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST'])
            ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', self::EXT);
        $this->assertSame(0, TermCandidate::query()->count());

        // eingetragene Webseite mit Einbindung: erlaubt, aber nie mit Sitzung
        Settings::set($this->admin(), ['candidates.origins' => 'https://www.schule.ch']);
        $this->actingAs($this->userWithRoles([Role::USER], 'x@schule.example', false));
        $response = $this->melden(['wort' => 'LP21'], 'https://www.schule.ch')->assertStatus(202)
            ->assertHeader('Access-Control-Allow-Origin', 'https://www.schule.ch');
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
        $c = TermCandidate::query()->sole();
        $this->assertSame(['embed', null], [$c->channel, $c->reported_by_id]);
    }

    public function test_nur_json(): void
    {
        $this->post('/erweiterung/kandidaten', ['wort' => 'XY'], ['Origin' => self::EXT])->assertStatus(415);
    }

    public function test_killswitch_sperrt_nur_anonymes_melden(): void
    {
        Settings::set($this->admin(), ['candidates.anonymous' => false]);
        $this->app['auth']->forgetGuards();
        $this->melden(['wort' => 'XY'])->assertForbidden()->assertJsonPath('detail', 'Zurzeit nur angemeldet möglich.');
        $this->getJson('/erweiterung/ich', ['Origin' => self::EXT])->assertJsonPath('anonym', false);

        $this->actingAs($this->userWithRoles([Role::USER], 'lea@schule.example', false));
        $this->melden(['wort' => 'XY'])->assertStatus(202);
    }

    public function test_abruflimit_anonym(): void
    {
        RateLimiter::clear('kandidat:all');
        for ($i = 1; $i <= 5; $i++) {
            $this->melden(['wort' => "W{$i}"])->assertStatus(202);
        }
        $this->melden(['wort' => 'W6'])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(5, TermCandidate::query()->count());
    }

    public function test_erklaerung_falsch_zu_bekanntem_begriff(): void
    {
        $glossary = app(GlossaryService::class);
        $term = $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true), null);
        $this->melden(['wort' => 'SL', 'art' => 'falsch', 'code' => $term->code, 'kommentar' => 'Veraltet'])->assertStatus(202);
        $c = TermCandidate::query()->sole();
        $this->assertSame(['wrong', $term->id], [$c->kind, $c->term_id]);
        $this->melden(['wort' => 'SL', 'art' => 'falsch', 'code' => 'ZZZZZZ'])->assertStatus(422);
    }

    public function test_sperrliste_und_verworfenes_kommt_nicht_wieder(): void
    {
        $admin = $this->admin();
        Settings::set($admin, ['candidates.blocklist' => 'spam, werbung']);
        $this->app['auth']->forgetGuards();
        $this->melden(['wort' => 'Gratis Werbung'])->assertStatus(202);
        $this->assertSame(0, TermCandidate::query()->count());

        $this->melden(['wort' => 'ABC'])->assertStatus(202);
        $c = TermCandidate::query()->sole();
        $this->actingAs($admin)->post("/admin/kandidaten/{$c->id}/entscheid", ['entscheid' => 'rejected'])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->melden(['wort' => 'abc'])->assertStatus(202);
        $c->refresh();
        $this->assertSame(['rejected', 2], [$c->status, $c->count]);
        $this->assertSame(1, TermCandidate::query()->count());
    }

    public function test_sichtung_und_als_entwurf_anlegen(): void
    {
        $this->melden(['wort' => 'SSA'])->assertStatus(202);
        $c = TermCandidate::query()->sole();
        $this->actingAs($this->admin());
        $this->get('/admin/kandidaten')->assertInertia(fn (Assert $p) => $p->component('admin/candidates')->where('kandidaten.0.wort', 'SSA'));
        $this->get("/admin/begriffe/neu?official_abbreviation=SSA&kandidat={$c->id}")->assertInertia(fn (Assert $p) => $p->where('werte.kandidat', $c->id));
        $this->post('/admin/begriffe', ['official_abbreviation' => 'SSA', 'official_name' => 'Schulsozialarbeit', 'status' => 'current', 'kandidat' => $c->id])->assertRedirect();
        $c->refresh();
        $this->assertSame('accepted', $c->status);
        $this->assertNotNull($c->result_term_id);
        // nichts wird ohne Moderation sichtbar
        $this->getJson('/api/v1/index')->assertJsonCount(0, 'terms');
    }

    public function test_sichtung_nur_mit_recht(): void
    {
        $this->actingAs($this->userWithRoles([Role::USER], 'u@schule.example'))->get('/admin/kandidaten')->assertForbidden();
    }
}
