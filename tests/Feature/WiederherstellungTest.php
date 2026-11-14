<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\User;
use App\Support\DatabaseDump;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

/** Vollständige Sicherung wiederherstellen, z. B. Daten von dev nach prod übernehmen (#353). SQLite und PostgreSQL. */
final class WiederherstellungTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        foreach (glob(storage_path('app/backups/vor-wiederherstellung-*.zip')) ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function dump(bool $anonymize = false, ?string $password = null): string
    {
        $file = storage_path('framework/testing/wiederherstellen-'.count($this->files).'.zip');
        DatabaseDump::create($file, $password, null, $anonymize);
        $this->files[] = $file;

        return $file;
    }

    private function daten(): User
    {
        $lea = $this->userWithRoles([Role::USER], 'lea.muster@schule.example');
        $lea->forceFill(['two_factor_secret' => 'GEHEIMNIS123', 'two_factor_recovery_codes' => ['a-1', 'b-2']])->save();
        $glossary = app(GlossaryService::class);
        $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true), null);

        return $lea;
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
    }

    /** anderen APP_KEY setzen, wie auf einer anderen Instanz */
    private function otherKey(): string
    {
        $key = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));
        config(['app.key' => $key]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');

        return $key;
    }

    public function test_nur_in_production(): void
    {
        $this->daten();
        $this->artisan('glossary:wiederherstellen', ['datei' => $this->dump(), '--ja' => true])
            ->expectsOutputToContain('nur in production')->assertFailed();
    }

    public function test_ersetzt_alle_daten_mit_gleicher_zaehlung_und_sicherung_vorher(): void
    {
        $lea = $this->daten();
        $file = $this->dump();
        $counts = ['users' => DB::table('users')->count(), 'terms' => DB::table('terms')->count()];

        // Stand danach verändern: neues Konto, Lea gelöscht, Sitzung offen
        $this->userWithRoles([Role::USER], 'neu@schule.example');
        DB::table('users')->where('id', $lea->id)->update(['email' => 'geaendert@schule.example']);
        DB::table('sessions')->insert(['id' => 'sitzung-1', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => 'x', 'last_activity' => time()]);

        $this->production();
        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--ja' => true])
            ->expectsOutputToContain('Wiederhergestellt')->assertSuccessful();

        $this->assertSame($counts['users'], DB::table('users')->count());
        $this->assertSame($counts['terms'], DB::table('terms')->count());
        $this->assertSame(1, DB::table('users')->where('email', 'lea.muster@schule.example')->count());
        $this->assertSame(0, DB::table('users')->where('email', 'neu@schule.example')->count());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertSame('GEHEIMNIS123', User::query()->findOrFail($lea->id)->two_factor_secret);
        $this->assertSame(1, DB::table('user_audit')->where('action', 'system.restore')->count());
        $this->assertCount(1, glob(storage_path('app/backups/vor-wiederherstellung-*.zip')) ?: []);
        $this->assertFalse(app()->isDownForMaintenance());

        // Neue Datensätze nach der Wiederherstellung (Sequenzen auf PostgreSQL nachgeführt)
        $this->userWithRoles([Role::USER], 'danach@schule.example');
        $this->assertSame($counts['users'] + 1, DB::table('users')->count());
    }

    public function test_anderer_app_key_nur_mit_schluessel_der_quelle(): void
    {
        $lea = $this->daten();
        $sourceKey = (string) config('app.key');
        $file = $this->dump();
        $this->otherKey();
        $this->production();

        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--ja' => true, '--no-interaction' => true])
            ->expectsOutputToContain('anderen APP_KEY')->assertFailed();
        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--ja' => true, '--schluessel-quelle' => 'base64:'.base64_encode(random_bytes(32))])
            ->expectsOutputToContain('passt nicht')->assertFailed();

        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--ja' => true, '--schluessel-quelle' => $sourceKey])
            ->assertSuccessful();
        $restored = User::query()->findOrFail($lea->id);
        $this->assertSame('GEHEIMNIS123', $restored->two_factor_secret);   // mit dem neuen Schlüssel lesbar
        $this->assertSame(['a-1', 'b-2'], $restored->two_factor_recovery_codes);
    }

    public function test_probelauf_aendert_nichts(): void
    {
        $this->daten();
        $file = $this->dump();
        $this->userWithRoles([Role::USER], 'neu@schule.example');
        $users = DB::table('users')->count();
        $this->production();

        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--trocken' => true])
            ->expectsOutputToContain('Prüfung bestanden')->assertSuccessful();
        $this->assertSame($users, DB::table('users')->count());
        $this->assertSame([], glob(storage_path('app/backups/vor-wiederherstellung-*.zip')) ?: []);
    }

    public function test_mit_passwort(): void
    {
        $this->daten();
        $file = $this->dump(password: 'sehr-geheim');
        $this->production();

        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--ja' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Passwort')->assertFailed();
        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--ja' => true, '--passwort' => 'sehr-geheim'])
            ->assertSuccessful();
    }

    public function test_anonymisiert_und_veraendert_abgelehnt(): void
    {
        $this->daten();
        $this->production();
        $this->artisan('glossary:wiederherstellen', ['datei' => $this->dump(true), '--ja' => true])
            ->expectsOutputToContain('anonymisiert')->assertFailed();

        $file = $this->dump();
        $zip = new ZipArchive;
        $zip->open($file);
        $zip->addFromString('tabellen/terms.jsonl', '{"id":1}'."\n");
        $zip->close();
        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--ja' => true])
            ->expectsOutputToContain('Prüfsumme')->assertFailed();
    }

    public function test_andere_migrationen_abgelehnt(): void
    {
        $this->daten();
        $file = $this->dump();
        DB::table('migrations')->insert(['id' => (int) DB::table('migrations')->max('id') + 1, 'migration' => '2099_01_01_000000_spaeter', 'batch' => 99]);
        $users = DB::table('users')->count();
        $this->production();

        $this->artisan('glossary:wiederherstellen', ['datei' => $file, '--ja' => true])
            ->expectsOutputToContain('Migrationen passen nicht')->assertFailed();
        $this->assertSame($users, DB::table('users')->count());
    }
}
