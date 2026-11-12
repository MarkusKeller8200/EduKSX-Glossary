<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UserAudit;
use App\Support\DatabaseDump;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

/** Vollständiger Dump der Datenbank (#268). */
class DumpTest extends TestCase
{
    public function test_nur_mit_recht_system(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/system/dump')->assertForbidden();
    }

    public function test_dump_ohne_passwort_mit_tabellen_und_manifest(): void
    {
        $user = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');
        $this->actingAs($user);

        $response = $this->post('/admin/system/dump');
        $response->assertOk();
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);
        $path = $file->getFile()->getPathname();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertFalse($manifest['encrypted']);
        $this->assertSame(DB::table('users')->count(), $manifest['tables']['users']);
        $this->assertContains('sessions', $manifest['skipped']);
        $users = array_filter(explode("\n", (string) $zip->getFromName('tabellen/users.jsonl')));
        $this->assertCount($manifest['tables']['users'], $users);
        $this->assertSame(hash('sha256', (string) $zip->getFromName('tabellen/users.jsonl')), $manifest['files']['tabellen/users.jsonl']);
        $zip->close();
        @unlink($path);

        $this->assertTrue(UserAudit::query()->where('action', 'system.dump')->where('result', 'ok')->exists());
    }

    public function test_dump_mit_passwort_ist_verschluesselt(): void
    {
        if (! defined(ZipArchive::class.'::EM_AES_256')) {
            $this->markTestSkipped('libzip ohne AES');
        }
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));

        $response = $this->post('/admin/system/dump', ['passwort' => 'ein-langes-passwort', 'passwort_confirmation' => 'ein-langes-passwort']);
        $response->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive;
        $zip->open($path);
        $stat = $zip->statName('manifest.json');
        $this->assertSame(ZipArchive::EM_AES_256, $stat['encryption_method']);
        $zip->setPassword('ein-langes-passwort');
        $this->assertTrue(json_decode((string) $zip->getFromName('manifest.json'), true)['encrypted']);
        $zip->close();
        @unlink($path);
    }

    public function test_passwort_vorgeschrieben(): void
    {
        config(['glossary.dump_require_password' => true]);
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));

        $this->postJson('/admin/system/dump')->assertUnprocessable()->assertJsonValidationErrors('passwort');
    }

    public function test_sqlite_datei_als_exakte_kopie(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'glossary-quelle').'.sqlite';
        touch($source);
        config(['database.connections.dumptest' => ['driver' => 'sqlite', 'database' => $source, 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::connection('dumptest')->statement('create table beispiel (id integer primary key, text varchar)');
        DB::connection('dumptest')->table('beispiel')->insert([['text' => 'Ä'], ['text' => 'b']]);

        $target = tempnam(sys_get_temp_dir(), 'glossary-dump').'.zip';
        $manifest = DatabaseDump::create($target, null, 'dumptest');

        $this->assertTrue($manifest['sqlite_file']);
        $this->assertSame(['beispiel' => 2], $manifest['tables']);
        $zip = new ZipArchive;
        $zip->open($target);
        $copy = tempnam(sys_get_temp_dir(), 'glossary-kopie');
        file_put_contents($copy, (string) $zip->getFromName('datenbank/glossary.sqlite'));
        $zip->close();
        $pdo = new \PDO('sqlite:'.$copy);
        $this->assertSame(2, (int) $pdo->query('select count(*) from beispiel')->fetchColumn());

        DB::purge('dumptest');
        @unlink($source);
        @unlink($target);
        @unlink($copy);
    }
}
