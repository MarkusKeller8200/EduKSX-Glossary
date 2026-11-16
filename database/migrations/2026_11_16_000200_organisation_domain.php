<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konten mit der Domain der Organisation automatisch aufnehmen (#462):
 * - organizations.email_domain (eine je Organisation, eindeutig) und organizations.is_domain_auto_join
 * - organization_members.joined_via (wie beigetreten) und notice_shown_at (Hinweis an die Person nach der Anmeldung)
 *
 * abwaertskompatibel: nur neue Spalten mit Vorgabe bzw. nullable; die Vorversion läuft unverändert weiter (U18).
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwaertskompatibel: neue Spalten, nichts umbenannt oder entfernt
        StrictSchema::addColumns('organizations', function (Blueprint $t) {
            $t->string('email_domain', 190)->nullable()->unique();
            $t->boolean('is_domain_auto_join')->default(false);
        });
        StrictSchema::addColumns('organization_members', function (Blueprint $t) {
            $t->string('joined_via', 20)->nullable();
            $t->timestamp('notice_shown_at')->nullable();
        }, [
            'joined_via' => ['organization_members_joined_via_check', "\"joined_via\" is null or \"joined_via\" in ('invitation', 'registration', 'domain', 'founded', 'admin')"],
        ]);
    }

    public function down(): void
    {
        Schema::table('organization_members', fn (Blueprint $t) => $t->dropColumn(['joined_via', 'notice_shown_at']));
        Schema::table('organizations', function (Blueprint $t) {
            $t->dropUnique(['email_domain']);
            $t->dropColumn(['email_domain', 'is_domain_auto_join']);
        });
    }
};
