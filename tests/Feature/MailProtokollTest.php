<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use App\Models\Role;
use App\Support\MailLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Mail-Protokoll (#463): ohne Inhalt, Adresse maskiert, Warteschlange, Löschfrist. */
final class MailProtokollTest extends TestCase
{
    public function test_versendete_mail_maskiert_ohne_inhalt(): void
    {
        config(['mail.default' => 'array']);
        Mail::to('Lea.Muster@Schule.example')->send(new TestMail);

        $row = DB::table('mail_log')->first();
        $this->assertNotNull($row);
        $this->assertSame('TestMail', $row->kind);
        $this->assertSame('l***@schule.example', $row->recipient);
        $this->assertSame('sent', $row->status);
        $this->assertSame(['id', 'created_at', 'kind', 'recipient', 'status', 'mailer', 'error'], array_keys((array) $row));

        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
        $this->get('/admin/system')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('mails.versendet.0.art', 'Testmail')
            ->where('mails.versendet.0.an', 'l***@schule.example'));
    }

    public function test_fehlgeschlagene_mail_ohne_meldung_wiederholen_und_verwerfen(): void
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Mail\\TestMail', 'data' => []]),
            'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Verbindung zu lea@schule.example abgelehnt\n#0 ...",
            'failed_at' => now()]);
        $andere = (string) Str::uuid();
        DB::table('failed_jobs')->insert(['uuid' => $andere, 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\Irgendwas']), 'exception' => 'X', 'failed_at' => now()]);

        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
        $this->get('/admin/system')->assertInertia(fn (Assert $p) => $p
            ->has('mails.fehlgeschlagen', 1)
            ->where('mails.fehlgeschlagen.0.fehler', 'TransportException'));
        $this->assertStringNotContainsString('lea@schule.example', (string) $this->get('/admin/system')->getContent());

        $this->delete("/admin/system/mails/{$andere}")->assertNotFound();   // nur Mails
        $this->delete("/admin/system/mails/{$uuid}")->assertRedirect();
        $this->assertFalse(DB::table('failed_jobs')->where('uuid', $uuid)->exists());
    }

    public function test_loeschfrist_90_tage(): void
    {
        DB::table('mail_log')->insert(['created_at' => now()->subDays(91), 'kind' => 'TestMail', 'recipient' => 'a***@b.ch', 'status' => 'sent', 'mailer' => 'smtp']);
        DB::table('mail_log')->insert(['created_at' => now()->subDays(10), 'kind' => 'TestMail', 'recipient' => 'a***@b.ch', 'status' => 'sent', 'mailer' => 'smtp']);
        $this->assertSame(1, MailLog::prune());
        $this->assertSame(1, DB::table('mail_log')->count());
    }

    public function test_ohne_recht_nicht_erreichbar(): void
    {
        $this->actingAs($this->userWithRoles([Role::ADMIN], 'admin2@example.org'));
        $this->delete('/admin/system/mails/'.Str::uuid())->assertForbidden();
    }
}
