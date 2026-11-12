<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rollen-Schlüssel englisch (#288, KONZEPT-KONVENTIONEN): gast → guest, benutzer → user.
 * Zuordnungen und Rechte hängen an der ID und bleiben erhalten; Anzeigenamen bleiben deutsch.
 */
return new class extends Migration
{
    private const KEYS = ['gast' => 'guest', 'benutzer' => 'user'];

    public function up(): void
    {
        foreach (self::KEYS as $old => $new) {
            DB::table('roles')->where('key', $old)->update(['key' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::KEYS as $old => $new) {
            DB::table('roles')->where('key', $new)->update(['key' => $old]);
        }
    }
};
