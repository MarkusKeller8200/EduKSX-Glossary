<?php

use App\Glossary\Permalink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permalink je Begriff (DEC-026, #154): Code mit 6 Zeichen, eindeutig und unveränderlich. Vorhandene Begriffe
 * (auch zusammengeführte, damit alte Links weiterleiten) erhalten jetzt einen Code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->string('code', 6)->nullable()->unique();
        });

        $used = [];
        foreach (DB::table('terms')->whereNull('code')->orderBy('created_at')->pluck('id') as $id) {
            do {
                $code = '';
                for ($i = 0; $i < Permalink::LENGTH; $i++) {
                    $code .= Permalink::ALPHABET[random_int(0, strlen(Permalink::ALPHABET) - 1)];
                }
            } while (isset($used[$code]));
            $used[$code] = true;
            DB::table('terms')->where('id', $id)->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
